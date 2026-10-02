<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CertificateTemplateModel;
use App\Models\GeneratedCertificateModel;
use App\Models\SymposiumEventModel;
use App\Models\EvaluationModel;
use App\Models\AuditLogModel;
use App\Models\TeamMemberModel;
use App\Models\StudentModel;
use App\Services\TeamResolverService;
use App\Services\CertificateIdentityService;
use App\Services\CertificateVerificationUrlService;
use App\Services\CertificateEligibilityService;
use App\Services\CertificateReconciliationService;

final class CertificateBulkService
{
    private CertificateTemplateService $templateService;
    private CertificateTemplateModel $templateModel;
    private GeneratedCertificateModel $gcModel;
    private CertificateGeneratorService $generator;
    private SymposiumEventModel $eventModel;
    private EvaluationModel $evalModel;
    private AuditLogModel $auditModel;
    private TeamResolverService $teamResolver;
    private CertificateIdentityService $identityService;
    private CertificateVerificationUrlService $verificationUrlService;

    public const CERT_STORAGE_DIR = __DIR__ . '/../../storage/certificates';

    public function __construct()
    {
        $this->templateService        = new CertificateTemplateService();
        $this->templateModel          = new CertificateTemplateModel();
        $this->gcModel                = new GeneratedCertificateModel();
        $this->generator              = new CertificateGeneratorService();
        $this->eventModel             = new SymposiumEventModel();
        $this->evalModel              = new EvaluationModel();
        $this->auditModel             = new AuditLogModel();
        $this->teamResolver           = new TeamResolverService();
        $this->identityService        = new CertificateIdentityService();
        $this->verificationUrlService = new CertificateVerificationUrlService();
    }

    // =========================================================================
    // PUBLIC API — WINNER CERTIFICATES
    // =========================================================================

