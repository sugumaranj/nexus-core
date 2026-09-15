<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : CompetitionCoordinatorModel.php
 * Location    : app/Models/
 * Description : Database operations for competition coordinator assignments.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Assign coordinators to a competition (replace-all strategy)
 * • Retrieve coordinators for a competition
 * • Remove all coordinator assignments for a competition
 *
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use App\Database\Database;
use PDO;

final class CompetitionCoordinatorModel
{
    /**
     * PDO database connection.
     *
     * @var PDO
     */
    private PDO $db;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * -------------------------------------------------------------------------
     * Assign coordinators to a competition (replace all existing).
     *
     * Expects $coordinators as an array of:
     *   ['user_id' => int, 'coordinator_type' => string, 'notes' => string|null]
     *
     * Called inside a transaction from CompetitionService.
     *
     * @param int   $competitionId
     * @param array $coordinators
     *
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function assignCoordinators(int $competitionId, array $coordinators): bool
    {
        // Remove existing assignments first
        $this->removeAll($competitionId);

        if (empty($coordinators)) {
            return true;
        }

        $sql = "
            INSERT INTO competition_coordinators
                (competition_id, coordinator_type, user_id, responsibility, notes)
            VALUES
                (:competition_id, :coordinator_type, :user_id, :responsibility, :notes)
        ";

        $stmt = $this->db->prepare($sql);

        // Map coordinator_type to legacy responsibility field for back-compat
        $responsibilityMap = [
            'Staff Coordinator'   => 'Coordinator',
            'Event Coordinator'   => 'Coordinator',
            'Student Coordinator' => 'Coordinator',
            'Judge'               => 'Incharge',
        ];

        foreach ($coordinators as $coordinator) {
            $userId          = (int) ($coordinator['user_id'] ?? 0);
            $coordinatorType = trim((string) ($coordinator['coordinator_type'] ?? 'Staff Coordinator'));
            $notes           = trim((string) ($coordinator['notes'] ?? '')) ?: null;

            if ($userId <= 0) {
                continue;
            }

            $ok = $stmt->execute([
                'competition_id'   => $competitionId,
                'coordinator_type' => $coordinatorType,
                'user_id'          => $userId,
                'responsibility'   => $responsibilityMap[$coordinatorType] ?? 'Coordinator',
                'notes'            => $notes,
            ]);

            if (!$ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * -------------------------------------------------------------------------
     * Retrieve all coordinators for a competition with user details.
     *
     * @param int $competitionId
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getCoordinators(int $competitionId): array
    {
        $sql = "
            SELECT
                cc.coordinator_assignment_id,
                cc.user_id,
                cc.coordinator_type,
                cc.responsibility,
                cc.notes,
                cc.assigned_at,
                u.full_name,
                u.email,
                u.employee_id,
                u.role,
                d.department_name
            FROM competition_coordinators cc
            INNER JOIN users u ON u.user_id = cc.user_id
            LEFT JOIN departments d ON d.department_id = u.department_id
            WHERE cc.competition_id = :competition_id
            ORDER BY cc.coordinator_type ASC, u.full_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':competition_id', $competitionId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Remove all coordinator assignments for a competition.
     *
     * @param int $competitionId
     *
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function removeAll(int $competitionId): bool
    {
        $sql = "
            DELETE FROM competition_coordinators
            WHERE competition_id = :competition_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':competition_id', $competitionId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * -------------------------------------------------------------------------
     * Check whether a user is assigned as coordinator for a competition.
     *
     * Used by RegistrationService to enforce scoped coordinator access:
     * a coordinator may only approve/reject applications for competitions
     * they are explicitly assigned to.
     *
     * @param int $competitionId
     * @param int $userId
     *
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function isAssigned(int $competitionId, int $userId): bool
    {
        $sql = "
            SELECT 1
            FROM competitions c
            LEFT JOIN competition_coordinators cc 
                   ON cc.competition_id = c.competition_id 
                  AND cc.user_id = :user_id_coord
            WHERE c.competition_id = :competition_id
              AND (c.created_by = :user_id_owner OR cc.user_id IS NOT NULL)
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'competition_id'  => $competitionId,
            'user_id_coord'   => $userId,
            'user_id_owner'   => $userId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * -------------------------------------------------------------------------
     * Retrieve all competitions assigned to a coordinator (including stats).
     *
     * @param int $userId
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getAssignedCompetitions(int $userId): array
    {
        $sql = "
            SELECT 
                c.competition_id, 
                c.title, 
                c.competition_code, 
                c.status, 
                c.registration_limit,
                c.registration_deadline,
                s.title AS symposium_name,
                d.department_name,
                COUNT(a.application_id) AS total_registrations,
                SUM(CASE WHEN a.application_status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN a.application_status = 'Approved' THEN 1 ELSE 0 END) AS approved_count,
                SUM(CASE WHEN a.application_status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_count
            FROM competitions c
            INNER JOIN symposiums s ON c.symposium_id = s.symposium_id
            -- ARCHITECTURAL PRINCIPLE: department_name resolved from
            -- symposium_departments (canonical source of truth).
            LEFT JOIN symposium_departments sd ON sd.symposium_id = s.symposium_id
            LEFT JOIN departments d ON d.department_id = sd.department_id
            LEFT JOIN competition_coordinators cc ON c.competition_id = cc.competition_id AND cc.user_id = :user_id_coord
            LEFT JOIN applications a ON c.competition_id = a.competition_id
            WHERE (c.created_by = :user_id_creator OR cc.user_id IS NOT NULL)
            GROUP BY c.competition_id
            ORDER BY c.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id_coord', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id_creator', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
