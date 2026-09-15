<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : TeamMemberModel.php
 * Location    : app/Models/
 * Description : Database operations for the team_members table.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use App\Database\Database;
use PDO;

final class TeamMemberModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Add a member to a team.
     *
     * @param int $teamId
     * @param int $studentId
     * @param string $role Defaults to 'Member'
     * @return bool
     */
    public function add(int $teamId, int $studentId): bool
    {
        $sql = "
            INSERT INTO team_members (team_id, student_id)
            VALUES (:team_id, :student_id)
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'team_id'     => $teamId,
            'student_id'  => $studentId,
        ]);
    }

    /**
     * Check if a student is already in a specific team.
     *
     * @param int $teamId
     * @param int $studentId
     * @return bool
     */
    public function isStudentInTeam(int $teamId, int $studentId): bool
    {
        $sql = "
            SELECT 1
            FROM team_members
            WHERE team_id = :team_id
              AND student_id = :student_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'team_id'    => $teamId,
            'student_id' => $studentId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Check if a student is the creator of the application for this team.
     *
     * @param int $teamId
     * @param int $creatorStudentId
     * @return bool
     */
    public function isCreator(int $teamId, int $creatorStudentId): bool
    {
        $sql = "
            SELECT 1
            FROM teams t
            INNER JOIN applications a ON t.application_id = a.application_id
            WHERE t.team_id = :team_id
              AND a.student_id = :creator_student_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'team_id'            => $teamId,
            'creator_student_id' => $creatorStudentId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Get all members of a team.
     *
     * @param int $teamId
     * @return array
     */
    public function getByTeam(int $teamId): array
    {
        $sql = "
            SELECT
                tm.team_member_id,
                tm.team_id,
                tm.student_id,
                tm.joined_at,
                s.full_name       AS member_name,
                s.register_number AS member_register_number,
                s.email           AS member_email,
                s.phone           AS member_phone,
                s.gender          AS member_gender,
                s.academic_year   AS member_year,
                d.department_name AS member_department
            FROM team_members tm
            INNER JOIN students    s ON s.student_id    = tm.student_id
            INNER JOIN departments d ON d.department_id = s.department_id
            WHERE tm.team_id = :team_id
            ORDER BY s.full_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['team_id' => $teamId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Remove a member from a team.
     *
     * @param int $teamId
     * @param int $studentId
     * @return bool
     */
    public function remove(int $teamId, int $studentId): bool
    {
        $sql = "
            DELETE FROM team_members
            WHERE team_id = :team_id
              AND student_id = :student_id
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'team_id'    => $teamId,
            'student_id' => $studentId,
        ]);
    }

    /**
     * Check if a student is already in ANY team for a specific symposium event.
     *
     * @param int $studentId
     * @param int $symposiumEventId
     * @return bool
     */
    public function isStudentInAnyTeamForEvent(int $studentId, int $symposiumEventId): bool
    {
        $sql = "
            SELECT 1
            FROM team_members tm
            INNER JOIN teams t ON tm.team_id = t.team_id
            INNER JOIN applications a ON t.application_id = a.application_id
            WHERE tm.student_id = :student_id
              AND a.symposium_event_id = :symposium_event_id
              AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'student_id'         => $studentId,
            'symposium_event_id' => $symposiumEventId,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}
