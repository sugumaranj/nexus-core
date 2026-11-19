<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : AuthController.php
 * Location    : app/Controllers/
 * Description : Handles user authentication requests.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display login page
 * • Authenticate users
 * • Redirect users based on role
 * • Logout users
 *
 * NOTE
 * -------------------------------------------------------------------------
 * This controller extends BaseController to reuse:
 * • View rendering
 * • Redirect helper
 * • Flash messages
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Services\AuthService;

final class AuthController extends BaseController
{
    /**
     * Authentication Service.
     *
     * @var AuthService
     */
    private AuthService $authService;

    /**
     * ---------------------------------------------------------------------
     * Constructor.
     * ---------------------------------------------------------------------
     */
    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * ---------------------------------------------------------------------
     * Display Login Page.
     *
     * Redirect authenticated users directly to the dashboard.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function showLogin(): void
    {
        if ($this->authService->check()) {

            $this->redirect('/dashboard');
        }

        $this->render(
            'auth.login',
            [
                'pageTitle' => 'Login'
            ],
            'auth'
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Authenticate User.
     *
     * @return never
     * ---------------------------------------------------------------------
     */
    public function login(): never
    {
        Session::start();

        $rateLimiter = new \App\Services\RateLimiterService();
        $clientIp = \App\Services\RateLimiterService::getClientIp();

        if ($rateLimiter->isRateLimited($clientIp, '/login', 5, 300)) {
            http_response_code(429);
            $this->error('Too many failed login attempts. Please wait 5 minutes before trying again.');
            $this->redirect('/login');
        }

        /*
        |--------------------------------------------------------------------------
        | Retrieve Form Data
        |--------------------------------------------------------------------------
        */

        $email = trim($_POST['email'] ?? '');

        $password = $_POST['password'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | Validate Input
        |--------------------------------------------------------------------------
        */

        if ($email === '' || $password === '') {

            $this->error(
                'Email and Password are required.'
            );

            $this->redirect('/login');
        }

        /*
        |--------------------------------------------------------------------------
        | Attempt Login
        |--------------------------------------------------------------------------
        */

        if (!$this->authService->login($email, $password)) {
            $rateLimiter->recordAttempt($clientIp, '/login');

            $this->error(
                'Invalid email or password.'
            );

            $this->redirect('/login');
        }

        $rateLimiter->clearAttempts($clientIp, '/login');

        /*
        |--------------------------------------------------------------------------
        | Retrieve Authenticated User
        |--------------------------------------------------------------------------
        */

        $user = $this->authService->user();

        /*
        |--------------------------------------------------------------------------
        | Redirect Based On Role
        |--------------------------------------------------------------------------
        */

        $target = match ($user['role'] ?? '') {

            'Admin'               => '/dashboard/admin',

            'Principal'           => '/dashboard/principal',

            'HOD'                 => '/dashboard/hod',

            'Staff Coordinator'   => '/dashboard/staff-coordinator',

            'Staff'               => '/dashboard/staff',

            'Student Coordinator' => '/dashboard/student-coordinator',

            default               => '/login',

        };

        $this->redirect($target);
    }

    /**
     * ---------------------------------------------------------------------
     * Logout User.
     *
     * @return never
     * ---------------------------------------------------------------------
     */
    public function logout(): never
    {
        $this->authService->logout();

        $this->redirect('/');
    }
}