<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS
 * =========================================================================
 * File        : EmailQueueModel.php
 * Location    : app/Models/
 * Description : Database operations for the email_queue table.
 *
 * The email_queue table is the backbone of the DB-backed email queue.
 * Each row represents one email job for one recipient.
 * The queue worker processes 'pending' and retries 'failed' jobs
 * up to max_attempts times, with exponential backoff.
 *
 * Queue States
 * ─────────────────────────────────────────────────────────────
 *   pending    → newly enqueued, ready for first attempt
 *   processing → claimed by worker (prevents double-send)
 *   sent       → delivered successfully
 *   failed     → last attempt failed, will be retried
 *   dead       → exceeded max_attempts, no more retries
 *
 * =========================================================================
 */

namespace App\Models;

use PDO;

final class EmailQueueModel extends BaseModel
{
    // =========================================================================
    // ENQUEUE
    // =========================================================================

    /**
     * Add a single email job to the queue.
     *
     * @param array $data {
     *     symposium_id, trigger_event, triggered_by,
     *     student_id, recipient_email, recipient_name,
     *     subject, html_body
     * }
     *
     * @return int  Inserted queue_id, or 0 on failure.
     */
    public function enqueue(array $data): int
    {
        $sql = '
            INSERT INTO email_queue
                (symposium_id, trigger_event, triggered_by, student_id,
                 recipient_email, recipient_name, subject, html_body,
                 status, next_attempt_at)
            VALUES
                (:symposium_id, :trigger_event, :triggered_by, :student_id,
                 :recipient_email, :recipient_name, :subject, :html_body,
                 \'pending\', NOW())
        ';

        $stmt = $this->db->prepare($sql);
        $ok   = $stmt->execute([
            'symposium_id'    => $data['symposium_id'],
            'trigger_event'   => $data['trigger_event'],
            'triggered_by'    => $data['triggered_by'] ?? null,
            'student_id'      => $data['student_id']   ?? null,
            'recipient_email' => $data['recipient_email'],
            'recipient_name'  => $data['recipient_name'] ?? null,
            'subject'         => $data['subject'],
            'html_body'       => $data['html_body'],
        ]);

        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    // =========================================================================
    // CLAIM (atomic, prevents double-processing)
    // =========================================================================

    /**
     * Atomically claim a batch of pending/failed jobs that are due.
     *
     * Uses UPDATE + SELECT to claim rows safely without a SELECT FOR UPDATE.
     * A worker_token is passed so concurrent workers never claim the same job.
     *
     * @param int    $batchSize   Maximum number of jobs to claim per run.
     * @param string $workerToken Unique token for this worker run (prevents race).
     *
     * @return array  Claimed job rows ready to send.
     */
    public function claimBatch(int $batchSize = 20, string $workerToken = ''): array
    {
        if ($workerToken === '') {
            $workerToken = bin2hex(random_bytes(8));
        }

        // Step 1 — claim by marking as 'processing' with a temporary token in last_error.
        // Only jobs whose next_attempt_at <= NOW() are eligible.
        $update = '
            UPDATE email_queue
            SET    status     = \'processing\',
                   last_error = :token
            WHERE  status     IN (\'pending\', \'failed\')
              AND  next_attempt_at <= NOW()
              AND  attempts < max_attempts
            ORDER  BY created_at ASC
            LIMIT  :batch_size
        ';

        $stmt = $this->db->prepare($update);
        $stmt->bindValue(':token',      $workerToken);
        $stmt->bindValue(':batch_size', $batchSize, PDO::PARAM_INT);
        $stmt->execute();

        // Step 2 — retrieve only the rows we just claimed.
        $select = '
            SELECT *
            FROM   email_queue
            WHERE  status     = \'processing\'
              AND  last_error  = :token
            ORDER  BY created_at ASC
        ';

        $stmt2 = $this->db->prepare($select);
        $stmt2->execute(['token' => $workerToken]);

        return $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // MARK RESULTS
    // =========================================================================

    /**
     * Mark a job as successfully sent.
     *
     * @param int $queueId
     *
     * @return bool
     */
    public function markSent(int $queueId): bool
    {
        $sql = '
            UPDATE email_queue
            SET    status     = \'sent\',
                   last_error = NULL,
                   sent_at    = NOW()
            WHERE  queue_id   = :queue_id
        ';

        return $this->db->prepare($sql)->execute(['queue_id' => $queueId]);
    }

    /**
     * Mark a job as failed, incrementing attempts.
     *
     * Calculates exponential backoff: next attempt after 2^attempts minutes.
     * If attempts >= max_attempts the job is moved to 'dead'.
     *
     * @param int    $queueId
     * @param int    $attempts   Current attempt count (before this failure).
     * @param int    $maxAttempts
     * @param string $errorMsg
     *
     * @return bool
     */
    public function markFailed(int $queueId, int $attempts, int $maxAttempts, string $errorMsg): bool
    {
        $newAttempts = $attempts + 1;

        // Exponential backoff: 2, 4, 8 minutes …
        $backoffMinutes = (int) min(pow(2, $newAttempts), 60);

        $newStatus = ($newAttempts >= $maxAttempts) ? 'dead' : 'failed';

        $sql = '
            UPDATE email_queue
            SET    status           = :status,
                   attempts         = :attempts,
                   last_error       = :error,
                   next_attempt_at  = DATE_ADD(NOW(), INTERVAL :backoff MINUTE)
            WHERE  queue_id = :queue_id
        ';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'status'   => $newStatus,
            'attempts' => $newAttempts,
            'error'    => substr($errorMsg, 0, 1000),
            'backoff'  => $backoffMinutes,
            'queue_id' => $queueId,
        ]);
    }

    // =========================================================================
    // STATS / REPORTING
    // =========================================================================

    /**
     * Get queue statistics for a symposium.
     *
     * @param int $symposiumId
     *
     * @return array{pending: int, sent: int, failed: int, dead: int, total: int}
     */
    public function getStatsBySymposium(int $symposiumId): array
    {
        $sql = '
            SELECT
                SUM(status = \'pending\')    AS pending,
                SUM(status = \'processing\') AS processing,
                SUM(status = \'sent\')       AS sent,
                SUM(status = \'failed\')     AS failed,
                SUM(status = \'dead\')       AS dead,
                COUNT(*)                     AS total
            FROM email_queue
            WHERE symposium_id = :symposium_id
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'pending'    => (int) ($row['pending']    ?? 0),
            'processing' => (int) ($row['processing'] ?? 0),
            'sent'       => (int) ($row['sent']       ?? 0),
            'failed'     => (int) ($row['failed']     ?? 0),
            'dead'       => (int) ($row['dead']       ?? 0),
            'total'      => (int) ($row['total']       ?? 0),
        ];
    }

    /**
     * Count pending jobs across all symposiums (for dashboard badge).
     *
     * @return int
     */
    public function countPending(): int
    {
        $sql = 'SELECT COUNT(*) FROM email_queue WHERE status IN (\'pending\', \'failed\')';
        return (int) $this->db->query($sql)->fetchColumn();
    }
}
