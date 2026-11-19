<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS — CLI Document & Certificate Queue Worker
 * =========================================================================
 * File        : process_document_queue.php
 * Location    : scripts/
 * Description : Processes pending document/certificate generation jobs.
 *
 * Runs via CLI on a schedule (e.g. Windows Task Scheduler / Linux Cron)
 * or spawned on-demand in detached background OS processes by DocumentQueueService.
 *
 * Usage:
 *   php scripts/process_document_queue.php               # Processes all pending jobs
 *   php scripts/process_document_queue.php --once        # Claims and processes at most 1 job
 *   php scripts/process_document_queue.php --job-id=42   # Claims and processes specific job #42
 *   php scripts/process_document_queue.php --dry-run     # Inspects pending jobs without claiming
 * =========================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be executed via CLI.\n");
}

// Ensure execution is immune to disconnects and timeouts
@ignore_user_abort(true);
@set_time_limit(0);

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/vendor/autoload.php';

use App\Core\Bootstrap;
use App\Models\DocumentJobModel;
use App\Services\CertificateBulkService;
use App\Services\CertificateReconciliationService;
use App\Services\DocumentQueueService;

Bootstrap::loadEnvironment($projectRoot);

$isOnce   = in_array('--once', $argv, true);
$isDryRun = in_array('--dry-run', $argv, true);

$targetJobId = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--job-id=')) {
        $targetJobId = (int) substr($arg, 9);
        $isOnce = true;
    }
}

$jobModel  = new DocumentJobModel();
$timestamp = date('Y-m-d H:i:s');

echo "[{$timestamp}] NexusCore Document Worker starting...\n";

if ($isDryRun) {
    $db = \App\Database\Database::getConnection();
    $pendingCount    = (int) $db->query("SELECT COUNT(*) FROM document_jobs WHERE status = 'Pending'")->fetchColumn();
    $processingCount = (int) $db->query("SELECT COUNT(*) FROM document_jobs WHERE status = 'Processing'")->fetchColumn();
    echo "[DRY-RUN] Pending jobs: {$pendingCount}, In-flight processing: {$processingCount}\n";
    exit(0);
}

$processed = 0;

