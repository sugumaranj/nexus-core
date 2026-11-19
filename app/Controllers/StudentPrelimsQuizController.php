<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\StudentAuthMiddleware;
use App\Models\AttendanceRecordModel;
use App\Models\Prelims\PrelimsAttemptModel;
use App\Models\Prelims\PrelimsQuizModel;
use App\Services\Prelims\DeviceGuardService;
use App\Services\Prelims\PrelimsEligibilityService;
use App\Services\Prelims\QuizAttemptService;

final class StudentPrelimsQuizController extends BaseController
{
    private PrelimsQuizModel          $quizModel;
    private PrelimsAttemptModel       $attemptModel;
    private AttendanceRecordModel     $attendanceModel;
    private PrelimsEligibilityService $eligibility;
    private QuizAttemptService        $attemptService;
    private DeviceGuardService        $deviceGuard;

    public function __construct()
    {
        StudentAuthMiddleware::handle();
        $this->quizModel       = new PrelimsQuizModel();
        $this->attemptModel    = new PrelimsAttemptModel();
        $this->attendanceModel = new AttendanceRecordModel();
        $this->eligibility     = new PrelimsEligibilityService();
        $this->attemptService  = new QuizAttemptService();
        $this->deviceGuard     = new DeviceGuardService();
    }

    private function student(): array
    {
        return (array) Session::get('student', []);
    }

    private function studentCsrfToken(): string
    {
        $sid = (int) ($this->student()['student_id'] ?? 0);
        $tok = substr(hash_hmac('sha256', (string) $sid, 'NexusCore_CSRF_Salt_2026'), 0, 32);
        Session::set('csrf_token', $tok);
        return $tok;
    }

    private function verifyStudentCsrf(string $submitted): bool
    {
        $exp = $this->studentCsrfToken();
        return $submitted !== '' && hash_equals($exp, $submitted);
    }

