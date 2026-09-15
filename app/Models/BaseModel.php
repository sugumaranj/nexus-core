<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : BaseModel.php
 * Location    : app/Models/
 * Description : Base model for all application models.
 *
 * Every model extends this class to access the shared PDO connection.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use App\Database\Database;
use PDO;

abstract class BaseModel
{
    /**
     * PDO database connection.
     *
     * @var PDO
     */
    protected PDO $db;

    /**
     * Initialize the shared database connection.
     */
    public function __construct()
    {
        $this->db = Database::getConnection();
    }
}