<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : principal.php
 * Location    : templates/dashboard/
 * Description : Principal Dashboard — Production Ready
 *
 * Sections
 * -------------------------------------------------------------------------
 * 1. Page Header        — Welcome banner with date/time
 * 2. Urgent Alert       — Flashes when approvals are pending
 * 3. Stats Strip        — 6 key metric cards (symposium-workflow focused)
 * 4. Pending Approvals  — Actionable table of symposiums awaiting decision
 * 5. Recent Approvals   — Last 5 symposiums approved by this Principal
 * 6. Quick Navigation   — Icon tiles matching the sidebar menu
 *
 * NOTE: The Principal has NO access to /competitions or /applications.
 *       Those routes are for Staff Coordinators and Student Coordinators.
 *       All stats and links here are scoped to the symposium approval
 *       workflow and read-only institutional overview.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Core\Session;
use App\Helpers\DateHelper;

$user                       = Session::get('user');
$stats                      = $stats ?? [];
$pendingSymposiums          = $pendingSymposiums ?? [];
$recentlyApprovedSymposiums = $recentlyApprovedSymposiums ?? [];

$pendingCount  = $stats['pending_final_approval'] ?? 0;
$rejectedCount = $stats['rejected_symposiums'] ?? 0;

/**
 * Helper — render a Bootstrap status badge for a symposium status string.
 */
function principalStatusBadge(string $status): string
{
    $map = [
        'Draft'                        => 'secondary',
        'Submitted'                    => 'info',
        'Pending HOD Approval'         => 'warning',
        'Pending Principal Approval'   => 'warning',
        'Rejected by HOD'              => 'danger',
        'Rejected by Principal'        => 'danger',
        'Approved'                     => 'success',
        'Scheduling Complete'          => 'success',
        'Registration Open'            => 'success',
        'Registration Closed'          => 'primary',
        'Completed'                    => 'dark',
        'Cancelled'                    => 'danger',
    ];

    $colour = $map[$status] ?? 'secondary';

    return '<span class="badge bg-' . htmlspecialchars($colour, ENT_QUOTES, 'UTF-8') . '">'
         . htmlspecialchars($status, ENT_QUOTES, 'UTF-8')
         . '</span>';
}

?>

<!-- ============================================================= -->
<!-- 1. Page Header                                                 -->
<!-- ============================================================= -->

<div class="d-flex justify-content-between align-items-center flex-wrap mb-4">

    <div>
        <h1 class="fw-bold mb-1">
            Welcome, <?= htmlspecialchars($user['full_name'] ?? 'Principal') ?>
        </h1>
        <p class="text-muted mb-0">
            Principal Dashboard &mdash; Symposium Approval &amp; Institutional Overview
        </p>
    </div>

    <div class="text-muted small d-flex align-items-center gap-2">
        <i class="bi bi-clock"></i>
        <span><?= DateHelper::date('now') ?></span>
    </div>

</div>

<!-- ============================================================= -->
<!-- 2. Urgent Approval Alert                                       -->
<!-- ============================================================= -->

<?php if ($pendingCount > 0): ?>

    <div class="alert alert-warning d-flex align-items-center justify-content-between mb-4 py-3 px-4" role="alert" id="principal-approval-alert">

        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
            <div>
                <strong><?= $pendingCount ?> symposium<?= $pendingCount > 1 ? 's' : '' ?></strong>
                <?= $pendingCount > 1 ? 'are' : 'is' ?> awaiting your final approval.
                Review them in the table below.
            </div>
        </div>

        <a href="<?= base_url() ?>/symposiums?quick_filter=Pending+Principal+Approval"
           class="btn btn-sm btn-warning ms-3 flex-shrink-0">
            <i class="bi bi-check2-all me-1"></i> Review All
        </a>

    </div>

<?php endif; ?>

