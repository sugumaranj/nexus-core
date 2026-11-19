<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\AttendanceRecordModel;
use App\Models\CompetitionStageModel;
use App\Models\FacultyAssignmentModel;
use App\Models\Prelims\PrelimsAttemptModel;
use App\Models\Prelims\PrelimsQuestionModel;
use App\Models\Prelims\PrelimsQuizModel;
use App\Models\Prelims\PrelimsShortlistModel;
use App\Models\SymposiumEventModel;
use App\Services\Prelims\Pdf\Extractors\SmalotPdfExtractor;
use App\Services\Prelims\PrelimsQuizService;
use App\Services\Prelims\QuizAttemptService;
use App\Services\Prelims\QuizDisposalService;
use App\Services\Prelims\ShortlistPromotionService;

final class PrelimsFicController extends BaseController
{
    private PrelimsQuizModel       $quizModel;
    private PrelimsQuestionModel   $questionModel;
    private PrelimsAttemptModel    $attemptModel;
    private PrelimsShortlistModel  $shortlistModel;
    private SymposiumEventModel    $eventModel;
    private CompetitionStageModel  $stageModel;
    private FacultyAssignmentModel $facultyModel;
    private PrelimsQuizService     $quizService;
    private QuizAttemptService     $attemptService;
    private ShortlistPromotionService $promotionService;
    private QuizDisposalService    $disposalService;

    public function __construct()
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Admin', 'Principal', 'HOD', 'Staff Coordinator', 'Staff');

