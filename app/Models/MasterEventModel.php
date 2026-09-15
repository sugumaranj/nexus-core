<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : MasterEventModel.php
 * Location    : app/Models/
 * Description : Database access model for Master Event Templates Library.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Retrieve master event templates with category, status, and search filters
 * • CRUD operations for master_events table
 * • Versioning & status state management
 * • Relational rules management (master_event_rules table)
 * • Resource requirements management (resources & event_resources tables)
 * • Event cloning engine
 * • Server-side event code generation
 *
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use App\Database\Database;
use PDO;

final class MasterEventModel extends BaseModel
{
    /**
     * Get all active / non-deleted master events with optional filters.
     *
     * @param string|null $category
     * @param string|null $status
     * @param string|null $search
     * @return array
     */
    public function getAll(?string $category = null, ?string $status = null, ?string $search = null): array
    {
        $sql = "
            SELECT 
                me.*,

                u.full_name as creator_name,
                (SELECT COUNT(*) FROM master_event_rules WHERE event_id = me.event_id) as rules_count
            FROM master_events me

            LEFT JOIN users u ON u.user_id = me.created_by
            WHERE me.is_deleted = 0
        ";

        $params = [];

        if ($category !== null && $category !== '' && $category !== 'All') {
            $sql .= " AND me.category = :category";
            $params['category'] = $category;
        }

        if ($status !== null && $status !== '' && $status !== 'All') {
            $sql .= " AND me.status = :status";
            $params['status'] = $status;
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (me.event_name LIKE :search_name OR me.event_code LIKE :search_code OR me.description LIKE :search_desc)";
            $term = '%' . trim($search) . '%';
            $params['search_name'] = $term;
            $params['search_code'] = $term;
            $params['search_desc'] = $term;
        }

        $sql .= " ORDER BY me.category ASC, me.event_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find a single master event by ID.
     *
     * @param int $eventId
     * @return array|false
     */
    public function findById(int $eventId): array|false
    {
        $sql = "
            SELECT 
                me.*,

                u.full_name as creator_name
            FROM master_events me

            LEFT JOIN users u ON u.user_id = me.created_by
            WHERE me.event_id = :event_id AND me.is_deleted = 0
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['event_id' => $eventId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * Find by event code.
     *
     * @param string $code
     * @return array|false
     */
    public function findByCode(string $code): array|false
    {
        $sql = "
            SELECT * FROM master_events 
            WHERE event_code = :code AND is_deleted = 0 
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['code' => $code]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * Find by event name (case-insensitive).
     *
     * @param string $name
     * @param int|null $excludeId
     * @return array|false
     */
    public function findByName(string $name, ?int $excludeId = null): array|false
    {
        $sql = "SELECT * FROM master_events WHERE LOWER(event_name) = LOWER(:name) AND is_deleted = 0";
        $params = ['name' => $name];

        if ($excludeId !== null) {
            $sql .= " AND event_id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * Generate unique Event Code from Name.
     * Example: Paper Presentation -> PPT001, Coding -> COD001
     *
     * @param string $name
     * @return string
     */
    public function generateEventCode(string $name): string
    {
        $clean = strtoupper(preg_replace('/[^a-zA-Z]/', '', $name));
        $prefix = strlen($clean) >= 3 ? substr($clean, 0, 3) : str_pad($clean, 3, 'X');

        $sql = "
            SELECT event_code 
            FROM master_events 
            WHERE event_code LIKE :prefix 
            ORDER BY event_code DESC 
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['prefix' => $prefix . '%']);
        $lastCode = $stmt->fetchColumn();

        if ($lastCode) {
            $num = (int) substr((string)$lastCode, 3);
            $next = $num + 1;
        } else {
            $next = 1;
        }

        return $prefix . sprintf('%03d', $next);
    }

    /**
     * Create a new Master Event template.
     *
     * @param array $data
     * @return int|false
     */
    public function create(array $data): int|false
    {
        $sql = "
            INSERT INTO master_events (
                parent_event_id, event_code, event_name, category,
                participation_type, min_team_size, max_team_size,
                duration_type, duration_minutes,
                start_time, end_time, duration_days,
                description, default_instructions, requires_prelims,
                supports_registration,
                supports_attendance, supports_evaluation,
                supports_certificates, supports_tie_break, judging_method,
                maximum_score, status, effective_from, created_by
            ) VALUES (
                :parent_event_id, :event_code, :event_name, :category,
                :participation_type, :min_team_size, :max_team_size,
                :duration_type, :duration_minutes,
                :start_time, :end_time, :duration_days,
                :description, :default_instructions, :requires_prelims,
                :supports_registration,
                :supports_attendance, :supports_evaluation,
                :supports_certificates, :supports_tie_break, :judging_method,
                :maximum_score, :status, :effective_from, :created_by
            )
        ";

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            'parent_event_id'          => $data['parent_event_id'] ?? null,
            'event_code'               => $data['event_code'],
            'event_name'               => $data['event_name'],
            'category'                 => $data['category'],
            'participation_type'       => $data['participation_type'] ?? 'Individual',
            'min_team_size'            => (int)($data['min_team_size'] ?? 1),
            'max_team_size'            => (int)($data['max_team_size'] ?? 1),
            'duration_type'            => $data['duration_type'] ?? 'minutes',
            'duration_minutes'         => isset($data['duration_minutes']) && $data['duration_minutes'] !== '' ? (int)$data['duration_minutes'] : null,
            'start_time'               => !empty($data['start_time']) ? $data['start_time'] : null,
            'end_time'                 => !empty($data['end_time']) ? $data['end_time'] : null,
            'duration_days'            => (int)($data['duration_days'] ?? 1),
            'description'              => $data['description'] ?? null,
            'default_instructions'     => $data['default_instructions'] ?? null,
            'requires_prelims'         => (int)($data['requires_prelims'] ?? 0),
            'supports_registration'    => (int)($data['supports_registration'] ?? 1),

            'supports_attendance'      => (int)($data['supports_attendance'] ?? 1),
            'supports_evaluation'      => (int)($data['supports_evaluation'] ?? 1),
            'supports_certificates'    => (int)($data['supports_certificates'] ?? 1),
            'supports_tie_break'       => (int)($data['supports_tie_break'] ?? 0),
            'judging_method'           => $data['judging_method'] ?? 'Marks',
            'maximum_score'            => (float)($data['maximum_score'] ?? 100.00),
            'status'                   => $data['status'] ?? 'Draft',
            'effective_from'           => !empty($data['effective_from']) ? $data['effective_from'] : null,
            'created_by'               => (int)$data['created_by'],
        ]);

        return $success ? (int) $this->db->lastInsertId() : false;
    }

    /**
     * Update an existing Master Event.
     *
     * @param int $eventId
     * @param array $data
     * @return bool
     */
    public function update(int $eventId, array $data): bool
    {
        $sql = "
            UPDATE master_events SET
                event_name               = :event_name,
                category                 = :category,
                participation_type       = :participation_type,
                min_team_size            = :min_team_size,
                max_team_size            = :max_team_size,
                duration_type            = :duration_type,
                duration_minutes         = :duration_minutes,
                start_time               = :start_time,
                end_time                 = :end_time,
                duration_days            = :duration_days,
                description              = :description,
                default_instructions     = :default_instructions,
                requires_prelims         = :requires_prelims,
                supports_registration    = :supports_registration,

                supports_attendance      = :supports_attendance,
                supports_evaluation      = :supports_evaluation,
                supports_certificates    = :supports_certificates,
                supports_tie_break       = :supports_tie_break,
                judging_method           = :judging_method,
                maximum_score            = :maximum_score,
                status                   = :status,
                effective_from           = :effective_from
            WHERE event_id = :event_id AND is_deleted = 0
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'event_name'               => $data['event_name'],
            'category'                 => $data['category'] ?? 'Technical',
            'participation_type'       => $data['participation_type'] ?? 'Individual',
            'min_team_size'            => (int)($data['min_team_size'] ?? 1),
            'max_team_size'            => (int)($data['max_team_size'] ?? 1),
            'duration_type'            => $data['duration_type'] ?? 'minutes',
            'duration_minutes'         => isset($data['duration_minutes']) && $data['duration_minutes'] !== '' ? (int)$data['duration_minutes'] : null,
            'start_time'               => !empty($data['start_time']) ? $data['start_time'] : null,
            'end_time'                 => !empty($data['end_time']) ? $data['end_time'] : null,
            'duration_days'            => (int)($data['duration_days'] ?? 1),
            'description'              => $data['description'] ?? null,
            'default_instructions'     => $data['default_instructions'] ?? null,
            'requires_prelims'         => (int)($data['requires_prelims'] ?? 0),
            'supports_registration'    => (int)($data['supports_registration'] ?? 1),

            'supports_attendance'      => (int)($data['supports_attendance'] ?? 1),
            'supports_evaluation'      => (int)($data['supports_evaluation'] ?? 1),
            'supports_certificates'    => (int)($data['supports_certificates'] ?? 1),
            'supports_tie_break'       => (int)($data['supports_tie_break'] ?? 0),
            'judging_method'           => $data['judging_method'] ?? 'Marks',
            'maximum_score'            => (float)($data['maximum_score'] ?? 100.00),
            'status'                   => $data['status'] ?? 'Draft',
            'effective_from'           => !empty($data['effective_from']) ? $data['effective_from'] : null,
            'event_id'                 => $eventId,
        ]);
    }

    /**
     * Soft delete a master event template.
     *
     * @param int $eventId
     * @param int $userId
     * @return bool
     */
    public function softDelete(int $eventId, int $userId): bool
    {
        $sql = "
            UPDATE master_events SET
                is_deleted = 1,
                deleted_at = NOW(),
                deleted_by = :user_id
            WHERE event_id = :event_id
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'event_id' => $eventId,
            'user_id'  => $userId,
        ]);
    }

    /**
     * Clone an existing Master Event to create a new template.
     *
     * @param int $sourceEventId
     * @param string $newName
     * @param int $userId
     * @return int|false
     */
    public function cloneEvent(int $sourceEventId, string $newName, int $userId): int|false
    {
        $source = $this->findById($sourceEventId);
        if (!$source) {
            return false;
        }

        $newCode = $this->generateEventCode($newName);

        $cloneData = $source;
        $cloneData['parent_event_id'] = $sourceEventId;
        $cloneData['event_code']      = $newCode;
        $cloneData['event_name']      = $newName;

        $cloneData['status']          = 'Draft';
        $cloneData['created_by']      = $userId;

        $newId = $this->create($cloneData);

        // Clone rules
        $rules = $this->getRules($sourceEventId);
        $this->saveRules($newId, $rules);

        return $newId;
    }

    // =========================================================================
    // RULES MANAGEMENT (master_event_rules)
    // =========================================================================

    /**
     * Get rules for a master event grouped by section.
     *
     * @param int $eventId
     * @return array
     */
    public function getRules(int $eventId): array
    {
        $sql = "
            SELECT * FROM master_event_rules
            WHERE event_id = :event_id
            ORDER BY section ASC, display_order ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['event_id' => $eventId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Replace rules for a master event.
     *
     * @param int $eventId
     * @param array $rules Array of ['section' => string, 'rule_text' => string, 'display_order' => int]
     * @return bool
     */
    public function saveRules(int $eventId, array $rules): bool
    {
        // Delete existing rules
        $delSql = "DELETE FROM master_event_rules WHERE event_id = :event_id";
        $delStmt = $this->db->prepare($delSql);
        $delStmt->execute(['event_id' => $eventId]);

        if (empty($rules)) {
            return true;
        }

        $insSql = "
            INSERT INTO master_event_rules (event_id, section, rule_text, display_order)
            VALUES (:event_id, :section, :rule_text, :display_order)
        ";
        $insStmt = $this->db->prepare($insSql);

        foreach ($rules as $order => $rule) {
            $ruleText = rtrim($rule['rule_text'] ?? '');
            if ($ruleText === '') {
                continue;
            }
            $insStmt->execute([
                'event_id'      => $eventId,
                'section'       => $rule['section'],
                'rule_text'     => $ruleText,
                'display_order' => (int)($rule['display_order'] ?? ($order + 1)),
            ]);
        }

        return true;
    }
}
