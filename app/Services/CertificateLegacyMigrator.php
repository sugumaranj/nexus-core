<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : CertificateLegacyMigrator.php
 * Location    : app/Services/
 * Description : Authoritative PHP migration helper for the certificate type
 *               refactor. Provides the exact generated_at + certificate_id
 *               tie-break ordering that cannot be expressed reliably in a
 *               single SQL MAX() GROUP BY query.
 *
 * Usage
 * -------------------------------------------------------------------------
 * Run AFTER executing 2026_09_27_certificate_type_refactor.sql.
 * This class handles the second pass — promoting is_current = 1 for
 * Winner rows using strict generated_at DESC + certificate_id DESC ordering.
 *
 * Only 'Winner' certificate_type rows are promoted automatically.
 * 'Legacy' rows stay NULL and require resolveAmbiguousRecords() + admin review.
 *
 * Project     : NexusCore
 * Branch      : feature/certificate-system-refactor
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Database\Database;
use App\Models\AuditLogModel;
use PDO;

final class CertificateLegacyMigrator
{
    private PDO          $db;
    private AuditLogModel $auditModel;

    public function __construct()
    {
        $this->db         = Database::getConnection();
        $this->auditModel = new AuditLogModel();
    }

    // =========================================================================
    // PRIMARY: Promote is_current for confidently classified Winner records
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Promote is_current = 1 for the authoritative current Winner certificate
     * per (event, application, recipient) group.
     *
     * Ordering rule (authoritative):
     *   1. greatest generated_at DESC
     *   2. greatest certificate_id DESC as tie-breaker for same-second rows
     *
     * Only certificate_type = 'Winner' rows are processed.
     * Legacy rows are never promoted automatically.
     *
     * @param int|null $userId   Staff user ID for audit log (null = system run)
     * @return array{promoted: int, skipped: int, errors: array}
     * -------------------------------------------------------------------------
     */
    public function promoteCurrentIssuances(?int $userId = null): array
    {
        $promoted = 0;
        $skipped  = 0;
        $errors   = [];

        // Fetch all Generated Winner rows ordered by the authoritative criteria.
        // PHP processes the results — first row per (event, app, recipient, type) wins.
        $sql = "
            SELECT
                certificate_id,
                symposium_event_id,
                application_id,
                COALESCE(recipient_student_id, 0) AS r_id,
                certificate_type,
                generated_at
            FROM generated_certificates
            WHERE generation_status = 'Generated'
              AND certificate_type  = 'Winner'
            ORDER BY
                symposium_event_id         ASC,
                application_id             ASC,
                COALESCE(recipient_student_id, 0) ASC,
                certificate_type           ASC,
                generated_at               DESC,   -- primary recency signal
                certificate_id             DESC    -- tie-breaker for same generated_at
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Track which (event, app, recipient, type) groups we have already promoted
        $seen = [];

        foreach ($rows as $row) {
            $key = $row['symposium_event_id']
                . '|' . $row['application_id']
                . '|' . $row['r_id']
                . '|' . $row['certificate_type'];

            if (isset($seen[$key])) {
                // Not the first (most recent) row for this group — stays NULL
                $skipped++;
                continue;
            }

            // First row for this group = authoritative current issuance
            $seen[$key] = true;

            try {
                $upd = $this->db->prepare(
                    "UPDATE generated_certificates
                     SET is_current = 1
                     WHERE certificate_id = :id"
                );
                $upd->execute(['id' => (int) $row['certificate_id']]);
                $promoted++;
            } catch (\Throwable $e) {
                $errors[] = "certificate_id={$row['certificate_id']}: " . $e->getMessage();
            }
        }

        $this->auditModel->log(
            'generated_certificates',
            0,
            'legacy_migration_promote_current',
            $userId ?? 0,
            "Legacy migrator: promoted={$promoted}, skipped={$skipped}, errors=" . count($errors)
        );

        return compact('promoted', 'skipped', 'errors');
    }

