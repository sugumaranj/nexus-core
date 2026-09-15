<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : dashboard.php
 * Location    : templates/fic/
 * Description : Faculty In-Charge (FIC) Portal — My Assigned Events Dashboard.
 *
 * Variables injected
 * -------------------------------------------------------------------------
 * $user            — session user array
 * $assignedEvents  — array of events where this user is active FIC
 * $stats           — array with fic_total_events, fic_with_judge, fic_no_judge,
 *                    fic_upcoming, judge_total_events
 *
 * -------------------------------------------------------------------------
 */

use App\Core\Session;
use App\Helpers\DateHelper;

$user           = $user           ?? Session::get('user', []);
$assignedEvents = $assignedEvents ?? [];
$stats          = $stats          ?? [];

$today = date('Y-m-d');

$sessionLabels = ['FN' => 'Forenoon', 'AN' => 'Afternoon', 'Full Day' => 'Full Day'];

function ficStatusBadge(string $status): string {
    return match($status) {
        'Registration Open'   => '<span class="badge bg-success">Registration Open</span>',
        'Registration Closed' => '<span class="badge bg-warning text-dark">Reg. Closed</span>',
        'Running'             => '<span class="badge bg-primary">Running</span>',
        'Completed'           => '<span class="badge bg-secondary">Completed</span>',
        'Cancelled'           => '<span class="badge bg-danger">Cancelled</span>',
        'Published'           => '<span class="badge bg-info text-dark">Published</span>',
        default               => '<span class="badge bg-light text-dark">' . htmlspecialchars($status, ENT_QUOTES) . '</span>',
    };
}
?>

