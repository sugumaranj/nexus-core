<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : 404.php
 * Location    : templates/errors/
 * Description : 404 Page Not Found error page.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Core\Session;

$loggedInUser = Session::get('user', []);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>404 — Page Not Found | NexusCore EMS</title>

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
            background: rgba(13, 110, 253, 0.10);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .error-code {
            font-size: 4.5rem;
            font-weight: 800;
            line-height: 1;
            color: #0d6efd;
            letter-spacing: -2px;
        }
    </style>

</head>

<body>

    <div class="error-card mx-auto px-3">

        <div class="card shadow-sm border-0 p-5">

            <div class="error-icon-wrap">

                <i class="bi bi-compass text-primary fs-1"></i>

            </div>

            <div class="error-code mb-1">404</div>

            <h2 class="fw-bold mb-2">Page Not Found</h2>

            <p class="text-muted mb-4">
                The page you are looking for does not exist or has been moved.
                <br>
                Please check the URL or navigate back to the dashboard.
            </p>

            <div class="d-flex gap-3 justify-content-center flex-wrap">

                <?php if (!empty($loggedInUser)): ?>

                    <a
                        href="<?= base_url() ?>/dashboard"
                        class="btn btn-primary px-4">

                        <i class="bi bi-speedometer2 me-2"></i>

                        Back to Dashboard

                    </a>

                <?php else: ?>

                    <a
                        href="<?= base_url() ?>/login"
                        class="btn btn-primary px-4">

                        <i class="bi bi-box-arrow-in-right me-2"></i>

                        Go to Login

                    </a>

                <?php endif; ?>

            </div>

        </div>

        <p class="text-muted small mt-3 mb-0">
            NexusCore EMS &mdash; If you believe this is an error,
            please contact your Administrator.
        </p>

    </div>

    <script src="<?= asset('assets/js/vendor/bootstrap.bundle.min.js') ?>"></script>

</body>

</html>
