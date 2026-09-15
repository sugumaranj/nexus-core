<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : StudentPortalController.php
 * Location    : app/Controllers/
 * Description : Handles all student-facing portal pages.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Student dashboard
 * • Symposium listing (notice-board filtered view)
 * • Event listing for a symposium (event-based architecture)
 * • My Registrations (event-based)
 * • Team management (flat team — no leader)
 * • Notifications
 * • PDF circular download
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\StudentAuthMiddleware;
use App\Models\ApplicationModel;
use App\Models\SymposiumEventModel;
use App\Models\SymposiumModel;
use App\Models\TeamMemberModel;
use App\Models\TeamModel;
use App\Services\PdfDocumentService;
use App\Services\StudentAuthService;

/**
 * Manages all student portal pages. Protected by StudentAuthMiddleware.
 */
class StudentPortalController extends BaseController
{
    /** @var SymposiumModel Symposium data access */
    private SymposiumModel $sympModel;

    /** @var SymposiumEventModel Event snapshot data access */
    private SymposiumEventModel $eventModel;

    /** @var ApplicationModel Application/registration data access */
    private ApplicationModel $appModel;

    /** @var TeamModel Team data access */
    private TeamModel $teamModel;

    /** @var TeamMemberModel Team member data access */
    private TeamMemberModel $memberModel;

    /**
     * Constructor — enforces student authentication on all methods.
     */
    public function __construct()
    {
        StudentAuthMiddleware::handle();
        $this->sympModel   = new SymposiumModel();
        $this->eventModel  = new SymposiumEventModel();
        $this->appModel    = new ApplicationModel();
        $this->teamModel   = new TeamModel();
        $this->memberModel = new TeamMemberModel();
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Student Dashboard.
     *
     * Shows active symposiums, recent registrations, and quick stats.
     *
     * GET /student/dashboard
     * -------------------------------------------------------------------------
     */
    public function dashboard(): void
    {
        $studentAuth = new StudentAuthService();
        $student     = $studentAuth->student();

        // Fetch symposiums that are visible to students (Approved or beyond)
        $activeSymposiums = $this->sympModel->getApprovedForStudents();
        foreach ($activeSymposiums as &$symp) {
            $symp['has_rescheduled'] = false;
            $events = $this->eventModel->getScheduledEvents((int)$symp['symposium_id']);
            foreach ($events as $ev) {
                if ($ev['schedule_status'] === 'Rescheduled') {
                    $symp['has_rescheduled'] = true;
                    break;
                }
            }
        }

        // All registrations for the student
        $allApplications    = $this->appModel->getByStudentEventBased((int)$student['student_id']);
        $recentApplications = array_slice($allApplications, 0, 5);

        // Stats for dashboard widgets
        $activeApplicationsCount = 0;
        foreach ($allApplications as $app) {
            if ($app['application_status'] !== 'Withdrawn' && $app['application_status'] !== 'Cancelled') {
                $activeApplicationsCount++;
            }
        }
        $myRegistrationsCount  = $activeApplicationsCount;
        $activeSymposiumsCount = count($activeSymposiums);

        // Important Notices — upcoming venue-assigned events in the next 7 days
        $todayTs  = strtotime(date('Y-m-d'));
        $limitTs  = strtotime('+7 days');
        $importantNotices = [];
        foreach ($allApplications as $app) {
            // Only active registrations with a venue and an upcoming date
            $status   = $app['application_status'] ?? '';
            $evDate   = $app['event_date'] ?? '';
            $venName  = $app['venue_name'] ?? '';
            if (in_array($status, ['Withdrawn', 'Cancelled', 'Rejected'], true)) { continue; }
            if (empty($evDate) || empty($venName)) { continue; }
            $evTs = strtotime($evDate);
            if ($evTs < $todayTs || $evTs > $limitTs) { continue; }
            $importantNotices[] = $app;
        }
        // Sort by event date ASC
        usort($importantNotices, fn($a, $b) => strtotime($a['event_date']) <=> strtotime($b['event_date']));
        $importantNotices = array_slice($importantNotices, 0, 10);

        $this->render('student.dashboard', [
            'pageTitle'            => 'Student Dashboard',
            'student'              => $student,
            'symposiums'           => $activeSymposiums,
            'activeSymposiums'     => $activeSymposiums,
            'recentApplications'   => $recentApplications,
            'myRegistrationsCount' => $myRegistrationsCount,
            'activeSymposiumsCount'=> $activeSymposiumsCount,
            'importantNotices'     => $importantNotices,
        ], 'student');
    }

    // =========================================================================
    // SYMPOSIUMS
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Symposiums Listing.
     *
     * Lists all approved symposiums that students can browse and register for.
     *
     * GET /student/symposiums
     * -------------------------------------------------------------------------
     */
    public function symposiums(): void
    {
        $symposiums = $this->sympModel->getApprovedForStudents();

        $this->render('student.symposiums', [
            'pageTitle'  => 'Symposiums',
            'symposiums' => $symposiums,
        ], 'student');
    }

    /**
     * -------------------------------------------------------------------------
     * Download Symposium PDF Circular.
     *
     * Streams a PDF brochure for the selected symposium.
     *
     * GET /student/symposiums/generate-pdf?id={symposiumId}
     * -------------------------------------------------------------------------
     */
    public function downloadPdf(): void
    {
        $symposiumId = (int)($_GET['id'] ?? 0);
        $type        = in_array($_GET['type'] ?? '', ['circular', 'brochure', 'schedule'], true)
                        ? $_GET['type']
                        : 'circular';

        $symposium = $this->sympModel->findById($symposiumId);

        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/student/symposiums');
        }

        try {
            $pdfService = new PdfDocumentService();
            $pdfService->generateSymposiumDocument($symposiumId, $type);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Student PDF generation error', ['exception' => $e]);
            Session::flash('error', 'Could not generate PDF: ' . $e->getMessage());
            $this->redirect('/student/symposiums');
        }
    }

