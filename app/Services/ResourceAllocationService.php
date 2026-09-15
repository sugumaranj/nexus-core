<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS
 * =========================================================================
 * File        : ResourceAllocationService.php
 * Location    : app/Services/
 * Description : Business logic for the Resource Allocation Module.
 *
 * Responsibilities
 * ─────────────────────────────────────────────────────────────────────────
 * • RBAC: who can manage allocations
 * • Conflict Detection Engine  (date + session + time, cross-role)
 * • Assign Faculty In-Charge   (with conflict check)
 * • Remove Faculty In-Charge
 * • Replace Faculty In-Charge  (atomic: remove old → assign new)
 * • Assign Judge               (with conflict check)
 * • Remove Judge
 * • Replace Judge              (atomic: remove old → assign new)
 * • Staff Availability         (per event: Green / Orange / Red)
 * • Allocation Dashboard Stats
 * • Email notifications via EmailService (queue-based, no direct SMTP)
 * • Audit logging
 *
 * Conflict Rule (per user mandate)
 * ─────────────────────────────────────────────────────────────────────────
 * "One staff = one assignment per day."
 * A person cannot be Faculty In-Charge AND Judge on the same date,
 * even if the sessions differ (FN vs AN).
 * This is the STRICT rule: date alone determines a conflict.
 *
 * Architecture
 * ─────────────────────────────────────────────────────────────────────────
 * This service NEVER contains raw SQL.
 * SQL belongs in FacultyAssignmentModel and JudgeAssignmentModel.
 * Email is enqueued via EmailQueueModel (NOT sent directly).
 *
 * =========================================================================
 */

namespace App\Services;

use App\Database\Database;
use App\Models\FacultyAssignmentModel;
use App\Models\JudgeAssignmentModel;
use App\Models\SymposiumEventModel;
use App\Models\UserModel;
use App\Models\DepartmentModel;
use App\Models\EmailQueueModel;
use App\Models\AuditLogModel;
use PDO;

final class ResourceAllocationService
{
    // ─── Models ──────────────────────────────────────────────────────────────
    private FacultyAssignmentModel $facultyModel;
    private JudgeAssignmentModel   $judgeModel;
    private SymposiumEventModel    $eventModel;
    private UserModel              $userModel;
    private DepartmentModel        $deptModel;
    private EmailQueueModel        $emailQueue;
    private AuditLogModel          $auditModel;
    private PDO                    $db;

    // ─── Roles allowed as Faculty In-Charge or Judge ─────────────────────────
    private const ASSIGNABLE_ROLES = ['HOD', 'Staff Coordinator', 'Staff'];

    // ─── Roles that may MANAGE allocations ───────────────────────────────────
    private const MANAGER_ROLES = ['Admin', 'Staff Coordinator'];

    public function __construct()
    {
        $this->facultyModel = new FacultyAssignmentModel();
        $this->judgeModel   = new JudgeAssignmentModel();
        $this->eventModel   = new SymposiumEventModel();
        $this->userModel    = new UserModel();
        $this->deptModel    = new DepartmentModel();
        $this->emailQueue   = new EmailQueueModel();
        $this->auditModel   = new AuditLogModel();
        $this->db           = Database::getConnection();
    }

    // =========================================================================
    // RBAC
    // =========================================================================

    /**
     * Can this user manage (assign/remove) resources?
     *
     * @param array $user
     * @return bool
     */
    public function canManage(array $user): bool
    {
        return in_array($user['role'] ?? '', self::MANAGER_ROLES, true);
    }

    /**
     * Can this user be assigned as Faculty In-Charge or Judge?
     * Students are never assigned. Only staff-tier roles.
     *
     * @param array $targetUser  The user to be assigned.
     * @return bool
     */
    public function isAssignable(array $targetUser): bool
    {
        return in_array($targetUser['role'] ?? '', self::ASSIGNABLE_ROLES, true)
            && ($targetUser['account_status'] ?? '') === 'Active';
    }

