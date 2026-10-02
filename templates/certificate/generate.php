<?php declare(strict_types=1); ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?= htmlspecialchars($page_title ?? 'Generate Certificates', ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <div>
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
                    <!-- Button preserved for accessibility/no-js fallback, visually hidden on md+ since onchange is active -->
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-search me-1"></i> Load
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Combined Generation Form ───────────────────────────────────── -->
    <form method="post" action="<?= base_url('/certificates/generate') ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Session::get('_token', ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="generate_both">
        <input type="hidden" name="event_id" value="<?= (int)($selected_event ?? 0) ?>">

        <div class="row g-4 mb-4">
            <!-- Card 1: Winner Certificate Generation -->
            <div class="col-lg-6">
                <div class="card shadow h-100 border-warning">
                    <div class="card-header bg-warning text-dark fw-bold">
                        <i class="bi bi-trophy-fill me-2"></i> Winner Certificates
                        <span class="badge bg-dark ms-2">Rank 1 / 2 / 3</span>
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
                    <div class="card-header bg-primary text-white fw-bold">
                        <i class="bi bi-person-check-fill me-2"></i> Participant Certificates
                        <span class="badge bg-light text-primary ms-2">Attendance Based</span>
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
                <button type="submit" class="btn btn-success btn-lg w-100 shadow-sm" <?= empty($selected_event) ? 'disabled' : '' ?>>
                    <i class="bi bi-magic me-2"></i> Generate Both Winners & Participants
                </button>
            </div>
        </div>
    </form>
</div>
