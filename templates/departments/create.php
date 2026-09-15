<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : create.php
 * Location    : templates/departments/
 * Description : Add Department Page
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

$errors = $errors ?? [];
$old = $old ?? [];

?>

<div class="container-fluid">

    <!-- ================================================================ -->
    <!-- Page Header -->
    <!-- ================================================================ -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Add Department
            </h2>

            <p class="text-muted mb-0">
                Create a new academic department.
            </p>

        </div>

        <a
            href="<?= base_url() ?>/departments"
            class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left"></i>

            Back

        </a>

    </div>

    <!-- ================================================================ -->
    <!-- Error Message -->
    <!-- ================================================================ -->

    <?php if (!empty($error)) : ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>

    <!-- ================================================================ -->
    <!-- Department Form -->
    <!-- ================================================================ -->

    <div class="card shadow-sm border-0">

        <div class="card-body">

            <form
                action="<?= base_url() ?>/departments"
                method="POST">

                <div class="row">

                    <!-- Department Code -->

                    <div class="col-md-6 mb-3">

                        <label class="form-label fw-semibold">

                            Department Code

                        </label>

                        <input
                            type="text"
                            name="department_code"
                            class="form-control <?= isset($errors['department_code']) ? 'is-invalid' : '' ?>"
                            value="<?= htmlspecialchars($old['department_code'] ?? '') ?>"
                            placeholder="Example: CSE"
                            maxlength="10">

                        <?php if (isset($errors['department_code'])) : ?>

                            <div class="invalid-feedback">

                                <?= htmlspecialchars($errors['department_code']) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                    <!-- Short Name -->

                    <div class="col-md-6 mb-3">

                        <label class="form-label fw-semibold">

                            Short Name

                        </label>

                        <input
                            type="text"
                            name="short_name"
                            class="form-control"
                            value="<?= htmlspecialchars($old['short_name'] ?? '') ?>"
                            placeholder="Example: Computer Science">

                    </div>

                </div>

                <!-- Department Name -->

                <div class="mb-3">

                    <label class="form-label fw-semibold">

                        Department Name

                    </label>

                    <input
                        type="text"
                        name="department_name"
                        class="form-control <?= isset($errors['department_name']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars($old['department_name'] ?? '') ?>"
                        placeholder="Enter Department Name">

                    <?php if (isset($errors['department_name'])) : ?>

                        <div class="invalid-feedback">

                            <?= htmlspecialchars($errors['department_name']) ?>

                        </div>

                    <?php endif; ?>

                </div>

                <!-- Status -->

                <div class="mb-4">

                    <label class="form-label fw-semibold">

                        Status

                    </label>

                    <select
                        name="is_active"
                        class="form-select">

                        <option value="1"
                            <?= (($old['is_active'] ?? '1') == '1') ? 'selected' : '' ?>>

                            Active

                        </option>

                        <option value="0"
                            <?= (($old['is_active'] ?? '') == '0') ? 'selected' : '' ?>>

                            Inactive

                        </option>

                    </select>

                </div>

                <!-- Buttons -->

                <div class="d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="bi bi-check-circle"></i>

                        Save Department

                    </button>

                    <a
                        href="<?= base_url() ?>/departments"
                        class="btn btn-outline-secondary">

                        Cancel

                    </a>

                </div>

            </form>

        </div>

    </div>

</div>