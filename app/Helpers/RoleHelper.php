<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : RoleHelper.php
 * Location    : app/Helpers/
 * Description : Single source of truth for all role-related data.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Canonical list of all valid roles
 * • Employee ID prefix per role
 * • Badge colour per role (Bootstrap context class)
 * • Allowed department types per role
 * • Sidebar menu configuration per role
 * • Permission flags per role
 * • Convenience role-check helpers (isAdmin, isHOD, …)
 *
 * IMPORTANT
 * -------------------------------------------------------------------------
 * Never scatter `if ($role === 'Staff Coordinator')` comparisons
 * across the codebase.  Import this helper instead.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Helpers;

final class RoleHelper
{
    // =========================================================================
    // Canonical Role Constants
    // =========================================================================

    public const ADMIN               = 'Admin';
    public const PRINCIPAL           = 'Principal';
    public const HOD                 = 'HOD';
    public const STAFF_COORDINATOR   = 'Staff Coordinator';
    public const STAFF               = 'Staff';
    public const STUDENT_COORDINATOR = 'Student Coordinator';

    // =========================================================================
    // Employee ID Prefixes
    // -------------------------------------------------------------------------
    // Format: {PREFIX}{NNNN}   (4-digit zero-padded numeric suffix)
    //
    // Examples:
    //   Admin            → ADM0001
    //   Principal        → PRI0001
    //   HOD              → HOD0001
    //   Staff Coordinator→ SC0001
    //   Staff            → STF0001
    //   Student Coord.   → PGSC0001
    // =========================================================================

    private const PREFIXES = [
        self::ADMIN               => 'ADM',
        self::PRINCIPAL           => 'PRI',
        self::HOD                 => 'HOD',
        self::STAFF_COORDINATOR   => 'SC',
        self::STAFF               => 'STF',
        self::STUDENT_COORDINATOR => 'PGSC',
    ];

    // =========================================================================
    // Badge Colours (Bootstrap bg-* context classes)
    // =========================================================================

    private const BADGE_COLOURS = [
        self::ADMIN               => 'danger',
        self::PRINCIPAL           => 'dark',
        self::HOD                 => 'primary',
        self::STAFF_COORDINATOR   => 'success',
        self::STAFF               => 'secondary',
        self::STUDENT_COORDINATOR => 'info',
    ];

    // =========================================================================
    // Dashboard Stat Keys
    // -------------------------------------------------------------------------
    // Maps a role to the key in the admin stats array that counts that role.
    // =========================================================================

    private const DASHBOARD_STAT_KEYS = [
        self::HOD                 => 'total_hods',
        self::STAFF_COORDINATOR   => 'total_staff_coordinators',
        self::STAFF               => 'total_staff',
        self::STUDENT_COORDINATOR => 'total_student_coordinators',
    ];

    // =========================================================================
    // Allowed Department Codes Per Role
    // -------------------------------------------------------------------------
    // null  → any department (or no restriction)
    // array → only these department_codes are allowed
    //
    // Staff Coordinator and Student Coordinator are restricted to PG departments
    // (PGCS, PGCA) because they organise NEXUS.
    // =========================================================================

    private const ALLOWED_DEPT_CODES = [
        self::ADMIN               => null,          // no dept required
        self::PRINCIPAL           => null,          // no dept required
        self::HOD                 => null,          // any dept
        self::STAFF_COORDINATOR   => ['PGCS', 'PGCA'],
        self::STAFF               => null,          // any dept
        self::STUDENT_COORDINATOR => ['PGCS', 'PGCA'],
    ];

    // =========================================================================
    // Roles that REQUIRE a department
    // =========================================================================

    private const REQUIRES_DEPT = [
        self::HOD,
        self::STAFF,
        self::STAFF_COORDINATOR,
        self::STUDENT_COORDINATOR,
    ];

    // =========================================================================
    // Roles that CAN access the Symposium module
    // =========================================================================

    private const SYMPOSIUM_ROLES = [
        self::PRINCIPAL,
        self::HOD,
        self::STAFF_COORDINATOR,
    ];

    // =========================================================================
    // Roles that CAN CREATE symposiums (Staff Coordinator ONLY)
    // =========================================================================

