<?php declare(strict_types=1); ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="bi bi-stack text-primary me-2"></i> <?= htmlspecialchars($page_title ?? 'Symposium Batch Generation', ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <div>
            <a href="<?= base_url('/certificates/generate') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Switch to Single Event
            </a>
        </div>
    </div>

    <?php if (!empty($flash_message)): ?>
    <div class="alert alert-<?= htmlspecialchars($flash_message['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show mb-4" role="alert">
        <?= htmlspecialchars($flash_message['text'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Select Symposium -->
    <div class="card shadow mb-4 border-primary">
        <div class="card-body">
            <form method="get" action="<?= base_url('/certificates/symposium-batch') ?>" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Select Symposium to check status</label>
                    <select name="symposium_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Choose Symposium --</option>
                        <?php foreach ($symposiums ?? [] as $symp): ?>
                            <option value="<?= (int)$symp['symposium_id'] ?>"
                                <?= (isset($selected_symposium) && (int)$selected_symposium === (int)$symp['symposium_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($symp['symposium_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($selected_symposium) && isset($events_stats)): ?>
        <div class="card shadow mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-dark">
                    Status Overview: <?= htmlspecialchars($symposium_info['symposium_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                </h6>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <!-- Master Generate Button with Template Selectors -->
                    <form method="post" action="<?= base_url('/certificates/symposium-batch') ?>" class="d-inline-flex align-items-center gap-2 mb-0">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="symposium_id" value="<?= (int)$selected_symposium ?>">
                        
                        <select name="winner_template_id" class="form-select form-select-sm" style="max-width: 160px;" title="Winner Template">
                            <option value="">-- Winner Default --</option>
                            <?php foreach ($winner_templates ?? [] as $t): ?>
                                <option value="<?= (int)$t['certificate_template_id'] ?>"><?= htmlspecialchars($t['template_name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="participant_template_id" class="form-select form-select-sm" style="max-width: 160px;" title="Participant Template">
                            <option value="">-- Part. Default --</option>
                            <?php foreach ($participant_templates ?? [] as $t): ?>
                                <option value="<?= (int)$t['certificate_template_id'] ?>"><?= htmlspecialchars($t['template_name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn btn-primary btn-sm text-nowrap" onclick="return confirm('This will generate all missing certificates for ALL locked events in this symposium using the selected templates. Proceed?')">
                            <i class="bi bi-lightning-charge-fill me-1"></i> Generate All
                        </button>
                    </form>
                    
                    <!-- Download Symposium Zip -->
                    <a href="<?= base_url('/certificates/download/symposium-package?symposium_id=' . (int)$selected_symposium) ?>" class="btn btn-success btn-sm text-nowrap">
                        <i class="bi bi-file-earmark-zip me-1"></i> Download All (ZIP)
                    </a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Event Name</th>
                            <th>Status</th>
                            <th>Winner Certificates</th>
                            <th>Participant Certificates</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($events_stats)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No events found for this symposium.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($events_stats as $stat): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($stat['event_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    
                                    <?php if (!$stat['is_locked']): ?>
                                        <td><span class="badge bg-secondary"><i class="bi bi-lock"></i> Unlocked</span></td>
                                        <td class="text-muted small">Cannot generate (Event not locked)</td>
                                        <td class="text-muted small">Cannot generate (Event not locked)</td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-secondary" disabled>Generate</button>
                                        </td>
                                    <?php else: ?>
                                        <td><span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Locked</span></td>
                                        
                                        <!-- Winner Stats -->
                                        <td>
                                            <?php $wp = $stat['winner_preflight']; ?>
                                            <?php if ($wp === null || !empty($wp['error'])): ?>
                                                <span class="text-danger small"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($wp['error'] ?? 'Unknown Error', ENT_QUOTES) ?></span>
                                            <?php else: ?>
                                                <div class="small">
                                                    Eligible: <strong><?= $wp['eligible'] ?></strong><br>
                                                    Generated: <span class="text-success"><?= $wp['generated'] ?></span><br>
                                                    <?php if ($wp['missing'] > 0): ?>
                                                        <span class="text-warning fw-bold">Missing: <?= $wp['missing'] ?></span>
                                                    <?php else: ?>
                                                        <span class="text-success"><i class="bi bi-check"></i> Complete</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Participant Stats -->
                                        <td>
                                            <?php $pp = $stat['participant_preflight']; ?>
                                            <?php if ($pp === null || !empty($pp['error'])): ?>
                                                <span class="text-danger small"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($pp['error'] ?? 'Unknown Error', ENT_QUOTES) ?></span>
                                            <?php else: ?>
                                                <div class="small">
                                                    Eligible: <strong><?= $pp['eligible'] ?></strong><br>
                                                    Generated: <span class="text-success"><?= $pp['generated'] ?></span><br>
                                                    <?php if ($pp['missing'] > 0): ?>
                                                        <span class="text-warning fw-bold">Missing: <?= $pp['missing'] ?></span>
                                                    <?php else: ?>
                                                        <span class="text-success"><i class="bi bi-check"></i> Complete</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- Action -->
                                        <td class="text-center">
                                            <a href="<?= base_url('/certificates/generate?event_id=' . $stat['event_id']) ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-box-arrow-in-right"></i> Generate
                                            </a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
