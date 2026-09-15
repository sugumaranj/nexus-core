<?php
declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : dashboard.php
 * Location    : templates/scheduling/
 * Description : Scheduling Dashboard â€” Event Date & Time Management
 *
 * Shows: Stats widgets (Events / Scheduled / Pending / Progress %),
 *        Daily timeline view, per-event actions, Scheduling Complete button.
 *
 * -------------------------------------------------------------------------
 */

use App\Services\SymposiumService;

$symposium   = $symposium   ?? [];
$events      = $events      ?? [];
$dashboard   = $dashboard   ?? [];
$grouped_days = $grouped_days ?? [];
$canManage   = $canManage   ?? false;
$schedulingAllowed = $schedulingAllowed ?? false;
$schedulingLocked  = $schedulingLocked  ?? false;
$success     = $success ?? '';
$error       = $error   ?? '';

$symId     = (int) ($symposium['symposium_id'] ?? 0);
$symTitle  = htmlspecialchars($symposium['title'] ?? '', ENT_QUOTES, 'UTF-8');
$symStatus = $symposium['status'] ?? '';

$total         = (int) ($dashboard['total']          ?? 0);
$scheduled     = (int) ($dashboard['scheduled']      ?? 0);
$pending       = (int) ($dashboard['pending']        ?? 0);
$pct           = (int) ($dashboard['completion_pct'] ?? 0);
$isComplete    = (bool) ($dashboard['is_complete']   ?? false);

// Session label mapping
$sessionLabels = ['FN' => 'Forenoon', 'AN' => 'Afternoon', 'Full Day' => 'Full Day'];
$sessionIcons  = ['FN' => 'bi-brightness-high', 'AN' => 'bi-sunset', 'Full Day' => 'bi-sun-fill'];

