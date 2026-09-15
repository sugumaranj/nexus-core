<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : AuditLogModel.php
 * Location    : app/Models/
 * Description : Handles all audit log database operations.
 *
 * Fix (Issue 1):
 *   - Replaced invalid PDO::query($sql, $params) calls with
 *     proper prepare/execute prepared statements.
 *   - Aligned INSERT/SELECT columns with the actual audit_logs table:
 *     audit_log_id, user_id, action, module_name, table_name,
 *     record_id, ip_address, user_agent, action_time
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use PDO;

final class AuditLogModel extends BaseModel
{
    /**
     * Log an action.
     *
     * Maps the service-layer intent (entityType, entityId, action, userId, description)
     * onto the actual audit_logs columns: module_name, record_id, action, user_id.
     *
     * @param string      $entityType   Entity type (e.g. 'Symposium', 'Competition')
     * @param int         $entityId     The entity's primary key
     * @param string      $action       Action description
     * @param int|null    $userId       User who performed the action
     * @param string|null $description  Optional details / remarks
     *
     * @return bool
     */
    public function log(
        string $entityType,
        int $entityId,
        string $action,
        ?int $userId = null,
        ?string $description = null
    ): bool {
        // If description is provided, append it to the action string
        // since the actual table has no separate description column.
        $actionText = $action;
        if ($description !== null && trim($description) !== '') {
            $actionText .= ': ' . trim($description);
        }

        $sql = "INSERT INTO audit_logs
                    (user_id, action, table_name, record_id, ip_address, user_agent)
                VALUES
                    (:user_id, :action, :table_name, :record_id, :ip_address, :user_agent)";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'user_id'     => $userId,
            'action'      => $actionText,
            'table_name'  => $entityType,
            'record_id'   => $entityId,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    /**
     * Get logs for an entity.
     *
     * @param string $entityType   Entity type used as table_name
     * @param int    $entityId     Primary key used as record_id
     *
     * @return array
     */
    public function getLogsForEntity(string $entityType, int $entityId): array
    {
        $sql = "SELECT
                    l.*,
                    u.full_name AS user_name,
                    u.role      AS user_role
                FROM audit_logs l
                LEFT JOIN users u ON l.user_id = u.user_id
                WHERE l.table_name = :table_name
                  AND l.record_id  = :record_id
                ORDER BY l.action_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'table_name' => $entityType,
            'record_id'  => $entityId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
