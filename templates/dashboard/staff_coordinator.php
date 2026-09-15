<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : staff_coordinator.php
 * Location    : templates/dashboard/
 * Description : Staff Coordinator Dashboard
 *
 * Symposium and competition management dashboard.
 * Staff Coordinators create symposiums, plan competitions,
 * assign coordinators, and manage the approval workflow.
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
            <?= htmlspecialchars($user['full_name'] ?? 'Staff Coordinator') ?>

        </h1>

        <p class="text-muted mb-0">

            Staff Coordinator Dashboard &mdash; <?= $dept ?>

        </p>

    </div>

    <div class="text-muted small">

        <i class="bi bi-clock me-1"></i>

        <?= \App\Helpers\DateHelper::date('now') ?>

    </div>

</div>

<!-- ============================================================= -->
<!-- Pending Approvals Alert                                        -->
<!-- ============================================================= -->

<?php if (($stats['pending_hod'] ?? 0) > 0): ?>

    <div class="alert alert-info d-flex align-items-center mb-4" role="alert">

        <i class="bi bi-info-circle-fill me-3 fs-5 flex-shrink-0"></i>

        <div>

            <strong><?= $stats['pending_hod'] ?> symposium(s)</strong>
            you created are pending HOD / Principal approval.

        </div>

        <a href="<?= base_url() ?>/symposiums" class="btn btn-sm btn-outline-primary ms-auto">
            View Status
        </a>

    </div>

<?php endif; ?>

<?php if (($stats['revision_req'] ?? 0) > 0): ?>

    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">

        <i class="bi bi-exclamation-octagon-fill me-3 fs-5 flex-shrink-0"></i>

        <div>

            <strong><?= $stats['revision_req'] ?> symposium(s)</strong>
            require your revision before they can proceed.

        </div>

        <a href="<?= base_url() ?>/symposiums" class="btn btn-sm btn-danger ms-auto">
            Review Now
        </a>

    </div>

<?php endif; ?>

