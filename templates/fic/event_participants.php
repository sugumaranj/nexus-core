<?php
declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('/my/assigned-events') ?>">My Assigned Events</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('/my/assigned-events/event?id=' . $event['symposium_event_id']) ?>"><?= htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Registrations</li>
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
                'id' => $event['symposium_event_id'],
                'status' => $filters['application_status'] ?? '',
                'department_id' => $filters['department_id'] ?? '',
                'academic_year' => $filters['academic_year'] ?? '',
                'application_type' => $filters['application_type'] ?? ''
            ]);
            ?>
            <a href="<?= base_url('/my/assigned-events/registrations/export?' . $exportParams) ?>" target="_blank" class="btn btn-outline-danger shadow-sm">
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
                <input type="hidden" name="id" value="<?= htmlspecialchars((string)$event['symposium_event_id'], ENT_QUOTES, 'UTF-8') ?>">
                
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
                        <th>Role</th>
                        <th>Team ID</th>
                        <th>Registered On</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr><td colspan="11" class="text-center py-4 text-muted">No participants found.</td></tr>
                    <?php else: ?>
                        <?php
                        $sno = 1;
                        foreach ($applications as $app):
                            $isTeam       = ($app['application_type'] ?? '') === 'Team';
                            $isLeader     = (bool)($app['is_team_leader'] ?? !$isTeam);
                            $statusClass  = 'secondary';
                            switch($app['application_status'] ?? '') {
                                case 'Approved':  $statusClass = 'success'; break;
                                case 'Pending':   $statusClass = 'warning text-dark'; break;
                                case 'Rejected':  $statusClass = 'danger'; break;
                                case 'Withdrawn': $statusClass = 'dark'; break;
                            }
                        ?>
                        <tr class="<?= $isTeam && !$isLeader ? 'table-light' : '' ?>">
                            <td><?= $sno++ ?></td>
                            <td class="font-monospace small"><?= htmlspecialchars($app['application_no'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($app['register_number'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?= htmlspecialchars($app['student_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td><?= htmlspecialchars($app['student_department'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($app['student_year'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if ($isTeam): ?>
                                    <span class="badge bg-success">Team</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">Individual</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isTeam): ?>
                                    <?php if ($isLeader): ?>
                                        <span class="badge bg-warning text-dark" title="Team Leader">
                                            <i class="bi bi-star-fill me-1"></i>Leader
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border" title="Team Member">
                                            <i class="bi bi-person-fill me-1"></i>Member
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= !empty($app['team_id']) ? 'TEAM-' . htmlspecialchars((string)$app['team_id'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                            <td><?= \App\Helpers\DateHelper::date($app['applied_at'] ?? 'now') ?></td>
                            <td>
                                <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($app['application_status'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


