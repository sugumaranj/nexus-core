<?php
declare(strict_types=1);

$quizzes = $quizzes ?? [];
?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-laptop text-primary me-2"></i>My Prelims Quizzes</h3>
            <p class="text-muted small mb-0">Desktop / Laptop computer required. Only students marked <strong>Present</strong> or <strong>Late</strong> in Prelims Attendance may attend.</p>
        </div>
        <a href="<?= base_url('/student/dashboard') ?>" class="btn btn-outline-secondary btn-sm">&larr; Dashboard</a>
    </div>

    <?php if (empty($quizzes)): ?>
        <div class="card border-0 shadow-sm"><div class="card-body text-center py-5 text-muted">
            <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
            No digital prelims quizzes are published for your registered events yet.
        </div></div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($quizzes as $q):
                $att = (string)($q['prelims_attendance'] ?? 'Unmarked');
                $attOk = in_array($att, ['Present', 'Late'], true);
                $attStatus = (string)($q['attempt_status'] ?? '');
                $qStatus = (string)($q['status'] ?? '');
            ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-light text-dark border mb-1"><?= htmlspecialchars((string)$q['symposium_title']) ?></span>
                                <h5 class="fw-bold mb-0"><?= htmlspecialchars((string)$q['title']) ?></h5>
                                <div class="small text-muted"><?= htmlspecialchars((string)$q['event_name']) ?> · <?= (int)$q['questions_per_student'] ?> Questions · <?= round((int)$q['duration_seconds'] / 60, 1) ?> min</div>
                            </div>
                            <span class="badge bg-<?= $qStatus === 'Live' ? 'success' : ($qStatus === 'Finalized' || $qStatus === 'Disposed' ? 'primary' : 'secondary') ?>"><?= htmlspecialchars($qStatus) ?></span>
                        </div>

                        <div class="d-flex flex-wrap gap-2 my-2 small">
                            <span class="badge bg-<?= $attOk ? 'success' : ($att === 'Absent' ? 'danger' : 'warning text-dark') ?>">
                                Prelims Attendance: <?= htmlspecialchars($att) ?>
                            </span>
                            <?php if ($attStatus !== ''): ?>
                                <span class="badge bg-info text-dark">Attempt: <?= htmlspecialchars($attStatus) ?></span>
                            <?php endif; ?>
                            <?php if (($q['selection_status'] ?? '') === 'Selected'): ?>
                                <span class="badge bg-success"><i class="bi bi-trophy-fill me-1"></i>Selected for Finals (Rank #<?= (int)$q['prelims_rank'] ?>)</span>
                            <?php elseif (($q['selection_status'] ?? '') === 'Not_Selected'): ?>
                                <span class="badge bg-secondary">Not Shortlisted</span>
                            <?php endif; ?>
                        </div>

                        <div class="mt-auto pt-3 border-top d-flex justify-content-end">
                            <?php if ($attStatus !== '' && $attStatus !== 'In_Progress'): ?>
                                <a href="<?= base_url('/student/prelims/submitted?quiz_id=' . (int)$q['quiz_id']) ?>" class="btn btn-outline-success btn-sm">View Submission Receipt</a>
                            <?php elseif ($qStatus === 'Live' || $attStatus === 'In_Progress'): ?>
                                <a href="<?= base_url('/student/prelims/lobby?quiz_id=' . (int)$q['quiz_id']) ?>" class="btn btn-primary btn-sm fw-semibold">
                                    <?= $attStatus === 'In_Progress' ? 'Resume Quiz Session' : 'Proceed to Exam Lobby' ?> &rarr;
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-sm" disabled>Not Live Yet</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script src="<?= asset('assets/js/prelims/offline-flush.js') ?>"></script>
