<?php

declare(strict_types=1);
/* Enable error reporting for debugging in public/index.php */
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : index.php
 * Location    : public/
 * Description : Front Controller
 *
 * Every HTTP request enters the application through this file.
 * It bootstraps the application, registers routes and dispatches
 * the incoming request.
 *
 * -------------------------------------------------------------------------
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\AttendanceController;
use App\Controllers\RegistrationCoordinatorController;
use App\Controllers\DashboardController;
use App\Controllers\StudentFeedbackController;
use App\Controllers\FeedbackController;
use App\Controllers\DepartmentController;
use App\Controllers\ResourceAllocationController;
use App\Controllers\VenueController;
use App\Controllers\HomeController;
use App\Controllers\ModuleController;
use App\Controllers\StudentAuthController;
use App\Controllers\StudentController;
use App\Controllers\StudentPortalController;
use App\Controllers\StudentRegistrationController;
use App\Controllers\SyncCenterController;
use App\Controllers\SymposiumController;
use App\Controllers\FacultyInChargeController;
use App\Controllers\UserController;
use App\Controllers\JudgeController;
use App\Controllers\SettingsController;
use App\Controllers\MasterEventController;
use App\Controllers\SymposiumEventController;
use App\Controllers\SchedulingController;
use App\Controllers\NoticeBoardController;
use App\Controllers\ChatbotController;
use App\Controllers\ScheduleImageController;
use App\Controllers\ResultViewerController;
use App\Controllers\CertificateController;
use App\Controllers\CertificateTemplateController;
use App\Controllers\CertificateGenerationController;
use App\Controllers\CertificateDownloadController;
use App\Controllers\CertificateVerificationController;
use App\Controllers\StudentCertificateController;
use App\Controllers\PaymentController;
use App\Controllers\PrelimsFicController;
use App\Controllers\StudentPrelimsQuizController;
use App\Controllers\PrelimsReportController;

use App\Core\Application;
use App\Core\Bootstrap;
use App\Core\Router;

/*
|--------------------------------------------------------------------------
| Bootstrap Application
|--------------------------------------------------------------------------
|
| Load environment variables and initialize the application.
|
*/

Bootstrap::loadEnvironment(dirname(__DIR__));

/*
|--------------------------------------------------------------------------
| Error & Exception Handling
|--------------------------------------------------------------------------
|
| Register global exception and error handlers to prevent white screens
| and log all errors properly.
|
*/
\App\Core\ErrorHandler::register();

$app = new Application();

/*
|--------------------------------------------------------------------------
| Create Router
|--------------------------------------------------------------------------
*/

$router = new Router();

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
|
| These routes are accessible without authentication.
|
*/

// Landing Page
$router->get(
    '/',
    [HomeController::class, 'index']
);

// Login Page
$router->get(
    '/login',
    [AuthController::class, 'showLogin']
);

// Login Form Submission
$router->post(
    '/login',
    [AuthController::class, 'login']
);

// Logout
$router->get(
    '/logout',
    [AuthController::class, 'logout']
);

// Public Notice Board
$router->get(
    '/notice-board',
    [NoticeBoardController::class, 'index']
);

/*
|--------------------------------------------------------------------------
| Dashboard Routes
|--------------------------------------------------------------------------
|
| Dashboard routing based on authenticated user roles.
|
*/

// Redirect user to appropriate dashboard
$router->get(
    '/dashboard',
    [DashboardController::class, 'index']
);

// Administrator Dashboard
$router->get(
    '/dashboard/admin',
    [DashboardController::class, 'admin']
);

// Principal Dashboard
$router->get(
    '/dashboard/principal',
    [DashboardController::class, 'principal']
);

// Head of Department Dashboard
$router->get(
    '/dashboard/hod',
    [DashboardController::class, 'hod']
);

// Staff Coordinator Dashboard
$router->get(
    '/dashboard/staff-coordinator',
    [DashboardController::class, 'staffCoordinator']
);

// Staff Dashboard
$router->get(
    '/dashboard/staff',
    [DashboardController::class, 'staff']
);

// Student Coordinator Dashboard
$router->get(
    '/dashboard/student-coordinator',
    [DashboardController::class, 'studentCoordinator']
);

/*
|--------------------------------------------------------------------------
| System Settings Routes
|--------------------------------------------------------------------------
*/

$router->get(
    '/settings',
    [SettingsController::class, 'index']
);

$router->post(
    '/settings/update',
    [SettingsController::class, 'update']
);

// Email configuration (Admin)
$router->post(
    '/settings/email',
    [SettingsController::class, 'updateEmailSettings']
);

