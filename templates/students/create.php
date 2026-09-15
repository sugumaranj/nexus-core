<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : create.php
 * Location    : templates/students/
 * Description : Create Student Form
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display student creation form
 * • Display validation errors
 * • Preserve previously entered values
 * • Submit data to StudentController
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

$old = $old ?? [];

$errors = $errors ?? [];

$error = $error ?? null;

$departments = $departments ?? [];

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="fw-bold mb-1">

            Create Student

        </h2>

        <p class="text-muted mb-0">

            Add a new student profile to NexusCore EMS.

        </p>

    </div>

    <a
        href="<?= base_url() ?>/students"
        class="btn btn-outline-secondary">

        <i class="bi bi-arrow-left-circle me-1"></i>

        Back

    </a>

</div>

<?php if ($error): ?>

<div class="alert alert-danger">

    <?= htmlspecialchars((string) $error) ?>

</div>

<?php endif; ?>

<?php if (!empty($errors)): ?>

<div class="alert alert-danger">

    <strong>

        Please correct the following errors.

    </strong>

</div>

<?php endif; ?>

<p class="text-muted small mb-3">
    Fields marked with <span class="text-danger fw-bold">*</span> are mandatory.
</p>

<div class="card shadow-sm border-0">

    <div class="card-body">

        <form
            method="post"
            action="<?= base_url() ?>/students/store"
            enctype="multipart/form-data">

            <div class="row g-4">

                <div class="col-md-6">

                    <label class="form-label">

                        Profile Photo
                        <span class="text-muted small">(optional)</span>

                    </label>

                    <input
                        type="file"
                        name="profile_photo"
                        class="form-control <?= isset($errors['profile_photo']) ? 'is-invalid' : '' ?>">

                    <?php if (isset($errors['profile_photo'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['profile_photo']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Register Number <span class="text-danger">*</span>

                    </label>

                    <input
                        type="text"
                        name="register_number"
                        class="form-control <?= isset($errors['register_number']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) ($old['register_number'] ?? '')) ?>">

                    <?php if (isset($errors['register_number'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['register_number']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Roll Number
                        <span class="text-muted small">(optional)</span>

                    </label>

                    <input
                        type="text"
                        name="roll_number"
                        class="form-control <?= isset($errors['roll_number']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) ($old['roll_number'] ?? '')) ?>">

                    <?php if (isset($errors['roll_number'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['roll_number']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Full Name <span class="text-danger">*</span>

                    </label>

                    <input
                        type="text"
                        name="full_name"
                        class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) ($old['full_name'] ?? '')) ?>">

                    <?php if (isset($errors['full_name'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['full_name']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Email Address <span class="text-danger">*</span>

                    </label>

                    <input
                        type="email"
                        name="email"
                        class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) ($old['email'] ?? '')) ?>">

                    <?php if (isset($errors['email'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['email']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Phone Number <span class="text-danger">*</span>

                    </label>

                    <input
                        type="text"
                        id="phone_field"
                        name="phone"
                        class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) ($old['phone'] ?? '')) ?>">

                    <?php if (isset($errors['phone'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['phone']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Gender <span class="text-danger">*</span>

                    </label>

                    <select
                        name="gender"
                        class="form-select <?= isset($errors['gender']) ? 'is-invalid' : '' ?>">

                        <option value="">Select Gender</option>

                        <?php foreach (['Male', 'Female'] as $gender): ?>

                            <option
                                value="<?= $gender ?>"
                                <?= (($old['gender'] ?? '') === $gender) ? 'selected' : '' ?>>

                                <?= $gender ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <?php if (isset($errors['gender'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['gender']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Date of Birth <small class="text-muted">(Day, Month, Year)</small>
                        <span class="text-muted small">(optional)</span>

                    </label>

                    <?php
                    $oldDob = trim((string)($old['dob'] ?? ''));
                    $oldYear = (string)($old['dob_year'] ?? '');
                    $oldMonth = (string)($old['dob_month'] ?? '');
                    $oldDay = (string)($old['dob_day'] ?? '');
                    if ($oldDob !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $oldDob, $m)) {
                        $oldYear = $m[1]; $oldMonth = $m[2]; $oldDay = $m[3];
                    }
                    ?>
                    <div class="row g-2">
                        <div class="col-4">
                            <select name="dob_day" class="form-select <?= isset($errors['dob']) ? 'is-invalid' : '' ?>">
                                <option value="">Day</option>
                                <?php for ($d = 1; $d <= 31; $d++): $dStr = sprintf('%02d', $d); ?>
                                    <option value="<?= $dStr ?>" <?= $oldDay === $dStr ? 'selected' : '' ?>><?= $dStr ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <select name="dob_month" class="form-select <?= isset($errors['dob']) ? 'is-invalid' : '' ?>">
                                <option value="">Month</option>
                                <?php
                                $months = [
                                    '01' => 'Jan (01)', '02' => 'Feb (02)', '03' => 'Mar (03)', '04' => 'Apr (04)',
                                    '05' => 'May (05)', '06' => 'Jun (06)', '07' => 'Jul (07)', '08' => 'Aug (08)',
                                    '09' => 'Sep (09)', '10' => 'Oct (10)', '11' => 'Nov (11)', '12' => 'Dec (12)'
                                ];
                                foreach ($months as $mVal => $mLabel): ?>
                                    <option value="<?= $mVal ?>" <?= $oldMonth === $mVal ? 'selected' : '' ?>><?= $mLabel ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <select name="dob_year" class="form-select <?= isset($errors['dob']) ? 'is-invalid' : '' ?>">
                                <option value="">Year</option>
                                <?php
                                $curY = (int) date('Y');
                                for ($y = $curY; $y >= 1970; $y--): $yStr = (string)$y; ?>
                                    <option value="<?= $yStr ?>" <?= $oldYear === $yStr ? 'selected' : '' ?>><?= $yStr ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <?php if (isset($errors['dob'])): ?>

                        <div class="text-danger small mt-1">

                            <?= htmlspecialchars((string) $errors['dob']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Department <span class="text-danger">*</span>

                    </label>

                    <select
                        name="department_id"
                        class="form-select <?= isset($errors['department_id']) ? 'is-invalid' : '' ?>">

                        <option value="">Select Department</option>

                        <?php foreach ($departments as $department): ?>

                            <option
                                value="<?= (int) $department['department_id'] ?>"
                                <?= (($old['department_id'] ?? '') == $department['department_id']) ? 'selected' : '' ?>>

                                <?= htmlspecialchars((string) $department['department_name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <?php if (isset($errors['department_id'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['department_id']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Academic Year <span class="text-danger">*</span>

                    </label>

                    <select
                        name="academic_year"
                        class="form-select <?= isset($errors['academic_year']) ? 'is-invalid' : '' ?>">

                        <option value="">Select Year</option>

                        <option value="1" <?= (($old['academic_year'] ?? '') === '1') ? 'selected' : '' ?>>
                            First Year
                        </option>

                        <option value="2" <?= (($old['academic_year'] ?? '') === '2') ? 'selected' : '' ?>>
                            Second Year
                        </option>

                        <option value="3" <?= (($old['academic_year'] ?? '') === '3') ? 'selected' : '' ?>>
                            Third Year
                        </option>

                    </select>

                    <?php if (isset($errors['academic_year'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['academic_year']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Semester <span class="text-danger">*</span>

                    </label>

                    <select
                        name="semester"
                        class="form-select <?= isset($errors['semester']) ? 'is-invalid' : '' ?>">

                        <option value="">Select Semester</option>

                        <?php for ($semester = 1; $semester <= 6; $semester++): ?>

                            <option
                                value="<?= $semester ?>"
                                <?= (($old['semester'] ?? '') === (string) $semester) ? 'selected' : '' ?>>

                                <?= htmlspecialchars((string) semester_label($semester), ENT_QUOTES, 'UTF-8') ?>

                            </option>

                        <?php endfor; ?>

                    </select>

                    <?php if (isset($errors['semester'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['semester']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Section
                        <span class="text-muted small">(optional)</span>

                    </label>

                    <input
                        type="text"
                        name="section"
                        class="form-control <?= isset($errors['section']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) ($old['section'] ?? '')) ?>">

                    <?php if (isset($errors['section'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['section']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Admission Year <span class="text-danger">*</span>

                    </label>

                    <input
                        type="text"
                        id="admission_year"
                        name="admission_year"
                        class="form-control <?= isset($errors['admission_year']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) ($old['admission_year'] ?? '')) ?>"
                        placeholder="e.g. 2024"
                        maxlength="4">

                    <?php if (isset($errors['admission_year'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['admission_year']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Graduation Year
                        <span class="text-muted small">(auto-filled from Admission Year)</span>

                    </label>

                    <input
                        type="text"
                        id="graduation_year"
                        name="graduation_year"
                        class="form-control <?= isset($errors['graduation_year']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) ($old['graduation_year'] ?? '')) ?>"
                        placeholder="e.g. 2027"
                        maxlength="4">

                    <?php if (isset($errors['graduation_year'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['graduation_year']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-12">

                    <!-- ── Auto-generated Password ─────────────────────────────── -->
                    <label class="form-label d-flex align-items-center gap-2">
                        Password <span class="text-danger">*</span>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1" style="font-size:.72rem;">
                            <i class="bi bi-magic me-1"></i>Auto-generated
                        </span>
                    </label>

                    <p class="text-muted small mb-2">
                        Password is auto-generated as:
                        <strong>last 4 digits of phone</strong> + <strong>admission year</strong>
                        &nbsp;(e.g. phone <code>6385964558</code> + year <code>2024</code> → <code>45582024</code>).
                        Fill in Phone Number and Admission Year first.
                    </p>

                    <!-- Preview box -->
                    <div id="pwd-auto-box">

                        <div class="input-group mb-1">
                            <span class="input-group-text bg-body-secondary text-muted">
                                <i class="bi bi-key-fill"></i>
                            </span>
                            <input
                                type="text"
                                id="pwd_preview"
                                class="form-control font-monospace fw-semibold bg-body-secondary <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                placeholder="Enter phone &amp; admission year above…"
                                readonly>
                            <button type="button" id="pwd_toggle_vis" class="btn btn-outline-secondary" title="Show / hide">
                                <i class="bi bi-eye" id="pwd_eye_icon"></i>
                            </button>
                            <button type="button" id="pwd_copy_btn" class="btn btn-outline-primary" title="Copy to clipboard">
                                <i class="bi bi-clipboard" id="pwd_copy_icon"></i>
                            </button>
                        </div>

                        <?php if (isset($errors['password'])): ?>
                            <div class="text-danger small mt-1">
                                <?= htmlspecialchars((string) $errors['password']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="text-danger small mt-1">
                                <?= htmlspecialchars((string) $errors['confirm_password']) ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-1">
                            <a href="#" id="switch_to_manual" class="text-muted small">
                                <i class="bi bi-pencil-square me-1"></i>Set password manually instead
                            </a>
                        </div>

                    </div>

                    <!-- Hidden actual fields submitted to server -->
                    <input type="hidden" id="password_hidden"         name="password">
                    <input type="hidden" id="confirm_password_hidden" name="confirm_password">

                    <!-- Manual override box (hidden by default) -->
                    <div id="pwd-manual-box" style="display:none;">

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label small text-muted">Password</label>
                                <input
                                    type="password"
                                    id="password_manual"
                                    class="form-control"
                                    placeholder="Min. 8 characters">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small text-muted">Confirm Password</label>
                                <input
                                    type="password"
                                    id="confirm_password_manual"
                                    class="form-control"
                                    placeholder="Repeat password">
                                <div id="pwd_match_msg" class="small mt-1" style="display:none;"></div>
                            </div>

                        </div>

                        <div class="mt-2">
                            <a href="#" id="switch_to_auto" class="text-muted small">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Switch back to auto-generate
                            </a>
                        </div>

                    </div>
                    <!-- ── / Auto-generated Password ────────────────────────────── -->

                </div>

                <div class="col-md-6">

                    <label class="form-label">

                        Account Status <span class="text-danger">*</span>

                    </label>

                    <select
                        name="account_status"
                        class="form-select <?= isset($errors['account_status']) ? 'is-invalid' : '' ?>">

                        <?php foreach (['Active', 'Inactive'] as $statusOption): ?>

                            <option
                                value="<?= $statusOption ?>"
                                <?= (($old['account_status'] ?? 'Active') === $statusOption) ? 'selected' : '' ?>>

                                <?= $statusOption ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <?php if (isset($errors['account_status'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars((string) $errors['account_status']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-12 d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary">

                        Create Student

                    </button>

                    <button
                        type="reset"
                        class="btn btn-outline-secondary">

                        Reset

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

<script>
(function () {

    /* ── Graduation Year auto-fill ───────────────────────── */
    const admissionInput  = document.getElementById('admission_year');
    const graduationInput = document.getElementById('graduation_year');

    if (admissionInput && graduationInput) {
        function autoFillGraduation() {
            const val = admissionInput.value.trim();
            if (/^\d{4}$/.test(val)) {
                if (graduationInput.dataset.autoFilled === '1' || graduationInput.value === '') {
                    graduationInput.value = String(parseInt(val, 10) + 3);
                    graduationInput.dataset.autoFilled = '1';
                }
            } else {
                if (graduationInput.dataset.autoFilled === '1') {
                    graduationInput.value = '';
                    graduationInput.dataset.autoFilled = '0';
                }
            }
        }

        graduationInput.addEventListener('input', function () { this.dataset.autoFilled = '0'; });
        admissionInput.addEventListener('input',  autoFillGraduation);
        admissionInput.addEventListener('change', autoFillGraduation);
        autoFillGraduation();
    }

    /* ── Auto-generated Password ─────────────────────────── */
    const phoneInput       = document.getElementById('phone_field');
    const pwdPreview       = document.getElementById('pwd_preview');
    const pwdHidden        = document.getElementById('password_hidden');
    const cpwdHidden       = document.getElementById('confirm_password_hidden');
    const pwdAutoBox       = document.getElementById('pwd-auto-box');
    const pwdManualBox     = document.getElementById('pwd-manual-box');
    const pwdToggleVis     = document.getElementById('pwd_toggle_vis');
    const pwdEyeIcon       = document.getElementById('pwd_eye_icon');
    const pwdCopyBtn       = document.getElementById('pwd_copy_btn');
    const pwdCopyIcon      = document.getElementById('pwd_copy_icon');
    const switchToManual   = document.getElementById('switch_to_manual');
    const switchToAuto     = document.getElementById('switch_to_auto');
    const passwordManual   = document.getElementById('password_manual');
    const cpwdManual       = document.getElementById('confirm_password_manual');
    const pwdMatchMsg      = document.getElementById('pwd_match_msg');

    let isAutoMode  = true;
    let pwdMasked   = true; // preview shows dots by default

    /* Compute generated password */
    function buildPassword() {
        const phone = (phoneInput ? phoneInput.value.replace(/\D/g, '') : '');
        const year  = (admissionInput ? admissionInput.value.trim() : '');
        if (phone.length >= 4 && /^\d{4}$/.test(year)) {
            return phone.slice(-4) + year;
        }
        return '';
    }

    /* Update preview + hidden fields */
    function syncAutoPassword() {
        if (!isAutoMode) return;
        const pwd = buildPassword();
        pwdPreview.placeholder = pwd === ''
            ? 'Enter phone & admission year above…'
            : '';
        // Show masked or plain
        pwdPreview.value = pwd === '' ? '' : (pwdMasked ? '•'.repeat(pwd.length) : pwd);
        pwdPreview.dataset.real = pwd;
        pwdHidden.value  = pwd;
        cpwdHidden.value = pwd;
    }

    /* Show / hide toggle */
    if (pwdToggleVis) {
        pwdToggleVis.addEventListener('click', function () {
            pwdMasked = !pwdMasked;
            const real = pwdPreview.dataset.real || '';
            pwdPreview.value = real === '' ? '' : (pwdMasked ? '•'.repeat(real.length) : real);
            pwdEyeIcon.classList.toggle('bi-eye',      pwdMasked);
            pwdEyeIcon.classList.toggle('bi-eye-slash', !pwdMasked);
        });
    }

    /* Copy to clipboard */
    if (pwdCopyBtn) {
        pwdCopyBtn.addEventListener('click', function () {
            const real = pwdPreview.dataset.real || '';
            if (!real) return;
            navigator.clipboard.writeText(real).then(function () {
                pwdCopyIcon.classList.replace('bi-clipboard', 'bi-clipboard-check');
                pwdCopyBtn.classList.replace('btn-outline-primary', 'btn-success');
                setTimeout(function () {
                    pwdCopyIcon.classList.replace('bi-clipboard-check', 'bi-clipboard');
                    pwdCopyBtn.classList.replace('btn-success', 'btn-outline-primary');
                }, 2000);
            });
        });
    }

    /* Switch to manual mode */
    if (switchToManual) {
        switchToManual.addEventListener('click', function (e) {
            e.preventDefault();
            isAutoMode = false;
            pwdAutoBox.style.display   = 'none';
            pwdManualBox.style.display = 'block';
            pwdHidden.value  = '';
            cpwdHidden.value = '';
        });
    }

    /* Switch back to auto mode */
    if (switchToAuto) {
        switchToAuto.addEventListener('click', function (e) {
            e.preventDefault();
            isAutoMode = true;
            pwdManualBox.style.display = 'none';
            pwdAutoBox.style.display   = 'block';
            syncAutoPassword();
        });
    }

    /* Sync manual inputs into hidden fields */
    function syncManual() {
        if (isAutoMode) return;
        const p  = passwordManual  ? passwordManual.value  : '';
        const cp = cpwdManual      ? cpwdManual.value      : '';
        pwdHidden.value  = p;
        cpwdHidden.value = cp;

        // Live match feedback
        if (pwdMatchMsg) {
            if (cp === '') {
                pwdMatchMsg.style.display = 'none';
            } else if (p === cp) {
                pwdMatchMsg.style.display = 'block';
                pwdMatchMsg.className     = 'small mt-1 text-success';
                pwdMatchMsg.textContent   = '✓ Passwords match';
            } else {
                pwdMatchMsg.style.display = 'block';
                pwdMatchMsg.className     = 'small mt-1 text-danger';
                pwdMatchMsg.textContent   = '✗ Passwords do not match';
            }
        }
    }

    if (passwordManual)  passwordManual.addEventListener('input',  syncManual);
    if (cpwdManual)      cpwdManual.addEventListener('input',      syncManual);

    /* Re-compute whenever phone or admission year changes */
    if (phoneInput)    phoneInput.addEventListener('input',    syncAutoPassword);
    if (admissionInput) admissionInput.addEventListener('input', syncAutoPassword);

    /* Initial sync */
    syncAutoPassword();

})();
</script>
