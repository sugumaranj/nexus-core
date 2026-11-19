<?php

declare(strict_types=1);

namespace App\Services\Prelims;

use App\Database\Database;
use App\Models\AuditLogModel;
use App\Models\Prelims\PrelimsQuizModel;
use PDO;

/**
 * Safe Post-Finalization Quiz Content Disposal Service.
 *
 * Purges sensitive & bulky quiz data:
 *   - Uploaded PDF binaries on disk (overwritten/unlinked)
 *   - `prelims_attempt_questions` (per-student response bloat)
 *   - `prelims_question_options`, `prelims_questions`, `prelims_quiz_topics`
 *   - Marks `prelims_quiz_sources` as 'Disposed' and nulls stored_path
 *
 * Permanently preserves:
 *   - `prelims_quiz_attempts` (all student scores, ranks, accuracy, completion times)
 *   - `prelims_shortlist` (promoted finalists for Main Event)
 *   - `prelims_attempt_violations` (integrity audit log)
 *   - `prelims_quizzes.analytics_snapshot_json` (precomputed analytics for future reports)
 *   - `attendance_records` (both Prelims & Main Event attendance)
 */
final class QuizDisposalService
{
    private PDO              $db;
    private PrelimsQuizModel $quizModel;
    private AuditLogModel    $auditModel;

    public function __construct()
    {
        $this->db         = Database::getConnection();
        $this->quizModel  = new PrelimsQuizModel();
        $this->auditModel = new AuditLogModel();
    }

    public function disposeQuizContent(int $quizId, int $userId): array
    {
        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz) {
            return ['success' => false, 'message' => 'Quiz not found.'];
        }
        if ($quiz['status'] === 'Disposed') {
            return ['success' => false, 'message' => 'Quiz content has already been disposed.'];
        }
        if ($quiz['status'] !== 'Finalized') {
            return ['success' => false, 'message' => 'Quiz must be Finalized (with finalists promoted) before disposing quiz content.'];
        }

        // 1. Capture rich analytics snapshot BEFORE purging raw questions/answers
        $snapshot      = $this->buildPreDisposalAnalyticsSnapshot($quizId);
        $snapshotJson  = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        // Collect PDF file paths before DB update
        $pathsStmt = $this->db->prepare("SELECT stored_path FROM prelims_quiz_sources WHERE quiz_id = :qid AND stored_path IS NOT NULL");
        $pathsStmt->execute(['qid' => $quizId]);
        $pdfPaths = $pathsStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $purgedCounts = [
            'attempt_questions' => 0,
            'questions'         => 0,
            'topics'            => 0,
            'pdf_files'         => 0,
        ];