<style>
.fic-hero {
    background: linear-gradient(135deg, #1e3a5f 0%, #1d4ed8 100%);
    border-radius: 20px;
    color: #fff;
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(29,78,216,.25);
    position: relative;
    overflow: hidden;
}
.fic-hero::after {
    content: '\f3b3';
    font-family: 'bootstrap-icons';
    position: absolute;
    right: 2rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 7rem;
    opacity: .08;
}
.fic-stat-card {
    border-radius: 16px;
    border: none;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
    transition: transform .15s, box-shadow .15s;
    cursor: default;
}
.fic-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 24px rgba(0,0,0,.12);
}
.fic-event-card {
    border-radius: 14px;
    border: 1px solid #e9ecef;
    background: #fff;
    transition: box-shadow .15s, border-color .15s;
}
.fic-event-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,.10);
    border-color: #0d6efd;
}
.fic-event-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    flex-shrink: 0;
}
.judge-pill {
    display: inline-flex; align-items: center; gap: .3rem;
    font-size: .78rem;
    padding: .25rem .65rem;
    border-radius: 20px;
    font-weight: 600;
}
.pill-green { background: rgba(34,197,94,.12); color: #16a34a; }
.pill-orange { background: rgba(245,158,11,.12); color: #d97706; }
.no-events-state {
    text-align: center; padding: 4rem 2rem; color: #9ca3af;
}
</style>

<!-- =========================================================== -->
<!-- Hero Banner                                                  -->
<!-- =========================================================== -->

<div class="fic-hero mb-4">
    <div class="row align-items-center">
        <div class="col">
            <h1 class="fw-bold mb-1 fs-3">
                <i class="bi bi-person-workspace me-2"></i>
                My Assigned Events
            </h1>
            <p class="mb-0 opacity-75">
                Events you are managing as Faculty In-Charge
                &mdash; <?= htmlspecialchars($user['full_name'] ?? 'Faculty', ENT_QUOTES) ?>
            </p>
        </div>
        <div class="col-auto text-end d-none d-md-block">
            <div class="opacity-75 small">
                <i class="bi bi-clock me-1"></i>
                <?= DateHelper::date('now') ?>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================== -->
<!-- Statistics                                                   -->
<!-- =========================================================== -->

<div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-4">

    <div class="col">
        <div class="fic-stat-card card h-100">
            <div class="card-body text-center py-3">
                <div class="bg-primary bg-opacity-10 rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                    <i class="bi bi-calendar3 text-primary fs-4"></i>
                </div>
                <h2 class="fw-bold mb-0"><?= (int)($stats['fic_total_events'] ?? 0) ?></h2>
                <small class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Assigned Events</small>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="fic-stat-card card h-100">
            <div class="card-body text-center py-3">
                <div class="bg-success bg-opacity-10 rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                    <i class="bi bi-person-check text-success fs-4"></i>
                </div>
                <h2 class="fw-bold mb-0 text-success"><?= (int)($stats['fic_with_judge'] ?? 0) ?></h2>
                <small class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">With Judges</small>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="fic-stat-card card h-100">
            <div class="card-body text-center py-3">
                <div class="bg-warning bg-opacity-10 rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                    <i class="bi bi-person-dash text-warning fs-4"></i>
                </div>
                <h2 class="fw-bold mb-0 text-warning"><?= (int)($stats['fic_no_judge'] ?? 0) ?></h2>
                <small class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Need Judges</small>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="fic-stat-card card h-100">
            <div class="card-body text-center py-3">
                <div class="bg-info bg-opacity-10 rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                    <i class="bi bi-calendar-event text-info fs-4"></i>
                </div>
                <h2 class="fw-bold mb-0 text-info"><?= (int)($stats['fic_upcoming'] ?? 0) ?></h2>
                <small class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Upcoming</small>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="fic-stat-card card h-100" onclick="window.location='<?= base_url() ?>/judge/dashboard'" style="cursor:pointer;">
            <div class="card-body text-center py-3">
                <div class="bg-danger bg-opacity-10 rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                    <i class="bi bi-star text-danger fs-4"></i>
                </div>
                <h2 class="fw-bold mb-0 text-danger"><?= (int)($stats['judge_total_events'] ?? 0) ?></h2>
                <small class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">As Judge</small>
            </div>
        </div>
    </div>

</div>

<!-- Judge assignment alert -->
<?php if (($stats['fic_no_judge'] ?? 0) > 0): ?>
<div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-3 fs-5 flex-shrink-0"></i>
    <div>
        <strong><?= (int)$stats['fic_no_judge'] ?> event(s)</strong> you manage
        still need a judge assigned.
    </div>
</div>
<?php endif; ?>

<!-- =========================================================== -->
<!-- Assigned Events List                                         -->
<!-- =========================================================== -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-list-check me-2 text-primary"></i>
            Events I'm Managing
        </h5>
        <a href="<?= base_url() ?>/judge/dashboard" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-star me-1"></i>Judge Dashboard
        </a>
    </div>

    <div class="card-body p-3">

        <?php if (empty($assignedEvents)): ?>

            <div class="no-events-state">
                <i class="bi bi-calendar-x fs-1 d-block mb-3"></i>
                <h5 class="fw-semibold text-secondary">No Events Assigned</h5>
                <p class="mb-0 small">You have not been assigned as Faculty In-Charge for any event yet.</p>
            </div>

        <?php else: ?>

            <div class="row g-3">
            <?php foreach ($assignedEvents as $event):
                $eid        = (int)($event['symposium_event_id'] ?? 0);
                $eventName  = htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES);
                $symTitle   = htmlspecialchars($event['symposium_title'] ?? '', ENT_QUOTES);
                $eventDate  = $event['event_date'] ?? '';
                $session    = $sessionLabels[$event['session'] ?? ''] ?? ($event['session'] ?? '—');
                $startTime  = $event['start_time'] ?? '';
                $venueName  = htmlspecialchars($event['venue_name'] ?? '—', ENT_QUOTES);
                $judgeCount = (int)($event['judge_count'] ?? 0);
                $regCount   = (int)($event['registration_count'] ?? 0);
                $status     = $event['event_status'] ?? '';
                $category   = $event['category'] ?? '';
                $isPast     = $eventDate && $eventDate < $today;

                $iconClass  = $category === 'Technical'
                    ? 'bg-primary bg-opacity-10 text-primary'
                    : 'bg-purple bg-opacity-10 text-warning';
                $icon       = $category === 'Technical' ? 'bi-cpu' : 'bi-music-note-beamed';
            ?>
                <div class="col-md-6 col-xl-4">
                    <div class="fic-event-card p-3 h-100 d-flex flex-column">

                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="fic-event-icon bg-primary bg-opacity-10">
                                <i class="bi <?= $icon ?> text-primary"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <h6 class="fw-bold mb-1 text-truncate" title="<?= $eventName ?>"><?= $eventName ?></h6>
                                <small class="text-muted text-truncate d-block"><?= $symTitle ?></small>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <?= ficStatusBadge($status) ?>
                            <span class="badge bg-light text-dark border"><?= htmlspecialchars($category, ENT_QUOTES) ?></span>
                        </div>

                        <div class="mb-3 flex-grow-1">
                            <div class="small text-muted mb-1">
                                <i class="bi bi-calendar3 me-1 text-secondary"></i>
                                <?= $eventDate ? DateHelper::date($eventDate) : '<em>Not scheduled</em>' ?>
                                <?php if ($eventDate && $startTime): ?>
                                    &nbsp;&bull;&nbsp;<?= htmlspecialchars(date('h:i A', strtotime($startTime)), ENT_QUOTES) ?>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted mb-1">
                                <i class="bi bi-clock me-1 text-secondary"></i>
                                <?= htmlspecialchars($session, ENT_QUOTES) ?>
                            </div>
                            <div class="small text-muted mb-1">
                                <i class="bi bi-geo-alt me-1 text-secondary"></i>
                                <?= $venueName ?>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="judge-pill <?= $judgeCount > 0 ? 'pill-green' : 'pill-orange' ?>">
                                <i class="bi bi-<?= $judgeCount > 0 ? 'person-check' : 'person-dash' ?>"></i>
                                <?= $judgeCount ?> Judge<?= $judgeCount !== 1 ? 's' : '' ?>
                            </div>
                            <div class="small text-muted">
                                <i class="bi bi-people me-1"></i><?= $regCount ?> Registered
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-2">
                            <a href="<?= base_url() ?>/my/assigned-events/event?id=<?= $eid ?>"
                               class="btn btn-sm btn-primary flex-fill">
                                <i class="bi bi-gear me-1"></i>Manage
                            </a>
                            <a href="<?= base_url() ?>/my/assigned-events/registrations?id=<?= $eid ?>"
                               class="btn btn-sm btn-outline-secondary flex-fill">
                                <i class="bi bi-people me-1"></i>Regs
                            </a>
                            <a href="<?= base_url() ?>/attendance/event?id=<?= $eid ?>"
                               class="btn btn-sm btn-outline-success flex-fill">
                                <i class="bi bi-calendar-check me-1"></i>Attend
                            </a>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>

</div>

<!-- =========================================================== -->
<!-- Quick Links                                                  -->
<!-- =========================================================== -->

<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-0 pt-3 pb-2">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-grid me-2 text-secondary"></i>
            Quick Links
        </h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6 col-lg-3">
                <a href="<?= base_url() ?>/judge/dashboard" class="btn btn-outline-danger w-100 py-3">
                    <i class="bi bi-star fs-4 d-block mb-2"></i>Judge Dashboard
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="<?= base_url() ?>/attendance" class="btn btn-outline-success w-100 py-3">
                    <i class="bi bi-calendar2-check fs-4 d-block mb-2"></i>Attendance
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="<?= base_url() ?>/notifications" class="btn btn-outline-primary w-100 py-3">
                    <i class="bi bi-bell fs-4 d-block mb-2"></i>Notifications
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="<?= base_url() ?>/students" class="btn btn-outline-secondary w-100 py-3">
                    <i class="bi bi-people fs-4 d-block mb-2"></i>Students
                </a>
            </div>
        </div>
    </div>
</div>
