<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : RoleMiddleware.php
 * Location    : app/Middleware/
 * Description : Role-Based Access Control (RBAC) middleware.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Verify the authenticated user holds an allowed role.
 * • Render a 403 Access Denied response for unauthorized users.
 * • Provide a clean static API that controllers can call inline.
 *
 * Usage
 * -------------------------------------------------------------------------
 * // Allow only Admin:
 * RoleMiddleware::requireRole('Admin');
 *
 * // Allow Admin or Staff Coordinator:
 * RoleMiddleware::requireRole('Admin', 'Staff Coordinator');
 *
 * NOTE
 * -------------------------------------------------------------------------
 * Always call AuthMiddleware::handle() before this middleware.
 * RoleMiddleware assumes a valid authenticated session exists.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Middleware;

use App\Core\Session;
use App\Helpers\RoleHelper;

final class RoleMiddleware
{

    /**
     * ---------------------------------------------------------------------
     * Require one or more allowed roles.
     *
     * If the authenticated user's role is NOT in the provided list,
     * a 403 HTTP response is rendered and execution stops.
     *
     * @param string ...$roles One or more allowed role names.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public static function requireRole(string ...$roles): void
    {
        Session::start();

        $user     = Session::get('user', []);

        if (RoleHelper::hasRole($user, ...$roles)) {
            return; // Authorized — continue execution.
        }

        self::render403();
    }

    /**
     * Alias for requiring any of an array of allowed roles.
     *
     * @param array $roles
     * @return void
     */
    public static function requireAnyRole(array $roles): void
    {
        self::requireRole(...$roles);
    }

    /**
     * ---------------------------------------------------------------------
     * Render a 403 Access Denied page and stop execution.
     *
     * @return never
     * ---------------------------------------------------------------------
     */
    private static function render403(): never
    {
        http_response_code(403);

        $errorView = dirname(__DIR__, 2) . '/templates/errors/403.php';

        if (file_exists($errorView)) {
            require $errorView;
        } else {
            echo '<h1>403 — Access Denied</h1>';
            echo '<p>You do not have permission to access this page.</p>';
        }

        exit;
    }
}
