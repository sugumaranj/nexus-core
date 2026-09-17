<?php
/**
 * templates/student/feedback.php
 * 
 * @var string $pageTitle
 * @var array $pending
 * @var array $submitted
 */
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1 fw-bold text-dark"><i class="bi bi-chat-square-text text-primary me-2"></i>Event Feedback</h2>
            <p class="text-muted mb-0">Share your experience anonymously for events you attended.</p>
        </div>
    </div>

    <!-- Privacy Notice -->
    <div class="alert alert-info border-start border-4 border-info d-flex align-items-start gap-3">
        <i class="bi bi-shield-lock-fill fs-3"></i>
        <div>
            <h5 class="alert-heading h6 fw-bold mb-1">Your privacy is protected</h5>
            <p class="mb-0 small">
                Your feedback is strictly anonymous. Your identity (name, register number, etc.) is never shown to event staff, faculty, HODs, or judges. Only the combined rating and review text are visible to them.
            </p>
        </div>
    </div>

    <!-- Pending Feedback Section -->
    <h3 class="h5 fw-bold mb-3 mt-4 text-dark border-bottom pb-2">Pending Feedback</h3>
    <?php if (empty($pending)): ?>
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-body text-center py-5 text-muted">
                <i class="bi bi-calendar2-check fs-1 mb-3 d-block text-secondary"></i>
                <h5 class="h6 fw-bold text-dark">No feedback currently available</h5>
                <p class="small mb-0">Feedback becomes available after attendance is finalized and your attendance is marked as Present or Late.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php foreach ($pending as $app): ?>
                <div class="col">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="mb-auto">
                                <span class="badge bg-primary-subtle text-primary mb-2">
                                    <i class="bi bi-tag-fill me-1"></i><?= htmlspecialchars($app['symposium_title'] ?? '') ?>
                                </span>
                                <h5 class="card-title h6 fw-bold text-dark mb-1">
                                    <?= htmlspecialchars($app['event_name'] ?? '') ?>
                                </h5>
                                <p class="small text-muted mb-3">
                                    <i class="bi bi-calendar-event me-1"></i>
                                    <?= date('d M Y', strtotime($app['event_date'])) ?>
                                </p>
                            </div>
                            <div class="mt-3">
                                <a href="<?= base_url() ?>/student/feedback/event?id=<?= (int)$app['symposium_event_id'] ?>" class="btn btn-primary w-100 fw-bold">
                                    <i class="bi bi-pencil-square me-1"></i> Give Feedback
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Submitted Feedback Section -->
    <h3 class="h5 fw-bold mb-3 mt-5 text-dark border-bottom pb-2">Submitted Feedback</h3>
    <?php if (empty($submitted)): ?>
        <div class="card shadow-sm border-0 bg-light">
            <div class="card-body text-center py-4 text-muted">
                <p class="small mb-0">You have not submitted any feedback yet.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 g-4">
            <?php foreach ($submitted as $app): ?>
                <div class="col">
                    <div class="card shadow-sm border-0 border-start border-success border-4 h-100 bg-light">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle-fill me-1"></i> Feedback Submitted
                                </span>
                            </div>
                            <h5 class="card-title h6 fw-bold text-dark mb-1">
                                <?= htmlspecialchars($app['event_name'] ?? '') ?>
                            </h5>
                            <p class="small text-muted mb-3">
                                <?= htmlspecialchars($app['symposium_title'] ?? '') ?>
                            </p>
                            
                            <div class="mt-2 text-warning fs-5">
                                <?php 
                                $rating = (int)($app['_submitted_rating'] ?? 0);
                                for ($i = 1; $i <= 5; $i++): 
                                    if ($i <= $rating): ?>
                                        <i class="bi bi-star-fill"></i>
                                    <?php else: ?>
                                        <i class="bi bi-star"></i>
                                    <?php endif;
                                endfor; 
                                ?>
                                <span class="text-muted ms-2 fs-6">(Your Rating)</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
