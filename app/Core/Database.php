<?php

declare(strict_types=1);

/**
 * ---------------------------------------------------------
 * NexusCore
 * ---------------------------------------------------------
 * File        : Database.php
 * Description : Creates and manages a single PDO database
 *               connection for the application.
 *
 * Project     : NexusCore
 * PHP Version : 8.2+
 * ---------------------------------------------------------
 */

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    /**
     * Holds the single PDO instance.
     *
     * @var PDO|null
     */
    private static ?PDO $connection = null;

    /**
     * Prevent creating objects using "new".
     */
    private function __construct()
    {
    }

    /**
     * Returns a reusable PDO connection.
     *
     * @param array $config Database configuration.
     *
     * @return PDO
     *
     * @throws PDOException
     */
    public static function getConnection(array $config): PDO
    {
        if (self::$connection === null) {

            // Build the DSN string.
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            self::$connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }

        return self::$connection;
    }
}