<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS — Registration Reminder Cron Script
 * =========================================================================
 * File        : process_reminders.php
 * Location    : scripts/
 * Description : Sends 3-day registration-deadline reminders to students
 *               who have not yet registered for any competition.
 *
 * Run daily at 8 AM via Windows Task Scheduler or cron.
 *
 * Windows Task Scheduler:
 *   Program:   C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\NexusCore\scripts\process_reminders.php
 *   Schedule:  Daily at 08:00 AM
 *
 * Linux crontab:
 *   0 8 * * * /usr/bin/php /var/www/NexusCore/scripts/process_reminders.php
 *
 * Logic:
 *   Find symposiums where:
 *     • status IN ('Registration Open')
 *     • registration_end is exactly 3 days from today (date match)
 *     • reminder has not already been sent for this symposium today
 *       (checked by absence of 'registration_reminder' rows in email_queue
 *        created within the last 20 hours for this symposium)
 * =========================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/vendor/autoload.php';

use App\Core\Bootstrap;
use App\Database\Database;
use App\Services\SymposiumEmailService;

Bootstrap::loadEnvironment($projectRoot);

$timestamp = date('Y-m-d H:i:s');
echo "[{$timestamp}] Reminder script starting...\n";

try {
    $db = Database::getConnection();

    // Find symposiums whose registration closes in exactly 3 days.
    // "3 days away" = registration_end date == DATE(NOW() + 3 DAYS).
    $sql = "
        SELECT s.symposium_id, s.title
        FROM   symposiums s
        WHERE  s.status = 'Registration Open'
          AND  DATE(s.registration_end) = DATE(DATE_ADD(NOW(), INTERVAL 3 DAY))
          AND  s.symposium_id NOT IN (
               -- Skip if reminders already sent within last 20 hours for this symposium.
               SELECT DISTINCT symposium_id
               FROM   email_queue
               WHERE  trigger_event = 'registration_reminder'
                 AND  created_at   >= DATE_SUB(NOW(), INTERVAL 20 HOUR)
          )
    ";

    $stmt = $db->query($sql);
    $eligible = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($eligible)) {
        echo "[{$timestamp}] No symposiums need reminders today.\n";
        exit(0);
    }

    $emailSvc = new SymposiumEmailService();

    foreach ($eligible as $row) {
        $symposiumId = (int) $row['symposium_id'];
        echo "[{$timestamp}] Processing reminders for: {$row['title']} (ID {$symposiumId})\n";

        $result = $emailSvc->sendRegistrationReminderEmails($symposiumId, 1);
        echo "[{$timestamp}]   → Enqueued: {$result['enqueued']}, Skipped: {$result['skipped']}"
            . (isset($result['reason']) ? " ({$result['reason']})" : '') . "\n";
    }

} catch (\Throwable $e) {
    echo "[{$timestamp}] ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "[{$timestamp}] Reminder script complete.\n";
exit(0);
