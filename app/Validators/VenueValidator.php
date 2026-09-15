<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : VenueValidator.php
 * Location    : app/Validators/
 * Description : Validates Venue input data.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Validators;

final class VenueValidator
{
    /**
     * Validate Venue Data.
     *
     * @param array $data
     * @return array Array of errors. Empty if valid.
     */
    public function validate(array $data): array
    {
        $errors = [];

        $venueCode = trim($data['venue_code'] ?? '');
        if ($venueCode === '') {
            $errors['venue_code'] = 'Venue code is required.';
        } elseif (strlen($venueCode) > 20) {
            $errors['venue_code'] = 'Venue code cannot exceed 20 characters.';
        }

        $venueName = trim($data['venue_name'] ?? '');
        if ($venueName === '') {
            $errors['venue_name'] = 'Venue name is required.';
        } elseif (strlen($venueName) > 120) {
            $errors['venue_name'] = 'Venue name cannot exceed 120 characters.';
        }

        $buildingName = trim($data['building_name'] ?? '');
        if ($buildingName === '') {
            $errors['building_name'] = 'Building name is required.';
        } elseif (strlen($buildingName) > 100) {
            $errors['building_name'] = 'Building name cannot exceed 100 characters.';
        }

        $seatingCapacity = trim($data['seating_capacity'] ?? '');
        if ($seatingCapacity === '' || !is_numeric($seatingCapacity) || (int)$seatingCapacity < 0) {
            $errors['seating_capacity'] = 'Valid seating capacity is required.';
        }

        return $errors;
    }
}
