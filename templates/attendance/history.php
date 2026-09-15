<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS — Attendance History
 * templates/attendance/history.php
 * -------------------------------------------------------------------------
 */

$event    = $event    ?? [];
$sessions = $sessions ?? [];
$summary  = $summary  ?? [];
$eid      = (int) ($event['symposium_event_id'] ?? 0);
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
        <li class="breadcrumb-item active">Session History</li>
    </ol>
</nav>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h2 class="fw-bold mb-1">
            <i class="bi bi-clock-history text-primary me-2"></i>Attendance History
        </h2>
        <p class="text-muted mb-0">
            <?= htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
    <a href="<?= base_url() ?>/attendance/event?id=<?= $eid ?>"
       class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Mark Sheet
    </a>
</div>

<!-- Summary Row -->
<div class="row g-3 mb-4">
    <?php
    $sCards = [
        ['label'=>'Total Sessions', 'val'=> count($sessions), 'icon'=>'bi-calendar-check', 'color'=>'primary'],
        ['label'=>'Present',        'val'=> $summary['present'] ?? 0, 'icon'=>'bi-check-circle', 'color'=>'success'],
        ['label'=>'Absent',         'val'=> $summary['absent']  ?? 0, 'icon'=>'bi-x-circle',     'color'=>'danger'],
        ['label'=>'Late',           'val'=> $summary['late']    ?? 0, 'icon'=>'bi-clock',         'color'=>'warning'],
    ];
    foreach ($sCards as $sc): ?>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
            <i class="bi <?= $sc['icon'] ?> text-<?= $sc['color'] ?> fs-4 mb-1"></i>
            <div class="fw-bold fs-5"><?= $sc['val'] ?></div>
            <div class="text-muted small"><?= $sc['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Sessions Table -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white px-4 py-3">
        <h6 class="fw-bold mb-0">
            <i class="bi bi-list-ul me-2"></i>All Sessions
        </h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($sessions)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
            No attendance sessions found for this event.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4">Session</th>
                        <th>Status</th>
                        <th>Opened By</th>
                        <th>Opened At</th>
                        <th>Closed At</th>
                        <th>Duration</th>
                        <th class="px-4">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sessions as $i => $sess):
                        $openedAt = $sess['opened_at'] ?? '';
                        $closedAt = $sess['closed_at'] ?? '';
                        $duration = '';
                        if ($openedAt && $closedAt) {
                            $diff = strtotime($closedAt) - strtotime($openedAt);
                            $duration = round($diff / 60) . ' min';
                        } elseif ($openedAt) {
                            $duration = 'Active';
                        }
                    ?>
                    <tr>
                        <td class="px-4 fw-semibold">#<?= $i + 1 ?></td>
                        <td>
                            <span class="badge bg-<?= $sess['status'] === 'Open' ? 'success' : 'secondary' ?>">
                                <?= $sess['status'] ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($sess['opened_by_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="small"><?= $openedAt ? \App\Helpers\DateHelper::dateTime($openedAt) : '—' ?></td>
                        <td class="small"><?= $closedAt ? \App\Helpers\DateHelper::dateTime($closedAt) : '—' ?></td>
                        <td class="small <?= $duration === 'Active' ? 'text-success' : 'text-muted' ?>"><?= $duration ?></td>
                        <td class="px-4 small text-muted">
                            <?= htmlspecialchars($sess['notes'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
