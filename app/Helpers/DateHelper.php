<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : DateHelper.php
 * Location    : app/Helpers/
 * Description : Centralised date/time formatting for the entire application.
 *
 * ALL date/time display throughout NexusCore must go through this helper.
 *
 * Rules
 * ─────
 * • Date display  : DD/MM/YYYY         (e.g. 07/08/2026)
 * • Time display  : h:i AM/PM          (e.g. 09:30 AM, 11:45 PM)
 * • DateTime      : DD/MM/YYYY h:i AM/PM
 * • Timezone      : Asia/Kolkata (IST, UTC+5:30) — always
 *
 * Never call date() or strtotime() directly in templates/views.
 * Use these static methods instead.
 *
 * -------------------------------------------------------------------------
 */

namespace App\Helpers;

final class DateHelper
{
    /** Display format constants */
    public const FMT_DATE     = 'd/m/Y';           // 07/08/2026
    public const FMT_TIME     = 'h:i A';           // 09:30 AM
    public const FMT_DATETIME = 'd/m/Y h:i A';     // 07/08/2026 09:30 AM
    public const FMT_DATE_LONG = 'd M Y';          // 07 Aug 2026

    /** Internal storage format (for DB, comparisons — never for display) */
    public const FMT_DB_DATE     = 'Y-m-d';
    public const FMT_DB_DATETIME = 'Y-m-d H:i:s';

    // =========================================================================
    // DISPLAY FORMATTERS
    // =========================================================================

    /**
     * Format a date string as DD/MM/YYYY.
     *
     * @param string|null $dateStr  Any parseable date/datetime string
     * @param string      $fallback Shown when $dateStr is null/empty/invalid
     * @return string
     */
    public static function date(?string $dateStr, string $fallback = 'TBA'): string
    {
        if (empty($dateStr)) {
            return $fallback;
        }

        $ts = strtotime($dateStr);
        if ($ts === false) {
            return $fallback;
        }

        return date(self::FMT_DATE, $ts);
    }

    /**
     * Format a time string as h:i AM/PM.
     *
     * Accepts TIME columns ('HH:MM:SS'), DATETIME, or any parseable string.
     *
     * @param string|null $timeStr
     * @param string      $fallback
     * @return string
     */
    public static function time(?string $timeStr, string $fallback = 'TBA'): string
    {
        if (empty($timeStr)) {
            return $fallback;
        }

        // If it looks like a bare time (HH:MM or HH:MM:SS), prefix a dummy date
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', trim($timeStr))) {
            $timeStr = '2000-01-01 ' . $timeStr;
        }

        $ts = strtotime($timeStr);
        if ($ts === false) {
            return $fallback;
        }

        return date(self::FMT_TIME, $ts);
    }

    /**
     * Format a datetime string as DD/MM/YYYY h:i AM/PM.
     *
     * @param string|null $dtStr
     * @param string      $fallback
     * @return string
     */
    public static function dateTime(?string $dtStr, string $fallback = 'TBA'): string
    {
        if (empty($dtStr)) {
            return $fallback;
        }

        $ts = strtotime($dtStr);
        if ($ts === false) {
            return $fallback;
        }

        return date(self::FMT_DATETIME, $ts);
    }

    /**
     * Format date as "07 Aug 2026" (long readable form).
     *
     * @param string|null $dateStr
     * @param string      $fallback
     * @return string
     */
    public static function dateLong(?string $dateStr, string $fallback = 'TBA'): string
    {
        if (empty($dateStr)) {
            return $fallback;
        }

        $ts = strtotime($dateStr);
        if ($ts === false) {
            return $fallback;
        }

        return date(self::FMT_DATE_LONG, $ts);
    }

    /**
     * Relative time: "2 hours ago", "in 3 days", etc.
     *
     * @param string|null $dtStr
     * @param string      $fallback
     * @return string
     */
    public static function fromNow(?string $dtStr, string $fallback = 'Unknown'): string
    {
        if (empty($dtStr)) {
            return $fallback;
        }

        $ts = strtotime($dtStr);
        if ($ts === false) {
            return $fallback;
        }

        $diff = time() - $ts;

        if (abs($diff) < 60) {
            return 'Just now';
        }

        $future = $diff < 0;
        $diff   = abs($diff);

        $intervals = [
            [60,           'minute'],
            [3600,         'hour'],
            [86400,        'day'],
            [604800,       'week'],
            [2592000,      'month'],
            [31536000,     'year'],
        ];

        $label = '';
        foreach ($intervals as [$divisor, $unit]) {
            if ($diff < $divisor) {
                break;
            }
            $count = (int) round($diff / $divisor);
            $label = $count . ' ' . $unit . ($count !== 1 ? 's' : '');
        }

        if (empty($label)) {
            return 'Just now';
        }

        return $future ? 'in ' . $label : $label . ' ago';
    }

    // =========================================================================
    // UTILITY
    // =========================================================================

    /**
     * Return the current IST datetime as a DB-safe string.
     * Use this instead of date('Y-m-d H:i:s') in DB writes.
     *
     * @return string  e.g. "2026-08-07 21:30:00"
     */
    public static function nowDb(): string
    {
        return date(self::FMT_DB_DATETIME);
    }

    /**
     * Return the current IST date as a DB-safe string.
     *
     * @return string  e.g. "2026-08-07"
     */
    public static function todayDb(): string
    {
        return date(self::FMT_DB_DATE);
    }

    /**
     * Check if a given datetime string is in the past.
     *
     * @param string|null $dtStr
     * @return bool
     */
    public static function isPast(?string $dtStr): bool
    {
        if (empty($dtStr)) {
            return false;
        }
        $ts = strtotime($dtStr);
        return $ts !== false && $ts < time();
    }

    /**
     * Check if a given datetime string is in the future.
     *
     * @param string|null $dtStr
     * @return bool
     */
    public static function isFuture(?string $dtStr): bool
    {
        if (empty($dtStr)) {
            return false;
        }
        $ts = strtotime($dtStr);
        return $ts !== false && $ts > time();
    }

    /**
     * Check if a given date string falls on a Saturday or Sunday.
     *
     * @param string|null $dateStr
     * @return bool
     */
    public static function isWeekend(?string $dateStr): bool
    {
        if (empty($dateStr)) {
            return false;
        }
        $ts = strtotime($dateStr);
        if ($ts === false) {
            return false;
        }
        $dayOfWeek = (int) date('w', $ts);
        // 0 = Sunday, 6 = Saturday
        return $dayOfWeek === 0 || $dayOfWeek === 6;
    }
}
