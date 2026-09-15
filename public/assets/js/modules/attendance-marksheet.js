/**
 * ============================================================
 * NexusCore EMS — attendance-marksheet.js (State-Driven)
 * ============================================================
 * File        : public/assets/js/modules/attendance-marksheet.js
 * Description : Mark Sheet page controller
 *               Fully migrated to Event-based (Symposium Event) workflow.
 *
 * Responsibilities
 * ─────────────────────────────────────────────────────────
 * • State-Driven Architecture (Single Source of Truth)
 * • Online: Reads DOM, saves to IndexedDB Cache
 * • Offline: Rebuilds state from Cache + Sync Queue
 * • Hydrates DOM, Filters, Stats entirely from in-memory state
 *
 * Config (injected by mark-sheet.php via window.NEXUS_ATTENDANCE):
 *   {
 *     sessionOpen:       bool,
 *     sessionId:         int,
 *     symposiumEventId:  int,
 *     csrfToken:         string,
 *     canMark:           bool,
 *     baseUrl:           string,
 *   }
 *
 * ============================================================
 */

'use strict';

(function () {
    const config = window.NEXUS_ATTENDANCE;
    if (!config) return;

    const BASE_URL         = config.baseUrl || '/NexusCore';
    let SESSION_ID         = config.sessionId;
    const SYMPOSIUM_EID    = config.symposiumEventId;
    const CSRF_TOKEN       = config.csrfToken;
    const CAN_MARK         = config.canMark;

    if (!CAN_MARK) return;

    // ----------------------------------------------------------
    // Reveal Judge Mark Sheet section after session close
    // Works for both offline and (via page reload) online close.
    // ----------------------------------------------------------
    function revealJudgeMarkSheets() {
        const card = document.getElementById('judge-marksheet-card');
        if (!card) return;

        // If buttons already exist (server-rendered), just show the card.
        card.style.display = '';

        // Scroll smoothly so the user sees it appear.
        setTimeout(() => card.scrollIntoView({ behavior: 'smooth', block: 'start' }), 200);
    }

    // Helper: check true server connectivity (sync engine heartbeat > navigator.onLine)
    function isServerReachable() {
        return window.nexusUI?.syncEngine?.trueOnlineStatus ?? navigator.onLine;
    }

    // ----------------------------------------------------------
    // Single Source of Truth
    // ----------------------------------------------------------
    window.NexusAttendanceUI = window.NexusAttendanceUI || {};
    window.NexusAttendanceUI.state = {}; // Map of applicationId -> Participant Object

    // ----------------------------------------------------------
    // State Variables
    // ----------------------------------------------------------
    let activeFilter = 'All';
    let searchQuery  = '';

    // ----------------------------------------------------------
    // DOM References
    // ----------------------------------------------------------
    const tableBody           = document.getElementById('participant-table-body');
    const searchInput         = document.getElementById('attendance-search');
    const filterTabs          = document.querySelectorAll('#filter-tabs [data-filter]');
    const shownCount          = document.getElementById('shown-count');
    const bulkPresentBtn      = document.getElementById('bulk-present-btn');
    const bulkAbsentBtn       = document.getElementById('bulk-absent-btn');
    const offlineQueueInfo    = document.getElementById('offline-queue-info');
    const offlinePendingCount = document.getElementById('offline-pending-count');

    // ----------------------------------------------------------
    // Offline Open Session Handling
    // ----------------------------------------------------------
    function bindOpenSessionForm() {
        const form = document.getElementById('open-session-form');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // ── Offline fallback ────────────────────────────────────
            const executeOfflineFallback = async () => {
                try {
                    await window.nexusUI?.db?.savePendingRecord({
                        symposium_event_id: SYMPOSIUM_EID,
                        session_id:         0,
                        application_id:     0,
                        student_id:         0,
                        attendance_status:  'OPEN_SESSION',
                        captured_at:        new Date().toISOString(),
                    });

                    window.nexusUI?.refreshPendingCount();
                    window.nexusUI?.showToast('Session opened offline. Will sync automatically when online.', 'success', 5000);

                    // Hide modal
                    const modalEl = document.getElementById('openSessionModal');
                    if (modalEl) {
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    }

                    _applySessionOpenUI();

                } catch (err) {
                    window.nexusUI?.showToast('Failed to save offline action.', 'error');
                }
            };

            if (!isServerReachable()) {
                await executeOfflineFallback();
                window.nexusUI?.syncEngine?.forceOffline?.();
                return;
            }

            // ── Online path ──────────────────────────────────────────
            // Disable submit button to prevent double-submit
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Opening…'; }

            try {
                const formData = new FormData(form);
                const res = await fetch(form.action, {
                    method:  'POST',
                    headers: { 'Accept': 'application/json' },
                    body:    formData,
                });

                let data = null;
                try { data = await res.json(); } catch (_) {}

                if (res.ok && data?.success) {
                    // Persist the new session ID so offline marks use the correct ID
                    if (data.session_id) SESSION_ID = data.session_id;

                    window.nexusUI?.showToast('Attendance session opened.', 'success');
                    _applySessionOpenUI();

                    // Hide modal
                    const modalEl = document.getElementById('openSessionModal');
                    if (modalEl) {
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    }
                    
                    // Reload to pick up server-rendered session state (like the new session ID in the close form)
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    const msg = data?.message || `Server error (${res.status}). Please try again.`;
                    window.nexusUI?.showToast(msg, 'error', 6000);
                    
                    // Auto-refresh if it's a CSRF error to recover from stale tabs
                    if (res.status === 403 && msg.includes('Invalid security token')) {
                        setTimeout(() => window.location.reload(), 1500);
                    }
                    
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="bi bi-unlock me-2"></i>Open Session Now'; }
                }
            } catch (err) {
                console.warn('[MarkSheet] Online open session failed — using offline fallback:', err);
                if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="bi bi-unlock me-2"></i>Open Session Now'; }
                await executeOfflineFallback();
                window.nexusUI?.syncEngine?.forceOffline?.();
            }
        });
    }

    // ----------------------------------------------------------
    // Internal: apply session-open UI state changes
    // ----------------------------------------------------------
    function _applySessionOpenUI() {
        // Swap buttons
        const btnOpen = document.getElementById('btn-open-session-modal');
        if (btnOpen) btnOpen.style.display = 'none';

        const formClose = document.getElementById('close-session-form');
        if (formClose) formClose.style.display = 'block';

        // Update badge
        const badge = document.getElementById('session-status-badge');
        if (badge) {
            badge.className = 'badge bg-success text-white px-3 py-2 rounded-pill shadow-sm';
            badge.innerHTML = '<i class="bi bi-unlock me-1"></i>Session Open';
        }
        const statusText = document.getElementById('session-status-text');
        if (statusText) statusText.innerHTML = 'Opened just now';

        // Reveal attendance buttons, hide status badges
        document.querySelectorAll('.attendance-btn-group').forEach(grp => { grp.style.display = 'inline-flex'; });
        document.querySelectorAll('.badge-unmarked').forEach(b => { b.style.display = 'none'; });
        document.querySelectorAll('.badge-status').forEach(b => { b.style.display = 'none'; });

        const bulkDiv = document.getElementById('bulk-actions-container');
        if (bulkDiv) bulkDiv.style.display = 'flex';

        config.sessionOpen = true;
    }

    // ----------------------------------------------------------
    // Initialize
    // ----------------------------------------------------------
    async function waitForNexusUI() {
        // Wait until attendance-ui.js has fully initialized (or failed)
        if (window.nexusUI) return;
        return new Promise(resolve => {
            window.addEventListener('nexusUIReady', resolve, { once: true });
            
            // Fallback timeout just in case
            setTimeout(resolve, 3000);
        });
    }

    document.addEventListener('DOMContentLoaded', async () => {
        await waitForNexusUI();
        await initState();
        hydrateDOM();
        recalculateSummaryStats();
        applyFilter();

        bindAttendanceButtons();
        bindSearch();
        bindFilterTabs();
        bindBulkActions();
        bindCloseSessionForm();
        bindOpenSessionForm();
    });

    // ----------------------------------------------------------
    // 1. Initialize State (Online vs Offline)
    // ----------------------------------------------------------
    async function initState() {
        // Extract base state from PHP-rendered DOM
        const rows     = tableBody?.querySelectorAll('.participant-row') || [];
        const baseState = [];

        rows.forEach(row => {
            const appId = parseInt(row.dataset.applicationId);
            const stuId = parseInt(row.dataset.studentId);
            const p = {
                symposium_event_id: SYMPOSIUM_EID,
                application_id:     appId,
                student_id:         stuId,
                name:               row.dataset.name  || '',
                reg:                row.dataset.reg   || '',
                dept:               row.dataset.dept  || '',
                attendance_status:  row.dataset.status || 'Unmarked',
                sync_source:        row.dataset.syncSource || 'Online',
                data_source:        'Server',
            };
            baseState.push(p);
            window.NexusAttendanceUI.state[stuId] = p;
        });

        if (!window.nexusUI?.db) return;
        const db = window.nexusUI.db;

        if (isServerReachable()) {
            // Online: persist server state to cache
            await db.saveParticipantsCache(baseState);
        } else {
            // Offline: server HTML may be stale — load from cache
            const cached = await db.getParticipantsCache(SYMPOSIUM_EID);
            if (cached.length > 0) {
                cached.forEach(p => {
                    window.NexusAttendanceUI.state[p.student_id] = { ...p, data_source: 'Server' };
                });
            }
        }

        // Apply offline Sync Queue (pending records) strictly in order
        const pending  = await db.getPendingRecords();
        const relevant = pending.filter(
            r => r.symposium_event_id === SYMPOSIUM_EID && r.sync_status !== 'synced'
        );

        let pendingCount = 0;
        
        for (const rec of relevant) {
            if (rec.attendance_status === 'CLOSE_SESSION') {
                const form = document.getElementById('close-session-form');
                if (form) form.style.display = 'none';
                
                const badge = document.getElementById('session-status-badge');
                if (badge) {
                    badge.className = 'badge bg-warning text-dark px-3 py-2 rounded-pill shadow-sm';
                    badge.innerHTML = '<i class="bi bi-cloud-slash me-1"></i>Closed (Offline)';
                }
                const statusText = document.getElementById('session-status-text');
                if (statusText) {
                    statusText.innerHTML = 'Closed just now (Offline)';
                }
                
                document.querySelectorAll('.att-btn').forEach(btn => {
                    btn.disabled = true;
                    btn.classList.add('disabled');
                });
                
                const bulkDiv = document.getElementById('bulk-actions-container');
                if (bulkDiv) bulkDiv.style.display = 'none';

                // Reveal judge mark sheet download section
                revealJudgeMarkSheets();
            } else if (rec.attendance_status === 'OPEN_SESSION') {
                const btnOpen = document.getElementById('btn-open-session-modal');
                if (btnOpen) btnOpen.style.display = 'none';
                
                const formClose = document.getElementById('close-session-form');
                if (formClose) formClose.style.display = 'block';

                const badge = document.getElementById('session-status-badge');
                if (badge) {
                    badge.className = 'badge bg-success text-white px-3 py-2 rounded-pill shadow-sm';
                    badge.innerHTML = '<i class="bi bi-unlock me-1"></i>Session Open (Offline)';
                }
                const statusText = document.getElementById('session-status-text');
                if (statusText) {
                    statusText.innerHTML = 'Opened just now (Offline)';
                }
                
                document.querySelectorAll('.attendance-btn-group').forEach(grp => {
                    grp.style.display = 'inline-flex';
                });
                document.querySelectorAll('.badge-unmarked').forEach(b => b.style.display = 'none');
                document.querySelectorAll('.badge-status').forEach(b => { b.style.display = 'none'; });
                
                const bulkDiv = document.getElementById('bulk-actions-container');
                if (bulkDiv) bulkDiv.style.display = 'flex';
                
                config.sessionOpen = true;
            } else {
                if (rec.student_id && window.NexusAttendanceUI.state[rec.student_id]) {
                    window.NexusAttendanceUI.state[rec.student_id].attendance_status = rec.attendance_status;
                    window.NexusAttendanceUI.state[rec.student_id].data_source = rec.sync_status === 'failed' ? 'Failed Sync' : 'IndexedDB (Pending)';
                    window.NexusAttendanceUI.state[rec.student_id].last_error = rec.last_error;
                } else if (rec.application_id) {
                    // Fallback for record looking up via appId
                    const p = Object.values(window.NexusAttendanceUI.state).find(st => st.application_id === rec.application_id);
                    if (p) {
                        p.attendance_status = rec.attendance_status;
                        p.data_source = rec.sync_status === 'failed' ? 'Failed Sync' : 'IndexedDB (Pending)';
                        p.last_error = rec.last_error;
                    }
                }
                pendingCount++;
            }
        }

        if (offlineQueueInfo)    offlineQueueInfo.style.display    = pendingCount > 0 ? '' : 'none';
        if (offlinePendingCount) offlinePendingCount.textContent   = pendingCount;
    }

    // ----------------------------------------------------------
    // 2. Hydrate DOM purely from State
    // ----------------------------------------------------------
    function hydrateDOM() {
        const rows = tableBody?.querySelectorAll('.participant-row') || [];

        rows.forEach(row => {
            const appId = parseInt(row.dataset.applicationId);
            const stuId = parseInt(row.dataset.studentId);
            const p     = window.NexusAttendanceUI.state[stuId];
            if (!p) return;

            // 1. Update dataset
            row.dataset.status = p.attendance_status;

            // 2. Update buttons
            const cell = document.getElementById(`att-cell-${appId}-${stuId}`);
            if (cell) {
                cell.querySelectorAll('.att-btn').forEach(btn => {
                    const s = btn.dataset.status;
                    if (s === p.attendance_status) {
                        btn.classList.remove('btn-outline-success', 'btn-outline-warning', 'btn-outline-danger');
                        btn.classList.add(
                            s === 'Present' ? 'btn-success' :
                            s === 'Late'    ? 'btn-warning' : 'btn-danger',
                            'active'
                        );
                    } else {
                        btn.classList.remove('btn-success', 'btn-warning', 'btn-danger', 'active');
                        btn.classList.add(
                            s === 'Present' ? 'btn-outline-success' :
                            s === 'Late'    ? 'btn-outline-warning' : 'btn-outline-danger'
                        );
                    }
                });
            }

            // 3. Update Sync Source Badge
            const syncCell = document.getElementById(`sync-cell-${appId}-${stuId}`);
            if (syncCell) {
                if (p.data_source === 'IndexedDB (Pending)') {
                    syncCell.innerHTML = `<span class="badge bg-warning text-dark small"><i class="bi bi-clock-history"></i> Pending</span>`;
                } else if (p.data_source === 'Failed Sync') {
                    const errMsg = (p.last_error || 'Unknown error').replace(/"/g, '&quot;');
                    syncCell.innerHTML = `<span class="badge bg-danger small" title="${errMsg}" data-bs-toggle="tooltip"><i class="bi bi-exclamation-triangle"></i> Failed Sync</span>`;
                } else {
                    const source = p.sync_source || 'Online';
                    if (source === 'Offline') {
                        syncCell.innerHTML = `<span class="badge bg-info text-dark small"><i class="bi bi-wifi-off"></i> Offline</span>`;
                    } else {
                        syncCell.innerHTML = `<span class="badge bg-light text-muted border small"><i class="bi bi-wifi"></i> Online</span>`;
                    }
                }
            }
        });
    }

    // ----------------------------------------------------------
    // 3. Filters based on State
    // ----------------------------------------------------------
    function applyFilter() {
        const rows = tableBody?.querySelectorAll('.participant-row') || [];
        let shown  = 0;

        rows.forEach(row => {
            const stuId = parseInt(row.dataset.studentId);
            const p     = window.NexusAttendanceUI.state[stuId];
            if (!p) return;

            const matchSearch =
                searchQuery === '' ||
                p.name.toLowerCase().includes(searchQuery) ||
                p.reg.toLowerCase().includes(searchQuery)  ||
                p.dept.toLowerCase().includes(searchQuery);

            const matchFilter =
                activeFilter === 'All' ||
                p.attendance_status === activeFilter ||
                (activeFilter === 'Unmarked' && (!p.attendance_status || p.attendance_status === 'Unmarked'));

            const visible = matchSearch && matchFilter;
            row.style.display = visible ? '' : 'none';
            if (visible) shown++;
        });

        if (shownCount) shownCount.textContent = shown;
    }

    // ----------------------------------------------------------
    // 4. Statistics based on State
    // ----------------------------------------------------------
    function recalculateSummaryStats() {
        const participants = Object.values(window.NexusAttendanceUI.state);

        let present = 0, absent = 0, late = 0, unmarked = 0;
        const depts = {};

        participants.forEach(p => {
            if (!depts[p.dept]) {
                depts[p.dept] = { total: 0, present: 0, absent: 0, late: 0, unmarked: 0 };
            }
            depts[p.dept].total++;

            if (p.attendance_status === 'Present')      { present++; depts[p.dept].present++; }
            else if (p.attendance_status === 'Absent')  { absent++;  depts[p.dept].absent++;  }
            else if (p.attendance_status === 'Late')    { late++;    depts[p.dept].late++;    }
            else                                        { unmarked++; depts[p.dept].unmarked++; }
        });

        const total = participants.length;
        const rate  = total > 0 ? Math.round(((present + late) / total) * 100) : 0;

        document.getElementById('stat-total-approved')?.textContent !== undefined &&
            (document.getElementById('stat-total-approved').textContent = total);
        document.getElementById('stat-present')?.textContent !== undefined &&
            (document.getElementById('stat-present').textContent = present);
        document.getElementById('stat-absent')?.textContent !== undefined &&
            (document.getElementById('stat-absent').textContent = absent);
        document.getElementById('stat-late')?.textContent !== undefined &&
            (document.getElementById('stat-late').textContent = late);
        document.getElementById('stat-unmarked')?.textContent !== undefined &&
            (document.getElementById('stat-unmarked').textContent = unmarked);
        document.getElementById('stat-attendance-pct')?.textContent !== undefined &&
            (document.getElementById('stat-attendance-pct').textContent = rate + '%');

        // Department breakdown table
        const tbody = document.getElementById('dept-breakdown-body');
        if (tbody) {
            tbody.innerHTML = '';
            for (const [deptName, d] of Object.entries(depts)) {
                if (!deptName) continue;
                const drate = d.total > 0 ? Math.round(((d.present + d.late) / d.total) * 100) : 0;
                let bgClass = 'danger';
                if (drate >= 75) bgClass = 'success';
                else if (drate >= 50) bgClass = 'warning';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="px-4">${deptName.charAt(0).toUpperCase() + deptName.slice(1)}</td>
                    <td class="text-center">${d.total}</td>
                    <td class="text-center text-success fw-semibold">${d.present}</td>
                    <td class="text-center text-warning fw-semibold">${d.late}</td>
                    <td class="text-center text-danger fw-semibold">${d.absent}</td>
                    <td class="text-center text-muted">${d.unmarked}</td>
                    <td class="text-center">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:5px;">
                                <div class="progress-bar bg-${bgClass}" style="width:${drate}%"></div>
                            </div>
                            <span class="small fw-semibold" style="min-width:35px;">${drate}%</span>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            }
        }
    }

    // ----------------------------------------------------------
    // Event Binders
    // ----------------------------------------------------------
    function bindSearch() {
        searchInput?.addEventListener('input', () => {
            searchQuery = searchInput.value.toLowerCase().trim();
            applyFilter();
        });
    }

    function bindFilterTabs() {
        filterTabs.forEach(btn => {
            btn.addEventListener('click', () => {
                filterTabs.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activeFilter = btn.dataset.filter;
                applyFilter();
            });
        });
    }

    function bindAttendanceButtons() {
        tableBody?.addEventListener('click', async (e) => {
            const btn = e.target.closest('.att-btn');
            if (!btn) return;

            const appId  = parseInt(btn.dataset.appId);
            const stuId  = parseInt(btn.dataset.stuId);
            const status = btn.dataset.status;
            if (!stuId || !status) return;

            await handleMark(appId, stuId, status);
        });
    }

    async function handleMark(appId, stuId, status, notes = '') {
        const p = window.NexusAttendanceUI.state[stuId];
        if (!p) return;

        const oldStatus = p.attendance_status;
        if (oldStatus === status) return;

        // 1. Optimistic update
        p.attendance_status = status;
        p.data_source       = isServerReachable() ? 'Server' : 'IndexedDB (Pending)';

        hydrateDOM();
        recalculateSummaryStats();
        applyFilter();

        if (isServerReachable()) {
            try {
                const formData = new FormData();
                formData.append('csrf_token',          CSRF_TOKEN);
                formData.append('session_id',           SESSION_ID);
                formData.append('symposium_event_id',   SYMPOSIUM_EID);
                formData.append('application_id',       appId);
                formData.append('student_id',           stuId);
                formData.append('attendance_status',    status);
                formData.append('coordinator_notes',    notes);

                const res = await fetch(`${BASE_URL}/attendance/mark`, {
                    method:  'POST',
                    headers: { 'Accept': 'application/json' },
                    body:    formData,
                });

                if (!res.ok) {
                    const errorText = await res.text();
                    let errorMsg = `Server error (${res.status})`;
                    try {
                        const parsed = JSON.parse(errorText);
                        if (parsed.message) errorMsg = parsed.message;
                    } catch(e) {}
                    throw new Error(`BUSINESS_ERROR:${errorMsg}`);
                }

                const data = await res.json();
                if (data.success) {
                    window.nexusUI?.showToast(`Marked ${status}`, 'success', 1500);
                } else {
                    throw new Error(`BUSINESS_ERROR:${data.message || 'Server rejected attendance.'}`);
                }
            } catch (err) {
                if (err.message.startsWith('BUSINESS_ERROR:')) {
                    const msg = err.message.replace('BUSINESS_ERROR:', '');
                    window.nexusUI?.showToast(msg, 'error');
                    // Revert optimistic update
                    p.attendance_status = oldStatus;
                    hydrateDOM();
                    recalculateSummaryStats();
                    applyFilter();
                } else {
                    window.nexusUI?.showToast(err.message + ' — saving offline.', 'warning');
                    await queueOffline(appId, stuId, status, oldStatus);
                    window.nexusUI?.syncEngine?.forceOffline();
                }
            }
        } else {
            await queueOffline(appId, stuId, status, oldStatus);
            window.nexusUI?.syncEngine?.forceOffline();
        }
    }

    async function queueOffline(appId, studentId, status, oldStatus) {
        if (!window.nexusUI?.db) {
            window.nexusUI?.showToast('Offline storage unavailable.', 'error');
            return;
        }

        try {
            await window.nexusUI.db.savePendingRecord({
                symposium_event_id: SYMPOSIUM_EID,
                session_id:         SESSION_ID,
                application_id:     appId,
                student_id:         studentId,
                attendance_status:  status,
                old_status:         oldStatus,
                captured_at:        new Date().toISOString(),
            });

            const p = window.NexusAttendanceUI.state[studentId];
            if (p) p.data_source = 'IndexedDB (Pending)';
            hydrateDOM();

            const count = await window.nexusUI.db.getPendingCount();
            if (offlinePendingCount) offlinePendingCount.textContent = count;
            if (offlineQueueInfo)    offlineQueueInfo.style.display  = count > 0 ? '' : 'none';

            window.nexusUI?.refreshPendingCount();
            window.nexusUI?.showToast(`Saved offline (${status}). Will sync when online.`, 'warning', 3000);
            window.nexusUI?.syncEngine?.registerBackgroundSync();

        } catch (err) {
            window.nexusUI?.showToast('Failed to save offline: ' + err.message, 'error');
        }
    }

    // ----------------------------------------------------------
    // Bulk Actions
    // ----------------------------------------------------------
    function bindBulkActions() {
        bulkPresentBtn?.addEventListener('click', () => bulkMark('Present'));
        bulkAbsentBtn?.addEventListener('click',  () => bulkMark('Absent'));
    }

    async function bulkMark(defaultStatus) {
        const rows = tableBody?.querySelectorAll('.participant-row:not([style*="display: none"])') || [];
        if (rows.length === 0) {
            window.nexusUI?.showToast('No participants visible to mark.', 'warning');
            return;
        }

        const confirmed = window.confirm(`Mark all ${rows.length} visible participants as "${defaultStatus}"?`);
        if (!confirmed) return;

        window.nexusUI?.showToast(`Marking ${rows.length} participants as ${defaultStatus}…`, 'info', 2000);

        // Optimistic update
        const batch = [];
        const oldStatuses = {};
        
        rows.forEach(row => {
            const appId = parseInt(row.dataset.applicationId);
            const stuId = parseInt(row.dataset.studentId);
            const p = window.NexusAttendanceUI.state[stuId];
            if (p && p.attendance_status !== defaultStatus) {
                batch.push({appId, stuId});
                oldStatuses[stuId] = p.attendance_status;
                p.attendance_status = defaultStatus;
                p.data_source       = isServerReachable() ? 'Server' : 'IndexedDB (Pending)';
            }
        });

        if (batch.length === 0) {
            window.nexusUI?.showToast(`All visible participants are already marked as ${defaultStatus}.`, 'info');
            return;
        }

        hydrateDOM();
        recalculateSummaryStats();
        applyFilter();

        if (isServerReachable()) {
            const overrides = {};
            batch.forEach(b => overrides[b.stuId] = defaultStatus);

            try {
                const formData = new FormData();
                formData.append('csrf_token',         CSRF_TOKEN);
                formData.append('session_id',          SESSION_ID);
                formData.append('symposium_event_id',  SYMPOSIUM_EID);
                formData.append('default_status',      defaultStatus);
                formData.append('overrides',           JSON.stringify(overrides));

                const res = await fetch(`${BASE_URL}/attendance/bulk-mark`, {
                    method:  'POST',
                    headers: { 'Accept': 'application/json' },
                    body:    formData,
                });
                const data = await res.json();

                if (data.success) {
                    window.nexusUI?.showToast(`${data.succeeded || rows.length} marked as ${defaultStatus}.`, 'success');
                } else {
                    throw new Error(data.message || 'Bulk mark failed');
                }
            } catch (err) {
                console.warn('[MarkSheet] Bulk online failed — using offline:', err);
                await bulkQueueOffline(batch, defaultStatus, oldStatuses);
                window.nexusUI?.syncEngine?.forceOffline();
            }
        } else {
            await bulkQueueOffline(batch, defaultStatus, oldStatuses);
            window.nexusUI?.syncEngine?.forceOffline();
        }
    }

    async function bulkQueueOffline(batch, status, oldStatuses = {}) {
        for (const b of batch) {
            const p = window.NexusAttendanceUI.state[b.stuId];
            if (!p) continue;
            await queueOffline(b.appId, b.stuId, status, oldStatuses[b.stuId]);
        }
        hydrateDOM();
        window.nexusUI?.refreshPendingCount();
        window.nexusUI?.showToast(`Saved offline. Will sync when online.`, 'warning', 4000);
    }

    // ----------------------------------------------------------
    // Keyboard shortcuts (hover + key)
    // ----------------------------------------------------------
    document.addEventListener('keydown', (e) => {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;
        const focusedRow = document.querySelector('.participant-row:hover');
        if (!focusedRow) return;

        const appId = parseInt(focusedRow.dataset.applicationId);
        const stuId = parseInt(focusedRow.dataset.studentId);
        if (!appId || !stuId) return;

        // Prevent marking if the session is closed (indicated by disabled or hidden buttons)
        const sampleBtn = focusedRow.querySelector('.att-btn');
        if (!sampleBtn) return;
        
        const group = sampleBtn.closest('.attendance-btn-group');
        const isHidden = group && window.getComputedStyle(group).display === 'none';
        if (isHidden || sampleBtn.disabled || sampleBtn.classList.contains('disabled')) return;

        const keyMap = { p: 'Present', a: 'Absent', l: 'Late' };
        const status = keyMap[e.key.toLowerCase()];
        if (status) {
            e.preventDefault();
            handleMark(appId, stuId, status);
        }
    });

    // ----------------------------------------------------------
    // Offline Close Session Handling
    // ----------------------------------------------------------
    function bindCloseSessionForm() {
        const form = document.getElementById('close-session-form');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            // ── Offline fallback ────────────────────────────────────
            const executeOfflineFallback = async () => {
                const confirmed = confirm('Are you sure you want to close this session? It will be synced when you are back online.');
                if (!confirmed) return;

                try {
                    await window.nexusUI?.db?.savePendingRecord({
                        symposium_event_id: SYMPOSIUM_EID,
                        session_id:         SESSION_ID,
                        application_id:     0,
                        student_id:         0,
                        attendance_status:  'CLOSE_SESSION',
                        captured_at:        new Date().toISOString(),
                    });

                    window.nexusUI?.refreshPendingCount();
                    window.nexusUI?.showToast('Session closed offline. Will sync automatically when online.', 'success', 5000);
                    _applySessionCloseUI();

                } catch (err) {
                    window.nexusUI?.showToast('Failed to save offline action.', 'error');
                }
            };

            if (!isServerReachable()) {
                await executeOfflineFallback();
                window.nexusUI?.syncEngine?.forceOffline?.();
                return;
            }

            // ── Online path ──────────────────────────────────────────
            const confirmed = confirm('Are you sure you want to close this attendance session? Ensure all records are saved.');
            if (!confirmed) return;

            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Closing…'; }

            try {
                const formData = new FormData(form);
                const res = await fetch(form.action, {
                    method:  'POST',
                    headers: { 'Accept': 'application/json' },
                    body:    formData,
                });

                let data = null;
                try { data = await res.json(); } catch (_) {}

                if (res.ok && data?.success) {
                    window.nexusUI?.showToast('Session closed successfully.', 'success');
                    // Give it a brief moment so user sees toast, then reload for full server state sync
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    const msg = data?.message || `Server error (${res.status}). Please try again.`;
                    window.nexusUI?.showToast(msg, 'error', 6000);
                    
                    // Auto-refresh if it's a CSRF error to recover from stale tabs
                    if (res.status === 403 && msg.includes('Invalid security token')) {
                        setTimeout(() => window.location.reload(), 1500);
                    }
                    
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="bi bi-lock me-1"></i>Close Session'; }
                }
            } catch (err) {
                console.warn('[MarkSheet] Online close session failed — using offline fallback:', err);
                if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<i class="bi bi-lock me-1"></i>Close Session'; }
                await executeOfflineFallback();
                window.nexusUI?.syncEngine?.forceOffline?.();
            }
        });
    }

    // ----------------------------------------------------------
    // Internal: apply session-close UI state changes
    // ----------------------------------------------------------
    function _applySessionCloseUI() {
        const closeForm = document.getElementById('close-session-form');
        if (closeForm) closeForm.style.display = 'none';

        const badge = document.getElementById('session-status-badge');
        if (badge) {
            badge.className = 'badge bg-secondary me-2';
            badge.innerHTML = 'Session Closed';
        }
        const statusText = document.getElementById('session-status-text');
        if (statusText) statusText.innerHTML = 'Closed just now';

        document.querySelectorAll('.att-btn').forEach(btn => {
            btn.disabled = true;
            btn.classList.add('disabled');
        });

        const bulkDiv = document.getElementById('bulk-actions-container');
        if (bulkDiv) bulkDiv.style.display = 'none';

        // Reveal judge mark sheet download section
        revealJudgeMarkSheets();
    }

    // Expose global callback for sync engine so UI refreshes after sync
    window.NexusAttendanceUI.onSyncComplete = async function(results) {
        if (results && results.synced > 0) {
            // A sync just occurred. The server may have generated a new Session ID
            // or closed a session. We must reload to get the fresh PHP state.
            setTimeout(() => window.location.reload(), 1500);
            return;
        }

        const db = window.nexusUI?.db;
        if (!db) return;

        await initState();
        hydrateDOM();
        recalculateSummaryStats();
        applyFilter();
    };

})();
