<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : 403.php
 * Location    : templates/errors/
 * Description : 403 Access Denied error page.
 *
 * This page is rendered by RoleMiddleware when a user attempts
 * to access a resource they are not authorized to view.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Core\Session;

$loggedInUser = Session::get('user', []);
$userRole     = $loggedInUser['role'] ?? 'User';

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>403 — Access Denied | NexusCore EMS</title>

    <link
        rel="stylesheet"
        href="<?= asset('assets/css/bootstrap.min.css') ?>">

    <link
        rel="stylesheet"
        href="<?= asset('assets/icons/bootstrap-icons/font/bootstrap-icons.css') ?>">

    <link
        rel="stylesheet"
        href="<?= asset('assets/css/variables.css') ?>">

    <link
        rel="stylesheet"
        href="<?= asset('assets/css/app.css') ?>">

    <style>
        body {
            background: var(--color-bg, #f4f6fb);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error-card {
            max-width: 480px;
            width: 100%;
            text-align: center;
        }

        .error-icon-wrap {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: rgba(220, 53, 69, 0.10);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .error-code {
            font-size: 4.5rem;
            font-weight: 800;
            line-height: 1;
            color: #dc3545;
            letter-spacing: -2px;
        }
    </style>

</head>

<body>

    <div class="error-card mx-auto px-3">

        <div class="card shadow-sm border-0 p-5">

            <!-- Icon -->

            <div class="error-icon-wrap">

                <i class="bi bi-shield-lock-fill text-danger fs-1"></i>

            </div>

            <!-- Error Code -->

            <div class="error-code mb-1">403</div>

            <!-- Title -->

            <h2 class="fw-bold mb-2">Access Denied</h2>

            <!-- Description -->

            <p class="text-muted mb-4">
                You do not have permission to access this page.
                <br>
                Your current role (<strong><?= htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') ?></strong>)
                is not authorized for this action.
            </p>

            <!-- Actions -->

            <div class="d-flex gap-3 justify-content-center flex-wrap">

                <a
                    href="<?= base_url() ?>/dashboard"
                    class="btn btn-primary px-4">

                    <i class="bi bi-speedometer2 me-2"></i>

                    Back to Dashboard

                </a>

                <a
                    href="<?= base_url() ?>/logout"
                    class="btn btn-outline-secondary px-4">

                    <i class="bi bi-box-arrow-right me-2"></i>

                    Logout

                </a>

            </div>

        </div>

        <!-- Footer note -->

        <p class="text-muted small mt-3 mb-0">
            NexusCore EMS &mdash; If you believe this is an error,
            please contact your Administrator.
        </p>

    </div>

    <script src="<?= asset('assets/js/vendor/bootstrap.bundle.min.js') ?>"></script>

</body>

</html>
