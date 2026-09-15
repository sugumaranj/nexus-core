<?php
declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">My Registrations</h2>
    </div>

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

    <?php if (empty($applications)): ?>
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3">No Registrations Yet</h5>
                <p class="text-muted">You haven't registered for any events yet.</p>
                <a href="<?= base_url('/student/symposiums') ?>" class="btn btn-primary mt-2">Browse Symposiums</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Application No</th>
                            <th>Event</th>
                            <th>Symposium</th>
                            <th>Date &amp; Time</th>
                            <th>Venue</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Registered On</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td><span class="fw-bold"><?= htmlspecialchars($app['application_no'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <?= htmlspecialchars($app['event_name'], ENT_QUOTES, 'UTF-8') ?>
                                    <small class="text-muted d-block"><?= htmlspecialchars($app['event_code'], ENT_QUOTES, 'UTF-8') ?></small>
                                </td>
                                <td><?= htmlspecialchars($app['symposium_title'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if (!empty($app['event_date'])): ?>
                                    <div class="fw-semibold"><?= date('d M Y', strtotime($app['event_date'])) ?></div>
                                    <?php if (!empty($app['start_time']) && !empty($app['end_time'])): ?>
                                    <small class="text-muted">
                                        <?= substr($app['start_time'], 0, 5) ?>–<?= substr($app['end_time'], 0, 5) ?>
                                    </small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted small fst-italic">TBA</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($app['venue_name'])): ?>
                                    <div class="fw-semibold"><?= htmlspecialchars($app['venue_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <small class="text-muted">
                                        <?php if (!empty($app['venue_code'])): ?>
                                        <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($app['venue_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($app['building_name'])): ?><?= htmlspecialchars($app['building_name'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                        <?php if (!empty($app['floor'])): ?> · Floor <?= htmlspecialchars($app['floor'], ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                                    </small>
                                    <?php else: ?>
                                    <span class="text-muted small fst-italic">TBA</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($app['application_type'] === 'Team'): ?>
                                        <span class="badge bg-success"><i class="bi bi-people"></i> Team</span>
                                    <?php else: ?>
                                        <span class="badge bg-info"><i class="bi bi-person"></i> Individual</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'secondary';
                                    switch($app['application_status']) {
                                        case 'Approved': $statusClass = 'success'; break;
                                        case 'Pending': $statusClass = 'warning text-dark'; break;
                                        case 'Rejected': $statusClass = 'danger'; break;
                                        case 'Withdrawn': $statusClass = 'dark'; break;
                                    }
                                    ?>
                                    <span class="badge bg-<?= $statusClass ?>"><?= htmlspecialchars($app['application_status'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td><?= \App\Helpers\DateHelper::dateTime($app['applied_at']) ?></td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <?php if ($app['application_type'] === 'Team' && $app['team_id']): ?>
                                            <a href="<?= base_url('/student/teams/view?id=' . $app['team_id']) ?>" class="btn btn-sm btn-primary" title="Manage Team">
                                                <i class="bi bi-people-fill"></i> Manage
                                            </a>
                                        <?php endif; ?>

                                        <?php 
                                            $isRegistrationOpen = false;
                                            if (!empty($app['registration_end'])) {
                                                $isRegistrationOpen = (date('Y-m-d H:i:s') <= $app['registration_end']);
                                            }
                                        ?>
                                        <?php if ($isRegistrationOpen && (int)$app['student_id'] === $currentStudentId && ($app['application_status'] === 'Approved' || $app['application_status'] === 'Pending')): ?>
                                            <form action="<?= base_url('/student/applications/withdraw') ?>" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to withdraw from this event? This action cannot be undone.');">
                                                <input type="hidden" name="application_id" value="<?= htmlspecialchars((string)$app['application_id'], ENT_QUOTES, 'UTF-8') ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Withdraw">
                                                    <i class="bi bi-x-circle"></i> Withdraw
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
