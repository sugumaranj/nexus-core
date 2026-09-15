<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : dashboard.php
 * Location    : templates/layouts/
 * Description : Master Dashboard Layout
 *
 * This layout is used by all authenticated dashboard pages.
 *
 * It automatically loads:
 * -------------------------------------------------------
 * • Sidebar Navigation
 * • Top Navigation Bar
 * • Flash Alerts (success / error / warning / info)
 * • Page Content
 * • Footer
 * • Bootstrap CSS & JS
 * • Global Application Styles
 * • Dashboard Styles
 *
 * Every dashboard view is rendered inside:
 *      <main class="dashboard-main">
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

/*
|--------------------------------------------------------------------------
| Default Page Title
|--------------------------------------------------------------------------
|
| If a page does not provide a title, use the application name.
|
*/

$pageTitle = $pageTitle ?? config('name');

// Ensure CSRF token exists for the layout
if (!\App\Core\Session::get('_token')) {
    \App\Core\Session::set('_token', bin2hex(random_bytes(32)));
}
$dashboardCsrfToken = \App\Core\Session::get('_token');

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <!-- =============================================================== -->
    <!-- Basic Meta Information -->
    <!-- =============================================================== -->

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge">

    <!-- Prevent browser caching of authenticated pages -->
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">

    <title>

        <?= htmlspecialchars(
            $pageTitle,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($dashboardCsrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="description" content="NexusCore EMS Dashboard">
    <meta name="base-url" content="<?= base_url() ?>">

    <!-- =============================================================== -->
    <!-- Bootstrap CSS -->
    <!-- =============================================================== -->

    <link
        rel="stylesheet"
        href="<?= asset('assets/css/bootstrap.min.css') ?>">

    <!-- =============================================================== -->
    <!-- Bootstrap Icons -->
    <!-- =============================================================== -->

    <link
        rel="stylesheet"
        href="<?= asset('assets/icons/bootstrap-icons/font/bootstrap-icons.css') ?>">

    <!-- =============================================================== -->
    <!-- Global Application Styles -->
    <!-- =============================================================== -->

    <link
        rel="stylesheet"
        href="<?= asset('assets/css/variables.css') ?>">

    <link
        rel="stylesheet"
        href="<?= asset('assets/css/app.css') ?>">

    <!-- =============================================================== -->
    <!-- Dashboard Specific Styles                                     -->
    <!-- =============================================================== -->

    <link
        rel="stylesheet"
        href="<?= asset('assets/css/dashboard.css') ?>">

    <!-- =============================================================== -->
    <!-- Attendance Module Styles                                      -->
    <!-- =============================================================== -->

    <link
        rel="stylesheet"
        href="<?= asset('assets/css/attendance.css') ?>">

    <!-- =============================================================== -->
    <!-- PWA — Web App Manifest & Theme                                -->
    <!-- =============================================================== -->

    <link rel="manifest" href="<?= asset('manifest.json') ?>">

    <meta name="theme-color" content="#1f2937">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="application-name" content="NexusCore">

    <!-- Flatpickr CSS for Date Formatting (local — no CDN dependency) -->
    <link rel="stylesheet" href="<?= asset('assets/css/flatpickr.min.css') ?>">

</head>

<body class="dashboard-body">

<!-- =============================================================== -->
<!-- Preloader -->
<!-- =============================================================== -->
<style>
    #nexus-preloader {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: #f8f9fa; /* Matches dashboard body bg */
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
        transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
    }
    .nexus-loader {
        width: 64px;
        height: 64px;
        position: relative;
        animation: loaderPulse 1.2s ease-in-out infinite;
    }
    .nexus-loader::before, .nexus-loader::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        inset: 0;
        border: 4px solid transparent;
    }
    .nexus-loader::before {
        border-top-color: #0d6efd; /* Primary blue */
        border-bottom-color: #0d6efd;
        animation: loaderSpin 2s linear infinite;
    }
    .nexus-loader::after {
        border-left-color: #6610f2; /* Deep purple */
        border-right-color: #6610f2;
        animation: loaderSpin 1.5s linear infinite reverse;
    }
    @keyframes loaderSpin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    @keyframes loaderPulse {
        0%, 100% { transform: scale(1); filter: drop-shadow(0 0 10px rgba(13, 110, 253, 0.4)); }
        50% { transform: scale(1.1); filter: drop-shadow(0 0 20px rgba(102, 16, 242, 0.6)); }
    }
    .preloader-hidden {
        opacity: 0;
        visibility: hidden;
    }
</style>
<div id="nexus-preloader">
    <div class="nexus-loader"></div>
</div>

<!-- =============================================================== -->
<!-- Dashboard Wrapper -->
<!-- =============================================================== -->

<div class="dashboard-wrapper">

    <!-- =========================================================== -->
    <!-- Sidebar -->
    <!-- =========================================================== -->

    <?php require dirname(__DIR__) . '/partials/sidebar.php'; ?>

    <!-- =========================================================== -->
    <!-- Dashboard Content Area -->
    <!-- =========================================================== -->

    <div class="dashboard-content">

        <!-- ======================================================= -->
        <!-- Top Navigation (Removed - Redundant with Sidebar) -->
        <!-- ======================================================= -->

        <!-- ======================================================= -->
        <!-- Main Page Content -->
        <!-- ======================================================= -->

        <main class="dashboard-main">

            <!-- =================================================== -->
            <!-- Flash Alerts (success / error / warning / info)     -->
            <!-- Centrally rendered here so every page gets alerts.  -->
            <!-- =================================================== -->

            <?php require dirname(__DIR__) . '/partials/alerts.php'; ?>

            <!-- =================================================== -->
            <!-- View Content -->
            <!-- =================================================== -->

            <?php require $contentFile; ?>

        </main>

        <!-- ======================================================= -->
        <!-- Footer -->
        <!-- ======================================================= -->

        <?php require dirname(__DIR__) . '/partials/footer.php'; ?>

    </div>

</div>

<!-- =============================================================== -->
<!-- Bootstrap JavaScript Bundle -->
<!-- =============================================================== -->

<script
    src="<?= asset('assets/js/vendor/bootstrap.bundle.min.js') ?>">
</script>

<!-- =============================================================== -->
<!-- Attendance UI — Service Worker + Network Status + Sync Engine  -->
<!-- =============================================================== -->
    <?php $cacheBust = time(); ?>
    <script src="<?= asset('assets/js/modules/attendance-db.js') ?>?v=<?= $cacheBust ?>"></script>
    <script src="<?= asset('assets/js/modules/attendance-sync.js') ?>?v=<?= $cacheBust ?>"></script>
    <script src="<?= asset('assets/js/modules/attendance-ui.js') ?>?v=<?= $cacheBust ?>"></script>

<!-- Flatpickr JS for Date Formatting (local — no CDN dependency) -->
<script src="<?= asset('assets/js/vendor/flatpickr.min.js') ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        flatpickr('input[type="date"]', {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d/m/Y",
            allowInput: true
        });

        flatpickr('input[type="datetime-local"]', {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            altInput: true,
            altFormat: "d/m/Y h:i K",
            allowInput: true
        });
    });

    // Hide Preloader on DOMContentLoaded for faster perception
    document.addEventListener('DOMContentLoaded', function() {
        const preloader = document.getElementById('nexus-preloader');
        if (preloader) {
            preloader.classList.add('preloader-hidden');
            setTimeout(() => { preloader.style.display = 'none'; }, 500);
        }
    });
</script>

</body>

</html>
