<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : AuthService.php
 * Location    : app/Services/
 * Description : Handles user authentication.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Authenticate users
 * • Verify account status
 * • Verify password
 * • Create authenticated session
 * • Update last login timestamp
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Core\Session;
use App\Models\UserModel;

final class AuthService
{
    /**
     * User model instance.
     *
     * @var UserModel
     */
    private UserModel $userModel;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * ---------------------------------------------------------------------
     * Authenticate User.
     *
     * @param string $email
     * @param string $password
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function login(string $email, string $password): bool
    {
        /*
        |--------------------------------------------------------------------------
        | Retrieve User
        |--------------------------------------------------------------------------
        */

        $user = $this->userModel->findByEmail($email);

        if ($user === false) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Account Status
        |--------------------------------------------------------------------------
        */

        if (($user['account_status'] ?? '') !== 'Active') {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Verify Password
        |--------------------------------------------------------------------------
        */

        if (
            !password_verify(
                $password,
                $user['password_hash']
            )
        ) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent Session Fixation
        |--------------------------------------------------------------------------
        */

        Session::regenerate();

        /*
        |--------------------------------------------------------------------------
        | Store Authenticated User
        |--------------------------------------------------------------------------
        */

        Session::set('user', [

            'user_id'         => (int) $user['user_id'],

            'employee_id'     => $user['employee_id'],

            'department_id'   => $user['department_id'],
            
            'department_name' => $user['department_name'] ?? null,

            'full_name'       => $user['full_name'],

            'email'           => $user['email'],

            'role'            => $user['role'],

            'account_status'  => $user['account_status']

        ]);

        /*
        |--------------------------------------------------------------------------
        | Update Last Login
        |--------------------------------------------------------------------------
        */

        $this->userModel->updateLastLogin(
            (int) $user['user_id']
        );

        return true;
    }

    /**
     * ---------------------------------------------------------------------
     * Logout User.
     * ---------------------------------------------------------------------
     */
    public function logout(): void
    {
        Session::destroy();
    }

    /**
     * ---------------------------------------------------------------------
     * Check Authentication.
     * ---------------------------------------------------------------------
     */
    public function check(): bool
    {
        return Session::has('user');
    }

    /**
     * ---------------------------------------------------------------------
     * Retrieve Current User.
     * ---------------------------------------------------------------------
     */
    public function user(): ?array
    {
        return Session::get('user');
    }
}