<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : StudentCertificateController.php
 * Location    : app/Controllers/
 * Description : Handles all student-facing certificate routes.
 *               Every method is protected by StudentAuthMiddleware.
 *
 * Routes
 * -------------------------------------------------------------------------
 * GET /student/certificates            → index()   — list own active certs
 * GET /student/certificates/view       → view()    — inline PDF
 * GET /student/certificates/download   → download() — attachment PDF
 *
 * Security Model
 * -------------------------------------------------------------------------
 * • StudentAuthMiddleware::handle() is called in the constructor.
 *   All methods are authenticated — no public access.
 * • cert_id is accepted from the URL query string as a LOOKUP KEY ONLY.
 *   The URL value grants nothing. Server-side ownership is ALWAYS enforced
 *   inside CertificateDownloadService::streamSingleForStudent().
 * • Only certificates with generation_status = 'Generated', is_current = 1,
 *   file_path != '', and a physically present PDF file may be downloaded.
 * • Superseded, Generating, Finalizing, and Failed certificates are NOT
 *   offered to the student even if they know the cert_id.
 *
 * Project     : NexusCore
 * Branch      : feature/certificate-system-refactor
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\StudentAuthMiddleware;
use App\Models\GeneratedCertificateModel;
use App\Services\CertificateDownloadService;
use App\Services\CertificateRankService;

final class StudentCertificateController
{
    private array $student;
    private CertificateRankService $rankService;
    private GeneratedCertificateModel $certModel;

    public function __construct()
    {
        // Enforce student authentication on every method in this controller
        StudentAuthMiddleware::handle();

        $this->student     = Session::get('student', []);
        $this->rankService = new CertificateRankService();
        $this->certModel   = new GeneratedCertificateModel();
    }

    // =========================================================================
    // GET /student/certificates
    // =========================================================================

    /**
     * List all active (is_current = 1, Generated) certificates for the
     * authenticated student.
     */
    public function index(): void
    {
        $studentId = (int) ($this->student['student_id'] ?? 0);

        if ($studentId === 0) {
            $this->redirectToLogin();
        }

        $rawCerts = $this->certModel->getByStudent($studentId);

        // Enrich each certificate with its display_label
        $certificates = array_map(function (array $cert): array {
            $certType = $cert['certificate_type'] ?? 'Legacy';
            $rank     = isset($cert['rank_position']) ? (int) $cert['rank_position'] : null;

            $cert['display_label']      = $this->rankService->getCertificateDisplayLabel($certType, $rank);
            $cert['is_winner']          = $this->rankService->isWinnerRank($rank) && $certType === 'Winner';
            $cert['certificate_type_label'] = match ($certType) {
                'Winner'      => 'Winner',
                'Participant' => 'Participant',
                default       => 'Certificate',
            };
            return $cert;
        }, $rawCerts);

        $this->render('student.my_certificates', [
            'pageTitle'    => 'My Certificates — NexusCore',
            'certificates' => $certificates,
            'student'      => $this->student,
        ]);
    }

    // =========================================================================
    // GET /student/certificates/view?cert_id={id}
    // =========================================================================

    /**
     * Stream a certificate as an inline PDF.
     *
     * cert_id is a lookup key only — ownership is enforced in the service.
     */
    public function view(): void
    {
        $certId    = (int) ($_GET['cert_id'] ?? 0);
        $studentId = (int) ($this->student['student_id'] ?? 0);

        if ($certId <= 0 || $studentId === 0) {
            http_response_code(400);
            exit('Invalid request.');
        }

        (new CertificateDownloadService())->streamSingleForStudent(
            $certId,
            $studentId,
            true  // inline = true
        );
    }

    // =========================================================================
    // GET /student/certificates/download?cert_id={id}
    // =========================================================================

    /**
     * Stream a certificate as a downloadable PDF attachment.
     *
     * cert_id is a lookup key only — ownership is enforced in the service.
     */
    public function download(): void
    {
        $certId    = (int) ($_GET['cert_id'] ?? 0);
        $studentId = (int) ($this->student['student_id'] ?? 0);

        if ($certId <= 0 || $studentId === 0) {
            http_response_code(400);
            exit('Invalid request.');
        }

        (new CertificateDownloadService())->streamSingleForStudent(
            $certId,
            $studentId,
            false  // inline = false → attachment
        );
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Render a student portal view using the student layout.
     *
     * @param string $view    Dot-notation view path (e.g. 'student.my_certificates')
     * @param array  $data    Variables to extract into the view scope
     */
    private function render(string $view, array $data = []): void
    {
        extract($data);

        // Convert dot notation to path
        $viewFile = __DIR__ . '/../../templates/'
            . str_replace('.', '/', $view) . '.php';

        $contentFile = realpath($viewFile);

        if (!$contentFile || !is_file($contentFile)) {
            http_response_code(500);
            exit('View not found: ' . htmlspecialchars($view, ENT_QUOTES));
        }

        $layoutFile = __DIR__ . '/../../templates/layouts/student.php';

        if (!file_exists($layoutFile)) {
            http_response_code(500);
            exit('Student layout not found.');
        }

        require $layoutFile;
    }

    /**
     * Redirect to student login page.
     */
    private function redirectToLogin(): never
    {
        header('Location: /student/login');
        exit;
    }
}
