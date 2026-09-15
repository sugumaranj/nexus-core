<?php
declare(strict_types=1);

// Group students
$grouped = [];
foreach ($unregisteredStudents as $student) {
    $dept = $student['department_name'];
    $year = $student['academic_year'];
    $grouped[$dept][$year][] = $student;
}
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('/coordinator/registrations') ?>">Registration Management</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('/coordinator/registrations/summary?symposium_id=' . $symposium['symposium_id']) ?>"><?= htmlspecialchars($symposium['title'], ENT_QUOTES, 'UTF-8') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Not Registered</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Students Not Registered</h2>
        <div>
            <?php
            $exportUrl = base_url('/coordinator/registrations/not-registered?symposium_id=' . $symposium['symposium_id'] . '&export=pdf');
            if (isset($_GET['department_id'])) $exportUrl .= '&department_id=' . urlencode($_GET['department_id']);
            if (isset($_GET['academic_year'])) $exportUrl .= '&academic_year=' . urlencode($_GET['academic_year']);
            ?>
            <a href="<?= $exportUrl ?>" class="btn btn-primary" target="_blank"><i class="bi bi-file-earmark-pdf me-1"></i> Export to PDF</a>
            <a href="<?= base_url('/coordinator/registrations?symposium_id=' . $symposium['symposium_id']) ?>" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?= base_url('/coordinator/registrations/not-registered') ?>" class="row g-3 align-items-end">
                <input type="hidden" name="symposium_id" value="<?= $symposium['symposium_id'] ?>">
                
                <div class="col-md-5">
                    <label class="form-label fw-bold text-uppercase" style="font-size: 0.8rem;">Department</label>
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="All">All Departments</option>
                        <?php foreach($departments as $d): ?>
                            <option value="<?= $d['department_id'] ?>" <?= ($filters['department_id'] ?? '') == $d['department_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-5">
                    <label class="form-label fw-bold text-uppercase" style="font-size: 0.8rem;">Academic Year</label>
                    <select name="academic_year" class="form-select form-select-sm">
                        <option value="All">All Years</option>
                        <option value="1" <?= ($filters['academic_year'] ?? '') == '1' ? 'selected' : '' ?>>1st Year</option>
                        <option value="2" <?= ($filters['academic_year'] ?? '') == '2' ? 'selected' : '' ?>>2nd Year</option>
                        <option value="3" <?= ($filters['academic_year'] ?? '') == '3' ? 'selected' : '' ?>>3rd Year</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Formal Header Display -->
    <div class="card shadow-sm mb-4">
        <div class="card-body px-5 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <?php if (!empty($collegeLogo)): ?>
                    <img src="<?= base_url('public/' . ltrim($collegeLogo, '/')) ?>" alt="College Logo" style="height: 70px;" onerror="this.src='<?= base_url($collegeLogo) ?>'">
                <?php else: ?>
                    <div style="width: 70px;"></div>
                <?php endif; ?>
                <div class="text-center">
                    <h3 class="fw-bold mb-1" style="font-family: 'Times New Roman', Times, serif;"><?= mb_strtoupper($collegeName ?? 'GOVERNMENT ARTS AND SCIENCE COLLEGE') ?></h3>
                    <h5 class="mb-3" style="font-family: 'Times New Roman', Times, serif;">VEERAPANDI, THENI</h5>
                    <h4 class="fw-bold text-primary mb-0">STUDENTS NOT REGISTERED REPORT</h4>
                </div>
                <img src="<?= base_url('public/assets/images/logo/nexuscore-logo.png') ?>" alt="Nexus Logo" style="height: 60px;" onerror="this.src='<?= base_url('assets/images/logo/nexuscore-logo.png') ?>'">
            </div>

            <hr class="mb-4">

            <div class="row mb-4">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr><th class="ps-0" style="width: 150px;">SYMPOSIUM:</th><td><?= htmlspecialchars($symposium['title']) ?></td></tr>
                        <tr><th class="ps-0">CODE:</th><td><?= htmlspecialchars($symposium['symposium_code']) ?></td></tr>
                        <tr><th class="ps-0">ACADEMIC YEAR:</th><td><?= htmlspecialchars($symposium['academic_year']) ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr><th class="ps-0" style="width: 150px;">REG. PERIOD:</th><td><?= date('d-m-Y', strtotime((string)$symposium['registration_start'])) ?> to <?= date('d-m-Y', strtotime((string)$symposium['registration_end'])) ?></td></tr>
                        <tr><th class="ps-0">GENERATED:</th><td><?= date('d-M-Y h:i A') ?></td></tr>
                        <tr><th class="ps-0">DEPARTMENTS:</th><td><?= count($departments) ?> Participating</td></tr>
                    </table>
                </div>
            </div>

            <h5 class="fw-bold mb-3 text-uppercase">1. OVERALL SUMMARY</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="border rounded p-3 bg-light">
                        <div class="text-muted fw-bold" style="font-size: 0.8rem;">TOTAL ELIGIBLE STUDENTS</div>
                        <div class="fs-3 fw-normal text-primary"><?= $stats['total_eligible'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 bg-light">
                        <div class="text-muted fw-bold" style="font-size: 0.8rem;">REGISTERED STUDENTS</div>
                        <div class="fs-3 fw-normal text-success"><?= $stats['total_registered'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 bg-light border-warning">
                        <div class="text-muted fw-bold" style="font-size: 0.8rem;">NOT REGISTERED STUDENTS</div>
                        <div class="fs-3 fw-normal text-warning"><?= $stats['total_unregistered'] ?? 0 ?></div>
                    </div>
                </div>
            </div>

            <h5 class="fw-bold mb-3 text-uppercase">2. DETAILED LIST (FILTERED)</h5>
            
            <?php if (empty($grouped)): ?>
                <div class="alert alert-success text-center py-4">
                    <i class="bi bi-check-circle fs-1 d-block mb-2"></i>
                    All eligible students for this filter have registered!
                </div>
            <?php else: ?>
                <?php foreach ($grouped as $dept => $years): ?>
                    <div class="mb-4">
                        <h5 class="fw-bold bg-dark text-white p-2 mb-0 rounded-top"><?= htmlspecialchars($dept) ?></h5>
                        <?php foreach ($years as $year => $students): ?>
                            <div class="bg-light p-2 border-start border-end fw-bold text-primary">
                                <?= academic_year_label($year) ?>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 5%;">S.No</th>
                                            <th style="width: 20%;">Register No</th>
                                            <th style="width: 40%;">Name</th>
                                            <th style="width: 15%;">Year</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $sno = 1; foreach ($students as $student): ?>
                                            <tr>
                                                <td><?= $sno++ ?></td>
                                                <td><?= htmlspecialchars($student['register_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= academic_year_label($student['academic_year']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
