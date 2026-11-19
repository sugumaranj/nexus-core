<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\CertificateTemplateModel;
use App\Models\GeneratedCertificateModel;
use App\Models\DocumentJobModel;
use App\Services\CertificateBulkService;
use App\Services\CertificateEligibilityService;
use App\Services\CertificatePackageService;
use App\Services\CertificateRankService;
use App\Services\DocumentQueueService;
use App\Database\Database;
use App\Core\Session;
use PDO;

class CertificateGenerationController extends BaseController
{
    protected CertificateTemplateModel  $templateModel;
    protected GeneratedCertificateModel $certModel;
    protected CertificateBulkService    $bulkService;
    protected CertificatePackageService $packageService;
    protected DocumentJobModel          $jobModel;
    protected DocumentQueueService      $queueService;

    public function __construct()
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator');

        $this->templateModel  = new CertificateTemplateModel();
        $this->certModel      = new GeneratedCertificateModel();
        $this->bulkService    = new CertificateBulkService();
        $this->packageService = new CertificatePackageService();
        $this->jobModel       = new DocumentJobModel();
        $this->queueService   = new DocumentQueueService($this->jobModel);
    }

    public function generate(): void
        {
            $db   = Database::getConnection();
    
            $symposiumId = (int) ($_GET['symposium_id'] ?? 0);
            $eventId     = (int) ($_GET['event_id'] ?? 0);
    
            // If an event was passed but no symposium, look up the symposium
            if ($eventId > 0 && $symposiumId === 0) {
                $stmt = $db->prepare("SELECT symposium_id FROM symposium_events WHERE symposium_event_id = :id");
                $stmt->execute(['id' => $eventId]);
                $symposiumId = (int) $stmt->fetchColumn();
            }
    
            // Load all symposiums
            $stmt = $db->prepare("
                SELECT symposium_id, title AS symposium_name, symposium_code
                FROM symposiums
                ORDER BY title ASC
            ");
            $stmt->execute();
            $symposiums = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            // Load events for the selected symposium
            $events = [];
            if ($symposiumId > 0) {
                $stmt = $db->prepare("
                    SELECT symposium_event_id, event_name
                    FROM symposium_events
                    WHERE symposium_id = :sid AND is_locked = 1
                    ORDER BY event_name ASC
                ");
                $stmt->execute(['sid' => $symposiumId]);
                $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
    
            $templates = $this->templateModel->getAll(true); // active only
    
            // Winner / Participant template lists for per-type selectors
            $winnerTemplates      = (new CertificateTemplateModel())->getByType('Winner');
            $participantTemplates = (new CertificateTemplateModel())->getByType('Participant');
    
            // Eligibility preflight ΓÇö only when an event is selected
            $winnerPreflight      = null;
            $participantPreflight = null;
            if ($eventId > 0) {
                try {
                    $eligibilitySvc       = new CertificateEligibilityService();
                    $winnerPreflight      = $eligibilitySvc->getWinnerPreflight($eventId);
                    $participantPreflight = $eligibilitySvc->getParticipantPreflight($eventId);
                } catch (\Throwable $e) {
                    error_log('[CertificateController::generate] preflight error: ' . $e->getMessage());
                }
            }
    
            $templateId  = (int) ($_GET['template_id'] ?? 0);
    
            $this->render('certificate.generate', [
                'page_title'           => 'Generate Certificates',
                'symposiums'           => $symposiums,
                'events'               => $events,
                'templates'            => $templates,
                'winner_templates'     => $winnerTemplates,
                'participant_templates' => $participantTemplates,
                'winner_preflight'     => $winnerPreflight,
                'participant_preflight' => $participantPreflight,
                'selected_symposium'   => $symposiumId,
                'selected_event'       => $eventId,
                'selected_template'    => $templateId,
                'recent_jobs'          => $this->jobModel->getRecentJobs(5, (int)($user['user_id'] ?? 0)),
            ]);
        }

    public function runGeneration(): void
    {
        $this->verifyCsrf();

        $action                = $_POST['action'] ?? 'generate_both';
        $eventId               = (int) ($_POST['event_id'] ?? 0);
        $explicitTemplateId    = !empty($_POST['template_id']) ? (int) $_POST['template_id'] : null;
        $winnerTemplateId      = !empty($_POST['winner_template_id']) ? (int) $_POST['winner_template_id'] : $explicitTemplateId;
        $participantTemplateId = !empty($_POST['participant_template_id']) ? (int) $_POST['participant_template_id'] : $explicitTemplateId;
        $allowRegenerate       = !empty($_POST['allow_regenerate']);
        $allowRegenWinner      = !empty($_POST['allow_regenerate_winner']) || $allowRegenerate;
        $allowRegenParticipant = !empty($_POST['allow_regenerate_participant']) || $allowRegenerate;

        if ($eventId <= 0) {
            $this->error('Please select an event.');
            $this->redirect('/certificates/generate');
        }

        $user = $this->user();

        // Determine job type
        if ($action === 'generate_winners') {
            $jobType = DocumentQueueService::JOB_TYPE_WINNER;
        } elseif ($action === 'generate_participants') {
            $jobType = DocumentQueueService::JOB_TYPE_PARTICIPANT;
        } else {
            $jobType = DocumentQueueService::JOB_TYPE_BOTH;
            $action  = 'generate_both';
        }

        $payload = [
            'action'                       => $action,
            'event_id'                     => $eventId,
            'user_id'                      => $user['user_id'],
            'winner_template_id'           => $winnerTemplateId,
            'participant_template_id'      => $participantTemplateId,
            'template_id'                  => $explicitTemplateId,
            'allow_regenerate'             => $allowRegenerate,
            'allow_regenerate_winner'      => $allowRegenWinner,
            'allow_regenerate_participant' => $allowRegenParticipant,
        ];

        try {
            $jobId = $this->queueService->enqueueJob($jobType, $payload, (int)$user['user_id'], $eventId, null);

            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                || !empty($_POST['is_ajax']);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success'      => true,
                    'job_id'       => $jobId,
                    'redirect_url' => base_url('/certificates/job-progress?job_id=' . $jobId),
                    'status_url'   => base_url('/certificates/job-status?job_id=' . $jobId),
                ]);
                exit;
            }

            $this->redirect('/certificates/job-progress?job_id=' . $jobId);
        } catch (\Throwable $e) {
            $this->error('Unable to queue certificate generation: ' . $e->getMessage());
            $this->redirect('/certificates/generate?event_id=' . $eventId);
        }
    }

    public function generationReport(): void
    {
        $jobId  = (int) ($_GET['job_id'] ?? 0);
        $report = [];
        $job    = null;

        if ($jobId > 0) {
            $job    = $this->jobModel->findById($jobId) ?: null;
            $report = $this->jobModel->getJobReport($jobId) ?? [];
        }

        if (empty($report)) {
            $report = Session::get('cert_generation_report', []);
            Session::remove('cert_generation_report');
        }

        $this->render('certificate.report', [
            'page_title' => 'Certificate Generation Report',
            'report'     => $report,
            'job'        => $job,
        ]);
    }

    public function ajaxPreflight(): void
        {
            header('Content-Type: application/json');
    
            $eventId = (int) ($_GET['event_id'] ?? 0);
    
            if ($eventId <= 0) {
                echo json_encode(['error' => 'No event selected.']);
                exit;
            }
    
            $svc = new CertificateEligibilityService();
            echo json_encode([
                'winner'      => $svc->getWinnerPreflight($eventId),
                'participant' => $svc->getParticipantPreflight($eventId),
            ]);
            exit;
        }

    public function generated(): void
        {
            $eventId = (int) ($_GET['event_id'] ?? 0);
            $symposiumId = (int) ($_GET['symposium_id'] ?? 0);
            $eventReport = null;
            $symposiumReport = null;
    
            // Certificate type filter (Winner | Participant | All)
            $certType = in_array($_GET['cert_type'] ?? '', ['Winner', 'Participant', 'All'])
                        ? $_GET['cert_type']
                        : 'All';
    
            $db = Database::getConnection();
    
            if ($eventId > 0) {
                $certs = $this->certModel->getByEvent($eventId, $certType !== 'All' ? $certType : null);
                try {
                    $eventReport = $this->packageService->getEventCompleteness($eventId);
                } catch (\Throwable $e) {
                    $eventReport = null;
                }
            } elseif ($symposiumId > 0) {
                // Fetch all certs for symposium
                $stmt = $db->prepare("
                    SELECT
                        gc.*,
                        se.event_name,
                        sym.title AS symposium_title
                    FROM generated_certificates gc
                    INNER JOIN symposium_events se ON se.symposium_event_id = gc.symposium_event_id
                    INNER JOIN symposiums sym       ON sym.symposium_id      = se.symposium_id
                    WHERE sym.symposium_id = :sym_id AND gc.generation_status != 'Failed'
                    ORDER BY se.event_date DESC, gc.generated_at DESC
                ");
                $stmt->execute(['sym_id' => $symposiumId]);
                $certs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                
                // Check if symposium is completed
                $stmt = $db->prepare("SELECT status FROM symposiums WHERE symposium_id = :sym_id");
                $stmt->execute(['sym_id' => $symposiumId]);
                $symStatus = $stmt->fetchColumn();
                
                if ($symStatus === \App\Services\SymposiumService::STATUS_COMPLETED) {
                    $symposiumReport = ['missing' => 0];
                } else {
                    $symposiumReport = ['missing' => 1];
                }
            } else {
                // Get all recent certificates
                $stmt = $db->prepare("
                    SELECT
                        gc.*,
                        se.event_name,
                        sym.title AS symposium_title
                    FROM generated_certificates gc
                    INNER JOIN symposium_events se ON se.symposium_event_id = gc.symposium_event_id
                    INNER JOIN symposiums sym       ON sym.symposium_id      = se.symposium_id
                    WHERE gc.generation_status != 'Failed'
                    ORDER BY gc.generated_at DESC
                    LIMIT 500
                ");
                $stmt->execute();
                $certs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
    
            // Event selector
            $stmt = $db->prepare("
                SELECT se.symposium_event_id, se.event_name, sym.title AS symposium_title
                FROM symposium_events se
                INNER JOIN symposiums sym ON sym.symposium_id = se.symposium_id
                WHERE se.is_locked = 1
                ORDER BY se.event_date DESC, se.event_name ASC
            ");
            $stmt->execute();
            $events = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            
            // Symposium selector
            $stmt = $db->prepare("
                SELECT symposium_id, title
                FROM symposiums
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            $symposiums = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    
            $this->render('certificate.generated', [
                'page_title'         => 'Generated Certificates',
                'certs'              => $certs,
                'events'             => $events,
                'symposiums'         => $symposiums,
                'selected_event'     => $eventId,
                'selected_symposium' => $symposiumId,
                'event_report'       => $eventReport,
                'symposium_report'   => $symposiumReport,
                'selected_cert_type' => $certType,
                'rank_service'       => new CertificateRankService(),
                'recent_jobs'        => $this->jobModel->getRecentJobs(5, (int)($this->user()['user_id'] ?? 0)),
            ]);
        }

    public function ajaxGetEvents(): void
        {
            header('Content-Type: application/json');
    
            $symposiumId = (int) ($_GET['symposium_id'] ?? 0);
    
            if ($symposiumId <= 0) {
                echo json_encode([]);
                return;
            }
    
            $db   = Database::getConnection();
            $stmt = $db->prepare("
                SELECT
                    se.symposium_event_id,
                    se.event_name,
                    se.event_code,
                    se.participation_type,
                    se.event_date,
                    COUNT(cr.result_id) AS result_count
                FROM symposium_events se
                LEFT JOIN competition_results cr
                       ON cr.symposium_event_id = se.symposium_event_id
                      AND cr.published = 1
                WHERE se.symposium_id = :symposium_id
                  AND se.is_locked    = 1
                GROUP BY se.symposium_event_id
                ORDER BY se.event_name ASC
            ");
            $stmt->execute(['symposium_id' => $symposiumId]);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
            echo json_encode(['events' => $events]);
        }

    public function ajaxGetResults(): void
        {
            header('Content-Type: application/json');
    
            $eventId = (int) ($_GET['event_id'] ?? 0);
    
            if ($eventId <= 0) {
                echo json_encode([]);
                return;
            }
    
            $db   = Database::getConnection();
            $stmt = $db->prepare("
                SELECT
                    cr.result_id,
                    cr.rank_position,
                    cr.result_status,
                    s.full_name AS student_name,
                    s.full_name AS name_display,
                    s.register_number
                FROM competition_results cr
                INNER JOIN applications a ON a.application_id  = cr.application_id
                INNER JOIN students s     ON s.student_id      = a.student_id
                WHERE cr.symposium_event_id = :event_id
                  AND cr.published           = 1
                ORDER BY cr.rank_position ASC, s.full_name ASC
            ");
            $stmt->execute(['event_id' => $eventId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
            echo json_encode(['results' => $results]);
        }

    public function symposiumBatch(): void
        {
            $db = Database::getConnection();
            
            $symposiumId = (int) ($_GET['symposium_id'] ?? 0);
    
            // Load all symposiums
            $stmt = $db->query("
                SELECT symposium_id, title AS symposium_name, symposium_code
                FROM symposiums
                ORDER BY created_at DESC
            ");
            $symposiums = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
            $eventsStats = [];
            $symposiumInfo = null;
    
            if ($symposiumId > 0) {
                // Get symposium info
                $stmt = $db->prepare("SELECT title AS symposium_name FROM symposiums WHERE symposium_id = :id");
                $stmt->execute(['id' => $symposiumId]);
                $symposiumInfo = $stmt->fetch(\PDO::FETCH_ASSOC);
    
                // Load all events for this symposium
                $stmt = $db->prepare("
                    SELECT symposium_event_id, event_name, is_locked
                    FROM symposium_events
                    WHERE symposium_id = :sid
                    ORDER BY event_name ASC
                ");
                $stmt->execute(['sid' => $symposiumId]);
                $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
                $eligibilityService = new \App\Services\CertificateEligibilityService();
    
                foreach ($events as $event) {
                    $eid = (int)$event['symposium_event_id'];
                    
                    if ($event['is_locked']) {
                        $winnerPreflight = $eligibilityService->getWinnerPreflight($eid);
                        $participantPreflight = $eligibilityService->getParticipantPreflight($eid);
                    } else {
                        $winnerPreflight = null;
                        $participantPreflight = null;
                    }
    
                    $eventsStats[] = [
                        'event_id'             => $eid,
                        'event_name'           => $event['event_name'],
                        'is_locked'            => (bool)$event['is_locked'],
                        'winner_preflight'     => $winnerPreflight,
                        'participant_preflight'=> $participantPreflight,
                    ];
                }
            }
    
            $winnerTemplates      = (new \App\Models\CertificateTemplateModel())->getByType('Winner');
            $participantTemplates = (new \App\Models\CertificateTemplateModel())->getByType('Participant');
            $user                 = $this->user();
    
            $this->render('certificate.symposium_batch', [
                'page_title'           => 'Symposium Batch Generation',
                'symposiums'           => $symposiums,
                'selected_symposium'   => $symposiumId,
                'symposium_info'       => $symposiumInfo,
                'events_stats'         => $eventsStats,
                'winner_templates'     => $winnerTemplates,
                'participant_templates'=> $participantTemplates,
                'csrf_token'           => Session::get('_token', ''),
                'flash_message'        => Session::getFlash('certificate_flash'),
                'recent_jobs'          => $this->jobModel->getRecentJobs(5, (int)($user['user_id'] ?? 0)),
            ]);
        }

    public function runSymposiumBatch(): void
    {
        $this->verifyCsrf();

        $symposiumId           = (int) ($_POST['symposium_id'] ?? 0);
        $allowRegenerate       = !empty($_POST['allow_regenerate']);
        $winnerTemplateId      = !empty($_POST['winner_template_id']) ? (int) $_POST['winner_template_id'] : null;
        $participantTemplateId = !empty($_POST['participant_template_id']) ? (int) $_POST['participant_template_id'] : null;

        if ($symposiumId <= 0) {
            $this->error('Please select a symposium.');
            $this->redirect('/certificates/symposium-batch');
        }

        $user = $this->user();

        $payload = [
            'action'                  => 'symposium_batch',
            'symposium_id'            => $symposiumId,
            'user_id'                 => $user['user_id'],
            'winner_template_id'      => $winnerTemplateId,
            'participant_template_id' => $participantTemplateId,
            'allow_regenerate'        => $allowRegenerate,
        ];

        try {
            $jobId = $this->queueService->enqueueJob(
                DocumentQueueService::JOB_TYPE_SYMPOSIUM_BATCH,
                $payload,
                (int)$user['user_id'],
                null,
                $symposiumId
            );

            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
                || !empty($_POST['is_ajax']);

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success'      => true,
                    'job_id'       => $jobId,
                    'redirect_url' => base_url('/certificates/job-progress?job_id=' . $jobId),
                    'status_url'   => base_url('/certificates/job-status?job_id=' . $jobId),
                ]);
                exit;
            }

            $this->redirect('/certificates/job-progress?job_id=' . $jobId);
        } catch (\Throwable $e) {
            $this->error('Unable to queue symposium batch generation: ' . $e->getMessage());
            $this->redirect('/certificates/symposium-batch?symposium_id=' . $symposiumId);
        }
    }

    public function queueGeneration(): void
    {
        $this->verifyCsrf();
        header('Content-Type: application/json');

        $eventId               = (int) ($_POST['event_id'] ?? 0);
        $symposiumId           = (int) ($_POST['symposium_id'] ?? 0);
        $action                = trim($_POST['action'] ?? 'generate_both');
        $allowRegenerate       = !empty($_POST['allow_regenerate']);
        $winnerTemplateId      = !empty($_POST['winner_template_id']) ? (int) $_POST['winner_template_id'] : null;
        $participantTemplateId = !empty($_POST['participant_template_id']) ? (int) $_POST['participant_template_id'] : null;
        $explicitTemplateId    = !empty($_POST['template_id']) ? (int) $_POST['template_id'] : null;
        $allowRegenWinner      = !empty($_POST['allow_regenerate_winner']) || $allowRegenerate;
        $allowRegenParticipant = !empty($_POST['allow_regenerate_participant']) || $allowRegenerate;
        $user                  = $this->user();

        if ($eventId <= 0 && $symposiumId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid event or symposium selected.']);
            return;
        }

        if ($action === 'symposium_batch' || ($symposiumId > 0 && $eventId <= 0)) {
            $jobType = DocumentQueueService::JOB_TYPE_SYMPOSIUM_BATCH;
        } elseif ($action === 'generate_winners') {
            $jobType = DocumentQueueService::JOB_TYPE_WINNER;
        } elseif ($action === 'generate_participants') {
            $jobType = DocumentQueueService::JOB_TYPE_PARTICIPANT;
        } else {
            $jobType = DocumentQueueService::JOB_TYPE_BOTH;
        }

        $payload = [
            'action'                       => $action,
            'event_id'                     => $eventId > 0 ? $eventId : null,
            'symposium_id'                 => $symposiumId > 0 ? $symposiumId : null,
            'user_id'                      => $user['user_id'],
            'winner_template_id'           => $winnerTemplateId ?: $explicitTemplateId,
            'participant_template_id'      => $participantTemplateId ?: $explicitTemplateId,
            'template_id'                  => $explicitTemplateId,
            'allow_regenerate'             => $allowRegenerate,
            'allow_regenerate_winner'      => $allowRegenWinner,
            'allow_regenerate_participant' => $allowRegenParticipant,
        ];

        try {
            $jobId = $this->queueService->enqueueJob(
                $jobType,
                $payload,
                (int)$user['user_id'],
                $eventId > 0 ? $eventId : null,
                $symposiumId > 0 ? $symposiumId : null
            );

            echo json_encode([
                'success'      => true,
                'job_id'       => $jobId,
                'message'      => 'Certificate generation job queued and background worker dispatched successfully.',
                'redirect_url' => base_url('/certificates/job-progress?job_id=' . $jobId),
                'status_url'   => base_url('/certificates/job-status?job_id=' . $jobId),
            ]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function jobStatus(): void
    {
        header('Content-Type: application/json');
        $jobId = (int) ($_GET['job_id'] ?? 0);

        if ($jobId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid job ID.']);
            return;
        }

        $job = $this->jobModel->getJobStatus($jobId);
        if (!$job) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Job not found.']);
            return;
        }

        // Heartbeat watchdog: if job is Pending and was created > 2 seconds ago, re-dispatch worker
        if ($job['status'] === 'Pending') {
            $createdAt = strtotime((string)$job['created_at']);
            if (time() - $createdAt >= 2) {
                $this->queueService->dispatchBackgroundWorker($jobId);
            }
        }

        $totalItems     = (int) $job['total_items'];
        $processedItems = (int) $job['processed_items'];
        $status         = (string) $job['status'];

        $progress = 0;
        if ($totalItems > 0) {
            $progress = (int) min(100, round(($processedItems / $totalItems) * 100));
        }
        if ($status === 'Completed') {
            $progress = 100;
        }

        $isFinished        = in_array($status, ['Completed', 'Failed'], true);
        $targetEventId     = (int) ($job['symposium_event_id'] ?? 0);
        $targetSymposiumId = (int) ($job['symposium_id'] ?? 0);

        $viewCertsUrl = $targetEventId > 0
            ? base_url('/certificates/generated?event_id=' . $targetEventId)
            : ($targetSymposiumId > 0 ? base_url('/certificates/generated?symposium_id=' . $targetSymposiumId) : base_url('/certificates/generated'));

        $downloadZipUrl = $targetEventId > 0
            ? base_url('/certificates/download/event-zip?event_id=' . $targetEventId)
            : ($targetSymposiumId > 0 ? base_url('/certificates/download/symposium-package?symposium_id=' . $targetSymposiumId) : null);

        echo json_encode([
            'success'          => true,
            'job_id'           => (int) $job['job_id'],
            'job_type'         => $job['job_type'],
            'status'           => $status,
            'progress'         => $progress,
            'total_items'      => $totalItems,
            'processed'        => $processedItems,
            'is_finished'      => $isFinished,
            'error_message'    => $job['error_message'],
            'report_url'       => base_url('/certificates/report?job_id=' . $jobId),
            'view_certs_url'   => $viewCertsUrl,
            'download_zip_url' => $downloadZipUrl,
        ]);
    }

    public function jobProgress(): void
    {
        $jobId = (int) ($_GET['job_id'] ?? 0);
        if ($jobId <= 0) {
            $this->error('Invalid job ID.');
            $this->redirect('/certificates/generate');
        }

        $job = $this->jobModel->findById($jobId);
        if (!$job) {
            $this->error('Job not found.');
            $this->redirect('/certificates/generate');
        }

        $db            = Database::getConnection();
        $eventName     = null;
        $symposiumName = null;

        $eventId     = (int) ($job['symposium_event_id'] ?? 0);
        $symposiumId = (int) ($job['symposium_id'] ?? 0);

        if ($eventId > 0) {
            $stmt = $db->prepare("
                SELECT se.event_name, s.title AS symposium_name
                FROM symposium_events se
                INNER JOIN symposiums s ON s.symposium_id = se.symposium_id
                WHERE se.symposium_event_id = :eid
            ");
            $stmt->execute(['eid' => $eventId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $eventName     = $row['event_name'];
                $symposiumName = $row['symposium_name'];
            }
        } elseif ($symposiumId > 0) {
            $stmt = $db->prepare("SELECT title FROM symposiums WHERE symposium_id = :sid");
            $stmt->execute(['sid' => $symposiumId]);
            $symposiumName = $stmt->fetchColumn() ?: 'Symposium';
        }

        $progress = 0;
        $total    = (int) $job['total_items'];
        $done     = (int) $job['processed_items'];
        if ($total > 0) {
            $progress = (int) min(100, round(($done / $total) * 100));
        }
        if ($job['status'] === 'Completed') {
            $progress = 100;
        }

        $this->render('certificate.job_progress', [
            'page_title'     => 'Certificate Generation Progress',
            'job'            => $job,
            'job_id'         => $jobId,
            'event_name'     => $eventName,
            'symposium_name' => $symposiumName,
            'progress'       => $progress,
        ]);
    }

    private function verifyCsrf(): void
    {
        $token         = $_POST['_token'] ?? '';
        $sessionToken  = Session::get('_token', '');
        if (!hash_equals($sessionToken, $token)) {
            $this->error('Security token mismatch. Please try again.');
            $this->redirect('/certificates');
        }
    }
}