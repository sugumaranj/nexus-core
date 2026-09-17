<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : FacultyInChargeController.php
 * Location    : app/Controllers/
 * Description : Faculty In-Charge (FIC) portal controller.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Dashboard: list all events this user is FIC for
 * • Event Detail: view single event and manage judges (FIC-only, guarded)
 * • AJAX: assign judge to own event
 * • AJAX: remove judge from own event
 *
 * Authorization
 * -------------------------------------------------------------------------
 * Role check: Admin, Principal, HOD, Staff Coordinator, Staff
 * Event-level check: must be active FIC for the specific event (enforced
 * server-side on all event-specific routes). Admin bypasses event check.
 *
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Services\FacultyInChargeService;
use App\Services\ResourceAllocationService;
use App\Models\ApplicationModel;

final class FacultyInChargeController extends BaseController
{
    private FacultyInChargeService    $ficService;
    private ResourceAllocationService $allocationService;

    public function __construct()
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole(
            'Admin', 'Principal', 'HOD', 'Staff Coordinator', 'Staff'
        );

        $this->ficService        = new FacultyInChargeService();
        $this->allocationService = new ResourceAllocationService();
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    /**
     * GET /my/assigned-events
     * List all events where the logged-in user is an active FIC.
     */
    public function dashboard(): void
    {
        $user   = $this->user();
        $userId = (int) ($user['user_id'] ?? 0);

        $assignedEvents = $this->ficService->getAssignedEvents($userId);
        $stats          = $this->ficService->getFicDashboardStats($userId);

        $this->render('fic.dashboard', [
            'pageTitle'      => 'My Assigned Events',
            'user'           => $user,
            'assignedEvents' => $assignedEvents,
            'stats'          => $stats,
        ]);
    }

    // =========================================================================
    // EVENT DETAIL
    // =========================================================================

    /**
     * GET /my/assigned-events/event?id={symposium_event_id}
     * View and manage a single assigned event (judge management).
     *
     * Authorization: must be active FIC OR Admin.
     */
    public function eventDetail(): void
    {
        $eventId = (int) ($_GET['id'] ?? 0);
        $user    = $this->user();
        $userId  = (int) ($user['user_id'] ?? 0);
        $role    = $user['role'] ?? '';

        if ($eventId === 0) {
            $this->error('Invalid event ID.');
            $this->redirect('/my/assigned-events');
        }

        $detail = $this->ficService->getEventDetail($eventId, $userId);

        if ($detail === null) {
            $this->error('Event not found.');
            $this->redirect('/my/assigned-events');
        }

        // Enforce FIC authorization for non-admin roles
        if ($role !== 'Admin' && !$detail['is_fic']) {
            http_response_code(403);
            $errorView = dirname(__DIR__, 2) . '/templates/errors/403.php';
            if (file_exists($errorView)) {
                require $errorView;
            } else {
                echo '<h1>403 — Access Denied</h1><p>You are not assigned as Faculty In-Charge for this event.</p>';
            }
            exit;
        }

        // Evaluate attendance state for Judge Mark Sheets
        $sessionModel = new \App\Models\AttendanceSessionModel();
        $activeSession = $sessionModel->getActiveEventSession($eventId);
        $finalizedSession = null;
        $attendanceLocked = false;
        
        if (!$activeSession) {
            $finalizedSession = $sessionModel->getFinalizedEventSession($eventId);
            if ($finalizedSession) {
                $attendanceLocked = true;
            }
        }

        // Add Feedback Variables
        $feedbackService = new \App\Services\FeedbackService();
        $feedbackFinalized = $feedbackService->isAttendanceFinalized($eventId);
        $feedbackSummary = [];
        $feedbackReviews = [];
        if ($feedbackFinalized) {
            $feedbackSummary = $feedbackService->getEventFeedbackSummary($eventId);
            $feedbackReviews = $feedbackService->getEventAnonymousReviews($eventId, 50, 0);
        }

        $this->render('fic.event_detail', [
            'pageTitle'        => 'Manage Event — ' . htmlspecialchars($detail['event']['event_name'] ?? '', ENT_QUOTES),
            'user'             => $user,
            'event'            => $detail['event'],
            'currentFic'       => $detail['current_fic'],
            'currentJudges'    => $detail['current_judges'],
            'staffList'        => $detail['assignable_staff'],
            'regCount'         => $detail['registration_count'],
            'stages'           => $detail['stages'] ?? [],
            'attendanceLocked' => $attendanceLocked,
            'finalizedSession' => $finalizedSession,
            'feedbackFinalized'=> $feedbackFinalized,
            'feedbackSummary'  => $feedbackSummary,
            'feedbackReviews'  => $feedbackReviews,
        ]);
    }

