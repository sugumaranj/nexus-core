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
 * Schema versions
 * -------------------------------------------------------------------------
 * V1 — Original 16-field canonical order (immutable):
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
 * V2 — V1 + two appended fields (immutable, added after V1):
 *   17. certificate_type
 *   18. attendance_session_id
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
     * Canonical field order V1 — immutable.
     * Do NOT reorder. Add new fields at the END with a new version tag.
     */
    public const CANONICAL_FIELDS_V1 = [
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
     * Canonical field order V2 — V1 extended with two new fields appended at the end.
     * Do NOT reorder existing fields. Immutable.
     */
    public const CANONICAL_FIELDS_V2 = [
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
        'certificate_type',
        'attendance_session_id',
    ];

    /**
     * Build the canonical snapshot array from the issued certificate data.
     * Uses CANONICAL_FIELDS_V2 for new certificates.
     *
     * @param  array  $certData        The generated_certificates row (after insert, so certificate_number is present).
     * @param  array  $generationData  The generation_data array from the eligibility service (contains event/student fields).
     * @return array  Canonical snapshot — exactly the fields and ordering defined in CANONICAL_FIELDS_V2.
     */
    public function buildSnapshot(array $certData, array $generationData): array
    {
        $merged = array_merge($generationData, $certData);

        // Inject V2-specific fields from cert data if present
        $merged['certificate_type']      = $certData['certificate_type'] ?? null;
        $merged['attendance_session_id'] = $certData['attendance_session_id'] ?? null;

        $snapshot = [];
        foreach (self::CANONICAL_FIELDS_V2 as $field) {
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
     * @param  array  $snapshot       Output of buildSnapshot().
     * @param  int    $schemaVersion  Schema version (1 or 2). Selects the correct field set.
     * @return string  64-character lowercase hex SHA-256 hash.
     */
    public function hashSnapshot(array $snapshot, int $schemaVersion = 2): string
    {
        $fields = $schemaVersion === 1
            ? self::CANONICAL_FIELDS_V1
            : self::CANONICAL_FIELDS_V2;

        // Re-order to the canonical field set for the given schema version
        $ordered = [];
        foreach ($fields as $field) {
            $ordered[$field] = $snapshot[$field] ?? '';
        }

        // json_encode preserves insertion order (guaranteed by the loop above).
        $json = json_encode($ordered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', $json);
    }

    /**
     * Convenience method: build snapshot and compute hash in one call.
     * Always uses schema version 2 for new certificates.
     *
     * @param  array  $certData        Certificate record row.
     * @param  array  $generationData  Generation data from eligibility service.
     * @return array{snapshot: array, hash: string, schema_version: int}
     */
    public function buildAndHash(array $certData, array $generationData): array
    {
        $snapshot = $this->buildSnapshot($certData, $generationData);
        return [
            'snapshot'       => $snapshot,
            'hash'           => $this->hashSnapshot($snapshot, 2),
            'schema_version' => 2,
        ];
    }

    /**
     * Recompute the hash from a stored canonical_snapshot JSON string.
     * Used by CertificateVerificationService to verify integrity.
     *
     * Parses the stored JSON snapshot and rehashes using the canonical field set
     * for the specified schema version.
     *
     * @param  string  $snapshotJson   The stored canonical_snapshot column value.
     * @param  int     $schemaVersion  Schema version (1 = CANONICAL_FIELDS_V1, 2 = CANONICAL_FIELDS_V2).
     * @return string  64-character hex SHA-256, or empty string on decode error.
     */
    public function hashFromStoredSnapshot(string $snapshotJson, int $schemaVersion = 1): string
    {
        $snapshot = json_decode($snapshotJson, true);
        if (!is_array($snapshot)) {
            return '';
        }

        $fields = $schemaVersion === 1
            ? self::CANONICAL_FIELDS_V1
            : self::CANONICAL_FIELDS_V2;

        // Re-order to canonical order before hashing to guard against
        // DB-level JSON reordering on some storage engines.
        $ordered = [];
        foreach ($fields as $field) {
            $ordered[$field] = $snapshot[$field] ?? '';
        }

        $json = json_encode($ordered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', $json);
    }
}
