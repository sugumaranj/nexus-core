<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : DashboardService.php
 * Location    : app/Services/
 * Description : Provides dashboard statistics for each user role.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Query efficient COUNT aggregates per role.
 * • Avoid N+1 queries — one query per dashboard section.
 * • Return only data relevant to each role.
 * • Handle missing or empty tables gracefully.
 *
 * NOTE
 * -------------------------------------------------------------------------
 * This service uses direct PDO queries for dashboard statistics.
 * It does NOT duplicate logic already in existing models — it only
 * fetches aggregate counts that are not available elsewhere.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Database\Database;
use PDO;
use Throwable;

final class DashboardService
{
    /**
     * PDO database connection.
     *
     * @var PDO
     */
    private PDO $db;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // =========================================================================
    // ADMINISTRATOR DASHBOARD
    // =========================================================================

    /**
     * ---------------------------------------------------------------------
     * Retrieve all statistics for the Administrator dashboard.
     *
     * Returns:
     * • total_departments  — active departments
     * • total_users        — all users (active)
     * • total_staff        — users with role Staff or Staff Coordinator
     * • total_students     — students in students table
     * • total_symposiums   — all symposiums
     * • total_competitions — active competitions
     * • pending_approvals  — symposiums in Draft/pending state
     * • total_registrations— competition applications
     *
     * @return array<string, int>
     * ---------------------------------------------------------------------
     */
    public function getAdminStats(): array
    {
        return [
            // ── Organisational ────────────────────────────────────────────
            'total_departments'         => $this->count("
                SELECT COUNT(*) FROM departments WHERE is_active = 1
            "),

            // ── Users (grand total) ───────────────────────────────────────
            'total_users'               => $this->count("
                SELECT COUNT(*) FROM users WHERE account_status = 'Active'
            "),

            // ── Per-role breakdown ────────────────────────────────────────
            'total_admins'              => $this->count("
                SELECT COUNT(*) FROM users
                WHERE role = 'Admin' AND account_status = 'Active'
            "),
            'total_principals'          => $this->count("
                SELECT COUNT(*) FROM users
                WHERE role = 'Principal' AND account_status = 'Active'
            "),
            'total_hods'                => $this->count("
                SELECT COUNT(*) FROM users
                WHERE role = 'HOD' AND account_status = 'Active'
            "),
            'total_staff_coordinators'  => $this->count("
                SELECT COUNT(*) FROM users
                WHERE role = 'Staff Coordinator' AND account_status = 'Active'
            "),
            'total_staff'               => $this->count("
                SELECT COUNT(*) FROM users
                WHERE role = 'Staff' AND account_status = 'Active'
            "),
            'total_student_coordinators'=> $this->count("
                SELECT COUNT(*) FROM users
                WHERE role = 'Student Coordinator' AND account_status = 'Active'
            "),

            // ── Students ──────────────────────────────────────────────────
            'total_students'            => $this->count("
                SELECT COUNT(*) FROM students WHERE account_status = 'Active'
            "),

            // ── Events & Master Event Library ─────────────────────────────
            'total_symposiums'          => $this->count("
                SELECT COUNT(*) FROM symposiums
            "),
            'total_master_events'       => $this->count("
                SELECT COUNT(*) FROM master_events WHERE is_deleted = 0
            "),
            'total_symposium_events'    => $this->count("
                SELECT COUNT(*) FROM symposium_events WHERE is_deleted = 0
            "),
            'total_competitions'        => $this->count("
                SELECT COUNT(*) FROM master_events WHERE is_deleted = 0
            "),

            // ── Workflow ──────────────────────────────────────────────────
            'pending_approvals'         => $this->count("
                SELECT COUNT(*) FROM symposiums
                WHERE status IN ('Submitted','Pending HOD Approval','Pending Principal Approval')
            "),
            'total_registrations'       => $this->count("
                SELECT COUNT(*) FROM applications
            "),
        ];
    }

    // =========================================================================
    // PRINCIPAL DASHBOARD
    // =========================================================================

    /**
     * ---------------------------------------------------------------------
     * Retrieve statistics for the Principal dashboard.
     *
     * Returns:
     * • pending_approvals     — symposiums awaiting action
     * • approved_symposiums   — symposiums with open/completed status
     * • rejected_symposiums   — symposiums with rejected status
     * • total_departments     — active departments
     * • total_students        — active students
     * • total_symposiums      — all symposiums
     *
     * @return array<string, int>
     * ---------------------------------------------------------------------
     */
    public function getPrincipalStats(): array
    {
        return [
            // ── Core approval workflow ─────────────────────────────────────
            'pending_final_approval'  => $this->count("
                SELECT COUNT(DISTINCT sa.symposium_id)
                FROM symposium_approvals sa
                WHERE sa.approval_level = 'Principal'
                  AND sa.status = 'Pending'
            "),

            // ── Symposium pipeline ─────────────────────────────────────────
            'total_symposiums'    => $this->count("
                SELECT COUNT(*) FROM symposiums
            "),
            'approved_symposiums' => $this->count("
                SELECT COUNT(*) FROM symposiums
                WHERE status IN ('Approved', 'Scheduling Complete', 'Registration Open', 'Registration Closed', 'Completed')
            "),
            'rejected_symposiums' => $this->count("
                SELECT COUNT(*) FROM symposiums
                WHERE status IN ('Rejected by HOD', 'Rejected by Principal')
            "),

            // ── Institutional overview (read-only context for principal) ───
            'total_departments'   => $this->count("
                SELECT COUNT(*) FROM departments WHERE is_active = 1
            "),
            'total_students'      => $this->count("
                SELECT COUNT(*) FROM students WHERE account_status = 'Active'
            "),
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * Get symposiums pending Principal approval (with details).
     *
     * Returns the list of symposiums in 'Pending Principal Approval' status
     * so the principal dashboard can display actionable rows.
     *
     * @param  int $limit Maximum rows to return.
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getPendingPrincipalSymposiums(int $limit = 10): array
    {
        $sql = "
            SELECT
                s.symposium_id,
                s.symposium_code,
                s.title,
                s.symposium_type,
                s.academic_year,
                s.status,
                s.submitted_at,
                u.full_name AS created_by_name,
                GROUP_CONCAT(d.department_name ORDER BY sd.id SEPARATOR ' & ') AS organizing_departments
            FROM symposiums s
            LEFT JOIN users u ON u.user_id = s.created_by
            LEFT JOIN symposium_departments sd ON sd.symposium_id = s.symposium_id
            LEFT JOIN departments d ON d.department_id = sd.department_id
            WHERE s.status = 'Pending Principal Approval'
            GROUP BY s.symposium_id
            ORDER BY s.submitted_at ASC
            LIMIT :lim
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * ---------------------------------------------------------------------
     * Get recently approved symposiums (approved by Principal).
     *
     * @param  int $limit Maximum rows to return.
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getRecentlyApprovedSymposiums(int $limit = 5): array
    {
        $sql = "
            SELECT
                s.symposium_id,
                s.symposium_code,
                s.title,
                s.symposium_type,
                s.status,
                sa.approved_at,
                u.full_name AS created_by_name,
                GROUP_CONCAT(d.department_name ORDER BY sd.id SEPARATOR ' & ') AS organizing_departments
            FROM symposiums s
            INNER JOIN symposium_approvals sa
                ON sa.symposium_id = s.symposium_id
               AND sa.approval_level = 'Principal'
               AND sa.status = 'Approved'
            LEFT JOIN users u ON u.user_id = s.created_by
            LEFT JOIN symposium_departments sd ON sd.symposium_id = s.symposium_id
            LEFT JOIN departments d ON d.department_id = sd.department_id
            GROUP BY s.symposium_id, sa.approved_at
            ORDER BY sa.approved_at DESC
            LIMIT :lim
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    // =========================================================================
    // HOD DASHBOARD
    // =========================================================================

    /**
     * ---------------------------------------------------------------------
     * Retrieve department-specific statistics for the HOD dashboard.
     *
     * All counts are scoped to the HOD's own department.
     *
     * @param int $departmentId The HOD's department_id from session.
     *
     * @return array<string, int>
     * ---------------------------------------------------------------------
     */
    public function getHodStats(int $departmentId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT d.department_id 
            FROM departments d 
            LEFT JOIN department_approvers da ON da.department_id = d.department_id 
            WHERE COALESCE(da.approver_department_id, d.department_id) = :dept_id
        ");
        $stmt->execute([':dept_id' => $departmentId]);
        $deptIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($deptIds)) {
            $deptIds = [$departmentId];
        }
        $inClause = implode(',', array_map('intval', $deptIds));

        return [
            'dept_students'       => $this->countWith("
                SELECT COUNT(*) FROM students
                WHERE department_id IN ($inClause) AND account_status = 'Active'
            ", []),

            'dept_staff'          => $this->countWith("
                SELECT COUNT(*) FROM users
                WHERE department_id IN ($inClause)
                  AND role IN ('Staff', 'Staff Coordinator', 'Student Coordinator')
                  AND account_status = 'Active'
            ", []),

            // Symposiums organized by this department (via junction table)
            'dept_symposiums'     => $this->countWith("
                SELECT COUNT(DISTINCT sd.symposium_id)
                FROM symposium_departments sd
                WHERE sd.department_id IN ($inClause)
            ", []),

            // Competitions under symposiums organized by this department
            'dept_competitions'   => $this->countWith("
                SELECT COUNT(DISTINCT c.competition_id) FROM competitions c
                INNER JOIN symposium_departments sd ON sd.symposium_id = c.symposium_id
                WHERE sd.department_id IN ($inClause)
                  AND c.is_active = 1 AND c.is_deleted = 0
            ", []),

            // Count symposiums where this HOD's department has a Pending approval record
            'pending_my_approval' => $this->countWith("
                SELECT COUNT(DISTINCT sa.symposium_id)
                FROM symposium_approvals sa
                WHERE sa.department_id IN ($inClause)
                  AND sa.approval_level = 'HOD'
                  AND sa.status = 'Pending'
            ", []),

            'approved_today'      => $this->countWith("
                SELECT COUNT(DISTINCT sa.symposium_id)
                FROM symposium_approvals sa
                WHERE sa.department_id IN ($inClause)
                  AND sa.status = 'Approved'
                  AND DATE(sa.approved_at) = CURDATE()
            ", []),

            'revision_requests'   => $this->countWith("
                SELECT COUNT(DISTINCT sa.symposium_id)
                FROM symposium_approvals sa
                WHERE sa.department_id IN ($inClause)
                  AND sa.status = 'Revision Requested'
            ", []),

            'dept_registrations'  => $this->countWith("
                SELECT COUNT(*) FROM applications a
                INNER JOIN competitions c ON c.competition_id = a.competition_id
                INNER JOIN symposium_departments sd ON sd.symposium_id = c.symposium_id
                WHERE sd.department_id IN ($inClause)
            ", []),

            // These are computed separately per user in DashboardController
            // Placeholder so template doesn't break if called without userId context
            'fic_assigned_events'   => 0,
            'fic_no_judge'          => 0,
            'judge_assigned_events' => 0,
        ];
    }

    // =========================================================================
    // STAFF COORDINATOR DASHBOARD
    // =========================================================================

    /**
     * ---------------------------------------------------------------------
     * Retrieve statistics for the Staff Coordinator dashboard.
     *
     * Scoped to symposiums and competitions created by this user.
     *
     * @param int $userId The Staff Coordinator's user_id from session.
     *
     * @return array<string, int>
     * ---------------------------------------------------------------------
     */
    public function getStaffCoordinatorStats(int $userId, ?int $symposiumId = null): array
    {
        $regParams = [':user_id' => $userId];
        $symFilter = "";
        
        if ($symposiumId !== null) {
            $symFilter = " AND s.symposium_id = :symposium_id";
            $regParams[':symposium_id'] = $symposiumId;
        }

        return [
            'my_drafts'       => $this->countWith("
                SELECT COUNT(*) FROM symposiums 
                WHERE status = '" . \App\Services\SymposiumService::STATUS_DRAFT . "' 
                  AND created_by = :user_id
            ", [':user_id' => $userId]),

            'pending_hod'     => $this->countWith("
                SELECT COUNT(*) FROM symposiums
                WHERE status IN ('" . \App\Services\SymposiumService::STATUS_PENDING_HOD . "', '" . \App\Services\SymposiumService::STATUS_PENDING_PRINCIPAL . "')
                  AND created_by = :user_id
            ", [':user_id' => $userId]),

            'revision_req'    => $this->countWith("
                SELECT COUNT(*) FROM symposiums
                WHERE status IN ('" . \App\Services\SymposiumService::STATUS_REJECTED_HOD . "', '" . \App\Services\SymposiumService::STATUS_REJECTED_PRINCIPAL . "')
                  AND created_by = :user_id
            ", [':user_id' => $userId]),

            'my_approved'     => $this->countWith("
                SELECT COUNT(*) FROM symposiums
                WHERE status IN ('Approved', 'Scheduling Complete', 'Registration Open', 'Registration Closed', 'Completed')
                  AND created_by = :user_id
            ", [':user_id' => $userId]),

            'my_symposiums'   => $this->countWith("
                SELECT COUNT(*) FROM symposiums
                WHERE created_by = :user_id
            ", [':user_id' => $userId]),

            'my_competitions' => $this->countWith("
                SELECT COUNT(c.competition_id) 
                FROM competitions c
                INNER JOIN symposiums s ON c.symposium_id = s.symposium_id
                WHERE s.created_by = :user_id
            ", [':user_id' => $userId]),

            'total_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) 
                FROM applications a
                INNER JOIN competitions c ON a.competition_id = c.competition_id
                INNER JOIN symposiums s ON c.symposium_id = s.symposium_id
                WHERE s.created_by = :user_id $symFilter
            ", $regParams),

            'pending_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) 
                FROM applications a
                INNER JOIN competitions c ON a.competition_id = c.competition_id
                INNER JOIN symposiums s ON c.symposium_id = s.symposium_id
                WHERE a.application_status = 'Pending' 
                  AND s.created_by = :user_id $symFilter
            ", $regParams),

            'approved_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) 
                FROM applications a
                INNER JOIN competitions c ON a.competition_id = c.competition_id
                INNER JOIN symposiums s ON c.symposium_id = s.symposium_id
                WHERE a.application_status = 'Approved' 
                  AND s.created_by = :user_id $symFilter
            ", $regParams),

            'rejected_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) 
                FROM applications a
                INNER JOIN competitions c ON a.competition_id = c.competition_id
                INNER JOIN symposiums s ON c.symposium_id = s.symposium_id
                WHERE a.application_status = 'Rejected' 
                  AND s.created_by = :user_id $symFilter
            ", $regParams),

            'withdrawn_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) 
                FROM applications a
                INNER JOIN competitions c ON a.competition_id = c.competition_id
                INNER JOIN symposiums s ON c.symposium_id = s.symposium_id
                WHERE a.application_status = 'Withdrawn' 
                  AND s.created_by = :user_id $symFilter
            ", $regParams),

            'cancelled_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) 
                FROM applications a
                INNER JOIN competitions c ON a.competition_id = c.competition_id
                INNER JOIN symposiums s ON c.symposium_id = s.symposium_id
                WHERE a.application_status = 'Cancelled' 
                  AND s.created_by = :user_id $symFilter
            ", $regParams),

            'today_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) 
                FROM applications a
                INNER JOIN competitions c ON a.competition_id = c.competition_id
                INNER JOIN symposiums s ON c.symposium_id = s.symposium_id
                WHERE DATE(a.applied_at) = CURDATE() 
                  AND s.created_by = :user_id $symFilter
            ", $regParams),

            'total_students'      => $this->count("
                SELECT COUNT(*) FROM students WHERE account_status = 'Active'
            "),

            // --- Scheduling Stats ---
            'events_pending_scheduling' => $this->countWith("
                SELECT COUNT(se.symposium_event_id)
                FROM symposium_events se
                JOIN symposiums s ON s.symposium_id = se.symposium_id
                WHERE s.status = 'Approved'
                  AND se.schedule_status = 'Unscheduled'
                  AND s.created_by = :user_id
            ", [':user_id' => $userId]),

            'events_scheduled' => $this->countWith("
                SELECT COUNT(se.symposium_event_id)
                FROM symposium_events se
                JOIN symposiums s ON s.symposium_id = se.symposium_id
                WHERE s.status = 'Approved'
                  AND se.schedule_status IN ('Scheduled','Rescheduled')
                  AND s.created_by = :user_id
            ", [':user_id' => $userId]),

            'total_events_in_approved' => $this->countWith("
                SELECT COUNT(se.symposium_event_id)
                FROM symposium_events se
                JOIN symposiums s ON s.symposium_id = se.symposium_id
                WHERE s.status = 'Approved'
                  AND s.created_by = :user_id
            ", [':user_id' => $userId]),

            'approved_symposiums_needing_scheduling' => $this->countWith("
                SELECT COUNT(DISTINCT s.symposium_id)
                FROM symposiums s
                WHERE s.status = 'Approved'
                  AND s.created_by = :user_id
            ", [':user_id' => $userId]),
        ];
    }

    // =========================================================================
    // STAFF DASHBOARD
    // =========================================================================

    /**
     * ---------------------------------------------------------------------
     * Retrieve statistics for the Staff dashboard.
     *
     * Staff focus on student management — no symposium creation.
     *
     * @return array<string, int>
     * ---------------------------------------------------------------------
     */
    public function getStaffStats(int $userId): array
    {
        return [
            'total_students'      => $this->count("
                SELECT COUNT(*) FROM students WHERE account_status = 'Active'
            "),
            'inactive_students'   => $this->count("
                SELECT COUNT(*) FROM students WHERE account_status = 'Inactive'
            "),
            'total_departments'   => $this->count("
                SELECT COUNT(*) FROM departments WHERE is_active = 1
            "),
            'total_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) FROM applications a
                INNER JOIN competition_coordinators cc ON cc.competition_id = a.competition_id
                WHERE cc.user_id = :user_id
            ", [':user_id' => $userId]),
            'pending_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) FROM applications a
                INNER JOIN competition_coordinators cc ON cc.competition_id = a.competition_id
                WHERE cc.user_id = :user_id AND a.application_status = 'Pending'
            ", [':user_id' => $userId]),
            'approved_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) FROM applications a
                INNER JOIN competition_coordinators cc ON cc.competition_id = a.competition_id
                WHERE cc.user_id = :user_id AND a.application_status = 'Approved'
            ", [':user_id' => $userId]),
            'rejected_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) FROM applications a
                INNER JOIN competition_coordinators cc ON cc.competition_id = a.competition_id
                WHERE cc.user_id = :user_id AND a.application_status = 'Rejected'
            ", [':user_id' => $userId]),
            'withdrawn_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) FROM applications a
                INNER JOIN competition_coordinators cc ON cc.competition_id = a.competition_id
                WHERE cc.user_id = :user_id AND a.application_status = 'Withdrawn'
            ", [':user_id' => $userId]),
            'cancelled_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) FROM applications a
                INNER JOIN competition_coordinators cc ON cc.competition_id = a.competition_id
                WHERE cc.user_id = :user_id AND a.application_status = 'Cancelled'
            ", [':user_id' => $userId]),
            'today_registrations' => $this->countWith("
                SELECT COUNT(DISTINCT a.application_id) FROM applications a
                INNER JOIN competition_coordinators cc ON cc.competition_id = a.competition_id
                WHERE cc.user_id = :user_id AND DATE(a.applied_at) = CURDATE()
            ", [':user_id' => $userId]),
            'assigned_competitions' => $this->countWith("
                SELECT COUNT(*) FROM competition_coordinators
                WHERE user_id = :user_id
            ", [':user_id' => $userId]),

            // --- FIC / Judge Assignment Stats ---
            'fic_assigned_events' => $this->countWith("
                SELECT COUNT(*) FROM faculty_assignments
                WHERE user_id = :user_id AND is_active = 1
            ", [':user_id' => $userId]),

            'fic_no_judge' => $this->countWith("
                SELECT COUNT(DISTINCT fa.symposium_event_id)
                FROM faculty_assignments fa
                WHERE fa.user_id = :user_id AND fa.is_active = 1
                  AND NOT EXISTS (
                      SELECT 1 FROM competition_judges cj
                      WHERE cj.symposium_event_id = fa.symposium_event_id AND cj.is_active = 1
                  )
            ", [':user_id' => $userId]),

            'judge_assigned_events' => $this->countWith("
                SELECT COUNT(*) FROM competition_judges
                WHERE user_id = :user_id AND is_active = 1
                  AND symposium_event_id IS NOT NULL
            ", [':user_id' => $userId]),
        ];
    }

    // =========================================================================
    // STUDENT COORDINATOR DASHBOARD
    // =========================================================================

    /**
     * ---------------------------------------------------------------------
     * Retrieve statistics for the Student Coordinator dashboard.
     *
     * Scoped to competitions where this user is assigned as coordinator.
     *
     * @param int $userId The Student Coordinator's user_id from session.
     *
     * @return array<string, int>
     * ---------------------------------------------------------------------
     */
    public function getStudentCoordinatorStats(int $userId): array
    {
        return [
            'assigned_competitions' => $this->countWith("
                SELECT COUNT(*) FROM competition_coordinators
                WHERE user_id = :user_id
            ", [':user_id' => $userId]),

            'pending_registrations' => $this->countWith("
                SELECT COUNT(*) FROM applications a
                INNER JOIN competition_coordinators cc
                    ON cc.competition_id = a.competition_id
                WHERE cc.user_id = :user_id
                  AND a.application_status = 'Pending'
            ", [':user_id' => $userId]),

            'approved_registrations' => $this->countWith("
                SELECT COUNT(*) FROM applications a
                INNER JOIN competition_coordinators cc
                    ON cc.competition_id = a.competition_id
                WHERE cc.user_id = :user_id
                  AND a.application_status = 'Approved'
            ", [':user_id' => $userId]),

            'total_participants'  => $this->countWith("
                SELECT COUNT(DISTINCT a.student_id) FROM applications a
                INNER JOIN competition_coordinators cc
                    ON cc.competition_id = a.competition_id
                WHERE cc.user_id = :user_id
            ", [':user_id' => $userId]),
        ];
    }

    // =========================================================================
    // FACULTY IN-CHARGE / JUDGE STATS
    // =========================================================================

    /**
     * ---------------------------------------------------------------------
     * Get FIC and Judge assignment statistics for a user.
     *
     * Used by Staff and HOD dashboards to show personal assignment counts.
     *
     * @param int $userId
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getFicStats(int $userId): array
    {
        return [
            'fic_assigned_events' => $this->countWith("
                SELECT COUNT(*)
                FROM faculty_assignments
                WHERE user_id = :user_id AND is_active = 1
            ", [':user_id' => $userId]),

            'fic_with_judge' => $this->countWith("
                SELECT COUNT(DISTINCT fa.symposium_event_id)
                FROM faculty_assignments fa
                WHERE fa.user_id = :user_id
                  AND fa.is_active = 1
                  AND EXISTS (
                      SELECT 1 FROM competition_judges cj
                      WHERE cj.symposium_event_id = fa.symposium_event_id
                        AND cj.is_active = 1
                  )
            ", [':user_id' => $userId]),

            'fic_no_judge' => $this->countWith("
                SELECT COUNT(DISTINCT fa.symposium_event_id)
                FROM faculty_assignments fa
                WHERE fa.user_id = :user_id
                  AND fa.is_active = 1
                  AND NOT EXISTS (
                      SELECT 1 FROM competition_judges cj
                      WHERE cj.symposium_event_id = fa.symposium_event_id
                        AND cj.is_active = 1
                  )
            ", [':user_id' => $userId]),

            'judge_assigned_events' => $this->countWith("
                SELECT COUNT(*)
                FROM competition_judges
                WHERE user_id = :user_id
                  AND is_active = 1
                  AND symposium_event_id IS NOT NULL
            ", [':user_id' => $userId]),
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * Get upcoming and today events for a user based on their role.
     *
     * @param int $userId
     * @param string $role
     * @param int $departmentId
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getUpcomingEvents(int $userId, string $role, int $departmentId = 0): array
    {
        $sql = "
            SELECT se.*, s.title as symposium_title, v.venue_name
            FROM symposium_events se
            JOIN symposiums s ON s.symposium_id = se.symposium_id
            LEFT JOIN venues v ON v.venue_id = se.venue_id
            WHERE se.status != 'Cancelled'
              AND se.event_date >= CURDATE()
        ";
        
        $params = [];
        
        if ($role === 'HOD') {
            $stmt = $this->db->prepare("
                SELECT d.department_id 
                FROM departments d 
                LEFT JOIN department_approvers da ON da.department_id = d.department_id 
                WHERE COALESCE(da.approver_department_id, d.department_id) = :dept_id
            ");
            $stmt->execute([':dept_id' => $departmentId]);
            $deptIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (empty($deptIds)) {
                $deptIds = [$departmentId];
            }
            $inClause = implode(',', array_map('intval', $deptIds));
            
            $sql .= " AND s.symposium_id IN (SELECT symposium_id FROM symposium_departments WHERE department_id IN ($inClause))";
        } elseif ($role === 'Staff Coordinator') {
            // Staff Coordinators have global access to all symposiums, 
            // so they should see all upcoming events.
            // No extra WHERE conditions are needed.
        } elseif ($role === 'Staff') {
            $sql .= " AND (
                se.symposium_event_id IN (SELECT symposium_event_id FROM faculty_assignments WHERE user_id = :user_id AND is_active = 1)
                OR 
                se.symposium_event_id IN (SELECT symposium_event_id FROM competition_judges WHERE user_id = :user_id AND is_active = 1)
            )";
            $params['user_id'] = $userId;
        } else {
            return [];
        }
        
        $sql .= " ORDER BY se.event_date ASC, se.start_time ASC LIMIT 5";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * ---------------------------------------------------------------------
     * Run a COUNT query with no parameters.
     *
     * Returns 0 if the query fails (e.g. table doesn't exist yet).
     *
     * @param string $sql
     *
     * @return int
     * ---------------------------------------------------------------------
     */
    private function count(string $sql): int
    {
        try {
            $stmt = $this->db->query(trim($sql));

            return $stmt !== false
                ? (int) $stmt->fetchColumn()
                : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * ---------------------------------------------------------------------
     * Run a COUNT query with named parameters.
     *
     * Returns 0 if the query fails (e.g. table doesn't exist yet).
     *
     * @param string $sql
     * @param array  $params Named parameter bindings.
     *
     * @return int
     * ---------------------------------------------------------------------
     */
    private function countWith(string $sql, array $params): int
    {
        try {
            $stmt = $this->db->prepare(trim($sql));
            $stmt->execute($params);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }
}
