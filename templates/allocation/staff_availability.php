<?php
declare(strict_types=1);
/**
 * -------------------------------------------------------------------------
 * NexusCore EMS — Staff Availability Screen
 * Location    : templates/allocation/staff_availability.php
 * Description : Shows availability matrix — all staff vs all events.
 * -------------------------------------------------------------------------
 */
$symposium          = $symposium          ?? [];
$events             = $events             ?? [];
$availabilityMatrix = $availabilityMatrix ?? [];
$departments        = $departments        ?? [];
$deptFilter         = $deptFilter         ?? '';
$roleFilter         = $roleFilter         ?? '';

$symId    = (int) ($symposium['symposium_id'] ?? 0);
$symTitle = htmlspecialchars($symposium['title'] ?? '');
$sessionLabels = ['FN' => 'FN', 'AN' => 'AN', 'Full Day' => 'Full'];
?>
<style>
.avail-dot { display:inline-block; width:10px; height:10px; border-radius:50%; }
.dot-green  { background:#22c55e; box-shadow:0 0 0 2px rgba(34,197,94,.25); }
.dot-orange { background:#f59e0b; box-shadow:0 0 0 2px rgba(245,158,11,.25); }
.dot-red    { background:#ef4444; box-shadow:0 0 0 2px rgba(239,68,68,.25); }
.avail-table { font-size:.8rem; white-space:nowrap; }
.avail-table th { background:#f8f9fa; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; vertical-align:middle; }
.avail-table td { vertical-align:middle; }
.avail-table .sticky-col { position:sticky; left:0; z-index:2; border-right:1px solid #dee2e6; box-shadow: 2px 0 5px -2px rgba(0,0,0,0.05); }
.avail-table thead th.sticky-col { z-index:3; background:#f8f9fa; }
.avail-table tbody td.sticky-col { z-index:1; background:#fff; }
.avail-cell { display:inline-flex; align-items:center; gap:4px; }
.filter-bar { background:#f8f9fa; border-radius:12px; padding:.75rem 1rem; margin-bottom:1rem; display:flex; gap:.65rem; flex-wrap:wrap; align-items:center; }
.legend-item { display:flex; align-items:center; gap:5px; font-size:.78rem; color:#6c757d; }
/* Ensure scroll works properly */
.table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
</style>

<!-- Header -->
<div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-4">
    <div>
        <nav aria-label="breadcrumb" class="mb-1">
            <ol class="breadcrumb mb-0" style="font-size:.82rem;">
                <li class="breadcrumb-item"><a href="<?= base_url() ?>/symposiums">Symposiums</a></li>
                <li class="breadcrumb-item"><a href="<?= base_url() ?>/symposiums/allocation?symposium_id=<?= $symId ?>">Staff Allocation</a></li>
                <li class="breadcrumb-item active">Staff Availability</li>
            </ol>
        </nav>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-calendar2-check me-2 text-success"></i>Staff Availability</h1>
        <p class="text-secondary mb-0" style="font-size:.87rem;"><?= $symTitle ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url() ?>/symposiums/allocation?symposium_id=<?= $symId ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Allocation
        </a>
    </div>
</div>

<!-- Legend -->
<div class="d-flex gap-4 mb-3 flex-wrap">
    <span class="legend-item"><span class="avail-dot dot-green"></span> Available — No assignment this day</span>
    <span class="legend-item"><span class="avail-dot dot-orange"></span> Busy — Has another assignment this day</span>
    <span class="legend-item"><span class="avail-dot dot-red"></span> Inactive — Account not active</span>
    <span class="legend-item"><span class="badge bg-primary" style="font-size:.68rem;">FIC</span> Assigned as Faculty In-Charge</span>
    <span class="legend-item"><span class="badge bg-success" style="font-size:.68rem;">J</span> Assigned as Judge</span>
</div>

<!-- Filters -->
<form method="GET" action="">
    <input type="hidden" name="symposium_id" value="<?= $symId ?>">
    <div class="filter-bar">
        <i class="bi bi-funnel text-secondary"></i>
        <select name="dept" class="form-select form-select-sm" style="max-width:180px;" onchange="this.form.submit()">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= htmlspecialchars((string)$d['department_id']) ?>" <?= ($deptFilter == $d['department_id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['department_name'] ?? $d['department_code']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <select name="role" class="form-select form-select-sm" style="max-width:160px;" onchange="this.form.submit()">
            <option value="">All Roles</option>
            <?php foreach (['Admin', 'Principal', 'HOD', 'Staff Coordinator', 'Staff'] as $r): ?>
            <option value="<?= htmlspecialchars($r) ?>" <?= ($roleFilter === $r) ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($deptFilter || $roleFilter): ?>
        <a href="?symposium_id=<?= $symId ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle me-1"></i>Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php if (empty($events)): ?>
<div class="text-center py-5 text-muted">
    <i class="bi bi-calendar-x display-4 opacity-40"></i>
    <p class="mt-3">No scheduled events found for this symposium.</p>
</div>
<?php else: ?>

<?php
// Collect unique staff across all events
$staffMap = [];
foreach ($availabilityMatrix as $eid => $staffList) {
    foreach ($staffList as $s) {
        $uid = (int) $s['user_id'];
        if (!isset($staffMap[$uid])) {
            $staffMap[$uid] = $s;
        }
    }
}
// Sort: available first
uasort($staffMap, fn($a, $b) => strcmp($a['full_name'] ?? '', $b['full_name'] ?? ''));
?>

<div class="card border-0 shadow-sm" style="border-radius:16px; overflow:hidden;">
    <div class="table-responsive">
        <table class="table table-bordered avail-table mb-0">
            <thead>
                <tr>
                    <th class="sticky-col" style="min-width:200px;">Staff Member</th>
                    <th style="min-width:140px;">Role / Dept</th>
                    <?php foreach ($events as $event): ?>
                    <th class="text-center" style="min-width:140px; white-space:nowrap; padding-left:1rem; padding-right:1rem;">
                        <div class="mb-1 fw-bold" style="font-size:.75rem;">
                            <?= htmlspecialchars($event['event_name'] ?? '') ?>
                        </div>
                        <div class="fw-normal" style="font-size:.68rem; color:#6c757d; white-space:nowrap;">
                            <?php if (!empty($event['event_date']) || !empty($event['session'])): ?>
                                <?= $event['event_date'] ? \App\Helpers\DateHelper::date($event['event_date']) : '' ?>
                                <?= (!empty($event['event_date']) && !empty($event['session'])) ? '&bull;' : '' ?>
                                <?= htmlspecialchars($sessionLabels[$event['session'] ?? ''] ?? '') ?>
                            <?php endif; ?>
                        </div>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffMap as $uid => $staffInfo): ?>
                <tr>
                    <td class="sticky-col">
                        <div class="fw-semibold" style="font-size:.84rem; white-space:nowrap; padding-right: 1rem;">
                            <?= htmlspecialchars($staffInfo['full_name'] ?? '') ?>
                        </div>
                    </td>
                    <td style="white-space:nowrap; padding-right: 2rem;">
                        <div style="font-size:.75rem; color:#6c757d;">
                            <span class="fw-medium"><?= htmlspecialchars($staffInfo['role'] ?? '') ?></span>
                            <?php if (!empty($staffInfo['department_name'])): ?>
                            <span class="mx-1">&bull;</span><?= htmlspecialchars($staffInfo['department_name']) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php foreach ($events as $event): ?>
                    <?php
                        $eid = (int) $event['symposium_event_id'];
                        $found = null;
                        foreach (($availabilityMatrix[$eid] ?? []) as $s) {
                            if ((int)$s['user_id'] === $uid) { $found = $s; break; }
                        }
                        $status   = $found['availability_status'] ?? 'available';
                        $isFic    = (bool) ($found['is_faculty'] ?? false);
                        $isJudge  = (bool) ($found['is_judge'] ?? false);
                        $conflict = $found['conflict_detail'] ?? null;
                        $dot      = match($status) {
                            'busy'     => '<span class="avail-dot dot-orange"></span>',
                            'inactive' => '<span class="avail-dot dot-red"></span>',
                            default    => '<span class="avail-dot dot-green"></span>',
                        };
                    ?>
                    <td class="text-center">
                        <div class="d-flex flex-column align-items-center gap-1">
                            <?= $dot ?>
                            <?php if ($isFic):   ?><span class="badge bg-primary"  style="font-size:.62rem;">FIC</span><?php endif; ?>
                            <?php if ($isJudge): ?><span class="badge bg-success"  style="font-size:.62rem;">J</span><?php endif; ?>
                            <?php if ($status === 'busy' && $conflict): ?>
                            <span title="<?= htmlspecialchars("{$conflict['type']} — {$conflict['event_name']} ({$conflict['session']})") ?>"
                                  style="cursor:help; font-size:.65rem; color:#f59e0b;">
                                <i class="bi bi-info-circle"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($staffMap)): ?>
                <tr><td colspan="<?= 2 + count($events) ?>" class="text-center text-muted py-4">No staff found for selected filters.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
