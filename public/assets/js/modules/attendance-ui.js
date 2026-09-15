/**
 * ============================================================
 * NexusCore EMS — attendance-ui.js  (Production v2)
 * ============================================================
 * File        : public/assets/js/modules/attendance-ui.js
 * Description : UI coordination — network status bar, badge
 *               updates, toast notifications, PWA install.
 *
 * This module runs on ALL dashboard pages that load it
 * (via the dashboard layout). It manages:
 *   • Service Worker registration + update detection
 *   • Network status bar rendering
 *   • Pending badge count
 *   • Toast notification system
 *   • PWA install prompt
 *
 * ============================================================
 */

'use strict';

// ============================================================
// Service Worker Registration
// ============================================================
async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        console.warn('[PWA] Service Workers not supported in this browser.');
        return;
    }

    try {
        const reg = await navigator.serviceWorker.register(
            '/NexusCore/public/service-worker.js',
            { scope: '/NexusCore/' }
        );

        console.log('[PWA] Service Worker registered. Scope:', reg.scope);

        // Update detection
        reg.addEventListener('updatefound', () => {
            const newWorker = reg.installing;
            newWorker?.addEventListener('statechange', () => {
                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                    console.log('[PWA] New Service Worker available.');
                    showUpdateToast();
                }
            });
        });

    } catch (err) {
        console.error('[PWA] Service Worker registration failed:', err);
    }
}

// ============================================================
// Network Status Bar
// ============================================================
function updateNetworkStatusBar(isOnline, isSyncing = false, pendingCount = 0) {
    const bar        = document.getElementById('network-status-bar');
    const icon       = document.getElementById('network-icon');
    const text       = document.getElementById('network-text');
    const badgeBar   = document.getElementById('pending-badge-bar');
    const countEl    = document.getElementById('pending-count-bar');
    const syncBtn    = document.getElementById('manual-sync-btn');
    const syncText   = document.getElementById('sync-status-text');

    if (!bar) return;

    // Remove all state classes then add the correct one
    bar.classList.remove('network-online', 'network-offline', 'network-syncing', 'network-checking');
    bar.classList.add(
        isSyncing ? 'network-syncing' : (isOnline ? 'network-online' : 'network-offline')
    );

    if (icon) {
        icon.className = 'bi me-1 ' +
            (isSyncing ? 'bi-arrow-repeat spin' : (isOnline ? 'bi-wifi' : 'bi-wifi-off'));
    }

    if (text) {
        text.textContent = isSyncing ? 'Syncing…' : (isOnline ? 'Online' : 'Offline');
    }

    if (syncText) {
        syncText.style.display = isSyncing ? '' : 'none';
    }

    if (pendingCount > 0 && badgeBar) {
        badgeBar.style.display = '';
        if (countEl) countEl.textContent = pendingCount;
    } else if (badgeBar) {
        badgeBar.style.display = 'none';
    }

    if (syncBtn) {
        syncBtn.style.display = (pendingCount > 0 && isOnline && !isSyncing) ? '' : 'none';
    }
}

// ============================================================
// Pending Count Updater (reads from IndexedDB)
// ============================================================
async function refreshPendingCount(db) {
    try {
        const count = await db.getPendingCount();

        // Update all badge elements
        document.querySelectorAll('.nexus-pending-badge').forEach(el => {
            el.textContent = count;
            el.closest('[data-pending-wrapper]')?.style.setProperty(
                'display',
                count > 0 ? '' : 'none'
            );
        });

        return count;
    } catch {
        return 0;
    }
}

// ============================================================
// Toast Notification System
// ============================================================
function showToast(message, type = 'info', duration = 4000) {
    // Create container if needed
    let container = document.getElementById('nexus-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'nexus-toast-container';
        container.style.cssText = `
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        `;
        document.body.appendChild(container);
    }

    const colors = {
        success : { bg: '#16a34a', icon: 'bi-check-circle-fill' },
        error   : { bg: '#dc2626', icon: 'bi-x-circle-fill' },
        warning : { bg: '#f59e0b', icon: 'bi-exclamation-triangle-fill' },
        info    : { bg: '#2563eb', icon: 'bi-info-circle-fill' },
        sync    : { bg: '#7c3aed', icon: 'bi-arrow-repeat' },
    };

    const style = colors[type] || colors.info;

    const toast = document.createElement('div');
    toast.style.cssText = `
        background: ${style.bg};
        color: white;
        padding: 0.75rem 1rem;
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        box-shadow: 0 4px 14px rgba(0,0,0,0.2);
        transform: translateX(120%);
        transition: transform 0.3s ease;
        max-width: 320px;
    `;

    toast.innerHTML = `<i class="bi ${style.icon}"></i><span>${message}</span>`;
    container.appendChild(toast);

    // Slide in
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(0)';
        });
    });

    // Auto-dismiss
    setTimeout(() => {
        toast.style.transform = 'translateX(120%)';
        toast.addEventListener('transitionend', () => toast.remove());
    }, duration);
}

