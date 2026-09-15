(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', async () => {
        if (!window.NexusAttendanceDB) return;

        try {
            const db = new window.NexusAttendanceDB();
            await db.open();
            
            const pending = await db.getPendingRecords();
            if (!pending || pending.length === 0) return;

            // Group by event
            const byEvent = {};
            pending.forEach(rec => {
                const eid = rec.symposium_event_id;
                if (!byEvent[eid]) byEvent[eid] = [];
                byEvent[eid].push(rec);
            });

            // Process each event
            for (const [eid, records] of Object.entries(byEvent)) {
                processEventStats(eid, records);
            }
        } catch (err) {
            console.error('[AttendanceOverview] Failed to load offline records:', err);
        }

        // ----------------------------------------------------------
        // Pre-fetch Mark Sheet pages for seamless offline access
        // ----------------------------------------------------------
        if (navigator.onLine) {
            const prefetchPages = () => {
                document.querySelectorAll('a[href*="/attendance/event?id="]').forEach(link => {
                    // Fetching with text/html Accept header triggers the SW PAGES_CACHE
                    fetch(link.href, { headers: { 'Accept': 'text/html' }, priority: 'low' })
                        .catch(() => {});
                });
            };

            if (window.requestIdleCallback) {
                window.requestIdleCallback(prefetchPages, { timeout: 2000 });
            } else {
                setTimeout(prefetchPages, 1000);
            }
        }

        // Intercept PDF Export clicks when offline
        document.querySelectorAll('a[href*="/attendance/export?id="]').forEach(link => {
            link.addEventListener('click', (e) => {
                if (!navigator.onLine) {
                    e.preventDefault();
                    window.nexusUI?.showToast("Offline Mode: PDF export is not available. Open the event to print the Mark Sheet instead.", "warning", 5000);
                }
            });
        });
    });

    function processEventStats(eid, records) {
        let isClosedOffline = false;
        const deltas = { Present: 0, Absent: 0, Late: 0 };

        records.forEach(rec => {
            if (rec.attendance_status === 'CLOSE_SESSION') {
                isClosedOffline = true;
                return;
            }

            const newStatus = rec.attendance_status;
            const oldStatus = rec.old_status; // Captured before the offline mark

            // If there's an old status that was one of our counted stats, decrement it
            if (oldStatus === 'Present') deltas.Present--;
            else if (oldStatus === 'Absent') deltas.Absent--;
            else if (oldStatus === 'Late') deltas.Late--;

            // Increment the new status
            if (newStatus === 'Present') deltas.Present++;
            else if (newStatus === 'Absent') deltas.Absent++;
            else if (newStatus === 'Late') deltas.Late++;
        });

        // 1. Handle Session Closure UI Updates
        if (isClosedOffline) {
            const badge = document.getElementById(`live-badge-${eid}`);
            if (badge) badge.remove();

            const circle = document.getElementById(`circle-${eid}`);
            if (circle) circle.classList.remove('attendance-circle-active');

            const btnText = document.getElementById(`mark-btn-text-${eid}`);
            if (btnText) btnText.textContent = 'Open Attendance';
        }

        // 2. Handle Stat Updates
        const elTotal = document.getElementById(`stat-total-${eid}`);
        const elPresent = document.getElementById(`stat-present-${eid}`);
        const elAbsent = document.getElementById(`stat-absent-${eid}`);
        const elLate = document.getElementById(`stat-late-${eid}`);

        if (!elTotal || !elPresent || !elAbsent || !elLate) return;

        const total = parseInt(elTotal.textContent) || 0;
        let present = parseInt(elPresent.textContent) || 0;
        let absent = parseInt(elAbsent.textContent) || 0;
        let late = parseInt(elLate.textContent) || 0;

        // Apply deltas
        present += deltas.Present;
        absent += deltas.Absent;
        late += deltas.Late;

        // Ensure bounds (just in case)
        if (present < 0) present = 0;
        if (absent < 0) absent = 0;
        if (late < 0) late = 0;

        // Update DOM
        elPresent.textContent = present;
        elAbsent.textContent = absent;
        elLate.textContent = late;

        // Recalculate percentage
        const pct = total > 0 ? Math.round(((present + late) / total) * 100) : 0;

        const elCirclePct = document.getElementById(`circle-pct-${eid}`);
        const elBarPct = document.getElementById(`bar-pct-${eid}`);
        const elBarWidth = document.getElementById(`bar-width-${eid}`);

        if (elCirclePct) elCirclePct.textContent = pct + '%';
        if (elBarPct) elBarPct.textContent = pct + '%';
        
        if (elBarWidth) {
            elBarWidth.style.width = pct + '%';
            // Update color class
            elBarWidth.className = 'progress-bar bg-' + (pct >= 75 ? 'success' : (pct >= 50 ? 'warning' : 'danger'));
        }
    }

})();
