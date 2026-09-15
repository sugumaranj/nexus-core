<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : sidebar.php
 * Location    : templates/partials/
 * Description : Dashboard Sidebar Navigation (Role-Based)
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display application branding
 * • Display logged-in user information with role badge
 * • Display role-specific navigation menu (driven by RoleHelper)
 * • Highlight active navigation item
 * • Display logout option
 *
 * Roles & Menu Access
 * -------------------------------------------------------------------------
 * | Role               | Defined in                           |
 * |--------------------|--------------------------------------|
 * | Admin              | RoleHelper::adminMenu()              |
 * | Principal          | RoleHelper::principalMenu()          |
 * | HOD                | RoleHelper::hodMenu()                |
 * | Staff Coordinator  | RoleHelper::staffCoordinatorMenu()   |
 * | Staff              | RoleHelper::staffMenu()              |
 * | Student Coordinator| RoleHelper::studentCoordinatorMenu() |
 *
 * NOTE
 * -------------------------------------------------------------------------
 * Menu items are NOT hardcoded here.
 * They are fetched from RoleHelper::getSidebarMenu() which is the
 * single source of truth.  Add new items only in RoleHelper.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Core\Session;
use App\Helpers\RoleHelper;



/*
|--------------------------------------------------------------------------
| Logged-in User
|--------------------------------------------------------------------------
*/

$loggedInUser = Session::get('user', []);

$currentRole = $loggedInUser['role'] ?? '';


/*
|--------------------------------------------------------------------------
| Current Request Path
|--------------------------------------------------------------------------
*/

$currentPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
) ?? '/';

/*
|--------------------------------------------------------------------------
| Sidebar Menu — driven by RoleHelper (single source of truth)
|--------------------------------------------------------------------------
*/

$menuItems = RoleHelper::getSidebarMenu($currentRole);

/*
|--------------------------------------------------------------------------
| Role Badge Colour
|--------------------------------------------------------------------------
*/

$badgeColour = RoleHelper::getBadgeColour($currentRole);

?>

<!-- =============================================================== -->
<!-- Dashboard Sidebar                                               -->
<!-- =============================================================== -->

<aside class="dashboard-sidebar">

    <!-- =========================================================== -->
    <!-- Application Brand                                           -->
    <!-- =========================================================== -->

    <div class="sidebar-brand">

        <i class="bi bi-mortarboard-fill me-2"></i>

        <span>NexusCore</span>

    </div>

    <!-- =========================================================== -->
    <!-- Logged-in User                                              -->
    <!-- =========================================================== -->

    <div class="sidebar-user text-center py-3">

        <div class="avatar-circle mb-3">

            <i class="bi bi-person-fill"></i>

        </div>

        <h6 class="mb-1">

            <?= htmlspecialchars(
                $loggedInUser['full_name'] ?? 'Administrator',
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </h6>

        <span class="badge bg-<?= htmlspecialchars($badgeColour, ENT_QUOTES, 'UTF-8') ?> text-white" style="font-size:.7rem;">

            <?= htmlspecialchars(
                $currentRole,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </span>

        <?php if (!empty($loggedInUser['employee_id'])): ?>

            <div class="mt-1">
                <small class="text-secondary" style="font-size:.7rem;opacity:.75;">
                    <?= htmlspecialchars($loggedInUser['employee_id'], ENT_QUOTES, 'UTF-8') ?>
                </small>
            </div>

        <?php endif; ?>

        <?php if (in_array($currentRole, ['HOD', 'Staff', 'Staff Coordinator']) && !empty($loggedInUser['department_name'])): ?>

            <div class="mt-1">
                <small class="text-secondary" style="font-size:.7rem;opacity:.75;">
                    <?= htmlspecialchars($loggedInUser['department_name'], ENT_QUOTES, 'UTF-8') ?>
                </small>
            </div>

        <?php endif; ?>

    </div>

    <hr class="sidebar-divider">

    <!-- =========================================================== -->
    <!-- Navigation Menu                                             -->
    <!-- =========================================================== -->

    <ul class="sidebar-menu">

        <?php foreach ($menuItems as $item): ?>

            <?php
            if (($item['is_header'] ?? false) === true):
            ?>
                <li class="sidebar-header mt-3 mb-2 px-3 text-uppercase text-white" style="font-size: 0.75rem; font-weight: 600; letter-spacing: 0.5px; opacity: 0.6;">
                    <?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>
                </li>
            <?php continue; endif; ?>

            <?php

            /*
            |--------------------------------------------------------------
            | Active Menu Detection
            |--------------------------------------------------------------
            */

            $itemPath = parse_url(
                rtrim(base_url(), '/') . '/' . ltrim($item['url'], '/'),
                PHP_URL_PATH
            ) ?? '';

            // Capture the full URI from the request (path + query string)
            $currentFullUri = $_SERVER['REQUEST_URI'] ?? '/';

            // Build the full item URL path for comparison (path only, no base)
            $normCurrent = rtrim($currentPath, '/');
            $normItem    = rtrim($itemPath, '/');

            // Build item full URL relative path (with query string if any)
            $itemRelativeUrl = '/' . ltrim($item['url'], '/');

            // Active if: exact full URL match (incl. query string), OR path-prefix sub-page match
            // Guard: $normItem must not be empty to avoid root '/' matching every item
            $isActive =
                ($currentFullUri === $itemRelativeUrl) ||
                ($normCurrent === $normItem && $normItem !== '') ||
                ($normItem !== '' && str_starts_with($normCurrent, $normItem . '/'));

            ?>

            <li>

                <a
                    href="<?= htmlspecialchars(
                        base_url() . $item['url'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    class="<?= $isActive ? 'active' : '' ?>">

                    <i class="bi <?= htmlspecialchars(
                        $item['icon'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"></i>

                    <span>

                        <?= htmlspecialchars(
                            $item['title'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>

                </a>
                

            </li>

        <?php endforeach; ?>

      <!-- /*Tested - specific role- ONLY ADMIN CAN SEE THIS*/
        <?php if ($currentRole === 'Admin'): ?>
                <a href="/users/manage" class="nav-link">
                <i class="bi bi-people"></i> Manage Users - test
                </a>
        <?php endif; ?>
        -->
       
    </ul>

    <!-- =========================================================== -->
    <!-- Sidebar Footer                                              -->
    <!-- =========================================================== -->

    <div class="sidebar-footer mt-auto">

        <a
            href="<?= base_url() ?>/logout"
            class="logout-link">

            <i class="bi bi-box-arrow-right"></i>

            <span>Logout</span>

        </a>

    </div>

</aside>