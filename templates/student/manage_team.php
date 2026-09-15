<?php
declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('/student/my-registrations') ?>">My Registrations</a></li>
            <li class="breadcrumb-item active" aria-current="page">Manage Team</li>
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

    <h2 class="mb-4">Team for <?= htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8') ?></h2>

    <div class="row g-4">
        <!-- Team Info -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Team Info</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <span class="text-muted d-block small">Event Code</span>
                        <span class="fw-bold"><?= htmlspecialchars($event['event_code'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted d-block small">Event Name</span>
                        <span><?= htmlspecialchars($event['event_name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted d-block small">Team ID</span>
                        <span class="badge bg-secondary">TEAM-<?= htmlspecialchars((string)$team['team_id'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="mb-3">
                        <span class="text-muted d-block small">Members</span>
                        <span class="badge bg-info text-dark"><?= count($members) ?> / <?= (int)$event['max_team_size'] ?> members</span>
                    </div>
                    <div>
                        <span class="text-muted d-block small">Status</span>
                        <?php
                        $statusClass = 'secondary';
                        switch($application['application_status']) {
                            case 'Approved': $statusClass = 'success'; break;
                            case 'Pending': $statusClass = 'warning text-dark'; break;
                            case 'Rejected': $statusClass = 'danger'; break;
                            case 'Withdrawn': $statusClass = 'dark'; break;
                        }
                        ?>
                        <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($application['application_status'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Members List -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Team Members</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Register No</th>
                                <th>Department</th>
                                <th>Year</th>
                                <?php 
                                $isRegOpen = empty($application['registration_end']) || date('Y-m-d H:i:s') <= $application['registration_end'];
                                if ($isRegOpen && !in_array($application['application_status'], ['Withdrawn', 'Cancelled', 'Rejected'])): 
                                ?>
                                <th class="text-center">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $index => $member): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <?= htmlspecialchars($member['member_name'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php if ($member['student_id'] === $currentStudentId): ?>
                                            <span class="badge bg-primary ms-1">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($member['member_register_number'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($member['member_department'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)$member['member_year'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php if ($isRegOpen && !in_array($application['application_status'], ['Withdrawn', 'Cancelled', 'Rejected'])): ?>
                                    <td class="text-center">
                                        <?php if ($currentStudentId !== (int)$application['student_id'] && (int)$member['student_id'] === $currentStudentId): ?>
                                            <form action="<?= base_url('/student/teams/members/remove') ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to leave this team?');">
                                                <input type="hidden" name="team_id" value="<?= htmlspecialchars((string)$team['team_id'], ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="member_student_id" value="<?= htmlspecialchars((string)$member['student_id'], ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Leave Team</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php 
            $isTerminal = in_array($application['application_status'], ['Withdrawn', 'Cancelled', 'Rejected']);
            if ($isRegOpen && $currentStudentId === (int)$application['student_id'] && !$isTerminal && count($members) < (int)$event['max_team_size']): 
            ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Add Member</h5>
                </div>
                <div class="card-body">
                    <form action="<?= base_url('/student/teams/members') ?>" method="post" class="row g-3 align-items-end">
                        <input type="hidden" name="team_id" value="<?= htmlspecialchars((string)$team['team_id'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="col-md-8">
                            <label class="form-label">Register Number</label>
                            <input type="text" class="form-control" name="register_number" required placeholder="e.g. C4S35637">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-person-plus"></i> Add Member</button>
                        </div>
                    </form>
                    <div class="mt-3 text-muted small">
                        <i class="bi bi-info-circle"></i> Min members: <?= (int)$event['min_team_size'] ?>. Max members: <?= (int)$event['max_team_size'] ?>.
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