// AJAX: test SMTP connection
$router->post(
    '/settings/email/test',
    [SettingsController::class, 'testEmail']
);


/*
|--------------------------------------------------------------------------
| Department Management Routes
|--------------------------------------------------------------------------
*/

// List Departments
$router->get(
    '/departments',
    [DepartmentController::class, 'index']
);

// Display Create Department Form
$router->get(
    '/departments/create',
    [DepartmentController::class, 'create']
);

// Store Department
$router->post(
    '/departments',
    [DepartmentController::class, 'store']
);

// Display Edit Department Form
$router->get(
    '/departments/edit',
    [DepartmentController::class, 'edit']
);

// Update Department
$router->post(
    '/departments/update',
    [DepartmentController::class, 'update']
);

// Delete Department
$router->post(
    '/departments/delete',
    [DepartmentController::class, 'delete']
);

/*
|--------------------------------------------------------------------------
| Venue Management Routes
|--------------------------------------------------------------------------
*/

// List Venues
$router->get(
    '/venues',
    [VenueController::class, 'index']
);

// Display Create Venue Form
$router->get(
    '/venues/create',
    [VenueController::class, 'create']
);

// Store Venue
$router->post(
    '/venues',
    [VenueController::class, 'store']
);

// Display Edit Venue Form
$router->get(
    '/venues/edit',
    [VenueController::class, 'edit']
);

// Update Venue
$router->post(
    '/venues/update',
    [VenueController::class, 'update']
);

// Delete Venue
$router->post(
    '/venues/delete',
    [VenueController::class, 'delete']
);

/*
|--------------------------------------------------------------------------
| Master Event Library Routes (Admin)
|--------------------------------------------------------------------------
*/

// List Master Events
$router->get(
    '/admin/events',
    [MasterEventController::class, 'index']
);

// Display Create Master Event Form
$router->get(
    '/admin/events/create',
    [MasterEventController::class, 'create']
);

// Store Master Event
$router->post(
    '/admin/events/create',
    [MasterEventController::class, 'store']
);

// Display Master Event Detail
$router->get(
    '/admin/events/view',
    [MasterEventController::class, 'view']
);

// Display Edit Master Event Form
$router->get(
    '/admin/events/edit',
    [MasterEventController::class, 'edit']
);

// Update Master Event
$router->post(
    '/admin/events/edit',
    [MasterEventController::class, 'update']
);

// Clone Master Event
$router->post(
    '/admin/events/clone',
    [MasterEventController::class, 'clone']
);

// Soft Delete Master Event
$router->post(
    '/admin/events/delete',
    [MasterEventController::class, 'delete']
);

// AJAX: Generate Event Code from Name
$router->get(
    '/admin/events/ajax/generate-code',
    [MasterEventController::class, 'generateCode']
);

// AJAX: Get Master Event Details JSON for info card
$router->get(
    '/admin/events/ajax/get-data',
    [MasterEventController::class, 'getEventData']
);

/*
|--------------------------------------------------------------------------
| Symposium Event Scheduling Routes (Coordinator)
|--------------------------------------------------------------------------
*/

// List Events for a Symposium
$router->get(
    '/symposiums/events',
    [SymposiumEventController::class, 'index']
);

// Display Add Event Form (Existing vs New)
$router->get(
    '/symposiums/events/create',
    [SymposiumEventController::class, 'create']
);

// Display 7-Step Coordinator Event Builder Wizard
$router->get(
    '/symposiums/events/wizard',
    [SymposiumEventController::class, 'wizard']
);

// Store Event in Symposium
$router->post(
    '/symposiums/events/create',
    [SymposiumEventController::class, 'store']
);

// View Scheduled Event Detail & Snapshot
$router->get(
    '/symposiums/events/view',
    [SymposiumEventController::class, 'view']
);

// Display Edit Event Form
$router->get(
    '/symposiums/events/edit',
    [SymposiumEventController::class, 'edit']
);

// Update Scheduled Event
$router->post(
    '/symposiums/events/edit',
    [SymposiumEventController::class, 'update']
);

// Delete Event from Symposium
$router->post(
    '/symposiums/events/delete',
    [SymposiumEventController::class, 'delete']
);

// Delete ALL Events from a Symposium (password-protected)
$router->post(
    '/symposiums/events/delete-all',
    [SymposiumEventController::class, 'deleteAll']
);

$router->post(
    '/symposiums/events/bulk-add',
    [SymposiumEventController::class, 'bulkAdd']
);

