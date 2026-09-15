<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : student_coordinator.php
 * Location    : templates/dashboard/
 * Description : Student Coordinator Dashboard
 *
 * Assigned competition and participant management dashboard.
 * Student Coordinators are institutional staff who assist with
 * competition operations — verifying registrations, managing
 * participant lists, and assisting with attendance.
 *
 * Student Coordinators CANNOT modify symposiums or manage users.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Core\Session;

$user  = Session::get('user');
$stats = $stats ?? [];

// Fallback: If session lacks department_name but has department_id, fetch it dynamically.
if (empty($user['department_name']) && !empty($user['department_id'])) {
    $dbDept = (new \App\Models\DepartmentModel())->findById((int)$user['department_id']);
    $user['department_name'] = $dbDept['department_name'] ?? null;
}

$dept = htmlspecialchars($user['department_name'] ?? 'Department', ENT_QUOTES, 'UTF-8');

?>

<!-- ============================================================= -->
<!-- Page Header                                                     -->
<!-- ============================================================= -->

<div class="d-flex justify-content-between align-items-center flex-wrap mb-4">

    <div>

        <h1 class="fw-bold mb-1">

            Welcome,
            <?= htmlspecialchars($user['full_name'] ?? 'Student Coordinator') ?>

        </h1>

        <p class="text-muted mb-0">

            Student Coordinator Dashboard &mdash; <?= $dept ?>

        </p>

    </div>

    <div class="text-muted small">

        <i class="bi bi-clock me-1"></i>

        <?= \App\Helpers\DateHelper::date('now') ?>

    </div>

</div>

<!-- ============================================================= -->
<!-- Pending Registration Alert                                     -->
<!-- ============================================================= -->

<?php if (($stats['pending_registrations'] ?? 0) > 0): ?>

    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">

        <i class="bi bi-exclamation-triangle-fill me-3 fs-5 flex-shrink-0"></i>

        <div>

            <strong><?= $stats['pending_registrations'] ?> registration(s)</strong>
            in your assigned competitions are pending verification.

        </div>

        <a href="<?= base_url() ?>/assigned-competitions" class="btn btn-sm btn-warning ms-auto">
            Review Now
        </a>

    </div>

<?php endif; ?>

<!-- ============================================================= -->
<!-- Statistics                                                      -->
<!-- ============================================================= -->

<div class="row row-cols-1 row-cols-md-2 g-4 mb-5">

    <!-- Assigned Competitions -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Assigned Competitions
                        </small>

                        <h2 class="fw-bold mt-1 mb-0 text-primary">
                            <?= number_format($stats['assigned_competitions'] ?? 0) ?>
                        </h2>

                    </div>

                    <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-trophy text-primary fs-2"></i>
                    </div>

                </div>

                <a href="<?= base_url() ?>/assigned-competitions" class="small text-primary mt-2 d-block">
                    View assigned &rarr;
                </a>

            </div>

        </div>

    </div>

    <!-- Pending Registrations -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Pending Registrations
                        </small>

                        <h2 class="fw-bold mt-1 mb-0 <?= ($stats['pending_registrations'] ?? 0) > 0 ? 'text-warning' : '' ?>">
                            <?= number_format($stats['pending_registrations'] ?? 0) ?>
                        </h2>

                    </div>

                    <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-hourglass-split text-warning fs-2"></i>
                    </div>

                </div>

                <span class="small text-muted mt-2 d-block">
                    Awaiting verification
                </span>

            </div>

        </div>

    </div>

    <!-- Approved Registrations -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Approved Registrations
                        </small>

                        <h2 class="fw-bold mt-1 mb-0 text-success">
                            <?= number_format($stats['approved_registrations'] ?? 0) ?>
                        </h2>

                    </div>

                    <div class="bg-success bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-clipboard-check text-success fs-2"></i>
                    </div>

                </div>

                <span class="small text-muted mt-2 d-block">
                    Confirmed participants
                </span>

            </div>

        </div>

    </div>

    <!-- Total Participants -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Total Participants
                        </small>

                        <h2 class="fw-bold mt-1 mb-0">
                            <?= number_format($stats['total_participants'] ?? 0) ?>
                        </h2>

                    </div>

                    <div class="bg-info bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-people text-info fs-2"></i>
                    </div>

                </div>

                <span class="small text-muted mt-2 d-block">
                    Unique students registered
                </span>

            </div>

        </div>

    </div>

</div>

<!-- ============================================================= -->
<!-- Quick Access                                                    -->
<!-- ============================================================= -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white border-0 pt-3 pb-2">

        <h5 class="mb-0 fw-semibold">

            <i class="bi bi-grid me-2 text-secondary"></i>

            Quick Access

        </h5>

    </div>

    <div class="card-body">

        <div class="row g-3">

            <div class="col-md-6 col-lg-3">

                <a href="<?= base_url() ?>/assigned-competitions"
                   class="btn btn-outline-primary w-100 py-3">

                    <i class="bi bi-trophy fs-4 d-block mb-2"></i>

                    Assigned Competitions

                </a>

            </div>

            <div class="col-md-6 col-lg-3">

                <a href="<?= base_url() ?>/competitions"
                   class="btn btn-outline-warning w-100 py-3">

                    <i class="bi bi-people fs-4 d-block mb-2"></i>

                    Participant List

                </a>

            </div>

            <div class="col-md-6 col-lg-3">

                <a href="<?= base_url() ?>/competitions"
                   class="btn btn-outline-success w-100 py-3">

                    <i class="bi bi-clipboard-check fs-4 d-block mb-2"></i>

                    Verify Registrations

                </a>

            </div>

            <div class="col-md-6 col-lg-3">

                <a href="<?= base_url() ?>/competitions"
                   class="btn btn-outline-secondary w-100 py-3">

                    <i class="bi bi-calendar3 fs-4 d-block mb-2"></i>

                    Competition Schedule

                </a>

            </div>

        </div>

    </div>

</div>

<!-- ============================================================= -->
<!-- Notifications Placeholder                                      -->
<!-- ============================================================= -->

<div class="card shadow-sm border-0">

    <div class="card-header bg-white border-0 pt-3 pb-2">

        <h5 class="mb-0 fw-semibold">

            <i class="bi bi-bell me-2 text-secondary"></i>

            Notifications

        </h5>

    </div>

    <div class="card-body text-center text-muted py-5">

        <i class="bi bi-bell-slash fs-1 mb-3 d-block"></i>

        <p class="mb-0">

            No new notifications.

        </p>

    </div>

</div>
