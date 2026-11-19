<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\FacultyAssignmentModel;
use App\Models\Prelims\PrelimsQuizModel;
use App\Services\Prelims\PrelimsReportService;

final class PrelimsReportController extends BaseController
{
    private PrelimsReportService   $reportService;
    private PrelimsQuizModel       $quizModel;
    private FacultyAssignmentModel $facultyModel;

    public function __construct()
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Admin', 'Principal', 'HOD', 'Staff Coordinator', 'Staff');

        $this->reportService = new PrelimsReportService();
        $this->quizModel     = new PrelimsQuizModel();
        $this->facultyModel  = new FacultyAssignmentModel();
    }

    private function canViewEventReport(int $eventId): bool
    {
        $user = $this->user();
        $role = (string) ($user['role'] ?? '');
        $uid  = (int) ($user['user_id'] ?? 0);
        if (in_array($role, ['Admin', 'Principal', 'HOD', 'Staff Coordinator'], true)) {
            return true;
        }
        return $this->facultyModel->isAssigned($eventId, $uid);
    }

    /**
     * GET /reports/prelims?quiz_id=
     */
    public function prelims(): void
    {
        $quizId = (int) ($_GET['quiz_id'] ?? 0);
        $data   = $this->reportService->getPrelimsReportData($quizId);
        if (empty($data['success']) || !$this->canViewEventReport((int) $data['quiz']['symposium_event_id'])) {
            $this->error($data['message'] ?? 'Unauthorized or report not found.');
            $this->redirect('/dashboard');
        }

        $this->render('reports.prelims_report', [
            'pageTitle' => 'Prelims Report — ' . ($data['quiz']['event_name'] ?? ''),
            'report'    => $data,
        ]);
    }

    /**
     * GET /reports/prelims/pdf?quiz_id=
     */
    public function prelimsPdf(): void
    {
        $quizId = (int) ($_GET['quiz_id'] ?? 0);
        $data   = $this->reportService->getPrelimsReportData($quizId);
        if (empty($data['success']) || !$this->canViewEventReport((int) $data['quiz']['symposium_event_id'])) {
            http_response_code(403);
            exit('Unauthorized or report not found.');
        }

        $report = $data;
        ob_start();
        require dirname(__DIR__, 2) . '/templates/reports/prelims_report_pdf.php';
        $html = (string) ob_get_clean();

        $pdfBytes = $this->reportService->renderHtmlToPdfBytes($html, 'A4', 'landscape');
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', ($data['quiz']['event_name'] ?? 'Prelims') . '_Prelims_Report') . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfBytes));
        echo $pdfBytes;
        exit;
    }

    /**
     * GET /reports/prelims/csv?quiz_id=
     */
    public function prelimsCsv(): void
    {
        $quizId = (int) ($_GET['quiz_id'] ?? 0);
        $data   = $this->reportService->getPrelimsReportData($quizId);
        if (empty($data['success']) || !$this->canViewEventReport((int) $data['quiz']['symposium_event_id'])) {
            http_response_code(403);
            exit('Unauthorized or report not found.');
        }

        $csv      = $this->reportService->buildPrelimsCsv($data);
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', ($data['quiz']['event_name'] ?? 'Prelims') . '_Merit_List') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($csv));
        echo $csv;
        exit;
    }

    /**
     * GET /reports/main-event?event_id=
     */
    public function mainEvent(): void
    {
        $eventId = (int) ($_GET['event_id'] ?? 0);
        if ($eventId <= 0 || !$this->canViewEventReport($eventId)) {
            $this->error('Unauthorized or invalid event.');
            $this->redirect('/dashboard');
        }

        $data = $this->reportService->getMainEventReportData($eventId);
        if (empty($data['success'])) {
            $this->error($data['message'] ?? 'Event not found.');
            $this->redirect('/dashboard');
        }

        $this->render('reports.main_event_report', [
            'pageTitle' => 'Main Event Report — ' . ($data['event']['event_name'] ?? ''),
            'report'    => $data,
        ]);
    }

    /**
     * GET /reports/main-event/pdf?event_id=
     */
    public function mainEventPdf(): void
    {
        $eventId = (int) ($_GET['event_id'] ?? 0);
        if ($eventId <= 0 || !$this->canViewEventReport($eventId)) {
            http_response_code(403);
            exit('Unauthorized.');
        }

        $data = $this->reportService->getMainEventReportData($eventId);
        if (empty($data['success'])) {
            http_response_code(404);
            exit('Not found.');
        }

        $report = $data;
        ob_start();
        require dirname(__DIR__, 2) . '/templates/reports/main_event_report_pdf.php';
        $html = (string) ob_get_clean();

        $pdfBytes = $this->reportService->renderHtmlToPdfBytes($html, 'A4', 'portrait');
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', ($data['event']['event_name'] ?? 'Event') . '_Main_Event_Report') . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfBytes));
        echo $pdfBytes;
        exit;
    }
}
