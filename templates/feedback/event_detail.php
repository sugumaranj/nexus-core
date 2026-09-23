<?php
/**
 * templates/feedback/event_detail.php
 *
 * Staff-facing identified feedback detail view.
 * Used by: HOD, Staff Coordinator (/feedback/event)
 *          FIC (/feedback/fic/event)
 *          Judge (/feedback/judge/event)
 *
 * Shows full student identity per the business requirement.
 * Authorized staff only — authorization enforced in FeedbackController.
 *
 * @var string $pageTitle
 * @var array  $event
 * @var array  $summary       {response_count, avg_rating, eligible_count, response_rate, distribution}
 * @var array  $feedbackList  [{full_name, register_number, department_name, academic_year, rating, review}]
 * @var string $backUrl
 * @var bool   $hideHeader    Optional — set true when included as a partial inside another page
 */
?>
<div class="container-fluid py-4">

    <?php if (!($hideHeader ?? false)): ?>
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <a href="<?= base_url() . htmlspecialchars($backUrl ?? '/feedback', ENT_QUOTES, 'UTF-8') ?>"
               class="btn btn-sm btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="bi bi-bar-chart-line text-primary me-2"></i>Feedback Summary
            </h1>
            <p class="text-muted mb-0"><?= htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (($summary['response_count'] ?? 0) === 0): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-chat-square-text fs-1 text-secondary mb-3 d-block"></i>
                <h5 class="fw-bold text-dark">No feedback submitted yet</h5>
                <p class="mb-0">No feedback has been submitted for this event.</p>
            </div>
        </div>

    <?php else: ?>

        <!-- ===== Summary Stats ===== -->
        <div class="row g-4 mb-4">

            <!-- Overview card -->
            <div class="col-xl-4 col-lg-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="m-0 font-weight-bold text-primary">Overview</h6>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <div class="text-center mb-4">
                            <div class="display-3 fw-bold text-dark">
                                <?= number_format((float) $summary['avg_rating'], 1) ?>
                            </div>
                            <div class="text-warning fs-3 mb-1">
                                <?php
                                $avg = (int) round((float) $summary['avg_rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $avg
                                        ? '<i class="bi bi-star-fill"></i>'
                                        : '<i class="bi bi-star"></i>';
                                }
                                ?>
                            </div>
                            <div class="text-muted">Average Rating</div>
                        </div>

                        <div class="row text-center g-2">
                            <div class="col-6 border-end">
                                <div class="h4 fw-bold text-dark mb-0"><?= (int) $summary['response_count'] ?></div>
                                <div class="small text-muted">Responses</div>
                            </div>
                            <div class="col-6">
                                <div class="h4 fw-bold text-dark mb-0"><?= (float) $summary['response_rate'] ?>%</div>
                                <div class="small text-muted">Response Rate</div>
                            </div>
                        </div>
                        <div class="text-center mt-3 small text-muted">
                            Out of <?= (int) $summary['eligible_count'] ?> eligible participants
                        </div>
                    </div>
                </div>
            </div>

            <!-- Distribution card -->
            <div class="col-xl-8 col-lg-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="m-0 font-weight-bold text-primary">Rating Distribution</h6>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center px-4">
                        <?php
                        $maxCount = max($summary['distribution'] ?: [0]);
                        for ($i = 5; $i >= 1; $i--):
                            $count = $summary['distribution'][$i] ?? 0;
                            $pct   = ($maxCount > 0 && $summary['response_count'] > 0)
                                     ? ($count / $summary['response_count']) * 100
                                     : 0;
                        ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="text-muted fw-bold me-2" style="width:25px;">
                                    <?= $i ?> <i class="bi bi-star-fill text-warning" style="font-size:.7rem;"></i>
                                </div>
                                <div class="progress flex-grow-1" style="height:12px;">
                                    <div class="progress-bar <?= $i >= 4 ? 'bg-success' : ($i == 3 ? 'bg-warning' : 'bg-danger') ?>"
                                         role="progressbar"
                                         style="width:<?= $pct ?>%"
                                         aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <div class="ms-3 text-muted" style="width:30px;text-align:right;"><?= $count ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Student Feedback List ===== -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-people-fill me-2"></i>Student Feedback
                </h6>
                <span class="badge bg-secondary"><?= count($feedbackList) ?> response<?= count($feedbackList) !== 1 ? 's' : '' ?></span>
            </div>
            <div class="card-body p-0">

                <?php if (empty($feedbackList)): ?>
                    <div class="p-4 text-center text-muted">No written reviews provided.</div>

                <?php else: ?>
                    <!-- Desktop table -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <th>Register No.</th>
                                    <th>Department</th>
                                    <th class="text-center">Year</th>
                                    <th class="text-center">Rating</th>
                                    <th>Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedbackList as $idx => $fb): ?>
                                    <tr>
                                        <td class="text-muted"><?= $idx + 1 ?></td>
                                        <td class="fw-semibold">
                                            <?= htmlspecialchars($fb['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= htmlspecialchars($fb['register_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            <?= htmlspecialchars($fb['department_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $yr = (string) ($fb['academic_year'] ?? '');
                                            $yrLabel = match ($yr) {
                                                '1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV',
                                                default => $yr
                                            };
                                            echo htmlspecialchars($yrLabel, ENT_QUOTES, 'UTF-8');
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="text-warning">
                                                <?php
                                                $r = (int) ($fb['rating'] ?? 0);
                                                for ($s = 1; $s <= 5; $s++) {
                                                    echo $s <= $r
                                                        ? '<i class="bi bi-star-fill"></i>'
                                                        : '<i class="bi bi-star text-secondary"></i>';
                                                }
                                                ?>
                                            </span>
                                            <span class="ms-1 text-muted small">(<?= $r ?>)</span>
                                        </td>
                                        <td>
                                            <?php if (!empty($fb['review'])): ?>
                                                <span class="text-dark">
                                                    <?= nl2br(htmlspecialchars($fb['review'], ENT_QUOTES, 'UTF-8')) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic small">No review provided</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile cards -->
                    <div class="d-md-none">
                        <?php foreach ($feedbackList as $idx => $fb):
                            $r = (int) ($fb['rating'] ?? 0);
                        ?>
                            <div class="border-bottom p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div>
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($fb['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?= htmlspecialchars($fb['register_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                            &bull; <?= htmlspecialchars($fb['department_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </div>
                                    <div class="text-warning small">
                                        <?php for ($s = 1; $s <= 5; $s++) {
                                            echo $s <= $r ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star text-secondary"></i>';
                                        } ?>
                                    </div>
                                </div>
                                <?php if (!empty($fb['review'])): ?>
                                    <p class="mb-0 small text-dark">
                                        <?= nl2br(htmlspecialchars($fb['review'], ENT_QUOTES, 'UTF-8')) ?>
                                    </p>
                                <?php else: ?>
                                    <p class="mb-0 small text-muted fst-italic">No review provided</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>
