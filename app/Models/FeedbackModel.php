<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : FeedbackModel.php
 * Location    : app/Models/
 * Description : Data access for event_feedback table.
 *
 * Architecture
 * -------------------------------------------------------------------------
 * Feedback is IDENTIFIED — each row is associated with a specific student_id.
 * Authorized staff can see full student identity per the business requirement.
 *
 * This model is responsible ONLY for database operations.
 * All business logic (eligibility, authorization) lives in FeedbackService.
 *
 * Project     : NexusCore
 * Refactored  : 2026-09-23 — removed anonymous hash architecture,
 *               replaced with student_id direct association.
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use PDO;

final class FeedbackModel extends BaseModel
{
    // =========================================================================
    // WRITE OPERATIONS
    // =========================================================================

    /**
     * Insert a new identified feedback record.
     *
     * @param int         $eventId
     * @param int         $studentId
     * @param int         $rating   1–5
     * @param string|null $review
     * @return int  Inserted feedback_id, or 0 on failure
     */
    public function insertFeedback(int $eventId, int $studentId, int $rating, ?string $review): int
    {
        $sql = '
            INSERT INTO event_feedback
                (symposium_event_id, student_id, rating, review)
            VALUES
                (:event_id, :student_id, :rating, :review)
        ';

        $stmt = $this->db->prepare($sql);
        $ok   = $stmt->execute([
            'event_id'   => $eventId,
            'student_id' => $studentId,
            'rating'     => $rating,
            'review'     => $review,
        ]);

        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    // =========================================================================
    // EXISTENCE / DUPLICATE CHECKS
    // =========================================================================

    /**
     * Check if a student has already submitted feedback for this event.
     *
     * @param int $eventId
     * @param int $studentId
     * @return bool
     */
    public function existsByStudent(int $eventId, int $studentId): bool
    {
        $sql = '
            SELECT 1 FROM event_feedback
            WHERE symposium_event_id = :event_id
              AND student_id         = :student_id
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'event_id'   => $eventId,
            'student_id' => $studentId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Bulk check whether a student has submitted feedback for multiple events.
     * Returns a map of [event_id => bool].
     *
     * Avoids N+1 queries on the My Registrations / Feedback page.
     *
     * @param int   $studentId
     * @param array $eventIds
     * @return array<int, bool>
     */
    public function existsByStudentBulk(int $studentId, array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $sql = "
            SELECT symposium_event_id
            FROM event_feedback
            WHERE student_id = ?
              AND symposium_event_id IN ($placeholders)
        ";

        $params = array_merge([$studentId], array_values($eventIds));
        $stmt   = $this->db->prepare($sql);
        $stmt->execute($params);

        $found  = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $result = [];
        foreach ($eventIds as $eid) {
            $result[(int) $eid] = in_array((string) $eid, $found, true)
                                  || in_array($eid, $found, true);
        }

        return $result;
    }

    // =========================================================================
    // STUDENT'S OWN FEEDBACK (for student-facing pages)
    // =========================================================================

    /**
     * Get a student's submitted rating for a single event.
     *
     * @param int $eventId
     * @param int $studentId
     * @return int|null  null if not submitted
     */
    public function getStudentRating(int $eventId, int $studentId): ?int
    {
        $sql = '
            SELECT rating FROM event_feedback
            WHERE symposium_event_id = :event_id
              AND student_id         = :student_id
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'event_id'   => $eventId,
            'student_id' => $studentId,
        ]);

        $rating = $stmt->fetchColumn();
        return $rating !== false ? (int) $rating : null;
    }

    /**
     * Bulk fetch submitted ratings for a student across multiple events.
     * Returns a map of [event_id => rating].
     *
     * @param int   $studentId
     * @param array $eventIds
     * @return array<int, int>
     */
    public function getRatingsByStudentBulk(int $studentId, array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $sql = "
            SELECT symposium_event_id, rating
            FROM event_feedback
            WHERE student_id = ?
              AND symposium_event_id IN ($placeholders)
        ";

        $params = array_merge([$studentId], array_values($eventIds));
        $stmt   = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[(int) $row['symposium_event_id']] = (int) $row['rating'];
        }

