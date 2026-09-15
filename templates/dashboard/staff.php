<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : staff.php
 * Location    : templates/dashboard/
 * Description : Staff Dashboard
 *
 * Student management focused dashboard.
 * Staff members manage student records and assist coordinators.
 * They do NOT create symposiums or manage competitions.
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
            <?= htmlspecialchars($user['full_name'] ?? 'Staff') ?>

        </h1>

        <p class="text-muted mb-0">

            Staff Dashboard &mdash; <?= $dept ?>

        </p>

    </div>

    <div class="text-muted small">

        <i class="bi bi-clock me-1"></i>

        <?= \App\Helpers\DateHelper::date('now') ?>

    </div>

</div>

<!-- ============================================================= -->
<!-- Statistics                                                      -->
<!-- ============================================================= -->

<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-4 mb-5">

    <!-- Total Students -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Active Students
                        </small>

                        <h2 class="fw-bold mt-1 mb-0 text-success">
                            <?= number_format($stats['total_students'] ?? 0) ?>
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

    <!-- Judge Assignments -->

    <div class="col">

        <div class="card shadow-sm border-0 h-100" style="cursor:pointer;" onclick="window.location='<?= base_url() ?>/judge/dashboard'">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            Judge Assignments
                        </small>

                        <h2 class="fw-bold mt-1 mb-0 text-secondary">
                            <?= number_format($stats['judge_assigned_events'] ?? 0) ?>
                        </h2>

                    </div>

                    <div class="bg-secondary bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-star text-secondary fs-2"></i>
                    </div>

                </div>

                <a href="<?= base_url() ?>/judge/dashboard" class="small text-secondary mt-2 d-block">
                    View assignments &rarr;
                </a>

            </div>

        </div>

    </div>

    <!-- Departments -->

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

                <span class="small text-muted mt-2 d-block">
                    Active departments
                </span>

            </div>

        </div>

    </div>

    <!-- FIC Assigned Events -->
    <div class="col">
        <div class="card shadow-sm border-0 h-100" style="cursor:pointer;" onclick="window.location='<?= base_url() ?>/my/assigned-events'">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-semibold text-uppercase" style="font-size:.72rem;letter-spacing:.05em;">
                            FIC Assignments
                        </small>
                        <h2 class="fw-bold mt-1 mb-0 text-primary">
                            <?= number_format($stats['fic_assigned_events'] ?? 0) ?>
                        </h2>
                    </div>
                    <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                        <i class="bi bi-person-workspace text-primary fs-2"></i>
                    </div>
                </div>
                <a href="<?= base_url() ?>/my/assigned-events" class="small text-primary mt-2 d-block">
                    Manage events &rarr;
                </a>
            </div>
        </div>
    </div>

</div>

<!-- ============================================================= -->
<!-- Registration Statistics                                         -->
<!-- ============================================================= -->

<div class="card shadow-sm border-0 mb-5">
    <div class="card-header bg-white border-0 pt-3 pb-2">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-clipboard-data text-info me-2"></i>
            Assigned Registration Statistics
        </h5>
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

<!-- ============================================================= -->
<!-- Upcoming & Today Events                                        -->
<!-- ============================================================= -->

<div class="card shadow-sm border-0 mb-4">
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
<!-- Quick Actions — Student Management                            -->
<!-- ============================================================= -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white border-0 pt-3 pb-2">

        <h5 class="mb-0 fw-semibold">

            <i class="bi bi-lightning-charge-fill text-warning me-2"></i>

            Student Actions

        </h5>

    </div>

    <div class="card-body">

        <div class="row g-3">

            <div class="col-md-6 col-lg-auto">

                <a href="<?= base_url() ?>/students/create"
                   class="btn btn-success px-4">

                    <i class="bi bi-person-plus-fill me-2"></i>

                    Add New Student

                </a>

            </div>

            <div class="col-md-6 col-lg-auto">

                <a href="<?= base_url() ?>/students"
                   class="btn btn-outline-primary px-4">

                    <i class="bi bi-people me-2"></i>

                    View All Students

                </a>

            </div>



            <div class="col-md-6 col-lg-auto">

                <a href="<?= base_url() ?>/my/assigned-events"
                   class="btn btn-outline-info px-4">

                    <i class="bi bi-person-workspace me-2"></i>

                    My Assigned Events

                </a>

            </div>

        </div>

    </div>

</div>

<!-- ============================================================= -->
<!-- My Assignments Summary                                         -->
<!-- ============================================================= -->

<?php if (($stats['fic_assigned_events'] ?? 0) > 0 || ($stats['judge_assigned_events'] ?? 0) > 0): ?>

<div class="card shadow-sm border-0 mb-4">

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

