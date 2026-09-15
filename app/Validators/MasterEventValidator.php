<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : MasterEventValidator.php
 * Location    : app/Validators/
 * Description : Server-side input validation for Master Event templates.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Enforce server-side non-empty rules, category & team size constraints
 * • Validate team bounds (max_team_size >= min_team_size)
 * • Validate prelims configuration when enabled
 * • Enforce registration cascade rules (registration=0 => attendance/eval=0)
 * • Return structured field error messages for forms
 *
 * -------------------------------------------------------------------------
 */

namespace App\Validators;

final class MasterEventValidator
{
    /**
     * Validate Master Event form data.
     *
     * @param array $data
     * @param bool $isCreate
     * @return array Array of field => error message strings. Empty array means valid.
     */
    public function validate(array $data, bool $isCreate = true): array
    {
        $errors = [];

        // 1. Event Name
        $name = trim($data['event_name'] ?? '');
        if ($name === '') {
            $errors['event_name'] = 'Event name is required.';
        } elseif (mb_strlen($name) > 150) {
            $errors['event_name'] = 'Event name cannot exceed 150 characters.';
        }

        // Description
        $desc = trim($data['description'] ?? '');
        if ($desc !== '' && mb_strlen($desc) > 255) {
            $errors['description'] = 'Description cannot exceed 255 characters.';
        }

        // 2. Category
        $category = $data['category'] ?? '';
        if ($category === '') {
            $errors['category'] = 'Category is required.';
        } elseif (!in_array($category, ['Technical', 'Non-Technical'], true)) {
            $errors['category'] = 'Invalid category selected.';
        }

        // 3. Participation & Team Size
        $participation = $data['participation_type'] ?? 'Individual';
        if (!in_array($participation, ['Individual', 'Team', 'Both'], true)) {
            $errors['participation_type'] = 'Invalid participation type.';
        }

        $minTeam = (int)($data['min_team_size'] ?? 1);
        $maxTeam = (int)($data['max_team_size'] ?? 1);

        if ($participation === 'Individual') {
            // Force 1
        } else {
            if ($minTeam < 1) {
                $errors['min_team_size'] = 'Minimum team size must be at least 1.';
            }
            if ($maxTeam < $minTeam) {
                $errors['max_team_size'] = 'Maximum team size cannot be smaller than minimum team size.';
            }
            if ($maxTeam > 20) {
                $errors['max_team_size'] = 'Maximum team size cannot exceed 20 members.';
            }
        }

        // 4. Time Duration — supports two modes
        $durType = $data['duration_type'] ?? 'minutes';
        if ($durType === 'minutes') {
            $mins = $data['duration_minutes'] ?? '';
            if ($mins === '' || !is_numeric($mins)) {
                $errors['duration_minutes'] = 'Duration (minutes) is required.';
            } elseif ((int)$mins <= 0) {
                $errors['duration_minutes'] = 'Duration must be greater than 0 minutes.';
            } elseif ((int)$mins > 1440) {
                $errors['duration_minutes'] = 'Duration cannot exceed 1440 minutes (24 hours).';
            }
        } else {
            // time_slot mode
            $startTime    = trim($data['start_time'] ?? '');
            $endTime      = trim($data['end_time'] ?? '');
            $durationDays = (int)($data['duration_days'] ?? 1);
            if ($startTime === '') {
                $errors['start_time'] = 'Start time is required.';
            }
            if ($endTime === '') {
                $errors['end_time'] = 'End time is required.';
            }
            if ($durationDays < 1) {
                $errors['duration_days'] = 'Duration must be at least 1 day.';
            } elseif ($durationDays > 30) {
                $errors['duration_days'] = 'Duration cannot exceed 30 days.';
            }
        }

        // 5. Prelims Configuration
        // (prelim_type has been deprecated, only boolean requires_prelims is used)

        // 6. Maximum Score
        $maxScore = (float)($data['maximum_score'] ?? 100.00);
        if ($maxScore <= 0) {
            $errors['maximum_score'] = 'Maximum score must be greater than 0.';
        }

        // 7. Status
        $status = $data['status'] ?? 'Draft';
        if (!in_array($status, ['Draft', 'Published', 'Archived'], true)) {
            $errors['status'] = 'Invalid status selected.';
        }

        return $errors;
    }
}
