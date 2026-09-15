<?php

declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($page_title ?? 'Generation Report', ENT_QUOTES, 'UTF-8') ?></h1>
        <div>
            <a href="<?= base_url('/certificates/generate') ?>" class="btn btn-sm btn-secondary shadow-sm me-2"><i class="bi bi-arrow-left"></i> Back</a>
            <a href="<?= base_url('/certificates/generated') ?>" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-list-check"></i> View Generated Certificates</a>
        </div>
    </div>

    <?php if (empty($report)): ?>
        <div class="alert alert-info shadow-sm">
            <i class="bi bi-info-circle-fill me-2"></i> No generation report available. Please run a generation task first.
        </div>
    <?php else: ?>
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Processed</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars((string)($report['total_processed'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="col-auto"><i class="bi bi-file-earmark-bar-graph fa-2x text-gray-300 display-6"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Generated Successfully</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars((string)($report['total_generated'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="col-auto"><i class="bi bi-check-circle fa-2x text-gray-300 display-6"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Skipped</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars((string)($report['total_skipped'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="col-auto"><i class="bi bi-skip-forward fa-2x text-gray-300 display-6"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Failed</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars((string)($report['total_failed'] ?? 0), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="col-auto"><i class="bi bi-x-circle fa-2x text-gray-300 display-6"></i></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Detailed Log</h6>
            </div>
            <div class="card-body">
                <?php if (empty($report['details'])): ?>
                    <p class="text-muted">No detail logs available.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>App / Result ID</th>
                                    <th>Recipient</th>
                                    <th>Status</th>
                                    <th>Reason / Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report['details'] as $detail): ?>
                                    <tr>
                                        <td><span class="font-monospace text-muted"><?= htmlspecialchars((string)($detail['identifier'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td class="fw-bold"><?= htmlspecialchars($detail['recipient'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if ($detail['status'] === 'Generated'): ?>
                                                <span class="badge bg-success">Generated</span>
                                            <?php elseif ($detail['status'] === 'Regenerated'): ?>
                                                <span class="badge bg-info text-dark">Regenerated</span>
                                            <?php elseif ($detail['status'] === 'Skipped'): ?>
                                                <span class="badge bg-warning text-dark">Skipped</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small"><?= htmlspecialchars($detail['reason'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
