<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : edit.php
 * Location    : templates/symposiums/events/
 * Description : Edit Symposium Event View (Matches Master Event Template)
 * -------------------------------------------------------------------------
 */

$event              = $event ?? [];
$formData           = $formData ?? [];
$errors             = $errors ?? [];
$formattedRulesText = $formattedRulesText ?? '';

$categories          = $formData['categories'] ?? ['Technical', 'Non-Technical'];
$participationTypes  = $formData['participation_types'] ?? ['Individual', 'Team', 'Both'];
$prelimTypes         = $formData['prelim_types'] ?? ['MCQ', 'File Submission', 'Coding Test', 'Abstract Screening', 'Custom'];
$judgingMethods      = $formData['judging_methods'] ?? ['Marks', 'Rubrics', 'Voting', 'Mixed'];
$statuses            = $formData['statuses'] ?? ['Scheduled', 'Registration Open', 'Registration Closed', 'Running', 'Completed', 'Cancelled'];

$returnTo = $_GET['return_to'] ?? '';
$actionUrl = base_url() . "/symposiums/events/edit?id=" . $event['symposium_event_id'];
if ($returnTo === 'allocation') {
    $actionUrl .= '&return_to=allocation';
}
$cancelUrl = $returnTo === 'allocation' 
    ? base_url() . "/symposiums/allocation?symposium_id=" . $event['symposium_id'] 
    : base_url() . "/symposiums/events?symposium_id=" . $event['symposium_id'];

?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="<?= base_url() ?>/symposiums">Symposiums</a></li>
                <li class="breadcrumb-item"><a href="<?= base_url() ?>/symposiums/events?symposium_id=<?= $event['symposium_id'] ?>">Events</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Event</li>
            </ol>
        </nav>
        <h2 class="fw-bold mb-1">
            <i class="bi bi-pencil-square text-primary me-2"></i>Edit Event: <?= htmlspecialchars($event['event_name'] ?? '') ?>
        </h2>
        <p class="text-muted mb-0">
            Updating <strong class="text-dark"><?= htmlspecialchars($event['event_name'] ?? '') ?></strong> (<?= htmlspecialchars($event['event_code'] ?? '') ?>).
        </p>
    </div>
    <div>
        <a href="<?= $cancelUrl ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> <?= $returnTo === 'allocation' ? 'Back to Allocation' : 'Back to Schedule' ?>
        </a>
    </div>
</div>

