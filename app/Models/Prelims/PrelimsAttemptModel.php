<?php

declare(strict_types=1);

namespace App\Models\Prelims;

use App\Models\BaseModel;
use PDO;

final class PrelimsAttemptModel extends BaseModel
{
    public function findById(int $attemptId): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM prelims_quiz_attempts WHERE attempt_id = :aid LIMIT 1");
        $stmt->execute(['aid' => $attemptId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByQuizAndStudent(int $quizId, int $studentId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT * FROM prelims_quiz_attempts
            WHERE quiz_id = :qid AND student_id = :sid
            LIMIT 1
        ");
        $stmt->execute(['qid' => $quizId, 'sid' => $studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createAttempt(array $data): int
    {
        $sql = "
            INSERT INTO prelims_quiz_attempts (
                quiz_id, symposium_event_id, application_id, student_id,
                session_token_hash, device_fingerprint_hash, client_ip, user_agent,
                shuffle_seed, started_at, server_ends_at, last_sync_at,
                status, total_questions, max_score
            ) VALUES (
                :quiz_id, :symposium_event_id, :application_id, :student_id,
                :session_token_hash, :device_fingerprint_hash, :client_ip, :user_agent,
                :shuffle_seed, :started_at, :server_ends_at, NOW(),
                'In_Progress', :total_questions, :max_score
            )
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'quiz_id'                 => (int) $data['quiz_id'],
            'symposium_event_id'      => (int) $data['symposium_event_id'],
            'application_id'          => (int) $data['application_id'],
            'student_id'              => (int) $data['student_id'],
            'session_token_hash'      => (string) $data['session_token_hash'],
            'device_fingerprint_hash' => (string) $data['device_fingerprint_hash'],
            'client_ip'               => $data['client_ip'] ?? null,
            'user_agent'              => mb_substr((string) ($data['user_agent'] ?? ''), 0, 250),
            'shuffle_seed'            => (int) $data['shuffle_seed'],
            'started_at'              => (string) $data['started_at'],
            'server_ends_at'          => (string) $data['server_ends_at'],
            'total_questions'         => (int) $data['total_questions'],
            'max_score'               => (float) $data['max_score'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Persist materialized shuffled question sequence for an attempt.
     *
     * @param array<int, array{question_id: int, source_id: int, topic_id: int, display_order: int, option_order: int[]}> $items
     */
    public function insertAttemptQuestions(int $attemptId, array $items): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO prelims_attempt_questions (
                attempt_id, question_id, source_id, topic_id, display_order, option_order_json
            ) VALUES (
                :attempt_id, :question_id, :source_id, :topic_id, :display_order, :option_order_json
            )
        ");

        foreach ($items as $item) {
            $stmt->execute([
                'attempt_id'        => $attemptId,
                'question_id'       => (int) $item['question_id'],
                'source_id'         => (int) $item['source_id'],
                'topic_id'          => (int) $item['topic_id'],
                'display_order'     => (int) $item['display_order'],
                'option_order_json' => json_encode(array_values($item['option_order']), JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /**
     * Fetch the materialized exam paper (questions + shuffled options — NEVER includes is_correct!).
     */
    public function getExamPaperForAttempt(int $attemptId): array
    {
        $sql = "
            SELECT aq.display_order, aq.question_id, aq.option_order_json,
                   aq.selected_option_id, aq.is_marked_review,
                   q.question_text
            FROM prelims_attempt_questions aq
            INNER JOIN prelims_questions q ON q.question_id = aq.question_id
            WHERE aq.attempt_id = :aid
            ORDER BY aq.display_order ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['aid' => $attemptId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($rows)) {
            return [];
        }

        $qIds = array_map(static fn(array $r): int => (int) $r['question_id'], $rows);
        $placeholders = implode(',', array_fill(0, count($qIds), '?'));
        $optStmt = $this->db->prepare("
            SELECT option_id, question_id, option_text
            FROM prelims_question_options
            WHERE question_id IN ({$placeholders})
        ");
        $optStmt->execute($qIds);

        $optsById = [];
        while ($o = $optStmt->fetch(PDO::FETCH_ASSOC)) {
            $optsById[(int) $o['option_id']] = (string) $o['option_text'];
        }

        $paper = [];
        foreach ($rows as $r) {
            $orderIds = json_decode((string) $r['option_order_json'], true) ?: [];
            $compactOpts = [];
            foreach ($orderIds as $oid) {
                $oidInt = (int) $oid;
                if (isset($optsById[$oidInt])) {
                    $compactOpts[] = [$oidInt, $optsById[$oidInt]];
                }
            }
            $paper[] = [
                'd' => (int) $r['display_order'],
                't' => (string) $r['question_text'],
                'o' => $compactOpts,
                's' => $r['selected_option_id'] !== null ? (int) $r['selected_option_id'] : null,
                'm' => (int) $r['is_marked_review'],
            ];
        }

        return $paper;
    }

    /**
     * Apply a batched micro-delta of answers for low-bandwidth syncing.
     *
     * @param array<int, array{display_order: int, option_id: ?int, marked: int}> $deltas
     */
    public function batchSaveAnswers(int $attemptId, array $deltas): int
    {
        if (empty($deltas)) {
            $this->touchLastSync($attemptId);
            return 0;
        }

        $upd = $this->db->prepare("
            UPDATE prelims_attempt_questions aq
            SET aq.selected_option_id = :opt_id,
                aq.is_marked_review   = :marked,
                aq.answered_at        = IF(:opt_id2 IS NOT NULL, NOW(), NULL)
            WHERE aq.attempt_id = :aid
              AND aq.display_order = :ord
              AND (
                  :opt_id3 IS NULL
                  OR EXISTS (
                      SELECT 1 FROM prelims_question_options o
                      WHERE o.option_id = :opt_id4 AND o.question_id = aq.question_id
                  )
              )
        ");

        $updated = 0;
        foreach ($deltas as $d) {
            $optId = $d['option_id'] !== null && $d['option_id'] > 0 ? (int) $d['option_id'] : null;
            $upd->execute([
                'opt_id'  => $optId,
                'opt_id2' => $optId,
                'opt_id3' => $optId,
                'opt_id4' => $optId,
                'marked'  => !empty($d['marked']) ? 1 : 0,
                'aid'     => $attemptId,
                'ord'     => (int) $d['display_order'],
            ]);
            $updated += $upd->rowCount();
        }

        $this->touchLastSync($attemptId);
        return $updated;
    }

    public function touchLastSync(int $attemptId): void
    {
        $stmt = $this->db->prepare("UPDATE prelims_quiz_attempts SET last_sync_at = NOW() WHERE attempt_id = :aid");
        $stmt->execute(['aid' => $attemptId]);
    }

    public function logViolation(int $attemptId, string $type, ?string $clientTs = null, ?array $details = null, bool $incrementStrike = true): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO prelims_attempt_violations (attempt_id, violation_type, client_timestamp, details_json)
            VALUES (:aid, :vtype, :cts, :details)
        ");
        $stmt->execute([
            'aid'     => $attemptId,
            'vtype'   => $type,
            'cts'     => $clientTs,
            'details' => $details !== null ? json_encode($details) : null,
        ]);

        if ($incrementStrike) {
            $upd = $this->db->prepare("
                UPDATE prelims_quiz_attempts
                SET violation_count = violation_count + 1,
                    integrity_level = CASE
                        WHEN violation_count + 1 >= 4 THEN 'High'
                        WHEN violation_count + 1 >= 2 THEN 'Medium'
                        WHEN violation_count + 1 >= 1 THEN 'Low'
                        ELSE 'Clean'
                    END
                WHERE attempt_id = :aid
            ");
            $upd->execute(['aid' => $attemptId]);
        }

        $cntStmt = $this->db->prepare("SELECT violation_count FROM prelims_quiz_attempts WHERE attempt_id = :aid");
        $cntStmt->execute(['aid' => $attemptId]);
        return (int) $cntStmt->fetchColumn();
    }

    public function getExpiredInProgressAttempts(int $quizId, int $graceSeconds = 15): array
    {
        $stmt = $this->db->prepare("
            SELECT attempt_id FROM prelims_quiz_attempts
            WHERE quiz_id = :qid
              AND status = 'In_Progress'
              AND DATE_ADD(server_ends_at, INTERVAL :grace SECOND) <= NOW()
        ");
        $stmt->bindValue(':qid', $quizId, PDO::PARAM_INT);
        $stmt->bindValue(':grace', $graceSeconds, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Get all attempts for a quiz ordered by the strictly automated tie-break rule:
     *   1. raw_score DESC
     *   2. correct_count DESC
     *   3. time_taken_seconds ASC
     *   4. submitted_at ASC
     *   5. attempt_id ASC
     */
    public function getRankedAttemptsForQuiz(int $quizId): array
    {
        $sql = "
            SELECT att.*,
                   s.full_name AS student_name, s.register_number, s.email, s.student_type,
                   COALESCE(s.external_department, d.short_name, d.department_name) AS department_name,
                   COALESCE(c.college_name, s.college_name, 'Home Institution') AS college_name,
                   a.application_no, a.application_type,
                   ps.selection_status, ps.prelims_rank
            FROM prelims_quiz_attempts att
            INNER JOIN students s        ON s.student_id = att.student_id
            LEFT JOIN departments d      ON d.department_id = s.department_id
            LEFT JOIN colleges c         ON c.college_id = s.college_id
            INNER JOIN applications a    ON a.application_id = att.application_id
            LEFT JOIN prelims_shortlist ps
                ON ps.quiz_id = att.quiz_id AND ps.application_id = att.application_id
            WHERE att.quiz_id = :qid
            ORDER BY
                CASE WHEN att.status = 'In_Progress' THEN 1 ELSE 0 END ASC,
                att.raw_score DESC,
                att.correct_count DESC,
                COALESCE(att.time_taken_seconds, 999999) ASC,
                COALESCE(att.submitted_at, '9999-12-31 23:59:59') ASC,
                att.attempt_id ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['qid' => $quizId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getViolationsForQuiz(int $quizId, int $limit = 100): array
    {
        $sql = "
            SELECT v.*, att.student_id, s.full_name AS student_name, s.register_number
            FROM prelims_attempt_violations v
            INNER JOIN prelims_quiz_attempts att ON att.attempt_id = v.attempt_id
            INNER JOIN students s ON s.student_id = att.student_id
            WHERE att.quiz_id = :qid
            ORDER BY v.server_logged_at DESC, v.violation_id DESC
            LIMIT :lim
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':qid', $quizId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function grantReentryToken(int $attemptId, string $newTokenHash, int $extraSeconds = 0): bool
    {
        $stmt = $this->db->prepare("
            UPDATE prelims_quiz_attempts
            SET session_token_hash = :tok,
                extra_time_seconds = extra_time_seconds + :ext,
                server_ends_at     = DATE_ADD(server_ends_at, INTERVAL :ext2 SECOND),
                reentry_grants     = reentry_grants + 1
            WHERE attempt_id = :aid AND status = 'In_Progress'
        ");
        return $stmt->execute([
            'tok'  => $newTokenHash,
            'ext'  => max(0, $extraSeconds),
            'ext2' => max(0, $extraSeconds),
            'aid'  => $attemptId,
        ]);
    }

    /**
     * Re-open a mistakenly submitted or auto-submitted attempt (keeping existing answers),
     * resetting status to In_Progress with a fresh single-session lock token hash and new timer.
     */
    public function reopenSubmittedAttempt(
        int $attemptId,
        string $newTokenHash,
        int $resumeSeconds,
        bool $resetViolations = true
    ): bool {
        $violClause = $resetViolations
            ? "violation_count = 0, integrity_level = 'Clean',"
            : '';

        $sql = "
            UPDATE prelims_quiz_attempts
            SET status             = 'In_Progress',
                submitted_at       = NULL,
                time_taken_seconds = NULL,
                {$violClause}
                session_token_hash = :tok,
                extra_time_seconds = extra_time_seconds + :ext,
                server_ends_at     = DATE_ADD(NOW(), INTERVAL :ext2 SECOND),
                reentry_grants     = reentry_grants + 1
            WHERE attempt_id = :aid
              AND status != 'In_Progress'
        ";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'tok'  => $newTokenHash,
            'ext'  => max(60, $resumeSeconds),
            'ext2' => max(60, $resumeSeconds),
            'aid'  => $attemptId,
        ]);
    }

    /**
     * Permanently delete a student attempt (cascading attempt_questions & violations)
     * so the student can attend a fresh shuffled attempt after a technical issue.
     */
    public function deleteAttemptForReset(int $attemptId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM prelims_quiz_attempts WHERE attempt_id = :aid");
        return $stmt->execute(['aid' => $attemptId]);
    }
}
