<?php
declare(strict_types=1);

$quiz    = $quiz ?? [];
$attempt = $attempt ?? [];
?>
<div class="container py-5" style="max-width:640px">
    <div class="card border-0 shadow text-center">
        <div class="card-body p-5">
            <i class="bi bi-check-circle-fill text-success mb-3 d-block" style="font-size:3.5rem"></i>
            <h3 class="fw-bold mb-2">Prelims Test Submitted</h3>
            <p class="text-muted mb-4"><?= htmlspecialchars((string)($quiz['title'] ?? '')) ?></p>

            <div class="row g-2 text-start bg-light rounded p-3 mb-4 small">
                <div class="col-6 text-muted">Status:</div>
                <div class="col-6 fw-semibold"><?= htmlspecialchars((string)($attempt['status'] ?? 'Submitted')) ?></div>
                <div class="col-6 text-muted">Questions Answered:</div>
                <div class="col-6 fw-semibold"><?= (int)($attempt['answered_count'] ?? 0) ?> / <?= (int)($attempt['total_questions'] ?? 0) ?></div>
                <div class="col-6 text-muted">Time Taken:</div>
                <div class="col-6 fw-semibold"><?= gmdate('i:s', (int)($attempt['time_taken_seconds'] ?? 0)) ?></div>
                <div class="col-6 text-muted">Submitted At:</div>
                <div class="col-6 fw-semibold"><?= htmlspecialchars((string)($attempt['submitted_at'] ?? '-')) ?></div>
                <div class="col-6 text-muted">Receipt Ref:</div>
                <div class="col-6 fw-semibold"><code>PRE-<?= (int)($attempt['attempt_id'] ?? 0) ?></code></div>
            </div>

            <p class="small text-muted">Shortlisted finalists will be notified automatically once the Faculty In-Charge finalizes results.</p>
            <a href="<?= base_url('/student/prelims') ?>" class="btn btn-primary px-4">Back to My Prelims Quizzes</a>
        </div>
    </div>
</div>
<script src="<?= asset('assets/js/prelims/offline-flush.js') ?>"></script>
