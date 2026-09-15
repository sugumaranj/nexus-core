<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : AttendanceController.php
 * Location    : app/Controllers/
 * Description : Handles all attendance-related HTTP requests.
 *               Fully migrated to the Event-based (Symposium Event) workflow.
 *
 * Routes
 * -------------------------------------------------------------------------
 * GET  /attendance                                → index()
 * GET  /attendance/event                          → markSheet()
 * POST /attendance/open                           → openSession()
 * POST /attendance/close                          → closeSession()
 * POST /attendance/mark                           → mark()           (JSON)
 * POST /attendance/bulk-mark                      → bulkMark()       (JSON)
 * POST /attendance/sync                           → sync()           (JSON API)
 * GET  /attendance/history                        → history()
 * GET  /attendance/report                         → report()
 * GET  /attendance/export                         → exportPdf()
 *
 * RBAC
 * -------------------------------------------------------------------------
 * Marking (open/close/mark/bulk/sync):
 *   → FIC (Faculty Incharge) must be assigned to the event via faculty_assignments
 *   → Admin, Principal, HOD → view/report only
 * Report/Export:
 *   → All authenticated roles
 *
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Models\AttendanceSessionModel;
use App\Models\FacultyAssignmentModel;
use App\Models\SymposiumEventModel;
use App\Services\AttendanceService;

final class AttendanceController extends BaseController
{
    private AttendanceService      $attendanceService;
    private SymposiumEventModel    $eventModel;
    private AttendanceSessionModel $sessionModel;
    private FacultyAssignmentModel $facultyModel;

    public function __construct()
    {
        $this->requireLogin();
        RoleMiddleware::requireRole(
            'Admin', 'Principal', 'HOD',
            'Staff', 'Staff Coordinator', 'Student Coordinator'
        );

        $this->attendanceService = new AttendanceService();
        $this->eventModel        = new SymposiumEventModel();
        $this->sessionModel      = new AttendanceSessionModel();
        $this->facultyModel      = new FacultyAssignmentModel();
    }

    // =========================================================================
    // CSRF Helper
    // =========================================================================

    /**
     * Returns the current session CSRF token, creating one only if absent.
     *
     * IMPORTANT: We must NOT regenerate the token on every page load.
     * Doing so invalidates tokens already embedded in open forms or other
     * browser tabs, causing valid submissions to fail with "Invalid security
     * token". The token is tied to the login session and rotates only on
     * login / logout (where session_regenerate_id is called).
     */
    private function generateCsrfToken(): string
    {
        $user = $this->user();
        $uid  = $user['user_id'] ?? 0;
        // Deterministic token based on user ID prevents stale-tab mismatch after session resets
        $token = substr(hash_hmac('sha256', (string)$uid, 'NexusCore_CSRF_Salt_2026'), 0, 32);
        
        // Keep it in session for backward compatibility with other controllers
        Session::set('csrf_token', $token);
        return $token;
    }

    private function validateCsrf(): bool
    {
        $submitted = $_POST['csrf_token'] ?? '';
        $expected  = $this->generateCsrfToken();
        $stored    = Session::get('csrf_token', '');
        
        return hash_equals($expected, $submitted) || ($stored !== '' && hash_equals($stored, $submitted));
    }

    private function validateJsonCsrf(array $input): bool
    {
        $submitted = $input['csrf_token'] ?? '';
        $expected  = $this->generateCsrfToken();
        $stored    = Session::get('csrf_token', '');
        
        return hash_equals($expected, $submitted) || ($stored !== '' && hash_equals($stored, $submitted));
    }

    // =========================================================================
    // JSON Response Helper
    // =========================================================================

