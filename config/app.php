<?php

declare(strict_types=1);

/**
 * ---------------------------------------------------------
 * NexusCore
 * ---------------------------------------------------------
 * File        : app.php
 * Description : Application configuration.
 * ---------------------------------------------------------
 */

// Helper to safely fetch environment variables from $_ENV or getenv()
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

    /*
    |--------------------------------------------------------------------------
    | Application
    |--------------------------------------------------------------------------
    */

    'name' => $env('APP_NAME', 'NexusCore'),

    /*
    |--------------------------------------------------------------------------
    | Institution Identity
    |--------------------------------------------------------------------------
    |
    | The official name and address of the college.
    | Used in all printed reports, PDFs, and official documents.
    |
    */

    'college_name'    => 'Government Arts and Science College',
    'college_address' => 'Veerapandi, Theni District',
    'college_event'   => 'Nexus ?" Intra Department Symposium',

    'environment' => $env('APP_ENV', 'production'),

    'debug' => filter_var(
        $env('APP_DEBUG', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | Change only this value when deploying.
    |
    */

    'base_url' => $env('BASE_URL', '/NexusCore'),

    'asset_url' => $env('ASSET_URL', '/NexusCore/public'),

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    */

    'timezone' => 'Asia/Kolkata',

    /*
    |--------------------------------------------------------------------------
    | Charset
    |--------------------------------------------------------------------------
    */

    'charset' => 'UTF-8'

];