    // =========================================================================
    // EVENT PARTICIPANTS / REGISTRATIONS
    // =========================================================================

    /**
     * GET /my/assigned-events/registrations?id={symposium_event_id}&status=&search=&department_id=&academic_year=&application_type=
     * View the students registered for this event.
     *
     * Authorization: must be active FIC OR Admin.
     */
    public function participants(): void
    {
        $eventId = (int) ($_GET['id'] ?? 0);
        $user    = $this->user();
        $userId  = (int) ($user['user_id'] ?? 0);
        $role    = $user['role'] ?? '';
        
        $filters = [
            'application_status' => $_GET['status'] ?? 'All',
            'department_id'      => $_GET['department_id'] ?? '',
            'academic_year'      => $_GET['academic_year'] ?? '',
            'application_type'   => $_GET['application_type'] ?? 'All'
        ];
        $search  = $_GET['search'] ?? '';

        if ($eventId === 0) {
            $this->error('Invalid event ID.');
            $this->redirect('/my/assigned-events');
        }

        $detail = $this->ficService->getEventDetail($eventId, $userId);

        if ($detail === null) {
            $this->error('Event not found.');
            $this->redirect('/my/assigned-events');
        }

        // Enforce FIC authorization for non-admin roles
        if ($role !== 'Admin' && !$detail['is_fic']) {
            http_response_code(403);
            $errorView = dirname(__DIR__, 2) . '/templates/errors/403.php';
            if (file_exists($errorView)) {
                require $errorView;
            } else {
                echo '<h1>403 — Access Denied</h1><p>You are not assigned as Faculty In-Charge for this event.</p>';
            }
            exit;
        }

        $appModel = new ApplicationModel();
        $applications = $appModel->getForEventReport($eventId, $filters);
        
        if ($search !== '') {
            $searchLower = strtolower($search);
            $applications = array_filter($applications, function($app) use ($searchLower) {
                return str_contains(strtolower($app['student_name']     ?? ''), $searchLower)
                    || str_contains(strtolower($app['register_number']  ?? ''), $searchLower)
                    || str_contains(strtolower($app['application_no']   ?? ''), $searchLower);
            });
        }
        
        $stats = $appModel->getStatsForEvent($eventId);
        
        $deptModel = new \App\Models\DepartmentModel();
        $departments = array_filter($deptModel->getAll(), fn($d) => $d['is_active']);
        $academicYears = $appModel->getDistinctAcademicYearsForEvent($eventId);

        $this->render('fic.event_participants', [
            'pageTitle'     => 'Registrations — ' . htmlspecialchars($detail['event']['event_name'] ?? '', ENT_QUOTES),
            'user'          => $user,
            'event'         => $detail['event'],
            'applications'  => $applications,
            'stats'         => $stats,
            'filters'       => $filters,
            'search'        => $search,
            'departments'   => $departments,
            'academicYears' => $academicYears
        ]);
    }

    /**
     * GET /my/assigned-events/registrations/export?id={symposium_event_id}&status=&department_id=&academic_year=&application_type=
     * Generate PDF of the registered students.
     */
    public function exportRegistrationPdf(): void
    {
        $eventId = (int) ($_GET['id'] ?? 0);
        $user    = $this->user();
        $userId  = (int) ($user['user_id'] ?? 0);
        $role    = $user['role'] ?? '';
        
        $filters = [
            'application_status' => $_GET['status'] ?? 'All',
            'department_id'      => $_GET['department_id'] ?? '',
            'academic_year'      => $_GET['academic_year'] ?? '',
            'application_type'   => $_GET['application_type'] ?? 'All'
        ];

        if ($eventId === 0) {
            $this->error('Invalid event ID.');
            $this->redirect('/my/assigned-events');
        }

        $detail = $this->ficService->getEventDetail($eventId, $userId);

        if ($detail === null) {
            $this->error('Event not found.');
            $this->redirect('/my/assigned-events');
        }

        // Enforce FIC authorization for non-admin roles
        if ($role !== 'Admin' && !$detail['is_fic']) {
            http_response_code(403);
            echo '<h1>403 — Access Denied</h1><p>You are not assigned as Faculty In-Charge for this event.</p>';
            exit;
        }

        $appModel = new ApplicationModel();
        $applications = $appModel->getForEventReport($eventId, $filters);
        $stats = $appModel->getStatsForEvent($eventId);
        
        if (!empty($filters['department_id'])) {
            $deptModel = new \App\Models\DepartmentModel();
            $dept = $deptModel->findById((int)$filters['department_id']);
            $filters['department_name'] = $dept['department_name'] ?? 'Unknown';
        }

        $sympModel = new \App\Models\SymposiumModel();
        $symposium = $sympModel->findById((int)$detail['event']['symposium_id']);

        $reportService = new \App\Services\ReportService();
        $reportService->generateRegistrationReportPdf($detail['event'], $symposium, $filters, $applications, $stats);
    }

