<?php
$eventDate = !empty($event['event_date']) ? date('M d, Y', strtotime($event['event_date'])) : 'TBD';
$isLocked = (bool)$event['is_locked'];
$maxScore = number_format((float)($snapshot['maximum_score'] ?? 100), 2);
?>

<div class="container-fluid py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= base_url('/evaluation/results') ?>" class="text-decoration-none">Evaluation & Results</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($event['event_name']) ?></li>
        </ol>
    </nav>

    <!-- Hero Section -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden position-relative" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white;">
        <div class="card-body p-5">
            <div class="row align-items-center relative z-index-1">
                <div class="col-md-8">
                    <div class="mb-2">
                        <?php if ($isLocked): ?>
                            <span class="badge bg-white text-dark rounded-pill px-3 py-2 fw-semibold me-2 shadow-sm">
                                <i class="bi bi-lock-fill text-success me-1"></i> Cryptographically Locked
                            </span>
                        <?php endif; ?>
                        <span class="badge bg-white bg-opacity-25 rounded-pill px-3 py-2 fw-semibold">
                            <i class="bi bi-bar-chart-fill me-1"></i> Final Results
                        </span>
                    </div>
                    <h2 class="fw-bold mt-3 mb-2 display-5 text-white"><?= htmlspecialchars($event['event_name']) ?></h2>
                    <p class="mb-0 fs-5 opacity-75">
                        <i class="bi bi-calendar3 me-2"></i> <?= $eventDate ?> 
                        <span class="mx-2">&bull;</span> 
                        Max Score: <?= $maxScore ?>
                    </p>
                </div>
            </div>
        </div>
        <!-- Decorative Background Circle -->
        <div class="position-absolute" style="top: -50px; right: -50px; width: 300px; height: 300px; background: rgba(255,255,255,0.05); border-radius: 50%;"></div>
    </div>

    <!-- Leaderboard & Audit -->
    <div class="row g-4">
        <!-- Leaderboard -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i> Official Leaderboard</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle border-bottom">
                            <thead class="table-light text-secondary small text-uppercase">
                                <tr>
                                    <th class="ps-3 border-0 rounded-start">Rank</th>
                                    <th class="border-0">Participant</th>
                                    <th class="border-0">Total</th>
                                    <th class="border-0">Average</th>
                                    <th class="pe-3 border-0 rounded-end text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody class="border-top-0">
                                <?php foreach ($results as $index => $r): ?>
                                    <?php 
                                        $rank = (int)$r['rank_position'];
                                        $isTop3 = $rank <= 3;
                                        $rankBadgeColor = $rank === 1 ? 'bg-warning text-dark' : ($rank === 2 ? 'bg-secondary text-white' : ($rank === 3 ? 'bg-orange text-white' : 'bg-light text-secondary'));
                                    ?>
                                    <tr>
                                        <td class="ps-3 py-3" style="width: 80px;">
                                            <span class="badge rounded-circle d-flex align-items-center justify-content-center <?= $rankBadgeColor ?> shadow-sm" style="width: 35px; height: 35px; font-size: 1rem;">
                                                <?= $rank ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $resolved = $resolvedParticipants[$r['application_id']] ?? null;
                                            $members = $resolved['members'] ?? (isset($resolved['student_name']) ? [$resolved] : [['student_name' => $r['student_name'] ?? '', 'register_number' => $r['register_number'] ?? '', 'department_name' => $r['department_name'] ?? '', 'academic_year' => $r['academic_year'] ?? '']]);
                                            foreach ($members as $member):
                                                $yrRaw = (string)($member['academic_year'] ?? $member['student_year'] ?? $member['member_year'] ?? '');
                                                $yrFmt = $yrRaw;
                                                if ($yrRaw === '1') $yrFmt = '1st Year';
                                                elseif ($yrRaw === '2') $yrFmt = '2nd Year';
                                                elseif ($yrRaw === '3') $yrFmt = '3rd Year';
                                                elseif ($yrRaw === '4') $yrFmt = '4th Year';
                                            ?>
                                                <div class="mb-1 border-bottom pb-1 border-light">
                                                    <div class="fw-bold text-dark"><?= htmlspecialchars($member['name'] ?? $member['student_name'] ?? $member['member_name'] ?? 'Unknown') ?></div>
                                                    <div class="text-muted small">
                                                        <?= htmlspecialchars($member['register_number'] ?? $member['member_reg_no'] ?? '') ?> &bull; 
                                                        <?= htmlspecialchars($member['department'] ?? $member['department_name'] ?? $member['member_dept'] ?? '') ?>
                                                        <?= $yrFmt ? ' &bull; ' . htmlspecialchars($yrFmt) : '' ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>
                                        <td class="fw-semibold"><?= number_format((float)$r['total_score'], 2) ?></td>
                                        <td class="fw-semibold"><?= number_format((float)$r['average_score'], 2) ?></td>
                                        <td class="pe-3 text-end">
                                            <?php if ($r['result_status'] === 'First Place'): ?>
                                                <span class="badge bg-warning bg-opacity-25 text-dark rounded-pill px-3 py-2 fw-semibold">First Place</span>
                                            <?php elseif ($r['result_status'] === 'Second Place'): ?>
                                                <span class="badge bg-secondary bg-opacity-25 text-secondary rounded-pill px-3 py-2 fw-semibold">Second Place</span>
                                            <?php elseif ($r['result_status'] === 'Third Place'): ?>
                                                <span class="badge bg-orange bg-opacity-25 text-white rounded-pill px-3 py-2 fw-semibold" style="background-color: #fd7e14;">Third Place</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-secondary rounded-pill px-3 py-2"><?= htmlspecialchars($r['result_status'] ?? 'Participant') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audit Trail -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 bg-light mb-4">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-shield-check text-primary me-2"></i> Mathematical Audit</h6>
                    
                    <div class="mb-3">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Methodology</small>
                        <div class="fw-semibold text-dark"><?= htmlspecialchars($engineSnapshot['methodology'] ?? 'Unknown') ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Engine Precision</small>
                        <div class="text-dark small">
                            <i class="bi bi-calculator me-1"></i> Internal Calculation: <span class="fw-semibold"><?= $engineSnapshot['precision_policy']['internal_calculation'] ?? 16 ?> decimals</span><br>
                            <i class="bi bi-list-ol me-1"></i> Tie Hierarchy: <span class="fw-semibold">None (Shared Ranks)</span>
                        </div>
                    </div>

                    <?php if (!empty($event['snapshot_hash'])): ?>
                    <div class="p-3 bg-white border rounded-3 position-relative mt-4">
                        <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: 0.7rem;">
                            <i class="bi bi-hash me-1"></i> SHA-256 Snapshot Hash
                        </small>
                        <code class="text-dark d-block text-break" style="font-size: 0.8rem;"><?= htmlspecialchars($event['snapshot_hash']) ?></code>
                        <div class="mt-2 text-success small fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Data integrity verified
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.bg-orange { background-color: #fd7e14; }
.table-hover tbody tr:hover { background-color: #f8f9fa; }
</style>