    private function jsonCompressed(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (
            is_string($json)
            && strlen($json) > 512
            && function_exists('gzencode')
            && str_contains((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip')
        ) {
            $gz = gzencode($json, 6);
            if ($gz !== false) {
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
                header('Content-Length: ' . strlen($gz));
                echo $gz;
                exit;
            }
        }
        echo $json ?: '{}';
        exit;
    }

    private function setLockCookie(int $quizId, string $token, int $ttlSeconds = 14400): void
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie(
            QuizAttemptService::lockCookieName($quizId),
            $token,
            [
                'expires'  => time() + max(600, $ttlSeconds),
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Strict',
            ]
        );
    }

    /**
     * GET /student/prelims
     * Lists all prelims quizzes for events this student is registered in.
     */
    public function index(): void
    {
        header('Accept-CH: Sec-CH-UA-Mobile, Sec-CH-UA-Platform, Sec-CH-UA-Model');

        $student   = $this->student();
        $studentId = (int) ($student['student_id'] ?? 0);
        $quizzes   = $this->quizModel->getQuizzesForStudent($studentId);

        foreach ($quizzes as &$q) {
            $q['prelims_attendance'] = $this->attendanceModel->getStudentPrelimsAttendanceStatus(
                (int) $q['symposium_event_id'],
                $studentId
            ) ?? 'Unmarked';
        }
        unset($q);

        $this->render('student.prelims.index', [
            'pageTitle' => 'Prelims Quizzes',
            'student'   => $student,
            'quizzes'   => $quizzes,
        ], 'student');
    }

    /**
     * GET /student/prelims/lobby?quiz_id=
     * Pre-Exam Readiness Gate: Desktop-only check, Attendance gate, Single-session check, Rules.
     */
    public function lobby(): void
    {
        header('Accept-CH: Sec-CH-UA-Mobile, Sec-CH-UA-Platform, Sec-CH-UA-Model');

        $quizId    = (int) ($_GET['quiz_id'] ?? 0);
        $student   = $this->student();
        $studentId = (int) ($student['student_id'] ?? 0);
        $cookieTok = (string) ($_COOKIE[QuizAttemptService::lockCookieName($quizId)] ?? '');

        $check = $this->eligibility->checkEligibility(
            $quizId,
            $studentId,
            $_SERVER,
            [],
            $cookieTok
        );

        if (!$check['eligible']) {
            $this->render('student.prelims.blocked', [
                'pageTitle' => 'Quiz Access Restricted',
                'student'   => $student,
                'check'     => $check,
                'csrfToken' => $this->studentCsrfToken(),
            ], 'student');
            return;
        }

        $this->render('student.prelims.lobby', [
            'pageTitle' => 'Exam Readiness Check — ' . ($check['quiz']['title'] ?? 'Prelims Quiz'),
            'student'   => $student,
            'quiz'      => $check['quiz'],
            'check'     => $check,
            'csrfToken' => $this->studentCsrfToken(),
        ], 'student');
    }

    /**
     * POST /student/prelims/reenter
     * Redeems an FIC-issued emergency re-entry code (for genuine lab PC crash) and sets new cookie.
     */
    public function reenter(): void
    {
        $quizId    = (int) ($_POST['quiz_id'] ?? 0);
        $code      = strtoupper(trim((string) ($_POST['reentry_code'] ?? '')));
        $csrf      = (string) ($_POST['csrf_token'] ?? '');
        $studentId = (int) ($this->student()['student_id'] ?? 0);

        if (!$this->verifyStudentCsrf($csrf) || $quizId <= 0 || $code === '') {
            $this->error('Invalid re-entry code or security token.');
            $this->redirect('/student/prelims/lobby?quiz_id=' . $quizId);
        }

        if ($this->deviceGuard->isObviousMobileRequest($_SERVER)) {
            $this->error('Mobile devices are strictly not allowed.');
            $this->redirect('/student/prelims/lobby?quiz_id=' . $quizId);
        }

        $attempt = $this->attemptModel->findByQuizAndStudent($quizId, $studentId);
        if (!$attempt || $attempt['status'] !== 'In_Progress') {
            $this->error('No active quiz session found for re-entry.');
            $this->redirect('/student/prelims');
        }

        if (!hash_equals((string) $attempt['session_token_hash'], hash('sha256', $code))) {
            $this->error('Invalid emergency re-entry code.');
            $this->redirect('/student/prelims/lobby?quiz_id=' . $quizId);
        }

        $this->setLockCookie($quizId, $code);
        $this->redirect('/student/prelims/exam?quiz_id=' . $quizId);
    }

    /**
     * POST /student/prelims/start
     * Validates all 5 gates + desktop hardware attestation, locks single session,
     * sets HttpOnly lock cookie, and returns gzipped compact exam payload.
     */
    public function start(): void
    {
        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($input)) {
            $this->jsonCompressed(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        if (!$this->verifyStudentCsrf((string) ($input['csrf_token'] ?? ''))) {
            $this->jsonCompressed(['success' => false, 'message' => 'Invalid security token. Please reload.'], 403);
        }

        $quizId        = (int) ($input['quiz_id'] ?? 0);
        $clientSignals = is_array($input['device'] ?? null) ? $input['device'] : [];
        $studentId     = (int) ($this->student()['student_id'] ?? 0);
        $cookieTok     = (string) ($_COOKIE[QuizAttemptService::lockCookieName($quizId)] ?? '');

        $res = $this->attemptService->startOrResumeAttempt(
            $quizId,
            $studentId,
            $_SERVER,
            $clientSignals,
            $cookieTok
        );

        if (empty($res['success'])) {
            $httpCode = ($res['code'] ?? '') === 'DUPLICATE_SESSION' ? 409 : 403;
            $this->jsonCompressed($res, $httpCode);
        }

        // Issue/refresh HttpOnly SameSite=Strict lock cookie bound to this browser
        $ttl = ((int) ($res['payload']['rem'] ?? 3600)) + 1800;
        $this->setLockCookie($quizId, (string) $res['lock_token'], $ttl);

        $this->jsonCompressed([
            'success' => true,
            'exam_url'=> base_url('/student/prelims/exam?quiz_id=' . $quizId),
            'payload' => $res['payload'],
        ]);
    }

    /**
     * GET /student/prelims/exam?quiz_id=
     * Ultra-Low-Bandwidth Standalone Exam Shell (< 7 KB gzipped, zero external frameworks!).
     */
    public function exam(): void
    {
        header('Accept-CH: Sec-CH-UA-Mobile, Sec-CH-UA-Platform, Sec-CH-UA-Model');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $quizId    = (int) ($_GET['quiz_id'] ?? 0);
        $student   = $this->student();
        $studentId = (int) ($student['student_id'] ?? 0);
        $cookieTok = (string) ($_COOKIE[QuizAttemptService::lockCookieName($quizId)] ?? '');

        // Server-side hard block on mobile headers
        if ($this->deviceGuard->isObviousMobileRequest($_SERVER)) {
            $this->redirect('/student/prelims/lobby?quiz_id=' . $quizId);
        }

        $check = $this->eligibility->checkEligibility($quizId, $studentId, $_SERVER, [], $cookieTok);
        if (!$check['eligible'] || $check['mode'] !== 'resume') {
            if (($check['code'] ?? '') === 'ALREADY_COMPLETED' || ($check['code'] ?? '') === 'TIME_EXPIRED') {
                $this->redirect('/student/prelims/submitted?quiz_id=' . $quizId);
            }
            $this->redirect('/student/prelims/lobby?quiz_id=' . $quizId);
        }

        $boot = $this->attemptService->startOrResumeAttempt($quizId, $studentId, $_SERVER, [], $cookieTok);
        if (empty($boot['success'])) {
            $this->redirect('/student/prelims/lobby?quiz_id=' . $quizId);
        }

        // Enable gzip output compression for ultra-low bandwidth (1-3 kbps)
        if (!headers_sent() && function_exists('ob_gzhandler') && str_contains((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip')) {
            ob_start('ob_gzhandler');
        }

        $bootstrap = $boot['payload'];
        $csrfToken = $this->studentCsrfToken();
        $studentName = (string) ($student['full_name'] ?? 'Student');
        $studentReg  = (string) ($student['register_number'] ?? '');

        require dirname(__DIR__, 2) . '/templates/layouts/quiz_exam.php';
        exit;
    }

    /**
     * POST /student/prelims/sync
     * Micro-delta sync endpoint (~40 bytes request, ~50 bytes response).
     */
    public function sync(): void
    {
        $raw = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($raw)) {
            $this->jsonCompressed(['ok' => 0, 'code' => 'BAD_JSON'], 400);
        }

        if (!$this->verifyStudentCsrf((string) ($raw['c'] ?? ''))) {
            $this->jsonCompressed(['ok' => 0, 'code' => 'BAD_CSRF'], 403);
        }

        $attemptId = (int) ($raw['aid'] ?? 0);
        $quizId    = (int) ($raw['qid'] ?? 0);
        $studentId = (int) ($this->student()['student_id'] ?? 0);
        $cookieTok = (string) ($_COOKIE[QuizAttemptService::lockCookieName($quizId)] ?? '');

        $answers    = is_array($raw['a'] ?? null) ? $raw['a'] : [];
        $violations = is_array($raw['v'] ?? null) ? $raw['v'] : [];

        $res = $this->attemptService->syncDelta($attemptId, $studentId, $cookieTok, $answers, $violations);
        $this->jsonCompressed($res, !empty($res['ok']) ? 200 : 409);
    }

    /**
     * POST /student/prelims/submit
     * Idempotent final submission + full local answer map reconciliation (works via fetch or sendBeacon).
     */
    public function submit(): void
    {
        $raw = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($raw)) {
            $this->jsonCompressed(['success' => false, 'message' => 'Invalid submission payload.'], 400);
        }

        if (!$this->verifyStudentCsrf((string) ($raw['c'] ?? ''))) {
            $this->jsonCompressed(['success' => false, 'message' => 'Invalid security token.'], 403);
        }

        $attemptId         = (int) ($raw['aid'] ?? 0);
        $quizId            = (int) ($raw['qid'] ?? 0);
        $studentId         = (int) ($this->student()['student_id'] ?? 0);
        $cookieTok         = (string) ($_COOKIE[QuizAttemptService::lockCookieName($quizId)] ?? '');
        $allAns            = is_array($raw['a'] ?? null) ? $raw['a'] : [];
        $offlineViolations = is_array($raw['v'] ?? null) ? $raw['v'] : [];
        $clientLockedAt    = isset($raw['la']) && is_string($raw['la']) ? trim($raw['la']) : null;
        $reason            = in_array((string) ($raw['r'] ?? ''), ['Submitted', 'Auto_Submitted_Time', 'Auto_Submitted_Violation'], true)
            ? (string) $raw['r']
            : 'Submitted';

        $res = $this->attemptService->submitAttempt(
            $attemptId,
            $studentId,
            $cookieTok,
            $allAns,
            $reason,
            $offlineViolations,
            $clientLockedAt
        );

        if (!empty($res['success'])) {
            // Clear lock cookie upon confirmed submission
            setcookie(QuizAttemptService::lockCookieName($quizId), '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
            $res['redirect_url'] = base_url('/student/prelims/submitted?quiz_id=' . $quizId);
        }

        $this->jsonCompressed($res, !empty($res['success']) ? 200 : 400);
    }

    /**
     * GET /student/prelims/submitted?quiz_id=
     */
    public function submitted(): void
    {
        $quizId    = (int) ($_GET['quiz_id'] ?? 0);
        $student   = $this->student();
        $studentId = (int) ($student['student_id'] ?? 0);

        $quiz    = $this->quizModel->findById($quizId);
        $attempt = $this->attemptModel->findByQuizAndStudent($quizId, $studentId);

        if (!$quiz || !$attempt) {
            $this->redirect('/student/prelims');
        }

        $this->render('student.prelims.submitted', [
            'pageTitle' => 'Quiz Submitted — ' . ($quiz['title'] ?? ''),
            'student'   => $student,
            'quiz'      => $quiz,
            'attempt'   => $attempt,
        ], 'student');
    }
}
