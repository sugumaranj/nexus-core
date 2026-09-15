<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : index.php
 * Location    : templates/home/
 * Description : Landing page of the NexusCore application.
 *
 * NOTE:
 * This file contains only the landing page content.
 * The HTML <head>, CSS and JavaScript files are loaded
 * through templates/layouts/master.php.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

?>

<!-- ==========================================================
     NAVIGATION BAR
=========================================================== -->

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">

    <div class="container">

        <!-- Application Logo -->

        <a class="navbar-brand d-flex align-items-center"
           href="<?= base_url(); ?>">

            <img
                src="<?= asset('assets/images/logo/nexuscore-logo.png'); ?>"
                alt="NexusCore Logo"
                width="55"
                height="55"
                class="me-3">

            <div>

                <h4 class="fw-bold text-primary mb-0">
                    NexusCore
                </h4>

                <small class="text-muted">
                    Academic Symposium Management Platform
                </small>

            </div>

        </a>

        <!-- Mobile Menu Button -->

        <button
            class="navbar-toggler"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavbar">

            <span class="navbar-toggler-icon"></span>

        </button>

        <!-- Navigation Menu -->

        <div class="collapse navbar-collapse"
             id="mainNavbar">

            <ul class="navbar-nav ms-auto align-items-lg-center">

                <li class="nav-item">

                    <a class="nav-link active"
                       href="#">

                        Home

                    </a>

                </li>

                <li class="nav-item">

                    <a class="nav-link"
                       href="#features">

                        Features

                    </a>

                </li>

                <li class="nav-item">

                    <a class="nav-link"
                       href="#about">

                        About

                    </a>

                </li>

                <li class="nav-item ms-lg-3">

                    <a
                        href="<?= base_url(); ?>/login"
                        class="btn btn-outline-primary mb-2 mb-lg-0">

                        <i class="bi bi-person-badge me-2"></i>

                        Staff Login

                    </a>

                </li>

                <li class="nav-item ms-lg-2">

                    <a
                        href="<?= base_url(); ?>/student/login"
                        class="btn btn-primary">

                        <i class="bi bi-mortarboard-fill me-2"></i>

                        Student Login

                    </a>

                </li>

            </ul>

        </div>

    </div>

</nav>

<!-- ==========================================================
     HERO SECTION
=========================================================== -->

<section class="hero-section py-5">

    <div class="container">

        <div class="row align-items-center gy-5">

            <!-- Left Content -->

            <div class="col-lg-6">

                <span class="badge bg-primary mb-3 fs-6">

                    Secure • Intelligent • Scalable

                </span>

                <h1 class="display-4 fw-bold">

                    Manage Academic Symposiums

                    <span class="text-primary">

                        Smarter

                    </span>

                </h1>

                <p class="lead text-muted mt-4">

                    NexusCore is a secure and intelligent academic symposium
                    management platform designed to simplify event planning,
                    competition management, participant registration,
                    offline attendance synchronization,
                    certificate generation,
                    analytics reporting,
                    automated notifications,
                    and chatbot assistance.

                </p>

                <div class="mt-4">

                    <a
                        href="<?= base_url(); ?>/login"
                        class="btn btn-outline-primary btn-lg me-3 mb-2 mb-lg-0">

                        <i class="bi bi-person-badge me-2"></i>

                        Staff Login

                    </a>

                    <a
                        href="<?= base_url(); ?>/student/login"
                        class="btn btn-primary btn-lg me-3">

                        <i class="bi bi-mortarboard-fill me-2"></i>

                        Student Login

                    </a>

                    <a
                        href="#features"
                        class="btn btn-outline-primary btn-lg">

                        Learn More

                    </a>

                </div>

            </div>

            <!-- Right Illustration -->

            <div class="col-lg-6 text-center">

                <img
                    src="<?= asset('assets/images/logo/nexuscore-logo.png'); ?>"
                    alt="NexusCore Logo"
                    class="img-fluid"
                    style="max-width:220px;">

                <h4 class="fw-bold mt-4">

                    NexusCore

                </h4>

                <p class="text-muted">

                    Simplifying Academic Event Management

                </p>

            </div>

        </div>

    </div>

</section>

<!-- ==========================================================
     FEATURES
=========================================================== -->

