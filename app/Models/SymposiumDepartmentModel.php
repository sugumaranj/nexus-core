<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore
 * =========================================================================
 * File        : SymposiumDepartmentModel.php
 * Location    : app/Models/
 * Description : Manages the many-to-many relationship between
 *               symposiums and their organizing departments.
 *
 * =========================================================================
 * ARCHITECTURAL PRINCIPLE — CANONICAL SOURCE OF TRUTH
 * =========================================================================
 * The `symposium_departments` table IS the single source of truth for
 * all organizing department information across the entire NexusCore EMS.
 *
 * Every module that needs to know "which department(s) organize this
 * symposium" MUST use this model. The legacy column
 * `symposiums.organizing_department_id` is kept ONLY as a backward-
 * compatibility FK and MUST NOT be used for any business logic.
 *
 * Modules required to use symposium_departments (this model):
 * ─────────────────────────────────────────────────────────────
 *   ✓ Symposium Create / Edit   (SymposiumService → syncDepartments)
 *   ✓ Symposium View            (SymposiumModel  → GROUP_CONCAT join)
 *   ✓ Symposium List / Search   (SymposiumModel  → GROUP_CONCAT join)
 *   ✓ Approval Workflow         (SymposiumService → getHodsForSymposium)
 *   ✓ PDF Circular / Brochure   (PdfDocumentService → this model)
 *   ✓ Competition Edit Auth     (CompetitionService → isDepartmentOrganizer)
 *   ✓ Competition List Scope    (CompetitionModel   → EXISTS subquery)
 *   ✓ Dashboard Scope           (CompetitionModel   → INNER JOIN)
 *   ✓ Registration Gate         (RegistrationService → isDepartmentOrganizer)
 *   ✓ Notifications             (SymposiumService → getHodsForSymposium)
 *   ✓ Staff Coordinator View    (CompetitionCoordinatorModel → sd join)
 *
 * Responsibilities of this model
 * ─────────────────────────────────────────────────────────────
 *   • Insert organizing departments for a symposium
 *   • Sync (replace) the full department list for a symposium
 *   • Retrieve departments for a symposium (with names/codes)
 *   • Retrieve HOD users for a symposium via department_approvers
 *   • Check if a given department is an organizer (isDepartmentOrganizer)
 *   • Get flat list of department IDs
 *   • Delete all department links for a symposium
 *
 * =========================================================================
 */

namespace App\Models;

use PDO;

final class SymposiumDepartmentModel extends BaseModel
{
    /**
     * -----------------------------------------------------------------------
     * Link a department to a symposium.
     *
     * @param int    $symposiumId
     * @param int    $departmentId
     * @param string $role         e.g. 'Organizer', 'Co-Organizer'
     *
     * @return bool
     * -----------------------------------------------------------------------
     */
    public function add(int $symposiumId, int $departmentId, string $role = 'Organizer'): bool
    {
        $sql = '
            INSERT IGNORE INTO symposium_departments
                (symposium_id, department_id, department_role)
            VALUES
                (:symposium_id, :department_id, :role)
        ';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'symposium_id'  => $symposiumId,
            'department_id' => $departmentId,
            'role'          => $role,
        ]);
    }

    /**
     * -----------------------------------------------------------------------
     * Replace all organizing departments for a symposium.
     *
     * Deletes existing and inserts the new list in one call.
     *
     * @param int   $symposiumId
     * @param array $departmentIds  Array of department_id integers
     * @param string $role
     *
     * @return bool
     * -----------------------------------------------------------------------
     */
    public function syncDepartments(int $symposiumId, array $departmentIds, string $role = 'Organizer'): bool
    {
        $this->deleteForSymposium($symposiumId);

        foreach ($departmentIds as $deptId) {
            if (!$this->add($symposiumId, (int) $deptId, $role)) {
                return false;
            }
        }

        return true;
    }

    /**
     * -----------------------------------------------------------------------
     * Get all organizing departments for a symposium.
     *
     * @param int $symposiumId
     *
     * @return array  Each row has: department_id, department_name,
     *                              department_code, department_role
     * -----------------------------------------------------------------------
     */
    public function getDepartmentsForSymposium(int $symposiumId): array
    {
        $sql = '
            SELECT
                sd.id,
                sd.department_id,
                sd.department_role,
                d.department_name,
                d.department_code
            FROM symposium_departments sd
            JOIN departments d ON d.department_id = sd.department_id
            WHERE sd.symposium_id = :symposium_id
            ORDER BY sd.id ASC
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -----------------------------------------------------------------------
     * Get the HOD user(s) for each organizing department of a symposium.
     *
     * Returns one HOD per department.
     *
     * @param int $symposiumId
     *
     * @return array  Each row has: user_id, full_name, email,
     *                              department_id, department_name
     * -----------------------------------------------------------------------
     */
    public function getHodsForSymposium(int $symposiumId): array
    {
        $sql = "
            SELECT
                u.user_id,
                u.full_name,
                u.email,
                u.signature_path,
                d.department_id,
                d.department_name,
                d.department_code,
                sd.department_role
            FROM symposium_departments sd
            JOIN departments d ON d.department_id = sd.department_id
            LEFT JOIN department_approvers da ON da.department_id = d.department_id
            JOIN users u
                ON u.department_id = COALESCE(da.approver_department_id, d.department_id)
                AND u.role = 'HOD'
                AND u.account_status = 'Active'
            WHERE sd.symposium_id = :symposium_id
            ORDER BY sd.id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -----------------------------------------------------------------------
     * Check if a given department is one of the organizing departments
     * for a specific symposium.
     *
     * @param int $symposiumId
     * @param int $departmentId
     *
     * @return bool
     * -----------------------------------------------------------------------
     */
    public function isDepartmentOrganizer(int $symposiumId, int $departmentId): bool
    {
        $sql = '
            SELECT COUNT(*) FROM symposium_departments
            WHERE symposium_id = :symposium_id
              AND department_id = :department_id
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'symposium_id'  => $symposiumId,
            'department_id' => $departmentId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * -----------------------------------------------------------------------
     * Delete all organizing department links for a symposium.
     *
     * @param int $symposiumId
     *
     * @return bool
     * -----------------------------------------------------------------------
     */
    public function deleteForSymposium(int $symposiumId): bool
    {
        $sql = 'DELETE FROM symposium_departments WHERE symposium_id = :symposium_id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute(['symposium_id' => $symposiumId]);
    }

    /**
     * -----------------------------------------------------------------------
     * Get department IDs for a symposium (flat array).
     *
     * @param int $symposiumId
     *
     * @return array<int>
     * -----------------------------------------------------------------------
     */
    public function getDepartmentIds(int $symposiumId): array
    {
        $sql = '
            SELECT department_id
            FROM symposium_departments
            WHERE symposium_id = :symposium_id
            ORDER BY id ASC
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
