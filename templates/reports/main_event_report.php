<?php
declare(strict_types=1);

$r           = $report ?? [];
$event       = $r['event'] ?? [];
$prelimsQuiz = $r['prelims_quiz'] ?? null;
$pAtt        = $r['prelims_attendance'] ?? [];
$mAtt        = $r['main_attendance'] ?? [];
$finalists   = $r['prelims_finalists'] ?? [];
$results     = $r['final_results'] ?? [];
$eid         = (int) ($event['symposium_event_id'] ?? 0);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><i class="bi bi-trophy-fill text-warning me-2"></i>Main Event Comprehensive Report</h3>
        <div class="text-muted small">Report ID: <code><?= htmlspecialchars((string)($r['report_id'] ?? '')) ?></code> · <?= htmlspecialchars((string)($event['event_name'] ?? '')) ?></div>
    </div>
    <div class="d-flex gap-2">
        <?php if ($prelimsQuiz): ?>
            <a href="<?= base_url('/reports/prelims?quiz_id=' . (int)$prelimsQuiz['quiz_id']) ?>" class="btn btn-outline-primary btn-sm">&larr; Prelims Report</a>
        <?php endif; ?>
        <a href="<?= base_url('/reports/main-event/pdf?event_id=' . $eid) ?>" class="btn btn-danger btn-sm" target="_blank"><i class="bi bi-file-pdf me-1"></i>Download Official PDF</a>
    </div>
</div>

<!-- Prelims -> Main Event Funnel -->
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Prelims Attended</div><div class="fs-4 fw-bold"><?= (int)($pAtt['present'] ?? 0) + (int)($pAtt['late'] ?? 0) ?></div><div class="small text-muted">Registered: <?= (int)($pAtt['total_registered'] ?? 0) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Promoted Finalists</div><div class="fs-4 fw-bold text-primary"><?= count($finalists) ?></div><div class="small text-muted">Qualified from Prelims</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Main Event Attendance</div><div class="fs-4 fw-bold text-success"><?= (int)($mAtt['present'] ?? 0) + (int)($mAtt['late'] ?? 0) ?> / <?= (int)($mAtt['total_registered'] ?? 0) ?></div><div class="small text-muted">Present: <?= (int)($mAtt['present'] ?? 0) ?> · Late: <?= (int)($mAtt['late'] ?? 0) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="small text-muted">Final Evaluated Results</div><div class="fs-4 fw-bold text-warning"><?= count($results) ?></div><div class="small text-muted">Status: <?= htmlspecialchars((string)($event['status'] ?? '')) ?></div></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Main Event Final Rankings &amp; Winners</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light"><tr><th>Rank</th><th>App No</th><th>Participant</th><th>Register No</th><th>Department / College</th><th>Total Score</th><th>Average</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Main event evaluation results have not been published yet.</td></tr>
                <?php else: foreach ($results as $res): ?>
                    <tr class="<?= (int)($res['rank_position'] ?? 99) <= 3 ? 'table-warning' : '' ?>">
                        <td class="fw-bold">#<?= (int)($res['rank_position'] ?? 0) ?></td>
                        <td><code><?= htmlspecialchars((string)$res['application_no']) ?></code></td>
                        <td class="fw-semibold"><?= htmlspecialchars((string)$res['student_name']) ?></td>
                        <td><?= htmlspecialchars((string)$res['register_number']) ?></td>
                        <td><?= htmlspecialchars((string)$res['department_name']) ?> · <?= htmlspecialchars((string)$res['college_name']) ?></td>
                        <td class="fw-bold"><?= htmlspecialchars((string)$res['total_score']) ?></td>
                        <td><?= htmlspecialchars((string)$res['average_score']) ?></td>
                        <td><span class="badge bg-<?= (int)($res['rank_position'] ?? 99) <= 3 ? 'success' : 'secondary' ?>"><?= htmlspecialchars((string)$res['result_status']) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
