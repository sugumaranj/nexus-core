<?php
declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : event_allocation.php
 * Location    : templates/allocation/
 * Description : Per-event Faculty In-Charge & Judge Assignment Screen.
 *
 * Features:
 *   • Current assignments (Faculty + Judges) with Remove / Replace buttons
 *   • Staff availability grid with Green/Orange/Red indicators
 *   • Conflict preview on hover/select (shows event, date, session, venue)
 *   • Department + Role + Search filters (live filter, no page reload)
 *   • AJAX availability check on staff selection
 *   • Auto-suggest: Available staff shown first
 *   • Assign / Replace modals with one-click submit
 * -------------------------------------------------------------------------
 */

$event          = $event          ?? [];
$symposium      = $symposium      ?? [];
$canManage      = $canManage      ?? false;
$currentFaculty = $currentFaculty ?? [];
$currentJudges  = $currentJudges  ?? [];
$staffList      = $staffList      ?? [];
$departments    = $departments    ?? [];
$deptFilter     = $deptFilter     ?? '';
$roleFilter     = $roleFilter     ?? '';
$assignableRoles = $assignableRoles ?? [];

$eid       = (int) ($event['symposium_event_id'] ?? 0);
$symId     = (int) ($event['symposium_id'] ?? 0);
$eventName = htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES);
$eventDate = $event['event_date'] ?? '';
$session   = $event['session'] ?? '';
$startTime = $event['start_time'] ?? '';
$endTime   = $event['end_time'] ?? '';

$sessionLabels = ['FN' => 'Forenoon', 'AN' => 'Afternoon', 'Full Day' => 'Full Day'];
$sessionLabel  = $sessionLabels[$session] ?? $session;

$availableCount = count(array_filter($staffList, fn($s) => $s['availability_status'] === 'available'));
$busyCount      = count(array_filter($staffList, fn($s) => $s['availability_status'] === 'busy'));
$inactiveCount  = count(array_filter($staffList, fn($s) => $s['availability_status'] === 'inactive'));

function availBadge(string $status): string {
    return match($status) {
        'available' => '<span class="avail-dot dot-green" title="Available"></span>',
        'busy'      => '<span class="avail-dot dot-orange" title="Busy"></span>',
        'inactive'  => '<span class="avail-dot dot-red" title="Inactive"></span>',
        default     => '<span class="avail-dot dot-grey"></span>',
    };
}
?>

