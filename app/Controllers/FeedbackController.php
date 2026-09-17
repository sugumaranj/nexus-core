<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Services\FeedbackService;
use App\Models\SymposiumEventModel;
use App\Models\SymposiumModel;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

class FeedbackController extends BaseController
{
    private FeedbackService $feedbackService;
    private SymposiumEventModel $eventModel;
    private SymposiumModel $symposiumModel;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->feedbackService = new FeedbackService();
        $this->eventModel = new SymposiumEventModel();
        $this->symposiumModel = new SymposiumModel();
    }

    /**
     * Display list of events with feedback summaries for HOD/Staff Coordinator.
     * 
     * GET /feedback
     */
    public function index(): void
    {
        RoleMiddleware::requireRole('Admin', 'HOD', 'Staff Coordinator');

        $symposiums = $this->symposiumModel->getAll();
        
        $selectedSymposiumId = (int)($_GET['symposium_id'] ?? 0);
        if ($selectedSymposiumId === 0 && !empty($symposiums)) {
            $selectedSymposiumId = (int)$symposiums[0]['symposium_id'];
        }

        $events = [];
        if ($selectedSymposiumId > 0) {
            $allEvents = $this->eventModel->getBySymposium($selectedSymposiumId);
            
            // Only show events where attendance is finalized
            foreach ($allEvents as $ev) {
                if ($this->feedbackService->isAttendanceFinalized((int)$ev['symposium_event_id'])) {
                    $ev['feedback_summary'] = $this->feedbackService->getEventFeedbackSummary((int)$ev['symposium_event_id']);
                    $events[] = $ev;
                }
            }
        }

        $this->render('feedback.index', [
            'pageTitle' => 'Event Feedback',
            'symposiums' => $symposiums,
            'selectedSymposiumId' => $selectedSymposiumId,
            'events' => $events,
        ], 'dashboard');
    }

    /**
     * Display anonymous reviews for an event (HOD/Staff Coordinator).
     * 
     * GET /feedback/event
     */
    public function event(): void
    {
        RoleMiddleware::requireRole('Admin', 'HOD', 'Staff Coordinator');

        $eventId = (int)($_GET['id'] ?? 0);
        if ($eventId <= 0) {
            Session::flash('error', 'Invalid event selected.');
            $this->redirect('/feedback');
        }

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            Session::flash('error', 'Event not found.');
            $this->redirect('/feedback');
        }

        $summary = $this->feedbackService->getEventFeedbackSummary($eventId);
        $reviews = $this->feedbackService->getEventAnonymousReviews($eventId, 50, 0);

        $this->render('feedback.event_summary', [
            'pageTitle' => 'Feedback: ' . $event['event_name'],
            'event'     => $event,
            'summary'   => $summary,
            'reviews'   => $reviews,
            'backUrl'   => '/feedback?symposium_id=' . $event['symposium_id'],
        ], 'dashboard');
    }

    /**
     * Display anonymous reviews for an event (FIC).
     * 
     * GET /feedback/fic/event
     */
    public function ficEvent(): void
    {
        $userId = (int)Session::get('user')['user_id'];
        $eventId = (int)($_GET['id'] ?? 0);

        if ($eventId <= 0 || !$this->feedbackService->canFICViewFeedback($eventId, $userId)) {
            // Redirect with error or show 403
            Session::flash('error', 'You are not authorized to view feedback for this event.');
            $this->redirect('/dashboard');
        }

        $event = $this->eventModel->findById($eventId);
        $summary = $this->feedbackService->getEventFeedbackSummary($eventId);
        $reviews = $this->feedbackService->getEventAnonymousReviews($eventId, 50, 0);

        $this->render('feedback.event_summary', [
            'pageTitle' => 'Feedback: ' . $event['event_name'],
            'event'     => $event,
            'summary'   => $summary,
            'reviews'   => $reviews,
            'backUrl'   => '/my/assigned-events/event?id=' . $eventId,
        ], 'dashboard');
    }

    /**
     * Display anonymous reviews for an event (Judge).
     * 
     * GET /feedback/judge/event
     */
    public function judgeEvent(): void
    {
        $userId = (int)Session::get('user')['user_id'];
        $eventId = (int)($_GET['id'] ?? 0);

        if ($eventId <= 0 || !$this->feedbackService->canJudgeViewFeedback($eventId, $userId)) {
            Session::flash('error', 'You are not authorized to view feedback for this event.');
            $this->redirect('/dashboard');
        }

        $event = $this->eventModel->findById($eventId);
        $summary = $this->feedbackService->getEventFeedbackSummary($eventId);
        $reviews = $this->feedbackService->getEventAnonymousReviews($eventId, 50, 0);

        $this->render('feedback.event_summary', [
            'pageTitle' => 'Feedback: ' . $event['event_name'],
            'event'     => $event,
            'summary'   => $summary,
            'reviews'   => $reviews,
            'backUrl'   => '/judge/evaluate?id=' . $eventId,
        ], 'dashboard');
    }
}
