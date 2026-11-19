<?php

declare(strict_types=1);

namespace App\Services\Prelims;

use App\Database\Database;
use App\Models\AttendanceRecordModel;
use App\Models\Prelims\PrelimsAttemptModel;
use App\Models\Prelims\PrelimsQuizModel;
use PDO;

/**
 * Atomic 5-Gate Eligibility Guard for Student Prelims Quiz Access:
 *   Gate 1: Approved event registration (Individual or Team member).
 *   Gate 2: Prelims Attendance strictly marked 'Present' or 'Late'.
 *   Gate 3: Quiz status is 'Live' and within schedule window.
 *   Gate 4: Desktop-only device verification (blocks mobile even in Desktop Site mode).
 *   Gate 5: Strict Single-Session / Single-Attempt non-interruption enforcement.
 */
final class PrelimsEligibilityService
{
    public const DUPLICATE_SESSION_MESSAGE = 'Only one time a student can attend the test.';

    private PDO                   $db;
    private PrelimsQuizModel      $quizModel;
    private PrelimsAttemptModel   $attemptModel;
    private AttendanceRecordModel $attendanceModel;
    private DeviceGuardService    $deviceGuard;

    public function __construct(
        ?PrelimsQuizModel $quizModel = null,
        ?PrelimsAttemptModel $attemptModel = null,
        ?AttendanceRecordModel $attendanceModel = null,
        ?DeviceGuardService $deviceGuard = null
    ) {
        $this->db              = Database::getConnection();
        $this->quizModel       = $quizModel ?? new PrelimsQuizModel();
        $this->attemptModel    = $attemptModel ?? new PrelimsAttemptModel();
        $this->attendanceModel = $attendanceModel ?? new AttendanceRecordModel();
        $this->deviceGuard     = $deviceGuard ?? new DeviceGuardService();
    }

    /**
     * Evaluate all 5 eligibility gates for a student entering or starting a prelims quiz.
     *
     * @param array<string, mixed> $serverHeaders
     * @param array<string, mixed> $clientSignals
     * @return array{
     *   eligible: bool,
     *   mode: string,
     *   code: string,
     *   message: string,
     *   quiz?: array,
     *   application_id?: int,
     *   attendance_status?: string,
     *   attempt?: array,
     *   device?: array
     * }
     */
    public function checkEligibility(
        int $quizId,
        int $studentId,
        array $serverHeaders = [],
        array $clientSignals = [],
        ?string $cookieLockToken = null
    ): array {
        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz) {
            return [
                'eligible' => false,
                'mode'     => 'blocked',
                'code'     => 'QUIZ_NOT_FOUND',
                'message'  => 'Prelims quiz not found.',
            ];
        }

        $eventId = (int) $quiz['symposium_event_id'];

        // GATE 1: Approved registration (Individual or Team member)
        $applicationId = $this->resolveApprovedApplicationId($eventId, $studentId);
        if ($applicationId === null) {
            return [
                'eligible' => false,
                'mode'     => 'blocked',
                'code'     => 'NOT_REGISTERED',
                'message'  => 'You do not have an approved registration for this event.',
                'quiz'     => $quiz,
            ];
        }

        // GATE 2: Prelims Attendance strictly marked 'Present' or 'Late'
        $attStatus = $this->attendanceModel->getStudentPrelimsAttendanceStatus($eventId, $studentId);
        if (!in_array($attStatus, ['Present', 'Late'], true)) {
            $detail = $attStatus === 'Absent'
                ? 'Your prelims attendance is marked Absent.'
                : 'Your prelims attendance has not been marked yet.';
            return [
                'eligible'          => false,
                'mode'              => 'blocked',
                'code'              => 'ATTENDANCE_REQUIRED',
                'message'           => "{$detail} Only students marked Present or Late in Prelims Attendance are permitted to attend this quiz.",
                'quiz'              => $quiz,
                'application_id'    => $applicationId,
                'attendance_status' => $attStatus ?? 'Unmarked',
            ];
        }

        // GATE 4 (Device Check — before session check so mobile is immediately blocked)
        $deviceEval = $this->deviceGuard->evaluate($serverHeaders, $clientSignals);
        if (!empty($quiz['block_mobile']) && !$deviceEval['allowed']) {
            return [
                'eligible'          => false,
                'mode'              => 'blocked',
                'code'              => 'MOBILE_BLOCKED',
                'message'           => 'Mobile phones and tablets (including Desktop Site mode) are strictly not allowed for this quiz. Please use a desktop or laptop computer.',
                'quiz'              => $quiz,
                'application_id'    => $applicationId,
                'attendance_status' => $attStatus,
                'device'            => $deviceEval,
            ];
        }

