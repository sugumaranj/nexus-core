<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : event_detail.php
 * Location    : templates/fic/
 * Description : FIC Event Management Page — view event and manage judges.
 *
 * Variables injected
 * -------------------------------------------------------------------------
 * $event          — symposium_event row with venue + symposium details
 * $currentFic     — array of active FIC assignments for this event
 * $currentJudges  — array of active judges for this event
 * $staffList      — array of all staff with availability_status
 * $regCount       — (int) number of registered participants
 * $user           — session user array
 *
 * -------------------------------------------------------------------------
 */

use App\Core\Session;
use App\Helpers\DateHelper;

$user          = $user          ?? Session::get('user', []);
$event         = $event         ?? [];
$currentFic    = $currentFic    ?? [];
$currentJudges = $currentJudges ?? [];
$staffList     = $staffList     ?? [];
$regCount      = (int)($regCount ?? 0);

$eid        = (int)($event['symposium_event_id'] ?? 0);
$eventName  = htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES);
$symTitle   = htmlspecialchars($event['symposium_title'] ?? '', ENT_QUOTES);
$symCode    = htmlspecialchars($event['symposium_code'] ?? '', ENT_QUOTES);
$eventDate  = $event['event_date'] ?? '';
$session    = $event['session'] ?? '';
$startTime  = $event['start_time'] ?? '';
$endTime    = $event['end_time'] ?? '';
$venueName  = htmlspecialchars($event['venue_name'] ?? '—', ENT_QUOTES);
$building   = htmlspecialchars($event['building_name'] ?? '', ENT_QUOTES);
$category   = $event['category'] ?? '';
$judgeMethod = $event['judging_method'] ?? 'Marks';
$maxScore   = $event['maximum_score'] ?? '100.00';
$eventStatus = $event['status'] ?? '';

$sessionLabels = ['FN' => 'Forenoon', 'AN' => 'Afternoon', 'Full Day' => 'Full Day'];
$sessionLabel  = $sessionLabels[$session] ?? $session;

$availCount    = count(array_filter($staffList, fn($s) => $s['availability_status'] === 'available'));
$busyCount     = count(array_filter($staffList, fn($s) => $s['availability_status'] === 'busy'));
?>