        return $result;
    }

    // =========================================================================
    // STAFF-FACING REVIEW RETRIEVAL (identified — with student info)
    // =========================================================================

    /**
     * Get paginated feedback for an event, with full student identity.
     *
     * Only returns: name, register_number, department_name, academic_year,
     * rating, review. No email, phone, DOB, password or other PII.
     *
     * @param int $eventId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getEventFeedback(int $eventId, int $limit = 50, int $offset = 0): array
    {
        $sql = '
            SELECT
                s.full_name,
                s.register_number,
                d.department_name,
                s.academic_year,
                ef.rating,
                ef.review,
                ef.created_at
            FROM event_feedback ef
            INNER JOIN students    s ON s.student_id    = ef.student_id
            INNER JOIN departments d ON d.department_id = s.department_id
            WHERE ef.symposium_event_id = :event_id
            ORDER BY ef.feedback_id ASC
            LIMIT :limit OFFSET :offset
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',    $limit,   PDO::PARAM_INT);
        $stmt->bindValue(':offset',   $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // AGGREGATE / SUMMARY QUERIES
    // =========================================================================

    /**
     * Get aggregate summary: total responses and average rating.
     *
     * @param int $eventId
     * @return array{response_count: int, avg_rating: float}
     */
    public function getSummaryByEvent(int $eventId): array
    {
        $sql = '
            SELECT
                COUNT(*) AS response_count,
                AVG(rating) AS avg_rating
            FROM event_feedback
            WHERE symposium_event_id = :event_id
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['event_id' => $eventId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'response_count' => (int)   ($result['response_count'] ?? 0),
            'avg_rating'     => (float) ($result['avg_rating']     ?? 0.0),
        ];
    }

    /**
     * Get rating distribution (1-star through 5-star counts).
     *
     * @param int $eventId
     * @return array<int, int>  [1 => n, 2 => n, 3 => n, 4 => n, 5 => n]
     */
    public function getRatingDistribution(int $eventId): array
    {
        $sql = '
            SELECT rating, COUNT(*) AS cnt
            FROM event_feedback
            WHERE symposium_event_id = :event_id
            GROUP BY rating
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['event_id' => $eventId]);

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $distribution[(int) $row['rating']] = (int) $row['cnt'];
        }

        return $distribution;
    }

    /**
     * Count eligible participants (Present OR Late) from the finalized session.
     *
     * Denominator for response rate calculation.
     *
     * @param int $eventId
     * @return int
     */
    public function getEligibleParticipantCount(int $eventId): int
    {
        $sql = '
            SELECT COUNT(DISTINCT ar.student_id) AS eligible_count
            FROM attendance_records ar
            INNER JOIN attendance_sessions ats ON ats.session_id = ar.session_id
            WHERE ar.symposium_event_id  = :event_id
              AND ats.symposium_event_id = :event_id2
              AND ats.status             = \'Closed\'
              AND ar.attendance_status   IN (\'Present\', \'Late\')
              AND ats.session_id = (
                  SELECT session_id FROM attendance_sessions
                  WHERE symposium_event_id = :event_id3
                    AND status = \'Closed\'
                  ORDER BY closed_at DESC
                  LIMIT 1
              )
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'event_id'  => $eventId,
            'event_id2' => $eventId,
            'event_id3' => $eventId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Get response counts for multiple events (for the index listing page).
     *
     * @param array $eventIds
     * @return array<int, int>  [event_id => response_count]
     */
    public function getResponseCountForEvents(array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $sql = "
            SELECT symposium_event_id, COUNT(*) AS response_count
            FROM event_feedback
            WHERE symposium_event_id IN ($placeholders)
            GROUP BY symposium_event_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($eventIds));

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[(int) $row['symposium_event_id']] = (int) $row['response_count'];
        }

        return $result;
    }

    // =========================================================================
    // ATTENDANCE HELPERS (used by FeedbackService — no change from previous)
    // =========================================================================

    /**
     * Get a student's attendance status for a specific session.
     *
     * @param int $studentId
     * @param int $eventId
     * @param int $sessionId
     * @return string|null
     */
    public function getStudentAttendanceStatus(int $studentId, int $eventId, int $sessionId): ?string
    {
        $sql = '
            SELECT attendance_status
            FROM attendance_records
            WHERE student_id          = :student_id
              AND symposium_event_id  = :event_id
              AND session_id          = :session_id
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'student_id' => $studentId,
            'event_id'   => $eventId,
            'session_id' => $sessionId,
        ]);

        $status = $stmt->fetchColumn();
        return $status !== false ? (string) $status : null;
    }

    /**
     * Bulk fetch attendance statuses for a student across multiple sessions.
     * Returns a map of [session_id => attendance_status].
     *
     * @param int   $studentId
     * @param array $sessionIds
     * @return array<int, string>
     */
    public function getStudentAttendanceStatusesForSessions(int $studentId, array $sessionIds): array
    {
        if (empty($sessionIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
        $sql = "
            SELECT session_id, attendance_status
            FROM attendance_records
            WHERE student_id = ?
              AND session_id IN ($placeholders)
        ";

        $params = array_merge([$studentId], array_values($sessionIds));
        $stmt   = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[(int) $row['session_id']] = $row['attendance_status'];
        }

        return $result;
    }
}
