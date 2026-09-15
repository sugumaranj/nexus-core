/**
 * ============================================================
 * NexusCore EMS — attendance-db.js
 * ============================================================
 * File        : public/assets/js/modules/attendance-db.js
 * Description : IndexedDB abstraction layer for offline attendance.
 *               Fully migrated to Event-based (Symposium Event) workflow.
 *
 * Database    : NexusCoreAttendanceDB  (version 3)
 * Stores
 * ─────────────────────────────────────────────────────────
 * pending_records
 *   keyPath    : localId (auto-increment)
 *   indexes    : symposium_event_id, application_id, sync_status,
 *                event_app (compound)
 *
 * sync_log
 *   keyPath    : logId (auto-increment)
 *
 * participants_cache
 *   keyPath    : application_id
 *   indexes    : symposium_event_id
 *
 * ============================================================
 */

'use strict';

class NexusAttendanceDB {
    constructor() {
        this.dbName    = 'NexusCoreAttendanceDB';
        this.dbVersion = 6; // v6: fix participants_cache keyPath for team events
        this.db        = null;
    }

    // ----------------------------------------------------------
    // Open / Initialize
    // ----------------------------------------------------------
    open() {
        return new Promise((resolve, reject) => {
            if (this.db) {
                resolve(this.db);
                return;
            }

            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onupgradeneeded = (event) => {
                const db      = event.target.result;
                const oldVer  = event.oldVersion;

                // -------------------------------------------------
                // Store: pending_records
                // -------------------------------------------------
                if (!db.objectStoreNames.contains('pending_records')) {
                    const store = db.createObjectStore('pending_records', {
                        keyPath:       'localId',
                        autoIncrement: true,
                    });
                    store.createIndex('symposium_event_id', 'symposium_event_id', { unique: false });
                    store.createIndex('application_id',     'application_id',     { unique: false });
                    store.createIndex('student_id',         'student_id',         { unique: false });
                    store.createIndex('sync_status',        'sync_status',        { unique: false });
                    // Compound index: event + student (unique per participant row, even for team events)
                    store.createIndex('event_student', ['symposium_event_id', 'student_id'], { unique: false });
                    store.createIndex('event_app', ['symposium_event_id', 'application_id'], { unique: false });
                } else if (oldVer < 3) {
                    // Upgrade existing store from v1/v2 to v3
                    const tx    = event.target.transaction;
                    const store = tx.objectStore('pending_records');

                    // Remove old competition_id indexes if they exist
                    if (store.indexNames.contains('competition_id'))  store.deleteIndex('competition_id');
                    if (store.indexNames.contains('comp_app'))        store.deleteIndex('comp_app');

                    // Add new event-based indexes
                    if (!store.indexNames.contains('symposium_event_id')) {
                        store.createIndex('symposium_event_id', 'symposium_event_id', { unique: false });
                    }
                    if (!store.indexNames.contains('event_app')) {
                        store.createIndex('event_app', ['symposium_event_id', 'application_id'], { unique: false });
                    }
                    if (!store.indexNames.contains('student_id')) {
                        store.createIndex('student_id', 'student_id', { unique: false });
                    }
                    if (!store.indexNames.contains('event_student')) {
                        store.createIndex('event_student', ['symposium_event_id', 'student_id'], { unique: false });
                    }
                }

                // -------------------------------------------------
                // Store: sync_log
                // -------------------------------------------------
                if (!db.objectStoreNames.contains('sync_log')) {
                    const logStore = db.createObjectStore('sync_log', {
                        keyPath:       'logId',
                        autoIncrement: true,
                    });
                    logStore.createIndex('symposium_event_id', 'symposium_event_id', { unique: false });
                    logStore.createIndex('logged_at',          'logged_at',          { unique: false });
                } else if (oldVer < 3) {
                    const tx       = event.target.transaction;
                    const logStore = tx.objectStore('sync_log');
                    if (logStore.indexNames.contains('competition_id')) {
                        logStore.deleteIndex('competition_id');
                    }
                    if (!logStore.indexNames.contains('symposium_event_id')) {
                        logStore.createIndex('symposium_event_id', 'symposium_event_id', { unique: false });
                    }
                }

                // -------------------------------------------------
                // Store: participants_cache
                // -------------------------------------------------
                // IMPORTANT: keyPath must be compound [symposium_event_id, student_id].
                // Team events share a single application_id across all team members, so using
                // application_id as keyPath caused team members to silently overwrite each other
                // in the cache (only 1 of N members would survive).
                if (!db.objectStoreNames.contains('participants_cache')) {
                    const cacheStore = db.createObjectStore('participants_cache', {
                        keyPath: ['symposium_event_id', 'student_id']
                    });
                    cacheStore.createIndex('symposium_event_id', 'symposium_event_id', { unique: false });
                } else if (oldVer < 6) {
                    // v6: drop and recreate participants_cache with compound keyPath
                    // (The old single-field keyPath 'application_id' breaks for team events)
                    if (db.objectStoreNames.contains('participants_cache')) {
                        db.deleteObjectStore('participants_cache');
                    }
                    const cacheStore = db.createObjectStore('participants_cache', {
                        keyPath: ['symposium_event_id', 'student_id']
                    });
                    cacheStore.createIndex('symposium_event_id', 'symposium_event_id', { unique: false });
                }

                // -------------------------------------------------
                // Store: sync_config (added in v4, enforced in v5)
                // -------------------------------------------------
                if (!db.objectStoreNames.contains('sync_config')) {
                    db.createObjectStore('sync_config', { keyPath: 'key' });
                }
            };

            request.onsuccess = (event) => {
                this.db = event.target.result;
                console.log('[AttendanceDB] Opened:', this.dbName, 'v' + this.dbVersion);
                
                // Handle versionchange event in case another tab upgrades the DB later
                this.db.onversionchange = () => {
                    this.db.close();
                    console.warn('[AttendanceDB] Database upgraded in another tab. Please refresh.');
                };
                
                resolve(this.db);
            };

            request.onerror = (event) => {
                console.error('[AttendanceDB] Open error:', event.target.error);
                reject(event.target.error);
            };

            request.onblocked = (event) => {
                console.error('[AttendanceDB] Upgrade blocked by another tab.');
                if (typeof window !== 'undefined' && window.nexusUI?.showToast) {
                    window.nexusUI.showToast('Please close other NexusCore tabs to update the app, then refresh this page.', 'warning', 10000);
                } else {
                    alert('Please close other NexusCore tabs to update the app, then refresh this page.');
                }
                reject(new Error('IndexedDB upgrade blocked by another tab.'));
            };
        });
    }

