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

return [

    /*
    |--------------------------------------------------------------------------
    | Application
    |--------------------------------------------------------------------------
    */

    'name' => $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?? 'NexusCore',

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

    'environment' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'production',

    'debug' => filter_var(
        $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? false,
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

    'base_url' => $_ENV['BASE_URL'] ?? getenv('BASE_URL') ?? '/NexusCore',

    'asset_url' => $_ENV['ASSET_URL'] ?? getenv('ASSET_URL') ?? '/NexusCore/public',

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