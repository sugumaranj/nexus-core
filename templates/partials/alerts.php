<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : alerts.php
 * Location    : templates/partials/
 * Description : Centralized flash message display partial.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Read and display 'success' flash message (cleared after display)
 * • Read and display 'error'   flash message (cleared after display)
 * • Read and display 'warning' flash message (cleared after display)
 * • Read and display 'info'    flash message (cleared after display)
 *
 * Usage
 * -------------------------------------------------------------------------
 * Included once in the dashboard layout (dashboard.php) so that every
 * authenticated page automatically shows flash messages after redirects.
 *
 * IMPORTANT
 * -------------------------------------------------------------------------
 * Flash messages are consumed here and CLEARED from session automatically
 * by Session::getFlash().  Individual view templates must NOT call
 * Session::getFlash() again for the same keys — they will get null.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Core\Session;

/*
|--------------------------------------------------------------------------
| Read Flash Messages
|--------------------------------------------------------------------------
*/

$_flash_success = Session::getFlash('success');
$_flash_error   = Session::getFlash('error');
$_flash_warning = Session::getFlash('warning');
$_flash_info    = Session::getFlash('info');

?>

<?php if ($_flash_success): ?>

    <div
        class="alert alert-success alert-dismissible fade show mb-3"
        role="alert">

        <i class="bi bi-check-circle-fill me-2"></i>

        <?= htmlspecialchars((string) $_flash_success, ENT_QUOTES, 'UTF-8') ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close">
        </button>

    </div>

<?php endif; ?>

<?php if ($_flash_error): ?>

    <div
        class="alert alert-danger alert-dismissible fade show mb-3"
        role="alert">

        <i class="bi bi-exclamation-triangle-fill me-2"></i>

        <?= htmlspecialchars((string) $_flash_error, ENT_QUOTES, 'UTF-8') ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close">
        </button>

    </div>

<?php endif; ?>

<?php if ($_flash_warning): ?>

    <div
        class="alert alert-warning alert-dismissible fade show mb-3"
        role="alert">

        <i class="bi bi-exclamation-circle-fill me-2"></i>

        <?= htmlspecialchars((string) $_flash_warning, ENT_QUOTES, 'UTF-8') ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close">
        </button>

    </div>

<?php endif; ?>

<?php if ($_flash_info): ?>

    <div
        class="alert alert-info alert-dismissible fade show mb-3"
        role="alert">

        <i class="bi bi-info-circle-fill me-2"></i>

        <?= htmlspecialchars((string) $_flash_info, ENT_QUOTES, 'UTF-8') ?>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="alert"
            aria-label="Close">
        </button>

    </div>

<?php endif; ?>
