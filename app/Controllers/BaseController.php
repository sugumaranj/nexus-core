<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : BaseController.php
 * Location    : app/Controllers/
 * Description : Base controller shared by all controllers.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Render views
 * • Redirect users
 * • Flash messages
 * • Authentication helper
 * • Authorization helper
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Core\View;

abstract class BaseController
{
    /**
     * Render a view.
     */
    protected function render(
        string $view,
        array $data = [],
        string $layout = 'dashboard'
    ): void {

        View::render(
            $view,
            $data,
            $layout
        );
    }

    /**
     * Redirect.
     */
    protected function redirect(string $path): never
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            header('Location: ' . $path);
        } else {
            header('Location: ' . base_url($path));
        }

        exit;
    }

    /**
     * Success Flash.
     */
    protected function success(string $message): void
    {
        Session::flash(
            'success',
            $message
        );
    }

    /**
     * Error Flash.
     */
    protected function error(string $message): void
    {
        Session::flash(
            'error',
            $message
        );
    }

    /**
     * Check Login.
     */
    protected function requireLogin(): void
    {
        Session::start();

        if (!Session::has('user')) {

            $this->redirect('/login');

        }
    }

    /**
     * Current User.
     */
    protected function user(): array
    {
        return Session::get('user', []);
    }
}