<form method="POST" action="<?= $actionUrl ?>" id="symposiumEventEditForm">
    <div class="row g-4">

        <!-- Left Column -->
        <div class="col-lg-8">

            <!-- Card 1: General Info -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="bi bi-info-circle me-2"></i>1. General Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info border-0 small mb-3">
                        <i class="bi bi-info-circle-fill me-2"></i><strong>Note:</strong> Changes made here will only apply to this specific Symposium instance. The Master Event in the library will not be affected.
                    </div>
                    <div class="row g-3">
                        
                        <!-- Event Name -->
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Event Name <span class="text-danger">*</span></label>
                            <input type="text" name="event_name" id="event_name" 
                                   class="form-control <?= isset($errors['event_name']) ? 'is-invalid' : '' ?>" 
                                   value="<?= htmlspecialchars($event['event_name'] ?? '') ?>" required>
                            <?php if (isset($errors['event_name'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['event_name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Event Code (Read-Only on Edit) -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Event Code</label>
                            <input type="text" class="form-control font-monospace bg-light" value="<?= htmlspecialchars($event['event_code'] ?? '') ?>" readonly>
                        </div>

                        <!-- Event Description (Optional) -->
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Event Description <span class="text-muted fw-normal">(Optional)</span></label>
                            <input type="text" name="description" id="description" 
                                   class="form-control <?= isset($errors['description']) ? 'is-invalid' : '' ?>" 
                                   placeholder="e.g. (Logo, Invitation, Banner, etc.,)" 
                                   value="<?= htmlspecialchars($event['description'] ?? '') ?>">
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
                                               value="<?= $cat ?>" <?= ($event['category'] ?? 'Technical') === $cat ? 'checked' : '' ?>>
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
                                <?php foreach ($statuses as $st): ?>
                                    <option value="<?= $st ?>" <?= ($event['status'] ?? 'Scheduled') === $st ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Card 2: Format & Team Config -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="bi bi-people me-2"></i>2. Participation Defaults
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Participation Type <span class="text-danger">*</span></label>
                            <select name="participation_type" id="participation_type" class="form-select">
                                <?php foreach ($participationTypes as $pt): ?>
                                    <option value="<?= $pt ?>" <?= ($event['participation_type'] ?? 'Individual') === $pt ? 'selected' : '' ?>><?= $pt ?></option>
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
                                               value="<?= htmlspecialchars((string)($event['min_team_size'] ?? 1)) ?>">
                                        <?php if(isset($errors['min_team_size'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['min_team_size']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Maximum Team Size</label>
                                        <input type="number" name="max_team_size" id="max_team_size" class="form-control <?= isset($errors['max_team_size']) ? 'is-invalid' : '' ?>" min="1" max="20" 
                                               value="<?= htmlspecialchars((string)($event['max_team_size'] ?? 1)) ?>">
                                        <?php if(isset($errors['max_team_size'])): ?>
                                            <div class="invalid-feedback"><?= htmlspecialchars($errors['max_team_size']) ?></div>
                                        <?php endif; ?>
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
                    <textarea name="rules_text" id="rules_text_editor" class="form-control rounded-top-0 border-top-0 font-monospace" rows="12" 
                              placeholder="Enter rules and guidelines...&#10;• Open to all UG & PG students with valid college ID.&#10;▪ Maximum 2 members allowed per team.&#10;➢ Laptops with required software must be brought by participants.&#10;1. Malpractice will lead to instant disqualification."><?= htmlspecialchars($formattedRulesText ?? '') ?></textarea>
                    
                    <div class="form-text mt-2 text-muted d-flex justify-content-between align-items-center flex-wrap gap-1">
                        <span><i class="bi bi-info-circle me-1 text-primary"></i> Press <kbd>Tab</kbd> to indent and <kbd>Shift+Tab</kbd> to outdent. <kbd>Enter</kbd> automatically continues bullet lists!</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right Column -->
        <div class="col-lg-4">

            <!-- Card: Prelims Round & Submission Options -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-journal-check me-2 text-primary"></i>Prelims Round
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label fw-bold small">Prelims Decision</label>
                        <select name="prelim_decision" class="form-select form-select-sm">
                            <option value="Pending" <?= ($event['prelim_decision'] ?? 'Pending') === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Required" <?= ($event['prelim_decision'] ?? 'Pending') === 'Required' ? 'selected' : '' ?>>Required</option>
                            <option value="Not Required" <?= ($event['prelim_decision'] ?? 'Pending') === 'Not Required' ? 'selected' : '' ?>>Not Required</option>
                        </select>
                        <div class="form-text mt-1 text-muted"><i class="bi bi-info-circle me-1"></i>Set to "Required" to allow the FIC to schedule preliminary stages.</div>
                    </div>
                </div>
            </div>

            <!-- Card: Evaluation Settings -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-award me-2"></i>Evaluation Settings</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Judging Method</label>
                        <select name="judging_method" class="form-select form-select-sm">
                            <?php foreach ($judgingMethods as $jm): ?>
                                <option value="<?= $jm ?>" <?= ($event['judging_method'] ?? 'Marks') === $jm ? 'selected' : '' ?>><?= $jm ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Maximum Score <span class="text-primary">(Default: 10)</span></label>
                        <input type="number" name="maximum_score" class="form-control form-control-sm" step="0.01" value="<?= htmlspecialchars((string)($event['maximum_score'] ?? 10.00)) ?>">
                    </div>

                </div>
            </div>

            <!-- Card: Module Toggle Flags -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-toggle-on me-2"></i>Module Features</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="supports_registration" value="0">
                        <input class="form-check-input" type="checkbox" name="supports_registration" id="flag_reg" value="1" <?= !empty($event['supports_registration']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold small" for="flag_reg">Enable Student Registration</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="supports_attendance" value="0">
                        <input class="form-check-input" type="checkbox" name="supports_attendance" id="flag_att" value="1" <?= !empty($event['supports_attendance']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold small" for="flag_att">Enable Attendance Tracking</label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="supports_evaluation" value="0">
                        <input class="form-check-input" type="checkbox" name="supports_evaluation" id="flag_eval" value="1" <?= !empty($event['supports_evaluation']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold small" for="flag_eval">Enable Judge Evaluation</label>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input type="hidden" name="supports_certificates" value="0">
                        <input class="form-check-input" type="checkbox" name="supports_certificates" id="flag_cert" value="1" <?= !empty($event['supports_certificates']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold small" for="flag_cert">Enable Certificate Generation</label>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2 py-2 fw-bold">
                        <i class="bi bi-save me-1"></i> Update Event Details
                    </button>
                    <a href="<?= $cancelUrl ?>" class="btn btn-outline-secondary w-100 btn-sm">Cancel</a>
                </div>
            </div>

        </div>

    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Participation Type Toggle
    const participationSelect = document.getElementById('participation_type');
    const teamSizeSection = document.getElementById('team_size_section');

    function toggleTeamSection() {
        if (!participationSelect) return;
        teamSizeSection.style.display = participationSelect.value !== 'Individual' ? 'block' : 'none';
    }
    if (participationSelect) participationSelect.addEventListener('change', toggleTeamSection);
    toggleTeamSection();

    // 3. Word-Style Rules Editor Tab Key Indentation & Auto-Bullet Continuation
    const rulesEditor = document.getElementById('rules_text_editor');
    if (rulesEditor) {
        rulesEditor.addEventListener('keydown', function(e) {
            const start = this.selectionStart;
            const end = this.selectionEnd;
            const value = this.value;

            // Handle Rich Text Formatting Shortcuts (Ctrl+B, Ctrl+I, Ctrl+U)
            if (e.ctrlKey) {
                const char = e.key.toLowerCase();
                let tag = null;
                if (char === 'b') tag = 'b';
                else if (char === 'i') tag = 'i';
                else if (char === 'u') tag = 'u';

                if (tag) {
                    e.preventDefault();
                    const selectedText = value.substring(start, end);
                    const wrappedText = `<${tag}>${selectedText}</${tag}>`;
                    this.value = value.substring(0, start) + wrappedText + value.substring(end);
                    // Move cursor inside the tags if no text was selected, otherwise after
                    const newCursorPos = start === end ? start + tag.length + 2 : start + wrappedText.length;
                    this.selectionStart = this.selectionEnd = newCursorPos;
                    return;
                }
            }

            // Handle Tab & Shift+Tab
            if (e.key === 'Tab') {
                e.preventDefault();
                indentText(!e.shiftKey);
                return;
            }

            // Handle Enter Key for Auto-Bullet Continuation
            if (e.key === 'Enter') {
                const lineStart = value.lastIndexOf('\n', start - 1) + 1;
                const currentLine = value.substring(lineStart, start);

                const bulletMatch = currentLine.match(/^(\s*)([•▪➢\-*]|\d+\.|\w\.)\s+/);
                if (bulletMatch) {
                    e.preventDefault();
                    let prefix = bulletMatch[0];
                    const numMatch = bulletMatch[2].match(/^(\d+)\.$/);
                    if (numMatch) {
                        const nextNum = parseInt(numMatch[1], 10) + 1;
                        prefix = bulletMatch[1] + nextNum + '. ';
                    }
                    this.value = value.substring(0, start) + '\n' + prefix + value.substring(end);
                    this.selectionStart = this.selectionEnd = start + 1 + prefix.length;
                }
            }
        });
    }
});

function indentText(isIndent = true) {
    const editor = document.getElementById('rules_text_editor');
    if (!editor) return;
    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    const value = editor.value;
    const indentStr = '    '; // 4 spaces for clean tab alignment

    if (start === end) {
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        if (isIndent) {
            editor.value = value.substring(0, start) + indentStr + value.substring(end);
            editor.selectionStart = editor.selectionEnd = start + indentStr.length;
        } else {
            const line = value.substring(lineStart, start);
            if (line.startsWith(indentStr)) {
                editor.value = value.substring(0, lineStart) + value.substring(lineStart + indentStr.length);
                editor.selectionStart = editor.selectionEnd = Math.max(lineStart, start - indentStr.length);
            } else if (line.startsWith('\t')) {
                editor.value = value.substring(0, lineStart) + value.substring(lineStart + 1);
                editor.selectionStart = editor.selectionEnd = Math.max(lineStart, start - 1);
            }
        }
    } else {
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        const lineEnd = value.indexOf('\n', end);
        const realEnd = lineEnd === -1 ? value.length : lineEnd;
        const selectedLines = value.substring(lineStart, realEnd).split('\n');

        let newText = '';
        if (isIndent) {
            newText = selectedLines.map(line => indentStr + line).join('\n');
        } else {
            newText = selectedLines.map(line => {
                if (line.startsWith(indentStr)) return line.substring(indentStr.length);
                if (line.startsWith('\t')) return line.substring(1);
                return line;
            }).join('\n');
        }

        editor.value = value.substring(0, lineStart) + newText + value.substring(realEnd);
        editor.selectionStart = lineStart;
        editor.selectionEnd = lineStart + newText.length;
    }
    editor.focus();
}

function insertBullet(bullet) {
    const editor = document.getElementById('rules_text_editor');
    if (!editor) return;
    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    const value = editor.value;

    const selectedText = value.substring(start, end);
    if (selectedText.length > 0) {
        const lines = selectedText.split('\n');
        const bulleted = lines.map(line => bullet + line.replace(/^[•▪➢\-*]\s+/, '')).join('\n');
        editor.value = value.substring(0, start) + bulleted + value.substring(end);
        editor.selectionStart = start;
        editor.selectionEnd = start + bulleted.length;
    } else {
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        editor.value = value.substring(0, lineStart) + bullet + value.substring(lineStart);
        editor.selectionStart = editor.selectionEnd = start + bullet.length;
    }
    editor.focus();
}

function applyFormat(style) {
    const editor = document.getElementById('rules_text_editor');
    if (!editor) return;
    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    const value = editor.value;
    const selectedText = value.substring(start, end) || 'text';

    let openTag = '', closeTag = '';
    switch(style) {
        case 'bold': openTag = '<b>'; closeTag = '</b>'; break;
        case 'italic': openTag = '<i>'; closeTag = '</i>'; break;
        case 'underline': openTag = '<u>'; closeTag = '</u>'; break;
        case 'strikethrough': openTag = '<s>'; closeTag = '</s>'; break;
    }

    const replacement = openTag + selectedText + closeTag;
    editor.value = value.substring(0, start) + replacement + value.substring(end);
    editor.selectionStart = start + openTag.length;
    editor.selectionEnd = start + openTag.length + selectedText.length;
    editor.focus();
}

function insertHeader(headerTitle) {
    const editor = document.getElementById('rules_text_editor');
    if (!editor) return;
    const start = editor.selectionStart;
    const value = editor.value;
    const headerText = `\n[${headerTitle}]\n`;
    
    editor.value = value.substring(0, start) + headerText + value.substring(start);
    editor.selectionStart = editor.selectionEnd = start + headerText.length;
    editor.focus();
}
        }
    });
});
</script>
