<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS — CLI Email Queue Worker
 * =========================================================================
 * File        : process_email_queue.php
 * Location    : scripts/
 * Description : Processes pending email jobs from the email_queue table.
 *
 * This script is the ONLY process that actually sends emails via SMTP.
 * It should be run on a schedule every 5 minutes via Windows Task Scheduler
 * or the XAMPP cron equivalent.
 *
 * ─── Setup Instructions ──────────────────────────────────────────────────
 *
 * Windows Task Scheduler (recommended for XAMPP):
 *   Program:   C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\NexusCore\scripts\process_email_queue.php
 *   Schedule:  Every 5 minutes
 *
 * Linux/Mac crontab:
 *   *\/5 * * * * /usr/bin/php /var/www/NexusCore/scripts/process_email_queue.php >> /var/log/nexus_email.log 2>&1
 *
 * ─── What this script does ───────────────────────────────────────────────
 *   1. Bootstraps the NexusCore application (loads .env, DB, autoloader).
 *   2. Calls EmailService::processQueue() to claim and send a batch.
 *   3. Prints a summary line to stdout (captured in Task Scheduler log).
 *   4. Exits cleanly. The scheduler calls it again in 5 minutes.
 *
 * ─── Retry logic ─────────────────────────────────────────────────────────
 *   Handled entirely by EmailQueueModel::markFailed():
 *     • Attempt 1 fails → retry in 2 minutes
 *     • Attempt 2 fails → retry in 4 minutes
 *     • Attempt 3 fails → status = 'dead', no more retries
 *   All failures are permanently logged in email_send_log.
 *
 * =========================================================================
 */

// This script must be run from CLI only.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/vendor/autoload.php';

use App\Core\Bootstrap;
use App\Services\EmailService;

Bootstrap::loadEnvironment($projectRoot);

// ─── Run ─────────────────────────────────────────────────────────────────────

$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] NexusCore Email Worker starting...\n";

try {
    $emailService = new EmailService();
    $result = $emailService->processQueue();

    echo "[{$timestamp}] Done. "
        . "Processed: {$result['processed']}, "
        . "Sent: {$result['sent']}, "
        . "Failed: {$result['failed']}"
        . (isset($result['reason']) ? " ({$result['reason']})" : '')
        . "\n";

} catch (\Throwable $e) {
    echo "[{$timestamp}] WORKER ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

exit(0);
