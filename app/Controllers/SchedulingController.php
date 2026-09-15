<?php
declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : SchedulingController.php
 * Location    : app/Controllers/
 * Description : Event Date & Time Scheduling Module Controller.
 *
 * Workflow: Symposium Approved -> Schedule Events -> Scheduling Complete
 *            -> Open Registration
 *
 * Routes handled:
 *   GET  /symposiums/scheduling              - dashboard()
 *   GET  /symposiums/scheduling/event        - scheduleEvent()
 *   POST /symposiums/scheduling/event        - saveSchedule()
 *   GET  /symposiums/scheduling/reschedule   - rescheduleEvent()
 *   POST /symposiums/scheduling/reschedule   - saveReschedule()
 *   GET  /symposiums/scheduling/report       - report()
 *   POST /symposiums/scheduling/complete     - markComplete()
 *   POST /symposiums/scheduling/ajax/check   - ajaxCheckConflict()
 *
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\SymposiumEventModel;
use App\Services\SymposiumEventService;
use App\Services\SymposiumService;

final class SchedulingController extends BaseController
{
    private SymposiumEventService $eventService;
    private SymposiumService      $symposiumService;
    private SymposiumEventModel   $eventModel;

    public function __construct()
    {
        $this->eventService     = new SymposiumEventService();
        $this->symposiumService = new SymposiumService();
        $this->eventModel       = new SymposiumEventModel();
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    /**
     * Scheduling dashboard for a symposium.
     *
     * Shows: stats cards (Events/Scheduled/Pending/Progress),
     *        daily timeline view, and per-event scheduling actions.
     */
    public function dashboard(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin', 'Principal', 'HOD');

        $user       = Session::get('user', []);
        $symposiumId = (int) ($_GET['symposium_id'] ?? 0);

        if ($symposiumId === 0) {
            $this->redirect('/symposiums');
        }

        $symposium = $this->symposiumService->getSymposiumById($symposiumId);
        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/symposiums');
        }

        $schedulingAllowed = $this->symposiumService->isSchedulingAllowed($symposium);
        $schedulingLocked  = $this->symposiumService->isSchedulingLocked($symposium);
        $canManage         = $this->eventService->canManage($user, $symposium) && $schedulingAllowed;

        // Get all events with scheduling data
        $allEvents   = $this->eventModel->getBySymposium($symposiumId);
        $dashboard   = $this->eventModel->getSchedulingDashboard($symposiumId);
        $groupedDays = $this->eventModel->getGroupedByDay($symposiumId);

        $success = Session::getFlash('success') ?? '';
        $error   = Session::getFlash('error') ?? '';

        $this->render('scheduling.dashboard', [
            'pageTitle'         => 'Event Scheduling — ' . htmlspecialchars($symposium['title']),
            'user'              => $user,
            'symposium'         => $symposium,
            'events'            => $allEvents,
            'dashboard'         => $dashboard,
            'grouped_days'      => $groupedDays,
            'schedulingAllowed' => $schedulingAllowed,
            'schedulingLocked'  => $schedulingLocked,
            'canManage'         => $canManage,
            'success'           => $success,
            'error'             => $error,
        ], 'dashboard');
    }

    // =========================================================================
    // SCHEDULE EVENT (first-time)
    // =========================================================================

    /**
     * Show the schedule form for a single event.
     */
    public function scheduleEvent(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

        $user              = Session::get('user', []);
        $symposiumEventId  = (int) ($_GET['id'] ?? 0);

        if ($symposiumEventId === 0) {
            $this->redirect('/symposiums');
        }

        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event) {
            Session::flash('error', 'Event not found.');
            $this->redirect('/symposiums');
        }

        $symposium = $this->symposiumService->getSymposiumById((int) $event['symposium_id']);
        if (!$symposium || !$this->symposiumService->isSchedulingAllowed($symposium)) {
            Session::flash('error', 'Scheduling is not available for this symposium.');
            $this->redirect('/symposiums/scheduling?symposium_id=' . $event['symposium_id']);
        }

        $errors  = Session::getFlash('errors') ?? [];
        $oldData = Session::getFlash('old') ?? [];

