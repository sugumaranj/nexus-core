/**
 * ============================================================
 * NexusCore EMS — Service Worker  (v8 — Production Ready)
 * ============================================================
 * File        : public/service-worker.js
 *
 * IMPORTANT DESIGN DECISIONS:
 * ─────────────────────────────────────────────────────────
 * • The SW does NOT importScripts attendance-db.js or
 *   attendance-sync.js because those files reference `window`,
 *   `localStorage`, and `document` which do not exist in SW
 *   context, causing runtime errors.
 *
 * • Background sync is handled by MESSAGING: when the SW's
 *   sync event fires it sends a message to open page clients
 *   which then run their own NexusSyncEngine.syncAll().
 *
 * • Offline detection is done by the page via AJAX heartbeat
 *   to /ping.json — never by navigator.onLine.
 *
 * Cache Strategy
 * ─────────────────────────────────────────────────────────
 * • Static assets (CSS/JS/fonts/icons) → Cache-First
 * • PHP navigation pages                → Network-First + offline fallback
 * • API endpoints / POST requests       → Network-only (pass-through)
 * • /ping.json heartbeat                → Network-only (never cache)
 * ============================================================
 */

'use strict';

const CACHE_VERSION = 'v22';
const SHELL_CACHE   = `nexuscore-shell-${CACHE_VERSION}`;
const PAGES_CACHE   = `nexuscore-pages-${CACHE_VERSION}`;

// Static app-shell assets to pre-cache on install
const SHELL_ASSETS = [
    '/NexusCore/public/assets/css/bootstrap.min.css',
    '/NexusCore/public/assets/css/flatpickr.min.css',
    '/NexusCore/public/assets/css/variables.css',
    '/NexusCore/public/assets/css/app.css',
    '/NexusCore/public/assets/css/dashboard.css',
    '/NexusCore/public/assets/css/attendance.css',
    '/NexusCore/public/assets/icons/bootstrap-icons/font/bootstrap-icons.css',
    '/NexusCore/public/assets/js/vendor/bootstrap.bundle.min.js',
    '/NexusCore/public/assets/js/vendor/flatpickr.min.js',
    '/NexusCore/public/assets/js/modules/attendance-db.js',
    '/NexusCore/public/assets/js/modules/attendance-sync.js',
    '/NexusCore/public/assets/js/modules/attendance-ui.js',
    '/NexusCore/public/assets/js/modules/attendance-marksheet.js',
    '/NexusCore/public/assets/js/modules/attendance-overview.js',
];

// ============================================================
// INSTALL — pre-cache app shell assets
// ============================================================
self.addEventListener('install', (event) => {
    console.log('[SW v8] Installing...');
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then(cache =>
                // Use allSettled so one 404 does not abort the whole install
                Promise.allSettled(
                    SHELL_ASSETS.map(url =>
                        cache.add(url).catch(err =>
                            console.warn('[SW] Could not cache:', url, err.message)
                        )
                    )
                )
            )
            .then(() => {
                console.log('[SW v8] Install complete — skip waiting');
                return self.skipWaiting();
            })
    );
});

// ============================================================
// ACTIVATE — clean up stale caches, claim all clients
// ============================================================
self.addEventListener('activate', (event) => {
    console.log('[SW v8] Activating...');
    event.waitUntil(
        caches.keys()
            .then(keys =>
                Promise.all(
                    keys.filter(k => k !== SHELL_CACHE && k !== PAGES_CACHE)
                        .map(k => {
                            console.log('[SW] Deleting stale cache:', k);
                            return caches.delete(k);
                        })
                )
            )
            .then(() => {
                console.log('[SW v8] Activated — claiming clients');
                return self.clients.claim();
            })
    );
});

// ============================================================
// FETCH — Route interception
// ============================================================
self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);

    // Only handle http/https
    if (!req.url.startsWith('http')) return;

    // Never intercept non-GET (POST sync, etc.)
    if (req.method !== 'GET') return;

    // Never cache the heartbeat ping — always hit the network
    if (url.pathname.endsWith('/ping.json')) return;

    // Never cache the sync API
    if (url.pathname.endsWith('/attendance/sync')) return;

    // Static assets → Cache-First
    if (isStaticAsset(url)) {
        event.respondWith(cacheFirst(req));
        return;
    }

    // Navigation (HTML pages) → Network-First with offline fallback
    if (req.mode === 'navigate' || (req.headers.get('Accept') || '').includes('text/html')) {
        event.respondWith(networkFirst(req));
        return;
    }

    // Everything else — pass through
});

