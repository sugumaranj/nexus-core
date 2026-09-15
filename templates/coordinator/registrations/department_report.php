<?php
declare(strict_types=1);
$overall = $reportData['overall'];
$deptData = $reportData['departments'];
$yearData = $reportData['years'];
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= base_url('/coordinator/registrations') ?>">Registration Management</a></li>
                <li class="breadcrumb-item"><a href="<?= base_url('/coordinator/registrations/summary?symposium_id=' . $symposium['symposium_id']) ?>"><?= htmlspecialchars($symposium['title'], ENT_QUOTES, 'UTF-8') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Department Registration Report</li>
            </ol>
        </nav>
        <a href="<?= base_url('/coordinator/registrations/report/department?symposium_id=' . $symposium['symposium_id'] . '&export=pdf&' . http_build_query(array_filter($filters))) ?>" class="btn btn-primary shadow-sm">
            <i class="bi bi-file-earmark-pdf me-2"></i> Export to PDF
        </a>
    </div>

    <!-- Filters Section -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?= base_url('/coordinator/registrations/report/department') ?>" class="row g-3">
                <input type="hidden" name="symposium_id" value="<?= $symposium['symposium_id'] ?>">
                
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold text-uppercase">Department</label>
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['department_id'] ?>" <?= ($filters['department_id'] ?? '') == $dept['department_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold text-uppercase">Academic Year</label>
                    <select name="academic_year" class="form-select form-select-sm">
                        <option value="">All Years</option>
                        <option value="1" <?= ($filters['academic_year'] ?? '') == '1' ? 'selected' : '' ?>>1st Year</option>
                        <option value="2" <?= ($filters['academic_year'] ?? '') == '2' ? 'selected' : '' ?>>2nd Year</option>
                        <option value="3" <?= ($filters['academic_year'] ?? '') == '3' ? 'selected' : '' ?>>3rd Year</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold text-uppercase">Gender</label>
                    <select name="gender" class="form-select form-select-sm">
                        <option value="All" <?= ($filters['gender'] ?? 'All') == 'All' ? 'selected' : '' ?>>All Genders</option>
                        <option value="Male" <?= ($filters['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($filters['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold text-uppercase">Status</label>
                    <select name="application_status" class="form-select form-select-sm">
                        <option value="All" <?= ($filters['application_status'] ?? 'All') == 'All' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="Pending" <?= ($filters['application_status'] ?? '') == 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Approved" <?= ($filters['application_status'] ?? '') == 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Rejected" <?= ($filters['application_status'] ?? '') == 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="Withdrawn" <?= ($filters['application_status'] ?? '') == 'Withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label text-muted small fw-bold text-uppercase">Type</label>
                    <select name="application_type" class="form-select form-select-sm">
                        <option value="All" <?= ($filters['application_type'] ?? 'All') == 'All' ? 'selected' : '' ?>>All Types</option>
                        <option value="Individual" <?= ($filters['application_type'] ?? '') == 'Individual' ? 'selected' : '' ?>>Individual</option>
                        <option value="Team" <?= ($filters['application_type'] ?? '') == 'Team' ? 'selected' : '' ?>>Team</option>
                    </select>
                </div>

                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Formal Header Display -->
    <div class="card shadow-sm mb-4">
        <div class="card-body px-5 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <?php if ($collegeLogo): ?>
                    <img src="<?= base_url('public/' . ltrim($collegeLogo, '/')) ?>" alt="College Logo" style="height: 70px;" onerror="this.src='<?= base_url($collegeLogo) ?>'">
                <?php else: ?>
                    <div style="width: 70px;"></div>
                <?php endif; ?>
                <div class="text-center">
                    <h3 class="fw-bold mb-1" style="font-family: 'Times New Roman', Times, serif;"><?= mb_strtoupper($collegeName) ?></h3>
                    <h5 class="mb-3" style="font-family: 'Times New Roman', Times, serif;">VEERAPANDI, THENI</h5>
                    <h4 class="fw-bold text-primary mb-0">DEPARTMENT-WISE REGISTRATION REPORT</h4>
                </div>
                <img src="<?= base_url('public/assets/images/logo/nexuscore-logo.png') ?>" alt="Nexus Logo" style="height: 60px;" onerror="this.src='<?= base_url('assets/images/logo/nexuscore-logo.png') ?>'">
            </div>

            <hr class="mb-4">

            <div class="row mb-4">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><th class="ps-0" style="width: 150px;">Symposium:</th><td><?= htmlspecialchars($symposium['title']) ?></td></tr>
                        <tr><th class="ps-0">Code:</th><td><?= htmlspecialchars($symposium['symposium_code']) ?></td></tr>
                        <tr><th class="ps-0">Academic Year:</th><td><?= htmlspecialchars($symposium['academic_year']) ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><th class="ps-0" style="width: 150px;">Reg. Period:</th><td>
                            <?= !empty($symposium['registration_start']) ? date('d-m-Y', strtotime($symposium['registration_start'])) . ' to ' . date('d-m-Y', strtotime($symposium['registration_end'])) : 'N/A' ?>
                        </td></tr>
                        <tr><th class="ps-0">Generated:</th><td><?= date('d-M-Y H:i A') ?></td></tr>
                        <tr><th class="ps-0">Departments:</th><td><?= count($deptData) ?> Participating</td></tr>
                    </table>
                </div>
            </div>

            <!-- 1. OVERALL SUMMARY -->
            <h5 class="fw-bold mb-3 border-bottom pb-2">1. OVERALL REGISTRATION SUMMARY</h5>
            <div class="row g-3 mb-5">
                <div class="col-md-3"><div class="p-3 bg-light rounded border"><div class="text-muted small fw-bold">TOTAL APPLICATIONS</div><h3 class="mb-0 text-primary"><?= $overall['total_applications'] ?></h3></div></div>
                <div class="col-md-3"><div class="p-3 bg-light rounded border"><div class="text-muted small fw-bold">TOTAL PARTICIPANTS</div><h3 class="mb-0 text-success"><?= $overall['total_participants'] ?></h3></div></div>
                <div class="col-md-3"><div class="p-3 bg-light rounded border"><div class="text-muted small fw-bold">MALE PARTICIPANTS</div><h3 class="mb-0 text-info"><?= $overall['male'] ?></h3></div></div>
                <div class="col-md-3"><div class="p-3 bg-light rounded border"><div class="text-muted small fw-bold">FEMALE PARTICIPANTS</div><h3 class="mb-0 text-warning"><?= $overall['female'] ?></h3></div></div>
                
                <div class="col-md-2"><div class="p-3 bg-light rounded border"><div class="text-muted small fw-bold">INDIVIDUAL</div><h4 class="mb-0"><?= $overall['individual'] ?></h4></div></div>
                <div class="col-md-2"><div class="p-3 bg-light rounded border"><div class="text-muted small fw-bold">TEAM</div><h4 class="mb-0"><?= $overall['team'] ?></h4></div></div>
                <div class="col-md-2"><div class="p-3 bg-light rounded border"><div class="text-muted small fw-bold">APPROVED</div><h4 class="mb-0 text-success"><?= $overall['approved'] ?></h4></div></div>
                <div class="col-md-2"><div class="p-3 bg-light rounded border"><div class="text-muted small fw-bold">PENDING</div><h4 class="mb-0 text-secondary"><?= $overall['pending'] ?></h4></div></div>
                <div class="col-md-2"><div class="p-3 bg-light rounded border"><div class="text-muted small fw-bold">REJECTED</div><h4 class="mb-0 text-danger"><?= $overall['rejected'] ?></h4></div></div>
                <div class="col-md-2"><div class="p-3 bg-light rounded border"><div class="text-muted small fw-bold">WITHDRAWN</div><h4 class="mb-0 text-dark"><?= $overall['withdrawn'] ?></h4></div></div>
            </div>

            <!-- 2. DEPARTMENT-WISE SUMMARY -->
            <h5 class="fw-bold mb-3 border-bottom pb-2">2. DEPARTMENT-WISE REGISTRATION SUMMARY</h5>
            <div class="table-responsive mb-5">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr class="text-center align-middle">
                            <th rowspan="2">S.No</th>
                            <th rowspan="2" class="text-start">Department Name</th>
                            <th colspan="2">Applications</th>
                            <th colspan="3">Participants</th>
                            <th colspan="4">Status (Participants)</th>
                        </tr>
                        <tr class="text-center">
                            <th>Total</th>
                            <th>Team</th>
                            <th>Total</th>
                            <th>Male</th>
                            <th>Female</th>
                            <th class="text-success">Appr</th>
                            <th class="text-secondary">Pend</th>
                            <th class="text-danger">Rej</th>
                            <th class="text-dark">Withd</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($deptData)): ?>
                            <tr><td colspan="11" class="text-center py-3 text-muted">No registrations found matching criteria.</td></tr>
                        <?php else: $sno = 1; foreach($deptData as $d): ?>
                            <tr class="text-center">
                                <td><?= $sno++ ?></td>
                                <td class="text-start fw-bold"><?= htmlspecialchars($d['department_name']) ?></td>
                                <td class="fw-bold bg-light"><?= $d['total_applications'] ?></td>
                                <td><?= $d['team'] ?></td>
                                <td class="fw-bold bg-light"><?= $d['total_participants'] ?></td>
                                <td><?= $d['male'] ?></td>
                                <td><?= $d['female'] ?></td>
                                <td class="text-success"><?= $d['approved'] ?></td>
                                <td class="text-secondary"><?= $d['pending'] ?></td>
                                <td class="text-danger"><?= $d['rejected'] ?></td>
                                <td class="text-dark"><?= $d['withdrawn'] ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 3. YEAR-WISE SUMMARY -->
            <h5 class="fw-bold mb-3 border-bottom pb-2">3. ACADEMIC YEAR-WISE SUMMARY</h5>
            <div class="table-responsive mb-5">
                <table class="table table-bordered align-middle" style="max-width: 800px;">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th>Academic Year</th>
                            <th>Total Apps</th>
                            <th class="bg-light">Total Participants</th>
                            <th>Male</th>
                            <th>Female</th>
                            <th class="text-success">Approved</th>
                            <th class="text-secondary">Pending</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($yearData)): ?>
                            <tr><td colspan="7" class="text-center py-3 text-muted">No registrations found.</td></tr>
                        <?php else: foreach($yearData as $y): ?>
                            <tr class="text-center">
                                <td class="fw-bold"><?= academic_year_label($y['academic_year']) ?></td>
                                <td><?= $y['total_applications'] ?></td>
                                <td class="fw-bold bg-light"><?= $y['total_participants'] ?></td>
                                <td><?= $y['male'] ?></td>
                                <td><?= $y['female'] ?></td>
                                <td class="text-success"><?= $y['approved'] ?></td>
                                <td class="text-secondary"><?= $y['pending'] ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 4. DETAILED BREAKDOWN -->
            <h5 class="fw-bold mb-3 border-bottom pb-2">4. DETAILED DEPARTMENT & YEAR BREAKDOWN</h5>
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr class="text-center">
                            <th class="text-start">Department</th>
                            <th>Academic Year</th>
                            <th>Male</th>
                            <th>Female</th>
                            <th class="bg-light fw-bold">Total Participants</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($deptData)): ?>
                            <tr><td colspan="5" class="text-center py-3 text-muted">No data found.</td></tr>
                        <?php else: 
                            foreach($deptData as $d): 
                                $yearsCount = count($d['years']);
                                $first = true;
                                foreach($d['years'] as $y):
                        ?>
                            <tr class="text-center">
                                <?php if($first): ?>
                                    <td class="text-start fw-bold align-middle" rowspan="<?= $yearsCount ?>"><?= htmlspecialchars($d['department_name']) ?></td>
                                <?php $first = false; endif; ?>
                                <td><?= academic_year_label($y['academic_year']) ?></td>
                                <td><?= $y['male'] ?></td>
                                <td><?= $y['female'] ?></td>
                                <td class="bg-light fw-bold"><?= $y['total'] ?></td>
                            </tr>
                        <?php endforeach; endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
