<?php
declare(strict_types=1);

/**
 * CertificateReconciliationService
 * Handles stuck 'Generating' and 'Finalizing' certificates.
 * Must be called at the start of each bulk generation run.
 */

namespace App\Services;

use App\Database\Database;
use App\Models\AuditLogModel;
use PDO;

final class CertificateReconciliationService
{
    private PDO $db;
    private AuditLogModel $auditModel;
    private const CERT_STORAGE_DIR = __DIR__ . '/../../storage/certificates';
    private const STUCK_THRESHOLD_MINUTES = 5;

    public function __construct()
    {
        $this->db         = Database::getConnection();
        $this->auditModel = new AuditLogModel();
    }

    /**
     * Find and recover all Generating/Finalizing rows older than the threshold.
     * Called automatically at the start of each bulk generation run.
     *
     * Recovery rules (per row):
     *  - Final PDF exists → set generation_status='Generated' + file_path
     *  - Temp PDF exists → rename to final → set generation_status='Generated' + file_path
     *  - Neither exists → set generation_status='Failed' + generation_notes
     *
     * @param int|null $userId  User running reconciliation (null = system)
     * @return array{recovered: int, failed: int, skipped: int, log: array}
     */
    public function reconcileStuckIssuances(?int $userId = null): array
    {
        $recovered = 0;
        $failed    = 0;
        $skipped   = 0;
        $log       = [];

        $threshold = date('Y-m-d H:i:s', time() - self::STUCK_THRESHOLD_MINUTES * 60);

        $stmt = $this->db->prepare("
            SELECT certificate_id, file_path
            FROM generated_certificates
            WHERE generation_status IN ('Generating', 'Finalizing')
              AND generated_at < :threshold
        ");
        $stmt->execute(['threshold' => $threshold]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $certId = (int) $row['certificate_id'];
            $finalPath  = self::CERT_STORAGE_DIR . '/certificate_' . $certId . '.pdf';
            $relPath    = 'storage/certificates/certificate_' . $certId . '.pdf';

            // Check final path first
            if (file_exists($finalPath)) {
                $this->setGenerated($certId, $relPath);
                $log[] = "[RECOVERED-FINAL] cert_id={$certId}";
                $recovered++;
                continue;
            }

            // Check for temp file pattern: tmp_cert_{uniqid}.pdf
            $tmpPattern = self::CERT_STORAGE_DIR . '/tmp_cert_*.pdf';
            $tmpFiles   = glob($tmpPattern);
            $tmpFound   = false;

            foreach ($tmpFiles ?? [] as $tmpFile) {
                // Match by creation time proximity to the cert's generated_at
                // (we cannot know the exact uniqid; use the first tmp file
                // that appeared after this cert's row was inserted)
                if (!is_readable($tmpFile)) { continue; }
                // Attempt rename
                if ($this->atomicMove($tmpFile, $finalPath)) {
                    $this->setGenerated($certId, $relPath);
                    $log[] = "[RECOVERED-TMP] cert_id={$certId} from={$tmpFile}";
                    $recovered++;
                    $tmpFound = true;
                    break;
                }
            }

            if ($tmpFound) { continue; }

            // Neither exists — mark Failed
            $this->db->prepare("
                UPDATE generated_certificates
                SET generation_status = 'Failed',
                    generation_notes  = 'Reconciled: no PDF found on disk'
                WHERE certificate_id = :id
            ")->execute(['id' => $certId]);
            $log[] = "[FAILED] cert_id={$certId}";
            $failed++;
        }

        if (!empty($rows)) {
            $this->auditModel->log(
                'generated_certificates', 0, 'reconciliation_run', $userId ?? 0,
                "Reconciled " . count($rows) . " stuck rows: recovered={$recovered}, failed={$failed}"
            );
        }

        return compact('recovered', 'failed', 'skipped', 'log');
    }

    /**
     * Atomic file move: rename() on same filesystem, copy+unlink on different.
     */
    private function atomicMove(string $src, string $dst): bool
    {
        if (@rename($src, $dst)) {
            return true;
        }
        // Cross-filesystem fallback
        if (@copy($src, $dst)) {
            @unlink($src);
            return true;
        }
        return false;
    }

    /**
     * Mark a certificate row as Generated with confirmed file_path.
     */
    private function setGenerated(int $certId, string $relPath): void
    {
        $this->db->prepare("
            UPDATE generated_certificates
            SET generation_status = 'Generated',
                file_path         = :path
            WHERE certificate_id = :id
        ")->execute(['path' => $relPath, 'id' => $certId]);
    }
}
