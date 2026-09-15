<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore
 * =========================================================================
 * File        : PdfDocumentService.php
 * Location    : app/Services/
 * Description : Generates official PDF documents for approved symposiums.
 *
 * -------------------------------------------------------------------------
 * ARCHITECTURAL PRINCIPLE — SINGLE SOURCE OF TRUTH
 * -------------------------------------------------------------------------
 * Organizing Department information is ALWAYS resolved from the
 * `symposium_departments` junction table via SymposiumDepartmentModel.
 *
 * The legacy column `symposiums.organizing_department_id` is NEVER
 * used for business logic in this service. It exists only as a
 * backward-compatibility foreign key.
 *
 * This principle applies to:
 *   ✓ Circular PDF — signature blocks are built from organizing depts
 *   ✓ Brochure PDF — dept display is built from organizing depts
 *   ✓ HOD name resolution — resolved via department_approvers mapping
 *
 * -------------------------------------------------------------------------
 * Document Types
 * -------------------------------------------------------------------------
 *   'circular'  → Official Circular (with approval signatures)
 *   'brochure'  → Event Brochure (with competitions listing)
 *
 * =========================================================================
 */

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Models\SymposiumModel;
use App\Models\SymposiumEventModel;
use App\Models\SymposiumApprovalModel;
use App\Models\SymposiumDepartmentModel;

final class PdfDocumentService
{
    private SymposiumModel           $symposiumModel;
    private SymposiumEventModel      $eventModel;
    private SymposiumApprovalModel   $approvalModel;
    private SymposiumDepartmentModel $deptModel;

    public function __construct()
    {
        $this->symposiumModel = new SymposiumModel();
        $this->eventModel     = new SymposiumEventModel();
        $this->approvalModel  = new SymposiumApprovalModel();
        $this->deptModel      = new SymposiumDepartmentModel();
    }

    // =========================================================================
    // CACHE
    // =========================================================================

    /** Directory where rendered PDFs are stored (relative to project root). */
    private const CACHE_DIR = __DIR__ . '/../../storage/pdf_cache';

    /**
     * Return the cache file path for a given symposium + type + updated_at.
     * The updated_at timestamp acts as a natural cache-busting key —
     * any edit to the symposium automatically produces a new filename.
     */
    private function cachePath(int $symposiumId, string $type, string $updatedAt): string
    {
        $slug = preg_replace('/[^a-z0-9_\-]/i', '', $type);
        $ts   = preg_replace('/[^0-9]/', '', $updatedAt); // "2026-08-01 10:00:00" → "20260801100000"
        return self::CACHE_DIR . "/{$symposiumId}_{$slug}_{$ts}.pdf";
    }

    /**
     * Purge all cached PDFs for a specific symposium (call after any update).
     */
    public function purgeCache(int $symposiumId): void
    {
        if (!is_dir(self::CACHE_DIR)) return;
        foreach (glob(self::CACHE_DIR . "/{$symposiumId}_*.pdf") ?: [] as $file) {
            @unlink($file);
        }
    }

    // =========================================================================
    // GENERATE
    // =========================================================================

    public function generateSymposiumDocument(int $symposiumId, string $type): void
    {
        $symposium = $this->symposiumModel->findById($symposiumId);
        if (!$symposium) {
            die('Symposium not found.');
        }

        // Must be approved to generate official documents
        if (!in_array($symposium['status'], ['Approved', 'Scheduling Complete', 'Registration Open', 'Registration Closed', 'Completed'], true)) {
            die('Documents can only be generated for approved symposiums.');
        }

        $filename = str_replace([' ', '/'], '_', $symposium['title']) . "_{$type}.pdf";

        // ── Cache check ──────────────────────────────────────────────────────
        $updatedAt = (string)($symposium['updated_at'] ?? $symposium['created_at'] ?? '0');
        $cacheFile = $this->cachePath($symposiumId, $type, $updatedAt);

        if (file_exists($cacheFile)) {
            // Serve instantly from cache — no rendering needed
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($cacheFile));
            header('Cache-Control: public, max-age=3600');
            readfile($cacheFile);
            exit;
        }

