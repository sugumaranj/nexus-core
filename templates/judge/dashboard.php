<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : dashboard.php
 * Location    : templates/judge/
 * Description : Judge Portal — Events assigned to this user as judge.
 *
 * Variables injected
 * -------------------------------------------------------------------------
 * $user           — session user array
 * $assignedEvents — array from JudgeAssignmentModel::getAssignedEventsForUser()
 * $stats          — [ 'total_assigned', 'upcoming' ]
 *
 * NOTE: Evaluation module is not yet implemented.
 *       This dashboard shows event assignments only.
 *
 * -------------------------------------------------------------------------
 */

use App\Core\Session;
use App\Helpers\DateHelper;

$user           = $user           ?? Session::get('user', []);
$assignedEvents = $assignedEvents ?? [];
$stats          = $stats          ?? [];

$today         = date('Y-m-d');
$sessionLabels = ['FN' => 'Forenoon', 'AN' => 'Afternoon', 'Full Day' => 'Full Day'];
?>

<style>
.judge-hero {
    background: linear-gradient(135deg, #1a1a2e 0%, #6d28d9 100%);
    border-radius: 20px; color: #fff; padding: 2rem 2.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(109,40,217,.25);
    position: relative; overflow: hidden;
}
.judge-hero::after {
    content: '\f5a0';
    font-family: 'bootstrap-icons';
    position: absolute; right: 2rem; top: 50%;
    transform: translateY(-50%);
    font-size: 7rem; opacity: .07;
}
.judge-stat-card {
    border-radius: 16px; border: none;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
    transition: transform .15s, box-shadow .15s;
}
.judge-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 24px rgba(0,0,0,.12);
}
.event-row-card {
    border-radius: 12px; border: 1px solid #e9ecef;
    padding: .85rem 1.1rem; background: #fff;
    transition: box-shadow .15s, border-color .15s;
}
.event-row-card:hover {
    box-shadow: 0 3px 14px rgba(0,0,0,.09);
    border-color: #6d28d9;
}
.status-badge {
    font-size: .72rem; padding: .25rem .6rem;
    border-radius: 20px; font-weight: 600;
}
</style>

<!-- Hero Banner -->
<div class="judge-hero mb-4">
    <div class="row align-items-center">
        <div class="col">
            <h1 class="fw-bold mb-1 fs-3">
                <i class="bi bi-star-fill me-2"></i>
                Judge Dashboard
            </h1>
            <p class="mb-0 opacity-75">
                Events you are assigned to judge &mdash;
                <?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES) ?>
            </p>
        </div>
        <div class="col-auto text-end d-none d-md-block opacity-75 small">
            <i class="bi bi-clock me-1"></i><?= DateHelper::date('now') ?>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="row row-cols-1 row-cols-md-2 g-3 mb-4">

    <div class="col">
        <div class="judge-stat-card card h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="bg-purple bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center"
                     style="width:52px;height:52px;background:rgba(109,40,217,.12)!important;flex-shrink:0;">
                    <i class="bi bi-star fs-3" style="color:#6d28d9;"></i>
                </div>
                <div>
                    <h2 class="fw-bold mb-0" style="color:#6d28d9;"><?= (int)($stats['total_assigned'] ?? 0) ?></h2>
                    <small class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Total Assigned Events</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col">
        <div class="judge-stat-card card h-100">
            <div class="card-body d-flex align-items-center gap-3 py-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center"
                     style="width:52px;height:52px;background:rgba(34,197,94,.12);flex-shrink:0;">
                    <i class="bi bi-calendar-event text-success fs-3"></i>
                </div>
                <div>
                    <h2 class="fw-bold mb-0 text-success"><?= (int)($stats['upcoming'] ?? 0) ?></h2>
                    <small class="text-muted text-uppercase" style="font-size:.7rem;letter-spacing:.05em;">Upcoming Events</small>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- =========================================================== -->
