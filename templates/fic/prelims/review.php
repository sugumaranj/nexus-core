<?php
declare(strict_types=1);

$quiz      = $quiz ?? [];
$sources   = $sources ?? [];
$topics    = $topics ?? [];
$questions = $questions ?? [];
$qid       = (int) ($quiz['quiz_id'] ?? 0);

$pendingCount  = 0;
$approvedCount = 0;
foreach ($questions as $q) {
    $hasCorr = false;
    foreach ($q['options'] ?? [] as $o) {
        if ((int)($o['is_correct'] ?? 0) === 1) { $hasCorr = true; break; }
    }
    if (($q['review_status'] ?? '') === 'Approved' && $hasCorr) {
        $approvedCount++;
    } else {
        $pendingCount++;
    }
}
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h3 class="fw-bold mb-0"><i class="bi bi-list-check text-primary me-2"></i>Question Verification Workbench</h3>
        <div class="text-muted small">
            <?= htmlspecialchars((string)($quiz['title'] ?? '')) ?> · 
            <strong><?= count($questions) ?></strong> total questions · 
            <span class="text-success fw-semibold"><i class="bi bi-check-circle"></i> <?= $approvedCount ?> Approved</span> · 
            <span class="<?= $pendingCount > 0 ? 'text-warning fw-bold' : 'text-muted' ?>"><i class="bi bi-exclamation-circle"></i> <?= $pendingCount ?> Needs Review</span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('/fic/prelims/manage?quiz_id=' . $qid) ?>" class="btn btn-outline-secondary btn-sm">&larr; Back to Setup</a>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#manualQModal">
            <i class="bi bi-plus-circle me-1"></i>Add Manual Question
        </button>
    </div>
</div>

