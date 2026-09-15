<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : create.php
 * Location    : templates/admin/events/
 * Description : Master Event Template Creation Form View.
 * -------------------------------------------------------------------------
 */

$formData = $formData ?? [];
$errors   = $errors ?? [];
$old      = $old ?? [];

$categories          = $formData['categories'] ?? ['Technical', 'Non-Technical'];
$participationTypes  = $formData['participation_types'] ?? ['Individual', 'Team', 'Both'];
$sessions            = $formData['sessions'] ?? ['FN', 'AN', 'Full Day'];
$prelimTypes         = $formData['prelim_types'] ?? ['MCQ', 'File Submission', 'Coding Test', 'Abstract Screening', 'Custom'];
$judgingMethods      = $formData['judging_methods'] ?? ['Marks', 'Rubrics', 'Voting', 'Mixed'];
$statuses            = $formData['statuses'] ?? ['Draft', 'Published', 'Archived'];
$ruleSections        = $formData['rule_sections'] ?? ['Eligibility', 'Topics', 'Materials Required', 'Judging Criteria', 'Restrictions', 'Disqualification', 'Notes'];
$venues              = $formData['venues'] ?? [];

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold mb-1">
            <i class="bi bi-plus-circle text-primary me-2"></i>Create Master Event Template
        </h2>
        <p class="text-muted mb-0">
            Define a reusable event template for future college symposiums.
        </p>
    </div>
    <div>
        <a href="<?= base_url() ?>/admin/events" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Library
        </a>
    </div>
</div>

