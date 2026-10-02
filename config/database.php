<?php

declare(strict_types=1);

$env = function(string $key, $default = null) {
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }
    $val = getenv($key);
    if ($val !== false) {
        return $val;
    }
    return $default;
};

return [

    'host' => $env('DB_HOST', 'localhost'),

    'port' => $env('DB_PORT', '3306'),

    'database' => $env('DB_NAME', 'nexus_ems'),

    'username' => $env('DB_USER', 'root'),

    'password' => $env('DB_PASSWORD', ''),

    'charset' => 'utf8mb4'

];