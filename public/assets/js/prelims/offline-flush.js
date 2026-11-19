/**
 * NexusCore Prelims — Cross-Page Offline Submission Auto-Flusher
 * Checks localStorage for any locked offline quiz submissions (`nx_prelims_pending_*`)
 * and automatically uploads them to /student/prelims/submit as soon as online.
 */
(function () {
    'use strict';

    async function flushAllPendingSubmissions() {
        if (navigator.onLine === false) return;
        var metaCsrf = document.querySelector('meta[name="csrf-token"]');
        var metaBase = document.querySelector('meta[name="base-url"]');
        var csrf = metaCsrf ? metaCsrf.getAttribute('content') : '';
        var baseUrl = (metaBase ? metaBase.getAttribute('content') : '').replace(/\/$/, '');
        if (!csrf || !baseUrl) return;

        var keys = [];
        try {
            for (var i = 0; i < localStorage.length; i++) {
                var k = localStorage.key(i);
                if (k && k.indexOf('nx_prelims_pending_') === 0) {
                    keys.push(k);
                }
            }
        } catch (e) {
            return;
        }

        if (!keys.length) return;

        var syncedAny = false;
        for (var idx = 0; idx < keys.length; idx++) {
            var key = keys[idx];
            var bundle = null;
            try { bundle = JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) { continue; }
            if (!bundle || !bundle.aid || !bundle.qid) continue;

            try {
                var res = await fetch(baseUrl + '/student/prelims/submit', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        c: csrf,
                        aid: bundle.aid,
                        qid: bundle.qid,
                        r: bundle.r || 'Submitted',
                        la: bundle.la || null,
                        a: bundle.a || [],
                        v: bundle.v || []
                    })
                });
                var j = await res.json();
                if (j && j.success) {
                    localStorage.removeItem(key);
                    localStorage.removeItem('nx_prelims_a_' + bundle.aid);
                    syncedAny = true;
                }
            } catch (e) {
                // Keep in localStorage for next online retry
            }
        }

        if (syncedAny) {
            var banner = document.createElement('div');
            banner.className = 'alert alert-success shadow-sm m-3';
            banner.innerHTML = '<strong>Offline Exam Synced!</strong> Your locked offline prelims submission has been automatically uploaded and graded.';
            var main = document.querySelector('main') || document.body;
            main.insertBefore(banner, main.firstChild);
            setTimeout(function () { window.location.reload(); }, 1800);
        }
    }

    document.addEventListener('DOMContentLoaded', flushAllPendingSubmissions);
    window.addEventListener('online', flushAllPendingSubmissions);
})();
