<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Models\ApplicationModel;
use App\Models\SymposiumEventModel;
use App\Models\SymposiumModel;
use App\Models\TeamMemberModel;
use App\Models\TeamModel;
use App\Services\RegistrationService;

/**
 * Controller for Registration Coordinators
 */
class RegistrationCoordinatorController extends BaseController
{
    /** @var SymposiumModel */
    private SymposiumModel $sympModel;
    
    /** @var SymposiumEventModel */
    private SymposiumEventModel $eventModel;
    
    /** @var ApplicationModel */
    private ApplicationModel $appModel;
    
    /** @var RegistrationService */
    private RegistrationService $regService;

    /**
     * Constructor sets up middleware and required roles
     */
    public function __construct()
    {
        AuthMiddleware::handle();
        $role = $_SESSION['user']['role'] ?? '';
        $allowedRoles = ['Admin', 'Staff', 'Staff Coordinator', 'Student Coordinator'];
        
        if (!in_array($role, $allowedRoles, true)) {
            Session::flash('error', 'Access denied.');
            $this->redirect('/dashboard');
        }

        $this->sympModel = new SymposiumModel();
        $this->eventModel = new SymposiumEventModel();
        $this->appModel = new ApplicationModel();
        $this->regService = new RegistrationService();
    }

    /**
     * Helper to get current staff ID
     * @return int
     */
    private function getCurrentStaffId(): int
    {
        return (int)($_SESSION['user']['user_id'] ?? 0);
    }

    /**
     * View dashboard of registrations per symposium
     * GET /coordinator/registrations
     */
    public function dashboard(): void
    {
        $allSymposiums = $this->sympModel->getAll();
        $validStatuses = ['Approved', 'Scheduling Complete', 'Registration Open', 'Registration Closed', 'Completed'];
        
        $symposiums = array_filter($allSymposiums, function($s) use ($validStatuses) {
            return in_array($s['status'], $validStatuses, true);
        });

        $stats = [];
        foreach ($symposiums as $symp) {
            $sympId = (int)$symp['symposium_id'];
            $stats[$sympId] = $this->appModel->getStatsForSymposium($sympId);
            $events = $this->eventModel->getBySymposium($sympId);
            $stats[$sympId]['event_count'] = count($events);
        }

        $this->render('coordinator.registrations.index', [
            'pageTitle'  => 'Registrations Dashboard',
            'symposiums' => $symposiums,
            'stats'      => $stats
        ], 'dashboard');
    }

    /**
     * View participants for a specific event
     * GET /coordinator/registrations/event?event_id={id}&status=&search=&department_id=&academic_year=&application_type=
     */
    public function eventParticipants(): void
    {
        $eventId = (int)($_GET['event_id'] ?? 0);
        
        $filters = [
            'application_status' => $_GET['status'] ?? 'All',
            'department_id'      => $_GET['department_id'] ?? '',
            'academic_year'      => $_GET['academic_year'] ?? '',
            'application_type'   => $_GET['application_type'] ?? 'All'
        ];
        $search = $_GET['search'] ?? '';

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            Session::flash('error', 'Event not found.');
            $this->redirect('/coordinator/registrations');
        }

        $symposium = $this->sympModel->findById((int)$event['symposium_id']);
        
        $applications = $this->appModel->getForEventReport($eventId, $filters);
        
        $uniqueApps = [];
        foreach ($applications as $app) {
            if (!isset($uniqueApps[$app['application_id']])) {
                $uniqueApps[$app['application_id']] = $app;
            }
        }
        $applications = array_values($uniqueApps);
        
        $resolver = new \App\Services\TeamResolverService();
        $resolvedParticipants = $resolver->resolveParticipantsForApplications($applications);
        
