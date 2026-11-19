<?php

declare(strict_types=1);

namespace App\Services\Prelims;

use App\Database\Database;
use App\Models\AuditLogModel;
use App\Models\CompetitionStageModel;
use App\Models\Prelims\PrelimsQuestionModel;
use App\Models\Prelims\PrelimsQuizModel;
use App\Models\SymposiumEventModel;
use App\Services\Prelims\Pdf\Extractors\BinaryFileValidator;
use App\Services\Prelims\Pdf\PdfQuestionImportPipeline;
use PDO;

final class PrelimsQuizService
{
    public const PDF_STORAGE_ROOT = __DIR__ . '/../../../storage/prelims_pdfs';

    private PDO                       $db;
    private PrelimsQuizModel          $quizModel;
    private PrelimsQuestionModel      $questionModel;
    private SymposiumEventModel       $eventModel;
    private CompetitionStageModel     $stageModel;
    private PdfQuestionImportPipeline $pdfPipeline;
    private BinaryFileValidator       $binaryValidator;
    private QuestionSamplerService    $sampler;
    private AuditLogModel             $auditModel;

    public function __construct()
    {
        $this->db              = Database::getConnection();
        $this->quizModel       = new PrelimsQuizModel();
        $this->questionModel   = new PrelimsQuestionModel();
        $this->eventModel      = new SymposiumEventModel();
        $this->stageModel      = new CompetitionStageModel();
        $this->pdfPipeline     = new PdfQuestionImportPipeline();
        $this->binaryValidator = new BinaryFileValidator();
        $this->sampler         = new QuestionSamplerService();
        $this->auditModel      = new AuditLogModel();
    }

    /**
     * Create or update a prelims quiz configuration for a symposium event/stage.
     */
    public function saveQuizConfig(int $symposiumEventId, ?int $stageId, array $input, int $userId): array
    {
        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Symposium event not found.'];
        }

        $questionsPerStudent = max(1, min(300, (int) ($input['questions_per_student'] ?? 20)));
        $timerMode           = ($input['timer_mode'] ?? 'per_question') === 'total_minutes' ? 'total_minutes' : 'per_question';
        $secondsPerQuestion  = max(10, min(600, (int) ($input['seconds_per_question'] ?? 60)));

        if ($timerMode === 'per_question') {
            $durationSeconds = $questionsPerStudent * $secondsPerQuestion;
        } else {
            $totalMinutes    = max(1, min(360, (int) ($input['total_minutes'] ?? 20)));
            $durationSeconds = $totalMinutes * 60;
            $secondsPerQuestion = max(10, (int) round($durationSeconds / $questionsPerStudent));
        }

        $marksPerQ       = max(0.25, min(100.0, (float) ($input['marks_per_question'] ?? 1.00)));
        $negativeMarks   = max(0.0, min($marksPerQ, (float) ($input['negative_marks'] ?? 0.00)));
        $topN            = max(1, min(5000, (int) ($input['top_n_select'] ?? 10)));
        $minPassPct      = max(0.0, min(100.0, (float) ($input['min_pass_percentage'] ?? 0.00)));
        $violAutoSubmit  = !empty($input['violation_auto_submit']) ? 1 : 0;
        $maxViolations   = max(1, min(20, (int) ($input['max_violations'] ?? 3)));
        $reqFullscreen   = isset($input['require_fullscreen']) ? (!empty($input['require_fullscreen']) ? 1 : 0) : 1;
        $blockMobile     = isset($input['block_mobile']) ? (!empty($input['block_mobile']) ? 1 : 0) : 1;