// AJAX: Check Scheduling & Capacity Conflicts
$router->post(
    '/symposiums/events/ajax/check-conflict',
    [SymposiumEventController::class, 'checkConflict']
);

/*
|--------------------------------------------------------------------------
| Event Scheduling Module Routes
|--------------------------------------------------------------------------
| Scheduling is only allowed AFTER symposium is Approved.
| Workflow: Approved -> Schedule Events -> Scheduling Complete -> Registration Open
|--------------------------------------------------------------------------
*/

// Scheduling Dashboard
$router->get(
    '/symposiums/scheduling',
    [SchedulingController::class, 'dashboard']
);

// Schedule a single event (first-time)
$router->get(
    '/symposiums/scheduling/event',
    [SchedulingController::class, 'scheduleEvent']
);
$router->post(
    '/symposiums/scheduling/event',
    [SchedulingController::class, 'saveSchedule']
);

// Reschedule an event
$router->get(
    '/symposiums/scheduling/reschedule',
    [SchedulingController::class, 'rescheduleEvent']
);
$router->post(
    '/symposiums/scheduling/reschedule',
    [SchedulingController::class, 'saveReschedule']
);

// Mark Scheduling Complete
$router->post(
    '/symposiums/scheduling/complete',
    [SchedulingController::class, 'markComplete']
);

// Schedule Report
$router->get(
    '/symposiums/scheduling/report',
    [SchedulingController::class, 'report']
);

// AJAX: Real-time conflict check for scheduling form
$router->post(
    '/symposiums/scheduling/ajax/check',
    [SchedulingController::class, 'ajaxCheckConflict']
);

/*
|--------------------------------------------------------------------------
| Resource Allocation Module Routes
|--------------------------------------------------------------------------
*/

// Dashboard & Screens
$router->get('/symposiums/allocation', [ResourceAllocationController::class, 'dashboard']);
$router->get('/symposiums/allocation/event', [ResourceAllocationController::class, 'eventAllocation']);
$router->get('/symposiums/allocation/availability', [ResourceAllocationController::class, 'staffAvailability']);

// Faculty Assignments
$router->post('/symposiums/allocation/faculty/assign', [ResourceAllocationController::class, 'assignFaculty']);
$router->post('/symposiums/allocation/faculty/remove', [ResourceAllocationController::class, 'removeFaculty']);
$router->post('/symposiums/allocation/faculty/replace', [ResourceAllocationController::class, 'replaceFaculty']);
$router->post('/symposiums/allocation/faculty/bulk-assign', [ResourceAllocationController::class, 'bulkAssignFaculty']);

// Judge Assignments
$router->post('/symposiums/allocation/judge/assign', [ResourceAllocationController::class, 'assignJudge']);
$router->post('/symposiums/allocation/judge/remove', [ResourceAllocationController::class, 'removeJudge']);
$router->post('/symposiums/allocation/judge/replace', [ResourceAllocationController::class, 'replaceJudge']);
$router->post('/symposiums/allocation/judge/bulk-assign', [ResourceAllocationController::class, 'bulkAssignJudge']);

// Reports
$router->get('/symposiums/allocation/reports/faculty', [ResourceAllocationController::class, 'facultyReport']);
$router->get('/symposiums/allocation/reports/judges', [ResourceAllocationController::class, 'judgeReport']);
$router->get('/symposiums/allocation/reports/combined', [ResourceAllocationController::class, 'combinedReport']);

// AJAX Endpoints
$router->get('/symposiums/allocation/ajax/availability', [ResourceAllocationController::class, 'ajaxCheckAvailability']);
$router->get('/symposiums/allocation/ajax/search-staff', [ResourceAllocationController::class, 'ajaxSearchStaff']);
$router->get('/symposiums/allocation/ajax/venue-availability', [ResourceAllocationController::class, 'ajaxVenueAvailability']);

// Venue Assignment
$router->post('/symposiums/allocation/venue/assign', [ResourceAllocationController::class, 'assignVenue']);
$router->post('/symposiums/allocation/venue/remove', [ResourceAllocationController::class, 'removeVenue']);

// Venue Report
$router->get('/symposiums/allocation/reports/venue', [ResourceAllocationController::class, 'venueReport']);

/*
|--------------------------------------------------------------------------
| User Management Routes
|--------------------------------------------------------------------------
*/

// List Users
$router->get(
    '/users',
    [UserController::class, 'index']
);

// Display Create User Form
$router->get(
    '/users/create',
    [UserController::class, 'create']
);

// Store User
$router->post(
    '/users/store',
    [UserController::class, 'store']
);

