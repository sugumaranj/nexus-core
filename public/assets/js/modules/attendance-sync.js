/**
 * ============================================================
 * NexusCore EMS — attendance-sync.js  (Production v2)
 * ============================================================
 * File        : public/assets/js/modules/attendance-sync.js
 * Description : Synchronization Engine
 *               Fully migrated to Event-based (Symposium Event) workflow.
 *
 * Responsibilities
 * ─────────────────────────────────────────────────────────
 * • Heartbeat ping every 5s to determine true server reachability
 * • Auto-sync when back online
 * • Manual syncAll() triggered by user
 * • Exponential backoff retry (1→2→4→8→16s, max 5 retries)
 * • Batch processing (max 20 records per request)
 * • Groups pending records by symposium_event_id
 * • Posts each group to POST /attendance/sync
 * • Background Sync via Service Worker postMessage
 *
 * Events Emitted
 * ─────────────────────────────────────────────────────────
 * • heartbeat-done   { online: bool }        — after every heartbeat
 * • network-online   {}                      — transition: offline → online
 * • network-offline  {}                      — transition: online → offline
 * • sync-start       {}                      — sync batch started
 * • sync-complete    { synced, failed }      — sync finished
 * • sync-error       { error }               — fatal sync error
 * • batch-complete   { symposiumEventId, synced, failed }
 *
 * ============================================================
 */

'use strict';

class NexusSyncEngine {
    constructor(db) {
        this.db          = db;         // NexusAttendanceDB instance
        // Use native status as baseline to prevent 'Back online' toast spam on page load
        this.trueOnlineStatus = typeof navigator !== 'undefined' ? navigator.onLine : null;
        this.isSyncing   = false;
        this.batchSize   = 20;
        this.maxRetries  = 5;
        this.baseRetryMs = 1000;       // 1 second base delay
        this.listeners   = {};         // event listeners
        this._heartbeatTimer = null;
        this._heartbeatCtrl  = null;   // AbortController for the current in-flight ping
        this.sessionExpired = false;

        // Generation counter: incremented every time the native 'offline' event fires.
        // Any in-flight fetch that completes after an offline event sees a mismatched
        // generation and discards its result, preventing the race condition where a
        // stale fetch flips the status back to Online.
        this._gen = 0;
    }

    // ----------------------------------------------------------
    // Event System
    // ----------------------------------------------------------
    on(event, fn) {
        if (!this.listeners[event]) this.listeners[event] = [];
        this.listeners[event].push(fn);
        return this;
    }

    emit(event, data) {
        (this.listeners[event] || []).forEach(fn => {
            try { fn(data); } catch (e) { console.error('[SyncEngine] Event handler error:', event, e); }
        });
    }

