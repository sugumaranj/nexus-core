<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : edit.php
 * Location    : templates/users/
 * Description : Edit User Form
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display editable user information
 * • Preserve submitted values after validation failure
 * • Display validation errors
 * • Submit updates to UserController
 *
 * NOTE
 * -------------------------------------------------------------------------
 * Employee ID is immutable and therefore displayed as read-only.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Helpers\RoleHelper;

/*
|--------------------------------------------------------------------------
| Validation Errors
|--------------------------------------------------------------------------
*/

$errors = $errors ?? [];

/*
|--------------------------------------------------------------------------
| Business Rule Error (passed directly from controller on re-render)
|--------------------------------------------------------------------------
*/

$error = $error ?? null;

/*
|--------------------------------------------------------------------------
| User Data
|--------------------------------------------------------------------------
*/

$user = $user ?? [];

/*
|--------------------------------------------------------------------------
| Safe Defaults
|--------------------------------------------------------------------------
|
| Ensure every expected user key exists to prevent undefined index
| warnings and htmlspecialchars() null errors.
|
*/

$user = array_merge([

    'user_id'        => 0,

    'employee_id'    => '',

    'full_name'      => '',

    'email'          => '',

    'phone'          => '',

    'department_id'  => '',

    'role'           => '',

    'profile_photo'  => '',

    'signature_path' => '',

    'account_status' => 'Active',

    'created_at'     => '',

    'updated_at'     => '',

    'last_login'     => '',

], $user);

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="fw-bold mb-1">

            Edit User

        </h2>

        <p class="text-muted mb-0">

            Update the selected user's information.

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

<div class="alert alert-danger">

    <?= htmlspecialchars($error) ?>

</div>

<?php endif; ?>

<div class="card shadow-sm border-0">

    <div class="card-body">

        <form
            action="<?= base_url() ?>/users/update"
            method="post"
            enctype="multipart/form-data">

            <input
                type="hidden"
                name="user_id"
                value="<?= (int) $user['user_id'] ?>">

            <div class="row g-4">

                <!-- Employee ID — Locked (cannot be changed after creation) -->

                <div class="col-md-4">

                    <label class="form-label fw-semibold">
                        Employee ID
                        <small class="text-muted fw-normal">(locked)</small>
                    </label>

                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-upc-scan text-muted"></i>
                        </span>
                        <input
                            type="text"
                            class="form-control bg-light fw-semibold"
                            value="<?= htmlspecialchars((string) $user['employee_id']) ?>"
                            readonly
                            title="Employee ID is permanently assigned and cannot be changed.">
                        <span class="input-group-text bg-<?= htmlspecialchars(RoleHelper::getBadgeColour($user['role'])) ?> text-white" style="font-size:.8rem;">
                            <?= htmlspecialchars($user['role']) ?>
                        </span>
                    </div>

                    <div class="form-text text-muted">
                        <i class="bi bi-lock-fill me-1"></i>
                        Employee ID is permanently assigned and cannot be modified.
                    </div>

                </div>

                <!-- Full Name -->

                <div class="col-md-8">

                    <label class="form-label">

                        Full Name

                    </label>

                    <input
                        type="text"
                        name="full_name"
                        class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) $user['full_name']) ?>">

                    <?php if (isset($errors['full_name'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars($errors['full_name']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <!-- Email -->

                <div class="col-md-6">

                    <label class="form-label">

                        Email Address

                    </label>

                    <input
                        type="email"
                        name="email"
                        class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) $user['email']) ?>">

                    <?php if (isset($errors['email'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars($errors['email']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <!-- Phone -->

                <div class="col-md-6">

                    <label class="form-label">

                        Phone Number

                    </label>

                    <input
                        type="text"
                        name="phone"
                        class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string) $user['phone']) ?>">

                    <?php if (isset($errors['phone'])): ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars($errors['phone']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <!-- Department -->

                <div class="col-md-6">

                    <label class="form-label">

                        Department

                    </label>

                    <select
                        name="department_id"
                        class="form-select <?= isset($errors['department_id']) ? 'is-invalid' : '' ?>">

                        <option value="">Select Department</option>

                        <?php foreach ($departments as $department): ?>

                            <option
                                value="<?= $department['department_id'] ?>"
                                <?= ((string) ($user['department_id'] ?? '') === (string) $department['department_id']) ? 'selected' : '' ?>>

                                <?= htmlspecialchars($department['department_name']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- Role -->

                <div class="col-md-6">

                    <label class="form-label">

                        Role

                    </label>

                    <select
                        name="role"
                        class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>">

                        <?php foreach (RoleHelper::getAllRoles() as $roleOption): ?>

                            <option
                                value="<?= htmlspecialchars($roleOption) ?>"
                                <?= ((string) $user['role'] === $roleOption) ? 'selected' : '' ?>>

                                <?= htmlspecialchars($roleOption) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- Profile Photo -->

                <div class="col-md-6">

                    <label class="form-label">

                        Replace Profile Photo

                    </label>

                    <input
                        type="file"
                        name="profile_photo"
                        class="form-control">

                </div>

                    <!--
                    |--------------------------------------------------------------------------
                    | Remove Profile Photo
                    |--------------------------------------------------------------------------
                    -->

                    <div class="col-md-6 d-flex align-items-center">

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="remove_profile_photo"
                                id="remove_profile_photo"
                                value="1"
                                <?= !empty($user['remove_profile_photo']) ? 'checked' : '' ?> />

                            <label class="form-check-label" for="remove_profile_photo">

                                Remove Profile Photo

                            </label>

                        </div>

                    </div>

                <!-- Signature -->

                <div class="col-md-6">

                    <label class="form-label">

                        Replace Signature

                    </label>

                    <input
                        type="file"
                        name="signature_path"
                        class="form-control">

                </div>

                    <!--
                    |--------------------------------------------------------------------------
                    | Remove Signature
                    |--------------------------------------------------------------------------
                    -->

                    <div class="col-md-6 d-flex align-items-center">

                        <div class="form-check">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="remove_signature"
                                id="remove_signature"
                                value="1"
                                <?= !empty($user['remove_signature']) ? 'checked' : '' ?> />

                            <label class="form-check-label" for="remove_signature">

                                Remove Signature

                            </label>

                        </div>

                    </div>

                <!-- Account Status -->

                <div class="col-md-6">

                    <label class="form-label">

                        Account Status

                    </label>

                    <select
                        name="account_status"
                        class="form-select">

                        <?php foreach (['Active','Inactive','Blocked'] as $status): ?>

                            <option
                                value="<?= $status ?>"
                                <?= ((string) $user['account_status'] === $status) ? 'selected' : '' ?>>

                                <?= $status ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>

            <div class="mt-5 d-flex gap-2">

                <button
                    type="submit"
                    class="btn btn-primary">

                    <i class="bi bi-check-circle me-1"></i>

                    Update User

                </button>

                <a
                    href="<?= base_url() ?>/users"
                    class="btn btn-secondary">

                    Cancel

                </a>

            </div>

        </form>

    </div>

</div>