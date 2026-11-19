<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS
 * =========================================================================
 * File        : Cache.php
 * Location    : app/Core/
 * Description : Unified zero-dependency native caching service.
 *
 * Automatically detects and utilizes APCu in-memory cache when enabled,
 * seamlessly falling back to the local `app_cache` MySQL table without
 * requiring Redis, Memcached, or external services.
 * =========================================================================
 */

namespace App\Core;

use App\Database\Database;
use PDO;
use Throwable;

final class Cache
{
    private static ?bool $apcuEnabled = null;

    /**
     * Determine whether native APCu in-memory caching is active.
     */
    public static function isApcuAvailable(): bool
    {
        if (self::$apcuEnabled === null) {
            self::$apcuEnabled = function_exists('apcu_fetch') 
                && filter_var(ini_get('apc.enabled'), FILTER_VALIDATE_BOOLEAN);
        }
        return self::$apcuEnabled;
    }

    /**
     * Retrieve an item from the cache.
     *
     * @param string $key
     * @param mixed  $default Default value if key is not found or expired
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::isApcuAvailable()) {
            $success = false;
            $val = apcu_fetch($key, $success);
            if ($success) {
                return $val;
            }
        }

        // Fallback: Local database app_cache table
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('
                SELECT value_json 
                FROM app_cache 
                WHERE cache_key = :k AND expires_at > NOW() 
                LIMIT 1
            ');
            $stmt->execute(['k' => $key]);
            $raw = $stmt->fetchColumn();

            if ($raw !== false && $raw !== null) {
                return json_decode((string)$raw, true);
            }
        } catch (Throwable) {
            // Silently fall through to default on transient database error
        }

        return $default;
    }

    /**
     * Store an item in the cache.
     *
     * @param string $key
     * @param mixed  $value Must be JSON-serializable
     * @param int    $ttlSeconds Time to live in seconds (default: 300 = 5 minutes)
     * @return bool
     */
    public static function set(string $key, mixed $value, int $ttlSeconds = 300): bool
    {
        $stored = false;

        if (self::isApcuAvailable()) {
            $stored = apcu_store($key, $value, $ttlSeconds);
        }

        // Store in database fallback table as well
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('
                REPLACE INTO app_cache (cache_key, value_json, expires_at)
                VALUES (:k, :v, DATE_ADD(NOW(), INTERVAL :ttl SECOND))
            ');
            $stmt->bindValue(':k', $key);
            $stmt->bindValue(':v', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $stmt->bindValue(':ttl', $ttlSeconds, PDO::PARAM_INT);
            $dbOk = $stmt->execute();
            return $stored || $dbOk;
        } catch (Throwable) {
            return $stored;
        }
    }

    /**
     * Determine whether an item exists in the cache and is not expired.
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param string   $key
     * @param int      $ttlSeconds
     * @param callable $callback
     * @return mixed
     */
    public static function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        self::set($key, $value, $ttlSeconds);
        return $value;
    }

    /**
     * Delete an item from the cache.
     *
     * @param string $key
     * @return bool
     */
    public static function delete(string $key): bool
    {
        if (self::isApcuAvailable()) {
            apcu_delete($key);
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('DELETE FROM app_cache WHERE cache_key = :k');
            return $stmt->execute(['k' => $key]);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Clear all cached keys starting with a specific prefix.
     *
     * @param string $prefix
     * @return bool
     */
    public static function clearPrefix(string $prefix): bool
    {
        if (self::isApcuAvailable() && class_exists('\APCUIterator')) {
            try {
                $regex = '/^' . preg_quote($prefix, '/') . '/';
                $iterator = new \APCUIterator($regex);
                apcu_delete($iterator);
            } catch (Throwable) {
                // Ignore APCUIterator failure
            }
        }

        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('DELETE FROM app_cache WHERE cache_key LIKE :prefix');
            return $stmt->execute(['prefix' => $prefix . '%']);
        } catch (Throwable) {
            return false;
        }
    }
}
