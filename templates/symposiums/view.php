<?php

declare(strict_types=1);

use App\Services\SymposiumService;

$symposium        = $symposium ?? [];
$approvals        = $approvals ?? [];
$auditLogs        = $auditLogs ?? [];
$canSubmit        = $canSubmit ?? false;
$canApprove       = $canApprove ?? false;
$canEdit          = $canEdit ?? false;
$canDelete        = $canDelete ?? false;
$isStaffViewOnly  = $isStaffViewOnly ?? false;
$symposiumStatuses = $symposiumStatuses ?? [];
$success          = $success ?? '';
$error            = $error ?? '';

$user     = App\Core\Session::get('user');
$userRole = $user['role'] ?? '';

$status  = $symposium['status'] ?? '';
$symId   = (int) ($symposium['symposium_id'] ?? 0);

// Status badge helper
function statusBadge(string $status): string
{
    $class = symposium_status_badge_class($status);
    return "<span class=\"badge bg-{$class}\">" . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . "</span>";
}

// Approval stage icons
function approvalIcon(string $status): string
{
    return match ($status) {
        'Approved'           => '<i class="bi bi-check-circle-fill text-success"></i>',
        'Revision Requested' => '<i class="bi bi-x-circle-fill text-danger"></i>',
        'Pending'            => '<i class="bi bi-clock-fill text-warning"></i>',
        default              => '<i class="bi bi-circle text-muted"></i>',
    };
}

$isApprovedOrBeyond = in_array($status, [
    SymposiumService::STATUS_APPROVED,
    SymposiumService::STATUS_SCHEDULING_COMPLETE,
    SymposiumService::STATUS_REGISTRATION_OPEN,
    SymposiumService::STATUS_REGISTRATION_CLOSE,
    SymposiumService::STATUS_COMPLETED,
], true);

