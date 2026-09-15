<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : CertificateVerificationService.php
 * Location    : app/Services/
 * Description : Core service for the public certificate verification
 *               pipeline. Resolves a token to a certificate, verifies
 *               both the certificate identity hash and the PDF file hash,
 *               checks lifecycle status, and returns a structured result.
 *
 * Verification Pipeline
 * -------------------------------------------------------------------------
 * 1. Certificate lookup by verification_token.
 * 2. Lifecycle status evaluation.
 * 3. Recompute certificate_hash from stored canonical_snapshot.
 * 4. Compare recomputed hash with stored certificate_hash.
 * 5. Safely resolve PDF absolute path.
 * 6. Check PDF existence.
 *    → If missing, return DOCUMENT_UNAVAILABLE (not a hash failure).
 * 7. Calculate SHA-256 of actual PDF bytes.
 * 8. Compare with stored file_hash.
 * 9. Return VerificationResult.
 *
 * Security
 * -------------------------------------------------------------------------
 * • The public result MUST NOT expose: certificate_id, application_id,
 *   recipient_student_id, result_id, template_id, version_id, file_path,
 *   generated_by, or any internal identifier.
 * • The public result may expose: certificate_number, recipient_name,
 *   event name, rank, result_status, generated_at, certificate_hash,
 *   file_hash, and verification status.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Models\GeneratedCertificateModel;

final class CertificateVerificationService
{
    // ── Public status constants ────────────────────────────────────────────

    /** Certificate is valid and both hashes verified. */
    public const STATUS_VERIFIED = 'VERIFIED';

    /** Token not found or token is syntactically invalid. */
    public const STATUS_NOT_FOUND = 'NOT_FOUND';

    /** Certificate has been superseded by regeneration. Old token is no longer active. */
    public const STATUS_SUPERSEDED = 'SUPERSEDED';

    /** Certificate's canonical snapshot hash does not match the stored hash. Data tampered. */
    public const STATUS_HASH_MISMATCH = 'HASH_MISMATCH';

    /** The PDF file is not found on disk. Certificate identity is still valid. */
    public const STATUS_DOCUMENT_UNAVAILABLE = 'DOCUMENT_UNAVAILABLE';

    /** The stored PDF SHA-256 does not match the current file on disk. File tampered. */
    public const STATUS_PDF_MISMATCH = 'PDF_MISMATCH';

    /** Certificate has legacy NULL fields (generated before verification was implemented). */
    public const STATUS_LEGACY = 'LEGACY';

    // ── Dependencies ───────────────────────────────────────────────────────

    private GeneratedCertificateModel $certModel;
    private CertificateIdentityService $identityService;

    private const PROJECT_ROOT = __DIR__ . '/../../';

    public function __construct()
    {
        $this->certModel       = new GeneratedCertificateModel();
        $this->identityService = new CertificateIdentityService();
    }

    /**
     * Run the full verification pipeline for a given token.
     *
     * @param  string $token  Raw token from the request (sanitized before calling).
     * @return array  Verification result DTO (see buildResult()).
     */
    public function verify(string $token): array
    {
        // ── 1. Syntactic token validation ──────────────────────────────────
        // A valid token is exactly 64 lowercase hex characters.
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return $this->buildResult(self::STATUS_NOT_FOUND, null);
        }

        // ── 2. Certificate lookup ──────────────────────────────────────────
        $cert = $this->certModel->findByVerificationToken($token);
        if (!$cert) {
            return $this->buildResult(self::STATUS_NOT_FOUND, null);
        }

        // ── 3. Legacy certificate (no snapshot) ───────────────────────────
        if (empty($cert['canonical_snapshot']) || empty($cert['certificate_hash'])) {
            return $this->buildResult(self::STATUS_LEGACY, $cert);
        }