    private const SYMPOSIUM_CREATE_ROLES = [
        self::STAFF_COORDINATOR,
    ];

    // =========================================================================
    // PUBLIC API — Lists & Lookups
    // =========================================================================

    /**
     * Return the full ordered list of valid roles.
     *
     * This is the authoritative list used by dropdowns, validators, and RBAC.
     *
     * @return array<string>
     */
    public static function getAllRoles(): array
    {
        return [
            self::ADMIN,
            self::PRINCIPAL,
            self::HOD,
            self::STAFF_COORDINATOR,
            self::STAFF,
            self::STUDENT_COORDINATOR,
        ];
    }

    /**
     * Return the Employee ID prefix for a given role.
     *
     * @param string $role
     *
     * @return string  Empty string if role is unknown.
     */
    public static function getPrefix(string $role): string
    {
        return self::PREFIXES[$role] ?? '';
    }

    /**
     * Return the Bootstrap badge colour context class for a given role.
     *
     * @param string $role
     *
     * @return string  e.g. 'primary', 'danger', …
     */
    public static function getBadgeColour(string $role): string
    {
        return self::BADGE_COLOURS[$role] ?? 'secondary';
    }

    /**
     * Return the dashboard stat key for a given role.
     *
     * @param string $role
     *
     * @return string|null
     */
    public static function getDashboardStatKey(string $role): ?string
    {
        return self::DASHBOARD_STAT_KEYS[$role] ?? null;
    }

    /**
     * Return the allowed department codes for a given role.
     *
     * Returns null if there is no restriction (any department or no department).
     *
     * @param string $role
     *
     * @return array<string>|null
     */
    public static function getAllowedDeptCodes(string $role): ?array
    {
        return self::ALLOWED_DEPT_CODES[$role] ?? null;
    }

    /**
     * Return whether the given role requires a department to be selected.
     *
     * @param string $role
     *
     * @return bool
     */
    public static function requiresDepartment(string $role): bool
    {
        return in_array($role, self::REQUIRES_DEPT, true);
    }

    /**
     * Return whether a role is valid (exists in the canonical list).
     *
     * @param string $role
     *
     * @return bool
     */
    public static function isValidRole(string $role): bool
    {
        return in_array($role, self::getAllRoles(), true);
    }

    /**
     * Return whether a role can access the Symposium module at all.
     *
     * Note: Admin can VIEW symposiums but is NOT in this list because
     * Admin access to the full module is handled separately (view-only).
     *
     * @param string $role
     *
     * @return bool
     */
    public static function canAccessSymposiums(string $role): bool
    {
        return in_array($role, self::SYMPOSIUM_ROLES, true);
    }

    /**
     * Return whether a role can CREATE symposiums.
     *
     * Only Staff Coordinator can create, edit, delete, and submit symposiums.
     *
     * @param string $role
     *
     * @return bool
     */
    public static function canCreateSymposium(string $role): bool
    {
        return in_array($role, self::SYMPOSIUM_CREATE_ROLES, true);
    }

    // =========================================================================
    // PUBLIC API — Convenience Role Checkers
    // =========================================================================

    /**
     * @param array $user  Session user array (must contain 'role' key).
     */
    public static function isAdmin(array $user): bool
    {
        return ($user['role'] ?? '') === self::ADMIN;
    }

    public static function isPrincipal(array $user): bool
    {
        return ($user['role'] ?? '') === self::PRINCIPAL;
    }

    public static function isHOD(array $user): bool
    {
        return ($user['role'] ?? '') === self::HOD;
    }

    public static function isStaffCoordinator(array $user): bool
    {
        return ($user['role'] ?? '') === self::STAFF_COORDINATOR;
    }

    public static function isStaff(array $user): bool
    {
        return ($user['role'] ?? '') === self::STAFF;
    }

    public static function isStudentCoordinator(array $user): bool
    {
        return ($user['role'] ?? '') === self::STUDENT_COORDINATOR;
    }

    /**
     * Return whether the user has any of the given roles.
     *
     * Usage: RoleHelper::hasRole($user, 'Admin', 'Principal')
     *
     * @param array  $user
     * @param string ...$roles
     *
     * @return bool
     */
    public static function hasRole(array $user, string ...$roles): bool
    {
        return in_array($user['role'] ?? '', $roles, true);
    }