// Display Edit User Form
$router->get(
    '/users/edit',
    [UserController::class, 'edit']
);

// Update User
$router->post(
    '/users/update',
    [UserController::class, 'update']
);

// View User Details
$router->get(
    '/users/view',
    [UserController::class, 'view']
);

// Delete User
$router->post(
    '/users/delete',
    [UserController::class, 'delete']
);

// Change User Account Status
$router->post(
    '/users/change-status',
    [UserController::class, 'changeStatus']
);

// Reset User Password
$router->post(
    '/users/reset-password',
    [UserController::class, 'resetPassword']
);

// AJAX: Generate Employee ID preview (called on role selection change)
$router->get(
    '/users/generate-employee-id',
    [UserController::class, 'generateEmployeeId']
);

/*
|--------------------------------------------------------------------------
| Placeholder Module Routes
|--------------------------------------------------------------------------
|
| These modules are currently under development.
| They display placeholder pages until implementation.
|
*/

// Student Management
$router->get(
    '/students',
    [StudentController::class, 'index']
);

// Display Create Student Form
$router->get(
    '/students/create',
    [StudentController::class, 'create']
);

// Store Student
$router->post(
    '/students/store',
    [StudentController::class, 'store']
);

// Display Edit Student Form
$router->get(
    '/students/edit',
    [StudentController::class, 'edit']
);

// Update Student
$router->post(
    '/students/update',
    [StudentController::class, 'update']
);

// View Student Details
$router->get(
    '/students/view',
    [StudentController::class, 'view']
);

// Delete Student
$router->post(
    '/students/delete',
    [StudentController::class, 'delete']
);

// Symposium Management
$router->get(
    '/symposiums',
    [SymposiumController::class, 'index']
);

// Display Create Symposium Form
$router->get(
    '/symposiums/create',
    [SymposiumController::class, 'create']
);

// Store Symposium
$router->post(
    '/symposiums/store',
    [SymposiumController::class, 'store']
);

// Display Edit Symposium Form
$router->get(
    '/symposiums/edit',
    [SymposiumController::class, 'edit']
);

// Update Symposium
$router->post(
    '/symposiums/update',
    [SymposiumController::class, 'update']
);

// View Symposium Details
$router->get(
    '/symposiums/view',
    [SymposiumController::class, 'view']
);

// Delete Symposium
$router->post(
    '/symposiums/delete',
    [SymposiumController::class, 'delete']
);

// Change Symposium Status
$router->get(
    '/symposiums/generate-pdf',
    [SymposiumController::class, 'generatePdf']
);

$router->post(
    '/symposiums/submit',
    [SymposiumController::class, 'submit']
);

$router->post(
    '/symposiums/approve',
    [SymposiumController::class, 'approve']
);

$router->post(
    '/symposiums/reject',
    [SymposiumController::class, 'reject']
);

/*
|--------------------------------------------------------------------------
| Competition Management Routes — REMOVED
|--------------------------------------------------------------------------
|
| The Competition module has been superseded by the Event-based
| registration architecture. All routes previously handled by
| CompetitionController are now managed through the Symposium Events
| and Registration Coordinator modules.
|
| NOTE: CompetitionModel and CompetitionController PHP class files are
| retained internally for Attendance and Evaluation module compatibility
| until those modules are rebuilt on the Event-based architecture.
|
*/

/*
|--------------------------------------------------------------------------
| Coordinator Portal — Event-Based Registration Management
|--------------------------------------------------------------------------
|
| These routes use the new RegistrationCoordinatorController which is
| aligned with the Symposium Event architecture.
|
*/

// Registration Dashboard — Symposium list with stats
$router->get(
    '/coordinator/registrations',
    [RegistrationCoordinatorController::class, 'dashboard']
);

// Event Participants List
$router->get(
    '/coordinator/registrations/event',
    [RegistrationCoordinatorController::class, 'eventParticipants']
);

// Export PDF for Event
$router->get(
    '/coordinator/registrations/event/export',
    [RegistrationCoordinatorController::class, 'exportRegistrationPdf']
);

// Cancel Application (Staff-initiated)
$router->post(
    '/coordinator/registrations/cancel',
    [RegistrationCoordinatorController::class, 'cancel']
);

// Symposium Registration Summary
$router->get(
    '/coordinator/registrations/summary',
    [RegistrationCoordinatorController::class, 'summary']
);

// Students Not Registered
$router->get(
    '/coordinator/registrations/not-registered',
    [RegistrationCoordinatorController::class, 'notRegistered']
);

