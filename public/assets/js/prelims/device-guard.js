/**
 * NexusCore Prelims — Hardware Device Guard (Desktop-Only Enforcement)
 * Detects mobile phones & tablets EVEN when "Request Desktop Site" is enabled.
 * Zero external dependencies.
 */
(function (global) {
    'use strict';

    function getWebGLRenderer() {
        try {
            var canvas = document.createElement('canvas');
            var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (!gl) return '';
            var ext = gl.getExtension('WEBGL_debug_renderer_info');
            return ext ? String(gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) || '') : '';
        } catch (e) {
            return '';
        }
    }

    function collectDeviceSignals() {
        var mm = function (q) {
            try { return !!(window.matchMedia && window.matchMedia(q).matches); }
            catch (e) { return false; }
        };
        var uad = navigator.userAgentData || null;
        var tz = '';
        try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}

        return {
            ua_data_mobile: !!(uad && uad.mobile),
            ua_data_platform: uad && uad.platform ? String(uad.platform) : '',
            platform: String(navigator.platform || ''),
            max_touch_points: Number(navigator.maxTouchPoints || 0),
            pointer_coarse: mm('(pointer: coarse)'),
            hover_none: mm('(hover: none)'),
            any_pointer_fine: mm('(any-pointer: fine)'),
            any_hover_hover: mm('(any-hover: hover)'),
            screen_width: Number(window.screen ? window.screen.width : 0),
            screen_height: Number(window.screen ? window.screen.height : 0),
            dpr: Number(window.devicePixelRatio || 1),
            webgl_renderer: getWebGLRenderer(),
            mobile_orientation_api: (typeof window.orientation !== 'undefined'),
            timezone: tz
        };
    }

    function evaluateLocally(s) {
        var reasons = [];
        var score = 0;

        if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|Tablet|CriOS|FxiOS/i.test(navigator.userAgent || '')) {
            score += 100;
            reasons.push('Mobile User-Agent detected.');
        }
        if (s.ua_data_mobile || /^(Android|iOS|iPadOS)$/i.test(s.ua_data_platform)) {
            score += 100;
            reasons.push('Mobile OS reported by browser Client Hints.');
        }
        if (/Adreno|Mali|PowerVR|Apple\s+GPU|Immortalis|Xclipse|Tegra|SGX|Vivante/i.test(s.webgl_renderer)) {
            score += 90;
            reasons.push('Mobile GPU hardware detected.');
        }
        if (s.pointer_coarse && s.hover_none && !s.any_pointer_fine) {
            score += 90;
            reasons.push('Touch-only mobile input detected (no precision mouse/trackpad).');
        } else if (s.max_touch_points > 0 && !s.any_pointer_fine && !s.any_hover_hover) {
            score += 85;
            reasons.push('Touch-only mobile display detected.');
        } else if (s.pointer_coarse && s.hover_none) {
            score += 60;
            reasons.push('Coarse pointer with no hover support detected.');
        }

        var minDim = Math.min(s.screen_width, s.screen_height);
        var maxDim = Math.max(s.screen_width, s.screen_height);
        if (minDim > 0 && minDim <= 520 && maxDim <= 1024) {
            score += 70;
            reasons.push('Mobile screen dimensions detected.');
        }
        if (/Linux armv|Linux aarch64/i.test(s.platform) && s.max_touch_points > 0 && !s.any_pointer_fine) {
            score += 80;
            reasons.push('Mobile ARM Linux in Desktop Site mode detected.');
        }
        if (/MacIntel/i.test(s.platform) && s.max_touch_points > 1 && !s.any_pointer_fine) {
            score += 85;
            reasons.push('iOS/iPadOS Desktop Site mode detected.');
        }

        return {
            allowed: score < 60,
            score: score,
            reasons: reasons,
            signals: s
        };
    }

    global.NexusDeviceGuard = {
        collect: collectDeviceSignals,
        evaluate: function () { return evaluateLocally(collectDeviceSignals()); }
    };
})(window);
