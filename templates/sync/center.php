<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS — Sync Center
 * templates/sync/center.php
 * -------------------------------------------------------------------------
 */

$recent  = $recent  ?? [];
$summary = $summary ?? [];
$role    = $role    ?? '';
?>

<!-- ======================================================= -->
<!-- Network Status Bar                                       -->
<!-- ======================================================= -->
<div id="network-status-bar" class="network-status-bar network-checking">
    <i class="bi bi-wifi me-1" id="network-icon"></i>
    <span id="network-text">Online</span>
    <span class="ms-3 small text-muted" id="last-sync-text"></span>
    <span class="ms-auto" id="pending-badge-bar" style="display:none;">
        <span class="badge bg-warning text-dark">
            <i class="bi bi-clock-history me-1"></i>
            <span id="pending-count-bar">0</span> Pending
        </span>
    </span>
</div>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h2 class="fw-bold mb-1">
            <i class="bi bi-arrow-repeat text-primary me-2"></i>Sync Center
        </h2>
        <p class="text-muted mb-0">
            Monitor and manage offline attendance synchronization.
        </p>
    </div>
    <button class="btn btn-primary btn-sm" id="sync-all-btn">
        <i class="bi bi-arrow-repeat me-1"></i>Sync All Pending
    </button>
</div>