// Department-wise Report
$router->get(
    '/coordinator/registrations/report/department',
    [RegistrationCoordinatorController::class, 'departmentReport']
);

// Team Report
$router->get(
    '/coordinator/registrations/report/teams',
    [RegistrationCoordinatorController::class, 'teamReport']
);

/*
|--------------------------------------------------------------------------
| Certificate Generation Module Routes
|--------------------------------------------------------------------------
*/

// Template Library
$router->get('/certificates', [CertificateTemplateController::class, 'index']);

// Template Upload
$router->get('/certificates/create',  [CertificateTemplateController::class, 'create']);
$router->post('/certificates/store',  [CertificateTemplateController::class, 'store']);

// Certificate Designer
$router->get('/certificates/designer',              [CertificateTemplateController::class, 'designer']);
$router->post('/certificates/designer/save',        [CertificateTemplateController::class, 'saveDesign']);
$router->post('/certificates/designer/preview',     [CertificateTemplateController::class, 'designerPreview']);

// Preview
$router->get('/certificates/preview',     [CertificateTemplateController::class, 'preview']);
$router->get('/certificates/preview/pdf', [CertificateTemplateController::class, 'previewPdf']);

// Generation
$router->get('/certificates/generate',  [CertificateGenerationController::class, 'generate']);
$router->post('/certificates/generate', [CertificateGenerationController::class, 'runGeneration']);

// Background Document Queue (Async Generation & Polling)
$router->post('/certificates/generate-async', [CertificateGenerationController::class, 'queueGeneration']);
$router->get('/certificates/job-status',       [CertificateGenerationController::class, 'jobStatus']);
$router->get('/certificates/job-progress',     [CertificateGenerationController::class, 'jobProgress']);

$router->get('/certificates/symposium-batch',  [CertificateGenerationController::class, 'symposiumBatch']);
$router->post('/certificates/symposium-batch', [CertificateGenerationController::class, 'runSymposiumBatch']);

$router->get('/certificates/report',    [CertificateGenerationController::class, 'generationReport']);

// Generated Certificates List
$router->get('/certificates/generated',      [CertificateGenerationController::class, 'generated']);

// Download
$router->get('/certificates/download',     [CertificateDownloadController::class, 'download']);
$router->get('/certificates/view',         [CertificateDownloadController::class, 'view']);
$router->get('/certificates/download/zip', [CertificateDownloadController::class, 'downloadZip']);
$router->get('/certificates/download/event-zip', [CertificateDownloadController::class, 'downloadEventZip']);
$router->get('/certificates/download/symposium-package', [CertificateDownloadController::class, 'downloadSymposiumPackage']);
$router->get('/certificates/download/symposium-pdf', [CertificateDownloadController::class, 'downloadSymposiumPdf']);

// Template Actions
$router->post('/certificates/template/activate',   [CertificateTemplateController::class, 'activateTemplate']);
$router->post('/certificates/template/deactivate', [CertificateTemplateController::class, 'deactivateTemplate']);
$router->post('/certificates/template/duplicate',  [CertificateTemplateController::class, 'duplicateTemplate']);
$router->post('/certificates/template/archive',    [CertificateTemplateController::class, 'archiveTemplate']);
$router->post('/certificates/template/unarchive',  [CertificateTemplateController::class, 'unarchiveTemplate']);
$router->post('/certificates/template/delete',     [CertificateTemplateController::class, 'deleteTemplate']);
$router->get('/certificates/template/edit',        [CertificateTemplateController::class, 'editTemplate']);
$router->post('/certificates/template/edit',       [CertificateTemplateController::class, 'updateTemplate']);

// AJAX Endpoints
$router->get('/certificates/ajax/events',    [CertificateGenerationController::class, 'ajaxGetEvents']);
$router->get('/certificates/ajax/results',   [CertificateGenerationController::class, 'ajaxGetResults']);
$router->get('/certificates/ajax/preflight', [CertificateGenerationController::class, 'ajaxPreflight']);

// Public Certificate Verification (no authentication required)
// URL format: /certificates/verify?token=<64-char hex>
$router->get('/certificates/verify', [CertificateVerificationController::class, 'verify']);

// Staff — Type-specific ZIP downloads (Admin or Staff Coordinator — own symposium)
$router->get('/certificates/download/winners-zip',      [CertificateDownloadController::class, 'downloadWinnersZip']);
$router->get('/certificates/download/participants-zip', [CertificateDownloadController::class, 'downloadParticipantsZip']);

// Event template assignment (Admin or Staff Coordinator — own symposium)
$router->post('/certificates/template/set-event', [CertificateTemplateController::class, 'setEventTemplate']);

