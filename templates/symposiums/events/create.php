<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : create.php
 * Location    : templates/symposiums/events/
 * Description : Add Event to Symposium View (Existing vs New).
 * -------------------------------------------------------------------------
 */

$symposium = $symposium ?? [];
$formData  = $formData ?? [];
$errors    = $errors ?? [];
$old       = $old ?? [];

$masterEvents = $formData['master_events'] ?? [];
$venues       = $formData['venues'] ?? [];
$facultyUsers = $formData['faculty_users'] ?? [];
$sessions     = $formData['sessions'] ?? ['FN', 'AN', 'Full Day'];
$eventModes   = $formData['event_modes'] ?? ['Offline', 'Online', 'Hybrid'];

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= base_url() ?>/symposiums">Symposiums</a></li>
                <li class="breadcrumb-item"><a href="<?= base_url() ?>/symposiums/events?symposium_id=<?= $symposium['symposium_id'] ?>">Events</a></li>
                <li class="breadcrumb-item active" aria-current="page">Add Event</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1">
            <i class="bi bi-plus-circle text-primary me-2"></i>Add Event to <?= htmlspecialchars($symposium['title']) ?>
        </h2>
        <p class="text-muted mb-0">
            Choose an existing template from the Master Library or create a new master event.
        </p>
    </div>
    <div>
        <a href="<?= base_url() ?>/symposiums/events?symposium_id=<?= $symposium['symposium_id'] ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Events Schedule
        </a>
    </div>
</div>