        if ($search !== '') {
            $searchLower = strtolower($search);
            // We must filter the resolvedParticipants array based on search, and then reconstruct $applications
            $filteredResolved = [];
            $filteredApps = [];
            foreach ($applications as $app) {
                $appId = (int)$app['application_id'];
                $resolved = $resolvedParticipants[$appId] ?? null;
                if (!$resolved) continue;

                $match = false;
                if ($resolved['participant_type'] === 'individual') {
                    $p = $resolved['participant'];
                    if (str_contains(strtolower($p['name'] ?? ''), $searchLower) ||
                        str_contains(strtolower($p['register_number'] ?? ''), $searchLower)) {
                        $match = true;
                    }
                } else {
                    foreach ($resolved['members'] as $m) {
                        if (str_contains(strtolower($m['name'] ?? ''), $searchLower) ||
                            str_contains(strtolower($m['register_number'] ?? ''), $searchLower)) {
                            $match = true;
                            break;
                        }
                    }
                }

                if ($match) {
                    $filteredApps[] = $app;
                    $filteredResolved[$appId] = $resolved;
                }
            }
            $applications = $filteredApps;
            $resolvedParticipants = $filteredResolved;
        }

        $stats = $this->appModel->getStatsForEvent($eventId);
        
        $deptModel = new \App\Models\DepartmentModel();
        $departments = array_filter($deptModel->getAll(), fn($d) => $d['is_active']);
        $academicYears = $this->appModel->getDistinctAcademicYearsForEvent($eventId);

