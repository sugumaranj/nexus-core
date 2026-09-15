<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : index.php
 * Location    : templates/symposiums/events/
 * Description : List Scheduled Events for a Symposium View.
 * -------------------------------------------------------------------------
 */

$symposium = $symposium ?? [];
$events    = $events ?? [];
$canManage = $canManage ?? false;

$totalEvents = count($events);
$categories  = array_unique(array_column($events, 'category'));

?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= base_url() ?>/symposiums">Symposiums</a></li>
                <li class="breadcrumb-item"><a href="<?= base_url() ?>/symposiums/view?id=<?= $symposium['symposium_id'] ?>"><?= htmlspecialchars($symposium['title']) ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Events</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1 text-dark">
            <i class="bi bi-calendar-event text-primary me-2"></i><?= htmlspecialchars($symposium['title']) ?>
            <span class="text-muted fw-normal">— Events</span>
        </h2>
        <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
            <span class="text-muted small fw-semibold"><?= htmlspecialchars($symposium['symposium_code']) ?></span>
            <span class="text-muted">·</span>
            <span class="badge rounded-pill text-bg-primary"><?= htmlspecialchars($symposium['symposium_type']) ?></span>
            <span class="text-muted">·</span>
            <span class="text-muted small"><i class="bi bi-calendar-range me-1"></i><?= \App\Helpers\DateHelper::date($symposium['event_start_date']) ?> – <?= \App\Helpers\DateHelper::date($symposium['event_end_date']) ?></span>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap align-items-center">
        <?php if ($canManage): ?>
            <?php if (isset($currentUser) && $currentUser['role'] === 'Staff Coordinator'): ?>
                <button type="button" class="btn btn-warning shadow-sm fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
                    <i class="bi bi-cloud-arrow-down-fill me-1"></i> Import Master Events
                </button>
                <?php if ($totalEvents > 0): ?>
                <button type="button" class="btn btn-danger shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#deleteAllModal">
                    <i class="bi bi-trash3-fill me-1"></i> Delete All Events
                </button>
                <?php endif; ?>
            <?php endif; ?>
            <a href="<?= base_url() ?>/symposiums/events/create?symposium_id=<?= $symposium['symposium_id'] ?>" class="btn btn-primary shadow-sm fw-semibold">
                <i class="bi bi-plus-lg me-1"></i> Add Event
            </a>
            <a href="<?= base_url() ?>/notice-board?symposium_id=<?= $symposium['symposium_id'] ?>" class="btn btn-outline-primary shadow-sm fw-semibold" target="_blank">
                <i class="bi bi-printer me-1"></i> Print Report
            </a>
        <?php else: ?>
            <?php if (in_array($symposium['status'], ['Approved', 'Published'])): ?>
                <a href="<?= base_url() ?>/notice-board?symposium_id=<?= $symposium['symposium_id'] ?>" class="btn btn-info text-white shadow-sm fw-semibold" target="_blank">
                    <i class="bi bi-clipboard me-1"></i> Notice Board
                </a>
            <?php endif; ?>
        <?php endif; ?>
        <a href="<?= base_url() ?>/symposiums/view?id=<?= $symposium['symposium_id'] ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>

<!-- Stats Bar -->
<?php if (!empty($events)): ?>
<div class="row g-3 mb-4">
    <?php
        $techCount    = count(array_filter($events, fn($e) => $e['category'] === 'Technical'));
        $nonTechCount = count(array_filter($events, fn($e) => $e['category'] !== 'Technical'));
        $withPrelims  = count(array_filter($events, fn($e) => isset($e['prelim_decision']) && $e['prelim_decision'] === 'Required'));
    ?>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3 px-2 h-100 filter-card" data-filter="all" style="cursor: pointer; outline: 2px solid #0d6efd;">
            <div class="fw-bold fs-3 text-primary"><?= $totalEvents ?></div>
            <div class="text-muted small">Total Events</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3 px-2 h-100 filter-card" data-filter="Technical" style="cursor: pointer; transition: all 0.2s;">
            <div class="fw-bold fs-3 text-info"><?= $techCount ?></div>
            <div class="text-muted small">Technical</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3 px-2 h-100 filter-card" data-filter="Non-Technical" style="cursor: pointer; transition: all 0.2s;">
            <div class="fw-bold fs-3" style="color:#7c3aed;"><?= $nonTechCount ?></div>
            <div class="text-muted small">Non-Technical</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3 px-2 h-100 filter-card" data-filter="Prelims" style="cursor: pointer; transition: all 0.2s;">
            <div class="fw-bold fs-3 text-warning"><?= $withPrelims ?></div>
            <div class="text-muted small">With Prelims</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Events Grid -->
