<?php

declare(strict_types=1);

/**
 * Student helper functions.
 *
 * This file is autoloaded via composer.json "files" so functions
 * are available globally in templates and controllers.
 */

if (!function_exists('academic_year_label')) {
    /**
     * Convert stored numeric academic year to a user-friendly label.
     *
     * @param string|int|null $value
     *
     * @return string
     */
    function academic_year_label($value): string
    {
        $map = [
            '1' => '1st Year',
            '2' => '2nd Year',
            '3' => '3rd Year'
        ];

        $key = (string) ($value ?? '');

        return $map[$key] ?? '-';
    }
}

if (!function_exists('semester_label')) {
    /**
     * Convert stored numeric semester to a user-friendly label.
     *
     * @param string|int|null $value
     *
     * @return string
     */
    function semester_label($value): string
    {
        $key = (string) ($value ?? '');

        if ($key === '') {
            return '-';
        }

        return 'Semester ' . $key;
    }
}