// ============================================================
// Update available toast
// ============================================================
function showUpdateToast() {
    const container = document.getElementById('nexus-toast-container') ||
        (() => {
            const el = document.createElement('div');
            el.id = 'nexus-toast-container';
            el.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;';
            document.body.appendChild(el);
            return el;
        })();

    const toast = document.createElement('div');
    toast.style.cssText = `
        background: #1f2937;
        color: white;
        padding: 1rem;
        border-radius: 10px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.3);
        max-width: 280px;
        font-size: 0.875rem;
    `;
    toast.innerHTML = `
        <div class="mb-2"><strong>Update Available</strong></div>
        <div class="text-muted" style="font-size:0.8rem;margin-bottom:0.75rem;">
            A new version of NexusCore is ready.
        </div>
        <button onclick="location.reload()" style="background:#2563eb;color:white;border:none;border-radius:6px;padding:0.4rem 0.8rem;cursor:pointer;font-size:0.8rem;">
            Refresh Now
        </button>
    `;
    container.appendChild(toast);
}

// ============================================================
// PWA Install Prompt
// ============================================================
let deferredInstallPrompt = null;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;

    // Show install button if present on page
    const installBtn = document.getElementById('pwa-install-btn');
    if (installBtn) {
        installBtn.style.display = '';
        installBtn.addEventListener('click', async () => {
            if (!deferredInstallPrompt) return;
            deferredInstallPrompt.prompt();
            const { outcome } = await deferredInstallPrompt.userChoice;
            console.log('[PWA] Install outcome:', outcome);
            deferredInstallPrompt = null;
            installBtn.style.display = 'none';
        });
    }
});

// ============================================================
// Initialize UI Module
// ============================================================
async function initAttendanceUI() {
    // Register service worker
    registerServiceWorker();

    // Initialize IndexedDB
    let db = null;
    let syncEngine = null;

    try {
        if (window.NexusAttendanceDB) {
            db = new NexusAttendanceDB();
            await db.open();

            // Save config for Service Worker background sync
            if (window.NEXUS_ATTENDANCE) {
                const configToSave = { ...window.NEXUS_ATTENDANCE };
                configToSave.deviceId = localStorage.getItem('nexus_device_id') || db._getDeviceId();
                await db.saveConfig(configToSave);
            }

            // Initialize sync engine
            if (window.NexusSyncEngine) {
                syncEngine = new NexusSyncEngine(db);

                syncEngine
                    .on('heartbeat-done', async ({ online }) => {
                        const count = await refreshPendingCount(db);
                        if (!syncEngine.isSyncing) {
                            updateNetworkStatusBar(online, false, count);
                        }
                    })
                    .on('sync-start', () => {
                        updateNetworkStatusBar(true, true);
                    })
                    .on('sync-complete', async ({ synced, failed }) => {
                        const count = await refreshPendingCount(db);
                        updateNetworkStatusBar(syncEngine.trueOnlineStatus, false, count);
                        if (synced > 0) {
                            showToast(`${synced} attendance record${synced !== 1 ? 's' : ''} synced.`, 'sync');
                        }
                        if (failed > 0) {
                            showToast(`${failed} record${failed !== 1 ? 's' : ''} failed to sync.`, 'error');
                        }
                    })
                    .on('sync-error', ({ error }) => {
                        updateNetworkStatusBar(syncEngine.trueOnlineStatus, false);
                        if (error === 'CSRF_ERROR') {
                            showToast('Session expired. Please refresh the page to sync.', 'error', 7000);
                        } else {
                            showToast('Sync error: ' + error, 'error');
                        }
                    })
                    .on('network-online', () => {
                        showToast('Back online!', 'success', 2000);
                    })
                    .on('network-offline', () => {
                        showToast('You are offline. Attendance will be saved locally.', 'warning', 5000);
                    });

                // Start the engine
                syncEngine.init();
            }

            const manualSyncBtn = document.getElementById('manual-sync-btn');
            manualSyncBtn?.addEventListener('click', async () => {
                if (syncEngine) await syncEngine.syncAll(true);
            });
        }
    } catch (err) {
        console.error('[AttendanceUI] Initialization failed:', err);
    }

    if (!syncEngine) {
        updateNetworkStatusBar(navigator.onLine);
        window.addEventListener('online',  () => updateNetworkStatusBar(true));
        window.addEventListener('offline', () => updateNetworkStatusBar(false));
    }

    // ALWAYS expose globally, even if db is null
    window.nexusUI = {
        db,
        syncEngine,
        showToast,
        refreshPendingCount: () => refreshPendingCount(db),
    };
    
    // Dispatch an event so listeners don't have to poll
    window.dispatchEvent(new Event('nexusUIReady'));
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAttendanceUI);
} else {
    initAttendanceUI();
}
