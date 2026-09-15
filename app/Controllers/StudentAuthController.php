<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : StudentAuthController.php
 * Location    : app/Controllers/
 * Description : Handles student authentication requests.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display student login page
 * • Authenticate student by register_number + password
 * • Redirect authenticated student to the portal dashboard
 * • Logout student (destroy student session only)
 *
 * Design Notes
 * -------------------------------------------------------------------------
 * This controller is parallel to AuthController (staff login).
 * It uses 'auth' layout — the existing authentication layout —
 * since no separate student layout is needed for login.
 * After login, redirects to /student/dashboard.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Services\StudentAuthService;

final class StudentAuthController extends BaseController
{
    /**
     * Student Authentication Service.
     *
     * @var StudentAuthService
     */
    private StudentAuthService $authService;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->authService = new StudentAuthService();
    }

    /**
     * -------------------------------------------------------------------------
     * Display the Student Login Page.
     *
     * If the student is already authenticated, redirect directly to the portal.
     *
     * @return void
     * -------------------------------------------------------------------------
     */
    public function showLogin(): void
    {
        if ($this->authService->check()) {

            $this->redirect('/student/dashboard');
        }

        $this->render(
            'student.login',
            ['pageTitle' => 'Student Portal — Login'],
            'auth'
        );
    }

    /**
     * -------------------------------------------------------------------------
     * Authenticate the Student.
     *
     * POST /student/login
     *
     * @return never
     * -------------------------------------------------------------------------
     */
    public function login(): never
    {
        Session::start();

        /*
        |----------------------------------------------------------------------
        | Retrieve and sanitize POST data
        |----------------------------------------------------------------------
        */

        $registerNumber = trim($_POST['register_number'] ?? '');
        $password       = $_POST['password'] ?? '';

        /*
        |----------------------------------------------------------------------
        | Basic presence validation — no blank fields
        |----------------------------------------------------------------------
        */

        if ($registerNumber === '' || $password === '') {

            $this->error('Register number and password are required.');

            $this->redirect('/student/login');
        }

        /*
        |----------------------------------------------------------------------
        | Delegate to service — service returns ['success' => bool, 'message' => string]
        |----------------------------------------------------------------------
        */

        $result = $this->authService->login($registerNumber, $password);

        if (!$result['success']) {

            $this->error($result['message']);

            $this->redirect('/student/login');
        }

        /*
        |----------------------------------------------------------------------
        | Redirect to Student Dashboard
        |----------------------------------------------------------------------
        */

        $this->redirect('/student/dashboard');
    }

    /**
     * -------------------------------------------------------------------------
     * Logout the Student.
     *
     * Destroys only the student session. Staff sessions are unaffected.
     *
     * @return never
     * -------------------------------------------------------------------------
     */
    public function logout(): never
    {
        $this->authService->logout();
        Session::remove('chatbot_history');

        $this->redirect('/student/login');
    }
}
