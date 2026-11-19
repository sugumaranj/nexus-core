<?php

declare(strict_types=1);

/**
 * ---------------------------------------------------------
 * NexusCore
 * URL Helper
 * ---------------------------------------------------------
 * Contains helper functions used across the application.
 * ---------------------------------------------------------
 */

if (!function_exists('config')) {

    /**
     * Read configuration values.
     *
     * Example:
     * config('base_url')
     *
     * @param string $key
     * @return mixed
     */
    function config(string $key): mixed
    {
        static $config = null;

        if ($config === null) {
            $config = require dirname(__DIR__, 2) . '/config/app.php';
        }

        return $config[$key] ?? null;
    }
}

if (!function_exists('base_url')) {

    /**
     * Returns the application base URL, optionally with a path appended.
     *
     * @param string $path  Optional path to append (e.g. '/notice-board?id=1')
     * @return string
     */
    function base_url(string $path = ''): string
    {
        $configured = config('base_url');
        $base = rtrim(is_string($configured) ? $configured : '', '/');
        if ($path !== '') {
            return $base . '/' . ltrim($path, '/');
        }
        return $base;
    }
}

if (!function_exists('asset')) {

    /**
     * Generate an asset URL.
     *
     * Example:
     * asset('assets/css/app.css')
     *
     * @param string $path
     * @return string
     */
    function asset(string $path): string
    {
        $assetBase = rtrim(config('asset_url') ?? config('base_url'), '/');
        $cleanPath = ltrim($path, '/');
        $url = $assetBase . '/' . $cleanPath;

        // Auto cache busting: append ?v=<filemtime> if local asset exists
        $filePathPart = explode('?', $cleanPath)[0];
        $localFile = dirname(__DIR__, 2) . '/public/' . $filePathPart;
        if (file_exists($localFile) && !is_dir($localFile)) {
            $mtime = filemtime($localFile);
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . 'v=' . $mtime;
        }

        return $url;
    }
}

if (!function_exists('college_name')) {

    /**
     * Returns the official college name from config.
     *
     * Example:
     *   college_name()  →  "Government Arts and Science College"
     *
     * @return string
     */
    function college_name(): string
    {
        return (string) (config('college_name') ?? 'Government Arts and Science College');
    }
}

if (!function_exists('college_address')) {

    /**
     * Returns the college address from config.
     *
     * Example:
     *   college_address()  →  "Veerapandi, Theni District"
     *
     * @return string
     */
    function college_address(): string
    {
        return (string) (config('college_address') ?? 'Veerapandi, Theni District');
    }
}

if (!function_exists('college_event')) {

    /**
     * Returns the college's flagship event name from config.
     *
     * Example:
     *   college_event()  →  "Nexus — Intra Department Symposium"
     *
     * @return string
     */
    function college_event(): string
    {
        return (string) (config('college_event') ?? 'Nexus — Intra Department Symposium');
    }
}

if (!function_exists('college_logo')) {
    function college_logo(): ?string
    {
        static $collegeLogo = null;
        static $loaded = false;

        if (!$loaded) {
            $settingModel = new \App\Models\SystemSettingModel();
            $collegeLogo = $settingModel->getValue('COLLEGE_LOGO');
            $loaded = true;
        }

        return $collegeLogo ? asset($collegeLogo) : null;
    }
}

if (!function_exists('event_logo')) {
    function event_logo(): ?string
    {
        static $eventLogo = null;
        static $loaded = false;

        if (!$loaded) {
            $settingModel = new \App\Models\SystemSettingModel();
            $eventLogo = $settingModel->getValue('EVENT_LOGO');
            $loaded = true;
        }

        return $eventLogo ? asset($eventLogo) : null;
    }
}


if (!function_exists('form_idempotency_field')) {
    /**
     * Generate an idempotency token hidden input field for forms.
     * Prevents duplicate form submissions and network replays.
     *
     * @return string
     */
    function form_idempotency_field(): string
    {
        \App\Core\Session::start();
        $token = bin2hex(random_bytes(16));
        return '<input type="hidden" name="form_idempotency_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_form_idempotency_token')) {
    /**
     * Verify and consume an idempotency token.
     * Returns false if token has already been processed within the TTL window.
     *
     * @param string|null $token
     * @param int         $ttlSeconds Default: 300 (5 minutes)
     * @return bool
     */
    function verify_form_idempotency_token(?string $token, int $ttlSeconds = 300): bool
    {
        if (empty($token)) {
            return true; // Backward compatibility for un-migrated forms or testing
        }

        \App\Core\Session::start();
        $now = time();
        $processed = \App\Core\Session::get('_form_tokens', []);

        // Prune expired tokens
        $clean = [];
        if (is_array($processed)) {
            foreach ($processed as $t => $time) {
                if (($now - (int)$time) < $ttlSeconds) {
                    $clean[$t] = $time;
                }
            }
        }

        if (isset($clean[$token])) {
            \App\Core\Session::set('_form_tokens', $clean);
            return false; // Replay / duplicate detected!
        }

        $clean[$token] = $now;
        \App\Core\Session::set('_form_tokens', $clean);
        return true;
    }
}