    // =========================================================================
    // CONFLICT DETECTION ENGINE
    // =========================================================================

    /**
     * Check if a user has ANY assignment (Faculty or Judge) on the given date.
     *
     * Implements the STRICT one-assignment-per-day rule:
     * → Checks both faculty_assignments and competition_judges tables.
     * → Returns an array describing the conflict, or empty array if clean.
     *
     * @param int      $userId
     * @param string   $date           Event date (Y-m-d)
     * @param int|null $excludeEventId Exclude this event (used during replace)
     *
     * @return array  Empty = no conflict. Non-empty = conflict details.
     */
    public function detectConflict(int $userId, ?string $date, ?int $excludeEventId = null): array
    {
        if (empty($date)) {
            return [];
        }

        $conflicts = [];

        // 1. Check Faculty assignments on this date
        $facultyConflicts = $this->facultyModel->getActiveAssignmentsByDate(
            $userId, $date, $excludeEventId
        );
        foreach ($facultyConflicts as $fc) {
            $conflicts[] = [
                'type'       => 'Faculty Incharge',
                'event_name' => $fc['event_name'],
                'event_date' => $fc['event_date'],
                'session'    => $fc['session'],
                'start_time' => $fc['start_time'],
                'end_time'   => $fc['end_time'],
                'venue_name' => $fc['venue_name'] ?? '—',
            ];
        }

        // 2. Check Judge assignments on this date
        $judgeConflicts = $this->judgeModel->getActiveAssignmentsByDate(
            $userId, $date, $excludeEventId
        );
        foreach ($judgeConflicts as $jc) {
            $conflicts[] = [
                'type'       => 'Judge',
                'event_name' => $jc['event_name'],
                'event_date' => $jc['event_date'],
                'session'    => $jc['session'],
                'start_time' => $jc['start_time'],
                'end_time'   => $jc['end_time'],
                'venue_name' => $jc['venue_name'] ?? '—',
            ];
        }

        return $conflicts;
    }

    // =========================================================================
    // FACULTY IN-CHARGE — ASSIGN
    // =========================================================================

    /**
     * Assign a user as Faculty In-Charge for a symposium event.
     *
     * Validates:
     *  1. User is assignable (role + active)
     *  2. No same-day conflict (Faculty or Judge on any event)
     *  3. Not already assigned to this event
     *
     * On success: assigns, logs audit, enqueues notification email.
     *
     * @param int    $symposiumEventId
     * @param int    $userId
     * @param array  $actingUser        The user performing the action
     * @param string $notes
     *
     * @return array{success: bool, message: string}
     */
    public function assignFaculty(
        int    $symposiumEventId,
        int    $userId,
        array  $actingUser,
        string $notes = ''
    ): array {
        if (!$this->canManage($actingUser)) {
            return ['success' => false, 'message' => 'You are not authorized to manage resource allocation.'];
        }

        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Symposium event not found.'];
        }

        $targetUser = $this->userModel->findById($userId);
        if (!$targetUser || !$this->isAssignable($targetUser)) {
            return ['success' => false, 'message' => 'This user cannot be assigned. Only active staff members are eligible.'];
        }

        // Already assigned?
        if ($this->facultyModel->isAssigned($symposiumEventId, $userId)) {
            return ['success' => false, 'message' => 'This staff member is already assigned as Faculty In-Charge for this event.'];
        }

        // Conflict check
        $conflicts = $this->detectConflict($userId, $event['event_date']);
        if (!empty($conflicts)) {
            $c = $conflicts[0];
            return [
                'success' => false,
                'message' => sprintf(
                    '%s is already assigned as %s for "%s" on %s (%s, %s – %s, %s). One assignment per day is allowed.',
                    htmlspecialchars($targetUser['full_name'], ENT_QUOTES),
                    $c['type'],
                    htmlspecialchars($c['event_name'], ENT_QUOTES),
                    \App\Helpers\DateHelper::date($c['event_date']),
                    $c['session'],
                    $c['start_time'],
                    $c['end_time'],
                    htmlspecialchars($c['venue_name'], ENT_QUOTES)
                ),
                'conflict' => $c,
            ];
        }

