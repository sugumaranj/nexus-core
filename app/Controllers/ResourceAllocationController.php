<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS
 * =========================================================================
 * File        : ResourceAllocationController.php
 * Location    : app/Controllers/
 * Description : HTTP handler for the Resource Allocation Module.
 *
 * Workflow Position
 * ─────────────────────────────────────────────────────────────────────────
 * Scheduling Complete → Faculty Allocation → Judge Allocation → Attendance
 *
 * Routes handled
 * ─────────────────────────────────────────────────────────────────────────
 *   GET  /symposiums/allocation                         → dashboard()
 *   GET  /symposiums/allocation/event                   → eventAllocation()
 *   GET  /symposiums/allocation/availability            → staffAvailability()
 *   POST /symposiums/allocation/faculty/assign          → assignFaculty()
 *   POST /symposiums/allocation/faculty/remove          → removeFaculty()
 *   POST /symposiums/allocation/faculty/replace         → replaceFaculty()
 *   POST /symposiums/allocation/judge/assign            → assignJudge()
 *   POST /symposiums/allocation/judge/remove            → removeJudge()
 *   POST /symposiums/allocation/judge/replace           → replaceJudge()
 *   GET  /symposiums/allocation/reports/faculty         → facultyReport()
 *   GET  /symposiums/allocation/reports/judges          → judgeReport()
 *   GET  /symposiums/allocation/reports/combined        → combinedReport()
 *   GET  /symposiums/allocation/ajax/availability       → ajaxCheckAvailability()
 *   GET  /symposiums/allocation/ajax/search-staff       → ajaxSearchStaff()
 *
 * Access
 * ─────────────────────────────────────────────────────────────────────────
 * Manage : Staff Coordinator, Admin, HOD
 * View   : All authenticated staff
 *
 * =========================================================================
 */

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\FacultyAssignmentModel;
use App\Models\JudgeAssignmentModel;
use App\Models\SymposiumEventModel;
use App\Models\UserModel;
use App\Models\DepartmentModel;
use App\Models\VenueModel;
use App\Services\ResourceAllocationService;
use App\Services\SymposiumService;
use App\Services\VenueAssignmentService;

final class ResourceAllocationController extends BaseController
{
    private ResourceAllocationService $allocationService;
    private FacultyAssignmentModel    $facultyModel;
    private JudgeAssignmentModel      $judgeModel;
    private SymposiumEventModel       $eventModel;
    private UserModel                 $userModel;
    private DepartmentModel           $deptModel;
    private SymposiumService          $symposiumService;
    private VenueAssignmentService    $venueService;
    private VenueModel                $venueModel;

    public function __construct()
    {
        $this->allocationService = new ResourceAllocationService();
        $this->facultyModel      = new FacultyAssignmentModel();
        $this->judgeModel        = new JudgeAssignmentModel();
        $this->eventModel        = new SymposiumEventModel();
        $this->userModel         = new UserModel();
        $this->deptModel         = new DepartmentModel();
        $this->symposiumService  = new SymposiumService();
        $this->venueService      = new VenueAssignmentService();
        $this->venueModel        = new VenueModel();
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    /**
     * Resource Allocation Dashboard for a symposium.
     *
     * Shows: stats cards (Events / Faculty Assigned / Judges Assigned /
     * Pending Allocation / Conflicts), today's events, all events list.
     */
    public function dashboard(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin', 'Principal');

        $user        = Session::get('user', []);
        $symposiumId = (int) ($_GET['symposium_id'] ?? 0);

        if ($symposiumId === 0) {
            $symposiums = $this->symposiumService->searchSymposiums('', '', '', '', '', '', $user);
            $this->render('allocation.select', [
                'pageTitle'  => 'Select Symposium — Staff Allocation',
                'symposiums' => $symposiums,
                'user'       => $user,
            ], 'dashboard');
            return;
        }

        $symposium = $this->symposiumService->getSymposiumById($symposiumId);
        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/symposiums');
        }

        $canManage   = $this->allocationService->canManage($user);
        $events      = $this->eventModel->getBySymposium($symposiumId);
        $stats       = $this->allocationService->getDashboardStats($symposiumId);
        $departments = $this->deptModel->getAll();

        // Enrich each event with its faculty and judges
        foreach ($events as &$event) {
            $eid = (int) $event['symposium_event_id'];
            $event['faculty'] = $this->facultyModel->getForEvent($eid);
            $event['judges']  = $this->judgeModel->getForEvent($eid);
        }
        unset($event);

        // Today's events (for the "Today" sidebar widget)
        $today       = date('Y-m-d');
        $todayEvents = array_filter($events, fn($e) => ($e['event_date'] ?? '') === $today);

        $this->render('allocation.dashboard', [
            'pageTitle'   => 'Resource Allocation — ' . htmlspecialchars($symposium['title']),
            'user'        => $user,
            'symposium'   => $symposium,
            'events'      => $events,
            'todayEvents' => array_values($todayEvents),
            'stats'       => $stats,
            'canManage'   => $canManage,
            'departments' => $departments,
            'success'     => Session::getFlash('success') ?? '',
            'error'       => Session::getFlash('error') ?? '',
        ], 'dashboard');
    }

