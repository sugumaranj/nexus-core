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
        $base = rtrim(config('base_url'), '/');
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
        return $assetBase . '/' . ltrim($path, '/');
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