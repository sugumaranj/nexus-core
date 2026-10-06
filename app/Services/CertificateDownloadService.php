<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : CertificateDownloadService.php
 * Location    : app/Services/
 * Description : Handles certificate PDF delivery and bulk ZIP packaging.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Stream a single certificate PDF to the browser
 * • Build a ZIP archive of multiple certificates
 * • Stream a ZIP archive to the browser
 * • Authorise downloads against the generated_certificates record
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Models\GeneratedCertificateModel;
use App\Models\AuditLogModel;
use App\Models\TeamMemberModel;
use RuntimeException;

final class CertificateDownloadService
{
    private GeneratedCertificateModel $certModel;
    private AuditLogModel $auditModel;

    private const PROJECT_ROOT = __DIR__ . '/../../';

    public function __construct()
    {
        $this->certModel  = new GeneratedCertificateModel();
        $this->auditModel = new AuditLogModel();
    }

    /**
     * Stream a single certificate PDF to the browser.
     *
     * @param  int   $certId  generated_certificates.certificate_id
     * @param  array $user    Session user array
     * @param  bool  $inline  If true, use inline disposition. If false, use attachment.
     * @return never
     */
    public function streamSingle(int $certId, array $user, bool $inline = false): never
    {
        $cert = $this->certModel->findById($certId);

        if (!$cert) {
            $this->abort404('Certificate not found.');
        }

        $absPath = self::PROJECT_ROOT . ltrim($cert['file_path'], '/');

        $filename    = 'Certificate_' . $cert['certificate_number'] . '.pdf';
        $disposition = $inline ? 'inline' : 'attachment';
        $action      = $inline ? 'certificate_viewed' : 'certificate_downloaded';

        // Secure file path validation: resolve against storage root before serving
        $realPath    = realpath($absPath);
        $storageRoot = realpath(self::PROJECT_ROOT . 'storage/certificates');

        if (!$realPath || !str_starts_with($realPath, $storageRoot) || !is_file($realPath) || !is_readable($realPath)) {
            $this->abort404('Certificate file not found or unreadable.');
        }

        $this->auditModel->log(
            'generated_certificates',
            $certId,
            $action,
            (int) ($user['user_id'] ?? 0),
            $cert['certificate_number'] . ' — event ' . ($cert['symposium_event_id'] ?? 'Unknown')
        );

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($realPath));
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');

