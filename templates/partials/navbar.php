<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : navbar.php
 * Location    : templates/partials/
 * Description : Dashboard Top Navigation Bar
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display application title
 * • Display logged-in user information
 * • Display notification button
 * • Display user avatar
 * • Reserved space for future profile/settings dropdown
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Core\Session;

/*
|--------------------------------------------------------------------------
| Logged-in User
|--------------------------------------------------------------------------
|
| Retrieve authenticated user from session.
|
*/

    $loggedInUser = Session::get('user', []);

/*
|--------------------------------------------------------------------------
| User Information
|--------------------------------------------------------------------------
*/
    $fullName = $loggedInUser['full_name'] ?? 'Administrator';

    $role = $loggedInUser['role'] ?? 'Administrator';
?>

<!-- =============================================================== -->
<!-- Dashboard Top Navigation -->
<!-- =============================================================== -->

<nav class="dashboard-navbar">

    <!-- =========================================================== -->
    <!-- Left Section : Application Title -->
    <!-- =========================================================== -->

    <div class="navbar-left">

        <h4 class="fw-bold mb-0">

            <i class="bi bi-mortarboard-fill text-primary me-2"></i>

            NexusCore EMS

        </h4>

    </div>

    <!-- =========================================================== -->
    <!-- Right Section : User Controls -->
    <!-- =========================================================== -->

    <div class="navbar-right d-flex align-items-center">

        <!-- ======================================================= -->
        <!-- Notifications -->
        <!-- ======================================================= -->

        <button
            type="button"
            class="btn btn-light rounded-circle position-relative me-3"
            title="Notifications">

            <i class="bi bi-bell fs-5"></i>

            <!-- Notification Badge -->

            <span
                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">

                0

            </span>

        </button>

        <!-- ======================================================= -->
        <!-- User Information -->
        <!-- ======================================================= -->

        <div class="text-end me-3">

            <div class="fw-semibold">

                <?= htmlspecialchars(
                    $fullName,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

            <small class="text-muted">

                <?= htmlspecialchars(
                    $role,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </small>

        </div>

        <!-- ======================================================= -->
        <!-- User Avatar -->
        <!-- ======================================================= -->

        <div
            class="rounded-circle bg-primary text-white d-flex justify-content-center align-items-center"
            style="width:48px;height:48px;">

            <i class="bi bi-person-fill fs-4"></i>

        </div>

    </div>

</nav>