<!-- Filter & Separate Answer Key Panel -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Filter by PDF Source</label>
                <select class="form-select form-select-sm" onchange="window.location='<?= base_url('/fic/prelims/review?quiz_id=' . $qid) ?>' + (this.value ? '&source_id='+this.value : '')">
                    <option value="">All Uploaded PDFs (<?= count($sources) ?>)</option>
                    <?php foreach ($sources as $s): ?>
                        <option value="<?= (int)$s['source_id'] ?>" <?= (int)($selectedSourceId ?? 0) === (int)$s['source_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$s['original_filename']) ?> (<?= (int)$s['approved_questions'] ?>/<?= (int)$s['total_questions'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($sources)): ?>
            <div class="col-md-8">
                <form id="sepKeyForm" class="row g-2 align-items-end" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="quiz_id" value="<?= $qid ?>">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Apply Key to Source</label>
                        <select class="form-select form-select-sm" name="source_id" required>
                            <?php foreach ($sources as $s): ?>
                                <option value="<?= (int)$s['source_id'] ?>"><?= htmlspecialchars((string)$s['original_filename']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Type Key or Upload Key PDF</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" name="answer_key_text" id="ansKeyTextInput" placeholder="e.g. 1.A 2.C 3.B 4.D ...">
                            <label class="btn btn-outline-secondary" title="Attach Answer Key PDF" style="cursor:pointer;">
                                <i class="bi bi-file-earmark-pdf"></i>
                                <input type="file" name="answer_key_pdf" accept="application/pdf" style="display:none;" onchange="if(this.files[0]) document.getElementById('ansKeyTextInput').value = 'PDF: ' + this.files[0].name;">
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-lightning-charge me-1"></i>Apply Key</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Status Filter Pills -->
<?php if (!empty($questions)): ?>
<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
    <div class="btn-group btn-group-sm" role="group" id="filterStatusGroup">
        <button type="button" class="btn btn-outline-secondary active" data-filter="all">All Questions (<?= count($questions) ?>)</button>
        <button type="button" class="btn btn-outline-warning text-dark position-relative" data-filter="pending">
            <i class="bi bi-exclamation-triangle me-1"></i>Needs Review (<?= $pendingCount ?>)
            <?php if ($pendingCount > 0): ?>
                <span class="badge bg-danger rounded-pill ms-1"><?= $pendingCount ?></span>
            <?php endif; ?>
        </button>
        <button type="button" class="btn btn-outline-success" data-filter="approved">
            <i class="bi bi-check2-circle me-1"></i>Approved (<?= $approvedCount ?>)
        </button>
    </div>
    <?php if ($pendingCount > 0): ?>
        <div class="alert alert-warning py-1 px-3 mb-0 small d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill text-warning"></i>
            <span><strong><?= $pendingCount ?> question(s)</strong> are awaiting answer confirmation. Select the answer below and click <strong>Save &amp; Approve</strong> or paste a key to reach 100%.</span>
        </div>
    <?php else: ?>
        <div class="alert alert-success py-1 px-3 mb-0 small d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill text-success"></i>
            <span><strong>100% Ready!</strong> All <?= $approvedCount ?> questions are approved and available for students.</span>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Questions List -->
<?php if (empty($questions)): ?>
    <div class="card border-0 shadow-sm"><div class="card-body text-center py-5 text-muted">No questions extracted yet. Upload a PDF or add a manual question.</div></div>
<?php else: ?>
    <div class="d-flex flex-column gap-3">
        <?php foreach ($questions as $idx => $q):
            $corrLbl = '';
            $optMap  = [];
            foreach ($q['options'] as $o) {
                $optMap[$o['option_label']] = $o['option_text'];
                if ((int)$o['is_correct'] === 1) $corrLbl = (string)$o['option_label'];
            }
            $needsReview = ($q['review_status'] !== 'Approved' || $corrLbl === '');
        ?>
        <div class="card border-0 shadow-sm q-card <?= $needsReview ? 'border-start border-4 border-warning' : 'border-start border-4 border-success' ?>" data-status="<?= $needsReview ? 'pending' : 'approved' ?>">
            <div class="card-body">
                <form class="q-edit-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="quiz_id" value="<?= $qid ?>">
                    <input type="hidden" name="source_id" value="<?= (int)$q['source_id'] ?>">
                    <input type="hidden" name="question_id" value="<?= (int)$q['question_id'] ?>">

                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="small">
                            <span class="badge bg-dark me-1">#<?= $idx + 1 ?></span>
                            <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars((string)$q['original_filename']) ?></span>
                            <span class="badge bg-info text-dark me-1">Topic: <?= htmlspecialchars((string)$q['topic_name']) ?></span>
                            <span class="badge bg-<?= $needsReview ? 'warning text-dark' : 'success' ?>"><?= htmlspecialchars((string)$q['review_status']) ?> · <?= htmlspecialchars((string)$q['answer_source']) ?></span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-success py-0 px-2">Save &amp; Approve</button>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 del-q-btn" data-qid="<?= (int)$q['question_id'] ?>">Delete</button>
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-9">
                            <textarea class="form-control form-control-sm" name="question_text" rows="2" required><?= htmlspecialchars((string)$q['question_text']) ?></textarea>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select form-select-sm mb-1" name="topic_id">
                                <?php foreach ($topics as $t): ?>
                                    <option value="<?= (int)$t['topic_id'] ?>" <?= (int)$t['topic_id'] === (int)$q['topic_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string)$t['topic_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select form-select-sm" name="correct_label" required>
                                <option value="">-- Select Correct --</option>
                                <?php foreach (['A','B','C','D','E','F'] as $L): if (!isset($optMap[$L]) && !in_array($L, ['A','B','C','D'], true)) continue; ?>
                                    <option value="<?= $L ?>" <?= $corrLbl === $L ? 'selected' : '' ?>>Correct: Option <?= $L ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2">
                        <?php foreach (['A','B','C','D'] as $L): ?>
                            <div class="col-md-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text fw-bold <?= $corrLbl === $L ? 'bg-success text-white' : '' ?>"><?= $L ?></span>
                                    <input type="text" class="form-control" name="options[<?= $L ?>]" value="<?= htmlspecialchars((string)($optMap[$L] ?? '')) ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Add Manual Question Modal -->
<div class="modal fade" id="manualQModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="addManualQForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="quiz_id" value="<?= $qid ?>">
            <div class="modal-header"><h5 class="modal-title">Add Manual Question</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Topic Name</label>
                        <input type="text" class="form-control form-control-sm" name="new_topic_name" value="General" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Correct Option</label>
                        <select class="form-select form-select-sm" name="correct_label"><option>A</option><option>B</option><option>C</option><option>D</option></select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Question Text</label>
                    <textarea class="form-control" name="question_text" rows="2" required></textarea>
                </div>
                <div class="row g-2">
                    <?php foreach (['A','B','C','D'] as $L): ?>
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text fw-bold"><?= $L ?></span>
                                <input type="text" class="form-control" name="options[<?= $L ?>]" required>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Save &amp; Approve</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const baseUrl = <?= json_encode(rtrim(base_url(), '/')) ?>;
    const csrf = <?= json_encode($csrfToken) ?>;
    const quizId = <?= $qid ?>;

    async function submitQForm(form) {
        const res = await fetch(baseUrl + '/fic/prelims/question/save', { method: 'POST', body: new FormData(form) });
        const j = await res.json();
        if (j.success) location.reload();
        else alert(j.message || 'Save failed.');
    }

    // Status Filter (All / Needs Review / Approved)
    document.querySelectorAll('#filterStatusGroup button').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#filterStatusGroup button').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            document.querySelectorAll('.q-card').forEach(card => {
                if (filter === 'all' || card.dataset.status === filter) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    document.querySelectorAll('.q-edit-form').forEach(f => f.addEventListener('submit', e => { e.preventDefault(); submitQForm(f); }));
    document.getElementById('addManualQForm')?.addEventListener('submit', e => { e.preventDefault(); submitQForm(e.target); });

    document.querySelectorAll('.del-q-btn').forEach(btn => btn.addEventListener('click', async function () {
        if (!confirm('Delete this question?')) return;
        const fd = new FormData();
        fd.append('csrf_token', csrf); fd.append('quiz_id', String(quizId)); fd.append('question_id', this.dataset.qid);
        const res = await fetch(baseUrl + '/fic/prelims/question/delete', { method: 'POST', body: fd });
        const j = await res.json();
        if (j.success) location.reload(); else alert(j.message || 'Failed.');
    }));

    document.getElementById('sepKeyForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const res = await fetch(baseUrl + '/fic/prelims/upload-answer-key', { method: 'POST', body: new FormData(this) });
        const j = await res.json();
        alert(j.message || 'Done');
        if (j.success) location.reload();
    });
});
</script>
