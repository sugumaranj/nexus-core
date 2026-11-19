<?php
declare(strict_types=1);

namespace App\Models;

use App\Database\Database;
use PDO;
use Throwable;

class CompetitionStageModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Get all stages for a symposium event.
     */
    public function getBySymposiumEvent(int $symposiumEventId): array
    {
        $sql = "
            SELECT cs.*, v.venue_name 
            FROM competition_stages cs
            LEFT JOIN venues v ON v.venue_id = cs.venue_id
            WHERE cs.symposium_event_id = :symposium_event_id
            ORDER BY cs.stage_order ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':symposium_event_id', $symposiumEventId, PDO::PARAM_INT);        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a new stage.
     */
    public function addStage(array $data): int|false
    {
        try {
            $sql = "INSERT INTO competition_stages (
                        symposium_event_id, stage_name, custom_name, stage_order, 
                        stage_date, start_time, end_time, venue_id, description, is_active
                    ) VALUES (
                        :symposium_event_id, :stage_name, :custom_name, :stage_order, 
                        :stage_date, :start_time, :end_time, :venue_id, :description, :is_active
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':symposium_event_id', $data['symposium_event_id'], PDO::PARAM_INT);
            $stmt->bindValue(':stage_name', $data['stage_name'], PDO::PARAM_STR);
            $stmt->bindValue(':custom_name', $data['custom_name'] ?? null, $data['custom_name'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':stage_order', $data['stage_order'] ?? 1, PDO::PARAM_INT);
            $stmt->bindValue(':stage_date', $data['stage_date'] ?? null, $data['stage_date'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':start_time', $data['start_time'] ?? null, $data['start_time'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':end_time', $data['end_time'] ?? null, $data['end_time'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':venue_id', $data['venue_id'] ?? null, $data['venue_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':description', $data['description'] ?? null, $data['description'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':is_active', $data['is_active'] ?? 1, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            }
            return false;
        } catch (Throwable $e) {
            error_log('Error adding competition stage: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update an existing stage.
     */
    public function updateStage(int $stageId, array $data): bool
    {
        try {
            $sql = "UPDATE competition_stages SET 
                        stage_name = COALESCE(:stage_name, stage_name),
                        custom_name = :custom_name,
                        stage_order = COALESCE(:stage_order, stage_order),
                        stage_date = :stage_date,
                        start_time = :start_time,
                        end_time = :end_time,
                        venue_id = :venue_id,
                        description = :description,
                        is_active = :is_active
                    WHERE stage_id = :stage_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':stage_id', $stageId, PDO::PARAM_INT);
            $stmt->bindValue(':stage_name', $data['stage_name'] ?? null, !empty($data['stage_name']) ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':custom_name', $data['custom_name'] ?? null, !empty($data['custom_name']) ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':stage_order', isset($data['stage_order']) ? (int)$data['stage_order'] : null, isset($data['stage_order']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':stage_date', $data['stage_date'] ?? null, !empty($data['stage_date']) ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':start_time', $data['start_time'] ?? null, !empty($data['start_time']) ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':end_time', $data['end_time'] ?? null, !empty($data['end_time']) ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':venue_id', $data['venue_id'] ?? null, !empty($data['venue_id']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':description', $data['description'] ?? null, !empty($data['description']) ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':is_active', $data['is_active'] ?? 1, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (Throwable $e) {
            error_log('Error updating competition stage: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check whether a stage has linked prelims quizzes or attendance sessions.
     */
    public function hasLinkedData(int $stageId): bool
    {
        try {
            $qStmt = $this->db->prepare("SELECT COUNT(*) FROM prelims_quizzes WHERE stage_id = :sid");
            $qStmt->execute(['sid' => $stageId]);
            if ((int)$qStmt->fetchColumn() > 0) {
                return true;
            }
        } catch (Throwable $e) {
            // Table may not exist on unmigrated schemas
        }

        try {
            $aStmt = $this->db->prepare("SELECT COUNT(*) FROM attendance_sessions WHERE stage_id = :sid");
            $aStmt->execute(['sid' => $stageId]);
            if ((int)$aStmt->fetchColumn() > 0) {
                return true;
            }
        } catch (Throwable $e) {
            // Column may not exist on unmigrated schemas
        }

        return false;
    }

    /**
     * Delete a stage only if it has no linked prelims quiz or attendance sessions.
     */
    public function deleteStageIfUnused(int $stageId): bool
    {
        if ($this->hasLinkedData($stageId)) {
            return false;
        }
        $stmt = $this->db->prepare("DELETE FROM competition_stages WHERE stage_id = :sid");
        return $stmt->execute(['sid' => $stageId]);
    }

    /**
     * Find the Digital Prelims stage for a symposium event (if configured).
     */
    public function getDigitalPrelimsStage(int $symposiumEventId): array|false
    {
        $sql = "
            SELECT *
            FROM competition_stages
            WHERE symposium_event_id = :eid
              AND stage_name = 'Digital Prelims'
              AND is_active = 1
            ORDER BY stage_order ASC
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['eid' => $symposiumEventId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get a specific stage by ID.
     */
    public function findById(int $stageId): array|false
    {
        $sql = "SELECT * FROM competition_stages WHERE stage_id = :stage_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':stage_id', $stageId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