    // =========================================================================
    // SECONDARY: Report ambiguous Legacy records for admin review
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Return all generated_certificates rows where certificate_type = 'Legacy'
     * and generation_status = 'Generated', grouped with their event/student data
     * for administrative review.
     *
     * These rows were NOT automatically promoted to is_current = 1 during
     * migration. An admin must manually review each record and either:
     *  - Promote it (set is_current = 1 + certificate_type) via the admin UI.
     *  - Leave it as Legacy historical data.
     *
     * @return array  List of Legacy certificate rows needing admin decision.
     * -------------------------------------------------------------------------
     */
    public function getAmbiguousLegacyRecords(): array
    {
        $sql = "
            SELECT
                gc.certificate_id,
                gc.certificate_number,
                gc.symposium_event_id,
                gc.application_id,
                gc.recipient_student_id,
                gc.recipient_name,
                gc.rank_position,
                gc.result_status,
                gc.certificate_type,
                gc.generation_status,
                gc.is_current,
                gc.generated_at,
                se.event_name,
                s.full_name    AS student_name,
                s.register_number
            FROM generated_certificates gc
            LEFT JOIN symposium_events se ON se.symposium_event_id = gc.symposium_event_id
            LEFT JOIN students         s  ON s.student_id          = gc.recipient_student_id
            WHERE gc.certificate_type   = 'Legacy'
              AND gc.generation_status  = 'Generated'
            ORDER BY gc.symposium_event_id ASC, gc.generated_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Manually promote a single Legacy record to is_current = 1 with an
     * explicitly set certificate_type. Called by the admin resolution UI.
     *
     * Requirements:
     *  - The row must currently have certificate_type = 'Legacy'.
     *  - The row must have generation_status = 'Generated'.
     *  - $newType must be 'Winner' or 'Participant'.
     *  - No other is_current = 1 row may exist for the same issuance key + type.
     *
     * @param int    $certId   Certificate ID to promote.
     * @param string $newType  'Winner' or 'Participant'.
     * @param int    $adminId  Admin user ID performing the promotion.
     * @return array{success: bool, message: string}
     * -------------------------------------------------------------------------
     */
    public function resolveAmbiguousRecord(int $certId, string $newType, int $adminId): array
    {
        if (!in_array($newType, ['Winner', 'Participant'], true)) {
            return ['success' => false, 'message' => 'Invalid certificate_type. Must be Winner or Participant.'];
        }

        $cert = $this->db->prepare(
            "SELECT * FROM generated_certificates WHERE certificate_id = :id LIMIT 1"
        );
        $cert->execute(['id' => $certId]);
        $row = $cert->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['success' => false, 'message' => 'Certificate not found.'];
        }

        if ($row['certificate_type'] !== 'Legacy') {
            return ['success' => false, 'message' => 'Only Legacy records may be resolved via this method.'];
        }

        if ($row['generation_status'] !== 'Generated') {
            return ['success' => false, 'message' => 'Only Generated-status records may be promoted.'];
        }

        // Build the new issuance key with the new type
        $recipientPart = $row['recipient_student_id'] ?? 0;
        $newKey = $row['symposium_event_id']
            . '|' . $row['application_id']
            . '|' . $recipientPart
            . '|' . $newType;

        try {
            $this->db->beginTransaction();

            // Update certificate_type, cert_issuance_key, and promote to is_current = 1
            $upd = $this->db->prepare("
                UPDATE generated_certificates
                SET certificate_type    = :type,
                    cert_issuance_key   = :key,
                    is_current          = 1
                WHERE certificate_id = :id
            ");
            $upd->execute([
                'type' => $newType,
                'key'  => $newKey,
                'id'   => $certId,
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'message' => 'DB error: ' . $e->getMessage()];
        }

        $this->auditModel->log(
            'generated_certificates',
            $certId,
            'legacy_record_resolved',
            $adminId,
            "Legacy record {$certId} resolved as {$newType}"
        );

        return ['success' => true, 'message' => "Certificate {$certId} promoted as {$newType}."];
    }
}
