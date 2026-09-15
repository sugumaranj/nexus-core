<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : view.php
 * Location    : templates/students/
 * Description : View Student Details
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display complete student profile
 * • Display profile photo
 * • Display academic and personal details
 * • Read-only student profile
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

$student = $student ?? [];

$registerNumber = (string) ($student['register_number'] ?? '-');
$rollNumber = (string) ($student['roll_number'] ?? '-');
$fullName = (string) ($student['full_name'] ?? '-');
$email = (string) ($student['email'] ?? '-');
$phone = (string) ($student['phone'] ?? '-');
$departmentName = (string) ($student['department_name'] ?? '-');
$gender = (string) ($student['gender'] ?? '-');
$dobRaw = trim((string) ($student['dob'] ?? ''));
$dob = ($dobRaw !== '' && $dobRaw !== '-') ? \App\Helpers\DateHelper::date($dobRaw) : '-';
$academicYear = (string) ($student['academic_year'] ?? '-');
$academicYearLabel = '-';
if ($academicYear !== '-' && $academicYear !== '') {
    $academicYearLabel = academic_year_label($academicYear);
}
$semester = (string) ($student['semester'] ?? '-');
$section = (string) ($student['section'] ?? '-');
$admissionYear = (string) ($student['admission_year'] ?? '-');
$graduationYear = (string) ($student['graduation_year'] ?? '-');
$status = (string) ($student['account_status'] ?? '-');
$createdAt = (string) ($student['created_at'] ?? '-');
$updatedAt = (string) ($student['updated_at'] ?? '-');
$profilePhoto = $student['profile_photo'] ?? null;

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="fw-bold mb-1">

            Student Details

        </h2>

        <p class="text-muted mb-0">

            View complete student information.

        </p>

    </div>

    <div>

        <a
            href="<?= base_url() ?>/students/edit?id=<?= (int) ($student['student_id'] ?? 0) ?>"
            class="btn btn-primary">

            <i class="bi bi-pencil-square me-1"></i>

            Edit

        </a>

        <a
            href="<?= base_url() ?>/students"
            class="btn btn-outline-secondary">

            Back

        </a>

    </div>

</div>

<div class="row">

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

                <p class="text-muted mb-2">

                    <?= htmlspecialchars($registerNumber, ENT_QUOTES, 'UTF-8') ?>

                </p>

                <span class="badge bg-<?= ($status === 'Active') ? 'success' : 'warning' ?>">

                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>

                </span>

            </div>

        </div>

    </div>

    <div class="col-lg-8">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h5 class="fw-bold mb-3">

                    Academic Details

                </h5>

                <table class="table table-bordered align-middle">

                    <tbody>

                        <tr>

                            <th width="220">Register Number</th>

                            <td><?= htmlspecialchars($registerNumber, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Roll Number</th>

                            <td><?= htmlspecialchars($rollNumber, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Department</th>

                            <td><?= htmlspecialchars($departmentName, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Academic Year</th>

                            <td><?= htmlspecialchars($academicYearLabel, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Semester</th>

                            <td><?= htmlspecialchars((string) semester_label($semester), ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Section</th>

                            <td><?= htmlspecialchars($section, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Admission Year</th>

                            <td><?= htmlspecialchars($admissionYear, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Graduation Year</th>

                            <td><?= htmlspecialchars($graduationYear, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

        <div class="card shadow-sm border-0 mt-4">

            <div class="card-body">

                <h5 class="fw-bold mb-3">

                    Personal Details

                </h5>

                <table class="table table-bordered align-middle">

                    <tbody>

                        <tr>

                            <th width="220">Full Name</th>

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

                            <th>Gender</th>

                            <td><?= htmlspecialchars($gender, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Date of Birth</th>

                            <td><?= htmlspecialchars($dob, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Account Status</th>

                            <td><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Created Date</th>

                            <td><?= htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                        <tr>

                            <th>Updated Date</th>

                            <td><?= htmlspecialchars($updatedAt, ENT_QUOTES, 'UTF-8') ?></td>

                        </tr>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>
