<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : wizard.php
 * Location    : templates/symposiums/events/
 * Description : 7-Step Interactive Coordinator Event Builder Wizard.
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
                <li class="breadcrumb-item active" aria-current="page">Event Builder Wizard</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1">
            <i class="bi bi-magic text-primary me-2"></i>5-Step Event Builder Wizard
        </h2>
        <p class="text-muted mb-0">
            Guided event scheduling for <strong><?= htmlspecialchars($symposium['title']) ?></strong>.
        </p>
    </div>
    <div>
        <a href="<?= base_url() ?>/symposiums/events?symposium_id=<?= $symposium['symposium_id'] ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-lg me-1"></i> Cancel Wizard
        </a>
    </div>
</div>

<!-- Wizard Step Progress Bar Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between position-relative wizard-progress-container">
            <div class="wizard-step active" data-step="1">
                <div class="step-icon">1</div>
                <div class="step-label">Source</div>
            </div>
            <div class="wizard-step" data-step="2">
                <div class="step-icon">2</div>
                <div class="step-label">Category</div>
            </div>
            <div class="wizard-step" data-step="3">
                <div class="step-icon">3</div>
                <div class="step-label">Participation</div>
            </div>
            <div class="wizard-step" data-step="4">
                <div class="step-icon">4</div>
                <div class="step-label">Rules</div>
            </div>
            <div class="wizard-step" data-step="5">
                <div class="step-icon">5</div>
                <div class="step-label">Review</div>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="<?= base_url() ?>/symposiums/events/create" id="wizardForm">
    <input type="hidden" name="symposium_id" value="<?= $symposium['symposium_id'] ?>">
    <input type="hidden" name="is_wizard" value="1">

    <!-- STEP 1: Source Selection -->
    <div class="wizard-pane card border-0 shadow-sm mb-4" id="pane-1">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-collection-fill me-2"></i>Step 1: Choose Event Template Source</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-4">Select whether to schedule an existing template from the Master Event Library or define a brand new event template.</p>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 border rounded-3 bg-light cursor-pointer hover-border-primary text-center source-card active-source" id="card_existing">
                        <input type="radio" name="source_type" id="wiz_src_existing" value="existing" checked class="d-none">
                        <i class="bi bi-journal-bookmark-fill display-4 text-primary mb-2 d-block"></i>
                        <h5 class="fw-bold text-dark mb-1">Use Existing Master Event</h5>
                        <p class="text-muted small mb-0">Select from pre-defined templates (Coding, Paper Presentation, Quiz, etc.).</p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-3 border rounded-3 bg-light cursor-pointer hover-border-success text-center source-card" id="card_new">
                        <input type="radio" name="source_type" id="wiz_src_new" value="new" class="d-none">
                        <i class="bi bi-plus-square-fill display-4 text-success mb-2 d-block"></i>
                        <h5 class="fw-bold text-dark mb-1">Create New Master Event</h5>
                        <p class="text-muted small mb-0">Define a new event template that will also be saved to the Master Library.</p>
                    </div>
                </div>
            </div>

            <!-- Existing template selector (shown by default) -->
            <div class="mt-4" id="existing_select_box">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Filter by Category</label>
                        <select id="wiz_master_filter_category" class="form-select">
                            <option value="">All Categories</option>
                            <option value="Technical">Technical</option>
                            <option value="Non-Technical">Non-Technical</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Select Template <span class="text-danger">*</span></label>
                        <select name="event_id" id="wiz_master_select" class="form-select">
                            <option value="" data-category="">-- Select Master Event Template --</option>
                            <?php foreach ($masterEvents as $me): ?>
                                <?php 
                                    $durType = $me['duration_type'] ?? 'minutes';
                                    $durDisplay = ($durType === 'time_slot') ? 'Time Slot' : ((int)($me['duration_minutes'] ?? 60) . 'm');
                                ?>
                                <option value="<?= $me['event_id'] ?>" data-category="<?= htmlspecialchars($me['category']) ?>">[<?= htmlspecialchars($me['event_code']) ?>] <?= htmlspecialchars($me['event_name']) ?> (<?= htmlspecialchars($me['category']) ?> &bull; <?= htmlspecialchars($me['participation_type']) ?> &bull; <?= $durDisplay ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- New Master Event inline fields (hidden by default) -->
            <div class="mt-4 d-none" id="new_event_box">
                <div class="alert alert-success py-2 small mb-3">
                    <i class="bi bi-info-circle me-1"></i> This will create a new Master Event Template and immediately schedule it for this symposium.
                </div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Event Name <span class="text-danger">*</span></label>
                        <input type="text" name="new_event_name" id="wiz_new_name" class="form-control" placeholder="e.g. Paper Presentation, Hackathon, Quiz...">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                        <select name="new_event_category" id="wiz_new_category" class="form-select">
                            <option value="Technical">Technical</option>
                            <option value="Non-Technical">Non-Technical</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Participation Type</label>
                        <select name="new_participation_type" id="wiz_new_part" class="form-select">
                            <option value="Individual">Individual</option>
                            <option value="Team">Team</option>
                            <option value="Both">Both</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Duration (minutes)</label>
                        <input type="number" name="new_duration_minutes" id="wiz_new_duration" class="form-control" value="60" min="1" max="1440">
                    </div>
                    <div class="col-md-4" id="new_team_size_box" style="display:none;">
                        <label class="form-label fw-bold">Max Team Size</label>
                        <input type="number" name="new_max_team_size" class="form-control" value="4" min="2" max="20">
                    </div>
                </div>
            </div>

        </div>
    </div>


    <!-- STEP 2: Category & Classification -->
    <div class="wizard-pane card border-0 shadow-sm mb-4 d-none" id="pane-2">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-tag-fill me-2"></i>Step 2: Category & Classification</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Category</label>
                    <input type="text" id="wiz_category_display" class="form-control bg-light fw-bold" readonly value="Technical">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Event Code</label>
                    <input type="text" id="wiz_code_display" class="form-control font-monospace bg-light" readonly value="Auto-filled">
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 3: Participation & Team Limits -->
    <div class="wizard-pane card border-0 shadow-sm mb-4 d-none" id="pane-3">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-people-fill me-2"></i>Step 3: Participation Format</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Format</label>
                    <input type="text" id="wiz_part_display" class="form-control bg-light fw-bold" readonly value="Individual">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Time Duration</label>
                    <input type="text" id="wiz_duration_display" class="form-control bg-light" readonly value="">
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 4: Rules Review -->
    <div class="wizard-pane card border-0 shadow-sm mb-4 d-none" id="pane-4">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-card-checklist me-2"></i>Step 4: Rules & Guidelines</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">Master Event rules will be copied here. You can customize them for this symposium without affecting the master template.</p>
            <div class="mb-3">
                <label class="form-label fw-bold">Event Rules <span class="text-danger">*</span></label>
                <textarea name="custom_rules" id="wiz_custom_rules" class="form-control font-monospace" rows="10" placeholder="Rules will load upon template selection..."></textarea>
            </div>
            <div>
                <label class="form-label fw-bold">Custom Symposium Override Notes (Optional)</label>
                <textarea name="custom_notes" class="form-control" rows="2" placeholder="Any specific instructions for this year's symposium..."></textarea>
            </div>
        </div>
    </div>





    <!-- STEP 5: Review & Final Confirmation -->
    <div class="wizard-pane card border-0 shadow-sm mb-4 d-none" id="pane-5">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-primary"><i class="bi bi-check-circle-fill me-2"></i>Step 5: Final Review</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-success py-2 small mb-3">
                <i class="bi bi-info-circle me-1"></i> Venue, Faculty, and Schedule can be assigned later from the event details page.
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 small">
                    <tbody>
                        <tr><td class="fw-bold text-muted" style="width: 30%;">Event Template:</td><td id="rev_event_name" class="fw-bold">--</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Wizard Action Navigation Buttons -->
    <div class="card border-0 shadow-sm bg-light">
        <div class="card-body d-flex justify-content-between">
            <button type="button" class="btn btn-outline-secondary" id="wizPrevBtn" style="display: none;">
                <i class="bi bi-arrow-left me-1"></i> Previous Step
            </button>
            <button type="button" class="btn btn-primary px-4 fw-bold ms-auto" id="wizNextBtn">
                Next Step <i class="bi bi-arrow-right ms-1"></i>
            </button>
            <button type="submit" class="btn btn-success px-4 fw-bold ms-auto" id="wizSubmitBtn" style="display: none;">
                <i class="bi bi-check-lg me-1"></i> Confirm & Schedule Event
            </button>
        </div>
    </div>
</form>

<style>
.wizard-progress-container { margin-bottom: 0; }
.wizard-step { text-align: center; position: relative; flex: 1; opacity: 0.5; }
.wizard-step.active { opacity: 1; }
.wizard-step .step-icon { width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; color: #475569; display: inline-flex; align-items: center; justify-content: center; font-weight: bold; margin-bottom: 4px; }
.wizard-step.active .step-icon { background: #0d6efd; color: #fff; }
.wizard-step.completed .step-icon { background: #198754; color: #fff; }
.wizard-step .step-label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.source-card.active-source { border-color: #0d6efd !important; background-color: #eff6ff !important; }
.source-card.active-source-new { border-color: #198754 !important; background-color: #f0fdf4 !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentStep = 1;
    const totalSteps = 5;

    const prevBtn   = document.getElementById('wizPrevBtn');
    const nextBtn   = document.getElementById('wizNextBtn');
    const submitBtn = document.getElementById('wizSubmitBtn');

    // --- Source type toggle ---
    const cardExisting = document.getElementById('card_existing');
    const cardNew      = document.getElementById('card_new');
    const radioExisting = document.getElementById('wiz_src_existing');
    const radioNew      = document.getElementById('wiz_src_new');
    const existingBox  = document.getElementById('existing_select_box');
    const newBox       = document.getElementById('new_event_box');
    
    // Category Filtering for Existing Templates
    const filterCat = document.getElementById('wiz_master_filter_category');
    const masterSelect = document.getElementById('wiz_master_select');
    
    filterCat.addEventListener('change', function() {
        const cat = this.value;
        Array.from(masterSelect.options).forEach(opt => {
            if (opt.value === "") return; // keep default option
            if (cat === "" || opt.getAttribute('data-category') === cat) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
        masterSelect.value = ""; // reset selection
        document.getElementById('wiz_category_display').value = '';
        document.getElementById('wiz_code_display').value = '';
        document.getElementById('wiz_part_display').value = '';
        document.getElementById('wiz_duration_display').value = '';
        document.getElementById('rev_event_name').innerText = '--';
        document.getElementById('wiz_custom_rules').value = '';
    });

    function setSource(type) {
        if (type === 'existing') {
            radioExisting.checked = true;
            cardExisting.classList.add('active-source');
            cardExisting.classList.remove('active-source-new');
            cardNew.classList.remove('active-source-new', 'active-source');
            existingBox.classList.remove('d-none');
            newBox.classList.add('d-none');
        } else {
            radioNew.checked = true;
            cardNew.classList.add('active-source-new');
            cardNew.classList.remove('active-source');
            cardExisting.classList.remove('active-source', 'active-source-new');
            newBox.classList.remove('d-none');
            existingBox.classList.add('d-none');
        }
    }

    cardExisting.addEventListener('click', () => setSource('existing'));
    cardNew.addEventListener('click', () => setSource('new'));

    // Show/hide team size when participation type changes
    document.getElementById('wiz_new_part').addEventListener('change', function() {
        document.getElementById('new_team_size_box').style.display = this.value !== 'Individual' ? 'block' : 'none';
    });

    function showStep(step) {
        document.querySelectorAll('.wizard-pane').forEach(p => p.classList.add('d-none'));
        document.getElementById('pane-' + step).classList.remove('d-none');

        document.querySelectorAll('.wizard-step').forEach(s => {
            const sNum = parseInt(s.getAttribute('data-step'));
            s.classList.remove('active', 'completed');
            if (sNum === step) s.classList.add('active');
            if (sNum < step) s.classList.add('completed');
        });

        prevBtn.style.display = step === 1 ? 'none' : 'block';
        if (step === totalSteps) {
            nextBtn.style.display = 'none';
            submitBtn.style.display = 'block';
        } else {
            nextBtn.style.display = 'block';
            submitBtn.style.display = 'none';
        }
    }

    nextBtn.addEventListener('click', function() {
        if (currentStep === 1) {
            const isNew = radioNew.checked;
            if (isNew) {
                const newName = document.getElementById('wiz_new_name').value.trim();
                if (!newName) {
                    alert('Please enter an Event Name for the new master event.');
                    document.getElementById('wiz_new_name').focus();
                    return;
                }
                // Fill review from new event fields
                document.getElementById('wiz_category_display').value = document.getElementById('wiz_new_category').value;
                document.getElementById('wiz_code_display').value = 'Auto (New)';
                document.getElementById('wiz_part_display').value = document.getElementById('wiz_new_part').value;
                document.getElementById('wiz_duration_display').value = document.getElementById('wiz_new_duration').value + ' Mins';
                document.getElementById('rev_event_name').innerText = newName + ' (New Template)';
                document.getElementById('wiz_custom_rules').value = '';
                document.getElementById('wiz_custom_rules').placeholder = 'Rules will be configured after the event is created in the Master Library.';
            } else {
                const selectVal = document.getElementById('wiz_master_select').value;
                if (!selectVal) {
                    alert('Please select a Master Event template to proceed.');
                    return;
                }
            }
        }
        if (currentStep < totalSteps) {
            currentStep++;
            showStep(currentStep);
        }
    });

    prevBtn.addEventListener('click', function() {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
    });

    // Auto-fill template data when existing template is selected
    document.getElementById('wiz_master_select').addEventListener('change', function() {
        const id = this.value;
        if (!id) return;
        fetch('<?= base_url() ?>/admin/events/ajax/get-data?id=' + id)
            .then(res => res.json())
            .then(data => {
                if (data.event_name) {
                    document.getElementById('wiz_category_display').value = data.category;
                    document.getElementById('wiz_code_display').value = data.event_code;
                    document.getElementById('wiz_part_display').value = data.participation_type;
                    
                    let durText = '';
                    if (data.duration_type === 'time_slot') {
                        const fmt = t => { if(!t) return '?'; const [h,m] = t.split(':'); const hr = +h; return (hr%12||12)+':'+m+' '+(hr<12?'AM':'PM'); };
                        durText = fmt(data.start_time) + ' to ' + fmt(data.end_time) + ' (' + (data.duration_days||1) + ' Day' + (data.duration_days>1?'s':'') + ')';
                    } else {
                        durText = (data.duration_minutes || 60) + ' Mins';
                    }
                    document.getElementById('wiz_duration_display').value = durText;
                    
                    document.getElementById('rev_event_name').innerText = data.event_name + ' (' + data.event_code + ')';
                    if (data.raw_rules !== undefined) {
                        document.getElementById('wiz_custom_rules').value = data.raw_rules;
                    }
                }
            });
    });

    showStep(1);
});
</script>