do {
    // 1. Atomically claim job
    if ($targetJobId !== null && $targetJobId > 0) {
        $job = $jobModel->claimSpecificJob($targetJobId);
        if (!$job) {
            $existing = $jobModel->findById($targetJobId);
            if ($existing) {
                echo "[{$timestamp}] Job #{$targetJobId} cannot be claimed (current status: {$existing['status']}).\n";
            } else {
                echo "[{$timestamp}] Job #{$targetJobId} not found.\n";
            }
            break;
        }
    } else {
        $job = $jobModel->claimNextJob();
        if (!$job) {
            if ($processed === 0) {
                echo "[{$timestamp}] Queue is empty. No jobs to process.\n";
            }
            break;
        }
    }

    $jobId       = (int) $job['job_id'];
    $jobType     = (string) $job['job_type'];
    $payload     = json_decode($job['payload_json'] ?? '{}', true) ?: [];
    $eventId     = (int) ($job['symposium_event_id'] ?? $payload['event_id'] ?? 0);
    $symposiumId = (int) ($job['symposium_id'] ?? $payload['symposium_id'] ?? 0);
    $userId      = (int) ($job['created_by'] ?? $payload['user_id'] ?? 1);
    $action      = (string) ($payload['action'] ?? '');

    echo "[{$timestamp}] Claimed Job #{$jobId} [{$jobType}] (Event: {$eventId}, Symposium: {$symposiumId})\n";

    try {
        // Pre-generation reconciliation: recover any stuck rows from past crashes
        (new CertificateReconciliationService())->reconcileStuckIssuances($userId);

        $bulkService = new CertificateBulkService();

        // Attach monotonically increasing progress updater
        $bulkService->setProgressCallback(function (int $current, int $total) use ($jobModel, $jobId) {
            $jobModel->updateProgress($jobId, $current);
        });

        $report = null;

        if ($jobType === DocumentQueueService::JOB_TYPE_WINNER || $action === 'generate_winners') {
            // Winner Only
            $templateId      = !empty($payload['winner_template_id']) ? (int) $payload['winner_template_id'] : (!empty($payload['template_id']) ? (int) $payload['template_id'] : null);
            $allowRegenerate = !empty($payload['allow_regenerate_winner']) || !empty($payload['allow_regenerate']);
            $report          = $bulkService->generateWinnersForEvent($eventId, $userId, $allowRegenerate, $templateId);

        } elseif ($jobType === DocumentQueueService::JOB_TYPE_PARTICIPANT || $action === 'generate_participants') {
            // Participant Only
            $templateId      = !empty($payload['participant_template_id']) ? (int) $payload['participant_template_id'] : (!empty($payload['template_id']) ? (int) $payload['template_id'] : null);
            $allowRegenerate = !empty($payload['allow_regenerate_participant']) || !empty($payload['allow_regenerate']);
            $report          = $bulkService->generateParticipantsForEvent($eventId, $userId, $allowRegenerate, $templateId);

        } elseif ($jobType === DocumentQueueService::JOB_TYPE_SYMPOSIUM_BATCH || $action === 'symposium_batch') {
            // Symposium Batch
            $winnerTemplateId      = !empty($payload['winner_template_id']) ? (int) $payload['winner_template_id'] : null;
            $participantTemplateId = !empty($payload['participant_template_id']) ? (int) $payload['participant_template_id'] : null;
            $allowRegenerate       = !empty($payload['allow_regenerate']);

            $report = $bulkService->generateForSymposium(
                $symposiumId,
                $userId,
                true,
                $allowRegenerate,
                $winnerTemplateId,
                $participantTemplateId
            );

        } else {
            // Default: Both Winners & Participants for single event
            $winnerTemplateId      = !empty($payload['winner_template_id']) ? (int) $payload['winner_template_id'] : (!empty($payload['template_id']) ? (int) $payload['template_id'] : null);
            $participantTemplateId = !empty($payload['participant_template_id']) ? (int) $payload['participant_template_id'] : (!empty($payload['template_id']) ? (int) $payload['template_id'] : null);
            $allowRegenWinner      = !empty($payload['allow_regenerate_winner']) || !empty($payload['allow_regenerate']);
            $allowRegenParticipant = !empty($payload['allow_regenerate_participant']) || !empty($payload['allow_regenerate']);

            $report = [
                'event_id'         => $eventId,
                'certificate_type' => 'Both',
                'total_processed'  => 0,
                'total_generated'  => 0,
                'total_skipped'    => 0,
                'total_failed'     => 0,
                'details'          => [],
            ];

            $wErrors = [];
            $pErrors = [];

            // 1. Generate Winners
            try {
                $wReport = $bulkService->generateWinnersForEvent($eventId, $userId, $allowRegenWinner, $winnerTemplateId);
                $report['total_processed'] += $wReport['total_processed'];
                $report['total_generated'] += $wReport['total_generated'];
                $report['total_skipped']   += $wReport['total_skipped'];
                $report['total_failed']    += $wReport['total_failed'];
                $report['details']         = array_merge($report['details'], $wReport['details'] ?? []);
            } catch (\Throwable $e) {
                $wErrors[] = $e->getMessage();
                $report['details'][] = [
                    'identifier' => '-',
                    'recipient'  => 'Event Validation (Winners)',
                    'status'     => 'Skipped',
                    'reason'     => $e->getMessage(),
                ];
            }

            // 2. Generate Participants
            try {
                $pReport = $bulkService->generateParticipantsForEvent($eventId, $userId, $allowRegenParticipant, $participantTemplateId);
                $report['total_processed'] += $pReport['total_processed'];
                $report['total_generated'] += $pReport['total_generated'];
                $report['total_skipped']   += $pReport['total_skipped'];
                $report['total_failed']    += $pReport['total_failed'];
                $report['details']         = array_merge($report['details'], $pReport['details'] ?? []);
            } catch (\Throwable $e) {
                $pErrors[] = $e->getMessage();
                $report['details'][] = [
                    'identifier' => '-',
                    'recipient'  => 'Event Validation (Participants)',
                    'status'     => 'Skipped',
                    'reason'     => $e->getMessage(),
                ];
            }

            if ($report['total_processed'] === 0 && (!empty($wErrors) || !empty($pErrors))) {
                throw new \RuntimeException(implode(" | ", array_merge($wErrors, $pErrors)));
            }
        }

        // Save persistent report JSON and mark job completed
        $jobModel->saveJobReport($jobId, $report ?: []);
        echo "[{$timestamp}] Completed Job #{$jobId} (Processed: " . ($report['total_processed'] ?? 0) . ", Generated: " . ($report['total_generated'] ?? 0) . ")\n";
        $processed++;

    } catch (\Throwable $e) {
        $errMsg = $e->getMessage();
        $jobModel->markFailed($jobId, $errMsg);
        echo "[{$timestamp}] FAILED Job #{$jobId}: {$errMsg}\n";
    }

} while (!$isOnce);

echo "[{$timestamp}] Worker finished. Processed {$processed} job(s).\n";
exit(0);
