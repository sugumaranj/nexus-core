<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : AuthMiddleware.php
 * Location    : app/Middleware/
 * Description : Ensures that only authenticated users can access
 *               protected routes.
 * -------------------------------------------------------------------------
 */

namespace App\Middleware;

use App\Core\Session;

final class AuthMiddleware
{
    /**
     * Check whether the current user is authenticated.
     *
     * @return void
     */
    public static function handle(): void
    {
        Session::start();

        if (!Session::has('user')) {

            Session::flash(
                'error',
                'Please login to continue.'
            );

            header(
                'Location: ' . base_url() . '/login'
            );

            exit;
        }
    }
}