<?php

declare(strict_types=1);

use App\Helpers\RoleHelper;

$symposiums = $symposiums ?? [];
$search = $search ?? '';
$departmentId = $department_id ?? '';
$academicYear = $academic_year ?? '';
$symposiumType = $symposium_type ?? '';
$status = $status ?? '';
$quickFilter = $quick_filter ?? '';
$departments = $departments ?? [];
$academicYears = $academicYears ?? [];
$symposiumTypes = $symposiumTypes ?? [];
$symposiumStatuses = $symposiumStatuses ?? [];
$quickFilters = $quickFilters ?? [];
$totalSymposiums = $totalSymposiums ?? 0;
$draftCount = $draftCount ?? 0;
$registrationOpenCount = $registrationOpenCount ?? 0;
$registrationClosedCount = $registrationClosedCount ?? 0;
$completedCount = $completedCount ?? 0;
$cancelledCount = $cancelledCount ?? 0;
$canCreateSymposium = $canCreateSymposium ?? false;

// Determine if the current user is Staff (view-only)
$_sessionUser     = App\Core\Session::get('user', []);
$isStaffViewOnly  = RoleHelper::isStaff($_sessionUser);
unset($_sessionUser);

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="fw-bold mb-1">

            Symposium Management

        </h2>

        <p class="text-muted mb-0">

            <?php if ($isStaffViewOnly): ?>
                View symposiums, schedules, registrations and publication status.
            <?php else: ?>
                Manage symposiums, schedules, registrations and publication status.
            <?php endif; ?>

        </p>

    </div>

    <?php if ($canCreateSymposium): ?>

        <a
            href="<?= base_url() ?>/symposiums/create"
            class="btn btn-primary">

            <i class="bi bi-plus-circle me-1"></i>

            Add Symposium

        </a>

    <?php endif; ?>

</div>

<?php if ($isStaffViewOnly): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-3" role="alert">
    <i class="bi bi-eye fs-5"></i>
    <div>
        <strong>View-Only Access</strong> &mdash; As a Staff member, you can view all symposiums but cannot create, edit, or take any management actions.
    </div>
</div>
<?php endif; ?>



<!-- ── Stat Cards ──────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Total Symposiums -->
    <div class="col-12 col-sm-6 col-lg">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #6366f1 !important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="rounded-circle d-flex align-items-center justify-content-center"
                          style="width:32px;height:32px;background:rgba(99,102,241,.12);">
                        <i class="bi bi-collection" style="color:#6366f1;font-size:.9rem;"></i>
                    </span>
                    <span class="text-muted small fw-medium">Total</span>
                </div>
                <div class="fw-bold" style="font-size:1.75rem;line-height:1;color:#6366f1;">
                    <?= (int) $totalSymposiums ?>
                </div>
                <div class="text-muted" style="font-size:.72rem;">Symposiums</div>
            </div>
        </div>
    </div>

    <!-- Pending Approvals -->
    <div class="col-12 col-sm-6 col-lg">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #f59e0b !important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="rounded-circle d-flex align-items-center justify-content-center"
                          style="width:32px;height:32px;background:rgba(245,158,11,.12);">
                        <i class="bi bi-hourglass-split" style="color:#f59e0b;font-size:.9rem;"></i>
                    </span>
                    <span class="text-muted small fw-medium">Pending</span>
                </div>
                <div class="fw-bold" style="font-size:1.75rem;line-height:1;color:#f59e0b;">
                    <?= (int) $pendingCount ?>
                </div>
                <div class="text-muted" style="font-size:.72rem;">Approvals</div>
            </div>
        </div>
    </div>

    <!-- Approved Today -->
    <div class="col-12 col-sm-6 col-lg">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #10b981 !important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="rounded-circle d-flex align-items-center justify-content-center"
                          style="width:32px;height:32px;background:rgba(16,185,129,.12);">
                        <i class="bi bi-check-circle-fill" style="color:#10b981;font-size:.9rem;"></i>
                    </span>
                    <span class="text-muted small fw-medium">Approved</span>
                </div>
                <div class="fw-bold" style="font-size:1.75rem;line-height:1;color:#10b981;">
                    <?= (int) $approvedTodayCount ?>
                </div>
                <div class="text-muted" style="font-size:.72rem;">Today</div>
            </div>
        </div>
    </div>

    <!-- Rejected -->
    <div class="col-12 col-sm-6 col-lg">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #ef4444 !important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="rounded-circle d-flex align-items-center justify-content-center"
                          style="width:32px;height:32px;background:rgba(239,68,68,.12);">
                        <i class="bi bi-x-circle-fill" style="color:#ef4444;font-size:.9rem;"></i>
                    </span>
                    <span class="text-muted small fw-medium">Rejected</span>
                </div>
                <div class="fw-bold" style="font-size:1.75rem;line-height:1;color:#ef4444;">
                    <?= (int) $rejectedCount ?>
                </div>
                <div class="text-muted" style="font-size:.72rem;">Symposiums</div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-12 col-sm-8 col-lg-4">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #06b6d4 !important;">
            <div class="card-body py-3 px-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="rounded-circle d-flex align-items-center justify-content-center"
                          style="width:32px;height:32px;background:rgba(6,182,212,.12);">
                        <i class="bi bi-activity" style="color:#06b6d4;font-size:.9rem;"></i>
                    </span>
                    <span class="fw-semibold small" style="color:#06b6d4;">Recent Activity</span>
                </div>
                <div style="overflow-y:auto;max-height:90px;">
                    <?php if (empty($recentActivity)): ?>
                        <p class="text-muted small mb-0">No recent activity.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($recentActivity as $activity): ?>
                                <li class="small d-flex align-items-start gap-1 mb-1">
                                    <i class="bi bi-dot text-muted mt-0" style="font-size:1rem;flex-shrink:0;"></i>
                                    <span>
                                        <strong><?= htmlspecialchars($activity['user_name'] ?? 'System', ENT_QUOTES) ?>:</strong>
                                        <?= htmlspecialchars($activity['action'], ENT_QUOTES) ?>
                                        <span class="text-muted">(<?= \App\Helpers\DateHelper::time($activity['action_time']) ?>)</span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>
