<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    /**
     * Register the error handler, exception handler, and shutdown function.
     */
    public static function register(): void
    {
        error_reporting(E_ALL);

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Convert PHP errors into ErrorException.
     *
     * @param int    $level
     * @param string $message
     * @param string $file
     * @param int    $line
     *
     * @return bool
     * @throws ErrorException
     */
    public static function handleError(int $level, string $message, string $file, int $line): bool
    {
        if (error_reporting() !== 0) {
            throw new ErrorException($message, 0, $level, $file, $line);
        }
        return false;
    }

    /**
     * Handle uncaught exceptions.
     *
     * @param Throwable $exception
     */
    public static function handleException(Throwable $exception): void
    {
        // 1. Log the exception internally
        Logger::error('Uncaught Exception', ['exception' => $exception]);

        // 2. Clear any output buffers to prevent partial rendering
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // 3. Determine HTTP response code
        $code = $exception->getCode();
        if (!is_numeric($code) || $code < 100 || $code > 599) {
            $code = 500;
        }
        http_response_code((int)$code);

        // 4. Determine if we are in Debug Mode
        $isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

        if ($isDebug) {
            self::renderTracePage($exception);
        } else {
            self::renderProductionPage();
        }

        exit(1);
    }

    /**
     * Handle fatal errors (e.g., syntax errors, out of memory) that bypass normal error handling.
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $exception = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
            self::handleException($exception);
        }
    }

    /**
     * Render the safe 500 error page for production.
     */
    private static function renderProductionPage(): void
    {
        $templatePath = dirname(__DIR__, 2) . '/templates/errors/500.php';
        if (file_exists($templatePath)) {
            require $templatePath;
        } else {
            echo '<h1>500 Internal Server Error</h1>';
            echo '<p>An unexpected error occurred. Please try again later.</p>';
        }
    }

    /**
     * Render a detailed trace page for development.
     */
    private static function renderTracePage(Throwable $exception): void
    {
        $templatePath = dirname(__DIR__, 2) . '/templates/errors/trace.php';
        if (file_exists($templatePath)) {
            require $templatePath;
        } else {
            echo '<h1>Exception: ' . htmlspecialchars($exception->getMessage()) . '</h1>';
            echo '<p>in ' . htmlspecialchars($exception->getFile()) . ' on line ' . $exception->getLine() . '</p>';
            echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
        }
    }
}