<form method="POST" action="<?= base_url() ?>/symposiums/events/create" id="addSymposiumEventForm">
    <input type="hidden" name="symposium_id" value="<?= $symposium['symposium_id'] ?>">

    <!-- Source Type Selection -->
    <div class="card border-0 shadow-sm mb-4 bg-light">
        <div class="card-body p-4 text-center">
            <h5 class="fw-bold mb-3 text-dark">How would you like to set up this event?</h5>
            <?php $srcType = $old['source_type'] ?? 'existing'; ?>
            <div class="btn-group shadow-sm" role="group" aria-label="Event Source Selection">
                <input type="radio" class="btn-check" name="source_type" id="src_existing" value="existing" autocomplete="off" <?= $srcType === 'existing' ? 'checked' : '' ?>>
                <label class="btn btn-outline-primary px-4 py-2 fw-semibold" for="src_existing">
                    <i class="bi bi-collection-fill me-2"></i>Single Master Event
                </label>
              
                <input type="radio" class="btn-check" name="source_type" id="src_bulk" value="bulk_import" autocomplete="off" <?= $srcType === 'bulk_import' ? 'checked' : '' ?>>
                <label class="btn btn-outline-primary px-4 py-2 fw-semibold" for="src_bulk">
                    <i class="bi bi-stack me-2"></i>Bulk Import Events
                </label>

                <input type="radio" class="btn-check" name="source_type" id="src_new" value="new" autocomplete="off" <?= $srcType === 'new' ? 'checked' : '' ?>>
                <label class="btn btn-outline-primary px-4 py-2 fw-semibold" for="src_new">
                    <i class="bi bi-plus-square-fill me-2"></i>Create New Master Event
                </label>
            </div>
            <p class="text-muted small mt-3 mb-0" id="source_help_text">Choosing a single event lets you use a template and customize its configuration exclusively for this symposium.</p>
        </div>
    </div>

    <!-- Section 1: Event Template Selection -->
    <div id="existing_event_section">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-search me-2"></i>Select Master Event Template</h5>
            </div>
            <div class="card-body">
                <?php if (empty($masterEvents)): ?>
                    <div class="alert alert-success d-flex align-items-center mb-0" role="alert">
                        <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                        <div>
                            <strong>All Caught Up!</strong><br>
                            All available active Master Events have already been imported into this symposium. There are no more events left to import.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Filter by Category</label>
                            <select id="category_filter" class="form-select">
                                <option value="All">All Categories</option>
                                <option value="Technical">Technical</option>
                                <option value="Non-Technical">Non-Technical</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Search Master Event <span class="text-danger">*</span></label>
                            <select name="event_id" id="master_event_select" class="form-select <?= isset($errors['event_id']) ? 'is-invalid' : '' ?>">
                                <option value="" data-category="All">-- Choose an Event Template from Library --</option>
                                <?php foreach ($masterEvents as $me): ?>
                                    <option value="<?= $me['event_id'] ?>" data-category="<?= htmlspecialchars($me['category']) ?>" <?= ((int)($old['event_id'] ?? 0) === (int)$me['event_id']) ? 'selected' : '' ?>>
                                        [<?= htmlspecialchars($me['event_code']) ?>] <?= htmlspecialchars($me['event_name']) ?> (<?= htmlspecialchars($me['category']) ?> &bull; <?= htmlspecialchars($me['participation_type']) ?> &bull; <?= (int)($me['duration_minutes'] ?? 60) ?>m)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['event_id'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['event_id']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Section 1C: Bulk Import Selection -->
    <div id="bulk_import_section" style="display: none;">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-check2-square me-2"></i>Select Events to Import</h5>
                <div>
                    <button type="button" id="btn_bulk_check_all" class="btn btn-sm btn-primary me-2"><i class="bi bi-check-all me-1"></i>Select All for Import</button>
                    <button type="button" id="btn_bulk_uncheck_all" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x me-1"></i>Deselect All</button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($masterEvents)): ?>
                    <div class="alert alert-success d-flex align-items-center mb-0" role="alert">
                        <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                        <div>
                            <strong>All Caught Up!</strong><br>
                            All available active Master Events have already been imported into this symposium. There are no more events left to import.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Filter by Category</label>
                            <select id="bulk_category_filter" class="form-select">
                                <option value="All">All Categories</option>
                                <option value="Technical">Technical</option>
                                <option value="Non-Technical">Non-Technical</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="p-3 bg-light rounded border mb-3">
                        <p class="mb-2 text-muted small"><i class="bi bi-info-circle me-1"></i> These events will be imported using their default configurations from the Master Library. You can individually edit their schedules later.</p>
                    </div>
                    
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3" id="bulk_events_container">
                        <?php foreach ($masterEvents as $me): ?>
                            <div class="col bulk-event-item" data-category="<?= htmlspecialchars($me['category']) ?>">
                                <div class="form-check p-3 border rounded h-100 bg-white shadow-sm d-flex align-items-center">
                                    <input class="form-check-input ms-0 me-3" type="checkbox" name="bulk_event_ids[]" value="<?= $me['event_id'] ?>" id="bulk_evt_<?= $me['event_id'] ?>" style="transform: scale(1.3);">
                                    <label class="form-check-label w-100 cursor-pointer" for="bulk_evt_<?= $me['event_id'] ?>">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($me['event_name']) ?></div>
                                        <div class="small text-muted">[<?= htmlspecialchars($me['event_code']) ?>] &bull; <?= htmlspecialchars($me['category']) ?></div>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Section 2: Event Configuration (Shared Form) -->
    <div id="event_form_container">
        <div id="new_event_alert" class="p-3 mb-4 rounded bg-success-subtle text-success-emphasis border border-success-subtle shadow-sm" style="display: none;">
            <i class="bi bi-info-circle-fill me-2"></i>This will create a new Master Event Template and immediately schedule it for this symposium.
        </div>
        <div id="existing_event_alert" class="p-3 mb-4 rounded bg-info-subtle text-info-emphasis border border-info-subtle shadow-sm">
            <i class="bi bi-pencil-square me-2"></i>You can override these default configurations for this symposium. The original Master Event will remain unchanged.
        </div>
        <?php
            $originalOld = $old;
            $old = $old['master'] ?? [];
            include __DIR__ . '/_new_master_form.php';
            $old = $originalOld;
        ?>
    </div>

    <!-- Submit Button -->
    <div class="card border-0 shadow-sm bg-light">
        <div class="card-body d-flex justify-content-between align-items-center">
            <a href="<?= base_url() ?>/symposiums/events?symposium_id=<?= $symposium['symposium_id'] ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4 fw-bold">
                <i class="bi bi-check-lg me-1"></i> Schedule Event in Symposium
            </button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const srcExisting = document.getElementById('src_existing');
    const srcBulk = document.getElementById('src_bulk');
    const srcNew = document.getElementById('src_new');
    
    const sectionExisting = document.getElementById('existing_event_section');
    const sectionBulk = document.getElementById('bulk_import_section');
    
    const eventFormContainer = document.getElementById('event_form_container');
    const newEventAlert = document.getElementById('new_event_alert');
    const existingEventAlert = document.getElementById('existing_event_alert');

    const masterSelect = document.getElementById('master_event_select');
    const categoryFilter = document.getElementById('category_filter');

    // Filter Master Events by Category (Single)
    if (categoryFilter && masterSelect) {
        categoryFilter.addEventListener('change', function() {
            const cat = this.value;
            masterSelect.value = ''; // Reset selection
            eventFormContainer.style.display = 'none';

            Array.from(masterSelect.options).forEach(opt => {
                if (opt.value === '') return; // Skip placeholder
                
                if (cat === 'All' || opt.getAttribute('data-category') === cat) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                }
            });
        });
    }

    // Toggle Source Mode
    function toggleSource() {
        // Reset visibility
        sectionExisting.style.display = 'none';
        sectionBulk.style.display = 'none';
        eventFormContainer.style.display = 'none';
        newEventAlert.style.display = 'none';
        existingEventAlert.style.display = 'none';

        const formInputs = eventFormContainer.querySelectorAll('input, select, textarea');

        if (srcNew.checked) {
            document.getElementById('addSymposiumEventForm').reset();
            srcNew.checked = true; // restore radio
            
            eventFormContainer.style.display = 'block';
            newEventAlert.style.display = 'block';
            formInputs.forEach(el => el.disabled = false);
        } else if (srcBulk.checked) {
            sectionBulk.style.display = 'block';
            formInputs.forEach(el => el.disabled = true);
        } else {
            sectionExisting.style.display = 'block';
            if (masterSelect && masterSelect.value) {
                eventFormContainer.style.display = 'block';
            }
            existingEventAlert.style.display = 'block';
            formInputs.forEach(el => el.disabled = false);
        }
    }

    if (srcExisting && srcNew && srcBulk) {
        srcExisting.addEventListener('change', toggleSource);
        srcBulk.addEventListener('change', toggleSource);
        srcNew.addEventListener('change', toggleSource);
        toggleSource(); // initial call
    }

    // Bulk Import Logic
    const bulkCatFilter = document.getElementById('bulk_category_filter');
    const bulkItems = document.querySelectorAll('.bulk-event-item');
    
    if (bulkCatFilter) {
        bulkCatFilter.addEventListener('change', function() {
            const cat = this.value;
            bulkItems.forEach(item => {
                if (cat === 'All' || item.getAttribute('data-category') === cat) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    const btnCheckAll = document.getElementById('btn_bulk_check_all');
    const btnUncheckAll = document.getElementById('btn_bulk_uncheck_all');

    if (btnCheckAll) {
        btnCheckAll.addEventListener('click', () => {
            bulkItems.forEach(item => {
                if (item.style.display !== 'none') {
                    item.querySelector('.form-check-input').checked = true;
                }
            });
        });
    }

    if (btnUncheckAll) {
        btnUncheckAll.addEventListener('click', () => {
            bulkItems.forEach(item => {
                if (item.style.display !== 'none') {
                    item.querySelector('.form-check-input').checked = false;
                }
            });
        });
    }

    // Handle Existing Master Event Selection
    masterSelect.addEventListener('change', function() {
        const eventId = this.value;
        if (!eventId) {
            eventFormContainer.style.display = 'none';
            return;
        }

        // Show form & alert
        eventFormContainer.style.display = 'block';
        existingEventAlert.style.display = 'block';
        newEventAlert.style.display = 'none';

        // Fetch master event template data for auto-fill
        fetch('<?= base_url() ?>/admin/events/ajax/get-data?id=' + eventId)
            .then(res => res.json())
            .then(data => {
                if (data.event_name) {
                    // Populate Form Fields
                    document.getElementById('event_name').value = data.event_name;
                    document.getElementById('event_code').value = data.event_code;
                    
                    const catRadio = document.getElementById('cat_' + data.category);
                    if (catRadio) catRadio.checked = true;

                    const pType = document.getElementById('participation_type');
                    if (pType) {
                        pType.value = data.participation_type;
                        pType.dispatchEvent(new Event('change'));
                    }

                    if (data.min_team_size) document.getElementById('min_team_size').value = data.min_team_size;
                    if (data.max_team_size) document.getElementById('max_team_size').value = data.max_team_size;

                    if (data.duration_type === 'time_slot') {
                        document.getElementById('dur_slot').checked = true;
                    } else {
                        document.getElementById('dur_minutes').checked = true;
                    }
                    document.getElementById('dur_minutes').dispatchEvent(new Event('change'));

                    if (data.duration_minutes) document.getElementById('duration_minutes_input').value = data.duration_minutes;
                    
                    // document.getElementById('requires_prelims').checked = (data.requires_prelims == 1);
                    // document.getElementById('requires_prelims').dispatchEvent(new Event('change'));

                    if (data.prelim_type) {
                        const ptSelect = document.querySelector('select[name="master[prelim_type]"]');
                        if (ptSelect) ptSelect.value = data.prelim_type;
                    }

                    document.getElementById('flag_reg').checked = (data.supports_registration == 1);
                    document.getElementById('flag_att').checked = (data.supports_attendance == 1);
                    document.getElementById('flag_eval').checked = (data.supports_evaluation == 1);
                    document.getElementById('flag_cert').checked = (data.supports_certificates == 1);

                    const jmSelect = document.querySelector('select[name="master[judging_method]"]');
                    if (jmSelect && data.judging_method) jmSelect.value = data.judging_method;
                    
                    const scoreInput = document.querySelector('input[name="master[maximum_score]"]');
                    if (scoreInput && data.maximum_score) scoreInput.value = data.maximum_score;

                    if (data.raw_rules) {
                        document.getElementById('rules_text_editor').value = data.raw_rules;
                    } else {
                        document.getElementById('rules_text_editor').value = '';
                    }
                }
            })
            .catch(err => console.error('Error fetching master event details:', err));
    });
});
</script>

<?php include __DIR__ . '/_new_master_js.php'; ?>
