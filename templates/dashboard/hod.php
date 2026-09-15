<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : hod.php
 * Location    : templates/dashboard/
 * Description : Head of Department (HOD) Dashboard
 *
 * Department-scoped dashboard. All statistics reflect only the
 * HOD's own department. Data is filtered by department_id from session.
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
            <?= htmlspecialchars($user['full_name'] ?? 'HOD') ?>

        </h1>

        <p class="text-muted mb-0">

            HOD Dashboard &mdash; <?= $dept ?>

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

<?php if (($stats['pending_my_approval'] ?? 0) > 0): ?>

    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">

        <i class="bi bi-exclamation-triangle-fill me-3 fs-5 flex-shrink-0"></i>

        <div>

            <strong><?= $stats['pending_my_approval'] ?> symposium(s)</strong>
            in your department are waiting for review.

        </div>

        <a href="<?= base_url() ?>/symposiums" class="btn btn-sm btn-warning ms-auto">
            Review Now
        </a>

    </div>

<?php endif; ?>

<!-- ============================================================= -->
<!-- Department Statistics                                          -->
<!-- ============================================================= -->

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mb-5">

    <!-- Department Students -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Dept. Students
                        </small>

                        <h2 class="fw-bold mt-1 mb-0 text-success">
                            <?= number_format($stats['dept_students'] ?? 0) ?>
                        </h2>

                    </div>

                    <div class="bg-success bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-people text-success fs-2"></i>
                    </div>

                </div>

                <a href="<?= base_url() ?>/students" class="small text-success mt-2 d-block">
                    View students &rarr;
                </a>

            </div>

        </div>

    </div>

    <!-- Department Staff -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Dept. Staff
                        </small>

                        <h2 class="fw-bold mt-1 mb-0">
                            <?= number_format($stats['dept_staff'] ?? 0) ?>
                        </h2>

                    </div>

                    <div class="bg-info bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-person-badge text-info fs-2"></i>
                    </div>

                </div>

                <span class="small text-muted mt-2 d-block">
                    Staff &amp; Coordinators
                </span>

            </div>

        </div>

    </div>

    <!-- Department Symposiums -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Dept. Symposiums
                        </small>

                        <h2 class="fw-bold mt-1 mb-0">
                            <?= number_format($stats['dept_symposiums'] ?? 0) ?>
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

    <!-- Department Competitions -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Dept. Competitions
                        </small>

                        <h2 class="fw-bold mt-1 mb-0">
                            <?= number_format($stats['dept_competitions'] ?? 0) ?>
                        </h2>

                    </div>

                    <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-trophy text-danger fs-2"></i>
                    </div>

                </div>

                <a href="<?= base_url() ?>/competitions" class="small text-danger mt-2 d-block">
                    View all &rarr;
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
                            Pending My Approval
                        </small>

                        <h2 class="fw-bold mt-1 mb-0 <?= ($stats['pending_my_approval'] ?? 0) > 0 ? 'text-warning' : '' ?>">
                            <?= number_format($stats['pending_my_approval'] ?? 0) ?>
                        </h2>

                    </div>

                    <div class="bg-warning bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-hourglass-split text-warning fs-2"></i>
                    </div>

                </div>

                <a href="<?= base_url() ?>/symposiums" class="small text-warning mt-2 d-block">
                    Review &rarr;
                </a>

            </div>

        </div>

    </div>

    <!-- Department Registrations -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Registrations
                        </small>

                        <h2 class="fw-bold mt-1 mb-0">
                            <?= number_format($stats['dept_registrations'] ?? 0) ?>
                        </h2>

                    </div>

                    <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-clipboard-check text-primary fs-2"></i>
                    </div>

                </div>

                <span class="small text-muted mt-2 d-block">
                    From dept. competitions
                </span>

            </div>

        </div>

    </div>

</div>

<!-- ============================================================= -->
<!-- My Assignments Summary                                         -->
<!-- ============================================================= -->

<?php if (($stats['fic_assigned_events'] ?? 0) > 0 || ($stats['judge_assigned_events'] ?? 0) > 0): ?>

<div class="card shadow-sm border-0 mb-5">

    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">

        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-person-workspace text-primary me-2"></i>
            My Event Assignments
        </h5>

        <a href="<?= base_url() ?>/my/assigned-events" class="btn btn-sm btn-outline-primary">
            View All
        </a>

    </div>

    <div class="card-body">
        <div class="row g-3 text-center">

            <div class="col">
                <div class="p-3 bg-primary bg-opacity-10 rounded-3">
                    <h3 class="fw-bold mb-1 text-primary"><?= number_format($stats['fic_assigned_events'] ?? 0) ?></h3>
                    <small class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">As FIC</small>
                </div>
            </div>

            <div class="col">
                <div class="p-3 bg-warning bg-opacity-10 rounded-3">
                    <h3 class="fw-bold mb-1 text-warning"><?= number_format($stats['fic_no_judge'] ?? 0) ?></h3>
                    <small class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Need Judge</small>
                </div>
            </div>

            <div class="col">
                <div class="p-3 rounded-3" style="background:rgba(109,40,217,.08);">
                    <h3 class="fw-bold mb-1" style="color:#6d28d9;"><?= number_format($stats['judge_assigned_events'] ?? 0) ?></h3>
                    <small class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">As Judge</small>
                </div>
            </div>

        </div>
    </div>

</div>

<?php endif; ?>

<!-- ============================================================= -->
<!-- Upcoming & Today Events                                        -->
<!-- ============================================================= -->

<div class="card shadow-sm border-0 mb-5">
    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-calendar-event text-primary me-2"></i>
            Upcoming &amp; Today Events
        </h5>
        <a href="<?= base_url() ?>/my/assigned-events" class="btn btn-sm btn-outline-primary">
            View Schedule
        </a>
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
                        $dateDisplay = $isToday ? 'TODAY' : ($eventDate ? strtoupper(date('M d', $eventDate)) : 'TBD');
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
                <p class="small mb-0">There are no events scheduled for today or the future.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============================================================= -->
<!-- Quick Access                                                    -->
<!-- ============================================================= -->

<div class="card shadow-sm border-0">

    <div class="card-header bg-white border-0 pt-3 pb-2">

        <h5 class="mb-0 fw-semibold">

            <i class="bi bi-grid me-2 text-secondary"></i>

            Quick Access

        </h5>

    </div>

    <div class="card-body">

        <div class="row g-3">

            <div class="col-md-6 col-lg-3">

                <a href="<?= base_url() ?>/students"
                   class="btn btn-outline-success w-100 py-3">

                    <i class="bi bi-people fs-4 d-block mb-2"></i>

                    Students

                </a>

            </div>

            <div class="col-md-6 col-lg-3">

                <a href="<?= base_url() ?>/symposiums"
                   class="btn btn-outline-warning w-100 py-3">

                    <i class="bi bi-calendar-event fs-4 d-block mb-2"></i>

                    Symposiums

                </a>

            </div>

            <div class="col-md-6 col-lg-3">

                <a href="<?= base_url() ?>/my/assigned-events"
                   class="btn btn-outline-danger w-100 py-3">

                    <i class="bi bi-person-workspace fs-4 d-block mb-2"></i>

                    My Assigned Events

                </a>

            </div>

            <div class="col-md-6 col-lg-3">

                <a href="<?= base_url() ?>/reports"
                   class="btn btn-outline-info w-100 py-3">

                    <i class="bi bi-bar-chart-line fs-4 d-block mb-2"></i>

                    Reports

                </a>

            </div>

        </div>

    </div>

</div>
