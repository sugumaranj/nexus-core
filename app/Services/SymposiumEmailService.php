<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS
 * =========================================================================
 * File        : SymposiumEmailService.php
 * Location    : app/Services/
 * Description : Symposium-specific email notification orchestrator.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * WHAT THIS SERVICE DOES
 * ─────────────────────────────────────────────────────────────────────────
 * This service is the ONLY place that decides:
 *   (a) WHEN to send email (which trigger event)
 *   (b) WHO receives it (dynamically resolved — no hardcoded dept IDs)
 *   (c) WHAT it says (renders the correct template)
 *   (d) HOW it is delivered (enqueued to email_queue for retry)
 *   (e) HOW the dashboard syncs (NotificationModel::createForStudent)
 *
 * The service NEVER sends emails directly.
 * It only enqueues jobs and creates in-app notifications.
 * Actual SMTP delivery is handled by EmailService::processQueue()
 * running inside the CLI worker script every 5 minutes.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * TRIGGER EVENTS
 * ─────────────────────────────────────────────────────────────────────────
 *   principal_approved    → Trigger 1  (SymposiumService::approve)
 *   registration_open     → Trigger 2  (SchedulingController::markComplete
 *                                       or openRegistration)
 *   registration_reminder → Trigger 3  (CLI worker: process_reminders.php)
 *   registration_closed   → Trigger 4  (SchedulingController or cron)
 *
 * ─────────────────────────────────────────────────────────────────────────
 * RECIPIENT DETERMINATION — NO HARDCODED IDs
 * ─────────────────────────────────────────────────────────────────────────
 * 1. Fetch organizing dept codes from `symposium_departments` join `departments`.
 * 2. Map those codes to TARGET student department codes:
 *      • Any PG dept (PGCS / PGCA)        → target CA + CS  (Nexus rule)
 *      • UG dept CA                        → target CA + CS
 *      • UG dept CS                        → target CA + CS
 *      • Any other dept code               → target that dept only
 * 3. Resolve actual department_ids by querying `departments` for the codes.
 * 4. SELECT students WHERE department_id IN (...) AND email IS NOT NULL
 *    AND account_status = 'Active'.
 *
 * The mapping is driven by department_code strings from the DB — no numbers.
 * Adding a new department automatically works without code changes.
 *
 * =========================================================================
 */

namespace App\Services;


use App\Models\AuditLogModel;
use App\Models\EmailQueueModel;
use App\Models\NotificationModel;
use App\Models\SymposiumDepartmentModel;
use App\Models\SymposiumModel;
use App\Models\SystemSettingModel;
use PDO;
use App\Database\Database;

final class SymposiumEmailService
{
    // ─── Dependencies ────────────────────────────────────────────────────────

    private EmailQueueModel         $queueModel;
    private NotificationModel       $notifModel;
    private AuditLogModel           $auditModel;
    private SymposiumModel          $sympModel;
    private SymposiumDepartmentModel $deptModel;

    private SystemSettingModel      $settings;
    private PDO                     $db;

    // ─── Constants ───────────────────────────────────────────────────────────

    /**
     * UG department codes that are the TARGET audience for Nexus.
     * These are codes from the `departments.department_code` column.
     *
     * Nexus is organized by PG departments but is FOR UG BCA + B.Sc. CS students.
     * This constant drives the recipient mapping — not IDs, only codes.
     */
    private const NEXUS_TARGET_CODES = ['CA', 'CS'];

    /**
     * Dept codes that are PG (organizers of Nexus).
     * When any of these organizes, recipients = NEXUS_TARGET_CODES.
     */
    private const PG_DEPT_CODES = ['PGCS', 'PGCA'];

    // ─────────────────────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->queueModel = new EmailQueueModel();
        $this->notifModel = new NotificationModel();
        $this->auditModel = new AuditLogModel();
        $this->sympModel  = new SymposiumModel();
        $this->deptModel  = new SymposiumDepartmentModel();

