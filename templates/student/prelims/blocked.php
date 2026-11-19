<?php
declare(strict_types=1);

$check = $check ?? ['code' => 'BLOCKED', 'message' => 'Access denied.'];
$quiz  = $check['quiz'] ?? [];
$code  = (string) ($check['code'] ?? 'BLOCKED');
$qid   = (int) ($quiz['quiz_id'] ?? ($_GET['quiz_id'] ?? 0));
?>
<div class="container py-5" style="max-width:640px">
    <div class="card border-0 shadow text-center">
        <div class="card-body p-5">
            <div class="mb-3">
                <i class="bi bi-shield-exclamation text-danger" style="font-size:3.5rem"></i>
            </div>
            <h3 class="fw-bold mb-2">Quiz Access Restricted</h3>
            <p class="text-muted mb-4"><?= htmlspecialchars((string)$check['message']) ?></p>

            <?php if ($code === 'DUPLICATE_SESSION' && $qid > 0): ?>
                <div class="border rounded p-3 bg-light text-start mb-4">
                    <div class="small fw-semibold mb-2">Had a verified lab PC crash? Enter FIC Emergency Re-Entry Code:</div>
                    <form method="POST" action="<?= base_url('/student/prelims/reenter') ?>" class="input-group input-group-sm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($csrfToken ?? '')) ?>">
                        <input type="hidden" name="quiz_id" value="<?= $qid ?>">
                        <input type="text" class="form-control text-uppercase" name="reentry_code" placeholder="8-CHAR CODE" required>
                        <button type="submit" class="btn btn-warning text-dark fw-semibold">Resume</button>
                    </form>
                </div>
            <?php endif; ?>

            <a href="<?= base_url('/student/prelims') ?>" class="btn btn-primary px-4">&larr; Back to My Prelims Quizzes</a>
        </div>
    </div>
</div>
<script src="<?= asset('assets/js/prelims/offline-flush.js') ?>"></script>