        $this->render('scheduling.schedule_event', [
            'pageTitle' => 'Schedule Event — ' . htmlspecialchars($event['event_name']),
            'user'      => $user,
            'event'     => $event,
            'symposium' => $symposium,
            'errors'    => $errors,
            'old'       => $oldData,
            'sessions'  => ['FN' => 'Forenoon (FN)', 'AN' => 'Afternoon (AN)', 'Full Day' => 'Full Day'],
        ], 'dashboard');
    }

    /**
     * Save the schedule for an event (POST).
     */
    public function saveSchedule(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

        $user             = Session::get('user', []);
        $symposiumEventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $symposiumId      = (int) ($_POST['symposium_id'] ?? 0);

        if ($symposiumEventId === 0) {
            $this->redirect('/symposiums');
        }

        $data = [
            'event_date'     => trim($_POST['event_date'] ?? ''),
            'start_time'     => trim($_POST['start_time'] ?? ''),
            'end_time'       => trim($_POST['end_time'] ?? ''),
            'session'        => trim($_POST['session'] ?? ''),
            'reporting_time' => trim($_POST['reporting_time'] ?? ''),
        ];

        $result = $this->eventService->scheduleEvent($symposiumEventId, $data, $user);

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
            Session::flash('errors', $result['errors'] ?? []);
            Session::flash('old', $data);
            $this->redirect('/symposiums/scheduling/event?id=' . $symposiumEventId);
            return;
        }

        $this->redirect('/symposiums/scheduling?symposium_id=' . $symposiumId);
    }

    // =========================================================================
    // RESCHEDULE EVENT
    // =========================================================================

    /**
     * Show the reschedule form for an already-scheduled event.
     */
    public function rescheduleEvent(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

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

        $symposium = $this->symposiumService->getSymposiumById((int) $event['symposium_id']);
        if (!$symposium || !$this->symposiumService->isSchedulingAllowed($symposium)) {
            Session::flash('error', 'Rescheduling is not available for this symposium.');
            $this->redirect('/symposiums/scheduling?symposium_id=' . $event['symposium_id']);
        }

        $errors  = Session::getFlash('errors') ?? [];
        $oldData = Session::getFlash('old') ?? [];

        $this->render('scheduling.reschedule_event', [
            'pageTitle' => 'Reschedule — ' . htmlspecialchars($event['event_name']),
            'user'      => $user,
            'event'     => $event,
            'symposium' => $symposium,
            'errors'    => $errors,
            'old'       => $oldData,
            'sessions'  => ['FN' => 'Forenoon (FN)', 'AN' => 'Afternoon (AN)', 'Full Day' => 'Full Day'],
        ], 'dashboard');
    }

    /**
     * Save a reschedule (POST).
     */
    public function saveReschedule(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

        $user             = Session::get('user', []);
        $symposiumEventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $symposiumId      = (int) ($_POST['symposium_id'] ?? 0);

        if ($symposiumEventId === 0) {
            $this->redirect('/symposiums');
        }

        $data = [
            'event_date'        => trim($_POST['event_date'] ?? ''),
            'start_time'        => trim($_POST['start_time'] ?? ''),
            'end_time'          => trim($_POST['end_time'] ?? ''),
            'session'           => trim($_POST['session'] ?? ''),
            'reporting_time'    => trim($_POST['reporting_time'] ?? ''),
            'reschedule_reason' => trim($_POST['reschedule_reason'] ?? ''),
        ];

        $result = $this->eventService->rescheduleEvent($symposiumEventId, $data, $user);

        if ($result['success']) {
            // ── Check if the rescheduled event now conflicts with its existing venue ──
            $venueService     = new \App\Services\VenueAssignmentService();
            $venueValidation  = $venueService->validateVenueAfterReschedule($symposiumEventId);

            if ($venueValidation['has_conflict']) {
                // Show success for the reschedule, but warn about the venue conflict
                Session::flash('success', $result['message']);
                Session::flash('warning_venue_conflict',
                    "⚠️ Venue conflict detected after rescheduling: \"{$venueValidation['venue_name']}\" "
                    . "is now also assigned to \"{$venueValidation['conflict_event']}\" at the same time. "
                    . "Please reassign the venue for this event."
                );
            } else {
                Session::flash('success', $result['message']);
            }
        } else {
            Session::flash('error', $result['message']);
            Session::flash('errors', $result['errors'] ?? []);
            Session::flash('old', $data);
            $this->redirect('/symposiums/scheduling/reschedule?id=' . $symposiumEventId);
            return;
        }

        $this->redirect('/symposiums/scheduling?symposium_id=' . $symposiumId);
    }

    // =========================================================================
    // MARK SCHEDULING COMPLETE
    // =========================================================================

    /**
     * Mark a symposium's scheduling as complete (POST).
     * Transitions: Approved -> Scheduling Complete.
     */
    public function markComplete(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator', 'Admin');

        $user        = Session::get('user', []);
        $symposiumId = (int) ($_POST['symposium_id'] ?? 0);

        if ($symposiumId === 0) {
            $this->redirect('/symposiums');
        }

        $result = $this->symposiumService->markSchedulingComplete($symposiumId, $user);

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        $this->redirect('/symposiums/scheduling?symposium_id=' . $symposiumId);
    }

    // =========================================================================
    // SCHEDULE REPORT
    // =========================================================================

    /**
     * Printable schedule report grouped by Day > Morning / Afternoon.
     */
    public function report(): void
    {
        AuthMiddleware::handle();

        $symposiumId = (int) ($_GET['symposium_id'] ?? 0);
        if ($symposiumId === 0) {
            $this->redirect('/symposiums');
        }

        $symposium   = $this->symposiumService->getSymposiumById($symposiumId);
        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/symposiums');
        }

        $groupedDays = $this->eventModel->getGroupedByDay($symposiumId);
        $dashboard   = $this->eventModel->getSchedulingDashboard($symposiumId);

        $this->render('scheduling.report', [
            'pageTitle'   => 'Schedule Report — ' . htmlspecialchars($symposium['title']),
            'symposium'   => $symposium,
            'grouped_days' => $groupedDays,
            'dashboard'   => $dashboard,
        ], 'print');
    }

    // =========================================================================
    // AJAX CONFLICT CHECK
    // =========================================================================

    /**
     * AJAX endpoint to check for time conflicts in real-time.
     *
     * POST body (JSON or form): symposium_id, event_date, start_time, end_time, exclude_id
     * Returns JSON: { available: bool, conflicts: [] }
     */
    public function ajaxCheckConflict(): void
    {
        AuthMiddleware::handle();

        // Release the session file lock immediately.
        // PHP's default file-based session handler holds an exclusive lock on the
        // session file from session_start() until the request ends (or session_write_close()
        // is called). When the browser fires this AJAX request while the parent page is
        // still being rendered in another tab/request with the SAME session, the AJAX
        // session_start() blocks until the parent releases the lock — causing the
        // "checking..." spinner to hang forever.
        session_write_close();

        header('Content-Type: application/json');

        $input = $_POST;

        // Support JSON body too
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }

        $symposiumId = (int) ($input['symposium_id'] ?? 0);
        $eventDate   = trim($input['event_date'] ?? '');
        $startTime   = trim($input['start_time'] ?? '');
        $endTime     = trim($input['end_time'] ?? '');
        $excludeId   = !empty($input['exclude_id']) ? (int) $input['exclude_id'] : null;

        if ($symposiumId === 0 || empty($eventDate) || empty($startTime) || empty($endTime)) {
            echo json_encode(['available' => true, 'conflicts' => []]);
            return;
        }

        // Also validate date boundary
        $symposium = $this->symposiumService->getSymposiumById($symposiumId);
        $dateErrors = [];
        if ($symposium) {
            if (!empty($symposium['event_start_date']) && $eventDate < $symposium['event_start_date']) {
                $dateErrors[] = ['type' => 'date_boundary', 'message' => 'Date is before symposium start (' . \App\Helpers\DateHelper::date($symposium['event_start_date']) . ')'];
            } elseif (!empty($symposium['event_end_date']) && $eventDate > $symposium['event_end_date']) {
                $dateErrors[] = ['type' => 'date_boundary', 'message' => 'Date is after symposium end (' . \App\Helpers\DateHelper::date($symposium['event_end_date']) . ')'];
            }
        }

        $result = $this->eventService->checkSchedulingConflicts($symposiumId, $eventDate, $startTime, $endTime, $excludeId);
        $result['conflicts'] = array_merge($dateErrors, $result['conflicts']);
        $result['available'] = empty($result['conflicts']);

        echo json_encode($result);
    }
}
