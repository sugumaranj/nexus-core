<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : dashboard.php
 * Location    : templates/student/
 * Description : Student Portal dashboard (Phase B stub).
 *
 * Phase B: Confirms authentication is working.
 * Full widget implementation in Phase C.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Core\Session;

$success = Session::getFlash('success');
$error   = Session::getFlash('error');

?>

<div class="container-fluid py-4 px-4">

    <!-- ======================================================
         Flash Messages
    ====================================================== -->

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- ======================================================
         Welcome Header
    ====================================================== -->

    <div class="d-flex align-items-center justify-content-between mb-4">

        <div>

            <h4 class="fw-bold mb-0">

                Welcome, <?= htmlspecialchars($student['full_name'] ?? 'Student', ENT_QUOTES, 'UTF-8') ?>

            </h4>

            <p class="text-muted mb-0">

                Register Number: <strong><?= htmlspecialchars($student['register_number'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                &nbsp;|&nbsp;
                <?= academic_year_label($student['academic_year'] ?? '') ?>
                &nbsp;|&nbsp;
                Semester <?= (int) ($student['semester'] ?? 0) ?>

            </p>

        </div>

        <span class="badge bg-success fs-6 px-3 py-2">
            <i class="bi bi-shield-check me-1"></i> Authenticated
        </span>

    </div>

    <!-- ======================================================
         Phase B Authentication Confirmation Card
    ====================================================== -->

    <div class="row g-4">

        <div class="col-12">

            <div class="card shadow-sm border-0">

                <div class="card-body p-4">

                    <div class="d-flex align-items-center gap-3 mb-3">

                        <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                            <i class="bi bi-mortarboard-fill text-primary fs-3"></i>
                        </div>

                        <div>
                            <h5 class="fw-bold mb-0">Student Portal — Active</h5>
                            <p class="text-muted mb-0 small">Browse active symposiums, register for events, manage your teams, and track your participation status.</p>
                        </div>

                    </div>

                    <hr>

                    <div class="row g-3">

                        <div class="col-md-6">
                            <a href="<?= base_url() ?>/student/my-registrations" class="text-decoration-none">
                                <div class="card bg-light border-0 text-center py-4 h-100 table-hover transition-all">
                                    <div class="fs-1 text-primary">
                                        <i class="bi bi-clipboard-check"></i>
                                    </div>
                                    <div class="fw-semibold mt-2 text-dark">My Registrations</div>
                                    <div class="badge bg-primary rounded-pill mt-2 px-3"><?= $myRegistrationsCount ?? 0 ?></div>
                                </div>
                            </a>
                        </div>

                        <div class="col-md-6">
                            <a href="<?= base_url() ?>/student/symposiums" class="text-decoration-none">
                                <div class="card bg-light border-0 text-center py-4 h-100 table-hover transition-all">
                                    <div class="fs-1 text-warning">
                                        <i class="bi bi-calendar-event"></i>
                                    </div>
                                    <div class="fw-semibold mt-2 text-dark">Active Symposiums</div>
                                    <div class="badge bg-warning text-dark rounded-pill mt-2 px-3"><?= $activeSymposiumsCount ?? 0 ?></div>
                                </div>
                            </a>
                        </div>


                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- ======================================================
         Important Notices — Upcoming Events with Venue
    ====================================================== -->
    <?php $importantNotices = $importantNotices ?? []; ?>
    <?php if (!empty($importantNotices)): ?>
    <div class="row g-4 mt-2">
        <div class="col-12">
            <h5 class="fw-bold mb-3">
                <i class="bi bi-bell-fill text-danger me-2"></i> Important Notices
                <span class="badge bg-danger ms-2" style="font-size:.75rem;"><?= count($importantNotices) ?></span>
            </h5>
            <div class="row g-3">
                <?php foreach ($importantNotices as $notice): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 border-start border-4 border-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 py-1" style="font-size:.75rem;">
                                    <i class="bi bi-geo-alt-fill me-1"></i>Venue Confirmed
                                </span>
                                <?php $isToday = ($notice['event_date'] === date('Y-m-d')); ?>
                                <?php if ($isToday): ?>
                                <span class="badge bg-success" style="font-size:.7rem;">TODAY</span>
                                <?php else: ?>
                                <small class="text-muted fw-semibold"><?= date('d M', strtotime($notice['event_date'])) ?></small>
                                <?php endif; ?>
                            </div>
                            <h6 class="card-title fw-bold text-dark mb-1">
                                <?= htmlspecialchars($notice['event_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </h6>
                            <p class="card-text text-muted small mb-2">
                                <?= htmlspecialchars($notice['symposium_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </p>
                            <div class="d-flex flex-column gap-1">
                                <?php if (!empty($notice['event_date'])): ?>
                                <div class="small text-muted">
                                    <i class="bi bi-calendar2-event me-1 text-primary"></i>
                                    <?= date('d M Y', strtotime($notice['event_date'])) ?>
                                    <?php if (!empty($notice['start_time']) && !empty($notice['end_time'])): ?>
                                    &bull; <?= substr($notice['start_time'], 0, 5) ?>–<?= substr($notice['end_time'], 0, 5) ?>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <div class="small">
                                    <i class="bi bi-geo-alt me-1 text-danger"></i>
                                    <strong><?= htmlspecialchars($notice['venue_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if (!empty($notice['venue_code'])): ?>
                                    <span class="badge bg-light text-dark border ms-1" style="font-size:.68rem;">
                                        <?= htmlspecialchars($notice['venue_code'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($notice['building_name'])): ?>
                                    <br><span class="text-muted ms-3"><?= htmlspecialchars($notice['building_name'], ENT_QUOTES, 'UTF-8') ?><?= !empty($notice['floor']) ? ', Floor ' . htmlspecialchars($notice['floor'], ENT_QUOTES, 'UTF-8') : '' ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================================================
         Latest Notices (Active Symposiums)
    ====================================================== -->

    <div class="row g-4 mt-2">
        <div class="col-12">
            <h5 class="fw-bold mb-3"><i class="bi bi-megaphone-fill text-primary me-2"></i> Latest Notices</h5>
            
            <?php if (empty($activeSymposiums)): ?>
                <div class="alert alert-light border border-secondary text-center text-muted">
                    No active symposiums or notices available at the moment.
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($activeSymposiums as $symposium): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 shadow-sm border-0 border-start border-4 border-primary">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-1">
                                            <?= htmlspecialchars($symposium['status'] ?? 'Active', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <small class="text-muted fw-semibold"><?= (int)($symposium['academic_year'] ?? 0) ?></small>
                                    </div>
                                    <h5 class="card-title fw-bold text-dark mb-1">
                                        <?= htmlspecialchars($symposium['title'] ?? 'Symposium', ENT_QUOTES, 'UTF-8') ?>
                                    </h5>
                                    <p class="card-text text-muted small mb-2">
                                        <?= htmlspecialchars($symposium['symposium_type'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </p>

                                    <?php if (!empty($symposium['event_start_date'])): ?>
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-calendar2-event me-1 text-primary"></i>
                                            <strong>Event:</strong>
                                            <?= date('d M Y', strtotime($symposium['event_start_date'])) ?>
                                            <?php if (!empty($symposium['event_end_date']) && $symposium['event_end_date'] !== $symposium['event_start_date']): ?>
                                                &ndash; <?= date('d M Y', strtotime($symposium['event_end_date'])) ?>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($symposium['registration_end'])): ?>
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-clock me-1 text-warning"></i>
                                            <strong>Reg. closes:</strong>
                                            <?= date('d M Y', strtotime($symposium['registration_end'])) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <hr class="text-muted opacity-25 my-3">
                                    
                                    <div class="d-flex flex-column gap-2">

                                        <?php
                                            // Check if an uploaded circular file exists on disk
                                            $circularPath = $symposium['circular_path'] ?? '';
                                            $hasCircular  = !empty($circularPath) && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim($circularPath, '/'));
                                        ?>

                                        <?php if ($hasCircular): ?>
                                            <a href="<?= base_url(ltrim($circularPath, '/')) ?>"
                                               class="btn btn-sm btn-outline-danger w-100 text-start" target="_blank">
                                                <i class="bi bi-file-earmark-pdf-fill me-2"></i> Download Circular
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= base_url('/student/symposiums/generate-pdf?id=' . $symposium['symposium_id']) ?>"
                                               class="btn btn-sm btn-outline-danger w-100 text-start" target="_blank">
                                                <i class="bi bi-file-earmark-pdf-fill me-2"></i> Download Official Circular
                                            </a>
                                        <?php endif; ?>

                                        <?php
                                            // Check if an uploaded brochure file exists on disk
                                            $brochurePath = $symposium['brochure_path'] ?? '';
                                            $hasBrochure  = !empty($brochurePath) && file_exists(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim($brochurePath, '/'));
                                        ?>

                                        <?php if ($hasBrochure): ?>
                                            <a href="<?= base_url(ltrim($brochurePath, '/')) ?>"
                                               class="btn btn-sm btn-outline-warning text-dark border-warning w-100 text-start" target="_blank">
                                                <i class="bi bi-file-earmark-image-fill me-2 text-warning"></i> View Brochure
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= base_url('/notice-board?symposium_id=' . $symposium['symposium_id']) ?>"
                                               class="btn btn-sm btn-outline-warning text-dark border-warning w-100 text-start" target="_blank">
                                                <i class="bi bi-file-earmark-text-fill me-2 text-warning"></i> View Event Brochure
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($symposium['status'] ?? '', ['Scheduling Complete', 'Registration Open', 'Registration Closed', 'Completed'])): ?>
                                            <a href="<?= base_url('/student/symposiums/generate-pdf?id=' . $symposium['symposium_id'] . '&type=schedule') ?>"
                                               class="btn btn-sm btn-outline-success w-100 text-start" target="_blank">
                                                <i class="bi bi-calendar-event me-2"></i> <?= !empty($symposium['has_rescheduled']) ? 'Download Revised Schedule' : 'Download Schedule' ?>
                                            </a>
                                        <?php endif; ?>

                                        <a href="<?= base_url('/student/symposiums/events?id=' . $symposium['symposium_id']) ?>"
                                           class="btn btn-sm btn-primary w-100 text-start">
                                            <i class="bi bi-pencil-square me-2"></i> Register for Events
                                        </a>

                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
