<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : create.php
 * Location    : templates/users/
 * Description : Create User Form
 *
 * Features
 * -------------------------------------------------------------------------
 * • Employee ID is AUTO-GENERATED — no manual input.
 *   Selecting a Role triggers an AJAX call that returns the next ID.
 *   The actual ID is generated atomically server-side on submission.
 * • Roles are fetched from RoleHelper (not hardcoded here).
 * • Department dropdown is restricted for Staff Coordinator and
 *   Student Coordinator to PG departments only.
 * • Real-time role badge and Employee ID preview on role change.
 * • Full validation error display.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Helpers\RoleHelper;

$old    = $old    ?? [];
$errors = $errors ?? [];
$error  = $error  ?? null;

/*
|--------------------------------------------------------------------------
| Department restrictions per role (used by JS for filtering)
|--------------------------------------------------------------------------
*/

$pgOnlyRoles = ['Staff Coordinator', 'Student Coordinator'];

?>

<!-- =============================================================== -->
<!-- Page Header                                                     -->
<!-- =============================================================== -->

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="fw-bold mb-1">

            <i class="bi bi-person-plus-fill me-2 text-primary"></i>

            Create User

        </h2>

        <p class="text-muted mb-0">

            Create a new staff account for NexusCore.
            The Employee ID is generated automatically.

        </p>

    </div>

    <a
        href="<?= base_url() ?>/users"
        class="btn btn-outline-secondary">

        <i class="bi bi-arrow-left-circle me-1"></i>

        Back

    </a>

</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-x-circle-fill me-2"></i>
    <strong>Please correct the following errors.</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $field => $msg): ?>
            <li><?= htmlspecialchars($msg) ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- =============================================================== -->
<!-- Create User Form                                                 -->
<!-- =============================================================== -->

