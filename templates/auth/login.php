<?php

declare(strict_types=1);

use App\Core\Session;

$error = Session::getFlash('error');


/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : login.php
 * Location    : templates/auth/
 * Description : Login page.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */
?>

<div class="container">

    <div class="row justify-content-center align-items-center min-vh-100 py-5">

        <div class="col-lg-5 col-md-7">

            <div class="card login-card shadow-lg">

                <div class="card-body p-5">

                    <!-- Logo -->

                    <div class="text-center mb-4">

                        <i class="bi bi-trophy-fill text-primary login-header-icon"
                           style="font-size:70px;"></i>

                        <h2 class="fw-bold mt-3 mb-1">

                            NexusCore

                        </h2>

                        <p class="text-muted">

                            Academic Symposium Management Platform

                        </p>

                    </div>

                    <!-- Welcome -->

                    <div class="text-center mb-4">

                        <h4 class="fw-semibold">

                            Staff Login

                        </h4>

                        <p class="text-muted">

                            Enter your staff email and password to continue.

                        </p>

                    </div>

                    <!-- Login Form -->

                    <?php if ($error): ?>

                        <div class="alert alert-danger">

                            <?= htmlspecialchars($error) ?>
                        </div>

                    <?php endif; ?>    
                    
                    <form
                        method="POST"
                        action="<?= base_url(); ?>/login">

                        <!-- Email -->

                        <div class="mb-3">

                            <label
                                class="form-label fw-semibold">

                                Email Address

                            </label>

                            <div class="input-group">

                                <span class="input-group-text">

                                    <i class="bi bi-envelope-fill"></i>

                                </span>

                                <input
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    placeholder="Enter your email"
                                    required
                                    autofocus>

                            </div>

                        </div>

                        <!-- Password -->

                        <div class="mb-3">

                            <label
                                class="form-label fw-semibold">

                                Password

                            </label>

                            <div class="input-group">

                                <span class="input-group-text">

                                    <i class="bi bi-lock-fill"></i>

                                </span>

                                <input
                                    type="password"
                                    name="password"
                                    class="form-control"
                                    placeholder="Enter your password"
                                    required>

                            </div>

                        </div>

                        <!-- Remember -->

                        <div
                            class="form-check mb-4">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="remember"
                                name="remember">

                            <label
                                class="form-check-label"
                                for="remember">

                                Remember Me

                            </label>

                        </div>

                        <!-- Login Button -->

                        <button
                            type="submit"
                            class="btn btn-primary w-100 py-2">

                            <i class="bi bi-box-arrow-in-right me-2"></i>

                            Login

                        </button>

                    </form>

                    <!-- Student Login Link -->

                    <hr class="my-4">

                    <div class="text-center">

                        <small class="text-muted">

                            Are you a student?

                            <a href="<?= base_url(); ?>/student/login" class="text-decoration-none">

                                Student Login

                            </a>

                        </small>

                    </div>

                    <!-- Footer -->

                    <hr class="my-4">

                    <div class="text-center">

                        <small class="text-muted">

                            Government Arts and Science College

                            <br>

                            Veerapandi, Theni

                            <br><br>

                            © <?= date('Y'); ?>

                            NexusCore

                        </small>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>