<style>
.event-banner {
    background: linear-gradient(135deg, #1e3a5f 0%, #1d4ed8 100%);
    border-radius: 20px;
    color: #fff;
    padding: 1.75rem 2rem;
    box-shadow: 0 8px 32px rgba(29,78,216,.25);
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}
.event-banner::after {
    content: '\f1e0';
    font-family: 'bootstrap-icons';
    position: absolute; right: 2rem; top: 50%;
    transform: translateY(-50%);
    font-size: 6rem; opacity: .06;
}
.section-card {
    border-radius: 16px; border: none;
    box-shadow: 0 2px 14px rgba(0,0,0,.07);
    margin-bottom: 1.5rem;
}
.judge-person-card {
    border-radius: 12px;
    border: 1px solid #e9ecef;
    padding: .75rem 1rem;
    display: flex; align-items: center; gap: .75rem;
    background: #fff; transition: box-shadow .15s;
}
.judge-person-card:hover { box-shadow: 0 3px 14px rgba(0,0,0,.08); }
.person-avatar {
    width: 42px; height: 42px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; font-weight: 700; flex-shrink: 0;
}
.av-blue  { background: rgba(13,110,253,.12); color: #0d6efd; }
.av-green { background: rgba(25,135,84,.12);  color: #198754; }
.av-purple{ background: rgba(102,16,242,.12); color: #6610f2; }

.staff-grid-item {
    border-radius: 12px; border: 1px solid #e9ecef;
    padding: .6rem .9rem; cursor: pointer;
    display: flex; align-items: center; gap: .6rem;
    background: #fff; transition: all .15s; font-size: .84rem;
}
.staff-grid-item:hover { border-color: #0d6efd; box-shadow: 0 2px 12px rgba(13,110,253,.1); }
.staff-grid-item.busy-item  { background: #fffbeb; }
.staff-grid-item.inact-item { opacity: .55; cursor: not-allowed; }
.avail-dot { display:inline-block; width:9px; height:9px; border-radius:50%; flex-shrink:0; }
.dot-green  { background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.2); }
.dot-orange { background:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,.2); }
.dot-red    { background:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.2); }

.toast-container-custom {
    position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999;
}
</style>

<!-- =========================================================== -->
<!-- Toast notifications                                          -->
<!-- =========================================================== -->
<div class="toast-container-custom" id="ficToastContainer"></div>

<!-- =========================================================== -->
<!-- Breadcrumb                                                   -->
<!-- =========================================================== -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= base_url() ?>/my/assigned-events">My Assigned Events</a>
        </li>
        <li class="breadcrumb-item active" aria-current="page"><?= $eventName ?></li>
    </ol>
</nav>

<!-- =========================================================== -->
<!-- Event Info Banner                                            -->
<!-- =========================================================== -->
<div class="event-banner">
    <div class="row align-items-center g-3">
        <div class="col">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-light text-dark border-0 shadow-sm"><?= htmlspecialchars($category, ENT_QUOTES) ?></span>
                <span class="badge bg-light text-dark border-0 shadow-sm"><?= htmlspecialchars($judgeMethod, ENT_QUOTES) ?> / <?= htmlspecialchars((string)$maxScore, ENT_QUOTES) ?> pts</span>
            </div>
            <h1 class="fw-bold mb-1 fs-3"><?= $eventName ?></h1>
            <p class="mb-0 opacity-75 small">
                <i class="bi bi-mortarboard me-1"></i><?= $symTitle ?>
                &nbsp;<span class="badge bg-light text-dark border-0 shadow-sm"><?= $symCode ?></span>
            </p>
        </div>
        <div class="col-auto d-none d-md-block text-end">
            <div class="row g-2 text-center">
                <div class="col-auto">
                    <div style="font-size:.7rem; opacity:.7; text-transform:uppercase; letter-spacing:.05em;">Date</div>
                    <div class="fw-bold"><?= $eventDate ? DateHelper::date($eventDate) : '—' ?></div>
                </div>
                <div class="col-auto">
                    <div style="font-size:.7rem; opacity:.7; text-transform:uppercase; letter-spacing:.05em;">Session</div>
                    <div class="fw-bold"><?= htmlspecialchars($sessionLabel, ENT_QUOTES) ?></div>
                </div>
                <div class="col-auto">
                    <div style="font-size:.7rem; opacity:.7; text-transform:uppercase; letter-spacing:.05em;">Venue</div>
                    <div class="fw-bold"><?= $venueName ?></div>
                    <?php
                    $venueCode     = htmlspecialchars($event['venue_code']     ?? '', ENT_QUOTES);
                    $buildingName  = htmlspecialchars($event['building_name']  ?? '', ENT_QUOTES);
                    $venueFloor    = htmlspecialchars($event['floor']          ?? '', ENT_QUOTES);
                    ?>
                    <?php if ($venueCode || $buildingName || $venueFloor): ?>
                    <div style="font-size:.7rem; opacity:.8;">
                        <?php if ($venueCode): ?><span class="badge bg-white bg-opacity-25 text-white me-1"><?= $venueCode ?></span><?php endif; ?>
                        <?php if ($buildingName): ?><?= $buildingName ?><?php endif; ?>
                        <?php if ($venueFloor): ?> · Fl.<?= $venueFloor ?><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-auto">
                    <div style="font-size:.7rem; opacity:.7; text-transform:uppercase; letter-spacing:.05em;">Registrations</div>
                    <div class="fw-bold">
                        <a href="<?= base_url() ?>/my/assigned-events/registrations?id=<?= $event['symposium_event_id'] ?>" class="text-white text-decoration-none border-bottom border-white border-opacity-50">
                            <?= $regCount ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- ======================================================= -->
    <!-- LEFT — Current FIC + Judges                             -->
    <!-- ======================================================= -->
    <div class="col-lg-5">

        <!-- Current FIC -->
        <div class="section-card card">
            <div class="card-header bg-white border-0 pt-3 pb-2">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-person-badge text-primary me-2"></i>
                    Faculty In-Charge
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($currentFic)): ?>
                    <p class="text-muted text-center py-3 mb-0 small">No FIC assigned.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                    <?php foreach ($currentFic as $fic):
                        $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', trim($fic['full_name'] ?? 'FA'))));
                        $initials = substr($initials, 0, 2);
                    ?>
                        <div class="judge-person-card">
                            <div class="person-avatar av-blue"><?= htmlspecialchars($initials, ENT_QUOTES) ?></div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small"><?= htmlspecialchars($fic['full_name'] ?? '', ENT_QUOTES) ?></div>
                                <div class="text-muted" style="font-size:.75rem;">
                                    <?= htmlspecialchars($fic['user_role'] ?? '', ENT_QUOTES) ?>
                                    <?php if (!empty($fic['department_name'])): ?>
                                        &mdash; <?= htmlspecialchars($fic['department_name'], ENT_QUOTES) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="badge bg-primary bg-opacity-10 text-primary" style="font-size:.7rem;">FIC</span>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Evaluation Management (If Supported) -->
        <?php if (!empty($event['supports_evaluation'])): ?>
            <div class="section-card card mb-3 border-primary">
                <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold text-primary">
                        <i class="bi bi-bar-chart-fill me-2"></i> Evaluation Module
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ((bool)($event['is_locked'] ?? false)): ?>
                        <div class="alert alert-success mb-0 py-2 border-0 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-check-circle-fill me-2"></i> Results are Published & Locked.
                            </div>
                            <a href="<?= base_url('/evaluation/results/event?id=' . (int)$event['symposium_event_id']) ?>" class="btn btn-sm btn-light fw-bold text-success shadow-sm">
                                View Leaderboard <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="small text-muted mb-3">
                            Once all judges have submitted their scores, click below to run the mathematical analysis and freeze the final rankings.
                        </p>
                        <button type="button" class="btn btn-primary w-100 fw-bold" id="btnPublishResults" onclick="publishResults(<?= (int)$event['symposium_event_id'] ?>)">
                            <i class="bi bi-lock-fill me-1"></i> Publish Results
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <script>
            async function publishResults(eventId) {
                if (!confirm("Are you sure? This will lock the event evaluations and publish the final rankings permanently!")) return;
                
                const btn = document.getElementById('btnPublishResults');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Publishing...';

                // Remove any previous tie banner
                const oldBanner = document.getElementById('tie-warning-banner');
                if (oldBanner) oldBanner.remove();
                
                try {
                    const response = await fetch('<?= base_url() ?>/my/assigned-events/publish-results?id=' + eventId, { method: 'POST' });
                    const result = await response.json();
                    if (result.success) {
                        alert(result.message);
                        location.reload();
                    } else {
                        alert("Error: " + result.message);
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Publish Results';
                    }
                } catch (e) {
                    alert("Network error.");
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-lock-fill me-1"></i> Publish Results';
                }
            }
            </script>
        <?php endif; ?>

        <!-- Current Judges -->
        <div class="section-card card">
            <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-star text-warning me-2"></i>
                    Assigned Judges
                    <span class="badge bg-warning text-dark ms-1"><?= count($currentJudges) ?></span>
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($currentJudges)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-person-dash fs-2 text-muted d-block mb-2"></i>
                        <p class="text-muted small mb-0">No judges assigned yet.<br>Use the panel on the right to add one.</p>
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2" id="judgeListContainer">
                    <?php foreach ($currentJudges as $judge):
                        $initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', trim($judge['full_name'] ?? 'JG'))));
                        $initials = substr($initials, 0, 2);
                        $jUserId  = (int)($judge['user_id'] ?? 0);
                    ?>
                        <div class="judge-person-card" id="judge-card-<?= $jUserId ?>">
                            <div class="person-avatar av-green"><?= htmlspecialchars($initials, ENT_QUOTES) ?></div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold small"><?= htmlspecialchars($judge['full_name'] ?? '', ENT_QUOTES) ?></div>
                                <div class="text-muted" style="font-size:.75rem;">
                                    <?= htmlspecialchars($judge['user_role'] ?? '', ENT_QUOTES) ?>
                                    <?php if (!empty($judge['department_name'])): ?>
                                        &mdash; <?= htmlspecialchars($judge['department_name'], ENT_QUOTES) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger px-2 py-1 remove-judge-btn"
                                    data-event-id="<?= $eid ?>"
                                    data-user-id="<?= $jUserId ?>"
                                    data-name="<?= htmlspecialchars($judge['full_name'] ?? '', ENT_QUOTES) ?>"
                                    title="Remove judge">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- Judge Mark Sheets -->
        <div class="section-card card mt-3 border-info">
            <div class="card-header bg-white border-0 pt-3 pb-2">
                <h6 class="mb-0 fw-semibold text-info" style="color: #0dcaf0;">
                    <i class="bi bi-file-earmark-pdf me-2"></i> Judge Mark Sheets
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($attendanceLocked)): ?>
                    <div class="alert alert-warning mb-0 py-2 border-0 small">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Attendance must be locked before judge mark sheets can be generated.
                    </div>
                <?php elseif (empty($currentJudges)): ?>
                    <div class="alert alert-info mb-0 py-2 border-0 small">
                        <i class="bi bi-info-circle-fill me-2"></i> No active judges assigned.
                    </div>
                <?php else: ?>
                    <p class="small text-muted mb-2">Download physical mark entry sheets for the assigned judges.</p>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($currentJudges as $judge): ?>
                            <a href="<?= base_url() ?>/attendance/judge-mark-sheet?id=<?= $eid ?>&judge_assignment_id=<?= (int)($judge['judge_assignment_id'] ?? 0) ?>" 
                               class="btn btn-outline-info btn-sm text-start fw-semibold" target="_blank" style="border-color: #0dcaf0; color: #0dcaf0;">
                                <i class="bi bi-download me-2"></i> Download <?= htmlspecialchars($judge['full_name'] ?? '') ?> &mdash; <?= htmlspecialchars($judge['department_name'] ?? 'No Dept') ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ======================================================= -->
    <!-- Feedback Summary Section                                -->
    <!-- ======================================================= -->
    <div class="col-12 mt-4">
        <?php if (!($feedbackFinalized ?? false)): ?>
            <div class="card shadow-sm border-0">
                <div class="card-body text-center py-4">
                    <i class="bi bi-chat-square-text text-muted fs-2 d-block mb-2"></i>
                    <h6 class="fw-bold mb-1">Feedback Unavailable</h6>
                    <p class="text-muted small mb-0">Feedback data will be available after attendance is finalized.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-bar-chart-line me-2"></i>Anonymous Feedback Summary
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php 
                    $subData = [
                        'event'   => $event,
                        'summary' => $feedbackSummary ?? [],
                        'reviews' => $feedbackReviews ?? [],
                        'hideHeader' => true,
                    ];
                    extract($subData);
                    require dirname(__DIR__) . '/feedback/event_summary.php';
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ======================================================= -->
    <!-- RIGHT — Assign Judge Panel                              -->
    <!-- ======================================================= -->
    <div class="col-lg-7">
        <div class="section-card card">
            <div class="card-header bg-white border-0 pt-3 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-person-plus text-success me-2"></i>
                    Assign a Judge
                </h6>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-success bg-opacity-10 text-success" style="font-size:.72rem;">
                        <span class="avail-dot dot-green me-1"></span><?= $availCount ?> Available
                    </span>
                    <span class="badge bg-warning bg-opacity-10 text-warning" style="font-size:.72rem;">
                        <span class="avail-dot dot-orange me-1"></span><?= $busyCount ?> Busy
                    </span>
                </div>
            </div>
            <div class="card-header bg-white border-bottom pt-0 pb-2">
                <input type="text"
                       id="staffSearchInput"
                       class="form-control form-control-sm"
                       placeholder="Search by name, role, or department…"
                       autocomplete="off">
            </div>
            <div class="card-body" style="max-height:480px; overflow-y:auto;">

                <?php if (empty($staffList)): ?>
                    <p class="text-muted text-center py-4 mb-0 small">No staff available to assign as judge.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2" id="staffGrid">
                    <?php foreach ($staffList as $staff):
                        $sUserId = (int)($staff['user_id'] ?? 0);
                        $avail   = $staff['availability_status'] ?? 'available';
                        $dotClass = match($avail) {
                            'available' => 'dot-green',
                            'busy'      => 'dot-orange',
                            default     => 'dot-red',
                        };
                        $itemClass = match($avail) {
                            'busy'     => 'busy-item',
                            'inactive' => 'inact-item',
                            default    => '',
                        };
                        $initials  = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', trim($staff['full_name'] ?? 'ST'))));
                        $initials  = substr($initials, 0, 2);
                        $canAssign = $avail !== 'inactive';
                        $title     = match($avail) {
                            'busy'     => 'Busy on this event date — conflict warning',
                            'inactive' => 'Account inactive',
                            default    => 'Available — click to assign as judge',
                        };
                        
                        // Hide busy/inactive staff from the selection grid as requested
                        if ($avail !== 'available') {
                            continue;
                        }
                    ?>
                        <div class="staff-grid-item <?= $itemClass ?>"
                             data-name="<?= htmlspecialchars(strtolower($staff['full_name'] ?? ''), ENT_QUOTES) ?>"
                             data-role="<?= htmlspecialchars(strtolower($staff['role'] ?? ''), ENT_QUOTES) ?>"
                             data-dept="<?= htmlspecialchars(strtolower($staff['department_name'] ?? ''), ENT_QUOTES) ?>"
                             title="<?= htmlspecialchars($title, ENT_QUOTES) ?>">

                            <span class="avail-dot <?= $dotClass ?>"></span>

                            <div class="person-avatar av-purple" style="width:34px;height:34px;font-size:.8rem;flex-shrink:0;">
                                <?= htmlspecialchars($initials, ENT_QUOTES) ?>
                            </div>

                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate" style="font-size:.83rem;">
                                    <?= htmlspecialchars($staff['full_name'] ?? '', ENT_QUOTES) ?>
                                </div>
                                <div class="text-muted text-truncate" style="font-size:.73rem;">
                                    <?= htmlspecialchars($staff['role'] ?? '', ENT_QUOTES) ?>
                                    <?php if (!empty($staff['department_name'])): ?>
                                        &mdash; <?= htmlspecialchars($staff['department_name'], ENT_QUOTES) ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($canAssign): ?>
                                <button type="button"
                                        class="btn btn-sm btn-success px-2 py-1 flex-shrink-0 assign-judge-btn"
                                        data-event-id="<?= $eid ?>"
                                        data-user-id="<?= $sUserId ?>"
                                        data-name="<?= htmlspecialchars($staff['full_name'] ?? '', ENT_QUOTES) ?>"
                                        data-is-busy="<?= $avail === 'busy' ? '1' : '0' ?>">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            <?php else: ?>
                                <span class="badge bg-secondary" style="font-size:.65rem;">Inactive</span>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- ======================================================= -->
    <!-- FULL WIDTH — Prelims & Stages Config                    -->
    <!-- ======================================================= -->
    <div class="col-12">
        <div class="section-card card border-0">
            <?php
                $status    = $event['status'] ?? '';
                $decision  = $event['prelim_decision'] ?? 'Pending';
                $canDecide = in_array($status, ['Registration Closed', 'Running', 'Completed']);
                $symStart  = $event['symposium_start_date'] ?? '';
                $symEnd    = $event['symposium_end_date'] ?? '';
            ?>
            <div class="card-header bg-white border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 fw-semibold">
                    <i class="bi bi-list-task text-primary me-2"></i>
                    Prelims &amp; Stages
                    <?php if ($decision === 'Required'): ?>
                        <span class="badge bg-warning text-dark ms-2" style="font-size:.7rem;">Prelims Required</span>
                    <?php elseif ($decision === 'Not Required'): ?>
                        <span class="badge bg-success ms-2" style="font-size:.7rem;">No Prelims</span>
                    <?php else: ?>
                        <span class="badge bg-secondary ms-2" style="font-size:.7rem;">Decision Pending</span>
                    <?php endif; ?>
                </h6>
                <div class="d-flex align-items-center gap-2">
                    <?php
                        $userRole = $currentUser['role'] ?? '';
                        $canEditDecision = in_array($userRole, ['Admin', 'Staff Coordinator', 'HOD']);
                    ?>
                    <?php if (!$canDecide): ?>
                        <span class="badge bg-secondary py-2 px-3">
                            <i class="bi bi-lock-fill me-1"></i>Locked — Requires Registration Closed
                        </span>
                        <input type="hidden" id="prelimDecision" value="<?= htmlspecialchars($decision) ?>">
                    <?php else: ?>
                        <label class="form-label small fw-semibold me-1 mb-0" for="prelimDecision">Decision:</label>
                        <select class="form-select form-select-sm" style="width:auto;min-width:140px;" id="prelimDecision" <?= $canEditDecision ? '' : 'disabled' ?>>
                            <option value="Pending" <?= $decision === 'Pending' ? 'selected' : '' ?>>&#9203; Pending</option>
                            <option value="Required" <?= $decision === 'Required' ? 'selected' : '' ?>>&#10003; Required</option>
                            <option value="Not Required" <?= $decision === 'Not Required' ? 'selected' : '' ?>>&#10007; Not Required</option>
                        </select>
                        <?php if (!$canEditDecision): ?>
                            <span class="badge bg-light text-muted border py-1" title="Only Staff Coordinators can change this decision">
                                <i class="bi bi-shield-lock"></i>
                            </span>
                        <?php endif; ?>
                        <span id="decisionSaving" class="text-muted small d-none">
                            <span class="spinner-border spinner-border-sm me-1"></span>Saving…
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <!-- Stages config panel -->
            <div class="card-body bg-light" id="prelimsConfigPanel" style="display:<?= $decision === 'Required' ? 'block' : 'none' ?>;">
                <p class="text-muted small mb-3">
                    <i class="bi bi-info-circle me-1"></i>Define stages and schedule them. Times: 10:00 AM–3:30 PM, weekdays, within symposium dates.
                </p>
                <div id="stagesContainer" class="d-flex flex-column gap-3 mb-3">
                    <?php if (empty($stages)): ?>
                        <div class="stage-row card shadow-sm border-0">
                            <div class="card-body p-3 row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small text-muted mb-1">Stage Name</label>
                                    <select class="form-select form-select-sm stage-name-select">
                                        <option value="Offline Prelims">Offline Prelims</option>
                                        <option value="Digital Prelims">Digital Prelims</option>
                                        <option value="Quarter Finals">Quarter Finals</option>
                                        <option value="Semi Finals">Semi Finals</option>
                                        <option value="Finals">Finals</option>
                                        <option value="Custom">Custom…</option>
                                    </select>
                                </div>
                                <div class="col-md-2 custom-name-col d-none">
                                    <label class="form-label small text-muted mb-1">Custom Name</label>
                                    <input type="text" class="form-control form-control-sm custom-name-input" placeholder="Enter name">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small text-muted mb-1">Date</label>
                                    <input type="date" class="form-control form-control-sm stage-date"
                                           min="<?= $symStart ?>" max="<?= $symEnd ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small text-muted mb-1">Start Time</label>
                                    <input type="time" class="form-control form-control-sm stage-start" min="10:00" max="15:30">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small text-muted mb-1">End Time</label>
                                    <input type="time" class="form-control form-control-sm stage-end" min="10:00" max="15:30">
                                </div>
                                <div class="col-md-1 d-flex justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-stage-btn" title="Remove">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($stages as $stage): ?>
                            <div class="stage-row card shadow-sm border-0">
                                <div class="card-body p-3 row g-2 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">Stage Name</label>
                                        <select class="form-select form-select-sm stage-name-select">
                                            <option value="Offline Prelims" <?= $stage['stage_name'] === 'Offline Prelims' ? 'selected' : '' ?>>Offline Prelims</option>
                                            <option value="Digital Prelims" <?= $stage['stage_name'] === 'Digital Prelims' ? 'selected' : '' ?>>Digital Prelims</option>
                                            <option value="Quarter Finals" <?= $stage['stage_name'] === 'Quarter Finals' ? 'selected' : '' ?>>Quarter Finals</option>
                                            <option value="Semi Finals" <?= $stage['stage_name'] === 'Semi Finals' ? 'selected' : '' ?>>Semi Finals</option>
                                            <option value="Finals" <?= $stage['stage_name'] === 'Finals' ? 'selected' : '' ?>>Finals</option>
                                            <option value="Custom" <?= $stage['stage_name'] === 'Custom' ? 'selected' : '' ?>>Custom…</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 custom-name-col <?= $stage['stage_name'] !== 'Custom' ? 'd-none' : '' ?>">
                                        <label class="form-label small text-muted mb-1">Custom Name</label>
                                        <input type="text" class="form-control form-control-sm custom-name-input"
                                               placeholder="Name" value="<?= htmlspecialchars($stage['custom_name'] ?? '', ENT_QUOTES) ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small text-muted mb-1">Date</label>
                                        <input type="date" class="form-control form-control-sm stage-date"
                                               value="<?= $stage['stage_date'] ?? '' ?>"
                                               min="<?= $symStart ?>" max="<?= $symEnd ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small text-muted mb-1">Start Time</label>
                                        <input type="time" class="form-control form-control-sm stage-start"
                                               value="<?= substr($stage['start_time'] ?? '', 0, 5) ?>"
                                               min="10:00" max="15:30">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small text-muted mb-1">End Time</label>
                                        <input type="time" class="form-control form-control-sm stage-end"
                                               value="<?= substr($stage['end_time'] ?? '', 0, 5) ?>"
                                               min="10:00" max="15:30">
                                    </div>
                                    <div class="col-md-1 d-flex justify-content-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-stage-btn" title="Remove">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addStageBtn">
                    <i class="bi bi-plus-circle me-1"></i> Add Stage
                </button>
            </div>

            <!-- Not Required info panel -->
            <div class="card-body text-center py-4 text-muted" id="prelimsNotRequiredPanel"
                 style="display:<?= $decision === 'Not Required' ? 'block' : 'none' ?>;">
                <i class="bi bi-check-circle-fill text-success fs-3 mb-2 d-block"></i>
                <div class="fw-semibold">No Preliminary Round Required</div>
                <div class="small mt-1">This event proceeds directly to the main event scheduling.</div>
            </div>

            <!-- Pending info panel -->
            <div class="card-body text-center py-4 text-muted" id="prelimsPendingPanel"
                 style="display:<?= ($decision === 'Pending') ? 'block' : 'none' ?>;">
                <i class="bi bi-hourglass-split text-warning fs-3 mb-2 d-block"></i>
                <div class="fw-semibold">Preliminary Decision Pending</div>
                <div class="small mt-1">
                    <?= $canDecide
                        ? 'Select a decision above (Required or Not Required) to proceed.'
                        : 'Decision can only be made after Registration Closes.' ?>
                </div>
            </div>

            <div class="card-footer bg-white border-0 d-flex justify-content-end align-items-center gap-2 py-3" id="stageFooter"
                 style="display:<?= $decision === 'Required' ? 'flex' : 'none' ?>;">
                <span id="stageSaveStatus" class="text-muted small"></span>
                <button type="button" id="savePrelimsBtn" class="btn btn-primary px-4 shadow-sm" style="border-radius:8px;">
                    <i class="bi bi-floppy me-1"></i> Save Stages
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="ficToastContainer" style="z-index: 1060"></div>
<!-- Confirm busy-assign modal                                    -->
<!-- =========================================================== -->
<div class="modal fade" id="busyConfirmModal" tabindex="-1" aria-labelledby="busyConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark border-0">
                <h5 class="modal-title" id="busyConfirmLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Conflict Warning
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong id="busyPersonName"></strong> already has another assignment on <strong><?= $eventDate ? DateHelper::date($eventDate) : 'this day' ?></strong>.</p>
                <p class="mb-0 text-muted small">Do you still want to assign them as a judge for this event?</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning text-dark" id="busyConfirmBtn">Assign Anyway</button>
            </div>
        </div>
    </div>
</div>

<!-- =========================================================== -->
<!-- JavaScript                                                   -->
<!-- =========================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const eventId  = <?= $eid ?>;
    const baseUrl  = '<?= rtrim(base_url(), '/') ?>';

    // ── Helpers ─────────────────────────────────────────────────
    function showToast(message, type = 'success') {
        const tc  = document.getElementById('ficToastContainer');
        const id  = 'toast-' + Date.now();
        const bg  = type === 'success' ? 'bg-success' : 'bg-danger';
        const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
        tc.insertAdjacentHTML('beforeend', `
            <div id="${id}" class="toast align-items-center text-white ${bg} border-0 mb-2 show" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi ${icon} me-2"></i>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);
        setTimeout(() => document.getElementById(id)?.remove(), 4000);
    }

    function setLoading(btn, loading) {
        if (loading) {
            btn.dataset.origHtml = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            btn.disabled  = true;
        } else {
            btn.innerHTML = btn.dataset.origHtml || '';
            btn.disabled  = false;
        }
    }

    async function postAction(url, body) {
        const fd = new FormData();
        Object.entries(body).forEach(([k, v]) => fd.append(k, v));
        const res  = await fetch(url, { method: 'POST', body: fd });
        return res.json();
    }

    // ── Live staff search ───────────────────────────────────────
    document.getElementById('staffSearchInput').addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('#staffGrid .staff-grid-item').forEach(item => {
            const name = item.dataset.name || '';
            const role = item.dataset.role || '';
            const dept = item.dataset.dept || '';
            item.style.display = (!q || name.includes(q) || role.includes(q) || dept.includes(q))
                ? '' : 'none';
        });
    });

    // ── Assign Judge ────────────────────────────────────────────
    let pendingAssign = null;
    const busyModal  = new bootstrap.Modal(document.getElementById('busyConfirmModal'));

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.assign-judge-btn');
        if (!btn) return;

        const userId  = btn.dataset.userId;
        const name    = btn.dataset.name;
        const isBusy  = btn.dataset.isBusy === '1';

        if (isBusy) {
            document.getElementById('busyPersonName').textContent = name;
            pendingAssign = { btn, userId, name };
            busyModal.show();
        } else {
            doAssign(btn, userId, name);
        }
    });

    document.getElementById('busyConfirmBtn').addEventListener('click', function () {
        busyModal.hide();
        if (pendingAssign) {
            doAssign(pendingAssign.btn, pendingAssign.userId, pendingAssign.name);
            pendingAssign = null;
        }
    });

    async function doAssign(btn, userId, name) {
        setLoading(btn, true);
        try {
            const data = await postAction(baseUrl + '/my/assigned-events/assign-judge', {
                symposium_event_id: eventId,
                user_id: userId
            });

            if (data.success) {
                showToast(data.message || name + ' assigned as judge.');
                // Add to judge list
                addJudgeCard({ user_id: userId, full_name: name });
                // Disable the assign button and mark as assigned
                btn.closest('.staff-grid-item').classList.add('is-assigned');
                btn.classList.add('d-none');
            } else {
                showToast(data.message || 'Failed to assign judge.', 'danger');
                setLoading(btn, false);
            }
        } catch (err) {
            showToast('Network error. Please try again.', 'danger');
            setLoading(btn, false);
        }
    }

    function addJudgeCard(judge) {
        let container = document.getElementById('judgeListContainer');
        if (!container) {
            // Find the empty state div and replace it
            const emptyState = document.querySelector('.card-body .text-center');
            if (emptyState) {
                const parent = emptyState.parentElement;
                emptyState.remove();
                container = document.createElement('div');
                container.id = 'judgeListContainer';
                container.className = 'd-flex flex-column gap-2';
                parent.appendChild(container);
            } else {
                location.reload(); return;
            }
        }


        const initials = judge.full_name
            .split(' ')
            .map(w => w[0]?.toUpperCase() || '')
            .join('')
            .substring(0, 2);

        const card = document.createElement('div');
        card.className = 'judge-person-card';
        card.id = 'judge-card-' + judge.user_id;
        card.innerHTML = `
            <div class="person-avatar av-green">${initials}</div>
            <div class="flex-grow-1">
                <div class="fw-semibold small">${judge.full_name}</div>
                <div class="text-muted" style="font-size:.75rem;">Newly Assigned</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger px-2 py-1 remove-judge-btn"
                    data-event-id="${eventId}" data-user-id="${judge.user_id}"
                    data-name="${judge.full_name}" title="Remove judge">
                <i class="bi bi-x-lg"></i>
            </button>
        `;

        container.appendChild(card);
    }

    // ── Remove Judge ────────────────────────────────────────────
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.remove-judge-btn');
        if (!btn) return;

        const userId = btn.dataset.userId;
        const name   = btn.dataset.name;

        if (!confirm(`Remove ${name} as judge for this event?`)) return;

        setLoading(btn, true);
        try {
            const data = await postAction(baseUrl + '/my/assigned-events/remove-judge', {
                symposium_event_id: eventId,
                user_id: userId
            });

            if (data.success) {
                showToast(data.message || name + ' removed from judges.');
                document.getElementById('judge-card-' + userId)?.remove();
                // Re-enable in staff grid if present
                const staffItem = document.querySelector(`.assign-judge-btn[data-user-id="${userId}"]`);
                if (staffItem) {
                    staffItem.classList.remove('d-none');
                    staffItem.closest('.staff-grid-item').classList.remove('is-assigned');
                } else {
                    // If they were loaded as already assigned, they aren't in the DOM staff grid. Reload to refresh the list cleanly.
                    setTimeout(() => location.reload(), 1000);
                }
            } else {
                showToast(data.message || 'Failed to remove judge.', 'danger');
                setLoading(btn, false);
            }
        } catch (err) {
            showToast('Network error. Please try again.', 'danger');
            setLoading(btn, false);
        }
    });

    // ── Prelims: build stages array from DOM ────────────────────
    function buildStagesFromDom() {
        const rows = document.querySelectorAll('#stagesContainer .stage-row');
        const stages = [];
        rows.forEach(row => {
            stages.push({
                stage_name:  row.querySelector('.stage-name-select')?.value  || '',
                custom_name: row.querySelector('.custom-name-input')?.value  || '',
                stage_date:  row.querySelector('.stage-date')?.value         || '',
                start_time:  row.querySelector('.stage-start')?.value        || '',
                end_time:    row.querySelector('.stage-end')?.value          || '',
            });
        });
        return stages;
    }

    // ── Core AJAX prelims save ──────────────────────────────────
    async function savePrelims(decisionVal, includeStages) {
        const stages = (decisionVal === 'Required' && includeStages) ? buildStagesFromDom() : [];
        const res = await fetch(baseUrl + '/my/assigned-events/prelims', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                symposium_event_id: eventId,
                prelim_decision:    decisionVal,
                stages:             JSON.stringify(stages)
            })
        });
        const ct = res.headers.get('content-type') || '';
        if (!ct.includes('application/json')) {
            const txt = await res.text();
            console.error('Non-JSON response:', txt);
            throw new Error('Server error ' + res.status);
        }
        return res.json();
    }

    // ── Panel visibility ────────────────────────────────────────
    function showPanels(decision) {
        const configPanel      = document.getElementById('prelimsConfigPanel');
        const notRequiredPanel = document.getElementById('prelimsNotRequiredPanel');
        const pendingPanel     = document.getElementById('prelimsPendingPanel');
        const footer           = document.getElementById('stageFooter');
        if (configPanel)      configPanel.style.display      = decision === 'Required'     ? 'block' : 'none';
        if (notRequiredPanel) notRequiredPanel.style.display = decision === 'Not Required' ? 'block' : 'none';
        if (pendingPanel)     pendingPanel.style.display     = decision === 'Pending'      ? 'block' : 'none';
        if (footer)           footer.style.display           = decision === 'Required'     ? 'flex'  : 'none';
    }

    // ── Decision dropdown: auto-save on change ──────────────────
    const prelimDecisionEl = document.getElementById('prelimDecision');
    if (prelimDecisionEl && prelimDecisionEl.tagName === 'SELECT') {
        showPanels(prelimDecisionEl.value);

        prelimDecisionEl.addEventListener('change', async function () {
            const val = this.value;
            showPanels(val);
            const spinner = document.getElementById('decisionSaving');
            if (spinner) spinner.classList.remove('d-none');
            try {
                const data = await savePrelims(val, false);
                if (data.success) {
                    showToast('Decision saved: ' + val);
                } else {
                    showToast(data.message || 'Failed to save decision.', 'danger');
                }
            } catch (err) {
                console.error('Decision save error:', err);
                showToast('Network error saving decision.', 'danger');
            } finally {
                if (spinner) spinner.classList.add('d-none');
            }
        });
    }

    // ── Stage name → custom input toggle ───────────────────────
    const stagesContainer = document.getElementById('stagesContainer');
    if (stagesContainer) {
        stagesContainer.addEventListener('change', function (e) {
            if (e.target.classList.contains('stage-name-select')) {
                const row = e.target.closest('.stage-row');
                const col = row?.querySelector('.custom-name-col');
                if (!col) return;
                const inp = col.querySelector('input');
                if (e.target.value === 'Custom') {
                    col.classList.remove('d-none');
                    if (inp) inp.required = true;
                } else {
                    col.classList.add('d-none');
                    if (inp) { inp.required = false; inp.value = ''; }
                }
            }
        });
        stagesContainer.addEventListener('click', function (e) {
            const btn = e.target.closest('.remove-stage-btn');
            if (btn) btn.closest('.stage-row').remove();
        });
    }

    // ── Add Stage ───────────────────────────────────────────────
    const addStageBtn = document.getElementById('addStageBtn');
    if (addStageBtn) {
        addStageBtn.addEventListener('click', function () {
            const symStart = '<?= $symStart ?>';
            const symEnd   = '<?= $symEnd ?>';
            const minAttr  = symStart ? `min="${symStart}"` : '';
            const maxAttr  = symEnd   ? `max="${symEnd}"`   : '';
            const row = `
                <div class="stage-row card shadow-sm border-0">
                    <div class="card-body p-3 row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small text-muted mb-1">Stage Name</label>
                            <select class="form-select form-select-sm stage-name-select">
                                <option value="Offline Prelims">Offline Prelims</option>
                                <option value="Digital Prelims">Digital Prelims</option>
                                <option value="Quarter Finals">Quarter Finals</option>
                                <option value="Semi Finals">Semi Finals</option>
                                <option value="Finals">Finals</option>
                                <option value="Custom">Custom…</option>
                            </select>
                        </div>
                        <div class="col-md-2 custom-name-col d-none">
                            <label class="form-label small text-muted mb-1">Custom Name</label>
                            <input type="text" class="form-control form-control-sm custom-name-input" placeholder="Enter name">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Date</label>
                            <input type="date" class="form-control form-control-sm stage-date" ${minAttr} ${maxAttr}>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">Start Time</label>
                            <input type="time" class="form-control form-control-sm stage-start" min="10:00" max="15:30">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted mb-1">End Time</label>
                            <input type="time" class="form-control form-control-sm stage-end" min="10:00" max="15:30">
                        </div>
                        <div class="col-md-1 d-flex justify-content-end">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-stage-btn" title="Remove">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>`;
            if (stagesContainer) stagesContainer.insertAdjacentHTML('beforeend', row);
        });
    }

    // ── Save Stages button ──────────────────────────────────────
    const savePrelimsBtn = document.getElementById('savePrelimsBtn');
    if (savePrelimsBtn) {
        savePrelimsBtn.addEventListener('click', async function () {
            const decisionVal = prelimDecisionEl ? prelimDecisionEl.value : 'Required';
            // Client-side validation
            const rows = document.querySelectorAll('#stagesContainer .stage-row');
            let valid = true;
            rows.forEach((row, idx) => {
                const n = idx + 1;
                const d = row.querySelector('.stage-date')?.value  || '';
                const s = row.querySelector('.stage-start')?.value || '';
                const e = row.querySelector('.stage-end')?.value   || '';
                if (!d) { showToast(`Stage ${n}: Date is required.`, 'danger'); valid = false; }
                if (!s) { showToast(`Stage ${n}: Start time is required.`, 'danger'); valid = false; }
                if (!e) { showToast(`Stage ${n}: End time is required.`, 'danger'); valid = false; }
                if (s && e && e <= s) { showToast(`Stage ${n}: End time must be after start time.`, 'danger'); valid = false; }
                if (s && s < '10:00') { showToast(`Stage ${n}: Cannot start before 10:00 AM.`, 'danger'); valid = false; }
                if (e && e > '15:30') { showToast(`Stage ${n}: Cannot end after 3:30 PM.`, 'danger'); valid = false; }
            });
            if (!valid) return;

            setLoading(this, true);
            try {
                const data = await savePrelims(decisionVal, true);
                if (data.success) {
                    showToast(data.message || 'Stages saved successfully.');
                    const statusEl = document.getElementById('stageSaveStatus');
                    if (statusEl) statusEl.textContent = 'Last saved at ' +
                        new Date().toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit'});
                } else {
                    showToast(data.message || 'Error saving stages.', 'danger');
                }
            } catch (err) {
                console.error('Stage save error:', err);
                showToast('Network error. Please try again.', 'danger');
            }
            setLoading(this, false);
        });
    }

});
</script>
