<?php

declare(strict_types=1);

return [

    'host' => $_ENV['DB_HOST'] ?? getenv('DB_HOST'),

    'port' => $_ENV['DB_PORT'] ?? getenv('DB_PORT'),

    'database' => $_ENV['DB_NAME'] ?? getenv('DB_NAME'),

    'username' => $_ENV['DB_USER'] ?? getenv('DB_USER'),

    'password' => $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD'),

    'charset' => 'utf8mb4'

];