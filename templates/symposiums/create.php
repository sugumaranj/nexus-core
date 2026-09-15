<?php

declare(strict_types=1);

$old       = $old ?? [];
$errors    = $errors ?? [];
$error     = $error ?? null;
$departments    = $departments ?? [];
$defaultDeptIds = $defaultDeptIds ?? [];
$academicYears  = $academicYears ?? [];
$symposiumTypes = $symposiumTypes ?? [];

$currentYear = (int) date('Y');

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1"><i class="bi bi-calendar-event me-2 text-primary"></i>Create Symposium</h2>
        <p class="text-muted mb-0">Fill in basic information. Add events from the Master Library after creation, then submit for approval.</p>
    </div>
    <a href="<?= base_url() ?>/symposiums" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left-circle me-1"></i> Back
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?>
        <button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-circle-fill fs-5 mt-1"></i>
        <div>
            <strong>Please correct the following errors before submitting.</strong>
            <ul class="mb-0 mt-1 ps-3">
                <?php foreach ($errors as $msg): ?>
                    <li><?= htmlspecialchars((string) $msg, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<!-- Client-side validation summary (hidden by default, shown only on failed submit) -->
<div id="symFormSummary" class="alert alert-danger align-items-start gap-2 mb-3 d-none" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
    <div><strong>Please fix the highlighted errors before submitting.</strong></div>
</div>

<!-- Workflow hint -->
<div class="alert alert-info d-flex align-items-start gap-3 mb-4">
    <i class="bi bi-info-circle-fill fs-5 mt-1 text-info"></i>
    <div>
        <strong>How NEXUS Symposium Works</strong>
        <ol class="mb-0 mt-1 ps-3">
            <li>Create symposium with basic details</li>
            <li>Schedule events from the Master Library</li>
            <li>Submit for approval → HODs → Principal</li>
            <li>After approval, generate brochure and circular</li>
        </ol>
    </div>
</div>

<form method="post" action="<?= base_url() ?>/symposiums/store"
      enctype="multipart/form-data" data-validate="symposium" novalidate>

    <!-- ===== Basic Information ===== -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Basic Information</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">

                <!-- Title -->
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Symposium Title <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        name="title"
                        id="field-title"
                        class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) ($old['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                        placeholder="e.g. NEXUS 2027 — National Technical Symposium"
                        maxlength="150"
                        autocomplete="off">
                    <div class="field-error <?= isset($errors['title']) ? 'visible' : '' ?>"
                         data-err="title"><?= htmlspecialchars((string) ($errors['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <!-- Academic Year -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Academic Year <span class="text-danger">*</span></label>
                    <select name="academic_year" id="field-academic_year"
                            class="form-select <?= isset($errors['academic_year']) ? 'is-invalid' : '' ?>">
                        <option value="">Select Year</option>
                        <?php foreach ($academicYears as $year): ?>
                            <option
                                value="<?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?>"
                                <?= ((string) ($old['academic_year'] ?? $currentYear) === (string) $year) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $year, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-error <?= isset($errors['academic_year']) ? 'visible' : '' ?>"
                         data-err="academic_year"><?= htmlspecialchars((string) ($errors['academic_year'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="form-text">Auto-generates code: NEXUS-{YEAR}-001</div>
                </div>

                <!-- Symposium Type -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Symposium Type <span class="text-danger">*</span></label>
                    <select name="symposium_type" id="field-symposium_type"
                            class="form-select <?= isset($errors['symposium_type']) ? 'is-invalid' : '' ?>">
                        <option value="">Select Type</option>
                        <?php foreach ($symposiumTypes as $type): ?>
                            <option
                                value="<?= htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') ?>"
                                <?= (($old['symposium_type'] ?? '') === $type) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-error <?= isset($errors['symposium_type']) ? 'visible' : '' ?>"
                         data-err="symposium_type"><?= htmlspecialchars((string) ($errors['symposium_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <!-- Organizing Departments -->
                <div class="col-md-8">
                    <label class="form-label fw-semibold">
                        Organizing Departments <span class="text-danger">*</span>
                    </label>
                    <select
                        name="organizing_departments[]"
                        id="field-organizing_departments"
                        class="form-select <?= isset($errors['organizing_departments']) ? 'is-invalid' : '' ?>"
                        multiple
                        size="4">
                        <?php foreach ($departments as $dept): ?>
                            <option
                                value="<?= (int) $dept['department_id'] ?>"
                                <?= in_array((int) $dept['department_id'], array_map('intval', $defaultDeptIds), true) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $dept['department_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-error <?= isset($errors['organizing_departments']) ? 'visible' : '' ?>"
                         data-err="organizing_departments"><?= htmlspecialchars((string) ($errors['organizing_departments'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="form-text">Hold Ctrl/Cmd to select multiple. For NEXUS: select PG Computer Science &amp; PG Computer Applications.</div>
                </div>

                <!-- Description -->
                <div class="col-12">
                    <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                    <textarea
                        name="description"
                        id="field-description"
                        class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>"
                        rows="4"
                        placeholder="Describe the symposium purpose, scope and audience."><?= htmlspecialchars((string) ($old['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="field-error <?= isset($errors['description']) ? 'visible' : '' ?>"
                         data-err="description"><?= htmlspecialchars((string) ($errors['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

            </div>
        </div>
    </div>

    <!-- ===== Dates & Timeline ===== -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom">
            <h5 class="mb-0"><i class="bi bi-calendar-range me-2"></i>Dates &amp; Timeline</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Registration Opens <span class="text-danger">*</span></label>
                    <input
                        type="datetime-local"
                        name="registration_start"
                        id="field-registration_start"
                        class="form-control <?= isset($errors['registration_start']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) ($old['registration_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="field-error <?= isset($errors['registration_start']) ? 'visible' : '' ?>"
                         data-err="registration_start"><?= htmlspecialchars((string) ($errors['registration_start'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Registration Closes <span class="text-danger">*</span></label>
                    <input
                        type="datetime-local"
                        name="registration_end"
                        id="field-registration_end"
                        class="form-control <?= isset($errors['registration_end']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) ($old['registration_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="field-error <?= isset($errors['registration_end']) ? 'visible' : '' ?>"
                         data-err="registration_end"><?= htmlspecialchars((string) ($errors['registration_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Event Start Date <span class="text-danger">*</span></label>
                    <input
                        type="date"
                        name="event_start_date"
                        id="field-event_start_date"
                        class="form-control <?= isset($errors['event_start_date']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) ($old['event_start_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="field-error <?= isset($errors['event_start_date']) ? 'visible' : '' ?>"
                         data-err="event_start_date"><?= htmlspecialchars((string) ($errors['event_start_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Event End Date <span class="text-danger">*</span></label>
                    <input
                        type="date"
                        name="event_end_date"
                        id="field-event_end_date"
                        class="form-control <?= isset($errors['event_end_date']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) ($old['event_end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="field-error <?= isset($errors['event_end_date']) ? 'visible' : '' ?>"
                         data-err="event_end_date"><?= htmlspecialchars((string) ($errors['event_end_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

            </div>
        </div>
    </div>

    <!-- ===== Submit ===== -->
    <div class="d-flex gap-3 justify-content-end align-items-center">
        <a href="<?= base_url() ?>/symposiums" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary px-5" id="symSubmitBtn">
            <i class="bi bi-check-circle me-1"></i> Create Symposium
        </button>
    </div>

</form>

<style>
.field-error { font-size: .8rem; color: #dc3545; margin-top: .25rem; display: none; }
.field-error.visible { display: block; }
.form-control.is-invalid, .form-select.is-invalid { border-color: #dc3545; }
.form-control.is-valid,   .form-select.is-valid   { border-color: #198754; }
.char-counter { font-size: .75rem; color: #6c757d; float: right; }
.char-counter.near-limit  { color: #fd7e14; }
.char-counter.at-limit    { color: #dc3545; font-weight: 600; }
#symFormSummary { display: none; }
</style>

<script>
(function () {
    'use strict';

    /* ─────────────────────────────────────────────────────────────────
     * Flag: only show validation errors after the user has actually
     * attempted to submit the form. Never on page load.
     * ───────────────────────────────────────────────────────────────── */
    var formSubmitAttempted = false;

    /* ── helpers ─────────────────────────────────────────────────── */
    function q(sel)  { return document.querySelector(sel); }
    function qa(sel) { return Array.from(document.querySelectorAll(sel)); }

    /* Get the current value of a date/datetime field.
     * Works whether Flatpickr has replaced it or not.
     * Flatpickr keeps the original hidden input in sync. */
    function fieldVal(name) {
        var el = q('[name="' + name + '"]');
        return el ? el.value : '';
    }

    function setErr(field, msg) {
        var el = q('[data-err="' + field + '"]');
        if (!el) return;
        el.textContent = msg;
        el.classList.toggle('visible', !!msg);
        var inp = q('[name="' + field + '"], [name="' + field + '[]"]');
        if (inp) {
            inp.classList.toggle('is-invalid', !!msg);
            inp.classList.toggle('is-valid',   !msg && inp.value !== '');
        }
    }

    /* ── char counter ────────────────────────────────────────────── */
    function attachCounter(name, limit) {
        var el   = q('[name="' + name + '"]');
        var wrap = el ? el.closest('.col-12, .col-md-8, .col-md-4') : null;
        if (!el || !wrap) return;
        var counter = document.createElement('span');
        counter.className = 'char-counter';
        counter.id = 'cc-' + name;
        var label = wrap.querySelector('label');
        if (label) label.appendChild(counter);
        function update() {
            var len = el.value.length;
            counter.textContent = len + ' / ' + limit;
            counter.className = 'char-counter' +
                (len >= limit ? ' at-limit' : (len >= limit * 0.9 ? ' near-limit' : ''));
        }
        el.addEventListener('input', update);
        update();
    }

    /* ── single-field validators (return error string or '') ───── */
    function vTitle(v) {
        v = v.trim();
        if (!v)            return 'Title is required.';
        if (v.length > 150) return 'Title cannot exceed 150 characters.';
        return '';
    }

    function vAcademicYear(v) {
        v = v.trim();
        if (!v) return 'Academic year is required.';
        if (!/^\d{4}$/.test(v)) return 'Academic year must be a valid 4-digit year.';
        var yr = parseInt(v, 10), cur = new Date().getFullYear();
        if (yr < 2000 || yr > cur + 10) return 'Academic year must be between 2000 and ' + (cur + 10) + '.';
        return '';
    }

    function vType(v) {
        var allowed = ['Intra Department', 'Inter Department'];
        if (!v || allowed.indexOf(v) === -1) return 'Please select a valid symposium type.';
        return '';
    }

    function vDepts() {
        var sel = q('select[name="organizing_departments[]"]');
        if (!sel) return '';
        var chosen = Array.from(sel.selectedOptions);
        if (chosen.length === 0) return 'Please select at least one organizing department.';
        return '';
    }

    function vDescription(v) {
        v = v.trim();
        if (!v)            return 'Description is required.';
        if (v.length > 5000) return 'Description cannot exceed 5000 characters.';
        return '';
    }

    function isValidDT(s) {
        if (!s) return false;
        return !isNaN(Date.parse(s));
    }
    function isValidDate(s) {
        if (!s || !/^\d{4}-\d{2}-\d{2}$/.test(s)) return false;
        return !isNaN(Date.parse(s));
    }
    function dtToDate(s) { return s ? s.substring(0, 10) : ''; }

    function vRegistrationStart(v, regEnd) {
        if (!v) return 'Registration start date is required.';
        if (!isValidDT(v)) return 'Registration start must be a valid date and time.';
        return '';
    }

    function vRegistrationEnd(v, regStart) {
        if (!v) return 'Registration end date is required.';
        if (!isValidDT(v)) return 'Registration end must be a valid date and time.';
        if (regStart && isValidDT(regStart) && new Date(v) <= new Date(regStart))
            return 'Registration end must be after registration start.';
        return '';
    }

    function vEventStart(v, regEnd) {
        if (!v) return 'Event start date is required.';
        if (!isValidDate(v)) return 'Event start date must be in YYYY-MM-DD format.';
        if (regEnd && isValidDT(regEnd)) {
            var regEndDate = dtToDate(regEnd);
            if (regEndDate > v)
                return 'Event start must be on or after the registration end date.';
        }
        return '';
    }

    function vEventEnd(v, evStart) {
        if (!v) return 'Event end date is required.';
        if (!isValidDate(v)) return 'Event end date must be in YYYY-MM-DD format.';
        if (evStart && isValidDate(evStart) && evStart > v)
            return 'Event end date must be on or after event start date.';
        return '';
    }

    /* ── cross-field date re-validation ─────────────────────────
     * Only run after a genuine user interaction (Flatpickr onChange). */
    function revalidateDates() {
        if (!formSubmitAttempted) return; // silence during page load
        var regStart = fieldVal('registration_start');
        var regEnd   = fieldVal('registration_end');
        var evStart  = fieldVal('event_start_date');
        var evEnd    = fieldVal('event_end_date');

        setErr('registration_start', vRegistrationStart(regStart, regEnd));
        setErr('registration_end',   vRegistrationEnd(regEnd, regStart));
        setErr('event_start_date',   vEventStart(evStart, regEnd));
        setErr('event_end_date',     vEventEnd(evEnd, evStart));
    }

    /* ── validate all fields, return true if clean ──────────── */
    function validateAll(showErrors) {
        function v(field, err) {
            if (showErrors) setErr(field, err);
            return err === '';
        }

        var title    = fieldVal('title');
        var acYear   = fieldVal('academic_year');
        var symType  = fieldVal('symposium_type');
        var desc     = fieldVal('description');
        var regStart = fieldVal('registration_start');
        var regEnd   = fieldVal('registration_end');
        var evStart  = fieldVal('event_start_date');
        var evEnd    = fieldVal('event_end_date');

        var ok = true;
        ok = v('title',                  vTitle(title))                        && ok;
        ok = v('academic_year',          vAcademicYear(acYear))                && ok;
        ok = v('symposium_type',         vType(symType))                       && ok;
        ok = v('organizing_departments', vDepts())                             && ok;
        ok = v('description',            vDescription(desc))                   && ok;
        ok = v('registration_start',     vRegistrationStart(regStart, regEnd)) && ok;
        ok = v('registration_end',       vRegistrationEnd(regEnd, regStart))   && ok;
        ok = v('event_start_date',       vEventStart(evStart, regEnd))         && ok;
        ok = v('event_end_date',         vEventEnd(evEnd, evStart))            && ok;

        var banner = q('#symFormSummary');
        if (banner) {
            if (!ok && showErrors) {
                banner.classList.remove('d-none');
                banner.classList.add('d-flex');
            } else {
                banner.classList.add('d-none');
                banner.classList.remove('d-flex');
            }
        }
        return ok;
    }

    /* ── attach live validation on blur/input ────────────────── */
    function attachLive(name, fn) {
        var el = q('[name="' + name + '"]');
        if (!el) return;
        el.addEventListener('blur', function() {
            if (formSubmitAttempted) setErr(name, fn());
        });
        el.addEventListener('input', function() {
            if (el.classList.contains('is-invalid')) setErr(name, fn());
        });
        el.addEventListener('change', function() {
            if (formSubmitAttempted) setErr(name, fn());
        });
    }

    /* ── init ────────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        // Absolutely guarantee banner is hidden on page load
        var bannerEl = q('#symFormSummary');
        if (bannerEl) {
            bannerEl.classList.add('d-none');
            bannerEl.classList.remove('d-flex');
        }

        attachCounter('title',       150);
        attachCounter('description', 5000);

        // Live per-field validation (non-date)
        attachLive('title',          function() { return vTitle(fieldVal('title')); });
        attachLive('academic_year',  function() { return vAcademicYear(fieldVal('academic_year')); });
        attachLive('symposium_type', function() { return vType(fieldVal('symposium_type')); });
        attachLive('description',    function() { return vDescription(fieldVal('description')); });

        // Dept multi-select live check
        var deptSel = q('select[name="organizing_departments[]"]');
        if (deptSel) {
            deptSel.addEventListener('change', function() {
                if (formSubmitAttempted) setErr('organizing_departments', vDepts());
            });
        }

        /* Date fields: re-initialize Flatpickr on these specific inputs
         * with an onChange callback so we catch user changes accurately.
         * The global Flatpickr in the layout fires synthetic 'change' events
         * during init — by re-initializing here with callbacks, we control
         * exactly when validation fires. */
        var dateFields = [
            { name: 'registration_start', opts: { enableTime: true, dateFormat: 'Y-m-d H:i', altInput: true, altFormat: 'd/m/Y h:i K', allowInput: true } },
            { name: 'registration_end',   opts: { enableTime: true, dateFormat: 'Y-m-d H:i', altInput: true, altFormat: 'd/m/Y h:i K', allowInput: true } },
            { name: 'event_start_date',   opts: { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y', allowInput: true } },
            { name: 'event_end_date',     opts: { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y', allowInput: true } },
        ];

        if (typeof flatpickr !== 'undefined') {
            dateFields.forEach(function(df) {
                var el = q('[name="' + df.name + '"]');
                if (!el) return;
                // Destroy any existing Flatpickr instance before re-init
                if (el._flatpickr) el._flatpickr.destroy();
                flatpickr(el, Object.assign({}, df.opts, {
                    onChange: function() {
                        // Only re-validate dates if the user has tried to submit
                        if (formSubmitAttempted) revalidateDates();
                    }
                }));
            });
        }

        // Form submit guard
        var forms = qa('form[data-validate="symposium"]');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                formSubmitAttempted = true;
                if (!validateAll(true)) {
                    e.preventDefault();
                    var firstErr = q('.field-error.visible');
                    if (firstErr) {
                        firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
        });

        // If PHP already returned server-side errors, mark fields visually
        qa('.is-invalid').forEach(function(el) {
            if (el.name) el.classList.add('is-invalid');
        });
    });
})();
</script>
