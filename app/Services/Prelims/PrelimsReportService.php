<?php

declare(strict_types=1);

namespace App\Services\Prelims;

use App\Database\Database;
use App\Models\AttendanceRecordModel;
use App\Models\Prelims\PrelimsAttemptModel;
use App\Models\Prelims\PrelimsQuizModel;
use App\Models\Prelims\PrelimsShortlistModel;
use App\Models\SymposiumEventModel;
use Dompdf\Dompdf;
use Dompdf\Options;
use PDO;

/**
 * Generates Comprehensive Reports for BOTH Prelims & Main Event (HTML, Official Dompdf PDF, CSV).
 * Operates seamlessly both BEFORE and AFTER quiz content disposal!
 */
final class PrelimsReportService
{
    private PDO                   $db;
    private PrelimsQuizModel      $quizModel;
    private PrelimsAttemptModel   $attemptModel;
    private PrelimsShortlistModel $shortlistModel;
    private AttendanceRecordModel $attendanceModel;
    private SymposiumEventModel   $eventModel;
    private QuizDisposalService   $disposalService;

    public function __construct()
    {
        $this->db              = Database::getConnection();
        $this->quizModel       = new PrelimsQuizModel();
        $this->attemptModel    = new PrelimsAttemptModel();
        $this->shortlistModel  = new PrelimsShortlistModel();
        $this->attendanceModel = new AttendanceRecordModel();
        $this->eventModel      = new SymposiumEventModel();
        $this->disposalService = new QuizDisposalService();
    }

    /**
     * Build complete Prelims Report dataset (works pre- and post-disposal).
     */
    public function getPrelimsReportData(int $quizId): array
    {
        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz) {
            return ['success' => false, 'message' => 'Prelims quiz not found.'];
        }

        $eventId    = (int) $quiz['symposium_event_id'];
        $attendance = $this->attendanceModel->getSummaryByEvent($eventId, 'Prelims');
        $attempts   = $this->attemptModel->getRankedAttemptsForQuiz($quizId);
        $shortlist  = $this->shortlistModel->getByQuiz($quizId);
        $selected   = array_values(array_filter(
            $shortlist,
            static fn(array $r): bool => ($r['selection_status'] ?? '') === 'Selected'
        ));
        $violations = $this->attemptModel->getViolationsForQuiz($quizId, 200);

        // Analytics: read frozen snapshot if disposed, else compute live
        $analytics = null;
        if (!empty($quiz['analytics_snapshot_json'])) {
            $analytics = json_decode((string) $quiz['analytics_snapshot_json'], true);
        }
        if (!is_array($analytics)) {
            $analytics = $this->disposalService->buildPreDisposalAnalyticsSnapshot($quizId);
        }

        $integritySummary = ['Clean' => 0, 'Low' => 0, 'Medium' => 0, 'High' => 0];
        foreach ($attempts as $a) {
            $lvl = (string) ($a['integrity_level'] ?? 'Clean');
            if (isset($integritySummary[$lvl])) {
                $integritySummary[$lvl]++;
            }
        }

        return [
            'success'           => true,
            'report_id'         => sprintf('RPT-PRELIMS-%d-%05d', date('Y'), $quizId),
            'generated_at'      => date('Y-m-d H:i:s'),
            'quiz'              => $quiz,
            'prelims_attendance'=> $attendance,
            'attempts'          => $attempts,
            'shortlist'         => $shortlist,
            'selected_finalists'=> $selected,
            'analytics'         => $analytics,
            'integrity_summary' => $integritySummary,
            'violations'        => $violations,
            'is_disposed'       => ($quiz['status'] === 'Disposed'),
        ];
    }

    /**
     * Build Unified Main Event Report (Prelims Funnel -> Main Event Attendance -> Final Judging & Winners).
     */
    public function getMainEventReportData(int $symposiumEventId): array
    {
        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Event not found.'];
        }

        $prelimsQuiz       = $this->quizModel->findByEventAndStage($symposiumEventId);
        $prelimsAttendance = $this->attendanceModel->getSummaryByEvent($symposiumEventId, 'Prelims');
        $mainAttendance    = $this->attendanceModel->getSummaryByEvent($symposiumEventId, 'Main');
        $finalists         = $prelimsQuiz ? $this->shortlistModel->getByQuiz((int) $prelimsQuiz['quiz_id'], 'Selected') : [];

        // Fetch Main Event published/evaluated results
        $resStmt = $this->db->prepare("
            SELECT r.*, a.application_no, a.application_type,
                   s.full_name AS student_name, s.register_number,
                   COALESCE(s.external_department, d.short_name, d.department_name) AS department_name,
                   COALESCE(c.college_name, s.college_name, 'Home Institution') AS college_name
            FROM competition_results r
            INNER JOIN applications a ON a.application_id = r.application_id
            INNER JOIN students s     ON s.student_id = a.student_id
            LEFT JOIN departments d   ON d.department_id = s.department_id
            LEFT JOIN colleges c      ON c.college_id = s.college_id
            WHERE r.symposium_event_id = :eid
            ORDER BY COALESCE(r.rank_position, 9999) ASC, r.total_score DESC
        ");
        $resStmt->execute(['eid' => $symposiumEventId]);
        $finalResults = $resStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fetch Main Event participant roster with attendance status
        $mainRoster = $this->attendanceModel->getParticipantsForEventAttendance($symposiumEventId, 0, 'Main');

        return [
            'success'            => true,
            'report_id'          => sprintf('RPT-MAIN-%d-%05d', date('Y'), $symposiumEventId),
            'generated_at'       => date('Y-m-d H:i:s'),
            'event'              => $event,
            'prelims_quiz'       => $prelimsQuiz ?: null,
            'prelims_attendance' => $prelimsAttendance,
            'prelims_finalists'  => $finalists,
            'main_attendance'    => $mainAttendance,
            'main_roster'        => $mainRoster,
            'final_results'      => $finalResults,
        ];
    }

    /**
     * Render any HTML report string to PDF bytes via Dompdf.
     */
    public function renderHtmlToPdfBytes(string $html, string $paper = 'A4', string $orientation = 'portrait'): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * Build CSV string for Prelims Merit List & Promoted Finalists.
     */
    public function buildPrelimsCsv(array $reportData): string
    {
        $fh = fopen('php://temp', 'r+b');
        fputcsv($fh, [
            'Rank', 'Register Number', 'Student Name', 'Department', 'College',
            'Application No', 'Type', 'Correct', 'Wrong', 'Unanswered',
            'Score', 'Max Score', 'Percentage (%)', 'Time Taken (s)',
            'Violations', 'Integrity', 'Attempt Status', 'Finals Selection',
        ], ',', '"', '');

        foreach ($reportData['attempts'] ?? [] as $a) {
            fputcsv($fh, [
                $a['rank_position'] ?? '-',
                $a['register_number'] ?? '',
                $a['student_name'] ?? '',
                $a['department_name'] ?? '',
                $a['college_name'] ?? '',
                $a['application_no'] ?? '',
                $a['application_type'] ?? 'Individual',
                $a['correct_count'] ?? 0,
                $a['wrong_count'] ?? 0,
                $a['unanswered_count'] ?? 0,
                $a['raw_score'] ?? 0,
                $a['max_score'] ?? 0,
                $a['percentage'] ?? 0,
                $a['time_taken_seconds'] ?? 0,
                $a['violation_count'] ?? 0,
                $a['integrity_level'] ?? 'Clean',
                $a['status'] ?? '',
                $a['selection_status'] ?? 'Not_Selected',
            ], ',', '"', '');
        }

        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);
        return $csv;
    }
}
