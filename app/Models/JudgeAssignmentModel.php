<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : JudgeAssignmentModel.php
 * Location    : app/Models/
 * Description : Clean, symposium-event-scoped judge assignment model.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Assign judges to symposium events (symposium_events context ONLY)
 * • Remove (soft-delete) judge assignments
 * • Fetch judges for an event
 * • Fetch events assigned to a judge
 * • Conflict detection queries (date-based)
 * • Report data for an entire symposium
 *
 * Architecture Note
 * -------------------------------------------------------------------------
 * This model is the Resource Allocation Module's judge model.
 * It writes to the EXISTING competition_judges table using the
 * symposium_event_id column (never competition_id — per design decision).
 *
 * The existing JudgeModel.php is untouched for backward compatibility
 * with EvaluationController and JudgeController.
 *
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use PDO;

final class JudgeAssignmentModel extends BaseModel
{
    // =========================================================================
    // ASSIGN
    // =========================================================================

    /**
     * Assign a user as a judge for a symposium event.
     *
     * Uses competition_judges with symposium_event_id (NOT competition_id).
     * Reactivates soft-deleted records instead of inserting duplicates.
     *
     * @param int    $symposiumEventId
     * @param int    $userId
     * @param int    $assignedBy
     * @param string $isCoordinatorJudge  'Yes' if the judge is also a coordinator
     *
     * @return bool
     */
    public function assign(
        int    $symposiumEventId,
        int    $userId,
        int    $assignedBy,
        string $isCoordinatorJudge = 'No'
    ): bool {
        $check = $this->db->prepare("
            SELECT judge_assignment_id, is_active
            FROM competition_judges
            WHERE symposium_event_id = :event_id AND user_id = :user_id
            LIMIT 1
        ");
        $check->execute(['event_id' => $symposiumEventId, 'user_id' => $userId]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            if ((int) $existing['is_active'] === 0) {
                $update = $this->db->prepare("
                    UPDATE competition_judges
                    SET is_active             = 1,
                        assigned_by           = :assigned_by,
                        assigned_at           = CURRENT_TIMESTAMP,
                        removed_by            = NULL,
                        removed_at            = NULL,
                        is_coordinator_judge  = :is_coordinator_judge
                    WHERE judge_assignment_id = :id
                ");
                return $update->execute([
                    'assigned_by'          => $assignedBy,
                    'is_coordinator_judge' => $isCoordinatorJudge,
                    'id'                   => (int) $existing['judge_assignment_id'],
                ]);
            }
            return false; // Already active
        }

        $insert = $this->db->prepare("
            INSERT INTO competition_judges
                (symposium_event_id, user_id, is_coordinator_judge, assigned_by)
            VALUES
                (:event_id, :user_id, :is_coordinator_judge, :assigned_by)
        ");

        return $insert->execute([
            'event_id'             => $symposiumEventId,
            'user_id'              => $userId,
            'is_coordinator_judge' => $isCoordinatorJudge,
            'assigned_by'          => $assignedBy,
        ]);
    }

    // =========================================================================
    // REMOVE
    // =========================================================================

    /**
     * Soft-remove a judge assignment for a symposium event.
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
            UPDATE competition_judges
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
     * Remove ALL active judge assignments for an event.
     * Used by replace().
     *
     * @param int $symposiumEventId
     * @param int $removedBy
     *
     * @return bool
     */
    public function removeAllForEvent(int $symposiumEventId, int $removedBy): bool
    {
        $stmt = $this->db->prepare("
            UPDATE competition_judges
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
     * Get all ACTIVE judges for a symposium event.
     *
     * @param int $symposiumEventId
     *
     * @return array
     */
    public function getForEvent(int $symposiumEventId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                cj.judge_assignment_id,
                cj.symposium_event_id,
                cj.user_id,
                cj.is_coordinator_judge,
                cj.assigned_at,
                cj.is_active,
                u.full_name,
                u.email,
                u.phone,
                u.role         AS user_role,
                u.signature_path,
                d.department_name,
                d.department_code,
                ab.full_name   AS assigned_by_name
            FROM competition_judges cj
            JOIN  users        u  ON u.user_id        = cj.user_id
            LEFT JOIN departments d  ON d.department_id   = u.department_id
            LEFT JOIN users       ab ON ab.user_id        = cj.assigned_by
            WHERE cj.symposium_event_id = :event_id
              AND cj.is_active          = 1
            ORDER BY u.full_name ASC
        ");
        $stmt->execute(['event_id' => $symposiumEventId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get ALL judge assignment history for an event (including removed).
     *
     * @param int $symposiumEventId
     *
     * @return array
     */
    public function getHistoryForEvent(int $symposiumEventId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                cj.*,
                u.full_name,
                u.email,
                u.role         AS user_role,
                d.department_name,
                ab.full_name   AS assigned_by_name,
                rb.full_name   AS removed_by_name
            FROM competition_judges cj
            JOIN  users u   ON u.user_id   = cj.user_id
            LEFT JOIN departments d   ON d.department_id = u.department_id
            LEFT JOIN users       ab  ON ab.user_id      = cj.assigned_by
            LEFT JOIN users       rb  ON rb.user_id      = cj.removed_by
            WHERE cj.symposium_event_id = :event_id
            ORDER BY cj.is_active DESC, cj.assigned_at DESC
        ");
        $stmt->execute(['event_id' => $symposiumEventId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // =========================================================================
    // READ — By User
    // =========================================================================

    /**
     * Get all ACTIVE symposium events where a user is assigned as judge.
     *
     * @param int $userId
     *
     * @return array
     */
    public function getEventsByUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                cj.judge_assignment_id,
                cj.is_coordinator_judge,
                cj.assigned_at,
                se.symposium_event_id,
                se.event_name,
                se.event_date,
                se.start_time,
                se.end_time,
                se.session,
                s.symposium_id,
                s.title    AS symposium_title,
                v.venue_name
            FROM competition_judges cj
            JOIN symposium_events se ON se.symposium_event_id = cj.symposium_event_id
            JOIN symposiums       s  ON s.symposium_id        = se.symposium_id
            LEFT JOIN venues      v  ON v.venue_id            = se.venue_id
            WHERE cj.user_id       = :user_id
              AND cj.is_active     = 1
              AND cj.symposium_event_id IS NOT NULL
            ORDER BY se.event_date ASC, se.start_time ASC
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // =========================================================================
    // CHECKS
    // =========================================================================

    /**
     * Check if a user is already an active judge for a symposium event.
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
            FROM competition_judges
            WHERE symposium_event_id = :event_id
              AND user_id            = :user_id
              AND is_active          = 1
            LIMIT 1
        ");
        $stmt->execute(['event_id' => $symposiumEventId, 'user_id' => $userId]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Find all active judge assignments for a user on a given date.
     *
     * Used by ResourceAllocationService for cross-role conflict detection.
     * Scoped to symposium_event_id records only (no competition_id rows).
     *
     * @param int      $userId
     * @param string   $date           Format: Y-m-d
     * @param int|null $excludeEventId Exclude this event (for replace)
     *
     * @return array
     */
    public function getActiveAssignmentsByDate(
        int    $userId,
        string $date,
        ?int   $excludeEventId = null
    ): array {
        $sql = "
            SELECT
                cj.judge_assignment_id,
                cj.is_coordinator_judge,
                se.symposium_event_id,
                se.event_name,
                se.event_date,
                se.start_time,
                se.end_time,
                se.session,
                v.venue_name
            FROM competition_judges cj
            JOIN symposium_events se ON se.symposium_event_id = cj.symposium_event_id
            LEFT JOIN venues      v  ON v.venue_id            = se.venue_id
            WHERE cj.user_id              = :user_id
              AND se.event_date           = :event_date
              AND cj.is_active            = 1
              AND cj.symposium_event_id   IS NOT NULL
        ";

        $params = ['user_id' => $userId, 'event_date' => $date];

        if ($excludeEventId !== null) {
            $sql .= " AND cj.symposium_event_id != :exclude_id";
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
     * Get all ACTIVE judge assignments for an entire symposium.
     * Used by the Judge Report.
     *
     * @param int $symposiumId
     *
     * @return array
     */
    public function getBySymposium(int $symposiumId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                cj.judge_assignment_id,
                cj.is_coordinator_judge,
                cj.assigned_at,
                se.symposium_event_id,
                se.event_name,
                se.event_date,
                se.start_time,
                se.end_time,
                se.session,
                u.full_name,
                u.email,
                u.phone,
                u.role         AS user_role,
                u.signature_path,
                d.department_name,
                v.venue_name,
                ab.full_name   AS assigned_by_name
            FROM competition_judges cj
            JOIN symposium_events se ON se.symposium_event_id = cj.symposium_event_id
            JOIN users            u  ON u.user_id             = cj.user_id
            LEFT JOIN departments d  ON d.department_id       = u.department_id
            LEFT JOIN venues      v  ON v.venue_id            = se.venue_id
            LEFT JOIN users       ab ON ab.user_id            = cj.assigned_by
            WHERE se.symposium_id      = :symposium_id
              AND cj.is_active         = 1
              AND cj.symposium_event_id IS NOT NULL
            ORDER BY se.event_date ASC, se.start_time ASC, u.full_name ASC
        ");
        $stmt->execute(['symposium_id' => $symposiumId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    // =========================================================================
    // JUDGE PORTAL — ASSIGNED EVENTS FOR A USER
    // =========================================================================

    /**
     * Get all events where this user is an active judge.
     *
     * Scoped to symposium_event_id records only.
     *
     * @param int $userId
     *
     * @return array
     */
    public function getAssignedEventsForUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                cj.judge_assignment_id,
                cj.is_coordinator_judge,
                cj.assigned_at,
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
                    FROM applications a
                    WHERE a.symposium_event_id = se.symposium_event_id
                ) AS registration_count
            FROM competition_judges cj
            JOIN symposium_events se ON se.symposium_event_id = cj.symposium_event_id
            JOIN symposiums       s  ON s.symposium_id        = se.symposium_id
            LEFT JOIN venues      v  ON v.venue_id            = se.venue_id
            WHERE cj.user_id              = :user_id
              AND cj.is_active            = 1
              AND cj.symposium_event_id   IS NOT NULL
            ORDER BY se.event_date ASC, se.start_time ASC
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
