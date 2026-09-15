<?php
declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('/coordinator/registrations') ?>">Registration Management</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('/coordinator/registrations/summary?symposium_id=' . $symposium['symposium_id']) ?>"><?= htmlspecialchars($symposium['title'], ENT_QUOTES, 'UTF-8') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8') ?></li>
        </ol>
    </nav>

    <?php if ($successMsg = \App\Core\Session::getFlash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($successMsg, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg = \App\Core\Session::getFlash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><?= htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8') ?> Participants</h2>
            <span class="badge bg-secondary"><?= htmlspecialchars($event['event_code'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div>
            <?php
            $exportParams = http_build_query([
                'event_id' => $event['symposium_event_id'],
                'status' => $filters['application_status'] ?? '',
                'department_id' => $filters['department_id'] ?? '',
                'academic_year' => $filters['academic_year'] ?? '',
                'application_type' => $filters['application_type'] ?? ''
            ]);
            ?>
            <a href="<?= base_url('/coordinator/registrations/event/export?' . $exportParams) ?>" target="_blank" class="btn btn-outline-danger shadow-sm">
                <i class="bi bi-file-earmark-pdf"></i> Export PDF
            </a>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm bg-primary text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= (int)($stats['total'] ?? 0) ?></h3>
                    <small>Total Registrations</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm bg-success text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= (int)($stats['approved'] ?? 0) ?></h3>
                    <small>Approved</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm bg-dark text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= (int)($stats['withdrawn'] ?? 0) ?></h3>
                    <small>Withdrawn</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm bg-secondary text-white">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= (int)($stats['cancelled'] ?? 0) ?></h3>
                    <small>Cancelled</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="" method="get" class="row g-3 align-items-end">
                <input type="hidden" name="event_id" value="<?= htmlspecialchars((string)$event['symposium_event_id'], ENT_QUOTES, 'UTF-8') ?>">
                
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, Register No, Team" value="<?= htmlspecialchars($search ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['department_id'] ?>" <?= (($filters['department_id'] ?? '') == $dept['department_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['department_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Year</label>
                    <select name="academic_year" class="form-select">
                        <option value="">All Years</option>
                        <?php foreach ($academicYears as $year): ?>
                            <option value="<?= $year ?>" <?= (($filters['academic_year'] ?? '') == $year) ? 'selected' : '' ?>>
                                Year <?= $year ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="application_type" class="form-select">
                        <option value="All">All Types</option>
                        <option value="Individual" <?= (($filters['application_type'] ?? '') === 'Individual') ? 'selected' : '' ?>>Individual</option>
                        <option value="Team" <?= (($filters['application_type'] ?? '') === 'Team') ? 'selected' : '' ?>>Team</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="All">All Statuses</option>
                        <option value="Approved" <?= (($filters['application_status'] ?? '') === 'Approved') ? 'selected' : '' ?>>Approved</option>
                        <option value="Pending" <?= (($filters['application_status'] ?? '') === 'Pending') ? 'selected' : '' ?>>Pending</option>
                        <option value="Rejected" <?= (($filters['application_status'] ?? '') === 'Rejected') ? 'selected' : '' ?>>Rejected</option>
                        <option value="Withdrawn" <?= (($filters['application_status'] ?? '') === 'Withdrawn') ? 'selected' : '' ?>>Withdrawn</option>
                        <option value="Cancelled" <?= (($filters['application_status'] ?? '') === 'Cancelled') ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>S.No</th>
                        <th>App No</th>
                        <th>Register No</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Year</th>
                        <th>Type</th>
                        <th>Registered On</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="10" class="text-center py-4 text-muted">No participants found.</td></tr>
                    <?php else: ?>
                        <?php 
                        $sno = 1;
                        foreach ($applications as $app): 
                            $appId = $app['application_id'];
                            $resolved = $resolvedParticipants[$appId] ?? null;
                            if (!$resolved) continue;

                            if ($resolved['participant_type'] === 'team'): 
                        ?>
                            <!-- Team Header Row -->
                            <tr class="table-secondary fw-bold">
                                <td><?= $sno++ ?></td>
                                <td><?= htmlspecialchars($app['application_no'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td colspan="4">
                                    Team Manager: <?= htmlspecialchars($resolved['manager']['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td><span class="badge bg-success">Team</span></td>
                                <td><?= \App\Helpers\DateHelper::date($app['applied_at'] ?? 'now') ?></td>
                                <td>
                                    <?php
                                    $statusClass = 'secondary';
                                    switch($app['application_status'] ?? '') {
                                        case 'Approved': $statusClass = 'success'; break;
                                        case 'Pending': $statusClass = 'warning text-dark'; break;
                                        case 'Rejected': $statusClass = 'danger'; break;
                                        case 'Withdrawn': $statusClass = 'dark'; break;
                                    }
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($app['application_status'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if (($app['application_status'] ?? '') === 'Approved'): ?>
                                        <form action="<?= base_url('/coordinator/registrations/cancel') ?>" method="post" onsubmit="return confirm('Cancel this registration?');">
                                            <input type="hidden" name="application_id" value="<?= htmlspecialchars((string)$appId, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="event_id" value="<?= htmlspecialchars((string)($event['symposium_event_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Registration">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <!-- Team Members -->
                            <?php foreach ($resolved['members'] as $mSno => $member): ?>
                            <tr>
                                <td></td>
                                <td></td>
                                <td><i class="bi bi-person-fill text-muted me-1"></i><?= htmlspecialchars($member['register_number'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($member['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($member['department'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)($member['academic_year'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td colspan="4"></td>
                            </tr>
                            <?php endforeach; ?>

                        <?php else: 
                            $p = $resolved['participant'];
                        ?>
                            <!-- Individual Row -->
                            <tr>
                                <td><?= $sno++ ?></td>
                                <td><?= htmlspecialchars($app['application_no'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($p['register_number'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($p['department'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string)($p['academic_year'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge bg-info">Individual</span></td>
                                <td><?= \App\Helpers\DateHelper::date($app['applied_at'] ?? 'now') ?></td>
                                <td>
                                    <?php
                                    $statusClass = 'secondary';
                                    switch($app['application_status'] ?? '') {
                                        case 'Approved': $statusClass = 'success'; break;
                                        case 'Pending': $statusClass = 'warning text-dark'; break;
                                        case 'Rejected': $statusClass = 'danger'; break;
                                        case 'Withdrawn': $statusClass = 'dark'; break;
                                    }
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($app['application_status'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if (($app['application_status'] ?? '') === 'Approved'): ?>
                                        <form action="<?= base_url('/coordinator/registrations/cancel') ?>" method="post" onsubmit="return confirm('Cancel this registration?');">
                                            <input type="hidden" name="application_id" value="<?= htmlspecialchars((string)$appId, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="event_id" value="<?= htmlspecialchars((string)($event['symposium_event_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel Registration">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
