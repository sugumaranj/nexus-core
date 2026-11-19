<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS
 * =========================================================================
 * File        : DocumentJobModel.php
 * Location    : app/Models/
 * Description : Database operations for the document_jobs queue table.
 *
 * Implements an asynchronous, atomic queue for bulk certificate generation,
 * reports compilation, and ZIP packaging.
 *
 * Queue States
 * ─────────────────────────────────────────────────────────────
 *   Pending    → newly enqueued, waiting for worker
 *   Processing → atomically claimed by worker thread
 *   Completed  → successfully generated and packaged
 *   Failed     → encountered error during execution
 * =========================================================================
 */

namespace App\Models;

use PDO;

final class DocumentJobModel extends BaseModel
{
    /**
     * Enqueue a new document generation job.
     *
     * @param array $data {
     *     job_type: string,
     *     symposium_event_id: int|null,
     *     symposium_id: int|null,
     *     payload_json: string|array,
     *     total_items: int,
     *     created_by: int
     * }
     * @return int Inserted job_id, or 0 on failure.
     */
    public function enqueue(array $data): int
    {
        $payload = is_array($data['payload_json'])
            ? json_encode($data['payload_json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : (string) $data['payload_json'];

        $sql = '
            INSERT INTO document_jobs
                (job_type, symposium_event_id, symposium_id, payload_json,
                 status, total_items, processed_items, created_by, created_at)
            VALUES
                (:job_type, :symposium_event_id, :symposium_id, :payload_json,
                 \'Pending\', :total_items, 0, :created_by, NOW())
        ';

        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            'job_type'           => $data['job_type'],
            'symposium_event_id' => $data['symposium_event_id'] ?? null,
            'symposium_id'       => $data['symposium_id'] ?? null,
            'payload_json'       => $payload,
            'total_items'        => (int) ($data['total_items'] ?? 0),
            'created_by'         => (int) ($data['created_by'] ?? 1),
        ]);

        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    /**
     * Helper alias to enqueue a job.
     */
    public function createJob(string $jobType, array $payload, int $userId = 1, ?int $eventId = null, ?int $symposiumId = null, int $totalItems = 0): int
    {
        return $this->enqueue([
            'job_type'           => $jobType,
            'symposium_event_id' => $eventId ?? ($payload['event_id'] ?? null),
            'symposium_id'       => $symposiumId ?? ($payload['symposium_id'] ?? null),
            'payload_json'       => $payload,
            'total_items'        => $totalItems,
            'created_by'         => $userId,
        ]);
    }

    /**
     * Helper alias to get job status.
     */
    public function getJobStatus(int $jobId): array|false
    {
        return $this->findById($jobId);
    }

    /**
     * Helper alias to complete a job.
     */
    public function completeJob(int $jobId, array|string|null $result = null): bool
    {
        if (is_array($result)) {
            return $this->saveJobReport($jobId, $result);
        }
        $outputPath = $result;
        return $this->markCompleted($jobId, $outputPath);
    }

    /**
     * Atomically claim a specific pending job using row locking.
     *
     * @param int $jobId
     * @return array|false Claimed job record, or false if not found or not pending.
     */
    public function claimSpecificJob(int $jobId): array|false
    {
        $this->db->beginTransaction();

        $select = "
            SELECT *
            FROM document_jobs
            WHERE job_id = :job_id AND status = 'Pending'
            LIMIT 1
            FOR UPDATE
        ";

        $stmt = $this->db->prepare($select);
        $stmt->execute(['job_id' => $jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            $this->db->rollBack();
            return false;
        }

        $update = "
            UPDATE document_jobs
            SET status = 'Processing',
                updated_at = NOW()
            WHERE job_id = :job_id
        ";

        $stmt = $this->db->prepare($update);
        $stmt->execute(['job_id' => $jobId]);

        $this->db->commit();

        $job['status'] = 'Processing';
        return $job;
    }

    /**
     * Atomically claim the next pending job using row locking.
     *
     * @return array|false Claimed job record, or false if queue is empty.
     */
    public function claimNextJob(): array|false
    {
        $this->db->beginTransaction();

        $select = "
            SELECT *
            FROM document_jobs
            WHERE status = 'Pending'
            ORDER BY created_at ASC
            LIMIT 1
            FOR UPDATE
        ";

        $job = $this->db->query($select)->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            $this->db->rollBack();
            return false;
        }

        $update = "
            UPDATE document_jobs
            SET status = 'Processing',
                updated_at = NOW()
            WHERE job_id = :job_id
        ";

        $stmt = $this->db->prepare($update);
        $stmt->execute(['job_id' => (int) $job['job_id']]);

        $this->db->commit();

        $job['status'] = 'Processing';
        return $job;
    }

    /**
     * Update progress of a running job.
     *
     * @param int $jobId
     * @param int $processedItems
     * @return bool
     */
    public function updateProgress(int $jobId, int $processedItems): bool
    {
        $sql = '
            UPDATE document_jobs
            SET processed_items = :processed,
                updated_at = NOW()
            WHERE job_id = :job_id
        ';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'processed' => $processedItems,
            'job_id'    => $jobId,
        ]);
    }