// Student Certificate Portal (authenticated students only)
$router->get('/student/certificates',          [StudentCertificateController::class, 'index']);
$router->get('/student/certificates/view',     [StudentCertificateController::class, 'view']);
$router->get('/student/certificates/download', [StudentCertificateController::class, 'download']);

// Reports
$router->get(
    '/reports',
    [ModuleController::class, 'reports']
);

// Evaluation & Results
$router->get(
    '/evaluation/results',
    [ResultViewerController::class, 'index']
);
$router->get(
    '/evaluation/results/event',
    [ResultViewerController::class, 'viewEvent']
);

// System Settings route has been moved to SettingsController

// Notifications
$router->get(
    '/notifications',
    [ModuleController::class, 'notifications']
);

// Audit Logs
$router->get(
    '/audit-logs',
    [ModuleController::class, 'auditLogs']
);

// Attendance route is handled by AttendanceController below.

/*
|--------------------------------------------------------------------------
| Student Authentication Routes
|--------------------------------------------------------------------------
|
| These routes are accessible without student authentication.
|
*/

// Student Login Page
$router->get(
    '/student/login',
    [StudentAuthController::class, 'showLogin']
);

// Student Login Form Submission
$router->post(
    '/student/login',
    [StudentAuthController::class, 'login']
);

// External Student Registration Page
$router->get(
    '/student/register',
    [StudentAuthController::class, 'showRegister']
);

// External Student Registration Submission
$router->post(
    '/student/register',
    [StudentAuthController::class, 'register']
);

// College Search API
$router->get(
    '/api/colleges/search',
    [StudentAuthController::class, 'ajaxSearchColleges']
);

// Student Logout
$router->get(
    '/student/logout',
    [StudentAuthController::class, 'logout']
);

/*
|--------------------------------------------------------------------------
| Student Portal Routes
|--------------------------------------------------------------------------
|
| Protected by StudentAuthMiddleware (applied inside each controller).
|
*/

// Student Dashboard
$router->get(
    '/student/dashboard',
    [StudentPortalController::class, 'dashboard']
);

// Symposiums List
$router->get(
    '/student/symposiums',
    [StudentPortalController::class, 'symposiums']
);

$router->get(
    '/student/symposiums/generate-pdf',
    [StudentPortalController::class, 'downloadPdf']
);

// Events List for a Symposium (Event-Based Architecture)
$router->get(
    '/student/symposiums/events',
    [StudentPortalController::class, 'events']
);

// Event Registration Form (GET)
$router->get(
    '/student/events/register',
    [StudentRegistrationController::class, 'showRegistrationForm']
);

// Submit Event Registration (POST)
$router->post(
    '/student/events/register',
    [StudentRegistrationController::class, 'register']
);

// Student Payment Checkout
$router->get(
    '/student/payment/checkout',
    [PaymentController::class, 'showCheckout']
);

// Student Payment Process
$router->post(
    '/student/payment/process',
    [PaymentController::class, 'processPayment']
);

// Payment Webhook
$router->post(
    '/api/payment/webhook',
    [PaymentController::class, 'webhook']
);

// My Registrations (History)
$router->get(
    '/student/my-registrations',
    [StudentPortalController::class, 'myRegistrations']
);

// Withdraw Application (POST)
$router->post(
    '/student/applications/withdraw',
    [StudentRegistrationController::class, 'withdraw']
);

// Manage Team Members (GET)
$router->get(
    '/student/teams/view',
    [StudentPortalController::class, 'team']
);

// Add Team Member (POST)
$router->post(
    '/student/teams/members',
    [StudentRegistrationController::class, 'addTeamMember']
);

// Remove Team Member (POST)
$router->post(
    '/student/teams/members/remove',
    [StudentRegistrationController::class, 'removeTeamMember']
);

// Student Notifications (GET)
$router->get(
    '/student/notifications',
    [StudentPortalController::class, 'notifications']
);

// Student Notifications (POST)
$router->post(
    '/student/notifications/clear',
    [StudentPortalController::class, 'clearNotifications']
);

// Student Chatbot API (POST)
$router->post(
    '/student/chatbot',
    [ChatbotController::class, 'handle']
);

// Student Chatbot History (GET)
$router->get(
    '/student/chatbot/history',
    [ChatbotController::class, 'getHistory']
);

// Student Feedback
$router->get(
    '/student/feedback',
    [StudentFeedbackController::class, 'index']
);
$router->get(
    '/student/feedback/event',
    [StudentFeedbackController::class, 'form']
);
$router->post(
    '/student/feedback/submit',
    [StudentFeedbackController::class, 'submit']
);

