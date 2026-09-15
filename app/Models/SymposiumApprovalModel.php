<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : SymposiumApprovalModel.php
 * Location    : app/Models/
 * Description : Manages symposium approval records.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Create pending approval records (upfront when submitted)
 * • Update an approval record when HOD/Principal acts
 * • Query pending approvals for HODs and Principal
 * • Get the full approval history for a symposium
 *
 * NOTE
 * -------------------------------------------------------------------------
 * The approval_order field drives the generic approval engine:
 *   1, 2, ... N = HOD approvals (one per organizing department)
 *   99          = Principal approval
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use PDO;

final class SymposiumApprovalModel extends BaseModel
{
    /**
     * -----------------------------------------------------------------------
     * Insert a new approval record in Pending state.
     *
     * Called when a symposium is submitted to pre-populate the queue.
     *
     * @param array $data Keys: symposium_id, approval_level, approval_order,
     *                          department_id (nullable), approver_user_id (nullable)
     *
     * @return bool
     * -----------------------------------------------------------------------
     */
    public function createPending(array $data): bool
    {
        $sql = '
            INSERT INTO symposium_approvals
                (symposium_id, approval_level, approval_stage, approval_order, approver_user_id, department_id, status)
            VALUES
                (:symposium_id, :approval_level, :approval_stage, :approval_order, :approver_user_id, :department_id, \'Pending\')
        ';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'symposium_id'      => $data['symposium_id'],
            'approval_level'    => $data['approval_level'],
            'approval_stage'    => $data['approval_stage'],
            'approval_order'    => $data['approval_order'],
            'approver_user_id'  => $data['approver_user_id'] ?? null,
            'department_id'     => $data['department_id'] ?? null,
        ]);
    }

    /**
     * -----------------------------------------------------------------------
     * Update an approval record when the approver acts (Approve / Revision).
     *
     * @param int   $approvalId
     * @param array $data  Keys: status, approver_user_id, remarks, ip_address
     *
     * @return bool
     * -----------------------------------------------------------------------
     */
    public function updateApproval(int $approvalId, array $data): bool
    {
        $sql = '
            UPDATE symposium_approvals
            SET
                status           = :status,
                approver_user_id = :approver_user_id,
                remarks          = :remarks,
                ip_address       = :ip_address,
                approved_at      = NOW()
            WHERE approval_id = :approval_id
        ';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'status'           => $data['status'],
            'approver_user_id' => $data['approver_user_id'],
            'remarks'          => $data['remarks'] ?? null,
            'ip_address'       => $data['ip_address'] ?? null,
            'approval_id'      => $approvalId,
        ]);
    }

    /**
     * -----------------------------------------------------------------------
     * Find the pending HOD approval record for a specific user.
     *
     * Used when an HOD tries to approve — checks that they have a pending
     * record in the queue for this symposium.
     *
     * @param int $symposiumId
     * @param int $userId
     *
     * @return array|false
     * -----------------------------------------------------------------------
     */
    public function getPendingApprovalForUser(int $symposiumId, int $userId): array|false
    {
        $sql = '
            SELECT *
            FROM symposium_approvals
            WHERE symposium_id      = :symposium_id
              AND approver_user_id  = :user_id
              AND approval_level    = \'HOD\'
              AND status            = \'Pending\'
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'symposium_id' => $symposiumId,
            'user_id'      => $userId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * -----------------------------------------------------------------------
     * Find a pending HOD approval for a user's department (by dept_id).
     *
     * Fallback when approver_user_id was not pre-assigned.
     *
     * @param int $symposiumId
     * @param int $departmentId
     *
     * @return array|false
     * -----------------------------------------------------------------------
     */
    public function getPendingApprovalForDepartment(int $symposiumId, int $departmentId): array|false
    {
        $sql = '
            SELECT *
            FROM symposium_approvals
            WHERE symposium_id  = :symposium_id
              AND department_id = :department_id
              AND approval_level = \'HOD\'
              AND status        = \'Pending\'
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'symposium_id'  => $symposiumId,
            'department_id' => $departmentId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * -----------------------------------------------------------------------
     * Find the pending Principal approval record.
     *
     * @param int $symposiumId
     *
     * @return array|false
     * -----------------------------------------------------------------------
     */
    public function getPendingPrincipalApproval(int $symposiumId): array|false
    {
        $sql = '
            SELECT *
            FROM symposium_approvals
            WHERE symposium_id   = :symposium_id
              AND approval_level = \'Principal\'
              AND status         = \'Pending\'
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * -----------------------------------------------------------------------
     * Count how many HOD approval records are still Pending.
     *
     * When this returns 0, all HODs have approved → escalate to Principal.
     *
     * @param int $symposiumId
     *
     * @return int
     * -----------------------------------------------------------------------
     */
    public function countPendingHodApprovals(int $symposiumId): int
    {
        $sql = '
            SELECT COUNT(*)
            FROM symposium_approvals
            WHERE symposium_id   = :symposium_id
              AND approval_level = \'HOD\'
              AND status         = \'Pending\'
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * -----------------------------------------------------------------------
     * Delete all approval records for a symposium.
     *
     * Called when the workflow resets (Revision Required → resubmit).
     *
     * @param int $symposiumId
     *
     * @return bool
     * -----------------------------------------------------------------------
     */
    public function deleteForSymposium(int $symposiumId): bool
    {
        $sql = 'DELETE FROM symposium_approvals WHERE symposium_id = :symposium_id';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute(['symposium_id' => $symposiumId]);
    }

    /**
     * -----------------------------------------------------------------------
     * Get the complete approval history for a symposium (for display).
     *
     * Ordered by approval_order ASC.
     *
     * @param int $symposiumId
     *
     * @return array
     * -----------------------------------------------------------------------
     */
    public function getApprovalsForSymposium(int $symposiumId): array
    {
        $sql = '
            SELECT
                a.approval_id,
                a.approval_level,
                a.approval_stage,
                a.approval_order,
                a.status,
                a.remarks,
                a.ip_address,
                a.approved_at,
                a.created_at,
                a.department_id,
                u.full_name   AS approver_name,
                u.role        AS approver_role,
                u.signature_path,
                d.department_name
            FROM symposium_approvals a
            LEFT JOIN users u       ON u.user_id       = a.approver_user_id
            LEFT JOIN departments d ON d.department_id = a.department_id
            WHERE a.symposium_id = :symposium_id
            ORDER BY a.approval_order ASC, a.created_at ASC
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -----------------------------------------------------------------------
     * Legacy: create a completed approval record directly.
     *
     * Kept for backward compatibility with older code paths.
     *
     * @param array $data
     *
     * @return bool
     * -----------------------------------------------------------------------
     */
    public function create(array $data): bool
    {
        $sql = '
            INSERT INTO symposium_approvals
                (symposium_id, approval_level, approval_order, approver_user_id,
                 department_id, status, remarks, approved_at)
            VALUES
                (:symposium_id, :approval_level, :approval_order, :approver_user_id,
                 :department_id, :status, :remarks, NOW())
        ';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'symposium_id'     => $data['symposium_id'],
            'approval_level'   => $data['approval_level'],
            'approval_order'   => $data['approval_order'] ?? 1,
            'approver_user_id' => $data['approver_user_id'],
            'department_id'    => $data['department_id'] ?? null,
            'status'           => $data['status'],
            'remarks'          => $data['remarks'] ?? null,
        ]);
    }
}
