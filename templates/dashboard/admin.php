<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : admin.php
 * Location    : templates/dashboard/
 * Description : Administrator Dashboard
 *
 * Redesigned with 9 separate stat cards per role, plus event stats.
 * Admin cannot create symposiums — button removed from Quick Actions.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Core\Session;

$user  = Session::get('user');
$stats = $stats ?? [];

?>

<!-- ============================================================= -->
<!-- Page Header                                                     -->
<!-- ============================================================= -->

<div class="d-flex justify-content-between align-items-center flex-wrap mb-4">

    <div>

        <h1 class="fw-bold mb-1">

            Welcome,
            <?= htmlspecialchars($user['full_name'] ?? 'Administrator') ?>

        </h1>

        <p class="text-muted mb-0">

            Administrator Dashboard &mdash; Full System Access

        </p>

    </div>

    <div class="text-muted small">

        <i class="bi bi-clock me-1"></i>

        <?= \App\Helpers\DateHelper::date('now') ?>

    </div>

</div>

<!-- ============================================================= -->
<!-- Row 1 — Organisational Overview                               -->
<!-- ============================================================= -->

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 mb-4">

    <!-- Total Departments -->
    <div class="col">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Departments
                        </small>
                        <h2 class="fw-bold mt-1 mb-0">
                            <?= number_format($stats['total_departments'] ?? 0) ?>
                        </h2>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-building text-primary fs-2"></i>
                    </div>
                </div>
                <a href="<?= base_url() ?>/departments" class="small text-primary mt-2 d-block">View all &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Total Users -->
    <div class="col">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Total Users
                        </small>
                        <h2 class="fw-bold mt-1 mb-0">
                            <?= number_format($stats['total_users'] ?? 0) ?>
                        </h2>
                    </div>
                    <div class="bg-info bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-people-fill text-info fs-2"></i>
                    </div>
                </div>
                <a href="<?= base_url() ?>/users" class="small text-info mt-2 d-block">Manage users &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Total Students -->
    <div class="col">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Students
                        </small>
                        <h2 class="fw-bold mt-1 mb-0">
                            <?= number_format($stats['total_students'] ?? 0) ?>
                        </h2>
                    </div>
                    <div class="bg-success bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-person-vcard text-success fs-2"></i>
                    </div>
                </div>
                <a href="<?= base_url() ?>/students" class="small text-success mt-2 d-block">View all &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Pending Approvals -->
    <div class="col">
        <div class="card shadow-sm border-0 h-100 <?= ($stats['pending_approvals'] ?? 0) > 0 ? 'border-start border-4 border-warning' : '' ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Pending Approvals
                        </small>
                        <h2 class="fw-bold mt-1 mb-0 <?= ($stats['pending_approvals'] ?? 0) > 0 ? 'text-warning' : '' ?>">
                            <?= number_format($stats['pending_approvals'] ?? 0) ?>
                        </h2>
                    </div>
                    <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-hourglass-split text-warning fs-2"></i>
                    </div>
                </div>
                <a href="<?= base_url() ?>/symposiums" class="small text-warning mt-2 d-block">Review &rarr;</a>
            </div>
        </div>
    </div>

</div>

<!-- ============================================================= -->
<!-- Row 2 — User Role Breakdown                                    -->
<!-- ============================================================= -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white border-0 pt-3 pb-2">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-people-fill text-info me-2"></i>
            User Role Breakdown
        </h5>
    </div>

    <div class="card-body">

        <div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3">

            <!-- HODs -->
            <div class="col">
                <div class="text-center border rounded-3 p-3 h-100">
                    <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px;">
                        <i class="bi bi-person-workspace text-primary fs-5"></i>
                    </div>
                    <div class="fw-bold fs-4"><?= number_format($stats['total_hods'] ?? 0) ?></div>
                    <small class="text-muted">HODs</small>
                </div>
            </div>

            <!-- Staff Coordinators -->
            <div class="col">
                <div class="text-center border rounded-3 p-3 h-100">
                    <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px;">
                        <i class="bi bi-person-badge-fill text-success fs-5"></i>
                    </div>
                    <div class="fw-bold fs-4"><?= number_format($stats['total_staff_coordinators'] ?? 0) ?></div>
                    <small class="text-muted">Staff Coordinators</small>
                </div>
            </div>

            <!-- Staff -->
            <div class="col">
                <div class="text-center border rounded-3 p-3 h-100">
                    <div class="bg-secondary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px;">
                        <i class="bi bi-person-fill text-secondary fs-5"></i>
                    </div>
                    <div class="fw-bold fs-4"><?= number_format($stats['total_staff'] ?? 0) ?></div>
                    <small class="text-muted">Staff</small>
                </div>
            </div>

            <!-- Student Coordinators -->
            <div class="col">
                <div class="text-center border rounded-3 p-3 h-100">
                    <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px;">
                        <i class="bi bi-mortarboard-fill text-info fs-5"></i>
                    </div>
                    <div class="fw-bold fs-4"><?= number_format($stats['total_student_coordinators'] ?? 0) ?></div>
                    <small class="text-muted">Student Coordinators</small>
                </div>
            </div>

            <!-- Principal -->
            <div class="col">
                <div class="text-center border rounded-3 p-3 h-100">
                    <div class="bg-dark bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px;">
                        <i class="bi bi-person-circle text-dark fs-5"></i>
                    </div>
                    <div class="fw-bold fs-4"><?= number_format($stats['total_principals'] ?? 0) ?></div>
                    <small class="text-muted">Principals</small>
                </div>
            </div>

            <!-- Admins -->
            <div class="col">
                <div class="text-center border rounded-3 p-3 h-100">
                    <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width:48px;height:48px;">
                        <i class="bi bi-shield-fill-check text-danger fs-5"></i>
                    </div>
                    <div class="fw-bold fs-4"><?= number_format($stats['total_admins'] ?? 0) ?></div>
                    <small class="text-muted">Admins</small>
                </div>
            </div>

        </div>

    </div>