        // ── Render ───────────────────────────────────────────────────────────
        $organizingDepts = $this->deptModel->getDepartmentsForSymposium($symposiumId);
        $hodsByDept      = $this->deptModel->getHodsForSymposium($symposiumId);
        $competitions    = $this->eventModel->getBySymposium($symposiumId);
        $approvals       = $this->approvalModel->getApprovalsForSymposium($symposiumId);

        $html = match ($type) {
            'circular' => $this->renderCircular($symposium, $organizingDepts, $hodsByDept, $competitions, $approvals),
            'brochure' => $this->renderBrochure($symposium, $organizingDepts, $competitions),
            'schedule' => $this->renderScheduleReport($symposium, $competitions),
            default    => null,
        };

        if ($html === null) {
            die('Invalid document type.');
        }

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output();

        // ── Store in cache ───────────────────────────────────────────────────
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR, 0755, true);
        }
        // Purge any older cached versions for this symposium+type before saving
        foreach (glob(self::CACHE_DIR . "/{$symposiumId}_{$type}_*.pdf") ?: [] as $old) {
            @unlink($old);
        }
        file_put_contents($cacheFile, $pdfContent);

        // ── Stream to browser ────────────────────────────────────────────────
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: public, max-age=3600');
        echo $pdfContent;
        exit;
    }

    // =========================================================================
    // CIRCULAR
    // =========================================================================

    /**
     * Render the Official Circular HTML.
     *
     * Signature blocks are built dynamically from the organizing departments
     * resolved via symposium_departments → department_approvers → HOD users.
     *
     * @param array $symposium       Symposium record (includes organizing_departments GROUP_CONCAT for display)
     * @param array $organizingDepts Rows from SymposiumDepartmentModel::getDepartmentsForSymposium()
     * @param array $hodsByDept      Rows from SymposiumDepartmentModel::getHodsForSymposium()
     * @param array $competitions    Competition records
     * @param array $approvals       Approval history records
     *
     * @return string HTML
     */
    private function renderCircular(
        array $symposium,
        array $organizingDepts,
        array $hodsByDept,
        array $competitions,
        array $approvals
    ): string {
        $settingModel = new \App\Models\SystemSettingModel();
        $rawLogoPath = $settingModel->getValue('COLLEGE_LOGO');
        $logoBase64 = $this->imageToBase64($rawLogoPath);
        
        $organizerNames = array_column($organizingDepts, 'department_name');
        if (empty($organizerNames)) {
            $deptTitle = strtoupper($symposium['organizing_departments'] ?? 'DEPARTMENT');
        } else {
            $formattedNames = [];
            foreach ($organizerNames as $n) {
                $n = strtoupper($n);
                if ($n === 'PG DEPARTMENT OF COMPUTER SCIENCE') {
                    $formattedNames[] = 'PG DEPARTMENT OF COMPUTER SCIENCE';
                } elseif ($n === 'PG DEPARTMENT OF COMPUTER APPLICATIONS') {
                    $formattedNames[] = 'COMPUTER APPLICATION';
                } elseif ($n === 'BACHELOR OF COMPUTER APPLICATIONS') {
                    $formattedNames[] = 'COMPUTER APPLICATION';
                } else {
                    $formattedNames[] = $n;
                }
            }
            $deptTitle = implode("<br>&<br>", $formattedNames);
        }

        $regDate = !empty($symposium['registration_start']) ? \App\Helpers\DateHelper::date($symposium['registration_start']) : 'TBA';
        $sympType = strtolower($symposium['symposium_type'] ?? 'intra department') . ' competition';
        $title = $symposium['title'] ?? 'Symposium';
        $academicYear = $symposium['academic_year'] ?? date('Y') . '-' . (date('Y') + 1);

        $html = "
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { margin: 20px; }
            </style>
        </head>
        <body>
            <table style='width: 100%; margin-bottom: 5px; border-collapse: collapse;'>
                <tr>
                    <td style='width: 25%; text-align: center; vertical-align: middle;'>
                        " . ($logoBase64 ? "<img src='{$logoBase64}' style='max-height: 130px; max-width: 130px;'>" : "") . "
                    </td>
                    <td style='width: 75%; text-align: center; vertical-align: middle; line-height: 1.2;'>
                        <span style=\"font-size: 26px; font-weight: bold; font-family: 'Times New Roman', Times, serif;\">Government Arts and Science College,</span><br>
                        <span style=\"font-size: 18px; font-weight: bold; font-family: 'Times New Roman', Times, serif;\">Veerapandi-625534,</span><br>
                        <span style=\"font-size: 16px; font-weight: bold; font-family: 'Times New Roman', Times, serif;\">Theni District.</span><br>
                        <span style=\"font-size: 14px; font-weight: bold; font-family: 'Times New Roman', Times, serif;\">Website: www.gascveerapand.in</span><br>
                        <span style=\"font-size: 14px; font-weight: bold; font-family: 'Times New Roman', Times, serif;\">e-Mail: gascvrp@gmail.com</span>
                    </td>
                </tr>
            </table>
            
            <hr style='border: 2px solid #000; margin-top: 5px; margin-bottom: 30px;'>

            <div style=\"text-align: center; font-family: 'Times New Roman', Times, serif; font-size: 18px; font-weight: bold; margin-bottom: 30px; line-height: 1.5;\">
                {$deptTitle}
            </div>

            <div style=\"text-align: center; font-family: 'Times New Roman', Times, serif; font-size: 18px; font-weight: bold; text-decoration: underline; margin-bottom: 40px;\">
                Circular
            </div>

            <p style=\"text-indent: 50px; text-align: justify; font-size: 16px; font-family: 'Times New Roman', Times, serif; line-height: 1.8; margin-bottom: 20px;\">
                We are pleased to announce the upcoming {$sympType} ({$title}) for the academic year {$academicYear}. These events are designed to provide for students to showcase their talents, enhance their skill and foster healthy competition.
            </p>

            <p style=\"text-indent: 50px; text-align: justify; font-size: 16px; font-family: 'Times New Roman', Times, serif; line-height: 1.8; margin-bottom: 60px;\">
                We encourage all the students to actively participate and make use of the opportunity. Let's come together to make the competitions exciting and memorable. The details of the competition are informed in the department notice board. The date of the events will be intimated later. Students can register for the event on {$regDate}. For further clarification, contact event coordinators.
            </p>";

        $signatureCells = $this->buildSignatureCells($hodsByDept, $organizingDepts);

        $principalSignature = '<div style="height: 60px;"></div>';
        foreach ($approvals as $approval) {
            if ($approval['approval_level'] === 'Principal' && $approval['status'] === 'Approved') {
                if (!empty($approval['signature_path'])) {
                    $sigBase64 = $this->imageToBase64($approval['signature_path']);
                    if ($sigBase64) {
                        $principalSignature = "<img src='{$sigBase64}' style='height: 60px; max-width: 150px;'>";
                    }
                }
                break;
            }
        }

        $html .= "
            <table style='width: 100%; border-collapse: collapse;'>
                <tr>
                    {$signatureCells}
                    <td style='width: 30%; text-align: center; vertical-align: bottom;'>
                        {$principalSignature}
                        <div style=\"font-weight: bold; font-family: 'Times New Roman', Times, serif; font-size: 16px;\">Principal</div>
                    </td>
                </tr>
            </table>
        </body>
        </html>";

        return $html;
    }

    /**
     * Build dynamic signature <td> elements for each organizing department's HOD.
     *
     * Each organizing department gets one signature block showing:
     *   - HOD's actual name (or "HOD — Dept Name" if user not found)
     *   - The department they represent
     *
     * Source: `symposium_departments` → `department_approvers` → `users`
     *
     * @param array $hodsByDept      Rows from getHodsForSymposium()
     * @param array $organizingDepts Rows from getDepartmentsForSymposium()
     *
     * @return string HTML <td> blocks
     */
    private function buildSignatureCells(array $hodsByDept, array $organizingDepts): string
    {
        if (empty($hodsByDept) && empty($organizingDepts)) {
            return '<td style="width: 30%; text-align: center; vertical-align: bottom;">
                        <div style="height: 60px;"></div>
                        <div style="font-weight: bold; font-family: \'Times New Roman\', Times, serif; font-size: 16px;">Head of the Department</div>
                    </td>';
        }

        // Re-index HODs by department_id for quick lookup
        $hodMap = [];
        foreach ($hodsByDept as $hod) {
            $hodMap[(int)$hod['department_id']] = $hod;
        }

        $cells = '';
        foreach ($organizingDepts as $dept) {
            $deptId = (int)$dept['department_id'];
            $deptName = $dept['department_name'];
            
            $shortName = $deptName;
            if (strtoupper($shortName) === 'PG DEPARTMENT OF COMPUTER SCIENCE') {
                $shortName = 'PG Department of CS';
            } elseif (strtoupper($shortName) === 'PG DEPARTMENT OF COMPUTER APPLICATIONS') {
                $shortName = 'PG Department of CA';
            } elseif (strtoupper($shortName) === 'BACHELOR OF COMPUTER APPLICATIONS') {
                $shortName = 'BCA';
            }

            $hod = $hodMap[$deptId] ?? null;
            $sigHtml = '<div style="height: 60px;"></div>';
            if ($hod && !empty($hod['signature_path'])) {
                $sigBase64 = $this->imageToBase64($hod['signature_path']);
                if ($sigBase64) {
                    $sigHtml = "<img src='{$sigBase64}' style='height: 60px; max-width: 150px;'>";
                }
            }
            
            $cells .= "
                <td style='width: 30%; text-align: center; vertical-align: bottom;'>
                    {$sigHtml}
                    <div style=\"font-weight: bold; font-family: 'Times New Roman', Times, serif; font-size: 16px;\">Head of the Department</div>
                    <div style=\"font-family: 'Times New Roman', Times, serif; font-size: 14px;\">({$shortName})</div>
                </td>";
        }

        return $cells;
    }

    /**
     * Helper to convert an image path to a base64 Data URI for reliable dompdf rendering
     */
    private function imageToBase64(?string $relativePath): string
    {
        if (empty($relativePath)) return '';
        
        if (str_starts_with($relativePath, 'http')) {
            $parsed = parse_url($relativePath);
            $relativePath = ltrim($parsed['path'] ?? '', '/');
            $relativePath = preg_replace('/^NexusCore\//', '', $relativePath);
            $relativePath = preg_replace('/^public\//', '', $relativePath);
        }

        $fullPath = __DIR__ . '/../../public/' . ltrim($relativePath, '/');
        
        if (file_exists($fullPath)) {
            $type = pathinfo($fullPath, PATHINFO_EXTENSION);
            $data = file_get_contents($fullPath);
            return 'data:image/' . $type . ';base64,' . base64_encode($data);
        }
        
        return '';
    }

    // =========================================================================
    // BROCHURE
    // =========================================================================

    /**
     * Render the Event Brochure HTML.
     *
     * Organizing departments are resolved from symposium_departments and
     * displayed as a proper list — never from organizing_department_id.
     *
     * @param array $symposium       Symposium record
     * @param array $organizingDepts Rows from SymposiumDepartmentModel::getDepartmentsForSymposium()
     * @param array $competitions    Competition records
     *
     * @return string HTML
     */
    private function renderBrochure(array $symposium, array $organizingDepts, array $competitions): string
    {
        $events = $competitions;
        $isManager = false;
        
        ob_start();
        require __DIR__ . '/../../templates/symposiums/notice_board.php';
        $noticeHtml = ob_get_clean();

        $html = "
        <html>
        <head>
            <meta charset='utf-8'>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; margin: 0; }
                .text-center { text-align: center; }
                .text-uppercase { text-transform: uppercase; }
                .text-muted { color: #6c757d; }
                .text-primary { color: #0d6efd; }
                .fw-bold { font-weight: bold; }
                .mb-0 { margin-bottom: 0; }
                .mb-2 { margin-bottom: 8px; }
                .mb-3 { margin-bottom: 16px; }
                .mb-4 { margin-bottom: 24px; }
                .fs-4 { font-size: 1.5rem; }
                .d-flex, .justify-content-between, .align-items-center { display: block; }
                .no-print { display: none !important; }
            </style>
        </head>
        <body>
            {$noticeHtml}
        </body>
        </html>
        ";

        return $html;
    }

    /**
     * Render the Schedule Report HTML.
     */
    private function renderScheduleReport(array $symposium, array $competitions): string
    {
        $symposiumId = (int) $symposium['symposium_id'];
        
        $grouped_days = $this->eventModel->getGroupedByDay($symposiumId);
        $dashboard    = $this->eventModel->getSchedulingDashboard($symposiumId);
        
        ob_start();
        $pageTitle = 'Schedule Report — ' . htmlspecialchars($symposium['title']);
        require __DIR__ . '/../../templates/scheduling/report.php';
        $reportHtml = ob_get_clean();

        $html = "
        <html>
        <head>
            <meta charset='utf-8'>
        </head>
        <body>
            <style>
                .report-action-bar { display: none !important; }
            </style>
            {$reportHtml}
        </body>
        </html>
        ";

        return $html;
    }

    /**
     * Generate and stream the Department Registration Report PDF.
     */
    public function generateDepartmentRegistrationReport(array $symposium, array $reportData, array $filters): void
    {
        $settingModel = new \App\Models\SystemSettingModel();
        $rawLogoPath = $settingModel->getValue('COLLEGE_LOGO');
        $collegeLogoBase64 = $this->imageToBase64($rawLogoPath);
        $nexusLogoBase64 = $this->imageToBase64('assets/images/logo/nexuscore-logo.png');
        $collegeName = $settingModel->getValue('COLLEGE_NAME', 'Government Arts and Science College');

        ob_start();
        require __DIR__ . '/../../templates/coordinator/registrations/department_report_pdf.php';
        $html = ob_get_clean();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Times-Roman');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'Department_Report_' . str_replace([' ', '/'], '_', $symposium['title']) . '.pdf';
        
        $dompdf->stream($filename, [
            "Attachment" => true
        ]);
        exit;
    }

    /**
     * Generate and stream the Students Not Registered Report PDF.
     */
    public function generateNotRegisteredReport(array $symposium, array $unregisteredStudents, array $filters, array $stats, array $departments): void
    {
        $settingModel = new \App\Models\SystemSettingModel();
        $rawLogoPath = $settingModel->getValue('COLLEGE_LOGO');
        $collegeLogoBase64 = $this->imageToBase64($rawLogoPath);
        $nexusLogoBase64 = $this->imageToBase64('assets/images/logo/nexuscore-logo.png');
        $collegeName = $settingModel->getValue('COLLEGE_NAME', 'Government Arts and Science College');

        ob_start();
        require __DIR__ . '/../../templates/coordinator/registrations/not_registered_pdf.php';
        $html = ob_get_clean();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Times-Roman');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'Not_Registered_' . str_replace([' ', '/'], '_', $symposium['title']) . '.pdf';
        
        $dompdf->stream($filename, [
            "Attachment" => true
        ]);
        exit;
    }
}