    // =========================================================================
    // PUBLIC API — Sidebar Menu Configuration
    // =========================================================================

    /**
     * Return the sidebar menu items for the given role.
     *
     * Each item: ['title' => string, 'icon' => string, 'url' => string]
     *
     * The caller is responsible for calling base_url() on the 'url' value.
     *
     * @param string $role
     *
     * @return array<array<string, string>>
     */
    public static function getSidebarMenu(string $role): array
    {
        return match ($role) {
            self::ADMIN               => self::adminMenu(),
            self::PRINCIPAL           => self::principalMenu(),
            self::HOD                 => self::hodMenu(),
            self::STAFF_COORDINATOR   => self::staffCoordinatorMenu(),
            self::STAFF               => self::staffMenu(),
            self::STUDENT_COORDINATOR => self::studentCoordinatorMenu(),
            default                   => [],
        };
    }

    // =========================================================================
    // PRIVATE — Per-Role Menu Definitions
    // =========================================================================

    private static function adminMenu(): array
    {
        return [
            ['title' => 'Dashboard',   'icon' => 'bi-speedometer2',   'url' => '/dashboard'],
            ['title' => 'Users',       'icon' => 'bi-people',          'url' => '/users'],
            ['title' => 'Students',    'icon' => 'bi-person-vcard',    'url' => '/students'],
            ['title' => 'Symposiums',  'icon' => 'bi-calendar-event',  'url' => '/symposiums'],
            ['title' => 'Registrations', 'icon' => 'bi-card-checklist', 'url' => '/coordinator/registrations'],
            ['title' => 'Staff Allocation', 'icon' => 'bi-people-fill', 'url' => '/symposiums/allocation'],
            ['title' => 'Certificates',    'icon' => 'bi-patch-check',     'url' => '/certificates'],
            ['title' => 'Reports',     'icon' => 'bi-bar-chart',       'url' => '/reports'],
            ['title' => 'Audit Logs',  'icon' => 'bi-journal-text',    'url' => '/audit-logs'],
            ['title' => 'Master Data', 'is_header' => true],
            ['title' => 'Master Events', 'icon' => 'bi-collection-fill', 'url' => '/admin/events'],
            ['title' => 'Departments', 'icon' => 'bi-building',        'url' => '/departments'],
            ['title' => 'Venues',      'icon' => 'bi-geo-alt',         'url' => '/venues'],
            ['title' => 'Settings',    'icon' => 'bi-gear',            'url' => '/settings'],
            ['title' => 'Sync Center', 'icon' => 'bi-arrow-repeat',    'url' => '/sync-center'],
        ];
    }

    private static function principalMenu(): array
    {
        return [
            ['title' => 'Dashboard',          'icon' => 'bi-speedometer2',      'url' => '/dashboard'],
            ['title' => 'Final Approvals',    'icon' => 'bi-check2-all',        'url' => '/symposiums?quick_filter=Pending+Principal+Approval'],
            ['title' => 'Approved Symposiums','icon' => 'bi-calendar-check',    'url' => '/symposiums?quick_filter=Approved'],
            ['title' => 'Reports',            'icon' => 'bi-bar-chart',         'url' => '/reports'],
        ];
    }

    private static function hodMenu(): array
    {
        return [
            ['title' => 'Dashboard',          'icon' => 'bi-speedometer2',      'url' => '/dashboard'],
            ['title' => 'My Assigned Events', 'icon' => 'bi-person-workspace','url' => '/my/assigned-events'],
            ['title' => 'Pending Approvals',  'icon' => 'bi-hourglass-split',   'url' => '/symposiums?status=Pending HOD Approval'],
            ['title' => 'Approved Symposiums','icon' => 'bi-calendar-check',    'url' => '/symposiums?status=Approved'],
            ['title' => 'Judge Dashboard',    'icon' => 'bi-star',              'url' => '/judge/dashboard'],
            ['title' => 'Reports',            'icon' => 'bi-bar-chart',         'url' => '/reports'],
            ['title' => 'Event Feedback',     'icon' => 'bi-chat-square-text',  'url' => '/feedback'],
            ['title' => 'Sync Center',        'icon' => 'bi-arrow-repeat',      'url' => '/sync-center'],
        ];
    }

