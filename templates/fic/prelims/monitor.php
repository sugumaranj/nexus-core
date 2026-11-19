<?php
declare(strict_types=1);

$quiz       = $quiz ?? [];
$attempts   = $attempts ?? [];
$violations = $violations ?? [];
$attSummary = $attSummary ?? ['present' => 0, 'late' => 0];
$qid        = (int) ($quiz['quiz_id'] ?? 0);
$qStatus    = (string) ($quiz['status'] ?? '');
$isLocked   = in_array($qStatus, ['Finalized', 'Disposed'], true);
$inProg     = count(array_filter($attempts, static fn($a) => $a['status'] === 'In_Progress'));
$submitted  = count($attempts) - $inProg;
$flagged    = count(array_filter($attempts, static fn($a) => (int)$a['violation_count'] > 0));
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><i class="bi bi-broadcast text-warning me-2"></i>Live Prelims Proctor Monitor</h3>
        <div class="text-muted small"><?= htmlspecialchars((string)($quiz['title'] ?? '')) ?> · Status: <strong><?= htmlspecialchars($qStatus) ?></strong></div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('/fic/prelims/manage?quiz_id=' . $qid) ?>" class="btn btn-outline-secondary btn-sm">&larr; Quiz Setup</a>
        <a href="<?= base_url('/fic/prelims/results?quiz_id=' . $qid) ?>" class="btn btn-success btn-sm">Results &amp; Finalize &rarr;</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Eligible (Present/Late)</div><div class="fs-3 fw-bold text-primary"><?= (int)$attSummary['present'] + (int)$attSummary['late'] ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Active In Progress</div><div class="fs-3 fw-bold text-warning" id="cntInProg"><?= $inProg ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Submitted</div><div class="fs-3 fw-bold text-success" id="cntSub"><?= $submitted ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Flagged Violations</div><div class="fs-3 fw-bold text-danger" id="cntFlag"><?= $flagged ?></div></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Candidate Sessions (Auto-refreshes every 15s unless a recovery dialog is open)</span>
        <button type="button" class="btn btn-sm btn-outline-primary" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Student</th>
                    <th>Dept</th>
                    <th>Status</th>
                    <th>Last Sync</th>
                    <th>Violations</th>
                    <th>Score</th>
                    <th class="text-end">Proctor &amp; Recovery Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attempts)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No students have started the quiz yet.</td></tr>
                <?php else: foreach ($attempts as $a): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars((string)$a['student_name']) ?></strong>
                            <div class="text-muted"><?= htmlspecialchars((string)$a['register_number']) ?></div>
                        </td>
                        <td><?= htmlspecialchars((string)$a['department_name']) ?></td>
                        <td>
                            <span class="badge bg-<?= $a['status']==='In_Progress'?'warning text-dark':(str_contains((string)$a['status'],'Violation')?'danger':'success') ?>">
                                <?= htmlspecialchars((string)$a['status']) ?>
                            </span>
                            <?php if ((int)($a['reentry_grants'] ?? 0) > 0): ?>
                                <div class="text-muted" style="font-size:.7rem;">Re-opened <?= (int)$a['reentry_grants'] ?>x</div>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars((string)($a['last_sync_at'] ?? '-')) ?></td>
                        <td><span class="badge bg-<?= (int)$a['violation_count'] > 0 ? 'danger' : 'success' ?>"><?= (int)$a['violation_count'] ?> (<?= htmlspecialchars((string)$a['integrity_level']) ?>)</span></td>
                        <td><?= $a['status'] === 'In_Progress' ? '—' : (htmlspecialchars((string)$a['raw_score']) . ' / ' . htmlspecialchars((string)$a['max_score'])) ?></td>
                        <td class="text-end">
                            <?php if ($isLocked): ?>
                                <span class="text-muted">Locked (<?= htmlspecialchars($qStatus) ?>)</span>
                            <?php elseif ($a['status'] === 'In_Progress'): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning text-dark py-0 reentry-btn" data-aid="<?= (int)$a['attempt_id'] ?>" data-name="<?= htmlspecialchars((string)$a['student_name'], ENT_QUOTES) ?>">
                                    <i class="bi bi-key me-1"></i>Re-Entry Code
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 force-btn" data-aid="<?= (int)$a['attempt_id'] ?>">
                                    Force Submit
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 reopen-btn"
                                        data-aid="<?= (int)$a['attempt_id'] ?>"
                                        data-name="<?= htmlspecialchars((string)$a['student_name'], ENT_QUOTES) ?>"
                                        data-status="<?= htmlspecialchars((string)$a['status'], ENT_QUOTES) ?>">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Re-Open (Resume)
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 reset-btn"
                                        data-aid="<?= (int)$a['attempt_id'] ?>"
                                        data-name="<?= htmlspecialchars((string)$a['student_name'], ENT_QUOTES) ?>">
                                    <i class="bi bi-arrow-repeat me-1"></i>Reset (Retake)
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Re-Open / Resume Attempt Modal -->
<div class="modal fade" id="reopenModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-semibold"><i class="bi bi-arrow-counterclockwise me-2"></i>Re-Open &amp; Resume Student Attempt</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="reopenForm">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="attempt_id" id="reopenAttemptId">
                    <div class="alert alert-light border small mb-3">
                        Student: <strong id="reopenStudentName"></strong><br>
                        Current Status: <span class="badge bg-secondary" id="reopenCurrStatus"></span><br>
                        All previously saved answers will be preserved.
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Remaining Time to Grant (Minutes)</label>
                        <input type="number" class="form-control" name="resume_minutes" value="10" min="1" max="180" required>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="reset_violations" value="1" id="chkResetVio" checked>
                        <label class="form-check-label small fw-semibold" for="chkResetVio">Reset Violation Counter to 0 (Recommended for accidental auto-submit)</label>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Audit Reason (Required)</label>
                        <input type="text" class="form-control" name="reason" required placeholder="e.g. Accidental browser popup auto-submit / lab power cut">
                    </div>

                    <div id="reopenResultBox" class="alert alert-success d-none mt-3 text-center">
                        <div class="small fw-semibold mb-1">Attempt Re-Opened! Share this Re-Entry Code with the student:</div>
                        <div class="fs-2 fw-bold font-monospace letter-spacing-2" id="reopenCodeText"></div>
                        <div class="small text-muted mt-1">Student goes to <strong>My Prelims Quizzes &rarr; Resume Quiz Session</strong> and enters this code on their desktop.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" onclick="if(window._needReload) location.reload();">Close</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold" id="btnSubmitReopen">Re-Open &amp; Generate Code</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const baseUrl = <?= json_encode(rtrim(base_url(), '/')) ?>;
    const csrf = <?= json_encode($csrfToken) ?>;
    let modalOpen = false;
    window._needReload = false;

    const reopenModalEl = document.getElementById('reopenModal');
    const reopenModal = reopenModalEl ? new bootstrap.Modal(reopenModalEl) : null;
    reopenModalEl?.addEventListener('shown.bs.modal', () => { modalOpen = true; });
    reopenModalEl?.addEventListener('hidden.bs.modal', () => { modalOpen = false; if (window._needReload) location.reload(); });

    document.querySelectorAll('.reopen-btn').forEach(b => b.addEventListener('click', function () {
        document.getElementById('reopenAttemptId').value = this.dataset.aid;
        document.getElementById('reopenStudentName').textContent = this.dataset.name;
        document.getElementById('reopenCurrStatus').textContent = this.dataset.status;
        document.getElementById('reopenResultBox').classList.add('d-none');
        document.getElementById('btnSubmitReopen').disabled = false;
        document.getElementById('btnSubmitReopen').classList.remove('d-none');
        reopenModal?.show();
    }));

    document.getElementById('reopenForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitReopen');
        btn.disabled = true;
        const r = await fetch(baseUrl + '/fic/prelims/reopen-attempt', { method: 'POST', body: new FormData(this) });
        const j = await r.json();
        if (j.success) {
            window._needReload = true;
            document.getElementById('reopenCodeText').textContent = j.reentry_code;
            document.getElementById('reopenResultBox').classList.remove('d-none');
            btn.classList.add('d-none');
        } else {
            alert(j.message || 'Failed to re-open attempt.');
            btn.disabled = false;
        }
    });

    document.querySelectorAll('.reset-btn').forEach(b => b.addEventListener('click', async function () {
        const studentName = this.dataset.name;
        const reason = prompt(`FULL RETAKE RESET for ${studentName}:\nThis deletes their previous attempt (logged in Audit Logs) so they can start fresh.\nEnter reason:`, 'Technical failure — authorized full retake');
        if (reason === null || reason.trim() === '') return;
        const fd = new FormData();
        fd.append('csrf_token', csrf); fd.append('attempt_id', this.dataset.aid); fd.append('reason', reason.trim());
        const r = await fetch(baseUrl + '/fic/prelims/reset-attempt', { method: 'POST', body: fd });
        const j = await r.json();
        alert(j.message || 'Done');
        if (j.success) location.reload();
    }));

    document.querySelectorAll('.force-btn').forEach(b => b.addEventListener('click', async function () {
        const reason = prompt('Reason for force-submitting this student attempt:', 'Violation / Exam closed');
        if (reason === null) return;
        const fd = new FormData();
        fd.append('csrf_token', csrf); fd.append('attempt_id', this.dataset.aid); fd.append('reason', reason);
        const r = await fetch(baseUrl + '/fic/prelims/force-submit', { method: 'POST', body: fd });
        const j = await r.json();
        alert(j.message || 'Done');
        if (j.success) location.reload();
    }));

    document.querySelectorAll('.reentry-btn').forEach(b => b.addEventListener('click', async function () {
        const mins = prompt('Verified lab PC crash re-entry: Extra minutes to grant (0-30):', '2');
        if (mins === null) return;
        const fd = new FormData();
        fd.append('csrf_token', csrf); fd.append('attempt_id', this.dataset.aid); fd.append('extra_minutes', mins);
        const r = await fetch(baseUrl + '/fic/prelims/grant-reentry', { method: 'POST', body: fd });
        const j = await r.json();
        alert(j.message || 'Done');
    }));

    setInterval(() => { if (!modalOpen) location.reload(); }, 15000);
});
</script>