</div>

<!-- ============================================================= -->
<!-- Row 3 — Event Statistics                                        -->
<!-- ============================================================= -->

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 mb-4">

    <!-- Total Symposiums -->
    <div class="col">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Symposiums
                        </small>
                        <h2 class="fw-bold mt-1 mb-0">
                            <?= number_format($stats['total_symposiums'] ?? 0) ?>
                        </h2>
                    </div>
                    <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-calendar-event text-warning fs-2"></i>
                    </div>
                </div>
                <a href="<?= base_url() ?>/symposiums" class="small text-warning mt-2 d-block">View all &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Total Competitions -->
    <div class="col">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Competitions
                        </small>
                        <h2 class="fw-bold mt-1 mb-0">
                            <?= number_format($stats['total_competitions'] ?? 0) ?>
                        </h2>
                    </div>
                    <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-trophy-fill text-danger fs-2"></i>
                    </div>
                </div>
                <a href="<?= base_url() ?>/competitions" class="small text-danger mt-2 d-block">View all &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Total Registrations -->
    <div class="col">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Registrations
                        </small>
                        <h2 class="fw-bold mt-1 mb-0">
                            <?= number_format($stats['total_registrations'] ?? 0) ?>
                        </h2>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-clipboard-check text-primary fs-2"></i>
                    </div>
                </div>
                <span class="small text-muted mt-2 d-block">Competition applications</span>
            </div>
        </div>
    </div>

    <!-- Departments (link card) -->
    <div class="col">
        <div class="card shadow-sm border-0 h-100 bg-light">
            <div class="card-body d-flex flex-column justify-content-center text-center">
                <i class="bi bi-graph-up-arrow fs-1 text-primary mb-2"></i>
                <p class="mb-1 fw-semibold">System Reports</p>
                <a href="<?= base_url() ?>/reports" class="btn btn-sm btn-outline-primary mt-2">
                    View Reports
                </a>
            </div>
        </div>
    </div>

</div>

<!-- ============================================================= -->
<!-- Quick Actions — Admin (No Symposium Create)                    -->
<!-- ============================================================= -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white border-0 pt-3 pb-2">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-lightning-charge-fill text-warning me-2"></i>
            Quick Actions
        </h5>
    </div>

    <div class="card-body">

        <div class="row g-3">

            <div class="col-xl-auto col-lg-auto col-md-6 col-sm-12">
                <a href="<?= base_url() ?>/departments/create"
                   class="btn btn-primary w-100">
                    <i class="bi bi-building-add me-2"></i>
                    Add Department
                </a>
            </div>

            <div class="col-xl-auto col-lg-auto col-md-6 col-sm-12">
                <a href="<?= base_url() ?>/users/create"
                   class="btn btn-success w-100">
                    <i class="bi bi-person-plus-fill me-2"></i>
                    Add User
                </a>
            </div>

            <div class="col-xl-auto col-lg-auto col-md-6 col-sm-12">
                <a href="<?= base_url() ?>/students/create"
                   class="btn btn-outline-success w-100">
                    <i class="bi bi-person-vcard me-2"></i>
                    Add Student
                </a>
            </div>

            <div class="col-xl-auto col-lg-auto col-md-6 col-sm-12">
                <a href="<?= base_url() ?>/symposiums"
                   class="btn btn-outline-warning w-100">
                    <i class="bi bi-calendar-event me-2"></i>
                    View Symposiums
                </a>
            </div>

            <div class="col-xl-auto col-lg-auto col-md-6 col-sm-12">
                <a href="<?= base_url() ?>/reports"
                   class="btn btn-info text-white w-100">
                    <i class="bi bi-bar-chart-line-fill me-2"></i>
                    View Reports
                </a>
            </div>

        </div>

        <div class="alert alert-info border-0 mt-3 mb-0 py-2 px-3 d-flex align-items-center gap-2" style="font-size:.875rem;">
            <i class="bi bi-info-circle-fill text-info"></i>
            <span>
                Symposium creation, editing, and deletion are managed exclusively by
                <strong>Staff Coordinators</strong>.
                Admins have view-only access to symposiums.
            </span>
        </div>

    </div>

</div>

<!-- ============================================================= -->
<!-- Recent Activity Placeholder                                    -->
<!-- ============================================================= -->

<div class="card shadow-sm border-0">

    <div class="card-header bg-white border-0 pt-3 pb-2">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-clock-history me-2 text-secondary"></i>
            Recent Activity
        </h5>
    </div>

    <div class="card-body text-center text-muted py-5">

        <i class="bi bi-clock-history fs-1 mb-3 d-block"></i>

        <p class="mb-0">

            No recent activities available.

        </p>

    </div>

</div>
