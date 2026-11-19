<?php

declare(strict_types=1);

namespace App\Services\Prelims;

use App\Database\Database;
use PDO;

/**
 * Authoritative Server-Side Grading Engine.
 * Grades strictly against canonical `prelims_question_options.is_correct = 1`
 * regardless of how options were shuffled for the student.
 */
final class QuizGradingService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Grade an attempt and finalize its summary metrics atomically.
     *
     * @return array{
     *   attempt_id: int,
     *   status: string,
     *   total_questions: int,
     *   answered_count: int,
     *   correct_count: int,
     *   wrong_count: int,
     *   unanswered_count: int,
     *   raw_score: float,
     *   max_score: float,
     *   percentage: float,
     *   accuracy_percent: float,
     *   time_taken_seconds: int
     * }
     */
    public function gradeAndFinalizeAttempt(
        int $attemptId,
        string $finalStatus = 'Submitted',
        bool $forceRegrade = false,
        ?int $clientLockedAtTs = null
    ): array {
        $validStatuses = ['Submitted', 'Auto_Submitted_Time', 'Auto_Submitted_Violation', 'Force_Submitted'];
        if (!in_array($finalStatus, $validStatuses, true)) {
            $finalStatus = 'Submitted';
        }

        $startedTx = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $startedTx = true;
        }

        try {
            $attStmt = $this->db->prepare("
                SELECT att.*, q.marks_per_question, q.negative_marks, q.duration_seconds
                FROM prelims_quiz_attempts att
                INNER JOIN prelims_quizzes q ON q.quiz_id = att.quiz_id
                WHERE att.attempt_id = :aid
                FOR UPDATE
            ");
            $attStmt->execute(['aid' => $attemptId]);
            $attempt = $attStmt->fetch(PDO::FETCH_ASSOC);

            if (!$attempt) {
                throw new \RuntimeException("Attempt #{$attemptId} not found.");
            }

            // Idempotent return if already finalized (unless explicitly reconciling an offline-swept attempt)
            if ($attempt['status'] !== 'In_Progress' && !$forceRegrade) {
                if ($startedTx) {
                    $this->db->commit();
                }
                return [
                    'attempt_id'         => $attemptId,
                    'status'             => (string) $attempt['status'],
                    'total_questions'    => (int) $attempt['total_questions'],
                    'answered_count'     => (int) $attempt['answered_count'],
                    'correct_count'      => (int) $attempt['correct_count'],
                    'wrong_count'        => (int) $attempt['wrong_count'],
                    'unanswered_count'   => (int) $attempt['unanswered_count'],
                    'raw_score'          => (float) $attempt['raw_score'],
                    'max_score'          => (float) $attempt['max_score'],
                    'percentage'         => (float) $attempt['percentage'],
                    'accuracy_percent'   => (float) $attempt['accuracy_percent'],
                    'time_taken_seconds' => (int) ($attempt['time_taken_seconds'] ?? 0),
                ];
            }

            // Mark per-question correctness directly via canonical option table
            $gradeRowsStmt = $this->db->prepare("
                UPDATE prelims_attempt_questions aq
                LEFT JOIN prelims_question_options opt ON opt.option_id = aq.selected_option_id
                SET aq.is_correct = CASE
                    WHEN aq.selected_option_id IS NULL THEN NULL
                    WHEN opt.is_correct = 1 THEN 1
                    ELSE 0
                END
                WHERE aq.attempt_id = :aid
            ");
            $gradeRowsStmt->execute(['aid' => $attemptId]);

            // Aggregate counts
            $aggStmt = $this->db->prepare("
                SELECT
                    COUNT(*) AS total_q,
                    SUM(selected_option_id IS NOT NULL) AS answered_q,
                    SUM(is_correct = 1) AS correct_q,
                    SUM(is_correct = 0) AS wrong_q
                FROM prelims_attempt_questions
                WHERE attempt_id = :aid
            ");
            $aggStmt->execute(['aid' => $attemptId]);
            $agg = $aggStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $totalQ      = (int) ($agg['total_q'] ?? 0);
            $answeredQ   = (int) ($agg['answered_q'] ?? 0);
            $correctQ    = (int) ($agg['correct_q'] ?? 0);
            $wrongQ      = (int) ($agg['wrong_q'] ?? 0);
            $unansweredQ = max(0, $totalQ - $answeredQ);

            $marksPerQ   = (float) $attempt['marks_per_question'];
            $negMarks    = (float) $attempt['negative_marks'];
            $maxScore    = round($totalQ * $marksPerQ, 2);
            $rawScore    = round(($correctQ * $marksPerQ) - ($wrongQ * $negMarks), 2);
            $percentage  = $maxScore > 0 ? round(max(0.0, ($rawScore / $maxScore) * 100.0), 2) : 0.00;
            $accuracyPct = $answeredQ > 0 ? round(($correctQ / $answeredQ) * 100.0, 2) : 0.00;

            $startedTs    = strtotime((string) $attempt['started_at']) ?: time();
            $endsTs       = strtotime((string) $attempt['server_ends_at']) ?: time();
            $maxDuration  = (int) $attempt['duration_seconds'] + (int) $attempt['extra_time_seconds'];

            if ($clientLockedAtTs !== null && $clientLockedAtTs >= $startedTs && $clientLockedAtTs <= ($endsTs + 15)) {
                $effectiveNow = min($clientLockedAtTs, $endsTs);
            } else {
                $effectiveNow = ($finalStatus === 'Auto_Submitted_Time') ? min(time(), $endsTs) : time();
            }
            $timeTaken = max(1, min($maxDuration, $effectiveNow - $startedTs));

            $upd = $this->db->prepare("
                UPDATE prelims_quiz_attempts
                SET status             = :status,
                    submitted_at       = NOW(),
                    time_taken_seconds = :time_taken,
                    total_questions    = :total_q,
                    answered_count     = :answered_q,
                    correct_count      = :correct_q,
                    wrong_count        = :wrong_q,
                    unanswered_count   = :unanswered_q,
                    raw_score          = :raw_score,
                    max_score          = :max_score,
                    percentage         = :percentage,
                    accuracy_percent   = :accuracy
                WHERE attempt_id = :aid
            ");
            $upd->execute([
                'status'       => $finalStatus,
                'time_taken'   => $timeTaken,
                'total_q'      => $totalQ,
                'answered_q'   => $answeredQ,
                'correct_q'    => $correctQ,
                'wrong_q'      => $wrongQ,
                'unanswered_q' => $unansweredQ,
                'raw_score'    => $rawScore,
                'max_score'    => $maxScore,
                'percentage'   => $percentage,
                'accuracy'     => $accuracyPct,
                'aid'          => $attemptId,
            ]);

            if ($startedTx) {
                $this->db->commit();
            }

            return [
                'attempt_id'         => $attemptId,
                'status'             => $finalStatus,
                'total_questions'    => $totalQ,
                'answered_count'     => $answeredQ,
                'correct_count'      => $correctQ,
                'wrong_count'        => $wrongQ,
                'unanswered_count'   => $unansweredQ,
                'raw_score'          => $rawScore,
                'max_score'          => $maxScore,
                'percentage'         => $percentage,
                'accuracy_percent'   => $accuracyPct,
                'time_taken_seconds' => $timeTaken,
            ];
        } catch (\Throwable $e) {
            if ($startedTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
