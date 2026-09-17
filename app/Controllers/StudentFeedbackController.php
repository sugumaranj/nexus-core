<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Services\FeedbackService;
use App\Models\SymposiumEventModel;
use App\Models\ApplicationModel;
use App\Services\StudentAuthService;
use App\Middleware\StudentAuthMiddleware;

class StudentFeedbackController extends BaseController
{
    private FeedbackService $feedbackService;
    private SymposiumEventModel $eventModel;
    private ApplicationModel $applicationModel;
    private StudentAuthService $studentAuthService;

    public function __construct()
    {
        StudentAuthMiddleware::handle();
        $this->feedbackService = new FeedbackService();
        $this->eventModel = new SymposiumEventModel();
        $this->applicationModel = new ApplicationModel();
        $this->studentAuthService = new StudentAuthService();
    }

    /**
     * Display the feedback page (Pending & Submitted).
     * 
     * GET /student/feedback
     */
    public function index(): void
    {
        $student = $this->studentAuthService->student();
        $studentId = (int)$student['student_id'];

        // Get all student applications
        $applications = $this->applicationModel->getByStudentEventBased($studentId);

        // Get data from service
        $data = $this->feedbackService->getStudentFeedbackPageData($studentId, $applications);

        $this->render('student.feedback', [
            'pageTitle' => 'Event Feedback',
            'pending'   => $data['pending'],
            'submitted' => $data['submitted'],
        ], 'student');
    }

    /**
     * Display feedback form for a specific event.
     * 
     * GET /student/feedback/event
     */
    public function form(): void
    {
        $eventId = (int)($_GET['id'] ?? 0);
        if ($eventId <= 0) {
            Session::flash('error', 'Invalid event selected.');
            $this->redirect('/student/feedback');
        }

        $student = $this->studentAuthService->student();
        $studentId = (int)$student['student_id'];

        $eligibility = $this->feedbackService->isStudentEligible($studentId, $eventId);
        if (!$eligibility['eligible']) {
            Session::flash('error', $eligibility['reason']);
            $this->redirect('/student/feedback');
        }

        if ($this->feedbackService->hasStudentSubmitted($studentId, $eventId)) {
            Session::flash('info', 'You have already submitted feedback for this event.');
            $this->redirect('/student/feedback');
        }

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            Session::flash('error', 'Event not found.');
            $this->redirect('/student/feedback');
        }

        $this->render('student.feedback_form', [
            'pageTitle' => 'Submit Feedback',
            'event'     => $event,
        ], 'student');
    }

    /**
     * Process feedback submission.
     * 
     * POST /student/feedback/submit
     */
    public function submit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/student/feedback');
        }

        // CSRF Verification
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== Session::get('csrf_token')) {
            Session::flash('error', 'Invalid security token. Please try again.');
            $this->redirect('/student/feedback');
        }

        $student = $this->studentAuthService->student();
        $studentId = (int)$student['student_id'];
        
        $eventId = (int)($_POST['symposium_event_id'] ?? 0);
        $rating  = (int)($_POST['rating'] ?? 0);
        $review  = $_POST['review'] ?? null;

        $result = $this->feedbackService->submitFeedback($studentId, $eventId, $rating, $review);

        if ($result['success']) {
            Session::flash('success', $result['message']);
            $this->redirect('/student/feedback');
        } else {
            Session::flash('error', $result['message']);
            $this->redirect('/student/feedback/event?id=' . $eventId);
        }
    }
}