<!-- ============================================================= -->
<!-- Statistics                                                      -->
<!-- ============================================================= -->

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mb-5">

    <!-- My Symposiums -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            My Symposiums
                        </small>

                        <h2 class="fw-bold mt-1 mb-0">
                            <?= number_format($stats['my_symposiums'] ?? 0) ?>
                        </h2>

                    </div>

                    <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-calendar-event text-warning fs-2"></i>
                    </div>

                </div>

                <a href="<?= base_url() ?>/symposiums" class="small text-warning mt-2 d-block">
                    View all &rarr;
                </a>

            </div>

        </div>

    </div>

    <!-- Faculty In-Charge -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Assigned as Faculty In-Charge
                        </small>

                        <h2 class="fw-bold mt-1 mb-0">
                            <?= number_format($stats['fic_assigned_events'] ?? 0) ?>
                        </h2>

                    </div>

                    <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-person-workspace text-danger fs-2"></i>
                    </div>

                </div>

                <a href="<?= base_url() ?>/my/assigned-events" class="small text-danger mt-2 d-block">
                    View assigned &rarr;
                </a>

            </div>

        </div>

    </div>

    <!-- Pending Approvals -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Pending Approvals
                        </small>

                        <h2 class="fw-bold mt-1 mb-0 <?= ($stats['pending_hod'] ?? 0) > 0 ? 'text-warning' : '' ?>">
                            <?= number_format($stats['pending_hod'] ?? 0) ?>
                        </h2>

                    </div>

                    <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-hourglass-split text-warning fs-2"></i>
                    </div>

                </div>

                <span class="small text-muted mt-2 d-block">
                    Awaiting HOD / Principal
                </span>

            </div>

        </div>

    </div>


    <!-- Assigned as Judge -->
    <div class="col">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Assigned as Judge
                        </small>
                        <h2 class="fw-bold mt-1 mb-0" style="color:#6d28d9;">
                            <?= number_format($stats['judge_assigned_events'] ?? 0) ?>
                        </h2>
                    </div>
                    <div class="rounded-circle p-3" style="background:rgba(109,40,217,.08);">
                        <i class="bi bi-star-fill fs-2" style="color:#6d28d9;"></i>
                    </div>
                </div>
                <a href="<?= base_url() ?>/judge/dashboard" class="small mt-2 d-block" style="color:#6d28d9;">
                    View assigned &rarr;
                </a>
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
                    <div class="bg-info bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-people text-info fs-2"></i>
                    </div>
                </div>
                <a href="<?= base_url() ?>/students" class="small text-info mt-2 d-block">
                    View all &rarr;
                </a>
            </div>
        </div>
    </div>

    <!-- Total Registrations & Breakdown -->
    <div class="col-12 col-xl-12">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-clipboard-data text-success me-2"></i>
                    Registration Statistics
                </h5>
                <form method="GET" action="<?= base_url() ?>/dashboard/staff-coordinator" class="d-inline-block m-0">
                    <select name="symposium_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 200px;">
                        <option value="">All Symposiums</option>
                        <?php foreach ($mySymposiums ?? [] as $sym): ?>
                            <option value="<?= htmlspecialchars((string)$sym['symposium_id']) ?>" <?= (($selectedSymposiumId ?? null) == $sym['symposium_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$sym['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body">
                <div class="row text-center g-3">
                    <div class="col">
                        <div class="p-3 bg-light rounded">
                            <h3 class="fw-bold mb-1"><?= number_format($stats['total_registrations'] ?? 0) ?></h3>
                            <small class="text-muted text-uppercase" style="font-size:0.7rem;letter-spacing:0.05em;">Total</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 bg-light rounded">
                            <h3 class="fw-bold mb-1 text-primary"><?= number_format($stats['today_registrations'] ?? 0) ?></h3>
                            <small class="text-muted text-uppercase" style="font-size:0.7rem;letter-spacing:0.05em;">Today</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 bg-light rounded">
                            <h3 class="fw-bold mb-1 text-secondary"><?= number_format($stats['withdrawn_registrations'] ?? 0) ?></h3>
                            <small class="text-muted text-uppercase" style="font-size:0.7rem;letter-spacing:0.05em;">Withdrawn</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



</div>

<!-- ============================================================= -->
<!-- Scheduling Stats (Approved Symposiums Only)                    -->
<!-- ============================================================= -->

<?php
$eventsTotal     = (int) ($stats['total_events_in_approved']    ?? 0);
$eventsScheduled = (int) ($stats['events_scheduled']            ?? 0);
$eventsPending   = (int) ($stats['events_pending_scheduling']   ?? 0);
$schedPct        = $eventsTotal > 0 ? round(($eventsScheduled / $eventsTotal) * 100) : 0;
$approvedNeeding = (int) ($stats['approved_symposiums_needing_scheduling'] ?? 0);
?>

<?php if ($approvedNeeding > 0): ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex align-items-center justify-content-between">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-calendar-check text-primary me-2"></i>
            Event Scheduling
        </h5>
        <a href="<?= base_url() ?>/symposiums?status=Approved" class="small text-primary">View Approved &rarr;</a>
    </div>
    <div class="card-body">

        <!-- Stats: Events / Scheduled / Pending / Progress -->
        <div class="row text-center g-3 mb-3">
            <div class="col-3">
                <div class="p-3 bg-light rounded-3">
                    <h3 class="fw-bold mb-1"><?= $eventsTotal ?></h3>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.04em">Events</small>
                </div>
            </div>
            <div class="col-3">
                <div class="p-3 rounded-3" style="background:#d1fae5">
                    <h3 class="fw-bold mb-1 text-success"><?= $eventsScheduled ?></h3>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.04em">Scheduled</small>
                </div>
            </div>
            <div class="col-3">
                <div class="p-3 rounded-3" style="background:#fff3cd">
                    <h3 class="fw-bold mb-1 <?= $eventsPending > 0 ? 'text-warning' : 'text-success' ?>"><?= $eventsPending ?></h3>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.04em">Pending</small>
                </div>
            </div>
            <div class="col-3">
                <div class="p-3 rounded-3" style="background:<?= $schedPct === 100 ? '#d1fae5' : '#e0f2fe' ?>">
                    <h3 class="fw-bold mb-1 <?= $schedPct === 100 ? 'text-success' : 'text-primary' ?>"><?= $schedPct ?>%</h3>
                    <small class="text-muted text-uppercase fw-semibold" style="font-size:.7rem;letter-spacing:.04em">Progress</small>
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="progress mb-3" style="height:10px;border-radius:8px">
            <div class="progress-bar bg-<?= $schedPct === 100 ? 'success' : ($schedPct >= 50 ? 'primary' : 'warning') ?>"
                 style="width:<?= $schedPct ?>%;border-radius:8px" role="progressbar"></div>
        </div>

        <?php if ($eventsPending > 0): ?>
            <?php if (!empty($approvedSymposiums)): ?>
                <div class="mt-3">
                    <p class="small fw-semibold text-muted mb-2">Select a Symposium to schedule events:</p>
                    <form action="<?= base_url() ?>/symposiums/scheduling" method="GET" class="d-flex gap-2 align-items-center">
                        <select name="symposium_id" class="form-select form-select-sm" style="max-width:300px;" required>
                            <option value="">-- Choose Symposium --</option>
                            <?php foreach ($approvedSymposiums as $symposium): ?>
                                <option value="<?= $symposium['symposium_id'] ?>">
                                    <?= htmlspecialchars($symposium['symposium_code'] ?? $symposium['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm text-nowrap">
                            <i class="bi bi-calendar-check me-1"></i> Schedule
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <a href="<?= base_url() ?>/symposiums?status=Approved" class="btn btn-primary btn-sm">
                    <i class="bi bi-calendar-check me-1"></i>Schedule Events Now
                </a>
            <?php endif; ?>
        <?php else: ?>
        <div class="text-success fw-semibold small"><i class="bi bi-check-circle-fill me-1"></i>All events scheduled! Mark symposiums as Scheduling Complete.</div>
        <?php endif; ?>

    </div>
</div>
<?php endif; ?>

<!-- ============================================================= -->
<!-- Upcoming & Today Events                                        -->
<!-- ============================================================= -->

<?php
// ── Share button: next date with upcoming events ───────────────────────────
// The coordinator shares tomorrow's schedule the day before.
// If no events tomorrow, offer sharing the next available date instead.
$todayDate    = date('Y-m-d');
$tomorrowDate = date('Y-m-d', strtotime('+1 day'));
$shareDate    = null;
$shareLabel   = '';

// Find the first future date (>= today) that has events in $upcomingEvents
foreach ($upcomingEvents ?? [] as $_e) {
    if ($_e['event_date'] > $todayDate) {
        $shareDate = $_e['event_date'];
        break;
    }
}

if ($shareDate !== null) {
    $imageUrl = base_url() . '/schedule/image?date=' . $shareDate;
    if ($shareDate === $tomorrowDate) {
        $shareLabel = 'Share Tomorrow';
    } else {
        $shareLabel = 'Share ' . date('d M', strtotime($shareDate));
    }
} else {
    $imageUrl = null;
}
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-calendar-event text-primary me-2"></i>
            Upcoming &amp; Today Events
        </h5>
        <div>
            <?php if ($imageUrl): ?>
                <button type="button" id="btn-share-schedule"
                        data-image-url="<?= htmlspecialchars($imageUrl) ?>"
                        data-share-date="<?= htmlspecialchars($shareDate) ?>"
                        class="btn btn-sm btn-success me-2"
                        title="Share <?= htmlspecialchars(date('d/m/Y', strtotime($shareDate))) ?> schedule on WhatsApp">
                    <i class="bi bi-whatsapp"></i>
                    <?= htmlspecialchars($shareLabel) ?>
                    <span class="spinner-border spinner-border-sm d-none ms-1" id="share-spinner" role="status"></span>
                </button>
            <?php endif; ?>
            <a href="<?= base_url() ?>/my/assigned-events" class="btn btn-sm btn-outline-primary">
                View Schedule
            </a>
        </div>
    </div>

    <div class="card-body p-0">
        <?php if (!empty($upcomingEvents)): ?>
            <div class="list-group list-group-flush">
                <?php foreach ($upcomingEvents as $event): ?>
                    <?php 
                        $eventDateStr = (string)($event['event_date'] ?? '');
                        $eventDate = $eventDateStr ? strtotime($eventDateStr) : false;
                        $isToday = $eventDateStr && date('Y-m-d') === $eventDateStr;
                        $badgeClass = $isToday ? 'bg-danger text-white' : 'bg-primary bg-opacity-10 text-primary';
                    ?>
                    <div class="list-group-item px-4 py-3 d-flex align-items-start border-bottom-0 border-top" style="transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#f8f9fa';" onmouseout="this.style.backgroundColor='';">
                        <div class="me-3 mt-1">
                            <div class="badge <?= $badgeClass ?> rounded-3 p-2 text-center shadow-sm" style="min-width: 65px;">
                                <?php if ($isToday): ?>
                                    <span class="d-block fw-bold fs-6 my-1">TODAY</span>
                                <?php else: ?>
                                    <span class="d-block fw-bold fs-5"><?= $eventDate ? date('d', $eventDate) : '-' ?></span>
                                    <span class="d-block small text-uppercase" style="letter-spacing:1px;"><?= $eventDate ? date('M', $eventDate) : 'TBD' ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 fw-bold text-dark" style="font-size: 1.05rem;">
                                <?= htmlspecialchars($event['event_name']) ?>
                            </h6>
                            <p class="mb-1 text-muted small">
                                <i class="bi bi-diagram-3 me-1"></i> <?= htmlspecialchars($event['symposium_title']) ?>
                            </p>
                            <div class="d-flex align-items-center mt-2 small">
                                <span class="text-secondary me-3 bg-light rounded px-2 py-1">
                                    <i class="bi bi-clock me-1 text-primary"></i> 
                                    <?= !empty($event['start_time']) ? date('h:i A', strtotime($event['start_time'])) : 'TBD' ?> - <?= !empty($event['end_time']) ? date('h:i A', strtotime($event['end_time'])) : 'TBD' ?>
                                </span>
                                <span class="text-secondary bg-light rounded px-2 py-1">
                                    <i class="bi bi-geo-alt me-1 text-danger"></i> 
                                    <?= htmlspecialchars($event['venue_name'] ?? 'TBD') ?>
                                </span>
                            </div>
                        </div>
                        <div class="ms-auto text-end mt-1">
                            <?php
                                $statusClass = match($event['status']) {
                                    'Running' => 'bg-success text-white',
                                    'Registration Open' => 'bg-info text-white',
                                    'Registration Closed' => 'bg-secondary text-white',
                                    'Published' => 'bg-primary text-white',
                                    default => 'bg-light text-dark border'
                                };
                            ?>
                            <span class="badge <?= $statusClass ?> px-3 py-2 rounded-pill fw-medium shadow-sm">
                                <?= htmlspecialchars($event['status']) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-calendar-x fs-1 text-light mb-3 d-block" style="opacity: 0.5;"></i>
                <h6 class="fw-semibold text-secondary">No upcoming events</h6>
                <p class="small mb-0">You don't have any events scheduled for today or the future.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================= -->
<!-- Quick Actions                                                   -->
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

            <div class="col-md-6 col-lg-auto">

                <a href="<?= base_url() ?>/symposiums/create"
                   class="btn btn-warning text-dark px-4">

                    <i class="bi bi-calendar-plus me-2"></i>

                    Create Symposium

                </a>

            </div>



            <div class="col-md-6 col-lg-auto">
                <a href="<?= base_url() ?>/students/create"
                   class="btn btn-outline-success px-4">
                    <i class="bi bi-person-plus-fill me-2"></i>
                    Add Student
                </a>
            </div>

            <div class="col-md-6 col-lg-auto">
                <a href="<?= base_url() ?>/admin/events/create"
                   class="btn btn-outline-info px-4">
                    <i class="bi bi-lightning-fill me-2"></i>
                    Add Event
                </a>
            </div>

            <div class="col-md-6 col-lg-auto">

                <a href="<?= base_url() ?>/symposiums"
                   class="btn btn-outline-primary px-4">

                    <i class="bi bi-calendar-event me-2"></i>

                    My Symposiums

                </a>

            </div>

        </div>

    </div>

</div>

<!-- ============================================================= -->
<!-- Symposium Approval Workflow Info (Updated with Scheduling)      -->
<!-- ============================================================= -->

<div class="card shadow-sm border-0">

    <div class="card-header bg-white border-0 pt-3 pb-2">

        <h5 class="mb-0 fw-semibold">

            <i class="bi bi-diagram-3 me-2 text-secondary"></i>

            Symposium Workflow

        </h5>

    </div>

    <div class="card-body">

        <div class="d-flex flex-wrap align-items-center gap-2 text-center">

            <?php
            $workflow = [
                ['icon' => 'bi-person-fill',        'label' => 'You Create',         'color' => 'primary'],
                ['icon' => 'bi-arrow-right',        'label' => '',                   'color' => 'none'],
                ['icon' => 'bi-send',               'label' => 'Submit',             'color' => 'warning'],
                ['icon' => 'bi-arrow-right',        'label' => '',                   'color' => 'none'],
                ['icon' => 'bi-building',           'label' => 'HOD Approves',       'color' => 'info'],
                ['icon' => 'bi-arrow-right',        'label' => '',                   'color' => 'none'],
                ['icon' => 'bi-person-badge',       'label' => 'Principal Approves', 'color' => 'primary'],
                ['icon' => 'bi-arrow-right',        'label' => '',                   'color' => 'none'],
                ['icon' => 'bi-calendar-check',     'label' => 'Schedule Events',    'color' => 'success'],
                ['icon' => 'bi-arrow-right',        'label' => '',                   'color' => 'none'],
                ['icon' => 'bi-check-all',          'label' => 'Sched. Complete',    'color' => 'success'],
                ['icon' => 'bi-arrow-right',        'label' => '',                   'color' => 'none'],
                ['icon' => 'bi-clock-history',      'label' => 'Auto-Opens on Date', 'color' => 'danger'],
                ['icon' => 'bi-arrow-right',        'label' => '',                   'color' => 'none'],
                ['icon' => 'bi-flag-fill',          'label' => 'Event Day',          'color' => 'dark'],
            ];
            ?>

            <?php foreach ($workflow as $step): ?>

                <?php if ($step['color'] === 'none'): ?>

                    <i class="bi <?= $step['icon'] ?> text-muted fs-5 d-none d-md-block"></i>

                <?php else: ?>

                    <div class="text-center" style="min-width:70px">

                        <div class="bg-<?= $step['color'] ?> bg-opacity-10 rounded-circle p-2 mx-auto mb-1"
                             style="width:44px;height:44px;display:flex;align-items:center;justify-content:center">
                            <i class="bi <?= $step['icon'] ?> text-<?= $step['color'] ?>"></i>
                        </div>

                        <small class="text-muted" style="font-size:.7rem;">
                            <?= $step['label'] ?>
                        </small>

                    </div>

                <?php endif; ?>

            <?php endforeach; ?>

        </div>

    </div>

</div>

<?php if ($imageUrl): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn     = document.getElementById('btn-share-schedule');
    if (!btn) return;

    const imageUrl  = btn.dataset.imageUrl;
    const shareDate = btn.dataset.shareDate;
    const spinner   = document.getElementById('share-spinner');

    btn.addEventListener('click', async function () {
        btn.disabled = true;
        spinner.classList.remove('d-none');

        try {
            // Fetch the server-generated PNG
            const response = await fetch(imageUrl, { credentials: 'same-origin' });
            if (!response.ok) throw new Error('Server returned ' + response.status);

            const blob = await response.blob();
            const file = new File([blob], 'schedule-' + shareDate + '.png', { type: 'image/png' });

            // Mobile: use native share sheet (shares directly to WhatsApp etc.)
            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                try {
                    await navigator.share({
                        files: [file],
                        title:  'Event Schedule – ' + shareDate,
                        text:   'Please find the event schedule below.'
                    });
                } catch (err) {
                    if (err.name !== 'AbortError') {
                        triggerDownload(blob, file.name);
                    }
                }
            } else {
                // Desktop fallback: download the PNG
                triggerDownload(blob, file.name);
                showToast('Schedule image downloaded! Share it on WhatsApp Web by attaching the file.');
            }
        } catch (err) {
            console.error('Schedule image error:', err);
            showToast('Failed to generate schedule image. Please try again.', true);
        } finally {
            btn.disabled = false;
            spinner.classList.add('d-none');
        }
    });

    function triggerDownload(blob, filename) {
        const url = URL.createObjectURL(blob);
        const a   = document.createElement('a');
        a.href     = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 m-3 p-3 rounded shadow-sm text-white'
            + (isError ? ' bg-danger' : ' bg-success');
        toast.style.cssText = 'z-index:9999;min-width:280px;font-size:.9rem;';
        toast.textContent   = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }
});
</script>
<?php endif; ?>
