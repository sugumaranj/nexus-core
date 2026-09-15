/**
 * ==========================================================================
 * NexusCore — Competition Wizard JavaScript
 * ==========================================================================
 * File        : competition-wizard.js
 * Location    : public/assets/js/
 * Description : Client-side logic for the 7-step Competition Wizard.
 *
 * Features
 * --------------------------------------------------------------------------
 * • Multi-step wizard navigation with step validation
 * • Competition code AJAX availability check
 * • Scheduling conflict AJAX check (venue + coordinator)
 * • Rule section add / remove / edit
 * • Coordinator searchable user picker
 * • Live review summary (Step 7)
 * • Toast notifications
 *
 * ==========================================================================
 */

'use strict';

const CompetitionWizard = (() => {

    /* -----------------------------------------------------------------------
     * STATE
     * --------------------------------------------------------------------- */
    let currentStep   = 1;
    const totalSteps  = 7;
    let ruleSections  = [];   // [{ section, content, display_order }]
    let coordinators  = [];   // [{ user_id, full_name, email, coordinator_type, department_name }]
    let codeCheckTimer = null;

    /* -----------------------------------------------------------------------
     * DOM REFS
     * --------------------------------------------------------------------- */
    const wizardEl      = () => document.getElementById('competition-wizard');
    const stepPanels    = () => document.querySelectorAll('.comp-wizard-panel');
    const stepCircles   = () => document.querySelectorAll('.comp-wizard-step');
    const nextBtn       = () => document.getElementById('wizard-next');
    const prevBtn       = () => document.getElementById('wizard-prev');
    const draftBtn      = () => document.getElementById('wizard-draft');
    const publishBtn    = () => document.getElementById('wizard-publish');
    const stepIndicator = () => document.getElementById('wizard-step-indicator');

    /* -----------------------------------------------------------------------
     * INIT
     * --------------------------------------------------------------------- */
    function init() {
        if (!wizardEl()) return;

        // Pre-populate from existing data (edit mode)
        loadExistingRules();
        loadExistingCoordinators();

        // Wire wizard nav
        nextBtn()?.addEventListener('click', goNext);
        prevBtn()?.addEventListener('click', goPrev);
        draftBtn()?.addEventListener('click', () => submitForm('Draft'));
        publishBtn()?.addEventListener('click', () => submitForm('Scheduled'));

        // Load sessionStorage draft if any
        loadSessionDraft();

        document.getElementById('wizard-form')?.addEventListener('input', debounce(saveSessionDraft, 500));
        document.getElementById('wizard-form')?.addEventListener('change', saveSessionDraft);

        // Code availability check
        const codeInput = document.getElementById('competition_code');
        if (codeInput) {
            codeInput.addEventListener('input', () => {
                clearTimeout(codeCheckTimer);
                codeCheckTimer = setTimeout(checkCodeAvailability, 450);
            });
        }

        // Participation type toggle
        const partType = document.getElementById('participation_type');
        if (partType) {
            partType.addEventListener('change', toggleTeamSizeFields);
            toggleTeamSizeFields.call(partType);
        }

        // Competition mode toggle
        const modeSelect = document.getElementById('competition_mode');
        if (modeSelect) {
            modeSelect.addEventListener('change', toggleModeFields);
            toggleModeFields.call(modeSelect);
        }

        // Digital prelims toggle
        const hasDp = document.getElementById('has_digital_prelims');
        if (hasDp) {
            hasDp.addEventListener('change', togglePrelimType);
        }

        // Rule section add button
        document.getElementById('add-rule-section')?.addEventListener('click', addRuleSection);

        // Coordinator search
        const coordSearch = document.getElementById('coordinator-search-input');
        if (coordSearch) {
            coordSearch.addEventListener('input', debounce(searchCoordinators, 300));
            coordSearch.addEventListener('focus', () => {
                if (coordSearch.value.length >= 2) searchCoordinators();
            });
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.comp-user-search')) closeCoordDropdown();
            });
        }

        stepCircles().forEach((circle, idx) => {
            circle.style.cursor = 'pointer';
            circle.addEventListener('click', () => jumpToStep(idx + 1));
        });

        goToStep(1);
        updateNavButtons();
    }

    /* -----------------------------------------------------------------------
     * WIZARD NAVIGATION
     * --------------------------------------------------------------------- */
    function goNext() {
        if (currentStep < totalSteps) {
            jumpToStep(currentStep + 1);
        }
    }

    function goPrev() {
        if (currentStep > 1) {
            jumpToStep(currentStep - 1);
        }
    }

    function jumpToStep(targetStep) {
        targetStep = parseInt(targetStep, 10);
        if (targetStep === currentStep) return;

        if (targetStep > currentStep) {
            // Going forward: validate current and all intermediate steps
            let isValid = true;
            let failedStep = currentStep;
            
            for (let i = currentStep; i < targetStep; i++) {
                let temp = currentStep;
                currentStep = i; // Set for validation
                if (!validateCurrentStep()) {
                    isValid = false;
                    failedStep = i;
                }
                currentStep = temp; // Restore
                
                if (!isValid) break;
            }

            if (!isValid) {
                goToStep(failedStep);
                return;
            }
        }

        // If crossing step 3 going forward, run conflict check before proceeding
        if (currentStep <= 3 && targetStep > 3) {
            runConflictCheck(() => {
                if (targetStep === totalSteps) buildReviewSummary();
                goToStep(targetStep);
            });
            return;
        }

        if (targetStep === totalSteps) {
            buildReviewSummary();
        }
        
        goToStep(targetStep);
    }

    function goToStep(step) {
        currentStep = parseInt(step, 10);

        stepPanels().forEach((panel, idx) => {
            if (idx + 1 === currentStep) {
                panel.classList.add('active');
                panel.style.display = 'block';
            } else {
                panel.classList.remove('active');
                panel.style.display = 'none';
            }
        });

        stepCircles().forEach((circle, idx) => {
            circle.classList.remove('active', 'completed');
            if (idx + 1 === step) {
                circle.classList.add('active');
            } else if (idx + 1 < step) {
                circle.classList.add('completed');
                const circ = circle.querySelector('.comp-wizard-step-circle');
                if (circ) circ.innerHTML = '<i class="bi bi-check-lg"></i>';
            } else {
                const circ = circle.querySelector('.comp-wizard-step-circle');
                if (circ) circ.textContent = idx + 1;
            }
        });

        updateNavButtons();

        // Scroll wizard into view
        wizardEl()?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function updateNavButtons() {
        const prev    = prevBtn();
        const next    = nextBtn();
        const draft   = draftBtn();
        const publish = publishBtn();

        if (prev)    prev.style.display    = currentStep > 1         ? '' : 'none';
        if (next)    next.style.display    = currentStep < totalSteps ? '' : 'none';
        if (draft)   draft.style.display   = currentStep === totalSteps ? '' : 'none';
        if (publish) publish.style.display = currentStep === totalSteps ? '' : 'none';
    }

    function submitForm(targetStatus) {
        const statusSelect = document.getElementById('status');
        if (statusSelect) {
            statusSelect.value = targetStatus;
        }
        
        sessionStorage.removeItem('compWizardDraft_' + window.location.pathname);
        document.getElementById('wizard-form')?.submit();
    }

    function saveSessionDraft() {
        const form = document.getElementById('wizard-form');
        if (!form) return;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        sessionStorage.setItem('compWizardDraft_' + window.location.pathname, JSON.stringify(data));
    }

    function loadSessionDraft() {
        const draft = sessionStorage.getItem('compWizardDraft_' + window.location.pathname);
        if (!draft) return;
        try {
            const data = JSON.parse(draft);
            const form = document.getElementById('wizard-form');
            if (!form) return;
            for (const [key, value] of Object.entries(data)) {
                const el = form.elements[key];
                if (el) {
                    if (el.type === 'checkbox' || el.type === 'radio') {
                        if (el.length) Array.from(el).forEach(e => { if(e.value === value) e.checked = true; });
                        else el.checked = (el.value === value);
                    } else {
                        el.value = value;
                    }
                }
            }
            if (data.rule_sections_json) {
                try { ruleSections = JSON.parse(data.rule_sections_json); renderRuleSections(); } catch {}
            }
            if (data.coordinators_json) {
                try { coordinators = JSON.parse(data.coordinators_json); renderCoordinators(); } catch {}
            }
            document.getElementById('participation_type')?.dispatchEvent(new Event('change'));
            document.getElementById('competition_mode')?.dispatchEvent(new Event('change'));
            document.getElementById('has_digital_prelims')?.dispatchEvent(new Event('change'));
        } catch (e) {}
    }

    /* -----------------------------------------------------------------------
     * STEP VALIDATION
     * --------------------------------------------------------------------- */
    function validateCurrentStep() {
        clearStepErrors();
        let valid = true;

        switch (currentStep) {
            case 1: valid = validateStep1(); break;
            case 2: valid = validateStep2(); break;
            case 3: valid = validateStep3(); break;
            case 4: valid = validateStep4(); break;
            case 5: valid = validateStep5(); break;
            case 6: valid = validateStep6(); break;
            default: valid = true;
        }

        if (!valid) {
            showToast('Please correct the highlighted errors before continuing.', 'warning');
            const firstError = document.querySelector('.comp-wizard-panel.active .is-invalid');
            if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        return valid;
    }

    function validateStep1() {
        let valid = true;
        const codeInput = document.getElementById('competition_code');
        if (codeInput && !codeInput.readOnly) {
            const code = codeInput.value.trim();
            if (!code || !/^[A-Z0-9\-]{2,30}$/i.test(code)) {
                showFieldError('competition_code', 'Code must be 2–30 characters.');
                valid = false;
            }
        }
        const title = document.getElementById('title')?.value.trim();
        if (!title) { showFieldError('title', 'Competition title is required.'); valid = false; }
        const sym = document.getElementById('symposium_id')?.value;
        if (!sym) { showFieldError('symposium_id', 'Please select a symposium.'); valid = false; }
        const type = document.getElementById('competition_type_id')?.value;
        if (!type) { showFieldError('competition_type_id', 'Please select a competition type.'); valid = false; }
        const cat = document.getElementById('category')?.value;
        if (!cat) { showFieldError('category', 'Category is required.'); valid = false; }
        const st = document.getElementById('status')?.value;
        if (!st) { showFieldError('status', 'Status is required.'); valid = false; }
        return valid;
    }

    function validateStep2() {
        let valid = true;
        const maxP = document.getElementById('max_participants')?.value;
        if (maxP && parseInt(maxP) < 1) { showFieldError('max_participants', 'Must be >= 1.'); valid = false; }
        const rLim = document.getElementById('registration_limit')?.value;
        if (rLim && parseInt(rLim) < 1) { showFieldError('registration_limit', 'Must be >= 1.'); valid = false; }

        const pType = document.getElementById('participation_type')?.value;
        if (pType === 'Team' || pType === 'Both') {
            const minT = parseInt(document.getElementById('min_team_size')?.value || 0);
            const maxT = parseInt(document.getElementById('max_team_size')?.value || 0);
            if (minT < 1) { showFieldError('min_team_size', 'Min team size >= 1.'); valid = false; }
            if (maxT < 1) { showFieldError('max_team_size', 'Max team size >= 1.'); valid = false; }
            if (minT > 0 && maxT > 0 && minT > maxT) { showFieldError('max_team_size', 'Max must be >= Min.'); valid = false; }
        }
        return valid;
    }

    function validateStep3() {
        let valid = true;
        const date = document.getElementById('event_date')?.value;
        if (!date) { showFieldError('event_date', 'Event date is required.'); valid = false; }
        const start = document.getElementById('start_time')?.value;
        const end   = document.getElementById('end_time')?.value;
        if (!start) { showFieldError('start_time', 'Start time is required.'); valid = false; }
        if (!end) { showFieldError('end_time', 'End time is required.'); valid = false; }
        if (start && end && start >= end) { showFieldError('end_time', 'End time must be after start time.'); valid = false; }
        const venue = document.getElementById('venue_id')?.value;
        if (!venue) { showFieldError('venue_id', 'Please select a venue.'); valid = false; }
        return valid;
    }

    function validateStep4() {
        let valid = true;
        const mode = document.getElementById('competition_mode')?.value;
        if (mode === 'Online' || mode === 'Hybrid') {
            const platform = document.getElementById('online_platform')?.value;
            if (!platform) { showFieldError('online_platform', 'Please select a platform.'); valid = false; }
        }
        return valid;
    }

    function validateStep5() {
        if (ruleSections.length === 0) {
            showToast('Please add at least one rule section.', 'warning');
            return false;
        }
        const hasEmpty = ruleSections.some(r => !r.section.trim() || !r.content.trim());
        if (hasEmpty) {
            showToast('Please fill out all rule sections or remove empty ones.', 'warning');
            return false;
        }
        return true;
    }

    function validateStep6() {
        if (coordinators.length === 0) {
            showToast('Please assign at least one coordinator.', 'warning');
            return false;
        }
        return true;
    }

    function showFieldError(fieldId, message) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        field.classList.add('is-invalid');
        const feedback = field.parentElement?.querySelector('.invalid-feedback')
            || field.nextElementSibling;
        if (feedback && feedback.classList.contains('invalid-feedback')) {
            feedback.textContent = message;
        }
    }

    function clearStepErrors() {
        document.querySelectorAll('.comp-wizard-panel.active .is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
    }

    /* -----------------------------------------------------------------------
     * CODE AVAILABILITY CHECK
     * --------------------------------------------------------------------- */
    async function checkCodeAvailability() {
        const input     = document.getElementById('competition_code');
        const feedback  = document.getElementById('code-availability-feedback');
        if (!input || !feedback) return;

        const code      = input.value.trim().toUpperCase();
        const excludeId = input.dataset.excludeId || '';
        if (code.length < 2) {
            feedback.textContent = '';
            return;
        }

        try {
            const params = new URLSearchParams({ code });
            if (excludeId) params.set('exclude_id', excludeId);

            const res  = await fetch(`${BASE_URL}/competitions/ajax/check-code?${params}`);
            const data = await res.json();

            feedback.textContent  = data.message;
            feedback.className    = 'form-text';
            input.classList.remove('is-invalid', 'is-valid');

            if (data.available) {
                feedback.classList.add('text-success');
                input.classList.add('is-valid');
            } else {
                feedback.classList.add('text-danger');
                input.classList.add('is-invalid');
            }
        } catch {
            feedback.textContent = '';
        }
    }

    /* -----------------------------------------------------------------------
     * CONFLICT CHECK
     * --------------------------------------------------------------------- */
    function runConflictCheck(onSuccess) {
        const conflictPanel = document.getElementById('conflict-panel');
        const conflictList  = document.getElementById('conflict-list');
        if (!conflictPanel || !conflictList) { onSuccess(); return; }

        const payload = {
            venue_id:   document.getElementById('venue_id')?.value,
            event_date: document.getElementById('event_date')?.value,
            start_time: document.getElementById('start_time')?.value,
            end_time:   document.getElementById('end_time')?.value,
            coordinators: coordinators.map(c => ({ user_id: c.user_id })),
            exclude_id: document.getElementById('wizard_exclude_id')?.value || 0,
        };

        fetch(`${BASE_URL}/competitions/ajax/check-conflict`, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        })
        .then(r => r.json())
        .then(data => {
            if (data.has_conflict) {
                conflictPanel.style.display = '';
                conflictList.innerHTML = data.conflicts
                    .map(c => `<li>${escHtml(c)}</li>`).join('');
                // Show conflict but do not block — user sees the warning
                showToast('Scheduling conflicts detected. Please review before saving.', 'warning');
            } else {
                conflictPanel.style.display = 'none';
                conflictList.innerHTML = '';
            }
            onSuccess();
        })
        .catch(() => onSuccess()); // Don't block on network error
    }

    /* -----------------------------------------------------------------------
     * PARTICIPATION TYPE TOGGLE
     * --------------------------------------------------------------------- */
    function toggleTeamSizeFields() {
        const type        = this.value;
        const teamFields  = document.getElementById('team-size-fields');
        if (!teamFields) return;
        const showTeam = type === 'Team' || type === 'Both';
        teamFields.style.display = showTeam ? '' : 'none';
    }

    /* -----------------------------------------------------------------------
     * COMPETITION MODE TOGGLE
     * --------------------------------------------------------------------- */
    function toggleModeFields() {
        const mode           = this.value;
        const onlineSection  = document.getElementById('online-config-section');
        if (!onlineSection) return;
        onlineSection.style.display = (mode === 'Online' || mode === 'Hybrid') ? '' : 'none';
    }

    /* -----------------------------------------------------------------------
     * PRELIM TYPE TOGGLE
     * --------------------------------------------------------------------- */
    function togglePrelimType() {
        const checked       = this.checked || this.value === '1';
        const prelimSection = document.getElementById('prelim-type-section');
        if (!prelimSection) return;
        prelimSection.style.display = checked ? '' : 'none';
    }

    /* -----------------------------------------------------------------------
     * RULE SECTIONS
     * --------------------------------------------------------------------- */
    function loadExistingRules() {
        const existing = document.getElementById('existing-rules-json');
        if (!existing) return;
        try {
            ruleSections = JSON.parse(existing.value || '[]');
        } catch { ruleSections = []; }
        renderRuleSections();
    }

    function addRuleSection() {
        ruleSections.push({ section: '', content: '', display_order: ruleSections.length + 1 });
        renderRuleSections();
    }

    function removeRuleSection(index) {
        ruleSections.splice(index, 1);
        renderRuleSections();
    }

    function renderRuleSections() {
        const container = document.getElementById('rule-sections-container');
        if (!container) return;

        const sectionOptions = RULE_SECTIONS.map(s =>
            `<option value="${escHtml(s)}">${escHtml(s)}</option>`
        ).join('');

        container.innerHTML = ruleSections.map((rule, idx) => `
            <div class="comp-rule-item mb-3" data-index="${idx}">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-grip-vertical comp-rule-drag-handle mt-2"></i>
                    <div class="flex-grow-1">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <select class="form-select form-select-sm"
                                        onchange="CompetitionWizard.updateRuleSection(${idx}, 'section', this.value)">
                                    <option value="">Select Section…</option>
                                    ${sectionOptions.replace(
                                        `value="${escHtml(rule.section)}"`,
                                        `value="${escHtml(rule.section)}" selected`
                                    )}
                                </select>
                            </div>
                            <div class="col-md-8">
                                <textarea class="form-control form-control-sm"
                                          rows="3"
                                          placeholder="Rule content…"
                                          onchange="CompetitionWizard.updateRuleSection(${idx}, 'content', this.value)"
                                >${escHtml(rule.content)}</textarea>
                            </div>
                        </div>
                    </div>
                    <button type="button"
                            class="comp-rule-remove mt-2"
                            onclick="CompetitionWizard.removeRuleSection(${idx})"
                            title="Remove">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `).join('');

        syncRulesHiddenField();
    }

    function updateRuleSection(index, key, value) {
        if (ruleSections[index]) {
            ruleSections[index][key] = value;
            syncRulesHiddenField();
        }
    }

    function syncRulesHiddenField() {
        const field = document.getElementById('rule_sections_json');
        if (field) field.value = JSON.stringify(ruleSections);
    }

    /* -----------------------------------------------------------------------
     * COORDINATORS
     * --------------------------------------------------------------------- */
    function loadExistingCoordinators() {
        const existing = document.getElementById('existing-coordinators-json');
        if (!existing) return;
        try {
            coordinators = JSON.parse(existing.value || '[]');
        } catch { coordinators = []; }
        renderCoordinators();
    }

    async function searchCoordinators() {
        const input    = document.getElementById('coordinator-search-input');
        const dropdown = document.getElementById('coordinator-dropdown');
        if (!input || !dropdown) return;

        const q = input.value.trim();
        if (q.length < 2) { dropdown.classList.remove('show'); return; }

        try {
            const res  = await fetch(`${BASE_URL}/competitions/ajax/search-users?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            const users = data.users || [];

            if (users.length === 0) {
                dropdown.classList.remove('show');
                return;
            }

            dropdown.innerHTML = users.map(u => `
                <div class="comp-user-option"
                     onclick="CompetitionWizard.selectCoordinator(${JSON.stringify(u).replace(/"/g, '&quot;')})">
                    <div class="user-name">${escHtml(u.full_name)}</div>
                    <div class="user-email">${escHtml(u.email)} · ${escHtml(u.role)}</div>
                </div>
            `).join('');

            dropdown.classList.add('show');
        } catch { dropdown.classList.remove('show'); }
    }

    function selectCoordinator(user) {
        // Prevent duplicate
        if (coordinators.find(c => c.user_id === user.user_id)) {
            showToast(`${user.full_name} is already added.`, 'info');
            return;
        }

        user.coordinator_type = 'Staff Coordinator'; // default
        coordinators.push(user);
        renderCoordinators();
        closeCoordDropdown();

        const input = document.getElementById('coordinator-search-input');
        if (input) input.value = '';
    }

    function removeCoordinator(index) {
        coordinators.splice(index, 1);
        renderCoordinators();
    }

    function updateCoordinatorType(index, type) {
        if (coordinators[index]) {
            coordinators[index].coordinator_type = type;
            syncCoordinatorsHiddenField();
        }
    }

    function renderCoordinators() {
        const container = document.getElementById('coordinator-list');
        if (!container) return;

        const typeOptions = [
            'Staff Coordinator', 'Event Coordinator', 'Student Coordinator', 'Judge'
        ];

        if (coordinators.length === 0) {
            container.innerHTML = `<p class="text-muted small">No coordinators assigned yet.</p>`;
            syncCoordinatorsHiddenField();
            return;
        }

        container.innerHTML = coordinators.map((c, idx) => `
            <div class="comp-coordinator-item">
                <div class="comp-coordinator-avatar">
                    ${escHtml(c.full_name.charAt(0).toUpperCase())}
                </div>
                <div class="flex-grow-1">
                    <div class="comp-coordinator-name">${escHtml(c.full_name)}</div>
                    <div class="comp-coordinator-role">${escHtml(c.email || '')}</div>
                </div>
                <select class="form-select form-select-sm w-auto"
                        onchange="CompetitionWizard.updateCoordinatorType(${idx}, this.value)">
                    ${typeOptions.map(t =>
                        `<option value="${t}"${c.coordinator_type === t ? ' selected' : ''}>${t}</option>`
                    ).join('')}
                </select>
                <button type="button"
                        class="comp-coordinator-remove"
                        onclick="CompetitionWizard.removeCoordinator(${idx})"
                        title="Remove">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        `).join('');

        syncCoordinatorsHiddenField();
    }

    function syncCoordinatorsHiddenField() {
        const field = document.getElementById('coordinators_json');
        if (field) field.value = JSON.stringify(coordinators);
    }

    function closeCoordDropdown() {
        document.getElementById('coordinator-dropdown')?.classList.remove('show');
    }

    /* -----------------------------------------------------------------------
     * REVIEW SUMMARY (Step 7)
     * --------------------------------------------------------------------- */
    function buildReviewSummary() {
        const reviewEl = document.getElementById('review-summary');
        if (!reviewEl) return;

        const g = id => document.getElementById(id)?.value || '—';
        const t = id => document.getElementById(id)?.options[document.getElementById(id)?.selectedIndex]?.text || g(id);

        reviewEl.innerHTML = `
            <div class="row g-4">
                <div class="col-md-6">
                    <h6 class="text-primary fw-bold mb-3"><i class="bi bi-info-circle me-2"></i>Basic Info</h6>
                    <table class="table table-sm table-bordered">
                        <tr><th>Code</th><td>${escHtml(g('competition_code').toUpperCase())}</td></tr>
                        <tr><th>Title</th><td>${escHtml(g('title'))}</td></tr>
                        <tr><th>Symposium</th><td>${escHtml(t('symposium_id'))}</td></tr>
                        <tr><th>Type</th><td>${escHtml(t('competition_type_id'))}</td></tr>
                        <tr><th>Category</th><td>${escHtml(g('category'))}</td></tr>
                        <tr><th>Status</th><td>${escHtml(g('status'))}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="text-primary fw-bold mb-3"><i class="bi bi-calendar3 me-2"></i>Schedule</h6>
                    <table class="table table-sm table-bordered">
                        <tr><th>Date</th><td>${escHtml(g('event_date'))}</td></tr>
                        <tr><th>Session</th><td>${escHtml(g('session'))}</td></tr>
                        <tr><th>Time</th><td>${escHtml(g('start_time'))} – ${escHtml(g('end_time'))}</td></tr>
                        <tr><th>Venue</th><td>${escHtml(t('venue_id'))}</td></tr>
                        <tr><th>Deadline</th><td>${escHtml(g('registration_deadline'))}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="text-primary fw-bold mb-3"><i class="bi bi-people me-2"></i>Participation</h6>
                    <table class="table table-sm table-bordered">
                        <tr><th>Type</th><td>${escHtml(g('participation_type'))}</td></tr>
                        <tr><th>Mode</th><td>${escHtml(g('competition_mode'))}</td></tr>
                        <tr><th>Judging</th><td>${escHtml(g('judging_method'))}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="text-primary fw-bold mb-3"><i class="bi bi-person-badge me-2"></i>Coordinators</h6>
                    ${coordinators.length > 0
                        ? coordinators.map(c => `<span class="badge bg-primary me-1 mb-1">${escHtml(c.full_name)}</span>`).join('')
                        : '<span class="text-muted small">None assigned</span>'
                    }
                    <h6 class="text-primary fw-bold mb-3 mt-3"><i class="bi bi-list-check me-2"></i>Rules</h6>
                    ${ruleSections.length > 0
                        ? `<span class="text-success small"><i class="bi bi-check-circle"></i> ${ruleSections.length} section(s) defined</span>`
                        : '<span class="text-muted small">No rules added</span>'
                    }
                </div>
            </div>
        `;
    }

    /* -----------------------------------------------------------------------
     * TOAST NOTIFICATION
     * --------------------------------------------------------------------- */
    function showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container');
        if (!toastContainer) return;

        const colorMap = {
            success: 'bg-success', warning: 'bg-warning text-dark',
            danger: 'bg-danger', info: 'bg-info text-dark'
        };

        const id   = 'toast-' + Date.now();
        const html = `
            <div id="${id}" class="toast align-items-center ${colorMap[type] || 'bg-info text-dark'} border-0 show"
                 role="alert" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body fw-semibold">${escHtml(message)}</div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`;

        toastContainer.insertAdjacentHTML('beforeend', html);
        setTimeout(() => document.getElementById(id)?.remove(), 5000);
    }

    /* -----------------------------------------------------------------------
     * UTILITIES
     * --------------------------------------------------------------------- */
    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function debounce(fn, delay) {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    /* -----------------------------------------------------------------------
     * PUBLIC API
     * --------------------------------------------------------------------- */
    return {
        init,
        goToStep,
        addRuleSection,
        removeRuleSection,
        updateRuleSection,
        removeCoordinator,
        selectCoordinator,
        updateCoordinatorType,
    };

})();

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', CompetitionWizard.init);
