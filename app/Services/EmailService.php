<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS
 * =========================================================================
 * File        : EmailService.php
 * Location    : app/Services/
 * Description : Core email delivery service using PHPMailer.
 *
 * Responsibilities
 * ─────────────────────────────────────────────────────────────
 *   • Load SMTP credentials from system_settings (Admin-configurable)
 *   • Send a single HTML email via PHPMailer
 *   • Process the email_queue in batches (called by the CLI worker)
 *   • Apply exponential-backoff retry logic via EmailQueueModel
 *   • Write every outcome to email_send_log (EmailLogModel)
 *   • Provide a testConnection() method for the Admin settings UI
 *
 * Queue Architecture
 * ─────────────────────────────────────────────────────────────
 *   SymposiumEmailService  →  enqueues jobs  →  email_queue table
 *                                                      ↓
 *   scripts/process_email_queue.php  →  EmailService::processQueue()
 *                                                      ↓
 *                                           PHPMailer (SMTP)
 *                                                      ↓
 *                                           email_send_log (audit)
 *
 * The HTTP request only writes to email_queue and returns immediately.
 * Actual delivery is done by the CLI worker running every 5 minutes
 * via Windows Task Scheduler / XAMPP cron.
 *
 * =========================================================================
 */

namespace App\Services;

use App\Models\EmailQueueModel;
use App\Models\EmailLogModel;
use App\Models\SystemSettingModel;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

final class EmailService
{
    // ─── Dependencies ────────────────────────────────────────────────────────

    private EmailQueueModel   $queueModel;
    private EmailLogModel     $logModel;
    private SystemSettingModel $settings;

    // ─── SMTP config (lazy-loaded from system_settings) ──────────────────────

    private ?array $smtpConfig = null;

    // ─── Queue worker config ──────────────────────────────────────────────────

    /** How many queue jobs to process per worker invocation. */
    private const BATCH_SIZE = 30;

    /** PHPMailer timeout in seconds. */
    private const SMTP_TIMEOUT = 15;

    public function __construct()
    {
        $this->queueModel = new EmailQueueModel();
        $this->logModel   = new EmailLogModel();
        $this->settings   = new SystemSettingModel();
    }

    // =========================================================================
    // QUEUE PROCESSOR — called by scripts/process_email_queue.php
    // =========================================================================

