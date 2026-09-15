<?php

declare(strict_types=1);
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($page_title ?? 'Preview Certificate', ENT_QUOTES, 'UTF-8') ?></h1>
    </div>

    <div class="row">
        <div class="col-lg-6 mx-auto">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Select Preview Target</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="<?= base_url('/certificates/preview/pdf') ?>" target="_blank" id="preview-form">
                        <div class="mb-3">
                            <label for="template_id" class="form-label fw-bold">Template <span class="text-danger">*</span></label>
                            <select class="form-select" id="template_id" name="template_id" required>
                                <option value="">-- Choose Template --</option>
                                <?php foreach ($templates ?? [] as $tpl): ?>
                                    <option value="<?= htmlspecialchars((string)$tpl['certificate_template_id'], ENT_QUOTES, 'UTF-8') ?>" <?= (isset($template['certificate_template_id']) && $template['certificate_template_id'] == $tpl['certificate_template_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tpl['template_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="event_id" class="form-label fw-bold">Event</label>
                            <select class="form-select" id="event_id" name="event_id">
                                <option value="">-- Choose Event (Optional) --</option>
                                <?php foreach ($events ?? [] as $ev): ?>
                                    <option value="<?= htmlspecialchars((string)$ev['symposium_event_id'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($ev['event_name'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="result_id" class="form-label fw-bold">Result / Participant</label>
                            <select class="form-select" id="result_id" name="result_id" disabled>
                                <option value="">-- Select Event First --</option>
                            </select>
                            <div class="form-text">Leave blank to preview with dummy data.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-up-right"></i> Open PDF Preview
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const eventSelect = document.getElementById('event_id');
    const resultSelect = document.getElementById('result_id');

    eventSelect.addEventListener('change', function() {
        const eventId = this.value;
        resultSelect.innerHTML = '<option value="">-- Select Result --</option>';
        resultSelect.disabled = true;

        if (eventId) {
            fetch(`<?= base_url('/certificates/ajax/results') ?>?event_id=${eventId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.results && data.results.length > 0) {
                        data.results.forEach(res => {
                            const opt = document.createElement('option');
                            opt.value = res.result_id;
                            // Display student name or team name depending on the response
                            opt.textContent = res.name_display + ' (' + res.result_status + ')';
                            resultSelect.appendChild(opt);
                        });
                        resultSelect.disabled = false;
                    } else {
                        resultSelect.innerHTML = '<option value="">No published results found.</option>';
                    }
                })
                .catch(console.error);
        }
    });
});
</script>