        // Participation rules: Individual for Individual events (e.g., Debugging),
        // All_Members_Best (Best Member Score) for Team events (e.g., Team Quiz).
        $participationType = (string) ($event['participation_type'] ?? 'Individual');
        $teamScoringMode   = ($participationType === 'Team') ? 'All_Members_Best' : 'Individual';
        if ($participationType === 'Both' && ($input['team_scoring_mode'] ?? '') === 'All_Members_Best') {
            $teamScoringMode = 'All_Members_Best';
        }

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $title = ($event['event_name'] ?? 'Event') . ' — Digital Prelims Quiz';
        }

        $windowStart = $this->normalizeDatetime($input['window_start'] ?? null);
        $windowEnd   = $this->normalizeDatetime($input['window_end'] ?? null);
        if ($windowStart !== null && $windowEnd !== null && $windowEnd <= $windowStart) {
            return ['success' => false, 'message' => 'Quiz window end time must be after start time.'];
        }

        if ($stageId === null || $stageId <= 0) {
            $digitalStage = $this->stageModel->getDigitalPrelimsStage($symposiumEventId);
            $stageId      = $digitalStage ? (int) $digitalStage['stage_id'] : null;
        }

        $payload = [
            'symposium_event_id'    => $symposiumEventId,
            'stage_id'              => $stageId,
            'title'                 => mb_substr($title, 0, 180),
            'instructions'          => trim((string) ($input['instructions'] ?? '')) ?: null,
            'questions_per_student' => $questionsPerStudent,
            'timer_mode'            => $timerMode,
            'seconds_per_question'  => $secondsPerQuestion,
            'duration_seconds'      => $durationSeconds,
            'marks_per_question'    => $marksPerQ,
            'negative_marks'        => $negativeMarks,
            'top_n_select'          => $topN,
            'min_pass_percentage'   => $minPassPct,
            'team_scoring_mode'     => $teamScoringMode,
            'violation_auto_submit' => $violAutoSubmit,
            'max_violations'        => $maxViolations,
            'require_fullscreen'    => $reqFullscreen,
            'block_mobile'          => $blockMobile,
            'window_start'          => $windowStart,
            'window_end'            => $windowEnd,
            'created_by'            => $userId,
        ];

        $existing = !empty($input['quiz_id'])
            ? $this->quizModel->findById((int) $input['quiz_id'])
            : $this->quizModel->findByEventAndStage($symposiumEventId, $stageId);

        if ($existing) {
            if (in_array($existing['status'], ['Finalized', 'Disposed'], true)) {
                return ['success' => false, 'message' => 'Cannot edit configuration after quiz is finalized or disposed.'];
            }
            $quizId = (int) $existing['quiz_id'];
            $this->quizModel->updateConfig($quizId, $payload);
            $this->auditModel->log('PrelimsQuiz', $quizId, 'Config Updated', $userId, "Questions: {$questionsPerStudent}, Duration: {$durationSeconds}s, TopN: {$topN}");
            return ['success' => true, 'message' => 'Prelims quiz configuration updated.', 'quiz_id' => $quizId];
        }

        $quizId = $this->quizModel->create($payload);
        $this->auditModel->log('PrelimsQuiz', $quizId, 'Quiz Created', $userId, "Event #{$symposiumEventId}");
        return ['success' => true, 'message' => 'Prelims quiz created successfully.', 'quiz_id' => $quizId];
    }

    /**
     * Ingest an uploaded PDF binary (read in binary mode, validated via %PDF- signature,
     * parsed via pure-PHP smalot/pdfparser; flags `needs_ocr` if scanned/image-only).
     */
    public function ingestUploadedPdf(int $quizId, string $tmpFilePath, string $originalName, int $userId): array
    {
        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz) {
            return ['success' => false, 'message' => 'Quiz not found.'];
        }
        if (in_array($quiz['status'], ['Live', 'Closed', 'Finalized', 'Disposed'], true)) {
            return ['success' => false, 'message' => 'Cannot upload new PDFs while quiz is Live, Closed, Finalized, or Disposed.'];
        }

        // 1. Strict binary PDF validation
        $validation = $this->binaryValidator->validatePdf($tmpFilePath);
        if (!$validation['valid']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        $sha256 = (string) $validation['sha256'];
        $size   = (int) $validation['size'];

        if ($this->quizModel->findSourceBySha256($quizId, $sha256)) {
            return ['success' => false, 'message' => "This exact PDF ({$originalName}) has already been uploaded to this quiz."];
        }

        // 2. Store binary securely outside public webroot
        $dir = self::PDF_STORAGE_ROOT . DIRECTORY_SEPARATOR . $quizId;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return ['success' => false, 'message' => 'Failed to create private storage directory for PDFs.'];
        }

        $safeFileName = bin2hex(random_bytes(12)) . '.pdf';
        $destPath     = $dir . DIRECTORY_SEPARATOR . $safeFileName;
        $rawBytes     = $this->binaryValidator->readBinary($tmpFilePath);

        $out = fopen($destPath, 'wb');
        if ($out === false) {
            return ['success' => false, 'message' => 'Failed to store uploaded PDF binary.'];
        }
        fwrite($out, $rawBytes);
        fclose($out);

        // 3. Create source row
        $defaultTopic = $this->deriveDefaultTopicFromFilename($originalName);
        $sourceId = $this->quizModel->createSource([
            'quiz_id'           => $quizId,
            'original_filename' => mb_substr(basename($originalName), 0, 250),
            'stored_path'       => $destPath,
            'file_size_bytes'   => $size,
            'file_sha256'       => $sha256,
            'extraction_status' => 'Pending',
            'uploaded_by'       => $userId,
        ]);

        // 4. Pure-PHP typed PDF extraction (no OCR first)
        try {
            $parsed = $this->pdfPipeline->importFromBinaryPdf($destPath, $defaultTopic);
        } catch (\Throwable $e) {
            $this->quizModel->updateSourceExtraction($sourceId, [
                'page_count'           => 0,
                'extraction_method'    => 'smalot_php',
                'answer_strategy_used' => 'None',
                'extraction_status'    => 'Needs_OCR',
                'raw_warning_log'      => 'Typed PDF parser encountered error: ' . $e->getMessage() . '. Falling back to local browser OCR.',
            ]);
            return [
                'success'   => true,
                'needs_ocr' => true,
                'source_id' => $sourceId,
                'message'   => 'PDF requires local browser OCR extraction.',
            ];
        }

        if (!empty($parsed['needs_ocr'])) {
            $this->quizModel->updateSourceExtraction($sourceId, [
                'page_count'           => $parsed['page_count'],
                'extraction_method'    => 'smalot_php',
                'answer_strategy_used' => 'None',
                'extraction_status'    => 'Needs_OCR',
                'raw_warning_log'      => implode("\n", $parsed['warnings']),
            ]);
            return [
                'success'   => true,
                'needs_ocr' => true,
                'source_id' => $sourceId,
                'page_count'=> $parsed['page_count'],
                'message'   => 'Scanned/image PDF detected (low text density). Running self-contained local OCR...',
            ];
        }

        return $this->persistParsedImport($quizId, $sourceId, $parsed, $userId);
    }

    /**
     * Ingest page texts extracted by local project-folder browser OCR (pdf.js + tesseract.js).
     *
     * @param string[] $pageTexts
     */
    public function ingestOcrPages(int $quizId, int $sourceId, array $pageTexts, int $userId): array
    {
        $source = $this->quizModel->findSourceById($sourceId);
        if (!$source || (int) $source['quiz_id'] !== $quizId) {
            return ['success' => false, 'message' => 'PDF source not found for this quiz.'];
        }

        $defaultTopic = $this->deriveDefaultTopicFromFilename((string) $source['original_filename']);
        $parsed       = $this->pdfPipeline->importFromOcrPages($pageTexts, $defaultTopic);
        return $this->persistParsedImport($quizId, $sourceId, $parsed, $userId);
    }

    /**
     * Apply a separate answer key (raw text or uploaded key PDF) to a specific PDF source.
     */
    public function applySeparateAnswerKey(int $quizId, int $sourceId, string $rawKeyText, int $userId): array
    {
        $source = $this->quizModel->findSourceById($sourceId);
        if (!$source || (int) $source['quiz_id'] !== $quizId) {
            return ['success' => false, 'message' => 'PDF source not found.'];
        }

        $keyMap = $this->pdfPipeline->parseSeparateAnswerKeyText($rawKeyText);
        if (empty($keyMap)) {
            return ['success' => false, 'message' => 'No valid question-number to option mappings found in the answer key.'];
        }

        $updated = $this->questionModel->applySeparateKeyMap($quizId, $sourceId, $keyMap);
        $this->auditModel->log('PrelimsQuiz', $quizId, 'Separate Answer Key Applied', $userId, "Source #{$sourceId}, Matched {$updated} questions");

        return [
            'success' => true,
            'updated' => $updated,
            'message' => "Applied answer key successfully: matched {$updated} question(s).",
        ];
    }

    /**
     * Transition quiz lifecycle status with pre-publish quality & pool sufficiency checks.
     */
    public function changeStatus(int $quizId, string $targetStatus, int $userId): array
    {
        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz) {
            return ['success' => false, 'message' => 'Quiz not found.'];
        }

        $allowed = ['Draft', 'Published', 'Live', 'Paused', 'Closed'];
        if (!in_array($targetStatus, $allowed, true)) {
            return ['success' => false, 'message' => 'Invalid target status.'];
        }

        if (in_array($quiz['status'], ['Finalized', 'Disposed'], true)) {
            return ['success' => false, 'message' => 'Cannot change status after quiz is Finalized or Disposed.'];
        }

        if (in_array($targetStatus, ['Published', 'Live'], true)) {
            $buckets = $this->questionModel->getApprovedPoolBuckets($quizId);
            $audit   = $this->sampler->auditPoolSufficiency($buckets, (int) $quiz['questions_per_student']);
            if (!$audit['ok']) {
                return [
                    'success'  => false,
                    'message'  => implode(' ', $audit['warnings']),
                    'warnings' => $audit['warnings'],
                ];
            }
        }

        $this->quizModel->updateStatus($quizId, $targetStatus, $userId);
        $this->auditModel->log('PrelimsQuiz', $quizId, "Status -> {$targetStatus}", $userId);

        return [
            'success' => true,
            'status'  => $targetStatus,
            'message' => "Quiz status updated to {$targetStatus}.",
        ];
    }

    public function getPoolAudit(int $quizId): array
    {
        $quiz = $this->quizModel->findById($quizId);
        if (!$quiz) {
            return ['ok' => false, 'total_approved' => 0, 'source_count' => 0, 'topic_count' => 0, 'warnings' => ['Quiz not found.']];
        }
        $buckets = $this->questionModel->getApprovedPoolBuckets($quizId);
        return $this->sampler->auditPoolSufficiency($buckets, (int) $quiz['questions_per_student']);
    }

    private function persistParsedImport(int $quizId, int $sourceId, array $parsed, int $userId): array
    {
        $topicIdMap = [];
        $order      = 1;
        foreach ($parsed['topics'] as $tName) {
            $topicIdMap[$tName] = $this->quizModel->findOrCreateTopic($quizId, $sourceId, (string) $tName, $order++);
        }
        if (empty($topicIdMap)) {
            $topicIdMap['General'] = $this->quizModel->findOrCreateTopic($quizId, $sourceId, 'General', 1);
        }

        $counts = $this->questionModel->insertParsedQuestions($quizId, $sourceId, $parsed['questions'], $topicIdMap);

        $status = 'Extracted';
        if ($counts['inserted'] === 0 || $counts['pending'] > 0) {
            $status = 'Needs_Review';
        } else {
            $status = 'Approved';
        }

        $this->quizModel->updateSourceExtraction($sourceId, [
            'page_count'           => $parsed['page_count'],
            'extraction_method'    => $parsed['method'],
            'answer_strategy_used' => $parsed['dominant_strategy'],
            'extraction_status'    => $status,
            'raw_warning_log'      => !empty($parsed['warnings']) ? implode("\n", $parsed['warnings']) : null,
        ]);

        $this->auditModel->log(
            'PrelimsQuiz',
            $quizId,
            'PDF Extracted',
            $userId,
            "Source #{$sourceId} | Method: {$parsed['method']} | Inserted: {$counts['inserted']} | Approved: {$counts['approved']} | Strategy: {$parsed['dominant_strategy']}"
        );

        return [
            'success'              => true,
            'needs_ocr'            => false,
            'source_id'            => $sourceId,
            'method'               => $parsed['method'],
            'dominant_strategy'    => $parsed['dominant_strategy'],
            'inserted'             => $counts['inserted'],
            'approved'             => $counts['approved'],
            'pending'              => $counts['pending'],
            'topics_detected'      => count($topicIdMap),
            'warnings'             => $parsed['warnings'],
            'message'              => "Extracted {$counts['inserted']} question(s) across " . count($topicIdMap) . " topic(s) ({$counts['approved']} auto-verified, {$counts['pending']} need review).",
        ];
    }

    private function deriveDefaultTopicFromFilename(string $filename): string
    {
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $clean = trim(preg_replace('/[_\-]+/', ' ', $stem) ?? $stem);
        return $clean !== '' ? mb_substr($clean, 0, 120) : 'General';
    }

    private function normalizeDatetime(mixed $val): ?string
    {
        $str = trim((string) ($val ?? ''));
        if ($str === '') {
            return null;
        }
        $ts = strtotime($str);
        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }
}
