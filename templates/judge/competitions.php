<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : competitions.php
 * Location    : templates/judge/
 * Description : Judge Portal — Full list of events this user is assigned to judge.
 *
 * Variables injected
 * -------------------------------------------------------------------------
 * $user           — session user array
 * $assignedEvents — array from JudgeAssignmentModel::getAssignedEventsForUser()
 *
 * -------------------------------------------------------------------------
 */

use App\Core\Session;
use App\Helpers\DateHelper;

$user           = $user           ?? Session::get('user', []);
$assignedEvents = $assignedEvents ?? [];

$today         = date('Y-m-d');
$sessionLabels = ['FN' => 'Forenoon', 'AN' => 'Afternoon', 'Full Day' => 'Full Day'];
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url() ?>/judge/dashboard">Judge Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">All Judge Assignments</li>
    </ol>
</nav>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center flex-wrap mb-4 gap-2">
    <div>
        <h1 class="fw-bold mb-1 fs-3">
            <i class="bi bi-star me-2" style="color:#6d28d9;"></i>
            My Judge Assignments
        </h1>
        <p class="text-muted mb-0">
            Full list of events you are assigned to judge &mdash;
            <?= htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES) ?>
        </p>
    </div>
    <a href="<?= base_url() ?>/judge/dashboard" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<!-- Table -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <?php if (empty($assignedEvents)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-star fs-1 d-block mb-3 opacity-25"></i>
                <h5 class="fw-semibold text-secondary">No Judge Assignments</h5>
                <p class="mb-0 small">You have not been assigned as a judge for any event.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Event</th>
                            <th>Symposium</th>
                            <th>Date &amp; Session</th>
                            <th>Venue</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($assignedEvents as $event):
                        $eventName  = htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES);
                        $symTitle   = htmlspecialchars($event['symposium_title'] ?? '', ENT_QUOTES);
                        $eventDate  = $event['event_date'] ?? '';
                        $session    = $sessionLabels[$event['session'] ?? ''] ?? ($event['session'] ?? '—');
                        $venueName  = htmlspecialchars($event['venue_name'] ?? '—', ENT_QUOTES);
                        $regCount   = (int)($event['registration_count'] ?? 0);
                        $eventStatus = $event['event_status'] ?? '';
                        $isCoordJudge = ($event['is_coordinator_judge'] ?? 'No') === 'Yes';
                        $isPast     = $eventDate && $eventDate < $today;
                        $isToday    = $eventDate === $today;

                        $stBadge = match($eventStatus) {
                            'Registration Open'   => 'bg-success text-white',
                            'Registration Closed' => 'bg-warning text-dark',
                            'Completed'           => 'bg-secondary text-white',
                            'Cancelled'           => 'bg-danger text-white',
                            default               => 'bg-light text-dark border',
                        };
                    ?>
                        <tr>
                            <td><strong><?= $eventName ?></strong></td>
                            <td class="text-muted small"><?= $symTitle ?></td>
                            <td>
                                <div class="<?= $isToday ? 'text-success fw-bold' : ($isPast ? 'text-muted' : '') ?>" style="font-size:.85rem;">
                                    <?= $eventDate ? DateHelper::date($eventDate) : '—' ?>
                                    <?php if ($isToday): ?><span class="badge bg-success ms-1" style="font-size:.6rem;">TODAY</span><?php endif; ?>
                                </div>
                                <div class="text-muted small"><?= htmlspecialchars($session, ENT_QUOTES) ?></div>
                            </td>
                            <td class="text-muted small"><?= $venueName ?></td>
                            <td class="text-center"><?= $regCount ?></td>
                            <td><span class="badge <?= $stBadge ?>" style="font-size:.72rem;"><?= htmlspecialchars($eventStatus, ENT_QUOTES) ?></span></td>
                            <td>
                                <?php if ($isCoordJudge): ?>
                                    <span class="badge bg-primary" style="font-size:.68rem;">Coordinator</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border" style="font-size:.68rem;">Judge</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