<?php if (empty($events)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <div class="mb-3" style="font-size: 3.5rem; opacity: 0.18;">📅</div>
            <h5 class="text-dark fw-bold mb-1">No Events Scheduled Yet</h5>
            <p class="text-muted small mb-4">Add events from the Master Event Library or create a new event for this symposium.</p>
            <?php if ($canManage): ?>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="<?= base_url() ?>/symposiums/events/wizard?symposium_id=<?= $symposium['symposium_id'] ?>" class="btn btn-success">
                        <i class="bi bi-magic me-1"></i> Use Wizard
                    </a>
                    <a href="<?= base_url() ?>/symposiums/events/create?symposium_id=<?= $symposium['symposium_id'] ?>" class="btn btn-outline-primary">
                        <i class="bi bi-plus-lg me-1"></i> Add Manually
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($events as $se): ?>
            <?php
                $isTech = $se['category'] === 'Technical';
                $statusConfig = match($se['status'] ?? 'Scheduled') {
                    'Scheduled'           => ['bg-info-subtle text-info border-info',   'info',      'bi-clock'],
                    'Registration Open'   => ['bg-success-subtle text-success border-success', 'success', 'bi-check-circle-fill'],
                    'Registration Closed' => ['bg-warning-subtle text-warning border-warning', 'warning', 'bi-lock-fill'],
                    'Running'             => ['bg-primary-subtle text-primary border-primary', 'primary', 'bi-play-circle-fill'],
                    'Completed'           => ['bg-secondary-subtle text-secondary border-secondary', 'secondary', 'bi-check2-all'],
                    'Cancelled'           => ['bg-danger-subtle text-danger border-danger', 'danger', 'bi-x-circle-fill'],
                    default               => ['bg-light text-muted border', 'secondary', 'bi-dash-circle'],
                };
                $accentColor = $isTech ? '#2563eb' : '#7c3aed';
            ?>
            <div class="col-md-6 col-xl-4 event-item" data-category="<?= htmlspecialchars($se['category']) ?>" data-prelims="<?= (isset($se['prelim_decision']) && $se['prelim_decision'] === 'Required') ? '1' : '0' ?>">
                <div class="card border-0 shadow-sm h-100 event-card" style="border-top: 3px solid <?= $accentColor ?> !important; border-radius: 12px; overflow: hidden;">
                    <!-- Card Top: code + badges -->
                    <div class="card-header bg-white border-0 pb-0 pt-3 px-3 d-flex justify-content-between align-items-center">
                        <span class="badge bg-dark font-monospace fw-semibold" style="font-size: 0.7rem; letter-spacing: 0.04em;">
                            <?= htmlspecialchars($se['event_code']) ?>
                        </span>
                        <div class="d-flex gap-1">
                            <span class="badge rounded-pill small" style="<?= $isTech ? 'background:#dbeafe;color:#1d4ed8;' : 'background:#ede9fe;color:#6d28d9;' ?>">
                                <?= $isTech ? 'Technical' : 'Non-Technical' ?>
                            </span>
                            <span class="badge border small <?= $statusConfig[0] ?>">
                                <i class="bi <?= $statusConfig[2] ?> me-1"></i><?= htmlspecialchars($se['status']) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Event Name -->
                    <div class="px-3 pt-2 pb-0">
                        <h5 class="fw-bold text-dark mb-0" style="font-size: 1.1rem; line-height: 1.3;">
                            <a href="<?= base_url() ?>/symposiums/events/view?id=<?= $se['symposium_event_id'] ?>"
                               class="text-decoration-none text-dark">
                                <?= htmlspecialchars($se['event_name']) ?>
                            </a>
                        </h5>
                    </div>

                    <!-- Details -->
                    <div class="card-body pt-3 pb-2 px-3">
                        <div class="d-flex flex-column gap-1 small text-muted mb-3">
                            <div class="mb-1">
                                <i class="bi bi-calendar-event text-secondary me-2"></i>
                                <span class="text-dark fw-semibold"><?= !empty($se['event_date']) ? \App\Helpers\DateHelper::date($se['event_date']) : 'Date TBA' ?></span>
                            </div>
                            <div class="mb-2">
                                <i class="bi bi-clock text-secondary me-2"></i>
                                <?php if (!empty($se['start_time']) && !empty($se['end_time'])): ?>
                                    <span><?= \App\Helpers\DateHelper::time($se['start_time']) ?> &ndash; <?= \App\Helpers\DateHelper::time($se['end_time']) ?> <span class="text-muted small">(Main)</span></span>
                                <?php else: ?>
                                    <span>Main Event TBA</span>
                                <?php endif; ?>
                                <?php if (isset($se['prelim_decision']) && $se['prelim_decision'] === 'Required'): ?>
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1" style="font-size: 0.65rem;">+ Stages</span>
                                <?php endif; ?>
                                <?php if (!empty($se['session'])): ?>
                                    <span class="badge bg-light text-dark ms-1"><?= htmlspecialchars($se['session']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-geo-alt text-primary" style="width:14px;"></i>
                                <span class="text-dark fw-semibold"><?= htmlspecialchars($se['venue_name'] ?: '—') ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-person text-primary" style="width:14px;"></i>
                                <span><?= htmlspecialchars($se['faculty_coordinator_name'] ?: 'Unassigned') ?></span>
                            </div>
                        </div>

                        <!-- Chips -->
                        <div class="d-flex flex-wrap gap-1">
                            <span class="badge rounded-pill bg-light text-muted border" style="font-size:0.72rem;">
                                <i class="bi bi-people me-1"></i><?= htmlspecialchars($se['participation_type']) ?>
                            </span>
                            <?php if (!empty($se['max_participants'])): ?>
                                <span class="badge rounded-pill bg-light text-muted border" style="font-size:0.72rem;">
                                    <i class="bi bi-person-bounding-box me-1"></i>Max <?= (int)$se['max_participants'] ?>
                                </span>
                            <?php endif; ?>
                            <?php if (isset($se['prelim_decision']) && $se['prelim_decision'] === 'Required'): ?>
                                <span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle" style="font-size:0.72rem;">
                                    <i class="bi bi-journal-check me-1"></i>Prelims
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center px-3 py-2">
                        <a href="<?= base_url() ?>/symposiums/events/view?id=<?= $se['symposium_event_id'] ?>"
                           class="btn btn-sm btn-outline-primary fw-semibold px-3">
                            <i class="bi bi-eye me-1"></i> View
                        </a>
                        <?php if ($canManage): ?>
                            <div class="d-flex gap-1">
                                <a href="<?= base_url() ?>/symposiums/events/edit?id=<?= $se['symposium_event_id'] ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Remove"
                                        onclick="openDeleteModal(<?= $se['symposium_event_id'] ?>, '<?= htmlspecialchars(addslashes($se['event_name'])) ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= base_url() ?>/symposiums/events/delete">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger-subtle" style="width:36px;height:36px;">
                            <i class="bi bi-trash text-danger"></i>
                        </div>
                        <h5 class="modal-title fw-bold text-dark mb-0">Remove Event</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <input type="hidden" name="symposium_event_id" id="delete_se_id">
                    <p class="mb-1 text-dark">Remove <strong id="delete_se_name"></strong> from this symposium schedule?</p>
                    <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>The Master Event template stays safe in the library.</p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-semibold"><i class="bi bi-trash me-1"></i>Remove</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Import Modal -->
<div class="modal fade" id="bulkImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= base_url() ?>/symposiums/events/bulk-add" class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning-subtle" style="width:36px;height:36px;">
                            <i class="bi bi-cloud-arrow-down-fill text-warning"></i>
                        </div>
                        <h5 class="modal-title fw-bold text-dark mb-0">Import All Master Events</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <input type="hidden" name="symposium_id" value="<?= $symposium['symposium_id'] ?>">
                    <p class="mb-2 text-dark">This will instantly import all available Master Event templates into this symposium.</p>
                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Events will be imported with <strong>TBA</strong> dates, times, and venues. You must edit them later to set the final schedule.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark"><i class="bi bi-cloud-arrow-down-fill me-1"></i>Import Now</button>
                </div>
        </form>
    </div>
</div>

<!-- Delete All Events Modal -->
<div class="modal fade" id="deleteAllModal" tabindex="-1" aria-labelledby="deleteAllModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="<?= base_url() ?>/symposiums/events/delete-all" id="deleteAllForm">
            <input type="hidden" name="symposium_id" value="<?= $symposium['symposium_id'] ?>">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger" style="width:36px;height:36px;">
                            <i class="bi bi-exclamation-triangle-fill text-white" style="font-size:1rem;"></i>
                        </div>
                        <h5 class="modal-title fw-bold text-danger mb-0" id="deleteAllModalLabel">Delete All Events</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <!-- Warning Banner -->
                    <div class="alert alert-danger border-danger d-flex align-items-start gap-2 mb-3 py-2 px-3" style="border-left:4px solid #dc3545;">
                        <i class="bi bi-shield-exclamation fs-5 flex-shrink-0 mt-1"></i>
                        <div>
                            <div class="fw-bold small">This action is permanent and cannot be undone.</div>
                            <div class="text-muted small">All <strong><?= $totalEvents ?> event(s)</strong> will be permanently removed from <strong><?= htmlspecialchars($symposium['title']) ?></strong>. Master Event templates in the library will remain intact.</div>
                        </div>
                    </div>

                    <!-- Password Field -->
                    <label for="confirm_password" class="form-label fw-semibold text-dark small mb-1">
                        <i class="bi bi-lock-fill me-1 text-danger"></i>Enter your account password to confirm:
                    </label>
                    <div class="input-group">
                        <input type="password"
                               name="confirm_password"
                               id="confirm_password"
                               class="form-control border-danger"
                               placeholder="Your login password"
                               autocomplete="current-password"
                               required>
                        <button type="button"
                                class="btn btn-outline-secondary"
                                id="toggleDeletePassword"
                                title="Show/hide password">
                            <i class="bi bi-eye" id="deletePasswordEyeIcon"></i>
                        </button>
                    </div>
                    <div class="form-text text-danger mt-1" id="deletePasswordError" style="display:none;">
                        <i class="bi bi-exclamation-circle me-1"></i>Please enter your password before proceeding.
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold" id="deleteAllSubmitBtn">
                        <i class="bi bi-trash3-fill me-1"></i>Delete All <?= $totalEvents ?> Event(s)
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.event-card {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.event-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1) !important;
}
</style>

<script>
function openDeleteModal(id, name) {
    document.getElementById('delete_se_id').value = id;
    document.getElementById('delete_se_name').innerText = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    // ---- Filter Cards ----
    const filterCards = document.querySelectorAll('.filter-card');
    const eventItems = document.querySelectorAll('.event-item');

    filterCards.forEach(card => {
        card.addEventListener('click', function() {
            // Remove active styling from all
            filterCards.forEach(c => {
                c.style.outline = 'none';
                c.style.transform = 'translateY(0)';
            });
            
            // Add active styling to clicked card
            this.style.outline = '2px solid #0d6efd';
            this.style.transform = 'translateY(-3px)';

            const filter = this.getAttribute('data-filter');

            eventItems.forEach(item => {
                const category = item.getAttribute('data-category');
                
                if (filter === 'all') {
                    item.style.display = '';
                } else if (filter === 'Technical') {
                    if (category === 'Technical') {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                } else if (filter === 'Non-Technical') {
                    if (category !== 'Technical') {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                } else if (filter === 'Prelims') {
                    const hasPrelims = item.getAttribute('data-prelims');
                    if (hasPrelims === '1') {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                }
            });
        });
    });

    // ---- Delete All Modal: show/hide password toggle ----
    const toggleBtn = document.getElementById('toggleDeletePassword');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            const pwdInput = document.getElementById('confirm_password');
            const eyeIcon  = document.getElementById('deletePasswordEyeIcon');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                eyeIcon.className = 'bi bi-eye-slash';
            } else {
                pwdInput.type = 'password';
                eyeIcon.className = 'bi bi-eye';
            }
        });
    }

    // ---- Delete All Modal: client-side form validation ----
    const deleteAllForm = document.getElementById('deleteAllForm');
    if (deleteAllForm) {
        // Reset password field and error when modal opens
        const deleteAllModal = document.getElementById('deleteAllModal');
        if (deleteAllModal) {
            deleteAllModal.addEventListener('show.bs.modal', function() {
                const pwdInput   = document.getElementById('confirm_password');
                const errEl      = document.getElementById('deletePasswordError');
                const eyeIcon    = document.getElementById('deletePasswordEyeIcon');
                if (pwdInput)  { pwdInput.value = ''; pwdInput.type = 'password'; }
                if (errEl)     { errEl.style.display = 'none'; }
                if (eyeIcon)   { eyeIcon.className = 'bi bi-eye'; }
            });
        }

        deleteAllForm.addEventListener('submit', function(e) {
            const pwdInput = document.getElementById('confirm_password');
            const errEl    = document.getElementById('deletePasswordError');
            if (!pwdInput || pwdInput.value.trim() === '') {
                e.preventDefault();
                if (errEl) { errEl.style.display = 'block'; }
                if (pwdInput) { pwdInput.focus(); }
                return;
            }
            if (errEl) { errEl.style.display = 'none'; }

            // Disable submit button to prevent double-submit
            const submitBtn = document.getElementById('deleteAllSubmitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Deleting…';
            }
        });
    }
});
</script>
