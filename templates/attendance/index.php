<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : index.php
 * Location    : templates/attendance/
 * Description : Attendance Overview — list of competitions with attendance stats.
 * -------------------------------------------------------------------------
 */

$events    = $events    ?? [];
$role         = $role         ?? '';
$csrfToken    = $csrfToken    ?? '';
?>

<!-- ======================================================= -->
<!-- Network Status Bar                                       -->
<!-- ======================================================= -->
<div id="network-status-bar" class="network-status-bar network-checking">
    <i class="bi bi-wifi me-1" id="network-icon"></i>
    <span id="network-text">Online</span>
    <span class="ms-3 text-muted" id="last-sync-text" style="font-size:0.75rem;"></span>
    <span class="ms-auto me-2" id="pending-badge-bar" style="display:none;">
        <span class="badge bg-warning text-dark">
            <i class="bi bi-clock-history me-1"></i>
            <span id="pending-count-bar">0</span> Pending
        </span>
    </span>
</div>

<!-- ======================================================= -->
<!-- Page Header                                              -->
<!-- ======================================================= -->
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h2 class="fw-bold mb-1">
            <i class="bi bi-calendar2-check text-primary me-2"></i>Attendance
        </h2>
        <p class="text-muted mb-0">
            Manage attendance for your assigned events.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url() ?>/dashboard" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <a href="<?= base_url() ?>/sync-center" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-repeat me-1"></i>Sync Center
        </a>
    </div>
</div>

<!-- ======================================================= -->
<!-- Competitions Grid                                        -->
<!-- ======================================================= -->
<div class="row g-4">

<?php if (empty($events)): ?>
    <div class="col-12 text-center py-5">
        <div class="text-muted">
            <i class="bi bi-inbox fs-1 mb-3 d-block"></i>
            <h4>No Events Found</h4>
            <p>You are not assigned to any events for attendance management.</p>
        </div>
    </div>
<?php else: ?>

    <?php foreach ($events as $event):
        $eid      = (int) $event['symposium_event_id'];
        $summary  = $event['attendance_summary'] ?? [];
        $hasSession = (bool) $event['has_active_session'];
        $totalApproved = (int) ($summary['total_registered'] ?? 0);
        $present  = (int) ($summary['present'] ?? 0);
        $absent   = (int) ($summary['absent'] ?? 0);
        $late     = (int) ($summary['late'] ?? 0);
        $pct      = (float) ($summary['attendance_pct'] ?? 0);

        $statusClasses = [
            'Draft'               => 'secondary',
            'Scheduled'           => 'info',
            'Registration Open'   => 'primary',
            'Registration Closed' => 'warning',
            'Running'             => 'success',
            'Completed'           => 'dark',
            'Cancelled'           => 'danger',
        ];
        $statusBadge = $statusClasses[$event['status'] ?? ''] ?? 'secondary';
        $canOpenSession = in_array($event['status'] ?? '', ['Registration Closed','Running','Completed'], true);
    ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100 border-0 shadow-sm attendance-event-card" id="event-card-<?= $eid ?>">

            <!-- Card Header -->
            <div class="card-header bg-white border-bottom pt-4 pb-3 px-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1 me-2">
                        <span class="badge bg-<?= $statusBadge ?> mb-2">
                            <?= htmlspecialchars($event['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php if ($hasSession): ?>
                            <span class="badge bg-success ms-1 mb-2" id="live-badge-<?= $eid ?>">
                                <i class="bi bi-circle-fill" style="font-size:0.5rem;"></i> Live
                            </span>
                        <?php endif; ?>
                        <h6 class="fw-bold mb-1">
                            <?= htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </h6>
                        <p class="text-muted small mb-0">
                            <?= htmlspecialchars($event['event_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                        <p class="text-muted small mb-0">
                            <?= htmlspecialchars($event['symposium_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                    <div class="attendance-circle <?= $hasSession ? 'attendance-circle-active' : '' ?>" id="circle-<?= $eid ?>">
                        <span id="circle-pct-<?= $eid ?>"><?= $pct ?>%</span>
                    </div>
                </div>
            </div>

            <!-- Card Body -->
            <div class="card-body px-4 py-3">

                <!-- Attendance Stats -->
                <div class="row g-2 text-center mb-3">
                    <div class="col-3">
                        <div class="stat-chip stat-chip-primary">
                            <div class="stat-chip-val" id="stat-total-<?= $eid ?>"><?= $totalApproved ?></div>
                            <div class="stat-chip-lbl">Total</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-chip stat-chip-success">
                            <div class="stat-chip-val" id="stat-present-<?= $eid ?>"><?= $present ?></div>
                            <div class="stat-chip-lbl">Present</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-chip stat-chip-danger">
                            <div class="stat-chip-val" id="stat-absent-<?= $eid ?>"><?= $absent ?></div>
                            <div class="stat-chip-lbl">Absent</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="stat-chip stat-chip-warning">
                            <div class="stat-chip-val" id="stat-late-<?= $eid ?>"><?= $late ?></div>
                            <div class="stat-chip-lbl">Late</div>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <?php if ($totalApproved > 0): ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Attendance Rate</small>
                        <small class="fw-semibold" id="bar-pct-<?= $eid ?>"><?= $pct ?>%</small>
                    </div>
                    <div class="progress" style="height:6px;">
                        <div class="progress-bar bg-<?= $pct >= 75 ? 'success' : ($pct >= 50 ? 'warning' : 'danger') ?>"
                             id="bar-width-<?= $eid ?>"
                             style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- Card Footer -->
            <div class="card-footer bg-light border-top-0 px-4 py-3">
                <div class="d-grid gap-2">
                    <a href="<?= base_url() ?>/attendance/event?id=<?= $eid ?>"
                       id="mark-btn-<?= $eid ?>"
                       class="btn btn-primary btn-sm">
                        <i class="bi bi-calendar2-check me-1"></i>
                        <span id="mark-btn-text-<?= $eid ?>"><?= $hasSession ? 'Mark Attendance (Live)' : 'Open Attendance' ?></span>
                    </a>
                    <div class="btn-group w-100">
                        <a href="<?= base_url() ?>/attendance/history?id=<?= $eid ?>"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-clock-history"></i> History
                        </a>
                        <a href="<?= base_url() ?>/attendance/report?id=<?= $eid ?>"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-bar-chart"></i> Report
                        </a>
                        <a href="<?= base_url() ?>/attendance/export?id=<?= $eid ?>"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-file-earmark-pdf"></i> PDF
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <?php endforeach; ?>

<?php endif; ?>
</div>

<script src="<?= base_url() ?>/public/assets/js/modules/attendance-overview.js"></script>
