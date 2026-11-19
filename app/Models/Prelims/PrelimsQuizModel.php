<?php

declare(strict_types=1);

namespace App\Models\Prelims;

use App\Models\BaseModel;
use PDO;

final class PrelimsQuizModel extends BaseModel
{
    public function findById(int $quizId): array|false
    {
        $sql = "
            SELECT q.*,
                   se.event_name, se.event_code, se.participation_type, se.symposium_id,
                   se.event_date, se.status AS event_status, se.prelim_decision,
                   s.title AS symposium_title,
                   cs.stage_name, cs.stage_date, cs.start_time AS stage_start_time, cs.end_time AS stage_end_time
            FROM prelims_quizzes q
            INNER JOIN symposium_events se ON se.symposium_event_id = q.symposium_event_id
            INNER JOIN symposiums s        ON s.symposium_id = se.symposium_id
            LEFT JOIN competition_stages cs ON cs.stage_id = q.stage_id
            WHERE q.quiz_id = :qid
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['qid' => $quizId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByEventAndStage(int $symposiumEventId, ?int $stageId = null): array|false
    {
        if ($stageId !== null && $stageId > 0) {
            $stmt = $this->db->prepare("
                SELECT * FROM prelims_quizzes
                WHERE symposium_event_id = :eid AND stage_id = :sid
                LIMIT 1
            ");
            $stmt->execute(['eid' => $symposiumEventId, 'sid' => $stageId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $this->findById((int) $row['quiz_id']);
            }
        }

        $stmt = $this->db->prepare("
            SELECT quiz_id FROM prelims_quizzes
            WHERE symposium_event_id = :eid
            ORDER BY quiz_id DESC
            LIMIT 1
        ");
        $stmt->execute(['eid' => $symposiumEventId]);
        $qid = $stmt->fetchColumn();
        return $qid ? $this->findById((int) $qid) : false;
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO prelims_quizzes (
                symposium_event_id, stage_id, title, instructions,
                questions_per_student, timer_mode, seconds_per_question, duration_seconds,
                marks_per_question, negative_marks, top_n_select, min_pass_percentage,
                team_scoring_mode, violation_auto_submit, max_violations,
                require_fullscreen, block_mobile, window_start, window_end,
                status, created_by
            ) VALUES (
                :symposium_event_id, :stage_id, :title, :instructions,
                :questions_per_student, :timer_mode, :seconds_per_question, :duration_seconds,
                :marks_per_question, :negative_marks, :top_n_select, :min_pass_percentage,
                :team_scoring_mode, :violation_auto_submit, :max_violations,
                :require_fullscreen, :block_mobile, :window_start, :window_end,
                :status, :created_by
            )
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'symposium_event_id'    => (int) $data['symposium_event_id'],
            'stage_id'              => !empty($data['stage_id']) ? (int) $data['stage_id'] : null,
            'title'                 => (string) $data['title'],
            'instructions'          => $data['instructions'] ?? null,
            'questions_per_student' => (int) ($data['questions_per_student'] ?? 20),
            'timer_mode'            => (string) ($data['timer_mode'] ?? 'per_question'),
            'seconds_per_question'  => (int) ($data['seconds_per_question'] ?? 60),
            'duration_seconds'      => (int) ($data['duration_seconds'] ?? 1200),
            'marks_per_question'    => (float) ($data['marks_per_question'] ?? 1.00),
            'negative_marks'        => (float) ($data['negative_marks'] ?? 0.00),
            'top_n_select'          => (int) ($data['top_n_select'] ?? 10),
            'min_pass_percentage'   => (float) ($data['min_pass_percentage'] ?? 0.00),
            'team_scoring_mode'     => (string) ($data['team_scoring_mode'] ?? 'Individual'),
            'violation_auto_submit' => (int) ($data['violation_auto_submit'] ?? 1),
            'max_violations'        => (int) ($data['max_violations'] ?? 3),
            'require_fullscreen'    => (int) ($data['require_fullscreen'] ?? 1),
            'block_mobile'          => (int) ($data['block_mobile'] ?? 1),
            'window_start'          => !empty($data['window_start']) ? $data['window_start'] : null,
            'window_end'            => !empty($data['window_end']) ? $data['window_end'] : null,
            'status'                => (string) ($data['status'] ?? 'Draft'),
            'created_by'            => (int) $data['created_by'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateConfig(int $quizId, array $data): bool
    {
        $sql = "
            UPDATE prelims_quizzes
            SET title                 = :title,
                instructions          = :instructions,
                questions_per_student = :questions_per_student,
                timer_mode            = :timer_mode,
                seconds_per_question  = :seconds_per_question,
                duration_seconds      = :duration_seconds,
                marks_per_question    = :marks_per_question,
                negative_marks        = :negative_marks,
                top_n_select          = :top_n_select,
                min_pass_percentage   = :min_pass_percentage,
                team_scoring_mode     = :team_scoring_mode,
                violation_auto_submit = :violation_auto_submit,
                max_violations        = :max_violations,
                require_fullscreen    = :require_fullscreen,
                block_mobile          = :block_mobile,
                window_start          = :window_start,
                window_end            = :window_end
            WHERE quiz_id = :quiz_id
        ";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'quiz_id'               => $quizId,
            'title'                 => (string) $data['title'],
            'instructions'          => $data['instructions'] ?? null,
            'questions_per_student' => (int) $data['questions_per_student'],
            'timer_mode'            => (string) $data['timer_mode'],
            'seconds_per_question'  => (int) $data['seconds_per_question'],
            'duration_seconds'      => (int) $data['duration_seconds'],
            'marks_per_question'    => (float) $data['marks_per_question'],
            'negative_marks'        => (float) $data['negative_marks'],
            'top_n_select'          => (int) $data['top_n_select'],
            'min_pass_percentage'   => (float) $data['min_pass_percentage'],
            'team_scoring_mode'     => (string) $data['team_scoring_mode'],
            'violation_auto_submit' => (int) $data['violation_auto_submit'],
            'max_violations'        => (int) $data['max_violations'],
            'require_fullscreen'    => (int) $data['require_fullscreen'],
            'block_mobile'          => (int) $data['block_mobile'],
            'window_start'          => !empty($data['window_start']) ? $data['window_start'] : null,
            'window_end'            => !empty($data['window_end']) ? $data['window_end'] : null,
        ]);
    }

    public function updateStatus(int $quizId, string $status, ?int $userId = null): bool
    {
        if ($status === 'Finalized') {
            $stmt = $this->db->prepare("
                UPDATE prelims_quizzes
                SET status = 'Finalized', finalized_by = :uid, finalized_at = NOW()
                WHERE quiz_id = :qid
            ");
            return $stmt->execute(['qid' => $quizId, 'uid' => $userId]);
        }
        $stmt = $this->db->prepare("UPDATE prelims_quizzes SET status = :st WHERE quiz_id = :qid");
        return $stmt->execute(['qid' => $quizId, 'st' => $status]);
    }

    public function markDisposed(int $quizId, int $userId, string $analyticsJson): bool
    {
        $stmt = $this->db->prepare("
            UPDATE prelims_quizzes
            SET status = 'Disposed',
                analytics_snapshot_json = :snap,
                disposed_at = NOW(),
                disposed_by = :uid
            WHERE quiz_id = :qid
        ");
        return $stmt->execute([
            'qid'  => $quizId,
            'uid'  => $userId,
            'snap' => $analyticsJson,
        ]);
    }

    // -------------------------------------------------------------------------
    // Sources (PDFs)
    // -------------------------------------------------------------------------

    public function createSource(array $data): int
    {
        $sql = "
            INSERT INTO prelims_quiz_sources (
                quiz_id, original_filename, stored_path, file_size_bytes, file_sha256,
                page_count, extraction_method, answer_strategy_used,
                extraction_status, raw_warning_log, is_active, uploaded_by
            ) VALUES (
                :quiz_id, :original_filename, :stored_path, :file_size_bytes, :file_sha256,
                :page_count, :extraction_method, :answer_strategy_used,
                :extraction_status, :raw_warning_log, 1, :uploaded_by
            )
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'quiz_id'              => (int) $data['quiz_id'],
            'original_filename'    => (string) $data['original_filename'],
            'stored_path'          => $data['stored_path'] ?? null,
            'file_size_bytes'      => (int) ($data['file_size_bytes'] ?? 0),
            'file_sha256'          => (string) $data['file_sha256'],
            'page_count'           => (int) ($data['page_count'] ?? 0),
            'extraction_method'    => (string) ($data['extraction_method'] ?? 'smalot_php'),
            'answer_strategy_used' => $data['answer_strategy_used'] ?? null,
            'extraction_status'    => (string) ($data['extraction_status'] ?? 'Pending'),
            'raw_warning_log'      => $data['raw_warning_log'] ?? null,
            'uploaded_by'          => (int) $data['uploaded_by'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findSourceById(int $sourceId): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM prelims_quiz_sources WHERE source_id = :sid LIMIT 1");
        $stmt->execute(['sid' => $sourceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findSourceBySha256(int $quizId, string $sha256): array|false
    {
        $stmt = $this->db->prepare("
            SELECT * FROM prelims_quiz_sources
            WHERE quiz_id = :qid AND file_sha256 = :sha AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute(['qid' => $quizId, 'sha' => $sha256]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateSourceExtraction(int $sourceId, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE prelims_quiz_sources
            SET page_count = :page_count,
                extraction_method = :method,
                answer_strategy_used = :strategy,
                extraction_status = :status,
                raw_warning_log = :warnings
            WHERE source_id = :sid
        ");
        return $stmt->execute([
            'sid'        => $sourceId,
            'page_count' => (int) ($data['page_count'] ?? 0),
            'method'     => (string) ($data['extraction_method'] ?? 'smalot_php'),
            'strategy'   => $data['answer_strategy_used'] ?? null,
            'status'     => (string) ($data['extraction_status'] ?? 'Extracted'),
            'warnings'   => $data['raw_warning_log'] ?? null,
        ]);
    }

    public function getSourcesByQuiz(int $quizId): array
    {
        $sql = "
            SELECT src.*,
                   COUNT(DISTINCT t.topic_id) AS topic_count,
                   COUNT(DISTINCT CASE WHEN q.is_active = 1 THEN q.question_id END) AS total_questions,
                   COUNT(DISTINCT CASE WHEN q.is_active = 1 AND q.review_status = 'Approved' THEN q.question_id END) AS approved_questions,
                   COUNT(DISTINCT CASE WHEN q.is_active = 1 AND q.answer_confidence = 'Missing' THEN q.question_id END) AS missing_answer_count
            FROM prelims_quiz_sources src
            LEFT JOIN prelims_quiz_topics t ON t.source_id = src.source_id AND t.is_active = 1
            LEFT JOIN prelims_questions q   ON q.source_id = src.source_id
            WHERE src.quiz_id = :qid
            GROUP BY src.source_id
            ORDER BY src.source_id ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['qid' => $quizId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteSource(int $sourceId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM prelims_quiz_sources WHERE source_id = :sid");
        return $stmt->execute(['sid' => $sourceId]);
    }

    // -------------------------------------------------------------------------
    // Topics
    // -------------------------------------------------------------------------

    public function findOrCreateTopic(int $quizId, int $sourceId, string $topicName, int $order = 1): int
    {
        $cleanName = mb_substr(trim($topicName) ?: 'General', 0, 155);
        $sel = $this->db->prepare("
            SELECT topic_id FROM prelims_quiz_topics
            WHERE source_id = :sid AND topic_name = :name
            LIMIT 1
        ");
        $sel->execute(['sid' => $sourceId, 'name' => $cleanName]);
        $existing = $sel->fetchColumn();
        if ($existing) {
            return (int) $existing;
        }

        $ins = $this->db->prepare("
            INSERT INTO prelims_quiz_topics (quiz_id, source_id, topic_name, display_order, is_active)
            VALUES (:qid, :sid, :name, :ord, 1)
        ");
        $ins->execute([
            'qid'  => $quizId,
            'sid'  => $sourceId,
            'name' => $cleanName,
            'ord'  => $order,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getTopicsByQuiz(int $quizId): array
    {
        $sql = "
            SELECT t.*, src.original_filename,
                   COUNT(CASE WHEN q.is_active = 1 AND q.review_status = 'Approved' THEN 1 END) AS approved_count,
                   COUNT(CASE WHEN q.is_active = 1 THEN 1 END) AS total_count
            FROM prelims_quiz_topics t
            INNER JOIN prelims_quiz_sources src ON src.source_id = t.source_id
            LEFT JOIN prelims_questions q ON q.topic_id = t.topic_id
            WHERE t.quiz_id = :qid AND t.is_active = 1 AND src.is_active = 1
            GROUP BY t.topic_id
            ORDER BY src.source_id ASC, t.display_order ASC, t.topic_id ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['qid' => $quizId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Check once per request whether the prelims_quizzes table exists in the active DB.
     */
    public function isSchemaInstalled(): bool
    {
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'prelims_quizzes'");
            return (bool) $stmt->fetch(PDO::FETCH_NUM);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * List all quizzes accessible to a student based on their active event registrations.
     */
    public function getQuizzesForStudent(int $studentId): array
    {
        if (!$this->isSchemaInstalled()) {
            return [];
        }

        $sql = "
            SELECT DISTINCT
                q.quiz_id, q.symposium_event_id, q.title, q.questions_per_student,
                q.duration_seconds, q.status, q.window_start, q.window_end,
                q.top_n_select, q.team_scoring_mode,
                se.event_name, se.event_code, se.participation_type,
                sym.title AS symposium_title,
                app_ref.application_id, app_ref.application_status, app_ref.application_type,
                att.attempt_id, att.status AS attempt_status, att.raw_score, att.max_score,
                att.percentage, att.rank_position, att.submitted_at,
                ps.selection_status, ps.prelims_rank
            FROM (
                SELECT a.application_id, a.symposium_event_id, a.application_status, a.application_type
                FROM applications a
                WHERE a.student_id = :sid1
                  AND a.application_type = 'Individual'
                  AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
                UNION ALL
                SELECT a.application_id, a.symposium_event_id, a.application_status, a.application_type
                FROM team_members tm
                INNER JOIN teams t ON t.team_id = tm.team_id
                INNER JOIN applications a ON a.application_id = t.application_id
                WHERE tm.student_id = :sid2
                  AND a.application_type = 'Team'
                  AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
            ) AS app_ref
            INNER JOIN prelims_quizzes q     ON q.symposium_event_id = app_ref.symposium_event_id
            INNER JOIN symposium_events se   ON se.symposium_event_id = q.symposium_event_id
            INNER JOIN symposiums sym        ON sym.symposium_id = se.symposium_id
            LEFT JOIN prelims_quiz_attempts att
                ON att.quiz_id = q.quiz_id AND att.student_id = :sid3
            LEFT JOIN prelims_shortlist ps
                ON ps.quiz_id = q.quiz_id AND ps.application_id = app_ref.application_id
            WHERE q.status IN ('Published', 'Live', 'Paused', 'Closed', 'Finalized', 'Disposed')
            ORDER BY FIELD(q.status, 'Live', 'Published', 'Paused', 'Closed', 'Finalized', 'Disposed'), q.quiz_id DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sid1' => $studentId, 'sid2' => $studentId, 'sid3' => $studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