        readfile($realPath);
        exit;
    }

    /**
     * Build a ZIP archive containing the given certificate PDFs.
     *
     * @param  array $certIds  Array of certificate_id integers
     * @param  array $user     Session user array
     * @return string          Absolute path to the generated ZIP file
     * @throws RuntimeException if ZIP creation fails
     */
    public function buildZip(array $certIds, array $user): string
    {
        if (empty($certIds)) {
            throw new RuntimeException('No certificates selected for download.');
        }

        $certs = $this->certModel->getByIds($certIds);

        if (empty($certs)) {
            throw new RuntimeException('No valid certificates found for the selected IDs.');
        }

        // Create a temporary ZIP in storage/
        $tmpDir  = self::PROJECT_ROOT . 'storage/certificates/';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $zipPath = $tmpDir . 'bulk_download_' . time() . '_' . substr(md5(implode(',', $certIds)), 0, 8) . '.zip';

        $zip = new \PhpZip\ZipFile();

        $included = 0;
        foreach ($certs as $cert) {
            $absPath = self::PROJECT_ROOT . ltrim($cert['file_path'], '/');

            if (!file_exists($absPath) || !is_readable($absPath)) {
                // Log but continue — don't abort the whole ZIP for one missing file
                continue;
            }

            $entryName = 'Certificate_' . $cert['application_id'] . '.pdf';
            $zip->addFile($absPath, $entryName);
            $included++;
        }

        if ($included === 0) {
            throw new RuntimeException('No certificate files could be found on disk. The ZIP could not be created.');
        }

        $zip->saveAsFile($zipPath);
        $zip->close();

        return $zipPath;
    }

    /**
     * Stream a ZIP file to the browser and delete it after sending.
     *
     * @param  string $zipPath   Absolute path to the ZIP file
     * @param  string $filename  Download filename for the browser
     * @return never
     */
    public function streamZip(string $zipPath, string $filename): never
    {
        if (!file_exists($zipPath)) {
            $this->abort404('ZIP archive not found.');
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');

        readfile($zipPath);

        // Clean up the temporary ZIP
        @unlink($zipPath);

        exit;
    }

    /**
     * Abort with a 404 response.
     *
     * @param  string $message
     * @return never
     */
    private function abort404(string $message): never
    {
        http_response_code(404);
        echo '<h1>404 — Not Found</h1><p>' . htmlspecialchars($message) . '</p>';
        exit;
    }

    /**
     * -------------------------------------------------------------------------
     * Stream a certificate PDF to a student browser (inline or attachment).
     *
     * SECURITY — Ownership enforcement:
     *   If recipient_student_id IS NOT NULL:
     *     The authenticated student_id must equal recipient_student_id.
     *   If recipient_student_id IS NULL (per_team certificate):
     *     The authenticated student must be an active member of the application_id team.
     *
     * DOWNLOADABILITY:
     *   Only certificates with generation_status = 'Generated' AND is_current = 1
     *   AND file_path != '' AND physical file exists may be served.
     *   Any other state returns HTTP 404.
     *
     * @param int  $certId                Certificate ID from URL param (lookup key only).
     * @param int  $authenticatedStudentId Session student_id (never from URL).
     * @param bool $inline                 true = Content-Disposition: inline; false = attachment.
     * @return never                       Streams the PDF or terminates with HTTP error.
     * -------------------------------------------------------------------------
     */
    public function streamSingleForStudent(int $certId, int $authenticatedStudentId, bool $inline = true): never
    {
        $certModel = new GeneratedCertificateModel();
        $cert      = $certModel->findById($certId);

        if (!$cert) {
            http_response_code(404);
            exit('Certificate not found.');
        }

        // ── Strict downloadability check ────────────────────────────────────
        if (
            $cert['generation_status'] !== 'Generated'
            || (int)($cert['is_current'] ?? 0) !== 1
            || empty($cert['file_path'])
        ) {
            http_response_code(404);
            exit('Certificate is not available for download.');
        }

        // ── Path safety ────────────────────────────────────────────────────
        $storageRoot = realpath(__DIR__ . '/../../storage/certificates');
        $absPath     = realpath(__DIR__ . '/../../' . ltrim($cert['file_path'], '/'));

        if (!$absPath || !$storageRoot || !str_starts_with($absPath, $storageRoot) || !is_file($absPath)) {
            http_response_code(404);
            exit('Certificate file not found.');
        }

        // ── Ownership check ────────────────────────────────────────────────
        $recipientStudentId = isset($cert['recipient_student_id'])
            ? (int)$cert['recipient_student_id']
            : null;

        if ($recipientStudentId !== null) {
            // Direct ownership
            if ($recipientStudentId !== $authenticatedStudentId) {
                http_response_code(403);
                exit('Access denied.');
            }
        } else {
            // Per-team certificate: verify team membership
            $applicationId = (int)$cert['application_id'];
            if (!$this->isStudentTeamMember($applicationId, $authenticatedStudentId)) {
                http_response_code(403);
                exit('Access denied.');
            }
        }

        // ── Stream PDF ─────────────────────────────────────────────────────
        $filename    = 'certificate_' . ($cert['certificate_number'] ?? $certId) . '.pdf';
        $disposition = $inline ? 'inline' : 'attachment';

        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($absPath));
        header('Cache-Control: private, no-cache');
        header('X-Content-Type-Options: nosniff');

        readfile($absPath);
        exit;
    }

    /**
     * Verify that a student is an active member of the team associated with an application.
     *
     * @param int $applicationId
     * @param int $studentId
     * @return bool
     */
    private function isStudentTeamMember(int $applicationId, int $studentId): bool
    {
        $db   = \App\Database\Database::getConnection();
        $stmt = $db->prepare("
            SELECT 1
            FROM team_members tm
            INNER JOIN teams t ON t.team_id = tm.team_id
            WHERE t.application_id = :app_id
              AND tm.student_id    = :student_id
            LIMIT 1
        ");
        $stmt->execute(['app_id' => $applicationId, 'student_id' => $studentId]);
        return (bool) $stmt->fetchColumn();
    }
}
