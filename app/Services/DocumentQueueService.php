<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS — Document Queue Service & Worker Dispatcher
 * =========================================================================
 * File        : DocumentQueueService.php
 * Location    : app/Services/
 * Description : Dispatches asynchronous background certificate and document jobs.
 *               Ensures generation runs safely in a detached background OS process
 *               so jobs survive client tab closures, browser navigation,
 *               and client-side internet disconnections.
 * =========================================================================
 */

namespace App\Services;

use App\Database\Database;
use App\Models\DocumentJobModel;
use App\Services\CertificateEligibilityService;
use PDO;
use RuntimeException;
use Throwable;

final class DocumentQueueService
{
    public const JOB_TYPE_WINNER          = 'Certificate_Bulk_Winner';
    public const JOB_TYPE_PARTICIPANT     = 'Certificate_Bulk_Participant';
    public const JOB_TYPE_BOTH            = 'Certificate_Bulk_Both';
    public const JOB_TYPE_SYMPOSIUM_BATCH = 'Certificate_Symposium_Batch';

    private DocumentJobModel $jobModel;
    private CertificateEligibilityService $eligibilityService;

    public function __construct(
        ?DocumentJobModel $jobModel = null,
        ?CertificateEligibilityService $eligibilityService = null
    ) {
        $this->jobModel           = $jobModel ?? new DocumentJobModel();
        $this->eligibilityService = $eligibilityService ?? new CertificateEligibilityService();
    }

