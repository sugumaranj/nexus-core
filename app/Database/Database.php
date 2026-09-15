<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : Database.php
 * Location    : app/Database/
 * Description : Singleton PDO database connection.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    /**
     * Singleton PDO instance.
     */
    private static ?PDO $connection = null;

    /**
     * Prevent object creation.
     */
    private function __construct()
    {
    }

    /**
     * Returns a PDO connection with IST timezone set.
     *
     * @return PDO
     */
    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host     = $_ENV['DB_HOST']     ?? 'localhost';
        $port     = $_ENV['DB_PORT']     ?? '3306';
        $database = $_ENV['DB_NAME']     ?? '';
        $username = $_ENV['DB_USER']     ?? '';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $database
        );

        try {

            self::$connection = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );

            // Set MySQL session timezone to IST (UTC+5:30).
            // This ensures NOW(), CURTIME(), CURRENT_TIMESTAMP and the
            // Event Scheduler all operate in Indian Standard Time —
            // matching the timezone in which users enter dates.
            self::$connection->exec("SET time_zone = '+05:30'");

        } catch (PDOException $exception) {

            throw new RuntimeException(
                'Database connection failed: ' . $exception->getMessage()
            );

        }

        return self::$connection;
    }
}