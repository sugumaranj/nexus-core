<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : index.php
 * Location    : templates/admin/events/
 * Description : Master Event Templates Library Index View.
 * -------------------------------------------------------------------------
 */

$events     = $events ?? [];
$category   = $category ?? 'All';
$status     = $status ?? 'All';
$search     = $search ?? '';
$categories = $categories ?? ['Technical', 'Non-Technical'];
$statuses   = $statuses ?? ['Draft', 'Published', 'Archived'];

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">
            <i class="bi bi-collection-fill text-primary me-2"></i>Master Event Library
        </h2>
        <p class="text-muted mb-0">
            Permanent, reusable event templates used across symposiums.
        </p>
    </div>
    <div>
        <a href="<?= base_url() ?>/admin/events/create" class="btn btn-primary shadow-sm">
            <i class="bi bi-plus-lg me-1"></i> Add Master Event
        </a>
    </div>
</div>

<!-- Filters & Search Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= base_url() ?>/admin/events" class="row g-3 align-items-center">
            <input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>">
            
            <!-- Category Filter Tabs -->
            <div class="col-md-4">
                <div class="btn-group w-100" role="group">
                    <a href="<?= base_url() ?>/admin/events?category=All&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>" 
                       class="btn btn-outline-secondary btn-sm <?= $category === 'All' ? 'active fw-bold' : '' ?>">
                       All
                    </a>
                    <a href="<?= base_url() ?>/admin/events?category=Technical&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>" 
                       class="btn btn-outline-primary btn-sm <?= $category === 'Technical' ? 'active fw-bold' : '' ?>">
                       Technical
                    </a>
                    <a href="<?= base_url() ?>/admin/events?category=Non-Technical&status=<?= urlencode($status) ?>&search=<?= urlencode($search) ?>" 
                       class="btn btn-outline-purple btn-sm <?= $category === 'Non-Technical' ? 'active fw-bold' : '' ?>">
                       Non-Technical
                    </a>
                </div>
            </div>

            <!-- Status Dropdown -->
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="All" <?= $status === 'All' ? 'selected' : '' ?>>All Statuses</option>
                    <?php foreach ($statuses as $st): ?>
                        <option value="<?= $st ?>" <?= $status === $st ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Search Field -->
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, code, or description..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-search me-1"></i> Search
                    </button>
                    <?php if ($category !== 'All' || $status !== 'All' || $search !== ''): ?>
                        <a href="<?= base_url() ?>/admin/events" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </div>
            </div>

        </form>
    </div>
</div>