// Schedule Image Export (Staff Coordinator WhatsApp Share)
$router->get(
    '/schedule/image',
    [ScheduleImageController::class, 'generate']
);

/*
|--------------------------------------------------------------------------
| Attendance Routes — Event-Based (Symposium Event) Workflow
|--------------------------------------------------------------------------
|
| Offline-First Attendance Module — fully migrated to symposium_event_id.
| FIC (Faculty In-Charge) authorization is enforced in AttendanceService.
| The /attendance/sync route is a JSON API for the offline sync engine.
|
*/

// Attendance — Overview (FIC event list / Admin overview)
$router->get(
    '/attendance',
    [AttendanceController::class, 'index']
);

// Attendance — Mark Sheet for a symposium event (?id=symposium_event_id)
$router->get(
    '/attendance/event',
    [AttendanceController::class, 'markSheet']
);

// Attendance — Open Session
$router->post(
    '/attendance/open',
    [AttendanceController::class, 'openSession']
);

// Attendance — Close Session
$router->post(
    '/attendance/close',
    [AttendanceController::class, 'closeSession']
);

// Attendance — Mark Single Participant (AJAX + form fallback)
$router->post(
    '/attendance/mark',
    [AttendanceController::class, 'mark']
);

// Attendance — Bulk Mark All Participants
$router->post(
    '/attendance/bulk-mark',
    [AttendanceController::class, 'bulkMark']
);

// Attendance — Offline Sync (JSON API)
$router->post(
    '/attendance/sync',
    [AttendanceController::class, 'sync']
);

// Attendance — Session History
$router->get(
    '/attendance/history',
    [AttendanceController::class, 'history']
);

// Attendance — Report
$router->get(
    '/attendance/report',
    [AttendanceController::class, 'report']
);

// Attendance — PDF Export
$router->get(
    '/attendance/export',
    [AttendanceController::class, 'exportPdf']
);

// Attendance — Judge Mark Sheet PDF
$router->get(
    '/attendance/judge-mark-sheet',
    [AttendanceController::class, 'judgeMarkSheet']
);

// Feedback — HOD / Staff Coordinator
$router->get('/feedback', [FeedbackController::class, 'index']);
$router->get('/feedback/event', [FeedbackController::class, 'event']);

// Feedback — FIC (event-scoped)
$router->get('/feedback/fic/event', [FeedbackController::class, 'ficEvent']);

// Feedback — Judge (event-scoped)
$router->get('/feedback/judge/event', [FeedbackController::class, 'judgeEvent']);


/*
|--------------------------------------------------------------------------
| Judge Routes
|--------------------------------------------------------------------------
*/

// Judge Portal
$router->get('/judge/dashboard', [JudgeController::class, 'dashboard']);
$router->get('/judge/competitions', [JudgeController::class, 'competitions']);
$router->get('/judge/evaluate', [JudgeController::class, 'evaluate']);
$router->post('/judge/evaluate/bulk-submit', [JudgeController::class, 'bulkSubmitMarks']);

// Faculty In-Charge Portal
$router->get('/my/assigned-events', [FacultyInChargeController::class, 'dashboard']);
$router->get('/my/assigned-events/event', [FacultyInChargeController::class, 'eventDetail']);
$router->get('/my/assigned-events/registrations', [FacultyInChargeController::class, 'participants']);
$router->get('/my/assigned-events/registrations/export', [FacultyInChargeController::class, 'exportRegistrationPdf']);
$router->post('/my/assigned-events/assign-judge', [FacultyInChargeController::class, 'ajaxAssignJudge']);
$router->post('/my/assigned-events/remove-judge', [FacultyInChargeController::class, 'ajaxRemoveJudge']);
$router->post('/my/assigned-events/prelims', [FacultyInChargeController::class, 'savePrelims']);
$router->post('/my/assigned-events/publish-results', [FacultyInChargeController::class, 'publishResults']);
// Tie Resolution is now automatic

