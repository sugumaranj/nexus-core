<?php
declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : dashboard.php
 * Location    : templates/allocation/
 * Description : Resource Allocation Dashboard for a Symposium.
 *
 * Shows:
 *   • Stat cards: Events / FIC Assigned / Judges Assigned / Pending / Today
 *   • Allocation status timeline grouped by event date
 *   • Quick-action buttons: Assign FIC / Assign Judge / View Event
 *   • Available staff count widget
 *   • Today's events panel
 * -------------------------------------------------------------------------
 */

$symposium  = $symposium  ?? [];
$events     = $events     ?? [];
$stats      = $stats      ?? [];
$canManage  = $canManage  ?? false;
$todayEvents = $todayEvents ?? [];

$symId    = (int) ($symposium['symposium_id'] ?? 0);
$symTitle = htmlspecialchars($symposium['title'] ?? '', ENT_QUOTES, 'UTF-8');

$total          = (int) ($stats['total_events']     ?? 0);
$facAssigned    = (int) ($stats['faculty_assigned'] ?? 0);
$judgeAssigned  = (int) ($stats['judges_assigned']  ?? 0);
$facPending     = (int) ($stats['faculty_pending']  ?? 0);
$judgePending   = (int) ($stats['judges_pending']   ?? 0);

$sessionLabels = ['FN' => 'Forenoon', 'AN' => 'Afternoon', 'Full Day' => 'Full Day'];

// Group events by date
$grouped = [];
foreach ($events as $e) {
    $grouped[$e['event_date'] ?? 'Unknown'][] = $e;
}
ksort($grouped);

function allocStatusBadge(array $event): string {
    $hasFic   = !empty($event['faculty']);
    $hasJudge = !empty($event['judges']);
    if ($hasFic && $hasJudge) {
        return '<span class="badge bg-success">Fully Allocated</span>';
    } elseif ($hasFic || $hasJudge) {
        return '<span class="badge bg-warning text-dark">Partial</span>';
    }
    return '<span class="badge bg-light text-secondary border">Pending</span>';
}
?>

<style>
/* ═══════════════════════════════════════════════════════════════════════
   Resource Allocation Dashboard — Custom Styles
   ═══════════════════════════════════════════════════════════════════════ */