<!-- ============================================================= -->
<!-- 3. Stats Strip — Symposium workflow only (no competitions)     -->
<!-- ============================================================= -->

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 mb-4">

    <!-- Pending Final Approvals -->
    <div class="col">
        <a href="<?= base_url() ?>/symposiums?quick_filter=Pending+Principal+Approval" class="text-decoration-none">
            <div class="card border-0 h-100 <?= $pendingCount > 0 ? 'border-start border-4 border-warning' : '' ?>">
                <div class="card-body text-center py-3 px-2">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2 bg-warning bg-opacity-10" style="width:44px;height:44px;">
                        <i class="bi bi-hourglass-split text-warning fs-5"></i>
                    </div>
                    <div class="fw-bold fs-4 <?= $pendingCount > 0 ? 'text-warning' : '' ?>"><?= number_format($pendingCount) ?></div>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.04em;">Pending&nbsp;Approvals</small>
                </div>
            </div>
        </a>
    </div>

    <!-- Approved Symposiums -->
    <div class="col">
        <a href="<?= base_url() ?>/symposiums?quick_filter=Approved" class="text-decoration-none">
            <div class="card border-0 h-100">
                <div class="card-body text-center py-3 px-2">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2 bg-success bg-opacity-10" style="width:44px;height:44px;">
                        <i class="bi bi-calendar-check text-success fs-5"></i>
                    </div>
                    <div class="fw-bold fs-4 text-success"><?= number_format($stats['approved_symposiums'] ?? 0) ?></div>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.04em;">Approved</small>
                </div>
            </div>
        </a>
    </div>

    <!-- Total Symposiums -->
    <div class="col">
        <a href="<?= base_url() ?>/symposiums" class="text-decoration-none">
            <div class="card border-0 h-100">
                <div class="card-body text-center py-3 px-2">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2 bg-primary bg-opacity-10" style="width:44px;height:44px;">
                        <i class="bi bi-calendar-event text-primary fs-5"></i>
                    </div>
                    <div class="fw-bold fs-4"><?= number_format($stats['total_symposiums'] ?? 0) ?></div>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.04em;">Total&nbsp;Symposiums</small>
                </div>
            </div>
        </a>
    </div>

    <!-- Rejected Symposiums -->
    <div class="col">
        <div class="card border-0 h-100 <?= $rejectedCount > 0 ? 'border-start border-4 border-danger' : '' ?>">
            <div class="card-body text-center py-3 px-2">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2 bg-danger bg-opacity-10" style="width:44px;height:44px;">
                    <i class="bi bi-x-circle text-danger fs-5"></i>
                </div>
                <div class="fw-bold fs-4 <?= $rejectedCount > 0 ? 'text-danger' : '' ?>"><?= number_format($rejectedCount) ?></div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.04em;">Rejected</small>
            </div>
        </div>
    </div>

    <!-- Active Departments -->
    <div class="col">
        <div class="card border-0 h-100">
            <div class="card-body text-center py-3 px-2">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2 bg-secondary bg-opacity-10" style="width:44px;height:44px;">
                    <i class="bi bi-building text-secondary fs-5"></i>
                </div>
                <div class="fw-bold fs-4"><?= number_format($stats['total_departments'] ?? 0) ?></div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.04em;">Departments</small>
            </div>
        </div>
    </div>

    <!-- Students -->
    <div class="col">
        <div class="card border-0 h-100">
            <div class="card-body text-center py-3 px-2">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-2 bg-info bg-opacity-10" style="width:44px;height:44px;">
                    <i class="bi bi-people text-info fs-5"></i>
                </div>
                <div class="fw-bold fs-4"><?= number_format($stats['total_students'] ?? 0) ?></div>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:.68rem;letter-spacing:.04em;">Students</small>
            </div>
        </div>
    </div>

</div>

<!-- ============================================================= -->
<!-- 4. Pending Approvals Table                                     -->
<!-- ============================================================= -->

