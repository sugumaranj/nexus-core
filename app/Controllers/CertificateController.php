<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : CertificateController.php
 * Location    : app/Controllers/
 * Description : Certificate Generation Module Controller.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Template management (upload, configure, activate, archive)
 * • Visual designer (save field positions)
 * • Certificate generation (single event / bulk symposium)
 * • Download (single PDF, bulk ZIP)
 * • AJAX endpoints for dynamic form fields
 *
 * Access
 * -------------------------------------------------------------------------
 * • Roles: Admin, Staff Coordinator
 * • All routes protected by AuthMiddleware + RoleMiddleware
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\CertificateTemplateModel;
use App\Models\GeneratedCertificateModel;
use App\Services\CertificateTemplateService;
use App\Services\CertificateBulkService;
use App\Services\CertificateDownloadService;
use App\Services\CertificatePackageService;
use App\Services\CertificateGeneratorService;
use App\Database\Database;
use App\Core\Session;
use PDO;

final class CertificateController extends BaseController
{
    private CertificateTemplateModel    $templateModel;
    private GeneratedCertificateModel   $certModel;
    private CertificateTemplateService  $templateService;
    private CertificateBulkService      $bulkService;
    private CertificateDownloadService  $downloadService;
    private CertificatePackageService   $packageService;

    public function __construct()
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator');