/*
|--------------------------------------------------------------------------
| Prelims Quiz & Dual-Round Examination Routes (FIC / Staff / Reports)
|--------------------------------------------------------------------------
*/
$router->get('/fic/prelims/manage',              [PrelimsFicController::class, 'manage']);
$router->post('/fic/prelims/save-config',        [PrelimsFicController::class, 'saveConfig']);
$router->post('/fic/prelims/upload-pdf',         [PrelimsFicController::class, 'uploadPdf']);
$router->get('/fic/prelims/source-pdf',          [PrelimsFicController::class, 'streamSourcePdf']);
$router->post('/fic/prelims/submit-ocr-text',    [PrelimsFicController::class, 'submitOcrText']);
$router->post('/fic/prelims/upload-answer-key',  [PrelimsFicController::class, 'uploadAnswerKey']);
$router->post('/fic/prelims/source/delete',      [PrelimsFicController::class, 'deleteSource']);
$router->get('/fic/prelims/review',              [PrelimsFicController::class, 'review']);
$router->post('/fic/prelims/question/save',      [PrelimsFicController::class, 'saveQuestion']);
$router->post('/fic/prelims/question/delete',    [PrelimsFicController::class, 'deleteQuestion']);
$router->post('/fic/prelims/source/approve',     [PrelimsFicController::class, 'approveSource']);
$router->post('/fic/prelims/status',             [PrelimsFicController::class, 'changeStatus']);
$router->get('/fic/prelims/monitor',             [PrelimsFicController::class, 'monitor']);
$router->get('/fic/prelims/monitor-data',        [PrelimsFicController::class, 'monitorData']);
$router->post('/fic/prelims/force-submit',       [PrelimsFicController::class, 'forceSubmit']);
$router->post('/fic/prelims/grant-reentry',      [PrelimsFicController::class, 'grantReentry']);
$router->post('/fic/prelims/reopen-attempt',     [PrelimsFicController::class, 'reopenAttempt']);
$router->post('/fic/prelims/reset-attempt',      [PrelimsFicController::class, 'resetAttempt']);
$router->get('/fic/prelims/results',             [PrelimsFicController::class, 'results']);
$router->post('/fic/prelims/finalize-selection', [PrelimsFicController::class, 'finalizeSelection']);
$router->post('/fic/prelims/dispose',            [PrelimsFicController::class, 'dispose']);

// Student Prelims Quiz Portal & Ultra-Low-Bandwidth Exam Runner
$router->get('/student/prelims',                 [StudentPrelimsQuizController::class, 'index']);
$router->get('/student/prelims/lobby',           [StudentPrelimsQuizController::class, 'lobby']);
$router->post('/student/prelims/start',          [StudentPrelimsQuizController::class, 'start']);
$router->get('/student/prelims/exam',            [StudentPrelimsQuizController::class, 'exam']);
$router->post('/student/prelims/sync',           [StudentPrelimsQuizController::class, 'sync']);
$router->post('/student/prelims/submit',         [StudentPrelimsQuizController::class, 'submit']);
$router->post('/student/prelims/reenter',        [StudentPrelimsQuizController::class, 'reenter']);
$router->get('/student/prelims/submitted',       [StudentPrelimsQuizController::class, 'submitted']);

// Prelims & Main Event Official Reports
$router->get('/reports/prelims',                 [PrelimsReportController::class, 'prelims']);
$router->get('/reports/prelims/pdf',             [PrelimsReportController::class, 'prelimsPdf']);
$router->get('/reports/prelims/csv',             [PrelimsReportController::class, 'prelimsCsv']);
$router->get('/reports/main-event',              [PrelimsReportController::class, 'mainEvent']);
$router->get('/reports/main-event/pdf',          [PrelimsReportController::class, 'mainEventPdf']);

/*
|--------------------------------------------------------------------------
| Sync Center Route
|--------------------------------------------------------------------------
*/

$router->get(
    '/sync-center',
    [SyncCenterController::class, 'index']
);

/*
|--------------------------------------------------------------------------
| Symposium Event Status Auto-Sync (Lifecycle Engine)
|--------------------------------------------------------------------------
|
| Runs a lightweight time-based status sync on every page load (at most
| once per minute, throttled via session timestamp).
|
| This promotes events through their lifecycle automatically:
|   Published → Registration Open → Registration Closed → Running → Completed
|
| Draft and Cancelled events are never touched by this sync.
| Master Events (admin data) are completely unaffected.
|
*/

if (isset($_SESSION['user'])) {
    try {
        $eventSyncSvc = new \App\Services\SymposiumEventStatusSyncService();
        $eventSyncSvc->runIfDue();
        unset($eventSyncSvc);
    } catch (\Throwable $syncErr) {
        // Non-critical — never block a page load due to sync failure
        error_log('[EventStatusSync] ' . $syncErr->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| Dispatch Request
|--------------------------------------------------------------------------
|
| Match the incoming request with the registered routes
| and execute the appropriate controller action.
|
*/

$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $_SERVER['REQUEST_URI']
);