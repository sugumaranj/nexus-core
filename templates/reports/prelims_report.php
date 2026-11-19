<?php
declare(strict_types=1);

$r         = $report ?? [];
$quiz      = $r['quiz'] ?? [];
$att       = $r['prelims_attendance'] ?? [];
$attempts  = $r['attempts'] ?? [];
$selected  = $r['selected_finalists'] ?? [];
$analytics = $r['analytics'] ?? [];
$integrity = $r['integrity_summary'] ?? [];
$qid       = (int) ($quiz['quiz_id'] ?? 0);
$eid       = (int) ($quiz['symposium_event_id'] ?? 0);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>Prelims Comprehensive Report</h3>
        <div class="text-muted small">Report ID: <code><?= htmlspecialchars((string)($r['report_id'] ?? '')) ?></code> · <?= htmlspecialchars((string)($quiz['event_name'] ?? '')) ?> (<?= htmlspecialchars((string)($quiz['symposium_title'] ?? '')) ?>)</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('/reports/prelims/pdf?quiz_id=' . $qid) ?>" class="btn btn-danger btn-sm" target="_blank"><i class="bi bi-file-pdf me-1"></i>Download PDF</a>
        <a href="<?= base_url('/reports/prelims/csv?quiz_id=' . $qid) ?>" class="btn btn-outline-success btn-sm"><i class="bi bi-filetype-csv me-1"></i>Export CSV</a>
        <a href="<?= base_url('/reports/main-event?event_id=' . $eid) ?>" class="btn btn-outline-dark btn-sm">Main Event Report &rarr;</a>
    </div>
</div>

<?php if (!empty($r['is_disposed'])): ?>
    <div class="alert alert-secondary small py-2"><i class="bi bi-shield-check me-1"></i>Raw quiz PDFs &amp; questions have been safely disposed on <strong><?= htmlspecialchars((string)($quiz['disposed_at'] ?? '')) ?></strong>. Scores, finalists, and analytics below are served from the permanent archive snapshot.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Prelims Attendance</div><div class="fs-4 fw-bold"><?= (int)($att['present'] ?? 0) + (int)($att['late'] ?? 0) ?> / <?= (int)($att['total_registered'] ?? 0) ?></div><div class="small text-muted">Present: <?= (int)($att['present'] ?? 0) ?> · Late: <?= (int)($att['late'] ?? 0) ?> · Absent: <?= (int)($att['absent'] ?? 0) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Completed Attempts</div><div class="fs-4 fw-bold text-primary"><?= count($attempts) ?></div><div class="small text-muted">Avg Score: <?= htmlspecialchars((string)($analytics['avg_score'] ?? 0)) ?> · Max: <?= htmlspecialchars((string)($analytics['max_score_achieved'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Promoted to Finals</div><div class="fs-4 fw-bold text-success"><?= count($selected) ?></div><div class="small text-muted">Configured Top-N: <?= (int)($quiz['top_n_select'] ?? 0) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Integrity Summary</div><div class="small mt-1"><span class="badge bg-success">Clean: <?= (int)($integrity['Clean'] ?? 0) ?></span> <span class="badge bg-warning text-dark">Low/Med: <?= (int)($integrity['Low'] ?? 0) + (int)($integrity['Medium'] ?? 0) ?></span> <span class="badge bg-danger">High: <?= (int)($integrity['High'] ?? 0) ?></span></div></div></div></div>
</div>

<!-- Topic-wise Performance (works even after disposal!) -->
<?php if (!empty($analytics['topic_performance'])): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">PDF &amp; Topic-Wise Equal-Weightage Performance</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light"><tr><th>PDF Source</th><th>Topic</th><th>Questions Served</th><th>Answered</th><th>Correct</th><th>Accuracy %</th></tr></thead>
            <tbody>
                <?php foreach ($analytics['topic_performance'] as $tp): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$tp['source_name']) ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars((string)$tp['topic_name']) ?></td>
                    <td><?= (int)$tp['served_count'] ?></td>
                    <td><?= (int)$tp['answered_count'] ?></td>
                    <td><?= (int)$tp['correct_count'] ?></td>
                    <td><strong><?= htmlspecialchars((string)$tp['accuracy_pct']) ?>%</strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Promoted Finalists -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold text-success"><i class="bi bi-award-fill me-1"></i>Promoted Finalists for Main Event (<?= count($selected) ?>)</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th>Prelims Rank</th><th>App No</th><th>Candidate</th><th>Register No</th><th>Department / College</th><th>Score</th><th>Correct</th><th>Time Taken</th></tr></thead>
            <tbody>
                <?php if (empty($selected)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">Selection not finalized yet.</td></tr>
                <?php else: foreach ($selected as $f): ?>
                    <tr>
                        <td class="fw-bold">#<?= (int)$f['prelims_rank'] ?></td>
                        <td><code><?= htmlspecialchars((string)$f['application_no']) ?></code></td>
                        <td class="fw-semibold"><?= htmlspecialchars((string)$f['student_name']) ?></td>
                        <td><?= htmlspecialchars((string)$f['register_number']) ?></td>
                        <td><?= htmlspecialchars((string)$f['department_name']) ?> · <?= htmlspecialchars((string)$f['college_name']) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars((string)$f['final_score']) ?> / <?= htmlspecialchars((string)$f['max_score']) ?></td>
                        <td><?= (int)$f['correct_count'] ?></td>
                        <td><?= gmdate('i:s', (int)$f['time_taken_seconds']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Complete Student Scores -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">All Student Prelims Scores (<?= count($attempts) ?>)</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th>Rank</th><th>Student</th><th>Reg No</th><th>Dept</th><th>Correct/Wrong</th><th>Score</th><th>%</th><th>Time</th><th>Violations</th><th>Finals</th></tr></thead>
            <tbody>
                <?php foreach ($attempts as $a): ?>
                    <tr>
                        <td><?= $a['rank_position'] ? '#' . (int)$a['rank_position'] : '-' ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars((string)$a['student_name']) ?></td>
                        <td><?= htmlspecialchars((string)$a['register_number']) ?></td>
                        <td><?= htmlspecialchars((string)$a['department_name']) ?></td>
                        <td><?= (int)$a['correct_count'] ?> / <?= (int)$a['wrong_count'] ?></td>
                        <td class="fw-bold"><?= htmlspecialchars((string)$a['raw_score']) ?> / <?= htmlspecialchars((string)$a['max_score']) ?></td>
                        <td><?= htmlspecialchars((string)$a['percentage']) ?>%</td>
                        <td><?= gmdate('i:s', (int)($a['time_taken_seconds'] ?? 0)) ?></td>
                        <td><?= (int)$a['violation_count'] ?> (<?= htmlspecialchars((string)$a['integrity_level']) ?>)</td>
                        <td><span class="badge bg-<?= ($a['selection_status'] ?? '') === 'Selected' ? 'success' : 'secondary' ?>"><?= ($a['selection_status'] ?? '') === 'Selected' ? 'Selected' : 'Not Selected' ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
