<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private static string $logFile = __DIR__ . '/../../storage/logs/nexuscore-error.log';

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    private static function log(string $level, string $message, array $context = []): void
    {
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $date = date('Y-m-d H:i:s');
        
        // Custom formatting for exceptions in context
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $e = $context['exception'];
            $contextString = PHP_EOL . "Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
            $contextString .= "Stack Trace:" . PHP_EOL . $e->getTraceAsString();
            unset($context['exception']);
        } else {
            $contextString = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        }
        
        $logEntry = "[$date] $level: $message $contextString" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND);
    }
}