        $this->quizModel        = new PrelimsQuizModel();
        $this->questionModel    = new PrelimsQuestionModel();
        $this->attemptModel     = new PrelimsAttemptModel();
        $this->shortlistModel   = new PrelimsShortlistModel();
        $this->eventModel       = new SymposiumEventModel();
        $this->stageModel       = new CompetitionStageModel();
        $this->facultyModel     = new FacultyAssignmentModel();
        $this->quizService      = new PrelimsQuizService();
        $this->attemptService   = new QuizAttemptService();
        $this->promotionService = new ShortlistPromotionService();
        $this->disposalService  = new QuizDisposalService();
    }

    private function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
        exit;
    }

    private function canManageEvent(int $eventId): bool
    {
        $user = $this->user();
        $role = (string) ($user['role'] ?? '');
        $uid  = (int) ($user['user_id'] ?? 0);
        if (in_array($role, ['Admin', 'Staff Coordinator', 'HOD'], true)) {
            return true;
        }
        return $this->facultyModel->isAssigned($eventId, $uid);
    }

    private function csrfToken(): string
    {
        $uid = (int) ($this->user()['user_id'] ?? 0);
        $tok = substr(hash_hmac('sha256', (string) $uid, 'NexusCore_CSRF_Salt_2026'), 0, 32);
        Session::set('csrf_token', $tok);
        return $tok;
    }

    private function verifyCsrf(?string $submitted = null): bool
    {
        $sub = $submitted ?? (string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $exp = $this->csrfToken();
        $sto = (string) Session::get('csrf_token', '');
        return ($sub !== '' && hash_equals($exp, $sub)) || ($sto !== '' && $sub !== '' && hash_equals($sto, $sub));
    }

    /**
     * GET /fic/prelims/manage?event_id=&stage_id=
     */
    public function manage(): void
    {
        $eventId = (int) ($_GET['event_id'] ?? 0);
        $stageId = !empty($_GET['stage_id']) ? (int) $_GET['stage_id'] : null;
        $quizId  = (int) ($_GET['quiz_id'] ?? 0);

        if ($quizId > 0 && $eventId === 0) {
            $qRow = $this->quizModel->findById($quizId);
            if ($qRow) {
                $eventId = (int) $qRow['symposium_event_id'];
                $stageId = !empty($qRow['stage_id']) ? (int) $qRow['stage_id'] : null;
            }
        }

        if ($eventId <= 0 || !$this->canManageEvent($eventId)) {
            $this->error('Unauthorized or invalid event.');
            $this->redirect('/my/assigned-events');
        }

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            $this->error('Event not found.');
            $this->redirect('/my/assigned-events');
        }

        $quiz    = $quizId > 0
            ? $this->quizModel->findById($quizId)
            : $this->quizModel->findByEventAndStage($eventId, $stageId);
        $sources = $quiz ? $this->quizModel->getSourcesByQuiz((int) $quiz['quiz_id']) : [];
        $topics  = $quiz ? $this->quizModel->getTopicsByQuiz((int) $quiz['quiz_id']) : [];
        $pool    = $quiz ? $this->questionModel->getPoolSummary((int) $quiz['quiz_id']) : ['total' => 0, 'approved' => 0, 'pending' => 0, 'missing_answers' => 0];
        $audit   = $quiz ? $this->quizService->getPoolAudit((int) $quiz['quiz_id']) : ['ok' => false, 'warnings' => []];

        $attModel   = new AttendanceRecordModel();
        $prelimsAtt = $attModel->getSummaryByEvent($eventId, 'Prelims');

        $this->render('fic.prelims.manage', [
            'pageTitle'  => 'Prelims Quiz Setup — ' . ($event['event_name'] ?? ''),
            'event'      => $event,
            'stageId'    => $stageId,
            'quiz'       => $quiz ?: null,
            'sources'    => $sources,
            'topics'     => $topics,
            'pool'       => $pool,
            'poolAudit'  => $audit,
            'prelimsAtt' => $prelimsAtt,
            'csrfToken'  => $this->csrfToken(),
        ]);
    }

    /**
     * POST /fic/prelims/save-config
     */
    public function saveConfig(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }

        $eventId = (int) ($_POST['symposium_event_id'] ?? 0);
        $stageId = !empty($_POST['stage_id']) ? (int) $_POST['stage_id'] : null;

        if ($eventId <= 0 || !$this->canManageEvent($eventId)) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $res = $this->quizService->saveQuizConfig($eventId, $stageId, $_POST, (int) $this->user()['user_id']);
        $this->json($res, $res['success'] ? 200 : 400);
    }

    /**
     * POST /fic/prelims/upload-pdf
     * Supports uploading one or multiple PDFs in a single request (binary validated).
     */
    public function uploadPdf(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }

        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        $quiz   = $this->quizModel->findById($quizId);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Quiz not found or access denied.'], 403);
        }

        if (empty($_FILES['pdfs'])) {
            $this->json(['success' => false, 'message' => 'No PDF file uploaded.'], 400);
        }

        $files   = $_FILES['pdfs'];
        $names   = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmps    = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $errors  = is_array($files['error']) ? $files['error'] : [$files['error']];
        $uid     = (int) $this->user()['user_id'];
        $results = [];

        foreach ($names as $i => $origName) {
            if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $results[] = [
                    'filename' => (string) $origName,
                    'success'  => false,
                    'message'  => 'File upload error code: ' . ($errors[$i] ?? -1),
                ];
                continue;
            }
            $res = $this->quizService->ingestUploadedPdf($quizId, (string) $tmps[$i], (string) $origName, $uid);
            $res['filename'] = (string) $origName;
            if (!empty($res['needs_ocr']) && !empty($res['source_id'])) {
                $res['pdf_stream_url'] = base_url('/fic/prelims/source-pdf?source_id=' . (int) $res['source_id']);
            }
            $results[] = $res;
        }

        $this->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    /**
     * GET /fic/prelims/source-pdf?source_id=
     * Authenticated binary PDF stream for the FIC's local project-folder browser OCR engine.
     */
    public function streamSourcePdf(): void
    {
        $sourceId = (int) ($_GET['source_id'] ?? 0);
        $source   = $this->quizModel->findSourceById($sourceId);
        if (!$source) {
            http_response_code(404);
            exit('Source not found.');
        }

        $quiz = $this->quizModel->findById((int) $source['quiz_id']);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            http_response_code(403);
            exit('Forbidden.');
        }

        $path = (string) ($source['stored_path'] ?? '');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            http_response_code(404);
            exit('PDF binary no longer exists.');
        }

        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=300');
        readfile($path);
        exit;
    }

    /**
     * POST /fic/prelims/submit-ocr-text
     * Receives OCR page text extracted locally in the FIC's browser via pdf.js + tesseract.js.
     */
    public function submitOcrText(): void
    {
        $raw = json_decode(file_get_contents('php://input') ?: '{}', true);
        if (!is_array($raw) || !$this->verifyCsrf((string) ($raw['csrf_token'] ?? ''))) {
            $this->json(['success' => false, 'message' => 'Invalid security token or payload.'], 403);
        }

        $quizId    = (int) ($raw['quiz_id'] ?? 0);
        $sourceId  = (int) ($raw['source_id'] ?? 0);
        $pageTexts = is_array($raw['pages'] ?? null) ? $raw['pages'] : [];

        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $res = $this->quizService->ingestOcrPages($quizId, $sourceId, $pageTexts, (int) $this->user()['user_id']);
        $this->json($res, $res['success'] ? 200 : 400);
    }

    /**
     * POST /fic/prelims/upload-answer-key
     */
    public function uploadAnswerKey(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }

        $quizId   = (int) ($_POST['quiz_id'] ?? 0);
        $sourceId = (int) ($_POST['source_id'] ?? 0);
        $keyText  = trim((string) ($_POST['answer_key_text'] ?? ''));

        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        // Optional answer-key PDF upload
        if ($keyText === '' && !empty($_FILES['answer_key_pdf']['tmp_name']) && ($_FILES['answer_key_pdf']['error'] ?? 1) === UPLOAD_ERR_OK) {
            try {
                $extractor = new SmalotPdfExtractor();
                $doc       = $extractor->extractFromBinary((string) $_FILES['answer_key_pdf']['tmp_name']);
                $keyText   = $doc->rawText;
            } catch (\Throwable $e) {
                $this->json(['success' => false, 'message' => 'Failed to read answer key PDF: ' . $e->getMessage()], 400);
            }
        }

        $res = $this->quizService->applySeparateAnswerKey($quizId, $sourceId, $keyText, (int) $this->user()['user_id']);
        $this->json($res, $res['success'] ? 200 : 400);
    }

    /**
     * POST /fic/prelims/source/delete
     */
    public function deleteSource(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }
        $sourceId = (int) ($_POST['source_id'] ?? 0);
        $source   = $this->quizModel->findSourceById($sourceId);
        if (!$source) {
            $this->json(['success' => false, 'message' => 'Source not found.'], 404);
        }
        $quiz = $this->quizModel->findById((int) $source['quiz_id']);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }
        if (in_array($quiz['status'], ['Live', 'Closed', 'Finalized', 'Disposed'], true)) {
            $this->json(['success' => false, 'message' => 'Cannot remove sources after quiz goes Live.'], 400);
        }

        $path = (string) ($source['stored_path'] ?? '');
        $this->quizModel->deleteSource($sourceId);
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
        $this->json(['success' => true, 'message' => 'PDF source and its questions removed.']);
    }

    /**
     * GET /fic/prelims/review?quiz_id=&source_id=
     */
    public function review(): void
    {
        $quizId   = (int) ($_GET['quiz_id'] ?? 0);
        $sourceId = !empty($_GET['source_id']) ? (int) $_GET['source_id'] : null;
        $quiz     = $this->quizModel->findById($quizId);

        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->error('Unauthorized or quiz not found.');
            $this->redirect('/my/assigned-events');
        }

        $sources   = $this->quizModel->getSourcesByQuiz($quizId);
        $topics    = $this->quizModel->getTopicsByQuiz($quizId);
        $questions = $this->questionModel->getQuestionsWithOptionsForQuiz($quizId, $sourceId);

        $this->render('fic.prelims.review', [
            'pageTitle'        => 'Question Verification Workbench — ' . ($quiz['title'] ?? ''),
            'quiz'             => $quiz,
            'sources'          => $sources,
            'topics'           => $topics,
            'questions'        => $questions,
            'selectedSourceId' => $sourceId,
            'csrfToken'        => $this->csrfToken(),
        ]);
    }

    /**
     * POST /fic/prelims/question/save
     */
    public function saveQuestion(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }

        $quizId       = (int) ($_POST['quiz_id'] ?? 0);
        $sourceId     = (int) ($_POST['source_id'] ?? 0);
        $topicId      = (int) ($_POST['topic_id'] ?? 0);
        $newTopicName = trim((string) ($_POST['new_topic_name'] ?? ''));
        $questionId   = !empty($_POST['question_id']) ? (int) $_POST['question_id'] : null;
        $qText        = trim((string) ($_POST['question_text'] ?? ''));
        $correctLabel = strtoupper(trim((string) ($_POST['correct_label'] ?? 'A')));
        $optionsRaw   = $_POST['options'] ?? [];

        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        if ($qText === '' || !is_array($optionsRaw) || count(array_filter($optionsRaw, static fn($v) => trim((string) $v) !== '')) < 2) {
            $this->json(['success' => false, 'message' => 'Question text and at least 2 options are required.'], 400);
        }

        // If manual question and no source yet, create a Manual source container
        if ($sourceId <= 0) {
            $sources = $this->quizModel->getSourcesByQuiz($quizId);
            if (!empty($sources)) {
                $sourceId = (int) $sources[0]['source_id'];
            } else {
                $sourceId = $this->quizModel->createSource([
                    'quiz_id'           => $quizId,
                    'original_filename' => 'Manual Question Bank',
                    'stored_path'       => null,
                    'file_size_bytes'   => 0,
                    'file_sha256'       => hash('sha256', 'manual_' . $quizId . '_' . microtime(true)),
                    'page_count'        => 1,
                    'extraction_method' => 'manual',
                    'extraction_status' => 'Approved',
                    'uploaded_by'       => (int) $this->user()['user_id'],
                ]);
            }
        }

        if ($newTopicName !== '') {
            $topicId = $this->quizModel->findOrCreateTopic($quizId, $sourceId, $newTopicName);
        } elseif ($topicId <= 0) {
            $topicId = $this->quizModel->findOrCreateTopic($quizId, $sourceId, 'General');
        }

        $savedId = $this->questionModel->saveQuestionWithOptions(
            $quizId,
            $sourceId,
            $topicId,
            $questionId,
            $qText,
            $optionsRaw,
            $correctLabel,
            'Approved'
        );

        $this->json(['success' => true, 'question_id' => $savedId, 'message' => 'Question saved and approved.']);
    }

    /**
     * POST /fic/prelims/question/delete
     */
    public function deleteQuestion(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }
        $quizId     = (int) ($_POST['quiz_id'] ?? 0);
        $questionId = (int) ($_POST['question_id'] ?? 0);
        $quiz       = $this->quizModel->findById($quizId);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }
        $this->questionModel->deleteQuestion($quizId, $questionId);
        $this->json(['success' => true, 'message' => 'Question deleted.']);
    }

    /**
     * POST /fic/prelims/source/approve
     */
    public function approveSource(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }
        $quizId   = (int) ($_POST['quiz_id'] ?? 0);
        $sourceId = (int) ($_POST['source_id'] ?? 0);
        $quiz     = $this->quizModel->findById($quizId);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }
        $cnt = $this->questionModel->approveAllResolvedForSource($quizId, $sourceId);
        $this->json(['success' => true, 'approved' => $cnt, 'message' => "Approved {$cnt} resolved question(s) in this PDF."]);
    }

    /**
     * POST /fic/prelims/status
     */
    public function changeStatus(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? ''));
        $quiz   = $this->quizModel->findById($quizId);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }
        $res = $this->quizService->changeStatus($quizId, $status, (int) $this->user()['user_id']);
        $this->json($res, $res['success'] ? 200 : 400);
    }

    /**
     * GET /fic/prelims/monitor?quiz_id=
     */
    public function monitor(): void
    {
        $quizId = (int) ($_GET['quiz_id'] ?? 0);
        $quiz   = $this->quizModel->findById($quizId);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->error('Unauthorized or quiz not found.');
            $this->redirect('/my/assigned-events');
        }

        $this->attemptService->sweepExpiredAttempts($quizId);
        $attempts   = $this->attemptModel->getRankedAttemptsForQuiz($quizId);
        $violations = $this->attemptModel->getViolationsForQuiz($quizId, 50);
        $attSummary = (new AttendanceRecordModel())->getSummaryByEvent((int) $quiz['symposium_event_id'], 'Prelims');

        $this->render('fic.prelims.monitor', [
            'pageTitle'  => 'Live Prelims Monitor — ' . ($quiz['title'] ?? ''),
            'quiz'       => $quiz,
            'attempts'   => $attempts,
            'violations' => $violations,
            'attSummary' => $attSummary,
            'csrfToken'  => $this->csrfToken(),
        ]);
    }

    /**
     * GET /fic/prelims/monitor-data?quiz_id= (lightweight JSON poll)
     */
    public function monitorData(): void
    {
        $quizId = (int) ($_GET['quiz_id'] ?? 0);
        $quiz   = $this->quizModel->findById($quizId);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $this->attemptService->sweepExpiredAttempts($quizId);
        $attempts = $this->attemptModel->getRankedAttemptsForQuiz($quizId);

        $inProgress = 0;
        $submitted  = 0;
        $flagged    = 0;
        foreach ($attempts as $a) {
            if ($a['status'] === 'In_Progress') {
                $inProgress++;
            } else {
                $submitted++;
            }
            if ((int) $a['violation_count'] > 0) {
                $flagged++;
            }
        }

        $this->json([
            'success'     => true,
            'quiz_status' => $quiz['status'],
            'counts'      => [
                'total'       => count($attempts),
                'in_progress' => $inProgress,
                'submitted'   => $submitted,
                'flagged'     => $flagged,
            ],
            'attempts'    => $attempts,
        ]);
    }

    /**
     * POST /fic/prelims/force-submit
     */
    public function forceSubmit(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }
        $attemptId = (int) ($_POST['attempt_id'] ?? 0);
        $reason    = trim((string) ($_POST['reason'] ?? 'Force submitted by FIC'));
        $att       = $this->attemptModel->findById($attemptId);
        if (!$att || !$this->canManageEvent((int) $att['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }
        $res = $this->attemptService->forceSubmitByFic($attemptId, (int) $this->user()['user_id'], $reason);
        $this->json($res, $res['success'] ? 200 : 400);
    }

    /**
     * POST /fic/prelims/grant-reentry
     */
    public function grantReentry(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }
        $attemptId = (int) ($_POST['attempt_id'] ?? 0);
        $extraMin  = (int) ($_POST['extra_minutes'] ?? 0);
        $reason    = trim((string) ($_POST['reason'] ?? 'Verified lab PC crash'));
        $att       = $this->attemptModel->findById($attemptId);
        if (!$att || !$this->canManageEvent((int) $att['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }
        $res = $this->attemptService->grantEmergencyReentry($attemptId, (int) $this->user()['user_id'], $extraMin, $reason);
        $this->json($res, $res['success'] ? 200 : 400);
    }

    /**
     * POST /fic/prelims/reopen-attempt
     * Re-open a mistakenly auto-submitted / submitted attempt (keeping answers + new timer + Re-Entry Code).
     */
    public function reopenAttempt(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }
        $attemptId       = (int) ($_POST['attempt_id'] ?? 0);
        $resumeMin       = (int) ($_POST['resume_minutes'] ?? 10);
        $resetViolations = !isset($_POST['reset_violations']) || !empty($_POST['reset_violations']);
        $reason          = trim((string) ($_POST['reason'] ?? 'Technical issue / accidental auto-submit'));

        $att = $this->attemptModel->findById($attemptId);
        if (!$att || !$this->canManageEvent((int) $att['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $res = $this->attemptService->reopenAttemptByFic(
            $attemptId,
            (int) $this->user()['user_id'],
            $resumeMin,
            $resetViolations,
            $reason
        );
        $this->json($res, $res['success'] ? 200 : 400);
    }

    /**
     * POST /fic/prelims/reset-attempt
     * Audited full reset allowing the student to take a fresh shuffled attempt.
     */
    public function resetAttempt(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }
        $attemptId = (int) ($_POST['attempt_id'] ?? 0);
        $reason    = trim((string) ($_POST['reason'] ?? 'Technical failure — authorized retake'));

        $att = $this->attemptModel->findById($attemptId);
        if (!$att || !$this->canManageEvent((int) $att['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $res = $this->attemptService->resetAttemptForRetakeByFic(
            $attemptId,
            (int) $this->user()['user_id'],
            $reason
        );
        $this->json($res, $res['success'] ? 200 : 400);
    }

    /**
     * GET /fic/prelims/results?quiz_id=
     * Displays strictly automated ranking (NO manual FIC override!) & Top-N promotion controls.
     */
    public function results(): void
    {
        $quizId = (int) ($_GET['quiz_id'] ?? 0);
        $quiz   = $this->quizModel->findById($quizId);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->error('Unauthorized or quiz not found.');
            $this->redirect('/my/assigned-events');
        }

        $ranking   = $this->promotionService->computeAutomatedRanking($quizId);
        $shortlist = $this->shortlistModel->getByQuiz($quizId);

        $this->render('fic.prelims.results', [
            'pageTitle' => 'Prelims Results & Automated Selection — ' . ($quiz['title'] ?? ''),
            'quiz'      => $quiz,
            'ranking'   => $ranking,
            'shortlist' => $shortlist,
            'csrfToken' => $this->csrfToken(),
        ]);
    }

    /**
     * POST /fic/prelims/finalize-selection
     */
    public function finalizeSelection(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        $quiz   = $this->quizModel->findById($quizId);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }
        $res = $this->promotionService->finalizeAndPromote($quizId, (int) $this->user()['user_id']);
        $this->json($res, $res['success'] ? 200 : 400);
    }

    /**
     * POST /fic/prelims/dispose
     */
    public function dispose(): void
    {
        if (!$this->verifyCsrf()) {
            $this->json(['success' => false, 'message' => 'Invalid security token.'], 403);
        }
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        $quiz   = $this->quizModel->findById($quizId);
        if (!$quiz || !$this->canManageEvent((int) $quiz['symposium_event_id'])) {
            $this->json(['success' => false, 'message' => 'Access denied.'], 403);
        }
        $res = $this->disposalService->disposeQuizContent($quizId, (int) $this->user()['user_id']);
        $this->json($res, $res['success'] ? 200 : 400);
    }
}