<!-- Assigned Events Table                                        -->
<!-- =========================================================== -->
<div class="card shadow-sm border-0">

    <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-semibold">
            <i class="bi bi-list-ul me-2" style="color:#6d28d9;"></i>
            My Judge Assignments
        </h5>
        <a href="<?= base_url() ?>/my/assigned-events" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-person-workspace me-1"></i>FIC Portal
        </a>
    </div>

    <div class="card-body p-3">

        <?php if (empty($assignedEvents)): ?>

            <div class="text-center py-5 text-muted">
                <i class="bi bi-star fs-1 d-block mb-3 opacity-25"></i>
                <h5 class="fw-semibold text-secondary">No Judge Assignments</h5>
                <p class="mb-0 small">You have not been assigned as a judge for any event yet.</p>
            </div>

        <?php else: ?>

            <div class="d-flex flex-column gap-2">
            <?php foreach ($assignedEvents as $event):
                $eid        = (int)($event['symposium_event_id'] ?? 0);
                $eventName  = htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES);
                $symTitle   = htmlspecialchars($event['symposium_title'] ?? '', ENT_QUOTES);
                $eventDate  = $event['event_date'] ?? '';
                $session    = $sessionLabels[$event['session'] ?? ''] ?? ($event['session'] ?? '—');
                $startTime  = $event['start_time'] ?? '';
                $endTime    = $event['end_time'] ?? '';
                $venueName  = htmlspecialchars($event['venue_name'] ?? '—', ENT_QUOTES);
                $category   = htmlspecialchars($event['category'] ?? '', ENT_QUOTES);
                $eventStatus = $event['event_status'] ?? '';
                $isCoordJudge = ($event['is_coordinator_judge'] ?? 'No') === 'Yes';
                $regCount   = (int)($event['registration_count'] ?? 0);

                $isPast     = $eventDate && $eventDate < $today;
                $isToday    = $eventDate === $today;

                $dateClass  = $isToday ? 'text-success fw-bold' : ($isPast ? 'text-muted' : 'text-primary');
            ?>
                <div class="event-row-card">
                    <div class="row align-items-center g-2">

                        <div class="col">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="fw-semibold"><?= $eventName ?></span>
                                <?php if ($isCoordJudge): ?>
                                    <span class="badge bg-primary" style="font-size:.65rem;">Coordinator Judge</span>
                                <?php endif; ?>
                                <span class="badge bg-light text-dark border" style="font-size:.68rem;"><?= $category ?></span>
                            </div>
                            <div class="text-muted small"><?= $symTitle ?></div>
                        </div>

                        <div class="col-auto text-center d-none d-md-block">
                            <div class="<?= $dateClass ?>" style="font-size:.83rem;">
                                <?= $eventDate ? DateHelper::date($eventDate) : '—' ?>
                                <?php if ($isToday): ?>
                                    <span class="badge bg-success ms-1" style="font-size:.6rem;">TODAY</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($session, ENT_QUOTES) ?>
                                <?php if ($startTime): ?>
                                    &bull; <?= htmlspecialchars(date('h:i A', strtotime($startTime)), ENT_QUOTES) ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-auto text-center d-none d-lg-block">
                            <div class="small text-muted">
                                <i class="bi bi-geo-alt me-1"></i><?= $venueName ?>
                                <?php
                                $vCode  = htmlspecialchars($event['venue_code']    ?? '', ENT_QUOTES);
                                $vBldg  = htmlspecialchars($event['building_name'] ?? '', ENT_QUOTES);
                                $vFloor = htmlspecialchars($event['floor']         ?? '', ENT_QUOTES);
                                ?>
                                <?php if ($vCode): ?><span class="badge bg-light text-dark border ms-1" style="font-size:.65rem;"><?= $vCode ?></span><?php endif; ?>
                            </div>
                            <?php if ($vBldg || $vFloor): ?>
                            <div class="small text-muted" style="font-size:.72rem;">
                                <?= $vBldg ?><?= $vFloor ? ' · Fl.' . $vFloor : '' ?>
                            </div>
                            <?php endif; ?>
                            <div class="small text-muted">
                                <i class="bi bi-people me-1"></i><?= $regCount ?> registered
                            </div>
                        </div>

                        <div class="col-auto">
                            <?php
                            $stBadge = match($eventStatus) {
                                'Registration Open'   => 'bg-success text-white',
                                'Registration Closed' => 'bg-warning text-dark',
                                'Running'             => 'bg-primary text-white',
                                'Completed'           => 'bg-secondary text-white',
                                'Cancelled'           => 'bg-danger text-white',
                                default               => 'bg-light text-dark border',
                            };
                            ?>
                            <div class="d-flex align-items-center gap-2">
                                <span class="status-badge <?= $stBadge ?>"><?= htmlspecialchars($eventStatus, ENT_QUOTES) ?></span>
                                <?php if ($eventStatus === 'Running' || $eventStatus === 'Registration Closed' || $eventStatus === 'Completed'): ?>
                                    <a href="<?= base_url() ?>/judge/evaluate?id=<?= $eid ?>" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold" style="font-size:.75rem;">Evaluate <i class="bi bi-arrow-right ms-1"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </div>

</div>