<div class="card border-0 mb-4">

    <div class="card-header d-flex align-items-center justify-content-between py-3">

        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-check2-all text-warning fs-5"></i>
            <h5 class="mb-0 fw-semibold">Pending Final Approvals</h5>
            <?php if ($pendingCount > 0): ?>
                <span class="badge bg-warning text-dark"><?= $pendingCount ?></span>
            <?php endif; ?>
        </div>

        <a href="<?= base_url() ?>/symposiums?quick_filter=Pending+Principal+Approval"
           class="btn btn-sm btn-outline-warning">
            <i class="bi bi-arrow-right me-1"></i> View All
        </a>

    </div>

    <div class="card-body p-0">

        <?php if (empty($pendingSymposiums)): ?>

            <div class="text-center text-muted py-5">
                <i class="bi bi-check-circle fs-1 d-block mb-3 text-success"></i>
                <p class="mb-0 fw-semibold">No symposiums are currently awaiting your approval.</p>
                <small>All pending items will appear here when submitted.</small>
            </div>

        <?php else: ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="principal-pending-table">

                    <thead class="table-light">
                        <tr>
                            <th style="width:130px;">Code</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Organizing Dept.</th>
                            <th>Submitted</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($pendingSymposiums as $sym): ?>

                            <tr>
                                <td>
                                    <code class="text-primary"><?= htmlspecialchars($sym['symposium_code'] ?? '—', ENT_QUOTES, 'UTF-8') ?></code>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= htmlspecialchars($sym['title'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if (!empty($sym['academic_year'])): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars((string)$sym['academic_year'], ENT_QUOTES, 'UTF-8') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($sym['symposium_type'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?= htmlspecialchars($sym['organizing_departments'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="text-muted small">
                                    <?php
                                    if (!empty($sym['submitted_at'])) {
                                        try {
                                            $dt = new DateTimeImmutable($sym['submitted_at']);
                                            echo htmlspecialchars($dt->format('d M Y'), ENT_QUOTES, 'UTF-8');
                                        } catch (Throwable) {
                                            echo htmlspecialchars($sym['submitted_at'], ENT_QUOTES, 'UTF-8');
                                        }
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?= principalStatusBadge($sym['status'] ?? '') ?>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="<?= base_url() ?>/symposiums/<?= (int)$sym['symposium_id'] ?>"
                                       class="btn btn-sm btn-outline-primary me-1" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= base_url() ?>/symposiums/<?= (int)$sym['symposium_id'] ?>/approve"
                                       class="btn btn-sm btn-warning" title="Review & Approve">
                                        <i class="bi bi-check2-all me-1"></i>Review
                                    </a>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>
            </div>

        <?php endif; ?>

    </div>

</div>

<!-- ============================================================= -->
<!-- 5. Recently Approved Symposiums                                -->
<!-- ============================================================= -->

<div class="card border-0 mb-4">

    <div class="card-header d-flex align-items-center justify-content-between py-3">

        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-calendar-check text-success fs-5"></i>
            <h5 class="mb-0 fw-semibold">Recently Approved</h5>
        </div>

        <a href="<?= base_url() ?>/symposiums?quick_filter=Approved"
           class="btn btn-sm btn-outline-success">
            <i class="bi bi-arrow-right me-1"></i> View All Approved
        </a>

    </div>

    <div class="card-body p-0">

        <?php if (empty($recentlyApprovedSymposiums)): ?>

            <div class="text-center text-muted py-5">
                <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
                <p class="mb-0">No symposiums have been approved yet.</p>
            </div>

        <?php else: ?>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="principal-approved-table">

                    <thead class="table-light">
                        <tr>
                            <th style="width:130px;">Code</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Organizing Dept.</th>
                            <th>Approved On</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($recentlyApprovedSymposiums as $sym): ?>

                            <tr>
                                <td>
                                    <code class="text-success"><?= htmlspecialchars($sym['symposium_code'] ?? '—', ENT_QUOTES, 'UTF-8') ?></code>
                                </td>
                                <td>
                                    <a href="<?= base_url() ?>/symposiums/<?= (int)$sym['symposium_id'] ?>"
                                       class="fw-semibold text-decoration-none text-dark">
                                        <?= htmlspecialchars($sym['title'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= htmlspecialchars($sym['symposium_type'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?= htmlspecialchars($sym['organizing_departments'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="text-muted small">
                                    <?php
                                    if (!empty($sym['approved_at'])) {
                                        try {
                                            $dt = new DateTimeImmutable($sym['approved_at']);
                                            echo htmlspecialchars($dt->format('d M Y, g:i A'), ENT_QUOTES, 'UTF-8');
                                        } catch (Throwable) {
                                            echo htmlspecialchars($sym['approved_at'], ENT_QUOTES, 'UTF-8');
                                        }
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?= principalStatusBadge($sym['status'] ?? '') ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>
            </div>

        <?php endif; ?>

    </div>

</div>

<!-- ============================================================= -->
<!-- 6. Quick Navigation — mirrors the principal's sidebar exactly  -->
<!-- ============================================================= -->

<div class="card border-0">

    <div class="card-header py-3">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-grid-1x2 text-secondary fs-5"></i>
            <h5 class="mb-0 fw-semibold">Quick Navigation</h5>
        </div>
    </div>

    <div class="card-body">

        <div class="row g-3 justify-content-start">

            <!-- Final Approvals -->
            <div class="col-6 col-md-4 col-xl-2">
                <a href="<?= base_url() ?>/symposiums?quick_filter=Pending+Principal+Approval"
                   class="text-decoration-none" id="qnav-final-approvals">
                    <div class="border rounded-3 text-center p-3 h-100 qnav-tile <?= $pendingCount > 0 ? 'border-warning' : '' ?>">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2 bg-warning bg-opacity-10 position-relative" style="width:48px;height:48px;">
                            <i class="bi bi-check2-all text-warning fs-5"></i>
                            <?php if ($pendingCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem;">
                                    <?= $pendingCount ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="small fw-semibold text-dark">Final Approvals</div>
                        <div class="text-muted" style="font-size:.72rem;"><?= $pendingCount > 0 ? "$pendingCount pending" : 'All clear' ?></div>
                    </div>
                </a>
            </div>

            <!-- Approved Symposiums -->
            <div class="col-6 col-md-4 col-xl-2">
                <a href="<?= base_url() ?>/symposiums?quick_filter=Approved"
                   class="text-decoration-none" id="qnav-approved-symp">
                    <div class="border rounded-3 text-center p-3 h-100 qnav-tile">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2 bg-success bg-opacity-10" style="width:48px;height:48px;">
                            <i class="bi bi-calendar-check text-success fs-5"></i>
                        </div>
                        <div class="small fw-semibold text-dark">Approved Symposiums</div>
                        <div class="text-muted" style="font-size:.72rem;"><?= number_format($stats['approved_symposiums'] ?? 0) ?> approved</div>
                    </div>
                </a>
            </div>

            <!-- All Symposiums -->
            <div class="col-6 col-md-4 col-xl-2">
                <a href="<?= base_url() ?>/symposiums"
                   class="text-decoration-none" id="qnav-all-symp">
                    <div class="border rounded-3 text-center p-3 h-100 qnav-tile">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2 bg-primary bg-opacity-10" style="width:48px;height:48px;">
                            <i class="bi bi-calendar-event text-primary fs-5"></i>
                        </div>
                        <div class="small fw-semibold text-dark">All Symposiums</div>
                        <div class="text-muted" style="font-size:.72rem;"><?= number_format($stats['total_symposiums'] ?? 0) ?> total</div>
                    </div>
                </a>
            </div>

            <!-- Reports -->
            <div class="col-6 col-md-4 col-xl-2">
                <a href="<?= base_url() ?>/reports"
                   class="text-decoration-none" id="qnav-reports">
                    <div class="border rounded-3 text-center p-3 h-100 qnav-tile">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2 bg-info bg-opacity-10" style="width:48px;height:48px;">
                            <i class="bi bi-bar-chart text-info fs-5"></i>
                        </div>
                        <div class="small fw-semibold text-dark">Reports</div>
                        <div class="text-muted" style="font-size:.72rem;">Analytics &amp; data</div>
                    </div>
                </a>
            </div>

        </div>

    </div>

</div>

<!-- ============================================================= -->
<!-- Hover Enhancement for Quick Nav Tiles                         -->
<!-- ============================================================= -->

<style>
    .qnav-tile {
        transition: all .22s ease;
        background: #fff;
    }
    .qnav-tile:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,.10);
        border-color: #3b82f6 !important;
    }
    #principal-pending-table th,
    #principal-approved-table th {
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #64748b;
    }
</style>
