<?php
declare(strict_types=1);

$event      = $event ?? [];
$quiz       = $quiz ?? null;
$sources    = $sources ?? [];
$topics     = $topics ?? [];
$pool       = $pool ?? ['total' => 0, 'approved' => 0, 'pending' => 0, 'missing_answers' => 0];
$poolAudit  = $poolAudit ?? ['ok' => false, 'warnings' => []];
$prelimsAtt = $prelimsAtt ?? ['total_registered' => 0, 'present' => 0, 'late' => 0, 'absent' => 0];
$eid        = (int) ($event['symposium_event_id'] ?? 0);
$qid        = (int) ($quiz['quiz_id'] ?? 0);
$status     = (string) ($quiz['status'] ?? 'Draft');
$isTeam     = (($event['participation_type'] ?? 'Individual') === 'Team');
$lockedEdit = in_array($status, ['Finalized', 'Disposed'], true);
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= base_url('/my/assigned-events/event?id=' . $eid) ?>"><?= htmlspecialchars((string)($event['event_name'] ?? 'Event'), ENT_QUOTES) ?></a></li>
                <li class="breadcrumb-item active">Prelims Quiz Setup</li>
            </ol>
        </nav>
        <h3 class="fw-bold mb-0">
            <i class="bi bi-journal-check text-primary me-2"></i>Digital Prelims Quiz Manager
            <?php if ($quiz): ?>
                <span class="badge bg-<?= match($status) { 'Live'=>'success', 'Published'=>'info text-dark', 'Paused'=>'warning text-dark', 'Finalized'=>'primary', 'Disposed'=>'dark', default=>'secondary' } ?> ms-2 fs-6"><?= htmlspecialchars($status) ?></span>
            <?php endif; ?>
        </h3>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('/attendance/event?id=' . $eid . '&round=prelims') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-person-check me-1"></i>Prelims Attendance (<?= (int)$prelimsAtt['present'] + (int)$prelimsAtt['late'] ?> Eligible)
        </a>
        <?php if ($qid > 0): ?>
            <a href="<?= base_url('/fic/prelims/review?quiz_id=' . $qid) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-check me-1"></i>Question Workbench (<?= (int)$pool['approved'] ?>/<?= (int)$pool['total'] ?>)
            </a>
            <a href="<?= base_url('/fic/prelims/monitor?quiz_id=' . $qid) ?>" class="btn btn-outline-warning text-dark btn-sm">
                <i class="bi bi-broadcast me-1"></i>Live Monitor
            </a>
            <a href="<?= base_url('/fic/prelims/results?quiz_id=' . $qid) ?>" class="btn btn-outline-success btn-sm">
                <i class="bi bi-trophy me-1"></i>Results &amp; Selection
            </a>
            <a href="<?= base_url('/reports/prelims?quiz_id=' . $qid) ?>" class="btn btn-outline-dark btn-sm">
                <i class="bi bi-file-earmark-bar-graph me-1"></i>Prelims Report
            </a>
        <?php endif; ?>
    </div>
</div>

<div id="prelimsAlertBox"></div>

<!-- Top KPI Summary Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Prelims Eligible (Present + Late)</div>
            <div class="fs-4 fw-bold text-primary"><?= (int)$prelimsAtt['present'] + (int)$prelimsAtt['late'] ?> <span class="fs-6 text-muted fw-normal">/ <?= (int)$prelimsAtt['total_registered'] ?></span></div>
            <div class="small text-muted">Present: <?= (int)$prelimsAtt['present'] ?> · Late: <?= (int)$prelimsAtt['late'] ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Approved Question Pool</div>
            <div class="fs-4 fw-bold text-success"><?= (int)$pool['approved'] ?> <span class="fs-6 text-muted fw-normal">/ <?= (int)$pool['total'] ?></span></div>
            <div class="small text-muted">Needs Review: <?= (int)$pool['pending'] ?> · PDFs: <?= count($sources) ?></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Configured Exam Paper</div>
            <div class="fs-4 fw-bold text-dark"><?= (int)($quiz['questions_per_student'] ?? 20) ?> Qs</div>
            <div class="small text-muted">Duration: <?= round(((int)($quiz['duration_seconds'] ?? 1200)) / 60, 1) ?> min · Top <?= (int)($quiz['top_n_select'] ?? 10) ?> advance</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Scoring &amp; Tie-Break Policy</div>
            <div class="fw-bold text-dark mt-1"><?= $isTeam ? 'Team: Best Member Score' : 'Individual Score' ?></div>
            <div class="small text-muted">Auto: Score &rarr; Correct &rarr; Time</div>
        </div></div>
    </div>
