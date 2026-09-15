<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GeneratedCertificateModel;
use App\Models\SymposiumEventModel;
use App\Models\SymposiumModel;
use App\Models\EvaluationModel;
use App\Models\TeamModel;
use App\Models\TeamMemberModel;
use App\Models\CertificateTemplateModel;
use App\Models\AuditLogModel;
use App\Helpers\RoleHelper;
use RuntimeException;
use ZipArchive;
use setasign\Fpdi\Fpdi;

final class CertificatePackageService
{
    private GeneratedCertificateModel $certModel;
    private SymposiumEventModel $eventModel;
    private SymposiumModel $sympModel;
    private EvaluationModel $evalModel;
    private TeamModel $teamModel;
    private TeamMemberModel $teamMemberModel;
    private CertificateTemplateModel $templateModel;
    private AuditLogModel $auditModel;
    private SymposiumService $sympService;

    private const PROJECT_ROOT = __DIR__ . '/../../';

    public function __construct()
    {
        $this->certModel = new GeneratedCertificateModel();
        $this->eventModel = new SymposiumEventModel();
        $this->sympModel = new SymposiumModel();
        $this->evalModel = new EvaluationModel();
        $this->teamModel = new TeamModel();
        $this->teamMemberModel = new TeamMemberModel();
        $this->templateModel = new CertificateTemplateModel();
        $this->auditModel = new AuditLogModel();
        $this->sympService = new SymposiumService();
    }

    /**
     * Determine completeness of certificates for a given event.
     */
    public function getEventCompleteness(int $eventId): array
    {
        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            return ['expected' => 0, 'generated' => 0, 'missing' => 0, 'event_name' => 'Unknown'];
        }

        $results = $this->evalModel->getPublishedResults($eventId);
        $expected = 0;
        
        $templateService = new CertificateTemplateService();
        $template = $templateService->resolveTemplate($eventId);
        $teamCertMode = $template ? ($template['team_cert_mode'] ?? 'per_member') : 'per_member';
        $participationType = $event['participation_type'] ?? 'Individual';

        foreach ($results as $result) {
            if (($result['result_status'] ?? '') === 'Disqualified') {
                continue;
            }

            if ($participationType === 'Individual') {
                $expected++;
            } else {
                $appId = (int)$result['application_id'];
                $resolver = new \App\Services\TeamResolverService();
                $participant = $resolver->resolveParticipant($appId);
                if ($participant) {
                    if ($participant['participant_type'] === 'Team') {
                        $expected += count($participant['members'] ?? []);
                    } else {
                        $expected++;
                    }
                }
            }
        }

        $generated = $this->certModel->countByEvent($eventId);