        $this->render('coordinator.registrations.event_participants', [
            'pageTitle'            => 'Participants: ' . $event['event_name'],
            'event'                => $event,
            'symposium'            => $symposium,
            'applications'         => $applications,
            'resolvedParticipants' => $resolvedParticipants,
            'stats'                => $stats,
            'filters'              => $filters,
            'search'               => $search,
            'departments'          => $departments,
            'academicYears'        => $academicYears
        ], 'dashboard');
    }

    /**
     * Export Registration Report PDF
     * GET /coordinator/registrations/event/export?event_id={id}&status=&department_id=&academic_year=&application_type=
     */
    public function exportRegistrationPdf(): void
    {
        $eventId = (int)($_GET['event_id'] ?? 0);
        $filters = [
            'application_status' => $_GET['status'] ?? 'All',
            'department_id'      => $_GET['department_id'] ?? '',
            'academic_year'      => $_GET['academic_year'] ?? '',
            'application_type'   => $_GET['application_type'] ?? 'All'
        ];

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            Session::flash('error', 'Event not found.');
            $this->redirect('/coordinator/registrations');
        }

        $symposium = $this->sympModel->findById((int)$event['symposium_id']);
        
        $applications = $this->appModel->getForEventReport($eventId, $filters);
        
        $stats = $this->appModel->getStatsForEvent($eventId);
        
        if (!empty($filters['department_id'])) {
            $deptModel = new \App\Models\DepartmentModel();
            $dept = $deptModel->findById((int)$filters['department_id']);
            $filters['department_name'] = $dept['department_name'] ?? 'Unknown';
        }

        $reportService = new \App\Services\ReportService();
        $reportService->generateRegistrationReportPdf($event, $symposium, $filters, $applications, $stats);
    }

    /**
     * Cancel a registration (Staff Action)
     * POST /coordinator/registrations/cancel
     */
    public function cancel(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/coordinator/registrations');
        }

        $applicationId = (int)($_POST['application_id'] ?? 0);
        $remarks = trim($_POST['remarks'] ?? '');
        $staffId = $this->getCurrentStaffId();

        if (!$applicationId) {
            Session::flash('error', 'Invalid application.');
            $this->redirect('/coordinator/registrations');
        }

        $result = $this->regService->cancel($applicationId, $staffId, $remarks);

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        // redirect back
        $referer = $_SERVER['HTTP_REFERER'] ?? '/coordinator/registrations';
        $this->redirect($referer);
    }

    /**
     * View summary for a symposium
     * GET /coordinator/registrations/summary?symposium_id={id}
     */
    public function summary(): void
    {
        $symposiumId = (int)($_GET['symposium_id'] ?? 0);
        $symposium = $this->sympModel->findById($symposiumId);

        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/coordinator/registrations');
        }

        $eventWiseSummary = $this->appModel->getEventWiseSummary($symposiumId);
        $stats = $this->appModel->getStatsForSymposium($symposiumId);

        $this->render('coordinator.registrations.summary', [
            'pageTitle'        => 'Registration Summary: ' . $symposium['title'],
            'symposium'        => $symposium,
            'eventWiseSummary' => $eventWiseSummary,
            'stats'            => $stats
        ], 'dashboard');
    }

    /**
     * View unregistered students for a symposium
     * GET /coordinator/registrations/not-registered?symposium_id={id}
     */
    public function notRegistered(): void
    {
        $symposiumId = (int)($_GET['symposium_id'] ?? 0);
        $symposium = $this->sympModel->findById($symposiumId);

        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/coordinator/registrations');
        }
        $filters = [
            'department_id' => $_GET['department_id'] ?? 'All',
            'academic_year' => $_GET['academic_year'] ?? 'All'
        ];

        $unregisteredStudents = $this->appModel->getUnregisteredStudents($symposiumId, $filters);
        
        // Fetch only UG departments (BCA and BSc CS) for the dropdown
        $db = \App\Database\Database::getConnection();
        $deptSql = "SELECT * FROM departments WHERE department_id IN (1, 2) AND is_active = 1 ORDER BY department_name ASC";
        $departments = $db->query($deptSql)->fetchAll(\PDO::FETCH_ASSOC);

        // Base conditions
        $baseWhere = "s.department_id IN (1, 2) AND s.account_status = 'Active'";
        $params = [];
        
        if (!empty($filters['department_id']) && $filters['department_id'] !== 'All') {
            $baseWhere .= " AND s.department_id = :dept_id";
            $params['dept_id'] = $filters['department_id'];
        }
        
        if (!empty($filters['academic_year']) && $filters['academic_year'] !== 'All') {
            $baseWhere .= " AND s.academic_year = :year";
            $params['year'] = $filters['academic_year'];
        }

        // Compute total eligible students (filtered)
        $eligibleSql = "SELECT COUNT(*) FROM students s WHERE {$baseWhere}";
        $stmt = $db->prepare($eligibleSql);
        $stmt->execute($params);
        $totalEligible = (int) $stmt->fetchColumn();

        // Calculate total registered (filtered)
        $regSql = "
            SELECT COUNT(DISTINCT s.student_id)
            FROM students s
            WHERE {$baseWhere}
            AND (
                s.student_id IN (
                    SELECT a.student_id FROM applications a
                    INNER JOIN symposium_events se ON a.symposium_event_id = se.symposium_event_id
                    WHERE se.symposium_id = :sym_id2 AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
                )
                OR s.student_id IN (
                    SELECT tm.student_id FROM team_members tm
                    INNER JOIN teams t ON tm.team_id = t.team_id
                    INNER JOIN applications a ON t.application_id = a.application_id
                    INNER JOIN symposium_events se ON a.symposium_event_id = se.symposium_event_id
                    WHERE se.symposium_id = :sym_id3 AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
                )
            )
        ";
        $regParams = array_merge($params, [
            'sym_id2' => $symposiumId,
            'sym_id3' => $symposiumId
        ]);
        $stmt = $db->prepare($regSql);
        $stmt->execute($regParams);
        $totalRegistered = (int) $stmt->fetchColumn();

        $stats = [
            'total_eligible'   => $totalEligible,
            'total_registered' => $totalRegistered,
            'total_unregistered' => count($unregisteredStudents) // Based on the current filter! But actually the UI might want total unregistered. Wait, the report is "Not Registered: count" so we just show the count.
        ];

        if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
            $pdfService = new \App\Services\PdfDocumentService();
            $pdfService->generateNotRegisteredReport($symposium, $unregisteredStudents, $filters, $stats, $departments);
            return;
        }

        $this->render('coordinator.registrations.not_registered', [
            'pageTitle'            => 'Unregistered Students: ' . $symposium['title'],
            'symposium'            => $symposium,
            'unregisteredStudents' => $unregisteredStudents,
            'filters'              => $filters,
            'departments'          => $departments,
            'stats'                => $stats,
            'collegeName'          => (new \App\Models\SystemSettingModel())->getValue('COLLEGE_NAME', 'Government Arts and Science College'),
            'collegeLogo'          => (new \App\Models\SystemSettingModel())->getValue('COLLEGE_LOGO', '')
        ], 'dashboard');
    }

    /**
     * View department wise report
     * GET /coordinator/registrations/report/department?symposium_id={id}
     */
    public function departmentReport(): void
    {
        $symposiumId = (int)($_GET['symposium_id'] ?? 0);
        $symposium = $this->sympModel->findById($symposiumId);

        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/coordinator/registrations');
        }

        $filters = [
            'department_id'      => $_GET['department_id'] ?? '',
            'academic_year'      => $_GET['academic_year'] ?? '',
            'gender'             => $_GET['gender'] ?? 'All',
            'application_status' => $_GET['application_status'] ?? 'All',
            'application_type'   => $_GET['application_type'] ?? 'All'
        ];

        $reportData = $this->appModel->getComprehensiveDepartmentReport($symposiumId, $filters);

        // Fetch departments for filter dropdown
        $deptModel = new \App\Models\DepartmentModel();
        $departments = $deptModel->getAll();

        if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
            $pdfService = new \App\Services\PdfDocumentService();
            $pdfService->generateDepartmentRegistrationReport($symposium, $reportData, $filters);
            return;
        }

        $this->render('coordinator.registrations.department_report', [
            'pageTitle' => 'Department Registration Report: ' . $symposium['title'],
            'symposium' => $symposium,
            'reportData' => $reportData,
            'filters'   => $filters,
            'departments' => $departments,
            'collegeName' => (new \App\Models\SystemSettingModel())->getValue('COLLEGE_NAME', 'Government Arts and Science College'),
            'collegeLogo' => (new \App\Models\SystemSettingModel())->getValue('COLLEGE_LOGO', '')
        ], 'dashboard');
    }

    /**
     * View team report
     * GET /coordinator/registrations/report/teams?symposium_id={id}
     */
    public function teamReport(): void
    {
        $symposiumId = (int)($_GET['symposium_id'] ?? 0);
        $symposium = $this->sympModel->findById($symposiumId);

        if (!$symposium) {
            Session::flash('error', 'Symposium not found.');
            $this->redirect('/coordinator/registrations');
        }

        $teamsRaw = $this->appModel->getTeamReport($symposiumId);
        
        $teams = [];
        foreach ($teamsRaw as $row) {
            $tid = $row['team_id'];
            if (!isset($teams[$tid])) {
                $teams[$tid] = [
                    'team_id'        => $tid,
                    'manager_student_id' => $row['manager_student_id'],
                    'application_no' => $row['application_no'],
                    'event_name'     => $row['event_name'],
                    'event_code'     => $row['event_code'] ?? '',
                    'members'        => [],
                    'manager_name'   => ''
                ];
            }
            if (!empty($row['member_name'])) {
                $teams[$tid]['members'][] = [
                    'member_name'   => $row['member_name'],
                    'member_reg_no' => $row['member_reg_no'] ?? '',
                    'member_dept'   => $row['member_dept'] ?? '',
                    'member_year'   => $row['member_year'] ?? '',
                    'joined_at'     => $row['joined_at'] ?? ''
                ];
                if ($row['student_id'] == $row['manager_student_id']) {
                    $teams[$tid]['manager_name'] = $row['member_name'];
                }
            }
        }

        $this->render('coordinator.registrations.team_report', [
            'pageTitle' => 'Team Report: ' . $symposium['title'],
            'symposium' => $symposium,
            'teams'     => $teams
        ], 'dashboard');
    }
}