function schedBadge(string $status): string {
    return match($status) {
            <a href="<?= base_url() ?>/symposiums/view?id=<?= $symId ?>" class="text-muted small">
                <i class="bi bi-arrow-left me-1"></i>Back to Symposium
            </a>
        </div>
        <h1 class="fw-bold mb-1"><?= $symTitle ?></h1>
        <p class="text-muted mb-0">
            <i class="bi bi-calendar3 me-1"></i>Event Scheduling Dashboard
            &nbsp;&mdash;&nbsp;
            <span class="badge bg-<?= $symStatus === 'Approved' ? 'success' : ($symStatus === 'Scheduling Complete' ? 'info' : 'secondary') ?>">
                <?= htmlspecialchars($symStatus) ?>
            </span>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($symStatus === SymposiumService::STATUS_APPROVED || $symStatus === SymposiumService::STATUS_SCHEDULING_COMPLETE): ?>
        <a href="<?= base_url() ?>/symposiums/scheduling/report?symposium_id=<?= $symId ?>"
           class="btn btn-outline-secondary btn-sm" target="_blank">
            <i class="bi bi-printer me-1"></i> Schedule Report
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Flash messages -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4">
        <i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars($success) ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4">
        <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- ============================================================= -->
<!-- Gate Banner: not allowed                                       -->
<!-- ============================================================= -->
<?php if (!$schedulingAllowed && !$schedulingLocked): ?>
<div class="gate-locked mb-4 d-flex align-items-center gap-3">
    <i class="bi bi-lock-fill fs-2 opacity-75"></i>
    <div>
        <h5 class="fw-bold mb-1">Scheduling Not Available Yet</h5>
        <p class="mb-0 opacity-90">
            Event scheduling is only available after the symposium has been
            <strong>fully approved</strong> (HOD + Principal).
            Current status: <strong><?= htmlspecialchars($symStatus) ?></strong>
        </p>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================= -->
<!-- Scheduling Complete Banner                                     -->
<!-- ============================================================= -->
<?php if ($symStatus === SymposiumService::STATUS_SCHEDULING_COMPLETE): ?>
<div class="sched-complete-banner mb-4 d-flex align-items-center gap-3">
    <i class="bi bi-check-circle-fill fs-2"></i>
    <div>
        <h5 class="fw-bold mb-1">Scheduling Complete!</h5>
        <p class="mb-0 opacity-90">All <?= $total ?> events have been scheduled. Registration can now be opened by the Admin.</p>
    </div>
</div>
<?php endif; ?>

<!-- ============================================================= -->
<!-- STATS CARDS — clickable to filter the All Events panel        -->
<!-- ============================================================= -->
<div class="row g-3 mb-4">

    <!-- Events Total (All) -->
    <div class="col-6 col-lg-3">
        <div class="card sched-stat-card h-100 sc-active sc-active-all" id="scCard-all"
             onclick="schedFilter('all')" role="button" tabindex="0"
             onkeydown="if(event.key==='Enter'||event.key===' ')schedFilter('all')">
            <div class="card-body text-center py-4">
                <div class="bg-primary bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:56px;height:56px">
                    <i class="bi bi-calendar-event text-primary fs-3"></i>
                </div>
                <h2 class="fw-bold mb-1"><?= $total ?></h2>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:.72rem;letter-spacing:.05em">Events</small>
                <div class="stat-hint">Show all</div>
            </div>
        </div>
    </div>

    <!-- Scheduled -->
    <div class="col-6 col-lg-3">
        <div class="card sched-stat-card h-100" id="scCard-scheduled"
             onclick="schedFilter('scheduled')" role="button" tabindex="0"
             onkeydown="if(event.key==='Enter'||event.key===' ')schedFilter('scheduled')">
            <div class="card-body text-center py-4">
                <div class="bg-success bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:56px;height:56px">
                    <i class="bi bi-check-circle text-success fs-3"></i>
                </div>
                <h2 class="fw-bold mb-1 text-success"><?= $scheduled ?></h2>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:.72rem;letter-spacing:.05em">Scheduled</small>
                <div class="stat-hint">Click to filter</div>
            </div>
        </div>
    </div>

    <!-- Pending -->
    <div class="col-6 col-lg-3">
        <div class="card sched-stat-card h-100" id="scCard-pending"
             onclick="schedFilter('pending')" role="button" tabindex="0"
             onkeydown="if(event.key==='Enter'||event.key===' ')schedFilter('pending')">
            <div class="card-body text-center py-4">
                <div class="bg-warning bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:56px;height:56px">
                    <i class="bi bi-hourglass-split text-warning fs-3"></i>
                </div>
                <h2 class="fw-bold mb-1 <?= $pending > 0 ? 'text-warning' : 'text-muted' ?>"><?= $pending ?></h2>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:.72rem;letter-spacing:.05em">Pending</small>
                <div class="stat-hint">Click to filter</div>
            </div>
        </div>
    </div>

    <!-- Progress / Rescheduled -->
    <div class="col-6 col-lg-3">
        <div class="card sched-stat-card h-100" id="scCard-rescheduled"
             onclick="schedFilter('rescheduled')" role="button" tabindex="0"
             onkeydown="if(event.key==='Enter'||event.key===' ')schedFilter('rescheduled')">
            <div class="card-body text-center py-4">
                <div class="bg-info bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:56px;height:56px">
                    <i class="bi bi-speedometer2 text-info fs-3"></i>
                </div>
                <h2 class="fw-bold mb-1 <?= $pct === 100 ? 'text-success' : ($pct >= 50 ? 'text-primary' : 'text-warning') ?>"><?= $pct ?>%</h2>
                <small class="text-muted text-uppercase fw-semibold" style="font-size:.72rem;letter-spacing:.05em">Progress</small>
                <div class="progress mt-2" style="height:6px">
                    <div class="progress-bar bg-<?= $pct === 100 ? 'success' : ($pct >= 50 ? 'primary' : 'warning') ?>"
                         style="width:<?= $pct ?>%" role="progressbar"></div>
                </div>
                <div class="stat-hint mt-1">Rescheduled events</div>
            </div>
        </div>
    </div>

</div>

<!-- ============================================================= -->
<!-- Mark Scheduling Complete Button                               -->
<!-- ============================================================= -->
<?php if ($canManage && $isComplete && $symStatus === SymposiumService::STATUS_APPROVED): ?>
<div class="card border-success border-2 mb-4">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <h5 class="fw-bold text-success mb-1"><i class="bi bi-check-all me-2"></i>All Events Scheduled!</h5>
            <p class="text-muted mb-0">You've scheduled all <?= $total ?> events. Mark scheduling complete to allow registration to open.</p>
        </div>
        <form action="<?= base_url() ?>/symposiums/scheduling/complete" method="post"
              onsubmit="return confirm('Mark scheduling as complete? This will finalize the event schedule and allow registration to be opened.')">
            <input type="hidden" name="symposium_id" value="<?= $symId ?>">
            <button type="submit" class="btn btn-success px-4">
                <i class="bi bi-check-circle-fill me-2"></i>Mark Scheduling Complete
            </button>
        </form>
    </div>
</div>
<?php elseif ($canManage && !$isComplete && $symStatus === SymposiumService::STATUS_APPROVED): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-4">
    <i class="bi bi-info-circle-fill fs-5"></i>
    <div><?= $pending ?> event(s) still need to be scheduled before you can mark scheduling complete.</div>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- LEFT: Daily Timeline -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="bi bi-calendar3 me-2 text-primary"></i>Daily Timeline</h5>
                <small class="text-muted"><?= $scheduled ?> event(s) scheduled</small>
            </div>
            <div class="card-body">

                <?php if (empty($grouped_days)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x fs-1 text-muted d-block mb-3"></i>
                        <p class="text-muted">No events scheduled yet. Use the Event List on the right to assign dates and times.</p>
                    </div>
                <?php else: ?>

                    <?php $dayNumber = 1; foreach ($grouped_days as $date => $sessions): ?>
                        <div class="timeline-day">
                            <div class="d-flex align-items-center gap-3 mb-3">
                                <div class="bg-primary rounded px-3 py-2 text-white text-center" style="min-width:70px">
                                    <div style="font-size:.7rem;opacity:.85"><?= date('D', strtotime($date)) ?></div>
                                    <div class="fw-bold fs-5 lh-1"><?= date('d', strtotime($date)) ?></div>
                                    <div style="font-size:.7rem;opacity:.85"><?= date('M Y', strtotime($date)) ?></div>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Day <?= $dayNumber ?></h6>
                                    <small class="text-muted">
                                        <?php $total_day = array_sum(array_map('count', $sessions)); ?>
                                        <?= $total_day ?> event<?= $total_day !== 1 ? 's' : '' ?>
                                    </small>
                                </div>
                            </div>

                            <?php foreach ($sessions as $session => $evts): ?>
                                <div class="mb-3">
                                    <div class="timeline-session-label">
                                        <i class="bi <?= $sessionIcons[$session] ?? 'bi-clock' ?> me-1"></i>
                                        <?= $sessionLabels[$session] ?? $session ?>
                                    </div>
                                    <?php foreach ($evts as $ev): ?>
                                        <div class="timeline-item <?= $ev['schedule_status'] === 'Rescheduled' ? 'rescheduled' : '' ?>">
                                            <div class="timeline-time">
                                                <div class="time-from"><?= \App\Helpers\DateHelper::time($ev['start_time']) ?></div>
                                                <div class="time-to">to <?= \App\Helpers\DateHelper::time($ev['end_time']) ?></div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold"><?= htmlspecialchars($ev['event_name']) ?></div>
                                                <div class="small text-muted">
                                                    <code><?= htmlspecialchars($ev['event_code'] ?? '') ?></code>
                                                    <?php if ($ev['schedule_status'] === 'Rescheduled'): ?>
                                                        &bull; <span class="text-warning fw-semibold">Rescheduled</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if ($canManage): ?>
                                            <div class="ms-auto">
                                                <a href="<?= base_url() ?>/symposiums/scheduling/reschedule?id=<?= $ev['symposium_event_id'] ?>"
                                                   class="btn btn-sm btn-outline-warning py-1" title="Reschedule">
                                                    <i class="bi bi-clock-history"></i>
                                                </a>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($dayNumber < count($grouped_days)): ?>
                                <hr class="my-3">
                            <?php endif; ?>
                        </div>
                    <?php $dayNumber++; endforeach; ?>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- RIGHT: Event List with filter + search -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm" style="position:sticky;top:1rem">
            <!-- Header: title + active-filter chip -->
            <div class="card-header bg-white border-bottom d-flex align-items-center gap-2">
                <h5 class="mb-0 me-auto flex-shrink-0"><i class="bi bi-list-task me-2 text-primary"></i>All Events</h5>
                <span id="scChip" class="filter-chip d-none bg-primary text-white">
                    <i class="bi bi-funnel-fill" style="font-size:.6rem"></i>
                    <span id="scChipLabel"></span>
                    <button type="button" class="btn-close btn-close-white ms-1"
                            style="font-size:.45rem;width:.55rem;height:.55rem"
                            onclick="schedFilter('all')" title="Clear filter"></button>
                </span>
            </div>

            <!-- Search bar -->
            <div class="filter-searchbar">
                <i class="bi bi-search text-muted" style="font-size:.82rem"></i>
                <input type="text" id="scSearch" placeholder="Search events&hellip;"
                       oninput="schedSearch(this.value)" autocomplete="off">
                <button type="button" id="scSearchClear"
                        class="btn btn-link btn-sm p-0 text-muted d-none"
                        onclick="schedSearchClear()" title="Clear">
                    <i class="bi bi-x-circle"></i>
                </button>
            </div>

            <!-- Visible count -->
            <div class="px-3 py-1 bg-white border-bottom">
                <small class="text-muted">
                    Showing <strong id="scVisCount"><?= count($events) ?></strong> of <?= count($events) ?> events
                </small>
            </div>

            <div class="card-body p-0" style="max-height:565px; overflow-y:auto;" id="scScroll">
                <?php if (empty($events)): ?>
                    <div class="text-center py-4 text-muted small">No events added yet.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($events as $ev): ?>
                            <?php
                                $sStatus   = $ev['schedule_status'] ?? 'Unscheduled';
                                $filterKey = match($sStatus) {
                                    'Scheduled'   => 'scheduled',
                                    'Rescheduled' => 'rescheduled',
                                    default       => 'pending',
                                };
                            ?>
                            <div class="list-group-item event-list-item py-3 px-3"
                                 data-status="<?= htmlspecialchars($filterKey) ?>"
                                 data-name="<?= htmlspecialchars(mb_strtolower($ev['event_name'])) ?>"
                                 data-code="<?= htmlspecialchars(mb_strtolower($ev['event_code'] ?? '')) ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold small"><?= htmlspecialchars($ev['event_name']) ?></div>
                                        <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($ev['event_code'] ?? '') ?></div>
                                    </div>
                                    <?= schedBadge($sStatus) ?>
                                </div>

                                <?php if (!empty($ev['event_date'])): ?>
                                    <div class="mt-1 small text-muted">
                                        <i class="bi bi-calendar3 me-1"></i><?= \App\Helpers\DateHelper::date($ev['event_date']) ?>
                                        <?php if (!empty($ev['start_time'])): ?>
                                            &bull; <?= \App\Helpers\DateHelper::time($ev['start_time']) ?> &mdash; <?= \App\Helpers\DateHelper::time($ev['end_time']) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($canManage): ?>
                                    <div class="mt-2">
                                        <?php if ($sStatus === 'Unscheduled'): ?>
                                            <a href="<?= base_url() ?>/symposiums/scheduling/event?id=<?= $ev['symposium_event_id'] ?>"
                                               class="btn btn-sm btn-success w-100">
                                                <i class="bi bi-clock me-1"></i>Schedule Now
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= base_url() ?>/symposiums/scheduling/reschedule?id=<?= $ev['symposium_event_id'] ?>"
                                               class="btn btn-sm btn-outline-warning w-100">
                                                <i class="bi bi-clock-history me-1"></i>Reschedule
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- No results -->
                    <div id="evNoResults">
                        <i class="bi bi-search d-block mb-2 text-muted" style="font-size:2rem;opacity:.4"></i>
                        <div class="small">No events match this filter.</div>
                        <button class="btn btn-link btn-sm mt-2" onclick="schedFilter('all');schedSearchClear()">
                            Clear &amp; show all
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
(function () {
    var currentFilter = 'all';
    var currentSearch = '';

    var items     = Array.from(document.querySelectorAll('.event-list-item'));
    var chip      = document.getElementById('scChip');
    var chipLabel = document.getElementById('scChipLabel');
    var visCnt    = document.getElementById('scVisCount');
    var noRes     = document.getElementById('evNoResults');
    var srchInput = document.getElementById('scSearch');
    var srchClear = document.getElementById('scSearchClear');

    var cardIds = ['all','scheduled','pending','rescheduled'];
    var chipMeta = {
        'all':         { label: 'All',          cls: 'bg-primary text-white' },
        'scheduled':   { label: 'Scheduled',    cls: 'bg-success text-white' },
        'pending':     { label: 'Pending',      cls: 'bg-warning text-dark'  },
        'rescheduled': { label: 'Rescheduled',  cls: 'bg-warning text-dark'  }
    };

    window.schedFilter = function (f) {
        currentFilter = f;

        // Update card highlights
        cardIds.forEach(function (id) {
            var c = document.getElementById('scCard-' + id);
            if (!c) return;
            c.classList.remove('sc-active','sc-active-all','sc-active-scheduled','sc-active-pending','sc-active-rescheduled');
            if (id === f) c.classList.add('sc-active', 'sc-active-' + f);
        });

        // Update chip
        if (f === 'all') {
            chip.classList.add('d-none');
        } else {
            chip.classList.remove('d-none');
            var m = chipMeta[f] || {};
            chip.className = 'filter-chip ' + (m.cls || 'bg-primary text-white');
            chipLabel.textContent = m.label || f;
        }

        var scroll = document.getElementById('scScroll');
        if (scroll) scroll.scrollTop = 0;

        renderList();
    };

    window.schedSearch = function (val) {
        currentSearch = val.trim().toLowerCase();
        srchClear.classList.toggle('d-none', currentSearch === '');
        renderList();
    };

    window.schedSearchClear = function () {
        srchInput.value = '';
        currentSearch = '';
        srchClear.classList.add('d-none');
        renderList();
    };

    function renderList() {
        var vis = 0;
        items.forEach(function (item) {
            var statusOk = currentFilter === 'all' || item.dataset.status === currentFilter;
            var searchOk = currentSearch === ''
                || (item.dataset.name || '').indexOf(currentSearch) !== -1
                || (item.dataset.code || '').indexOf(currentSearch) !== -1;
            var show = statusOk && searchOk;
            item.classList.toggle('ev-hidden', !show);
            if (show) vis++;
        });
        if (visCnt) visCnt.textContent = vis;
        if (noRes)  noRes.style.display = vis === 0 ? 'block' : 'none';
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (currentSearch) { schedSearchClear(); }
            else if (currentFilter !== 'all') { schedFilter('all'); }
        }
    });

    // Initial render
    schedFilter('all');
})();
</script>
