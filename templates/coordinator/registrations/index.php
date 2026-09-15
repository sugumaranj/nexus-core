<?php
declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Registration Management</h2>
    </div>

    <?php if (empty($symposiums)): ?>
        <div class="alert alert-info">No symposiums available.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($symposiums as $symposium): ?>
                <?php $stat = $stats[$symposium['symposium_id']] ?? ['total' => 0, 'approved' => 0, 'pending' => 0, 'event_count' => 0]; ?>
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <h4 class="card-title mb-1"><?= htmlspecialchars($symposium['title'], ENT_QUOTES, 'UTF-8') ?></h4>
                                    <div class="mb-2">
                                        <span class="badge bg-secondary"><?= htmlspecialchars($symposium['symposium_type'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-calendar-range"></i> Reg Window: <br>
                                        <?= \App\Helpers\DateHelper::date($symposium['registration_start']) ?> - <?= \App\Helpers\DateHelper::date($symposium['registration_end']) ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="row text-center g-2">
                                        <div class="col-6">
                                            <div class="border rounded p-2 bg-light">
                                                <div class="fs-4 fw-bold text-primary"><?= (int)$stat['total'] ?></div>
                                                <div class="small text-muted">Total Regs</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="border rounded p-2 bg-light">
                                                <div class="fs-4 fw-bold text-success"><?= (int)$stat['approved'] ?></div>
                                                <div class="small text-muted">Approved</div>
                                            </div>
                                        </div>
                                        <div class="col-12 mt-2">
                                            <div class="border rounded p-2 bg-light">
                                                <div class="fs-5 fw-bold text-info"><?= (int)$stat['event_count'] ?></div>
                                                <div class="small text-muted">Events</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="d-grid gap-2">
                                        <a href="<?= base_url('/coordinator/registrations/summary?symposium_id=' . $symposium['symposium_id']) ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-pie-chart"></i> View Summary
                                        </a>
                                        <div class="btn-group w-100">
                                            <a href="<?= base_url('/coordinator/registrations/report/department?symposium_id=' . $symposium['symposium_id']) ?>" class="btn btn-outline-secondary">
                                                <i class="bi bi-building"></i> Dept Report
                                            </a>
                                            <a href="<?= base_url('/coordinator/registrations/report/teams?symposium_id=' . $symposium['symposium_id']) ?>" class="btn btn-outline-secondary">
                                                <i class="bi bi-people"></i> Teams
                                            </a>
                                        </div>
                                        <a href="<?= base_url('/coordinator/registrations/not-registered?symposium_id=' . $symposium['symposium_id']) ?>" class="btn btn-outline-warning">
                                            <i class="bi bi-person-x"></i> Not Registered
                                        </a>
                                        <a href="<?= base_url('/coordinator/registrations/summary?symposium_id=' . $symposium['symposium_id']) ?>" class="btn btn-outline-info">
                                            <i class="bi bi-list-task"></i> View Events to Manage Participants
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
