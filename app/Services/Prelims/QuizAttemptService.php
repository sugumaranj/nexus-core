<?php

declare(strict_types=1);

namespace App\Services\Prelims;

use App\Database\Database;
use App\Models\AuditLogModel;
use App\Models\Prelims\PrelimsAttemptModel;
use App\Models\Prelims\PrelimsQuestionModel;
use App\Models\Prelims\PrelimsQuizModel;
use PDO;

/**
 * Manages the Single-Session Exam Attempt Lifecycle, Ultra-Low-Bandwidth Delta Sync,
 * Server-Authoritative Timer, and FIC-Configurable Violation Auto-Submit.
 */
final class QuizAttemptService
{
    public const NETWORK_GRACE_SECONDS            = 12;
    public const OFFLINE_RECONCILE_WINDOW_SECONDS = 1800; // 30 minutes delayed reconnect window (before quiz finalization)

    private PDO                       $db;
    private PrelimsQuizModel          $quizModel;
    private PrelimsQuestionModel      $questionModel;
    private PrelimsAttemptModel       $attemptModel;
    private PrelimsEligibilityService $eligibility;
    private QuestionSamplerService    $sampler;
    private QuizGradingService        $grader;
    private AuditLogModel             $auditModel;

    public function __construct(
        ?PrelimsEligibilityService $eligibility = null,
        ?QuestionSamplerService $sampler = null,
        ?QuizGradingService $grader = null
    ) {
        $this->db            = Database::getConnection();
        $this->quizModel     = new PrelimsQuizModel();
        $this->questionModel = new PrelimsQuestionModel();
        $this->attemptModel  = new PrelimsAttemptModel();
        $this->eligibility   = $eligibility ?? new PrelimsEligibilityService();
        $this->sampler       = $sampler ?? new QuestionSamplerService();
        $this->grader        = $grader ?? new QuizGradingService();
        $this->auditModel    = new AuditLogModel();
    }

    public static function lockCookieName(int $quizId): string
    {
        return 'nx_quiz_lock_' . $quizId;
    }

