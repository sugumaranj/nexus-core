<?php
/**
 * templates/feedback/index.php
 * 
 * @var string $pageTitle
 * @var array $symposiums
 * @var int $selectedSymposiumId
 * @var array $events
 */
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800"><i class="bi bi-chat-square-text text-primary me-2"></i>Event Feedback</h1>
            <p class="text-muted mb-0">View feedback summaries for finalized events.</p>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body">
            <form action="" method="GET" class="row align-items-center g-3">
                <div class="col-auto">
                    <label for="symposium_id" class="col-form-label fw-bold">Select Symposium:</label>
                </div>
                <div class="col-auto flex-grow-1" style="max-width:400px;">
                    <select name="symposium_id" id="symposium_id" class="form-select" onchange="this.form.submit()">
                        <?php if(empty($symposiums)): ?>
                            <option value="">No symposiums found</option>
                        <?php else: ?>
                            <?php foreach ($symposiums as $s): ?>
                                <option value="<?= (int)$s['symposium_id'] ?>" <?= $s['symposium_id'] == $selectedSymposiumId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['title']) ?> (<?= date('Y', strtotime($s['event_start_date'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Events List -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom">
            <h6 class="m-0 font-weight-bold text-primary">Finalized Events</h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($events)): ?>
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-inbox fs-1 mb-3 d-block text-secondary"></i>
                    <p class="mb-0">No events with finalized attendance found for this symposium.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Event Name</th>
                                <th>Date</th>
                                <th class="text-center">Responses</th>
                                <th>Avg Rating</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $ev): ?>
                                <?php $sum = $ev['feedback_summary'] ?? []; ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($ev['event_name']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($ev['category'] ?? 'General') ?></div>
                                    </td>
                                    <td><?= date('d M Y', strtotime($ev['event_date'])) ?></td>
                                    <td class="text-center">
                                        <?php if (($sum['response_count'] ?? 0) > 0): ?>
                                            <span class="badge bg-success rounded-pill px-3 py-2 fs-6">
                                                <?= (int)$sum['response_count'] ?> / <?= (int)($sum['eligible_count'] ?? 0) ?>
                                            </span>
                                            <div class="small text-muted mt-1"><?= $sum['response_rate'] ?? 0 ?>% rate</div>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">0 Responses</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($sum['response_count'] ?? 0) > 0): ?>
                                            <div class="text-warning fw-bold fs-5">
                                                <?= number_format($sum['avg_rating'] ?? 0, 1) ?> <i class="bi bi-star-fill fs-6"></i>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <a href="<?= base_url() ?>/feedback/event?id=<?= (int)$ev['symposium_event_id'] ?>" class="btn btn-sm btn-outline-primary fw-bold">
                                            View Feedback
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
