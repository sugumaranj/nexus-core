<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : symposiums.php
 * Location    : templates/student/
 * Description : Lists all active symposiums for students.
 *
 * Variables:
 * - $symposiums (array)
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Core\Session;

$error = Session::getFlash('error');
?>

<div class="container-fluid py-4 px-4">

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="fw-bold mb-0">Active Symposiums</h4>
            <p class="text-muted mb-0">Select a symposium to browse and register for events.</p>
        </div>
    </div>

    <?php if (empty($symposiums)): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-5 text-center text-muted">
                <i class="bi bi-calendar-x fs-1 mb-3 d-block"></i>
                <h5>No Active Symposiums</h5>
                <p class="mb-0">There are currently no active symposiums accepting registrations.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($symposiums as $s): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body p-4 d-flex flex-column">
                            
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="fw-bold mb-0 text-primary">
                                    <?= htmlspecialchars($s['title'], ENT_QUOTES, 'UTF-8') ?>
                                </h5>
                                <span class="badge bg-success">Active</span>
                            </div>

                            <p class="text-muted small flex-grow-1">
                                <?= nl2br(htmlspecialchars($s['description'] ?? 'Join the most exciting tech festival of the year.', ENT_QUOTES, 'UTF-8')) ?>
                            </p>

                            <hr>

                            <div class="mb-3 small">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="text-muted"><i class="bi bi-calendar me-1"></i> Start Date:</span>
                                    <span class="fw-medium"><?= \App\Helpers\DateHelper::date($s['event_start_date']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted"><i class="bi bi-clock-history me-1"></i> Reg Closes:</span>
                                    <span class="fw-medium text-danger"><?= \App\Helpers\DateHelper::dateTime($s['registration_end']) ?></span>
                                </div>
                            </div>

                            <a href="<?= base_url() ?>/student/symposiums/events?id=<?= $s['symposium_id'] ?>" class="btn btn-primary w-100 mt-auto">
                                View Events <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
