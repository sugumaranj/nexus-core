<?php

declare(strict_types=1);

namespace App\Models\Prelims;

use App\Models\BaseModel;
use App\Services\Prelims\Pdf\DTO\ParsedQuestionDTO;
use PDO;

final class PrelimsQuestionModel extends BaseModel
{
    /**
     * Bulk insert parsed questions and their normalized options inside a transaction.
     *
     * @param ParsedQuestionDTO[]   $questions
     * @param array<string, int>    $topicIdMap  topic_name => topic_id
     * @return array{inserted: int, approved: int, pending: int}
     */
    public function insertParsedQuestions(
        int $quizId,
        int $sourceId,
        array $questions,
        array $topicIdMap
    ): array {
        $inserted = 0;
        $approved = 0;
        $pending  = 0;

        $qStmt = $this->db->prepare("
            INSERT INTO prelims_questions (
                quiz_id, source_id, topic_id, source_question_no,
                question_text, question_hash, answer_confidence,
                answer_source, review_status, is_active
            ) VALUES (
                :quiz_id, :source_id, :topic_id, :source_question_no,
                :question_text, :question_hash, :answer_confidence,
                :answer_source, :review_status, 1
            )
        ");

        $optStmt = $this->db->prepare("
            INSERT INTO prelims_question_options (
                question_id, option_label, option_text, is_correct, was_bolded
            ) VALUES (
                :question_id, :option_label, :option_text, :is_correct, :was_bolded
            )
        ");

        $startedTx = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $startedTx = true;
        }

        try {
            $fallbackTopicId = (int) reset($topicIdMap);

            foreach ($questions as $q) {
                $topicId = $topicIdMap[$q->topicName] ?? $fallbackTopicId;
                $hasValidAnswer = ($q->correctLabel !== null && isset($q->options[$q->correctLabel]));
                $reviewStatus   = ($hasValidAnswer && !$q->isDuplicate && count($q->options) >= 2) ? 'Approved' : 'Pending';

                $qStmt->execute([
                    'quiz_id'            => $quizId,
                    'source_id'          => $sourceId,
                    'topic_id'           => $topicId,
                    'source_question_no' => $q->sourceQuestionNo > 0 ? $q->sourceQuestionNo : null,
                    'question_text'      => $q->questionText,
                    'question_hash'      => $q->questionHash,
                    'answer_confidence'  => $hasValidAnswer ? $q->answerConfidence : 'Missing',
                    'answer_source'      => $hasValidAnswer ? $q->answerSource : 'Manual',
                    'review_status'      => $reviewStatus,
                ]);

                $questionId = (int) $this->db->lastInsertId();
                foreach ($q->options as $label => $opt) {
                    $optStmt->execute([
                        'question_id'  => $questionId,
                        'option_label' => strtoupper(substr((string) $label, 0, 1)),
                        'option_text'  => (string) $opt['text'],
                        'is_correct'   => ($hasValidAnswer && strtoupper((string) $label) === $q->correctLabel) ? 1 : 0,
                        'was_bolded'   => !empty($opt['is_bold']) ? 1 : 0,
                    ]);
                }

                $inserted++;
                if ($reviewStatus === 'Approved') {
                    $approved++;
                } else {
                    $pending++;
                }
            }

            if ($startedTx) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return compact('inserted', 'approved', 'pending');
    }

    /**
     * Get all questions (with options) for FIC Review Workbench.
     */
    public function getQuestionsWithOptionsForQuiz(int $quizId, ?int $sourceId = null): array
    {
        $where = 'q.quiz_id = :qid AND q.is_active = 1';
        $params = ['qid' => $quizId];
        if ($sourceId !== null && $sourceId > 0) {
            $where .= ' AND q.source_id = :sid';
            $params['sid'] = $sourceId;
        }

        $sql = "
            SELECT q.*, t.topic_name, src.original_filename
            FROM prelims_questions q
            INNER JOIN prelims_quiz_topics t    ON t.topic_id = q.topic_id
            INNER JOIN prelims_quiz_sources src ON src.source_id = q.source_id
            WHERE {$where}
            ORDER BY q.source_id ASC, q.topic_id ASC, COALESCE(q.source_question_no, q.question_id) ASC, q.question_id ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($rows)) {
            return [];
        }

        $qIds = array_map(static fn(array $r): int => (int) $r['question_id'], $rows);
        $optMap = $this->getOptionsGroupedByQuestionIds($qIds);

        foreach ($rows as &$r) {
            $r['options'] = $optMap[(int) $r['question_id']] ?? [];
        }
        unset($r);

        return $rows;
    }

    /**
     * Fetch approved question pool grouped by source_id -> topic_id for stratified sampling.
     *
     * @return array<int, array<int, array<int, array{question_id: int, source_id: int, topic_id: int, option_ids: int[]}>>>
     */
    public function getApprovedPoolBuckets(int $quizId): array
    {
        $sql = "
            SELECT q.question_id, q.source_id, q.topic_id
            FROM prelims_questions q
            INNER JOIN prelims_quiz_sources src ON src.source_id = q.source_id AND src.is_active = 1
            INNER JOIN prelims_quiz_topics t    ON t.topic_id = q.topic_id AND t.is_active = 1
            WHERE q.quiz_id = :qid
              AND q.is_active = 1
              AND q.review_status = 'Approved'
              AND EXISTS (
                  SELECT 1 FROM prelims_question_options o
                  WHERE o.question_id = q.question_id AND o.is_correct = 1
              )
            ORDER BY q.source_id ASC, q.topic_id ASC, q.question_id ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['qid' => $quizId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (empty($rows)) {
            return [];
        }

        $qIds = array_map(static fn(array $r): int => (int) $r['question_id'], $rows);
        $optMap = $this->getOptionsGroupedByQuestionIds($qIds);

        $buckets = [];
        foreach ($rows as $r) {
            $qid = (int) $r['question_id'];
            $sid = (int) $r['source_id'];
            $tid = (int) $r['topic_id'];
            $opts = $optMap[$qid] ?? [];
            if (count($opts) < 2) {
                continue;
            }
            $buckets[$sid][$tid][] = [
                'question_id' => $qid,
                'source_id'   => $sid,
                'topic_id'    => $tid,
                'option_ids'  => array_map(static fn(array $o): int => (int) $o['option_id'], $opts),
            ];
        }

        return $buckets;
    }

    /**
     * Save or update a single question & its options from FIC Review Workbench.
     */
    public function saveQuestionWithOptions(
        int $quizId,
        int $sourceId,
        int $topicId,
        ?int $questionId,
        string $questionText,
        array $options,
        string $correctLabel,
        string $reviewStatus = 'Approved'
    ): int {
        $hash = ParsedQuestionDTO::computeHash($questionText);
        $correctUpper = strtoupper(trim($correctLabel));

        $this->db->beginTransaction();
        try {
            if ($questionId !== null && $questionId > 0) {
                $stmt = $this->db->prepare("
                    UPDATE prelims_questions
                    SET topic_id = :tid,
                        question_text = :txt,
                        question_hash = :hash,
                        answer_confidence = 'Manual',
                        answer_source = 'Manual',
                        review_status = :rs
                    WHERE question_id = :qid AND quiz_id = :quiz_id
                ");
                $stmt->execute([
                    'tid'     => $topicId,
                    'txt'     => trim($questionText),
                    'hash'    => $hash,
                    'rs'      => $reviewStatus,
                    'qid'     => $questionId,
                    'quiz_id' => $quizId,
                ]);

                $del = $this->db->prepare("DELETE FROM prelims_question_options WHERE question_id = :qid");
                $del->execute(['qid' => $questionId]);
                $targetId = $questionId;
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO prelims_questions (
                        quiz_id, source_id, topic_id, question_text, question_hash,
                        answer_confidence, answer_source, review_status, is_active
                    ) VALUES (
                        :quiz_id, :sid, :tid, :txt, :hash,
                        'Manual', 'Manual', :rs, 1
                    )
                ");
                $stmt->execute([
                    'quiz_id' => $quizId,
                    'sid'     => $sourceId,
                    'tid'     => $topicId,
                    'txt'     => trim($questionText),
                    'hash'    => $hash,
                    'rs'      => $reviewStatus,
                ]);
                $targetId = (int) $this->db->lastInsertId();
            }

            $optStmt = $this->db->prepare("
                INSERT INTO prelims_question_options (question_id, option_label, option_text, is_correct, was_bolded)
                VALUES (:qid, :lbl, :txt, :corr, 0)
            ");
            foreach ($options as $lbl => $optText) {
                $clean = trim((string) $optText);
                if ($clean === '') {
                    continue;
                }
                $uLbl = strtoupper(substr((string) $lbl, 0, 1));
                $optStmt->execute([
                    'qid'  => $targetId,
                    'lbl'  => $uLbl,
                    'txt'  => $clean,
                    'corr' => $uLbl === $correctUpper ? 1 : 0,
                ]);
            }

            $this->db->commit();
            return $targetId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Apply a separate QNo => Label answer key to pending/missing questions of a source.
     *
     * @param array<int, string> $keyMap
     */
    public function applySeparateKeyMap(int $quizId, int $sourceId, array $keyMap): int
    {
        if (empty($keyMap)) {
            return 0;
        }
        $questions = $this->getQuestionsWithOptionsForQuiz($quizId, $sourceId);
        $updated = 0;

        $resetOpt = $this->db->prepare("UPDATE prelims_question_options SET is_correct = 0 WHERE question_id = :qid");
        $setOpt   = $this->db->prepare("UPDATE prelims_question_options SET is_correct = 1 WHERE question_id = :qid AND option_label = :lbl");
        $updQ     = $this->db->prepare("
            UPDATE prelims_questions
            SET answer_confidence = 'High', answer_source = 'Separate_Key', review_status = 'Approved'
            WHERE question_id = :qid
        ");

        $this->db->beginTransaction();
        try {
            foreach ($questions as $q) {
                $qNo = (int) ($q['source_question_no'] ?? 0);
                if ($qNo <= 0 || !isset($keyMap[$qNo])) {
                    continue;
                }
                $targetLbl = strtoupper($keyMap[$qNo]);
                $hasLbl = false;
                foreach ($q['options'] as $opt) {
                    if (strtoupper((string) $opt['option_label']) === $targetLbl) {
                        $hasLbl = true;
                        break;
                    }
                }
                if (!$hasLbl) {
                    continue;
                }

                $qid = (int) $q['question_id'];
                $resetOpt->execute(['qid' => $qid]);
                $setOpt->execute(['qid' => $qid, 'lbl' => $targetLbl]);
                $updQ->execute(['qid' => $qid]);
                $updated++;
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $updated;
    }

    public function deleteQuestion(int $quizId, int $questionId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM prelims_questions WHERE question_id = :qid AND quiz_id = :quiz_id");
        return $stmt->execute(['qid' => $questionId, 'quiz_id' => $quizId]);
    }

    public function approveAllResolvedForSource(int $quizId, int $sourceId): int
    {
        $stmt = $this->db->prepare("
            UPDATE prelims_questions q
            SET q.review_status = 'Approved'
            WHERE q.quiz_id = :qid
              AND q.source_id = :sid
              AND q.is_active = 1
              AND EXISTS (
                  SELECT 1 FROM prelims_question_options o
                  WHERE o.question_id = q.question_id AND o.is_correct = 1
              )
        ");
        $stmt->execute(['qid' => $quizId, 'sid' => $sourceId]);
        $count = $stmt->rowCount();

        $srcStmt = $this->db->prepare("
            UPDATE prelims_quiz_sources SET extraction_status = 'Approved'
            WHERE source_id = :sid AND quiz_id = :qid
        ");
        $srcStmt->execute(['sid' => $sourceId, 'qid' => $quizId]);

        return $count;
    }

    public function getPoolSummary(int $quizId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(review_status = 'Approved') AS approved,
                SUM(review_status = 'Pending') AS pending,
                SUM(answer_confidence = 'Missing') AS missing_answers
            FROM prelims_questions
            WHERE quiz_id = :qid AND is_active = 1
        ");
        $stmt->execute(['qid' => $quizId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total'           => (int) ($row['total'] ?? 0),
            'approved'        => (int) ($row['approved'] ?? 0),
            'pending'         => (int) ($row['pending'] ?? 0),
            'missing_answers' => (int) ($row['missing_answers'] ?? 0),
        ];
    }

    /**
     * @param int[] $questionIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function getOptionsGroupedByQuestionIds(array $questionIds): array
    {
        if (empty($questionIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
        $stmt = $this->db->prepare("
            SELECT * FROM prelims_question_options
            WHERE question_id IN ({$placeholders})
            ORDER BY option_label ASC
        ");
        $stmt->execute(array_values($questionIds));

        $grouped = [];
        while ($opt = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $grouped[(int) $opt['question_id']][] = $opt;
        }
        return $grouped;
    }
}