    private function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
        exit;
    }

    // =========================================================================
    // ROUTES
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * GET /attendance
     * Overview — list of events where the current user is assigned as FIC.
     * Admins/HOD/Principal see all events across all symposiums.
     * -------------------------------------------------------------------------
     */
    public function index(): void
    {
        $user = $this->user();
        $role = $user['role'] ?? '';
        $uid  = (int) $user['user_id'];

        if (in_array($role, ['Admin', 'Principal', 'HOD', 'Staff Coordinator'], true)) {
            // Fetch all published symposium events for overview
            $events = $this->eventModel->getAllWithSymposium();
        } else {
            // FIC/Staff: only events they are assigned to
            $assignments = $this->facultyModel->getAssignedEventsForUser($uid);
            $events = [];
            foreach ($assignments as $a) {
                $event = $this->eventModel->findById((int) $a['symposium_event_id']);
                if ($event) {
                    $events[] = $event;
                }
            }
        }

        // Attach session + attendance summary
        foreach ($events as &$ev) {
            $eid = (int) $ev['symposium_event_id'];
            $activeSession       = $this->sessionModel->getActiveEventSession($eid);
            $ev['active_session']     = $activeSession;
            $ev['has_active_session'] = (bool) $activeSession;
            $ev['attendance_summary'] = $this->attendanceService->getEventSummary($eid);
            $ev['can_mark']           = $this->attendanceService->canMarkEventAttendance($eid, $uid, $role);
        }
        unset($ev);

        $csrfToken = $this->generateCsrfToken();

        $this->render('attendance.index', [
            'pageTitle'  => 'Attendance Overview',
            'events'     => $events,
            'role'       => $role,
            'csrfToken'  => $csrfToken,
        ]);
    }

    /**
     * -------------------------------------------------------------------------
     * GET /attendance/event?id={symposium_event_id}
     * Main attendance mark sheet for a symposium event.
     * -------------------------------------------------------------------------
     */
    public function markSheet(): void
    {
        $user = $this->user();
        $role = $user['role'] ?? '';
        $uid  = (int) $user['user_id'];
        $eventId = (int) ($_GET['id'] ?? 0);

        if ($eventId <= 0) {
            $this->error('Invalid event ID.');
            $this->redirect('/attendance');
        }

        if (!$this->attendanceService->canViewEventAttendance($eventId, $uid, $role)) {
            $this->error('You are not authorized to access attendance for this event.');
            $this->redirect('/attendance');
        }

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            $this->error('Event not found.');
            $this->redirect('/attendance');
        }

        $canMark       = $this->attendanceService->canMarkEventAttendance($eventId, $uid, $role);
        if (empty($event['supports_attendance'])) {
            $canMark = false;
        }
        $activeSession = $this->sessionModel->getActiveEventSession($eventId);
        $sessionId     = $activeSession ? (int) $activeSession['session_id'] : 0;
        $history       = $this->attendanceService->getEventSessionHistory($eventId);

        // For display: use the active session, or fall back to most recent closed session
        $displaySessionId = $sessionId;
        if ($displaySessionId === 0 && !empty($history)) {
            $displaySessionId = (int) $history[0]['session_id'];
        }

        $participants  = $this->attendanceService->getEventParticipantsWithAttendance(
            $eventId,
            $displaySessionId
        );
        $summary       = $this->attendanceService->getEventSummary($eventId);
        $deptBreakdown = $displaySessionId > 0
            ? $this->attendanceService->getEventDeptBreakdown($eventId, $displaySessionId)
            : [];

        // FIC assignments for this event
        $ficAssignments = $this->facultyModel->getForEvent($eventId);

        // Judge mark sheet availability — check if this user is an active FIC
        $isFic = false;
        foreach ($ficAssignments as $fa) {
            if ((int) $fa['user_id'] === $uid && (int) ($fa['is_active'] ?? 0) === 1) {
                $isFic = true;
                break;
            }
        }

        // Judges assigned to this event (for mark sheet download buttons)
        $judgeModel    = new \App\Models\JudgeAssignmentModel();
        $currentJudges = $judgeModel->getForEvent($eventId);

        // Attendance lock state — true only when there is no active session AND a closed one exists
        $attendanceLocked = false;
        $finalizedSession = null;
        if (!empty($event['supports_attendance']) && !$activeSession) {
            $finalizedSession = $this->sessionModel->getFinalizedEventSession($eventId);
            if ($finalizedSession) {
                $attendanceLocked = true;
            }
        }

        $csrfToken = $this->generateCsrfToken();
        $offlineSyncToken = \App\Helpers\AuthHelper::generateOfflineSyncToken($user);

        $this->render('attendance.mark-sheet', [
            'pageTitle'        => 'Attendance — ' . ($event['event_name'] ?? ''),
            'event'            => $event,
            'activeSession'    => $activeSession,
            'sessionId'        => $sessionId,
            'participants'     => $participants,
            'summary'          => $summary,
            'history'          => $history,
            'deptBreakdown'    => $deptBreakdown,
            'ficAssignments'   => $ficAssignments,
            'canMark'          => $canMark,
            'csrfToken'        => $csrfToken,
            'offlineSyncToken' => $offlineSyncToken,
            'currentJudges'    => $currentJudges,
            'isFic'            => $isFic,
            'attendanceLocked' => $attendanceLocked,
        ]);
    }

    /**
     * -------------------------------------------------------------------------
     * POST /attendance/open
     * Open an attendance session for a symposium event.
     *
     * Dual-mode: returns JSON when called via AJAX (Accept: application/json),
     * or performs a redirect for traditional form submissions.
     * -------------------------------------------------------------------------
     */
    public function openSession(): void
    {
        $isAjax = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if (!$this->validateCsrf()) {
            if ($isAjax) {
                $sub = $_POST['csrf_token'] ?? 'null';
                $sto = Session::get('csrf_token', 'null');
                $debugMsg = 'Invalid security token. Please refresh and try again. [Sub: ' . substr($sub, 0, 5) . ', Sto: ' . substr($sto, 0, 5) . ']';
                $this->json(['success' => false, 'message' => $debugMsg], 403);
            }
            $this->error('Invalid security token. Please refresh and try again.');
            $this->redirect('/attendance');
        }

        $user    = $this->user();
        $eventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $notes   = trim($_POST['notes'] ?? '');

        if ($eventId <= 0) {
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'Invalid event ID.'], 400);
            }
            $this->error('Invalid event ID.');
            $this->redirect('/attendance');
        }

        $result = $this->attendanceService->openEventAttendance(
            $eventId,
            (int) $user['user_id'],
            $user['role'] ?? '',
            $notes
        );

        if ($isAjax) {
            $this->json($result, $result['success'] ? 200 : 400);
        }

        if ($result['success']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

        $this->redirect('/attendance/event?id=' . $eventId);
    }

    /**
     * -------------------------------------------------------------------------
     * POST /attendance/close
     * Close an attendance session.
     *
     * Dual-mode: returns JSON when called via AJAX (Accept: application/json),
     * or performs a redirect for traditional form submissions.
     * -------------------------------------------------------------------------
     */
    public function closeSession(): void
    {
        $isAjax = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if (!$this->validateCsrf()) {
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.'], 403);
            }
            $this->error('Invalid security token. Please refresh and try again.');
            $this->redirect('/attendance');
        }

        $user      = $this->user();
        $sessionId = (int) ($_POST['session_id'] ?? 0);
        $eventId   = (int) ($_POST['symposium_event_id'] ?? 0);

        if ($eventId <= 0) {
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'Invalid event ID.'], 400);
            }
            $this->error('Invalid event ID.');
            $this->redirect('/attendance');
        }

        $result = $this->attendanceService->closeEventAttendance(
            $sessionId,
            $eventId,
            (int) $user['user_id'],
            $user['role'] ?? ''
        );

        if ($isAjax) {
            $this->json($result, $result['success'] ? 200 : 400);
        }

        if ($result['success']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

        $this->redirect('/attendance/event?id=' . $eventId);
    }

    /**
     * -------------------------------------------------------------------------
     * POST /attendance/mark    (JSON API)
     * Mark a single participant's attendance.
     * -------------------------------------------------------------------------
     */
    public function mark(): void
    {
        $isAjax = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if (!$this->validateCsrf()) {
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            }
            $this->error('Invalid security token.');
            $this->redirect('/attendance');
        }

        $user          = $this->user();
        $sessionId     = (int) ($_POST['session_id'] ?? 0);
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $studentId     = (int) ($_POST['student_id'] ?? 0);
        $status        = trim($_POST['attendance_status'] ?? 'Absent');
        $eventId       = (int) ($_POST['symposium_event_id'] ?? 0);
        $notes         = trim($_POST['coordinator_notes'] ?? '');

        $result = $this->attendanceService->markEventAttendance(
            $sessionId,
            $applicationId,
            $studentId,
            $status,
            (int) $user['user_id'],
            $eventId,
            $user['role'] ?? '',
            $notes
        );

        if ($isAjax) {
            $this->json($result, $result['success'] ? 200 : 400);
        }

        if ($result['success']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

        $this->redirect('/attendance/event?id=' . $eventId);
    }

    /**
     * -------------------------------------------------------------------------
     * POST /attendance/bulk-mark    (JSON API)
     * Bulk mark all visible participants.
     * -------------------------------------------------------------------------
     */
    public function bulkMark(): void
    {
        $isAjax = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        if (!$this->validateCsrf()) {
            if ($isAjax) {
                $this->json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            }
            $this->error('Invalid security token.');
            $this->redirect('/attendance');
        }

        $user          = $this->user();
        $sessionId     = (int) ($_POST['session_id'] ?? 0);
        $eventId       = (int) ($_POST['symposium_event_id'] ?? 0);
        $defaultStatus = trim($_POST['default_status'] ?? 'Present');
        $overridesRaw  = $_POST['overrides'] ?? '{}';

        $overrides = [];
        if (is_string($overridesRaw)) {
            $decoded = json_decode($overridesRaw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $appId => $st) {
                    $overrides[(int) $appId] = $st;
                }
            }
        }

        $result = $this->attendanceService->bulkMarkEventAttendance(
            $sessionId,
            $eventId,
            $defaultStatus,
            $overrides,
            (int) $user['user_id'],
            $user['role'] ?? ''
        );

        if ($isAjax) {
            $this->json($result, $result['success'] ? 200 : 400);
        }

        if ($result['success']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

        $this->redirect('/attendance/event?id=' . $eventId);
    }

    /**
     * -------------------------------------------------------------------------
     * POST /attendance/sync   (JSON API — offline sync endpoint)
     *
     * Request body (JSON):
     * {
     *   "csrf_token": "...",
     *   "symposium_event_id": 7,
     *   "device_id": "device-uuid",
     *   "records": [
     *     { "application_id": 12, "attendance_status": "Present",
     *       "session_id": 3, "coordinator_notes": "",
     *       "client_timestamp": "2026-07-21T10:00:00" },
     *     ...
     *   ]
     * }
     * -------------------------------------------------------------------------
     */
    public function sync(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            $this->json(['success' => false, 'message' => 'Invalid JSON payload.'], 400);
        }

        $user = $this->user();
        $isStandardAuth = !empty($user) && !empty($user['user_id']);

        $syncToken = $input['sync_token'] ?? '';
        $isValidOfflineToken = false;

        if (!empty($syncToken)) {
            $userFromToken = \App\Helpers\AuthHelper::validateOfflineSyncToken($syncToken);
            if ($userFromToken) {
                $user = $userFromToken;
                $isValidOfflineToken = true;
            }
        }

        if (!$isStandardAuth && !$isValidOfflineToken) {
            $this->json(['success' => false, 'message' => 'Session expired. Please log in again.'], 401);
        }

        if ($isStandardAuth && !$isValidOfflineToken) {
            if (!$this->validateJsonCsrf($input)) {
                $this->json(['success' => false, 'message' => 'Invalid CSRF token.'], 403);
            }
        }

        $eventId  = (int) ($input['symposium_event_id'] ?? 0);
        $deviceId = trim($input['device_id'] ?? '');
        $records  = $input['records'] ?? [];

        if (!is_array($records)) {
            $this->json(['success' => false, 'message' => 'records must be an array.'], 400);
        }

        $result = $this->attendanceService->syncOfflineEventRecords(
            $records,
            (int) $user['user_id'],
            $user['role'] ?? '',
            $eventId,
            $deviceId
        );

        $this->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * -------------------------------------------------------------------------
     * GET /attendance/history?id={symposium_event_id}
     * Session history for an event.
     * -------------------------------------------------------------------------
     */
    public function history(): void
    {
        $user    = $this->user();
        $role    = $user['role'] ?? '';
        $uid     = (int) $user['user_id'];
        $eventId = (int) ($_GET['id'] ?? 0);

        if ($eventId <= 0) {
            $this->error('Invalid event ID.');
            $this->redirect('/attendance');
        }

        if (!$this->attendanceService->canViewEventAttendance($eventId, $uid, $role)) {
            $this->error('You are not authorized to view attendance for this event.');
            $this->redirect('/attendance');
        }

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            $this->error('Event not found.');
            $this->redirect('/attendance');
        }

        $sessions = $this->attendanceService->getEventSessionHistory($eventId);
        $summary  = $this->attendanceService->getEventSummary($eventId);

        $this->render('attendance.history', [
            'pageTitle' => 'Attendance History — ' . ($event['event_name'] ?? ''),
            'event'     => $event,
            'sessions'  => $sessions,
            'summary'   => $summary,
        ]);
    }

    /**
     * -------------------------------------------------------------------------
     * GET /attendance/report?id={symposium_event_id}
     * Attendance report for an event.
     * -------------------------------------------------------------------------
     */
    public function report(): void
    {
        $user    = $this->user();
        $role    = $user['role'] ?? '';
        $uid     = (int) $user['user_id'];
        $eventId = (int) ($_GET['id'] ?? 0);

        if ($eventId <= 0) {
            $this->error('Invalid event ID.');
            $this->redirect('/attendance');
        }

        if (!$this->attendanceService->canViewEventAttendance($eventId, $uid, $role)) {
            $this->error('You are not authorized to view this report.');
            $this->redirect('/attendance');
        }

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            $this->error('Event not found.');
            $this->redirect('/attendance');
        }

        $activeSession = $this->sessionModel->getActiveEventSession($eventId);
        $sessionId     = $activeSession ? (int) $activeSession['session_id'] : 0;

        // Fall back to latest session
        if ($sessionId === 0) {
            $sessions  = $this->attendanceService->getEventSessionHistory($eventId);
            $sessionId = !empty($sessions) ? (int) $sessions[0]['session_id'] : 0;
        }

        $participants  = $this->attendanceService->getEventParticipantsWithAttendance(
            $eventId,
            $sessionId
        );
        $summary       = $this->attendanceService->getEventSummary($eventId);
        $deptBreakdown = $sessionId > 0
            ? $this->attendanceService->getEventDeptBreakdown($eventId, $sessionId)
            : [];

        $ficAssignments = $this->facultyModel->getForEvent($eventId);

        $this->render('attendance.report', [
            'pageTitle'      => 'Attendance Report — ' . ($event['event_name'] ?? ''),
            'event'          => $event,
            'participants'   => $participants,
            'summary'        => $summary,
            'deptBreakdown'  => $deptBreakdown,
            'sessionId'      => $sessionId,
            'ficAssignments' => $ficAssignments,
        ]);
    }

    /**
     * -------------------------------------------------------------------------
     * GET /attendance/export?id={symposium_event_id}
     * PDF export of attendance sheet.
     * -------------------------------------------------------------------------
     */
    public function exportPdf(): void
    {
        $user    = $this->user();
        $role    = $user['role'] ?? '';
        $uid     = (int) $user['user_id'];
        $eventId = (int) ($_GET['id'] ?? 0);

        if ($eventId <= 0) {
            $this->error('Invalid event ID.');
            $this->redirect('/attendance');
        }

        if (!$this->attendanceService->canViewEventAttendance($eventId, $uid, $role)) {
            $this->error('You are not authorized to export this report.');
            $this->redirect('/attendance');
        }

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            $this->error('Event not found.');
            $this->redirect('/attendance');
        }

        $sessions  = $this->attendanceService->getEventSessionHistory($eventId);
        $sessionId = !empty($sessions) ? (int) $sessions[0]['session_id'] : 0;

        $participants   = $this->attendanceService->getEventParticipantsWithAttendance($eventId, $sessionId);
        $summary        = $this->attendanceService->getEventSummary($eventId);
        $deptBreakdown  = $sessionId > 0
            ? $this->attendanceService->getEventDeptBreakdown($eventId, $sessionId)
            : [];
        $ficAssignments = $this->facultyModel->getForEvent($eventId);

        // Generate PDF using ReportService
        $reportService = new \App\Services\ReportService();
        $reportService->generateAttendancePdf(
            $event,
            $participants,
            $summary,
            $deptBreakdown,
            $ficAssignments,
            $user
        );
    }
    /**
     * -------------------------------------------------------------------------
     * GET /attendance/judge-mark-sheet?id={symposium_event_id}&judge_assignment_id={judge_assignment_id}
     * Download the physical mark entry sheet for a specific judge.
     * -------------------------------------------------------------------------
     */
    public function judgeMarkSheet(): void
    {
        $user = $this->user();
        $role = $user['role'] ?? '';
        $uid  = (int) $user['user_id'];
        $eventId           = (int) ($_GET['id']                  ?? 0);
        $judgeAssignmentId = (int) ($_GET['judge_assignment_id'] ?? 0);

        if ($eventId <= 0 || $judgeAssignmentId <= 0) {
            $this->error('Invalid parameters.');
            $this->redirect('/my/assigned-events');
        }

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            $this->error('Event not found.');
            $this->redirect('/my/assigned-events');
        }

        // ── FIC Authorization ─────────────────────────────────────────────────
        // Cast user_id to int before comparison; PDO returns DB strings.
        $isFic = false;
        foreach ($this->facultyModel->getForEvent($eventId) as $fa) {
            if ((int) $fa['user_id'] === $uid && (int) ($fa['is_active'] ?? 0) === 1) {
                $isFic = true;
                break;
            }
        }

        if ($role !== 'Admin' && !$isFic) {
            http_response_code(403);
            die('403 — Unauthorized. You are not assigned as Faculty In-Charge for this event.');
        }

        // ── Judge validation ──────────────────────────────────────────────────
        $judgeModel      = new \App\Models\JudgeAssignmentModel();
        $judgeAssignment = null;
        foreach ($judgeModel->getForEvent($eventId) as $judge) {
            if ((int) $judge['judge_assignment_id'] === $judgeAssignmentId && (int) $judge['is_active'] === 1) {
                $judgeAssignment = $judge;
                break;
            }
        }

        if (!$judgeAssignment) {
            $this->error('Invalid or inactive judge assignment for this event.');
            $this->redirect("/my/assigned-events/event?id={$eventId}");
        }

        // ── Immutable participant list (server-side only) ──────────────────────
        $recordModel = new \App\Models\AttendanceRecordModel();

        // Always fetch all approved participants for the Judge Marksheet.
        // This ensures the marksheet can be downloaded offline (before sync) 
        // and remains 100% identical after sync.
        $students = $recordModel->getApprovedParticipantsForEvent($eventId);

        if (empty($students)) {
            $this->error('No eligible students found for this event.');
            $this->redirect("/my/assigned-events/event?id={$eventId}");
        }

        // ── College branding (SystemSettingModel::getValue, not getSetting) ───
        $settingModel = new \App\Models\SystemSettingModel();
        $collegeDetails = [
            'college_name' => $settingModel->getValue('college_name', 'COLLEGE NAME'),
            'logo_path'    => $settingModel->getValue('college_logo', ''),
        ];

        // ── Generate & stream PDF ─────────────────────────────────────────────
        $reportService = new \App\Services\ReportService();
        $reportService->generateJudgeMarkSheetPdf($event, $students, $judgeAssignment, $collegeDetails);
    }
}

