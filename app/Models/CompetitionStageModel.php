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
                        stage_date = :stage_date,
                        start_time = :start_time,
                        end_time = :end_time,
                        venue_id = :venue_id,
                        description = :description,
                        is_active = :is_active
                    WHERE stage_id = :stage_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':stage_id', $stageId, PDO::PARAM_INT);
            $stmt->bindValue(':stage_date', $data['stage_date'] ?? null, $data['stage_date'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':start_time', $data['start_time'] ?? null, $data['start_time'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':end_time', $data['end_time'] ?? null, $data['end_time'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':venue_id', $data['venue_id'] ?? null, $data['venue_id'] ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':description', $data['description'] ?? null, $data['description'] ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':is_active', $data['is_active'] ?? 1, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (Throwable $e) {
            error_log('Error updating competition stage: ' . $e->getMessage());
            return false;
        }
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
