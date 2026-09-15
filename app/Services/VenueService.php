<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : VenueService.php
 * Location    : app/Services/
 * Description : Business logic for Venue Management.
 *
 * Responsibilities
 * ----------------
 * • Validate business rules
 * • Prevent duplicate venue codes
 * • Prevent deletion if in use
 * • Call VenueModel
 * • Return meaningful responses
 *
 * NOTE
 * ----
 * This class DOES NOT contain SQL.
 * SQL belongs only inside VenueModel.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Models\VenueModel;

final class VenueService
{
    private VenueModel $venueModel;

    public function __construct()
    {
        $this->venueModel = new VenueModel();
    }

    /**
     * ---------------------------------------------------------------------
     * Get all venues.
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getAllVenues(): array
    {
        return $this->venueModel->getAll();
    }

    /**
     * ---------------------------------------------------------------------
     * Search venues.
     *
     * @param string $search
     * @param string $status
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function searchVenues(string $search, string $status): array
    {
        return $this->venueModel->searchVenues($search, $status);
    }

    /**
     * ---------------------------------------------------------------------
     * Get venue by ID.
     *
     * @param int $venueId
     *
     * @return array|false
     * ---------------------------------------------------------------------
     */
    public function getVenueById(int $venueId): array|false
    {
        return $this->venueModel->findById($venueId);
    }

    /**
     * ---------------------------------------------------------------------
     * Create a new venue.
     *
     * Business Rules:
     * • Venue code must be unique.
     *
     * @param array $data
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function createVenue(array $data): array
    {
        $data['venue_code'] = strtoupper(trim($data['venue_code'] ?? ''));
        $data['venue_name'] = trim($data['venue_name'] ?? '');
        $data['building_name'] = trim($data['building_name'] ?? '');

        if ($this->venueModel->existsCode($data['venue_code'])) {
            return [
                'success' => false,
                'message' => 'Venue code already exists.'
            ];
        }

        $created = $this->venueModel->create($data);

        if (!$created) {
            return [
                'success' => false,
                'message' => 'Unable to create venue.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Venue created successfully.'
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * Update Venue.
     *
     * Business Rules:
     * • Venue code must be unique (excluding self).
     *
     * @param int   $venueId
     * @param array $data
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function updateVenue(int $venueId, array $data): array
    {
        $data['venue_code'] = strtoupper(trim($data['venue_code'] ?? ''));
        $data['venue_name'] = trim($data['venue_name'] ?? '');
        $data['building_name'] = trim($data['building_name'] ?? '');

        if ($this->venueModel->existsCode($data['venue_code'], $venueId)) {
            return [
                'success' => false,
                'message' => 'Venue code already exists for another venue.'
            ];
        }

        $updated = $this->venueModel->update($venueId, $data);

        if (!$updated) {
            return [
                'success' => false,
                'message' => 'Unable to update venue.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Venue updated successfully.'
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * Delete Venue.
     *
     * Business Rules:
     * • Cannot delete if venue is in use by Competitions.
     *
     * @param int $venueId
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function deleteVenue(int $venueId): array
    {
        $venue = $this->venueModel->findById($venueId);

        if (!$venue) {
            return [
                'success' => false,
                'message' => 'Venue not found.'
            ];
        }

        if ($this->venueModel->isInUse($venueId)) {
            return [
                'success' => false,
                'message' => 'Venue cannot be deleted because it is assigned to one or more competitions. Please deactivate it instead.'
            ];
        }

        $deleted = $this->venueModel->delete($venueId);

        if (!$deleted) {
            return [
                'success' => false,
                'message' => 'Unable to delete venue.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Venue deleted successfully.'
        ];
    }
}
