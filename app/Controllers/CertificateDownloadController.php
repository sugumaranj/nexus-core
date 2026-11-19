<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Services\CertificateDownloadService;
use App\Services\CertificatePackageService;
use PDO;

class CertificateDownloadController extends BaseController
{
    protected CertificateDownloadService $downloadService;
    protected CertificatePackageService  $packageService;

    public function __construct()
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator');

        $this->downloadService = new CertificateDownloadService();
        $this->packageService  = new CertificatePackageService();
    }

    public function downloadWinnersZip(): void
        {
            $eventId = (int) ($_GET['event_id'] ?? 0);
    
            if ($eventId <= 0) {
                $this->error('Invalid event ID.');
                $this->redirect('/certificates/generated');
            }
    
            try {
                $zipPath  = $this->packageService->buildWinnerZip($eventId, $this->user());
                $filename = basename($zipPath);
                $this->downloadService->streamZip($zipPath, $filename);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());
                $this->redirect('/certificates/generated?event_id=' . $eventId);
            }
        }

    public function downloadParticipantsZip(): void
        {
            $eventId = (int) ($_GET['event_id'] ?? 0);
    
            if ($eventId <= 0) {
                $this->error('Invalid event ID.');
                $this->redirect('/certificates/generated');
            }
    
            try {
                $zipPath  = $this->packageService->buildParticipantZip($eventId, $this->user());
                $filename = basename($zipPath);
                $this->downloadService->streamZip($zipPath, $filename);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());
                $this->redirect('/certificates/generated?event_id=' . $eventId);
            }
        }

    public function download(): void
        {
            $certId = (int) ($_GET['id'] ?? 0);
    
            if ($certId <= 0) {
                $this->error('Invalid certificate ID.');
                $this->redirect('/certificates/generated');
            }
    
            $this->downloadService->streamSingle($certId, $this->user(), false);
        }

    public function view(): void
        {
            $certId = (int) ($_GET['id'] ?? 0);
    
            if ($certId <= 0) {
                $this->error('Invalid certificate ID.');
                $this->redirect('/certificates/generated');
            }
    
            $this->downloadService->streamSingle($certId, $this->user(), true);
        }

    public function downloadZip(): void
        {
            $rawIds = $_GET['ids'] ?? '';
            $certIds = array_filter(array_map('intval', explode(',', $rawIds)));
    
            if (empty($certIds)) {
                $this->error('No certificates selected.');
                $this->redirect('/certificates/generated');
            }
    
            try {
                $zipPath  = $this->downloadService->buildZip($certIds, $this->user());
                $filename = 'NexusCore_Certificates_' . date('Ymd_His') . '.zip';
                $this->downloadService->streamZip($zipPath, $filename);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());
                $this->redirect('/certificates/generated');
            }
        }

    public function downloadEventZip(): void
        {
            $eventId = (int) ($_GET['event_id'] ?? 0);
    
            if ($eventId <= 0) {
                $this->error('Invalid event ID.');
                $this->redirect('/certificates/generated');
            }
    
            try {
                // Verify completeness
                $comp = $this->packageService->getEventCompleteness($eventId);
                if ($comp['missing'] > 0) {
                    throw new \RuntimeException('Certificate generation is incomplete. ' . $comp['missing'] . ' certificates are missing.');
                }
    
                $zipPath  = $this->packageService->buildEventZip($eventId, $this->user());
                $filename = basename($zipPath); // Safe name generated in service
                $this->downloadService->streamZip($zipPath, $filename);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());
                $this->redirect('/certificates/generated?event_id=' . $eventId);
            }
        }

    public function downloadSymposiumPackage(): void
        {
            $symposiumId = (int) ($_GET['symposium_id'] ?? 0);
    
            if ($symposiumId <= 0) {
                $this->error('Invalid symposium ID.');
                $this->redirect('/symposiums');
            }
    
            try {
                $zipPath  = $this->packageService->buildSymposiumPackage($symposiumId, $this->user());
                $filename = basename($zipPath); // Safe name generated in service
                $this->downloadService->streamZip($zipPath, $filename);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());
                $this->redirect('/certificates/symposium-batch?symposium_id=' . $symposiumId);
            }
        }

    public function downloadSymposiumPdf(): void
        {
            $symposiumId = (int) ($_GET['symposium_id'] ?? 0);
    
            if ($symposiumId <= 0) {
                $this->error('Invalid symposium ID.');
                $this->redirect('/symposiums');
            }
    
            try {
                $pdfPath  = $this->packageService->buildSymposiumMergedPdf($symposiumId, $this->user());
                $filename = basename($pdfPath); // Safe name generated in service
                
                // Stream the PDF instead of ZIP
                if (!file_exists($pdfPath)) {
                    throw new \RuntimeException('PDF file could not be generated.');
                }
                
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($pdfPath));
                readfile($pdfPath);
                exit;
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());
                $this->redirect('/symposiums/show?id=' . $symposiumId);
            }
        }

}