<!-- Summary Stats -->
<div class="row g-3 mb-4">
    <?php
    $lastSync = $summary['last_sync_at']
        ? \App\Helpers\DateHelper::dateTime($summary['last_sync_at'])
        : 'Never';

    $sCards = [
        ['label'=>'Total Syncs',        'val'=> $summary['total']           ?? 0, 'icon'=>'bi-arrow-repeat',  'color'=>'primary'],
        ['label'=>'Completed',          'val'=> $summary['completed']       ?? 0, 'icon'=>'bi-check-circle',  'color'=>'success'],
        ['label'=>'Partial',            'val'=> $summary['partial']         ?? 0, 'icon'=>'bi-exclamation',   'color'=>'warning'],
        ['label'=>'Failed',             'val'=> $summary['failed']          ?? 0, 'icon'=>'bi-x-circle',      'color'=>'danger'],
        ['label'=>'Records Synced',     'val'=> $summary['total_synced']    ?? 0, 'icon'=>'bi-database-check','color'=>'info'],
        ['label'=>'Record Conflicts',   'val'=> $summary['total_conflicts'] ?? 0, 'icon'=>'bi-exclamation-triangle','color'=>'warning'],
    ];
    foreach ($sCards as $sc): ?>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card border-0 shadow-sm text-center py-3">
            <i class="bi <?= $sc['icon'] ?> text-<?= $sc['color'] ?> fs-4 mb-1"></i>
            <div class="fw-bold fs-5"><?= $sc['val'] ?></div>
            <div class="text-muted small"><?= $sc['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Offline Queues (from IndexedDB — populated by JS) -->
<div class="card border-0 shadow-sm mb-4" id="idb-queue-card" style="display:none;">
    <div class="card-header bg-warning bg-opacity-10 border-0 px-4 py-3">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-warning">
                <i class="bi bi-clock-history me-2"></i>
                Offline Synchronization Queues
                <span class="badge bg-warning text-dark ms-2" id="idb-pending-count">0</span>
            </h6>
            <div class="d-flex gap-2">
                <button class="btn btn-warning btn-sm text-dark" id="sync-now-btn">
                    <i class="bi bi-arrow-repeat me-1"></i>Sync All Pending
                </button>
                <button class="btn btn-danger btn-sm text-white" id="retry-failed-btn" style="display:none;">
                    <i class="bi bi-arrow-clockwise me-1"></i>Retry Failed
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="clear-synced-btn">
                    <i class="bi bi-trash me-1"></i>Clear Completed
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <!-- Tabs for Queues -->
        <ul class="nav nav-tabs px-4 pt-2 border-bottom-0" id="queueTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-pane" type="button" role="tab">Pending (<span id="tab-count-pending">0</span>)</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="failed-tab" data-bs-toggle="tab" data-bs-target="#failed-pane" type="button" role="tab">Failed (<span id="tab-count-failed">0</span>)</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed-pane" type="button" role="tab">Completed (<span id="tab-count-completed">0</span>)</button>
            </li>
        </ul>
        <div class="tab-content" id="queueTabContent">
            <!-- Pending Pane -->
            <div class="tab-pane fade show active" id="pending-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th class="px-4">Comp ID</th><th>App ID</th><th>Action</th><th>Captured At</th><th class="text-center">Status</th></tr></thead>
                        <tbody id="queue-body-pending"><tr><td colspan="5" class="text-center py-3 text-muted">No pending records.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <!-- Failed Pane -->
            <div class="tab-pane fade" id="failed-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th class="px-4">Comp ID</th><th>App ID</th><th>Action</th><th>Error</th><th class="text-center">Status</th></tr></thead>
                        <tbody id="queue-body-failed"><tr><td colspan="5" class="text-center py-3 text-muted">No failed records.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <!-- Completed Pane -->
            <div class="tab-pane fade" id="completed-pane" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th class="px-4">Comp ID</th><th>App ID</th><th>Action</th><th>Captured At</th><th class="text-center">Status</th></tr></thead>
                        <tbody id="queue-body-completed"><tr><td colspan="5" class="text-center py-3 text-muted">No completed records.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Server Sync History -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white px-4 py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0">
            <i class="bi bi-server me-2 text-primary"></i>Server Sync History
        </h6>
        <small class="text-muted">Last sync: <?= $lastSync ?></small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($recent)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
            No sync history found. Offline records will appear here after they are synced.
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="px-4">Competition</th>
                        <th class="text-center">Submitted</th>
                        <th class="text-center">Succeeded</th>
                        <th class="text-center">Failed</th>
                        <th class="text-center">Conflicts</th>
                        <th class="text-center">Status</th>
                        <th>Device</th>
                        <th>Synced At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $row):
                        $statusBadge = match($row['sync_status']) {
                            'Completed'  => 'success',
                            'Partial'    => 'warning text-dark',
                            'Failed'     => 'danger',
                            'Processing' => 'info',
                            default      => 'secondary',
                        };
                    ?>
                    <tr>
                        <td class="px-4">
                            <div class="fw-semibold small">
                                <?= htmlspecialchars($row['competition_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="text-muted" style="font-size:0.75rem;">
                                <?= htmlspecialchars($row['competition_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </td>
                        <td class="text-center small"><?= (int)$row['records_submitted'] ?></td>
                        <td class="text-center small text-success fw-semibold"><?= (int)$row['records_succeeded'] ?></td>
                        <td class="text-center small text-danger fw-semibold"><?= (int)$row['records_failed'] ?></td>
                        <td class="text-center small text-warning fw-semibold"><?= (int)($row['records_conflict'] ?? 0) ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $statusBadge ?>">
                                <?= htmlspecialchars($row['sync_status'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td class="small text-muted">
                            <?= htmlspecialchars(
                                $row['client_device_id']
                                    ? substr($row['client_device_id'], 0, 12) . '…'
                                    : '—',
                                ENT_QUOTES, 'UTF-8'
                            ) ?>
                        </td>
                        <td class="small text-muted">
                            <?= $row['completed_at']
                                ? \App\Helpers\DateHelper::dateTime($row['completed_at'])
                                : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- JS: Load IndexedDB queue into the Sync Center -->
<script>
    window.NEXUS_ATTENDANCE = {
        csrfToken: <?= json_encode($csrfToken ?? '') ?>,
        baseUrl: <?= json_encode(base_url()) ?>
    };
</script>
<script>
(async () => {
    const db    = new NexusAttendanceDB();
    await db.open();
    const records = await db.getPendingRecords();

    if (records.length > 0) {
        document.getElementById('idb-queue-card').style.display = '';

        const pending = records.filter(r => r.sync_status === 'pending');
        const failed = records.filter(r => r.sync_status === 'failed' || r.sync_status === 'conflict');
        const completed = records.filter(r => r.sync_status === 'synced');

        document.getElementById('idb-pending-count').textContent = pending.length;
        document.getElementById('tab-count-pending').textContent = pending.length;
        document.getElementById('tab-count-failed').textContent = failed.length;
        document.getElementById('tab-count-completed').textContent = completed.length;

        if (failed.length > 0) {
            document.getElementById('retry-failed-btn').style.display = 'inline-block';
        }

        const renderRow = (rec, isFailed) => {
            const tr = document.createElement('tr');
            let actionBadge = rec.attendance_status;
            if (actionBadge === 'CLOSE_SESSION') {
                actionBadge = `<span class="badge bg-secondary">Close Session</span>`;
            } else if (actionBadge === 'OPEN_SESSION') {
                actionBadge = `<span class="badge bg-primary">Open Session</span>`;
            } else {
                actionBadge = `<span class="badge bg-${rec.attendance_status === 'Present' ? 'success' : (rec.attendance_status === 'Late' ? 'warning text-dark' : 'danger')}">${rec.attendance_status}</span>`;
            }
            
            const appIdStr = rec.application_id === 0 ? '— (Session)' : rec.application_id;

            tr.innerHTML = `
                <td class="px-4">${rec.competition_id}</td>
                <td>${appIdStr}</td>
                <td>${actionBadge}</td>
                <td class="small text-muted">${isFailed ? (rec.last_error || 'Unknown Error') : new Date(rec.captured_at).toLocaleString()}</td>
                <td class="text-center"><span class="badge bg-${rec.sync_status === 'pending' ? 'warning text-dark' : (rec.sync_status === 'synced' ? 'success' : 'danger')}">${rec.sync_status}</span></td>
            `;
            return tr;
        };

        const populateQueue = (queueArr, tbodyId, isFailed) => {
            const tbody = document.getElementById(tbodyId);
            if (queueArr.length > 0) {
                tbody.innerHTML = '';
                queueArr.forEach(rec => tbody.appendChild(renderRow(rec, isFailed)));
            }
        };

        populateQueue(pending, 'queue-body-pending', false);
        populateQueue(failed, 'queue-body-failed', true);
        populateQueue(completed, 'queue-body-completed', false);
    }

    const engine = new NexusSyncEngine(db);

    document.getElementById('sync-now-btn')?.addEventListener('click', async () => {
        await engine.syncAll();
        location.reload();
    });
    
    document.getElementById('retry-failed-btn')?.addEventListener('click', async () => {
        // Reset failed to pending
        const failed = records.filter(r => r.sync_status === 'failed' || r.sync_status === 'conflict');
        for (const rec of failed) {
            rec.sync_status = 'pending';
            rec.retry_count = 0;
            const tx = db._tx('pending_records', 'readwrite');
            await db._promisify(tx.objectStore('pending_records').put(rec));
        }
        await engine.syncAll();
        location.reload();
    });

    document.getElementById('sync-all-btn')?.addEventListener('click', async () => {
        await engine.syncAll();
        location.reload();
    });

    document.getElementById('clear-synced-btn')?.addEventListener('click', async () => {
        await db.clearSynced();
        location.reload();
    });
})();
</script>