    /**
     * Process a batch of pending email jobs from the queue.
     *
     * This is the only method that sends emails.
     * It is called exclusively by the CLI worker script.
     *
     * Flow per job:
     *   1. Claim batch atomically (prevents duplicate sends).
     *   2. Build PHPMailer instance using stored html_body.
     *   3. On success → markSent() + append log (Sent).
     *   4. On failure → markFailed() with backoff + append log (Failed).
     *   5. Continue to next job regardless of outcome.
     *
     * @return array{processed: int, sent: int, failed: int}
     */
    public function processQueue(): array
    {
        // Load SMTP config once for the entire batch.
        $config = $this->loadSmtpConfig();

        // Guard: if email is disabled or not configured, skip.
        if (!$this->isConfigured($config)) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'reason' => 'Email not configured or disabled'];
        }

        $workerToken = 'worker_' . bin2hex(random_bytes(8));
        $jobs        = $this->queueModel->claimBatch(self::BATCH_SIZE, $workerToken);

        $sent    = 0;
        $failed  = 0;

        foreach ($jobs as $job) {
            $queueId     = (int) $job['queue_id'];
            $attempts    = (int) $job['attempts'];
            $maxAttempts = (int) $job['max_attempts'];

            try {
                // Build and send via PHPMailer.
                $this->sendWithMailer(
                    config:       $config,
                    toEmail:      $job['recipient_email'],
                    toName:       (string) ($job['recipient_name'] ?? ''),
                    subject:      $job['subject'],
                    htmlBody:     $job['html_body'],
                );

                // Success path.
                $this->queueModel->markSent($queueId);
                $this->logModel->append([
                    'symposium_id'    => $job['symposium_id'],
                    'trigger_event'   => $job['trigger_event'],
                    'triggered_by'    => $job['triggered_by'] ?? null,
                    'recipient_email' => $job['recipient_email'],
                    'recipient_name'  => $job['recipient_name'] ?? null,
                    'student_id'      => $job['student_id']     ?? null,
                    'status'          => 'Sent',
                    'error_message'   => null,
                ]);

                $sent++;

            } catch (\Throwable $e) {
                // Failure path — mark failed with backoff, never stops the loop.
                $errorMsg = $e->getMessage();

                $this->queueModel->markFailed($queueId, $attempts, $maxAttempts, $errorMsg);
                $this->logModel->append([
                    'symposium_id'    => $job['symposium_id'],
                    'trigger_event'   => $job['trigger_event'],
                    'triggered_by'    => $job['triggered_by'] ?? null,
                    'recipient_email' => $job['recipient_email'],
                    'recipient_name'  => $job['recipient_name'] ?? null,
                    'student_id'      => $job['student_id']     ?? null,
                    'status'          => 'Failed',
                    'error_message'   => $errorMsg,
                ]);

                $failed++;
            }
        }

        return [
            'processed' => count($jobs),
            'sent'      => $sent,
            'failed'    => $failed,
        ];
    }

    // =========================================================================
    // SMTP TEST — called by Admin Settings "Send Test Email"
    // =========================================================================

    /**
     * Send a test email to verify SMTP configuration.
     *
     * @param string $toEmail  Address to send the test to (usually Admin's own email).
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(string $toEmail): array
    {
        $config = $this->loadSmtpConfig();

        if (!$this->isConfigured($config)) {
            return ['success' => false, 'message' => 'Email is disabled or SMTP credentials are not configured.'];
        }

        try {
            $html = '<p>This is a test email from <strong>NexusCore EMS</strong>. Your email configuration is working correctly.</p>';

            $this->sendWithMailer(
                config:   $config,
                toEmail:  $toEmail,
                toName:   'NexusCore Admin',
                subject:  '[NexusCore] SMTP Test Email',
                htmlBody: $html,
            );

            return ['success' => true, 'message' => "Test email sent to {$toEmail} successfully."];

        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'SMTP error: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // PRIVATE — Core mailer
    // =========================================================================

    /**
     * Build a PHPMailer instance and send one email.
     *
     * Throws on any SMTP or configuration error.
     *
     * @param array  $config   SMTP config from system_settings.
     * @param string $toEmail
     * @param string $toName
     * @param string $subject
     * @param string $htmlBody
     *
     * @throws MailerException|RuntimeException
     */
    private function sendWithMailer(
        array  $config,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody
    ): void {
        $mail = new PHPMailer(true); // true = throw on error

        // ── SMTP settings ────────────────────────────────────────────────────
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->Port       = (int) $config['port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = (strtolower($config['encryption']) === 'ssl')
                                ? PHPMailer::ENCRYPTION_SMTPS
                                : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout    = self::SMTP_TIMEOUT;

        // ── Sender & recipient ───────────────────────────────────────────────
        $mail->setFrom($config['from_address'], $config['from_name']);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo($config['from_address'], $config['from_name']);

        // ── Content ──────────────────────────────────────────────────────────
        $mail->isHTML(true);
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        // ── Embed Logos (CID) ────────────────────────────────────────────────
        $baseDir = dirname(__DIR__, 2) . '/public/';
        
        if (!empty($config['college_logo']) && file_exists($baseDir . $config['college_logo'])) {
            $collegeLogoName = basename($config['college_logo']);
            $mail->addEmbeddedImage($baseDir . $config['college_logo'], 'college_logo', $collegeLogoName);
        }
        if (!empty($config['event_logo']) && file_exists($baseDir . $config['event_logo'])) {
            $eventLogoName = basename($config['event_logo']);
            $mail->addEmbeddedImage($baseDir . $config['event_logo'], 'event_logo', $eventLogoName);
        }


        // ── Plain-text fallback ──────────────────────────────────────────────
        // First, explicitly remove <style>...</style> and <head>...</head> blocks
        // so raw CSS code doesn't leak into the plain-text body (which Gmail uses for inbox preview).
        $altText = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $htmlBody) ?? $htmlBody;
        $altText = preg_replace('/<head\b[^>]*>(.*?)<\/head>/is', '', $altText) ?? $altText;
        
        // Then replace common line breaks with actual newlines before stripping remaining tags
        $mail->AltBody = strip_tags(
            preg_replace(['/<br\s*\/?>/i', '/<\/p>/i', '/<\/li>/i'], "\n", $altText) ?? ''
        );

        // ── Send ─────────────────────────────────────────────────────────────
        $mail->send();
    }

    // =========================================================================
    // PRIVATE — Config loader
    // =========================================================================

    /**
     * Load SMTP settings from system_settings (cached for this instance).
     *
     * @return array
     */
    private function loadSmtpConfig(): array
    {
        if ($this->smtpConfig !== null) {
            return $this->smtpConfig;
        }

        $this->smtpConfig = [
            'enabled'      => strtoupper((string) $this->settings->getValue('MAIL_ENABLED', 'NO')) === 'YES',
            'host'         => (string) $this->settings->getValue('MAIL_HOST',         'smtp.gmail.com'),
            'port'         => (int)    $this->settings->getValue('MAIL_PORT',         '587'),
            'encryption'   => (string) $this->settings->getValue('MAIL_ENCRYPTION',   'tls'),
            'from_address' => (string) $this->settings->getValue('MAIL_FROM_ADDRESS', ''),
            'from_name'    => (string) $this->settings->getValue('MAIL_FROM_NAME',    'Nexus Symposium'),
            'username'     => (string) $this->settings->getValue('MAIL_USERNAME',     ''),
            'password'     => (string) $this->settings->getValue('MAIL_PASSWORD',     ''),
            'college_logo' => (string) $this->settings->getValue('COLLEGE_LOGO',      ''),
            'event_logo'   => (string) $this->settings->getValue('EVENT_LOGO',        ''),
        ];

        return $this->smtpConfig;
    }

    /**
     * Check whether email is enabled and all required fields are set.
     *
     * @param array $config
     *
     * @return bool
     */
    private function isConfigured(array $config): bool
    {
        return $config['enabled']
            && $config['host']         !== ''
            && $config['from_address'] !== ''
            && $config['username']     !== ''
            && $config['password']     !== '';
    }
}
