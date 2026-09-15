<?php
declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('/coordinator/registrations') ?>">Registration Management</a></li>
            <li class="breadcrumb-item"><a href="<?= base_url('/coordinator/registrations/summary?symposium_id=' . $symposium['symposium_id']) ?>"><?= htmlspecialchars($symposium['title'], ENT_QUOTES, 'UTF-8') ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Team Report</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0">Team Report - <?= htmlspecialchars($symposium['title'], ENT_QUOTES, 'UTF-8') ?> 
            <span class="badge bg-info text-dark fs-6 ms-2"><?= count($teams ?? []) ?> Teams</span>
        </h2>
    </div>

    <?php if (empty($teams)): ?>
        <div class="alert alert-info">No teams found for this symposium.</div>
    <?php else: ?>
        <div class="accordion" id="teamsAccordion">
            <?php foreach ($teams as $teamId => $teamData): ?>
                <div class="accordion-item mb-3 border rounded shadow-sm">
                    <h2 class="accordion-header" id="heading-<?= $teamId ?>">
                        <button class="accordion-button collapsed px-4 py-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $teamId ?>" aria-expanded="false" aria-controls="collapse-<?= $teamId ?>">
                            <div class="d-flex w-100 justify-content-between align-items-center pe-3">
                                <div>
                                    <span class="fw-bold fs-5">App No: <?= htmlspecialchars($teamData['application_no'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-muted ms-2">(Manager: <?= htmlspecialchars($teamData['manager_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') ?>)</span>
                                </div>
                                <div>
                                    <span class="badge bg-secondary me-2"><?= htmlspecialchars($teamData['event_code'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="badge bg-primary"><?= htmlspecialchars($teamData['event_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="badge bg-info text-dark ms-2"><?= count($teamData['members']) ?> Members</span>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="collapse-<?= $teamId ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?= $teamId ?>" data-bs-parent="#teamsAccordion">
                        <div class="accordion-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">S.No</th>
                                            <th>Name</th>
                                            <th>Register No</th>
                                            <th>Department</th>
                                            <th>Year</th>
                                            <th>Joined At</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teamData['members'] as $idx => $member): ?>
                                            <tr>
                                                <td class="ps-4"><?= $idx + 1 ?></td>
                                                <td><?= htmlspecialchars($member['member_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($member['member_reg_no'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($member['member_dept'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars((string)$member['member_year'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= \App\Helpers\DateHelper::date($member['joined_at']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
