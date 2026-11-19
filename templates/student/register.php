<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : register.php
 * Location    : templates/student/
 * Description : External Student Self-Registration page.
 *
 * Layout      : auth  (templates/layouts/auth.php)
 * Controller  : StudentAuthController::showRegister()
 * Route       : GET /student/register
 * -------------------------------------------------------------------------
 */

use App\Core\Session;

$error    = Session::getFlash('error');
$success  = Session::getFlash('success');
$oldInput = Session::get('old_input') ?? [];
Session::remove('old_input');

$selectedCollegeId = (string)($oldInput['college_id'] ?? '');
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card login-card shadow-lg">
                <div class="card-body p-4 p-md-5">

                    <!-- Header & Branding -->
                    <div class="text-center mb-4">
                        <i class="bi bi-mortarboard-fill text-primary" style="font-size: 56px;"></i>
                        <h2 class="fw-bold mt-2 mb-1">NexusCore</h2>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-1 rounded-pill small fw-semibold">
                            Inter-College Student Portal
                        </span>
                        <h4 class="fw-semibold mt-3">External Student Registration</h4>
                        <p class="text-muted small">
                            Register your account to participate in upcoming inter-college symposiums and competitions.
                        </p>
                    </div>

                    <!-- Flash Alerts -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Registration Form -->
                    <form id="student-register-form" method="POST" action="<?= base_url(); ?>/student/register" novalidate>
                        <?= form_idempotency_field(); ?>
                        
                        <div class="row g-3">
                            <!-- Full Name -->
                            <div class="col-md-6">
                                <label for="full_name" class="form-label fw-semibold">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" id="full_name" name="full_name" class="form-control"
                                           placeholder="e.g. John Doe"
                                           value="<?= htmlspecialchars($oldInput['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           required autofocus>
                                </div>
                            </div>

                            <!-- Register / Roll Number -->
                            <div class="col-md-6">
                                <label for="register_number" class="form-label fw-semibold">
                                    College / University Roll Number <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-card-heading"></i></span>
                                    <input type="text" id="register_number" name="register_number" class="form-control"
                                           placeholder="e.g. 22CS045"
                                           value="<?= htmlspecialchars($oldInput['register_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           required>
                                </div>
                                <div class="form-text">As printed on your college ID card.</div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold">
                                    Email Address <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" id="email" name="email" class="form-control"
                                           placeholder="e.g. student@example.com"
                                           value="<?= htmlspecialchars($oldInput['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           required>
                                </div>
                            </div>

                            <!-- Phone -->
                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold">
                                    Mobile Phone Number <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input type="tel" id="phone" name="phone" class="form-control"
                                           placeholder="e.g. 9876543210"
                                           value="<?= htmlspecialchars($oldInput['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           required pattern="[0-9]{10}">
                                </div>
                            </div>

                            <!-- Institution / College Selection -->
                            <div class="col-12">
                                <label for="college_id" class="form-label fw-semibold">
                                    College / Institution <span class="text-danger">*</span>
                                </label>
                                <select id="college_id" name="college_id" class="form-select" required>
                                    <option value="">-- Select Your College --</option>
                                    <?php foreach ($colleges as $c): ?>
                                        <option value="<?= (int)$c['college_id'] ?>"
                                            <?= $selectedCollegeId === (string)$c['college_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['college_name'], ENT_QUOTES, 'UTF-8') ?>
                                            <?= !empty($c['city']) ? ' (' . htmlspecialchars($c['city'], ENT_QUOTES, 'UTF-8') . ')' : '' ?>
                                            <?= !empty($c['is_host']) ? ' [Host College]' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="other" <?= $selectedCollegeId === 'other' ? 'selected' : '' ?>>
                                        ➕ My College is not in this list (Enter manually)
                                    </option>
                                </select>
                            </div>

                            <!-- Dynamic Custom College Input Container -->
                            <div id="custom-college-container" class="col-12" style="display: <?= $selectedCollegeId === 'other' ? 'block' : 'none' ?>;">
                                <div class="p-3 bg-light rounded-3 border">
                                    <h6 class="fw-bold text-primary mb-2">
                                        <i class="bi bi-building-add me-1"></i> New Institution Details
                                    </h6>
                                    <p class="text-muted small mb-3">
                                        Please provide your full official college name. It will be recorded and submitted for administrator verification.
                                    </p>
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <label for="custom_college_name" class="form-label small fw-semibold">Full College Name <span class="text-danger">*</span></label>
                                            <input type="text" id="custom_college_name" name="custom_college_name" class="form-control"
                                                   placeholder="e.g. St. Xavier's College of Engineering, Madurai"
                                                   value="<?= htmlspecialchars($oldInput['custom_college_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Department Name -->
                            <div class="col-md-6">
                                <label for="department" class="form-label fw-semibold">
                                    Department / Major <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-briefcase"></i></span>
                                    <input type="text" id="department" name="department" class="form-control"
                                           placeholder="e.g. Computer Science and Engineering"
                                           value="<?= htmlspecialchars($oldInput['department'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                           required>
                                </div>
                            </div>

                            <!-- Academic Year & Semester -->
                            <div class="col-md-3">
                                <label for="academic_year" class="form-label fw-semibold">Year of Study</label>
                                <select id="academic_year" name="academic_year" class="form-select">
                                    <option value="1" <?= ($oldInput['academic_year'] ?? '1') === '1' ? 'selected' : '' ?>>1st Year</option>
                                    <option value="2" <?= ($oldInput['academic_year'] ?? '') === '2' ? 'selected' : '' ?>>2nd Year</option>
                                    <option value="3" <?= ($oldInput['academic_year'] ?? '') === '3' ? 'selected' : '' ?>>3rd Year</option>
                                    <option value="4" <?= ($oldInput['academic_year'] ?? '') === '4' ? 'selected' : '' ?>>4th Year</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label for="semester" class="form-label fw-semibold">Semester</label>
                                <select id="semester" name="semester" class="form-select">
                                    <?php for ($s = 1; $s <= 8; $s++): ?>
                                        <option value="<?= $s ?>" <?= ($oldInput['semester'] ?? '1') == (string)$s ? 'selected' : '' ?>>
                                            Semester <?= $s ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <!-- Gender -->
                            <div class="col-md-6">
                                <label for="gender" class="form-label fw-semibold">Gender</label>
                                <select id="gender" name="gender" class="form-select">
                                    <option value="Male" <?= ($oldInput['gender'] ?? 'Male') === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($oldInput['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                </select>
                            </div>

                            <!-- Date of Birth (Optional) -->
                            <div class="col-md-6">
                                <label for="dob" class="form-label fw-semibold">Date of Birth (Optional)</label>
                                <input type="date" id="dob" name="dob" class="form-control"
                                       value="<?= htmlspecialchars($oldInput['dob'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <!-- Password -->
                            <div class="col-md-6">
                                <label for="reg_password" class="form-label fw-semibold">
                                    Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" id="reg_password" name="password" class="form-control"
                                           placeholder="Minimum 6 characters" required minlength="6">
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="col-md-6">
                                <label for="reg_password_confirm" class="form-label fw-semibold">
                                    Confirm Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" id="reg_password_confirm" name="password_confirm" class="form-control"
                                           placeholder="Re-type password" required minlength="6">
                                </div>
                            </div>

                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary w-100 py-2 fs-6 fw-semibold shadow-sm">
                                    <i class="bi bi-check2-circle me-1"></i> Complete Registration
                                </button>
                            </div>

                        </div>
                    </form>

                    <hr class="my-4">

                    <!-- Login Link -->
                    <div class="text-center">
                        <p class="text-muted mb-0">
                            Already registered?
                            <a href="<?= base_url(); ?>/student/login" class="fw-bold text-primary text-decoration-none">
                                Log In to Student Portal
                            </a>
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const collegeSelect = document.getElementById('college_id');
    const customContainer = document.getElementById('custom-college-container');
    const customInput = document.getElementById('custom_college_name');

    collegeSelect.addEventListener('change', function() {
        if (this.value === 'other') {
            customContainer.style.display = 'block';
            customInput.required = true;
            customInput.focus();
        } else {
            customContainer.style.display = 'none';
            customInput.required = false;
        }
    });

    // Form client-side validation
    const form = document.getElementById('student-register-form');
    form.addEventListener('submit', function(e) {
        const pass = document.getElementById('reg_password').value;
        const confirmPass = document.getElementById('reg_password_confirm').value;

        if (pass.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters in length.');
            return;
        }

        if (pass !== confirmPass) {
            e.preventDefault();
            alert('Password and confirmation do not match.');
            return;
        }

        if (collegeSelect.value === 'other' && !customInput.value.trim()) {
            e.preventDefault();
            alert('Please enter your college name.');
            customInput.focus();
            return;
        }
    });
});
</script>
