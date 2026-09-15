<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;

final class Bootstrap
{
    public static function loadEnvironment(string $basePath): void
    {
        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->safeLoad();

        // Set application timezone to IST (Indian Standard Time UTC+5:30).
        // All dates entered by users (registration windows, event schedules)
        // are in IST, so PHP must use the same timezone for comparisons.
        date_default_timezone_set('Asia/Kolkata');
    }
}