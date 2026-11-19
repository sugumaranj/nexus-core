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
    /**
     * -------------------------------------------------------------------------
     * Display the External Student Registration Page.
     *
     * If the student is already authenticated, redirect directly to the portal.
     *
     * @return void
     * -------------------------------------------------------------------------
     */
    public function showRegister(): void
    {
        if ($this->authService->check()) {
            $this->redirect('/student/dashboard');
        }

        $collegeModel = new \App\Models\CollegeModel();
        $colleges = $collegeModel->getAllActive(false);

        $this->render(
            'student.register',
            [
                'pageTitle' => 'Student Registration — NexusCore',
                'colleges'  => $colleges,
            ],
            'auth'
        );
    }

    /**
     * -------------------------------------------------------------------------
     * Process External Student Self-Registration.
     *
     * POST /student/register
     *
     * @return never
     * -------------------------------------------------------------------------
     */
    public function register(): never
    {
        Session::start();

        $formToken = $_POST['form_idempotency_token'] ?? null;
        if (!verify_form_idempotency_token($formToken)) {
            $this->error('A registration request is already processing. Please do not submit multiple times.');
            $this->redirect('/student/register');
        }

        $result = $this->authService->registerExternalStudent($_POST);

        if (!$result['success']) {
            $this->error($result['message']);
            Session::set('old_input', [
                'full_name'           => $_POST['full_name'] ?? '',
                'register_number'     => $_POST['register_number'] ?? '',
                'email'               => $_POST['email'] ?? '',
                'phone'               => $_POST['phone'] ?? '',
                'college_id'          => $_POST['college_id'] ?? '',
                'custom_college_name' => $_POST['custom_college_name'] ?? '',
                'department'          => $_POST['department'] ?? '',
                'academic_year'       => $_POST['academic_year'] ?? '1',
                'semester'            => $_POST['semester'] ?? '1',
                'gender'              => $_POST['gender'] ?? 'Male',
            ]);
            $this->redirect('/student/register');
        }

        Session::remove('old_input');
        $this->success('Registration successful! You can now log in using your Register Number or Email.');
        $this->redirect('/student/login');
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX: Search Colleges
     *
     * GET /api/colleges/search?q=...
     *
     * @return void
     * -------------------------------------------------------------------------
     */
    public function ajaxSearchColleges(): void
    {
        $q = trim($_GET['q'] ?? '');
        $collegeModel = new \App\Models\CollegeModel();
        $results = $collegeModel->search($q, true, 20);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $results]);
        exit;
    }

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

        $rateLimiter = new \App\Services\RateLimiterService();
        $clientIp = \App\Services\RateLimiterService::getClientIp();

        if ($rateLimiter->isRateLimited($clientIp, '/student/login', 5, 300)) {
            http_response_code(429);
            $this->error('Too many failed login attempts. Please wait 5 minutes before trying again.');
            $this->redirect('/student/login');
        }

        /*
        |----------------------------------------------------------------------
        | Retrieve and sanitize POST data
        |----------------------------------------------------------------------
        */

        $identifier = trim($_POST['register_number'] ?? $_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';

        /*
        |----------------------------------------------------------------------
        | Basic presence validation — no blank fields
        |----------------------------------------------------------------------
        */

        if ($identifier === '' || $password === '') {

            $this->error('Register number or email and password are required.');

            $this->redirect('/student/login');
        }

        /*
        |----------------------------------------------------------------------
        | Delegate to service — service returns ['success' => bool, 'message' => string]
        |----------------------------------------------------------------------
        */

        $result = $this->authService->login($identifier, $password);

        if (!$result['success']) {
            $rateLimiter->recordAttempt($clientIp, '/student/login');

            $this->error($result['message']);

            $this->redirect('/student/login');
        }

        $rateLimiter->clearAttempts($clientIp, '/student/login');

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