<section
    id="features"
    class="py-5">

    <div class="container">

        <div class="text-center mb-5">

            <h2 class="fw-bold">

                Platform Features

            </h2>

            <p class="text-muted">

                Everything required to organize a modern academic symposium.

            </p>

        </div>

        <div class="row g-4">

            <!-- Feature 1 -->

            <div class="col-md-6 col-lg-4">

                <div class="card feature-card h-100 border-0 shadow-sm">

                    <div class="card-body text-center p-4">

                        <i class="bi bi-calendar-event fs-1 text-primary"></i>

                        <h5 class="mt-4">

                            Symposium Management

                        </h5>

                        <p>

                            Create and manage symposiums,
                            competitions,
                            venues
                            and schedules efficiently.

                        </p>

                    </div>

                </div>

            </div>

            <!-- Feature 2 -->

            <div class="col-md-6 col-lg-4">

                <div class="card feature-card h-100 border-0 shadow-sm">

                    <div class="card-body text-center p-4">

                        <i class="bi bi-person-check fs-1 text-success"></i>

                        <h5 class="mt-4">

                            Offline Attendance

                        </h5>

                        <p>

                            Record attendance even without internet
                            and synchronize automatically later.

                        </p>

                    </div>

                </div>

            </div>

            <!-- Feature 3 -->

            <div class="col-md-6 col-lg-4">

                <div class="card feature-card h-100 border-0 shadow-sm">

                    <div class="card-body text-center p-4">

                        <i class="bi bi-award fs-1 text-warning"></i>

                        <h5 class="mt-4">

                            Certificate Generation

                        </h5>

                        <p>

                            Automatically generate winner
                            and participation certificates.

                        </p>

                    </div>

                </div>

            </div>

            <!-- Feature 4 -->

            <div class="col-md-6 col-lg-4">

                <div class="card feature-card h-100 border-0 shadow-sm">

                    <div class="card-body text-center p-4">

                        <i class="bi bi-graph-up-arrow fs-1 text-info"></i>

                        <h5 class="mt-4">

                            Analytics Dashboard

                        </h5>

                        <p>

                            Interactive charts and reports
                            for registrations,
                            participation
                            and competition statistics.

                        </p>

                    </div>

                </div>

            </div>

            <!-- Feature 5 -->

            <div class="col-md-6 col-lg-4">

                <div class="card feature-card h-100 border-0 shadow-sm">

                    <div class="card-body text-center p-4">

                        <i class="bi bi-bell fs-1 text-danger"></i>

                        <h5 class="mt-4">

                            Smart Notifications

                        </h5>

                        <p>

                            Automated notification generation
                            for students.

                        </p>

                    </div>

                </div>

            </div>

            <!-- Feature 6 -->

            <div class="col-md-6 col-lg-4">

                <div class="card feature-card h-100 border-0 shadow-sm">

                    <div class="card-body text-center p-4">

                        <i class="bi bi-robot fs-1 text-secondary"></i>

                        <h5 class="mt-4">

                            Smart Chatbot Assistant

                        </h5>

                        <p>

                            Context-aware chatbot to assist
                            students.

                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<!-- ==========================================================
     ABOUT
=========================================================== -->

<section
    id="about"
    class="py-5 bg-light">

    <div class="container text-center">

        <h2 class="fw-bold mb-4">

            About NexusCore

        </h2>

        <p class="lead text-muted">

            NexusCore is developed to provide a secure,
            scalable and intelligent platform
            for managing academic symposiums efficiently.
            The system integrates modern web technologies,
            analytics and intelligent automation
            while maintaining a user-friendly experience.

        </p>

    </div>

</section>

<!-- ==========================================================
     FOOTER
=========================================================== -->

<footer class="bg-dark text-white py-4">

    <div class="container">

        <div class="row">

            <div class="col-md-6">

                <h5>

                    NexusCore

                </h5>

                <p>

                    Academic Symposium Management Platform

                </p>

            </div>

            <div class="col-md-6 text-md-end">

                <p class="mb-1">

                    Government Arts and Science College

                </p>

                <p class="mb-1">

                    Veerapandi, Theni

                </p>

                <small>

                    © <?= date('Y'); ?> NexusCore.
                    All Rights Reserved.

                </small>

            </div>

        </div>

    </div>

</footer>