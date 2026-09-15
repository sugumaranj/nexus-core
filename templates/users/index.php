<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : index.php
 * Location    : templates/users/
 * Description : User Management Dashboard
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display all users
 * • Display flash messages
 * • Display user statistics
 * • Search and filtering UI
 * • CRUD action buttons
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

use App\Helpers\RoleHelper;

/*
|--------------------------------------------------------------------------
| Search Filters
|--------------------------------------------------------------------------
*/

$search = $search ?? '';

$role = $role ?? '';

$status = $status ?? '';

/*
|--------------------------------------------------------------------------
| User Statistics
|--------------------------------------------------------------------------
*/

$totalUsers = count($users);

$activeUsers = count(
    array_filter(
        $users,
        fn ($user) => $user['account_status'] === 'Active'
    )
);

$inactiveUsers = count(
    array_filter(
        $users,
        fn ($user) => $user['account_status'] === 'Inactive'
    )
);

$blockedUsers = count(
    array_filter(
        $users,
        fn ($user) => $user['account_status'] === 'Blocked'
    )
);

?>

<!-- =============================================================== -->
<!-- Page Header -->
<!-- =============================================================== -->

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="fw-bold mb-1">

            User Management

        </h2>

        <p class="text-muted mb-0">

            Manage administrators, principals, HODs and staff coordinators.

        </p>

    </div>

    <a
        href="<?= base_url() ?>/users/create"
        class="btn btn-primary">

        <i class="bi bi-person-plus-fill me-1"></i>

        Add User

    </a>

</div>



<!-- =============================================================== -->
<!-- Statistics -->
<!-- =============================================================== -->

<div class="row g-4 mb-4">

    <div class="col-lg-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-muted">

                    Total Users

                </h6>

                <h2>

                    <?= $totalUsers ?>

                </h2>

            </div>

        </div>

    </div>

    <div class="col-lg-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-success">

                    Active

                </h6>

                <h2>

                    <?= $activeUsers ?>

                </h2>

            </div>

        </div>

    </div>

    <div class="col-lg-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-warning">

                    Inactive

                </h6>

                <h2>

                    <?= $inactiveUsers ?>

                </h2>

            </div>

        </div>

    </div>

    <div class="col-lg-3">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-danger">

                    Blocked

                </h6>

                <h2>

                    <?= $blockedUsers ?>

                </h2>

            </div>

        </div>

    </div>

</div>

<!-- =============================================================== -->
<!-- Search & Filter -->
<!-- =============================================================== -->

<div class="card shadow-sm border-0 mb-4">

    <div class="card-body">

        <form
            action="<?= base_url() ?>/users"
            method="get"
            class="row g-3">

            <div class="col-md-4">

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search user..."
                    value="<?= htmlspecialchars($search) ?>">

            </div>

            <div class="col-md-3">

                <select
                    name="role"
                    class="form-select">

                    <option value="">

                        All Roles

                    </option>

                    <?php foreach (RoleHelper::getAllRoles() as $roleOption): ?>

                        <option
                            value="<?= $roleOption ?>"
                            <?= ($role === $roleOption) ? 'selected' : '' ?>>

                            <?= $roleOption ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-3">

                <select
                    name="status"
                    class="form-select">

                    <option value="">

                        All Status

                    </option>

                    <?php foreach (['Active', 'Inactive', 'Blocked'] as $statusOption): ?>

                        <option
                            value="<?= $statusOption ?>"
                            <?= ($status === $statusOption) ? 'selected' : '' ?>>

                            <?= $statusOption ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-md-2">

                <button
                    type="submit"
                    class="btn btn-outline-primary w-100">

                    Search

                </button>

            </div>

        </form>

    </div>

</div>

<!-- =============================================================== -->
<!-- User Table -->
<!-- =============================================================== -->

<div class="card shadow-sm border-0">

    <div class="card-body table-responsive">

        <table class="table table-hover align-middle">

            <thead class="table-light">

            <tr>

                <th>Employee ID</th>

                <th>Name</th>

                <th>Department</th>

                <th>Role</th>

                <th>Email</th>

                <th>Status</th>

                <th width="220">

                    Actions

                </th>

            </tr>

            </thead>

            <tbody>

            <?php if (empty($users)): ?>

                <tr>

                    <td
                        colspan="7"
                        class="text-center text-muted py-5">

                        No users found.

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($users as $user): ?>

                    <tr>

                        <td>

                            <?= htmlspecialchars($user['employee_id']) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars($user['full_name']) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $user['department_name'] ?? '-'
                            ) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars($user['role']) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars($user['email']) ?>

                        </td>

                        <td>

                            <span class="badge bg-secondary">

                                <?= htmlspecialchars(
                                    $user['account_status']
                                ) ?>

                            </span>

                        </td>

                        <td>

                            <a
                                href="<?= base_url() ?>/users/view?id=<?= $user['user_id'] ?>"
                                class="btn btn-sm btn-info">

                                View

                            </a>

                            <a
                                href="<?= base_url() ?>/users/edit?id=<?= $user['user_id'] ?>"
                                class="btn btn-sm btn-warning">

                                Edit

                            </a>

                            <form
                                action="<?= base_url() ?>/users/delete"
                                method="post"
                                class="d-inline">

                                <input
                                    type="hidden"
                                    name="user_id"
                                    value="<?= $user['user_id'] ?>">

                                <button
                                    class="btn btn-sm btn-danger"
                                    onclick="return confirm('Delete this user?')">

                                    Delete

                                </button>

                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>