    /**
     * Update total items of a job.
     */
    public function updateTotalItems(int $jobId, int $totalItems): bool
    {
        $sql = '
            UPDATE document_jobs
            SET total_items = :total_items,
                updated_at = NOW()
            WHERE job_id = :job_id
        ';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'total_items' => max(0, $totalItems),
            'job_id'      => $jobId,
        ]);
    }

    /**
     * Mark a job as completed.
     *
     * @param int         $jobId
     * @param string|null $outputPath Path to generated archive or artifact
     * @return bool
     */
    public function markCompleted(int $jobId, ?string $outputPath = null): bool
    {
        $sql = "
            UPDATE document_jobs
            SET status = 'Completed',
                processed_items = total_items,
                output_path = :output_path,
                completed_at = NOW(),
                updated_at = NOW()
            WHERE job_id = :job_id
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'output_path' => $outputPath,
            'job_id'      => $jobId,
        ]);
    }

    /**
     * Save generation report array to persistent JSON storage and mark job completed.
     */
    public function saveJobReport(int $jobId, array $report): bool
    {
        $dir = dirname(__DIR__, 2) . '/storage/reports';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $relPath  = 'storage/reports/job_' . $jobId . '_report.json';
        $fullPath = dirname(__DIR__, 2) . '/' . $relPath;

        file_put_contents(
            $fullPath,
            json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        );

        $totalProcessed = (int) ($report['total_processed'] ?? 0);
        if ($totalProcessed > 0) {
            $this->updateTotalItems($jobId, $totalProcessed);
        }

        return $this->markCompleted($jobId, $relPath);
    }

    /**
     * Retrieve the parsed generation report for a job.
     */
    public function getJobReport(int $jobId): ?array
    {
        $job = $this->findById($jobId);
        if (!$job || empty($job['output_path'])) {
            return null;
        }

        $path = (string) $job['output_path'];
        $fullPath = (str_starts_with($path, '/') || (strlen($path) > 2 && $path[1] === ':'))
            ? $path
            : dirname(__DIR__, 2) . '/' . ltrim($path, '/\\');

        if (file_exists($fullPath)) {
            $content = (string) file_get_contents($fullPath);
            $data    = json_decode($content, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Mark a job as failed.
     *
     * @param int    $jobId
     * @param string $errorMessage
     * @return bool
     */
    public function markFailed(int $jobId, string $errorMessage): bool
    {
        $sql = "
            UPDATE document_jobs
            SET status = 'Failed',
                error_message = :error_message,
                completed_at = NOW(),
                updated_at = NOW()
            WHERE job_id = :job_id
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'error_message' => substr($errorMessage, 0, 1000),
            'job_id'        => $jobId,
        ]);
    }

    /**
     * Retrieve recent jobs for dashboard / generation pages.
     */
    public function getRecentJobs(int $limit = 5, ?int $userId = null): array
    {
        $sql = "
            SELECT
                dj.*,
                se.event_name,
                s.title AS symposium_title
            FROM document_jobs dj
            LEFT JOIN symposium_events se ON se.symposium_event_id = dj.symposium_event_id
            LEFT JOIN symposiums s ON s.symposium_id = dj.symposium_id OR s.symposium_id = se.symposium_id
        ";

        $params = [];
        if ($userId !== null && $userId > 0) {
            $sql .= " WHERE dj.created_by = :uid ";
            $params['uid'] = $userId;
        }

        $sql .= " ORDER BY dj.created_at DESC LIMIT " . (int)$limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find a job by ID.
     *
     * @param int $jobId
     * @return array|false
     */
    public function findById(int $jobId): array|false
    {
        $sql = '
            SELECT *
            FROM document_jobs
            WHERE job_id = :job_id
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['job_id' => $jobId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