        // ── 4. Lifecycle status ────────────────────────────────────────────
        // 'Regenerated' status means this certificate has been superseded.
        if ($cert['generation_status'] === 'Regenerated') {
            return $this->buildResult(self::STATUS_SUPERSEDED, $cert);
        }

        // ── 5. Certificate hash verification ──────────────────────────────
        $recomputedHash = $this->identityService->hashFromStoredSnapshot($cert['canonical_snapshot']);

        if ($recomputedHash === '' || !hash_equals($cert['certificate_hash'], $recomputedHash)) {
            return $this->buildResult(self::STATUS_HASH_MISMATCH, $cert);
        }

        // ── 6. Resolve PDF path ────────────────────────────────────────────
        $absPath     = self::PROJECT_ROOT . ltrim($cert['file_path'], '/');
        $realPath    = realpath($absPath);
        $storageRoot = realpath(self::PROJECT_ROOT . 'storage/certificates');

        // ── 7. PDF existence ───────────────────────────────────────────────
        if (!$realPath || !str_starts_with($realPath, $storageRoot) || !is_file($realPath) || !is_readable($realPath)) {
            // Certificate data is valid but file is unavailable — distinct state.
            return $this->buildResult(self::STATUS_DOCUMENT_UNAVAILABLE, $cert, [
                'certificate_hash_verified' => true,
                'recomputed_certificate_hash' => $recomputedHash,
            ]);
        }

        // ── 8. PDF hash verification ──────────────────────────────────────
        $actualFileHash = hash_file('sha256', $realPath);

        if (!$cert['file_hash'] || !hash_equals($cert['file_hash'], $actualFileHash)) {
            return $this->buildResult(self::STATUS_PDF_MISMATCH, $cert, [
                'certificate_hash_verified' => true,
                'recomputed_certificate_hash' => $recomputedHash,
            ]);
        }

        // ── 9. Full verification passed ────────────────────────────────────
        return $this->buildResult(self::STATUS_VERIFIED, $cert, [
            'certificate_hash_verified' => true,
            'pdf_hash_verified'         => true,
            'recomputed_certificate_hash' => $recomputedHash,
            'actual_file_hash'            => $actualFileHash,
        ]);
    }

    /**
     * Build the public-safe verification result DTO.
     *
     * SECURITY: Do not include internal IDs, file paths, or private data.
     * The view renders only what is in this DTO.
     *
     * @param  string     $status
     * @param  array|null $cert    The certificate row, or null if not found.
     * @param  array      $extra   Additional verified fields.
     * @return array
     */
    private function buildResult(string $status, ?array $cert, array $extra = []): array
    {
        $result = [
            'status'                    => $status,
            'certificate_number'        => null,
            'recipient_name'            => null,
            'event_name'                => null,
            'rank_position'             => null,
            'result_status'             => null,
            'generated_at'              => null,
            'certificate_hash'          => null,
            'file_hash'                 => null,
            'certificate_hash_verified' => false,
            'pdf_hash_verified'         => false,
        ];

        if ($cert !== null) {
            // Snapshot-derived display fields (from the immutable snapshot if available)
            $snapshot = !empty($cert['canonical_snapshot'])
                ? (json_decode($cert['canonical_snapshot'], true) ?? [])
                : [];

            $result['certificate_number'] = $cert['certificate_number'] ?? null;
            $result['recipient_name']     = $cert['recipient_name'] ?? null;
            $result['rank_position']      = $cert['rank_position'] ?? null;
            $result['result_status']      = $cert['result_status'] ?? null;
            $result['generated_at']       = $cert['generated_at'] ?? null;

            // event_name comes from the snapshot (immutable at issuance)
            $result['event_name']         = $snapshot['event_name'] ?? null;

            // Expose hashes only — never internal IDs or paths
            $result['certificate_hash']   = $cert['certificate_hash'] ?? null;
            $result['file_hash']          = $cert['file_hash'] ?? null;
        }

        return array_merge($result, $extra);
    }
}