        // GATE 5: Strict Single-Session / Single-Attempt Check (NEVER interrupts an active session!)
        $existingAttempt = $this->attemptModel->findByQuizAndStudent($quizId, $studentId);
        if ($existingAttempt) {
            if ($existingAttempt['status'] !== 'In_Progress') {
                return [
                    'eligible'          => false,
                    'mode'              => 'completed',
                    'code'              => 'ALREADY_COMPLETED',
                    'message'           => self::DUPLICATE_SESSION_MESSAGE,
                    'quiz'              => $quiz,
                    'application_id'    => $applicationId,
                    'attendance_status' => $attStatus,
                    'attempt'           => $existingAttempt,
                    'device'            => $deviceEval,
                ];
            }

            // Attempt is currently In_Progress — verify same-browser cryptographic lock cookie
            $hasValidCookie = ($cookieLockToken !== null && $cookieLockToken !== '')
                && hash_equals((string) $existingAttempt['session_token_hash'], hash('sha256', $cookieLockToken));

            if (!$hasValidCookie) {
                // Log blocked duplicate login attempt WITHOUT altering or interrupting the active session!
                $this->attemptModel->logViolation(
                    (int) $existingAttempt['attempt_id'],
                    'DUPLICATE_SESSION_BLOCKED',
                    null,
                    ['blocked_ip' => $serverHeaders['REMOTE_ADDR'] ?? null],
                    false
                );

                return [
                    'eligible'          => false,
                    'mode'              => 'blocked_duplicate',
                    'code'              => 'DUPLICATE_SESSION',
                    'message'           => self::DUPLICATE_SESSION_MESSAGE . ' An active quiz session is already running in another browser/device and cannot be interrupted.',
                    'quiz'              => $quiz,
                    'application_id'    => $applicationId,
                    'attendance_status' => $attStatus,
                    'attempt'           => $existingAttempt,
                    'device'            => $deviceEval,
                ];
            }

            // Same browser/session token matched! Allow seamless resume if time still remains
            $endsTs = strtotime((string) $existingAttempt['server_ends_at']);
            if ($endsTs !== false && time() > ($endsTs + 10)) {
                return [
                    'eligible'          => false,
                    'mode'              => 'expired',
                    'code'              => 'TIME_EXPIRED',
                    'message'           => 'Your quiz timer has expired and your responses have been finalized.',
                    'quiz'              => $quiz,
                    'application_id'    => $applicationId,
                    'attendance_status' => $attStatus,
                    'attempt'           => $existingAttempt,
                    'device'            => $deviceEval,
                ];
            }

            return [
                'eligible'          => true,
                'mode'              => 'resume',
                'code'              => 'OK_RESUME',
                'message'           => 'Resuming active quiz session.',
                'quiz'              => $quiz,
                'application_id'    => $applicationId,
                'attendance_status' => $attStatus,
                'attempt'           => $existingAttempt,
                'device'            => $deviceEval,
            ];
        }

        // GATE 3: Quiz Lifecycle Status & Schedule Window (for fresh starts)
        if ($quiz['status'] !== 'Live') {
            $statusMsg = match ($quiz['status']) {
                'Published' => 'The quiz has not started yet. Please wait for the Faculty In-Charge to launch it.',
                'Paused'    => 'The quiz is temporarily paused by the Faculty In-Charge.',
                'Closed', 'Finalized', 'Disposed' => 'This prelims quiz has already closed.',
                default     => 'This quiz is not open for students yet.',
            };
            return [
                'eligible'          => false,
                'mode'              => 'blocked',
                'code'              => 'QUIZ_NOT_LIVE',
                'message'           => $statusMsg,
                'quiz'              => $quiz,
                'application_id'    => $applicationId,
                'attendance_status' => $attStatus,
                'device'            => $deviceEval,
            ];
        }

        $now = time();
        if (!empty($quiz['window_start']) && $now < strtotime((string) $quiz['window_start'])) {
            return [
                'eligible'          => false,
                'mode'              => 'blocked',
                'code'              => 'BEFORE_WINDOW',
                'message'           => 'This quiz opens at ' . date('d M Y h:i A', strtotime((string) $quiz['window_start'])) . '.',
                'quiz'              => $quiz,
                'application_id'    => $applicationId,
                'attendance_status' => $attStatus,
                'device'            => $deviceEval,
            ];
        }

        if (!empty($quiz['window_end']) && $now > strtotime((string) $quiz['window_end'])) {
            return [
                'eligible'          => false,
                'mode'              => 'blocked',
                'code'              => 'AFTER_WINDOW',
                'message'           => 'The scheduled window for this quiz has ended.',
                'quiz'              => $quiz,
                'application_id'    => $applicationId,
                'attendance_status' => $attStatus,
                'device'            => $deviceEval,
            ];
        }

        return [
            'eligible'          => true,
            'mode'              => 'start',
            'code'              => 'OK_START',
            'message'           => 'Eligible to begin the prelims quiz.',
            'quiz'              => $quiz,
            'application_id'    => $applicationId,
            'attendance_status' => $attStatus,
            'device'            => $deviceEval,
        ];
    }

    /**
     * Resolve an approved application_id for either Individual or Team registration.
     */
    public function resolveApprovedApplicationId(int $symposiumEventId, int $studentId): ?int
    {
        $sql = "
            SELECT a.application_id
            FROM applications a
            WHERE a.symposium_event_id = :eid1
              AND a.student_id = :sid1
              AND a.application_type = 'Individual'
              AND a.application_status = 'Approved'
            UNION ALL
            SELECT a.application_id
            FROM team_members tm
            INNER JOIN teams t ON t.team_id = tm.team_id
            INNER JOIN applications a ON a.application_id = t.application_id
            WHERE a.symposium_event_id = :eid2
              AND tm.student_id = :sid2
              AND a.application_type = 'Team'
              AND a.application_status = 'Approved'
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'eid1' => $symposiumEventId,
            'sid1' => $studentId,
            'eid2' => $symposiumEventId,
            'sid2' => $studentId,
        ]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int) $val : null;
    }
}