<form method="POST" action="<?= base_url() ?>/admin/events/create" id="masterEventForm">
    <div class="row g-4">

        <!-- Left Column: Core Specifications -->
        <div class="col-lg-8">

            <!-- Card 1: General Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="bi bi-info-circle me-2"></i>1. General Information
                    </h5>
                </div>
                <div class="card-body">
                    
                    <div class="row g-3">
                        <!-- Event Name -->
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Event Name <span class="text-danger">*</span></label>
                            <input type="text" name="event_name" id="event_name" 
                                   class="form-control <?= isset($errors['event_name']) ? 'is-invalid' : '' ?>" 
                                   placeholder="e.g. Paper Presentation, Coding, Quiz" 
                                   value="<?= htmlspecialchars($old['event_name'] ?? '') ?>" required>
                            <?php if (isset($errors['event_name'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['event_name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Auto Code Preview -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Event Code <span class="badge bg-secondary font-monospace">Auto</span></label>
                            <input type="text" name="event_code" id="event_code" class="form-control font-monospace bg-light" 
                                   value="<?= htmlspecialchars($old['event_code'] ?? '') ?>" readonly placeholder="Auto-generated">
                            <small class="text-muted">Generated from event name</small>
                        </div>

                        <!-- Event Description (Optional) -->
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Event Description <span class="text-muted fw-normal">(Optional)</span></label>
                            <input type="text" name="description" id="description" 
                                   class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" 
                                   placeholder="e.g. (Logo, Invitation, Banner, etc.,)" 
                                   value="<?= htmlspecialchars($old['description'] ?? '') ?>">
                            <small class="text-muted">This will be shown as a subtitle in the brochure and final reports.</small>
                            <?php if (isset($errors['description'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['description']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Category -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Category <span class="text-danger">*</span></label>
                            <div class="d-flex gap-3 pt-1">
                                <?php foreach ($categories as $cat): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="category" id="cat_<?= $cat ?>" 
                                               value="<?= $cat ?>" <?= ($old['category'] ?? 'Technical') === $cat ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="cat_<?= $cat ?>">
                                            <?= $cat ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (isset($errors['category'])): ?>
                                <div class="text-danger small mt-1"><?= htmlspecialchars($errors['category']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Status & Version -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Status</label>
                            <select name="status" class="form-select">
                                <option value="Draft" <?= ($old['status'] ?? '') === 'Draft' ? 'selected' : '' ?>>Draft</option>
                                <option value="Published" <?= ($old['status'] ?? 'Published') === 'Published' ? 'selected' : '' ?>>Published</option>
                            </select>
                        </div>



                    </div>

                </div>
            </div>

            <!-- Card 2: Format & Team Config -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="bi bi-people me-2"></i>2. Participation & Duration Defaults
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        
                        <!-- Participation Type -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Participation Type <span class="text-danger">*</span></label>
                            <select name="participation_type" id="participation_type" class="form-select">
                                <?php foreach ($participationTypes as $pt): ?>
                                    <option value="<?= $pt ?>" <?= ($old['participation_type'] ?? 'Individual') === $pt ? 'selected' : '' ?>><?= $pt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Team Size Container (Show/Hide based on participation type) -->
                        <div class="col-12" id="team_size_section" style="display: none;">
                            <div class="p-3 bg-light rounded border">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Minimum Team Size</label>
                                        <input type="number" name="min_team_size" id="min_team_size" class="form-control <?= isset($errors['min_team_size']) ? 'is-invalid' : '' ?>" min="1" max="20" 
                                               value="<?= htmlspecialchars((string)($old['min_team_size'] ?? 2)) ?>">
                                        <?php if(isset($errors['min_team_size'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['min_team_size']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Maximum Team Size</label>
                                        <input type="number" name="max_team_size" id="max_team_size" class="form-control <?= isset($errors['max_team_size']) ? 'is-invalid' : '' ?>" min="1" max="20" 
                                               value="<?= htmlspecialchars((string)($old['max_team_size'] ?? 4)) ?>">
                                        <?php if(isset($errors['max_team_size'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['max_team_size']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Time Duration — Dual Mode -->
                        <?php $durType = $old['duration_type'] ?? 'minutes'; ?>
                        <div class="col-12">
                            <label class="form-label fw-bold">Time Duration <span class="text-danger">*</span></label>
                            <div class="border rounded-3 p-3 bg-light">

                                <!-- Mode Toggle Row -->
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <span class="text-muted small fw-semibold">Type:</span>
                                    <div class="btn-group" role="group">
                                        <input type="radio" class="btn-check" name="duration_type" id="dur_minutes" value="minutes" <?= $durType === 'minutes' ? 'checked' : '' ?>>
                                        <label class="btn btn-sm btn-outline-primary px-3" for="dur_minutes">
                                            <i class="bi bi-stopwatch me-1"></i>Minutes
                                        </label>
                                        <input type="radio" class="btn-check" name="duration_type" id="dur_slot" value="time_slot" <?= $durType === 'time_slot' ? 'checked' : '' ?>>
                                        <label class="btn btn-sm btn-outline-primary px-3" for="dur_slot">
                                            <i class="bi bi-calendar-range me-1"></i>Day Event (Time Slot)
                                        </label>
                                    </div>
                                </div>

                                <!-- Mode A: Simple Minutes -->
                                <div id="dur_mode_minutes" style="display: <?= $durType === 'minutes' ? 'block' : 'none' ?>;">
                                    <div class="row align-items-center g-2">
                                        <div class="col-auto">
                                            <div class="input-group" style="width: 200px;">
                                                <input type="number" name="duration_minutes" id="duration_minutes_input"
                                                       class="form-control form-control-lg text-center fw-bold <?= isset($errors['duration_minutes']) ? 'is-invalid' : '' ?>"
                                                       min="1" max="1440" placeholder="60"
                                                       value="<?= htmlspecialchars((string)($old['duration_minutes'] ?? '')) ?>">
                                                <span class="input-group-text fw-semibold">mins</span>
                                                <?php if(isset($errors['duration_minutes'])): ?>
                                                    <div class="invalid-feedback"><?= htmlspecialchars($errors['duration_minutes']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="text-muted small">
                                                <i class="bi bi-lightbulb text-warning me-1"></i>
                                                e.g. Paper Presentation → <strong>5 mins</strong> &nbsp;|&nbsp; Coding → <strong>60 mins</strong> &nbsp;|&nbsp; Debate → <strong>30 mins</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mode B: Time Slot -->
                                <div id="dur_mode_slot" style="display: <?= $durType === 'time_slot' ? 'block' : 'none' ?>;">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold text-muted mb-1"><i class="bi bi-play-circle me-1 text-success"></i>Start Time</label>
                                            <input type="time" name="start_time"
                                                   class="form-control <?= isset($errors['start_time']) ? 'is-invalid' : '' ?>"
                                                   value="<?= htmlspecialchars($old['start_time'] ?? '') ?>">
                                            <?php if(isset($errors['start_time'])): ?>
                                                <div class="invalid-feedback"><?= htmlspecialchars($errors['start_time']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold text-muted mb-1"><i class="bi bi-stop-circle me-1 text-danger"></i>End Time</label>
                                            <input type="time" name="end_time"
                                                   class="form-control <?= isset($errors['end_time']) ? 'is-invalid' : '' ?>"
                                                   value="<?= htmlspecialchars($old['end_time'] ?? '') ?>">
                                            <?php if(isset($errors['end_time'])): ?>
                                                <div class="invalid-feedback"><?= htmlspecialchars($errors['end_time']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold text-muted mb-1"><i class="bi bi-calendar-week me-1 text-primary"></i>No. of Days</label>
                                            <div class="input-group">
                                                <input type="number" name="duration_days" min="1" max="30"
                                                       class="form-control <?= isset($errors['duration_days']) ? 'is-invalid' : '' ?>"
                                                       value="<?= htmlspecialchars((string)($old['duration_days'] ?? 1)) ?>">
                                                <span class="input-group-text">day(s)</span>
                                                <?php if(isset($errors['duration_days'])): ?>
                                                    <div class="invalid-feedback"><?= htmlspecialchars($errors['duration_days']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2 p-2 bg-white rounded border" id="duration_preview" style="display:none;"></div>
                                    <div class="text-muted small mt-2">
                                        <i class="bi bi-lightbulb text-warning me-1"></i>
                                        e.g. Treasure Hunt → <strong>10:30 AM to 2:30 PM · 1 Day</strong>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Card 3: Rules & Guidelines -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="bi bi-card-checklist me-2"></i>3. Rules & Guidelines
                    </h5>
                    <span class="badge bg-light text-dark border"><i class="bi bi-file-word me-1 text-primary"></i>Word-Style Editor</span>
                </div>
                <div class="card-body">
                    <label class="form-label fw-bold">Rules & Guidelines Content</label>
                    
                    <!-- Word-Like Editor Toolbar -->
                    <div class="rules-toolbar p-2 bg-light border border-bottom-0 rounded-top d-flex flex-wrap gap-1 align-items-center">
                        <!-- Text Formatting -->
                        <div class="btn-group btn-group-sm me-2 mb-1" role="group">
                            <button type="button" class="btn btn-outline-secondary fw-bold" onmousedown="event.preventDefault(); document.execCommand('bold');" title="Bold (Ctrl+B)"><b>B</b></button>
                            <button type="button" class="btn btn-outline-secondary fst-italic" onmousedown="event.preventDefault(); document.execCommand('italic');" title="Italic (Ctrl+I)"><i>I</i></button>
                            <button type="button" class="btn btn-outline-secondary text-decoration-underline" onmousedown="event.preventDefault(); document.execCommand('underline');" title="Underline (Ctrl+U)"><u>U</u></button>
                            <button type="button" class="btn btn-outline-secondary text-decoration-line-through" onmousedown="event.preventDefault(); document.execCommand('strikeThrough');" title="Strikethrough"><s>S</s></button>
                        </div>

                        <!-- Bullet Styles Dropdown & Quick Buttons -->
                        <div class="btn-group btn-group-sm me-2 mb-1">
                            <button type="button" class="btn btn-outline-primary" onmousedown="event.preventDefault(); insertBulletWysiwyg('• ')" title="Standard Disc Bullet (•)"><i class="bi bi-list-task me-1"></i> • Disc</button>
                            <button type="button" class="btn btn-outline-primary" onmousedown="event.preventDefault(); insertBulletWysiwyg('▪ ')" title="Square Bullet (▪)">▪ Square</button>
                            <button type="button" class="btn btn-outline-primary" onmousedown="event.preventDefault(); insertBulletWysiwyg('➢ ')" title="Arrow Bullet (➢)">➢ Arrow</button>
                            <button type="button" class="btn btn-outline-primary" onmousedown="event.preventDefault(); insertBulletWysiwyg('1. ')" title="Numbered List (1. 2. 3.)"><i class="bi bi-list-ol me-1"></i> 1. 2. 3.</button>
                            <button type="button" class="btn btn-outline-primary" onmousedown="event.preventDefault(); insertBulletWysiwyg('A. ')" title="Alphabetical List (A. B. C.)">A. B. C.</button>
                        </div>

                        <!-- Indent / Outdent Controls -->
                        <div class="btn-group btn-group-sm me-2 mb-1">
                            <button type="button" class="btn btn-outline-secondary" onmousedown="event.preventDefault(); document.execCommand('indent');" title="Indent Text (Tab)"><i class="bi bi-text-indent-left me-1"></i> Indent</button>
                            <button type="button" class="btn btn-outline-secondary" onmousedown="event.preventDefault(); document.execCommand('outdent');" title="Outdent Text (Shift+Tab)"><i class="bi bi-text-indent-right me-1"></i> Outdent</button>
                        </div>

                        <!-- Section Header Quick Add -->
                        <div class="btn-group btn-group-sm mb-1">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-fonts me-1"></i> Section Header
                            </button>
                            <ul class="dropdown-menu shadow-sm">
                                <li><a class="dropdown-item" href="javascript:void(0)" onmousedown="event.preventDefault(); insertHeaderWysiwyg('Eligibility Rules')">[Eligibility Rules]</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onmousedown="event.preventDefault(); insertHeaderWysiwyg('Topics & Themes')">[Topics & Themes]</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onmousedown="event.preventDefault(); insertHeaderWysiwyg('Judging Criteria')">[Judging Criteria]</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onmousedown="event.preventDefault(); insertHeaderWysiwyg('Disqualification Rules')">[Disqualification Rules]</a></li>
                            </ul>
                        </div>
                    </div>

                    <!-- WYSIWYG Contenteditable Editor -->
                    <div id="rules_wysiwyg_editor"
                         contenteditable="true"
                         class="form-control rounded-top-0 border-top-0 rules-wysiwyg-editor"
                         style="min-height: 200px; font-family: 'Segoe UI', system-ui, sans-serif; font-size: 0.93rem; line-height: 1.8; white-space: pre-wrap; word-break: break-word; overflow-y: auto;"
                         data-placeholder="Enter rules and guidelines here..."
                    ><?= !empty($old['rules_text']) ? $old['rules_text'] : '' ?></div>

                    <!-- Hidden textarea to carry value on form submit -->
                    <textarea name="rules_text" id="rules_text_editor" style="display:none;"></textarea>

                    <div class="form-text mt-2 text-muted d-flex justify-content-between align-items-center flex-wrap gap-1">
                        <span><i class="bi bi-info-circle me-1 text-primary"></i> Select text then click <strong>B</strong>, <em>I</em>, or <u>U</u> to format. Use bullet buttons to add list items.</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Settings & Module Flags -->
        <div class="col-lg-4">

            <!-- Card: Prelims Round & Submission Options -->
            <div class="card shadow-sm border-0 mb-4 rounded-4">
                <div class="card-header bg-white border-0 pt-4 pb-0">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-journal-check me-2 text-primary"></i>Prelims Round
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Switch 1: Requires Prelims -->
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="requires_prelims" value="0">
                        <input class="form-check-input" type="checkbox" name="requires_prelims" id="requires_prelims" value="1"
                               <?= !empty($old['requires_prelims']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="requires_prelims">Enable Prelims Screening Round</label>
                    </div>

                    <hr class="my-3 text-muted">


                </div>
            </div>

            <!-- Card: Evaluation Settings -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-award me-2"></i>Evaluation Settings
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Judging Method</label>
                        <select name="judging_method" class="form-select form-select-sm">
                            <?php foreach ($judgingMethods as $jm): ?>
                                <option value="<?= $jm ?>" <?= ($old['judging_method'] ?? 'Marks') === $jm ? 'selected' : '' ?>><?= $jm ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Maximum Score <span class="text-primary">(Default: 10)</span></label>
                        <input type="number" name="maximum_score" class="form-control form-control-sm" step="0.01" value="<?= htmlspecialchars((string)($old['maximum_score'] ?? '10.00')) ?>">
                    </div>
                </div>
            </div>

            <!-- Card: Module Toggle Flags (Req #9 Cascade) -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-toggle-on me-2"></i>Module Features
                    </h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="supports_registration" id="flag_reg" value="1" checked>
                        <label class="form-check-label fw-semibold small" for="flag_reg">Enable Student Registration</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="supports_attendance" id="flag_att" value="1" checked>
                        <label class="form-check-label fw-semibold small" for="flag_att">Enable Attendance Tracking</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="supports_evaluation" id="flag_eval" value="1" checked>
                        <label class="form-check-label fw-semibold small" for="flag_eval">Enable Judge Evaluation</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="supports_certificates" id="flag_cert" value="1" checked>
                        <label class="form-check-label fw-semibold small" for="flag_cert">Enable Certificate Generation</label>
                    </div>

                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2 py-2 fw-bold">
                        <i class="bi bi-check-lg me-1"></i> Save Master Event Template
                    </button>
                    <a href="<?= base_url() ?>/admin/events" class="btn btn-outline-secondary w-100 btn-sm">Cancel</a>
                </div>
            </div>

        </div>

    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Auto Event Code Generation on Event Name input
    const nameInput = document.getElementById('event_name');
    const codeInput = document.getElementById('event_code');

    let debounceTimer;
    nameInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const nameVal = this.value.trim();
        if (nameVal.length === 0) {
            codeInput.value = '';
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch('<?= base_url() ?>/admin/events/ajax/generate-code?name=' + encodeURIComponent(nameVal))
                .then(res => res.json())
                .then(data => {
                    if (data.code) {
                        codeInput.value = data.code;
                    }
                })
                .catch(err => console.error('Error generating code:', err));
        }, 300);
    });

    // 2. Participation Type Toggle (Individual vs Team)
    const participationSelect = document.getElementById('participation_type');
    const teamSizeSection = document.getElementById('team_size_section');

    function toggleTeamSection() {
        const isTeam = participationSelect.value !== 'Individual';
        teamSizeSection.style.display = isTeam ? 'block' : 'none';
    }
    participationSelect.addEventListener('change', toggleTeamSection);
    toggleTeamSection();

    // Duration Mode Toggle
    const durModeMinutes = document.getElementById('dur_mode_minutes');
    const durModeSlot    = document.getElementById('dur_mode_slot');
    const durRadios      = document.querySelectorAll('input[name="duration_type"]');

    function toggleDurMode() {
        const isSlot = document.getElementById('dur_slot').checked;
        durModeMinutes.style.display = isSlot ? 'none' : 'block';
        durModeSlot.style.display    = isSlot ? 'block' : 'none';
        if (isSlot) updateDurationPreview();
    }
    durRadios.forEach(r => r.addEventListener('change', toggleDurMode));

    // Time Slot Live Preview
    const startInput = document.querySelector('input[name="start_time"]');
    const endInput   = document.querySelector('input[name="end_time"]');
    const daysInput  = document.querySelector('input[name="duration_days"]');
    const preview    = document.getElementById('duration_preview');

    function updateDurationPreview() {
        if (!startInput || !endInput || !daysInput || !preview) return;
        const s = startInput.value, e = endInput.value, d = parseInt(daysInput.value) || 1;
        if (s && e) {
            const fmt = t => { const [h,m] = t.split(':'); const hr = +h; return (hr%12||12)+':'+m+' '+(hr<12?'AM':'PM'); };
            const [sh,sm] = s.split(':').map(Number);
            const [eh,em] = e.split(':').map(Number);
            const totalMins = ((eh * 60 + em) - (sh * 60 + sm)) * d;
            const dayLabel = d > 1 ? `${d} Days` : '1 Day';
            const minBadge = totalMins > 0 ? ` &nbsp;<span class="badge bg-secondary">${totalMins} mins total</span>` : '';
            preview.style.display = 'block';
            preview.innerHTML = `<i class="bi bi-clock text-primary me-2"></i><strong>${fmt(s)} &ndash; ${fmt(e)}</strong> &nbsp;&middot;&nbsp; <span class="badge bg-primary">${dayLabel}</span>${minBadge}`;
        } else {
            preview.style.display = 'none';
            preview.innerHTML = '';
        }
    }
    if (startInput) startInput.addEventListener('input', updateDurationPreview);
    if (endInput)   endInput.addEventListener('input', updateDurationPreview);
    if (daysInput)  daysInput.addEventListener('input', updateDurationPreview);
    updateDurationPreview();

    // 3. Prelims Toggle
    // Remove prelimsCheck JS as it's no longer needed
    // 4. Registration Cascade Rule (Registration OFF => Attendance, Eval, Cert OFF)
    const flagReg = document.getElementById('flag_reg');
    const flagAtt = document.getElementById('flag_att');
    const flagEval = document.getElementById('flag_eval');
    const flagCert = document.getElementById('flag_cert');

    flagReg.addEventListener('change', function() {
        if (!this.checked) {
            flagAtt.checked = false;
            flagEval.checked = false;
            flagCert.checked = false;
            flagAtt.disabled = true;
            flagEval.disabled = true;
            flagCert.disabled = true;
        } else {
            flagAtt.disabled = false;
            flagEval.disabled = false;
            flagCert.disabled = false;
        }
    });

    // 5. WYSIWYG Rules Editor — sync contenteditable -> hidden textarea on form submit
    const wysiwygEditor = document.getElementById('rules_wysiwyg_editor');
    const hiddenTextarea = document.getElementById('rules_text_editor');
    const masterEventForm = document.getElementById('masterEventForm');

    // Sync on form submit
    if (masterEventForm && wysiwygEditor && hiddenTextarea) {
        masterEventForm.addEventListener('submit', function() {
            hiddenTextarea.value = wysiwygEditor.innerHTML;
        });
    }

    // Placeholder behaviour
    if (wysiwygEditor) {
        wysiwygEditor.addEventListener('focus', function() {
            if (this.innerHTML.trim() === '') this.classList.add('is-empty');
        });
        wysiwygEditor.addEventListener('input', function() {
            this.classList.toggle('is-empty', this.innerHTML.trim() === '' || this.innerHTML === '<br>');
        });
        // Trigger initial check
        if (wysiwygEditor.innerHTML.trim() === '') wysiwygEditor.classList.add('is-empty');
    }
});

// --- WYSIWYG Bullet insertion ---
function insertBulletWysiwyg(bullet) {
    const editor = document.getElementById('rules_wysiwyg_editor');
    if (!editor) return;
    editor.focus();
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    const range = sel.getRangeAt(0);
    range.deleteContents();
    const textNode = document.createTextNode(bullet);
    range.insertNode(textNode);
    range.setStartAfter(textNode);
    range.setEndAfter(textNode);
    sel.removeAllRanges();
    sel.addRange(range);
}

// --- WYSIWYG Section Header insertion ---
function insertHeaderWysiwyg(headerTitle) {
    const editor = document.getElementById('rules_wysiwyg_editor');
    if (!editor) return;
    editor.focus();
    document.execCommand('insertHTML', false,
        `<div><strong style="color:#0d6efd; font-size:0.85em; text-transform:uppercase; letter-spacing:0.08em;">[${headerTitle}]</strong></div><div><br></div>`);
}
</script>
<style>
#rules_wysiwyg_editor:empty::before,
#rules_wysiwyg_editor.is-empty::before {
    content: attr(data-placeholder);
    color: #adb5bd;
    pointer-events: none;
    display: block;
}
#rules_wysiwyg_editor:focus {
    outline: 0;
    box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25);
    border-color: #86b7fe;
}
</style>