    // =========================================================================
    // EVENT ALLOCATION (per-event assign screen)
    // =========================================================================

    /**
     * Per-event allocation screen.
     * Shows faculty and judges already assigned, staff availability grid.
     */
    public function eventAllocation(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin', 'Principal', 'Staff');

        $user             = Session::get('user', []);
        $symposiumEventId = (int) ($_GET['id'] ?? 0);

        if ($symposiumEventId === 0) {
            $this->redirect('/symposiums');
        }

        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event) {
            Session::flash('error', 'Event not found.');
            $this->redirect('/symposiums');
        }

        if (empty($event['event_date']) || empty($event['start_time'])) {
            Session::flash('error', 'Please schedule the event date and time before allocating staff.');
            $this->redirect('/symposiums/allocation?symposium_id=' . $event['symposium_id']);
        }

        $symposium   = $this->symposiumService->getSymposiumById((int) $event['symposium_id']);
        $canManage   = $this->allocationService->canManage($user);
        $departments = $this->deptModel->getAll();

        // Current assignments
        $currentFaculty = $this->facultyModel->getForEvent($symposiumEventId);
        $currentJudges  = $this->judgeModel->getForEvent($symposiumEventId);

        // Venue assignment
        $currentVenue  = null;
        $venueConflict = false;
        if (!empty($event['venue_id'])) {
            $currentVenue = $this->venueModel->findById((int)$event['venue_id']);
            // Check if the current venue now conflicts due to scheduling changes
            $venueValidation = $this->venueService->validateVenueAfterReschedule($symposiumEventId);
            $venueConflict   = $venueValidation['has_conflict'];
        }

        // Staff availability (with department and role filter from GET)
        $deptFilter = $_GET['dept'] ?? null;
        $roleFilter = $_GET['role'] ?? null;
        $staffList  = $this->allocationService->getStaffAvailability(
            $symposiumEventId, $deptFilter, $roleFilter
        );