        $this->db->beginTransaction();
        try {
            // 2. Purge per-student attempt question rows (preserves prelims_quiz_attempts!)
            $delAq = $this->db->prepare("
                DELETE aq FROM prelims_attempt_questions aq
                INNER JOIN prelims_quiz_attempts att ON att.attempt_id = aq.attempt_id
                WHERE att.quiz_id = :qid
            ");
            $delAq->execute(['qid' => $quizId]);
            $purgedCounts['attempt_questions'] = $delAq->rowCount();

            // 3. Purge questions (cascades to prelims_question_options)
            $delQ = $this->db->prepare("DELETE FROM prelims_questions WHERE quiz_id = :qid");
            $delQ->execute(['qid' => $quizId]);
            $purgedCounts['questions'] = $delQ->rowCount();

            // 4. Purge topics
            $delT = $this->db->prepare("DELETE FROM prelims_quiz_topics WHERE quiz_id = :qid");
            $delT->execute(['qid' => $quizId]);
            $purgedCounts['topics'] = $delT->rowCount();

            // 5. Mark PDF sources as Disposed (keeping source filename metadata for audit)
            $updSrc = $this->db->prepare("
                UPDATE prelims_quiz_sources
                SET stored_path = NULL, extraction_status = 'Disposed', is_active = 0
                WHERE quiz_id = :qid
            ");
            $updSrc->execute(['qid' => $quizId]);

            // 6. Stamp quiz as Disposed with frozen analytics snapshot
            $this->quizModel->markDisposed($quizId, $userId, $snapshotJson);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        // 7. Securely delete PDF binaries on disk
        foreach ($pdfPaths as $path) {
            $strPath = (string) $path;
            if ($strPath !== '' && is_file($strPath)) {
                @unlink($strPath);
                $purgedCounts['pdf_files']++;
            }
        }
        $quizDir = PrelimsQuizService::PDF_STORAGE_ROOT . DIRECTORY_SEPARATOR . $quizId;
        if (is_dir($quizDir)) {
            $remaining = array_diff(scandir($quizDir) ?: [], ['.', '..']);
            if (empty($remaining)) {
                @rmdir($quizDir);
            }
        }

        $this->auditModel->log(
            'PrelimsQuiz',
            $quizId,
            'Quiz Content Disposed',
            $userId,
            sprintf(
                'Purged %d questions, %d attempt-answer rows, %d topics, %d PDFs. Preserved all student scores & shortlist.',
                $purgedCounts['questions'],
                $purgedCounts['attempt_questions'],
                $purgedCounts['topics'],
                $purgedCounts['pdf_files']
            )
        );

        return [
            'success' => true,
            'purged'  => $purgedCounts,
            'message' => 'Quiz questions, raw responses, and uploaded PDFs have been securely disposed. All student scores, rankings, attendance, and promoted finalists are permanently preserved.',
        ];
    }

    /**
     * Build comprehensive pre-disposal analytics snapshot so reports remain rich after disposal.
     */
    public function buildPreDisposalAnalyticsSnapshot(int $quizId): array
    {
        // Per-PDF & Per-Topic accuracy stats
        $topicStmt = $this->db->prepare("
            SELECT src.original_filename AS source_name,
                   t.topic_name,
                   COUNT(aq.attempt_question_id) AS served_count,
                   SUM(aq.selected_option_id IS NOT NULL) AS answered_count,
                   SUM(aq.is_correct = 1) AS correct_count
            FROM prelims_attempt_questions aq
            INNER JOIN prelims_quiz_attempts att ON att.attempt_id = aq.attempt_id
            INNER JOIN prelims_quiz_sources src  ON src.source_id = aq.source_id
            INNER JOIN prelims_quiz_topics t     ON t.topic_id = aq.topic_id
            WHERE att.quiz_id = :qid
            GROUP BY src.source_id, t.topic_id
            ORDER BY src.source_id ASC, t.display_order ASC
        ");
        $topicStmt->execute(['qid' => $quizId]);
        $topicStats = [];
        foreach ($topicStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $served  = (int) $row['served_count'];
            $correct = (int) $row['correct_count'];
            $topicStats[] = [
                'source_name'    => (string) $row['source_name'],
                'topic_name'     => (string) $row['topic_name'],
                'served_count'   => $served,
                'answered_count' => (int) $row['answered_count'],
                'correct_count'  => $correct,
                'accuracy_pct'   => $served > 0 ? round(($correct / $served) * 100, 1) : 0.0,
            ];
        }

        // Score distribution histogram (0-20%, 20-40%, 40-60%, 60-80%, 80-100%)
        $histStmt = $this->db->prepare("
            SELECT
                SUM(percentage < 20) AS b_0_20,
                SUM(percentage >= 20 AND percentage < 40) AS b_20_40,
                SUM(percentage >= 40 AND percentage < 60) AS b_40_60,
                SUM(percentage >= 60 AND percentage < 80) AS b_60_80,
                SUM(percentage >= 80) AS b_80_100,
                COUNT(*) AS total_attempts,
                ROUND(AVG(raw_score), 2) AS avg_score,
                ROUND(MAX(raw_score), 2) AS max_score_achieved,
                ROUND(MIN(raw_score), 2) AS min_score_achieved,
                ROUND(AVG(time_taken_seconds), 0) AS avg_time_seconds
            FROM prelims_quiz_attempts
            WHERE quiz_id = :qid AND status != 'In_Progress'
        ");
        $histStmt->execute(['qid' => $quizId]);
        $agg = $histStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'captured_at'        => date('Y-m-d H:i:s'),
            'total_attempts'     => (int) ($agg['total_attempts'] ?? 0),
            'avg_score'          => (float) ($agg['avg_score'] ?? 0.0),
            'max_score_achieved' => (float) ($agg['max_score_achieved'] ?? 0.0),
            'min_score_achieved' => (float) ($agg['min_score_achieved'] ?? 0.0),
            'avg_time_seconds'   => (int) ($agg['avg_time_seconds'] ?? 0),
            'score_buckets'      => [
                '0-19%'   => (int) ($agg['b_0_20'] ?? 0),
                '20-39%'  => (int) ($agg['b_20_40'] ?? 0),
                '40-59%'  => (int) ($agg['b_40_60'] ?? 0),
                '60-79%'  => (int) ($agg['b_60_80'] ?? 0),
                '80-100%' => (int) ($agg['b_80_100'] ?? 0),
            ],
            'topic_performance'  => $topicStats,
        ];
    }
}
