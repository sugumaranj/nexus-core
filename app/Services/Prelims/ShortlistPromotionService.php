<?php

declare(strict_types=1);

namespace App\Services\Prelims;

use App\Database\Database;
use App\Models\AuditLogModel;
use App\Models\NotificationModel;
use App\Models\Prelims\PrelimsAttemptModel;
use App\Models\Prelims\PrelimsQuizModel;
use App\Models\Prelims\PrelimsShortlistModel;
use PDO;

/**
 * Strictly Automated Prelims Ranking & Top-N Finalist Promotion Service.
 *
 * Rules (Zero Manual Overrides):
 *   1. Deterministic comparator:
 *        raw_score DESC -> correct_count DESC -> time_taken_seconds ASC -> submitted_at ASC -> attempt_id ASC
 *   2. Individual events (e.g., Debugging): each student attempt ranks directly.
 *   3. Team events (e.g., Team Quiz): all members attend individually, and the
 *      team's official score & rank are determined automatically by the Best Member Score
 *      (highest-ranked member attempt under the same comparator).
 *   4. Top-N (meeting optional min_pass_percentage) are promoted to `prelims_shortlist`
 *      for Main Event attendance and Judge evaluation.
 */
final class ShortlistPromotionService
{
    private PDO                   $db;
    private PrelimsQuizModel      $quizModel;
    private PrelimsAttemptModel   $attemptModel;
    private PrelimsShortlistModel $shortlistModel;
    private QuizAttemptService    $attemptService;
    private NotificationModel     $notifModel;
    private AuditLogModel         $auditModel;

    public function __construct()
    {
        $this->db             = Database::getConnection();
        $this->quizModel      = new PrelimsQuizModel();
        $this->attemptModel   = new PrelimsAttemptModel();
        $this->shortlistModel = new PrelimsShortlistModel();
        $this->attemptService = new QuizAttemptService();
        $this->notifModel     = new NotificationModel();
        $this->auditModel     = new AuditLogModel();
    }

    /**
     * Compute the strictly automated ranking preview (before or after finalization).
     *
     * @return array{
     *   quiz: array,
     *   scoring_mode: string,
     *   individual_attempts: array,
     *   ranked_entries: array,
     *   selected_count: int,
     *   total_entries: int
     * }
     */
    public function computeAutomatedRanking(int $quizId): array
    {
        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz) {
            throw new \RuntimeException("Quiz #{$quizId} not found.");
        }

        // Ensure any expired in-progress attempts are graded first
        $this->attemptService->sweepExpiredAttempts($quizId);

        $allAttempts = $this->attemptModel->getRankedAttemptsForQuiz($quizId);
        $finished    = array_values(array_filter(
            $allAttempts,
            static fn(array $a): bool => $a['status'] !== 'In_Progress'
        ));

        // Sort deterministically in PHP too (guaranteed match with SQL ordering)
        usort($finished, [self::class, 'compareAttempts']);

        // Assign individual student rank positions
        foreach ($finished as $i => &$att) {
            $att['computed_rank'] = $i + 1;
        }
        unset($att);

        $scoringMode = (string) ($quiz['team_scoring_mode'] ?? 'Individual');
        $topN        = (int) $quiz['top_n_select'];
        $minPassPct  = (float) $quiz['min_pass_percentage'];

        // Build official entries (Individual = per attempt, Team = Best Member Score per application_id)
        $entries = [];
        if ($scoringMode === 'All_Members_Best') {
            $bestByApp = [];
            foreach ($finished as $att) {
                $appId = (int) $att['application_id'];
                if (!isset($bestByApp[$appId])) {
                    $bestByApp[$appId] = $att; // Since $finished is already strictly sorted best-first, first match IS the Best Member Score!
                }
            }
            $entries = array_values($bestByApp);
            usort($entries, [self::class, 'compareAttempts']);
        } else {
            $entries = $finished;
        }

        $rankedEntries = [];
        $selectedCount = 0;

        foreach ($entries as $idx => $entry) {
            $rank        = $idx + 1;
            $meetsCutoff = ((float) $entry['percentage'] >= $minPassPct);
            $isSelected  = ($selectedCount < $topN) && $meetsCutoff;
            if ($isSelected) {
                $selectedCount++;
            }

            $rankedEntries[] = [
                'prelims_rank'       => $rank,
                'application_id'     => (int) $entry['application_id'],
                'application_no'     => (string) ($entry['application_no'] ?? ''),
                'application_type'   => (string) ($entry['application_type'] ?? 'Individual'),
                'winning_attempt_id' => (int) $entry['attempt_id'],
                'winning_student_id' => (int) $entry['student_id'],
                'student_name'       => (string) ($entry['student_name'] ?? ''),
                'register_number'    => (string) ($entry['register_number'] ?? ''),
                'department_name'    => (string) ($entry['department_name'] ?? ''),
                'college_name'       => (string) ($entry['college_name'] ?? ''),
                'final_score'        => (float) $entry['raw_score'],
                'max_score'          => (float) $entry['max_score'],
                'percentage'         => (float) $entry['percentage'],
                'correct_count'      => (int) $entry['correct_count'],
                'time_taken_seconds' => (int) ($entry['time_taken_seconds'] ?? 0),
                'violation_count'    => (int) ($entry['violation_count'] ?? 0),
                'integrity_level'    => (string) ($entry['integrity_level'] ?? 'Clean'),
                'selection_status'   => $isSelected ? 'Selected' : 'Not_Selected',
            ];
        }