    // =========================================================================
    // AJAX — ASSIGN JUDGE
    // =========================================================================

    /**
     * POST /my/assigned-events/assign-judge
     * FIC assigns a judge to their event.
     *
     * POST params: symposium_event_id, user_id
     * Returns JSON: {success, message}
     */
    public function ajaxAssignJudge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $eventId  = (int) ($_POST['symposium_event_id'] ?? 0);
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $user     = $this->user();
        $userId   = (int) ($user['user_id'] ?? 0);
        $role     = $user['role'] ?? '';

        if ($eventId === 0 || $targetId === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            exit;
        }

        // Auth: must be FIC or Admin
        if ($role !== 'Admin' && !$this->ficService->isFic($eventId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied. You are not the FIC for this event.']);
            exit;
        }

        try {
            $result = $this->allocationService->assignJudge($eventId, $targetId, $user, true);
            echo json_encode($result);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
        }
        exit;
    }

    // =========================================================================
    // AJAX — REMOVE JUDGE
    // =========================================================================

    /**
     * POST /my/assigned-events/remove-judge
     * FIC removes a judge from their event.
     *
     * POST params: symposium_event_id, user_id
     * Returns JSON: {success, message}
     */
    public function ajaxRemoveJudge(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        $eventId  = (int) ($_POST['symposium_event_id'] ?? 0);
        $targetId = (int) ($_POST['user_id'] ?? 0);
        $user     = $this->user();
        $userId   = (int) ($user['user_id'] ?? 0);
        $role     = $user['role'] ?? '';

        if ($eventId === 0 || $targetId === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            exit;
        }

        // Auth: must be FIC or Admin
        if ($role !== 'Admin' && !$this->ficService->isFic($eventId, $userId)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied. You are not the FIC for this event.']);
            exit;
        }

        try {
            $result = $this->allocationService->removeJudge($eventId, $targetId, $user, true);
            echo json_encode($result);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Exception: ' . $e->getMessage()]);
        }
        exit;
    }

    // =========================================================================
    // PRELIMS & STAGES
    // =========================================================================

    /**
     * POST /my/assigned-events/prelims
     * Save prelims configuration (requires_prelims flag and competition stages).
     */
    public function savePrelims(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        
        $eventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $prelimDecision = $_POST['prelim_decision'] ?? 'Pending';
        $stagesData = $_POST['stages'] ?? []; 
        
        if (is_string($stagesData)) {
            $stagesData = json_decode($stagesData, true) ?: [];
        }

        $user   = $this->user();
        $userId = (int) ($user['user_id'] ?? 0);
        $role   = $user['role'] ?? '';

        if ($eventId === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid event ID.']);
            exit;
        }

        $detail = $this->ficService->getEventDetail($eventId, $userId);

        if ($detail === null) {
            echo json_encode(['success' => false, 'message' => 'Event not found.']);
            exit;
        }

        if ($role !== 'Admin' && !$detail['is_fic']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You are not assigned as Faculty In-Charge for this event.']);
            exit;
        }
        // Authorization: Only Staff Coordinator, Admin (and optionally HOD) can change prelim_decision.
        // FICs who lack these roles can only modify stages.
        if (!in_array($role, ['Admin', 'Staff Coordinator', 'HOD'])) {
            $prelimDecision = $detail['event']['prelim_decision'] ?? 'Pending';
        }

        $result = $this->ficService->savePrelimsConfig($eventId, $prelimDecision, $stagesData);

        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => 'Prelims configuration saved successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to save prelims configuration.']);
        }
        exit;
    }
    
    /**
     * POST /my/assigned-events/publish-results
     * Trigger EvaluationService->publishResults()
     */
    public function publishResults(): void
    {
        $user   = $this->user();
        $userId = (int) ($user['user_id'] ?? 0);
        $eventId = (int) ($_GET['id'] ?? 0);

        header('Content-Type: application/json');

        // Check if user is FIC for this event
        $detail = $this->ficService->getEventDetail($eventId, $userId);
        if (!$detail) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized or event not found.']);
            return;
        }

        $evalService = new \App\Services\EvaluationService();
        $result = $evalService->publishResults($eventId, $userId);

        echo json_encode($result);
    }

    // =========================================================================
    // TIE RESOLVER - DEPRECATED
    // =========================================================================
    // Tie resolution is now automatic via standard competition ranking.
}