        $this->templateModel   = new CertificateTemplateModel();
        $this->certModel       = new GeneratedCertificateModel();
        $this->templateService = new CertificateTemplateService();
        $this->bulkService     = new CertificateBulkService();
        $this->downloadService = new CertificateDownloadService();
        $this->packageService  = new CertificatePackageService();
    }

    // =========================================================================
    // Template Library
    // =========================================================================

    /**
     * Template library — list all templates.
     */
    public function index(): void
    {
        $templates = $this->templateModel->getAll(false, true);

        $this->render('certificate.index', [
            'page_title' => 'Certificate Templates',
            'templates'  => $templates,
        ]);
    }

    // =========================================================================
    // Template Upload
    // =========================================================================

    /**
     * Show the upload form.
     */
    public function create(): void
    {
        $this->render('certificate.create', [
            'page_title' => 'Upload Certificate Template',
        ]);
    }

    /**
     * Process template upload.
     */
    public function store(): void
    {
        $this->verifyCsrf();

        $file = $_FILES['pdf_file'] ?? null;

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $this->error('No file uploaded or upload error. Please try again.');
            $this->redirect('/certificates/create');
        }

        $data = [
            'template_name'     => trim($_POST['template_name'] ?? ''),
            'description'       => trim($_POST['description'] ?? ''),
            'rank_display_mode' => $_POST['rank_display_mode'] ?? 'checkboxes',
            'team_cert_mode'    => $_POST['team_cert_mode'] ?: null,
        ];

        if (empty($data['template_name'])) {
            $this->error('Template name is required.');
            $this->redirect('/certificates/create');
        }

        $result = $this->templateService->uploadTemplate($file, $data, $this->user()['user_id']);

        if (!$result['success']) {
            $this->error($result['message']);
            $this->redirect('/certificates/create');
        }

        $this->success('Template uploaded successfully. Now open the Designer to configure field positions.');
        $this->redirect('/certificates/designer?template_id=' . $result['template_id']);
    }

    // =========================================================================
    // Certificate Designer
    // =========================================================================

    /**
     * Visual designer for placing fields on the template.
     */
    public function designer(): void
    {
        $templateId = (int) ($_GET['template_id'] ?? 0);

        if ($templateId <= 0) {
            $this->error('Invalid template ID.');
            $this->redirect('/certificates');
        }

        $template = $this->templateModel->findById($templateId);

        if (!$template || $template['is_archived']) {
            $this->error('Template not found.');
            $this->redirect('/certificates');
        }

        $version    = $this->templateModel->getActiveVersion($templateId);
        $fieldConfig = [];

        if ($version) {
            $fieldConfig = json_decode($version['field_config'] ?? '[]', true) ?: [];
        }

        $this->render('certificate.designer', [
            'page_title'  => 'Certificate Designer — ' . htmlspecialchars($template['template_name']),
            'template'    => $template,
            'version'     => $version,
            'fieldConfig' => $fieldConfig,
            'fieldRegistry' => CertificateTemplateService::FIELD_REGISTRY,
        ]);
    }

    /**
     * Save field positions from designer (AJAX POST → JSON response).
     */
    public function saveDesign(): void
    {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);

        // CSRF validation for JSON endpoint — same session key as normal POST handlers
        $token        = $input['_token'] ?? '';
        $sessionToken = Session::get('_token', '');
        if (!$sessionToken || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Security token mismatch. Please reload the page and try again.']);
            return;
        }

        $templateId = (int) ($input['template_id'] ?? 0);
        $fields     = $input['fields'] ?? [];

        if ($templateId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid template ID.']);
            return;
        }

        $result = $this->templateService->saveDesign($templateId, $fields, $this->user()['user_id']);

        echo json_encode($result);
    }

    // =========================================================================
    // Template Actions
    // =========================================================================

    /**
     * Edit template metadata (GET → form, POST → update).
     */
    public function editTemplate(): void
    {
        $templateId = (int) ($_GET['id'] ?? 0);

        if ($templateId <= 0) {
            $this->error('Invalid template ID.');
            $this->redirect('/certificates');
        }

        $template = $this->templateModel->findById($templateId);
        if (!$template) {
            $this->error('Template not found.');
            $this->redirect('/certificates');
        }

        $this->render('certificate.edit_template', [
            'page_title' => 'Edit Template — ' . htmlspecialchars($template['template_name']),
            'template'   => $template,
        ]);
    }

    /**
     * Process template metadata update.
     */
    public function updateTemplate(): void
    {
        $this->verifyCsrf();

        $templateId = (int) ($_POST['template_id'] ?? 0);
        if ($templateId <= 0) {
            $this->error('Invalid template ID.');
            $this->redirect('/certificates');
        }

        $data = [
            'template_name'     => trim($_POST['template_name'] ?? ''),
            'description'       => trim($_POST['description'] ?? ''),
            'rank_display_mode' => $_POST['rank_display_mode'] ?? 'checkboxes',
            'team_cert_mode'    => $_POST['team_cert_mode'] ?: null,
        ];

        if (empty($data['template_name'])) {
            $this->error('Template name is required.');
            $this->redirect('/certificates/template/edit?id=' . $templateId);
        }

        $this->templateModel->update($templateId, $data);

        $this->success('Template updated successfully.');
        $this->redirect('/certificates');
    }

    /**
     * Activate a template.
     */
    public function activateTemplate(): void
    {
        $this->verifyCsrf();
        $templateId = (int) ($_POST['template_id'] ?? 0);

        $result = $this->templateService->activate($templateId, $this->user()['user_id']);

        if (!$result['success']) {
            $this->error($result['message']);
        } else {
            $this->success('Template activated and set as system default.');
        }

        $this->redirect('/certificates');
    }

    /**
     * Deactivate a template.
     */
    public function deactivateTemplate(): void
    {
        $this->verifyCsrf();
        $templateId = (int) ($_POST['template_id'] ?? 0);

        $result = $this->templateService->deactivate($templateId, $this->user()['user_id']);
        if ($result['success']) {
            $this->success('Template deactivated.');
        } else {
            $this->error($result['message'] ?? 'Deactivation failed.');
        }

        $this->redirect('/certificates');
    }

    /**
     * Duplicate a template (clone without design config).
     */
    public function duplicateTemplate(): void
    {
        $this->verifyCsrf();
        $templateId = (int) ($_POST['template_id'] ?? 0);

        $original = $this->templateModel->findById($templateId);
        if (!$original) {
            $this->error('Template not found.');
            $this->redirect('/certificates');
        }

        $newId = $this->templateModel->create([
            'template_name'     => $original['template_name'] . ' (Copy)',
            'description'       => $original['description'],
            'file_path'         => $original['file_path'],
            'file_hash'         => $original['file_hash'],
            'page_width_pt'     => $original['page_width_pt'],
            'page_height_pt'    => $original['page_height_pt'],
            'page_orientation'  => $original['page_orientation'],
            'rank_display_mode' => $original['rank_display_mode'],
            'team_cert_mode'    => $original['team_cert_mode'],
            'is_active'         => 0,
            'is_archived'       => 0,
            'created_by'        => $this->user()['user_id'],
        ]);

        $this->success('Template duplicated. You can now edit and configure the copy.');
        $this->redirect('/certificates/designer?template_id=' . $newId);
    }

    /**
     * Archive a template.
     */
    public function archiveTemplate(): void
    {
        $this->verifyCsrf();
        $templateId = (int) ($_POST['template_id'] ?? 0);

        $result = $this->templateService->archiveTemplate($templateId, $this->user()['user_id']);

        if ($result['success']) {
            $this->success('Template archived.');
        } else {
            $this->error($result['message'] ?? 'Archive failed.');
        }

        $this->redirect('/certificates');
    }

    public function unarchiveTemplate(): void
    {
        $this->verifyCsrf();
        $templateId = (int) ($_POST['template_id'] ?? 0);

        $result = $this->templateService->unarchiveTemplate($templateId, $this->user()['user_id']);

        if ($result['success']) {
            $this->success('Template unarchived.');
        } else {
            $this->error($result['message'] ?? 'Unarchive failed.');
        }

        $this->redirect('/certificates');
    }

    /**
     * Delete a template (only if no certificates generated).
     */
    public function deleteTemplate(): void
    {
        $this->verifyCsrf();
        $templateId = (int) ($_POST['template_id'] ?? 0);

        $result = $this->templateService->deleteTemplate($templateId, $this->user()['user_id']);

        if ($result['success']) {
            $this->success('Template deleted.');
        } else {
            $this->error($result['message']);
        }

        $this->redirect('/certificates');
    }

    // =========================================================================
    // Preview
    // =========================================================================

    /**
     * Preview selector — choose event and result for preview.
     */
    public function preview(): void
    {
        $templateId = (int) ($_GET['template_id'] ?? 0);
        $template   = $templateId ? $this->templateModel->findById($templateId) : null;

        // Get all locked events for selector
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT se.symposium_event_id, se.event_name, s.title AS symposium_title
            FROM symposium_events se
            JOIN symposiums s ON s.symposium_id = se.symposium_id
            WHERE se.is_locked = 1
            ORDER BY se.event_date DESC, se.event_name ASC
        ");
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $templates = $this->templateModel->getAll(true);

        $this->render('certificate.preview', [
            'page_title' => 'Preview Certificate',
            'template'   => $template,
            'templates'  => $templates,
            'events'     => $events,
        ]);
    }

    /**
     * Stream preview PDF using real result data.
     */
    public function previewPdf(): void
    {
        $templateId = (int) ($_GET['template_id'] ?? 0);
        
        if (isset($_GET['preview_only']) && $_GET['preview_only'] == '1') {
            if ($templateId <= 0) {
                http_response_code(400);
                echo 'Missing template_id';
                return;
            }
            $template = $this->templateModel->findById($templateId);
            if (!$template) {
                http_response_code(404);
                echo 'Template not found';
                return;
            }
            $templatePath = __DIR__ . '/../../' . ltrim($template['file_path'], '/');
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="template_bg.pdf"');
            readfile($templatePath);
            return;
        }

        $resultId = (int) ($_GET['result_id'] ?? 0);
        $isPreview = isset($_GET['preview']) && $_GET['preview'] == '1';
        $previewRank = isset($_GET['preview_rank']) ? (int)$_GET['preview_rank'] : 1;

        if ($templateId <= 0) {
            http_response_code(400);
            echo 'Missing template_id';
            return;
        }

        $template = $this->templateModel->findById($templateId);
        if (!$template) {
            http_response_code(404);
            echo 'Template not found';
            return;
        }

        $version = $this->templateModel->getActiveVersion($templateId);
        if (!$version) {
            http_response_code(422);
            echo 'No design configuration found. Please open the designer first.';
            return;
        }

        if ($resultId > 0) {
            // Fetch the result with full joins
            $db   = Database::getConnection();
            $stmt = $db->prepare("
                SELECT
                    cr.*,
                    a.application_type,
                    s.full_name       AS student_name,
                    s.gender,
                    s.register_number,
                    s.academic_year,
                    d.short_name      AS department_name,
                    se.event_name,
                    se.event_date,
                    v.venue_name AS venue_name,
                    se.participation_type,
                    sym.title         AS symposium_title
                FROM competition_results cr
                INNER JOIN applications a   ON a.application_id   = cr.application_id
                LEFT JOIN students s       ON s.student_id        = a.student_id
                LEFT JOIN departments d    ON d.department_id     = s.department_id
                INNER JOIN symposium_events se ON se.symposium_event_id = cr.symposium_event_id
                LEFT JOIN venues v ON v.venue_id = se.venue_id
                INNER JOIN symposiums sym   ON sym.symposium_id    = se.symposium_id
                WHERE cr.result_id = :result_id
                  AND cr.published  = 1
                LIMIT 1
            ");
            $stmt->execute(['result_id' => $resultId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                http_response_code(404);
                echo 'Published result not found.';
                return;
            }
            // Format academic_year as Roman numeral
            $result['academic_year'] = self::toRomanYear($result['academic_year'] ?? '');
        } elseif ($isPreview) {
            // Dummy data for designer preview — never touches DB records
            $result = [
                'student_name'    => 'Jane Doe',
                'gender'          => 'Female',
                'register_number' => 'REG-999999',
                'academic_year'   => 'III',
                'department_name' => 'B.Sc. CS',
                'event_name'      => 'Tech Debugging',
                'event_date'      => date('Y-m-d'),
                'venue_name'      => 'Main Auditorium',
                'symposium_title' => 'Nexus 2026',
                'rank_position'   => $previewRank,
                'result_status'   => $previewRank <= 3 ? 'Winner' : 'Participation',
                
            ];
        } else {
            http_response_code(400);
            echo 'Invalid request: Must provide result_id or preview=1';
            return;
        }

        $fieldConfig   = json_decode($version['field_config'] ?? '[]', true) ?: [];
        $templatePath  = __DIR__ . '/../../' . ltrim($template['file_path'], '/');

        // Use generator service
        $generator = new \App\Services\CertificateGeneratorService();

        try {
            $pdfBytes = $generator->generateOne($result, $fieldConfig, $templatePath, $template);
        } catch (\Throwable $e) {
            error_log('[CertificateController::previewPdf] Template ' . $templateId . ': ' . $e->getMessage());
            http_response_code(500);
            echo 'Unable to generate certificate preview. Please verify the template configuration and try again.';
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="preview_certificate.pdf"');
        header('Content-Length: ' . strlen($pdfBytes));
        header('Cache-Control: no-store, no-cache');
        echo $pdfBytes;
    }

    /**
     * Designer Preview — POST endpoint.
     *
     * Accepts the current, unsaved fieldsData from the designer and generates
     * a temporary preview PDF entirely in memory.
     *
     * NEVER writes to the database. The result is streamed inline to the browser.
     *
     * Security chain:
     *   1. AuthMiddleware::handle() — already enforced in __construct()
     *   2. RoleMiddleware::requireRole() — already enforced in __construct()
     *   3. CSRF from JSON body
     *   4. Template existence + archived check
     *   5. Tier 1 schema validation (via real production service method)
     *   6. Server-side field property whitelisting
     *
     * POST /certificates/designer/preview
     * Body: { _token, template_id, fields[], preview_rank, preview_gender }
     *
     * Success response:  Content-Type: application/pdf (inline)
     * Failure response:  Content-Type: application/json with HTTP error code
     */
    public function designerPreview(): void
    {
        // Auth + Role already enforced in __construct(). No further auth needed.

        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid JSON body.']);
            return;
        }

        // 1. CSRF validation — reuses same Session::get('_token') mechanism as all POST handlers
        $token        = $input['_token'] ?? '';
        $sessionToken = Session::get('_token', '');
        if (!$sessionToken || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
            return;
        }

        // 2. Validate template_id
        $templateId = (int) ($input['template_id'] ?? 0);
        if ($templateId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid template ID.']);
            return;
        }

        // 3. Load template and check existence + archived status
        //    (same pattern used by designer(), previewPdf(), and all other template endpoints)
        $template = $this->templateModel->findById($templateId);
        if (!$template || $template['is_archived']) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Template not found.']);
            return;
        }

        // 4. Tier 1 schema-only validation via the real production service method
        //    Allows partial/incomplete configurations — coordinator can preview mid-design.
        $rawFields = $input['fields'] ?? [];
        if (!is_array($rawFields)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Fields must be an array.']);
            return;
        }

        $validation = $this->templateService->validateFieldSchemaOnly(
            $rawFields,
            (float)($template['page_width_pt'] ?? 0),
            (float)($template['page_height_pt'] ?? 0)
        );
        if (!$validation['success']) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode($validation);
            return;
        }

        // 5. Server-side property whitelisting — authoritative filter for all submitted fields
        //    Client-side removal of properties (e.g. delete o.id) is NOT a security boundary.
        $fields = array_map([$this->templateService, 'whitelistField'], $rawFields);

        // 6. Preview parameters
        $previewRank   = max(0, min(4, (int)($input['preview_rank'] ?? 1)));
        $previewGender = strtolower(trim($input['preview_gender'] ?? 'male'));
        if (!in_array($previewGender, ['male', 'female'], true)) {
            $previewGender = 'male';
        }

        // 7. Build canonical preview result DTO.
        //    Keys confirmed from CertificateGeneratorService::resolveFieldValue() switch cases.
        //    Note: gender stored as 'Female'/'Male' in DB (capital) — strtolower() handled in generator.
        //    Note: academic_year passed as Roman numeral string (already converted upstream in other flows).
        $previewResult = [
            'student_name'    => 'Jane Doe',
            'gender'          => ucfirst($previewGender),   // 'Male' or 'Female' — matches DB format
            'register_number' => 'REG-999999',
            'academic_year'   => 'III',
            'department_name' => 'B.Sc. CS',     // short_name � matches d.short_name AS department_name in all DB queries
            'event_name'      => 'Tech Debugging Challenge',
            'event_date'      => date('Y-m-d'),
            'symposium_title' => 'Nexus ' . date('Y'),
            'rank_position'   => $previewRank,
            'result_status'   => $previewRank <= 3 ? 'Winner' : 'Participation',
            
        ];

        // 8. Resolve template PDF path using the canonical application pattern
        //    Confirmed from CertificateController::previewPdf() line 521 and
        //    CertificateBulkService::getAbsoluteTemplatePath() — identical pattern.
        $templatePath = __DIR__ . '/../../' . ltrim($template['file_path'], '/');
        if (!is_file($templatePath) || !is_readable($templatePath)) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Template PDF file could not be accessed.']);
            return;
        }

        // 9. Generate preview using the SAME canonical generator as final/bulk generation.
        //    Confirmed: CertificateBulkService and CertificateController::previewPdf() both
        //    use plain new CertificateGeneratorService() — no DI configuration to replicate.
        $generator = new CertificateGeneratorService();

        try {
            $pdfBytes = $generator->generateOne($previewResult, $fields, $templatePath, $template);
        } catch (\Throwable $e) {
            error_log('[CertificateController::designerPreview] Template ' . $templateId . ': ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unable to generate certificate preview. Please verify the template configuration.']);
            return;
        }

        // 10. Stream PDF inline — no DB write, no file storage.
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="designer_preview.pdf"');
        header('Content-Length: ' . strlen($pdfBytes));
        header('Cache-Control: no-store, no-cache');
        echo $pdfBytes;
    }

    // =========================================================================
    // Certificate Generation
    // =========================================================================

    /**
     * Generation form — select event / symposium.
     */
    public function generate(): void
    {
        $db   = Database::getConnection();

        // Symposiums with at least one locked event
        $stmt = $db->prepare("
            SELECT DISTINCT s.symposium_id, s.title AS symposium_name, s.symposium_code
            FROM symposiums s
            INNER JOIN symposium_events se ON se.symposium_id = s.symposium_id
            WHERE se.is_locked = 1
            ORDER BY s.title ASC
        ");
        $stmt->execute();
        $symposiums = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $templates = $this->templateModel->getAll(true); // active only

        $this->render('certificate.generate', [
            'page_title' => 'Generate Certificates',
            'symposiums' => $symposiums,
            'templates'  => $templates,
        ]);
    }

    /**
     * Execute certificate generation.
     */
    public function runGeneration(): void
    {
        $this->verifyCsrf();

        $symposiumEventId     = (int) ($_POST['event_id'] ?? 0);
        $templateId           = (int) ($_POST['template_id'] ?? 0);
        $includeParticipation = isset($_POST['include_participation']);
        $allowRegenerate      = isset($_POST['allow_regenerate']);

        if ($symposiumEventId <= 0) {
            $this->error('Please select an event.');
            $this->redirect('/certificates/generate');
        }

        $report = $this->bulkService->generateForEvent(
            $symposiumEventId,
            $this->user()['user_id'],
            $includeParticipation,
            $allowRegenerate,
            $templateId > 0 ? $templateId : null
        );

        // Store report in session for display
        Session::set('cert_generation_report', $report);

        $this->redirect('/certificates/report');
    }

    /**
     * Generation report page.
     */
    public function generationReport(): void
    {
        $report = Session::get('cert_generation_report', []);
        Session::remove('cert_generation_report');

        $this->render('certificate.report', [
            'page_title' => 'Certificate Generation Report',
            'report'     => $report,
        ]);
    }

    // =========================================================================
    // Generated Certificates List
    // =========================================================================

    /**
     * List all generated certificates with filters.
     */
        public function generated(): void
    {
        $eventId = (int) ($_GET['event_id'] ?? 0);
        $symposiumId = (int) ($_GET['symposium_id'] ?? 0);
        $eventReport = null;
        $symposiumReport = null;

        $db = Database::getConnection();

        if ($eventId > 0) {
            $certs = $this->certModel->getByEvent($eventId);
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
            'page_title'      => 'Generated Certificates',
            'certs'           => $certs,
            'events'          => $events,
            'symposiums'      => $symposiums,
            'selected_event'  => $eventId,
            'selected_symposium' => $symposiumId,
            'event_report'    => $eventReport,
            'symposium_report' => $symposiumReport,
        ]);
    }


    // =========================================================================
    // Download
    // =========================================================================

    /**
     * Download a single certificate PDF (attachment).
     */
    public function download(): void
    {
        $certId = (int) ($_GET['id'] ?? 0);

        if ($certId <= 0) {
            $this->error('Invalid certificate ID.');
            $this->redirect('/certificates/generated');
        }

        $this->downloadService->streamSingle($certId, $this->user(), false);
    }

    /**
     * View a single certificate PDF (inline).
     */
    public function view(): void
    {
        $certId = (int) ($_GET['id'] ?? 0);

        if ($certId <= 0) {
            $this->error('Invalid certificate ID.');
            $this->redirect('/certificates/generated');
        }

        $this->downloadService->streamSingle($certId, $this->user(), true);
    }

    /**
     * Bulk ZIP download.
     */
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

    /**
     * Event ZIP download.
     */
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

    /**
     * Symposium Package download.
     */
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
            $this->redirect('/symposiums/show?id=' . $symposiumId);
        }
    }

    /**
     * Symposium Package PDF download.
     */
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

    // =========================================================================
    // AJAX Endpoints
    // =========================================================================

    /**
     * AJAX: Get all locked events for a symposium.
     * GET /certificates/ajax/events?symposium_id=X
     */
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

    /**
     * AJAX: Get published results for an event (for preview selector).
     * GET /certificates/ajax/results?event_id=X
     */
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

    // =========================================================================
    // Internal Helpers
    // =========================================================================

    /**
     * Verify CSRF token on POST requests.
     */
    private function verifyCsrf(): void
    {
        $token         = $_POST['_token'] ?? '';
        $sessionToken  = Session::get('_token', '');

        if (!hash_equals($sessionToken, $token)) {
            $this->error('Security token mismatch. Please try again.');
            $this->redirect('/certificates');
        }
    }

    /**
     * Convert a numeric academic year to a Roman numeral string.
     * e.g. 1 → "I", 2 → "II", 3 → "III", 4 → "IV"
     * Non-numeric / unknown values are returned unchanged.
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