    // ----------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------
    _tx(storeName, mode = 'readonly') {
        return this.db.transaction([storeName], mode);
    }

    _store(storeName, mode = 'readonly') {
        return this._tx(storeName, mode).objectStore(storeName);
    }

    _promisify(request) {
        return new Promise((resolve, reject) => {
            request.onsuccess = (e) => resolve(e.target.result);
            request.onerror   = (e) => reject(e.target.error);
        });
    }

    // ----------------------------------------------------------
    // Save a single pending attendance record
    // ----------------------------------------------------------
    async savePendingRecord(record) {
        // 1. Find existing record to deduplicate offline marks.
        //
        // IMPORTANT: For team events, multiple participants share the SAME application_id.
        // Deduplication MUST be keyed by student_id (which is always unique per participant
        // row) to prevent one team member's offline mark from silently overwriting another's.
        let existing = null;
        if (record.student_id && record.student_id > 0 &&
            record.attendance_status !== 'OPEN_SESSION' &&
            record.attendance_status !== 'CLOSE_SESSION') {

            const readTx    = this._tx('pending_records', 'readonly');
            const readStore = readTx.objectStore('pending_records');
            const allRecords = await this._promisify(readStore.getAll());

            existing = allRecords.find(r =>
                r.student_id         === record.student_id &&
                r.symposium_event_id === record.symposium_event_id &&
                r.session_id         === record.session_id &&
                r.attendance_status  !== 'CLOSE_SESSION' &&
                r.attendance_status  !== 'OPEN_SESSION' &&
                r.sync_status        !== 'synced'
            );
        }

        // 2. Write record
        const writeTx = this._tx('pending_records', 'readwrite');
        const writeStore = writeTx.objectStore('pending_records');

        if (existing) {
            existing.attendance_status = record.attendance_status;
            existing.coordinator_notes = record.coordinator_notes || existing.coordinator_notes;
            existing.captured_at = record.captured_at || new Date().toISOString();
            existing.sync_status = 'pending';
            existing.retry_count = 0;
            existing.last_error = null;
            // DO NOT update existing.old_status! Keep the original server state to correctly reverse it on the dashboard.
            return this._promisify(writeStore.put(existing));
        }

        const data = {
            symposium_event_id: record.symposium_event_id,
            session_id:         record.session_id,
            application_id:     record.application_id,
            student_id:         record.student_id || 0,
            attendance_status:  record.attendance_status,
            old_status:         record.old_status || 'Unmarked', // Store old_status for accurate Dashboard stats
            coordinator_notes:  record.coordinator_notes || null,
            captured_at:        record.captured_at || new Date().toISOString(),
            client_device_id:   record.client_device_id || this._getDeviceId(),
            sync_status:        'pending',   // 'pending' | 'synced' | 'failed' | 'conflict'
            retry_count:        0,
            last_error:         null,
        };

        return this._promisify(writeStore.add(data));
    }