<!-- ── / Stat Cards ────────────────────────────────────────────────── -->



<div class="card shadow-sm border-0 mb-4">

    <div class="card-body">

        <form
            action="<?= base_url() ?>/symposiums"
            method="get"
            class="row g-3">

            <div class="col-md-4">

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search symposium..."
                    value="<?= htmlspecialchars((string) $search, ENT_QUOTES, 'UTF-8') ?>">

            </div>

            <div class="col-md-4">

                <select name="department_id" class="form-select">

                    <option value="">All Organizing Depts</option>

                    <?php foreach ($departments as $department): ?>

                        <option
                            value="<?= (int) $department['department_id'] ?>"
                            <?= ((string) $departmentId === (string) $department['department_id']) ? 'selected' : '' ?>>

                            <?= htmlspecialchars((string) $department['department_name'], ENT_QUOTES, 'UTF-8') ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-2">

                <select name="academic_year" class="form-select">

                    <option value="">All Years</option>

                    <?php foreach ($academicYears as $year): ?>

                        <option
                            value="<?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?>"
                            <?= ($academicYear === (string) $year) ? 'selected' : '' ?>>

                            <?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-2">

                <select name="symposium_type" class="form-select">

                    <option value="">All Types</option>

                    <?php foreach ($symposiumTypes as $type): ?>

                        <option
                            value="<?= htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') ?>"
                            <?= ($symposiumType === $type) ? 'selected' : '' ?>>

                            <?= htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-4">

                <select name="status" class="form-select">

                    <option value="">All Status</option>

                    <?php foreach ($symposiumStatuses as $statusOption): ?>

                        <option
                            value="<?= htmlspecialchars((string) $statusOption, ENT_QUOTES, 'UTF-8') ?>"
                            <?= ($status === $statusOption) ? 'selected' : '' ?>>

                            <?= htmlspecialchars((string) $statusOption, ENT_QUOTES, 'UTF-8') ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-4">

                <select name="quick_filter" class="form-select">

                    <option value="">All Filters</option>

                    <?php foreach ($quickFilters as $filter): ?>

                        <option
                            value="<?= htmlspecialchars((string) $filter, ENT_QUOTES, 'UTF-8') ?>"
                            <?= ($quickFilter === $filter) ? 'selected' : '' ?>>

                            <?= htmlspecialchars((string) $filter, ENT_QUOTES, 'UTF-8') ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-2">

                <button type="submit" class="btn btn-outline-primary w-100">Search</button>

            </div>

            <div class="col-md-2">

                <a href="<?= base_url() ?>/symposiums" class="btn btn-outline-secondary w-100">Reset</a>

            </div>

        </form>

    </div>