    /**
     * Resolves the correct PHP CLI binary on the host system.
     * Prioritizes PHP 8.5.12 located on the D: drive as configured in the runtime.
     */
    public static function getPhpBinary(): string
    {
        // 1. Explicit environment configuration
        $envPhp = getenv('PHP_BINARY_PATH') ?: getenv('PHP_EXE');
        if ($envPhp && is_file($envPhp)) {
            return $envPhp;
        }

        // 2. Exact D: drive XAMPP path for PHP 8.5.12
        $xampp85 = 'D:\\xampp_8_5_12\\php\\php.exe';
        if (is_file($xampp85)) {
            return $xampp85;
        }

        // 3. Current PHP_BINARY if CLI executable
        if (defined('PHP_BINARY') && PHP_BINARY && is_file(PHP_BINARY)) {
            $base = strtolower(basename(PHP_BINARY));
            if ($base === 'php.exe' || $base === 'php') {
                return PHP_BINARY;
            }
            $sibling = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'php.exe';
            if (is_file($sibling)) {
                return $sibling;
            }
        }

        // 4. Common Windows fallback paths
        $fallbacks = [
            'C:\\xampp_8_5_12\\php\\php.exe',
            'C:\\xampp\\php\\php.exe',
            'D:\\xampp\\php\\php.exe',
        ];
        foreach ($fallbacks as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        // 5. System PATH fallback
        return 'php';
    }

    /**
     * Dispatches the CLI background worker in a detached OS process.
     * Returns true if dispatch command succeeded.
     *
     * @param int|null $jobId Optional specific job ID to process
     */
    public function dispatchBackgroundWorker(?int $jobId = null): bool
    {
        $projectRoot = dirname(__DIR__, 2);
        $scriptPath  = $projectRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'process_document_queue.php';

        if (!is_file($scriptPath)) {
            error_log('[DocumentQueueService::dispatchBackgroundWorker] Script not found: ' . $scriptPath);
            return false;
        }

        $phpBinary = self::getPhpBinary();
        $arg       = ($jobId !== null && $jobId > 0) ? (' --job-id=' . (int)$jobId) : '';

        $isWindows = (PHP_OS_FAMILY === 'Windows' || strncasecmp(PHP_OS, 'WIN', 3) === 0);

        try {
            if ($isWindows) {
                // Windows detached non-blocking execution via start /B
                // Using start /B "" ensures windowless execution detached from the web server thread
                $command = sprintf(
                    'start /B "" "%s" "%s"%s > NUL 2>&1',
                    $phpBinary,
                    $scriptPath,
                    $arg
                );

                if (function_exists('popen')) {
                    $handle = @popen($command, 'r');
                    if ($handle !== false) {
                        @pclose($handle);
                        return true;
                    }
                }

                if (function_exists('exec')) {
                    @exec($command);
                    return true;
                }
            } else {
                // Linux / Unix detached background process
                $command = sprintf(
                    'nohup "%s" "%s"%s > /dev/null 2>&1 &',
                    $phpBinary,
                    $scriptPath,
                    $arg
                );

                if (function_exists('exec')) {
                    @exec($command);
                    return true;
                }

                if (function_exists('popen')) {
                    $handle = @popen($command, 'r');
                    if ($handle !== false) {
                        @pclose($handle);
                        return true;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[DocumentQueueService::dispatchBackgroundWorker] Failed to dispatch: ' . $e->getMessage());
            return false;
        }

        return false;
    }

    /**
     * Accurately calculates total expected recipients for a job before enqueuing.
     * Ensures progress percentage starts with the correct denominator.
     */
    public function calculateTotalItems(string $jobType, ?int $eventId, ?int $symposiumId, array $payload = []): int
    {
        $action = $payload['action'] ?? '';

        try {
            if ($eventId !== null && $eventId > 0) {
                if ($jobType === self::JOB_TYPE_WINNER || $action === 'generate_winners') {
                    $pre = $this->eligibilityService->getWinnerPreflight($eventId);
                    return max(0, (int)($pre['eligible'] ?? 0));
                }

                if ($jobType === self::JOB_TYPE_PARTICIPANT || $action === 'generate_participants') {
                    $pre = $this->eligibilityService->getParticipantPreflight($eventId);
                    return max(0, (int)($pre['eligible'] ?? 0));
                }

                // Default for single event: Both winners and participants
                $wPre = $this->eligibilityService->getWinnerPreflight($eventId);
                $pPre = $this->eligibilityService->getParticipantPreflight($eventId);
                return max(0, (int)($wPre['eligible'] ?? 0) + (int)($pPre['eligible'] ?? 0));
            }

            if ($symposiumId !== null && $symposiumId > 0) {
                $db = Database::getConnection();
                $stmt = $db->prepare("
                    SELECT symposium_event_id
                    FROM symposium_events
                    WHERE symposium_id = :sid AND is_locked = 1
                ");
                $stmt->execute(['sid' => $symposiumId]);
                $eventIds = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

                $total = 0;
                foreach ($eventIds as $eid) {
                    $wPre = $this->eligibilityService->getWinnerPreflight((int)$eid);
                    $pPre = $this->eligibilityService->getParticipantPreflight((int)$eid);
                    $total += (int)($wPre['eligible'] ?? 0) + (int)($pPre['eligible'] ?? 0);
                }
                return max(0, $total);
            }
        } catch (Throwable $e) {
            error_log('[DocumentQueueService::calculateTotalItems] Preflight calculation notice: ' . $e->getMessage());
        }

        return 0;
    }

    /**
     * Enqueues a certificate generation job and immediately triggers the background worker.
     *
     * @param string   $jobType     Job type constant or string
     * @param array    $payload     Job parameters
     * @param int      $userId      User who triggered the generation
     * @param int|null $eventId     Event ID
     * @param int|null $symposiumId Symposium ID
     * @return int Enqueued job ID
     * @throws RuntimeException If unable to insert job into database
     */
    public function enqueueJob(
        string $jobType,
        array $payload,
        int $userId,
        ?int $eventId = null,
        ?int $symposiumId = null
    ): int {
        $totalItems = $this->calculateTotalItems($jobType, $eventId, $symposiumId, $payload);

        $jobId = $this->jobModel->createJob(
            $jobType,
            $payload,
            $userId,
            $eventId,
            $symposiumId,
            $totalItems
        );

        if ($jobId <= 0) {
            throw new RuntimeException('Failed to create background document job in database.');
        }

        // Immediately launch detached background process
        $this->dispatchBackgroundWorker($jobId);

        return $jobId;
    }
}