    // ----------------------------------------------------------
    // Get all pending records (sync_status === 'pending' OR 'failed')
    // ----------------------------------------------------------
    async getPendingRecords(includeFailed = false) {
        const all = await this._getAllRecords();
        return all.filter(r => r.sync_status === 'pending' || (includeFailed && r.sync_status === 'failed'));
    }

    // ----------------------------------------------------------
    // Get ALL records (for Sync Center display)
    // ----------------------------------------------------------
    async _getAllRecords() {
        return this._promisify(
            this._store('pending_records').getAll()
        );
    }

    // ----------------------------------------------------------
    // Get count of pending (unsynced) records
    // ----------------------------------------------------------
    async getPendingCount() {
        const all = await this.getPendingRecords(true);
        return all.length;
    }

    // ----------------------------------------------------------
    // Mark a record as synced
    // ----------------------------------------------------------
    async markSynced(localId) {
        const tx    = this._tx('pending_records', 'readwrite');
        const store = tx.objectStore('pending_records');

        return new Promise((resolve, reject) => {
            const getReq = store.get(localId);
            getReq.onsuccess = (e) => {
                const record = e.target.result;
                if (!record) { resolve(false); return; }
                record.sync_status = 'synced';
                record.synced_at   = new Date().toISOString();
                const putReq = store.put(record);
                putReq.onsuccess = () => resolve(true);
                putReq.onerror   = (e) => reject(e.target.error);
            };
            getReq.onerror = (e) => reject(e.target.error);
        });
    }

    // ----------------------------------------------------------
    // Mark a record as failed
    // ----------------------------------------------------------
    async markFailed(localId, errorMessage = '') {
        const tx    = this._tx('pending_records', 'readwrite');
        const store = tx.objectStore('pending_records');

        return new Promise((resolve, reject) => {
            const getReq = store.get(localId);
            getReq.onsuccess = (e) => {
                const record = e.target.result;
                if (!record) { resolve(false); return; }
                record.retry_count  = (record.retry_count || 0) + 1;
                record.sync_status  = record.retry_count >= 5 ? 'dead' : 'failed';
                record.last_error   = errorMessage;
                const putReq = store.put(record);
                putReq.onsuccess = () => resolve(true);
                putReq.onerror   = (e) => reject(e.target.error);
            };
            getReq.onerror = (e) => reject(e.target.error);
        });
    }

