<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : competition_helper.php
 * Location    : app/Helpers/
 * Description : Global helper functions for the Competition module.
 *
 * These functions are autoloaded via composer.json and available in
 * all templates and controllers without an explicit require.
 *
 * -------------------------------------------------------------------------
 */

// -------------------------------------------------------------------------
// STATUS BADGE
// -------------------------------------------------------------------------

if (!function_exists('competition_status_badge_class')) {
    /**
     * Return Bootstrap badge colour class for a competition status.
     *
     * @param string|null $status
     * @return string
     */
    function competition_status_badge_class(?string $status): string
    {
        return match ($status) {
            'Draft'               => 'secondary',
            'Scheduled'           => 'info',
            'Registration Open'   => 'success',
            'Registration Closed' => 'warning',
            'Running'             => 'primary',
            'Completed'           => 'dark',
            'Cancelled'           => 'danger',
            default               => 'secondary',
        };
    }
}

// -------------------------------------------------------------------------
// MODE ICON
// -------------------------------------------------------------------------

if (!function_exists('competition_mode_icon')) {
    /**
     * Return a Bootstrap Icon class for a competition mode.
     *
     * @param string|null $mode
     * @return string
     */
    function competition_mode_icon(?string $mode): string
    {
        return match ($mode) {
            'Online'  => 'bi-wifi',
            'Offline' => 'bi-building',
            'Hybrid'  => 'bi-intersect',
            default   => 'bi-building',
        };
    }
}

// -------------------------------------------------------------------------
// CATEGORY COLOUR
// -------------------------------------------------------------------------

if (!function_exists('competition_category_color')) {
    /**
     * Return a CSS colour hex for a competition category.
     *
     * @param string|null $category
     * @return string
     */
    function competition_category_color(?string $category): string
    {
        return match ($category) {
            'Technical'     => '#2563eb',
            'Non-Technical' => '#7c3aed',
            default         => '#6b7280',
        };
    }
}

// -------------------------------------------------------------------------
// CATEGORY BADGE CLASS
// -------------------------------------------------------------------------

if (!function_exists('competition_category_badge_class')) {
    /**
     * Return Bootstrap badge class for a competition category.
     *
     * @param string|null $category
     * @return string
     */
    function competition_category_badge_class(?string $category): string
    {
        return match ($category) {
            'Technical'     => 'primary',
            'Non-Technical' => 'purple',
            default         => 'secondary',
        };
    }
}

// -------------------------------------------------------------------------
// FORMAT TIME
// -------------------------------------------------------------------------

if (!function_exists('competition_format_time')) {
    /**
     * Format a TIME string (HH:MM:SS) to 12-hour display (e.g. 09:30 AM).
     *
     * @param string|null $time
     * @return string
     */
    function competition_format_time(?string $time): string
    {
        if ($time === null || trim($time) === '') {
            return '-';
        }

        $timestamp = strtotime('1970-01-01 ' . $time);

        if ($timestamp === false) {
            return $time;
        }

        return date('h:i A', $timestamp);
    }
}

// -------------------------------------------------------------------------
// SESSION LABEL
// -------------------------------------------------------------------------

if (!function_exists('competition_session_label')) {
    /**
     * Return a display label for a session code.
     *
     * @param string|null $session
     * @return string
     */
    function competition_session_label(?string $session): string
    {
        return match ($session) {
            'FN'       => 'Forenoon',
            'AN'       => 'Afternoon',
            'Full Day' => 'Full Day',
            default    => $session ?? '-',
        };
    }
}

// -------------------------------------------------------------------------
// PARTICIPATION LABEL
// -------------------------------------------------------------------------

if (!function_exists('competition_participation_label')) {
    /**
     * Return a display label for participation type.
     *
     * @param string|null $type
     * @return string
     */
    function competition_participation_label(?string $type): string
    {
        return match ($type) {
            'Individual' => 'Individual',
            'Team'       => 'Team',
            'Both'       => 'Individual & Team',
            default      => $type ?? '-',
        };
    }
}

// -------------------------------------------------------------------------
// JUDGING METHOD LABEL
// -------------------------------------------------------------------------

if (!function_exists('competition_judging_label')) {
    /**
     * Return a display label for judging method.
     *
     * @param string|null $method
     * @return string
     */
    function competition_judging_label(?string $method): string
    {
        return match ($method) {
            'Marks'   => 'Marks-Based',
            'Rubrics' => 'Rubrics',
            'Voting'  => 'Public Voting',
            'Mixed'   => 'Mixed (Marks + Rubrics)',
            default   => $method ?? '-',
        };
    }
}

// -------------------------------------------------------------------------
// RULE SECTION ICON
// -------------------------------------------------------------------------

if (!function_exists('competition_rule_section_icon')) {
    /**
     * Return Bootstrap Icon class for a rule section.
     *
     * @param string|null $section
     * @return string
     */
    function competition_rule_section_icon(?string $section): string
    {
        return match ($section) {
            'Eligibility'           => 'bi-person-check',
            'Topics'                => 'bi-journal-text',
            'Judging Criteria'      => 'bi-star',
            'Allowed Software'      => 'bi-laptop',
            'Required Materials'    => 'bi-clipboard',
            'Prohibited Items'      => 'bi-slash-circle',
            'Submission Format'     => 'bi-file-earmark-arrow-up',
            'Time Limit'            => 'bi-clock',
            'Additional Instructions'=> 'bi-info-circle',
            'General Rules'         => 'bi-list-check',
            'Tie Break Rules'       => 'bi-shuffle',
            'Online Guidelines'     => 'bi-wifi',
            default                 => 'bi-circle',
        };
    }
}

// -------------------------------------------------------------------------
// COORDINATOR TYPE BADGE
// -------------------------------------------------------------------------

if (!function_exists('competition_coordinator_badge_class')) {
    /**
     * Return Bootstrap badge class for coordinator type.
     *
     * @param string|null $type
     * @return string
     */
    function competition_coordinator_badge_class(?string $type): string
    {
        return match ($type) {
            'Staff Coordinator'   => 'primary',
            'Event Coordinator'   => 'success',
            'Student Coordinator' => 'info',
            'Judge'               => 'warning',
            default               => 'secondary',
        };
    }
}
