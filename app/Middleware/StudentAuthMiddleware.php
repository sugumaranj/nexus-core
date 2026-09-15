<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : StudentAuthMiddleware.php
 * Location    : app/Middleware/
 * Description : Ensures only authenticated students can access the
 *               Student Portal.
 *
 * Design
 * -------------------------------------------------------------------------
 * This middleware is parallel to AuthMiddleware, which protects staff routes.
 * It checks $_SESSION['student'] (set by StudentAuthService::login()).
 * The two session keys are independent — a staff login does NOT grant
 * access to student routes, and vice versa.
 *
 * Usage
 * -------------------------------------------------------------------------
 * // At the top of any student-only controller method:
 * StudentAuthMiddleware::handle();
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Middleware;

use App\Core\Session;

final class StudentAuthMiddleware
{
    /**
     * -------------------------------------------------------------------------
     * Verify that a student session exists.
     *
     * If no valid student session is found, store a flash error and redirect
     * to the student login page. Execution stops immediately.
     *
     * @return void
     * -------------------------------------------------------------------------
     */
    public static function handle(): void
    {
        Session::start();

        if (!Session::has('student')) {

            Session::flash(
                'error',
                'Please log in to access the Student Portal.'
            );

            header(
                'Location: ' . base_url() . '/student/login'
            );

            exit;
        }
    }
}