        return [
            'event_id' => $eventId,
            'event_name' => $event['event_name'],
            'expected' => $expected,
            'generated' => $generated,
            'missing' => max(0, $expected - $generated),
        ];
    }

    /**
     * Build an Event ZIP containing all current/valid certificates.
     */
    public function buildEventZip(int $eventId, array $user): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive extension is not available.');
        }

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            throw new RuntimeException('Event not found.');
        }
        
        $symposium = $this->sympModel->findById((int)$event['symposium_id']);
        if (!$symposium) {
            throw new RuntimeException('Symposium not found.');
        }

        if (!RoleHelper::isAdmin($user) && !$this->sympService->canEditSymposium($user, $symposium)) {
            throw new RuntimeException('Unauthorized to download certificates for this event.');
        }

        // Fetch current active certificates
        $allCerts = $this->certModel->getByEvent($eventId);
        $currentCerts = array_filter($allCerts, function($c) {
            return $c['generation_status'] === 'Generated';
        });

        if (empty($currentCerts)) {
            throw new RuntimeException('No current certificates found for this event.');
        }

        $tmpDir = self::PROJECT_ROOT . 'storage/certificates/';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        
        $safeEventName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $event['event_name']);
        $safeSympName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $symposium['title']);
        $zipName = 'NexusCore_' . $safeSympName . '_' . $safeEventName . '_Certificates_' . time() . '.zip';
        $zipPath = $tmpDir . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Failed to create ZIP archive.');
        }

        $folderName = $safeEventName . '/';
        $zip->addEmptyDir($folderName);
        $zip->addEmptyDir($folderName . 'Winners/');
        $zip->addEmptyDir($folderName . 'Participants/');

        $included = 0;
        foreach ($currentCerts as $cert) {
            $absPath = self::PROJECT_ROOT . ltrim($cert['file_path'], '/');

            // Secure file-path validation
            $realPath = realpath($absPath);
            $storageRoot = realpath(self::PROJECT_ROOT . 'storage/certificates');
            
            if ($realPath && str_starts_with($realPath, $storageRoot) && is_file($realPath) && is_readable($realPath)) {
                $statusFolder = ($cert['result_status'] === 'Winner') ? 'Winners/' : 'Participants/';
                $prefix = ($cert['result_status'] === 'Winner' && !empty($cert['rank_position'])) 
                    ? 'Rank_' . $cert['rank_position'] . '_' 
                    : 'Participant_';
                $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $cert['recipient_name']);
                
                $safeCertFile = $prefix . $safeName . '_' . $cert['application_id'] . '.pdf';
                
                $zip->addFile($realPath, $folderName . $statusFolder . $safeCertFile);
                $included++;
            }
        }

        $zip->close();

        if ($included === 0) {
            @unlink($zipPath);
            throw new RuntimeException('No valid physical certificate files found.');
        }

        $this->auditModel->log(
            'generated_certificates', 
            $eventId, 
            'event_certificate_zip_generated', 
            (int)($user['user_id'] ?? 0), 
            'Generated ZIP for event: ' . $event['event_name']
        );

        return $zipPath;
    }

    /**
     * Build Complete Symposium Package
     */
    public function buildSymposiumPackage(int $symposiumId, array $user): string
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('PHP ZipArchive extension is not available.');
        }

        $symposium = $this->sympModel->findById($symposiumId);
        if (!$symposium) {
            throw new RuntimeException('Symposium not found.');
        }

        if (!RoleHelper::isAdmin($user) && !$this->sympService->canEditSymposium($user, $symposium)) {
            throw new RuntimeException('Unauthorized to download complete package for this symposium.');
        }

        if ($symposium['status'] !== SymposiumService::STATUS_COMPLETED) {
            throw new RuntimeException('Symposium must be ' . SymposiumService::STATUS_COMPLETED . ' to generate a complete package.');
        }

        $events = $this->eventModel->getBySymposium($symposiumId);
        if (empty($events)) {
            throw new RuntimeException('No events found for this symposium.');
        }

        // Check completeness
        $manifestLines = [];
        $manifestLines[] = "Certificate Number,Recipient,Event,Rank,Status,Generated Date";
        
        $validCertsByEvent = [];
        
        foreach ($events as $index => $event) {
            $eventId = (int)$event['symposium_event_id'];
            $comp = $this->getEventCompleteness($eventId);
            if ($comp['missing'] > 0) {
                throw new RuntimeException("Package incomplete. Event '{$event['event_name']}' is missing {$comp['missing']} certificates.");
            }
            
            $allCerts = $this->certModel->getByEvent($eventId);
            $currentCerts = array_filter($allCerts, function($c) {
                return $c['generation_status'] === 'Generated';
            });
            
            $validCertsByEvent[$eventId] = [
                'event' => $event,
                'index' => $index + 1,
                'certs' => $currentCerts
            ];
            
            foreach ($currentCerts as $cert) {
                $manifestLines[] = sprintf(
                    '"%s","%s","%s","%s","%s","%s"',
                    $cert['certificate_number'],
                    $cert['recipient_name'],
                    $event['event_name'],
                    $cert['rank_position'] ?? '',
                    $cert['result_status'],
                    $cert['generated_at']
                );
            }
        }

        $tmpDir = self::PROJECT_ROOT . 'storage/certificates/';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        
        $safeSympName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $symposium['title']);
        $zipName = 'NexusCore_' . $safeSympName . '_Complete_Certificate_Package_' . time() . '.zip';
        $zipPath = $tmpDir . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Failed to create ZIP archive.');
        }

        $included = 0;
        foreach ($validCertsByEvent as $data) {
            $event = $data['event'];
            $idx = str_pad((string)$data['index'], 2, '0', STR_PAD_LEFT);
            $safeEventName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $event['event_name']);
            $folderName = $idx . '_' . $safeEventName . '/';
            $zip->addEmptyDir($folderName);
            $zip->addEmptyDir($folderName . 'Winners/');
            $zip->addEmptyDir($folderName . 'Participants/');
            
            foreach ($data['certs'] as $cert) {
                $absPath = self::PROJECT_ROOT . ltrim($cert['file_path'], '/');
                $realPath = realpath($absPath);
                $storageRoot = realpath(self::PROJECT_ROOT . 'storage/certificates');
                
                if ($realPath && str_starts_with($realPath, $storageRoot) && is_file($realPath) && is_readable($realPath)) {
                    $statusFolder = ($cert['result_status'] === 'Winner') ? 'Winners/' : 'Participants/';
                    $prefix = ($cert['result_status'] === 'Winner' && !empty($cert['rank_position'])) 
                        ? 'Rank_' . $cert['rank_position'] . '_' 
                        : 'Participant_';
                    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $cert['recipient_name']);
                    
                    $safeCertFile = $prefix . $safeName . '_' . $cert['application_id'] . '.pdf';
                    $zip->addFile($realPath, $folderName . $statusFolder . $safeCertFile);
                    $included++;
                }
            }
        }
        
        // Add manifest
        $zip->addFromString('MANIFEST.csv', implode("\n", $manifestLines));

        $zip->close();

        if ($included === 0) {
            @unlink($zipPath);
            throw new RuntimeException('No valid physical certificate files found in the package.');
        }

        $this->auditModel->log(
            'symposiums', 
            $symposiumId, 
            'symposium_certificate_package_generated', 
            (int)($user['user_id'] ?? 0), 
            'Generated complete certificate package for symposium: ' . $symposium['title']
        );

        return $zipPath;
    }

    /**
     * Build Complete Symposium Package as a single Merged PDF
     */
    public function buildSymposiumMergedPdf(int $symposiumId, array $user): string
    {
        if (!class_exists('setasign\Fpdi\Fpdi')) {
            throw new RuntimeException('FPDI library is not available.');
        }

        $symposium = $this->sympModel->findById($symposiumId);
        if (!$symposium) {
            throw new RuntimeException('Symposium not found.');
        }

        if (!RoleHelper::isAdmin($user) && !$this->sympService->canEditSymposium($user, $symposium)) {
            throw new RuntimeException('Unauthorized to download complete package for this symposium.');
        }

        if ($symposium['status'] !== SymposiumService::STATUS_COMPLETED) {
            throw new RuntimeException('Symposium must be ' . SymposiumService::STATUS_COMPLETED . ' to generate a complete package.');
        }

        $events = $this->eventModel->getBySymposium($symposiumId);
        if (empty($events)) {
            throw new RuntimeException('No events found for this symposium.');
        }

        $pdf = new Fpdi();
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);

        $included = 0;

        foreach ($events as $event) {
            $eventId = (int)$event['symposium_event_id'];
            
            $comp = $this->getEventCompleteness($eventId);
            if ($comp['missing'] > 0) {
                throw new RuntimeException("Package incomplete. Event '{$event['event_name']}' is missing {$comp['missing']} certificates.");
            }
            
            $allCerts = $this->certModel->getByEvent($eventId);
            $currentCerts = array_filter($allCerts, function($c) {
                return $c['generation_status'] === 'Generated';
            });

            foreach ($currentCerts as $cert) {
                $absPath = self::PROJECT_ROOT . ltrim($cert['file_path'], '/');
                $realPath = realpath($absPath);
                $storageRoot = realpath(self::PROJECT_ROOT . 'storage/certificates');
                
                if ($realPath && str_starts_with($realPath, $storageRoot) && is_file($realPath) && is_readable($realPath)) {
                    $pageCount = $pdf->setSourceFile($realPath);
                    for ($i = 1; $i <= $pageCount; $i++) {
                        $tplId = $pdf->importPage($i);
                        $size = $pdf->getTemplateSize($tplId);
                        $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
                        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                        $pdf->useTemplate($tplId);
                    }
                    $included++;
                }
            }
        }

        if ($included === 0) {
            throw new RuntimeException('No valid physical certificate files found in the package.');
        }

        $tmpDir = self::PROJECT_ROOT . 'storage/certificates/';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        
        $safeSympName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $symposium['title']);
        $pdfName = 'NexusCore_' . $safeSympName . '_Complete_Certificate_Package_' . time() . '.pdf';
        $pdfPath = $tmpDir . $pdfName;

        $pdf->Output($pdfPath, 'F');

        $this->auditModel->log(
            'symposiums', 
            $symposiumId, 
            'symposium_certificate_package_generated', 
            (int)($user['user_id'] ?? 0), 
            'Generated complete merged PDF certificate package for symposium: ' . $symposium['title']
        );

        return $pdfPath;
    }
}
