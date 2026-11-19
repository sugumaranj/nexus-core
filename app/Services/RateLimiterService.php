<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS
 * =========================================================================
 * File        : RateLimiterService.php
 * Location    : app/Services/
 * Description : Database-backed sliding-window rate limiter for brute-force
 *               protection and API abuse prevention. Zero external dependencies.
 * =========================================================================
 */

namespace App\Services;

use App\Database\Database;
use PDO;
use Throwable;

final class RateLimiterService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Resolve the client IP address safely.
     *
     * @return string
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', (string)$_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    /**
     * Check if the client IP has exceeded the allowed attempt threshold in the given window.
     *
     * @param string $ip
     * @param string $endpoint
     * @param int    $maxAttempts
     * @param int    $windowSeconds Default: 300 (5 minutes)
     * @return bool True if rate-limited (blocked), false if allowed
     */
    public function isRateLimited(
        string $ip,
        string $endpoint,
        int $maxAttempts = 5,
        int $windowSeconds = 300
    ): bool {
        try {
            $sql = '
                SELECT COUNT(*)
                FROM login_attempts
                WHERE ip_address = :ip
                  AND endpoint = :endpoint
                  AND attempt_time > DATE_SUB(NOW(), INTERVAL :window SECOND)
            ';

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':ip', $ip);
            $stmt->bindValue(':endpoint', $endpoint);
            $stmt->bindValue(':window', $windowSeconds, PDO::PARAM_INT);
            $stmt->execute();

            $attempts = (int) $stmt->fetchColumn();
            return $attempts >= $maxAttempts;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Record a new attempt/failure.
     *
     * @param string $ip
     * @param string $endpoint
     * @return void
     */
    public function recordAttempt(string $ip, string $endpoint): void
    {
        try {
            $sql = '
                INSERT INTO login_attempts (ip_address, endpoint, attempt_time)
                VALUES (:ip, :endpoint, NOW())
            ';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'ip'       => $ip,
                'endpoint' => $endpoint,
            ]);
        } catch (Throwable) {
            // Silently ignore insert failures under read-only or lock contention
        }
    }

    /**
     * Clear all recorded attempts for this IP and endpoint upon successful authentication.
     *
     * @param string $ip
     * @param string $endpoint
     * @return void
     */
    public function clearAttempts(string $ip, string $endpoint): void
    {
        try {
            $sql = '
                DELETE FROM login_attempts
                WHERE ip_address = :ip
                  AND endpoint = :endpoint
            ';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'ip'       => $ip,
                'endpoint' => $endpoint,
            ]);
        } catch (Throwable) {
            // Ignore delete failures
        }
    }

    /**
     * Prune stale login attempt records older than 24 hours.
     *
     * @return int Number of deleted rows
     */
    public function pruneStaleAttempts(): int
    {
        try {
            $sql = 'DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)';
            return (int) $this->db->exec($sql);
        } catch (Throwable) {
            return 0;
        }
    }
}
