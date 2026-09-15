<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : auth.php
 * Location    : templates/layouts/
 * Description : Authentication layout used for all authentication pages.
 *
 * This layout is shared by:
 *  - Login
 *  - Forgot Password
 *  - Reset Password
 *  - Change Password (if required)
 *
 * Variables Expected:
 * -------------------------------------------------------------------------
 * $pageTitle   : Browser page title.
 * $contentFile : Absolute path of the content page to render.
 *
 * Project      : NexusCore
 * -------------------------------------------------------------------------
 */

$pageTitle = $pageTitle ?? config('name');
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Basic Meta Tags -->
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="NexusCore - Academic Symposium Management Platform">

    <meta
        name="author"
        content="Sugumaran J">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <!-- ==========================================================
         Bootstrap CSS
    =========================================================== -->
    <link
        rel="stylesheet"
        href="<?= asset('assets/css/bootstrap.min.css'); ?>">

    <!-- ==========================================================
         Bootstrap Icons
    =========================================================== -->
    <link
        rel="stylesheet"
        href="<?= asset('assets/icons/bootstrap-icons/font/bootstrap-icons.css'); ?>">

    <!-- ==========================================================
         Application Styles
    =========================================================== -->
    <link
        rel="stylesheet"
        href="<?= asset('assets/css/variables.css'); ?>">

    <link
        rel="stylesheet"
        href="<?= asset('assets/css/app.css'); ?>">

    <link
        rel="stylesheet"
        href="<?= asset('assets/css/auth.css'); ?>">

</head>

<body class="login-page">

    <!-- Decorative Animated Background Shapes -->
    <div class="bg-shape shape-1"></div>
    <div class="bg-shape shape-2"></div>
    <div class="bg-shape shape-3"></div>

    <?php

    /*
    |--------------------------------------------------------------------------
    | Page Content
    |--------------------------------------------------------------------------
    |
    | Loads the authentication page (login, forgot password, etc.)
    |
    */

    require $contentFile;

    ?>

    <!-- ==========================================================
         Bootstrap JavaScript Bundle
    =========================================================== -->
    <script src="<?= asset('assets/js/vendor/bootstrap.bundle.min.js'); ?>"></script>

    <!-- ==========================================================
         Application JavaScript
    =========================================================== -->
    <script src="<?= asset('assets/js/app.js'); ?>"></script>

</body>

</html>
