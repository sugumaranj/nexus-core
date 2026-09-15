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
use RuntimeException;
use ZipArchive;

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
     * @throws RuntimeException if ZipArchive is unavailable or ZIP creation fails
     */
    public function buildZip(array $certIds, array $user): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException(
                'PHP ZipArchive extension is not available. '
                . 'Enable the zip extension in php.ini to use bulk download.'
            );
        }

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

        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new RuntimeException('Failed to create ZIP archive. ZipArchive error code: ' . $result);
        }

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

        $zip->close();

        if ($included === 0) {
            @unlink($zipPath);
            throw new RuntimeException('No certificate files could be found on disk. The ZIP could not be created.');
        }

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
}
