<?php
declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS — Tomorrow Venue Reminder CLI Script
 * =========================================================================
 * File        : venue_reminder.php
 * Location    : scripts/
 *
 * Purpose
 * ─────────────────────────────────────────────────────────────────────────
 * Sends venue reminder notifications to FICs, Judges, and registered
 * Students for all symposium events scheduled for TOMORROW (IST).
 *
 * Idempotent: Runs daily via scheduled task / cron.
 *             Duplicate sends within the same day are prevented by the
 *             UNIQUE KEY (notification_type, notification_ref_key) in the
 *             notifications table. Re-running on the same day is a no-op.
 *
 * Schedule: Run every day at 18:00 IST (12:30 UTC) via Windows Task Scheduler
 *           or cron:
 *               30 12 * * * php /path/to/NexusCore/scripts/venue_reminder.php
 *
 * Usage
 * ─────────────────────────────────────────────────────────────────────────
 *   php scripts/venue_reminder.php
 *   php scripts/venue_reminder.php --dry-run      # Print without inserting
 *
 * =========================================================================
 */

if (PHP_SAPI !== 'cli') {
    exit('CLI only.' . PHP_EOL);
}

$isDryRun = in_array('--dry-run', $argv ?? [], true);

require_once __DIR__ . '/../vendor/autoload.php';
App\Core\Bootstrap::loadEnvironment(__DIR__ . '/..');

$pdo = App\Database\Database::getConnection();

// ── Timezone: IST is set via DB connection (SET time_zone = '+05:30') ──────
// PHP default timezone should also be IST
date_default_timezone_set('Asia/Kolkata');

$tomorrow    = date('Y-m-d', strtotime('+1 day'));
$todayDate   = date('Y-m-d');

log_msg("NexusCore Venue Reminder Script — " . date('Y-m-d H:i:s'));
log_msg("Tomorrow: $tomorrow");
log_msg("Dry run: " . ($isDryRun ? 'YES' : 'NO'));
log_msg(str_repeat('─', 60));

// ── Fetch all events scheduled for tomorrow with an assigned venue ──────────
$stmt = $pdo->prepare("
    SELECT
        se.symposium_event_id,
        se.event_name,
        se.event_date,
        se.start_time,
        se.end_time,
        se.session,
        v.venue_name,
        v.venue_code,
        v.building_name,
        v.floor
    FROM symposium_events se
    JOIN venues v ON v.venue_id = se.venue_id
    WHERE se.event_date = :tomorrow
      AND se.status NOT IN ('Cancelled', 'Draft')
      AND se.schedule_status IN ('Scheduled', 'Rescheduled', 'Locked')
    ORDER BY se.start_time ASC
");
$stmt->execute(['tomorrow' => $tomorrow]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    log_msg("No scheduled events with assigned venues found for tomorrow. Done.");
    exit(0);
}

log_msg("Found " . count($events) . " event(s) to process.");
log_msg('');

$notifModel = new App\Models\NotificationModel();
$totalSent  = 0;
$totalSkip  = 0;

foreach ($events as $event) {
    $eid       = (int)$event['symposium_event_id'];
    $eventName = $event['event_name'];
    $eventDate = $event['event_date'];
    $startTime = $event['start_time'];
    $endTime   = $event['end_time'];
    $session   = $event['session'];

    $venueDetail = $event['venue_name']
        . ($event['venue_code']    ? " [{$event['venue_code']}]" : '')
        . ($event['building_name'] ? ", {$event['building_name']}" : '')
        . ($event['floor']         ? ", Floor {$event['floor']}" : '');

    $title = "Tomorrow: {$eventName} Venue Reminder";
    $msg   = "Reminder: \"{$eventName}\" is scheduled for tomorrow ({$eventDate}, {$session}, "
           . substr($startTime, 0, 5) . "–" . substr($endTime, 0, 5) . ") "
           . "at {$venueDetail}. Please arrive on time.";

    // Ref key: unique per event per day (allows a new notification each day)
    $refKeySuffix = "remind-{$eid}-{$todayDate}";

    log_msg("Event #{$eid}: {$eventName} @ {$venueDetail}");

    // ── FIC notifications ─────────────────────────────────────────────────
    $ficStmt = $pdo->prepare("
        SELECT fa.user_id
        FROM faculty_assignments fa
        WHERE fa.symposium_event_id = :eid AND fa.is_active = 1
    ");
    $ficStmt->execute(['eid' => $eid]);
    $ficUsers = $ficStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($ficUsers as $uid) {
        $uid = (int)$uid;
        $refKey = "u{$uid}-{$refKeySuffix}";
        if (!$isDryRun) {
            $ok = $notifModel->createWithIdempotencyKey(
                'User', $uid, $eid,
                'venue_reminder', $refKey,
                $title, $msg, 'Pending'
            );
            $ok ? $totalSent++ : $totalSkip++;
        } else {
            log_msg("  [DRY] FIC User #{$uid}: {$title}");
            $totalSent++;
        }
    }

    // ── Judge notifications ───────────────────────────────────────────────
    $jdgStmt = $pdo->prepare("
        SELECT cj.user_id
        FROM competition_judges cj
        WHERE cj.symposium_event_id = :eid AND cj.is_active = 1
    ");
    $jdgStmt->execute(['eid' => $eid]);
    $jdgUsers = $jdgStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($jdgUsers as $uid) {
        $uid = (int)$uid;
        $refKey = "u{$uid}-{$refKeySuffix}";
        if (!$isDryRun) {
            $ok = $notifModel->createWithIdempotencyKey(
                'User', $uid, $eid,
                'venue_reminder', $refKey,
                $title, $msg, 'Pending'
            );
            $ok ? $totalSent++ : $totalSkip++;
        } else {
            log_msg("  [DRY] Judge User #{$uid}: {$title}");
            $totalSent++;
        }
    }

    // ── Student notifications ─────────────────────────────────────────────
    $stuStmt = $pdo->prepare("
        SELECT a.student_id
        FROM applications a
        WHERE a.symposium_event_id = :eid
          AND a.application_status NOT IN ('Withdrawn', 'Cancelled', 'Rejected')
    ");
    $stuStmt->execute(['eid' => $eid]);
    $students = $stuStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($students as $sid) {
        $sid = (int)$sid;
        $refKey = "s{$sid}-{$refKeySuffix}";
        if (!$isDryRun) {
            $ok = $notifModel->createWithIdempotencyKey(
                'Student', $sid, $eid,
                'venue_reminder', $refKey,
                $title, $msg, 'Pending'
            );
            $ok ? $totalSent++ : $totalSkip++;
        } else {
            log_msg("  [DRY] Student #{$sid}: {$title}");
            $totalSent++;
        }
    }

    log_msg("  FIC: " . count($ficUsers) . " | Judges: " . count($jdgUsers) . " | Students: " . count($students));
}

log_msg('');
log_msg(str_repeat('─', 60));
log_msg("Complete. Sent: {$totalSent} | Skipped (duplicate): {$totalSkip}");

// ─────────────────────────────────────────────────────────────────────────────
function log_msg(string $msg): void
{
    echo $msg . PHP_EOL;
}
