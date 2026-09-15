<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : mark-sheet.php
 * Location    : templates/attendance/
 * Description : The main Offline-First Attendance Mark Sheet.
 *
 * This view is the heart of the Attendance Module.
 * It supports full offline operation via IndexedDB + Service Worker.
 * -------------------------------------------------------------------------
 */

$event          = $event          ?? [];
$activeSession  = $activeSession  ?? null;
$sessionId      = $sessionId      ?? 0;
$participants   = $participants   ?? [];
$summary        = $summary        ?? [];
$history        = $history        ?? [];
$deptBreakdown  = $deptBreakdown  ?? [];
$ficAssignments = $ficAssignments ?? [];
$canMark        = $canMark        ?? false;
$csrfToken      = $csrfToken      ?? '';
$currentJudges  = $currentJudges  ?? [];
$isFic          = $isFic          ?? false;
$attendanceLocked = $attendanceLocked ?? false;

$eid         = (int) ($event['symposium_event_id'] ?? 0);
$sessionOpen = !empty($activeSession) && $activeSession['status'] === 'Open';

// Prepare judge download URLs for JS (used for offline-close reveal)
$judgeDownloadUrls = [];
foreach ($currentJudges as $j) {
    $judgeDownloadUrls[] = [
        'name'              => htmlspecialchars($j['full_name'] ?? '', ENT_QUOTES),
        'department'        => htmlspecialchars($j['department_name'] ?? '', ENT_QUOTES),
        'judge_assignment_id' => (int) ($j['judge_assignment_id'] ?? 0),
    ];
}
?>

<!-- ======================================================= -->
<!-- Network Status Bar                                       -->
<!-- ======================================================= -->
<div id="network-status-bar" class="network-status-bar network-checking">
    <i class="bi bi-wifi me-1" id="network-icon"></i>
    <span id="network-text">Online</span>
    <span class="ms-3" id="sync-status-text" style="font-size:0.8rem;display:none;"></span>
    <span class="ms-auto d-flex align-items-center gap-2">
        <span id="pending-badge-bar" style="display:none;">
            <span class="badge bg-warning text-dark">
                <i class="bi bi-clock-history me-1"></i>
                <span id="pending-count-bar">0</span> Pending
            </span>
        </span>
        <button class="btn btn-sm btn-network-sync py-0" id="manual-sync-btn"
                style="display:none;font-size:0.75rem;" title="Sync now">
            <i class="bi bi-arrow-repeat"></i> Sync
        </button>
    </span>
</div>

<!-- Hidden data for JS -->
<script>
    window.NEXUS_ATTENDANCE = {
        symposiumEventId : <?= $eid ?>,
        sessionId        : <?= $sessionId ?>,
        sessionOpen      : <?= $sessionOpen ? 'true' : 'false' ?>,
        canMark          : <?= $canMark ? 'true' : 'false' ?>,
        csrfToken        : <?= json_encode($csrfToken) ?>,
        offlineSyncToken : <?= json_encode($offlineSyncToken) ?>,
        baseUrl          : <?= json_encode(base_url()) ?>,
        isFic            : <?= $isFic ? 'true' : 'false' ?>,
        attendanceLocked : <?= $attendanceLocked ? 'true' : 'false' ?>,
        judgeDownloads   : <?= json_encode($judgeDownloadUrls) ?>,
    };
</script>

