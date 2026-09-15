<?php 
    $categories = $categories ?? ['Technical', 'Non-Technical'];
    $participationTypes = $participationTypes ?? ['Individual', 'Team', 'Both'];
    $judgingMethods = $judgingMethods ?? ['Marks', 'Rubric', 'Pass/Fail'];
?>
<div class="row g-4">
<?php
// If this form is included in a context where errors are prefixed with master_, normalize them
if (isset($errors) && is_array($errors)) {
    foreach ($errors as $k => $v) {
        if (str_starts_with($k, 'master_')) {
            $errors[substr($k, 7)] = $v;
        }
    }
}
?>
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
                            <input type="text" name="master[event_name]" id="event_name" 
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
                            <input type="text" name="master[event_code]" id="event_code" class="form-control font-monospace bg-light" 
                                   value="<?= htmlspecialchars($old['event_code'] ?? '') ?>" readonly placeholder="Auto-generated">
                            <small class="text-muted">Generated from event name</small>
                        </div>

                        <!-- Event Description (Optional) -->
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Event Description <span class="text-muted fw-normal">(Optional)</span></label>
                            <input type="text" name="master[description]" id="description" 
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
                                        <input class="form-check-input" type="radio" name="master[category]" id="cat_<?= $cat ?>" 
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
                            <select name="master[status]" class="form-select">
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
                            <select name="master[participation_type]" id="participation_type" class="form-select">
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
                                        <input type="number" name="master[min_team_size]" id="min_team_size" class="form-control <?= isset($errors['min_team_size']) ? 'is-invalid' : '' ?>" min="1" max="20" 
                                               value="<?= htmlspecialchars((string)($old['min_team_size'] ?? 2)) ?>">
                                        <?php if(isset($errors['min_team_size'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['min_team_size']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Maximum Team Size</label>
                                        <input type="number" name="master[max_team_size]" id="max_team_size" class="form-control <?= isset($errors['max_team_size']) ? 'is-invalid' : '' ?>" min="1" max="20" 
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
                                        <input type="radio" class="btn-check" name="master[duration_type]" id="dur_minutes" value="minutes" <?= $durType === 'minutes' ? 'checked' : '' ?>>
                                        <label class="btn btn-sm btn-outline-primary px-3" for="dur_minutes">
                                            <i class="bi bi-stopwatch me-1"></i>Minutes
                                        </label>
                                        <input type="radio" class="btn-check" name="master[duration_type]" id="dur_slot" value="time_slot" <?= $durType === 'time_slot' ? 'checked' : '' ?>>
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
                                                <input type="number" name="master[duration_minutes]" id="duration_minutes_input"
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
                                            <input type="time" name="master[start_time]"
                                                   class="form-control <?= isset($errors['start_time']) ? 'is-invalid' : '' ?>"
                                                   value="<?= htmlspecialchars($old['start_time'] ?? '') ?>">
                                            <?php if(isset($errors['start_time'])): ?>
                                                <div class="invalid-feedback"><?= htmlspecialchars($errors['start_time']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold text-muted mb-1"><i class="bi bi-stop-circle me-1 text-danger"></i>End Time</label>
                                            <input type="time" name="master[end_time]"
                                                   class="form-control <?= isset($errors['end_time']) ? 'is-invalid' : '' ?>"
                                                   value="<?= htmlspecialchars($old['end_time'] ?? '') ?>">
                                            <?php if(isset($errors['end_time'])): ?>
                                                <div class="invalid-feedback"><?= htmlspecialchars($errors['end_time']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold text-muted mb-1"><i class="bi bi-calendar-week me-1 text-primary"></i>No. of Days</label>
                                            <div class="input-group">
                                                <input type="number" name="master[duration_days]" min="1" max="30"
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
                            <button type="button" class="btn btn-outline-secondary fw-bold" onclick="applyFormat('bold')" title="Bold (Ctrl+B)"><b>B</b></button>
                            <button type="button" class="btn btn-outline-secondary fst-italic" onclick="applyFormat('italic')" title="Italic (Ctrl+I)"><i>I</i></button>
                            <button type="button" class="btn btn-outline-secondary text-decoration-underline" onclick="applyFormat('underline')" title="Underline (Ctrl+U)"><u>U</u></button>
                            <button type="button" class="btn btn-outline-secondary text-decoration-line-through" onclick="applyFormat('strikethrough')" title="Strikethrough"><s>S</s></button>
                        </div>

                        <!-- Bullet Styles Dropdown & Quick Buttons -->
                        <div class="btn-group btn-group-sm me-2 mb-1">
                            <button type="button" class="btn btn-outline-primary" onclick="insertBullet('• ')" title="Standard Disc Bullet (•)"><i class="bi bi-list-task me-1"></i> • Disc</button>
                            <button type="button" class="btn btn-outline-primary" onclick="insertBullet('▪ ')" title="Square Bullet (▪)">▪ Square</button>
                            <button type="button" class="btn btn-outline-primary" onclick="insertBullet('➢ ')" title="Arrow Bullet (➢)">➢ Arrow</button>
                            <button type="button" class="btn btn-outline-primary" onclick="insertBullet('1. ')" title="Numbered List (1. 2. 3.)"><i class="bi bi-list-ol me-1"></i> 1. 2. 3.</button>
                            <button type="button" class="btn btn-outline-primary" onclick="insertBullet('A. ')" title="Alphabetical List (A. B. C.)">A. B. C.</button>
                        </div>

                        <!-- Indent / Outdent Controls -->
                        <div class="btn-group btn-group-sm me-2 mb-1">
                            <button type="button" class="btn btn-outline-secondary" onclick="indentText(true)" title="Indent Text (Tab)"><i class="bi bi-text-indent-left me-1"></i> Indent</button>
                            <button type="button" class="btn btn-outline-secondary" onclick="indentText(false)" title="Outdent Text (Shift+Tab)"><i class="bi bi-text-indent-right me-1"></i> Outdent</button>
                        </div>

                        <!-- Section Header Quick Add -->
                        <div class="btn-group btn-group-sm mb-1">
                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-fonts me-1"></i> Section Header
                            </button>
                            <ul class="dropdown-menu shadow-sm">
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="insertHeader('Eligibility Rules')">[Eligibility Rules]</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="insertHeader('Topics & Themes')">[Topics & Themes]</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="insertHeader('Judging Criteria')">[Judging Criteria]</a></li>
                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="insertHeader('Disqualification Rules')">[Disqualification Rules]</a></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Main Textarea Editor -->
                    <textarea name="master[rules_text]" id="rules_text_editor" class="form-control rounded-top-0 border-top-0 font-monospace" rows="9" 
                              placeholder="Enter rules and guidelines...&#10;• Open to all UG & PG students with valid college ID.&#10;▪ Maximum 2 members allowed per team.&#10;➢ Laptops with required software must be brought by participants.&#10;1. Malpractice will lead to instant disqualification."><?= htmlspecialchars($old['rules_text'] ?? '') ?></textarea>
                    
                    <div class="form-text mt-2 text-muted d-flex justify-content-between align-items-center flex-wrap gap-1">
                        <span><i class="bi bi-info-circle me-1 text-primary"></i> Press <kbd>Tab</kbd> to indent and <kbd>Shift+Tab</kbd> to outdent. <kbd>Enter</kbd> automatically continues bullet lists!</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column: Settings & Module Flags -->
        <div class="col-lg-4">

            <!-- Card: Prelims Round & Submission Options -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-journal-check me-2 text-primary"></i>Prelims Round
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Switch 1: Requires Prelims -->
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="master[requires_prelims]" value="0">
                        <input class="form-check-input" type="checkbox" name="master[requires_prelims]" id="requires_prelims" value="1"
                               <?= !empty($old['requires_prelims']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="requires_prelims">Enable Prelims Screening Round</label>
                    </div>


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
                        <select name="master[judging_method]" class="form-select form-select-sm">
                            <?php foreach ($judgingMethods as $jm): ?>
                                <option value="<?= $jm ?>" <?= ($old['judging_method'] ?? 'Marks') === $jm ? 'selected' : '' ?>><?= $jm ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Maximum Score <span class="text-primary">(Default: 10)</span></label>
                        <input type="number" name="master[maximum_score]" class="form-control form-control-sm" step="0.01" value="<?= htmlspecialchars((string)($old['maximum_score'] ?? '10.00')) ?>">
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
                        <input type="hidden" name="master[supports_registration]" value="0">
                        <input class="form-check-input" type="checkbox" name="master[supports_registration]" id="flag_reg" value="1" checked>
                        <label class="form-check-label fw-semibold small" for="flag_reg">Enable Student Registration</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="master[supports_attendance]" value="0">
                        <input class="form-check-input" type="checkbox" name="master[supports_attendance]" id="flag_att" value="1" checked>
                        <label class="form-check-label fw-semibold small" for="flag_att">Enable Attendance Tracking</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="master[supports_evaluation]" value="0">
                        <input class="form-check-input" type="checkbox" name="master[supports_evaluation]" id="flag_eval" value="1" checked>
                        <label class="form-check-label fw-semibold small" for="flag_eval">Enable Judge Evaluation</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="master[supports_certificates]" value="0">
                        <input class="form-check-input" type="checkbox" name="master[supports_certificates]" id="flag_cert" value="1" checked>
                        <label class="form-check-label fw-semibold small" for="flag_cert">Enable Certificate Generation</label>
                    </div>
                </div>
            </div>


        </div>

    </div>
