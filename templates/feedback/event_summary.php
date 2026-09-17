<?php
/**
 * templates/feedback/event_summary.php
 * 
 * @var string $pageTitle
 * @var array $event
 * @var array $summary
 * @var array $reviews
 * @var string $backUrl
 */
?>
<div class="container-fluid py-4">
    <?php if (!($hideHeader ?? false)): ?>
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <div>
            <a href="<?= base_url() . ($backUrl ?? '/feedback') ?>" class="btn btn-sm btn-outline-secondary mb-2">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>
            <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-bar-chart-line text-primary me-2"></i>Feedback Summary</h1>
            <p class="text-muted mb-0"><?= htmlspecialchars($event['event_name']) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (($summary['response_count'] ?? 0) === 0): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-chat-square-text fs-1 text-secondary mb-3 d-block"></i>
                <h5 class="fw-bold text-dark">No feedback available</h5>
                <p class="mb-0">No anonymous feedback has been submitted for this event yet.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4 mb-4">
            <!-- Stats overview -->
            <div class="col-xl-4 col-lg-5">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="m-0 font-weight-bold text-primary">Overview</h6>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-center">
                        <div class="text-center mb-4">
                            <div class="display-3 fw-bold text-dark"><?= number_format($summary['avg_rating'], 1) ?></div>
                            <div class="text-warning fs-3 mb-1">
                                <?php 
                                $avg = round($summary['avg_rating']);
                                for($i=1; $i<=5; $i++) {
                                    echo $i <= $avg ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
                                }
                                ?>
                            </div>
                            <div class="text-muted">Average Rating</div>
                        </div>
                        
                        <div class="row text-center g-2">
                            <div class="col-6 border-end">
                                <div class="h4 fw-bold text-dark mb-0"><?= (int)$summary['response_count'] ?></div>
                                <div class="small text-muted">Responses</div>
                            </div>
                            <div class="col-6">
                                <div class="h4 fw-bold text-dark mb-0"><?= (float)$summary['response_rate'] ?>%</div>
                                <div class="small text-muted">Response Rate</div>
                            </div>
                        </div>
                        <div class="text-center mt-3 small text-muted">
                            Out of <?= (int)$summary['eligible_count'] ?> eligible participants
                        </div>
                    </div>
                </div>
            </div>

            <!-- Distribution -->
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
                            $pct = $maxCount > 0 ? ($count / $summary['response_count']) * 100 : 0;
                        ?>
                            <div class="d-flex align-items-center mb-3">
                                <div class="text-muted fw-bold me-2" style="width: 25px;"><?= $i ?> <i class="bi bi-star-fill text-warning" style="font-size:0.7rem;"></i></div>
                                <div class="progress flex-grow-1" style="height: 12px;">
                                    <div class="progress-bar <?= $i >= 4 ? 'bg-success' : ($i == 3 ? 'bg-warning' : 'bg-danger') ?>" 
                                         role="progressbar" style="width: <?= $pct ?>%" 
                                         aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="ms-3 text-muted" style="width: 30px; text-align:right;"><?= $count ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reviews -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Anonymous Reviews</h6>
                <span class="badge bg-secondary"><?= count($reviews) ?> text reviews</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($reviews)): ?>
                    <div class="p-4 text-center text-muted">
                        No written reviews provided.
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($reviews as $index => $r): ?>
                            <li class="list-group-item p-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-secondary">Anonymous Response #<?= $index + 1 ?></span>
                                    <div class="text-warning">
                                        <?php for($i=1; $i<=5; $i++) echo $i <= $r['rating'] ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>'; ?>
                                    </div>
                                </div>
                                <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($r['review'])) ?></p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