<style>
/* ═══════════════════════════════════════════════════════════════════ */
.avail-dot { display:inline-block; width:10px; height:10px; border-radius:50%; }
.dot-green  { background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.2); }
.dot-orange { background:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,.2); }
.dot-red    { background:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.2); }
.dot-grey   { background:#9ca3af; }

.event-info-banner {
    background: linear-gradient(135deg, #1e3a5f 0%, #1d4ed8 100%);
    border-radius: 18px; color: white; padding: 1.5rem;
    box-shadow: 0 4px 24px rgba(29,78,216,.3);
}
.alloc-section-card {
    border-radius: 16px; border: none;
    box-shadow: 0 2px 16px rgba(0,0,0,.06);
}
.assigned-person-card {
    border-radius: 12px; border: 1px solid #e9ecef;
    padding: .75rem 1rem;
    display: flex; align-items: center; gap: .75rem;
    background: #fff; transition: box-shadow .15s;
}
.assigned-person-card:hover { box-shadow: 0 3px 14px rgba(0,0,0,.1); }
.person-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; font-weight: 700; flex-shrink: 0;
}
.avatar-blue  { background: rgba(13,110,253,.12); color: #0d6efd; }
.avatar-green { background: rgba(25,135,84,.12);  color: #198754; }

.staff-grid-item {
    border-radius: 12px; border: 1px solid #e9ecef;
    padding: .65rem .9rem; cursor: pointer;
    display: flex; align-items: center; gap: .65rem;
    background: #fff; transition: all .15s;
    font-size: .85rem;
    position: relative;
}
.staff-grid-item:hover { border-color: #0d6efd; box-shadow: 0 2px 12px rgba(13,110,253,.1); }
.staff-grid-item.is-assigned { background: #f0fdf4; border-color: #22c55e; }
.staff-grid-item.is-busy     { background: #fffbeb; }
.staff-grid-item.is-inactive { opacity: .55; cursor: not-allowed; }

/* Selected state inside modal lists */
.staff-grid-item.is-selected {
    border-color: #0d6efd !important;
    background: #eff6ff !important;
    box-shadow: 0 0 0 3px rgba(13,110,253,.18) !important;
}
.staff-grid-item.is-selected::after {
    content: '\2713';
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 22px; height: 22px;
    background: #0d6efd;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .78rem;
    font-weight: 700;
    line-height: 22px;
    text-align: center;
}

.conflict-tooltip {
    background: #1e293b; color: #f1f5f9; border-radius: 10px;
    padding: .6rem .9rem; font-size: .78rem; line-height: 1.5;
    box-shadow: 0 8px 24px rgba(0,0,0,.3);
    white-space: nowrap; pointer-events: none;
    position: absolute; z-index: 1000; display: none;
}
.staff-grid-item:hover .conflict-tooltip { display: block; }

.filter-bar {
    background: #f8f9fa; border-radius: 14px; padding: .9rem 1.1rem;
    display: flex; gap: .75rem; align-items: center; flex-wrap: wrap;
    margin-bottom: 1rem;
}
.avail-legend span { font-size: .78rem; color: #6c757d; display: flex; align-items: center; gap: 5px; }
</style>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0" style="font-size:.82rem;">
        <li class="breadcrumb-item"><a href="<?= base_url() ?>/symposiums">Symposiums</a></li>
        <li class="breadcrumb-item"><a href="<?= base_url() ?>/symposiums/allocation?symposium_id=<?= $symId ?>">Staff Allocation</a></li>
        <li class="breadcrumb-item active"><?= $eventName ?></li>
    </ol>
</nav>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- Event Info Banner -->
<div class="event-info-banner mb-4">
    <div class="row align-items-center">
        <div class="col">
            <h2 class="h4 fw-bold mb-1"><?= $eventName ?></h2>
            <div class="d-flex flex-wrap gap-3" style="font-size:.85rem; opacity:.88;">
                <span><i class="bi bi-calendar3 me-1"></i><?= !empty($eventDate) && !empty($eventDate) ? \App\Helpers\DateHelper::date($eventDate) : '—' ?></span>
                <span><i class="bi bi-clock me-1"></i><?= !empty($startTime) && !empty($endTime) ? \App\Helpers\DateHelper::time($startTime) . ' – ' . \App\Helpers\DateHelper::time($endTime) : 'Time TBA' ?></span>
                <span><i class="bi bi-sunrise me-1"></i><?= $sessionLabel ?: 'TBA' ?></span>
                <?php if (!empty($event['venue_name'])): ?>
                <span><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($event['venue_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-auto d-none d-md-flex gap-2">
            <div class="text-center px-3">
                <div style="font-size:1.5rem; font-weight:800;"><?= $availableCount ?></div>
                <div style="font-size:.72rem; opacity:.8;">Available</div>
            </div>
            <div class="vr opacity-25"></div>
            <div class="text-center px-3">
                <div style="font-size:1.5rem; font-weight:800; color:#fbbf24;"><?= $busyCount ?></div>
                <div style="font-size:.72rem; opacity:.8;">Busy</div>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- Current Assignments Row -->
<div class="row g-3 mb-4">

    <!-- Faculty In-Charge Panel -->
    <div class="col-md-6">
        <div class="card alloc-section-card h-100">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <i class="bi bi-person-badge text-primary fs-5"></i>
                <strong>Faculty In-Charge</strong>
                <?php if (!empty($currentFaculty)): ?>
                <span class="badge bg-primary ms-auto"><?= count($currentFaculty) ?></span>
                <?php else: ?>
                <span class="badge bg-warning text-dark ms-auto">Not Assigned</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($currentFaculty)): ?>
                    <?php foreach ($currentFaculty as $fac): ?>
                    <div class="assigned-person-card mb-2">
                        <div class="person-avatar avatar-blue">
                            <i class="bi bi-person"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate" style="font-size:.9rem;"><?= htmlspecialchars($fac['full_name'] ?? '') ?></div>
                            <div class="text-secondary" style="font-size:.76rem;">
                                <?= htmlspecialchars($fac['user_role'] ?? '') ?>
                                <?php if (!empty($fac['department_name'])): ?> &bull; <?= htmlspecialchars($fac['department_name']) ?><?php endif; ?>
                            </div>
                        </div>
                        <?php if ($canManage): ?>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <!-- Replace -->
                            <button type="button" class="btn btn-sm btn-outline-warning px-2"
                                    onclick="openReplaceModal('faculty', <?= $eid ?>, <?= (int)$fac['user_id'] ?>, '<?= addslashes($fac['full_name'] ?? '') ?>')"
                                    title="Replace">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                            <!-- Remove -->
                            <form method="POST" action="<?= base_url() ?>/symposiums/allocation/faculty/remove" class="d-inline"
                                  onsubmit="return confirm('Remove <?= addslashes($fac['full_name'] ?? '') ?> as Faculty In-Charge?')">
                                <input type="hidden" name="symposium_event_id" value="<?= $eid ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $fac['user_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Remove">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-person-dash fs-2 opacity-40"></i>
                        <p class="mt-2 mb-0 small">No Faculty In-Charge assigned yet.</p>
                    </div>
                <?php endif; ?>

                <?php if ($canManage): ?>
                <div class="border-top pt-3 mt-2">
                    <button type="button" class="btn btn-primary btn-sm w-100"
                            onclick="openAssignModal('faculty', <?= $eid ?>)">
                        <i class="bi bi-person-plus me-2"></i>
                        <?= empty($currentFaculty) ? 'Assign Faculty In-Charge' : 'Add Another FIC' ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Judges Panel -->
    <div class="col-md-6">
        <div class="card alloc-section-card h-100">
            <div class="card-header bg-transparent border-bottom d-flex align-items-center gap-2 py-3">
                <i class="bi bi-star text-success fs-5"></i>
                <strong>Judges</strong>
                <?php if (!empty($currentJudges)): ?>
                <span class="badge bg-success ms-auto"><?= count($currentJudges) ?></span>
                <?php else: ?>
                <span class="badge bg-warning text-dark ms-auto">Not Assigned</span>
                <?php endif; ?>
            </div>
            <div class="card-body p-3">
                <?php if (!empty($currentJudges)): ?>
                    <?php foreach ($currentJudges as $judge): ?>
                    <div class="assigned-person-card mb-2">
                        <div class="person-avatar avatar-green">
                            <i class="bi bi-star-half"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate" style="font-size:.9rem;"><?= htmlspecialchars($judge['full_name'] ?? '') ?></div>
                            <div class="text-secondary" style="font-size:.76rem;">
                                <?= htmlspecialchars($judge['user_role'] ?? '') ?>
                                <?php if (!empty($judge['department_name'])): ?> &bull; <?= htmlspecialchars($judge['department_name']) ?><?php endif; ?>
                            </div>
                        </div>
                        <?php if ($canManage): ?>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <button type="button" class="btn btn-sm btn-outline-warning px-2"
                                    onclick="openReplaceModal('judge', <?= $eid ?>, <?= (int)$judge['user_id'] ?>, '<?= addslashes($judge['full_name'] ?? '') ?>')"
                                    title="Replace">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                            <form method="POST" action="<?= base_url() ?>/symposiums/allocation/judge/remove" class="d-inline"
                                  onsubmit="return confirm('Remove <?= addslashes($judge['full_name'] ?? '') ?> as Judge?')">
                                <input type="hidden" name="symposium_event_id" value="<?= $eid ?>">
                                <input type="hidden" name="user_id" value="<?= (int) $judge['user_id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger px-2" title="Remove">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-star fs-2 opacity-40"></i>
                        <p class="mt-2 mb-0 small">No judges assigned yet.</p>
                    </div>
                <?php endif; ?>

                <?php if ($canManage): ?>
                <div class="border-top pt-3 mt-2">
                    <button type="button" class="btn btn-success btn-sm w-100"
                            onclick="openAssignModal('judge', <?= $eid ?>)">
                        <i class="bi bi-star-fill me-2"></i>
                        <?= empty($currentJudges) ? 'Assign Judge' : 'Add Another Judge' ?>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- Venue Assignment                                                         -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<?php
$currentVenue   = $currentVenue ?? null;
$venueConflict  = $venueConflict ?? false;
?>
<div class="card alloc-section-card mb-4" id="venueAssignmentCard">
    <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
        <i class="bi bi-geo-alt fs-5 text-warning"></i>
        <strong>Venue</strong>
        <?php if ($currentVenue): ?>
        <span class="badge bg-success ms-auto"><?= htmlspecialchars($currentVenue['venue_name']) ?></span>
        <?php else: ?>
        <span class="badge bg-warning text-dark ms-auto">Not Assigned</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-3">

        <?php if ($venueConflict): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2 py-2 mb-3">
            <i class="bi bi-exclamation-triangle-fill text-danger mt-1"></i>
            <div>
                <strong>Venue Conflict!</strong>
                <div class="small">The assigned venue has a scheduling conflict with another event at the same time. Please reassign.</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($currentVenue): ?>
        <!-- Current venue detail -->
        <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-3" style="background:#f0fdf4; border:1px solid #bbf7d0;">
            <div style="width:44px;height:44px;border-radius:50%;background:rgba(34,197,94,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-building text-success fs-5"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-bold text-truncate"><?= htmlspecialchars($currentVenue['venue_name']) ?></div>
                <div class="text-secondary small">
                    <?php if (!empty($currentVenue['venue_code'])): ?>
                    <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($currentVenue['venue_code']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($currentVenue['building_name'])): ?>
                    <?= htmlspecialchars($currentVenue['building_name']) ?>
                    <?php endif; ?>
                    <?php if (!empty($currentVenue['floor'])): ?>
                    &bull; Floor <?= htmlspecialchars($currentVenue['floor']) ?>
                    <?php endif; ?>
                    <?php if (!empty($currentVenue['seating_capacity'])): ?>
                    &bull; <?= (int)$currentVenue['seating_capacity'] ?> seats
                    <?php endif; ?>
                    <?php if (!empty($currentVenue['is_computer_lab']) && $currentVenue['is_computer_lab']): ?>
                    <span class="badge bg-info text-dark ms-1"><i class="bi bi-pc-display me-1"></i>Computer Lab</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($canManage): ?>
            <div class="d-flex gap-1 flex-shrink-0">
                <button type="button" class="btn btn-sm btn-outline-warning" onclick="openVenueModal()" title="Change venue">
                    <i class="bi bi-arrow-repeat"></i>
                </button>
                <form method="POST" action="<?= base_url() ?>/symposiums/allocation/venue/remove"
                      onsubmit="return confirm('Remove the venue assignment from this event?')" class="d-inline">
                    <input type="hidden" name="symposium_event_id" value="<?= $eid ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove venue">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="text-center py-4 text-muted">
            <i class="bi bi-geo-alt fs-2 opacity-40"></i>
            <p class="mt-2 mb-0 small">No venue assigned yet.</p>
        </div>
        <?php endif; ?>

        <?php if ($canManage && $eventDate): ?>
        <div class="border-top pt-3 mt-2">
            <button type="button" class="btn btn-warning btn-sm w-100 text-dark" onclick="openVenueModal()">
                <i class="bi bi-geo-alt-fill me-2"></i>
                <?= $currentVenue ? 'Change Venue' : 'Assign Venue' ?>
            </button>
        </div>
        <?php elseif ($canManage && !$eventDate): ?>
        <div class="text-muted small text-center mt-2">
            <i class="bi bi-info-circle me-1"></i>Schedule the event date &amp; time first, then assign a venue.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($canManage): ?>
<!-- Venue Assignment Modal -->
<div class="modal fade" id="venueModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px; overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg,#78350f,#f59e0b); color:#fff;">
                <h5 class="modal-title fw-bold"><i class="bi bi-geo-alt-fill me-2"></i>Select Venue</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3 small text-muted">
                    <i class="bi bi-clock me-1"></i>
                    <?= $eventDate ? date('d M Y', strtotime($eventDate)) : '—' ?>
                    &nbsp;|&nbsp;
                    <?= $startTime ? substr($startTime, 0, 5) : '—' ?>–<?= $endTime ? substr($endTime, 0, 5) : '—' ?>
                    &nbsp;|&nbsp;<?= htmlspecialchars($session ?: '—') ?>
                </div>

                <div id="venueLoadingState" class="text-center py-4">
                    <div class="spinner-border text-warning" role="status"><span class="visually-hidden">Loading…</span></div>
                    <p class="mt-2 text-muted small">Checking venue availability…</p>
                </div>
                <div id="venueListContainer" class="row g-2 d-none"></div>
                <div id="venueErrorState" class="d-none text-center text-muted py-3">
                    <i class="bi bi-exclamation-circle fs-3"></i>
                    <p class="mt-2 small">Could not load venues. Please try again.</p>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="<?= base_url() ?>/symposiums/allocation/venue/assign" id="venueAssignForm">
                    <input type="hidden" name="symposium_event_id" value="<?= $eid ?>">
                    <input type="hidden" name="venue_id" id="selectedVenueId" value="">
                    <button type="submit" id="venueAssignBtn" class="btn btn-warning text-dark fw-bold" disabled>
                        <i class="bi bi-check-lg me-1"></i>Assign Venue
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// ─── Venue Modal JS ──────────────────────────────────────────────────────────
function openVenueModal() {
    const modal = new bootstrap.Modal(document.getElementById('venueModal'));
    modal.show();
    loadVenueAvailability();
}

function loadVenueAvailability() {
    const loading = document.getElementById('venueLoadingState');
    const list    = document.getElementById('venueListContainer');
    const err     = document.getElementById('venueErrorState');
    const btn     = document.getElementById('venueAssignBtn');

    loading.classList.remove('d-none');
    list.classList.add('d-none');
    err.classList.add('d-none');
    btn.disabled = true;
    document.getElementById('selectedVenueId').value = '';

    fetch('<?= base_url() ?>/symposiums/allocation/ajax/venue-availability?symposium_event_id=<?= $eid ?>')
        .then(r => r.json())
        .then(venues => {
            loading.classList.add('d-none');
            if (!venues || venues.length === 0) {
                err.classList.remove('d-none');
                return;
            }
            list.innerHTML = '';
            venues.forEach(v => {
                const isConflict = v.availability_status === 'conflict';
                const isCurrent  = v.is_assigned;
                const card = document.createElement('div');
                card.className = 'col-12 col-md-6';
                card.innerHTML = `
                    <div class="venue-pick-item p-3 rounded-3 border ${isConflict ? 'border-danger opacity-60' : isCurrent ? 'border-success bg-success bg-opacity-10' : 'border-secondary'}"
                         style="cursor:${isConflict ? 'not-allowed' : 'pointer'}; transition:all .15s;"
                         ${!isConflict ? `onclick="selectVenue(${v.venue_id}, '${v.venue_name.replace(/'/g, "\\'")}', this)"` : ''}>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-building text-secondary"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold" style="font-size:.9rem;">${v.venue_name}</div>
                                <div class="text-muted" style="font-size:.75rem;">
                                    ${v.venue_code ? `<span class="badge bg-light text-dark border me-1">${v.venue_code}</span>` : ''}
                                    ${v.building_name || ''}
                                    ${v.floor ? ` · Floor ${v.floor}` : ''}
                                    ${v.seating_capacity ? ` · ${v.seating_capacity} seats` : ''}
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                ${isCurrent ? '<span class="badge bg-success">Current</span>' : ''}
                                ${isConflict
                                    ? `<span class="badge bg-danger" title="Conflict: ${v.conflict_event}">Conflict</span>`
                                    : '<span class="badge bg-success-subtle text-success">Free</span>'}
                            </div>
                        </div>
                        ${isConflict ? `<div class="text-danger mt-1" style="font-size:.73rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Used by: ${v.conflict_event}</div>` : ''}
                    </div>
                `;
                list.appendChild(card);
            });
            list.classList.remove('d-none');
        })
        .catch(() => {
            loading.classList.add('d-none');
            err.classList.remove('d-none');
        });
}

function selectVenue(id, name, el) {
    document.querySelectorAll('.venue-pick-item').forEach(x => x.style.boxShadow = '');
    el.style.boxShadow = '0 0 0 3px rgba(245,158,11,.5)';
    document.getElementById('selectedVenueId').value = id;
    const btn = document.getElementById('venueAssignBtn');
    btn.disabled = false;
    btn.innerHTML = `<i class="bi bi-check-lg me-1"></i>Assign — ${name}`;
}
</script>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- Staff Availability Grid                                                  -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="card alloc-section-card mb-4">
    <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center gap-2">
        <i class="bi bi-people fs-5 text-secondary"></i>
        <strong>Staff Availability</strong>
        <div class="ms-auto d-flex gap-3 avail-legend">
            <span><?= availBadge('available') ?> Available (<?= $availableCount ?>)</span>
            <span><?= availBadge('busy') ?> Busy (<?= $busyCount ?>)</span>
            <span><?= availBadge('inactive') ?> Inactive (<?= $inactiveCount ?>)</span>
        </div>
    </div>
    <div class="card-body p-3">

        <!-- Filter Bar -->
        <div class="filter-bar">
            <i class="bi bi-funnel text-secondary"></i>
            <input type="text" id="staffSearch" class="form-control form-control-sm"
                   placeholder="Search name, employee ID…" style="max-width:200px;"
                   oninput="filterStaff()">
            <select id="deptFilter" class="form-select form-select-sm" style="max-width:160px;" onchange="filterStaff()">
                <option value="">All Departments</option>
                <?php foreach ($departments as $dept): ?>
                <option value="<?= htmlspecialchars((string)$dept['department_id']) ?>"
                    <?= ($deptFilter === (string)$dept['department_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dept['department_name'] ?? $dept['department_code']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select id="roleFilter" class="form-select form-select-sm" style="max-width:160px;" onchange="filterStaff()">
                <option value="">All Roles</option>
                <?php foreach ($assignableRoles as $r): ?>
                <option value="<?= htmlspecialchars($r) ?>" <?= ($roleFilter === $r) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($r) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select id="availFilter" class="form-select form-select-sm" style="max-width:140px;" onchange="filterStaff()">
                <option value="">All Availability</option>
                <option value="available">Available Only</option>
                <option value="busy">Busy</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary" onclick="resetFilters()">
                <i class="bi bi-x-circle me-1"></i>Clear
            </button>
        </div>

        <!-- Staff Grid -->
        <div class="row g-2" id="staffGrid">
            <?php foreach ($staffList as $staff): ?>
            <?php
                $uid    = (int) ($staff['user_id'] ?? 0);
                $status = $staff['availability_status'] ?? 'available';
                $isFic  = (bool) ($staff['is_faculty'] ?? false);
                $isJdg  = (bool) ($staff['is_judge'] ?? false);
                $conflict = $staff['conflict_detail'];

                $itemClass  = 'staff-grid-item';
                if ($isFic || $isJdg) $itemClass .= ' is-assigned';
                elseif ($status === 'busy') $itemClass .= ' is-busy';
                elseif ($status === 'inactive') $itemClass .= ' is-inactive';
            ?>
            <div class="col-12 col-sm-6 col-md-4 col-xl-3 staff-grid-col"
                 data-name="<?= strtolower($staff['full_name'] ?? '') ?>"
                 data-dept="<?= (int)($staff['department_id'] ?? 0) ?>"
                 data-role="<?= htmlspecialchars($staff['role'] ?? '') ?>"
                 data-avail="<?= $status ?>"
                 data-uid="<?= $uid ?>">
                <div class="<?= $itemClass ?>"
                     <?php if ($canManage && $status !== 'inactive' && !$isFic && !$isJdg): ?>
                     onclick="quickAssignModal(<?= $uid ?>, '<?= addslashes($staff['full_name'] ?? '') ?>', '<?= $status ?>')"
                     <?php endif; ?>>
                    <!-- Status dot -->
                    <?= availBadge($status) ?>
                    <!-- Info -->
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate" style="font-size:.84rem;">
                            <?= htmlspecialchars($staff['full_name'] ?? '') ?>
                        </div>
                        <div class="text-secondary" style="font-size:.72rem;">
                            <?= htmlspecialchars($staff['role'] ?? '') ?>
                            <?php if (!empty($staff['department_name'])): ?>
                            &bull; <?= htmlspecialchars($staff['department_name']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Right side badges -->
                    <div class="d-flex gap-1 flex-shrink-0 align-items-center">
                        <?php if ($isFic): ?>
                        <span class="badge bg-primary" style="font-size:.65rem;">FIC</span>
                        <?php endif; ?>
                        <?php if ($isJdg): ?>
                        <span class="badge bg-success" style="font-size:.65rem;">Judge</span>
                        <?php endif; ?>
                        <?php if ($status === 'busy' && $conflict): ?>
                        <i class="bi bi-exclamation-triangle text-warning" title="<?= htmlspecialchars("Busy: {$conflict['type']} for {$conflict['event_name']} ({$conflict['session']})") ?>"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Conflict tooltip -->
                <?php if ($status === 'busy' && $conflict): ?>
                <div class="mt-1 px-1">
                    <small class="text-warning" style="font-size:.71rem;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <strong><?= htmlspecialchars($conflict['type']) ?></strong> for
                        "<?= htmlspecialchars($conflict['event_name']) ?>"
                        (<?= htmlspecialchars($conflict['session']) ?>,
                        <?= htmlspecialchars($conflict['venue_name'] ?? '') ?>)
                    </small>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (empty($staffList)): ?>
            <div class="col-12 text-center py-4 text-muted">
                <i class="bi bi-people fs-2 opacity-40"></i>
                <p class="mt-2 mb-0 small">No staff found matching filters.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($canManage): ?>
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<!-- Assign Modal (Faculty or Judge) — Multi-Select                          -->
<!-- ═══════════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px; overflow:hidden;">
            <div class="modal-header" id="assignModalHeader">
                <h5 class="modal-title fw-bold" id="assignModalTitle">Assign</h5>
                <span id="selectedCountBadge" class="badge bg-white text-primary ms-2 d-none" style="font-size:.85rem;">0 selected</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">

                <!-- Search -->
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select Staff Members <small class="text-muted fw-normal">(click to select multiple)</small></label>
                    <input type="text" id="modalStaffSearch" class="form-control" placeholder="Search by name or employee ID…"
                           autocomplete="off" oninput="searchModalStaff(this.value)">
                </div>

                <!-- Staff list -->
                <div id="modalStaffResults" class="mb-3" style="max-height:320px; overflow-y:auto;"></div>

                <!-- Selected summary chips -->
                <div id="selectedChips" class="d-flex flex-wrap gap-2 mb-3"></div>

                <!-- Availability feedback (last checked) -->
                <div id="conflictPreview" class="d-none alert alert-warning rounded-3 mb-2 py-2">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <strong>Conflict Detected</strong>
                    <div id="conflictDetail" class="mt-1 small"></div>
                </div>

                <!-- Notes -->
                <div class="mb-0">
                    <label class="form-label fw-semibold">Notes (Optional)</label>
                    <input type="text" id="assignNotes" class="form-control" placeholder="Any remarks…" maxlength="255">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="assignSubmitBtn" class="btn btn-primary" disabled onclick="submitBulkAssign()">
                    <i class="bi bi-check-lg me-1"></i><span id="assignBtnLabel">Assign</span>
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Replace Modal -->
<div class="modal fade" id="replaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg" style="border-radius:18px; overflow:hidden;">
            <div class="modal-header bg-warning bg-opacity-10">
                <h5 class="modal-title fw-bold" id="replaceModalTitle">
                    <i class="bi bi-arrow-repeat me-2 text-warning"></i>Replace Assignment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="replaceModalForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="symposium_event_id" value="<?= $eid ?>">
                    <input type="hidden" name="user_id" id="replaceNewUserId">

                    <div class="alert alert-warning rounded-3 mb-3">
                        <strong id="replaceOldName"></strong> will be replaced.
                        All current assignments for this role will be removed and the new person assigned.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Staff Member</label>
                        <input type="text" id="replaceStaffSearch" class="form-control" placeholder="Search by name…"
                               oninput="searchReplaceStaff(this.value)">
                    </div>
                    <div id="replaceStaffResults" style="max-height:220px; overflow-y:auto;" class="mb-3"></div>

                    <div id="replaceConflictPreview" class="d-none alert alert-danger rounded-3 mb-3">
                        <i class="bi bi-x-circle-fill me-2"></i>
                        <strong>Conflict!</strong>
                        <div id="replaceConflictDetail" class="mt-1 small"></div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-semibold">Notes (Optional)</label>
                        <input type="text" name="notes" class="form-control" placeholder="Reason for replacement…" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="replaceSubmitBtn" class="btn btn-warning" disabled>
                        <i class="bi bi-arrow-repeat me-1"></i>Replace
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
const AJAX_BASE     = '<?= base_url() ?>';
const EVENT_ID      = <?= $eid ?>;
const CAN_MANAGE    = <?= $canManage ? 'true' : 'false' ?>;
const ALL_STAFF     = <?= json_encode(array_map(function($s) {
    return [
        'user_id'             => (int)($s['user_id'] ?? 0),
        'full_name'           => $s['full_name'] ?? '',
        'role'                => $s['role'] ?? '',
        'department_name'     => $s['department_name'] ?? '',
        'department_id'       => (int)($s['department_id'] ?? 0),
        'availability_status' => $s['availability_status'] ?? 'available',
        'availability_label'  => $s['availability_label'] ?? '',
        'conflict_detail'     => $s['conflict_detail'],
        'is_faculty'          => (bool)($s['is_faculty'] ?? false),
        'is_judge'            => (bool)($s['is_judge'] ?? false),
    ];
}, $staffList)) ?>;

let currentAssignType = 'faculty';
let currentReplaceType = 'faculty';
let selectedAssignUserIds = new Map(); // uid -> name
let selectedReplaceUserId = null;

/* ─── Grid Filtering ─── */
function filterStaff() {
    const q        = document.getElementById('staffSearch').value.toLowerCase().trim();
    const dept     = document.getElementById('deptFilter').value;
    const role     = document.getElementById('roleFilter').value.toLowerCase();
    const avail    = document.getElementById('availFilter').value;
    const cols     = document.querySelectorAll('.staff-grid-col');

    cols.forEach(col => {
        const name   = col.dataset.name  || '';
        const colDept = col.dataset.dept  || '';
        const colRole = col.dataset.role  || '';
        const colAvail = col.dataset.avail || '';

        const matchQ    = !q    || name.includes(q);
        const matchDept = !dept || colDept === dept;
        const matchRole = !role || colRole.toLowerCase() === role;
        const matchAvail = !avail || colAvail === avail;

        col.style.display = (matchQ && matchDept && matchRole && matchAvail) ? '' : 'none';
    });
}

function resetFilters() {
    document.getElementById('staffSearch').value  = '';
    document.getElementById('deptFilter').value   = '';
    document.getElementById('roleFilter').value   = '';
    document.getElementById('availFilter').value  = '';
    filterStaff();
}

/* ─── Assign Modal ─── */
function openAssignModal(type, eventId) {
    currentAssignType = type;
    selectedAssignUserIds.clear();

    document.getElementById('assignSubmitBtn').disabled = true;
    document.getElementById('assignBtnLabel').innerText = 'Assign';
    document.getElementById('modalStaffSearch').value = '';
    document.getElementById('modalStaffResults').innerHTML = '';
    document.getElementById('conflictPreview').classList.add('d-none');
    document.getElementById('assignNotes').value = '';
    
    updateSelectedChips();

    const header = document.getElementById('assignModalHeader');
    const title  = document.getElementById('assignModalTitle');

    if (type === 'faculty') {
        header.className = 'modal-header bg-primary bg-opacity-10';
        title.innerHTML  = '<i class="bi bi-person-badge me-2 text-primary"></i>Assign Faculty In-Charge';
    } else {
        header.className = 'modal-header bg-success bg-opacity-10';
        title.innerHTML  = '<i class="bi bi-star me-2 text-success"></i>Assign Judge';
    }

    // Populate results with available staff first
    renderModalStaff(ALL_STAFF, 'modalStaffResults', 'assign');
    const modalEl = document.getElementById('assignModal');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
}

function renderModalStaff(list, containerId, mode) {
    const container = document.getElementById(containerId);
    if (!list.length) {
        container.innerHTML = '<p class="text-muted small text-center py-2">No staff found.</p>';
        return;
    }

    container.innerHTML = list.slice(0, 40).map(s => {
        const busy     = s.availability_status === 'busy';
        const inactive = s.availability_status === 'inactive';
        const assigned = s.is_faculty || s.is_judge;
        const disabled = inactive || assigned;
        
        const isSelected = mode === 'assign' 
            ? selectedAssignUserIds.has(s.user_id) 
            : (!disabled && selectedReplaceUserId === s.user_id);
            
        const dotClass = assigned ? 'dot-green' : (s.availability_status === 'available' ? 'dot-green' : (busy ? 'dot-orange' : 'dot-red'));
        const conflict = s.conflict_detail;

        let conflictHint = '';
        if (assigned) {
            conflictHint = `<div class="text-success fw-bold" style="font-size:.71rem;margin-top:2px;">
                <i class="bi bi-check-circle-fill"></i> Already assigned to this event
            </div>`;
        } else if (busy && conflict) {
            conflictHint = `<div class="text-warning" style="font-size:.71rem;margin-top:2px;">
                <i class="bi bi-exclamation-triangle-fill"></i>
                ${escHtml(conflict.type)} — ${escHtml(conflict.event_name)} (${escHtml(conflict.session)})
            </div>`;
        }

        const selectedClass = isSelected ? ' is-selected' : '';
        const checkIcon = isSelected
            ? `<span class="sel-check" style="width:22px;height:22px;background:#0d6efd;color:#fff;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;">✓</span>`
            : '';

        return `<div class="staff-grid-item mb-2 ${inactive ? 'is-inactive' : ''} ${assigned ? 'is-assigned' : (busy ? 'is-busy' : '')}${selectedClass}"
                     data-uid="${s.user_id}"
                     style="cursor:${disabled ? 'not-allowed' : 'pointer'}"
                     onclick="${disabled ? '' : `selectStaff(${s.user_id}, '${escHtml(s.full_name)}', '${s.availability_status}', '${mode}')`}">
            <span class="avail-dot ${dotClass}"></span>
            <div class="flex-grow-1">
                <div class="fw-semibold" style="font-size:.85rem;">${escHtml(s.full_name)}</div>
                <div class="text-secondary" style="font-size:.73rem;">${escHtml(s.role)} ${s.department_name ? '· ' + escHtml(s.department_name) : ''}</div>
                ${conflictHint}
            </div>
            ${checkIcon}
        </div>`;
    }).join('');
}

function searchModalStaff(q) {
    const lower = q.toLowerCase();
    const filtered = ALL_STAFF.filter(s =>
        s.full_name.toLowerCase().includes(lower) ||
        (s.department_name || '').toLowerCase().includes(lower)
    );
    renderModalStaff(filtered, 'modalStaffResults', 'assign');
}


/* ─── Replace Modal ─── */
function openReplaceModal(type, eventId, oldUserId, oldName) {
    currentReplaceType = type;
    selectedReplaceUserId = null;

    document.getElementById('replaceNewUserId').value = '';
    document.getElementById('replaceSubmitBtn').disabled = true;
    document.getElementById('replaceStaffSearch').value = '';
    document.getElementById('replaceStaffResults').innerHTML = '';
    document.getElementById('replaceConflictPreview').classList.add('d-none');
    document.getElementById('replaceOldName').textContent = oldName;

    const form = document.getElementById('replaceModalForm');
    form.action = AJAX_BASE + '/symposiums/allocation/' + type + '/replace';

    renderModalStaff(ALL_STAFF.filter(s => s.user_id !== oldUserId), 'replaceStaffResults', 'replace');
    const modalEl = document.getElementById('replaceModal');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
}

function searchReplaceStaff(q) {
    const lower = q.toLowerCase();
    const filtered = ALL_STAFF.filter(s =>
        s.full_name.toLowerCase().includes(lower)
    );
    renderModalStaff(filtered, 'replaceStaffResults', 'replace');
}

document.getElementById('replaceStaffResults')?.addEventListener('click', function(e) {
    const item = e.target.closest('.staff-grid-item');
    if (!item || item.classList.contains('is-inactive')) return;
    // handled via onclick inline
});

function selectStaff(uid, name, status, mode) {
    if (mode === 'assign') {
        if (selectedAssignUserIds.has(uid)) {
            selectedAssignUserIds.delete(uid);
            
            // Remove highlight
            const el = document.querySelector(`#modalStaffResults .staff-grid-item[data-uid="${uid}"]`);
            if (el) {
                el.classList.remove('is-selected');
                const chk = el.querySelector('.sel-check');
                if (chk) chk.remove();
            }
        } else {
            selectedAssignUserIds.set(uid, name);
            
            // Add highlight
            const el = document.querySelector(`#modalStaffResults .staff-grid-item[data-uid="${uid}"]`);
            if (el) {
                el.classList.add('is-selected');
                if (!el.querySelector('.sel-check')) {
                    const chk = document.createElement('span');
                    chk.className = 'sel-check';
                    chk.style.cssText = 'width:22px;height:22px;background:#0d6efd;color:#fff;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;';
                    chk.textContent = '✓';
                    el.appendChild(chk);
                }
            }
        }

        updateSelectedChips();

        // If any selected user is busy, show conflict warning, else hide
        checkConflictsForSelected();
    } else if (mode === 'replace') {
        selectedReplaceUserId = uid;
        document.getElementById('replaceNewUserId').value = uid;

        // Visual highlight
        document.querySelectorAll('#replaceStaffResults .staff-grid-item').forEach(el => {
            el.classList.remove('is-selected');
            const chk = el.querySelector('.sel-check');
            if (chk) chk.remove();
        });
        const clicked = document.querySelector(`#replaceStaffResults .staff-grid-item[data-uid="${uid}"]`);
        if (clicked) {
            clicked.classList.add('is-selected');
            if (!clicked.querySelector('.sel-check')) {
                const chk = document.createElement('span');
                chk.className = 'sel-check';
                chk.style.cssText = 'width:22px;height:22px;background:#0d6efd;color:#fff;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;';
                chk.textContent = '✓';
                clicked.appendChild(chk);
            }
        }

        fetch(`${AJAX_BASE}/symposiums/allocation/ajax/availability?user_id=${uid}&symposium_event_id=${EVENT_ID}`)
            .then(r => r.json())
            .then(d => handleAvailResult(d, name, 'replace'));
    }
}

function handleAvailResult(data, name, mode) {
    if (mode === 'replace') {
        if (data.available) {
            document.getElementById('replaceConflictPreview').classList.add('d-none');
            document.getElementById('replaceSubmitBtn').disabled = false;
        } else {
            const c = data.conflicts?.[0];
            document.getElementById('replaceConflictPreview').classList.remove('d-none');
            document.getElementById('replaceConflictDetail').innerHTML = buildConflictHtml(name, c, data.error);
            document.getElementById('replaceSubmitBtn').disabled = true;
        }
    }
}

function updateSelectedChips() {
    const container = document.getElementById('selectedChips');
    const badge = document.getElementById('selectedCountBadge');
    const btn = document.getElementById('assignSubmitBtn');
    const label = document.getElementById('assignBtnLabel');
    const count = selectedAssignUserIds.size;

    container.innerHTML = '';
    
    if (count > 0) {
        badge.textContent = `${count} selected`;
        badge.classList.remove('d-none');
        btn.disabled = false;
        label.innerText = `Assign ${count}`;

        selectedAssignUserIds.forEach((name, uid) => {
            const chip = document.createElement('div');
            chip.className = 'badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 d-flex align-items-center gap-2 px-2 py-1';
            chip.style.fontSize = '.8rem';
            chip.innerHTML = `
                <span>${escHtml(name)}</span>
                <i class="bi bi-x-circle text-primary" style="cursor:pointer;" onclick="event.stopPropagation(); selectStaff(${uid}, '${escHtml(name)}', '', 'assign')"></i>
            `;
            container.appendChild(chip);
        });
    } else {
        badge.classList.add('d-none');
        btn.disabled = true;
        label.innerText = 'Assign';
    }
}

function checkConflictsForSelected() {
    const preview = document.getElementById('conflictPreview');
    const detail = document.getElementById('conflictDetail');
    let hasConflict = false;
    let conflictHtml = '';

    // We can use the ALL_STAFF array which already has conflict details
    selectedAssignUserIds.forEach((name, uid) => {
        const staff = ALL_STAFF.find(s => s.user_id === uid);
        if (staff && staff.availability_status === 'busy' && staff.conflict_detail) {
            hasConflict = true;
            conflictHtml += `<div class="mb-1">${buildConflictHtml(name, staff.conflict_detail)}</div>`;
        }
    });

    if (hasConflict) {
        preview.classList.remove('d-none');
        detail.innerHTML = conflictHtml;
    } else {
        preview.classList.add('d-none');
        detail.innerHTML = '';
    }
}

async function submitBulkAssign() {
    if (selectedAssignUserIds.size === 0) return;
    
    const btn = document.getElementById('assignSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Assigning...';
    
    const url = AJAX_BASE + '/symposiums/allocation/' + currentAssignType + '/bulk-assign';
    
    const formData = new URLSearchParams();
    formData.append('symposium_event_id', EVENT_ID);
    formData.append('notes', document.getElementById('assignNotes').value);
    
    for (let uid of selectedAssignUserIds.keys()) {
        formData.append('user_ids[]', uid);
    }
    
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        });
        
        const data = await res.json();
        
        if (data.success) {
            // Store flash message and reload
            sessionStorage.setItem('flash_success', data.summary);
            window.location.reload();
        } else {
            alert(data.summary || data.message || 'Failed to assign staff.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Assign ' + selectedAssignUserIds.size;
        }
    } catch (err) {
        alert('Network error while assigning.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Assign ' + selectedAssignUserIds.size;
    }
}

// Show flash message from sessionStorage if exists
document.addEventListener('DOMContentLoaded', () => {
    const flash = sessionStorage.getItem('flash_success');
    if (flash) {
        sessionStorage.removeItem('flash_success');
        // If there's a toast or alert system, use it here. For now, we can append an alert to the page or rely on PHP flash.
        // PHP flash is better, but since this is JS fetch reload, we can just show a toast or alert.
        // Assuming NexusCore has a toast function, if not, standard alert is fine.
        const msgCont = document.createElement('div');
        msgCont.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3 z-3 shadow-lg';
        msgCont.innerHTML = `
            <i class="bi bi-check-circle-fill me-2"></i>${escHtml(flash)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(msgCont);
        setTimeout(() => msgCont.remove(), 5000);
    }
});

function buildConflictHtml(name, c, errMsg) {
    if (!c) return escHtml(errMsg || 'Conflict detected.');
    const fmt = t => {
        if (!t) return '';
        let [h, m] = t.split(':');
        if (!h || !m) return t;
        h = parseInt(h, 10);
        return `${h % 12 || 12}:${m} ${h >= 12 ? 'PM' : 'AM'}`;
    };
    const timeStr = [fmt(c.start_time), fmt(c.end_time)].filter(Boolean).join(' – ');
    return `<strong>${escHtml(name)}</strong> is busy as <strong>${escHtml(c.type)}</strong> 
            for "<strong>${escHtml(c.event_name)}</strong>"<br>
            ${escHtml(c.event_date)} · ${escHtml(c.session)} · ${escHtml(timeStr)} · ${escHtml(c.venue_name || '—')}`;
}

function quickAssignModal(uid, name, status) {
    // Determine type from the last button clicked; default to faculty
    openAssignModal(currentAssignType || 'faculty', EVENT_ID);
    setTimeout(() => selectStaff(uid, name, status, 'assign'), 350);
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
