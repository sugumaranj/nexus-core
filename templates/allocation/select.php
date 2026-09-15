<?php
declare(strict_types=1);
/**
 * -------------------------------------------------------------------------
 * NexusCore EMS — Select Symposium for Staff Allocation
 * Location    : templates/allocation/select.php
 * -------------------------------------------------------------------------
 */
$symposiums = $symposiums ?? [];
?>

<div class="d-flex align-items-center mb-4">
    <div>
        <h1 class="h4 fw-bold mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>Staff Allocation</h1>
        <p class="text-secondary mb-0" style="font-size:.87rem;">Please select a symposium to manage staff allocations.</p>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Symposium Code</th>
                    <th>Title</th>
                    <th>Academic Year</th>
                    <th>Status</th>
                    <th width="150" class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($symposiums)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">No symposiums available.</td></tr>
                <?php else: ?>
                    <?php foreach ($symposiums as $sym): ?>
                        <tr>
                            <td><?= htmlspecialchars($sym['symposium_code'] ?? '-') ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($sym['title'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($sym['academic_year'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-<?= symposium_status_badge_class($sym['status'] ?? '') ?>">
                                    <?= htmlspecialchars($sym['status'] ?? '-') ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="<?= base_url() ?>/symposiums/allocation?symposium_id=<?= (int) $sym['symposium_id'] ?>" class="btn btn-primary btn-sm px-3">
                                    Manage Staff
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
