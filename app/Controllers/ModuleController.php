<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : ModuleController.php
 * Location    : app/Controllers/
 * Description : Handles placeholder pages for modules that are not yet
 *               implemented.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display placeholder page for future modules.
 * • Protect module pages using authentication middleware.
 * • Provide a reusable placeholder rendering method.
 *
 * NOTE
 * -------------------------------------------------------------------------
 * These placeholder pages will gradually be replaced with complete
 * modules during development.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Middleware\AuthMiddleware;

final class ModuleController extends BaseController
{
    /**
     * ---------------------------------------------------------------------
     * Constructor.
     *
     * Protect all module pages.
     * ---------------------------------------------------------------------
     */
    public function __construct()
    {
        AuthMiddleware::handle();
    }

    /**
     * ---------------------------------------------------------------------
     * Users Module
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function users(): void
    {
        $this->renderPlaceholder(
            'Users',
            'Manage administrators, principals, HODs, staff coordinators and user accounts.',
            'bi-people'
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Students Module
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function students(): void
    {
        $this->renderPlaceholder(
            'Students',
            'Manage student profiles, registrations and academic information.',
            'bi-person-vcard'
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Symposium Management Module
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function symposiums(): void
    {
        $this->renderPlaceholder(
            'Symposium Management',
            'Create, edit, publish and manage symposiums including dates, venues, coordinators and status.',
            'bi-calendar-event'
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Competition Management Module
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function competitions(): void
    {
        $this->renderPlaceholder(
            'Competition Management',
            'Create, edit and manage competitions, coordinators, venues, schedules and participation rules.',
            'bi-trophy'
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Certificate Management Module
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function certificates(): void
    {
        $this->renderPlaceholder(
            'Certificate Management',
            'Generate, download and verify participation and winner certificates.',
            'bi-award'
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Reports Module
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function reports(): void
    {
        $this->renderPlaceholder(
            'Reports',
            'Generate symposium, competition, registration, attendance and certificate reports.',
            'bi-bar-chart'
        );
    }

    /**
     * ---------------------------------------------------------------------
     * System Settings Module
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function settings(): void
    {
        $this->renderPlaceholder(
            'System Settings',
            'Configure application settings, preferences and master data.',
            'bi-gear'
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Notifications Module
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function notifications(): void
    {
        $this->renderPlaceholder(
            'Notifications',
            'View system notifications, alerts and announcements.',
            'bi-bell'
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Audit Logs Module
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function auditLogs(): void
    {
        $this->renderPlaceholder(
            'Audit Logs',
            'Review system audit logs, user activity and change history.',
            'bi-journal-text'
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Attendance Module
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function attendance(): void
    {
        $this->renderPlaceholder(
            'Attendance',
            'Track and manage event attendance, check-ins and participation records.',
            'bi-calendar2-check'
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Render Placeholder Page.
     *
     * This reusable method renders a common placeholder page for modules
     * that are currently under development.
     *
     * @param string $title
     * @param string $description
     * @param string $icon
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    private function renderPlaceholder(
        string $title,
        string $description,
        string $icon
    ): void {

        $this->render(
            'modules.placeholder',
            [
                'pageTitle'         => $title,
                'moduleTitle'       => $title,
                'moduleDescription' => $description,
                'moduleIcon'        => $icon,
            ]
        );
    }
}