// ============================================================
// Helper: is this a cacheable static asset?
// ============================================================
function isStaticAsset(url) {
    return (
        url.pathname.includes('/assets/css/')     ||
        url.pathname.includes('/assets/js/')      ||
        url.pathname.includes('/assets/icons/')   ||
        url.pathname.includes('/assets/images/')  ||
        url.pathname.endsWith('.woff2')            ||
        url.pathname.endsWith('.woff')             ||
        url.pathname.endsWith('.ttf')              ||
        url.pathname.endsWith('.svg')              ||
        url.pathname.endsWith('.png')              ||
        url.pathname.endsWith('.ico')
    );
}

// ============================================================
// Cache-First Strategy (for static assets)
// ============================================================
async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response && response.status === 200 && response.type !== 'opaque') {
            const cache = await caches.open(SHELL_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('Asset unavailable offline.', {
            status: 503,
            headers: { 'Content-Type': 'text/plain' }
        });
    }
}

// ============================================================
// Network-First Strategy (for HTML pages)
// ============================================================
async function networkFirst(request) {
    try {
        // Manual timeout — AbortSignal.timeout() not supported in all SW contexts
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 8000);

        const response = await fetch(request, { signal: controller.signal });
        clearTimeout(timer);

        if (response && response.status === 200) {
            const cache = await caches.open(PAGES_CACHE);
            cache.put(request, response.clone());
        }

        return response;
    } catch {
        // Network failed — try cache
        const cached = await caches.match(request);
        if (cached) return cached;

        // No cache — return inline offline page
        return offlinePage();
    }
}

// ============================================================
// Inline Offline Fallback Page
// ============================================================
function offlinePage() {
    return new Response(`<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NexusCore — Offline</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f4f7fc;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;padding:2rem}
.card{background:#fff;border-radius:16px;padding:3rem 2rem;box-shadow:0 8px 32px rgba(0,0,0,.1);max-width:420px;width:100%}
.icon{font-size:4rem;margin-bottom:1rem}
h2{color:#1f2937;margin-bottom:.5rem;font-size:1.5rem}
p{color:#6b7280;margin-bottom:1.5rem;line-height:1.6}
.btn{background:#2563eb;color:#fff;border:none;border-radius:8px;padding:.75rem 2rem;cursor:pointer;font-size:1rem;transition:background .2s}
.btn:hover{background:#1d4ed8}
.note{margin-top:1rem;font-size:.8rem;color:#9ca3af}
</style>
</head>
<body>
<div class="card">
<div class="icon">📡</div>
<h2>You are Offline</h2>
<p>NexusCore cannot reach the server right now. If you are marking attendance, your records are saved locally and will sync automatically when you reconnect.</p>
<button class="btn" onclick="location.reload()">Try Again</button>
<div class="note">Your offline records are stored securely on this device.</div>
</div>
</body>
</html>`, {
        status: 200,
        headers: { 'Content-Type': 'text/html; charset=UTF-8' }
    });
}

// ============================================================
// Background Sync — delegates to open page via message
// ============================================================
self.addEventListener('sync', (event) => {
    if (event.tag === 'nexuscore-attendance-sync') {
        console.log('[SW v8] Background sync event fired — notifying page clients');
        event.waitUntil(notifyClientsToSync());
    }
});

self.addEventListener('periodicsync', (event) => {
    if (event.tag === 'nexuscore-attendance-sync-periodic') {
        console.log('[SW v8] Periodic sync event fired — notifying page clients');
        event.waitUntil(notifyClientsToSync());
    }
});

async function notifyClientsToSync() {
    try {
        const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        if (clients.length === 0) {
            console.log('[SW v8] No open page clients to notify.');
            return;
        }
        clients.forEach(client => {
            client.postMessage({ type: 'BACKGROUND_SYNC_TRIGGERED' });
        });
        console.log(`[SW v8] Notified ${clients.length} client(s) to sync.`);
    } catch (err) {
        console.error('[SW v8] Failed to notify clients:', err);
    }
}

// ============================================================
// Message Handling (from page → service worker)
// ============================================================
self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        console.log('[SW v8] SKIP_WAITING received — activating immediately');
        self.skipWaiting();
    }

    if (event.data?.type === 'GET_VERSION') {
        event.ports[0]?.postMessage({ version: CACHE_VERSION });
    }
});
