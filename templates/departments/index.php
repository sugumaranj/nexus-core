<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : index.php
 * Location    : templates/departments/
 * Description : Department Listing Page
 *
 * Displays all departments with actions.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */
?>

<div class="container-fluid">

    <!-- =============================================================== -->
    <!-- Page Header -->
    <!-- =============================================================== -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold mb-1">
                Department Management
            </h2>

            <p class="text-muted mb-0">
                Create, update and manage academic departments.
            </p>

        </div>

        <a
            href="<?= base_url() ?>/departments/create"
            class="btn btn-primary">

            <i class="bi bi-plus-circle"></i>

            Add Department

        </a>

    </div>

    <!-- =============================================================== -->
    <!-- Department Table -->
    <!-- =============================================================== -->

    <div class="card shadow-sm border-0">

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead class="table-light">

                        <tr>

                            <th>ID</th>

                            <th>Department Code</th>

                            <th>Department Name</th>

                            <th>Short Name</th>

                            <th>Status</th>

                            <th width="180">

                                Action

                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!empty($departments)) : ?>

                        <!--
                            Departments are already sorted by department_id in
                            DepartmentModel::getAll(), so this loop prints the
                            rows in the same 1, 2, 3... order shown in the ID
                            column.
                        -->
                        <?php foreach ($departments as $department) : ?>

                            <tr>

                                <td>

                                    <?= htmlspecialchars(
                                        (string)$department['department_id']
                                    ) ?>

                                </td>

                                <td>

                                    <span class="badge bg-primary">

                                        <?= htmlspecialchars(
                                            $department['department_code']
                                        ) ?>

                                    </span>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $department['department_name']
                                    ) ?>

                                </td>

                                <td>

                                    <?= htmlspecialchars(
                                        $department['short_name']
                                    ) ?>

                                </td>

                                <td>

                                    <?php if ((int)$department['is_active'] === 1) : ?>

                                        <span class="badge bg-success">

                                            Active

                                        </span>

                                    <?php else : ?>

                                        <span class="badge bg-danger">

                                            Inactive

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td class="text-nowrap">

                                    <!-- Edit and delete actions are grouped so both buttons stay aligned. -->
                                    <div class="d-flex align-items-center gap-2">

                                    <a
                                        href="<?= base_url() ?>/departments/edit?id=<?= $department['department_id'] ?>"
                                        class="btn btn-warning btn-sm">

                                        <i class="bi bi-pencil-square"></i>

                                        Edit

                                    </a>

                                    <form
                                        action="<?= base_url() ?>/departments/delete"
                                        method="POST"
                                        class="m-0">

                                        <input
                                            type="hidden"
                                            name="department_id"
                                            value="<?= $department['department_id'] ?>">

                                        <button
                                            type="submit"
                                            class="btn btn-danger btn-sm"
                                            onclick="return confirm('Delete this department?')">

                                            <i class="bi bi-trash"></i>

                                            Delete

                                        </button>

                                    </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else : ?>

                        <tr>

                            <td
                                colspan="6"
                                class="text-center text-muted py-5">

                                <i class="bi bi-building fs-1"></i>

                                <br><br>

                                No Departments Found

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>
