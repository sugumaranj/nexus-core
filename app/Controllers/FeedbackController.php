<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : FeedbackController.php
 * Location    : app/Controllers/
 * Description : Staff-facing identified event feedback viewer.
 *
 * Routes
 * -------------------------------------------------------------------------
 * GET /feedback              → index()     — HOD / Staff Coordinator list
 * GET /feedback/event        → event()     — HOD / Staff Coordinator detail
 * GET /feedback/fic/event    → ficEvent()  — FIC (assigned events only)
 * GET /feedback/judge/event  → judgeEvent()— Judge (assigned events only)
 *
 * Authorization
 * -------------------------------------------------------------------------
 * • HOD / Staff Coordinator: RoleMiddleware enforced. No event-scoping.
 * • FIC: Must be actively assigned to the event (FacultyAssignmentModel).
 * • Judge: Must be actively assigned to the event (JudgeAssignmentModel).
 * • IDOR: event_id from GET is always validated through service authorization.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Services\FeedbackService;
use App\Models\SymposiumEventModel;
use App\Models\SymposiumModel;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

class FeedbackController extends BaseController
{
    private FeedbackService     $feedbackService;
    private SymposiumEventModel $eventModel;
    private SymposiumModel      $symposiumModel;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->feedbackService = new FeedbackService();
        $this->eventModel      = new SymposiumEventModel();
        $this->symposiumModel  = new SymposiumModel();
    }

    // =========================================================================
    // INDEX — Symposium/Event listing (HOD / Staff Coordinator)
    // =========================================================================

    /**
     * Display the feedback index: symposium selector + finalized event list.
     *
     * GET /feedback
     */
    public function index(): void
    {
        RoleMiddleware::requireRole('Admin', 'HOD', 'Staff Coordinator');

        $symposiums          = $this->symposiumModel->getAll();
        $selectedSymposiumId = (int) ($_GET['symposium_id'] ?? 0);

        if ($selectedSymposiumId === 0 && !empty($symposiums)) {
            $selectedSymposiumId = (int) $symposiums[0]['symposium_id'];
        }

        $events = [];
        if ($selectedSymposiumId > 0) {
            $allEvents = $this->eventModel->getBySymposium($selectedSymposiumId);

            foreach ($allEvents as $ev) {
                if ($this->feedbackService->isAttendanceFinalized((int) $ev['symposium_event_id'])) {
                    $ev['feedback_summary'] = $this->feedbackService->getEventFeedbackSummary(
                        (int) $ev['symposium_event_id']
                    );
                    $events[] = $ev;
                }
            }
        }

        $this->render('feedback.index', [
            'pageTitle'           => 'Event Feedback',
            'symposiums'          => $symposiums,
            'selectedSymposiumId' => $selectedSymposiumId,
            'events'              => $events,
        ], 'dashboard');
    }

    // =========================================================================
    // EVENT DETAIL — Identified feedback list (HOD / Staff Coordinator)
    // =========================================================================

    /**
     * Display identified student feedback for a specific event.
     *
     * GET /feedback/event?id={symposium_event_id}
     */
    public function event(): void
    {
        RoleMiddleware::requireRole('Admin', 'HOD', 'Staff Coordinator');

        $eventId = (int) ($_GET['id'] ?? 0);
        if ($eventId <= 0) {
            Session::flash('error', 'Invalid event selected.');
            $this->redirect('/feedback');
        }

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            Session::flash('error', 'Event not found.');
            $this->redirect('/feedback');
        }

        $summary      = $this->feedbackService->getEventFeedbackSummary($eventId);
        $feedbackList = $this->feedbackService->getEventFeedback($eventId, 50, 0);

        $this->render('feedback.event_detail', [
            'pageTitle'    => 'Feedback: ' . ($event['event_name'] ?? ''),
            'event'        => $event,
            'summary'      => $summary,
            'feedbackList' => $feedbackList,
            'backUrl'      => '/feedback?symposium_id=' . ($event['symposium_id'] ?? 0),
        ], 'dashboard');
    }

    // =========================================================================
    // FIC — Identified feedback for assigned event only
    // =========================================================================

    /**
     * Display identified feedback for a FIC's assigned event.
     *
     * GET /feedback/fic/event?id={symposium_event_id}
     */
    public function ficEvent(): void
    {
        $user    = Session::get('user', []);
        $userId  = (int) ($user['user_id'] ?? 0);
        $eventId = (int) ($_GET['id'] ?? 0);

        // Authorization: FIC must be assigned to this event
        if ($eventId <= 0 || !$this->feedbackService->canFICViewFeedback($eventId, $userId)) {
            Session::flash('error', 'You are not authorized to view feedback for this event.');
            $this->redirect('/my/assigned-events');
        }

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            Session::flash('error', 'Event not found.');
            $this->redirect('/my/assigned-events');
        }

        $summary      = $this->feedbackService->getEventFeedbackSummary($eventId);
        $feedbackList = $this->feedbackService->getEventFeedback($eventId, 50, 0);

        $this->render('feedback.event_detail', [
            'pageTitle'    => 'Feedback: ' . ($event['event_name'] ?? ''),
            'event'        => $event,
            'summary'      => $summary,
            'feedbackList' => $feedbackList,
            'backUrl'      => '/my/assigned-events/event?id=' . $eventId,
        ], 'dashboard');
    }

    // =========================================================================
    // JUDGE — Identified feedback for assigned event only
    // =========================================================================

    /**
     * Display identified feedback for a Judge's assigned event.
     *
     * GET /feedback/judge/event?id={symposium_event_id}
     */
    public function judgeEvent(): void
    {
        $user    = Session::get('user', []);
        $userId  = (int) ($user['user_id'] ?? 0);
        $eventId = (int) ($_GET['id'] ?? 0);

        // Authorization: Judge must be assigned to this event
        if ($eventId <= 0 || !$this->feedbackService->canJudgeViewFeedback($eventId, $userId)) {
            Session::flash('error', 'You are not authorized to view feedback for this event.');
            $this->redirect('/judge/my-events');
        }

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            Session::flash('error', 'Event not found.');
            $this->redirect('/judge/my-events');
        }

        $summary      = $this->feedbackService->getEventFeedbackSummary($eventId);
        $feedbackList = $this->feedbackService->getEventFeedback($eventId, 50, 0);

        $this->render('feedback.event_detail', [
            'pageTitle'    => 'Feedback: ' . ($event['event_name'] ?? ''),
            'event'        => $event,
            'summary'      => $summary,
            'feedbackList' => $feedbackList,
            'backUrl'      => '/judge/evaluate?id=' . $eventId,
        ], 'dashboard');
    }
}
