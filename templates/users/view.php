<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : view.php
 * Location    : templates/users/
 * Description : View User Details
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display complete user profile
 * • Display profile photo
 * • Display signature
 * • Display account information
 * • Read-only user profile
 *
 * NOTE
 * -------------------------------------------------------------------------
 * This page does NOT modify data.
 * It only displays user information.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

/*
|--------------------------------------------------------------------------
| User Data
|--------------------------------------------------------------------------
*/

$user = $user ?? [];

/*
|--------------------------------------------------------------------------
| Safe Values
|--------------------------------------------------------------------------
|
| Prevent htmlspecialchars() TypeError when database values are NULL.
|
*/

$employeeId     = (string) ($user['employee_id'] ?? '-');
$fullName       = (string) ($user['full_name'] ?? '-');
$email          = (string) ($user['email'] ?? '-');
$phone          = (string) ($user['phone'] ?? '-');
$departmentName = (string) ($user['department_name'] ?? '-');
$role           = (string) ($user['role'] ?? '-');
$status         = (string) ($user['account_status'] ?? '-');
$lastLogin      = (string) ($user['last_login'] ?? 'Never Logged In');
$createdAt      = (string) ($user['created_at'] ?? '-');
$updatedAt      = (string) ($user['updated_at'] ?? '-');

$profilePhoto = $user['profile_photo'] ?? null;
$signature    = $user['signature_path'] ?? null;

?>

<!-- =============================================================== -->
<!-- Page Header -->
<!-- =============================================================== -->

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="fw-bold mb-1">

            User Details

        </h2>

        <p class="text-muted mb-0">

            View complete user information.

        </p>

    </div>

    <div>

        <a
            href="<?= base_url() ?>/users/edit?id=<?= (int) ($user['user_id'] ?? 0) ?>"
            class="btn btn-primary">

            <i class="bi bi-pencil-square me-1"></i>

            Edit

        </a>

        <a
            href="<?= base_url() ?>/users"
            class="btn btn-outline-secondary">

            Back

        </a>

    </div>

</div>

<div class="row">

    <!-- =========================================================== -->
    <!-- User Summary -->
    <!-- =========================================================== -->

    <div class="col-lg-4">

        <div class="card shadow-sm border-0">

            <div class="card-body text-center">

                <?php if (!empty($profilePhoto)): ?>

                    <img
                        src="<?= asset($profilePhoto) ?>"
                        class="rounded-circle mb-3"
                        width="160"
                        height="160"
                        alt="Profile Photo">

                <?php else: ?>

                    <div class="display-1 text-secondary mb-3">

                        <i class="bi bi-person-circle"></i>

                    </div>

                <?php endif; ?>

                <h4>

                    <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>

                </h4>

                <p class="text-muted">

                    <?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>

                </p>

                <span class="badge bg-primary">

                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>

                </span>

            </div>

        </div>

    </div>

    <!-- =========================================================== -->
    <!-- User Information -->
    <!-- =========================================================== -->

    <div class="col-lg-8">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <table class="table table-bordered align-middle">

                    <tbody>

                        <tr>

                            <th width="220">Employee ID</th>

                            <td><?= htmlspecialchars($employeeId, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Full Name</th>

                            <td><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Email Address</th>

                            <td><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Phone Number</th>

                            <td><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Department</th>

                            <td><?= htmlspecialchars($departmentName, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Role</th>

                            <td><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Account Status</th>

                            <td><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Last Login</th>

                            <td><?= htmlspecialchars($lastLogin, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Created At</th>

                            <td><?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Updated At</th>

                            <td><?= htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

        <!-- ======================================================= -->
        <!-- Signature -->
        <!-- ======================================================= -->

        <div class="card shadow-sm border-0 mt-4">

            <div class="card-header">

                Signature

            </div>

            <div class="card-body text-center">

                <?php if (!empty($signature)): ?>

                    <img
                        src="<?= asset($signature) ?>"
                        class="img-fluid"
                        style="max-height:120px;"
                        alt="Signature">

                <?php else: ?>

                    <p class="text-muted mb-0">

                        No signature uploaded.

                    </p>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>