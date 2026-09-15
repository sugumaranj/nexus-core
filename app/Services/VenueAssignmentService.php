<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS
 * =========================================================================
 * File        : VenueAssignmentService.php
 * Location    : app/Services/
 * Description : Business logic for Venue Assignment to Symposium Events.
 *
 * Responsibilities
 * ─────────────────────────────────────────────────────────────────────────
 * • Assign, update, and remove a venue for a symposium event
 * • Serialize concurrent assignments using venue-row FOR UPDATE
 * • Detect scheduling conflicts (same venue, same date, overlapping times)
 * • Send idempotent notifications to FIC, Judge, and Students
 * • Validate venue availability after rescheduling
 *
 * Concurrency Safety
 * ─────────────────────────────────────────────────────────────────────────
 * Two staff coordinators assigning the same venue simultaneously are
 * serialized at the venue-row lock. The event-row is ALSO locked to prevent
 * the event data from changing under us during the transaction.
 *
 * Notification Idempotency
 * ─────────────────────────────────────────────────────────────────────────
 * Each notification carries a (notification_type, notification_ref_key)
 * that maps to a UNIQUE KEY in the notifications table. Re-running the
 * same assignment produces a no-op INSERT IGNORE.
 *
 * =========================================================================
 */

namespace App\Services;

use App\Database\Database;
use App\Models\AuditLogModel;
use App\Models\FacultyAssignmentModel;
use App\Models\JudgeAssignmentModel;
use App\Models\NotificationModel;
use App\Models\SymposiumEventModel;
use App\Models\VenueModel;
use App\Models\ApplicationModel;
use PDO;

final class VenueAssignmentService
{
    private PDO                   $db;
    private VenueModel            $venueModel;
    private SymposiumEventModel   $eventModel;
    private FacultyAssignmentModel $facultyModel;
    private JudgeAssignmentModel  $judgeModel;
    private NotificationModel     $notificationModel;
    private AuditLogModel         $auditModel;
    private ApplicationModel      $appModel;

    public function __construct()
    {
        $this->db                = Database::getConnection();
        $this->venueModel        = new VenueModel();
        $this->eventModel        = new SymposiumEventModel();
        $this->facultyModel      = new FacultyAssignmentModel();
        $this->judgeModel        = new JudgeAssignmentModel();
        $this->notificationModel = new NotificationModel();
        $this->auditModel        = new AuditLogModel();
        $this->appModel          = new ApplicationModel();
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Assign (or replace) a venue for a symposium event.
     *
     * @param int   $symposiumEventId
     * @param int   $venueId
     * @param array $user             Authenticated user from Session
     * @return array{success: bool, message: string}
     */
    public function assignVenue(int $symposiumEventId, int $venueId, array $user): array
    {
        if ($symposiumEventId <= 0 || $venueId <= 0) {
            return ['success' => false, 'message' => 'Invalid event or venue.'];
        }

        $this->db->beginTransaction();

        try {
            // ── Lock the VENUE row first (serializes concurrent assignments) ──
            $stmtV = $this->db->prepare(
                "SELECT venue_id, venue_name, venue_code, building_name, floor,
                        seating_capacity, is_active
                 FROM venues WHERE venue_id = :venue_id FOR UPDATE"
            );
            $stmtV->execute(['venue_id' => $venueId]);
            $venue = $stmtV->fetch(PDO::FETCH_ASSOC);

            if (!$venue) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Venue not found.'];
            }

            if (!(bool)$venue['is_active']) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Venue is inactive and cannot be assigned.'];
            }

            // ── Lock the EVENT row (prevents rescheduling mid-assignment) ──
            $stmtE = $this->db->prepare(
                "SELECT symposium_event_id, event_name, event_date, start_time,
                        end_time, session, venue_id, symposium_id
                 FROM symposium_events
                 WHERE symposium_event_id = :eid FOR UPDATE"
            );
            $stmtE->execute(['eid' => $symposiumEventId]);
            $event = $stmtE->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Symposium event not found.'];
            }

