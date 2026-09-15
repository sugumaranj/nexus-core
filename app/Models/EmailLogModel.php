<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS
 * =========================================================================
 * File        : EmailLogModel.php
 * Location    : app/Models/
 * Description : Permanent audit log of every email send attempt.
 *
 * The email_send_log table is a write-once append log.
 * Every row records the final outcome (Sent / Failed) of one
 * delivery attempt for one recipient.
 *
 * Purpose
 * ─────────────────────────────────────────────────────────────
 *   • Compliance / audit trail that persists even after queue rows
 *     are cleaned up.
 *   • Used by the Admin Email Log view to show sent/failed counts
 *     per symposium per trigger event.
 *
 * =========================================================================
 */

namespace App\Models;

use PDO;

final class EmailLogModel extends BaseModel
{
    /**
     * Append one log entry.
     *
     * @param array $data {
     *     symposium_id, trigger_event, triggered_by,
     *     recipient_email, recipient_name, student_id,
     *     status ('Sent'|'Failed'), error_message
     * }
     *
     * @return bool
     */
    public function append(array $data): bool
    {
        $sql = '
            INSERT INTO email_send_log
                (symposium_id, trigger_event, triggered_by,
                 recipient_email, recipient_name, student_id,
                 status, error_message)
            VALUES
                (:symposium_id, :trigger_event, :triggered_by,
                 :recipient_email, :recipient_name, :student_id,
                 :status, :error_message)
        ';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'symposium_id'    => $data['symposium_id'],
            'trigger_event'   => $data['trigger_event'],
            'triggered_by'    => $data['triggered_by']  ?? null,
            'recipient_email' => $data['recipient_email'],
            'recipient_name'  => $data['recipient_name'] ?? null,
            'student_id'      => $data['student_id']     ?? null,
            'status'          => $data['status'],
            'error_message'   => $data['error_message']  ?? null,
        ]);
    }

    /**
     * Get all log entries for a symposium, newest first.
     *
     * @param int $symposiumId
     * @param int $limit
     *
     * @return array
     */
    public function getBySymposium(int $symposiumId, int $limit = 500): array
    {
        $sql = '
            SELECT *
            FROM   email_send_log
            WHERE  symposium_id = :symposium_id
            ORDER  BY sent_at DESC
            LIMIT  :lim
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':symposium_id', $symposiumId, PDO::PARAM_INT);
        $stmt->bindValue(':lim',          $limit,        PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get summary counts grouped by trigger_event for a symposium.
     *
     * @param int $symposiumId
     *
     * @return array   Each row: trigger_event, sent, failed, total
     */
    public function getSummaryBySymposium(int $symposiumId): array
    {
        $sql = '
            SELECT
                trigger_event,
                SUM(status = \'Sent\')   AS sent,
                SUM(status = \'Failed\') AS failed,
                COUNT(*)                 AS total,
                MAX(sent_at)             AS last_sent_at
            FROM  email_send_log
            WHERE symposium_id = :symposium_id
            GROUP BY trigger_event
            ORDER BY MIN(sent_at) ASC
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
