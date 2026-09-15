<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : FacultyAssignmentModel.php
 * Location    : app/Models/
 * Description : Database operations for the faculty_assignments table.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Assign a user as Faculty In-Charge for a symposium event
 * • Remove (soft-delete) a faculty assignment
 * • Replace an existing faculty assignment (atomic)
 * • Fetch faculty for a specific event
 * • Fetch all events assigned to a specific user
 * • Conflict detection queries (date-based, cross-table aware)
 *
 * Architecture Note
 * -------------------------------------------------------------------------
 * This model handles ONLY faculty_assignments.
 * Conflict detection LOGIC lives in ResourceAllocationService.
 * This model only exposes the raw data queries needed by the service.
 *
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use PDO;

final class FacultyAssignmentModel extends BaseModel
{
    // =========================================================================
    // ASSIGN
    // =========================================================================

    /**
     * Assign a user as Faculty In-Charge for a symposium event.
     *
     * If an inactive record already exists for this user+event pair,
     * it will be reactivated (mirrors competition_judges pattern).
     *
     * @param int    $symposiumEventId
     * @param int    $userId
     * @param int    $assignedBy
     * @param string $role   Default 'Faculty Incharge' (future-ready for other roles)
     * @param string $notes
     *
     * @return bool
     */
    public function assign(
        int    $symposiumEventId,
        int    $userId,
        int    $assignedBy,
        string $role  = 'Faculty Incharge',
        string $notes = ''
    ): bool {
        // Check for existing record (active or inactive)
        $check = $this->db->prepare("
            SELECT assignment_id, is_active
            FROM faculty_assignments
            WHERE symposium_event_id = :event_id AND user_id = :user_id
            LIMIT 1
        ");
        $check->execute(['event_id' => $symposiumEventId, 'user_id' => $userId]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ((int) $existing['is_active'] === 0) {
                // Reactivate the soft-deleted record
                $update = $this->db->prepare("
                    UPDATE faculty_assignments
                    SET is_active       = 1,
                        assigned_by     = :assigned_by,
                        assigned_at     = CURRENT_TIMESTAMP,
                        removed_by      = NULL,
                        removed_at      = NULL,
                        assignment_role = :role,
                        notes           = :notes
                    WHERE assignment_id = :id
                ");
                return $update->execute([
                    'assigned_by' => $assignedBy,
                    'role'        => $role,
                    'notes'       => $notes,
                    'id'          => (int) $existing['assignment_id'],
                ]);
            }
            return false; // Already active — caller should check first
        }

        // New record
        $insert = $this->db->prepare("
            INSERT INTO faculty_assignments
                (symposium_event_id, user_id, assignment_role, assigned_by, notes)
            VALUES
                (:event_id, :user_id, :role, :assigned_by, :notes)
        ");

        return $insert->execute([
            'event_id'    => $symposiumEventId,
            'user_id'     => $userId,
            'role'        => $role,
            'assigned_by' => $assignedBy,
            'notes'       => $notes,
        ]);
    }

    // =========================================================================
    // REMOVE
    // =========================================================================

    /**
     * Soft-remove a faculty assignment.
     *
     * @param int $symposiumEventId
     * @param int $userId
     * @param int $removedBy
     *
     * @return bool
     */
    public function remove(int $symposiumEventId, int $userId, int $removedBy): bool
    {
        $stmt = $this->db->prepare("
            UPDATE faculty_assignments
            SET is_active  = 0,
                removed_by = :removed_by,
                removed_at = CURRENT_TIMESTAMP
            WHERE symposium_event_id = :event_id
              AND user_id            = :user_id
              AND is_active          = 1
        ");

        return $stmt->execute([
            'removed_by' => $removedBy,
            'event_id'   => $symposiumEventId,
            'user_id'    => $userId,
        ]);
    }

    /**
     * Remove ALL active faculty assignments for an event.
     * Used internally by replace().
     *
     * @param int $symposiumEventId
     * @param int $removedBy
     *
     * @return bool
     */
    public function removeAllForEvent(int $symposiumEventId, int $removedBy): bool
    {
        $stmt = $this->db->prepare("
            UPDATE faculty_assignments
            SET is_active  = 0,
                removed_by = :removed_by,
                removed_at = CURRENT_TIMESTAMP
            WHERE symposium_event_id = :event_id
              AND is_active          = 1
        ");

        return $stmt->execute([
            'removed_by' => $removedBy,
            'event_id'   => $symposiumEventId,
        ]);
    }

    // =========================================================================
    // READ — By Event
    // =========================================================================

    /**
     * Get all ACTIVE faculty assignments for a symposium event.
     *
     * @param int $symposiumEventId
     *
     * @return array
     */
    public function getForEvent(int $symposiumEventId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                fa.assignment_id,
                fa.symposium_event_id,
                fa.user_id,
                fa.assignment_role,
                fa.assigned_at,
                fa.notes,
                fa.is_active,
                u.full_name,
                u.email,
                u.phone,
                u.role       AS user_role,
                u.signature_path,
                d.department_name,
                d.department_code,
                ab.full_name AS assigned_by_name
            FROM faculty_assignments fa
            JOIN  users        u  ON u.user_id        = fa.user_id
            LEFT JOIN departments d  ON d.department_id   = u.department_id
            LEFT JOIN users       ab ON ab.user_id        = fa.assigned_by
            WHERE fa.symposium_event_id = :event_id
              AND fa.is_active          = 1
            ORDER BY u.full_name ASC
        ");
        $stmt->execute(['event_id' => $symposiumEventId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get ALL assignments (active + removed) for audit trail.
     *
     * @param int $symposiumEventId
     *
     * @return array
     */
    public function getHistoryForEvent(int $symposiumEventId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                fa.*,
                u.full_name,
                u.email,
                u.role      AS user_role,
                d.department_name,
                ab.full_name  AS assigned_by_name,
                rb.full_name  AS removed_by_name
            FROM faculty_assignments fa
            JOIN  users u   ON u.user_id   = fa.user_id
            LEFT JOIN departments d   ON d.department_id = u.department_id
            LEFT JOIN users       ab  ON ab.user_id      = fa.assigned_by
            LEFT JOIN users       rb  ON rb.user_id      = fa.removed_by
            WHERE fa.symposium_event_id = :event_id
            ORDER BY fa.is_active DESC, fa.assigned_at DESC
        ");
        $stmt->execute(['event_id' => $symposiumEventId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // =========================================================================
    // READ — By User
    // =========================================================================

    /**
     * Get all ACTIVE events where a user is assigned as Faculty In-Charge.
     *
     * @param int $userId
     *
     * @return array
     */
    public function getEventsByUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                fa.assignment_id,
                fa.assignment_role,
                fa.assigned_at,
                se.symposium_event_id,
                se.event_name,
                se.event_date,
                se.start_time,
                se.end_time,
                se.session,
                s.symposium_id,
                s.title   AS symposium_title,
                v.venue_name
            FROM faculty_assignments fa
            JOIN symposium_events se ON se.symposium_event_id = fa.symposium_event_id
            JOIN symposiums       s  ON s.symposium_id        = se.symposium_id
            LEFT JOIN venues      v  ON v.venue_id            = se.venue_id
            WHERE fa.user_id   = :user_id
              AND fa.is_active = 1
            ORDER BY se.event_date ASC, se.start_time ASC
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // =========================================================================
    // CHECKS
    // =========================================================================

    /**
     * Check if a user is already actively assigned to a specific event.
     *
     * @param int $symposiumEventId
     * @param int $userId
     *
     * @return bool
     */
    public function isAssigned(int $symposiumEventId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM faculty_assignments
            WHERE symposium_event_id = :event_id
              AND user_id            = :user_id
              AND is_active          = 1
            LIMIT 1
        ");
        $stmt->execute(['event_id' => $symposiumEventId, 'user_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Find all active faculty assignments for a user on a given date.
     *
     * Used by ResourceAllocationService for conflict detection.
     * Returns details of the conflicting event(s).
     *
     * @param int      $userId
     * @param string   $date           Format: Y-m-d
     * @param int|null $excludeEventId Exclude this event from the check (for replace)
     *
     * @return array  Each row describes a conflicting assignment.
     */
    public function getActiveAssignmentsByDate(
        int    $userId,
        string $date,
        ?int   $excludeEventId = null
    ): array {
        $sql = "
            SELECT
                fa.assignment_id,
                fa.assignment_role,
                se.symposium_event_id,
                se.event_name,
                se.event_date,
                se.start_time,
                se.end_time,
                se.session,
                v.venue_name
            FROM faculty_assignments fa
            JOIN symposium_events se ON se.symposium_event_id = fa.symposium_event_id
            LEFT JOIN venues      v  ON v.venue_id            = se.venue_id
            WHERE fa.user_id   = :user_id
              AND se.event_date = :event_date
              AND fa.is_active  = 1
        ";

        $params = ['user_id' => $userId, 'event_date' => $date];

        if ($excludeEventId !== null) {
            $sql .= " AND fa.symposium_event_id != :exclude_id";
            $params['exclude_id'] = $excludeEventId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // =========================================================================
    // REPORT DATA
    // =========================================================================

    /**
     * Get all faculty assignments for an entire symposium.
     * Used by the Faculty Report.
     *
     * @param int $symposiumId
     *
     * @return array
     */
    public function getBySymposium(int $symposiumId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                fa.assignment_id,
                fa.assignment_role,
                fa.assigned_at,
                fa.notes,
                se.symposium_event_id,
                se.event_name,
                se.event_date,
                se.start_time,
                se.end_time,
                se.session,
                u.full_name,
                u.email,
                u.phone,
                u.role        AS user_role,
                u.signature_path,
                d.department_name,
                v.venue_name,
                ab.full_name  AS assigned_by_name
            FROM faculty_assignments fa
            JOIN symposium_events se ON se.symposium_event_id = fa.symposium_event_id
            JOIN users            u  ON u.user_id             = fa.user_id
            LEFT JOIN departments d  ON d.department_id       = u.department_id
            LEFT JOIN venues      v  ON v.venue_id            = se.venue_id
            LEFT JOIN users       ab ON ab.user_id            = fa.assigned_by
            WHERE se.symposium_id = :symposium_id
              AND fa.is_active    = 1
            ORDER BY se.event_date ASC, se.start_time ASC, u.full_name ASC
        ");
        $stmt->execute(['symposium_id' => $symposiumId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    // =========================================================================
    // FIC PORTAL — ASSIGNED EVENTS FOR A USER
    // =========================================================================

    /**
     * Get all events where this user is an active Faculty In-Charge.
     *
     * Returns event details joined with symposium, venue, and
     * a count of active judges assigned to each event.
     *
     * @param int $userId
     *
     * @return array
     */
    public function getAssignedEventsForUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                fa.assignment_id,
                fa.assignment_role,
                fa.assigned_at,
                se.symposium_event_id,
                se.event_name,
                se.event_code,
                se.category,
                se.participation_type,
                se.event_date,
                se.session,
                se.start_time,
                se.end_time,
                se.status           AS event_status,
                se.schedule_status,
                se.supports_evaluation,
                se.judging_method,
                se.maximum_score,
                s.symposium_id,
                s.title             AS symposium_title,
                s.symposium_code,
                s.status            AS symposium_status,
                v.venue_name,
                v.venue_code,
                v.building_name,
                v.floor,
                v.seating_capacity,
                (
                    SELECT COUNT(*)
                    FROM competition_judges cj
                    WHERE cj.symposium_event_id = se.symposium_event_id
                      AND cj.is_active = 1
                ) AS judge_count,
                (
                    SELECT COUNT(*)
                    FROM applications a
                    WHERE a.symposium_event_id = se.symposium_event_id
                ) AS registration_count
            FROM faculty_assignments fa
            JOIN symposium_events se ON se.symposium_event_id = fa.symposium_event_id
            JOIN symposiums       s  ON s.symposium_id        = se.symposium_id
            LEFT JOIN venues      v  ON v.venue_id            = se.venue_id
            WHERE fa.user_id   = :user_id
              AND fa.is_active = 1
            ORDER BY se.event_date ASC, se.start_time ASC
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