<!-- ======================================================= -->
<!-- Page Header                                              -->
<!-- ======================================================= -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb" class="mb-1 d-print-none">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="<?= base_url() ?>/attendance">Attendance</a>
                </li>
                <li class="breadcrumb-item active">
                    <?= htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1">
            <i class="bi bi-calendar2-check text-primary me-2"></i>
            <?= htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p class="text-muted mb-0">
            <?= htmlspecialchars($event['event_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            &nbsp;·&nbsp;
            <?= !empty($event['event_date'])
                ? \App\Helpers\DateHelper::date($event['event_date'])
                : 'Date not set' ?>
            <?php if (!empty($ficAssignments)): ?>
            &nbsp;·&nbsp;
            <span class="text-muted">
                FIC: <?= htmlspecialchars(implode(', ', array_column($ficAssignments, 'full_name')), ENT_QUOTES, 'UTF-8') ?>
            </span>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap d-print-none">
        <button type="button" class="btn btn-danger btn-sm" onclick="window.print()">
            <i class="bi bi-file-earmark-pdf me-1"></i>Save PDF
        </button>
        <a href="<?= base_url() ?>/attendance/history?id=<?= $eid ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-clock-history me-1"></i>History
        </a>
        <a href="<?= base_url() ?>/attendance/report?id=<?= $eid ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-bar-chart me-1"></i>Report
        </a>
    </div>
</div>

<!-- ======================================================= -->
<!-- Session Status Panel                                     -->
<!-- ======================================================= -->
<div class="card border-0 shadow-sm mb-4 <?= $sessionOpen ? 'border-start border-5 border-success' : 'border-start border-5 border-secondary' ?> d-print-none">
    <div class="card-body py-3 px-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <?php if ($sessionOpen): ?>
                    <span id="session-status-badge" class="badge bg-success me-2">
                        <i class="bi bi-circle-fill" style="font-size:0.5rem;"></i> Session Open
                    </span>
                    <span id="session-status-text" class="text-muted small">
                        Opened at
                        <?= \App\Helpers\DateHelper::time($activeSession['opened_at']) ?>
                        by <?= htmlspecialchars($activeSession['opened_by_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                <?php elseif (!empty($activeSession)): ?>
                    <span id="session-status-badge" class="badge bg-secondary me-2">Session Closed</span>
                    <span id="session-status-text" class="text-muted small">
                        Closed at <?= \App\Helpers\DateHelper::time($activeSession['closed_at'] ?? '') ?>
                    </span>
                <?php elseif (empty($event['supports_attendance'])): ?>
                    <span id="session-status-badge" class="badge bg-secondary me-2">Attendance Disabled</span>
                    <span id="session-status-text" class="text-muted small">
                        Attendance tracking is not required for this event.
                    </span>
                <?php else: ?>
                    <span id="session-status-badge" class="badge bg-secondary me-2">No Session</span>
                    <span id="session-status-text" class="text-muted small">
                        Open a session to begin marking attendance.
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($canMark): ?>
            <div class="d-flex gap-2">
                <?php if (!$sessionOpen): ?>
                <!-- Open Session Button (Triggers Modal) -->
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#openSessionModal" id="btn-open-session-modal">
                    <i class="bi bi-unlock me-1"></i>Open Attendance Session
                </button>
                
                <!-- Hidden Close Session Form for offline transition -->
                <form id="close-session-form" method="POST" action="<?= base_url() ?>/attendance/close" style="display:none;">
                    <input type="hidden" name="csrf_token"          value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="session_id"          value="0" id="hidden_session_id">
                    <input type="hidden" name="symposium_event_id"  value="<?= $eid ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-lock me-1"></i>Close Session
                    </button>
                </form>
                <?php else: ?>
                <!-- Close Session Button -->
                <form id="close-session-form" method="POST" action="<?= base_url() ?>/attendance/close">
                    <input type="hidden" name="csrf_token"          value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="session_id"          value="<?= $sessionId ?>">
                    <input type="hidden" name="symposium_event_id"  value="<?= $eid ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-lock me-1"></i>Close Session
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ======================================================= -->
<!-- Summary Stats Row                                        -->
<!-- ======================================================= -->
<div class="row g-3 mb-4">
    <?php
    $totalApproved = (int) ($summary['total_registered'] ?? 0);
    $present       = (int) ($summary['present']          ?? 0);
    $absent        = (int) ($summary['absent']           ?? 0);
    $late          = (int) ($summary['late']             ?? 0);
    $unmarked      = (int) ($summary['unmarked']         ?? 0);
    $pct           = (float) ($summary['attendance_pct'] ?? 0);
    $statCards = [
        ['icon'=>'bi-people-fill',     'label'=>'Total Approved', 'val'=>$totalApproved, 'color'=>'primary'],
        ['icon'=>'bi-check-circle-fill','label'=>'Present',        'val'=>$present,       'color'=>'success'],
        ['icon'=>'bi-x-circle-fill',   'label'=>'Absent',         'val'=>$absent,        'color'=>'danger'],
        ['icon'=>'bi-clock-fill',      'label'=>'Late',           'val'=>$late,          'color'=>'warning'],
        ['icon'=>'bi-dash-circle-fill','label'=>'Unmarked',       'val'=>$unmarked,      'color'=>'secondary'],
        ['icon'=>'bi-percent',         'label'=>'Attendance %',   'val'=>$pct.'%',       'color'=>'info'],
    ];
    ?>
    <?php foreach ($statCards as $sc): ?>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <i class="bi <?= $sc['icon'] ?> text-<?= $sc['color'] ?> fs-4 mb-1"></i>
                <div class="fw-bold fs-5" id="stat-<?= strtolower(str_replace([' ', '%'], ['-', 'pct'], $sc['label'])) ?>"><?= $sc['val'] ?></div>
                <div class="text-muted small"><?= $sc['label'] ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ======================================================= -->
<!-- Attendance Mark Sheet                                    -->
<!-- ======================================================= -->
<?php if ($sessionOpen || !empty($participants)): ?>
<div class="card border-0 shadow-sm mb-4" id="attendance-marksheet-card">

    <!-- Card Header: Search + Filters + Bulk Actions -->
    <div class="card-header bg-white px-4 py-3 border-bottom d-print-none">
        <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">

            <!-- Search Box -->
            <div class="flex-grow-1" style="max-width:320px;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input type="text" id="attendance-search" class="form-control border-start-0"
                           placeholder="Search name, reg. no., department…"
                           autocomplete="off">
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="btn-group btn-group-sm" id="filter-tabs" role="group">
                <button type="button" class="btn btn-outline-secondary active" data-filter="All">All</button>
                <button type="button" class="btn btn-outline-success" data-filter="Present">Present</button>
                <button type="button" class="btn btn-outline-danger"  data-filter="Absent">Absent</button>
                <button type="button" class="btn btn-outline-warning" data-filter="Late">Late</button>
                <button type="button" class="btn btn-outline-secondary" data-filter="Unmarked">Unmarked</button>
            </div>

            <!-- Bulk Actions (only when session is open and can mark) -->
            <?php if ($sessionOpen && $canMark): ?>
            <div class="d-flex gap-2" id="bulk-actions-container">
                <button class="btn btn-success btn-sm" id="bulk-present-btn">
                    <i class="bi bi-check-all me-1"></i>All Present
                </button>
                <button class="btn btn-danger btn-sm" id="bulk-absent-btn">
                    <i class="bi bi-x-lg me-1"></i>All Absent
                </button>
            </div>
            <?php endif; ?>
        </div>

        <div class="mt-2 text-muted small" id="participant-count-info">
            Showing <span id="shown-count"><?= count($participants) ?></span>
            of <?= count($participants) ?> participants
        </div>
    </div>

    <!-- Card Body: Participant Table -->
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle" id="attendance-table">
                <thead class="table-light sticky-top">
                    <tr>
                        <th class="px-4" style="width:50px;">#</th>
                        <th>Participant</th>
                        <th>Department</th>
                        <th class="text-center" style="width:120px;">Type</th>
                        <th class="text-center" style="width:220px;">Attendance</th>
                        <th class="text-center" style="width:100px;">Source</th>
                    </tr>
                </thead>
                <tbody id="participant-table-body">
                <?php if (empty($participants)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            No approved participants found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($participants as $i => $p):
                        $appId       = (int) $p['application_id'];
                        $stuId       = (int) $p['student_id'];
                        $attStatus   = $p['attendance_status'] ?? null;  // null = unmarked
                        $syncSource = $p['sync_source'] ?? 'Server';
                        $isPending   = false; // Will be set by JS for offline records
                    ?>
                    <tr class="participant-row"
                        data-application-id="<?= $appId ?>"
                        data-student-id="<?= $stuId ?>"
                        data-name="<?= htmlspecialchars(strtolower($p['student_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-reg="<?= htmlspecialchars(strtolower($p['register_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-dept="<?= htmlspecialchars(strtolower($p['department_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-status="<?= htmlspecialchars($attStatus ?? 'Unmarked', ENT_QUOTES, 'UTF-8') ?>"
                        data-sync-source="<?= htmlspecialchars($syncSource, ENT_QUOTES, 'UTF-8') ?>">

                        <!-- Index -->
                        <td class="px-4 text-muted small"><?= $i + 1 ?></td>

                        <!-- Participant Info -->
                        <td>
                            <div class="fw-semibold">
                                <?= htmlspecialchars($p['student_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="small text-muted">
                                <?= htmlspecialchars($p['register_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                <?php if (($p['application_type'] ?? '') === 'Team'): ?>
                                    · <i class="bi bi-people-fill me-1"></i>
                                    Team Application
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Department -->
                        <td class="small">
                            <?= htmlspecialchars($p['department_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <!-- Type -->
                        <td class="text-center">
                            <span class="badge bg-light text-dark border">
                                <?= htmlspecialchars($p['application_type'] ?? 'Individual', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>

                        <!-- Attendance Buttons -->
                        <td class="text-center" id="att-cell-<?= $appId ?>-<?= $stuId ?>">
                            <?php if ($canMark): ?>
                            <div class="attendance-btn-group" role="group" <?= !$sessionOpen ? 'style="display:none;"' : '' ?>>
                                <button type="button"
                                        class="btn btn-sm att-btn att-present <?= $attStatus === 'Present' ? 'btn-success active' : 'btn-outline-success' ?>"
                                        data-status="Present"
                                        data-app-id="<?= $appId ?>"
                                        data-stu-id="<?= $stuId ?>"
                                        title="Mark Present (P)">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                                <button type="button"
                                        class="btn btn-sm att-btn att-late <?= $attStatus === 'Late' ? 'btn-warning active' : 'btn-outline-warning' ?>"
                                        data-status="Late"
                                        data-app-id="<?= $appId ?>"
                                        data-stu-id="<?= $stuId ?>"
                                        title="Mark Late (L)">
                                    <i class="bi bi-clock"></i>
                                </button>
                                <button type="button"
                                        class="btn btn-sm att-btn att-absent <?= $attStatus === 'Absent' ? 'btn-danger active' : 'btn-outline-danger' ?>"
                                        data-status="Absent"
                                        data-app-id="<?= $appId ?>"
                                        data-stu-id="<?= $stuId ?>"
                                        title="Mark Absent (A)">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($attStatus): ?>
                            <span class="badge bg-<?= $attStatus === 'Present' ? 'success' : ($attStatus === 'Late' ? 'warning text-dark' : 'danger') ?> badge-status" <?= ($sessionOpen && $canMark) ? 'style="display:none;"' : '' ?>>
                                <?= $attStatus ?>
                            </span>
                            <?php else: ?>
                            <span class="badge bg-light text-muted border badge-unmarked" <?= ($sessionOpen && $canMark) ? 'style="display:none;"' : '' ?>>Unmarked</span>
                            <?php endif; ?>
                        </td>

                        <!-- Sync Source -->
                        <td class="text-center" id="sync-cell-<?= $appId ?>-<?= $stuId ?>">
                            <?php if ($attStatus): ?>
                            <span class="badge <?= $syncSource === 'Offline' ? 'bg-info text-dark' : 'bg-light text-muted border' ?> small">
                                <i class="bi <?= $syncSource === 'Offline' ? 'bi-wifi-off' : 'bi-wifi' ?>"></i>
                                <?= $syncSource ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Card Footer: Offline Notice -->
    <div class="card-footer bg-white border-top px-4 py-2">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <small class="text-muted">
                <i class="bi bi-info-circle me-1"></i>
                Attendance can be marked while <strong>offline</strong>. Records will sync automatically when connectivity returns.
            </small>
            <div id="offline-queue-info" style="display:none;">
                <span class="badge bg-warning text-dark">
                    <i class="bi bi-clock-history me-1"></i>
                    <span id="offline-pending-count">0</span> records pending sync
                </span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ======================================================= -->
<!-- Department Breakdown (if session data available)        -->
<!-- ======================================================= -->
<?php if (!empty($deptBreakdown) || !empty($participants)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white px-4 py-3">
        <h6 class="fw-bold mb-0">
            <i class="bi bi-building me-2 text-primary"></i>Department-wise Breakdown
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4">Department</th>
                        <th class="text-center">Total</th>
                        <th class="text-center text-success">Present</th>
                        <th class="text-center text-warning">Late</th>
                        <th class="text-center text-danger">Absent</th>
                        <th class="text-center text-secondary">Unmarked</th>
                        <th class="text-center">Rate</th>
                    </tr>
                </thead>
                <tbody id="dept-breakdown-body">
                    <?php foreach ($deptBreakdown as $dept):
                        $total    = max(1, (int)($dept['total'] ?? 0));
                        $dpresent = (int)($dept['present'] ?? 0);
                        $dlate    = (int)($dept['late'] ?? 0);
                        $dabsent  = (int)($dept['absent'] ?? 0);
                        $dunmark  = (int)($dept['unmarked'] ?? 0);
                        $drate    = round(($dpresent + $dlate) / $total * 100);
                    ?>
                    <tr>
                        <td class="px-4">
                            <?= htmlspecialchars($dept['department_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="text-center"><?= $total ?></td>
                        <td class="text-center text-success fw-semibold"><?= $dpresent ?></td>
                        <td class="text-center text-warning fw-semibold"><?= $dlate ?></td>
                        <td class="text-center text-danger fw-semibold"><?= $dabsent ?></td>
                        <td class="text-center text-muted"><?= $dunmark ?></td>
                        <td class="text-center">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:5px;">
                                    <div class="progress-bar bg-<?= $drate>=75?'success':($drate>=50?'warning':'danger') ?>"
                                         style="width:<?= $drate ?>%"></div>
                                </div>
                                <span class="small fw-semibold" style="min-width:35px;"><?= $drate ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
// Show judge mark sheets if:
//  (a) user is FIC/Admin, AND
//  (b) session is already locked (server-side confirmed), OR session was open & will be closed (JS will reveal)
//  Always render the card div so JS can show/hide it.
$showJudgeSection = ($isFic || ($user['role'] ?? '') === 'Admin') && !empty($currentJudges);
?>
<?php if ($showJudgeSection): ?>
<!-- ======================================================= -->
<!-- Judge Mark Sheets                                        -->
<!-- ======================================================= -->
<div class="card border-0 shadow-sm mb-4 d-print-none" id="judge-marksheet-card"
     style="<?= $attendanceLocked ? '' : 'display:none;' ?>">
    <div class="card-header bg-white px-4 py-3 d-flex align-items-center justify-content-between">
        <h6 class="fw-bold mb-0">
            <i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Official Judge Mark Sheets
        </h6>
        <span class="badge bg-success bg-opacity-10 text-success fw-semibold" style="font-size:.75rem;">
            <i class="bi bi-lock-fill me-1"></i>Attendance Locked
        </span>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Download the physical mark entry sheet for each assigned judge. Print and hand to each judge for manual score entry.
        </p>
        <div class="row g-3" id="judge-marksheet-buttons">
            <?php foreach ($currentJudges as $judge): ?>
            <div class="col-sm-6 col-lg-4">
                <a href="<?= base_url() ?>/attendance/judge-mark-sheet?id=<?= $eid ?>&judge_assignment_id=<?= (int)($judge['judge_assignment_id'] ?? 0) ?>"
                   class="d-flex align-items-center gap-3 p-3 rounded-3 border text-decoration-none"
                   style="border-color:#dee2e6; transition: box-shadow .15s, border-color .15s;"
                   onmouseover="this.style.boxShadow='0 3px 14px rgba(0,0,0,.1)'; this.style.borderColor='#dc3545';"
                   onmouseout="this.style.boxShadow=''; this.style.borderColor='#dee2e6';"
                   target="_blank">
                    <div style="width:44px;height:44px;border-radius:50%;background:rgba(220,53,69,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-file-earmark-arrow-down text-danger" style="font-size:1.25rem;"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold small text-truncate"><?= htmlspecialchars($judge['full_name'] ?? '', ENT_QUOTES) ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($judge['department_name'] ?? '', ENT_QUOTES) ?></div>
                    </div>
                    <i class="bi bi-download text-danger"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ======================================================= -->
<!-- Open Session Modal                                       -->
<!-- ======================================================= -->
<?php if ($canMark && !$sessionOpen): ?>
<div class="modal fade" id="openSessionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-unlock text-success me-2"></i>Open Attendance Session
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Opening a session will allow you to mark attendance for all approved participants.
                    Only one session can be open at a time.
                </p>
                <form method="POST" action="<?= base_url() ?>/attendance/open" id="open-session-form">
                    <input type="hidden" name="csrf_token"    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="symposium_event_id" value="<?= $eid ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Session Notes (Optional)</label>
                        <input type="text" name="notes" class="form-control"
                               placeholder="e.g., Morning session, Hall A">
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-unlock me-2"></i>Open Session Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ======================================================= -->
<!-- Hidden CSRF for AJAX calls                              -->
<!-- ======================================================= -->
<input type="hidden" id="csrf-token-global" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

<!-- Attendance JS Modules -->
<script src="<?= asset('assets/js/modules/attendance-marksheet.js') ?>?v=<?= time() ?>"></script>
