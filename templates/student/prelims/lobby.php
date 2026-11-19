<?php
declare(strict_types=1);

$quiz  = $quiz ?? [];
$check = $check ?? [];
$qid   = (int) ($quiz['quiz_id'] ?? 0);
$isResume = (($check['mode'] ?? '') === 'resume');
?>
<div class="container py-4" style="max-width:780px">
    <div class="card border-0 shadow">
        <div class="card-header bg-primary text-white py-3">
            <h4 class="mb-0 fw-bold"><i class="bi bi-shield-check me-2"></i><?= htmlspecialchars((string)($quiz['title'] ?? 'Prelims Quiz')) ?></h4>
            <div class="small opacity-75"><?= htmlspecialchars((string)($quiz['event_name'] ?? '')) ?> · Pre-Exam System &amp; Device Verification</div>
        </div>
        <div class="card-body p-4">
            <!-- Automated Hardware Check Box -->
            <div id="deviceCheckAlert" class="alert alert-info d-flex align-items-center gap-2">
                <span class="spinner-border spinner-border-sm"></span>
                <span>Verifying desktop/laptop hardware environment...</span>
            </div>

            <div class="row g-3 mb-4 text-center">
                <div class="col-4"><div class="border rounded p-2 bg-light"><div class="small text-muted">Questions</div><div class="fw-bold fs-5"><?= (int)$quiz['questions_per_student'] ?></div></div></div>
                <div class="col-4"><div class="border rounded p-2 bg-light"><div class="small text-muted">Duration</div><div class="fw-bold fs-5"><?= round((int)$quiz['duration_seconds'] / 60, 1) ?> min</div></div></div>
                <div class="col-4"><div class="border rounded p-2 bg-light"><div class="small text-muted">Attendance</div><div class="fw-bold fs-5 text-success"><?= htmlspecialchars((string)($check['attendance_status'] ?? 'Verified')) ?></div></div></div>
            </div>

            <h6 class="fw-bold mb-2">Mandatory Exam Rules</h6>
            <ul class="small text-muted mb-3">
                <li><strong>One Session Only:</strong> Only one time a student can attend the test. Logging in from another browser/device is blocked and will not interrupt an active session.</li>
                <li><strong>Desktop / Laptop Only:</strong> Mobile phones and tablets (even in Desktop Site mode) are strictly blocked.</li>
                <li><strong>Fullscreen &amp; Browser Lockdown:</strong> Exiting fullscreen, switching tabs/windows, or copy/paste/right-click/DevTools shortcuts are recorded as violations<?= !empty($quiz['violation_auto_submit']) ? ' (Auto-submits at ' . (int)$quiz['max_violations'] . ' violations)' : '' ?>.</li>
                <li><strong>Submission Required to Leave:</strong> To exit the test early, click <em>Submit &amp; Exit</em>. Timer runs continuously on the server.</li>
                <li><strong>Low-Bandwidth Safe:</strong> Works smoothly even on 1–3 kbps networks; answers save locally immediately and sync automatically.</li>
            </ul>

            <?php if (!empty($quiz['instructions'])): ?>
                <div class="alert alert-light border small mb-3"><strong>Event Instructions:</strong><br><?= nl2br(htmlspecialchars((string)$quiz['instructions'])) ?></div>
            <?php endif; ?>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="agreeRules">
                <label class="form-check-label small fw-semibold" for="agreeRules">I confirm I am using a desktop/laptop computer and agree to all exam rules.</label>
            </div>

            <div id="startError" class="alert alert-danger d-none small"></div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="<?= base_url('/student/prelims') ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="button" id="btnStartQuiz" class="btn btn-success btn-lg px-4 fw-bold" disabled>
                    <i class="bi bi-play-fill me-1"></i><?= $isResume ? 'Resume Exam Now' : 'Start Exam in Fullscreen' ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('assets/js/prelims/device-guard.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const guardRes = window.NexusDeviceGuard.evaluate();
    const box = document.getElementById('deviceCheckAlert');
    const chk = document.getElementById('agreeRules');
    const btn = document.getElementById('btnStartQuiz');
    const errBox = document.getElementById('startError');
    const blockMobile = <?= (int)($quiz['block_mobile'] ?? 1) ?>;

    let deviceOk = (!blockMobile || guardRes.allowed);

    if (deviceOk) {
        box.className = 'alert alert-success small mb-4';
        box.innerHTML = '<i class="bi bi-pc-display me-2"></i><strong>Desktop/Laptop Verified.</strong> Hardware environment passed all checks.';
    } else {
        box.className = 'alert alert-danger small mb-4';
        box.innerHTML = '<i class="bi bi-phone-flip me-2"></i><strong>Access Denied — Mobile Device Detected:</strong> Mobile phones and tablets (including Desktop Site mode) are strictly blocked for this quiz.<br><span class="opacity-75">' + guardRes.reasons.join(' ') + '</span>';
    }

    function syncBtn() { btn.disabled = !(deviceOk && chk.checked); }
    chk.addEventListener('change', syncBtn);
    syncBtn();

    btn.addEventListener('click', async function () {
        btn.disabled = true;
        errBox.classList.add('d-none');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Preparing Shuffled Exam...';

        try {
            const res = await fetch(<?= json_encode(base_url('/student/prelims/start')) ?>, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    csrf_token: <?= json_encode($csrfToken) ?>,
                    quiz_id: <?= $qid ?>,
                    device: guardRes.signals
                })
            });
            const j = await res.json();
            if (!j.success) {
                errBox.textContent = j.message || 'Unable to start quiz.';
                errBox.classList.remove('d-none');
                btn.disabled = false;
                btn.textContent = 'Retry';
                return;
            }
            window.location.href = j.exam_url;
        } catch (e) {
            errBox.textContent = 'Network error while initializing quiz. Please retry.';
            errBox.classList.remove('d-none');
            btn.disabled = false;
            btn.textContent = 'Retry';
        }
    });
});
</script>
