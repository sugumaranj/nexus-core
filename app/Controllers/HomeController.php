<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : HomeController.php
 * Location    : app/Controllers/
 * Description : Handles all public (unauthenticated) pages.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display the application landing page.
 * • Display future public pages.
 *
 * NOTE
 * -------------------------------------------------------------------------
 * Dashboard pages are handled separately by DashboardController.
 *
 * This controller extends BaseController to reuse:
 * • View rendering
 * • Redirect helper
 * • Flash messages
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

final class HomeController extends BaseController
{
    /**
     * ---------------------------------------------------------------------
     * Display Landing Page.
     *
     * This is the public homepage shown before authentication.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function index(): void
    {
        $this->render(
            'home.index',
            [
                'pageTitle' => 'NexusCore'
            ],
            'master'
        );
    }
}