    private static function staffCoordinatorMenu(): array
    {
        return [
            ['title' => 'MAIN',                  'is_header' => true],
            ['title' => 'Dashboard',             'icon' => 'bi-speedometer2',   'url' => '/dashboard'],
            ['title' => 'Notifications',         'icon' => 'bi-bell',           'url' => '/notifications'],

            ['title' => 'MANAGEMENT',            'is_header' => true],
            ['title' => 'Students',              'icon' => 'bi-person-vcard',   'url' => '/students'],
            ['title' => 'Symposiums',            'icon' => 'bi-calendar-event', 'url' => '/symposiums'],
            ['title' => 'Registrations',         'icon' => 'bi-card-checklist', 'url' => '/coordinator/registrations'],
            ['title' => 'Attendance',            'icon' => 'bi-calendar2-check','url' => '/attendance'],
            ['title' => 'Event Feedback',        'icon' => 'bi-chat-square-text','url' => '/feedback'],

            ['title' => 'OPERATIONS',            'is_header' => true],
            ['title' => 'Staff Allocation',      'icon' => 'bi-people-fill',    'url' => '/symposiums/allocation'],
            ['title' => 'Master Events',         'icon' => 'bi-collection-fill', 'url' => '/admin/events'],
            ['title' => 'Venues',                'icon' => 'bi-geo-alt',         'url' => '/venues'],
            ['title' => 'Reports',               'icon' => 'bi-bar-chart',      'url' => '/reports'],
            ['title' => 'Evaluation & Results',  'icon' => 'bi-award',          'url' => '/evaluation/results'],
            ['title' => 'Certificates',          'icon' => 'bi-patch-check',    'url' => '/certificates'],

            ['title' => 'MY PORTALS',            'is_header' => true],
            ['title' => 'My Assigned Events',    'icon' => 'bi-person-workspace','url' => '/my/assigned-events'],
            ['title' => 'Judge Dashboard',       'icon' => 'bi-star',           'url' => '/judge/dashboard'],

            ['title' => 'SYSTEM',                'is_header' => true],
            ['title' => 'Sync Center',           'icon' => 'bi-arrow-repeat',   'url' => '/sync-center'],
        ];
    }

    private static function staffMenu(): array
    {
        return [
            ['title' => 'Dashboard',             'icon' => 'bi-speedometer2',   'url' => '/dashboard'],
            ['title' => 'Students',              'icon' => 'bi-person-vcard',   'url' => '/students'],
            ['title' => 'Symposiums',            'icon' => 'bi-calendar-event', 'url' => '/symposiums'],
            ['title' => 'Registrations',         'icon' => 'bi-card-checklist', 'url' => '/coordinator/registrations'],
            ['title' => 'My Assigned Events',    'icon' => 'bi-person-workspace','url' => '/my/assigned-events'],
            ['title' => 'Judge Dashboard',       'icon' => 'bi-star',           'url' => '/judge/dashboard'],
            ['title' => 'Attendance',            'icon' => 'bi-calendar2-check','url' => '/attendance'],
            ['title' => 'Sync Center',           'icon' => 'bi-arrow-repeat',   'url' => '/sync-center'],
        ];
    }

    private static function studentCoordinatorMenu(): array
    {
        return [
            ['title' => 'Dashboard',                 'icon' => 'bi-speedometer2',    'url' => '/dashboard'],
            ['title' => 'Student Registrations',     'icon' => 'bi-person-plus',     'url' => '/students'],
            ['title' => 'Competition Registrations', 'icon' => 'bi-trophy',          'url' => '/competitions'],
            ['title' => 'Attendance',                'icon' => 'bi-calendar2-check', 'url' => '/attendance'],
            ['title' => 'Certificates',              'icon' => 'bi-award',           'url' => '/certificates'],
            ['title' => 'Reports',                   'icon' => 'bi-bar-chart',       'url' => '/reports'],
            ['title' => 'Sync Center',               'icon' => 'bi-arrow-repeat',    'url' => '/sync-center'],
        ];
    }
}