</div>

<div class="card shadow-sm border-0">

    <div class="card-body table-responsive">

        <table class="table table-hover align-middle">

            <thead class="table-light">

                <tr>

                    <th>Symposium Code</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Organizing Dept.</th>
                    <th>Academic Year</th>
                    <th>Registration Period</th>
                    <th>Event Dates</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th width="230">Actions</th>

                </tr>

            </thead>

            <tbody>

                <?php if (empty($symposiums)): ?>

                    <tr>

                        <td colspan="10" class="text-center text-muted py-5">

                            No symposiums found.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($symposiums as $symposium): ?>

                        <tr>

                            <td><?= htmlspecialchars((string) ($symposium['symposium_code'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>

                            <td><?= htmlspecialchars((string) ($symposium['title'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>

                            <td><?= htmlspecialchars((string) ($symposium['symposium_type'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>

                            <td>
                                <?php
                                $deptString = (string) ($symposium['organizing_departments'] ?? '');
                                if (empty($deptString)):
                                    echo '-';
                                else:
                                    $depts = explode(' & ', $deptString);
                                    foreach ($depts as $d):
                                        if (trim($d) === '') continue;
                                ?>
                                    <div class="mb-1">
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 fw-normal text-wrap text-start lh-sm" style="font-size: 0.75rem;">
                                            <?= htmlspecialchars(trim($d), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>
                                <?php
                                    endforeach;
                                endif;
                                ?>
                            </td>

                            <td><?= htmlspecialchars((string) ($symposium['academic_year'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>

                            <td>

                                <?= htmlspecialchars((string) symposium_format_datetime($symposium['registration_start']), ENT_QUOTES, 'UTF-8') ?><br>
                                <small class="text-muted">to</small><br>
                                <?= htmlspecialchars((string) symposium_format_datetime($symposium['registration_end']), ENT_QUOTES, 'UTF-8') ?>

                            </td>

                            <td>

                                <?= htmlspecialchars((string) symposium_format_date($symposium['event_start_date']), ENT_QUOTES, 'UTF-8') ?><br>
                                <small class="text-muted">to</small><br>
                                <?= htmlspecialchars((string) symposium_format_date($symposium['event_end_date']), ENT_QUOTES, 'UTF-8') ?>

                            </td>

                            <td>

                                <span class="badge bg-<?= symposium_status_badge_class((string) ($symposium['status'] ?? '')) ?>">

                                    <?= htmlspecialchars((string) ($symposium['status'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>

                                </span>

                            </td>

                            <td><?= htmlspecialchars((string) ($symposium['created_by_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>

                            <td>

                                <div class="d-flex flex-wrap gap-2">

                                    <a href="<?= base_url() ?>/symposiums/view?id=<?= (int) ($symposium['symposium_id'] ?? 0) ?>" class="btn btn-outline-primary btn-sm">

                                        <i class="bi bi-eye"></i>

                                    </a>

                                    <?php if (!empty($symposium['can_edit'])): ?>

                                        <a href="<?= base_url() ?>/symposiums/edit?id=<?= (int) ($symposium['symposium_id'] ?? 0) ?>" class="btn btn-outline-secondary btn-sm">

                                            <i class="bi bi-pencil"></i>

                                        </a>

                                    <?php endif; ?>

                                    <?php if (!empty($symposium['can_delete'])): ?>

                                        <form action="<?= base_url() ?>/symposiums/delete" method="post" class="d-inline">

                                            <input type="hidden" name="symposium_id" value="<?= (int) ($symposium['symposium_id'] ?? 0) ?>">

                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this symposium?');">

                                                <i class="bi bi-trash"></i>

                                            </button>

                                        </form>

                                    <?php endif; ?>

                                    <?php if (!empty($symposium['can_change_status'])): ?>

                                        <a href="<?= base_url() ?>/symposiums/view?id=<?= (int) ($symposium['symposium_id'] ?? 0) ?>#status" class="btn btn-outline-info btn-sm">

                                            <i class="bi bi-gear"></i>

                                        </a>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>
