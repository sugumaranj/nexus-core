<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : SettingsController.php
 * Location    : app/Controllers/
 * Description : Controller for Admin Settings (Logo Management, Email Config).
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\SystemSettingModel;
use App\Services\EmailService;

final class SettingsController extends BaseController
{
    private SystemSettingModel $settingModel;

    public function __construct()
    {
        $this->settingModel = new SystemSettingModel();
    }

    /**
     * Display Settings Dashboard
     */
    public function index(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Admin');

        // Load email settings for the form
        $emailKeys = [
            'MAIL_ENABLED', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_ENCRYPTION',
            'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'MAIL_USERNAME', 'MAIL_PASSWORD',
        ];
        $emailSettings = [];
        foreach ($emailKeys as $key) {
            $emailSettings[$key] = $this->settingModel->getValue($key, '');
        }

        $this->render(
            'settings.index',
            [
                'pageTitle'     => 'System Settings',
                'user'          => Session::get('user'),
                'collegeLogo'   => $this->settingModel->getValue('COLLEGE_LOGO'),
                'eventLogo'     => $this->settingModel->getValue('EVENT_LOGO'),
                'emailSettings' => $emailSettings,
            ]
        );
    }

    /**
     * Handle File Uploads for Logos
     */
    public function update(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Admin');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/settings');
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/logos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $userId = (int) (Session::get('user')['user_id'] ?? 0);
        $successCount = 0;

        $allowedTypes = ['image/png'];
        $maxSize = 2 * 1024 * 1024;

        if (isset($_FILES['college_logo']) && $_FILES['college_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['college_logo'];
            if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'college_logo_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $this->settingModel->updateValue('COLLEGE_LOGO', 'uploads/logos/' . $filename, 'Official College Logo', $userId);
                    $successCount++;
                }
            } else {
                Session::flash('error', 'Invalid College Logo file. Must be PNG and max 2MB.');
                $this->redirect('/settings');
            }
        }

        if (isset($_FILES['event_logo']) && $_FILES['event_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['event_logo'];
            if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'event_logo_' . time() . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                    $this->settingModel->updateValue('EVENT_LOGO', 'uploads/logos/' . $filename, 'Nexus Event Logo', $userId);
                    $successCount++;
                }
            } else {
                Session::flash('error', 'Invalid Event Logo file. Must be PNG and max 2MB.');
                $this->redirect('/settings');
            }
        }

        if ($successCount > 0) {
            Session::flash('success', 'Logos updated successfully.');
        }

        $this->redirect('/settings');
    }

    // =========================================================================
    // EMAIL CONFIGURATION
    // =========================================================================

    /**
     * Save SMTP / Email configuration.
     * POST /settings/email
     */
    public function updateEmailSettings(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Admin');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/settings');
        }

        $userId = (int) (Session::get('user')['user_id'] ?? 0);

        $fields = [
            'MAIL_ENABLED'      => strtoupper(trim($_POST['mail_enabled']      ?? 'NO')) === 'YES' ? 'YES' : 'NO',
            'MAIL_HOST'         => trim($_POST['mail_host']         ?? ''),
            'MAIL_PORT'         => trim($_POST['mail_port']         ?? '587'),
            'MAIL_ENCRYPTION'   => strtolower(trim($_POST['mail_encryption']   ?? 'tls')),
            'MAIL_FROM_ADDRESS' => trim($_POST['mail_from_address'] ?? ''),
            'MAIL_FROM_NAME'    => trim($_POST['mail_from_name']    ?? 'Nexus Symposium'),
            'MAIL_USERNAME'     => trim($_POST['mail_username']     ?? ''),
        ];

        // Only update password if a new value is explicitly provided.
        $newPassword = trim($_POST['mail_password'] ?? '');
        if ($newPassword !== '') {
            $fields['MAIL_PASSWORD'] = $newPassword;
        }

        foreach ($fields as $key => $value) {
            $this->settingModel->updateValue($key, $value, null, $userId);
        }

        Session::flash('success', 'Email configuration saved successfully.');
        $this->redirect('/settings');
    }

    /**
     * AJAX: Send a test email using current SMTP settings.
     * POST /settings/email/test
     * Returns JSON: { success: bool, message: string }
     */
    public function testEmail(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Admin');

        header('Content-Type: application/json');

        $user    = Session::get('user');
        $toEmail = trim($_POST['test_email'] ?? ($user['email'] ?? ''));

        if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
            return;
        }

        try {
            $emailSvc = new EmailService();
            $result   = $emailSvc->testConnection($toEmail);
            echo json_encode($result);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }
}
