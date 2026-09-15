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

    'name' => $_ENV['APP_NAME'] ?? 'NexusCore',

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
    'college_event'   => 'Nexus — Intra Department Symposium',

    'environment' => $_ENV['APP_ENV'] ?? 'production',

    'debug' => filter_var(
        $_ENV['APP_DEBUG'] ?? false,
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

    'base_url' => '/NexusCore',

    'asset_url' => '/NexusCore/public',

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