    /**
     * Start a brand-new single-session attempt OR seamlessly resume an existing session
     * on the SAME browser (matching cryptographic lock cookie).
     *
     * Never interrupts an existing session when called from another browser/login!
     */
    public function startOrResumeAttempt(
        int $quizId,
        int $studentId,
        array $serverHeaders,
        array $clientSignals,
        ?string $cookieLockToken = null
    ): array {
        $check = $this->eligibility->checkEligibility(
            $quizId,
            $studentId,
            $serverHeaders,
            $clientSignals,
            $cookieLockToken
        );

        if (!$check['eligible']) {
            // If timer expired on an In_Progress attempt, finalize it cleanly now
            if (($check['code'] ?? '') === 'TIME_EXPIRED' && !empty($check['attempt']['attempt_id'])) {
                $this->grader->gradeAndFinalizeAttempt((int) $check['attempt']['attempt_id'], 'Auto_Submitted_Time');
            }
            return [
                'success' => false,
                'code'    => $check['code'],
                'message' => $check['message'],
            ];
        }

        $quiz = $check['quiz'];

        // Resume existing active session on SAME browser
        if ($check['mode'] === 'resume' && !empty($check['attempt'])) {
            $attempt = $check['attempt'];
            return $this->buildCompactBootstrapPayload($quiz, $attempt, $cookieLockToken);
        }

        // Fresh Start: atomic transaction guarded by UNIQUE(quiz_id, student_id)
        $poolBuckets = $this->questionModel->getApprovedPoolBuckets($quizId);
        $seed        = random_int(1, 2147483647);
        $sampled     = $this->sampler->sampleAndShuffle(
            $poolBuckets,
            (int) $quiz['questions_per_student'],
            $seed
        );

        if (empty($sampled)) {
            return [
                'success' => false,
                'code'    => 'EMPTY_POOL',
                'message' => 'No approved questions are available for this quiz. Please notify the Faculty In-Charge.',
            ];
        }

        $plainToken  = bin2hex(random_bytes(32));
        $tokenHash   = hash('sha256', $plainToken);
        $nowTs       = time();
        $startedAt   = date('Y-m-d H:i:s', $nowTs);
        $endsTs      = $nowTs + (int) $quiz['duration_seconds'];

        if (!empty($quiz['window_end'])) {
            $winEndTs = strtotime((string) $quiz['window_end']);
            if ($winEndTs !== false && $winEndTs > $nowTs) {
                $endsTs = min($endsTs, $winEndTs);
            }
        }
        $serverEndsAt = date('Y-m-d H:i:s', $endsTs);
        $maxScore     = round(count($sampled) * (float) $quiz['marks_per_question'], 2);

        $this->db->beginTransaction();
        try {
            $attemptId = $this->attemptModel->createAttempt([
                'quiz_id'                 => $quizId,
                'symposium_event_id'      => (int) $quiz['symposium_event_id'],
                'application_id'          => (int) $check['application_id'],
                'student_id'              => $studentId,
                'session_token_hash'      => $tokenHash,
                'device_fingerprint_hash' => (string) ($check['device']['fingerprint_hash'] ?? hash('sha256', 'desktop')),
                'client_ip'               => (string) ($serverHeaders['REMOTE_ADDR'] ?? ''),
                'user_agent'              => (string) ($serverHeaders['HTTP_USER_AGENT'] ?? ''),
                'shuffle_seed'            => $seed,
                'started_at'              => $startedAt,
                'server_ends_at'          => $serverEndsAt,
                'total_questions'         => count($sampled),
                'max_score'               => $maxScore,
            ]);

            $this->attemptModel->insertAttemptQuestions($attemptId, $sampled);
            $this->db->commit();
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // SQLSTATE 23000 (duplicate key) -> concurrent double-click / race login
            if ((string) $e->getCode() === '23000') {
                return [
                    'success' => false,
                    'code'    => 'DUPLICATE_SESSION',
                    'message' => PrelimsEligibilityService::DUPLICATE_SESSION_MESSAGE,
                ];
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        $attempt = $this->attemptModel->findById($attemptId);
        return $this->buildCompactBootstrapPayload($quiz, $attempt, $plainToken);
    }

    /**
     * Ultra-low-bandwidth micro-delta sync endpoint (~40 bytes request / ~50 bytes response).
     * Validates session token, saves batched answer deltas, records violations,
     * and enforces timer expiry & FIC-configured violation auto-submit.
     *
     * @param array<int, array{0: int, 1: ?int, 2?: int}> $compactAnswers  [[displayOrder, optionId|null, marked], ...]
     * @param array<int, array{t: string, ts?: string, d?: array}> $violations
     */
    public function syncDelta(
        int $attemptId,
        int $studentId,
        string $cookieLockToken,
        array $compactAnswers = [],
        array $violations = []
    ): array {
        $attempt = $this->attemptModel->findById($attemptId);
        if (!$attempt || (int) $attempt['student_id'] !== $studentId) {
            return ['ok' => 0, 'code' => 'NOT_FOUND', 'msg' => 'Attempt not found.'];
        }

        if ($attempt['status'] !== 'In_Progress') {
            return ['ok' => 0, 'code' => 'ALREADY_FINALIZED', 's' => $attempt['status'], 'rem' => 0];
        }

        if ($cookieLockToken === '' || !hash_equals((string) $attempt['session_token_hash'], hash('sha256', $cookieLockToken))) {
            return ['ok' => 0, 'code' => 'SESSION_MISMATCH', 'msg' => PrelimsEligibilityService::DUPLICATE_SESSION_MESSAGE];
        }

        $quiz   = $this->quizModel->findById((int) $attempt['quiz_id']);
        $endsTs = strtotime((string) $attempt['server_ends_at']) ?: time();
        $remSec = $endsTs - time();

        // Save answers even within grace window so low-bandwidth final clicks aren't lost
        $deltas = [];
        foreach ($compactAnswers as $entry) {
            if (!is_array($entry) || !isset($entry[0])) {
                continue;
            }
            $deltas[] = [
                'display_order' => (int) $entry[0],
                'option_id'     => isset($entry[1]) && $entry[1] !== null && (int) $entry[1] > 0 ? (int) $entry[1] : null,
                'marked'        => !empty($entry[2]) ? 1 : 0,
            ];
        }

        if ($remSec >= -self::NETWORK_GRACE_SECONDS) {
            $this->attemptModel->batchSaveAnswers($attemptId, $deltas);
        }

        // Process incoming anti-cheat violations
        $allowedVTypes = [
            'TAB_SWITCH', 'WINDOW_BLUR', 'FULLSCREEN_EXIT', 'SPLIT_SCREEN',
            'COPY_ATTEMPT', 'PASTE_ATTEMPT', 'DEVTOOLS_KEY', 'MOBILE_SPOOF',
        ];
        $currentViolCount = (int) $attempt['violation_count'];
        foreach ($violations as $v) {
            $vType = strtoupper(trim((string) ($v['t'] ?? '')));
            if (!in_array($vType, $allowedVTypes, true)) {
                continue;
            }
            $clientTs = !empty($v['ts']) && strtotime((string) $v['ts']) !== false
                ? date('Y-m-d H:i:s', strtotime((string) $v['ts']))
                : null;
            $details  = isset($v['d']) && is_array($v['d']) ? $v['d'] : null;
            $currentViolCount = $this->attemptModel->logViolation($attemptId, $vType, $clientTs, $details, true);
        }

        // Check timer expiration
        if ($remSec <= 0) {
            $this->grader->gradeAndFinalizeAttempt($attemptId, 'Auto_Submitted_Time');
            return [
                'ok'  => 1,
                's'   => 'Auto_Submitted_Time',
                'rem' => 0,
                'vc'  => $currentViolCount,
            ];
        }

        // Check FIC-configured violation auto-submit policy
        $autoSubmitOnViolation = !empty($quiz['violation_auto_submit']);
        $maxViolations         = (int) ($quiz['max_violations'] ?? 3);
        if ($autoSubmitOnViolation && $currentViolCount >= $maxViolations) {
            $this->grader->gradeAndFinalizeAttempt($attemptId, 'Auto_Submitted_Violation');
            return [
                'ok'  => 1,
                's'   => 'Auto_Submitted_Violation',
                'rem' => 0,
                'vc'  => $currentViolCount,
            ];
        }

        return [
            'ok'  => 1,
            's'   => 'In_Progress',
            'rem' => max(0, $remSec),
            'vc'  => $currentViolCount,
            'mv'  => $maxViolations,
            'vas' => $autoSubmitOnViolation ? 1 : 0,
        ];
    }

    /**
     * Idempotent final submission with full offline-locked answer & violation reconciliation.
     *
     * Supports:
     *  1. Normal online submission.
     *  2. Delayed offline reconnect submission up to OFFLINE_RECONCILE_WINDOW_SECONDS (30 min)
     *     past `server_ends_at` (as long as the quiz is NOT Finalized/Disposed and the
     *     single-session `cookieLockToken` matches).
     *  3. Seamless reconciliation + re-grading if a background expiry sweep already marked
     *     the attempt `Auto_Submitted_Time` while the student's device was offline!
     */
    public function submitAttempt(
        int $attemptId,
        int $studentId,
        string $cookieLockToken,
        array $finalCompactAnswers = [],
        string $reason = 'Submitted',
        array $offlineViolations = [],
        ?string $clientLockedAt = null
    ): array {
        $attempt = $this->attemptModel->findById($attemptId);
        if (!$attempt || (int) $attempt['student_id'] !== $studentId) {
            return ['success' => false, 'message' => 'Attempt not found.'];
        }

        $quiz = $this->quizModel->findById((int) $attempt['quiz_id']);
        $quizFinalized = !$quiz || in_array((string) $quiz['status'], ['Finalized', 'Disposed'], true);

        $endsTs     = strtotime((string) $attempt['server_ends_at']) ?: time();
        $startedTs  = strtotime((string) $attempt['started_at']) ?: time();
        $withinOfflineWindow = (time() <= ($endsTs + self::OFFLINE_RECONCILE_WINDOW_SECONDS)) && !$quizFinalized;

        // Parse optional client-locked timestamp (clamped strictly within [startedTs, endsTs + grace])
        $lockedTs = null;
        if ($clientLockedAt !== null && trim($clientLockedAt) !== '') {
            $parsedTs = strtotime($clientLockedAt);
            if ($parsedTs !== false) {
                $lockedTs = max($startedTs, min($endsTs, $parsedTs));
            }
        }

        // Normalize incoming compact answers
        $deltas = [];
        $incomingAnsweredCount = 0;
        foreach ($finalCompactAnswers as $entry) {
            if (!is_array($entry) || !isset($entry[0])) {
                continue;
            }
            $optId = isset($entry[1]) && $entry[1] !== null && (int) $entry[1] > 0 ? (int) $entry[1] : null;
            if ($optId !== null) {
                $incomingAnsweredCount++;
            }
            $deltas[] = [
                'display_order' => (int) $entry[0],
                'option_id'     => $optId,
                'marked'        => !empty($entry[2]) ? 1 : 0,
            ];
        }

        // Check if this is an already-finalized attempt
        if ($attempt['status'] !== 'In_Progress') {
            // Allow delayed offline reconciliation ONLY if:
            //  - Attempt was auto-swept by timer (`Auto_Submitted_Time`) while student was offline
            //  - Quiz is NOT Finalized/Disposed and still within 30-min offline reconnect window
            //  - Exact same browser single-session cookie token matches
            //  - Incoming offline bundle contains MORE answered questions than currently in DB
            $canReconcileSwept = (
                $attempt['status'] === 'Auto_Submitted_Time'
                && $withinOfflineWindow
                && $cookieLockToken !== ''
                && hash_equals((string) $attempt['session_token_hash'], hash('sha256', $cookieLockToken))
                && !empty($deltas)
                && $incomingAnsweredCount > (int) $attempt['answered_count']
            );

            if ($canReconcileSwept) {
                $this->attemptModel->batchSaveAnswers($attemptId, $deltas);
                $this->recordOfflineViolations($attemptId, $offlineViolations);
                $regraded = $this->grader->gradeAndFinalizeAttempt($attemptId, 'Auto_Submitted_Time', true, $lockedTs);

                return [
                    'success'            => true,
                    'reconciled_offline' => true,
                    'status'             => $regraded['status'],
                    'summary'            => $regraded,
                    'message'            => 'Your offline answers have been synced and graded successfully.',
                ];
            }

            return [
                'success'           => true,
                'already_submitted' => true,
                'status'            => $attempt['status'],
                'summary'           => [
                    'answered_count'  => (int) $attempt['answered_count'],
                    'total_questions' => (int) $attempt['total_questions'],
                    'submitted_at'    => $attempt['submitted_at'],
                ],
                'message'           => 'Test has already been submitted.',
            ];
        }

        if ($cookieLockToken === '' || !hash_equals((string) $attempt['session_token_hash'], hash('sha256', $cookieLockToken))) {
            return ['success' => false, 'message' => PrelimsEligibilityService::DUPLICATE_SESSION_MESSAGE];
        }

        // Reconcile all locally cached answers (accepts within 30-min offline reconnect window before finalization)
        if (!empty($deltas) && $withinOfflineWindow) {
            $this->attemptModel->batchSaveAnswers($attemptId, $deltas);
        }

        // Log any offline-queued violations
        $this->recordOfflineViolations($attemptId, $offlineViolations);

        $finalStatus = (time() > ($endsTs + self::NETWORK_GRACE_SECONDS) && $reason === 'Submitted' && $lockedTs === null)
            ? 'Auto_Submitted_Time'
            : $reason;
        $graded = $this->grader->gradeAndFinalizeAttempt($attemptId, $finalStatus, false, $lockedTs);

        return [
            'success' => true,
            'status'  => $graded['status'],
            'summary' => $graded,
            'message' => 'Your test has been submitted successfully.',
        ];
    }

    /**
     * Record offline-queued violation entries cleanly.
     */
    private function recordOfflineViolations(int $attemptId, array $offlineViolations): void
    {
        if (empty($offlineViolations)) {
            return;
        }
        $allowedVTypes = [
            'TAB_SWITCH', 'WINDOW_BLUR', 'FULLSCREEN_EXIT', 'SPLIT_SCREEN',
            'COPY_ATTEMPT', 'PASTE_ATTEMPT', 'DEVTOOLS_KEY', 'MOBILE_SPOOF',
        ];
        foreach ($offlineViolations as $v) {
            if (!is_array($v)) {
                continue;
            }
            $vType = strtoupper(trim((string) ($v['t'] ?? '')));
            if (!in_array($vType, $allowedVTypes, true)) {
                continue;
            }
            $clientTs = !empty($v['ts']) && strtotime((string) $v['ts']) !== false
                ? date('Y-m-d H:i:s', strtotime((string) $v['ts']))
                : null;
            $details = isset($v['d']) && is_array($v['d']) ? $v['d'] : ['offline_synced' => true];
            $this->attemptModel->logViolation($attemptId, $vType, $clientTs, $details, true);
        }
    }

    /**
     * Sweep and auto-finalize all expired In_Progress attempts for a quiz.
     */
    public function sweepExpiredAttempts(int $quizId): int
    {
        $expiredIds = $this->attemptModel->getExpiredInProgressAttempts($quizId, self::NETWORK_GRACE_SECONDS);
        $count = 0;
        foreach ($expiredIds as $aid) {
            $this->grader->gradeAndFinalizeAttempt((int) $aid, 'Auto_Submitted_Time');
            $count++;
        }
        return $count;
    }

    /**
     * FIC audited Force-Submit for an active attempt.
     */
    public function forceSubmitByFic(int $attemptId, int $ficUserId, string $reason = ''): array
    {
        $attempt = $this->attemptModel->findById($attemptId);
        if (!$attempt) {
            return ['success' => false, 'message' => 'Attempt not found.'];
        }
        if ($attempt['status'] !== 'In_Progress') {
            return ['success' => false, 'message' => 'Attempt is already finalized.'];
        }

        $this->attemptModel->logViolation($attemptId, 'FIC_FORCE_SUBMIT', null, [
            'fic_user_id' => $ficUserId,
            'reason'      => $reason,
        ], false);

        $graded = $this->grader->gradeAndFinalizeAttempt($attemptId, 'Force_Submitted');
        $this->auditModel->log('PrelimsQuiz', (int) $attempt['quiz_id'], 'Force Submit Attempt', $ficUserId, "Attempt #{$attemptId}: {$reason}");

        return ['success' => true, 'message' => 'Attempt force-submitted and graded.', 'summary' => $graded];
    }

    /**
     * FIC audited Emergency Re-Entry Grant (e.g., lab PC power failure) — issues a one-time
     * unlock token that re-binds the attempt cleanly to the replacement PC.
     */
    public function grantEmergencyReentry(int $attemptId, int $ficUserId, int $extraMinutes = 0, string $reason = ''): array
    {
        $attempt = $this->attemptModel->findById($attemptId);
        if (!$attempt) {
            return ['success' => false, 'message' => 'Attempt not found.'];
        }
        if ($attempt['status'] !== 'In_Progress') {
            return ['success' => false, 'message' => 'Cannot grant re-entry to a finalized attempt.'];
        }

        $extraSeconds = max(0, min(30, $extraMinutes)) * 60;
        $reentryCode  = strtoupper(bin2hex(random_bytes(4))); // 8-char code given to student at lab PC
        $newTokenHash = hash('sha256', $reentryCode);

        $this->attemptModel->grantReentryToken($attemptId, $newTokenHash, $extraSeconds);
        $this->attemptModel->logViolation($attemptId, 'FIC_REENTRY_GRANT', null, [
            'fic_user_id'   => $ficUserId,
            'extra_seconds' => $extraSeconds,
            'reason'        => $reason,
        ], false);
        $this->auditModel->log('PrelimsQuiz', (int) $attempt['quiz_id'], 'Emergency Re-Entry Granted', $ficUserId, "Attempt #{$attemptId} (+{$extraMinutes}m): {$reason}");

        return [
            'success'      => true,
            'reentry_code' => $reentryCode,
            'message'      => "Emergency re-entry code generated: {$reentryCode}",
        ];
    }

    /**
     * FIC audited Re-Open & Resume for a student whose attempt was mistakenly
     * auto-submitted (e.g., browser popup violation or technical/network issue).
     * Preserves all previously saved answers, resets violation count, grants fresh
     * resume minutes, and issues a secure 8-char Re-Entry Code bound to their desktop.
     */
    public function reopenAttemptByFic(
        int $attemptId,
        int $ficUserId,
        int $resumeMinutes = 10,
        bool $resetViolations = true,
        string $reason = ''
    ): array {
        $attempt = $this->attemptModel->findById($attemptId);
        if (!$attempt) {
            return ['success' => false, 'message' => 'Attempt not found.'];
        }

        $quiz = $this->quizModel->findById((int) $attempt['quiz_id']);
        if (!$quiz) {
            return ['success' => false, 'message' => 'Quiz not found.'];
        }
        if (in_array($quiz['status'], ['Finalized', 'Disposed'], true)) {
            return ['success' => false, 'message' => 'Cannot re-open attempts after the quiz has been Finalized or Disposed.'];
        }
        if ($attempt['status'] === 'In_Progress') {
            return $this->grantEmergencyReentry($attemptId, $ficUserId, $resumeMinutes, $reason);
        }

        // Verify attempt question paper still exists
        $qCntStmt = $this->db->prepare("SELECT COUNT(*) FROM prelims_attempt_questions WHERE attempt_id = :aid");
        $qCntStmt->execute(['aid' => $attemptId]);
        if ((int) $qCntStmt->fetchColumn() === 0) {
            return ['success' => false, 'message' => 'Attempt question paper is no longer available. Use Reset (Full Retake) instead.'];
        }

        $resumeSec    = max(1, min(180, $resumeMinutes)) * 60;
        $reentryCode  = strtoupper(bin2hex(random_bytes(4)));
        $newTokenHash = hash('sha256', $reentryCode);
        $oldStatus    = (string) $attempt['status'];

        $this->attemptModel->reopenSubmittedAttempt($attemptId, $newTokenHash, $resumeSec, $resetViolations);
        $this->attemptModel->logViolation($attemptId, 'FIC_REENTRY_GRANT', null, [
            'action'           => 'REOPEN_SUBMITTED',
            'previous_status'  => $oldStatus,
            'fic_user_id'      => $ficUserId,
            'resume_minutes'   => $resumeMinutes,
            'reset_violations' => $resetViolations,
            'reason'           => $reason,
        ], false);

        $this->auditModel->log(
            'PrelimsQuiz',
            (int) $attempt['quiz_id'],
            'Re-Opened Student Attempt',
            $ficUserId,
            "Attempt #{$attemptId} (Student #{$attempt['student_id']}, Prev: {$oldStatus}, +{$resumeMinutes}m): {$reason}"
        );

        return [
            'success'      => true,
            'reentry_code' => $reentryCode,
            'message'      => "Attempt re-opened with {$resumeMinutes} min remaining. Give this Re-Entry Code to the student: {$reentryCode}",
        ];
    }

    /**
     * FIC audited Full Reset / Retake for a student after a technical failure.
     * Logs full audit trail of the discarded attempt and removes it so the student
     * can attend a brand-new shuffled exam paper from /student/prelims.
     */
    public function resetAttemptForRetakeByFic(int $attemptId, int $ficUserId, string $reason = ''): array
    {
        $attempt = $this->attemptModel->findById($attemptId);
        if (!$attempt) {
            return ['success' => false, 'message' => 'Attempt not found.'];
        }

        $quiz = $this->quizModel->findById((int) $attempt['quiz_id']);
        if (!$quiz) {
            return ['success' => false, 'message' => 'Quiz not found.'];
        }
        if (in_array($quiz['status'], ['Finalized', 'Disposed'], true)) {
            return ['success' => false, 'message' => 'Cannot reset attempts after the quiz has been Finalized or Disposed.'];
        }

        $quizId    = (int) $attempt['quiz_id'];
        $studentId = (int) $attempt['student_id'];
        $oldStatus = (string) $attempt['status'];
        $oldScore  = (string) $attempt['raw_score'];

        $this->attemptModel->deleteAttemptForReset($attemptId);

        $this->auditModel->log(
            'PrelimsQuiz',
            $quizId,
            'Reset Attempt For Retake',
            $ficUserId,
            "Deleted Attempt #{$attemptId} (Student #{$studentId}, Prev Status: {$oldStatus}, Prev Score: {$oldScore}). Reason: {$reason}"
        );

        return [
            'success' => true,
            'message' => 'Student attempt has been reset. They can now attend the quiz fresh from their Student Portal.',
        ];
    }

    private function buildCompactBootstrapPayload(array $quiz, array $attempt, string $plainLockToken): array
    {
        $attemptId = (int) $attempt['attempt_id'];
        $paper     = $this->attemptModel->getExamPaperForAttempt($attemptId);
        $endsTs    = strtotime((string) $attempt['server_ends_at']) ?: time();
        $remSec    = max(0, $endsTs - time());

        return [
            'success'    => true,
            'lock_token' => $plainLockToken,
            'payload'    => [
                'aid' => $attemptId,
                'qid' => (int) $quiz['quiz_id'],
                'ttl' => (string) $quiz['title'],
                'srv' => time(),
                'rem' => $remSec,
                'fs'  => (int) $quiz['require_fullscreen'],
                'vas' => (int) $quiz['violation_auto_submit'],
                'mv'  => (int) $quiz['max_violations'],
                'vc'  => (int) $attempt['violation_count'],
                'q'   => $paper,
            ],
        ];
    }
}
