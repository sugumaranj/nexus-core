<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : index.php
 * Location    : templates/students/
 * Description : Student Management Dashboard
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display all students
 * • Display flash messages
 * • Display student statistics
 * • Search and filtering UI
 * • CRUD action buttons
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

$students = $students ?? [];

$search = $search ?? '';

$departmentId = $department_id ?? '';

$academicYear = $academic_year ?? '';

$semester = $semester ?? '';

$status = $status ?? '';

$departments = $departments ?? [];

$academicYears = $academicYears ?? [];

$semesters = $semesters ?? [];

$totalStudents = $totalStudents ?? 0;

$activeStudents = $activeStudents ?? 0;

$inactiveStudents = $inactiveStudents ?? 0;

?>

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h2 class="fw-bold mb-1">

            Student Management

        </h2>

        <p class="text-muted mb-0">

            Manage student profiles, academic records and account status.

        </p>

    </div>

    <a
        href="<?= base_url() ?>/students/create"
        class="btn btn-primary">

        <i class="bi bi-person-plus-fill me-1"></i>

        Add Student

    </a>

</div>



<div class="row g-4 mb-4">

    <div class="col-lg-4">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-muted">

                    Total Students

                </h6>

                <h2>

                    <?= $totalStudents ?>

                </h2>

            </div>

        </div>

    </div>

    <div class="col-lg-4">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-success">

                    Active

                </h6>

                <h2>

                    <?= $activeStudents ?>

                </h2>

            </div>

        </div>

    </div>

    <div class="col-lg-4">

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h6 class="text-warning">

                    Inactive

                </h6>

                <h2>

                    <?= $inactiveStudents ?>

                </h2>

            </div>

        </div>

    </div>

</div>

<div class="card shadow-sm border-0 mb-4">

    <div class="card-body">

        <form
            action="<?= base_url() ?>/students"
            method="get"
            class="row g-3">

            <div class="col-12 col-md-5">

                <input
                    type="text"
                    name="search"
                    class="form-control"
                    placeholder="Search student by name, register number..."
                    value="<?= htmlspecialchars((string) $search) ?>">

            </div>

            <div class="col-12 col-md-7">

                <select
                    name="department_id"
                    class="form-select"
                    title="Select Department">

                    <option value="">

                        All Departments

                    </option>

                    <?php foreach ($departments as $department): ?>

                        <option
                            value="<?= (int) $department['department_id'] ?>"
                            <?= ((string) $departmentId === (string) $department['department_id']) ? 'selected' : '' ?>
                            title="<?= htmlspecialchars((string) $department['department_name']) ?>">

                            <?= htmlspecialchars((string) $department['department_name']) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-12 col-md-3">

                <select
                    name="academic_year"
                    class="form-select">

                    <option value="">

                        All Years

                    </option>

                    <?php foreach ($academicYears as $year): ?>

                        <option
                            value="<?= htmlspecialchars((string) $year) ?>"
                            <?= ($academicYear === (string) $year) ? 'selected' : '' ?>>

                            <?= htmlspecialchars((string) academic_year_label((string) $year)) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-12 col-md-3">

                <select
                    name="semester"
                    class="form-select">

                    <option value="">

                        All Semesters

                    </option>

                    <?php foreach ($semesters as $semesterOption): ?>

                        <option
                            value="<?= htmlspecialchars((string) $semesterOption) ?>"
                            <?= ($semester === (string) $semesterOption) ? 'selected' : '' ?>>

                            <?= htmlspecialchars((string) semester_label($semesterOption)) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="col-12 col-md-3">

                <select
                    name="status"
                    class="form-select">

                    <option value="">

                        All Status

                    </option>

                    <option value="Active" <?= ($status === 'Active') ? 'selected' : '' ?>>

                        Active

                    </option>

                    <option value="Inactive" <?= ($status === 'Inactive') ? 'selected' : '' ?>>

                        Inactive

                    </option>

                </select>

            </div>

            <div class="col-12 col-md-3 d-flex gap-2">

                <button
                    type="submit"
                    class="btn btn-outline-primary w-100">

                    Search

                </button>

                <a
                    href="<?= base_url() ?>/students"
                    class="btn btn-outline-secondary w-100">

                    Reset

                </a>

            </div>

        </form>

    </div>

</div>

<div class="card shadow-sm border-0">

    <div class="card-body table-responsive">

        <table class="table table-hover align-middle">

            <thead class="table-light">

                <tr>

                    <th>Photo</th>

                    <th>Register No.</th>

                    <th>Roll No.</th>

                    <th>Name</th>

                    <th>Department</th>

                    <th>Academic Year</th>

                    <th>Semester</th>

                    <th>Status</th>

                    <th width="220">Actions</th>

                </tr>

            </thead>

            <tbody>

            <?php if (empty($students)): ?>

                <tr>

                    <td colspan="9" class="text-center text-muted py-5">

                        No students found.

                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($students as $student): ?>

                    <tr>

                        <td>

                            <?php if (!empty($student['profile_photo'])): ?>

                                <img
                                    src="<?= asset($student['profile_photo']) ?>"
                                    class="rounded-circle"
                                    width="40"
                                    height="40"
                                    alt="Profile Photo">

                            <?php else: ?>

                                <div class="text-secondary">

                                    <i class="bi bi-person-circle fs-4"></i>

                                </div>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?= htmlspecialchars((string) ($student['register_number'] ?? '-')) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars((string) ($student['roll_number'] ?? '-')) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars((string) ($student['full_name'] ?? '-')) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars((string) ($student['department_name'] ?? '-')) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars((string) (isset($student['academic_year']) && $student['academic_year'] !== '' ? academic_year_label((string) $student['academic_year']) : '-')) ?>

                        </td>

                        <td>

                            <?= htmlspecialchars((string) (isset($student['semester']) && $student['semester'] !== '' ? semester_label((string) $student['semester']) : '-')) ?>

                        </td>

                        <td>

                            <span class="badge bg-<?= (($student['account_status'] ?? '') === 'Active') ? 'success' : 'warning' ?>">

                                <?= htmlspecialchars((string) ($student['account_status'] ?? '-')) ?>

                            </span>

                        </td>

                        <td>

                            <div class="d-flex gap-2">

                                <a
                                    href="<?= base_url() ?>/students/view?id=<?= (int) ($student['student_id'] ?? 0) ?>"
                                    class="btn btn-outline-primary btn-sm">

                                    <i class="bi bi-eye"></i>

                                </a>

                                <a
                                    href="<?= base_url() ?>/students/edit?id=<?= (int) ($student['student_id'] ?? 0) ?>"
                                    class="btn btn-outline-secondary btn-sm">

                                    <i class="bi bi-pencil"></i>

                                </a>

                                <form
                                    action="<?= base_url() ?>/students/delete"
                                    method="post"
                                    class="d-inline">

                                    <input
                                        type="hidden"
                                        name="student_id"
                                        value="<?= (int) ($student['student_id'] ?? 0) ?>">

                                    <button
                                        type="submit"
                                        class="btn btn-outline-danger btn-sm"
                                        onclick="return confirm('Delete this student?')">

                                        <i class="bi bi-trash"></i>

                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>