        $this->render('allocation.event_allocation', [
            'pageTitle'       => 'Event Allocation — ' . htmlspecialchars($event['event_name']),
            'user'            => $user,
            'event'           => $event,
            'symposium'       => $symposium,
            'canManage'       => $canManage,
            'currentFaculty'  => $currentFaculty,
            'currentJudges'   => $currentJudges,
            'currentVenue'    => $currentVenue,
            'venueConflict'   => $venueConflict,
            'staffList'       => $staffList,
            'departments'     => $departments,
            'deptFilter'      => $deptFilter,
            'roleFilter'      => $roleFilter,
            'assignableRoles' => ['Admin', 'HOD', 'Staff Coordinator', 'Staff'],
            'success'         => Session::getFlash('success') ?? '',
            'error'           => Session::getFlash('error') ?? '',
        ], 'dashboard');
    }

    // =========================================================================
    // STAFF AVAILABILITY SCREEN
    // =========================================================================

    /**
     * Dedicated staff availability view for a symposium.
     * Shows all staff with Green/Orange/Red status for each event date.
     */
    public function staffAvailability(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

        $user        = Session::get('user', []);
        $symposiumId = (int) ($_GET['symposium_id'] ?? 0);

        if ($symposiumId === 0) {
            $this->redirect('/symposiums');
        }

        $symposium = $this->symposiumService->getSymposiumById($symposiumId);
        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/symposiums');
        }

        $events      = $this->eventModel->getBySymposium($symposiumId);
        $departments = $this->deptModel->getAll();
        $deptFilter  = $_GET['dept'] ?? null;
        $roleFilter  = $_GET['role'] ?? null;

        // Build availability matrix: event → staff availability
        $availabilityMatrix = [];
        foreach ($events as $event) {
            $eid = (int) $event['symposium_event_id'];
            $availabilityMatrix[$eid] = $this->allocationService->getStaffAvailability(
                $eid, $deptFilter, $roleFilter
            );
        }

        $this->render('allocation.staff_availability', [
            'pageTitle'          => 'Staff Availability — ' . htmlspecialchars($symposium['title']),
            'user'               => $user,
            'symposium'          => $symposium,
            'events'             => $events,
            'availabilityMatrix' => $availabilityMatrix,
            'departments'        => $departments,
            'deptFilter'         => $deptFilter,
            'roleFilter'         => $roleFilter,
        ], 'dashboard');
    }

    // =========================================================================
    // FACULTY — ASSIGN / REMOVE / REPLACE
    // =========================================================================

    /** POST: Assign Faculty In-Charge */
    public function assignFaculty(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

        $user             = Session::get('user', []);
        $symposiumEventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $userId           = (int) ($_POST['user_id'] ?? 0);
        $notes            = trim($_POST['notes'] ?? '');
        $redirect         = $this->resolveRedirect($symposiumEventId);

        $result = $this->allocationService->assignFaculty($symposiumEventId, $userId, $user, $notes);

        $result['success']
            ? Session::flash('success', $result['message'])
            : Session::flash('error', $result['message']);

        $this->redirect($redirect);
    }

    /** POST: Remove Faculty In-Charge */
    public function removeFaculty(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

        $user             = Session::get('user', []);
        $symposiumEventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $userId           = (int) ($_POST['user_id'] ?? 0);
        $redirect         = $this->resolveRedirect($symposiumEventId);

        $result = $this->allocationService->removeFaculty($symposiumEventId, $userId, $user);

        $result['success']
            ? Session::flash('success', $result['message'])
            : Session::flash('error', $result['message']);

        $this->redirect($redirect);
    }

    /** POST: Replace Faculty In-Charge (one-click replace) */
    public function replaceFaculty(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

        $user             = Session::get('user', []);
        $symposiumEventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $newUserId        = (int) ($_POST['user_id'] ?? 0);
        $notes            = trim($_POST['notes'] ?? '');
        $redirect         = $this->resolveRedirect($symposiumEventId);

        $result = $this->allocationService->replaceFaculty($symposiumEventId, $newUserId, $user, $notes);

        $result['success']
            ? Session::flash('success', $result['message'])
            : Session::flash('error', $result['message']);

        $this->redirect($redirect);
    }

    // =========================================================================
    // JUDGE — ASSIGN / REMOVE / REPLACE
    // =========================================================================

    /** POST: Assign Judge */
    public function assignJudge(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

        $user                = Session::get('user', []);
        $symposiumEventId    = (int) ($_POST['symposium_event_id'] ?? 0);
        $userId              = (int) ($_POST['user_id'] ?? 0);
        $redirect            = $this->resolveRedirect($symposiumEventId);

        $result = $this->allocationService->assignJudge($symposiumEventId, $userId, $user);

        $result['success']
            ? Session::flash('success', $result['message'])
            : Session::flash('error', $result['message']);

        $this->redirect($redirect);
    }

    /** POST: Remove Judge */
    public function removeJudge(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

        $user             = Session::get('user', []);
        $symposiumEventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $userId           = (int) ($_POST['user_id'] ?? 0);
        $redirect         = $this->resolveRedirect($symposiumEventId);

        $result = $this->allocationService->removeJudge($symposiumEventId, $userId, $user);

        $result['success']
            ? Session::flash('success', $result['message'])
            : Session::flash('error', $result['message']);

        $this->redirect($redirect);
    }

    /** POST: Replace Judge (one-click replace) */
    public function replaceJudge(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

        $user               = Session::get('user', []);
        $symposiumEventId   = (int) ($_POST['symposium_event_id'] ?? 0);
        $newUserId          = (int) ($_POST['user_id'] ?? 0);
        $redirect           = $this->resolveRedirect($symposiumEventId);

        $result = $this->allocationService->replaceJudge($symposiumEventId, $newUserId, $user);

        $result['success']
            ? Session::flash('success', $result['message'])
            : Session::flash('error', $result['message']);

        $this->redirect($redirect);
    }

    // =========================================================================
    // BULK ASSIGN (multi-staff in one go)
    // =========================================================================

    /**
     * POST /symposiums/allocation/faculty/bulk-assign
     *
     * Accepts: symposium_event_id, user_ids[] (array), notes
     * Returns JSON: { assigned: int, skipped: int, errors: [], messages: [] }
     */
    public function bulkAssignFaculty(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');
        header('Content-Type: application/json');

        $user             = Session::get('user', []);
        $symposiumEventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $userIds          = array_map('intval', (array) ($_POST['user_ids'] ?? []));
        $notes            = trim($_POST['notes'] ?? '');

        if ($symposiumEventId === 0 || empty($userIds)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            return;
        }

        $assigned = 0;
        $skipped  = 0;
        $messages = [];
        $errors   = [];

        foreach ($userIds as $uid) {
            if ($uid <= 0) { continue; }
            $result = $this->allocationService->assignFaculty($symposiumEventId, $uid, $user, $notes);
            if ($result['success']) {
                $assigned++;
                $messages[] = $result['message'];
            } else {
                $skipped++;
                $errors[] = $result['message'];
            }
        }

        echo json_encode([
            'success'  => $assigned > 0,
            'assigned' => $assigned,
            'skipped'  => $skipped,
            'messages' => $messages,
            'errors'   => $errors,
            'summary'  => $assigned > 0
                ? "{$assigned} staff assigned as Faculty In-Charge." . ($skipped > 0 ? " {$skipped} skipped." : '')
                : implode(' ', $errors),
        ]);
    }

    /**
     * POST /symposiums/allocation/judge/bulk-assign
     *
     * Accepts: symposium_event_id, user_ids[] (array), notes
     * Returns JSON: { assigned: int, skipped: int, errors: [], messages: [] }
     */
    public function bulkAssignJudge(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');
        header('Content-Type: application/json');

        $user             = Session::get('user', []);
        $symposiumEventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $userIds          = array_map('intval', (array) ($_POST['user_ids'] ?? []));
        $notes            = trim($_POST['notes'] ?? '');

        if ($symposiumEventId === 0 || empty($userIds)) {
            echo json_encode(['success' => false, 'message' => 'Invalid request.']);
            return;
        }

        $assigned = 0;
        $skipped  = 0;
        $messages = [];
        $errors   = [];

        foreach ($userIds as $uid) {
            if ($uid <= 0) { continue; }
            $result = $this->allocationService->assignJudge($symposiumEventId, $uid, $user);
            if ($result['success']) {
                $assigned++;
                $messages[] = $result['message'];
            } else {
                $skipped++;
                $errors[] = $result['message'];
            }
        }

        echo json_encode([
            'success'  => $assigned > 0,
            'assigned' => $assigned,
            'skipped'  => $skipped,
            'messages' => $messages,
            'errors'   => $errors,
            'summary'  => $assigned > 0
                ? "{$assigned} staff assigned as Judge." . ($skipped > 0 ? " {$skipped} skipped." : '')
                : implode(' ', $errors),
        ]);
    }

    // =========================================================================
    // REPORTS
    // =========================================================================

    /** GET: Faculty Assignment Report (A4 print) */
    public function facultyReport(): void
    {
        AuthMiddleware::handle();

        $symposiumId = (int) ($_GET['symposium_id'] ?? 0);
        $symposium   = $this->symposiumService->getSymposiumById($symposiumId);

        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/symposiums');
        }

        $assignments = $this->facultyModel->getBySymposium($symposiumId);
        $user        = Session::get('user', []);

        $this->render('allocation.reports.faculty_report', [
            'pageTitle'   => 'Faculty Assignment Report — ' . htmlspecialchars($symposium['title']),
            'symposium'   => $symposium,
            'assignments' => $assignments,
            'generatedBy' => $user['full_name'] ?? 'System',
            'generatedAt' => \App\Helpers\DateHelper::dateTime('now'),
        ], 'print');
    }

    /** GET: Judge Assignment Report (A4 print) */
    public function judgeReport(): void
    {
        AuthMiddleware::handle();

        $symposiumId = (int) ($_GET['symposium_id'] ?? 0);
        $symposium   = $this->symposiumService->getSymposiumById($symposiumId);

        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/symposiums');
        }

        $assignments = $this->judgeModel->getBySymposium($symposiumId);
        $user        = Session::get('user', []);

        $this->render('allocation.reports.judge_report', [
            'pageTitle'   => 'Judge Assignment Report — ' . htmlspecialchars($symposium['title']),
            'symposium'   => $symposium,
            'assignments' => $assignments,
            'generatedBy' => $user['full_name'] ?? 'System',
            'generatedAt' => \App\Helpers\DateHelper::dateTime('now'),
        ], 'print');
    }

    /** GET: Combined Resource Allocation Report (A4 print) */
    public function combinedReport(): void
    {
        AuthMiddleware::handle();

        $symposiumId      = (int) ($_GET['symposium_id'] ?? 0);
        $symposium        = $this->symposiumService->getSymposiumById($symposiumId);

        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/symposiums');
        }

        $events = $this->eventModel->getBySymposium($symposiumId);
        foreach ($events as &$event) {
            $eid = (int) $event['symposium_event_id'];
            $event['faculty'] = $this->facultyModel->getForEvent($eid);
            $event['judges']  = $this->judgeModel->getForEvent($eid);
        }
        unset($event);

        $user = Session::get('user', []);

        $this->render('allocation.reports.combined_report', [
            'pageTitle'   => 'Combined Allocation Report — ' . htmlspecialchars($symposium['title']),
            'symposium'   => $symposium,
            'events'      => $events,
            'generatedBy' => $user['full_name'] ?? 'System',
            'generatedAt' => \App\Helpers\DateHelper::dateTime('now'),
        ], 'print');
    }

    // =========================================================================
    // AJAX ENDPOINTS
    // =========================================================================

    /**
     * AJAX: Check if a user is available for a specific event.
     *
     * GET params: user_id, symposium_event_id
     * Returns JSON: { available: bool, conflicts: [], user_name: string }
     */
    public function ajaxCheckAvailability(): void
    {
        AuthMiddleware::handle();

        header('Content-Type: application/json');

        $userId           = (int) ($_GET['user_id'] ?? 0);
        $symposiumEventId = (int) ($_GET['symposium_event_id'] ?? 0);

        if ($userId === 0 || $symposiumEventId === 0) {
            echo json_encode(['available' => true, 'conflicts' => []]);
            return;
        }

        $result = $this->allocationService->checkUserAvailability($userId, $symposiumEventId);
        echo json_encode($result);
    }

    /**
     * AJAX: Search staff for assignment.
     *
     * Returns available staff first, then busy, then inactive.
     * GET params: symposium_event_id, q (search), dept_id, role
     */
    public function ajaxSearchStaff(): void
    {
        AuthMiddleware::handle();

        header('Content-Type: application/json');

        $symposiumEventId = (int) ($_GET['symposium_event_id'] ?? 0);
        $search           = trim($_GET['q'] ?? '');
        $deptId           = $_GET['dept_id'] ?? null;
        $roleFilter       = $_GET['role']    ?? null;

        if ($symposiumEventId === 0) {
            echo json_encode([]);
            return;
        }

        $staff = $this->allocationService->getStaffAvailability($symposiumEventId, $deptId, $roleFilter);

        // Apply text search filter
        if ($search !== '') {
            $s     = strtolower($search);
            $staff = array_filter($staff, function (array $u) use ($s): bool {
                return str_contains(strtolower($u['full_name'] ?? ''), $s)
                    || str_contains(strtolower($u['employee_id'] ?? ''), $s)
                    || str_contains(strtolower($u['department_name'] ?? ''), $s);
            });
        }

        // Return simplified payload for dropdown
        $payload = array_values(array_map(function (array $u): array {
            return [
                'user_id'             => $u['user_id'],
                'full_name'           => $u['full_name'],
                'role'                => $u['role'],
                'department_name'     => $u['department_name'] ?? '',
                'availability_status' => $u['availability_status'],
                'availability_label'  => $u['availability_label'],
                'conflict_detail'     => $u['conflict_detail'],
                'is_faculty'          => $u['is_faculty'],
                'is_judge'            => $u['is_judge'],
            ];
        }, $staff));

        echo json_encode($payload);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    // =========================================================================
    // VENUE — ASSIGN / REMOVE
    // =========================================================================

    /**
     * POST /symposiums/allocation/venue/assign
     *
     * Assigns (or replaces) the venue for a symposium event.
     * Requires Staff Coordinator or Admin role.
     * Concurrency-safe via VenueAssignmentService (venue-row FOR UPDATE).
     */
    public function assignVenue(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

        $user             = Session::get('user', []);
        $symposiumEventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $venueId          = (int) ($_POST['venue_id'] ?? 0);
        $redirect         = $this->resolveRedirect($symposiumEventId);

        if ($venueId <= 0) {
            Session::flash('error', 'Please select a venue.');
            $this->redirect($redirect);
        }

        $result = $this->venueService->assignVenue($symposiumEventId, $venueId, $user);

        $result['success']
            ? Session::flash('success', $result['message'])
            : Session::flash('error', $result['message']);

        $this->redirect($redirect);
    }

    /**
     * POST /symposiums/allocation/venue/remove
     *
     * Removes the venue assignment from a symposium event.
     */
    public function removeVenue(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

        $user             = Session::get('user', []);
        $symposiumEventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $redirect         = $this->resolveRedirect($symposiumEventId);

        $result = $this->venueService->removeVenue($symposiumEventId, $user);

        $result['success']
            ? Session::flash('success', $result['message'])
            : Session::flash('error', $result['message']);

        $this->redirect($redirect);
    }

    // =========================================================================
    // AJAX — VENUE AVAILABILITY
    // =========================================================================

    /**
     * GET /symposiums/allocation/ajax/venue-availability
     *
     * Returns JSON list of all active venues with availability_status for
     * the given event's time slot.
     *
     * Params: symposium_event_id
     */
    public function ajaxVenueAvailability(): void
    {
        AuthMiddleware::handle();
        header('Content-Type: application/json');

        $symposiumEventId = (int) ($_GET['symposium_event_id'] ?? 0);

        if ($symposiumEventId <= 0) {
            echo json_encode([]);
            return;
        }

        $venues = $this->venueService->getVenueAvailability($symposiumEventId);

        // Enrich with current assignment flag
        $event = $this->eventModel->findById($symposiumEventId);
        $currentVenueId = $event ? (int)($event['venue_id'] ?? 0) : 0;

        foreach ($venues as &$v) {
            $v['is_assigned'] = ((int)$v['venue_id'] === $currentVenueId && $currentVenueId > 0);
        }
        unset($v);

        echo json_encode(array_values($venues));
    }

    // =========================================================================
    // VENUE ALLOCATION REPORT
    // =========================================================================

    /**
     * GET /symposiums/allocation/reports/venue
     *
     * Formal A4 print-ready Venue Allocation Report.
     * Access: Admin, Staff Coordinator, HOD, Principal only.
     */
    public function venueReport(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator', 'HOD', 'Principal');

        $symposiumId = (int) ($_GET['symposium_id'] ?? 0);
        $symposium   = $this->symposiumService->getSymposiumById($symposiumId);

        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/symposiums');
        }

        $events = $this->venueService->getVenueAllocationReport($symposiumId);
        $user   = Session::get('user', []);

        $this->render('allocation.reports.venue_report', [
            'pageTitle'   => 'Venue Allocation Report — ' . htmlspecialchars($symposium['title']),
            'symposium'   => $symposium,
            'events'      => $events,
            'generatedBy' => $user['full_name'] ?? 'System',
            'generatedAt' => \App\Helpers\DateHelper::dateTime('now'),
        ], 'print');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Build the redirect URL after a POST action.
     * Always returns to the event allocation page.
     *
     * @param int $symposiumEventId
     * @return string
     */
    private function resolveRedirect(int $symposiumEventId): string
    {
        if ($symposiumEventId === 0) {
            return '/symposiums';
        }

        return '/symposiums/allocation/event?id=' . $symposiumEventId;
    }
}
