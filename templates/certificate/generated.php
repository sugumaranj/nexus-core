<?php

declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($page_title ?? 'Generated Certificates', ENT_QUOTES, 'UTF-8') ?></h1>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-light flex-wrap gap-2">
            <form method="get" action="<?= base_url('/certificates/generated') ?>" class="d-flex align-items-center m-0 gap-3">
                <div class="d-flex align-items-center">
                    <label for="symposium_filter" class="me-2 fw-bold text-dark text-nowrap">Filter by Symposium:</label>
                    <select name="symposium_id" id="symposium_filter" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 250px;">
                        <option value="">-- All Symposiums --</option>
                        <?php foreach ($symposiums ?? [] as $sym): ?>
                            <option value="<?= htmlspecialchars((string)$sym['symposium_id'], ENT_QUOTES, 'UTF-8') ?>" <?= ((int)($selected_symposium ?? 0) === (int)$sym['symposium_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sym['title'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex align-items-center">
                    <label for="event_filter" class="me-2 fw-bold text-dark text-nowrap">OR Event:</label>
                    <select name="event_id" id="event_filter" class="form-select form-select-sm" onchange="document.getElementById('symposium_filter').value=''; this.form.submit()" style="width: 250px;">
                        <option value="">-- All Events --</option>
                        <?php foreach ($events ?? [] as $ev): ?>
                            <option value="<?= htmlspecialchars((string)$ev['symposium_event_id'], ENT_QUOTES, 'UTF-8') ?>" <?= ((int)($selected_event ?? 0) === (int)$ev['symposium_event_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ev['event_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            <div>
                <?php if (!empty($selected_event) && isset($event_report)): ?>
                    <a href="<?= base_url('/certificates/download/event-zip?event_id=' . (int)$selected_event) ?>" 
                       class="btn btn-sm btn-primary <?= $event_report['missing'] > 0 ? 'disabled' : '' ?>"
                       <?= $event_report['missing'] > 0 ? 'title="Cannot download: ' . $event_report['missing'] . ' certificates missing" tabindex="-1" aria-disabled="true"' : '' ?>>
                        <i class="bi bi-file-earmark-zip"></i> Download Event ZIP
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($selected_symposium) && isset($symposium_report)): ?>
                    <a href="<?= base_url('/certificates/download/symposium-package?symposium_id=' . (int)$selected_symposium) ?>" 
                       class="btn btn-sm btn-outline-success <?= $symposium_report['missing'] > 0 ? 'disabled' : '' ?>"
                       <?= $symposium_report['missing'] > 0 ? 'title="Cannot download: Symposium is incomplete or certificates are missing" tabindex="-1" aria-disabled="true"' : '' ?>>
                        <i class="bi bi-file-earmark-zip"></i> Complete ZIP
                    </a>
                    <a href="<?= base_url('/certificates/download/symposium-pdf?symposium_id=' . (int)$selected_symposium) ?>" 
                       class="btn btn-sm btn-outline-danger ms-1 <?= $symposium_report['missing'] > 0 ? 'disabled' : '' ?>"
                       <?= $symposium_report['missing'] > 0 ? 'title="Cannot download: Symposium is incomplete or certificates are missing" tabindex="-1" aria-disabled="true"' : '' ?>>
                        <i class="bi bi-file-earmark-pdf"></i> Complete PDF
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="btn-download-zip" disabled>
                    <i class="bi bi-file-earmark-zip"></i> Download Selected
                </button>
            </div>
        </div>
        
        <?php if (!empty($selected_event) && isset($event_report)): ?>
        <div class="card-body border-bottom bg-light">
            <div class="row text-center">
                <div class="col-md-3">
                    <h6 class="text-muted mb-1">Event</h6>
                    <div class="fw-bold"><?= htmlspecialchars($event_report['event_name'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="col-md-3">
                    <h6 class="text-muted mb-1">Expected</h6>
                    <div class="fw-bold fs-5"><?= (int)$event_report['expected'] ?></div>
                </div>
                <div class="col-md-3">
                    <h6 class="text-muted mb-1">Generated</h6>
                    <div class="fw-bold fs-5 text-success"><?= (int)$event_report['generated'] ?></div>
                </div>
                <div class="col-md-3">
                    <h6 class="text-muted mb-1">Missing</h6>
                    <?php if ($event_report['missing'] > 0): ?>
                        <div class="fw-bold fs-5 text-danger"><?= (int)$event_report['missing'] ?></div>
                    <?php else: ?>
                        <div class="fw-bold fs-5 text-success">0</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($event_report['missing'] > 0): ?>
                <div class="alert alert-warning mt-3 mb-0 py-2">
                    <i class="bi bi-exclamation-triangle"></i> Certificate generation is incomplete. <strong><?= (int)$event_report['missing'] ?></strong> certificates are missing.
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="card-body">
            <?php if (empty($certs)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted mb-3"></i>
                    <h4 class="text-secondary">No Certificates Found</h4>
                    <p class="text-muted">No generated certificates match your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" class="form-check-input" id="check-all"></th>
                                <th>Cert #</th>
                                <th>Recipient Name</th>
                                <th>Event Name</th>
                                <th>Rank / Status</th>
                                <th>Gen. Status</th>
                                <th>Generated At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($certs as $cert): ?>
                                <tr>
                                    <td><input type="checkbox" class="form-check-input cert-check" value="<?= htmlspecialchars((string)$cert['certificate_id'], ENT_QUOTES, 'UTF-8') ?>"></td>
                                    <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($cert['certificate_number'], ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td class="fw-bold"><?= htmlspecialchars($cert['recipient_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($cert['event_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if ($cert['result_status'] === 'Winner'): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-trophy"></i> <?= htmlspecialchars((string)($cert['rank_position'] ?? '1'), ENT_QUOTES, 'UTF-8') ?> Rank</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark">Participant</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cert['generation_status'] === 'Generated'): ?>
                                            <span class="badge bg-success">Generated</span>
                                        <?php elseif ($cert['generation_status'] === 'Regenerated'): ?>
                                            <span class="badge bg-warning text-dark">Regenerated</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Failed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= htmlspecialchars($cert['generated_at'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php if (in_array($cert['generation_status'], ['Generated', 'Regenerated'])): ?>
                                            <div class="btn-group" role="group">
                                                <a href="<?= base_url('/certificates/view?id=' . urlencode((string)$cert['certificate_id'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View PDF">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <a href="<?= base_url('/certificates/download?id=' . urlencode((string)$cert['certificate_id'])) ?>" class="btn btn-sm btn-outline-primary" title="Download PDF">
                                                    <i class="bi bi-download"></i> Download
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">File unavailable</span>
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
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.getElementById('check-all');
    const checkboxes = document.querySelectorAll('.cert-check');
    const btnZip = document.getElementById('btn-download-zip');

    function updateZipButton() {
        const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
        btnZip.disabled = !anyChecked;
    }

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateZipButton();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateZipButton);
    });

    if (btnZip) {
        btnZip.addEventListener('click', function() {
            const ids = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
            if (ids.length > 0) {
                window.location.href = '<?= base_url('/certificates/download/zip') ?>?ids=' + ids.join(',');
            }
        });
    }
});
</script>