            if (empty($event['event_date']) || empty($event['start_time']) || empty($event['end_time'])) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Event must be scheduled (date and time set) before assigning a venue.'];
            }

            // ── Check for scheduling conflict (same venue, same date, overlapping times) ──
            $conflict = $this->eventModel->checkVenueConflict(
                $venueId,
                $event['event_date'],
                $event['start_time'],
                $event['end_time'],
                $symposiumEventId          // exclude self
            );

            if ($conflict !== false) {
                $this->db->rollBack();
                $conflictName = htmlspecialchars($conflict['event_name'] ?? 'Another event', ENT_QUOTES);
                return [
                    'success' => false,
                    'message' => "Venue conflict: \"{$venue['venue_name']}\" is already assigned to \"{$conflictName}\" "
                        . "on {$event['event_date']} at {$conflict['start_time']}–{$conflict['end_time']}.",
                ];
            }

            $previousVenueId = $event['venue_id'] ? (int)$event['venue_id'] : null;
            $isUpdate        = ($previousVenueId !== null);

            // ── Update venue_id on symposium_events ──────────────────────────
            $stmtUpd = $this->db->prepare(
                "UPDATE symposium_events SET venue_id = :venue_id
                 WHERE symposium_event_id = :eid"
            );
            $stmtUpd->execute(['venue_id' => $venueId, 'eid' => $symposiumEventId]);

            // ── Deactivate old Venue-type event_assignment (if any) ──────────
            $stmtDea = $this->db->prepare(
                "UPDATE event_assignments
                 SET is_active = 0
                 WHERE symposium_event_id = :eid AND assignment_type = 'Venue' AND is_active = 1"
            );
            $stmtDea->execute(['eid' => $symposiumEventId]);

            // ── Insert new Venue event_assignment ────────────────────────────
            $stmtIns = $this->db->prepare(
                "INSERT INTO event_assignments
                    (symposium_event_id, assignment_type, venue_id, assigned_user_id,
                     assigned_by, is_active)
                 VALUES
                    (:eid, 'Venue', :venue_id, NULL, :assigned_by, 1)"
            );
            $stmtIns->execute([
                'eid'         => $symposiumEventId,
                'venue_id'    => $venueId,
                'assigned_by' => (int)$user['user_id'],
            ]);

            $assignmentId = (int)$this->db->lastInsertId();

            // ── Audit log ─────────────────────────────────────────────────────
            $action = $isUpdate ? 'VENUE_UPDATED' : 'VENUE_ASSIGNED';
            $this->auditModel->log(
                'Symposium Events',
                $symposiumEventId,
                $action,
                (int)$user['user_id'],
                "Venue '{$venue['venue_name']}' ({$venue['venue_code']}) "
                . ($isUpdate ? 'updated' : 'assigned') . " to event '{$event['event_name']}'"
            );

            // ── Send notifications ─────────────────────────────────────────
            $notifType   = $isUpdate ? 'venue_updated' : 'venue_assigned';
            $notifTitle  = $isUpdate
                ? "Venue Updated: {$event['event_name']}"
                : "Venue Assigned: {$event['event_name']}";
            $venueDetail = "{$venue['venue_name']}"
                . ($venue['venue_code']    ? " [{$venue['venue_code']}]" : '')
                . ($venue['building_name'] ? ", {$venue['building_name']}" : '')
                . ($venue['floor']         ? ", Floor {$venue['floor']}" : '');
            $notifMsg    = "Your event \"{$event['event_name']}\" on {$event['event_date']} "
                . "({$event['session']}, {$event['start_time']}–{$event['end_time']}) "
                . "has been " . ($isUpdate ? 'moved to' : 'assigned to') . " venue: {$venueDetail}.";

            // FIC notifications
            $ficList = $this->facultyModel->getForEvent($symposiumEventId);
            foreach ($ficList as $fic) {
                $userId = (int)($fic['user_id'] ?? 0);
                if ($userId <= 0) { continue; }
                $this->notificationModel->createWithIdempotencyKey(
                    'User',
                    $userId,
                    $symposiumEventId,
                    $notifType,
                    "assign-{$assignmentId}-u{$userId}",
                    $notifTitle,
                    $notifMsg,
                    'Pending'      // appears in bell
                );
            }

            // Judge notifications
            $judgeList = $this->judgeModel->getForEvent($symposiumEventId);
            foreach ($judgeList as $judge) {
                $userId = (int)($judge['user_id'] ?? 0);
                if ($userId <= 0) { continue; }
                $this->notificationModel->createWithIdempotencyKey(
                    'User',
                    $userId,
                    $symposiumEventId,
                    $notifType,
                    "assign-{$assignmentId}-u{$userId}",
                    $notifTitle,
                    $notifMsg,
                    'Pending'
                );
            }

            // Student notifications (registered & not withdrawn)
            $registrations = $this->appModel->getBySymposiumEvent($symposiumEventId);
            foreach ($registrations as $reg) {
                $studentId = (int)($reg['student_id'] ?? 0);
                if ($studentId <= 0) { continue; }
                $this->notificationModel->createWithIdempotencyKey(
                    'Student',
                    $studentId,
                    $symposiumEventId,
                    $notifType,
                    "assign-{$assignmentId}-s{$studentId}",
                    $notifTitle,
                    $notifMsg,
                    'Pending'
                );
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => $isUpdate
                    ? "Venue updated to \"{$venue['venue_name']}\" for \"{$event['event_name']}\"."
                    : "Venue \"{$venue['venue_name']}\" assigned to \"{$event['event_name']}\".",
            ];

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[VenueAssignmentService::assignVenue] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error. Please try again.'];
        }
    }

    /**
     * Remove the venue assignment from a symposium event.
     *
     * @param int   $symposiumEventId
     * @param array $user
     * @return array{success: bool, message: string}
     */
    public function removeVenue(int $symposiumEventId, array $user): array
    {
        if ($symposiumEventId <= 0) {
            return ['success' => false, 'message' => 'Invalid event.'];
        }

        $this->db->beginTransaction();

        try {
            // Lock event row
            $stmtE = $this->db->prepare(
                "SELECT symposium_event_id, event_name, venue_id, event_date, start_time, end_time, session
                 FROM symposium_events WHERE symposium_event_id = :eid FOR UPDATE"
            );
            $stmtE->execute(['eid' => $symposiumEventId]);
            $event = $stmtE->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Event not found.'];
            }

            if (!$event['venue_id']) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'No venue is currently assigned to this event.'];
            }

            $venueName = '';
            $venue = $this->venueModel->findById((int)$event['venue_id']);
            if ($venue) {
                $venueName = $venue['venue_name'];
            }

            // Clear venue_id on event
            $this->db->prepare(
                "UPDATE symposium_events SET venue_id = NULL WHERE symposium_event_id = :eid"
            )->execute(['eid' => $symposiumEventId]);

            // Deactivate event_assignments Venue record
            $this->db->prepare(
                "UPDATE event_assignments
                 SET is_active = 0
                 WHERE symposium_event_id = :eid AND assignment_type = 'Venue' AND is_active = 1"
            )->execute(['eid' => $symposiumEventId]);

            // Audit
            $this->auditModel->log(
                'Symposium Events',
                $symposiumEventId,
                'VENUE_REMOVED',
                (int)$user['user_id'],
                "Venue '{$venueName}' removed from event '{$event['event_name']}'"
            );

            // Notifications (FIC, Judges, Students)
            $notifTitle = "Venue Removed: {$event['event_name']}";
            $notifMsg   = "The venue assignment for \"{$event['event_name']}\" on {$event['event_date']} "
                . "({$event['session']}, {$event['start_time']}–{$event['end_time']}) "
                . "has been removed. Please await the updated venue information.";

            $refSuffix = 'rem-' . date('Ymd-His');

            foreach ($this->facultyModel->getForEvent($symposiumEventId) as $fic) {
                $uid = (int)($fic['user_id'] ?? 0);
                if ($uid <= 0) { continue; }
                $this->notificationModel->createWithIdempotencyKey(
                    'User', $uid, $symposiumEventId,
                    'venue_removed', "{$refSuffix}-u{$uid}",
                    $notifTitle, $notifMsg, 'Pending'
                );
            }

            foreach ($this->judgeModel->getForEvent($symposiumEventId) as $judge) {
                $uid = (int)($judge['user_id'] ?? 0);
                if ($uid <= 0) { continue; }
                $this->notificationModel->createWithIdempotencyKey(
                    'User', $uid, $symposiumEventId,
                    'venue_removed', "{$refSuffix}-u{$uid}",
                    $notifTitle, $notifMsg, 'Pending'
                );
            }

            foreach ($this->appModel->getBySymposiumEvent($symposiumEventId) as $reg) {
                $sid = (int)($reg['student_id'] ?? 0);
                if ($sid <= 0) { continue; }
                $this->notificationModel->createWithIdempotencyKey(
                    'Student', $sid, $symposiumEventId,
                    'venue_removed', "{$refSuffix}-s{$sid}",
                    $notifTitle, $notifMsg, 'Pending'
                );
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => "Venue removed from \"{$event['event_name']}\".",
            ];

        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[VenueAssignmentService::removeVenue] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Database error. Please try again.'];
        }
    }

    /**
     * Get all active venues with their availability status for a given event's time slot.
     *
     * Returns each venue with:
     *   availability_status: 'available' | 'conflict'
     *   conflict_event:      name of conflicting event (if any)
     *
     * @param int $symposiumEventId
     * @return array
     */
    public function getVenueAvailability(int $symposiumEventId): array
    {
        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event) {
            return [];
        }

        $venues = $this->venueModel->getAllActive();

        $eventDate = $event['event_date']  ?? '';
        $startTime = $event['start_time']  ?? '';
        $endTime   = $event['end_time']    ?? '';

        foreach ($venues as &$venue) {
            $vid = (int)$venue['venue_id'];

            if (!$eventDate || !$startTime || !$endTime) {
                $venue['availability_status'] = 'unknown';
                $venue['conflict_event']      = null;
                continue;
            }

            $conflict = $this->eventModel->checkVenueConflict(
                $vid, $eventDate, $startTime, $endTime, $symposiumEventId
            );

            if ($conflict !== false) {
                $venue['availability_status'] = 'conflict';
                $venue['conflict_event']      = $conflict['event_name'] ?? 'Another event';
            } else {
                $venue['availability_status'] = 'available';
                $venue['conflict_event']      = null;
            }
        }
        unset($venue);

        return $venues;
    }

    /**
     * Validate whether the existing venue assignment is still valid after rescheduling.
     *
     * Called by SchedulingController after a successful reschedule.
     * Does NOT auto-remove the venue — returns a warning flag only.
     *
     * @param int $symposiumEventId
     * @return array{has_conflict: bool, venue_name: string, conflict_event: string}
     */
    public function validateVenueAfterReschedule(int $symposiumEventId): array
    {
        $event = $this->eventModel->findById($symposiumEventId);

        if (!$event || !$event['venue_id']) {
            return ['has_conflict' => false, 'venue_name' => '', 'conflict_event' => ''];
        }

        $conflict = $this->eventModel->checkVenueConflict(
            (int)$event['venue_id'],
            $event['event_date'],
            $event['start_time'],
            $event['end_time'],
            $symposiumEventId
        );

        if ($conflict !== false) {
            $venue = $this->venueModel->findById((int)$event['venue_id']);
            return [
                'has_conflict'  => true,
                'venue_name'    => $venue['venue_name'] ?? 'Current venue',
                'conflict_event'=> $conflict['event_name'] ?? 'Another event',
            ];
        }

        return ['has_conflict' => false, 'venue_name' => '', 'conflict_event' => ''];
    }

    /**
     * Get all events for a symposium with their assigned venue (for the venue report).
     *
     * @param int $symposiumId
     * @return array
     */
    public function getVenueAllocationReport(int $symposiumId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                se.symposium_event_id,
                se.event_name,
                se.event_code,
                se.event_date,
                se.start_time,
                se.end_time,
                se.session,
                se.status            AS event_status,
                se.schedule_status,
                v.venue_id,
                v.venue_name,
                v.venue_code,
                v.building_name,
                v.floor,
                v.seating_capacity,
                v.is_computer_lab,
                (
                    SELECT GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ')
                    FROM faculty_assignments fa
                    JOIN users u ON u.user_id = fa.user_id
                    WHERE fa.symposium_event_id = se.symposium_event_id
                      AND fa.is_active = 1
                ) AS fic_names,
                (
                    SELECT COUNT(*)
                    FROM applications a
                    WHERE a.symposium_event_id = se.symposium_event_id
                      AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
                ) AS registered_count
            FROM symposium_events se
            LEFT JOIN venues v ON v.venue_id = se.venue_id
            WHERE se.symposium_id = :symposium_id
              AND se.status != 'Cancelled'
            ORDER BY se.event_date ASC, se.start_time ASC
        ");
        $stmt->execute(['symposium_id' => $symposiumId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
