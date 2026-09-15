<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : EvaluationModel.php
 * Location    : app/Models/
 * Description : Database operations for the competition_evaluations table.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Upsert evaluation marks (draft / submitted).
 * • Fetch evaluation for a specific application and judge.
 * • Fetch all evaluations for an application.
 * • Track judge progress (assigned vs evaluated).
 *
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use PDO;

final class EvaluationModel extends BaseModel
{
    /**
     * Upsert an evaluation mark for a participant by a judge.
     *
     * @param int $applicationId
     * @param int $judgeId
     * @param float $mark
     * @param string|null $remarks
     * @param string $status 'Draft' or 'Submitted'
     *
     * @return bool
     */
    public function upsertEvaluation(
        int $applicationId,
        int $judgeId,
        float $mark,
        ?string $remarks,
        string $status,
        ?int $symposiumEventId = null
    ): bool {
        $sql = "
            INSERT INTO competition_evaluations (application_id, judge_id, symposium_event_id, mark, remarks, status)
            VALUES (:app_id, :judge_id, :symp_event_id, :mark, :remarks, :status)
            ON DUPLICATE KEY UPDATE
                mark = VALUES(mark),
                remarks = VALUES(remarks),
                status = VALUES(status),
                updated_at = CURRENT_TIMESTAMP
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'app_id'        => $applicationId,
            'judge_id'      => $judgeId,
            'symp_event_id' => $symposiumEventId,
            'mark'          => $mark,
            'remarks'       => $remarks,
            'status'        => $status
        ]);
    }

    /**
     * Bulk upsert multiple evaluation marks for a judge.
     *
     * @param int $symposiumEventId
     * @param int $judgeId
     * @param array $marksData Array of ['application_id' => int, 'mark' => float, 'remarks' => string]
     * @param string $status 'Draft' or 'Submitted'
     *
     * @return bool
     */
    public function bulkUpsertEvaluations(
        int $symposiumEventId,
        int $judgeId,
        array $marksData,
        string $status
    ): bool {
        if (empty($marksData)) {
            return true;
        }

        $this->db->beginTransaction();
        try {
            $sql = "
                INSERT INTO competition_evaluations (application_id, judge_id, symposium_event_id, mark, remarks, status)
                VALUES (:app_id, :judge_id, :symp_event_id, :mark, :remarks, :status)
                ON DUPLICATE KEY UPDATE
                    mark = VALUES(mark),
                    remarks = VALUES(remarks),
                    status = VALUES(status),
                    updated_at = CURRENT_TIMESTAMP
            ";
            $stmt = $this->db->prepare($sql);

            foreach ($marksData as $data) {
                $stmt->execute([
                    'app_id'        => (int) $data['application_id'],
                    'judge_id'      => $judgeId,
                    'symp_event_id' => $symposiumEventId,
                    'mark'          => (float) ($data['mark'] ?? 0),
                    'remarks'       => $data['remarks'] ?? '',
                    'status'        => $status
                ]);
            }
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Bulk evaluation save failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a specific evaluation for a judge and application.
     *
     * @param int $applicationId
     * @param int $judgeId
     *
     * @return array|null
     */
    public function getEvaluation(int $applicationId, int $judgeId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM competition_evaluations
            WHERE application_id = :app_id AND judge_id = :judge_id
            LIMIT 1
        ");
        $stmt->execute(['app_id' => $applicationId, 'judge_id' => $judgeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get all evaluations submitted by a specific judge for a competition.
     *
     * @param int $competitionId
     * @param int $judgeId
     *
     * @return array
     */
    public function getEvaluationsByJudge(int $symposiumEventId, int $judgeId): array
    {
        $sql = "
            SELECT *
            FROM competition_evaluations
            WHERE symposium_event_id = :eid AND judge_id = :jid
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['eid' => $symposiumEventId, 'jid' => $judgeId]);
        
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['application_id']] = $row;
        }
        return $results; // Keyed by application_id for easy lookup
    }

    /**
     * Get all evaluations for a specific application across all judges.
     *
     * @param int $applicationId
     *
     * @return array
     */
    public function getEvaluationsForApplication(int $applicationId): array
    {
        $sql = "
            SELECT ce.*, u.full_name as judge_name
            FROM competition_evaluations ce
            JOIN users u ON ce.judge_id = u.user_id
            WHERE ce.application_id = :app_id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['app_id' => $applicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if a specific judge has submitted all evaluations for a competition.
     * Returns counts: total_assigned, evaluated.
     * 
     * @param int $competitionId
     * @param int $judgeId
     * @param int $totalParticipants The total valid participants to evaluate
     * 
     * @return array
     */
    public function getJudgeProgress(int $competitionId, int $judgeId, int $totalParticipants): array
    {
        $sql = "
            SELECT COUNT(*) as evaluated_count
            FROM competition_evaluations ce
            JOIN applications a ON ce.application_id = a.application_id
            WHERE a.competition_id = :cid AND ce.judge_id = :jid AND ce.status = 'Submitted'
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cid' => $competitionId, 'jid' => $judgeId]);
        $evaluatedCount = (int)$stmt->fetchColumn();
        
        return [
            'total_assigned' => $totalParticipants,
            'evaluated' => $evaluatedCount,
            'remaining' => max(0, $totalParticipants - $evaluatedCount),
            'is_complete' => $evaluatedCount >= $totalParticipants && $totalParticipants > 0
        ];
    }
    /**
     * Get all submitted evaluations for a symposium event.
     * 
     * @param int $symposiumEventId
     * @return array Matrix of [judge_id => [application_id => mark]]
     */
    public function getSubmittedEvaluationsForEvent(int $symposiumEventId): array
    {
        $sql = "
            SELECT judge_id, application_id, mark
            FROM competition_evaluations
            WHERE symposium_event_id = :eid AND status = 'Submitted'
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['eid' => $symposiumEventId]);
        
        $matrix = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $jid = (int)$row['judge_id'];
            $appId = (int)$row['application_id'];
            $matrix[$jid][$appId] = $row['mark'];
        }
        return $matrix;
    }

    /**
     * Fetch published results for an event.
     */
    public function getPublishedResults(int $symposiumEventId): array
    {
        $sql = "
            SELECT 
                cr.*,
                a.application_no,
                a.application_type,
                s.full_name   AS student_name,
                s.gender,
                s.register_number,
                s.academic_year,
                d.short_name  AS department_name
            FROM competition_results cr
            JOIN applications a ON cr.application_id = a.application_id
            LEFT JOIN students s ON a.student_id = s.student_id
            LEFT JOIN departments d ON s.department_id = d.department_id
            WHERE cr.symposium_event_id = :eid
            ORDER BY cr.rank_position ASC, cr.result_id ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['eid' => $symposiumEventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
