<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : VenueModel.php
 * Location    : app/Models/
 * Description : Handles all Venue database operations.
 *
 * Responsibilities
 * ----------------
 * • Retrieve venues
 * • Retrieve one venue
 * • Check duplicate venue
 * • Insert venue
 * • Update venue
 * • Delete venue
 * • Check if venue is in use
 *
 * NOTE:
 * -----
 * This class ONLY communicates with the database.
 * Business rules belong in VenueService.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use PDO;

final class VenueModel extends BaseModel
{
    /**
     * ---------------------------------------------------------------------
     * Get all venues.
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getAll(): array
    {
        $sql = "
            SELECT *
            FROM venues
            ORDER BY building_name ASC, venue_name ASC
        ";

        $statement = $this->db->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ---------------------------------------------------------------------
     * Get all active venues.
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getAllActive(): array
    {
        $sql = "
            SELECT *
            FROM venues
            WHERE is_active = 1
            ORDER BY building_name ASC, venue_name ASC
        ";

        $statement = $this->db->prepare($sql);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
        $sql = "
            SELECT *
            FROM venues
            WHERE 1=1
        ";
        
        $params = [];

        if ($search !== '') {
            $sql .= " AND (venue_name LIKE :search_name OR venue_code LIKE :search_code OR building_name LIKE :search_build)";
            $params[':search_name'] = '%' . $search . '%';
            $params[':search_code'] = '%' . $search . '%';
            $params[':search_build'] = '%' . $search . '%';
        }

        if ($status !== '') {
            $sql .= " AND is_active = :status";
            $params[':status'] = (int) $status;
        }

        $sql .= " ORDER BY building_name ASC, venue_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ---------------------------------------------------------------------
     * Find venue by ID.
     *
     * @param int $venueId
     *
     * @return array|false
     * ---------------------------------------------------------------------
     */
    public function findById(int $venueId): array|false
    {
        $sql = "
            SELECT *
            FROM venues
            WHERE venue_id = :venue_id
        ";

        $statement = $this->db->prepare($sql);
        $statement->bindValue(':venue_id', $venueId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ---------------------------------------------------------------------
     * Find venue using venue code.
     *
     * @param string $venueCode
     *
     * @return array|false
     * ---------------------------------------------------------------------
     */
    public function findByCode(string $venueCode): array|false
    {
        $sql = "
            SELECT *
            FROM venues
            WHERE venue_code = :venue_code
        ";

        $statement = $this->db->prepare($sql);
        $statement->bindValue(':venue_code', strtoupper($venueCode));
        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ---------------------------------------------------------------------
     * Create Venue.
     *
     * @param array $data
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO venues
            (
                venue_code,
                venue_name,
                building_name,
                floor,
                seating_capacity,
                is_computer_lab,
                is_active
            )
            VALUES
            (
                :venue_code,
                :venue_name,
                :building_name,
                :floor,
                :seating_capacity,
                :is_computer_lab,
                :is_active
            )
        ";

        $statement = $this->db->prepare($sql);

        return $statement->execute([
            ':venue_code'      => strtoupper($data['venue_code']),
            ':venue_name'      => trim($data['venue_name']),
            ':building_name'   => trim($data['building_name']),
            ':floor'           => trim($data['floor'] ?? ''),
            ':seating_capacity'=> (int) $data['seating_capacity'],
            ':is_computer_lab' => (int) $data['is_computer_lab'],
            ':is_active'       => (int) $data['is_active']
        ]);
    }

    /**
     * ---------------------------------------------------------------------
     * Update Venue.
     *
     * @param int   $venueId
     * @param array $data
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function update(int $venueId, array $data): bool
    {
        $sql = "
            UPDATE venues
            SET
                venue_code       = :venue_code,
                venue_name       = :venue_name,
                building_name    = :building_name,
                floor            = :floor,
                seating_capacity = :seating_capacity,
                is_computer_lab  = :is_computer_lab,
                is_active        = :is_active
            WHERE
                venue_id = :venue_id
        ";

        $statement = $this->db->prepare($sql);

        return $statement->execute([
            ':venue_code'      => strtoupper($data['venue_code']),
            ':venue_name'      => trim($data['venue_name']),
            ':building_name'   => trim($data['building_name']),
            ':floor'           => trim($data['floor'] ?? ''),
            ':seating_capacity'=> (int) $data['seating_capacity'],
            ':is_computer_lab' => (int) $data['is_computer_lab'],
            ':is_active'       => (int) $data['is_active'],
            ':venue_id'        => $venueId
        ]);
    }

    /**
     * ---------------------------------------------------------------------
     * Delete Venue.
     *
     * @param int $venueId
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function delete(int $venueId): bool
    {
        $sql = "
            DELETE FROM venues
            WHERE venue_id = :venue_id
        ";

        $statement = $this->db->prepare($sql);
        $statement->bindValue(':venue_id', $venueId, PDO::PARAM_INT);

        return $statement->execute();
    }

    /**
     * ---------------------------------------------------------------------
     * Check if a Venue code exists, excluding a specific ID.
     *
     * @param string $code
     * @param int    $excludeId
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function existsCode(string $code, int $excludeId = 0): bool
    {
        $sql = "
            SELECT 1 FROM venues 
            WHERE venue_code = :code 
            AND venue_id != :exclude_id 
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':code' => strtoupper($code), 
            ':exclude_id' => $excludeId
        ]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * ---------------------------------------------------------------------
     * Check if Venue is in use by Competitions or other entities.
     *
     * @param int $venueId
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function isInUse(int $venueId): bool
    {
        // Check competitions table
        $stmt = $this->db->prepare(
            "SELECT 1 FROM competitions WHERE venue_id = :venue_id LIMIT 1"
        );
        $stmt->bindValue(':venue_id', $venueId, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn()) {
            return true;
        }

        // Check symposium_events table (direct venue assignment on the event row)
        $stmt = $this->db->prepare(
            "SELECT 1 FROM symposium_events WHERE venue_id = :venue_id LIMIT 1"
        );
        $stmt->bindValue(':venue_id', $venueId, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn()) {
            return true;
        }

        // Check event_assignments (Venue-type records)
        $stmt = $this->db->prepare(
            "SELECT 1 FROM event_assignments
             WHERE venue_id = :venue_id AND is_active = 1 LIMIT 1"
        );
        $stmt->bindValue(':venue_id', $venueId, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn()) {
            return true;
        }

        return false;
    }
}
