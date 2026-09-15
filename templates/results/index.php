<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-bold text-dark">Evaluation & Results</h4>
            <p class="text-muted mb-0">View final published results and cryptographic rankings for completed events.</p>
        </div>
    </div>

    <?php if (empty($events)): ?>
        <div class="card border-0 shadow-sm rounded-4 text-center py-5">
            <div class="card-body">
                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3 text-dark fw-bold">No Published Results Found</h5>
                <p class="text-muted mb-0">There are currently no symposium events with published evaluations.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            <?php foreach ($events as $event): ?>
                <div class="col">
                    <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative hover-lift">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2 fw-semibold">
                                    <i class="bi bi-check-circle-fill me-1"></i> Results Published
                                </span>
                                <?php if ($event['is_locked']): ?>
                                    <span class="badge bg-dark bg-opacity-10 text-dark rounded-pill px-2 py-2" title="Cryptographically Locked">
                                        <i class="bi bi-lock-fill"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <h5 class="fw-bold text-dark mb-1 text-truncate" title="<?= htmlspecialchars($event['event_name']) ?>">
                                <?= htmlspecialchars($event['event_name']) ?>
                            </h5>
                            <p class="text-muted small mb-3">
                                <i class="bi bi-calendar-event me-1"></i> <?= htmlspecialchars($event['symposium_name']) ?>
                            </p>

                            <div class="d-flex align-items-center text-secondary small mb-4">
                                <div class="me-3">
                                    <i class="bi bi-calendar3 me-1"></i> <?= !empty($event['event_date']) ? date('M d, Y', strtotime($event['event_date'])) : 'TBD' ?>
                                </div>
                            </div>
                            
                            <a href="<?= base_url('/evaluation/results/event?id=' . $event['symposium_event_id']) ?>" class="btn btn-primary w-100 rounded-pill fw-semibold shadow-sm">
                                View Leaderboard <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.hover-lift {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-lift:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important;
}
</style>