<!-- Master Events Grid / Table -->
<?php if (empty($events)): ?>
    <div class="card border-0 shadow-sm text-center py-5">
        <div class="card-body">
            <i class="bi bi-folder-x text-muted display-4"></i>
            <h5 class="mt-3 text-muted">No Master Events Found</h5>
            <p class="text-muted small">Try clearing filters or add your first master event template.</p>
            <a href="<?= base_url() ?>/admin/events/create" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Create Event Template
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($events as $evt): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                    <div class="card-header bg-white border-bottom-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                        <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($evt['event_code']) ?></span>
                        <div>
                            <?php if ($evt['category'] === 'Technical'): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Technical</span>
                            <?php else: ?>
                                <span class="badge bg-purple-subtle text-purple border border-purple-subtle rounded-pill" style="background-color: #f3e8ff; color: #7e22ce;">Non-Technical</span>
                            <?php endif; ?>

                            <?php
                                $statusBadge = match($evt['status']) {
                                    'Published' => 'bg-success',
                                    'Draft'     => 'bg-warning text-dark',
                                    'Archived'  => 'bg-secondary',
                                    default     => 'bg-info',
                                };
                            ?>
                            <span class="badge <?= $statusBadge ?> ms-1"><?= htmlspecialchars($evt['status']) ?></span>
                        </div>
                    </div>

                    <div class="card-body pt-2">
                        <h5 class="fw-bold text-dark mb-1">
                            <a href="<?= base_url() ?>/admin/events/view?id=<?= $evt['event_id'] ?>" class="text-decoration-none text-dark hover-primary">
                                <?= htmlspecialchars($evt['event_name']) ?>
                            </a>
                        </h5>

                        <!-- Highlights Badge Grid -->
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge bg-light text-dark border">
                                <?php
                                    $durType = $evt['duration_type'] ?? 'minutes';
                                    if ($durType === 'minutes') {
                                        $mins = (int)($evt['duration_minutes'] ?? 0);
                                        echo '<i class="bi bi-stopwatch me-1 text-primary"></i>';
                                        echo '<strong>' . $mins . '</strong> mins';
                                    } else {
                                        $st = $evt['start_time'] ?? '';
                                        $et = $evt['end_time'] ?? '';
                                        $dd = (int)($evt['duration_days'] ?? 1);
                                        if ($st && $et) {
                                            $fmtT = function($t) { [$h,$m] = explode(':',$t); $h=(int)$h; return ($h%12?:12).":$m".($h<12?' AM':' PM'); };
                                            [$sh,$sm] = array_map('intval', explode(':', $st));
                                            [$eh,$em] = array_map('intval', explode(':', $et));
                                            $totalMins = (($eh * 60 + $em) - ($sh * 60 + $sm)) * $dd;
                                            echo '<i class="bi bi-calendar-range me-1 text-primary"></i>';
                                            echo htmlspecialchars($fmtT($st)).' – '.htmlspecialchars($fmtT($et));
                                            echo ' · '.$dd.($dd>1?' Days':' Day');
                                            if ($totalMins > 0) echo ' <span class="text-muted">('.$totalMins.' mins)</span>';
                                        } else {
                                            echo '<i class="bi bi-clock me-1 text-primary"></i>Not set';
                                        }
                                    }
                                ?>
                            </span>
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-people me-1 text-info"></i><?= htmlspecialchars($evt['participation_type']) ?>
                                <?php if ($evt['participation_type'] !== 'Individual'): ?>
                                    (<?= (int)$evt['min_team_size'] ?>-<?= (int)$evt['max_team_size'] ?>)
                                <?php endif; ?>
                            </span>
                            <?php if (!empty($evt['requires_prelims'])): ?>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-journal-check me-1 text-warning"></i>Prelims Required
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="border-top pt-2 d-flex justify-content-between align-items-center text-muted small">
                            <span></span>

                        </div>
                    </div>

                    <div class="card-footer bg-light border-top-0 d-flex justify-content-between align-items-center">
                        <a href="<?= base_url() ?>/admin/events/view?id=<?= $evt['event_id'] ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye me-1"></i> View
                        </a>
                        <div class="btn-group btn-group-sm">
                            <a href="<?= base_url() ?>/admin/events/edit?id=<?= $evt['event_id'] ?>" class="btn btn-outline-primary" title="Edit Template">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-outline-success" title="Clone Template" 
                                    onclick="openCloneModal(<?= $evt['event_id'] ?>, '<?= htmlspecialchars(addslashes($evt['event_name'])) ?>')">
                                <i class="bi bi-copy"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger" title="Delete Template" 
                                    onclick="openDeleteModal(<?= $evt['event_id'] ?>, '<?= htmlspecialchars(addslashes($evt['event_name'])) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal: Clone Event Template -->
<div class="modal fade" id="cloneEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="<?= base_url() ?>/admin/events/clone">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-copy text-success me-2"></i>Clone Master Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="source_event_id" id="clone_source_id">
                    <p class="text-muted small mb-3">
                        Creating a duplicate copy of <strong id="clone_source_name"></strong>. Rules and resource requirements will be copied automatically.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">New Event Name <span class="text-danger">*</span></label>
                        <input type="text" name="new_event_name" id="clone_new_name" class="form-control" required placeholder="e.g. AI Coding Challenge">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-copy me-1"></i> Clone Template</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Delete Confirmation -->
<div class="modal fade" id="deleteEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= base_url() ?>/admin/events/delete">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Delete Event Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="event_id" id="delete_event_id">
                    <p class="mb-0">Are you sure you want to soft-delete <strong id="delete_event_name"></strong>?</p>
                    <small class="text-muted">This template will be hidden from the library but existing symposium references remain safe.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash me-1"></i> Delete Template</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openCloneModal(id, name) {
    document.getElementById('clone_source_id').value = id;
    document.getElementById('clone_source_name').innerText = name;
    document.getElementById('clone_new_name').value = name + ' (Copy)';
    var modal = new bootstrap.Modal(document.getElementById('cloneEventModal'));
    modal.show();
}

function openDeleteModal(id, name) {
    document.getElementById('delete_event_id').value = id;
    document.getElementById('delete_event_name').innerText = name;
    var modal = new bootstrap.Modal(document.getElementById('deleteEventModal'));
    modal.show();
}
</script>
