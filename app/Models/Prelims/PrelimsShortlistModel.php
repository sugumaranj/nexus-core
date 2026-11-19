<?php

declare(strict_types=1);

namespace App\Models\Prelims;

use App\Models\BaseModel;
use PDO;

final class PrelimsShortlistModel extends BaseModel
{
    /**
     * Atomically replace shortlist entries for a quiz (strictly automated — no manual overrides).
     *
     * @param array<int, array{
     *   application_id: int,
     *   winning_attempt_id: int,
     *   winning_student_id: int,
     *   final_score: float,
     *   correct_count: int,
     *   time_taken_seconds: int,
     *   prelims_rank: int,
     *   selection_status: string
     * }> $entries
     */
    public function replaceForQuiz(int $quizId, int $symposiumEventId, array $entries, int $promotedBy): void
    {
        $startedTx = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $startedTx = true;
        }

        try {
            $del = $this->db->prepare("DELETE FROM prelims_shortlist WHERE quiz_id = :qid");
            $del->execute(['qid' => $quizId]);

            $ins = $this->db->prepare("
                INSERT INTO prelims_shortlist (
                    quiz_id, symposium_event_id, application_id,
                    winning_attempt_id, winning_student_id,
                    final_score, correct_count, time_taken_seconds,
                    prelims_rank, selection_status, promoted_by, promoted_at
                ) VALUES (
                    :quiz_id, :symposium_event_id, :application_id,
                    :winning_attempt_id, :winning_student_id,
                    :final_score, :correct_count, :time_taken_seconds,
                    :prelims_rank, :selection_status, :promoted_by, NOW()
                )
            ");

            foreach ($entries as $e) {
                $ins->execute([
                    'quiz_id'            => $quizId,
                    'symposium_event_id' => $symposiumEventId,
                    'application_id'     => (int) $e['application_id'],
                    'winning_attempt_id' => (int) $e['winning_attempt_id'],
                    'winning_student_id' => (int) $e['winning_student_id'],
                    'final_score'        => (float) $e['final_score'],
                    'correct_count'      => (int) $e['correct_count'],
                    'time_taken_seconds' => (int) $e['time_taken_seconds'],
                    'prelims_rank'       => (int) $e['prelims_rank'],
                    'selection_status'   => (string) $e['selection_status'],
                    'promoted_by'        => $promotedBy,
                ]);
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
    }

    public function getByQuiz(int $quizId, ?string $selectionStatus = null): array
    {
        $where = 'ps.quiz_id = :qid';
        $params = ['qid' => $quizId];
        if ($selectionStatus !== null) {
            $where .= ' AND ps.selection_status = :st';
            $params['st'] = $selectionStatus;
        }

        $sql = "
            SELECT ps.*,
                   a.application_no, a.application_type,
                   s.full_name AS student_name, s.register_number, s.email, s.student_type,
                   COALESCE(s.external_department, d.short_name, d.department_name) AS department_name,
                   COALESCE(c.college_name, s.college_name, 'Home Institution') AS college_name,
                   att.max_score, att.percentage, att.accuracy_percent, att.violation_count, att.integrity_level
            FROM prelims_shortlist ps
            INNER JOIN applications a            ON a.application_id = ps.application_id
            INNER JOIN students s                ON s.student_id = ps.winning_student_id
            LEFT JOIN departments d              ON d.department_id = s.department_id
            LEFT JOIN colleges c                 ON c.college_id = s.college_id
            INNER JOIN prelims_quiz_attempts att ON att.attempt_id = ps.winning_attempt_id
            WHERE {$where}
            ORDER BY ps.prelims_rank ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Resolve all student IDs belonging to selected applications (handles both Individual and Team).
     *
     * @return int[]
     */
    public function getSelectedStudentIds(int $quizId): array
    {
        $sql = "
            SELECT DISTINCT sid FROM (
                SELECT a.student_id AS sid
                FROM prelims_shortlist ps
                INNER JOIN applications a ON a.application_id = ps.application_id
                WHERE ps.quiz_id = :qid1 AND ps.selection_status = 'Selected' AND a.application_type = 'Individual'
                UNION ALL
                SELECT tm.student_id AS sid
                FROM prelims_shortlist ps
                INNER JOIN applications a ON a.application_id = ps.application_id
                INNER JOIN teams t ON t.application_id = a.application_id
                INNER JOIN team_members tm ON tm.team_id = t.team_id
                WHERE ps.quiz_id = :qid2 AND ps.selection_status = 'Selected' AND a.application_type = 'Team'
            ) AS x
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['qid1' => $quizId, 'qid2' => $quizId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
