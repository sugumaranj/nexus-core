<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : login.php
 * Location    : templates/student/
 * Description : Student Portal login page.
 *
 * Layout      : auth  (templates/layouts/auth.php)
 * Controller  : StudentAuthController::showLogin()
 * Route       : GET /student/login
 *
 * Variables Available
 * -------------------------------------------------------------------------
 * $error — Flash error message from Session (or null)
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Core\Session;

$error   = Session::getFlash('error');
$success = Session::getFlash('success');

?>

<div class="container">

    <div class="row justify-content-center align-items-center min-vh-100 py-5">

        <div class="col-lg-5 col-md-7">

            <div class="card login-card shadow-lg">

                <div class="card-body p-5">

                    <!-- ======================================================
                         Logo & Branding
                    ====================================================== -->

                    <div class="text-center mb-4">

                        <i class="bi bi-mortarboard-fill text-primary login-header-icon"
                           style="font-size:70px;"></i>

                        <h2 class="fw-bold mt-3 mb-1">

                            NexusCore

                        </h2>

                        <p class="text-muted mb-0">

                            Student Portal

                        </p>

                    </div>

                    <!-- ======================================================
                         Welcome Message
                    ====================================================== -->

                    <div class="text-center mb-4">

                        <h4 class="fw-semibold">

                            Student Login

                        </h4>

                        <p class="text-muted">

                            Enter your register number and password to continue.

                        </p>

                    </div>

                    <!-- ======================================================
                         Flash Messages
                    ====================================================== -->

                    <?php if ($error): ?>

                        <div class="alert alert-danger alert-dismissible fade show" role="alert">

                            <i class="bi bi-exclamation-triangle-fill me-2"></i>

                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>

                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                        </div>

                    <?php endif; ?>

                    <?php if ($success): ?>

                        <div class="alert alert-success alert-dismissible fade show" role="alert">

                            <i class="bi bi-check-circle-fill me-2"></i>

                            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>

                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

                        </div>

                    <?php endif; ?>

                    <!-- ======================================================
                         Login Form
                         POST → /student/login
                    ====================================================== -->

                    <form
                        id="student-login-form"
                        method="POST"
                        action="<?= base_url(); ?>/student/login"
                        novalidate>

                        <!-- Register Number -->

                        <div class="mb-3">

                            <label
                                for="register_number"
                                class="form-label fw-semibold">

                                Register Number

                            </label>

                            <div class="input-group">

                                <span class="input-group-text">

                                    <i class="bi bi-person-badge-fill"></i>

                                </span>

                                <input
                                    type="text"
                                    id="register_number"
                                    name="register_number"
                                    class="form-control"
                                    placeholder="e.g. C4S35637"
                                    value="<?= htmlspecialchars($_POST['register_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                    autofocus
                                    autocomplete="username">

                            </div>

                        </div>

                        <!-- Password -->

                        <div class="mb-4">

                            <label
                                for="student_password"
                                class="form-label fw-semibold">

                                Password

                            </label>

                            <div class="input-group">

                                <span class="input-group-text">

                                    <i class="bi bi-lock-fill"></i>

                                </span>

                                <input
                                    type="password"
                                    id="student_password"
                                    name="password"
                                    class="form-control"
                                    placeholder="Enter your password"
                                    required
                                    autocomplete="current-password">

                                <button
                                    class="btn btn-outline-secondary"
                                    type="button"
                                    id="toggle-password"
                                    title="Show/Hide Password">

                                    <i class="bi bi-eye-fill" id="toggle-icon"></i>

                                </button>

                            </div>

                        </div>

                        <!-- Submit -->

                        <button
                            type="submit"
                            id="student-login-btn"
                            class="btn btn-primary w-100 py-2">

                            <i class="bi bi-box-arrow-in-right me-2"></i>

                            Login to Student Portal

                        </button>

                    </form>

                    <!-- ======================================================
                         Staff Login Link
                    ====================================================== -->

                    <hr class="my-4">

                    <div class="text-center">

                        <small class="text-muted">

                            Are you a staff member?

                            <a href="<?= base_url(); ?>/login" class="text-decoration-none">

                                Staff Login

                            </a>

                        </small>

                    </div>

                    <!-- ======================================================
                         Footer
                    ====================================================== -->

                    <div class="text-center mt-3">

                        <small class="text-muted">

                            Government Arts and Science College

                            <br>

                            Veerapandi, Theni

                            <br><br>

                            &copy; <?= date('Y'); ?> NexusCore

                        </small>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- Password Toggle Script -->

<script>

    (function () {

        const toggleBtn  = document.getElementById('toggle-password');
        const toggleIcon = document.getElementById('toggle-icon');
        const pwdInput   = document.getElementById('student_password');

        if (toggleBtn && pwdInput && toggleIcon) {

            toggleBtn.addEventListener('click', function () {

                const isPassword = pwdInput.type === 'password';

                pwdInput.type = isPassword ? 'text' : 'password';

                toggleIcon.className = isPassword
                    ? 'bi bi-eye-slash-fill'
                    : 'bi bi-eye-fill';

            });

        }

    })();

</script>
