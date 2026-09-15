<?php

declare(strict_types=1);

/**
 * Symposium helper functions.
 *
 * This file is autoloaded via composer.json "files" so functions
 * are available globally in templates and controllers.
 */

if (!function_exists('symposium_status_badge_class')) {
    /**
     * Return Bootstrap badge class for symposium status.
     *
     * @param string|null $status
     *
     * @return string
     */
    function symposium_status_badge_class(?string $status): string
    {
        $statusStr = $status ?? '';
        
        if (str_starts_with($statusStr, 'Pending')) {
            return 'warning text-dark';
        }
        if (str_starts_with($statusStr, 'Rejected') || str_starts_with($statusStr, 'Revision')) {
            return 'danger';
        }

        return match ($statusStr) {
            'Draft' => 'secondary',
            'Approved' => 'success',
            'Registration Open' => 'primary',
            'Registration Closed' => 'warning text-dark',
            'Completed' => 'dark',
            'Cancelled' => 'danger',
            default => 'secondary'
        };
    }
}

if (!function_exists('symposium_format_datetime')) {
    /**
     * Format symposium datetime for display.
     *
     * @param string|null $value
     *
     * @return string
     */
    function symposium_format_datetime(?string $value): string
    {
        return \App\Helpers\DateHelper::dateTime($value, '-');
    }
}

if (!function_exists('symposium_format_date')) {
    /**
     * Format symposium date for display.
     *
     * @param string|null $value
     *
     * @return string
     */
    function symposium_format_date(?string $value): string
    {
        return \App\Helpers\DateHelper::date($value, '-');
    }
}

if (!function_exists('symposium_academic_year_label')) {
    /**
     * Format symposium academic year for display.
     *
     * @param string|int|null $value
     *
     * @return string
     */
    function symposium_academic_year_label($value): string
    {
        $year = trim((string) ($value ?? ''));

        if ($year === '') {
            return '-';
        }

        return $year;
    }
}

if (!function_exists('symposium_is_image_file')) {
    /**
     * Check whether a stored file path points to an image.
     *
     * @param string|null $path
     *
     * @return bool
     */
    function symposium_is_image_file(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true);
    }
}

if (!function_exists('symposium_file_basename')) {
    /**
     * Extract a display-friendly file name from a stored path.
     *
     * @param string|null $path
     *
     * @return string
     */
    function symposium_file_basename(?string $path): string
    {
        if ($path === null || $path === '') {
            return '-';
        }

        return basename($path);
    }
}
