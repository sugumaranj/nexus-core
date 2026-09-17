<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class FeedbackModel extends BaseModel
{
    /**
     * Insert new anonymous feedback.
     * 
     * @param int $eventId
     * @param string $respondentHash
     * @param int $rating
     * @param string|null $review
     * @return int
     */
    public function insert(int $eventId, string $respondentHash, int $rating, ?string $review): int
    {
        $sql = "
            INSERT INTO event_feedback
                (symposium_event_id, respondent_hash, rating, review)
            VALUES
                (:event_id, :hash, :rating, :review)
        ";

        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            'event_id' => $eventId,
            'hash'     => $respondentHash,
            'rating'   => $rating,
            'review'   => $review,
        ]);

        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    /**
     * Check if a hash already exists for this event.
     * 
     * @param int $eventId
     * @param string $respondentHash
     * @return bool
     */
    public function existsByHash(int $eventId, string $respondentHash): bool
    {
        $sql = "
            SELECT 1 FROM event_feedback
            WHERE symposium_event_id = :event_id
              AND respondent_hash = :hash
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'event_id' => $eventId,
            'hash'     => $respondentHash,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Get submitted feedback rating by hash (for student view of own feedback).
     * 
     * @param int $eventId
     * @param string $respondentHash
     * @return int|null
     */
    public function getRatingByHash(int $eventId, string $respondentHash): ?int
    {
        $sql = "
            SELECT rating FROM event_feedback
            WHERE symposium_event_id = :event_id
              AND respondent_hash = :hash
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'event_id' => $eventId,
            'hash'     => $respondentHash,
        ]);

        $rating = $stmt->fetchColumn();
        return $rating !== false ? (int)$rating : null;
    }

    /**
     * Get aggregate summary for an event.
     * 
     * @param int $eventId
     * @return array
     */
    public function getSummaryByEvent(int $eventId): array
    {
        $sql = "
            SELECT 
                COUNT(*) AS response_count, 
                AVG(rating) AS avg_rating 
            FROM event_feedback 
            WHERE symposium_event_id = :event_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['event_id' => $eventId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'response_count' => (int) ($result['response_count'] ?? 0),
            'avg_rating'     => (float) ($result['avg_rating'] ?? 0.0),
        ];
    }

    /**
     * Get rating distribution.
     * 
     * @param int $eventId
     * @return array
     */
    public function getRatingDistribution(int $eventId): array
    {
        $sql = "
            SELECT rating, COUNT(*) AS cnt 
            FROM event_feedback 
            WHERE symposium_event_id = :event_id 
            GROUP BY rating
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['event_id' => $eventId]);
        
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $distribution[(int)$row['rating']] = (int)$row['cnt'];
        }
        
        return $distribution;
    }

    /**
     * Get anonymous reviews. No identity columns are selected.
     * 
     * @param int $eventId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAnonymousReviews(int $eventId, int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT rating, review 
            FROM event_feedback 
            WHERE symposium_event_id = :event_id 
              AND review IS NOT NULL 
              AND TRIM(review) != ''
            ORDER BY feedback_id ASC 
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':event_id', $eventId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get eligible participant count.
     * 
     * @param int $eventId
     * @return int
     */
    public function getEligibleParticipantCount(int $eventId): int
    {
        $sql = "
            SELECT COUNT(DISTINCT ar.student_id) AS eligible_count
            FROM attendance_records ar
            INNER JOIN attendance_sessions ats ON ats.session_id = ar.session_id
            WHERE ar.symposium_event_id = :event_id
              AND ats.symposium_event_id = :event_id2
              AND ats.status = 'Closed'
              AND ar.attendance_status IN ('Present', 'Late')
              AND ats.session_id = (
                  SELECT session_id FROM attendance_sessions
                  WHERE symposium_event_id = :event_id3 AND status = 'Closed'
                  ORDER BY closed_at DESC LIMIT 1
              )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'event_id'  => $eventId,
            'event_id2' => $eventId,
            'event_id3' => $eventId,
        ]);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get student attendance status for a specific session.
     * 
     * @param int $studentId
     * @param int $eventId
     * @param int $sessionId
     * @return string|null
     */
    public function getStudentAttendanceStatus(int $studentId, int $eventId, int $sessionId): ?string
    {
        $sql = "
            SELECT attendance_status 
            FROM attendance_records 
            WHERE student_id = :student_id 
              AND symposium_event_id = :event_id 
              AND session_id = :session_id
            LIMIT 1
        ";

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
     * Get student attendance statuses for multiple sessions (bulk).
     * 
     * @param int $studentId
     * @param array $sessionIds
     * @return array Map of [session_id => attendance_status]
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
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[(int)$row['session_id']] = $row['attendance_status'];
        }

        return $result;
    }

    /**
     * Bulk check existence of multiple hashes.

     * 
     * @param array $hashMap Map of [symposium_event_id => respondent_hash]
     * @return array Map of [symposium_event_id => bool (true if exists)]
     */
    public function existsByHashes(array $hashMap): array
    {
        if (empty($hashMap)) {
            return [];
        }

        // We build a condition like (symposium_event_id = ? AND respondent_hash = ?) OR ...
        $conditions = [];
        $params = [];
        $i = 0;
        foreach ($hashMap as $eventId => $hash) {
            $conditions[] = "(symposium_event_id = :e{$i} AND respondent_hash = :h{$i})";
            $params[":e{$i}"] = $eventId;
            $params[":h{$i}"] = $hash;
            $i++;
        }

        $sql = "SELECT symposium_event_id FROM event_feedback WHERE " . implode(' OR ', $conditions);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $existingEvents = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $result = [];
        foreach ($hashMap as $eventId => $hash) {
            $result[$eventId] = in_array((string)$eventId, $existingEvents, true) || in_array($eventId, $existingEvents, true);
        }

        return $result;
    }

    /**
     * Get ratings for multiple hashes in bulk.
     * 
     * @param array $hashMap Map of [symposium_event_id => respondent_hash]
     * @return array Map of [symposium_event_id => rating]
     */
    public function getRatingsByHashes(array $hashMap): array
    {
        if (empty($hashMap)) {
            return [];
        }

        $conditions = [];
        $params = [];
        $i = 0;
        foreach ($hashMap as $eventId => $hash) {
            $conditions[] = "(symposium_event_id = :e{$i} AND respondent_hash = :h{$i})";
            $params[":e{$i}"] = $eventId;
            $params[":h{$i}"] = $hash;
            $i++;
        }

        $sql = "SELECT symposium_event_id, rating FROM event_feedback WHERE " . implode(' OR ', $conditions);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[(int)$row['symposium_event_id']] = (int)$row['rating'];
        }

        return $result;
    }

    /**
     * Get response counts for multiple events in bulk.
     * 
     * @param array $eventIds
     * @return array Map of [symposium_event_id => response_count]
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
            $result[(int)$row['symposium_event_id']] = (int)$row['response_count'];
        }

        return $result;
    }
}
