<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : CompetitionValidator.php
 * Location    : app/Validators/
 * Description : Input validation for the Competition Management module.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Validate all 7 wizard steps independently
 * • Validate the full competition payload on store/update
 * • Date/time ordering checks
 * • Team size logic
 * • Deadline vs event date bounds
 *
 * NOTE
 * -------------------------------------------------------------------------
 * This class performs input validation ONLY.
 * It does NOT communicate with the database.
 * Business rules (duplicate codes, conflicts) belong in CompetitionService.
 *
 * -------------------------------------------------------------------------
 */

namespace App\Validators;

final class CompetitionValidator
{
    // -------------------------------------------------------------------------
    // Allowed ENUM values mirroring the database schema
    // -------------------------------------------------------------------------

    private array $allowedStatuses = [
        'Draft', 'Scheduled', 'Registration Open',
        'Registration Closed', 'Running', 'Completed', 'Cancelled',
    ];

    private array $allowedModes = ['Offline', 'Online', 'Hybrid'];

    private array $allowedCategories = ['Technical', 'Non-Technical'];

    private array $allowedSessions = ['FN', 'AN', 'Full Day'];

    private array $allowedParticipationTypes = ['Individual', 'Team', 'Both'];

    private array $allowedJudgingMethods = ['Marks', 'Rubrics', 'Voting', 'Mixed'];

    private array $allowedPrelimTypes = [
        'MCQ', 'File Submission', 'Coding Test',
        'Abstract Screening', 'Portfolio Review', 'Custom',
    ];

    private array $allowedPlatforms = [
        'Google Meet', 'Microsoft Teams', 'Zoom', 'Other',
    ];

    private array $allowedRuleSections = [
        'Eligibility', 'Topics', 'Judging Criteria', 'Allowed Software',
        'Required Materials', 'Prohibited Items', 'Submission Format',
        'Time Limit', 'Additional Instructions', 'General Rules',
        'Tie Break Rules', 'Online Guidelines',
    ];

    // =========================================================================
    // FULL VALIDATION
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Validate the complete competition payload.
     *
     * Returns an array of field => error message pairs.
     * Empty array = valid.
     *
     * @param array $data
     * @param bool  $isCreate
     *
     * @return array<string, string>
     * -------------------------------------------------------------------------
     */
    public function validate(array $data, bool $isCreate = true): array
    {
        $errors = [];

        $errors = array_merge($errors, $this->validateStep1($data, $isCreate));
        $errors = array_merge($errors, $this->validateStep2($data));
        $errors = array_merge($errors, $this->validateStep3($data));
        $errors = array_merge($errors, $this->validateStep4($data));
        $errors = array_merge($errors, $this->validateStep5($data));

        return $errors;
    }

    // =========================================================================
    // STEP-LEVEL VALIDATION
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Step 1 — Basic Information.
     *
     * @param array $data
     * @param bool  $isCreate
     *
     * @return array<string, string>
     * -------------------------------------------------------------------------
     */
    public function validateStep1(array $data, bool $isCreate = true): array
    {
        $errors = [];

        // Competition Code (create only — immutable after creation)
        if ($isCreate) {
            $code = strtoupper(trim((string) ($data['competition_code'] ?? '')));

            if ($code === '') {
                $errors['competition_code'] = 'Competition code is required.';
            } elseif (!preg_match('/^[A-Z0-9\-]{2,30}$/', $code)) {
                $errors['competition_code'] =
                    'Code must be 2-30 characters: letters, digits, hyphens only.';
            }
        }

        // Title
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Competition title is required.';
        } elseif (mb_strlen($title) > 150) {
            $errors['title'] = 'Title must not exceed 150 characters.';
        }

        // Symposium
        $symposiumId = trim((string) ($data['symposium_id'] ?? ''));
        if ($symposiumId === '' || !ctype_digit($symposiumId) || (int) $symposiumId <= 0) {
            $errors['symposium_id'] = 'Please select a symposium.';
        }

        // Competition Type
        $typeId = trim((string) ($data['competition_type_id'] ?? ''));
        if ($typeId === '' || !ctype_digit($typeId) || (int) $typeId <= 0) {
            $errors['competition_type_id'] = 'Please select a competition type.';
        }

        // Category
        $category = trim((string) ($data['category'] ?? ''));
        if (!in_array($category, $this->allowedCategories, true)) {
            $errors['category'] = 'Please select a valid category.';
        }

        // Status
        $status = trim((string) ($data['status'] ?? ''));
        if (!in_array($status, $this->allowedStatuses, true)) {
            $errors['status'] = 'Please select a valid status.';
        }