        return [
            'quiz'                => $quiz,
            'scoring_mode'        => $scoringMode,
            'individual_attempts' => $finished,
            'ranked_entries'      => $rankedEntries,
            'selected_count'      => $selectedCount,
            'total_entries'       => count($rankedEntries),
        ];
    }

    /**
     * Finalize the prelims quiz, persist deterministic ranks + Top-N shortlist,
     * promote finalists to the Main Event, and notify shortlisted students.
     */
    public function finalizeAndPromote(int $quizId, int $ficUserId): array
    {
        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz) {
            return ['success' => false, 'message' => 'Quiz not found.'];
        }
        if (in_array($quiz['status'], ['Finalized', 'Disposed'], true)) {
            return ['success' => false, 'message' => 'This prelims quiz has already been finalized.'];
        }

        // Force-close any lingering in-progress attempts if FIC is finalizing
        $openStmt = $this->db->prepare("SELECT attempt_id FROM prelims_quiz_attempts WHERE quiz_id = :qid AND status = 'In_Progress'");
        $openStmt->execute(['qid' => $quizId]);
        $openIds = $openStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $grader  = new QuizGradingService();
        foreach ($openIds as $aid) {
            $grader->gradeAndFinalizeAttempt((int) $aid, 'Force_Submitted');
        }

        $ranking = $this->computeAutomatedRanking($quizId);
        if (empty($ranking['ranked_entries'])) {
            return ['success' => false, 'message' => 'No completed student attempts found to finalize.'];
        }

        $eventId = (int) $quiz['symposium_event_id'];

        $this->db->beginTransaction();
        try {
            // 1. Persist immutable individual rank_position on every student attempt
            $updRank = $this->db->prepare("
                UPDATE prelims_quiz_attempts
                SET rank_position = :rk
                WHERE attempt_id = :aid
            ");
            foreach ($ranking['individual_attempts'] as $att) {
                $updRank->execute([
                    'rk'  => (int) $att['computed_rank'],
                    'aid' => (int) $att['attempt_id'],
                ]);
            }

            // 2. Write strictly automated prelims_shortlist
            $this->shortlistModel->replaceForQuiz($quizId, $eventId, $ranking['ranked_entries'], $ficUserId);

            // 3. Mark quiz as Finalized
            $this->quizModel->updateStatus($quizId, 'Finalized', $ficUserId);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        // 4. Notify promoted finalists (best-effort)
        try {
            $selectedStudents = $this->shortlistModel->getSelectedStudentIds($quizId);
            $eventName = (string) ($quiz['event_name'] ?? 'Event');
            foreach ($selectedStudents as $sid) {
                $this->notifModel->createForStudent(
                    $sid,
                    'Shortlisted for Main Event!',
                    "Congratulations! You have qualified from the Prelims Quiz for \"{$eventName}\" and have been promoted to the Final Round."
                );
            }
        } catch (\Throwable $e) {
            // Do not fail finalization if notification delivery has a non-critical issue
        }

        $this->auditModel->log(
            'PrelimsQuiz',
            $quizId,
            'Automated Finalization & Promotion',
            $ficUserId,
            "Promoted {$ranking['selected_count']} of {$ranking['total_entries']} entries (Mode: {$ranking['scoring_mode']}, TopN: {$quiz['top_n_select']})"
        );

        return [
            'success'        => true,
            'selected_count' => $ranking['selected_count'],
            'total_entries'  => $ranking['total_entries'],
            'message'        => "Prelims finalized automatically. {$ranking['selected_count']} finalist(s) promoted to the Main Event.",
        ];
    }

    /**
     * Strictly automated tie-breaking comparator:
     *   1. raw_score DESC
     *   2. correct_count DESC
     *   3. time_taken_seconds ASC
     *   4. submitted_at ASC
     *   5. attempt_id ASC
     */
    public static function compareAttempts(array $a, array $b): int
    {
        $scoreCmp = (float) $b['raw_score'] <=> (float) $a['raw_score'];
        if ($scoreCmp !== 0) {
            return $scoreCmp;
        }

        $correctCmp = (int) $b['correct_count'] <=> (int) $a['correct_count'];
        if ($correctCmp !== 0) {
            return $correctCmp;
        }

        $timeA   = (int) ($a['time_taken_seconds'] ?? 999999);
        $timeB   = (int) ($b['time_taken_seconds'] ?? 999999);
        $timeCmp = $timeA <=> $timeB;
        if ($timeCmp !== 0) {
            return $timeCmp;
        }

        $subA   = (string) ($a['submitted_at'] ?? '9999-12-31 23:59:59');
        $subB   = (string) ($b['submitted_at'] ?? '9999-12-31 23:59:59');
        $subCmp = strcmp($subA, $subB);
        if ($subCmp !== 0) {
            return $subCmp;
        }

        return (int) $a['attempt_id'] <=> (int) $b['attempt_id'];
    }
}
