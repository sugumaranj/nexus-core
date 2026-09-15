<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : SymposiumValidator.php
 * Location    : app/Validators/
 * Description : Input validation for the Symposium module.
 *
 * Changes from previous version
 * -------------------------------------------------------------------------
 * • Removed: symposium_code (now auto-generated)
 * • Removed: organizing_department_id (now from junction table)
 * • Removed: status (never submitted via form — controlled by workflow)
 * • Added:   theme (optional)
 * • Added:   objectives (optional)
 * • Kept:    all date validations
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Validators;

final class SymposiumValidator
{
    /**
     * @var array<int, string>
     */
    private array $allowedTypes = [
        'Intra Department',
        'Inter Department',
    ];

    /**
     * -----------------------------------------------------------------------
     * Validate symposium form data.
     *
     * @param array $data
     * @param bool  $isCreate  (reserved for future per-create rules)
     *
     * @return array<string, string>  Field => error message
     * -----------------------------------------------------------------------
     */
    public function validate(array $data, bool $isCreate = true): array
    {
        $errors = [];

        // ---- Title ----
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen($title) > 150) {
            $errors['title'] = 'Title cannot exceed 150 characters.';
        }

        // ---- Symposium Type ----
        $symposiumType = trim((string) ($data['symposium_type'] ?? ''));
        if (!in_array($symposiumType, $this->allowedTypes, true)) {
            $errors['symposium_type'] = 'Please select a valid symposium type.';
        }

        // ---- Academic Year ----
        $academicYear = trim((string) ($data['academic_year'] ?? ''));
        if ($academicYear === '') {
            $errors['academic_year'] = 'Academic year is required.';
        } elseif (!preg_match('/^\d{4}$/', $academicYear)) {
            $errors['academic_year'] = 'Academic year must be a valid 4-digit year.';
        } else {
            $year        = (int) $academicYear;
            $currentYear = (int) date('Y');
            if ($year < 2000 || $year > ($currentYear + 10)) {
                $errors['academic_year'] = 'Academic year must be between 2000 and ' . ($currentYear + 10) . '.';
            }
        }

        // ---- Description ----
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            $errors['description'] = 'Description is required.';
        } elseif (mb_strlen($description) > 5000) {
            $errors['description'] = 'Description cannot exceed 5000 characters.';
        }


        // ---- Dates ----
        $registrationStart = trim((string) ($data['registration_start'] ?? ''));
        $registrationEnd   = trim((string) ($data['registration_end'] ?? ''));
        $eventStartDate    = trim((string) ($data['event_start_date'] ?? ''));
        $eventEndDate      = trim((string) ($data['event_end_date'] ?? ''));

        if ($registrationStart === '') {
            $errors['registration_start'] = 'Registration start date is required.';
        } elseif (!$this->isValidDateTime($registrationStart)) {
            $errors['registration_start'] = 'Registration start must be a valid date and time.';
        } elseif ($isCreate && strtotime(date('Y-m-d', strtotime($registrationStart))) < strtotime(date('Y-m-d'))) {
            $errors['registration_start'] = 'Registration start date cannot be in the past.';
        }

        if ($registrationEnd === '') {
            $errors['registration_end'] = 'Registration end date is required.';
        } elseif (!$this->isValidDateTime($registrationEnd)) {
            $errors['registration_end'] = 'Registration end must be a valid date and time.';
        }

        if ($eventStartDate === '') {
            $errors['event_start_date'] = 'Event start date is required.';
        } elseif (!$this->isValidDate($eventStartDate)) {
            $errors['event_start_date'] = 'Event start date must be in YYYY-MM-DD format.';
        }

        if ($eventEndDate === '') {
            $errors['event_end_date'] = 'Event end date is required.';
        } elseif (!$this->isValidDate($eventEndDate)) {
            $errors['event_end_date'] = 'Event end date must be in YYYY-MM-DD format.';
        }

        // Cross-field date validations
        if (empty($errors['registration_start']) && empty($errors['registration_end'])
            && $this->isValidDateTime($registrationStart) && $this->isValidDateTime($registrationEnd)
        ) {
            if (strtotime($registrationStart) >= strtotime($registrationEnd)) {
                $errors['registration_end'] = 'Registration end must be after registration start.';
            }
        }

        if (empty($errors['registration_end']) && empty($errors['event_start_date'])
            && $this->isValidDateTime($registrationEnd) && $this->isValidDate($eventStartDate)
        ) {
            $regEndDate = date('Y-m-d', strtotime($registrationEnd));
            if ($regEndDate > $eventStartDate) {
                $errors['event_start_date'] = 'Event start must be on or after the registration end date.';
            }
        }

        if (empty($errors['event_start_date']) && empty($errors['event_end_date'])
            && $this->isValidDate($eventStartDate) && $this->isValidDate($eventEndDate)
        ) {
            if ($eventStartDate > $eventEndDate) {
                $errors['event_end_date'] = 'Event end date must be on or after event start date.';
            }
        }

        return $errors;
    }

    /**
     * -----------------------------------------------------------------------
     * Validate uploaded files (banner only at creation time).
     *
     * @param array $files  $_FILES
     *
     * @return array<string, string>
     * -----------------------------------------------------------------------
     */
    public function validateFiles(array $files): array
    {
        return [];
    }

    /**
     * Validate a status value.
     *
     * @param string $status
     * @param array  $allowedStatuses
     *
     * @return string|null
     */
    public function validateStatus(string $status, array $allowedStatuses): ?string
    {
        if (!in_array($status, $allowedStatuses, true)) {
            return 'Please select a valid symposium status.';
        }

        return null;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function validateUploadedFile(
        array $file,
        array $allowedExtensions,
        int $maxFileSize,
        string $label
    ): ?string {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            return "$label exceeds the maximum allowed file size of 2 MB.";
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return "$label upload failed. Please try again.";
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            $ext = implode(', ', array_map('strtoupper', $allowedExtensions));
            return "$label must be a $ext file.";
        }

        if ($file['size'] > $maxFileSize) {
            return "$label exceeds the maximum allowed file size of 2 MB.";
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            if (@getimagesize($file['tmp_name']) === false) {
                return "$label must be a valid image file.";
            }
        }

        return null;
    }

    private function isValidDateTime(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)
            && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)
            && !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)
        ) {
            return false;
        }

        return strtotime($value) !== false;
    }

    private function isValidDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        return strtotime($value) !== false;
    }
}
