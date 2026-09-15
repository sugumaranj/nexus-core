<?php

declare(strict_types=1);

/**
 * ---------------------------------------------------------
 * NexusCore
 * ---------------------------------------------------------
 * File        : Session.php
 * Description : Centralized session management.
 *
 * Project     : NexusCore
 * ---------------------------------------------------------
 */

namespace App\Core;

final class Session
{
    /**
     * Start a session if it is not already active.
     *
     * @return void
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Store a value in the session.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Retrieve a value from the session.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check whether a session key exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a value from the session.
     *
     * @param string $key
     *
     * @return void
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

        /**
     * -------------------------------------------------------------------------
     * Regenerate the session ID.
     *
     * This helps prevent session fixation attacks after a successful login.
     *
     * @return void
     * -------------------------------------------------------------------------
     */
    public static function regenerate(): void
    {
        self::start();

        session_regenerate_id(true);
    }

    /**
     * -------------------------------------------------------------------------
     * Store a flash message.
     *
     * Flash messages exist for only one request.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     * -------------------------------------------------------------------------
     */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * -------------------------------------------------------------------------
     * Retrieve and remove a flash message.
     *
     * @param string $key
     *
     * @return mixed
     * -------------------------------------------------------------------------
     */
    public static function getFlash(string $key): mixed
    {
        if (!isset($_SESSION['_flash'][$key])) {
            return null;
        }

        $value = $_SESSION['_flash'][$key];

        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    /**
     * Destroy the current session.
     *
     * @return void
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();

                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            session_destroy();
        }
    }
}