    /**
     * Generate Winner certificates for all eligible ranked recipients of an event.
     *
     * Pre-generation reconciliation is performed to recover any stuck issuances
     * from a prior interrupted run.
     *
     * @param int      $eventId            Symposium event primary key.
     * @param int      $userId             Operator performing the generation.
     * @param bool     $allowRegenerate    When true, supersedes existing current certs.
     * @param int|null $explicitTemplateId Override template resolution with a specific template.
     * @return array   Generation report array.
     */
    public function generateWinnersForEvent(
        int $eventId,
        int $userId,
        bool $allowRegenerate = false,
        ?int $explicitTemplateId = null
    ): array {
        // Pre-generation reconciliation — recover any stuck Generating/Finalizing rows
        (new CertificateReconciliationService())->reconcileStuckIssuances($userId);

        // Extend execution time limit for large batches
        set_time_limit(300);

        // ── 1. Re-fetch event; verify is_locked ──────────────────────────────
        $event = $this->eventModel->findById($eventId);
        if (!$event || !$event['is_locked']) {
            return $this->buildErrorReport($eventId, $event['event_name'] ?? 'Unknown', 'Event not found or not locked.');
        }

        // ── 2. Resolve template ──────────────────────────────────────────────
        if ($explicitTemplateId) {
            $template = $this->templateModel->findById($explicitTemplateId);
        } else {
            $template = $this->templateService->resolveTemplate($eventId, 'Winner');
        }

        if (!$template) {
            throw new \RuntimeException('No Winner certificate template is selected or configured for this event.');
        }

        // ── 3. Fetch active template version ─────────────────────────────────
        $version = $this->templateModel->getActiveVersion((int)$template['certificate_template_id']);
        if (!$version) {
            throw new \RuntimeException('Please open the Designer to create and save a layout for this Winner template before generating.');
        }

        $fieldConfig = json_decode($version['field_config'], true) ?? [];

        // ── 4. Re-validate template ───────────────────────────────────────────
        $validation = $this->templateService->validateForActivation((int)$template['certificate_template_id']);
        if (!$validation['success']) {
            throw new \RuntimeException('Please open the Designer and fix the following issue before generating: ' . ($validation['message'] ?? ''));
        }

        // ── 5. Re-fetch Winner recipients ─────────────────────────────────────
        try {
            $eligibilityService = new CertificateEligibilityService();
            $recipients = $eligibilityService->determineWinnerRecipients($eventId);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Eligibility check failed: ' . $e->getMessage());
        }

        if (empty($recipients)) {
            throw new \RuntimeException('No eligible Winner recipients found.');
        }

        $this->auditModel->log('generated_certificates', $eventId, 'bulk_winner_generation_started', $userId, 'Started bulk Winner certificate generation');

        $report = [
            'event_id'        => $eventId,
            'event_name'      => $event['event_name'],
            'certificate_type'=> 'Winner',
            'total_processed' => 0,
            'total_generated' => 0,
            'total_skipped'   => 0,
            'total_failed'    => 0,
            'details'         => [],
        ];

        $absoluteTemplatePath = $this->getAbsoluteTemplatePath($template['file_path']);
        $db                   = \App\Database\Database::getConnection();
        $certType             = 'Winner';

        foreach ($recipients as $recipient) {
            $report['total_processed']++;
            $appId     = (int)$recipient['application_id'];
            $studentId = (int)($recipient['recipient_student_id'] ?? 0) ?: null;

            // Check existing current cert via issuance key
            $existing = $this->gcModel->findCurrentByType($appId, $eventId, $studentId ?? 0, $certType);

            if ($existing && !$allowRegenerate) {
                $report['total_skipped']++;
                $report['details'][] = [
                    'identifier' => $appId,
                    'recipient'  => $recipient['recipient_name'],
                    'status'     => 'Skipped',
                    'reason'     => 'Winner certificate already generated (use regenerate option)',
                ];
                continue;
            }

            $tmpPath  = null;
            $newCertId = 0;

            try {
                $verificationToken = bin2hex(random_bytes(32));
                $verificationUrl   = $this->verificationUrlService->buildUrl($verificationToken);

                // ── Phase 0 (outside transaction): generate PDF → write to temp file ──
                $pdfBytes = $this->generator->generateOne(
                    $recipient['generation_data'],
                    $fieldConfig,
                    $absoluteTemplatePath,
                    $template,
                    $verificationUrl
                );
                $tmpPath  = self::CERT_STORAGE_DIR . '/tmp_cert_' . uniqid('', true) . '.pdf';
                file_put_contents($tmpPath, $pdfBytes);
                $fileHash = hash('sha256', $pdfBytes);

                // ── Build issuance key ────────────────────────────────────────────
                $issuanceKey = $this->buildIssuanceKey($eventId, $appId, $studentId, $certType);

                // ── Build partial cert data ───────────────────────────────────────
                $oldCertId      = $existing ? (int)$existing['certificate_id'] : null;
                $partialCertData = [
                    'symposium_event_id'       => $eventId,
                    'application_id'           => $appId,
                    'recipient_student_id'      => $studentId,
                    'result_id'                => $recipient['result_id'] ?? null,
                    'template_id'              => $template['certificate_template_id'],
                    'version_id'               => $version['version_id'],
                    'recipient_name'           => $recipient['recipient_name'],
                    'rank_position'            => $recipient['rank_position'] ?? null,
                    'result_status'            => $recipient['result_status'] ?? null,
                    'certificate_type'         => $certType,
                    'attendance_session_id'    => $recipient['attendance_session_id'] ?? null,
                    'is_current'               => 1,
                    'cert_issuance_key'        => $issuanceKey,
                    'canonical_schema_version' => 2,
                    'file_hash'                => $fileHash,
                    'generation_status'        => 'Generating',
                    'generated_by'             => $userId,
                ];

                $db->beginTransaction();

                if ($existing && $allowRegenerate) {
                    // Mark old cert Superseded (is_current = NULL)
                    $this->gcModel->markSuperseded((int)$oldCertId);
                }

                // Insert new cert at 'Generating' status
                $newCertId = $this->gcModel->insert(array_merge($partialCertData, [
                    'verification_token' => $verificationToken,
                    'file_path'          => '',
                    'certificate_hash'   => null,
                    'canonical_snapshot' => null,
                    'generation_notes'   => null,
                    'previous_cert_id'   => $oldCertId,
                ]));

                if (!$newCertId) {
                    throw new \Exception('Failed to insert Winner certificate record.');
                }

                // Build canonical snapshot (cert_number now known after insert)
                $freshCert     = $this->gcModel->findById($newCertId);
                $identity      = $this->identityService->buildAndHash($freshCert, $recipient['generation_data']);
                $canonicalJson = json_encode($identity['snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                // Update snapshot + hash in DB
                $this->gcModel->update($newCertId, [
                    'certificate_hash'   => $identity['hash'],
                    'canonical_snapshot' => $canonicalJson,
                ]);

                $db->commit();
                // DB committed. Row is 'Generating' with canonical data.

                // ── Phase 2: Mark Finalizing ──────────────────────────────────────
                $this->gcModel->update($newCertId, ['generation_status' => 'Finalizing']);

                // ── Phase 3: Atomic rename temp → final ───────────────────────────
                $finalPath = self::CERT_STORAGE_DIR . '/certificate_' . $newCertId . '.pdf';
                $relPath   = 'storage/certificates/certificate_' . $newCertId . '.pdf';
                $moved     = @rename($tmpPath, $finalPath);
                if (!$moved) {
                    // Cross-filesystem fallback
                    $moved = @copy($tmpPath, $finalPath);
                    if ($moved) {
                        @unlink($tmpPath);
                    }
                }
                if (!$moved) {
                    throw new \Exception('Failed to move Winner PDF to final path.');
                }
                $tmpPath = null; // successfully moved; no cleanup needed

                // ── Phase 4: Confirm Generated ────────────────────────────────────
                $this->gcModel->update($newCertId, [
                    'generation_status' => 'Generated',
                    'file_path'         => $relPath,
                ]);

                // Lock template version on first successful generation
                if (!$version['locked']) {
                    $this->templateModel->lockVersion((int)$version['version_id']);
                    $version['locked'] = 1;
                }

                $report['total_generated']++;
                $report['details'][] = [
                    'identifier' => $appId,
                    'recipient'  => $recipient['recipient_name'],
                    'status'     => 'Generated',
                    'reason'     => 'Success',
                ];

            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                // Cleanup temp file if it still exists
                if ($tmpPath !== null && file_exists($tmpPath)) {
                    @unlink($tmpPath);
                }
                // If a row was committed but rename failed, mark it Failed
                if ($newCertId > 0) {
                    try {
                        $this->gcModel->update($newCertId, [
                            'generation_status' => 'Failed',
                            'generation_notes'  => $e->getMessage(),
                            'is_current'        => null,
                        ]);
                    } catch (\Throwable) {}
                }
                $report['total_failed']++;
                $report['details'][] = [
                    'identifier' => $appId,
                    'recipient'  => $recipient['recipient_name'],
                    'status'     => 'Failed',
                    'reason'     => $e->getMessage(),
                ];
            }
        }

        $this->auditModel->log('generated_certificates', $eventId, 'bulk_winner_generation_completed', $userId, 'Completed bulk Winner certificate generation for event');

        return $report;
    }

    // =========================================================================
    // PUBLIC API — PARTICIPANT CERTIFICATES
    // =========================================================================

    /**
     * Generate Participant certificates for all attendance-eligible recipients of an event.
     *
     * Pre-generation reconciliation is performed to recover any stuck issuances
     * from a prior interrupted run.
     *
     * @param int      $eventId            Symposium event primary key.
     * @param int      $userId             Operator performing the generation.
     * @param bool     $allowRegenerate    When true, supersedes existing current certs.
     * @param int|null $explicitTemplateId Override template resolution with a specific template.
     * @return array   Generation report array.
     */
    public function generateParticipantsForEvent(
        int $eventId,
        int $userId,
        bool $allowRegenerate = false,
        ?int $explicitTemplateId = null
    ): array {
        // Pre-generation reconciliation — recover any stuck Generating/Finalizing rows
        (new CertificateReconciliationService())->reconcileStuckIssuances($userId);

        // Extend execution time limit for large batches
        set_time_limit(300);

        // ── 1. Re-fetch event; verify is_locked ──────────────────────────────
        $event = $this->eventModel->findById($eventId);
        if (!$event || !$event['is_locked']) {
            return $this->buildErrorReport($eventId, $event['event_name'] ?? 'Unknown', 'Event not found or not locked.');
        }

        // ── 2. Resolve template ──────────────────────────────────────────────
        if ($explicitTemplateId) {
            $template = $this->templateModel->findById($explicitTemplateId);
        } else {
            $template = $this->templateService->resolveTemplate($eventId, 'Participant');
        }

        if (!$template) {
            throw new \RuntimeException('No Participant certificate template is selected or configured for this event.');
        }

        // ── 3. Fetch active template version ─────────────────────────────────
        $version = $this->templateModel->getActiveVersion((int)$template['certificate_template_id']);
        if (!$version) {
            throw new \RuntimeException('Please open the Designer to create and save a layout for this Participant template before generating.');
        }

        $fieldConfig = json_decode($version['field_config'], true) ?? [];

        // ── 4. Re-validate template ───────────────────────────────────────────
        $validation = $this->templateService->validateForActivation((int)$template['certificate_template_id']);
        if (!$validation['success']) {
            throw new \RuntimeException('Please open the Designer and fix the following issue before generating: ' . ($validation['message'] ?? ''));
        }

        // ── 5. Re-fetch Participant recipients ────────────────────────────────
        try {
            $eligibilityService = new CertificateEligibilityService();
            $recipients = $eligibilityService->determineParticipantRecipients($eventId);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Eligibility check failed: ' . $e->getMessage());
        }

        if (empty($recipients)) {
            throw new \RuntimeException('No eligible Participant recipients found (Note: Winners are excluded from Participant generation).');
        }

        $this->auditModel->log('generated_certificates', $eventId, 'bulk_participant_generation_started', $userId, 'Started bulk Participant certificate generation');

        $report = [
            'event_id'        => $eventId,
            'event_name'      => $event['event_name'],
            'certificate_type'=> 'Participant',
            'total_processed' => 0,
            'total_generated' => 0,
            'total_skipped'   => 0,
            'total_failed'    => 0,
            'details'         => [],
        ];

        $absoluteTemplatePath = $this->getAbsoluteTemplatePath($template['file_path']);
        $db                   = \App\Database\Database::getConnection();
        $certType             = 'Participant';

        foreach ($recipients as $recipient) {
            $report['total_processed']++;
            $appId     = (int)$recipient['application_id'];
            $studentId = (int)($recipient['recipient_student_id'] ?? 0) ?: null;

            // Check existing current cert via issuance key
            $existing = $this->gcModel->findCurrentByType($appId, $eventId, $studentId ?? 0, $certType);

            if ($existing && !$allowRegenerate) {
                $report['total_skipped']++;
                $report['details'][] = [
                    'identifier' => $appId,
                    'recipient'  => $recipient['recipient_name'],
                    'status'     => 'Skipped',
                    'reason'     => 'Participant certificate already generated (use regenerate option)',
                ];
                continue;
            }

            $tmpPath   = null;
            $newCertId = 0;

            try {
                $verificationToken = bin2hex(random_bytes(32));
                $verificationUrl   = $this->verificationUrlService->buildUrl($verificationToken);

                // ── Phase 0 (outside transaction): generate PDF → write to temp file ──
                $pdfBytes = $this->generator->generateOne(
                    $recipient['generation_data'],
                    $fieldConfig,
                    $absoluteTemplatePath,
                    $template,
                    $verificationUrl
                );
                $tmpPath  = self::CERT_STORAGE_DIR . '/tmp_cert_' . uniqid('', true) . '.pdf';
                file_put_contents($tmpPath, $pdfBytes);
                $fileHash = hash('sha256', $pdfBytes);

                // ── Build issuance key ────────────────────────────────────────────
                $issuanceKey = $this->buildIssuanceKey($eventId, $appId, $studentId, $certType);

                // ── Build partial cert data ───────────────────────────────────────
                $oldCertId       = $existing ? (int)$existing['certificate_id'] : null;
                $partialCertData = [
                    'symposium_event_id'       => $eventId,
                    'application_id'           => $appId,
                    'recipient_student_id'      => $studentId,
                    'result_id'                => null,  // Participant certs have no result_id
                    'template_id'              => $template['certificate_template_id'],
                    'version_id'               => $version['version_id'],
                    'recipient_name'           => $recipient['recipient_name'],
                    'rank_position'            => null,
                    'result_status'            => null,
                    'certificate_type'         => $certType,
                    'attendance_session_id'    => $recipient['attendance_session_id'] ?? null,
                    'is_current'               => 1,
                    'cert_issuance_key'        => $issuanceKey,
                    'canonical_schema_version' => 2,
                    'file_hash'                => $fileHash,
                    'generation_status'        => 'Generating',
                    'generated_by'             => $userId,
                ];

                $db->beginTransaction();

                if ($existing && $allowRegenerate) {
                    // Mark old cert Superseded (is_current = NULL)
                    $this->gcModel->markSuperseded((int)$oldCertId);
                }

                // Insert new cert at 'Generating' status
                $newCertId = $this->gcModel->insert(array_merge($partialCertData, [
                    'verification_token' => $verificationToken,
                    'file_path'          => '',
                    'certificate_hash'   => null,
                    'canonical_snapshot' => null,
                    'generation_notes'   => null,
                    'previous_cert_id'   => $oldCertId,
                ]));

                if (!$newCertId) {
                    throw new \Exception('Failed to insert Participant certificate record.');
                }

                // Build canonical snapshot (cert_number now known after insert)
                $freshCert     = $this->gcModel->findById($newCertId);
                $identity      = $this->identityService->buildAndHash($freshCert, $recipient['generation_data']);
                $canonicalJson = json_encode($identity['snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                // Update snapshot + hash in DB
                $this->gcModel->update($newCertId, [
                    'certificate_hash'   => $identity['hash'],
                    'canonical_snapshot' => $canonicalJson,
                ]);

                $db->commit();
                // DB committed. Row is 'Generating' with canonical data.

                // ── Phase 2: Mark Finalizing ──────────────────────────────────────
                $this->gcModel->update($newCertId, ['generation_status' => 'Finalizing']);

                // ── Phase 3: Atomic rename temp → final ───────────────────────────
                $finalPath = self::CERT_STORAGE_DIR . '/certificate_' . $newCertId . '.pdf';
                $relPath   = 'storage/certificates/certificate_' . $newCertId . '.pdf';
                $moved     = @rename($tmpPath, $finalPath);
                if (!$moved) {
                    // Cross-filesystem fallback
                    $moved = @copy($tmpPath, $finalPath);
                    if ($moved) {
                        @unlink($tmpPath);
                    }
                }
                if (!$moved) {
                    throw new \Exception('Failed to move Participant PDF to final path.');
                }
                $tmpPath = null; // successfully moved; no cleanup needed

                // ── Phase 4: Confirm Generated ────────────────────────────────────
                $this->gcModel->update($newCertId, [
                    'generation_status' => 'Generated',
                    'file_path'         => $relPath,
                ]);

                // Lock template version on first successful generation
                if (!$version['locked']) {
                    $this->templateModel->lockVersion((int)$version['version_id']);
                    $version['locked'] = 1;
                }

                $report['total_generated']++;
                $report['details'][] = [
                    'identifier' => $appId,
                    'recipient'  => $recipient['recipient_name'],
                    'status'     => 'Generated',
                    'reason'     => 'Success',
                ];

            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                // Cleanup temp file if it still exists
                if ($tmpPath !== null && file_exists($tmpPath)) {
                    @unlink($tmpPath);
                }
                // If a row was committed but rename failed, mark it Failed
                if ($newCertId > 0) {
                    try {
                        $this->gcModel->update($newCertId, [
                            'generation_status' => 'Failed',
                            'generation_notes'  => $e->getMessage(),
                            'is_current'        => null,
                        ]);
                    } catch (\Throwable) {}
                }
                $report['total_failed']++;
                $report['details'][] = [
                    'identifier' => $appId,
                    'recipient'  => $recipient['recipient_name'],
                    'status'     => 'Failed',
                    'reason'     => $e->getMessage(),
                ];
            }
        }

        $this->auditModel->log('generated_certificates', $eventId, 'bulk_participant_generation_completed', $userId, 'Completed bulk Participant certificate generation for event');

        return $report;
    }

    // =========================================================================
    // PUBLIC API — SYMPOSIUM-LEVEL BULK GENERATION
    // =========================================================================

    /**
     * Generate both Winner AND Participant certificates for every locked event
     * in a symposium, aggregating all results into a single report.
     *
     * @param int  $symposiumId
     * @param int  $userId
     * @param bool $includeParticipation  When true, Participant certs are also generated.
     * @param bool $allowRegenerate
     * @return array Aggregated report across all events.
     */
    public function generateForSymposium(
        int $symposiumId, 
        int $userId, 
        bool $includeParticipation, 
        bool $allowRegenerate,
        ?int $winnerTemplateId = null,
        ?int $participantTemplateId = null
    ): array
    {
        $events = $this->eventModel->getBySymposium($symposiumId);

        $aggregatedReport = [
            'total_processed' => 0,
            'total_generated' => 0,
            'total_skipped'   => 0,
            'total_failed'    => 0,
            'event_reports'   => [],
        ];

        foreach ($events as $event) {
            if (!$event['is_locked']) {
                continue;
            }

            $eventId = (int)$event['symposium_event_id'];

            // Always generate Winner certificates
            try {
                $winnerReport = $this->generateWinnersForEvent($eventId, $userId, $allowRegenerate, $winnerTemplateId);
                $aggregatedReport['total_processed'] += $winnerReport['total_processed'];
                $aggregatedReport['total_generated'] += $winnerReport['total_generated'];
                $aggregatedReport['total_skipped']   += $winnerReport['total_skipped'];
                $aggregatedReport['total_failed']    += $winnerReport['total_failed'];
                $aggregatedReport['event_reports'][]  = $winnerReport;
                $aggregatedReport['details'] = array_merge($aggregatedReport['details'] ?? [], $winnerReport['details'] ?? []);
            } catch (\RuntimeException $e) {
                $aggregatedReport['total_failed']++;
                $errReport = $this->buildErrorReport($eventId, $event['event_name'], $e->getMessage());
                $aggregatedReport['event_reports'][] = $errReport;
                $aggregatedReport['details'] = array_merge($aggregatedReport['details'] ?? [], $errReport['details'] ?? []);
            }

            // Optionally generate Participant certificates
            if ($includeParticipation) {
                try {
                    $participantReport = $this->generateParticipantsForEvent($eventId, $userId, $allowRegenerate, $participantTemplateId);
                    $aggregatedReport['total_processed'] += $participantReport['total_processed'];
                    $aggregatedReport['total_generated'] += $participantReport['total_generated'];
                    $aggregatedReport['total_skipped']   += $participantReport['total_skipped'];
                    $aggregatedReport['total_failed']    += $participantReport['total_failed'];
                    $aggregatedReport['event_reports'][]  = $participantReport;
                    $aggregatedReport['details'] = array_merge($aggregatedReport['details'] ?? [], $participantReport['details'] ?? []);
                } catch (\RuntimeException $e) {
                    $aggregatedReport['total_failed']++;
                    $errReport = $this->buildErrorReport($eventId, $event['event_name'], $e->getMessage());
                    $aggregatedReport['event_reports'][] = $errReport;
                    $aggregatedReport['details'] = array_merge($aggregatedReport['details'] ?? [], $errReport['details'] ?? []);
                }
            }
        }

        return $aggregatedReport;
    }

    // =========================================================================
    // DEPRECATED BACKWARD-COMPAT WRAPPER
    // =========================================================================

    /**
     * @deprecated Use generateWinnersForEvent() (and generateParticipantsForEvent()) instead.
     *             This wrapper only generates Winner certificates.
     *             Preserved solely for backward compatibility with callers that have not yet
     *             migrated to the split API.
     */
    public function generateForEvent(
        int $symposiumEventId,
        int $userId,
        bool $includeParticipation = false,
        bool $allowRegenerate = false,
        ?int $explicitTemplateId = null
    ): array {
        return $this->generateWinnersForEvent($symposiumEventId, $userId, $allowRegenerate, $explicitTemplateId);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Build a deterministic issuance key used for deduplication and lookup.
     *
     * Format: "{eventId}|{appId}|{studentId}|{certType}"
     */
    private function buildIssuanceKey(int $eventId, int $appId, ?int $studentId, string $certType): string
    {
        return $eventId . '|' . $appId . '|' . ($studentId ?? 0) . '|' . $certType;
    }

    private function buildErrorReport(int $eventId, string $eventName, string $reason): array
    {
        return [
            'event_id'        => $eventId,
            'event_name'      => $eventName,
            'total_processed' => 0,
            'total_generated' => 0,
            'total_skipped'   => 0,
            'total_failed'    => 1,
            'details'         => [['identifier' => '-', 'recipient' => 'Event Validation', 'status' => 'Failed', 'reason' => $reason]],
        ];
    }

    private function getAbsoluteTemplatePath(string $relPath): string
    {
        return __DIR__ . '/../../' . ltrim($relPath, '/');
    }

    private function buildFilePath(int $certId): string
    {
        $dir = self::CERT_STORAGE_DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/certificate_' . $certId . '.pdf';
    }

    private function buildRelativePath(int $certId): string
    {
        return 'storage/certificates/certificate_' . $certId . '.pdf';
    }

    /**
     * Convert a numeric academic year to Roman numeral.
     * 1 → "I", 2 → "II", 3 → "III", 4 → "IV"
     */
    private static function toRomanYear(int|string $year): string
    {
        $map = [
            '1' => 'I',  '2' => 'II',  '3' => 'III',  '4' => 'IV',
            'I' => 'I',  'II' => 'II', 'III' => 'III', 'IV' => 'IV',
        ];
        $trimmed = trim((string)$year);
        return $map[$trimmed] ?? $trimmed;
    }
}