    // ----------------------------------------------------------
    // Initialize — attach browser events + run first heartbeat
    // ----------------------------------------------------------
    init() {
        // Listen for background sync message from service worker
        if (typeof window !== 'undefined' && navigator.serviceWorker) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data?.type === 'BACKGROUND_SYNC_TRIGGERED') {
                    console.log('[SyncEngine] Background sync triggered by SW');
                    this.syncAll();
                }
            });
        }

        // Heartbeat timer — every 2 seconds (faster detection for virtual adapter users)
        this._heartbeatTimer = setInterval(() => this._heartbeat(), 2000);

        // Run the first heartbeat immediately
        this._heartbeat();

        // Bind browser network events for instant response
        if (typeof window !== 'undefined') {
            window.addEventListener('offline', () => {
                // Invalidate any in-flight heartbeat fetch immediately
                this._gen++;
                if (this._heartbeatCtrl) {
                    this._heartbeatCtrl.abort();
                    this._heartbeatCtrl = null;
                }

                const wasOnline = this.trueOnlineStatus;
                this.trueOnlineStatus = false;
                this.emit('heartbeat-done', { online: false });
                if (wasOnline !== false) {
                    console.log('[SyncEngine] Native offline event — going offline immediately');
                    this.emit('network-offline', {});
                }
            });
            window.addEventListener('online', () => {
                console.log('[SyncEngine] Native online event — running heartbeat');
                this._heartbeat();
            });
        }

        return this;
    }

    // ----------------------------------------------------------
    // Network Heartbeat
    //   Pings /ping.json to determine true server reachability.
    //   ALWAYS emits 'heartbeat-done' so the UI can update.
    //   Emits 'network-online' / 'network-offline' ONLY on transitions.
    // ----------------------------------------------------------
    async _heartbeat() {
        const myGen    = this._gen;               // Snapshot generation at start
        const wasOnline = this.trueOnlineStatus;  // null on first run, then true/false

        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            // Browser says no network adapter is connected — trust it immediately
            // (Don't need to abort; native offline event listener already did)
            if (myGen !== this._gen) return; // Race check
            this.trueOnlineStatus = false;
        } else {
            try {
                const baseUrl = (typeof window !== 'undefined' && window.NEXUS_ATTENDANCE)
                                ? window.NEXUS_ATTENDANCE.baseUrl
                                : '/NexusCore';

                const controller = new AbortController();
                this._heartbeatCtrl = controller;
                const timeoutId = setTimeout(() => controller.abort(), 3000);

                const res = await fetch(
                    baseUrl + '/public/ping.json?_t=' + Date.now(),
                    {
                        method: 'GET',
                        cache: 'no-store',
                        headers: {
                            'Cache-Control': 'no-cache',
                            'Pragma': 'no-cache',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        signal: controller.signal
                    }
                );
                clearTimeout(timeoutId);
                this._heartbeatCtrl = null;

                // RACE GUARD: if the generation changed while we were fetching,
                // the native 'offline' event fired — discard this stale result.
                if (myGen !== this._gen) {
                    console.log('[SyncEngine] Discarding stale heartbeat result (offline event fired during fetch)');
                    return;
                }

                if (!res.ok) {
                    this.trueOnlineStatus = false;
                } else {
                    // MUST parse JSON! Captive portals, routers, or antivirus interceptors 
                    // often return 200 OK HTML error pages when the target IP is unreachable.
                    const data = await res.json();
                    this.trueOnlineStatus = (data && data.status === 'ok');
                }
            } catch (err) {
                this._heartbeatCtrl = null;
                // RACE GUARD: if aborted by native offline event, don't set state here
                // (the offline event listener already set trueOnlineStatus = false)
                if (myGen !== this._gen) {
                    console.log('[SyncEngine] Heartbeat aborted by offline event — state already set');
                    return;
                }
                this.trueOnlineStatus = false;
            }
        }

        // ALWAYS emit current state so the UI can update (fixes "Checking..." stuck bug)
        this.emit('heartbeat-done', { online: this.trueOnlineStatus });

        // Emit transition events (for toasts and auto-sync)
        if (wasOnline === true && !this.trueOnlineStatus) {
            console.log('[SyncEngine] Server unreachable — going offline');
            this.emit('network-offline', {});
        } else if (wasOnline === false && this.trueOnlineStatus) {
            console.log('[SyncEngine] Server reachable — online');
            this.emit('network-online', {});
            // Auto-sync after coming online (slight delay to avoid race)
            setTimeout(() => this.syncAll(), 500);
        } else if (this.trueOnlineStatus) {
            // Already online — check for queued records
            try {
                const pending = await this.db.getPendingCount();
                if (pending > 0) {
                    this.syncAll();
                }
            } catch { /* ignore DB errors during heartbeat */ }
        }
    }

    // ----------------------------------------------------------
    // Force Offline (called by marksheet when API requests fail)
    // ----------------------------------------------------------
    forceOffline() {
        if (this.trueOnlineStatus !== false) {
            console.log('[SyncEngine] Force offline triggered');
            this.trueOnlineStatus = false;
            this.emit('heartbeat-done', { online: false });
            this.emit('network-offline', {});
        }
    }

    // ----------------------------------------------------------
    // Main Sync — process all pending records grouped by event
    // ----------------------------------------------------------
    async syncAll(force = false) {
        if (this.sessionExpired) {
            console.log('[SyncEngine] Session expired — skipping sync until refresh');
            return { synced: 0, failed: 0, skipped: true };
        }

        if (this.isSyncing) {
            console.log('[SyncEngine] Sync already in progress — skipping');
            return { synced: 0, failed: 0, skipped: true };
        }

        if (!this.trueOnlineStatus) {
            console.log('[SyncEngine] Server unreachable — cannot sync');
            return { synced: 0, failed: 0, offline: true };
        }

        let config = typeof window !== 'undefined' ? window.NEXUS_ATTENDANCE : null;
        if (!config && this.db && typeof this.db.getConfig === 'function') {
            config = await this.db.getConfig();
        }

        if (!config || !config.csrfToken) {
            console.warn('[SyncEngine] No config or CSRF token — cannot sync');
            return { synced: 0, failed: 0, error: 'no_csrf' };
        }

        this.isSyncing = true;
        this.emit('sync-start', {});

        try {
            const pending = await this.db.getPendingRecords(force);

            if (pending.length === 0) {
                this.emit('sync-complete', { synced: 0, failed: 0 });
                return { synced: 0, failed: 0 };
            }

            console.log(`[SyncEngine] Syncing ${pending.length} records`);

            // Group by symposium_event_id
            const byEvent = {};
            for (const rec of pending) {
                const eid = rec.symposium_event_id;
                if (!byEvent[eid]) byEvent[eid] = [];
                byEvent[eid].push(rec);
            }

            let totalSynced = 0;
            let totalFailed = 0;
            let hasCsrfError = false;

            for (const [eventIdStr, records] of Object.entries(byEvent)) {
                if (hasCsrfError) break;
                const eventId = parseInt(eventIdStr, 10);
                const batches = this._chunk(records, this.batchSize);

                for (const batch of batches) {
                    const result = await this._sendBatch(eventId, batch, config);
                    
                    // Mark individual records based on result
                    if (result.error === 'CSRF_ERROR') {
                        hasCsrfError = true;
                        this.sessionExpired = true;
                        this.emit('sync-error', { error: 'CSRF_ERROR' });
                        break; // Stop syncing this event
                    }

                    totalSynced += result.synced;
                    totalFailed += result.failed;
                    
                    if (result.synced === 0 && result.failed > 0) {
                        // Complete failure — mark all failed
                        for (const rec of batch) {
                            await this.db.markFailed(rec.localId || rec.id, result.failReason || 'Sync failed');
                        }
                    } else {
                        // Partial or full success
                        const failedAppIds = result.failed_apps || [];
                        for (const rec of batch) {
                            if (failedAppIds.includes(rec.application_id)) {
                                await this.db.markFailed(rec.localId || rec.id, 'Rejected by server');
                            } else {
                                await this.db.markSynced(rec.localId || rec.id);
                            }
                        }
                    }

                    this.emit('batch-complete', {
                        symposiumEventId: eventId,
                        synced: result.synced,
                        failed: result.failed,
                    });
                }
            }

            // Log to IndexedDB sync log
            await this.db.addSyncLog({
                symposium_event_id: config.symposiumEventId || null,
                records_count:      pending.length,
                succeeded:          totalSynced,
                failed:             totalFailed,
                conflicts:          0,
                status:             totalFailed === 0 ? 'completed' : 'partial',
            });

            if (!hasCsrfError) {
                this.emit('sync-complete', { synced: totalSynced, failed: totalFailed });
            }
            console.log(`[SyncEngine] Sync complete — ${totalSynced} synced, ${totalFailed} failed`);

            // Notify mark sheet UI to refresh
            if (typeof window !== 'undefined' && window.NexusAttendanceUI && typeof window.NexusAttendanceUI.onSyncComplete === 'function') {
                await window.NexusAttendanceUI.onSyncComplete({ synced: totalSynced, failed: totalFailed });
            }

            return { synced: totalSynced, failed: totalFailed };

        } catch (err) {
            console.error('[SyncEngine] Sync error:', err);
            this.emit('sync-error', { error: err.message });
            return { synced: 0, failed: 0, error: err.message };

        } finally {
            this.isSyncing = false;
        }
    }

    // ----------------------------------------------------------
    // Send a single batch to the server.
    // IMPORTANT: This method NEVER calls markSynced/markFailed.
    //            That is the responsibility of syncAll().
    // Returns: { synced, failed, failed_apps?, error?, failReason? }
    // ----------------------------------------------------------
    async _sendBatch(symposiumEventId, records, config, retryCount = 0) {
        const baseUrl  = config.baseUrl || '/NexusCore';
        const deviceId = config.deviceId || (typeof localStorage !== 'undefined' ? localStorage.getItem('nexus_device_id') : 'unknown');

        const payload = {
            csrf_token:          config.csrfToken,
            sync_token:          config.offlineSyncToken || null,
            symposium_event_id:  symposiumEventId,
            device_id:           deviceId,
            records: records.map(r => ({
                application_id:    r.application_id,
                student_id:        r.student_id,
                session_id:        r.session_id,
                attendance_status: r.attendance_status,
                coordinator_notes: r.coordinator_notes || null,
                client_timestamp:  r.captured_at,
            })),
        };

        try {
            // Use AbortController for cross-browser compatibility (AbortSignal.timeout not supported everywhere)
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000);

            const response = await fetch(`${baseUrl}/attendance/sync`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body:    JSON.stringify(payload),
                signal:  controller.signal,
            });

            clearTimeout(timeoutId);

            // Conflict
            if (response.status === 409) {
                console.warn('[SyncEngine] Conflict for event', symposiumEventId);
                return { synced: 0, failed: records.length, failReason: 'Conflict' };
            }

            // Auth / CSRF error — do NOT retry, surface immediately
            if (response.status === 403 || response.status === 401) {
                return { synced: 0, failed: records.length, error: 'CSRF_ERROR', failReason: 'Session expired' };
            }

            if (!response.ok) {
                let errMsg = `HTTP ${response.status}`;
                try {
                    const isJson = response.headers.get('content-type')?.includes('application/json');
                    if (isJson) {
                        const errorData = await response.json();
                        errMsg = errorData.message || errMsg;
                    }
                } catch (e) {}

                if (response.status >= 400 && response.status < 500) {
                    // Client error (e.g. 400 Bad Request) - do NOT retry, surface immediately
                    return { synced: 0, failed: records.length, failReason: errMsg };
                }

                // 5xx Server Error or network issue - throw so we can retry with backoff
                throw new Error(errMsg);
            }

            const data = await response.json();

            if (data.success) {
                let failReason = null;
                if (data.errors && data.errors.length > 0) {
                    failReason = data.errors.join(' | ');
                }
                return {
                    synced:      data.succeeded ?? records.length,
                    failed:      data.failed    ?? 0,
                    failed_apps: data.failed_apps || [],
                    failReason:  failReason
                };
            } else {
                return { synced: 0, failed: records.length, failReason: data.message || 'Sync rejected by server' };
            }

        } catch (err) {
            // Retry with exponential backoff
            if (retryCount < this.maxRetries && err.message !== 'CSRF_ERROR') {
                const delay = Math.min(this.baseRetryMs * Math.pow(2, retryCount), 16000);
                console.warn(`[SyncEngine] Batch failed (attempt ${retryCount + 1}/${this.maxRetries}), retrying in ${delay}ms:`, err.message);
                await this._sleep(delay);
                return this._sendBatch(symposiumEventId, records, config, retryCount + 1);
            }

            // All retries exhausted OR CSRF error — return failure (let syncAll handle record marking)
            console.error('[SyncEngine] Batch permanently failed:', err.message);
            if (err.name === 'AbortError' || err.message.includes('abort')) {
                this.forceOffline();
            }
            return { synced: 0, failed: records.length, failReason: err.message };
        }
    }

    // ----------------------------------------------------------
    // Split array into chunks
    // ----------------------------------------------------------
    _chunk(arr, size) {
        const chunks = [];
        for (let i = 0; i < arr.length; i += size) {
            chunks.push(arr.slice(i, i + size));
        }
        return chunks;
    }

    // ----------------------------------------------------------
    // Register background sync with service worker
    // ----------------------------------------------------------
    async registerBackgroundSync() {
        if (!('serviceWorker' in navigator)) return false;

        try {
            const reg = await navigator.serviceWorker.ready;

            if ('SyncManager' in window) {
                await reg.sync.register('nexuscore-attendance-sync');
                console.log('[SyncEngine] Background sync registered');
            }

            if ('periodicSync' in reg) {
                const status = await navigator.permissions.query({ name: 'periodic-background-sync' });
                if (status.state === 'granted') {
                    await reg.periodicSync.register('nexuscore-attendance-sync-periodic', {
                        minInterval: 60 * 1000,
                    });
                    console.log('[SyncEngine] Periodic background sync registered');
                }
            }
            return true;
        } catch (err) {
            console.warn('[SyncEngine] Background sync registration failed:', err);
            return false;
        }
    }

    // ----------------------------------------------------------
    // Sleep utility
    // ----------------------------------------------------------
    _sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // ----------------------------------------------------------
    // Destroy — clean up timers
    // ----------------------------------------------------------
    destroy() {
        if (this._heartbeatTimer) {
            clearInterval(this._heartbeatTimer);
            this._heartbeatTimer = null;
        }
        if (this._heartbeatCtrl) {
            this._heartbeatCtrl.abort();
            this._heartbeatCtrl = null;
        }
    }
}

// Export as global
if (typeof window !== 'undefined') {
    window.NexusSyncEngine = NexusSyncEngine;
} else if (typeof self !== 'undefined') {
    self.NexusSyncEngine = NexusSyncEngine;
}