    // =========================================================================
    // EVENT-BASED REGISTRATION
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Events Listing for a Symposium.
     *
     * Displays all schedulable events within a symposium with the student's
     * current registration status for each event.
     *
     * GET /student/symposiums/events?id={symposiumId}
     * -------------------------------------------------------------------------
     */
    public function events(): void
    {
        $symposiumId = (int)($_GET['id'] ?? 0);
        $symposium   = $this->sympModel->findById($symposiumId);

        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/student/symposiums');
        }

        // Only show events for symposiums that have been approved or completed
        $validStatuses = ['Approved', 'Scheduling Complete', 'Registration Open', 'Registration Closed', 'Completed'];
        if (!in_array($symposium['status'], $validStatuses, true)) {
            Session::flash('error', 'Events are not available for this symposium yet.');
            $this->redirect('/student/symposiums');
        }

        // Load all event snapshots attached to this symposium
        $events = $this->eventModel->getBySymposium($symposiumId);

        // Identify which events the student has already applied for
        $studentAuth = new StudentAuthService();
        $studentSession = $studentAuth->student();
        $studentId   = (int)($studentSession['student_id'] ?? 0);

        $myApps = $this->appModel->getByStudentEventBased($studentId);

        // Build lookup map: [symposium_event_id => application_status]
        $registeredEvents = [];
        foreach ($myApps as $app) {
            if (!empty($app['symposium_event_id'])) {
                $status = $app['application_status'];
                if (!in_array($status, ['Withdrawn', 'Cancelled', 'Rejected'], true)) {
                    $registeredEvents[(int)$app['symposium_event_id']] = $status;
                }
            }
        }

        // Check if the symposium registration window is open right now
        $now              = date('Y-m-d H:i:s');
        $registrationOpen = (
            !empty($symposium['registration_start']) &&
            !empty($symposium['registration_end']) &&
            $now >= $symposium['registration_start'] &&
            $now <= $symposium['registration_end']
        );

        $this->render('student.events', [
            'pageTitle'        => 'Events — ' . htmlspecialchars($symposium['title'], ENT_QUOTES, 'UTF-8'),
            'symposium'        => $symposium,
            'events'           => $events,
            'registeredEvents' => $registeredEvents,
            'registrationOpen' => $registrationOpen,
        ], 'student');
    }

    // =========================================================================
    // MY REGISTRATIONS
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * My Registrations.
     *
     * Lists all event-based applications submitted by the logged-in student.
     *
     * GET /student/my-registrations
     * -------------------------------------------------------------------------
     */
    public function myRegistrations(): void
    {
        $studentAuth  = new StudentAuthService();
        $studentSession = $studentAuth->student();
        $studentId    = (int)($studentSession['student_id'] ?? 0);

        $applications = $this->appModel->getByStudentEventBased($studentId);

        $this->render('student.my_registrations', [
            'pageTitle'        => 'My Registrations',
            'applications'     => $applications,
            'currentStudentId' => $studentId,
        ], 'student');
    }

    // =========================================================================
    // TEAM MANAGEMENT
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Manage Team Members.
     *
     * Shows team details and member list. Only the student who originally
     * registered (application.student_id — the "creator") can add or remove
     * members. All members are equal — there is no Leader concept.
     *
     * GET /student/teams/view?id={teamId}
     * -------------------------------------------------------------------------
     */
    public function team(): void
    {
        $teamId = (int)($_GET['id'] ?? 0);
        $team   = $this->teamModel->findById($teamId);

        if (!$team) {
            Session::flash('error', 'Team not found.');
            $this->redirect('/student/my-registrations');
        }

        // Load the linked application to verify ownership
        $application = $this->appModel->findById((int)$team['application_id']);
        if (!$application) {
            Session::flash('error', 'The application associated with this team was not found.');
            $this->redirect('/student/my-registrations');
        }

        // Only the student who created the registration may manage the team
        $studentAuth = new StudentAuthService();
        $studentSession = $studentAuth->student();
        $studentId   = (int)($studentSession['student_id'] ?? 0);

        // Fetch all members with full student detail
        $members = $this->memberModel->getByTeam($teamId);

        $isCreator = ((int)$application['student_id'] === $studentId);
        $isMember  = false;
        foreach ($members as $m) {
            if ((int)$m['student_id'] === $studentId) {
                $isMember = true;
                break;
            }
        }

        if (!$isCreator && !$isMember) {
            Session::flash('error', 'You are not authorized to view this team.');
            $this->redirect('/student/my-registrations');
        }

        // Load the event snapshot for display context
        $event = $this->eventModel->findById((int)$application['symposium_event_id']);

        $this->render('student.manage_team', [
            'pageTitle'        => 'Manage Team',
            'team'             => $team,
            'members'          => $members,
            'event'            => $event,
            'application'      => $application,
            'currentStudentId' => $studentId,
        ], 'student');
    }

    // =========================================================================
    // NOTIFICATIONS
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Student Notifications.
     *
     * Displays all notifications for the logged-in student.
     *
     * GET /student/notifications
     * -------------------------------------------------------------------------
     */
    public function notifications(): void
    {
        $studentAuth = new StudentAuthService();
        $studentSession = $studentAuth->student();
        $studentId   = (int)($studentSession['student_id'] ?? 0);

        $this->render('student.notifications', [
            'pageTitle' => 'Notifications',
            'studentId' => $studentId,
        ], 'student');
    }
}
