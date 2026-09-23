<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : StudentFeedbackController.php
 * Location    : app/Controllers/
 * Description : Handles student-facing identified event feedback flows.
 *
 * Routes
 * -------------------------------------------------------------------------
 * GET  /student/feedback           → index()  — list pending/submitted
 * GET  /student/feedback/event     → form()   — feedback form for one event
 * POST /student/feedback/submit    → submit() — process submission
 *
 * Security
 * -------------------------------------------------------------------------
 * • student_id is ALWAYS obtained from the authenticated session.
 *   It is NEVER trusted from POST or GET parameters.
 * • event_id is from GET/POST but validated server-side (event must exist,
 *   student must be eligible).
 * • CSRF validated on POST using the existing project CSRF pattern.
 * • Eligibility re-checked on submit (not only on form load).
 * • DB unique constraint prevents concurrent duplicate submissions.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Services\FeedbackService;
use App\Models\SymposiumEventModel;
use App\Models\ApplicationModel;
use App\Services\StudentAuthService;
use App\Middleware\StudentAuthMiddleware;

class StudentFeedbackController extends BaseController
{
    private FeedbackService      $feedbackService;
    private SymposiumEventModel  $eventModel;
    private ApplicationModel     $applicationModel;
    private StudentAuthService   $studentAuthService;

    public function __construct()
    {
        StudentAuthMiddleware::handle();
        $this->feedbackService    = new FeedbackService();
        $this->eventModel         = new SymposiumEventModel();
        $this->applicationModel   = new ApplicationModel();
        $this->studentAuthService = new StudentAuthService();
    }

    // =========================================================================
    // INDEX — Pending & Submitted Feedback
    // =========================================================================

    /**
     * Display the student's feedback page (pending and submitted events).
     *
     * GET /student/feedback
     */
    public function index(): void
    {
        $student   = $this->studentAuthService->student();
        $studentId = (int) $student['student_id'];

        $applications = $this->applicationModel->getByStudentEventBased($studentId);

        $data = $this->feedbackService->getStudentFeedbackPageData($studentId, $applications);

        $this->render('student.feedback', [
            'pageTitle' => 'Event Feedback',
            'pending'   => $data['pending'],
            'submitted' => $data['submitted'],
        ], 'student');
    }

    // =========================================================================
    // FORM — Feedback Form for a Specific Event
    // =========================================================================

    /**
     * Display the feedback submission form.
     *
     * GET /student/feedback/event?id={symposium_event_id}
     */
    public function form(): void
    {
        $eventId = (int) ($_GET['id'] ?? 0);
        if ($eventId <= 0) {
            Session::flash('error', 'Invalid event selected.');
            $this->redirect('/student/feedback');
        }

        $student   = $this->studentAuthService->student();
        $studentId = (int) $student['student_id'];

        // Check eligibility
        $eligibility = $this->feedbackService->isStudentEligible($studentId, $eventId);
        if (!$eligibility['eligible']) {
            Session::flash('error', $eligibility['reason']);
            $this->redirect('/student/feedback');
        }

        // Check already submitted
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
            'pageTitle' => 'Submit Feedback — ' . htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES),
            'event'     => $event,
        ], 'student');
    }

    // =========================================================================
    // SUBMIT — Process Feedback Submission
    // =========================================================================

    /**
     * Process feedback form POST.
     *
     * POST /student/feedback/submit
     */
    public function submit(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/student/feedback');
        }

        // CSRF validation — uses the project's existing csrf_token session key
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== Session::get('csrf_token')) {
            Session::flash('error', 'Invalid security token. Please try again.');
            $this->redirect('/student/feedback');
        }

        // student_id from authenticated session ONLY — never from POST
        $student   = $this->studentAuthService->student();
        $studentId = (int) $student['student_id'];

        // event_id from POST but validated server-side in submitFeedback()
        $eventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $rating  = (int) ($_POST['rating']              ?? 0);
        $review  = $_POST['review']                      ?? null;

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
