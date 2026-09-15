<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : SyncQueueModel.php
 * Location    : app/Models/
 * Description : Database operations for the offline_sync_queue table.
 *               Fully migrated to Event-based (Symposium Event) workflow.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Log each offline sync batch submitted to the server
 * • Update the result of a sync operation
 * • Retrieve sync history for a user / Sync Center dashboard
 *
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use PDO;

final class SyncQueueModel extends BaseModel
{
    // =========================================================================
    // CREATE
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Create a new sync queue entry for an Event-based batch.
     *
     * @param int    $symposiumEventId
     * @param int    $userId
     * @param string $payloadHash       SHA-256 of sorted application_id list
     * @param int    $recordsSubmitted
     * @param string $clientDeviceId
     * @return int  queue_id or 0 on failure.
     * -------------------------------------------------------------------------
     */
    public function createEventEntry(
        int    $symposiumEventId,
        int    $userId,
        string $payloadHash,
        int    $recordsSubmitted,
        string $clientDeviceId = ''
    ): int {
        $sql = "
            INSERT INTO offline_sync_queue
                (symposium_event_id, user_id, payload_hash,
                 records_submitted, sync_status, client_device_id)
            VALUES
                (:symposium_event_id, :user_id, :payload_hash,
                 :records_submitted, 'Processing', :client_device_id)
        ";

        $stmt = $this->db->prepare($sql);
        $ok   = $stmt->execute([
            'symposium_event_id' => $symposiumEventId,
            'user_id'            => $userId,
            'payload_hash'       => $payloadHash,
            'records_submitted'  => $recordsSubmitted,
            'client_device_id'   => $clientDeviceId ?: null,
        ]);

        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Update the result of a sync operation.
     *
     * @param int    $queueId
     * @param int    $succeeded
     * @param int    $failed
     * @param int    $conflicts
     * @param string $status       'Completed' | 'Failed' | 'Partial'
     * @param string $errorMsg
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function updateEntry(
        int    $queueId,
        int    $succeeded,
        int    $failed,
        int    $conflicts,
        string $status,
        string $errorMsg = ''
    ): bool {
        $sql = "
            UPDATE offline_sync_queue
            SET
                records_succeeded = :succeeded,
                records_failed    = :failed,
                records_conflict  = :conflicts,
                sync_status       = :status,
                error_message     = :error_message,
                completed_at      = NOW()
            WHERE queue_id = :queue_id
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'succeeded'     => $succeeded,
            'failed'        => $failed,
            'conflicts'     => $conflicts,
            'status'        => $status,
            'error_message' => $errorMsg ?: null,
            'queue_id'      => $queueId,
        ]);
    }

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Get recent sync history for a user (newest first).
     *
     * @param int $userId
     * @param int $limit
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getRecentByUser(int $userId, int $limit = 20): array
    {
        $sql = "
            SELECT
                q.*,
                se.event_name,
                se.event_code,
                s.title AS symposium_title
            FROM offline_sync_queue q
            LEFT JOIN symposium_events se ON se.symposium_event_id = q.symposium_event_id
            LEFT JOIN symposiums s        ON s.symposium_id        = se.symposium_id
            WHERE q.user_id = :user_id
            ORDER BY q.submitted_at DESC
            LIMIT :lim
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Get sync history for a specific symposium event.
     *
     * @param int $symposiumEventId
     * @param int $limit
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getByEvent(int $symposiumEventId, int $limit = 20): array
    {
        $sql = "
            SELECT q.*, u.full_name AS user_name
            FROM offline_sync_queue q
            LEFT JOIN users u ON u.user_id = q.user_id
            WHERE q.symposium_event_id = :symposium_event_id
            ORDER BY q.submitted_at DESC
            LIMIT :lim
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':symposium_event_id', $symposiumEventId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Get sync summary counts for the Sync Center dashboard.
     *
     * @param int $userId
     * @return array  ['total', 'completed', 'partial', 'failed', 'processing', ...]
     * -------------------------------------------------------------------------
     */
    public function getSummaryByUser(int $userId): array
    {
        $sql = "
            SELECT
                COUNT(*)                                       AS total,
                SUM(sync_status = 'Completed')                AS completed,
                SUM(sync_status = 'Partial')                  AS partial,
                SUM(sync_status = 'Failed')                   AS failed,
                SUM(sync_status = 'Processing')               AS processing,
                SUM(records_succeeded)                        AS total_synced,
                SUM(records_failed)                           AS total_failed,
                SUM(records_conflict)                         AS total_conflicts,
                MAX(completed_at)                             AS last_sync_at
            FROM offline_sync_queue
            WHERE user_id = :user_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'           => (int) ($row['total']           ?? 0),
            'completed'       => (int) ($row['completed']       ?? 0),
            'partial'         => (int) ($row['partial']         ?? 0),
            'failed'          => (int) ($row['failed']          ?? 0),
            'processing'      => (int) ($row['processing']      ?? 0),
            'total_synced'    => (int) ($row['total_synced']    ?? 0),
            'total_failed'    => (int) ($row['total_failed']    ?? 0),
            'total_conflicts' => (int) ($row['total_conflicts'] ?? 0),
            'last_sync_at'    => $row['last_sync_at'] ?? null,
        ];
    }
}