.alloc-stat-card {
    border-radius: 18px;
    border: none;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
    transition: transform .18s, box-shadow .18s;
    overflow: hidden;
}
.alloc-stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 32px rgba(0,0,0,.14); }
.alloc-stat-icon {
    width: 56px; height: 56px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
}
.alloc-event-card {
    border-radius: 14px;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 12px rgba(0,0,0,.05);
    transition: box-shadow .15s;
}
.alloc-event-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.1); }
.alloc-role-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 20px;
    font-size: .75rem; font-weight: 600;
}
.pill-faculty  { background: rgba(13,110,253,.1);  color: #0d6efd; }
.pill-judge    { background: rgba(25,135, 84,.1);  color: #198754; }
.pill-pending  { background: rgba(108,117,125,.1); color: #6c757d; }
.day-header {
    font-size: .78rem; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; color: #6c757d;
    padding: .4rem .8rem; background: #f8f9fa; border-radius: 8px;
    margin-bottom: .75rem; display: flex; align-items: center; gap: .5rem;
}
.alloc-progress-bar-wrap { height: 6px; border-radius: 4px; background: #e9ecef; margin-top: .4rem; }
.alloc-progress-fill { height: 6px; border-radius: 4px; transition: width .5s; }
.today-badge {
    background: linear-gradient(135deg, #f59e0b 0%, #ef4444 100%);
    color: #fff; font-size: .7rem; font-weight: 700;
    padding: 2px 8px; border-radius: 12px; letter-spacing: .03em;
}
.stat-num { font-size: 2.2rem; font-weight: 800; line-height: 1; }
</style>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- Page Header                                                              -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <nav aria-label="breadcrumb" class="mb-1">
            <ol class="breadcrumb mb-0" style="font-size:.82rem;">
                <li class="breadcrumb-item"><a href="<?= base_url() ?>/symposiums">Symposiums</a></li>
                <li class="breadcrumb-item active"><?= $symTitle ?></li>
                <li class="breadcrumb-item active">Staff Allocation</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0">
            <i class="bi bi-people-fill me-2 text-primary"></i>Staff Allocation
        </h1>
        <p class="text-secondary mb-0" style="font-size:.87rem;"><?= $symTitle ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($canManage): ?>
        <a href="<?= base_url() ?>/symposiums/allocation/availability?symposium_id=<?= $symId ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-calendar2-check me-1"></i>Staff Availability
        </a>
        <?php endif; ?>
        <a href="<?= base_url() ?>/symposiums/allocation/reports/combined?symposium_id=<?= $symId ?>" class="btn btn-outline-primary btn-sm" target="_blank">
            <i class="bi bi-printer me-1"></i>Combined Report
        </a>
        <div class="dropdown">
            <button class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-file-earmark-bar-graph me-1"></i>Reports
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= base_url() ?>/symposiums/allocation/reports/faculty?symposium_id=<?= $symId ?>" target="_blank"><i class="bi bi-person-badge me-2 text-primary"></i>Faculty Report</a></li>
                <li><a class="dropdown-item" href="<?= base_url() ?>/symposiums/allocation/reports/judges?symposium_id=<?= $symId ?>" target="_blank"><i class="bi bi-star me-2 text-success"></i>Judge Report</a></li>
                <li><a class="dropdown-item" href="<?= base_url() ?>/symposiums/allocation/reports/venue?symposium_id=<?= $symId ?>" target="_blank"><i class="bi bi-geo-alt me-2 text-warning"></i>Venue Report</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= base_url() ?>/symposiums/allocation/reports/combined?symposium_id=<?= $symId ?>" target="_blank"><i class="bi bi-grid-3x3-gap me-2 text-secondary"></i>Combined Report</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- Stat Cards Row                                                           -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">
    <!-- Total Events -->
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card alloc-stat-card h-100 p-3">
            <div class="alloc-stat-icon bg-primary bg-opacity-10 mb-2">
                <i class="bi bi-calendar-event text-primary"></i>
            </div>
            <div class="stat-num text-primary"><?= $total ?></div>
            <div class="text-secondary" style="font-size:.78rem;">Total Events</div>
        </div>
    </div>
    <!-- FIC Assigned -->
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card alloc-stat-card h-100 p-3">
            <div class="alloc-stat-icon bg-info bg-opacity-10 mb-2">
                <i class="bi bi-person-badge text-info"></i>
            </div>
            <div class="stat-num text-info"><?= $facAssigned ?></div>
            <div class="text-secondary" style="font-size:.78rem;">FIC Assigned</div>
        </div>
    </div>
    <!-- Judges Assigned -->
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card alloc-stat-card h-100 p-3">
            <div class="alloc-stat-icon bg-success bg-opacity-10 mb-2">
                <i class="bi bi-star text-success"></i>
            </div>
            <div class="stat-num text-success"><?= $judgeAssigned ?></div>
            <div class="text-secondary" style="font-size:.78rem;">Judges Assigned</div>
        </div>
    </div>
    <!-- FIC Pending -->
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card alloc-stat-card h-100 p-3">
            <div class="alloc-stat-icon bg-warning bg-opacity-10 mb-2">
                <i class="bi bi-person-x text-warning"></i>
            </div>
            <div class="stat-num text-warning"><?= $facPending ?></div>
            <div class="text-secondary" style="font-size:.78rem;">FIC Pending</div>
        </div>
    </div>
    <!-- Judges Pending -->
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card alloc-stat-card h-100 p-3">
            <div class="alloc-stat-icon bg-danger bg-opacity-10 mb-2">
                <i class="bi bi-star-half text-danger"></i>
            </div>
            <div class="stat-num text-danger"><?= $judgePending ?></div>
            <div class="text-secondary" style="font-size:.78rem;">Judges Pending</div>
        </div>
    </div>
    <!-- Today's Events -->
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card alloc-stat-card h-100 p-3" style="background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%);">
            <div class="alloc-stat-icon mb-2" style="background:rgba(255,255,255,.15);">
                <i class="bi bi-sun-fill text-white"></i>
            </div>
            <div class="stat-num text-white"><?= count($todayEvents) ?></div>
            <div class="text-white opacity-75" style="font-size:.78rem;">Today's Events</div>
        </div>
    </div>
</div>

<!-- Progress bar row -->
<?php if ($total > 0): ?>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm p-3" style="border-radius:14px;">
            <div class="d-flex justify-content-between mb-1">
                <small class="fw-semibold text-secondary">Faculty In-Charge Coverage</small>
                <small class="fw-bold text-info"><?= round($facAssigned / $total * 100) ?>%</small>
            </div>
            <div class="alloc-progress-bar-wrap">
                <div class="alloc-progress-fill bg-info" style="width:<?= round($facAssigned / $total * 100) ?>%"></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm p-3" style="border-radius:14px;">
            <div class="d-flex justify-content-between mb-1">
                <small class="fw-semibold text-secondary">Judge Coverage</small>
                <small class="fw-bold text-success"><?= round($judgeAssigned / $total * 100) ?>%</small>
            </div>
            <div class="alloc-progress-bar-wrap">
                <div class="alloc-progress-fill bg-success" style="width:<?= round($judgeAssigned / $total * 100) ?>%"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- Events Timeline                                                          -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<?php if (empty($grouped)): ?>
<div class="text-center py-5">
    <i class="bi bi-calendar-x display-4 text-secondary"></i>
    <p class="text-secondary mt-3">No events scheduled yet. Add events to the symposium first.</p>
    <a href="<?= base_url() ?>/symposiums/events?symposium_id=<?= $symId ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Add Events
    </a>
</div>
<?php else: ?>
<div class="allocation-timeline">
    <?php foreach ($grouped as $date => $dayEvents): ?>
    <?php
        $isToday   = ($date === date('Y-m-d'));
        $timestamp = strtotime($date);
        if ($timestamp) {
            $dateLabel = $isToday ? 'Today — ' . \App\Helpers\DateHelper::date(date('Y-m-d H:i:s', $timestamp)) : \App\Helpers\DateHelper::date(date('Y-m-d H:i:s', $timestamp));
        } else {
            $dateLabel = 'Unscheduled / Date Pending';
        }
    ?>
    <div class="timeline-day mb-4">
        <!-- Day header -->
        <div class="day-header">
            <i class="bi bi-calendar3"></i>
            <?= htmlspecialchars($dateLabel) ?>
            <?php if ($isToday): ?>
            <span class="today-badge">TODAY</span>
            <?php endif; ?>
        </div>

        <div class="row g-3">
            <?php foreach ($dayEvents as $event): ?>
            <?php
                $eid      = (int) ($event['symposium_event_id'] ?? 0);
                $hasFic   = !empty($event['faculty']);
                $hasJudge = !empty($event['judges']);
            ?>
            <div class="col-md-6 col-xl-4">
                <div class="card alloc-event-card h-100" style="border-left: 4px solid <?= $hasFic && $hasJudge ? '#198754' : ($hasFic || $hasJudge ? '#ffc107' : '#dee2e6') ?>;">
                    <div class="card-body p-3">

                        <!-- Header -->
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-bold mb-0" style="font-size:.92rem;">
                                    <?= htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES) ?>
                                </h6>
                                <small class="text-secondary">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php
                                        $st = \App\Helpers\DateHelper::time($event['start_time'] ?? null, '');
                                        $et = \App\Helpers\DateHelper::time($event['end_time'] ?? null, '');
                                        echo htmlspecialchars(trim($st . ($st && $et ? ' – ' : '') . $et));
                                    ?>
                                    &bull;
                                    <?= htmlspecialchars($sessionLabels[$event['session'] ?? ''] ?? ($event['session'] ?? '')) ?>
                                </small>
                            </div>
                            <?= allocStatusBadge($event) ?>
                        </div>

                        <!-- Venue -->
                        <?php if (!empty($event['venue_name'])): ?>
                        <div class="mb-2">
                            <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($event['venue_name']) ?></small>
                        </div>
                        <?php endif; ?>

                        <!-- Faculty -->
                        <div class="mb-1">
                            <span class="text-secondary" style="font-size:.73rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase;">Faculty In-Charge</span>
                            <div class="mt-1 d-flex flex-wrap gap-1">
                                <?php if (!empty($event['faculty'])): ?>
                                    <?php foreach ($event['faculty'] as $fac): ?>
                                    <span class="alloc-role-pill pill-faculty">
                                        <i class="bi bi-person-check"></i>
                                        <?= htmlspecialchars($fac['full_name'] ?? '') ?>
                                    </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="alloc-role-pill pill-pending"><i class="bi bi-dash"></i>Not Assigned</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Judges -->
                        <div class="mb-3">
                            <span class="text-secondary" style="font-size:.73rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase;">Judges</span>
                            <div class="mt-1 d-flex flex-wrap gap-1">
                                <?php if (!empty($event['judges'])): ?>
                                    <?php foreach ($event['judges'] as $judge): ?>
                                    <span class="alloc-role-pill pill-judge">
                                        <i class="bi bi-star-half"></i>
                                        <?= htmlspecialchars($judge['full_name'] ?? '') ?>
                                    </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="alloc-role-pill pill-pending"><i class="bi bi-dash"></i>Not Assigned</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <?php if ($canManage): ?>
                        <div class="border-top pt-2 d-flex gap-2 flex-wrap">
                            <?php if (empty($event['event_date']) || empty($event['start_time'])): ?>
                                <a href="<?= base_url() ?>/symposiums/scheduling/event?id=<?= $eid ?>&return_to=allocation" class="btn btn-sm btn-secondary flex-fill" title="Schedule event date and time first">
                                    <i class="bi bi-calendar-plus me-1"></i>Schedule First
                                </a>
                            <?php else: ?>
                                <a href="<?= base_url() ?>/symposiums/allocation/event?id=<?= $eid ?>"
                                   class="btn btn-sm btn-primary flex-fill">
                                    <i class="bi bi-pencil-square me-1"></i>Allocate
                                </a>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="border-top pt-2">
                            <a href="<?= base_url() ?>/symposiums/allocation/event?id=<?= $eid ?>"
                               class="btn btn-sm btn-outline-secondary w-100">
                                <i class="bi bi-eye me-1"></i>View
                            </a>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
