<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : TeamModel.php
 * Location    : app/Models/
 * Description : Database operations for the teams table.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use App\Database\Database;
use PDO;

final class TeamModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Create a new team.
     *
     * @param array $data Keys: application_id, creator_student_id
     * @return int Team ID
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO teams (application_id, manager_student_id)
            VALUES (:application_id, :creator_student_id)
        ";

        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            'application_id'     => $data['application_id'],
            'creator_student_id' => $data['creator_student_id'],
        ]);

        if (!$ok) {
            return 0;
        }

        return (int) $this->db->lastInsertId();
    }

    /**
     * Find a team by its ID.
     *
     * @param int $teamId
     * @return array|false
     */
    public function findById(int $teamId): array|false
    {
        $sql = "
            SELECT
                t.*,
                a.application_no,
                a.symposium_event_id,
                a.student_id AS creator_student_id
            FROM teams t
            INNER JOIN applications a ON a.application_id = t.application_id
            WHERE t.team_id = :team_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['team_id' => $teamId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find a team by application ID.
     *
     * @param int $applicationId
     * @return array|false
     */
    public function findByApplicationId(int $applicationId): array|false
    {
        $sql = "
            SELECT
                t.*,
                a.application_no,
                a.symposium_event_id,
                a.student_id AS creator_student_id
            FROM teams t
            INNER JOIN applications a ON a.application_id = t.application_id
            WHERE t.application_id = :application_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['application_id' => $applicationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find the team for a student's event registration.
     *
     * @param int $studentId The creator's student ID
     * @param int $symposiumEventId
     * @return array|false
     */
    public function findByCreatorAndEvent(int $studentId, int $symposiumEventId): array|false
    {
        $sql = "
            SELECT t.*
            FROM teams t
            INNER JOIN applications a ON a.application_id = t.application_id
            WHERE a.student_id = :student_id
              AND a.symposium_event_id = :symposium_event_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'student_id' => $studentId,
            'symposium_event_id' => $symposiumEventId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get the current number of members in a team.
     *
     * @param int $teamId
     * @return int
     */
    public function getMemberCount(int $teamId): int
    {
        $sql = "SELECT COUNT(*) AS total FROM team_members WHERE team_id = :team_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['team_id' => $teamId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Get all teams for a specific symposium event.
     *
     * @param int $symposiumEventId
     * @return array
     */
    public function findBySymposiumEvent(int $symposiumEventId): array
    {
        $sql = "
            SELECT
                t.*,
                a.application_no,
                a.student_id AS creator_student_id
            FROM teams t
            INNER JOIN applications a ON a.application_id = t.application_id
            WHERE a.symposium_event_id = :symposium_event_id
              AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
            ORDER BY t.team_id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_event_id' => $symposiumEventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
