<?php

declare(strict_types=1);

$errors = $errors ?? [];
$department = $department ?? [];

?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Edit Department
            </h2>

            <p class="text-muted mb-0">
                Update department details and status.
            </p>

        </div>

        <a
            href="<?= base_url() ?>/departments"
            class="btn btn-outline-secondary">

            <i class="bi bi-arrow-left"></i>

            Back

        </a>

    </div>

    <?php if (!empty($error)) : ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>

        </div>

    <?php endif; ?>

    <div class="card shadow-sm border-0">

        <div class="card-body">

            <form
                action="<?= base_url() ?>/departments/update"
                method="POST">

                <input
                    type="hidden"
                    name="department_id"
                    value="<?= htmlspecialchars((string)($department['department_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="form-label fw-semibold">
                            Department Code
                        </label>

                        <input
                            type="text"
                            name="department_code"
                            class="form-control <?= isset($errors['department_code']) ? 'is-invalid' : '' ?>"
                            value="<?= htmlspecialchars((string)($department['department_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="10">

                        <?php if (isset($errors['department_code'])) : ?>

                            <div class="invalid-feedback">
                                <?= htmlspecialchars($errors['department_code'], ENT_QUOTES, 'UTF-8') ?>
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label fw-semibold">
                            Short Name
                        </label>

                        <input
                            type="text"
                            name="short_name"
                            class="form-control"
                            value="<?= htmlspecialchars((string)($department['short_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                    </div>

                </div>

                <div class="mb-3">

                    <label class="form-label fw-semibold">
                        Department Name
                    </label>

                    <input
                        type="text"
                        name="department_name"
                        class="form-control <?= isset($errors['department_name']) ? 'is-invalid' : '' ?>"
                        value="<?= htmlspecialchars((string)($department['department_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                    <?php if (isset($errors['department_name'])) : ?>

                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['department_name'], ENT_QUOTES, 'UTF-8') ?>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="mb-4">

                    <label class="form-label fw-semibold">
                        Status
                    </label>

                    <select
                        name="is_active"
                        class="form-select <?= isset($errors['is_active']) ? 'is-invalid' : '' ?>">

                        <option value="1" <?= ((string)($department['is_active'] ?? '1') === '1') ? 'selected' : '' ?>>
                            Active
                        </option>

                        <option value="0" <?= ((string)($department['is_active'] ?? '') === '0') ? 'selected' : '' ?>>
                            Inactive
                        </option>

                    </select>

                    <?php if (isset($errors['is_active'])) : ?>

                        <div class="invalid-feedback">
                            <?= htmlspecialchars($errors['is_active'], ENT_QUOTES, 'UTF-8') ?>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="bi bi-check-circle"></i>

                        Update Department

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
