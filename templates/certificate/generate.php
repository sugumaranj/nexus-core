<?php

declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($page_title ?? 'Generate Certificates', ENT_QUOTES, 'UTF-8') ?></h1>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Generation Configuration</h6>
                </div>
                <div class="card-body">
                    <form method="post" action="<?= base_url('/certificates/generate') ?>" id="generate-form">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Session::get('_token', ''), ENT_QUOTES, 'UTF-8') ?>">
                        
                        <div class="mb-3">
                            <label for="symposium_id" class="form-label fw-bold">Select Symposium <span class="text-danger">*</span></label>
                            <select class="form-select" id="symposium_id" name="symposium_id" required>
                                <option value="">-- Choose Symposium --</option>
                                <?php foreach ($symposiums ?? [] as $symp): ?>
                                    <option value="<?= htmlspecialchars((string)$symp['symposium_id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($symp['symposium_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="event_id" class="form-label fw-bold">Select Event <span class="text-danger">*</span></label>
                            <select class="form-select" id="event_id" name="event_id" required disabled>
                                <option value="">-- Choose Event --</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="template_id" class="form-label fw-bold">Select Template <span class="text-danger">*</span></label>
                            <select class="form-select" id="template_id" name="template_id" required>
                                <option value="">-- Choose Template --</option>
                                <?php foreach ($templates ?? [] as $tpl): ?>
                                    <option value="<?= htmlspecialchars((string)$tpl['certificate_template_id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($tpl['template_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4 p-3 bg-light border rounded">
                            <h6 class="fw-bold mb-3">Options</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="include_participation" id="include_participation" value="1" checked>
                                <label class="form-check-label" for="include_participation">
                                    Include Participation Certificates (for non-winners)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="allow_regenerate" id="allow_regenerate" value="1">
                                <label class="form-check-label" for="allow_regenerate">
                                    Allow Regenerate (Overwrite existing certificates for this event)
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success" id="btn-generate">
                            <i class="bi bi-play-circle"></i> Generate Certificates
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card shadow mb-4" id="preflight-card" style="display: none;">
                <div class="card-header py-3 bg-info text-white">
                    <h6 class="m-0 font-weight-bold"><i class="bi bi-check2-circle"></i> Pre-flight Check</h6>
                </div>
                <div class="card-body">
                    <div id="preflight-content">
                        <!-- Populated by JS -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sympSelect = document.getElementById('symposium_id');
    const eventSelect = document.getElementById('event_id');
    const preflightCard = document.getElementById('preflight-card');
    const preflightContent = document.getElementById('preflight-content');

    sympSelect.addEventListener('change', function() {
        const sympId = this.value;
        eventSelect.innerHTML = '<option value="">-- Choose Event --</option>';
        eventSelect.disabled = true;
        preflightCard.style.display = 'none';

        if (sympId) {
            fetch(`<?= base_url('/certificates/ajax/events') ?>?symposium_id=${sympId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.events && data.events.length > 0) {
                        data.events.forEach(ev => {
                            const opt = document.createElement('option');
                            opt.value = ev.symposium_event_id;
                            opt.textContent = ev.event_name;
                            eventSelect.appendChild(opt);
                        });
                        eventSelect.disabled = false;
                    }
                })
                .catch(console.error);
        }
    });

    eventSelect.addEventListener('change', function() {
        const eventId = this.value;
        if (eventId) {
            // Mock preflight logic or actual AJAX call if backend supports it
            preflightContent.innerHTML = `
                <p class="mb-2"><i class="bi bi-info-circle text-primary"></i> Ready to process.</p>
                <p class="small text-muted">Ensure results are published for this event before generating.</p>
            `;
            preflightCard.style.display = 'block';
        } else {
            preflightCard.style.display = 'none';
        }
    });
});
</script>