</div>

<div class="row g-4">
    <!-- Left: Quiz Configuration -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 fw-semibold">
                <i class="bi bi-sliders text-primary me-2"></i>1. Quiz Settings &amp; Security Controls
            </div>
            <div class="card-body">
                <form id="quizConfigForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="symposium_event_id" value="<?= $eid ?>">
                    <input type="hidden" name="stage_id" value="<?= (int)($stageId ?? ($quiz['stage_id'] ?? 0)) ?>">
                    <input type="hidden" name="quiz_id" value="<?= $qid ?>">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Quiz Title</label>
                        <input type="text" class="form-control" name="title" required <?= $lockedEdit ? 'disabled' : '' ?>
                               value="<?= htmlspecialchars((string)($quiz['title'] ?? (($event['event_name'] ?? 'Event') . ' — Digital Prelims Quiz')), ENT_QUOTES) ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Questions / Student</label>
                            <input type="number" class="form-control" id="cfgNumQ" name="questions_per_student" min="1" max="300" required <?= $lockedEdit ? 'disabled' : '' ?>
                                   value="<?= (int)($quiz['questions_per_student'] ?? 20) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Timer Mode</label>
                            <select class="form-select" id="cfgTimerMode" name="timer_mode" <?= $lockedEdit ? 'disabled' : '' ?>>
                                <option value="per_question" <?= ($quiz['timer_mode'] ?? 'per_question') === 'per_question' ? 'selected' : '' ?>>Seconds / Question</option>
                                <option value="total_minutes" <?= ($quiz['timer_mode'] ?? '') === 'total_minutes' ? 'selected' : '' ?>>Total Minutes</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="secPerQWrap">
                            <label class="form-label small fw-semibold">Seconds / Question</label>
                            <input type="number" class="form-control" id="cfgSecQ" name="seconds_per_question" min="10" max="600" <?= $lockedEdit ? 'disabled' : '' ?>
                                   value="<?= (int)($quiz['seconds_per_question'] ?? 60) ?>">
                        </div>
                        <div class="col-md-4 d-none" id="totalMinWrap">
                            <label class="form-label small fw-semibold">Total Minutes</label>
                            <input type="number" class="form-control" id="cfgTotMin" name="total_minutes" min="1" max="360" <?= $lockedEdit ? 'disabled' : '' ?>
                                   value="<?= max(1, (int)round(((int)($quiz['duration_seconds'] ?? 1200)) / 60)) ?>">
                        </div>
                    </div>
                    <div class="alert alert-light border py-2 px-3 small mb-3">
                        Computed Quiz Duration: <strong id="durationPreview">--</strong>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Top N to Finals</label>
                            <input type="number" class="form-control" name="top_n_select" min="1" max="5000" required <?= $lockedEdit ? 'disabled' : '' ?>
                                   value="<?= (int)($quiz['top_n_select'] ?? 10) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Marks / Correct</label>
                            <input type="number" step="0.25" class="form-control" name="marks_per_question" min="0.25" <?= $lockedEdit ? 'disabled' : '' ?>
                                   value="<?= htmlspecialchars((string)($quiz['marks_per_question'] ?? '1.00')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Negative Marks</label>
                            <input type="number" step="0.25" class="form-control" name="negative_marks" min="0" <?= $lockedEdit ? 'disabled' : '' ?>
                                   value="<?= htmlspecialchars((string)($quiz['negative_marks'] ?? '0.00')) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Schedule Window Start (Optional)</label>
                            <input type="datetime-local" class="form-control" name="window_start" <?= $lockedEdit ? 'disabled' : '' ?>
                                   value="<?= !empty($quiz['window_start']) ? date('Y-m-d\TH:i', strtotime((string)$quiz['window_start'])) : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Schedule Window End (Optional)</label>
                            <input type="datetime-local" class="form-control" name="window_end" <?= $lockedEdit ? 'disabled' : '' ?>
                                   value="<?= !empty($quiz['window_end']) ? date('Y-m-d\TH:i', strtotime((string)$quiz['window_end'])) : '' ?>">
                        </div>
                    </div>

                    <div class="border rounded p-3 bg-light mb-3">
                        <div class="fw-semibold small mb-2"><i class="bi bi-shield-lock text-danger me-1"></i>Browser Lockdown &amp; Anti-Cheat (No Camera)</div>
                        <div class="row g-2 align-items-center">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="chkAutoSub" name="violation_auto_submit" value="1" <?= ((int)($quiz['violation_auto_submit'] ?? 1)) === 1 ? 'checked' : '' ?> <?= $lockedEdit ? 'disabled' : '' ?>>
                                    <label class="form-check-label small fw-semibold" for="chkAutoSub">Auto-Submit on Max Violations</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Max Violations</span>
                                    <input type="number" class="form-control" name="max_violations" min="1" max="20" <?= $lockedEdit ? 'disabled' : '' ?>
                                           value="<?= (int)($quiz['max_violations'] ?? 3) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="require_fullscreen" value="1" <?= ((int)($quiz['require_fullscreen'] ?? 1)) === 1 ? 'checked' : '' ?> <?= $lockedEdit ? 'disabled' : '' ?>>
                                    <label class="form-check-label small">Enforce Fullscreen Mode</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="block_mobile" value="1" <?= ((int)($quiz['block_mobile'] ?? 1)) === 1 ? 'checked' : '' ?> <?= $lockedEdit ? 'disabled' : '' ?>>
                                    <label class="form-check-label small">Block Mobile / Desktop-Site Mode</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Instructions for Students</label>
                        <textarea class="form-control" name="instructions" rows="2" <?= $lockedEdit ? 'disabled' : '' ?> placeholder="Optional custom instructions..."><?= htmlspecialchars((string)($quiz['instructions'] ?? ''), ENT_QUOTES) ?></textarea>
                    </div>

                    <?php if (!$lockedEdit): ?>
                        <button type="submit" class="btn btn-primary w-100 fw-semibold">
                            <i class="bi bi-save me-1"></i>Save Quiz Configuration
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Right: Multi-PDF Upload & Lifecycle -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 fw-semibold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-pdf text-danger me-2"></i>2. Multi-PDF Question Bank Upload</span>
                <span class="badge bg-light text-dark border">Equal Weightage / PDF &amp; Topic</span>
            </div>
            <div class="card-body">
                <?php if ($qid <= 0): ?>
                    <div class="alert alert-info small mb-0">Save the Quiz Configuration on the left first to enable multi-PDF question bank ingestion.</div>
                <?php elseif (in_array($status, ['Live', 'Closed', 'Finalized', 'Disposed'], true)): ?>
                    <div class="alert alert-secondary small mb-0">PDF uploads are locked while the quiz is <strong><?= htmlspecialchars($status) ?></strong>.</div>
                <?php else: ?>
                    <p class="small text-muted mb-2">
                        Upload one or more PDF files. Typed PDFs are parsed directly in PHP (supports <strong>bolded answers</strong>, <strong>inline answers</strong>, <strong>tabular answer grids</strong>, and <strong>mid-document / end answer keys</strong>). Scanned PDFs automatically fall back to our built-in local browser OCR engine.
                    </p>
                    <form id="pdfUploadForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="quiz_id" value="<?= $qid ?>">
                        <div class="input-group mb-2">
                            <input type="file" class="form-control" name="pdfs[]" id="pdfFilesInput" accept="application/pdf,.pdf" multiple required>
                            <button type="submit" class="btn btn-danger fw-semibold" id="btnUploadPdf">
                                <i class="bi bi-cloud-upload me-1"></i>Extract PDFs
                            </button>
                        </div>
                    </form>
                    <div id="ocrProgressWrap" class="d-none mt-2">
                        <div class="small fw-semibold text-primary mb-1" id="ocrProgressText">Processing...</div>
                        <div class="progress" style="height:8px"><div class="progress-bar progress-bar-striped progress-bar-animated" id="ocrProgressBar" style="width:10%"></div></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($sources)): ?>
                    <hr>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>PDF Source</th>
                                    <th>Method</th>
                                    <th>Topics</th>
                                    <th>Questions</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sources as $src): ?>
                                    <tr>
                                        <td class="small fw-semibold"><?= htmlspecialchars((string)$src['original_filename']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars((string)$src['extraction_method']) ?></span></td>
                                        <td><?= (int)$src['topic_count'] ?></td>
                                        <td><span class="text-success fw-bold"><?= (int)$src['approved_questions'] ?></span> / <?= (int)$src['total_questions'] ?></td>
                                        <td><span class="badge bg-<?= $src['extraction_status']==='Approved'?'success':($src['extraction_status']==='Disposed'?'dark':'warning text-dark') ?>"><?= htmlspecialchars((string)$src['extraction_status']) ?></span></td>
                                        <td class="text-end">
                                            <?php if (!in_array($status, ['Live', 'Closed', 'Finalized', 'Disposed'], true)): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1 del-src-btn" data-sid="<?= (int)$src['source_id'] ?>" title="Remove PDF"><i class="bi bi-trash"></i></button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lifecycle Control Card -->
        <?php if ($qid > 0 && !in_array($status, ['Finalized', 'Disposed'], true)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 fw-semibold">
                <i class="bi bi-play-circle text-success me-2"></i>3. Quiz Lifecycle &amp; Launch Controls
            </div>
            <div class="card-body">
                <?php if (!empty($poolAudit['warnings'])): ?>
                    <div class="alert alert-warning small py-2">
                        <strong>Pool Check:</strong>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($poolAudit['warnings'] as $w): ?>
                                <li><?= htmlspecialchars((string)$w) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php elseif ($poolAudit['ok'] ?? false): ?>
                    <div class="alert alert-success small py-2">
                        <i class="bi bi-check-circle-fill me-1"></i>Question bank verified: <?= (int)$poolAudit['total_approved'] ?> approved questions across <?= (int)$poolAudit['source_count'] ?> PDF(s) and <?= (int)$poolAudit['topic_count'] ?> topic(s).
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2">
                    <?php if ($status === 'Draft'): ?>
                        <button type="button" class="btn btn-info text-dark fw-semibold status-btn" data-st="Published">Publish Quiz</button>
                    <?php endif; ?>
                    <?php if (in_array($status, ['Draft', 'Published', 'Paused'], true)): ?>
                        <button type="button" class="btn btn-success fw-semibold status-btn" data-st="Live"><i class="bi bi-broadcast me-1"></i>Go Live Now</button>
                    <?php endif; ?>
                    <?php if ($status === 'Live'): ?>
                        <button type="button" class="btn btn-warning text-dark fw-semibold status-btn" data-st="Paused">Pause Entry</button>
                        <button type="button" class="btn btn-danger fw-semibold status-btn" data-st="Closed">Close Quiz</button>
                    <?php endif; ?>
                    <?php if ($status === 'Paused'): ?>
                        <button type="button" class="btn btn-danger fw-semibold status-btn" data-st="Closed">Close Quiz</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?= asset('assets/js/prelims/local-ocr.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const baseUrl = <?= json_encode(rtrim(base_url(), '/')) ?>;
    const csrf = <?= json_encode($csrfToken) ?>;
    const quizId = <?= $qid ?>;

    function showAlert(msg, type = 'success') {
        document.getElementById('prelimsAlertBox').innerHTML =
            `<div class="alert alert-${type} alert-dismissible fade show">${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    }

    function updateDurationPreview() {
        const numQ = parseInt(document.getElementById('cfgNumQ')?.value || '20', 10);
        const mode = document.getElementById('cfgTimerMode')?.value || 'per_question';
        document.getElementById('secPerQWrap').classList.toggle('d-none', mode !== 'per_question');
        document.getElementById('totalMinWrap').classList.toggle('d-none', mode === 'per_question');
        let totalSec = 0;
        if (mode === 'per_question') {
            totalSec = numQ * parseInt(document.getElementById('cfgSecQ')?.value || '60', 10);
        } else {
            totalSec = parseInt(document.getElementById('cfgTotMin')?.value || '20', 10) * 60;
        }
        const m = Math.floor(totalSec / 60), s = totalSec % 60;
        document.getElementById('durationPreview').textContent = `${m} min ${s} sec (${totalSec}s for ${numQ} questions)`;
    }
    ['cfgNumQ', 'cfgTimerMode', 'cfgSecQ', 'cfgTotMin'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', updateDurationPreview);
        document.getElementById(id)?.addEventListener('change', updateDurationPreview);
    });
    updateDurationPreview();

    document.getElementById('quizConfigForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this);
        const res = await fetch(baseUrl + '/fic/prelims/save-config', { method: 'POST', body: fd });
        const j = await res.json();
        if (j.success) {
            showAlert(j.message, 'success');
            setTimeout(() => window.location.href = baseUrl + '/fic/prelims/manage?quiz_id=' + j.quiz_id, 600);
        } else {
            showAlert(j.message || 'Failed to save configuration.', 'danger');
        }
    });

    document.getElementById('pdfUploadForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = document.getElementById('btnUploadPdf');
        const progWrap = document.getElementById('ocrProgressWrap');
        const progText = document.getElementById('ocrProgressText');
        const progBar = document.getElementById('ocrProgressBar');
        btn.disabled = true;
        progWrap.classList.remove('d-none');
        progText.textContent = 'Uploading & running pure-PHP binary PDF extraction...';
        progBar.style.width = '25%';

        try {
            const res = await fetch(baseUrl + '/fic/prelims/upload-pdf', { method: 'POST', body: new FormData(this) });
            const j = await res.json();
            if (!j.success) throw new Error(j.message || 'Upload failed.');

            const msgs = [];
            for (const item of (j.results || [])) {
                if (item.needs_ocr && item.pdf_stream_url && window.NexusLocalOcr) {
                    progText.textContent = `Scanned PDF (${item.filename}): running self-contained local OCR...`;
                    const pages = await window.NexusLocalOcr.extractPages({
                        baseUrl: baseUrl,
                        pdfUrl: item.pdf_stream_url,
                        onProgress: (txt, pct) => {
                            progText.textContent = `${item.filename}: ${txt}`;
                            progBar.style.width = pct + '%';
                        }
                    });
                    const ocrRes = await fetch(baseUrl + '/fic/prelims/submit-ocr-text', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ csrf_token: csrf, quiz_id: quizId, source_id: item.source_id, pages: pages })
                    });
                    const oj = await ocrRes.json();
                    msgs.push(`<strong>${item.filename} (Local OCR):</strong> ${oj.message || 'Done'}`);
                } else {
                    msgs.push(`<strong>${item.filename}:</strong> ${item.message || (item.success ? 'Extracted' : 'Failed')}`);
                }
            }
            showAlert(msgs.join('<br>'), 'info');
            setTimeout(() => location.reload(), 1200);
        } catch (err) {
            showAlert(err.message || 'PDF processing error.', 'danger');
            btn.disabled = false;
            progWrap.classList.add('d-none');
        }
    });

    document.querySelectorAll('.del-src-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            if (!confirm('Delete this PDF source and all its extracted questions?')) return;
            const fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('source_id', this.dataset.sid);
            const res = await fetch(baseUrl + '/fic/prelims/source/delete', { method: 'POST', body: fd });
            const j = await res.json();
            if (j.success) location.reload();
            else showAlert(j.message || 'Delete failed.', 'danger');
        });
    });

    document.querySelectorAll('.status-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const st = this.dataset.st;
            if (!confirm(`Change quiz status to "${st}"?`)) return;
            const fd = new FormData();
            fd.append('csrf_token', csrf);
            fd.append('quiz_id', String(quizId));
            fd.append('status', st);
            const res = await fetch(baseUrl + '/fic/prelims/status', { method: 'POST', body: fd });
            const j = await res.json();
            if (j.success) location.reload();
            else showAlert(j.message || 'Status update failed.', 'danger');
        });
    });
});
</script>