    // ----------------------------------------------------------
    // Clear all synced records (cleanup)
    // ----------------------------------------------------------
    async clearSynced() {
        const all    = await this._getAllRecords();
        const synced = all.filter(r => r.sync_status === 'synced');
        const store  = this._store('pending_records', 'readwrite');

        for (const rec of synced) {
            await this._promisify(store.delete(rec.localId));
        }

        return synced.length;
    }

    // ----------------------------------------------------------
    // Add a sync log entry
    // ----------------------------------------------------------
    async addSyncLog(entry) {
        const data = {
            symposium_event_id: entry.symposium_event_id || null,
            records_count:      entry.records_count,
            succeeded:          entry.succeeded,
            failed:             entry.failed,
            conflicts:          entry.conflicts,
            status:             entry.status,
            logged_at:          new Date().toISOString(),
        };
        return this._promisify(
            this._store('sync_log', 'readwrite').add(data)
        );
    }

    // ----------------------------------------------------------
    // Get all sync log entries
    // ----------------------------------------------------------
    async getSyncLog(limit = 20) {
        const all = await this._promisify(
            this._store('sync_log').getAll()
        );
        return all.slice(-limit).reverse();
    }

    // ----------------------------------------------------------
    // Save participants cache for offline viewing
    // ----------------------------------------------------------
    async saveParticipantsCache(participants) {
        return new Promise((resolve, reject) => {
            const tx    = this._tx('participants_cache', 'readwrite');
            const store = tx.objectStore('participants_cache');
            for (const p of participants) {
                store.put(p);
            }
            tx.oncomplete = () => resolve();
            tx.onerror    = (e) => reject(e.target.error);
        });
    }

    // ----------------------------------------------------------
    // Get participants cache by symposium event ID
    // ----------------------------------------------------------
    async getParticipantsCache(symposiumEventId) {
        return new Promise((resolve, reject) => {
            const tx    = this._tx('participants_cache', 'readonly');
            const store = tx.objectStore('participants_cache');
            const index = store.index('symposium_event_id');
            const req   = index.getAll(IDBKeyRange.only(symposiumEventId));
            req.onsuccess = (e) => resolve(e.target.result || []);
            req.onerror   = (e) => reject(e.target.error);
        });
    }

    // ----------------------------------------------------------
    // Get or generate a stable device ID for this browser
    // ----------------------------------------------------------
    _getDeviceId() {
        if (typeof localStorage === 'undefined') {
            return 'dev-sw-unknown';
        }
        let id = localStorage.getItem('nexus_device_id');
        if (!id) {
            id = 'dev-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('nexus_device_id', id);
        }
        return id;
    }

    // ----------------------------------------------------------
    // Save Config for Service Worker
    // ----------------------------------------------------------
    async saveConfig(config) {
        const tx = this._tx('sync_config', 'readwrite');
        const store = tx.objectStore('sync_config');
        const req = store.put({ key: 'app_config', value: config });
        return this._promisify(req);
    }

    // ----------------------------------------------------------
    // Get Config for Service Worker
    // ----------------------------------------------------------
    async getConfig() {
        return new Promise((resolve, reject) => {
            const tx = this._tx('sync_config', 'readonly');
            const store = tx.objectStore('sync_config');
            const req = store.get('app_config');
            req.onsuccess = (e) => resolve(e.target.result ? e.target.result.value : null);
            req.onerror = (e) => reject(e.target.error);
        });
    }
}

// Export as global for use in other modules or workers
if (typeof window !== 'undefined') {
    window.NexusAttendanceDB = NexusAttendanceDB;
} else if (typeof self !== 'undefined') {
    self.NexusAttendanceDB = NexusAttendanceDB;
}
