<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS — Attendance Report
 * templates/attendance/report.php
 * -------------------------------------------------------------------------
 */

$event         = $event         ?? [];
$participants  = $participants  ?? [];
$summary       = $summary       ?? [];
$deptBreakdown = $deptBreakdown ?? [];
$eid           = (int) ($event['symposium_event_id'] ?? 0);
$sessionId     = $sessionId     ?? 0;

$totalApproved = (int) ($summary['total_approved'] ?? 0);
$present       = (int) ($summary['present'] ?? 0);
$absent        = (int) ($summary['absent'] ?? 0);
$late          = (int) ($summary['late'] ?? 0);
$unmarked      = (int) ($summary['unmarked'] ?? 0);
$pct           = (float) ($summary['attendance_pct'] ?? 0);
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url() ?>/attendance">Attendance</a></li>
        <li class="breadcrumb-item">
            <a href="<?= base_url() ?>/attendance/event?id=<?= $eid ?>">
                <?= htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </a>
        </li>
        <li class="breadcrumb-item active">Report</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1">
            <i class="bi bi-bar-chart text-primary me-2"></i>Attendance Report
        </h2>
        <p class="text-muted mb-0">
            <?= htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            &nbsp;·&nbsp;
            <?= !empty($event['event_date']) ? \App\Helpers\DateHelper::date($event['event_date']) : '' ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url() ?>/attendance/export?id=<?= $eid ?>" class="btn btn-danger btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
        </a>
        <a href="<?= base_url() ?>/attendance/event?id=<?= $eid ?>"
           class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['icon'=>'bi-people-fill',     'label'=>'Total Registered', 'val'=>$totalApproved, 'color'=>'primary', 'bg'=>'#eff6ff'],
        ['icon'=>'bi-check-circle-fill','label'=>'Present',         'val'=>$present,       'color'=>'success', 'bg'=>'#f0fdf4'],
        ['icon'=>'bi-x-circle-fill',   'label'=>'Absent',          'val'=>$absent,        'color'=>'danger',  'bg'=>'#fef2f2'],
        ['icon'=>'bi-clock-fill',      'label'=>'Late',            'val'=>$late,          'color'=>'warning', 'bg'=>'#fffbeb'],
        ['icon'=>'bi-dash-circle',     'label'=>'Unmarked',        'val'=>$unmarked,      'color'=>'secondary','bg'=>'#f8fafc'],
        ['icon'=>'bi-percent',         'label'=>'Attendance %',    'val'=>$pct.'%',       'color'=>'info',    'bg'=>'#ecfeff'],
    ];
    foreach ($cards as $c): ?>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm h-100" style="background:<?= $c['bg'] ?>;">
            <div class="card-body text-center py-3 px-2">
                <i class="bi <?= $c['icon'] ?> text-<?= $c['color'] ?> fs-4 mb-1 d-block"></i>
                <div class="fw-bold fs-4 text-<?= $c['color'] ?>"><?= $c['val'] ?></div>
                <div class="text-muted small"><?= $c['label'] ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Department Breakdown -->
<?php if (!empty($deptBreakdown)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white px-4 py-3">
        <h6 class="fw-bold mb-0">
            <i class="bi bi-building me-2 text-primary"></i>Department-wise Attendance
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
                        <th class="text-center text-muted">Unmarked</th>
                        <th class="text-center">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deptBreakdown as $dept):
                        $dtotal  = max(1, (int)($dept['total'] ?? 0));
                        $dpres   = (int)($dept['present'] ?? 0);
                        $dlate   = (int)($dept['late'] ?? 0);
                        $dabs    = (int)($dept['absent'] ?? 0);
                        $dunmark = (int)($dept['unmarked'] ?? 0);
                        $drate   = round(($dpres + $dlate) / $dtotal * 100);
                    ?>
                    <tr>
                        <td class="px-4 fw-semibold small">
                            <?= htmlspecialchars($dept['department_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="text-center small"><?= $dtotal ?></td>
                        <td class="text-center text-success small fw-semibold"><?= $dpres ?></td>
                        <td class="text-center text-warning small fw-semibold"><?= $dlate ?></td>
                        <td class="text-center text-danger small fw-semibold"><?= $dabs ?></td>
                        <td class="text-center text-muted small"><?= $dunmark ?></td>
                        <td class="text-center">
                            <div class="d-flex align-items-center gap-1">
                                <div class="progress flex-grow-1" style="height:5px;">
                                    <div class="progress-bar bg-<?= $drate>=75?'success':($drate>=50?'warning':'danger') ?>"
                                         style="width:<?= $drate ?>%"></div>
                                </div>
                                <span class="small"><?= $drate ?>%</span>
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

<!-- Full Participant List -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white px-4 py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">
            <i class="bi bi-list-check me-2"></i>Full Attendance List
        </h6>
        <div class="input-group input-group-sm" style="max-width:250px;">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control border-start-0"
                   id="report-search" placeholder="Search…" autocomplete="off">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0" id="report-table">
                <thead class="table-light">
                    <tr>
                        <th class="px-4">#</th>
                        <th>Name</th>
                        <th>Register No.</th>
                        <th>Department</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Source</th>
                        <th>Marked At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($participants)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            No participants found.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($participants as $i => $p):
                        $attStatus  = $p['attendance_status'] ?? null;
                        $markedAt   = $p['marked_at'] ?? null;
                        $syncSource = $p['sync_source'] ?? null;
                        $badgeClass = match($attStatus) {
                            'Present' => 'success',
                            'Absent'  => 'danger',
                            'Late'    => 'warning text-dark',
                            default   => 'light text-muted border',
                        };
                    ?>
                    <tr class="report-row"
                        data-name="<?= htmlspecialchars(strtolower($p['student_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        data-reg="<?= htmlspecialchars(strtolower($p['register_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <td class="px-4 text-muted small"><?= $i + 1 ?></td>
                        <td class="fw-semibold small">
                            <?= htmlspecialchars($p['student_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="small font-monospace">
                            <?= htmlspecialchars($p['register_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="small">
                            <?= htmlspecialchars($p['department_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-<?= $badgeClass ?>">
                                <?= $attStatus ?? 'Unmarked' ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <?php if ($syncSource): ?>
                            <span class="badge <?= $syncSource === 'Offline' ? 'bg-info text-dark' : 'bg-light text-muted border' ?> small">
                                <i class="bi <?= $syncSource === 'Offline' ? 'bi-wifi-off' : 'bi-wifi' ?>"></i>
                                <?= $syncSource ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?= $markedAt ? \App\Helpers\DateHelper::time($markedAt) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Report search filter
document.getElementById('report-search')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.report-row').forEach(row => {
        const match = row.dataset.name.includes(q) || row.dataset.reg.includes(q);
        row.style.display = match ? '' : 'none';
    });
});

document.querySelectorAll('a[href*="/attendance/export?id="]').forEach(link => {
    link.addEventListener('click', (e) => {
        if (!navigator.onLine) {
            e.preventDefault();
            window.nexusUI?.showToast("Offline Mode: PDF export is not available. Open the event to print the Mark Sheet instead.", "warning", 5000);
        }
    });
});
</script>
