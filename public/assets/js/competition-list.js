/**
 * ==========================================================================
 * NexusCore — Competition List JavaScript
 * ==========================================================================
 * File        : competition-list.js
 * Location    : public/assets/js/
 * Description : Client-side logic for the Competition List page.
 *
 * Features
 * --------------------------------------------------------------------------
 * • Confirm dialogs for delete / archive
 * • Status change via AJAX with Bootstrap toast
 * • Duplicate competition via AJAX
 * • Client-side column sort
 * • Real-time search (debounced)
 * • Quick filter tabs
 *
 * ==========================================================================
 */

'use strict';

const CompetitionList = (() => {

    /* -----------------------------------------------------------------------
     * INIT
     * --------------------------------------------------------------------- */
    function init() {
        initToastContainer();
        bindDeleteButtons();
        bindArchiveButtons();
        bindRestoreButtons();
        bindStatusChangeForms();
        bindDuplicateButtons();
        bindSearchRealtime();
        bindQuickFilters();
        highlightActiveQuickFilter();
    }

    /* -----------------------------------------------------------------------
     * DELETE
     * --------------------------------------------------------------------- */
    function bindDeleteButtons() {
        document.querySelectorAll('[data-action="competition-delete"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id    = btn.dataset.id;
                const title = btn.dataset.title;

                showConfirm(
                    `Delete "${title}"?`,
                    'This will permanently remove the competition. This action cannot be undone.',
                    'danger',
                    'Delete',
                    () => postAction(`${BASE_URL}/competitions/delete`, { competition_id: id })
                );
            });
        });
    }

    /* -----------------------------------------------------------------------
     * ARCHIVE
     * --------------------------------------------------------------------- */
    function bindArchiveButtons() {
        document.querySelectorAll('[data-action="competition-archive"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id    = btn.dataset.id;
                const title = btn.dataset.title;

                showConfirm(
                    `Archive "${title}"?`,
                    'The competition will be moved to the archive. You can restore it later.',
                    'warning',
                    'Archive',
                    () => postAction(`${BASE_URL}/competitions/archive`, { competition_id: id })
                );
            });
        });
    }

    /* -----------------------------------------------------------------------
     * RESTORE
     * --------------------------------------------------------------------- */
    function bindRestoreButtons() {
        document.querySelectorAll('[data-action="competition-restore"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id    = btn.dataset.id;
                const title = btn.dataset.title;

                showConfirm(
                    `Restore "${title}"?`,
                    'This competition will be restored and set to Draft status.',
                    'success',
                    'Restore',
                    () => postAction(`${BASE_URL}/competitions/restore`, { competition_id: id })
                );
            });
        });
    }

    /* -----------------------------------------------------------------------
     * DUPLICATE
     * --------------------------------------------------------------------- */
    function bindDuplicateButtons() {
        document.querySelectorAll('[data-action="competition-duplicate"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id    = btn.dataset.id;
                const title = btn.dataset.title;

                showConfirm(
                    `Duplicate "${title}"?`,
                    'A copy will be created in Draft status. Rules are copied, coordinators must be re-assigned.',
                    'info',
                    'Duplicate',
                    async () => {
                        try {
                            const res  = await postAjax(`${BASE_URL}/competitions/duplicate`, { competition_id: id });
                            const data = await res.json();
                            if (data.success && data.competition_id) {
                                showToast(data.message, 'success');
                                setTimeout(() => {
                                    window.location.href = `${BASE_URL}/competitions/edit?id=${data.competition_id}`;
                                }, 1200);
                            } else {
                                showToast(data.message || 'Failed to duplicate.', 'danger');
                            }
                        } catch {
                            showToast('An error occurred. Please try again.', 'danger');
                        }
                    }
                );
            });
        });
    }

    /* -----------------------------------------------------------------------
     * STATUS CHANGE
     * --------------------------------------------------------------------- */
    function bindStatusChangeForms() {
        document.querySelectorAll('.comp-status-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(form);
                const status   = formData.get('status');
                const title    = form.dataset.title;

                showConfirm(
                    `Change Status?`,
                    `Set "${title}" to "${status}"?`,
                    'primary',
                    'Change',
                    async () => {
                        try {
                            const res  = await fetch(`${BASE_URL}/competitions/change-status`, {
                                method:  'POST',
                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                body:    formData,
                            });
                            const data = await res.json();
                            if (data.success) {
                                showToast(data.message, 'success');
                                setTimeout(() => window.location.reload(), 1000);
                            } else {
                                showToast(data.message, 'danger');
                            }
                        } catch {
                            showToast('An error occurred.', 'danger');
                        }
                    }
                );
            });
        });
    }

    /* -----------------------------------------------------------------------
     * REAL-TIME SEARCH
     * --------------------------------------------------------------------- */
    function bindSearchRealtime() {
        const searchInput = document.getElementById('comp-search-input');
        if (!searchInput) return;

        let searchTimer = null;

        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                submitFilters();
            }, 500);
        });
    }

    /* -----------------------------------------------------------------------
     * QUICK FILTER TABS
     * --------------------------------------------------------------------- */
    function bindQuickFilters() {
        document.querySelectorAll('[data-quick-filter]').forEach(btn => {
            btn.addEventListener('click', () => {
                const filter    = btn.dataset.quickFilter;
                const statusSel = document.getElementById('filter-status');
                const catSel    = document.getElementById('filter-category');
                const modeSel   = document.getElementById('filter-mode');

                // Reset all filters
                if (statusSel) statusSel.value = '';
                if (catSel)    catSel.value    = '';
                if (modeSel)   modeSel.value   = '';

                // Apply selected quick filter
                switch (filter) {
                    case 'open':
                        if (statusSel) statusSel.value = 'Registration Open'; break;
                    case 'technical':
                        if (catSel) catSel.value = 'Technical'; break;
                    case 'non-technical':
                        if (catSel) catSel.value = 'Non-Technical'; break;
                    case 'online':
                        if (modeSel) modeSel.value = 'Online'; break;
                    case 'today':
                        const today = new Date().toISOString().slice(0, 10);
                        const dateEl = document.getElementById('filter-event-date');
                        if (dateEl) dateEl.value = today; break;
                    case 'completed':
                        if (statusSel) statusSel.value = 'Completed'; break;
                    case 'all': break; // clear all = just submit
                }

                submitFilters();
            });
        });
    }

    function highlightActiveQuickFilter() {
        const params = new URLSearchParams(window.location.search);
        const status = params.get('status');
        const cat    = params.get('category');
        const mode   = params.get('mode');

        document.querySelectorAll('[data-quick-filter]').forEach(btn => {
            const f = btn.dataset.quickFilter;
            let active = false;

            if (f === 'open'        && status === 'Registration Open') active = true;
            if (f === 'technical'   && cat    === 'Technical')         active = true;
            if (f === 'non-technical'&& cat   === 'Non-Technical')     active = true;
            if (f === 'online'      && mode   === 'Online')            active = true;
            if (f === 'completed'   && status === 'Completed')         active = true;
            if (f === 'all' && !status && !cat && !mode)               active = true;

            btn.classList.toggle('active', active);
        });
    }

    function submitFilters() {
        const form = document.getElementById('comp-filter-form');
        if (form) form.submit();
    }

    /* -----------------------------------------------------------------------
     * POST ACTION (form submit + redirect)
     * --------------------------------------------------------------------- */
    async function postAction(url, data) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;

        Object.entries(data).forEach(([k, v]) => {
            const input   = document.createElement('input');
            input.type    = 'hidden';
            input.name    = k;
            input.value   = v;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }

    async function postAjax(url, data) {
        const formData = new FormData();
        Object.entries(data).forEach(([k, v]) => formData.append(k, v));

        return fetch(url, {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body:    formData,
        });
    }

    /* -----------------------------------------------------------------------
     * CONFIRM MODAL
     * --------------------------------------------------------------------- */
    function showConfirm(title, message, type, confirmLabel, onConfirm) {
        // Remove any previous modal
        document.getElementById('comp-confirm-modal')?.remove();

        const colorMap = {
            danger: 'btn-danger', warning: 'btn-warning',
            info: 'btn-info', success: 'btn-success', primary: 'btn-primary'
        };

        const modalHtml = `
            <div class="modal fade" id="comp-confirm-modal" tabindex="-1" aria-modal="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">${escHtml(title)}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">${escHtml(message)}</p>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" id="comp-confirm-ok"
                                    class="btn ${colorMap[type] || 'btn-primary'}">
                                ${escHtml(confirmLabel)}
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal   = new bootstrap.Modal(document.getElementById('comp-confirm-modal'));
        const okBtn   = document.getElementById('comp-confirm-ok');

        okBtn.addEventListener('click', () => {
            modal.hide();
            onConfirm();
        });

        modal.show();
    }

    /* -----------------------------------------------------------------------
     * TOAST CONTAINER + SHOW TOAST
     * --------------------------------------------------------------------- */
    function initToastContainer() {
        if (!document.getElementById('toast-container')) {
            const el = document.createElement('div');
            el.id    = 'toast-container';
            el.className = 'position-fixed bottom-0 end-0 p-3';
            el.style.zIndex = '9999';
            document.body.appendChild(el);
        }
    }

    function showToast(message, type = 'info') {
        const colorMap = {
            success: 'bg-success text-white',
            danger:  'bg-danger text-white',
            warning: 'bg-warning text-dark',
            info:    'bg-info text-dark',
        };

        const id  = 'toast-' + Date.now();
        const html = `
            <div id="${id}" class="toast align-items-center ${colorMap[type] || 'bg-info text-dark'} border-0 show mb-2"
                 role="alert" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body fw-semibold">${escHtml(message)}</div>
                    <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`;

        document.getElementById('toast-container').insertAdjacentHTML('beforeend', html);
        setTimeout(() => document.getElementById(id)?.remove(), 5000);
    }

    /* -----------------------------------------------------------------------
     * UTILITIES
     * --------------------------------------------------------------------- */
    function escHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* -----------------------------------------------------------------------
     * PUBLIC API
     * --------------------------------------------------------------------- */
    return { init };

})();

document.addEventListener('DOMContentLoaded', CompetitionList.init);