<div class="card shadow-sm border-0">

    <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4">
        <h5 class="mb-0 fw-semibold text-dark">
            <i class="bi bi-person-gear me-2 text-primary"></i>
            User Information
        </h5>
    </div>

    <div class="card-body px-4 pb-4">

        <form
            method="post"
            action="<?= base_url() ?>/users/store"
            enctype="multipart/form-data"
            id="createUserForm"
            novalidate>

            <div class="row g-4">

                <!-- ═══════════════════════════════════════════════ -->
                <!-- Role Selection (must come FIRST so EID updates) -->
                <!-- ═══════════════════════════════════════════════ -->

                <div class="col-md-6">

                    <label for="role" class="form-label fw-semibold">
                        Role <span class="text-danger">*</span>
                    </label>

                    <select
                        id="role"
                        name="role"
                        class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>"
                        required>

                        <option value="">— Select Role —</option>

                        <?php foreach ($roles as $r): ?>

                            <option
                                value="<?= htmlspecialchars($r) ?>"
                                <?= (($old['role'] ?? '') === $r) ? 'selected' : '' ?>>

                                <?= htmlspecialchars($r) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <?php if (isset($errors['role'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['role']) ?></div>
                    <?php endif; ?>

                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- Employee ID — Auto-Generated (read-only)        -->
                <!-- ═══════════════════════════════════════════════ -->

                <div class="col-md-6">

                    <label class="form-label fw-semibold">
                        Employee ID
                    </label>

                    <div class="input-group">

                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-upc-scan text-muted"></i>
                        </span>

                        <input
                            type="text"
                            id="employee_id_preview"
                            class="form-control bg-light border-start-0 <?= isset($errors['employee_id']) ? 'is-invalid' : '' ?>"
                            placeholder="Select a role to generate ID…"
                            readonly
                            value="<?= htmlspecialchars($old['employee_id'] ?? '') ?>">

                        <span
                            id="employee_id_badge"
                            class="input-group-text d-none">
                        </span>

                    </div>

                    <!-- Hidden field that is actually submitted -->
                    <input
                        type="hidden"
                        id="employee_id_hidden"
                        name="employee_id"
                        value="<?= htmlspecialchars($old['employee_id'] ?? '') ?>">

                    <div id="employee_id_status" class="form-text text-muted mt-1">
                        <i class="bi bi-info-circle me-1"></i>
                        Employee ID is generated automatically based on the selected role.
                    </div>

                    <?php if (isset($errors['employee_id'])): ?>
                        <div class="text-danger small mt-1">
                            <i class="bi bi-exclamation-circle me-1"></i>
                            <?= htmlspecialchars($errors['employee_id']) ?>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- Full Name                                       -->
                <!-- ═══════════════════════════════════════════════ -->

                <div class="col-md-8">

                    <label for="full_name" class="form-label fw-semibold">
                        Full Name <span class="text-danger">*</span>
                    </label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars($old['full_name'] ?? '') ?>"
                        placeholder="e.g. Dr. Ramesh Kumar"
                        required>

                    <?php if (isset($errors['full_name'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['full_name']) ?></div>
                    <?php endif; ?>

                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- Account Status                                  -->
                <!-- ═══════════════════════════════════════════════ -->

                <div class="col-md-4">

                    <label for="account_status" class="form-label fw-semibold">
                        Account Status <span class="text-danger">*</span>
                    </label>

                    <select
                        id="account_status"
                        name="account_status"
                        class="form-select <?= isset($errors['account_status']) ? 'is-invalid' : '' ?>"
                        required>

                        <?php foreach (['Active', 'Inactive', 'Blocked'] as $status): ?>

                            <option
                                value="<?= $status ?>"
                                <?= (($old['account_status'] ?? 'Active') === $status) ? 'selected' : '' ?>>

                                <?= $status ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <?php if (isset($errors['account_status'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['account_status']) ?></div>
                    <?php endif; ?>

                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- Email                                           -->
                <!-- ═══════════════════════════════════════════════ -->

                <div class="col-md-6">

                    <label for="email" class="form-label fw-semibold">
                        Email Address <span class="text-danger">*</span>
                    </label>

                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope-fill text-muted"></i></span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                            value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                            placeholder="staff@college.edu"
                            required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- Phone                                           -->
                <!-- ═══════════════════════════════════════════════ -->

                <div class="col-md-6">

                    <label for="phone" class="form-label fw-semibold">
                        Phone Number
                    </label>

                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-telephone-fill text-muted"></i></span>
                        <input
                            type="text"
                            id="phone"
                            name="phone"
                            class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                            value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
                            placeholder="10-digit mobile number"
                            maxlength="15">
                        <?php if (isset($errors['phone'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['phone']) ?></div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- Department                                      -->
                <!-- ═══════════════════════════════════════════════ -->

                <div class="col-md-6">

                    <label for="department_id" class="form-label fw-semibold">
                        Department
                        <span id="dept_required_star" class="text-danger d-none"> *</span>
                    </label>

                    <select
                        id="department_id"
                        name="department_id"
                        class="form-select <?= isset($errors['department_id']) ? 'is-invalid' : '' ?>">

                        <option value="">— Select Department —</option>

                        <?php foreach ($departments as $department): ?>

                            <option
                                value="<?= $department['department_id'] ?>"
                                data-code="<?= htmlspecialchars($department['department_code']) ?>"
                                data-pg="<?= in_array($department['department_code'], ['PGCS', 'PGCA']) ? '1' : '0' ?>"
                                <?= (($old['department_id'] ?? '') == $department['department_id']) ? 'selected' : '' ?>>

                                <?= htmlspecialchars($department['department_name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <div id="dept_hint" class="form-text text-muted d-none">
                        <i class="bi bi-info-circle me-1"></i>
                        This role is restricted to PG Departments (PG CS / PG CA).
                    </div>

                    <?php if (isset($errors['department_id'])): ?>
                        <div class="invalid-feedback d-block"><?= htmlspecialchars($errors['department_id']) ?></div>
                    <?php endif; ?>

                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- Password                                        -->
                <!-- ═══════════════════════════════════════════════ -->

                <div class="col-md-6">

                    <label for="password" class="form-label fw-semibold">
                        Password <span class="text-danger">*</span>
                    </label>

                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill text-muted"></i></span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                            placeholder="Minimum 8 characters"
                            required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePwd" title="Show/Hide">
                            <i class="bi bi-eye" id="togglePwdIcon"></i>
                        </button>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- Confirm Password                                -->
                <!-- ═══════════════════════════════════════════════ -->

                <div class="col-md-6">

                    <label for="confirm_password" class="form-label fw-semibold">
                        Confirm Password <span class="text-danger">*</span>
                    </label>

                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill text-muted"></i></span>
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                            placeholder="Repeat password"
                            required>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?= htmlspecialchars($errors['confirm_password']) ?></div>
                        <?php endif; ?>
                    </div>

                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- Profile Photo                                   -->
                <!-- ═══════════════════════════════════════════════ -->

                <div class="col-md-6">

                    <label for="profile_photo" class="form-label fw-semibold">
                        Profile Photo
                        <small class="text-muted fw-normal">(jpg, jpeg, png, webp — max 2 MB)</small>
                    </label>

                    <input
                        type="file"
                        id="profile_photo"
                        name="profile_photo"
                        class="form-control <?= isset($errors['profile_photo']) ? 'is-invalid' : '' ?>"
                        accept=".jpg,.jpeg,.png,.webp">

                    <?php if (isset($errors['profile_photo'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['profile_photo']) ?></div>
                    <?php endif; ?>

                </div>

                <!-- ═══════════════════════════════════════════════ -->
                <!-- Signature                                       -->
                <!-- ═══════════════════════════════════════════════ -->

                <div class="col-md-6">

                    <label for="signature_path" class="form-label fw-semibold">
                        Signature
                        <small class="text-muted fw-normal">(jpg, jpeg, png — max 2 MB)</small>
                    </label>

                    <input
                        type="file"
                        id="signature_path"
                        name="signature_path"
                        class="form-control <?= isset($errors['signature_path']) ? 'is-invalid' : '' ?>"
                        accept=".jpg,.jpeg,.png">

                    <?php if (isset($errors['signature_path'])): ?>
                        <div class="invalid-feedback"><?= htmlspecialchars($errors['signature_path']) ?></div>
                    <?php endif; ?>

                </div>

            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- Action Buttons                                      -->
            <!-- ═══════════════════════════════════════════════════ -->

            <div class="mt-5 d-flex flex-wrap gap-2">

                <button
                    type="submit"
                    id="submitBtn"
                    class="btn btn-primary">

                    <i class="bi bi-person-check me-1"></i>

                    Create User

                </button>

                <button
                    type="reset"
                    class="btn btn-secondary"
                    id="resetBtn">

                    <i class="bi bi-arrow-counterclockwise me-1"></i>

                    Reset

                </button>

                <a
                    href="<?= base_url() ?>/users"
                    class="btn btn-outline-danger">

                    <i class="bi bi-x-circle me-1"></i>

                    Cancel

                </a>

            </div>

        </form>

    </div>

</div>

<!-- =============================================================== -->
<!-- JavaScript — Employee ID Preview + Department Restriction       -->
<!-- =============================================================== -->

<script>
(function () {
    'use strict';

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    // Roles that are restricted to PG departments only.
    const PG_ONLY_ROLES = ['Staff Coordinator', 'Student Coordinator'];

    // Roles that REQUIRE a department selection.
    const DEPT_REQUIRED_ROLES = ['HOD', 'Staff', 'Staff Coordinator', 'Student Coordinator'];

    const AJAX_URL = '<?= base_url() ?>/users/generate-employee-id';

    /*
    |--------------------------------------------------------------------------
    | DOM References
    |--------------------------------------------------------------------------
    */

    const roleSelect      = document.getElementById('role');
    const deptSelect      = document.getElementById('department_id');
    const eidPreview      = document.getElementById('employee_id_preview');
    const eidHidden       = document.getElementById('employee_id_hidden');
    const eidBadge        = document.getElementById('employee_id_badge');
    const eidStatus       = document.getElementById('employee_id_status');
    const deptHint        = document.getElementById('dept_hint');
    const deptRequiredStar= document.getElementById('dept_required_star');
    const togglePwdBtn    = document.getElementById('togglePwd');
    const togglePwdIcon   = document.getElementById('togglePwdIcon');
    const passwordInput   = document.getElementById('password');
    const resetBtn        = document.getElementById('resetBtn');

    // Store original department options for filtering
    const allDeptOptions  = Array.from(deptSelect.options).map(o => ({
        value : o.value,
        text  : o.text,
        isPg  : o.dataset.pg === '1',
        code  : o.dataset.code || '',
    }));

    /*
    |--------------------------------------------------------------------------
    | Employee ID AJAX Fetch
    |--------------------------------------------------------------------------
    */

    let fetchController = null;

    function fetchEmployeeId(role) {

        if (fetchController) {
            fetchController.abort();
        }

        fetchController = new AbortController();

        eidPreview.value       = '';
        eidHidden.value        = '';
        eidBadge.className     = 'input-group-text d-none';
        eidBadge.textContent   = '';

        if (!role) {
            eidPreview.placeholder = 'Select a role to generate ID…';
            eidStatus.innerHTML    = '<i class="bi bi-info-circle me-1"></i>Employee ID is generated automatically based on the selected role.';
            return;
        }

        eidPreview.placeholder = 'Generating…';
        eidStatus.innerHTML    = '<i class="bi bi-hourglass-split me-1 text-warning"></i>Fetching next available ID…';

        fetch(`${AJAX_URL}?role=${encodeURIComponent(role)}`, {
            signal : fetchController.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        .then(res => res.json())
        .then(data => {

            if (data.error) {
                eidPreview.placeholder = 'Could not generate ID';
                eidStatus.innerHTML    = `<i class="bi bi-exclamation-triangle-fill me-1 text-danger"></i>${data.error}`;
                return;
            }

            eidPreview.value  = data.employee_id;
            eidHidden.value   = data.employee_id;

            eidBadge.className   = `input-group-text bg-${data.badge_colour ?? 'secondary'} text-white`;
            eidBadge.textContent = data.employee_id;

            eidStatus.innerHTML = `<i class="bi bi-check-circle-fill me-1 text-success"></i>
                Preview: <strong>${data.employee_id}</strong> — The actual ID is confirmed on submission.`;

        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                eidStatus.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1 text-danger"></i>Failed to fetch Employee ID.';
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Department Filtering
    |--------------------------------------------------------------------------
    */

    function filterDepartments(role) {

        const isPgOnly    = PG_ONLY_ROLES.includes(role);
        const requiresDept= DEPT_REQUIRED_ROLES.includes(role);
        const currentVal  = deptSelect.value;

        // Rebuild department options
        deptSelect.innerHTML = '';

        const placeholder   = document.createElement('option');
        placeholder.value   = '';
        placeholder.textContent = '— Select Department —';
        deptSelect.appendChild(placeholder);

        allDeptOptions.forEach(opt => {
            if (opt.value === '') return; // skip the empty placeholder already added

            if (isPgOnly && !opt.isPg) return; // filter out UG depts for PG roles

            const o        = document.createElement('option');
            o.value        = opt.value;
            o.textContent  = opt.text;
            o.dataset.code = opt.code;
            o.dataset.pg   = opt.isPg ? '1' : '0';
            if (opt.value === currentVal) o.selected = true;
            deptSelect.appendChild(o);
        });

        // Show/hide PG-only hint
        deptHint.classList.toggle('d-none', !isPgOnly);
        deptRequiredStar.classList.toggle('d-none', !requiresDept);
    }

    /*
    |--------------------------------------------------------------------------
    | Role Change Handler
    |--------------------------------------------------------------------------
    */

    roleSelect.addEventListener('change', function () {
        const role = this.value;
        fetchEmployeeId(role);
        filterDepartments(role);
    });

    /*
    |--------------------------------------------------------------------------
    | Password Show/Hide Toggle
    |--------------------------------------------------------------------------
    */

    if (togglePwdBtn) {
        togglePwdBtn.addEventListener('click', function () {
            const isText = passwordInput.type === 'text';
            passwordInput.type          = isText ? 'password' : 'text';
            togglePwdIcon.className     = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Reset Button — clear Employee ID preview
    |--------------------------------------------------------------------------
    */

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            setTimeout(() => {
                eidPreview.value       = '';
                eidHidden.value        = '';
                eidPreview.placeholder = 'Select a role to generate ID…';
                eidBadge.className     = 'input-group-text d-none';
                eidBadge.textContent   = '';
                filterDepartments('');
            }, 50);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | On Load — if old role is set (validation error re-render), refresh
    |--------------------------------------------------------------------------
    */

    const initialRole = roleSelect.value;
    if (initialRole) {
        filterDepartments(initialRole);
        // Only fetch if EID preview is empty (i.e. not already set by old input)
        if (!eidPreview.value) {
            fetchEmployeeId(initialRole);
        }
    }

}());
</script>