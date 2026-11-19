<?php declare(strict_types=1); ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0 text-gray-800">
            <?= htmlspecialchars($page_title ?? 'Generate Certificates', ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <div class="d-flex gap-2">
            <a href="<?= base_url('/certificates/generated') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-award"></i> View Certificates
            </a>
            <a href="<?= base_url('/certificates/symposium-batch') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-stack"></i> Switch to Symposium Batch
            </a>
        </div>
    </div>

    <?php if (!empty($flash_message)): ?>
    <div class="alert alert-<?= htmlspecialchars($flash_message['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show mb-4" role="alert">
        <?= htmlspecialchars($flash_message['text'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- ── Recent Background Generation Jobs ─────────────────────────── -->
    <?php if (!empty($recent_jobs)): ?>
    <div class="card shadow-sm mb-4 border-info">
        <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-dark">
                <i class="bi bi-clock-history text-info me-2"></i> Recent Background Generation Jobs
            </h6>
            <span class="badge bg-secondary"><?= count($recent_jobs) ?> Recent</span>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Job ID</th>
                        <th>Target</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Submitted At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_jobs as $rj): ?>
                    <tr>
                        <td><span class="font-monospace fw-bold">#<?= (int)$rj['job_id'] ?></span></td>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($rj['event_name'] ?? $rj['symposium_title'] ?? 'Symposium Event', ENT_QUOTES, 'UTF-8') ?></div>
                            <small class="text-muted"><?= htmlspecialchars($rj['job_type']) ?></small>
                        </td>
                        <td>
                            <?php if ($rj['status'] === 'Completed'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Completed</span>
                            <?php elseif ($rj['status'] === 'Processing'): ?>
                                <span class="badge bg-primary"><span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Processing</span>
                            <?php elseif ($rj['status'] === 'Failed'): ?>
                                <span class="badge bg-danger"><i class="bi bi-exclamation-circle me-1"></i>Failed</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="small fw-semibold"><?= (int)$rj['processed_items'] ?> / <?= (int)$rj['total_items'] ?></span>
                                <?php $pct = $rj['total_items'] > 0 ? min(100, round(($rj['processed_items'] / $rj['total_items']) * 100)) : ($rj['status'] === 'Completed' ? 100 : 0); ?>
                                <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                    <div class="progress-bar <?= $rj['status'] === 'Completed' ? 'bg-success' : 'bg-primary' ?>" style="width: <?= $pct ?>%;"></div>
                                </div>
                            </div>
                        </td>
                        <td class="small text-muted"><?= htmlspecialchars((string)$rj['created_at']) ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= base_url('/certificates/job-progress?job_id=' . (int)$rj['job_id']) ?>" class="btn btn-xs btn-outline-secondary btn-sm py-0 px-2" title="Track Live Status">
                                <i class="bi bi-activity"></i> Status
                            </a>
                            <?php if ($rj['status'] === 'Completed'): ?>
                                <a href="<?= base_url('/certificates/report?job_id=' . (int)$rj['job_id']) ?>" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2">
                                    <i class="bi bi-file-earmark-text"></i> Report
                                </a>
                                <?php if (!empty($rj['symposium_event_id'])): ?>
                                    <a href="<?= base_url('/certificates/generated?event_id=' . (int)$rj['symposium_event_id']) ?>" class="btn btn-xs btn-outline-success btn-sm py-0 px-2">
                                        <i class="bi bi-award"></i> View
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Event Selector ─────────────────────────────────────────── -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 fw-bold text-primary"><i class="bi bi-funnel-fill me-2"></i>Select Event</h6>
        </div>
        <div class="card-body">
            <form method="get" action="<?= base_url('/certificates/generate') ?>" class="row g-3 align-items-end">
                <?php if (!empty($selected_template)): ?>
                    <input type="hidden" name="template_id" value="<?= (int)$selected_template ?>">
                <?php endif; ?>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Symposium <span class="text-danger">*</span></label>
                    <select name="symposium_id" class="form-select" id="sym_selector" required onchange="this.form.event_id.value=''; this.form.submit()">
                        <option value="">-- Choose Symposium --</option>
                        <?php foreach ($symposiums ?? [] as $symp): ?>
                            <option value="<?= (int)$symp['symposium_id'] ?>"
                                <?= (isset($selected_symposium) && (int)$selected_symposium === (int)$symp['symposium_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($symp['symposium_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Event <span class="text-danger">*</span></label>
                    <select name="event_id" class="form-select" id="event_selector" onchange="this.form.submit()" <?= empty($events) ? 'disabled' : '' ?>>
                        <option value="">-- Choose Event --</option>
                        <?php foreach ($events ?? [] as $ev): ?>
                            <option value="<?= (int)$ev['symposium_event_id'] ?>"
                                <?= (isset($selected_event) && (int)$selected_event === (int)$ev['symposium_event_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ev['event_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-none d-md-block">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-search me-1"></i> Load
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Combined Generation Form ───────────────────────────────────── -->
    <form id="certificateGenerationForm" method="post" action="<?= base_url('/certificates/generate') ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Session::get('_token', ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" id="actionInput" name="action" value="generate_both">
        <input type="hidden" name="event_id" value="<?= (int)($selected_event ?? 0) ?>">

        <div class="row g-4 mb-4">
            <!-- Card 1: Winner Certificate Generation -->
            <div class="col-lg-6">
                <div class="card shadow h-100 border-warning">
                    <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-trophy-fill me-2"></i> Winner Certificates
                            <span class="badge bg-dark ms-2">Rank 1 / 2 / 3</span>
                        </div>
                        <?php if (!empty($selected_event)): ?>
                        <button type="button" class="btn btn-sm btn-dark" onclick="submitCustomAction('generate_winners')">
                            Generate Winners Only
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <!-- Template selector (Winner templates only) -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Winner Template</label>
                            <select name="winner_template_id" class="form-select form-select-sm">
                                <option value="">-- Use event default --</option>
                                <?php foreach ($winner_templates ?? [] as $t): ?>
                                    <option value="<?= (int)$t['certificate_template_id'] ?>" <?= (isset($selected_template) && $selected_template === (int)$t['certificate_template_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['template_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Preflight: Winner -->
                        <?php if (isset($winner_preflight)): ?>
                        <div class="mb-3 small">
                            <table class="table table-sm table-bordered mb-0">
                                <tr><th>Eligible (Rank 1/2/3)</th><td><?= (int)($winner_preflight['eligible'] ?? 0) ?></td></tr>
                                <tr><th>Already Generated</th><td><?= (int)($winner_preflight['generated'] ?? 0) ?></td></tr>
                                <tr class="table-warning"><th>Missing</th><td><?= (int)($winner_preflight['missing'] ?? 0) ?></td></tr>
                                <tr class="table-danger"><th>Failed</th><td><?= (int)($winner_preflight['failed'] ?? 0) ?></td></tr>
                            </table>
                            <?php if (!empty($winner_preflight['error'])): ?>
                            <div class="alert alert-danger py-1 mt-2 small mb-0"><?= htmlspecialchars($winner_preflight['error'], ENT_QUOTES) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="allow_regenerate_winner" value="1" id="regen_winner">
                            <label class="form-check-label" for="regen_winner">Allow Regeneration for Winners</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Participant Certificate Generation -->
            <div class="col-lg-6">
                <div class="card shadow h-100 border-primary">
                    <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-person-check-fill me-2"></i> Participant Certificates
                            <span class="badge bg-light text-primary ms-2">Attendance Based</span>
                        </div>
                        <?php if (!empty($selected_event)): ?>
                        <button type="button" class="btn btn-sm btn-light text-primary" onclick="submitCustomAction('generate_participants')">
                            Generate Participants Only
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <!-- Template selector (Participant templates only) -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Participant Template</label>
                            <select name="participant_template_id" class="form-select form-select-sm">
                                <option value="">-- Use event default --</option>
                                <?php foreach ($participant_templates ?? [] as $t): ?>
                                    <option value="<?= (int)$t['certificate_template_id'] ?>" <?= (isset($selected_template) && $selected_template === (int)$t['certificate_template_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($t['template_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Preflight: Participant -->
                        <?php if (isset($participant_preflight)): ?>
                        <div class="mb-3 small">
                            <table class="table table-sm table-bordered mb-0">
                                <tr><th>Eligible (Present / Late)</th><td><?= (int)($participant_preflight['eligible'] ?? 0) ?></td></tr>
                                <tr><th>Already Generated</th><td><?= (int)($participant_preflight['generated'] ?? 0) ?></td></tr>
                                <tr class="table-warning"><th>Missing</th><td><?= (int)($participant_preflight['missing'] ?? 0) ?></td></tr>
                                <tr class="table-danger"><th>Failed</th><td><?= (int)($participant_preflight['failed'] ?? 0) ?></td></tr>
                            </table>
                            <?php if (!empty($participant_preflight['error'])): ?>
                            <div class="alert alert-danger py-1 mt-2 small mb-0"><?= htmlspecialchars($participant_preflight['error'], ENT_QUOTES) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="allow_regenerate_participant" value="1" id="regen_participant">
                            <label class="form-check-label" for="regen_participant">Allow Regeneration for Participants</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <button type="submit" id="btnSubmitGenerate" class="btn btn-success btn-lg w-100 shadow-sm py-3" <?= empty($selected_event) ? 'disabled' : '' ?>>
                    <i class="bi bi-magic me-2"></i> Generate Both Winners &amp; Participants
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function submitCustomAction(actionName) {
    const form = document.getElementById('certificateGenerationForm');
    const actionInput = document.getElementById('actionInput');
    actionInput.value = actionName;
    form.requestSubmit();
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('certificateGenerationForm');
    const submitBtn = document.getElementById('btnSubmitGenerate');

    if (form && submitBtn) {
        form.addEventListener('submit', async function(e) {
            // Optional AJAX progressive enhancement
            if (window.fetch && window.FormData) {
                e.preventDefault();
                submitBtn.disabled = true;
                const originalHtml = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Initializing Background Worker...';

                const formData = new FormData(form);
                formData.append('is_ajax', '1');

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (data && data.redirect_url) {
                            window.location.href = data.redirect_url;
                            return;
                        }
                    }
                } catch (err) {
                    console.warn('[Generate Form] AJAX fetch encountered notice, falling back to standard POST:', err);
                }

                // Fallback to standard submit if AJAX failed
                submitBtn.innerHTML = originalHtml;
                form.submit();
            }
        });
    }
});
</script>
