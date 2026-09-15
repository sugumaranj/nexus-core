<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : SyncCenterController.php
 * Location    : app/Controllers/
 * Description : Sync Center — displays offline sync history and status.
 *
 * Routes
 * -------------------------------------------------------------------------
 * GET /sync-center   → index()
 *
 * The Sync Center is visible to coordinators (Staff + Staff Coordinator).
 * Actual syncing is done via AttendanceController::sync().
 *
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\RoleMiddleware;
use App\Models\SyncQueueModel;

final class SyncCenterController extends BaseController
{
    private SyncQueueModel $syncModel;

    public function __construct()
    {
        $this->requireLogin();
        RoleMiddleware::requireRole(
            \App\Helpers\RoleHelper::ADMIN,
            \App\Helpers\RoleHelper::PRINCIPAL,
            \App\Helpers\RoleHelper::HOD,
            \App\Helpers\RoleHelper::STAFF,
            \App\Helpers\RoleHelper::STAFF_COORDINATOR,
            \App\Helpers\RoleHelper::STUDENT_COORDINATOR
        );

        $this->syncModel = new SyncQueueModel();
    }

    /**
     * -------------------------------------------------------------------------
     * GET /sync-center
     * -------------------------------------------------------------------------
     */
    public function index(): void
    {
        $user = $this->user();
        $uid  = (int) $user['user_id'];
        $role = $user['role'] ?? '';

        // Admins/HODs see all entries; coordinators see only their own
        $recent  = $this->syncModel->getRecentByUser($uid, 30);
        $summary = $this->syncModel->getSummaryByUser($uid);

        $csrfToken = $this->generateCsrfToken();

        $this->render('sync.center', [
            'pageTitle' => 'Sync Center',
            'recent'    => $recent,
            'summary'   => $summary,
            'role'      => $role,
            'csrfToken' => $csrfToken,
        ]);
    }

    /**
     * Returns the current session CSRF token, creating one only if absent.
     * Never regenerates an existing token — that would invalidate in-flight forms.
     */
    private function generateCsrfToken(): string
    {
        $user = $this->user();
        $uid  = $user['user_id'] ?? 0;
        $token = substr(hash_hmac('sha256', (string)$uid, 'NexusCore_CSRF_Salt_2026'), 0, 32);
        
        Session::set('csrf_token', $token);
        return $token;
    }
}