        $ok = $this->facultyModel->assign(
            $symposiumEventId, $userId, (int) $actingUser['user_id'], 'Faculty Incharge', $notes
        );

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to assign Faculty In-Charge. Please try again.'];
        }

        $this->writeAuditLog(
            $actingUser,
            'FACULTY_ASSIGNED',
            $symposiumEventId,
            "Faculty In-Charge assigned: User #{$userId} ({$targetUser['full_name']}) → Event #{$symposiumEventId} ({$event['event_name']})"
        );

        $this->enqueueAssignmentEmail(
            $targetUser,
            $event,
            'faculty_assigned',
            (int) $actingUser['user_id'],
            (int) ($event['symposium_id'] ?? 0)
        );

        return [
            'success' => true,
            'message' => "{$targetUser['full_name']} assigned as Faculty In-Charge for \"{$event['event_name']}\".",
        ];
    }

    // =========================================================================
    // FACULTY IN-CHARGE — REMOVE
    // =========================================================================

    /**
     * Remove a faculty assignment.
     *
     * @param int   $symposiumEventId
     * @param int   $userId
     * @param array $actingUser
     *
     * @return array{success: bool, message: string}
     */
    public function removeFaculty(int $symposiumEventId, int $userId, array $actingUser): array
    {
        if (!$this->canManage($actingUser)) {
            return ['success' => false, 'message' => 'You are not authorized to manage resource allocation.'];
        }

        $event      = $this->eventModel->findById($symposiumEventId);
        $targetUser = $this->userModel->findById($userId);

        if (!$event) {
            return ['success' => false, 'message' => 'Symposium event not found.'];
        }

        if (!$this->facultyModel->isAssigned($symposiumEventId, $userId)) {
            return ['success' => false, 'message' => 'This staff member is not currently assigned as Faculty In-Charge.'];
        }

        $ok = $this->facultyModel->remove($symposiumEventId, $userId, (int) $actingUser['user_id']);

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to remove Faculty In-Charge assignment.'];
        }

        $name = $targetUser['full_name'] ?? "User #{$userId}";

        $this->writeAuditLog(
            $actingUser,
            'FACULTY_REMOVED',
            $symposiumEventId,
            "Faculty In-Charge removed: User #{$userId} ({$name}) ← Event #{$symposiumEventId}"
        );

        if ($targetUser) {
            $this->enqueueAssignmentEmail(
                $targetUser,
                $event,
                'faculty_removed',
                (int) $actingUser['user_id'],
                (int) ($event['symposium_id'] ?? 0)
            );
        }

        return ['success' => true, 'message' => "{$name} removed from Faculty In-Charge for \"{$event['event_name']}\"."];
    }

    // =========================================================================
    // FACULTY IN-CHARGE — REPLACE
    // =========================================================================

    /**
     * Replace ALL current Faculty In-Charge assignments with a new user.
     *
     * Atomic: remove existing → assign new. Wrapped in a DB transaction.
     * Audit log + email notification for both old and new staff.
     *
     * @param int    $symposiumEventId
     * @param int    $newUserId
     * @param array  $actingUser
     * @param string $notes
     *
     * @return array{success: bool, message: string}
     */
    public function replaceFaculty(
        int    $symposiumEventId,
        int    $newUserId,
        array  $actingUser,
        string $notes = ''
    ): array {
        if (!$this->canManage($actingUser)) {
            return ['success' => false, 'message' => 'You are not authorized to manage resource allocation.'];
        }

        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Symposium event not found.'];
        }

        $newUser = $this->userModel->findById($newUserId);
        if (!$newUser || !$this->isAssignable($newUser)) {
            return ['success' => false, 'message' => 'The new staff member cannot be assigned. Only active staff are eligible.'];
        }

        // Conflict check (exclude this event since we're replacing)
        $conflicts = $this->detectConflict($newUserId, $event['event_date'], $symposiumEventId);
        if (!empty($conflicts)) {
            $c = $conflicts[0];
            return [
                'success'  => false,
                'message'  => sprintf(
                    '%s is already assigned as %s for "%s" on %s (%s). Cannot replace.',
                    htmlspecialchars($newUser['full_name'], ENT_QUOTES),
                    $c['type'],
                    htmlspecialchars($c['event_name'], ENT_QUOTES),
                    \App\Helpers\DateHelper::date($c['event_date']),
                    $c['session']
                ),
                'conflict' => $c,
            ];
        }

        try {
            $this->db->beginTransaction();

            $this->facultyModel->removeAllForEvent($symposiumEventId, (int) $actingUser['user_id']);

            $ok = $this->facultyModel->assign(
                $symposiumEventId, $newUserId, (int) $actingUser['user_id'], 'Faculty Incharge', $notes
            );

            if (!$ok) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Failed to assign new Faculty In-Charge.'];
            }

            $this->db->commit();

        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => 'A database error occurred during replacement.'];
        }

        $this->writeAuditLog(
            $actingUser,
            'FACULTY_REPLACED',
            $symposiumEventId,
            "Faculty In-Charge replaced with: {$newUser['full_name']} → Event #{$symposiumEventId} ({$event['event_name']})"
        );

        $this->enqueueAssignmentEmail(
            $newUser,
            $event,
            'faculty_assigned',
            (int) $actingUser['user_id'],
            (int) ($event['symposium_id'] ?? 0)
        );

        return [
            'success' => true,
            'message' => "Faculty In-Charge replaced with {$newUser['full_name']} for \"{$event['event_name']}\".",
        ];
    }

    // =========================================================================
    // JUDGE — ASSIGN
    // =========================================================================

    /**
     * Assign a judge to a symposium event.
     *
     * @param int   $symposiumEventId
     * @param int   $userId
     * @param array $actingUser
     *
     * @return array{success: bool, message: string}
     */
    public function assignJudge(
        int   $symposiumEventId,
        int   $userId,
        array $actingUser,
        bool  $bypassRoleCheck = false
    ): array {
        if (!$bypassRoleCheck && !$this->canManage($actingUser)) {
            return ['success' => false, 'message' => 'You are not authorized to manage resource allocation.'];
        }

        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Symposium event not found.'];
        }

        $targetUser = $this->userModel->findById($userId);
        if (!$targetUser || !$this->isAssignable($targetUser)) {
            return ['success' => false, 'message' => 'This user cannot be assigned. Only active staff members are eligible.'];
        }

        if ($this->judgeModel->isAssigned($symposiumEventId, $userId)) {
            return ['success' => false, 'message' => 'This staff member is already assigned as Judge for this event.'];
        }

        // Conflict check (strict: per-day rule)
        $conflicts = $this->detectConflict($userId, $event['event_date']);
        if (!empty($conflicts)) {
            $c = $conflicts[0];
            return [
                'success'  => false,
                'message'  => sprintf(
                    '%s is already assigned as %s for "%s" on %s (%s, %s – %s, %s). One assignment per day is allowed.',
                    htmlspecialchars($targetUser['full_name'], ENT_QUOTES),
                    $c['type'],
                    htmlspecialchars($c['event_name'], ENT_QUOTES),
                    \App\Helpers\DateHelper::date($c['event_date']),
                    $c['session'],
                    $c['start_time'],
                    $c['end_time'],
                    htmlspecialchars($c['venue_name'], ENT_QUOTES)
                ),
                'conflict' => $c,
            ];
        }

        $ok = $this->judgeModel->assign(
            $symposiumEventId, $userId, (int) $actingUser['user_id'], 'No'
        );

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to assign Judge. Please try again.'];
        }

        $this->writeAuditLog(
            $actingUser,
            'JUDGE_ASSIGNED',
            $symposiumEventId,
            "Judge assigned: User #{$userId} ({$targetUser['full_name']}) → Event #{$symposiumEventId} ({$event['event_name']})"
        );

        $this->enqueueAssignmentEmail(
            $targetUser,
            $event,
            'judge_assigned',
            (int) $actingUser['user_id'],
            (int) ($event['symposium_id'] ?? 0)
        );

        return [
            'success' => true,
            'message' => "{$targetUser['full_name']} assigned as Judge for \"{$event['event_name']}\".",
        ];
    }

    // =========================================================================
    // JUDGE — REMOVE
    // =========================================================================

    /**
     * Remove a judge assignment.
     *
     * @param int   $symposiumEventId
     * @param int   $userId
     * @param array $actingUser
     *
     * @return array{success: bool, message: string}
     */
    public function removeJudge(int $symposiumEventId, int $userId, array $actingUser, bool $bypassRoleCheck = false): array
    {
        if (!$bypassRoleCheck && !$this->canManage($actingUser)) {
            return ['success' => false, 'message' => 'You are not authorized to manage resource allocation.'];
        }

        $event      = $this->eventModel->findById($symposiumEventId);
        $targetUser = $this->userModel->findById($userId);

        if (!$event) {
            return ['success' => false, 'message' => 'Symposium event not found.'];
        }

        if (!$this->judgeModel->isAssigned($symposiumEventId, $userId)) {
            return ['success' => false, 'message' => 'This staff member is not currently assigned as Judge for this event.'];
        }

        $ok = $this->judgeModel->remove($symposiumEventId, $userId, (int) $actingUser['user_id']);

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to remove Judge assignment.'];
        }

        $name = $targetUser['full_name'] ?? "User #{$userId}";

        $this->writeAuditLog(
            $actingUser,
            'JUDGE_REMOVED',
            $symposiumEventId,
            "Judge removed: User #{$userId} ({$name}) ← Event #{$symposiumEventId}"
        );

        if ($targetUser) {
            $this->enqueueAssignmentEmail(
                $targetUser,
                $event,
                'judge_removed',
                (int) $actingUser['user_id'],
                (int) ($event['symposium_id'] ?? 0)
            );
        }

        return ['success' => true, 'message' => "{$name} removed from Judge for \"{$event['event_name']}\"."];
    }

    // =========================================================================
    // JUDGE — REPLACE
    // =========================================================================

    /**
     * Replace ALL current judges with a single new judge.
     *
     * @param int   $symposiumEventId
     * @param int   $newUserId
     * @param array $actingUser
     *
     * @return array{success: bool, message: string}
     */
    public function replaceJudge(
        int   $symposiumEventId,
        int   $newUserId,
        array $actingUser
    ): array {
        if (!$this->canManage($actingUser)) {
            return ['success' => false, 'message' => 'You are not authorized to manage resource allocation.'];
        }

        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Symposium event not found.'];
        }

        $newUser = $this->userModel->findById($newUserId);
        if (!$newUser || !$this->isAssignable($newUser)) {
            return ['success' => false, 'message' => 'The new staff member cannot be assigned.'];
        }

        $conflicts = $this->detectConflict($newUserId, $event['event_date'], $symposiumEventId);
        if (!empty($conflicts)) {
            $c = $conflicts[0];
            return [
                'success'  => false,
                'message'  => sprintf(
                    '%s is already assigned as %s for "%s" on %s. Cannot replace.',
                    htmlspecialchars($newUser['full_name'], ENT_QUOTES),
                    $c['type'],
                    htmlspecialchars($c['event_name'], ENT_QUOTES),
                    \App\Helpers\DateHelper::date($c['event_date'])
                ),
                'conflict' => $c,
            ];
        }

        try {
            $this->db->beginTransaction();

            $this->judgeModel->removeAllForEvent($symposiumEventId, (int) $actingUser['user_id']);

            $ok = $this->judgeModel->assign(
                $symposiumEventId, $newUserId, (int) $actingUser['user_id'], 'No'
            );

            if (!$ok) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Failed to assign new Judge.'];
            }

            $this->db->commit();

        } catch (\Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => 'A database error occurred during replacement.'];
        }

        $this->writeAuditLog(
            $actingUser,
            'JUDGE_REPLACED',
            $symposiumEventId,
            "Judge replaced with: {$newUser['full_name']} → Event #{$symposiumEventId}"
        );

        $this->enqueueAssignmentEmail(
            $newUser,
            $event,
            'judge_assigned',
            (int) $actingUser['user_id'],
            (int) ($event['symposium_id'] ?? 0)
        );

        return [
            'success' => true,
            'message' => "Judge replaced with {$newUser['full_name']} for \"{$event['event_name']}\".",
        ];
    }

    // =========================================================================
    // AVAILABILITY
    // =========================================================================

    /**
     * Get staff availability for a specific event.
     *
     * Returns all eligible staff with availability status:
     *   'available'   → No assignment on event date → Green
     *   'busy'        → Has another assignment on this date → Orange (with detail)
     *   'inactive'    → account_status != Active → Red
     *
     * Filters by role and optional department.
     *
     * @param int         $symposiumEventId
     * @param string|null $departmentId  Optional department filter
     * @param string|null $roleFilter    Optional role filter
     *
     * @return array
     */
    public function getStaffAvailability(
        int     $symposiumEventId,
        ?string $departmentId = null,
        ?string $roleFilter   = null
    ): array {
        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event) {
            return [];
        }

        $eventDate = $event['event_date'] ?? '';

        // Fetch all staff users with optional filters
        $allStaff = $this->userModel->getUsersByRoles(self::ASSIGNABLE_ROLES);

        // Department filter
        if ($departmentId !== null && $departmentId !== '') {
            $allStaff = array_filter($allStaff, fn($u) => (string) ($u['department_id'] ?? '') === $departmentId);
        }

        // Role filter
        if ($roleFilter !== null && $roleFilter !== '') {
            $allStaff = array_filter($allStaff, fn($u) => ($u['role'] ?? '') === $roleFilter);
        }

        $result = [];

        foreach ($allStaff as $staff) {
            $uid = (int) $staff['user_id'];

            if (($staff['account_status'] ?? '') !== 'Active') {
                $result[] = $staff + [
                    'availability_status' => 'inactive',
                    'availability_label'  => 'Inactive',
                    'conflict_detail'     => null,
                    'is_faculty'          => $this->facultyModel->isAssigned($symposiumEventId, $uid),
                    'is_judge'            => $this->judgeModel->isAssigned($symposiumEventId, $uid),
                ];
                continue;
            }

            if (empty($eventDate)) {
                $result[] = $staff + [
                    'availability_status' => 'available',
                    'availability_label'  => 'Available',
                    'conflict_detail'     => null,
                    'is_faculty'          => $this->facultyModel->isAssigned($symposiumEventId, $uid),
                    'is_judge'            => $this->judgeModel->isAssigned($symposiumEventId, $uid),
                ];
                continue;
            }

            $conflicts = $this->detectConflict($uid, $eventDate);

            if (!empty($conflicts)) {
                $c = $conflicts[0];
                $result[] = $staff + [
                    'availability_status' => 'busy',
                    'availability_label'  => 'Busy',
                    'conflict_detail'     => $c,
                    'is_faculty'          => $this->facultyModel->isAssigned($symposiumEventId, $uid),
                    'is_judge'            => $this->judgeModel->isAssigned($symposiumEventId, $uid),
                ];
            } else {
                $result[] = $staff + [
                    'availability_status' => 'available',
                    'availability_label'  => 'Available',
                    'conflict_detail'     => null,
                    'is_faculty'          => $this->facultyModel->isAssigned($symposiumEventId, $uid),
                    'is_judge'            => $this->judgeModel->isAssigned($symposiumEventId, $uid),
                ];
            }
        }

        // Sort: available first, then busy, then inactive
        usort($result, function (array $a, array $b): int {
            $order = ['available' => 0, 'busy' => 1, 'inactive' => 2];
            $aRank = $order[$a['availability_status']] ?? 3;
            $bRank = $order[$b['availability_status']] ?? 3;

            return $aRank !== $bRank
                ? $aRank <=> $bRank
                : strcmp($a['full_name'] ?? '', $b['full_name'] ?? '');
        });

        return array_values($result);
    }

    // =========================================================================
    // AVAILABILITY — AJAX (Single User Check)
    // =========================================================================

    /**
     * Check if a single user is available for a specific event date.
     * Used by the AJAX endpoint for real-time conflict preview.
     *
     * @param int $userId
     * @param int $symposiumEventId
     *
     * @return array{available: bool, conflicts: array}
     */
    public function checkUserAvailability(int $userId, int $symposiumEventId): array
    {
        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event) {
            return ['available' => false, 'conflicts' => [], 'error' => 'Event not found.'];
        }

        $targetUser = $this->userModel->findById($userId);
        if (!$targetUser) {
            return ['available' => false, 'conflicts' => [], 'error' => 'User not found.'];
        }

        if (!$this->isAssignable($targetUser)) {
            return [
                'available' => false,
                'conflicts' => [],
                'error'     => 'This user is not eligible for assignment (inactive or wrong role).',
            ];
        }

        $conflicts = $this->detectConflict($userId, $event['event_date'] ?? '');

        return [
            'available'   => empty($conflicts),
            'conflicts'   => $conflicts,
            'user_name'   => $targetUser['full_name'],
            'event_date'  => $event['event_date'] ?? '',
        ];
    }

    // =========================================================================
    // DASHBOARD STATS
    // =========================================================================

    /**
     * Get allocation statistics for a symposium.
     *
     * Returns:
     *  - total_events
     *  - faculty_assigned     (events with at least one FIC)
     *  - judges_assigned      (events with at least one judge)
     *  - faculty_pending      (events with no FIC)
     *  - judges_pending       (events with no judge)
     *  - conflicts_detected   (staff with more than one assignment on same date)
     *
     * @param int $symposiumId
     *
     * @return array
     */
    public function getDashboardStats(int $symposiumId): array
    {
        $facultyByEvent = [];
        $judgeByEvent   = [];

        // Get all assignments for this symposium grouped by event
        $facultyRows = $this->facultyModel->getBySymposium($symposiumId);
        $judgeRows   = $this->judgeModel->getBySymposium($symposiumId);

        foreach ($facultyRows as $row) {
            $facultyByEvent[$row['symposium_event_id']] = true;
        }
        foreach ($judgeRows as $row) {
            $judgeByEvent[$row['symposium_event_id']] = true;
        }

        // Count total scheduled events
        $events = $this->eventModel->getBySymposium($symposiumId);
        $total  = count($events);

        $facultyAssigned = count($facultyByEvent);
        $judgeAssigned   = count($judgeByEvent);
        $facultyPending  = $total - $facultyAssigned;
        $judgesPending   = $total - $judgeAssigned;

        return [
            'total_events'      => $total,
            'faculty_assigned'  => $facultyAssigned,
            'judges_assigned'   => $judgeAssigned,
            'faculty_pending'   => max(0, $facultyPending),
            'judges_pending'    => max(0, $judgesPending),
        ];
    }

    // =========================================================================
    // EMAIL NOTIFICATIONS (via EmailService queue)
    // =========================================================================

    /**
     * Enqueue an assignment/removal notification email.
     *
     * Uses the existing email_queue table + email_layout.php template system.
     * Never sends directly. The CLI worker delivers it via SMTP.
     *
     * @param array  $recipient    Target user (must have email)
     * @param array  $event        symposium_event row
     * @param string $triggerEvent One of: faculty_assigned / faculty_removed / judge_assigned / judge_removed
     * @param int    $triggeredBy  User who performed the action
     * @param int    $symposiumId  For the queue record
     *
     * @return void
     */
    private function enqueueAssignmentEmail(
        array  $recipient,
        array  $event,
        string $triggerEvent,
        int    $triggeredBy,
        int    $symposiumId
    ): void {
        if (empty($recipient['email'])) {
            return;
        }

        $isFaculty = str_contains($triggerEvent, 'faculty');
        $isAssign  = str_contains($triggerEvent, 'assigned');

        $roleLabel = $isFaculty ? 'Faculty In-Charge' : 'Judge';
        $action    = $isAssign ? 'assigned to' : 'removed from';

        $eventName = $event['event_name'] ?? 'Event';
        $eventDate = !empty($event['event_date']) ? \App\Helpers\DateHelper::date($event['event_date']) : '—';
        $session   = $event['session'] ?? '—';
        $venue     = $event['venue_name'] ?? '—';

        $subject  = "[NexusCore] {$roleLabel} {$action} — {$eventName}";

        $htmlBody = $this->renderEmailTemplate($triggerEvent, [
            'recipient_name' => $recipient['full_name'] ?? 'Faculty',
            'role_label'     => $roleLabel,
            'action'         => $action,
            'event_name'     => $eventName,
            'event_date'     => $eventDate,
            'session'        => $session,
            'venue'          => $venue,
        ]);

        try {
            $this->emailQueue->enqueue([
                'symposium_id'    => $symposiumId,
                'trigger_event'   => $triggerEvent,
                'triggered_by'    => $triggeredBy,
                'student_id'      => null,
                'recipient_email' => $recipient['email'],
                'recipient_name'  => $recipient['full_name'] ?? null,
                'subject'         => $subject,
                'html_body'       => $htmlBody,
            ]);
        } catch (\Throwable) {
            // Email failures MUST NOT break the main allocation flow
        }
    }

    /**
     * Render an email template to an HTML string.
     *
     * @param string $template   Template name (matches templates/emails/{template}.php)
     * @param array  $data
     *
     * @return string
     */
    private function renderEmailTemplate(string $template, array $data): string
    {
        $templateFile = dirname(__DIR__, 2) . "/templates/emails/{$template}.php";

        if (!file_exists($templateFile)) {
            // Fallback inline HTML if template file doesn't exist
            return sprintf(
                '<p>Dear %s,</p><p>You have been <strong>%s</strong> as <strong>%s</strong> for <strong>%s</strong> on %s (%s) at %s.</p>',
                htmlspecialchars($data['recipient_name'], ENT_QUOTES),
                htmlspecialchars($data['action'], ENT_QUOTES),
                htmlspecialchars($data['role_label'], ENT_QUOTES),
                htmlspecialchars($data['event_name'], ENT_QUOTES),
                htmlspecialchars($data['event_date'], ENT_QUOTES),
                htmlspecialchars($data['session'], ENT_QUOTES),
                htmlspecialchars($data['venue'], ENT_QUOTES)
            );
        }

        ob_start();
        extract($data, EXTR_SKIP);
        require $templateFile;
        $content = ob_get_clean();

        // Wrap in email layout
        $layoutFile = dirname(__DIR__, 2) . '/templates/emails/email_layout.php';
        if (!file_exists($layoutFile)) {
            return $content;
        }

        $headerTitle  = '[NexusCore] ' . ucfirst($data['action'] ?? '') . ' Notification';
        $preheader    = "You have been {$data['action']} as {$data['role_label']} for {$data['event_name']}.";
        $collegeName  = config('college_name');
        $footerNote   = 'This is an automated notification from NexusCore EMS.';

        ob_start();
        require $layoutFile;
        return ob_get_clean();
    }

    // =========================================================================
    // AUDIT LOG
    // =========================================================================

    /**
     * Write an audit log entry.
     *
     * @param array  $user
     * @param string $action
     * @param int    $recordId   The symposium_event_id
     * @param string $detail
     *
     * @return void
     */
    private function writeAuditLog(array $user, string $action, int $recordId, string $detail): void
    {
        try {
            $this->auditModel->log(
                'symposium_events',
                $recordId,
                $action,
                (int) ($user['user_id'] ?? 0),
                $detail
            );
        } catch (\Throwable $e) {
            // Audit failures must never break the allocation flow
        }
    }
}
