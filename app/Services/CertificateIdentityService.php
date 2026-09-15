<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : CertificateIdentityService.php
 * Location    : app/Services/
 * Description : Constructs a deterministic canonical payload from the
 *               certificate's immutable issued snapshot and computes
 *               the SHA-256 certificate_hash.
 *
 * Contract
 * -------------------------------------------------------------------------
 * The canonical payload is a deterministic, UTF-8 encoded JSON object
 * with explicitly ordered fields derived from the immutable certificate
 * snapshot captured at issuance time.
 *
 * Rules:
 *   - Fields are always serialized in the same fixed order.
 *   - All values are cast to string before hashing.
 *   - Empty/null values are serialized as an empty string "".
 *   - verification_token is EXCLUDED from the hash input.
 *   - file_hash is EXCLUDED from the hash input.
 *   - Mutable presentation data (filesystem paths, generated_at) are EXCLUDED.
 *   - This hash is NOT the PDF hash. It is the certificate data identity.
 *
 * Canonical field order:
 *   1.  certificate_number
 *   2.  recipient_name
 *   3.  rank_position
 *   4.  result_status
 *   5.  symposium_event_id
 *   6.  application_id
 *   7.  recipient_student_id
 *   8.  result_id
 *   9.  template_id
 *   10. version_id
 *   11. event_name
 *   12. symposium_title
 *   13. event_date
 *   14. department_name
 *   15. academic_year
 *   16. register_number
 *
 * This order is immutable. Adding new fields in future requires a new
 * schema version, NOT reordering existing fields.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

final class CertificateIdentityService
{
    /**
     * Canonical field order — immutable.
     * Do NOT reorder. Add new fields at the END with a new version tag.
     */
    private const CANONICAL_FIELDS = [
        'certificate_number',
        'recipient_name',
        'rank_position',
        'result_status',
        'symposium_event_id',
        'application_id',
        'recipient_student_id',
        'result_id',
        'template_id',
        'version_id',
        'event_name',
        'symposium_title',
        'event_date',
        'department_name',
        'academic_year',
        'register_number',
    ];

    /**
     * Build the canonical snapshot array from the issued certificate data.
     *
     * @param  array  $certData     The generated_certificates row (after insert, so certificate_number is present).
     * @param  array  $generationData  The generation_data array from the eligibility service (contains event/student fields).
     * @return array  Canonical snapshot — exactly the fields and ordering defined in CANONICAL_FIELDS.
     */
    public function buildSnapshot(array $certData, array $generationData): array
    {
        $merged = array_merge($generationData, $certData);

        $snapshot = [];
        foreach (self::CANONICAL_FIELDS as $field) {
            $value = $merged[$field] ?? null;
            // Normalize: null → "", everything else → trimmed string
            $snapshot[$field] = ($value === null) ? '' : trim((string)$value);
        }

        return $snapshot;
    }

    /**
     * Compute the SHA-256 certificate_hash from an already-built canonical snapshot.
     *
     * The snapshot is serialized as compact JSON (no whitespace, keys sorted by
     * canonical order — which is already guaranteed by buildSnapshot()).
     *
     * @param  array  $snapshot  Output of buildSnapshot().
     * @return string  64-character lowercase hex SHA-256 hash.
     */
    public function hashSnapshot(array $snapshot): string
    {
        // json_encode preserves insertion order (guaranteed by buildSnapshot).
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', $json);
    }

    /**
     * Convenience method: build snapshot and compute hash in one call.
     *
     * @param  array  $certData        Certificate record row.
     * @param  array  $generationData  Generation data from eligibility service.
     * @return array{snapshot: array, hash: string}
     */
    public function buildAndHash(array $certData, array $generationData): array
    {
        $snapshot = $this->buildSnapshot($certData, $generationData);
        return [
            'snapshot' => $snapshot,
            'hash'     => $this->hashSnapshot($snapshot),
        ];
    }

    /**
     * Recompute the hash from a stored canonical_snapshot JSON string.
     * Used by CertificateVerificationService to verify integrity.
     *
     * @param  string  $snapshotJson  The stored canonical_snapshot column value.
     * @return string  64-character hex SHA-256, or empty string on decode error.
     */
    public function hashFromStoredSnapshot(string $snapshotJson): string
    {
        $snapshot = json_decode($snapshotJson, true);
        if (!is_array($snapshot)) {
            return '';
        }

        // Re-order to canonical order before hashing to guard against
        // DB-level JSON reordering on some storage engines.
        $ordered = [];
        foreach (self::CANONICAL_FIELDS as $field) {
            $ordered[$field] = $snapshot[$field] ?? '';
        }

        $json = json_encode($ordered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', $json);
    }
}
