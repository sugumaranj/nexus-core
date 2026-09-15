<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\StudentAuthMiddleware;
use App\Models\SymposiumEventModel;
use App\Models\SymposiumModel;
use App\Services\RegistrationService;
use App\Services\StudentAuthService;
use App\Validators\RegistrationValidator;

/**
 * Controller for student event registrations
 */
class StudentRegistrationController extends BaseController
{
    /** @var RegistrationService */
    private RegistrationService $registrationService;
    
    /** @var SymposiumEventModel */
    private SymposiumEventModel $eventModel;
    
    /** @var SymposiumModel */
    private SymposiumModel $sympModel;

    /**
     * Constructor sets up middleware and services
     */
    public function __construct()
    {
        StudentAuthMiddleware::handle();
        $this->registrationService = new RegistrationService();
        $this->eventModel = new SymposiumEventModel();
        $this->sympModel = new SymposiumModel();
    }

    /**
     * Displays the registration form for an event
     * GET /student/events/register?event_id={id}
     */
    public function showRegistrationForm(): void
    {
        $eventId = (int)($_GET['event_id'] ?? 0);
        $event = $this->eventModel->findById($eventId);

        if (!$event) {
            Session::flash('error', 'Event not found.');
            $this->redirect('/student/symposiums');
        }

        $validStatuses = ['Published', 'Registration Open'];
        if (!in_array($event['status'], $validStatuses, true) || (int)$event['supports_registration'] !== 1) {
            Session::flash('error', 'Registration is not available for this event.');
            $this->redirect('/student/symposiums');
        }

        $symposium = $this->sympModel->findById((int)$event['symposium_id']);
        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/student/symposiums');
        }

        $now = date('Y-m-d H:i:s');
        if ($now < $symposium['registration_start'] || $now > $symposium['registration_end']) {
            Session::flash('error', 'Registration window is closed.');
            $this->redirect('/student/symposiums');
        }

        $studentAuth = new StudentAuthService();
        $studentSession = $studentAuth->student();
        
        $studentModel = new \App\Models\StudentModel();
        $student = $studentModel->findById((int)$studentSession['student_id']);

        $this->render('student.event_register', [
            'pageTitle' => 'Register for ' . $event['event_name'],
            'student'   => $student,
            'event'     => $event
        ], 'student');
    }

    /**
     * Handles event registration submission
     * POST /student/events/register
     */
    public function register(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/student/symposiums');
        }

        $eventId = (int)($_POST['symposium_event_id'] ?? 0);
        
        $studentAuth = new StudentAuthService();
        $studentSession = $studentAuth->student();
        $studentId = (int)($studentSession['student_id'] ?? 0);

        if (!$studentId || !$eventId) {
            Session::flash('error', 'Invalid request.');
            $this->redirect('/student/symposiums');
        }

        $result = $this->registrationService->registerForEvent($studentId, $eventId, $_POST);

        if ($result['success']) {
            Session::flash('success', $result['message']);
            $this->redirect('/student/my-registrations');
        } else {
            Session::flash('error', $result['message']);
            $this->redirect('/student/events/register?event_id=' . $eventId);
        }
    }

    /**
     * Withdraws a registration
     * POST /student/applications/withdraw
     */
    public function withdraw(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/student/my-registrations');
        }

        $applicationId = (int)($_POST['application_id'] ?? 0);
        $studentAuth = new StudentAuthService();
        $studentSession = $studentAuth->student();
        $studentId = (int)($studentSession['student_id'] ?? 0);

        if (!$applicationId || !$studentId) {
            Session::flash('error', 'Invalid request.');
            $this->redirect('/student/my-registrations');
        }

        $result = $this->registrationService->withdraw($studentId, $applicationId);

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        $this->redirect('/student/my-registrations');
    }

    /**
     * Adds a member to a team
     * POST /student/teams/members
     */
    public function addTeamMember(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/student/my-registrations');
        }

        $teamId = (int)($_POST['team_id'] ?? 0);
        $registerNumber = trim($_POST['register_number'] ?? '');
        
        if (!$teamId || empty($registerNumber)) {
            Session::flash('error', 'Invalid request.');
            $this->redirect('/student/my-registrations');
        }

        // Basic validation using Validator could go here, or handled natively
        if (strlen($registerNumber) < 5) {
            Session::flash('error', 'Invalid register number.');
            $this->redirect('/student/teams/view?id=' . $teamId);
        }

        $studentAuth = new StudentAuthService();
        $studentSession = $studentAuth->student();
        $studentId = (int)($studentSession['student_id'] ?? 0);

        $result = $this->registrationService->addTeamMember($teamId, $registerNumber, $studentId);

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        $this->redirect('/student/teams/view?id=' . $teamId);
    }

    /**
     * Removes a member from a team
     * POST /student/teams/members/remove
     */
    public function removeTeamMember(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/student/my-registrations');
        }

        $teamId = (int)($_POST['team_id'] ?? 0);
        $memberStudentId = (int)($_POST['member_student_id'] ?? 0);
        
        if (!$teamId || !$memberStudentId) {
            Session::flash('error', 'Invalid request.');
            $this->redirect('/student/my-registrations');
        }

        $studentAuth = new StudentAuthService();
        $studentSession = $studentAuth->student();
        $studentId = (int)($studentSession['student_id'] ?? 0);

        $result = $this->registrationService->removeTeamMember($teamId, $memberStudentId, $studentId);

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        $this->redirect('/student/teams/view?id=' . $teamId);
    }
}
