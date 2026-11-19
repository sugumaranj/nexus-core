<?php
declare(strict_types=1);

$quiz    = $quiz ?? [];
$ranking = $ranking ?? ['ranked_entries' => [], 'individual_attempts' => [], 'selected_count' => 0, 'total_entries' => 0, 'scoring_mode' => 'Individual'];
$qid     = (int) ($quiz['quiz_id'] ?? 0);
$eid     = (int) ($quiz['symposium_event_id'] ?? 0);
$status  = (string) ($quiz['status'] ?? 'Draft');
$isFinal = in_array($status, ['Finalized', 'Disposed'], true);
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><i class="bi bi-trophy text-success me-2"></i>Prelims Results &amp; Automated Top-<?= (int)$quiz['top_n_select'] ?> Selection</h3>
        <div class="text-muted small">
            <?= htmlspecialchars((string)($quiz['title'] ?? '')) ?> · Mode: <strong><?= $ranking['scoring_mode'] === 'All_Members_Best' ? 'Team (Best Member Score)' : 'Individual' ?></strong> · Strictly Automated Tie-Break: <code>Score DESC &rarr; Correct Answers DESC &rarr; Completion Time ASC</code> (No Manual Overrides)
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('/fic/prelims/manage?quiz_id=' . $qid) ?>" class="btn btn-outline-secondary btn-sm">&larr; Setup</a>
        <a href="<?= base_url('/reports/prelims?quiz_id=' . $qid) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-file-earmark-bar-graph me-1"></i>Prelims Report</a>
        <a href="<?= base_url('/reports/prelims/pdf?quiz_id=' . $qid) ?>" class="btn btn-danger btn-sm" target="_blank"><i class="bi bi-file-pdf me-1"></i>PDF</a>
        <a href="<?= base_url('/reports/prelims/csv?quiz_id=' . $qid) ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
    </div>
</div>

<!-- Action Banner -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <?php if ($status === 'Disposed'): ?>
                <span class="badge bg-dark me-2">Finalized &amp; Disposed</span>
                <span class="small text-muted">Raw PDFs, questions, and per-question answers have been securely purged. All student scores, finalists, and analytics remain preserved permanently.</span>
            <?php elseif ($status === 'Finalized'): ?>
                <span class="badge bg-success me-2">Finalists Promoted to Main Event</span>
                <span class="small text-muted">Main Event Attendance &amp; Judge Evaluation now filter strictly to the <?= (int)$ranking['selected_count'] ?> promoted finalist(s).</span>
            <?php else: ?>
                <span class="badge bg-warning text-dark me-2">Preview (Not Finalized)</span>
                <span class="small text-muted">Top <strong><?= (int)$ranking['selected_count'] ?></strong> of <?= (int)$ranking['total_entries'] ?> entry(ies) will automatically advance to the Main Event.</span>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2">
            <?php if (!$isFinal): ?>
                <button type="button" class="btn btn-success fw-semibold" id="btnFinalize">
                    <i class="bi bi-check2-circle me-1"></i>Finalize &amp; Promote Top <?= (int)$quiz['top_n_select'] ?>
                </button>
            <?php elseif ($status === 'Finalized'): ?>
                <a href="<?= base_url('/attendance/event?id=' . $eid . '&round=main') ?>" class="btn btn-outline-primary btn-sm">Open Main Event Attendance</a>
                <button type="button" class="btn btn-danger btn-sm fw-semibold" id="btnDispose">
                    <i class="bi bi-trash3 me-1"></i>Dispose Quiz Questions &amp; PDFs
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Official Merit & Selection Table -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Automated Merit List (Strict Tie-Break Order)</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Rank</th>
                    <th>Application</th>
                    <th>Candidate <?= $ranking['scoring_mode'] === 'All_Members_Best' ? '(Best Scoring Member)' : '' ?></th>
                    <th>Department / College</th>
                    <th>Score</th>
                    <th>Correct</th>
                    <th>Completion Time</th>
                    <th>Integrity</th>
                    <th>Finals Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ranking['ranked_entries'])): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No completed attempts yet.</td></tr>
                <?php else: foreach ($ranking['ranked_entries'] as $row): ?>
                    <tr class="<?= $row['selection_status'] === 'Selected' ? 'table-success' : '' ?>">
                        <td class="fw-bold">#<?= (int)$row['prelims_rank'] ?></td>
                        <td><code><?= htmlspecialchars((string)$row['application_no']) ?></code></td>
                        <td><strong><?= htmlspecialchars((string)$row['student_name']) ?></strong><div class="text-muted"><?= htmlspecialchars((string)$row['register_number']) ?></div></td>
                        <td><?= htmlspecialchars((string)$row['department_name']) ?><div class="text-muted"><?= htmlspecialchars((string)$row['college_name']) ?></div></td>
                        <td class="fw-bold"><?= htmlspecialchars((string)$row['final_score']) ?> / <?= htmlspecialchars((string)$row['max_score']) ?> (<?= htmlspecialchars((string)$row['percentage']) ?>%)</td>
                        <td><?= (int)$row['correct_count'] ?></td>
                        <td><?= gmdate('i:s', (int)$row['time_taken_seconds']) ?> (<?= (int)$row['time_taken_seconds'] ?>s)</td>
                        <td><span class="badge bg-<?= $row['integrity_level']==='Clean'?'success':($row['integrity_level']==='High'?'danger':'warning text-dark') ?>"><?= htmlspecialchars((string)$row['integrity_level']) ?></span></td>
                        <td>
                            <?php if ($row['selection_status'] === 'Selected'): ?>
                                <span class="badge bg-success"><i class="bi bi-award me-1"></i>Selected for Finals</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Not Selected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const baseUrl = <?= json_encode(rtrim(base_url(), '/')) ?>;
    const csrf = <?= json_encode($csrfToken) ?>;
    const quizId = <?= $qid ?>;

    document.getElementById('btnFinalize')?.addEventListener('click', async function () {
        if (!confirm('Finalize prelims rankings automatically and promote the top finalists to the Main Event? This locks rankings permanently.')) return;
        this.disabled = true;
        const fd = new FormData();
        fd.append('csrf_token', csrf); fd.append('quiz_id', String(quizId));
        const r = await fetch(baseUrl + '/fic/prelims/finalize-selection', { method: 'POST', body: fd });
        const j = await r.json();
        alert(j.message || 'Done');
        if (j.success) location.reload(); else this.disabled = false;
    });

    document.getElementById('btnDispose')?.addEventListener('click', async function () {
        if (!confirm('Dispose raw quiz PDFs, question bank, and per-question response rows? All student scores, rankings, analytics snapshot, and promoted finalists will be permanently preserved.')) return;
        this.disabled = true;
        const fd = new FormData();
        fd.append('csrf_token', csrf); fd.append('quiz_id', String(quizId));
        const r = await fetch(baseUrl + '/fic/prelims/dispose', { method: 'POST', body: fd });
        const j = await r.json();
        alert(j.message || 'Done');
        if (j.success) location.reload(); else this.disabled = false;
    });
});
</script>