        $this->settings   = new SystemSettingModel();
        $this->db         = Database::getConnection();
    }

    // =========================================================================
    // TRIGGER 1 — Principal Approved
    // =========================================================================

    /**
     * Enqueue announcement emails when a symposium is approved by the Principal.
     *
     * Called by SymposiumService::approve() immediately after DB commit.
     * The HTTP request returns immediately; delivery happens in the background.
     *
     * @param int $symposiumId
     * @param int $triggeredByUserId  Principal's user_id
     *
     * @return array{enqueued: int, skipped: int, reason?: string}
     */
    public function sendApprovalAnnouncementEmails(int $symposiumId, int $triggeredByUserId): array
    {
        return $this->enqueueForEvent(
            symposiumId:    $symposiumId,
            triggerEvent:   'principal_approved',
            triggeredBy:    $triggeredByUserId,
            subject:        fn(array $s) => "🎓 {$s['title']} — Officially Approved! Registration Now Preparing",
            templateFile:   'symposium_announcement',
            extraVars:      fn(array $s) => $this->buildAnnouncementVars($s),
            inAppTitle:     fn(array $s) => "✅ {$s['title']} — Approved!",
            inAppMessage:   fn(array $s) => "{$s['title']} has been officially approved. Registration opens on " . $this->formatDate($s['registration_start']) . '. Log in to the Student Portal to register.',
            inAppChannel:   'Email',
        );
    }

    // =========================================================================
    // TRIGGER 2 — Registration Open
    // =========================================================================

    /**
     * Enqueue emails when registration is formally opened (Scheduling Complete).
     *
     * @param int $symposiumId
     * @param int $triggeredByUserId
     *
     * @return array
     */
    public function sendRegistrationOpenEmails(int $symposiumId, int $triggeredByUserId): array
    {
        return $this->enqueueForEvent(
            symposiumId:   $symposiumId,
            triggerEvent:  'registration_open',
            triggeredBy:   $triggeredByUserId,
            subject:       fn(array $s) => "🏆 {$s['title']} — Registration is NOW OPEN!",
            templateFile:  'registration_open',
            extraVars:     fn(array $s) => $this->buildRegistrationOpenVars($s),
            inAppTitle:    fn(array $s) => "🏆 {$s['title']} — Registration Open!",
            inAppMessage:  fn(array $s) => "Registration for {$s['title']} is now open! Deadline: " . $this->formatDate($s['registration_end']) . '. Register before it closes.',
            inAppChannel:  'Email',
        );
    }

    // =========================================================================
    // TRIGGER 3 — Registration Reminder (3-day warning, unregistered only)
    // =========================================================================

    /**
     * Enqueue 3-day reminder emails to students who have NOT yet registered.
     *
     * Called by: scripts/process_reminders.php (cron, runs daily).
     * The script checks if today == (registration_end - 3 days).
     *
     * @param int $symposiumId
     * @param int $triggeredByUserId  0 if triggered by cron (use system user 1)
     *
     * @return array
     */
    public function sendRegistrationReminderEmails(int $symposiumId, int $triggeredByUserId = 1): array
    {
        $symposium = $this->sympModel->findById($symposiumId);
        if (!$symposium) {
            return ['enqueued' => 0, 'skipped' => 0, 'reason' => 'Symposium not found'];
        }

        // Get ALL eligible recipients.
        $allRecipients = $this->getEmailRecipientsForSymposium($symposiumId);
        if (empty($allRecipients)) {
            return ['enqueued' => 0, 'skipped' => 0, 'reason' => 'No recipients'];
        }

        // Filter to only students who have NOT registered.
        $registeredStudentIds = $this->getRegisteredStudentIds($symposiumId);
        $unregistered = array_filter(
            $allRecipients,
            fn($r) => !in_array((int) $r['student_id'], $registeredStudentIds, true)
        );

        if (empty($unregistered)) {
            return ['enqueued' => 0, 'skipped' => count($allRecipients), 'reason' => 'All students already registered'];
        }

        $regEnd   = $this->formatDate($symposium['registration_end'] ?? '');
        $daysLeft = max(1, (int) ceil(
            (strtotime($symposium['registration_end'] ?? 'now') - time()) / 86400
        ));

        $enqueued = 0;

        foreach ($unregistered as $recipient) {
            $html = $this->renderTemplate('registration_reminder', array_merge([
                'studentName'       => $recipient['full_name'],
                'symposiumTitle'    => $symposium['title'],
                'registrationEnd'   => $regEnd,
                'daysLeft'          => $daysLeft,
                'baseUrl'           => $this->baseUrl(),
            ], $this->layoutVars($symposium)));

            $this->queueModel->enqueue([
                'symposium_id'    => $symposiumId,
                'trigger_event'   => 'registration_reminder',
                'triggered_by'    => $triggeredByUserId,
                'student_id'      => $recipient['student_id'],
                'recipient_email' => $recipient['email'],
                'recipient_name'  => $recipient['full_name'],
                'subject'         => "⏰ {$symposium['title']} — Registration Closes in {$daysLeft} Day(s)!",
                'html_body'       => $html,
            ]);

            // In-app notification for reminder.
            $this->notifModel->createForStudent(
                (int) $recipient['student_id'],
                "⏰ {$symposium['title']} — Register Now!",
                "Registration closes in {$daysLeft} day(s) ({$regEnd}). You haven't registered yet. Log in and register before it's too late!"
            );

            $enqueued++;
        }

        $this->auditModel->log(
            'EmailNotification', $symposiumId,
            "Reminder emails enqueued for {$enqueued} unregistered students",
            $triggeredByUserId, "Trigger: registration_reminder"
        );

        // Trigger background worker asynchronously
        if ($enqueued > 0) {
            try {
                $phpPath    = 'C:\\xampp\\php\\php.exe';
                $scriptPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'process_email_queue.php';
                $cmd        = "start /B \"\" \"{$phpPath}\" \"{$scriptPath}\" > NUL 2>&1";
                if (function_exists('popen')) {
                    pclose(popen($cmd, "r"));
                }
            } catch (\Throwable $e) {}
        }

        return ['enqueued' => $enqueued, 'skipped' => count($allRecipients) - $enqueued];
    }

    // =========================================================================
    // TRIGGER 4 — Registration Closed
    // =========================================================================

    /**
     * Enqueue registration-closed notifications to all eligible students.
     * Registered students get a confirmation; unregistered get a "missed" notice.
     *
     * @param int $symposiumId
     * @param int $triggeredByUserId
     *
     * @return array
     */
    public function sendRegistrationClosedEmails(int $symposiumId, int $triggeredByUserId): array
    {
        $symposium = $this->sympModel->findById($symposiumId);
        if (!$symposium) {
            return ['enqueued' => 0, 'skipped' => 0, 'reason' => 'Symposium not found'];
        }

        $allRecipients        = $this->getEmailRecipientsForSymposium($symposiumId);
        $registeredStudentIds = $this->getRegisteredStudentIds($symposiumId);

        $enqueued = 0;

        foreach ($allRecipients as $recipient) {
            $studentId    = (int) $recipient['student_id'];
            $isRegistered = in_array($studentId, $registeredStudentIds, true);

            // Build competition names for registered students using a direct query.
            $compNames = '';
            if ($isRegistered) {
                $compSql  = '
                    SELECT c.title
                    FROM   applications a
                    JOIN   competitions c ON c.competition_id = a.competition_id
                    WHERE  a.student_id  = :student_id
                      AND  c.symposium_id = :symposium_id
                      AND  a.status NOT IN (\'Withdrawn\', \'Rejected\')
                ';
                $compStmt = $this->db->prepare($compSql);
                $compStmt->execute(['student_id' => $studentId, 'symposium_id' => $symposiumId]);
                $compNames = implode(', ', $compStmt->fetchAll(PDO::FETCH_COLUMN));
            }


            $html = $this->renderTemplate('registration_closed', array_merge([
                'studentName'      => $recipient['full_name'],
                'symposiumTitle'   => $symposium['title'],
                'eventStartDate'   => $this->formatDate($symposium['event_start_date'] ?? ''),
                'isRegistered'     => $isRegistered,
                'competitionNames' => $compNames,
                'baseUrl'          => $this->baseUrl(),
            ], $this->layoutVars($symposium)));

            $this->queueModel->enqueue([
                'symposium_id'    => $symposiumId,
                'trigger_event'   => 'registration_closed',
                'triggered_by'    => $triggeredByUserId,
                'student_id'      => $studentId,
                'recipient_email' => $recipient['email'],
                'recipient_name'  => $recipient['full_name'],
                'subject'         => $isRegistered
                    ? "✅ {$symposium['title']} — You're Registered! Get Ready."
                    : "{$symposium['title']} — Registration Closed",
                'html_body'       => $html,
            ]);

            $enqueued++;
        }

        $this->auditModel->log(
            'EmailNotification', $symposiumId,
            "Registration-closed emails enqueued for {$enqueued} students",
            $triggeredByUserId, "Trigger: registration_closed"
        );

        // Trigger background worker asynchronously
        if ($enqueued > 0) {
            try {
                $phpPath    = 'C:\\xampp\\php\\php.exe';
                $scriptPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'process_email_queue.php';
                $cmd        = "start /B \"\" \"{$phpPath}\" \"{$scriptPath}\" > NUL 2>&1";
                if (function_exists('popen')) {
                    pclose(popen($cmd, "r"));
                }
            } catch (\Throwable $e) {}
        }

        return ['enqueued' => $enqueued, 'skipped' => 0];
    }

    // =========================================================================
    // RECIPIENT RESOLVER — No hardcoded IDs
    // =========================================================================

    /**
     * Dynamically determine which students should receive emails for a symposium.
     *
     * Algorithm:
     *   1. Fetch organizing dept codes from symposium_departments join departments.
     *   2. Map organizing codes → target codes (Nexus rule: PG/UG CA/CS → CA + CS).
     *   3. Fetch department_ids for target codes.
     *   4. Query students: WHERE department_id IN (...) AND email IS NOT NULL
     *                      AND account_status = 'Active'.
     *
     * @param int $symposiumId
     *
     * @return array  Each element: [student_id, full_name, email, department_name]
     */
    public function getEmailRecipientsForSymposium(int $symposiumId): array
    {
        // Step 1 — organizing dept codes.
        $depts        = $this->deptModel->getDepartmentsForSymposium($symposiumId);
        $orgCodes     = array_column($depts, 'department_code');

        if (empty($orgCodes)) {
            return [];
        }

        // Step 2 — target dept codes (Nexus mapping, code-based).
        $targetCodes  = $this->resolveTargetDeptCodes($orgCodes);

        // Step 3 — resolve IDs from codes.
        $targetIds    = $this->getDeptIdsByCodes($targetCodes);

        if (empty($targetIds)) {
            return [];
        }

        // Step 4 — query active students with email in target departments.
        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));

        $sql = "
            SELECT
                s.student_id,
                s.full_name,
                s.email,
                d.department_name
            FROM   students s
            JOIN   departments d ON d.department_id = s.department_id
            WHERE  s.department_id IN ({$placeholders})
              AND  s.email IS NOT NULL
              AND  s.email <> ''
              AND  s.account_status = 'Active'
            ORDER  BY s.full_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($targetIds));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // PRIVATE — Internal helpers
    // =========================================================================

    /**
     * Core enqueue loop used by Triggers 1 and 2.
     *
     * @param int      $symposiumId
     * @param string   $triggerEvent
     * @param int      $triggeredBy
     * @param callable $subject      fn(array $symposium): string
     * @param string   $templateFile  Name without .php inside templates/emails/
     * @param callable $extraVars    fn(array $symposium): array
     * @param callable $inAppTitle   fn(array $symposium): string
     * @param callable $inAppMessage fn(array $symposium): string
     * @param string   $inAppChannel
     *
     * @return array{enqueued: int, skipped: int, reason?: string}
     */
    private function enqueueForEvent(
        int    $symposiumId,
        string $triggerEvent,
        int    $triggeredBy,
        callable $subject,
        string $templateFile,
        callable $extraVars,
        callable $inAppTitle,
        callable $inAppMessage,
        string $inAppChannel = 'Email'
    ): array {
        $symposium  = $this->sympModel->findById($symposiumId);
        if (!$symposium) {
            return ['enqueued' => 0, 'skipped' => 0, 'reason' => 'Symposium not found'];
        }

        $recipients = $this->getEmailRecipientsForSymposium($symposiumId);
        if (empty($recipients)) {
            return ['enqueued' => 0, 'skipped' => 0, 'reason' => 'No eligible recipients'];
        }

        $subjectStr  = $subject($symposium);
        $sharedExtra = $extraVars($symposium);
        $sharedTitle = $inAppTitle($symposium);
        $sharedMsg   = $inAppMessage($symposium);
        $enqueued    = 0;

        foreach ($recipients as $recipient) {
            // Render template per-student (personalized salutation).
            $html = $this->renderTemplate($templateFile, array_merge(
                $sharedExtra,
                $this->layoutVars($symposium),
                ['studentName' => $recipient['full_name']]
            ));

            // Enqueue email job.
            $this->queueModel->enqueue([
                'symposium_id'    => $symposiumId,
                'trigger_event'   => $triggerEvent,
                'triggered_by'    => $triggeredBy,
                'student_id'      => $recipient['student_id'],
                'recipient_email' => $recipient['email'],
                'recipient_name'  => $recipient['full_name'],
                'subject'         => $subjectStr,
                'html_body'       => $html,
            ]);

            // Create synchronized in-app notification.
            $this->notifModel->createForStudent(
                (int) $recipient['student_id'],
                $sharedTitle,
                $sharedMsg
            );

            $enqueued++;
        }

        // Write to audit log.
        $this->auditModel->log(
            'EmailNotification', $symposiumId,
            "Email jobs enqueued ({$triggerEvent}) for {$enqueued} students",
            $triggeredBy, "Trigger: {$triggerEvent}"
        );

        // Trigger the background worker asynchronously so it doesn't freeze the user's browser.
        // This is a production-ready approach for XAMPP/Windows.
        if ($enqueued > 0) {
            try {
                $phpPath    = 'C:\\xampp\\php\\php.exe';
                $scriptPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'process_email_queue.php';
                $cmd        = "start /B \"\" \"{$phpPath}\" \"{$scriptPath}\" > NUL 2>&1";
                
                if (function_exists('popen')) {
                    pclose(popen($cmd, "r"));
                }
            } catch (\Throwable $e) {
                // Fallback: ignore error
            }
        }

        return ['enqueued' => $enqueued, 'skipped' => 0];
    }


    /**
     * Map organizing department codes to target student department codes.
     *
     * Rules (code-based, not ID-based):
     *   • PG dept codes (PGCS, PGCA) → CA + CS  (Nexus is for UG CS students)
     *   • UG CA                       → CA + CS  (Nexus hosts both UG depts)
     *   • UG CS                       → CA + CS  (Nexus hosts both UG depts)
     *   • Any other code              → that same code only
     *
     * @param array<string> $orgCodes  Codes of organizing departments.
     *
     * @return array<string>  Unique target codes.
     */
    private function resolveTargetDeptCodes(array $orgCodes): array
    {
        $target = [];

        foreach ($orgCodes as $code) {
            $code = strtoupper(trim($code));

            if (in_array($code, self::PG_DEPT_CODES, true)) {
                // PG organizer → target UG students (Nexus rule)
                foreach (self::NEXUS_TARGET_CODES as $t) {
                    $target[] = $t;
                }
            } elseif (in_array($code, self::NEXUS_TARGET_CODES, true)) {
                // UG CA or CS organizer → also target both UG departments
                foreach (self::NEXUS_TARGET_CODES as $t) {
                    $target[] = $t;
                }
            } else {
                // Any other department targets only itself.
                $target[] = $code;
            }
        }

        return array_unique($target);
    }

    /**
     * Resolve department IDs from department codes.
     *
     * @param array<string> $codes
     *
     * @return array<int>
     */
    private function getDeptIdsByCodes(array $codes): array
    {
        if (empty($codes)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $sql  = "SELECT department_id FROM departments WHERE department_code IN ({$placeholders}) AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($codes));

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Get student IDs who have at least one application for this symposium's competitions.
     *
     * @param int $symposiumId
     *
     * @return array<int>
     */
    private function getRegisteredStudentIds(int $symposiumId): array
    {
        $sql = '
            SELECT DISTINCT a.student_id
            FROM   applications a
            JOIN   competitions c ON c.competition_id = a.competition_id
            WHERE  c.symposium_id = :symposium_id
              AND  a.status NOT IN (\'Withdrawn\', \'Rejected\')
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Build template variables specific to the Approval Announcement (Trigger 1).
     *
     * @param array $symposium
     *
     * @return array
     */
    private function buildAnnouncementVars(array $symposium): array
    {
        $depts = $this->deptModel->getDepartmentsForSymposium((int) $symposium['symposium_id']);
        $deptNames = implode(' & ', array_column($depts, 'department_name'));

        // Check if all events are scheduled.
        $scheduleReady = ($symposium['status'] === 'Scheduling Complete'
            || $symposium['status'] === 'Registration Open');

        return [
            'symposiumId'       => (int) $symposium['symposium_id'],
            'symposiumTitle'    => $symposium['title'],
            'symposiumCode'     => $symposium['symposium_code'],
            'academicYear'      => $symposium['academic_year'],
            'deptNames'         => $deptNames,
            'registrationStart' => $this->formatDate($symposium['registration_start']),
            'registrationEnd'   => $this->formatDate($symposium['registration_end']),
            'eventStartDate'    => $this->formatDate($symposium['event_start_date'] ?? ''),
            'eventEndDate'      => $this->formatDate($symposium['event_end_date']   ?? ''),
            'scheduleReady'     => $scheduleReady,
            'baseUrl'           => $this->baseUrl(),
        ];
    }

    /**
     * Build template variables for Registration Open (Trigger 2).
     *
     * @param array $symposium
     *
     * @return array
     */
    private function buildRegistrationOpenVars(array $symposium): array
    {
        // Count published competitions.
        $sql  = 'SELECT COUNT(*) FROM competitions WHERE symposium_id = :id AND status != \'Cancelled\'';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $symposium['symposium_id']]);
        $compCount = (int) $stmt->fetchColumn();

        return [
            'symposiumTitle'   => $symposium['title'],
            'registrationEnd'  => $this->formatDate($symposium['registration_end']),
            'eventStartDate'   => $this->formatDate($symposium['event_start_date'] ?? ''),
            'competitionCount' => $compCount,
            'baseUrl'          => $this->baseUrl(),
        ];
    }

    /**
     * Variables for the master email layout (header/footer).
     *
     * @param array $symposium
     *
     * @return array
     */
    private function layoutVars(array $symposium): array
    {
        $collegeName  = (string) $this->settings->getValue('COLLEGE_NAME', 'Government Arts and Science College');
        $collegeLogo  = (string) $this->settings->getValue('COLLEGE_LOGO', '');
        $eventLogo    = (string) $this->settings->getValue('EVENT_LOGO', '');
        $base         = $this->baseUrl();

        return [
            'collegeName'    => $collegeName,
            'collegeLogoUrl' => $collegeLogo !== '' ? $base . '/public/' . $collegeLogo : '',
            'eventLogoUrl'   => $eventLogo   !== '' ? $base . '/public/' . $eventLogo   : '',
            'headerTitle'    => $symposium['title'] ?? 'Nexus Symposium',
            'preheader'      => 'NexusCore Symposium Notification — ' . ($symposium['title'] ?? ''),
            'footerNote'     => $collegeName,
        ];
    }

    /**
     * Render a template file inside templates/emails/ and wrap it in the
     * master layout, returning the complete HTML string.
     *
     * @param string $templateName  e.g. 'symposium_announcement'
     * @param array  $vars          Variables to extract into template scope.
     *
     * @return string  Complete HTML email string.
     */
    private function renderTemplate(string $templateName, array $vars): string
    {
        $templatePath = dirname(__DIR__, 2) . "/templates/emails/{$templateName}.php";
        $layoutPath   = dirname(__DIR__, 2) . '/templates/emails/email_layout.php';

        // Render child content first.
        ob_start();
        extract($vars, EXTR_SKIP);
        require $templatePath;
        $content = ob_get_clean();

        // Wrap in master layout.
        ob_start();
        extract($vars, EXTR_SKIP);
        require $layoutPath;
        return ob_get_clean();
    }

    /**
     * Format a date/datetime string to human-readable format.
     *
     * @param string $date
     *
     * @return string  e.g. "08 Aug 2026, 10:00 AM" or "N/A"
     */
    private function formatDate(string $date): string
    {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return 'N/A';
        }

        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }

        // Include time only if it's a datetime.
        return str_contains($date, ':')
            ? \App\Helpers\DateHelper::dateTime(date('Y-m-d H:i:s', $ts))
            : \App\Helpers\DateHelper::date(date('Y-m-d H:i:s', $ts));
    }

    /**
     * Get the base URL of the application.
     *
     * @return string  e.g. "http://localhost/NexusCore"
     */
    private function baseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script   = dirname($_SERVER['SCRIPT_NAME'] ?? '', 2);
        return rtrim("{$protocol}://{$host}{$script}", '/');
    }
}
