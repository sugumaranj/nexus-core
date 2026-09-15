<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : DashboardController.php
 * Location    : app/Controllers/
 * Description : Handles all authenticated dashboard pages.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Redirect authenticated users to their role-specific dashboard.
 * • Render Administrator dashboard with system-wide statistics.
 * • Render Principal dashboard with approval overview.
 * • Render HOD dashboard scoped to their department.
 * • Render Staff Coordinator dashboard for symposium/competition management.
 * • Render Staff dashboard focused on student management.
 * • Render Student Coordinator dashboard for assigned competitions.
 *
 * NOTE
 * -------------------------------------------------------------------------
 * This controller extends BaseController to reuse:
 * • View rendering
 * • Redirect helper
 * • Authentication helper
 * • Flash messages
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Services\DashboardService;

final class DashboardController extends BaseController
{
    /**
     * Dashboard statistics service.
     *
     * @var DashboardService
     */
    private DashboardService $dashboardService;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->dashboardService = new DashboardService();
    }

    /**
     * ---------------------------------------------------------------------
     * Redirect user to the appropriate dashboard.
     *
     * Reads the authenticated user's role from the session and
     * redirects to the corresponding dashboard URL.
     *
     * @return never
     * ---------------------------------------------------------------------
     */
    public function index(): never
    {
        AuthMiddleware::handle();

        $user = Session::get('user', []);

        $target = match ($user['role'] ?? '') {

            'Admin'               => '/dashboard/admin',

            'Principal'           => '/dashboard/principal',

            'HOD'                 => '/dashboard/hod',

            'Staff Coordinator'   => '/dashboard/staff-coordinator',

            'Staff'               => '/dashboard/staff',

            'Student Coordinator' => '/dashboard/student-coordinator',

            default               => '/dashboard/admin',

        };

        $this->redirect($target);
    }

    /**
     * ---------------------------------------------------------------------
     * Administrator Dashboard
     *
     * Full system access. Displays system-wide statistics.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function admin(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Admin');

        $stats = $this->dashboardService->getAdminStats();

        $this->render(
            'dashboard.admin',
            [
                'pageTitle' => 'Administrator Dashboard',
                'user'      => Session::get('user'),
                'stats'     => $stats,
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Principal Dashboard
     *
     * Read-only administrative overview.
     * Shows approval queue, symposium overview, and statistics.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function principal(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Principal');

        $stats                    = $this->dashboardService->getPrincipalStats();
        $pendingSymposiums        = $this->dashboardService->getPendingPrincipalSymposiums(10);
        $recentlyApprovedSymposiums = $this->dashboardService->getRecentlyApprovedSymposiums(5);

        $this->render(
            'dashboard.principal',
            [
                'pageTitle'                  => 'Principal Dashboard',
                'user'                       => Session::get('user'),
                'stats'                      => $stats,
                'pendingSymposiums'          => $pendingSymposiums,
                'recentlyApprovedSymposiums' => $recentlyApprovedSymposiums,
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Head of Department (HOD) Dashboard
     *
     * Department-scoped dashboard. All statistics are filtered to
     * the HOD's own department using their session department_id.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function hod(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('HOD');

        $user         = Session::get('user', []);
        $departmentId = (int) ($user['department_id'] ?? 0);
        $userId       = (int) ($user['user_id'] ?? 0);

        $ficStats = $this->dashboardService->getFicStats($userId);
        $stats    = array_merge($this->dashboardService->getHodStats($departmentId), $ficStats);

        $upcomingEvents = $this->dashboardService->getUpcomingEvents($userId, 'HOD', $departmentId);

        $this->render(
            'dashboard.hod',
            [
                'pageTitle'      => 'HOD Dashboard',
                'user'           => $user,
                'stats'          => $stats,
                'departmentId'   => $departmentId,
                'userId'         => $userId,
                'upcomingEvents' => $upcomingEvents,
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Staff Coordinator Dashboard
     *
     * Symposium and competition management overview.
     * Statistics are scoped to symposiums and competitions created
     * by this Staff Coordinator.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function staffCoordinator(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator');

        $user   = Session::get('user', []);
        $userId = (int) ($user['user_id'] ?? 0);

        $symposiumId = null;
        if (isset($_GET['symposium_id']) && is_numeric($_GET['symposium_id'])) {
            $symposiumId = (int)$_GET['symposium_id'];
        }

        $ficStats = $this->dashboardService->getFicStats($userId);
        $stats = array_merge($this->dashboardService->getStaffCoordinatorStats($userId, $symposiumId), $ficStats);

        $symposiumModel = new \App\Models\SymposiumModel();
        
        $mySymposiums = $symposiumModel->getAll(null, null, null, null, null, $userId);
        $approvedSymposiums = $symposiumModel->getAll(null, null, null, 'Approved', null, $userId);

        $upcomingEvents = $this->dashboardService->getUpcomingEvents($userId, 'Staff Coordinator');

        $this->render(
            'dashboard.staff_coordinator',
            [
                'pageTitle'           => 'Staff Coordinator Dashboard',
                'user'                => $user,
                'stats'               => $stats,
                'mySymposiums'        => $mySymposiums,
                'approvedSymposiums'  => $approvedSymposiums,
                'selectedSymposiumId' => $symposiumId,
                'upcomingEvents'      => $upcomingEvents,
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Staff Dashboard
     *
     * Student management focused dashboard.
     * Staff can manage students and assist coordinators.
     * They do not create symposiums.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function staff(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff');

        $user   = Session::get('user', []);
        $userId = (int) ($user['user_id'] ?? 0);
        
        $ficStats = $this->dashboardService->getFicStats($userId);
        $stats    = array_merge($this->dashboardService->getStaffStats($userId), $ficStats);

        $upcomingEvents = $this->dashboardService->getUpcomingEvents($userId, 'Staff');

        $this->render(
            'dashboard.staff',
            [
                'pageTitle'      => 'Staff Dashboard',
                'user'           => $user,
                'stats'          => $stats,
                'upcomingEvents' => $upcomingEvents,
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Student Coordinator Dashboard
     *
     * Assigned competition and participant management dashboard.
     * Student Coordinators verify registrations and assist with
     * competition operations.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function studentCoordinator(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Student Coordinator');

        $user   = Session::get('user', []);
        $userId = (int) ($user['user_id'] ?? 0);

        $stats = $this->dashboardService->getStudentCoordinatorStats($userId);

        $this->render(
            'dashboard.student_coordinator',
            [
                'pageTitle' => 'Student Coordinator Dashboard',
                'user'      => $user,
                'stats'     => $stats,
            ]
        );
    }
}