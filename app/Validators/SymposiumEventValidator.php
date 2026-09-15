<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : SymposiumEventValidator.php
 * Location    : app/Validators/
 * Description : Server-side input validation for Symposium Scheduled Events.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Validate date, time, venue, and coordinator inputs
 * • Enforce end_time > start_time
 * • Enforce registration_end > registration_start
 * • Return structured field error messages for forms
 *
 * -------------------------------------------------------------------------
 */

namespace App\Validators;

final class SymposiumEventValidator
{
    /**
     * Validate symposium event scheduling data.
     *
     * @param array $data
     * @param bool $isCreate
     * @return array Array of field => error message strings. Empty array means valid.
     */
    public function validate(array $data, bool $isCreate = true): array
    {
        $errors = [];

        // 1. Master Event Selection (on Create)
        if ($isCreate && empty($data['event_id'])) {
            $errors['event_id'] = 'Please select a Master Event template.';
        }

        // 2. Venue Selection (Optional during creation)
        // Venue can be assigned later by the coordinator.

        // 3. Event Date (Optional)
        $eventDate = trim($data['event_date'] ?? '');

        // 4. Start & End Times (Optional)
        $startTime = trim($data['start_time'] ?? '');
        $endTime   = trim($data['end_time'] ?? '');

        if ($startTime !== '' && $endTime !== '' && $endTime <= $startTime) {
            $errors['end_time'] = 'End time must be later than start time.';
        }

        // 5. Registration Start & End (Optional)
        $regStart = trim($data['registration_start'] ?? '');
        $regEnd   = trim($data['registration_end'] ?? '');

        if ($regStart !== '' && $regEnd !== '' && $regEnd <= $regStart) {
            $errors['registration_end'] = 'Registration end date must be later than registration start date.';
        }

        // Enforce registration_end < event_start_datetime (Req #8)
        if ($regEnd !== '' && $eventDate !== '' && $startTime !== '') {
            $eventStartDateTime = $eventDate . ' ' . $startTime;
            if (strtotime($regEnd) >= strtotime($eventStartDateTime)) {
                $errors['registration_end'] = 'Registration deadline must end before the event start time (' . date('M d, Y h:i A', strtotime($eventStartDateTime)) . ').';
            }
        }

        // 6. Max Participants
        if (!empty($data['max_participants'])) {
            $maxPart = (int)$data['max_participants'];
            if ($maxPart <= 0) {
                $errors['max_participants'] = 'Maximum participants limit must be greater than 0.';
            }
        }

        return $errors;
    }
}