        // Display Order
        $displayOrder = trim((string) ($data['display_order'] ?? '1'));
        if ($displayOrder !== '' && (!ctype_digit($displayOrder) || (int) $displayOrder < 1)) {
            $errors['display_order'] = 'Display order must be a positive number.';
        }

        return $errors;
    }

    /**
     * -------------------------------------------------------------------------
     * Step 2 — Participation & Eligibility.
     *
     * @param array $data
     *
     * @return array<string, string>
     * -------------------------------------------------------------------------
     */
    public function validateStep2(array $data): array
    {
        $errors = [];

        // Participation type
        $participationType = trim((string) ($data['participation_type'] ?? ''));
        if (!in_array($participationType, $this->allowedParticipationTypes, true)) {
            $errors['participation_type'] = 'Please select a valid participation type.';
        }

        // Team sizes — only if Team or Both
        if (in_array($participationType, ['Team', 'Both'], true)) {
            $minTeam = (int) ($data['min_team_size'] ?? 1);
            $maxTeam = (int) ($data['max_team_size'] ?? 1);

            if ($minTeam < 1 || $minTeam > 10) {
                $errors['min_team_size'] = 'Minimum team size must be between 1 and 10.';
            }
            if ($maxTeam < 1 || $maxTeam > 10) {
                $errors['max_team_size'] = 'Maximum team size must be between 1 and 10.';
            }
            if (empty($errors['min_team_size']) && empty($errors['max_team_size'])
                && $minTeam > $maxTeam) {
                $errors['min_team_size'] = 'Minimum team size cannot exceed maximum team size.';
            }
        }

        // Max participants
        $maxParticipants = trim((string) ($data['max_participants'] ?? ''));
        if ($maxParticipants !== '') {
            if (!ctype_digit($maxParticipants) || (int) $maxParticipants < 1) {
                $errors['max_participants'] = 'Maximum participants must be a positive number.';
            }
        }

        // Registration limit
        $regLimit = trim((string) ($data['registration_limit'] ?? ''));
        if ($regLimit !== '') {
            if (!ctype_digit($regLimit) || (int) $regLimit < 1) {
                $errors['registration_limit'] = 'Registration limit must be a positive number.';
            }
        }

        return $errors;
    }

    /**
     * -------------------------------------------------------------------------
     * Step 3 — Schedule.
     *
     * @param array $data
     *
     * @return array<string, string>
     * -------------------------------------------------------------------------
     */
    public function validateStep3(array $data): array
    {
        $errors = [];

        // Event Date
        $eventDate = trim((string) ($data['event_date'] ?? ''));
        if ($eventDate === '') {
            $errors['event_date'] = 'Event date is required.';
        } elseif (!$this->isValidDate($eventDate)) {
            $errors['event_date'] = 'Event date must be a valid date (YYYY-MM-DD).';
        }

        // Session
        $session = trim((string) ($data['session'] ?? ''));
        if (!in_array($session, $this->allowedSessions, true)) {
            $errors['session'] = 'Please select a valid session (FN / AN / Full Day).';
        }

        // Start Time
        $startTime = trim((string) ($data['start_time'] ?? ''));
        if ($startTime === '') {
            $errors['start_time'] = 'Start time is required.';
        } elseif (!$this->isValidTime($startTime)) {
            $errors['start_time'] = 'Start time must be a valid time (HH:MM).';
        }

        // End Time
        $endTime = trim((string) ($data['end_time'] ?? ''));
        if ($endTime === '') {
            $errors['end_time'] = 'End time is required.';
        } elseif (!$this->isValidTime($endTime)) {
            $errors['end_time'] = 'End time must be a valid time (HH:MM).';
        }

        // Start < End
        if (empty($errors['start_time']) && empty($errors['end_time'])) {
            if ($startTime >= $endTime) {
                $errors['end_time'] = 'End time must be after start time.';
            }
        }

        // Venue
        $venueId = trim((string) ($data['venue_id'] ?? ''));
        if ($venueId === '' || !ctype_digit($venueId) || (int) $venueId <= 0) {
            $errors['venue_id'] = 'Please select a venue.';
        }

        // Reporting Time (optional, but if provided must be valid)
        $reportingTime = trim((string) ($data['reporting_time'] ?? ''));
        if ($reportingTime !== '' && !$this->isValidTime($reportingTime)) {
            $errors['reporting_time'] = 'Reporting time must be a valid time (HH:MM).';
        }

        // Registration Deadline
        $regDeadline = trim((string) ($data['registration_deadline'] ?? ''));
        if ($regDeadline === '') {
            $errors['registration_deadline'] = 'Registration deadline is required.';
        } elseif (!$this->isValidDateTime($regDeadline)) {
            $errors['registration_deadline'] = 'Registration deadline must be a valid date and time.';
        }

        // Registration deadline must be before event date
        if (empty($errors['event_date']) && empty($errors['registration_deadline'])
            && $this->isValidDate($eventDate) && $this->isValidDateTime($regDeadline)) {
            $deadlineDate = date('Y-m-d', strtotime($regDeadline));
            if ($deadlineDate > $eventDate) {
                $errors['registration_deadline'] =
                    'Registration deadline must be on or before the event date.';
            }
        }

        // Submission Deadline (optional)
        $subDeadline = trim((string) ($data['submission_deadline'] ?? ''));
        if ($subDeadline !== '' && !$this->isValidDateTime($subDeadline)) {
            $errors['submission_deadline'] = 'Submission deadline must be a valid date and time.';
        }

        return $errors;
    }

    /**
     * -------------------------------------------------------------------------
     * Step 4 — Competition Configuration.
     *
     * @param array $data
     *
     * @return array<string, string>
     * -------------------------------------------------------------------------
     */
    public function validateStep4(array $data): array
    {
        $errors = [];

        // Mode
        $mode = trim((string) ($data['competition_mode'] ?? ''));
        if (!in_array($mode, $this->allowedModes, true)) {
            $errors['competition_mode'] = 'Please select a valid competition mode.';
        }

        // Judging Method
        $judging = trim((string) ($data['judging_method'] ?? ''));
        if (!in_array($judging, $this->allowedJudgingMethods, true)) {
            $errors['judging_method'] = 'Please select a valid judging method.';
        }

        // Prelim Type — required if has_digital_prelims = 1
        $hasDigitalPrelims = (int) ($data['has_digital_prelims'] ?? 0);
        if ($hasDigitalPrelims === 1) {
            $prelimType = trim((string) ($data['prelim_type'] ?? ''));
            if (!in_array($prelimType, $this->allowedPrelimTypes, true)) {
                $errors['prelim_type'] = 'Please select a digital prelim type.';
            }
        }

        // Online platform required when mode = Online or Hybrid
        if (in_array($mode, ['Online', 'Hybrid'], true)) {
            $platform = trim((string) ($data['online_platform'] ?? ''));
            if (!in_array($platform, $this->allowedPlatforms, true)) {
                $errors['online_platform'] = 'Please select an online platform.';
            }
        }

        // Maximum Score
        $maxScore = trim((string) ($data['maximum_score'] ?? '100'));
        if (!is_numeric($maxScore) || (float) $maxScore <= 0) {
            $errors['maximum_score'] = 'Maximum score must be a positive number.';
        }

        return $errors;
    }

    /**
     * -------------------------------------------------------------------------
     * Step 5 — Rules (structured).
     *
     * @param array $data
     *
     * @return array<string, string>
     * -------------------------------------------------------------------------
     */
    public function validateStep5(array $data): array
    {
        $errors = [];

        $ruleSections = $data['rule_sections'] ?? [];

        if (!is_array($ruleSections)) {
            return $errors;
        }

        foreach ($ruleSections as $idx => $rule) {
            $section = trim((string) ($rule['section'] ?? ''));
            $content = trim((string) ($rule['content'] ?? ''));

            if ($section !== '' && !in_array($section, $this->allowedRuleSections, true)) {
                $errors["rule_sections_{$idx}_section"] =
                    "Rule #{$idx}: Please select a valid rule section.";
            }

            if ($section !== '' && $content === '') {
                $errors["rule_sections_{$idx}_content"] =
                    "Rule #{$idx}: Content is required for section '{$section}'.";
            }
        }

        return $errors;
    }

    /**
     * -------------------------------------------------------------------------
     * Validate status change request.
     *
     * @param string $status
     *
     * @return string|null
     * -------------------------------------------------------------------------
     */
    public function validateStatus(string $status): ?string
    {
        if (!in_array($status, $this->allowedStatuses, true)) {
            return 'Please select a valid competition status.';
        }

        return null;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Check whether a string is a valid YYYY-MM-DD date.
     *
     * @param string $value
     * @return bool
     */
    private function isValidDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        return strtotime($value) !== false;
    }

    /**
     * Check whether a string is a valid time (HH:MM or HH:MM:SS).
     *
     * @param string $value
     * @return bool
     */
    private function isValidTime(string $value): bool
    {
        return (bool) preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value);
    }

    /**
     * Check whether a string is a valid datetime (YYYY-MM-DD HH:MM or ISO 8601).
     *
     * @param string $value
     * @return bool
     */
    private function isValidDateTime(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $value)) {
            return false;
        }

        return strtotime($value) !== false;
    }
}
