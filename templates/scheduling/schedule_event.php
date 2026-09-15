<?php
declare(strict_types=1);

/**
 * Schedule Event Form
 * First-time scheduling of a symposium event.
 * Includes AJAX real-time conflict detection.
 */

$event     = $event     ?? [];
$symposium = $symposium ?? [];
$errors    = $errors    ?? [];
$old       = $old       ?? [];
$sessions  = $sessions  ?? [];

$seId    = (int) ($event['symposium_event_id'] ?? 0);
$symId   = (int) ($event['symposium_id'] ?? 0);
$symStart = $symposium['event_start_date'] ?? '';
$symEnd   = $symposium['event_end_date']   ?? '';
?>

<style>
.conflict-indicator { transition: all .2s; }
.conflict-ok   { color: #198754; font-weight: 600; }
.conflict-warn { color: #dc3545; font-weight: 600; }
.conflict-checking { color: #0d6efd; }
.field-card { border-radius: 12px; border: none; box-shadow: 0 2px 12px rgba(0,0,0,.07); }
</style>

<!-- Breadcrumb -->
<nav class="mb-4">
    <a href="<?= base_url() ?>/symposiums/scheduling?symposium_id=<?= $symId ?>" class="text-muted small">
        <i class="bi bi-arrow-left me-1"></i>Back to Scheduling
    </a>
</nav>

<div class="row g-4">

    <!-- Form -->
    <div class="col-lg-8">
        <div class="card field-card">
            <div class="card-header bg-white border-bottom">
                <h5 class="mb-0"><i class="bi bi-clock me-2 text-primary"></i>Schedule Event</h5>
            </div>
            <div class="card-body p-4">

                <!-- Event info -->
                <div class="p-3 bg-light rounded mb-4 d-flex gap-3 align-items-start">
                    <div class="bg-primary bg-opacity-10 rounded-circle p-2">
                        <i class="bi bi-calendar-event text-primary fs-5"></i>
                    </div>
                    <div>
                        <div class="fw-bold"><?= htmlspecialchars($event['event_name'] ?? '') ?></div>
                        <small class="text-muted"><code><?= htmlspecialchars($event['event_code'] ?? '') ?></code></small>
                    </div>
                </div>

                <form method="post" action="<?= base_url() ?>/symposiums/scheduling/event" id="scheduleForm">
                    <input type="hidden" name="symposium_event_id" value="<?= $seId ?>">
                    <input type="hidden" name="symposium_id" value="<?= $symId ?>">

                    <!-- Event Date -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="event_date">
                            <i class="bi bi-calendar3 me-1 text-primary"></i>Event Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" id="event_date" name="event_date"
                               class="form-control <?= !empty($errors['event_date']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($old['event_date'] ?? '') ?>"
                               min="<?= $symStart ?>" max="<?= $symEnd ?>"
                               required>
                        <?php if (!empty($errors['event_date'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['event_date']) ?></div>
                        <?php else: ?>
                            <div class="form-text">Must be between <?= \App\Helpers\DateHelper::date($symStart) ?> and <?= \App\Helpers\DateHelper::date($symEnd) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Time Row -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="start_time">
                                <i class="bi bi-play-circle me-1 text-success"></i>Start Time <span class="text-danger">*</span>
                            </label>
                            <input type="time" id="start_time" name="start_time"
                                   class="form-control <?= !empty($errors['start_time']) ? 'is-invalid' : '' ?>"
                                   value="<?= htmlspecialchars($old['start_time'] ?? '') ?>"
                                   required>
                            <?php if (!empty($errors['start_time'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['start_time']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="end_time">
                                <i class="bi bi-stop-circle me-1 text-danger"></i>End Time <span class="text-danger">*</span>
                            </label>
                            <input type="time" id="end_time" name="end_time"
                                   class="form-control <?= !empty($errors['end_time']) ? 'is-invalid' : '' ?>"
                                   value="<?= htmlspecialchars($old['end_time'] ?? '') ?>"
                                   required>
                            <?php if (!empty($errors['end_time'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['end_time']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- AJAX Conflict Indicator -->
                    <div id="conflictIndicator" class="mb-4 d-none">
                        <div class="p-3 rounded border" id="conflictBox">
                            <span id="conflictText"></span>
                        </div>
                    </div>

                    <!-- Session -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-layout-split me-1 text-warning"></i>Session <span class="text-danger">*</span>
                        </label>
                        <div class="d-flex gap-3 flex-wrap">
                            <?php foreach ($sessions as $val => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="session"
                                           id="session_<?= $val ?>" value="<?= $val ?>"
                                           <?= ($old['session'] ?? '') === $val ? 'checked' : '' ?> required>
                                    <label class="form-check-label" for="session_<?= $val ?>">
                                        <?= htmlspecialchars($label) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!empty($errors['session'])): ?>
                            <div class="text-danger small mt-1"><?= htmlspecialchars($errors['session']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Reporting Time (optional) -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="reporting_time">
                            <i class="bi bi-alarm me-1"></i>Reporting Time <span class="text-muted fw-normal">(Optional)</span>
                        </label>
                        <input type="time" id="reporting_time" name="reporting_time"
                               class="form-control"
                               value="<?= htmlspecialchars($old['reporting_time'] ?? '') ?>">
                        <div class="form-text">When participants should report (usually 15 min before start)</div>
                    </div>

                    <div class="d-flex gap-3 align-items-center">
                        <button type="submit" class="btn btn-primary px-4" id="submitBtn">
                            <i class="bi bi-check-circle me-2"></i>Confirm Schedule
                        </button>
                        <a href="<?= base_url() ?>/symposiums/scheduling?symposium_id=<?= $symId ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                        <span id="conflictSpinnerNote" class="text-muted small d-none ms-2">
                            <i class="bi bi-info-circle me-1"></i>Waiting for conflict check…
                        </span>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- Sidebar: Symposium info -->
    <div class="col-lg-4">
        <div class="card field-card">
            <div class="card-header bg-white border-bottom">
                <h6 class="mb-0"><i class="bi bi-info-circle me-2"></i>Symposium Date Range</h6>
            </div>
            <div class="card-body">
                <div class="p-3 bg-light rounded mb-3">
                    <div class="text-muted small fw-semibold mb-1">Start Date</div>
                    <div class="fw-bold"><?= \App\Helpers\DateHelper::date($symStart) ?></div>
                </div>
                <div class="p-3 bg-light rounded">
                    <div class="text-muted small fw-semibold mb-1">End Date</div>
                    <div class="fw-bold"><?= \App\Helpers\DateHelper::date($symEnd) ?></div>
                </div>
                <div class="alert alert-info mt-3 small">
                    <i class="bi bi-lightning me-1"></i>
                    <strong>Live Conflict Check:</strong> As you select date and time, the system will automatically check for scheduling conflicts.
                </div>
            </div>
        </div>
    </div>

</div>

<script>
(function() {
    const symposiumId = <?= $symId ?>;
    const excludeId   = <?= $seId ?>;
    const ajaxUrl     = '<?= base_url() ?>/symposiums/scheduling/ajax/check';
    const symStartDate = '<?= $symStart ?>';
    const symEndDate   = '<?= $symEnd ?>';
    const FETCH_TIMEOUT_MS = 8000; // 8 seconds

    const dateField   = document.getElementById('event_date');
    const startField  = document.getElementById('start_time');
    const endField    = document.getElementById('end_time');
    const indicator   = document.getElementById('conflictIndicator');
    const conflictBox = document.getElementById('conflictBox');
    const conflictTxt = document.getElementById('conflictText');
    const submitBtn   = document.getElementById('submitBtn');
    const spinnerNote = document.getElementById('conflictSpinnerNote');

    let debounceTimer   = null;
    let currentAbort    = null;
    let conflictChecked = false;

    function showNote(show) {
        if (spinnerNote) spinnerNote.classList.toggle('d-none', !show);
    }

    function autoSelectSession() {
        const start = startField.value;
        const end   = endField.value;
        if (!start || !end) return;

        let sess = '';
        if (start < '13:00' && end > '13:45')   sess = 'Full Day';
        else if (end <= '13:45')                 sess = 'FN';
        else if (start >= '13:00')               sess = 'AN';

        if (sess) {
            const radio = document.querySelector(`input[name="session"][value="${sess}"]`);
            if (radio) radio.checked = true;
        }
    }

    function checkConflict(immediate = false) {
        const date  = dateField.value;
        const start = startField.value;
        const end   = endField.value;

        if (date) {
            const dateObj = new Date(date);
            const day = dateObj.getDay();
            if (day === 0 || day === 6) {
                const dayName = day === 0 ? 'Sunday' : 'Saturday';
                indicator.classList.remove('d-none');
                conflictBox.className = 'p-3 rounded border border-danger bg-danger bg-opacity-10';
                conflictTxt.innerHTML = `<i class="bi bi-exclamation-triangle me-2"></i><span class="conflict-warn">Events cannot be scheduled on ${dayName}.</span>`;
                submitBtn.disabled = true;
                conflictChecked = true;
                showNote(false);
                return;
            }

            if (symStartDate && date < symStartDate) {
                indicator.classList.remove('d-none');
                conflictBox.className = 'p-3 rounded border border-danger bg-danger bg-opacity-10';
                conflictTxt.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i><span class="conflict-warn">Date is before symposium start (<?= \App\Helpers\DateHelper::date($symStart) ?>)</span>';
                submitBtn.disabled = true;
                conflictChecked = true;
                showNote(false);
                return;
            }
            if (symEndDate && date > symEndDate) {
                indicator.classList.remove('d-none');
                conflictBox.className = 'p-3 rounded border border-danger bg-danger bg-opacity-10';
                conflictTxt.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i><span class="conflict-warn">Date is after symposium end (<?= \App\Helpers\DateHelper::date($symEnd) ?>)</span>';
                submitBtn.disabled = true;
                conflictChecked = true;
                showNote(false);
                return;
            }
        }

        if (!date || !start || !end) {
            indicator.classList.add('d-none');
            conflictChecked = false;
            submitBtn.disabled = false;
            showNote(false);
            return;
        }

        if (start < '10:00') {
            indicator.classList.remove('d-none');
            conflictBox.className = 'p-3 rounded border border-danger bg-danger bg-opacity-10';
            conflictTxt.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i><span class="conflict-warn">College opens at 10:00 AM. Start time must be 10:00 AM or later.</span>';
            submitBtn.disabled = true;
            conflictChecked = true;
            showNote(false);
            return;
        }
        if (end > '15:30') {
            indicator.classList.remove('d-none');
            conflictBox.className = 'p-3 rounded border border-danger bg-danger bg-opacity-10';
            conflictTxt.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i><span class="conflict-warn">College closes at 03:30 PM. End time must be 03:30 PM or earlier.</span>';
            submitBtn.disabled = true;
            conflictChecked = true;
            showNote(false);
            return;
        }
        if (end <= start) {
            indicator.classList.remove('d-none');
            conflictBox.className = 'p-3 rounded border border-danger bg-danger bg-opacity-10';
            conflictTxt.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i><span class="conflict-warn">End time must be after start time.</span>';
            submitBtn.disabled = true;
            conflictChecked = true;
            showNote(false);
            return;
        }

        conflictTxt.innerHTML = '<i class="bi bi-arrow-repeat spin me-2"></i><span class="conflict-checking">Checking for conflicts…</span>';
        conflictBox.className  = 'p-3 rounded border border-primary bg-primary bg-opacity-10';
        indicator.classList.remove('d-none');
        submitBtn.disabled  = true;
        conflictChecked     = false;
        showNote(false);

        if (currentAbort) currentAbort.abort();
        clearTimeout(debounceTimer);
        const delay = immediate ? 0 : 400;

        debounceTimer = setTimeout(() => {
            const controller = new AbortController();
            currentAbort = controller;
            const timeoutId = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS);

            const fd = new FormData();
            fd.append('symposium_id', symposiumId);
            fd.append('event_date',   date);
            fd.append('start_time',   start);
            fd.append('end_time',     end);
            fd.append('exclude_id',   excludeId);

            fetch(ajaxUrl, { method: 'POST', body: fd, signal: controller.signal })
                .then(r => {
                    clearTimeout(timeoutId);
                    if (!r.ok) throw new Error('Server error: ' + r.status);
                    return r.json();
                })
                .then(data => {
                    conflictChecked = true;
                    showNote(false);
                    if (data.available) {
                        conflictBox.className = 'p-3 rounded border border-success bg-success bg-opacity-10';
                        conflictTxt.innerHTML = '<i class="bi bi-check-circle-fill me-2 text-success"></i><span class="conflict-ok">Available — No conflicts detected.</span>';
                        submitBtn.disabled = false;
                    } else {
                        conflictBox.className = 'p-3 rounded border border-danger bg-danger bg-opacity-10';
                        const msgs = data.conflicts.map(c => c.message).join('<br>');
                        conflictTxt.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i><span class="conflict-warn">' + msgs + '</span>';
                        submitBtn.disabled = true;
                    }
                })
                .catch(err => {
                    clearTimeout(timeoutId);
                    conflictChecked = true;
                    if (err.name === 'AbortError') {
                        conflictBox.className = 'p-3 rounded border border-warning bg-warning bg-opacity-10';
                        conflictTxt.innerHTML = '<i class="bi bi-wifi-off me-2 text-warning"></i><span class="text-warning fw-semibold">Conflict check timed out. You may still submit, but verify there are no scheduling conflicts.</span>';
                        indicator.classList.remove('d-none');
                    } else {
                        indicator.classList.add('d-none');
                    }
                    submitBtn.disabled = false;
                    showNote(false);
                });
        }, delay);
    }

    // Guard form submit — wait for conflict check if still pending
    document.getElementById('scheduleForm').addEventListener('submit', function(e) {
        if (!conflictChecked) {
            e.preventDefault();
            showNote(true);
            clearTimeout(debounceTimer);
            const date  = dateField.value;
            const start = startField.value;
            const end   = endField.value;
            if (!date || !start || !end) { this.submit(); return; }
            checkConflict(true);
            const self = this;
            const poll = setInterval(() => {
                if (conflictChecked) {
                    clearInterval(poll);
                    showNote(false);
                    if (!submitBtn.disabled) self.submit();
                }
            }, 100);
        }
    });

    dateField.addEventListener('change', () => checkConflict(false));
    startField.addEventListener('change', () => checkConflict(false));
    endField.addEventListener('change',   () => checkConflict(false));

    startField.addEventListener('change', autoSelectSession);
    endField.addEventListener('change',   autoSelectSession);

    let userEditedReporting = false;
    document.getElementById('reporting_time').addEventListener('input', function() {
        userEditedReporting = true;
    });

    startField.addEventListener('change', function() {
        if (!userEditedReporting && this.value) {
            const [h, m] = this.value.split(':').map(Number);
            const total = h * 60 + m - 15;
            if (total >= 0) {
                const rh = String(Math.floor(total / 60)).padStart(2, '0');
                const rm = String(total % 60).padStart(2, '0');
                document.getElementById('reporting_time').value = rh + ':' + rm;
            }
        }
    });

    // For schedule form, fields start empty — enable submit by default
    conflictChecked = true;
    submitBtn.disabled = false;

})();
</script>
<style>.spin { animation: spin 1s linear infinite; } @keyframes spin { to { transform: rotate(360deg); } }</style>
