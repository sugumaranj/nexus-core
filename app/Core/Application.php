<?php

declare(strict_types=1);

/**
 * ---------------------------------------------------------
 * NexusCore
 * ---------------------------------------------------------
 * File        : Application.php
 * Description : Initializes and manages the application.
 *
 * Responsibilities:
 *  - Load application configuration
 *  - Pre-warm the shared PDO singleton (App\Database\Database)
 *  - Start PHP session
 *
 * NOTE
 * ---------------------------------------------------------
 * The database connection is managed exclusively by
 * App\Database\Database (reads credentials from $_ENV).
 * Calling ::getConnection() here pre-warms the singleton
 * so that the first model constructor does not bear the
 * connection-establishment cost.
 *
 * App\Core\Database has been retired; do not re-introduce it.
 *
 * Project     : NexusCore
 * ---------------------------------------------------------
 */

namespace App\Core;

use App\Database\Database;

final class Application
{
    /**
     * Application configuration.
     *
     * @var array
     */
    private array $appConfig;

    /**
     * Constructor.
     *
     * Loads application configuration, starts the session,
     * and pre-warms the shared database singleton so every
     * downstream model gets the same PDO connection.
     */
    public function __construct()
    {
        $this->loadConfigurations();

        Session::start();

        $this->prewarmDatabase();
    }

    /**
     * Load application configuration file.
     *
     * @return void
     */
    private function loadConfigurations(): void
    {
        $this->appConfig = require dirname(__DIR__, 2) . '/config/app.php';
    }

    /**
     * Pre-warm the shared PDO singleton.
     *
     * App\Database\Database reads DB credentials from $_ENV
     * (populated by Bootstrap::loadEnvironment before this class
     * is instantiated).  Calling getConnection() here ensures
     * the PDO object is created once and reused by all models.
     *
     * @return void
     */
    private function prewarmDatabase(): void
    {
        Database::getConnection();
    }

    /**
     * Returns application configuration.
     *
     * @return array
     */
    public function getAppConfig(): array
    {
        return $this->appConfig;
    }
}