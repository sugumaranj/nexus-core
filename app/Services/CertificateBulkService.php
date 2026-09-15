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

    public function generateForEvent(
        int $symposiumEventId,
        int $userId,
        bool $includeParticipation = false,
        bool $allowRegenerate = false,
        ?int $explicitTemplateId = null
    ): array {
        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event || !$event['is_locked']) {
            return $this->buildErrorReport($symposiumEventId, $event['event_name'] ?? 'Unknown', 'Event not found or not locked.');
        }

        if ($explicitTemplateId) {
            $template = $this->templateModel->findById($explicitTemplateId);
        } else {
            $template = $this->templateService->resolveTemplate($symposiumEventId);
        }
        
        if (!$template) {
            return $this->buildErrorReport($symposiumEventId, $event['event_name'], 'No active certificate template configured.');
        }

        $version = $this->templateModel->getActiveVersion((int)$template['certificate_template_id']);
        if (!$version) {
            return $this->buildErrorReport($symposiumEventId, $event['event_name'], 'Template has no saved design configuration.');
        }

        $fieldConfig = json_decode($version['field_config'], true) ?? [];
        
        $validation = $this->templateService->validateForActivation((int)$template['certificate_template_id']);
        if (!$validation['success']) {
            return $this->buildErrorReport($symposiumEventId, $event['event_name'], 'Template validation failed: ' . ($validation['message'] ?? ''));
        }

        try {
            $eligibilityService = new CertificateEligibilityService();
            $recipients = $eligibilityService->getEligibleRecipients($symposiumEventId, $includeParticipation);
        } catch (\Exception $e) {
            return $this->buildErrorReport($symposiumEventId, $event['event_name'], 'Eligibility check failed: ' . $e->getMessage());
        }

        if (empty($recipients)) {
            return $this->buildErrorReport($symposiumEventId, $event['event_name'], 'No eligible recipients found.');
        }

        $this->auditModel->log('generated_certificates', $symposiumEventId, 'bulk_generation_started', $userId, 'Started bulk certificate generation');

        $report = [
            'event_id' => $symposiumEventId,
            'event_name' => $event['event_name'],
            'total_processed' => 0,
            'total_generated' => 0,
            'total_skipped' => 0,
            'total_failed' => 0,
            'details' => []
        ];

        $absoluteTemplatePath = $this->getAbsoluteTemplatePath($template['file_path']);

        $db = \App\Database\Database::getConnection();

        foreach ($recipients as $recipient) {
            $report['total_processed']++;
            $appId = $recipient['application_id'];
            $studentId = $recipient['recipient_student_id'];
            
            $existing = $this->gcModel->findActiveByApplicationAndEvent($appId, $symposiumEventId, $studentId);
            
            if ($existing && !$allowRegenerate) {
                $report['total_skipped']++;
                $report['details'][] = ['identifier' => $appId, 'recipient' => $recipient['recipient_name'], 'status' => 'Skipped', 'reason' => 'Certificate already generated (use regenerate option)'];
                continue;
            }

            try {
                // ── Step 1: Generate a cryptographically secure token ─────────────
                $verificationToken = bin2hex(random_bytes(32)); // 64-char hex
                $verificationUrl   = $this->verificationUrlService->buildUrl($verificationToken);

                // ── Step 2: Partial certificate data for snapshot ─────────────────
                // The snapshot needs cert-level data (number, template/version ids)
                // AND generation data (event, recipient). We build a partial certData
                // array; the certificate_number will be set after insert and then
                // we immediately update the canonical_snapshot + certificate_hash.
                $partialCertData = [
                    'symposium_event_id'   => $symposiumEventId,
                    'application_id'       => $appId,
                    'recipient_student_id' => $studentId ?: null,
                    'result_id'            => $recipient['result_id'],
                    'template_id'          => $template['certificate_template_id'],
                    'version_id'           => $version['version_id'],
                    'recipient_name'       => $recipient['recipient_name'],
                    'rank_position'        => $recipient['rank_position'],
                    'result_status'        => $recipient['result_status'],
                ];

                // ── Step 3: Generate PDF with QR (verification URL embedded) ──────
                // file_hash is computed AFTER QR insertion (from final PDF bytes).
                $pdfBytes = $this->generator->generateOne(
                    $recipient['generation_data'],
                    $fieldConfig,
                    $absoluteTemplatePath,
                    $template,
                    $verificationUrl
                );
                $fileHash = hash('sha256', $pdfBytes);

                $db->beginTransaction();

                if ($existing && $allowRegenerate) {
                    // ── REGENERATE: old cert becomes Regenerated; new cert issued ──
                    $oldCertId = (int)$existing['certificate_id'];
                    $this->gcModel->markRegenerated($oldCertId);

                    $newCertId = $this->gcModel->insert(array_merge($partialCertData, [
                        'file_path'          => '',
                        'file_hash'          => $fileHash,
                        'verification_token' => $verificationToken,
                        'certificate_hash'   => null, // set below after cert number assigned
                        'canonical_snapshot' => null, // set below after cert number assigned
                        'generation_status'  => 'Generated',
                        'generation_notes'   => null,
                        'generated_by'       => $userId,
                        'previous_cert_id'   => $oldCertId,
                    ]));

                    if (!$newCertId) {
                        throw new \Exception('Failed to insert regenerated certificate record.');
                    }

                    // ── Step 4: Build and persist canonical snapshot (cert number now known) ──
                    $freshCert        = $this->gcModel->findById($newCertId);
                    $identity         = $this->identityService->buildAndHash($freshCert, $recipient['generation_data']);
                    $canonicalJson    = json_encode($identity['snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    // ── Step 5: Write PDF to disk ─────────────────────────────────
                    $filePathAbs = $this->buildFilePath((int)$newCertId);
                    if (@file_put_contents($filePathAbs, $pdfBytes) === false) {
                        throw new \Exception('Failed to write PDF file to disk.');
                    }

                    $this->gcModel->update((int)$newCertId, [
                        'file_path'          => $this->buildRelativePath((int)$newCertId),
                        'certificate_hash'   => $identity['hash'],
                        'canonical_snapshot' => $canonicalJson,
                        'regenerated_count'  => ((int)($existing['regenerated_count'] ?? 0)) + 1,
                    ]);

                    $db->commit();

                    if (!$version['locked']) {
                        $this->templateModel->lockVersion((int)$version['version_id']);
                        $version['locked'] = 1;
                    }

                    $report['total_generated']++;
                    $report['details'][] = ['identifier' => $appId, 'recipient' => $recipient['recipient_name'], 'status' => 'Regenerated', 'reason' => 'Success'];

                } else {
                    // ── NEW GENERATION ────────────────────────────────────────────
                    $newCertId = $this->gcModel->insert(array_merge($partialCertData, [
                        'file_path'          => '',
                        'file_hash'          => $fileHash,
                        'verification_token' => $verificationToken,
                        'certificate_hash'   => null, // set below
                        'canonical_snapshot' => null, // set below
                        'generation_status'  => 'Generated',
                        'generation_notes'   => null,
                        'generated_by'       => $userId,
                        'previous_cert_id'   => null,
                    ]));

                    if (!$newCertId) {
                        throw new \Exception('Failed to insert generated certificate record.');
                    }

                    // ── Step 4: Build and persist canonical snapshot ──────────────
                    $freshCert     = $this->gcModel->findById($newCertId);
                    $identity      = $this->identityService->buildAndHash($freshCert, $recipient['generation_data']);
                    $canonicalJson = json_encode($identity['snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                    // ── Step 5: Write PDF to disk ─────────────────────────────────
                    $filePathAbs = $this->buildFilePath((int)$newCertId);
                    if (@file_put_contents($filePathAbs, $pdfBytes) === false) {
                        throw new \Exception('Failed to write PDF file to disk.');
                    }

                    $this->gcModel->update((int)$newCertId, [
                        'file_path'          => $this->buildRelativePath((int)$newCertId),
                        'certificate_hash'   => $identity['hash'],
                        'canonical_snapshot' => $canonicalJson,
                    ]);

                    $db->commit();

                    if (!$version['locked']) {
                        $this->templateModel->lockVersion((int)$version['version_id']);
                        $version['locked'] = 1;
                    }

                    $report['total_generated']++;
                    $report['details'][] = ['identifier' => $appId, 'recipient' => $recipient['recipient_name'], 'status' => 'Generated', 'reason' => 'Success'];
                }
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                if (isset($filePathAbs) && file_exists($filePathAbs)) {
                    @unlink($filePathAbs);
                }
                
                $report['total_failed']++;
                $report['details'][] = ['identifier' => $appId, 'recipient' => $recipient['recipient_name'], 'status' => 'Failed', 'reason' => $e->getMessage()];
            }
        }

        $this->auditModel->log('generated_certificates', $symposiumEventId, 'bulk_generation_completed', $userId, 'Completed bulk certificate generation for event');

        return $report;
    }

    public function generateForSymposium(int $symposiumId, int $userId, bool $includeParticipation, bool $allowRegenerate): array
    {
        $events = $this->eventModel->findBySymposium($symposiumId);
        $aggregatedReport = [
            'total_processed' => 0,
            'total_generated' => 0,
            'total_skipped' => 0,
            'total_failed' => 0,
            'event_reports' => []
        ];

        foreach ($events as $event) {
            if ($event['is_locked']) {
                $report = $this->generateForEvent((int)$event['symposium_event_id'], $userId, $includeParticipation, $allowRegenerate);
                
                $aggregatedReport['total_processed'] += $report['total_processed'];
                $aggregatedReport['total_generated'] += $report['total_generated'];
                $aggregatedReport['total_skipped'] += $report['total_skipped'];
                $aggregatedReport['total_failed'] += $report['total_failed'];
                $aggregatedReport['event_reports'][] = $report;
            }
        }

        return $aggregatedReport;
    }

    private function buildErrorReport(int $eventId, string $eventName, string $reason): array
    {
        return [
            'event_id' => $eventId,
            'event_name' => $eventName,
            'total_processed' => 0,
            'total_generated' => 0,
            'total_skipped' => 1,
            'total_failed' => 0,
            'details' => [['identifier' => '-', 'recipient' => 'Unknown', 'status' => 'Skipped', 'reason' => $reason]]
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