$isSchedulingCompleteOrBeyond = in_array($status, [
    SymposiumService::STATUS_SCHEDULING_COMPLETE,
    SymposiumService::STATUS_REGISTRATION_OPEN,
    SymposiumService::STATUS_REGISTRATION_CLOSE,
    SymposiumService::STATUS_COMPLETED,
], true);
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <div class="d-flex align-items-center gap-3 mb-1">
            <h2 class="fw-bold mb-0"><?= htmlspecialchars((string) ($symposium['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
            <?= statusBadge($status) ?>
        </div>
        <p class="text-muted mb-0">
            <code><?= htmlspecialchars((string) ($symposium['symposium_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
            &nbsp;|&nbsp;
            Academic Year: <strong><?= htmlspecialchars((string) ($symposium['academic_year'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
            &nbsp;|&nbsp;
            Created by: <strong><?= htmlspecialchars((string) ($symposium['created_by_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($canEdit): ?>
            <a href="<?= base_url() ?>/symposiums/edit?id=<?= $symId ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
        <?php endif; ?>
        <a href="<?= base_url() ?>/symposiums" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left-circle me-1"></i>Back
        </a>
    </div>
</div>

<?php if ($isStaffViewOnly): ?>
    <div class="alert alert-info d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-eye fs-5"></i>
        <div>
            <strong>View-Only Access</strong> &mdash; As a Staff member, you can view this symposium but cannot make any modifications, submit, approve, or take any workflow actions.
        </div>
    </div>
<?php endif; ?>

<!-- Flash messages -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-1"></i> <?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-circle me-1"></i> <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">

    <!-- LEFT COLUMN: Details -->
    <div class="col-lg-8">

        <!-- Workflow Actions -->
        <?php if ($canSubmit || $canApprove): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom d-flex align-items-center gap-2">
                    <i class="bi bi-gear-fill text-primary"></i>
                    <h5 class="mb-0">Workflow Action</h5>
                    <span class="ms-auto"><?= statusBadge($status) ?></span>
                </div>
                <div class="card-body">

                    <?php if ($canSubmit): ?>
                        <?php if (str_starts_with($status, 'Rejected') || str_starts_with($status, 'Revision')): ?>
                            <div class="alert alert-warning mb-3">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                This symposium was returned for revision. Make the required changes, then resubmit.
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($symposiumEvents)): ?>
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                You must add at least one scheduled master event before submitting for approval.
                            </div>
                            <button type="button" class="btn btn-primary" disabled>
                                <i class="bi bi-send-fill me-1"></i> Submit for Approval
                            </button>
                        <?php else: ?>
                            <form action="<?= base_url() ?>/symposiums/submit" method="post">
                                <input type="hidden" name="symposium_id" value="<?= $symId ?>">
                                <button type="submit" class="btn btn-primary"
                                    onclick="return confirm('Submit this symposium for approval? Ensure all scheduled events have venues and coordinators assigned.')">
                                    <i class="bi bi-send-fill me-1"></i> Submit for Approval
                                </button>
                                <small class="text-muted ms-2">HODs will be notified automatically.</small>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($canApprove): ?>
                        <div class="row g-3">
                            <!-- Approve -->
                            <div class="col-md-6">
                                <div class="card border-success border-1">
                                    <div class="card-body">
                                        <h6 class="text-success"><i class="bi bi-check-circle me-1"></i>Approve</h6>
                                        <form action="<?= base_url() ?>/symposiums/approve" method="post">
                                            <input type="hidden" name="symposium_id" value="<?= $symId ?>">
                                            <textarea name="remarks" class="form-control form-control-sm mb-2" rows="2"
                                                placeholder="Remarks (optional)"></textarea>
                                            <button type="submit" class="btn btn-success btn-sm w-100"
                                                onclick="return confirm('Approve this symposium?')">
                                                <i class="bi bi-check-lg me-1"></i> Approve
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <!-- Request Revision -->
                            <div class="col-md-6">
                                <div class="card border-danger border-1">
                                    <div class="card-body">
                                        <h6 class="text-danger"><i class="bi bi-arrow-counterclockwise me-1"></i>Request Revision</h6>
                                        <form action="<?= base_url() ?>/symposiums/reject" method="post">
                                            <input type="hidden" name="symposium_id" value="<?= $symId ?>">
                                            <textarea name="remarks" class="form-control form-control-sm mb-2" rows="2"
                                                placeholder="State reason for revision (required)" required></textarea>
                                            <button type="submit" class="btn btn-danger btn-sm w-100"
                                                onclick="return confirm('Return this symposium for revision?')">
                                                <i class="bi bi-x-lg me-1"></i> Request Revision
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>

        <!-- Basic Details Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Symposium Details</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-sm-6">
                        <div class="text-muted small fw-semibold mb-1">Organized By</div>
                        <div class="fw-semibold">
                            <?php
                            $deptString = (string) ($symposium['organizing_departments'] ?? '');
                            if (empty($deptString)):
                                echo '—';
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
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted small fw-semibold mb-1">Type</div>
                        <div><?= htmlspecialchars((string) ($symposium['symposium_type'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>

                    <div class="col-12">
                        <div class="text-muted small fw-semibold mb-1">Description</div>
                        <div><?= nl2br(htmlspecialchars((string) ($symposium['description'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Registration & Event Dates -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="bi bi-calendar-range me-2"></i>Schedule</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 rounded bg-light">
                            <div class="text-muted small fw-semibold mb-1"><i class="bi bi-pencil-square me-1"></i>Registration Period</div>
                            <div><?= htmlspecialchars((string) symposium_format_datetime($symposium['registration_start']), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-muted small">to</div>
                            <div><?= htmlspecialchars((string) symposium_format_datetime($symposium['registration_end']), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded bg-light">
                            <div class="text-muted small fw-semibold mb-1"><i class="bi bi-calendar-event me-1"></i>Event Dates</div>
                            <div><?= htmlspecialchars((string) symposium_format_date($symposium['event_start_date']), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-muted small">to</div>
                            <div><?= htmlspecialchars((string) symposium_format_date($symposium['event_end_date']), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scheduled Master Events Section -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0"><i class="bi bi-calendar-event me-2 text-primary"></i>Scheduled Master Events</h5>
                <div>
                    <?php if ($status === SymposiumService::STATUS_APPROVED): ?>
                        <a href="<?= base_url() ?>/symposiums/scheduling?symposium_id=<?= $symId ?>" class="btn btn-sm <?= \App\Helpers\RoleHelper::hasRole($loggedInUser, 'Staff Coordinator', 'Admin') ? 'btn-primary' : 'btn-outline-primary' ?> me-1">
                            <i class="bi <?= \App\Helpers\RoleHelper::hasRole($loggedInUser, 'Staff Coordinator', 'Admin') ? 'bi-calendar-check' : 'bi-eye' ?> me-1"></i> 
                            <?= \App\Helpers\RoleHelper::hasRole($loggedInUser, 'Staff Coordinator', 'Admin') ? 'Schedule Events' : 'View Schedule' ?>
                        </a>
                        <?php if (\App\Helpers\RoleHelper::hasRole($loggedInUser, 'Staff Coordinator', 'Admin')): ?>
                        <a href="<?= base_url() ?>/symposiums/allocation?symposium_id=<?= $symId ?>" class="btn btn-sm text-white me-1" style="background-color: #6f42c1; border-color: #6f42c1;">
                            <i class="bi bi-people-fill me-1"></i> Allocate Staff
                        </a>
                        <?php endif; ?>
                    <?php elseif ($status === SymposiumService::STATUS_SCHEDULING_COMPLETE): ?>
                        <span class="badge bg-success py-2 px-3 me-1"><i class="bi bi-check-all me-1"></i>Scheduling Complete</span>
                        <a href="<?= base_url() ?>/symposiums/scheduling?symposium_id=<?= $symId ?>" class="btn btn-sm btn-outline-success me-1">
                            <i class="bi bi-eye me-1"></i> View Schedule
                        </a>
                        <?php if (\App\Helpers\RoleHelper::hasRole($loggedInUser, 'Staff Coordinator', 'Admin')): ?>
                        <a href="<?= base_url() ?>/symposiums/allocation?symposium_id=<?= $symId ?>" class="btn btn-sm text-white me-1" style="background-color: #6f42c1; border-color: #6f42c1;">
                            <i class="bi bi-people-fill me-1"></i> Allocate Staff
                        </a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (\App\Helpers\RoleHelper::hasRole($loggedInUser, 'Staff Coordinator', 'Admin')): ?>
                        <a href="<?= base_url() ?>/symposiums/events/create?symposium_id=<?= $symId ?>" class="btn btn-sm btn-success me-1">
                            <i class="bi bi-plus-circle me-1"></i> Add Event to Symposium
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url() ?>/symposiums/events?symposium_id=<?= $symId ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-list-task me-1"></i> View All Events
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php $symposiumEvents = $symposiumEvents ?? []; ?>
                <?php if (empty($symposiumEvents)): ?>
                    <div class="text-center py-3">
                        <p class="text-muted mb-2">No master events scheduled yet for this symposium.</p>
                        <?php if (\App\Helpers\RoleHelper::hasRole($loggedInUser, 'Staff Coordinator', 'Admin')): ?>
                            <a href="<?= base_url() ?>/symposiums/events/create?symposium_id=<?= $symId ?>" class="btn btn-sm btn-success">
                                <i class="bi bi-plus-circle me-1"></i> Add Event to Symposium
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Event Code</th>
                                    <th>Event Name</th>
                                    <th>Category</th>
                                    <th>Date & Time</th>
                                    <th>Venue</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $eventCount = count($symposiumEvents); ?>
                                <?php foreach ($symposiumEvents as $index => $se): ?>
                                    <tr class="event-row <?= $index >= 3 ? 'd-none' : '' ?>">
                                        <td><code><?= htmlspecialchars($se['event_code'] ?? '') ?></code></td>
                                        <td><strong><?= htmlspecialchars($se['event_name'] ?? '') ?></strong></td>
                                        <td>
                                            <span class="badge <?= ($se['category'] ?? '') === 'Technical' ? 'bg-primary' : 'bg-info text-dark' ?>">
                                                <?= htmlspecialchars($se['category'] ?? '') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= !empty($se['event_date']) ? \App\Helpers\DateHelper::date($se['event_date']) : 'TBA' ?>
                                            <?php if (!empty($se['start_time'])): ?>
                                                &bull; <?= \App\Helpers\DateHelper::time($se['start_time']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><i class="bi bi-geo-alt text-muted me-1"></i><?= htmlspecialchars($se['venue_name'] ?? 'TBA') ?></td>
                                        <td>
                                            <a href="<?= base_url() ?>/symposiums/events/view?id=<?= $se['symposium_event_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <?php if ($eventCount > 3): ?>
                                <tfoot>
                                    <tr>
                                        <td colspan="6" class="text-center p-0">
                                            <button 
                                                class="btn btn-light w-100 py-2 border-0 fw-semibold text-primary" 
                                                type="button" 
                                                onclick="document.querySelectorAll('.event-row.d-none').forEach(row => row.classList.remove('d-none')); this.closest('tfoot').remove();">
                                                View More (<?= $eventCount - 3 ?> events) <i class="bi bi-chevron-down ms-1"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Documents (only after approval) -->
        <?php if ($isApprovedOrBeyond): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="bi bi-file-earmark-pdf me-2"></i>Documents</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php
                    $docTypes = [
                        'brochure'  => ['label' => 'Event Brochure',   'icon' => 'bi-journal-richtext', 'soon' => false, 'btn_class' => 'btn-outline-primary'],
                        'circular'  => ['label' => 'Official Circular', 'icon' => 'bi-envelope-open',   'soon' => false, 'btn_class' => 'btn-outline-primary'],
                        'schedule'  => ['label' => 'Event Schedule',   'icon' => 'bi-clock-history',    'soon' => !$isSchedulingCompleteOrBeyond, 'btn_class' => 'btn-outline-primary'],
                    ];
                    if ($status === \App\Services\SymposiumService::STATUS_COMPLETED) {
                        $docTypes['certificate_package'] = [
                            'label' => 'Complete Certificates (ZIP)', 
                            'icon' => 'bi-file-earmark-zip', 
                            'soon' => false, 
                            'btn_class' => 'btn-outline-success'
                        ];
                        $docTypes['certificate_package_pdf'] = [
                            'label' => 'Complete Certificates (PDF)', 
                            'icon' => 'bi-file-earmark-pdf', 
                            'soon' => false, 
                            'btn_class' => 'btn-outline-danger'
                        ];
                    }
                    foreach ($docTypes as $type => $doc): 
                        if ($type === 'brochure') {
                            $url = base_url() . "/notice-board?symposium_id=" . $symId;
                        } elseif ($type === 'certificate_package') {
                            $url = base_url() . "/certificates/download/symposium-package?symposium_id=" . $symId;
                        } elseif ($type === 'certificate_package_pdf') {
                            $url = base_url() . "/certificates/download/symposium-pdf?symposium_id=" . $symId;
                        } else {
                            $url = base_url() . "/symposiums/generate-pdf?type=" . urlencode($type) . "&id=" . $symId;
                        }
                    ?>
                    <div class="col-md-4 col-sm-6">
                        <?php if ($doc['soon']): ?>
                            <button class="btn btn-outline-secondary w-100 d-flex align-items-center gap-2" disabled title="Coming soon">
                                <i class="bi <?= $doc['icon'] ?>"></i>
                                <?= $doc['label'] ?>
                                <span class="badge bg-secondary ms-auto" style="font-size:0.7em;">Soon</span>
                            </button>
                        <?php else: ?>
                            <a href="<?= $url ?>"
                               target="<?= in_array($type, ['certificate_package', 'certificate_package_pdf']) ? '' : '_blank' ?>" 
                               class="btn <?= $doc['btn_class'] ?> w-100 d-flex align-items-center gap-2">
                                <i class="bi <?= $doc['icon'] ?>"></i>
                                <?= $doc['label'] ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-secondary d-flex gap-2 align-items-center mb-4">
            <i class="bi bi-lock fs-5"></i>
            <div><strong>Documents locked</strong> — Brochure, Circular and other documents will be available after the Principal approves this symposium.</div>
        </div>
        <?php endif; ?>

    </div>

    <!-- RIGHT COLUMN: Timeline & Logs -->
    <div class="col-lg-4">

        <!-- Approval Timeline -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Approval Timeline</h5>
            </div>
            <div class="card-body p-3">
                <?php if (empty($approvals)): ?>
                    <p class="text-muted small mb-0 text-center py-3">
                        <i class="bi bi-hourglass d-block fs-3 mb-2"></i>
                        Not yet submitted for approval.
                    </p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                        <?php 
                        $groupedApprovals = [];
                        foreach ($approvals as $a) {
                            $lvl = $a['approval_level'];
                            if (!isset($groupedApprovals[$lvl])) {
                                $groupedApprovals[$lvl] = [];
                            }
                            $groupedApprovals[$lvl][] = $a;
                        }
                        
                        $isFirstGroup = true;
                        foreach ($groupedApprovals as $level => $levelApprovals): 
                        ?>
                            <?php if (!$isFirstGroup): ?>
                                <div class="ms-2 ps-2 border-start border-2" style="height:12px; margin-top:-8px; margin-bottom:-8px;"></div>
                            <?php endif; ?>
                            <?php $isFirstGroup = false; ?>

                            <div class="card bg-light border-0">
                                <div class="card-body p-2">
                                    <h6 class="fw-bold mb-2 pb-1 border-bottom border-secondary border-opacity-25 text-uppercase" style="font-size:0.8rem; letter-spacing:0.5px;">
                                        <?= htmlspecialchars((string) $level, ENT_QUOTES, 'UTF-8') ?> Stage
                                    </h6>
                                    <div class="d-flex flex-column gap-2 mt-2">
                                        <?php foreach ($levelApprovals as $approval): ?>
                                            <div class="d-flex gap-2 bg-white p-2 rounded shadow-sm border border-secondary border-opacity-10">
                                                <div class="fs-5 pt-1">
                                                    <?= approvalIcon($approval['status']) ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-semibold small">
                                                        <?php if (!empty($approval['department_name'])): ?>
                                                            <?= htmlspecialchars((string) $approval['department_name'], ENT_QUOTES, 'UTF-8') ?>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars((string) $approval['approval_stage'], ENT_QUOTES, 'UTF-8') ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="d-flex align-items-center gap-1 mt-1">
                                                        <?php if ($approval['status'] === 'Pending'): ?>
                                                            <span class="badge bg-warning text-dark" style="font-size:0.65rem">Awaiting Action</span>
                                                        <?php elseif ($approval['status'] === 'Approved'): ?>
                                                            <span class="badge bg-success" style="font-size:0.65rem">Approved</span>
                                                            <?php if (!empty($approval['approver_name'])): ?>
                                                                <small class="text-muted" style="font-size:0.7rem">by <?= htmlspecialchars((string) $approval['approver_name'], ENT_QUOTES, 'UTF-8') ?></small>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger" style="font-size:0.65rem"><?= htmlspecialchars((string) $approval['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if (!empty($approval['remarks'])): ?>
                                                        <div class="fst-italic text-secondary mt-1" style="font-size:0.75rem; border-left: 2px solid #dee2e6; padding-left: 5px;">"<?= htmlspecialchars((string) $approval['remarks'], ENT_QUOTES, 'UTF-8') ?>"</div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($approval['approved_at']) && $approval['status'] !== 'Pending'): ?>
                                                        <div class="text-muted mt-1" style="font-size:0.65rem"><?= \App\Helpers\DateHelper::dateTime($approval['approved_at']) ?></div>
                                                    <?php elseif (!empty($approval['created_at'])): ?>
                                                        <div class="text-muted mt-1" style="font-size:0.65rem">Created: <?= \App\Helpers\DateHelper::dateTime($approval['created_at']) ?></div>
                                                    <?php else: ?>
                                                        <div class="text-muted mt-1" style="font-size:0.65rem">-</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="bi bi-journal-text me-2"></i>Activity Log</h5>
            </div>
            <div class="card-body p-0" style="max-height:320px; overflow-y:auto;">
                <?php if (empty($auditLogs)): ?>
                    <p class="text-muted small text-center py-3 mb-0">No activity recorded.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach (array_reverse($auditLogs) as $log): ?>
                            <li class="list-group-item py-2 px-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <small class="fw-semibold"><?= htmlspecialchars((string) $log['action'], ENT_QUOTES, 'UTF-8') ?></small>
                                    <small class="text-muted ms-2 text-nowrap"><?= !empty($log['action_time']) ? \App\Helpers\DateHelper::dateTime($log['action_time']) : '-' ?></small>
                                </div>
                                <?php if (!empty($log['user_name'])): ?>
                                    <small class="text-muted">by <?= htmlspecialchars((string) $log['user_name'], ENT_QUOTES, 'UTF-8') ?></small>
                                <?php endif; ?>
                                <?php if (!empty($log['description'])): ?>
                                    <div class="small fst-italic text-secondary">"<?= htmlspecialchars((string) $log['description'], ENT_QUOTES, 'UTF-8') ?>"</div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Meta Info -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <table class="table table-sm mb-0 small">
                    <tr>
                        <td class="text-muted">Created</td>
                        <td><?= \App\Helpers\DateHelper::date($symposium['created_at'] ?? 'now') ?></td>
                    </tr>
                    <?php if (!empty($symposium['submitted_at'])): ?>
                    <tr>
                        <td class="text-muted">Submitted</td>
                        <td><?= \App\Helpers\DateHelper::dateTime($symposium['submitted_at']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted">Last Updated</td>
                        <td><?= \App\Helpers\DateHelper::dateTime($symposium['updated_at'] ?? 'now') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Delete -->
        <?php if ($canDelete): ?>
            <form action="<?= base_url() ?>/symposiums/delete" method="post"
                onsubmit="return confirm('Permanently delete this symposium? This cannot be undone.')">
                <input type="hidden" name="symposium_id" value="<?= $symId ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                    <i class="bi bi-trash me-1"></i> Delete Symposium
                </button>
            </form>
        <?php endif; ?>

    </div>
</div>
