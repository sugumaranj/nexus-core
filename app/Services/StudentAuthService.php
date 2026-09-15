<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : StudentAuthService.php
 * Location    : app/Services/
 * Description : Handles student authentication for the Student Portal.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Authenticate a student by register_number + password
 * • Verify the student's account_status = 'Active'
 * • Create and destroy the student session ($_SESSION['student'])
 * • Return the current authenticated student
 *
 * Design Notes
 * -------------------------------------------------------------------------
 * This service is parallel to AuthService, which handles staff authentication.
 * It reuses the existing StudentModel::findByRegisterNumber() method, which
 * was confirmed present at line 208 of StudentModel.php.
 *
 * Session key: $_SESSION['student']
 * Staff key  : $_SESSION['user']  (independent — no cross-access)
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Core\Session;
use App\Models\StudentModel;

final class StudentAuthService
{
    /**
     * Student model instance.
     *
     * @var StudentModel
     */
    private StudentModel $studentModel;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->studentModel = new StudentModel();
    }

    // =========================================================================
    // AUTHENTICATION
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Authenticate a student.
     *
     * Verifies:
     *  1. Student exists by register_number
     *  2. Account status is 'Active'
     *  3. Password matches the stored hash
     *
     * On success: creates the $_SESSION['student'] session and regenerates
     * the session ID to prevent session fixation.
     *
     * @param string $registerNumber  e.g. 'C4S35637'
     * @param string $password        Plain-text password from the form
     *
     * @return array  ['success' => bool, 'message' => string]
     * -------------------------------------------------------------------------
     */
    public function login(string $registerNumber, string $password): array
    {
        /*
        |----------------------------------------------------------------------
        | 1. Find student by register_number
        |----------------------------------------------------------------------
        */

        $student = $this->studentModel->findByRegisterNumber(trim($registerNumber));

        if ($student === false) {
            return [
                'success' => false,
                'message' => 'Invalid register number or password.',
            ];
        }

        /*
        |----------------------------------------------------------------------
        | 2. Verify account status
        |----------------------------------------------------------------------
        */

        if (($student['account_status'] ?? '') !== 'Active') {
            return [
                'success' => false,
                'message' => 'Your account is inactive. Please contact the administrator.',
            ];
        }

        /*
        |----------------------------------------------------------------------
        | 3. Verify password
        |----------------------------------------------------------------------
        */

        if (!password_verify($password, $student['password_hash'] ?? '')) {
            return [
                'success' => false,
                'message' => 'Invalid register number or password.',
            ];
        }

        /*
        |----------------------------------------------------------------------
        | 4. Create session
        |
        | Store only the fields needed across the student portal.
        | password_hash is never stored in session.
        |----------------------------------------------------------------------
        */

        Session::regenerate();

        Session::set('student', [
            'student_id'      => (int)    $student['student_id'],
            'register_number' => (string) $student['register_number'],
            'full_name'       => (string) $student['full_name'],
            'email'           => (string) ($student['email'] ?? ''),
            'department_id'   => (int)    $student['department_id'],
            'academic_year'   => (int)    $student['academic_year'],
            'semester'        => (int)    $student['semester'],
            'gender'          => (string) $student['gender'],
            'account_status'  => (string) $student['account_status'],
        ]);

        return [
            'success' => true,
            'message' => 'Login successful.',
        ];
    }

    // =========================================================================
    // SESSION HELPERS
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Destroy the student session (logout).
     *
     * Removes only the 'student' session key.
     * Staff sessions are unaffected.
     *
     * @return void
     * -------------------------------------------------------------------------
     */
    public function logout(): void
    {
        Session::remove('student');
    }

    /**
     * -------------------------------------------------------------------------
     * Check whether a student session is active.
     *
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function check(): bool
    {
        return Session::has('student');
    }

    /**
     * -------------------------------------------------------------------------
     * Return the current authenticated student session array.
     *
     * @return array|null  null if no student session exists.
     * -------------------------------------------------------------------------
     */
    public function student(): ?array
    {
        $data = Session::get('student');

        return is_array($data) ? $data : null;
    }
}
