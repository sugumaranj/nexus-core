<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : SymposiumEventModel.php
 * Location    : app/Models/
 * Description : Database access model for Symposium Scheduled Events.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Retrieve scheduled events for a symposium with joins
 * • Snapshotting Engine: Attach a Master Event to a Symposium with frozen rules
 * • Update scheduling details (Date, Time, Session, Venue, Faculty, Capacity)
 * • Duplicate event check per symposium
 * • Display ordering management
 *
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use App\Database\Database;
use PDO;

final class SymposiumEventModel extends BaseModel
{
    private MasterEventModel $masterModel;

    public function __construct()
    {
        parent::__construct();
        $this->masterModel = new MasterEventModel();
    }

    /**
     * Get ALL scheduled events across all symposiums (for Admin/HOD overview).
     *
     * Returns events joined with symposium and venue data.
     *
     * @return array
     */
    public function getAllWithSymposium(?int $scopeCreatedBy = null): array
    {
        $sql = "
            SELECT
                se.*,
                s.title          AS symposium_title,
                s.symposium_code,
                s.status         AS symposium_status,
                v.venue_name,
                v.building_name,
                (
                    SELECT GROUP_CONCAT(u2.full_name SEPARATOR ', ')
                    FROM faculty_assignments fa
                    JOIN users u2 ON u2.user_id = fa.user_id
                    WHERE fa.symposium_event_id = se.symposium_event_id
                      AND fa.is_active = 1
                ) AS faculty_names,
                CASE
                    -- Always honour terminal / cancelled event states
                    WHEN se.status IN ('Cancelled', 'Completed', 'Running') THEN se.status
                    -- If the parent symposium has NOT reached approval yet, show the raw stored status
                    WHEN s.status IN ('Draft', 'Submitted', 'Pending HOD Approval', 'Pending Principal Approval',
                                      'Rejected by HOD', 'Rejected by Principal', 'Cancelled') THEN se.status
                    -- Post-approval: compute status from time windows
                    WHEN se.event_date IS NOT NULL AND se.end_time IS NOT NULL
                         AND CONCAT(se.event_date, ' ', se.end_time) < NOW() THEN 'Completed'
                    WHEN se.event_date IS NOT NULL AND se.start_time IS NOT NULL AND se.end_time IS NOT NULL
                         AND CONCAT(se.event_date, ' ', se.start_time) <= NOW()
                         AND CONCAT(se.event_date, ' ', se.end_time) >= NOW() THEN 'Running'
                    WHEN COALESCE(se.registration_end, s.registration_end) IS NOT NULL
                         AND COALESCE(se.registration_end, s.registration_end) < NOW() THEN 'Registration Closed'
                    WHEN COALESCE(se.registration_start, s.registration_start) IS NOT NULL
                         AND COALESCE(se.registration_end, s.registration_end) IS NOT NULL
                         AND COALESCE(se.registration_start, s.registration_start) <= NOW()
                         AND COALESCE(se.registration_end, s.registration_end) >= NOW() THEN 'Registration Open'
                    ELSE 'Published'
                END AS status
            FROM symposium_events se
            JOIN symposiums s ON s.symposium_id = se.symposium_id
            LEFT JOIN venues v ON v.venue_id = se.venue_id
            WHERE 1=1
        ";

        $params = [];
        if ($scopeCreatedBy !== null) {
            $sql .= " AND s.created_by = :created_by ";
            $params['created_by'] = $scopeCreatedBy;
        }

        $sql .= " ORDER BY se.event_date DESC, se.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get all events scheduled for a specific symposium.
     *
     * @param int $symposiumId
     * @return array
     */
    public function getBySymposium(int $symposiumId): array
    {
        $sql = "
            SELECT 
                se.*,
                s.title as symposium_title,
                s.symposium_code,
                v.venue_name,
                v.seating_capacity as venue_capacity,
                (
                    SELECT GROUP_CONCAT(u2.full_name SEPARATOR ', ')
                    FROM faculty_assignments fa
                    JOIN users u2 ON u2.user_id = fa.user_id
                    WHERE fa.symposium_event_id = se.symposium_event_id
                ) as faculty_coordinator_name,
                me.event_code as master_event_code,
                CASE
                    -- Always honour terminal / cancelled event states
                    WHEN se.status IN ('Cancelled', 'Completed', 'Running') THEN se.status
                    -- If the parent symposium has NOT reached approval yet, show the raw stored status
                    WHEN s.status IN ('Draft', 'Submitted', 'Pending HOD Approval', 'Pending Principal Approval',
                                      'Rejected by HOD', 'Rejected by Principal', 'Cancelled') THEN se.status
                    -- Post-approval: compute status from time windows
                    WHEN se.event_date IS NOT NULL AND se.end_time IS NOT NULL
                         AND CONCAT(se.event_date, ' ', se.end_time) < NOW() THEN 'Completed'
                    WHEN se.event_date IS NOT NULL AND se.start_time IS NOT NULL AND se.end_time IS NOT NULL
                         AND CONCAT(se.event_date, ' ', se.start_time) <= NOW()
                         AND CONCAT(se.event_date, ' ', se.end_time) >= NOW() THEN 'Running'
                    WHEN COALESCE(se.registration_end, s.registration_end) IS NOT NULL
                         AND COALESCE(se.registration_end, s.registration_end) < NOW() THEN 'Registration Closed'
                    WHEN COALESCE(se.registration_start, s.registration_start) IS NOT NULL
                         AND COALESCE(se.registration_end, s.registration_end) IS NOT NULL
                         AND COALESCE(se.registration_start, s.registration_start) <= NOW()
                         AND COALESCE(se.registration_end, s.registration_end) >= NOW() THEN 'Registration Open'
                    ELSE 'Published'
                END AS status
            FROM symposium_events se
            JOIN symposiums s ON s.symposium_id = se.symposium_id
            JOIN master_events me ON me.event_id = se.event_id
            LEFT JOIN venues v ON v.venue_id = se.venue_id
            WHERE se.symposium_id = :symposium_id
            ORDER BY se.display_order ASC, se.event_date ASC, se.start_time ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find a single symposium event by ID.
     *
     * @param int $symposiumEventId
     * @return array|false
     */
    public function findById(int $symposiumEventId): array|false
    {
        $sql = "
            SELECT 
                se.*,
                s.title as symposium_title,
                s.symposium_code,
                s.event_start_date as symposium_start_date,
                s.event_end_date as symposium_end_date,
                v.venue_name,
                v.seating_capacity as venue_capacity,
                u.full_name as faculty_coordinator_name,
                u.email as faculty_coordinator_email,
                CASE
                    -- Always honour terminal / cancelled event states
                    WHEN se.status IN ('Cancelled', 'Completed', 'Running') THEN se.status
                    -- If the parent symposium has NOT reached approval yet, show the raw stored status
                    WHEN s.status IN ('Draft', 'Submitted', 'Pending HOD Approval', 'Pending Principal Approval',
                                      'Rejected by HOD', 'Rejected by Principal', 'Cancelled') THEN se.status
                    -- Post-approval: compute status from time windows
                    WHEN se.event_date IS NOT NULL AND se.end_time IS NOT NULL
                         AND CONCAT(se.event_date, ' ', se.end_time) < NOW() THEN 'Completed'
                    WHEN se.event_date IS NOT NULL AND se.start_time IS NOT NULL AND se.end_time IS NOT NULL
                         AND CONCAT(se.event_date, ' ', se.start_time) <= NOW()
                         AND CONCAT(se.event_date, ' ', se.end_time) >= NOW() THEN 'Running'
                    WHEN COALESCE(se.registration_end, s.registration_end) IS NOT NULL
                         AND COALESCE(se.registration_end, s.registration_end) < NOW() THEN 'Registration Closed'
                    WHEN COALESCE(se.registration_start, s.registration_start) IS NOT NULL
                         AND COALESCE(se.registration_end, s.registration_end) IS NOT NULL
                         AND COALESCE(se.registration_start, s.registration_start) <= NOW()
                         AND COALESCE(se.registration_end, s.registration_end) >= NOW() THEN 'Registration Open'
                    ELSE 'Published'
                END AS status
            FROM symposium_events se
            JOIN symposiums s ON s.symposium_id = se.symposium_id
            LEFT JOIN venues v ON v.venue_id = se.venue_id
            LEFT JOIN users u ON u.user_id = se.faculty_coordinator_id
            WHERE se.symposium_event_id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $symposiumEventId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * Check if a master event is already attached to a symposium.
     *
     * @param int $symposiumId
     * @param int $masterEventId
     * @return bool
     */
    public function isDuplicate(int $symposiumId, int $masterEventId): bool
    {
        $sql = "SELECT COUNT(*) FROM symposium_events WHERE symposium_id = :sym_id AND event_id = :evt_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sym_id' => $symposiumId, 'evt_id' => $masterEventId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Attach a Master Event to a Symposium (Runs the Snapshotting Engine).
     *
     * @param int $symposiumId
     * @param int $masterEventId
     * @param array $schedule
     * @param int $userId
     * @return int|false
     */
    public function attachMasterEvent(int $symposiumId, int $masterEventId, array $schedule, int $userId): int|false
    {
        $master = $this->masterModel->findById($masterEventId);
        if (!$master) {
            return false;
        }

        // --- OVERRIDES ---
        $masterOverrides = $schedule['master'] ?? [];
        if (!empty($masterOverrides)) {
            // Checkboxes might be missing in POST if unchecked, so normalize them if needed
            $checkboxes = ['supports_registration', 'supports_attendance', 'supports_evaluation', 'supports_certificates', 'requires_prelims'];
            foreach ($checkboxes as $cb) {
                if (isset($schedule['master'])) {
                    // Only normalize if the form was actually submitted (i.e. 'master' array exists in $_POST)
                    $masterOverrides[$cb] = !empty($masterOverrides[$cb]) ? 1 : 0;
                }
            }
            $master = array_merge($master, $masterOverrides);
        }

        if (isset($masterOverrides['rules_text'])) {
            $masterService = new \App\Services\MasterEventService();
            $rules = $masterService->parseRulesText($masterOverrides['rules_text']);
        } elseif (!empty($schedule['custom_rules'])) {
            $masterService = new \App\Services\MasterEventService();
            $rules = $masterService->parseRulesText($schedule['custom_rules']);
        } else {
            $rules = $this->masterModel->getRules($masterEventId);
        }
        $snapshotRules = json_encode($rules, JSON_UNESCAPED_UNICODE);

        $snapshotEval = json_encode([
            'judging_method' => $master['judging_method'],
            'maximum_score'  => $master['maximum_score']
        ], JSON_UNESCAPED_UNICODE);

        // --- DYNAMIC STATUS INHERITANCE ---
        // When a new event is added to a symposium that is already approved (or beyond),
        // immediately compute the correct initial status rather than defaulting to 'Draft'.
        $symStmt = $this->db->prepare("SELECT status, registration_start, registration_end FROM symposiums WHERE symposium_id = ?");
        $symStmt->execute([$symposiumId]);
        $sym = $symStmt->fetch(PDO::FETCH_ASSOC);

        $postApprovalStatuses = [
            'Approved', 'Scheduling Complete', 'Registration Open',
            'Registration Closed', 'Completed',
        ];

        $initialStatus = 'Draft';
        if ($sym && in_array($sym['status'], $postApprovalStatuses, true)) {
            $now      = date('Y-m-d H:i:s');
            $regStart = !empty($schedule['registration_start']) ? $schedule['registration_start'] : $sym['registration_start'];
            $regEnd   = !empty($schedule['registration_end'])   ? $schedule['registration_end']   : $sym['registration_end'];

            $evtDate  = !empty($schedule['event_date'])   ? $schedule['event_date']   : null;
            $evtStart = !empty($schedule['start_time'])   ? $schedule['start_time']   : null;
            $evtEnd   = !empty($schedule['end_time'])     ? $schedule['end_time']     : null;

            if ($evtDate && $evtEnd && $evtDate . ' ' . $evtEnd < $now) {
                $initialStatus = 'Completed';
            } elseif ($evtDate && $evtStart && $evtEnd
                && $evtDate . ' ' . $evtStart <= $now
                && $evtDate . ' ' . $evtEnd   >= $now) {
                $initialStatus = 'Running';
            } elseif ($regEnd && $regEnd < $now) {
                $initialStatus = 'Registration Closed';
            } elseif ($regStart && $regEnd && $regStart <= $now && $regEnd >= $now) {
                $initialStatus = 'Registration Open';
            } else {
                $initialStatus = 'Published';
            }
        }

        $sql = "
            INSERT INTO symposium_events (
                symposium_id, event_id, master_version_used,
                event_code, event_name, description, category, participation_type,
                min_team_size, max_team_size, snapshot_rules,
                snapshot_instructions, snapshot_evaluation,
                supports_registration, supports_attendance, supports_evaluation,
                supports_certificates, prelim_decision,
                judging_method, maximum_score,
                venue_id, event_date, session, reporting_time, start_time, end_time,
                registration_start, registration_end, max_participants, event_mode,
                faculty_coordinator_id, custom_rules_override, custom_notes,
                display_order, status, created_by
            ) VALUES (
                :symposium_id, :event_id, :master_version_used,
                :event_code, :event_name, :description, :category, :participation_type,
                :min_team_size, :max_team_size, :snapshot_rules,
                :snapshot_instructions, :snapshot_evaluation,
                :supports_registration, :supports_attendance, :supports_evaluation,
                :supports_certificates, :prelim_decision,
                :judging_method, :maximum_score,
                :venue_id, :event_date, :session, :reporting_time, :start_time, :end_time,
                :registration_start, :registration_end, :max_participants, :event_mode,
                :faculty_coordinator_id, :custom_rules_override, :custom_notes,
                :display_order, :status, :created_by
            )
        ";

        // Determine display order
        $orderSql = "SELECT COALESCE(MAX(display_order), 0) + 1 FROM symposium_events WHERE symposium_id = :sym_id";
        $orderStmt = $this->db->prepare($orderSql);
        $orderStmt->execute(['sym_id' => $symposiumId]);
        $nextOrder = (int)$orderStmt->fetchColumn();

        $stmt = $this->db->prepare($sql);
        $success = $stmt->execute([
            'symposium_id'          => $symposiumId,
            'event_id'              => $masterEventId,
            'master_version_used'   => $master['version'] ?? '1.0',
            'event_code'            => $schedule['event_code'] ?? $master['event_code'],
            'event_name'            => $schedule['event_name'] ?? $master['event_name'],
            'description'           => $schedule['description'] ?? $master['description'] ?? null,
            'category'              => $master['category'],
            'participation_type'    => $master['participation_type'],
            'min_team_size'         => (int)$master['min_team_size'],
            'max_team_size'         => (int)$master['max_team_size'],
            'snapshot_rules'        => $snapshotRules,
            'snapshot_instructions' => $master['default_instructions'],
            'snapshot_evaluation'   => $snapshotEval,
            'supports_registration' => (int)$master['supports_registration'],
            'supports_attendance'   => (int)$master['supports_attendance'],
            'supports_evaluation'   => (int)$master['supports_evaluation'],
            'supports_certificates' => (int)$master['supports_certificates'],

            'prelim_decision'       => isset($masterOverrides['requires_prelims']) ? (!empty($masterOverrides['requires_prelims']) ? 'Required' : 'Not Required') : 'Pending',
            'judging_method'        => $master['judging_method'],
            'maximum_score'         => (float)$master['maximum_score'],
            'venue_id'              => !empty($schedule['venue_id']) ? (int)$schedule['venue_id'] : null,
            'event_date'            => !empty($schedule['event_date']) ? $schedule['event_date'] : null,
            'session'               => !empty($schedule['session']) ? $schedule['session'] : null,
            'reporting_time'        => !empty($schedule['reporting_time']) ? $schedule['reporting_time'] : null,
            'start_time'            => !empty($schedule['start_time']) ? $schedule['start_time'] : null,
            'end_time'              => !empty($schedule['end_time']) ? $schedule['end_time'] : null,
            'registration_start'    => !empty($schedule['registration_start']) ? $schedule['registration_start'] : null,
            'registration_end'      => !empty($schedule['registration_end']) ? $schedule['registration_end'] : null,
            'max_participants'      => !empty($schedule['max_participants']) ? (int)$schedule['max_participants'] : null,
            'event_mode'            => $schedule['event_mode'] ?? 'Offline',
            'faculty_coordinator_id'=> !empty($schedule['faculty_coordinator_id']) ? (int)$schedule['faculty_coordinator_id'] : null,
            'custom_rules_override' => !empty($schedule['custom_rules_override']) ? json_encode($schedule['custom_rules_override']) : null,
            'custom_notes'          => $schedule['custom_notes'] ?? null,
            'display_order'         => $nextOrder,
            'status'                => $initialStatus,
            'created_by'            => $userId,
        ]);

        return $success ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Update symposium event schedule & coordinator fields.
     *
     * @param int $symposiumEventId
     * @param array $data
     * @return bool
     */
    public function updateSymposiumEvent(int $symposiumEventId, array $data): bool
    {
        $existing = $this->findById($symposiumEventId);
        if (!$existing) {
            return false;
        }

        $sql = "
            UPDATE symposium_events SET
                event_name             = :event_name,
                description            = :description,
                category               = :category,
                participation_type     = :participation_type,
                min_team_size          = :min_team_size,
                max_team_size          = :max_team_size,
                snapshot_rules         = :snapshot_rules,
                supports_registration  = :supports_registration,
                supports_attendance    = :supports_attendance,
                supports_evaluation    = :supports_evaluation,
                supports_certificates  = :supports_certificates,

                prelim_decision        = :prelim_decision,
                judging_method         = :judging_method,
                maximum_score          = :maximum_score,
                venue_id               = :venue_id,
                event_date             = :event_date,
                session                = :session,
                reporting_time         = :reporting_time,
                start_time             = :start_time,
                end_time               = :end_time,
                registration_start     = :registration_start,
                registration_end       = :registration_end,
                max_participants       = :max_participants,
                event_mode             = :event_mode,
                faculty_coordinator_id = :faculty_coordinator_id,
                custom_rules_override  = :custom_rules_override,
                custom_notes           = :custom_notes,
                status                 = :status
            WHERE symposium_event_id = :id
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'event_name'            => $data['event_name'] ?? $existing['event_name'],
            'description'           => array_key_exists('description', $data) ? $data['description'] : $existing['description'],
            'category'              => $data['category'] ?? $existing['category'],
            'participation_type'    => $data['participation_type'] ?? $existing['participation_type'],
            'min_team_size'         => array_key_exists('min_team_size', $data) ? (int)$data['min_team_size'] : (int)$existing['min_team_size'],
            'max_team_size'         => array_key_exists('max_team_size', $data) ? (int)$data['max_team_size'] : (int)$existing['max_team_size'],
            'snapshot_rules'        => $data['snapshot_rules'] ?? $existing['snapshot_rules'],
            'supports_registration' => isset($data['supports_registration']) ? (int)$data['supports_registration'] : (int)$existing['supports_registration'],
            'supports_attendance'   => isset($data['supports_attendance']) ? (int)$data['supports_attendance'] : (int)$existing['supports_attendance'],
            'supports_evaluation'   => isset($data['supports_evaluation']) ? (int)$data['supports_evaluation'] : (int)$existing['supports_evaluation'],
            'supports_certificates' => isset($data['supports_certificates']) ? (int)$data['supports_certificates'] : (int)$existing['supports_certificates'],

            'prelim_decision'       => $data['prelim_decision'] ?? $existing['prelim_decision'] ?? 'Pending',
            'judging_method'        => $data['judging_method'] ?? $existing['judging_method'],
            'maximum_score'         => array_key_exists('maximum_score', $data) ? (float)$data['maximum_score'] : (float)$existing['maximum_score'],
            'venue_id'              => array_key_exists('venue_id', $data) ? (!empty($data['venue_id']) ? (int)$data['venue_id'] : null) : $existing['venue_id'],
            'event_date'            => array_key_exists('event_date', $data) ? (!empty($data['event_date']) ? $data['event_date'] : null) : $existing['event_date'],
            'session'               => array_key_exists('session', $data) ? (!empty($data['session']) ? $data['session'] : null) : $existing['session'],
            'reporting_time'        => array_key_exists('reporting_time', $data) ? (!empty($data['reporting_time']) ? $data['reporting_time'] : null) : $existing['reporting_time'],
            'start_time'            => array_key_exists('start_time', $data) ? (!empty($data['start_time']) ? $data['start_time'] : null) : $existing['start_time'],
            'end_time'              => array_key_exists('end_time', $data) ? (!empty($data['end_time']) ? $data['end_time'] : null) : $existing['end_time'],
            'registration_start'    => array_key_exists('registration_start', $data) ? (!empty($data['registration_start']) ? $data['registration_start'] : null) : $existing['registration_start'],
            'registration_end'      => array_key_exists('registration_end', $data) ? (!empty($data['registration_end']) ? $data['registration_end'] : null) : $existing['registration_end'],
            'max_participants'      => array_key_exists('max_participants', $data) ? (!empty($data['max_participants']) ? (int)$data['max_participants'] : null) : $existing['max_participants'],
            'event_mode'            => $data['event_mode'] ?? $existing['event_mode'] ?? 'Offline',
            'faculty_coordinator_id'=> array_key_exists('faculty_coordinator_id', $data) ? (!empty($data['faculty_coordinator_id']) ? (int)$data['faculty_coordinator_id'] : null) : $existing['faculty_coordinator_id'],
            'custom_rules_override' => array_key_exists('custom_rules_override', $data) ? (!empty($data['custom_rules_override']) ? json_encode($data['custom_rules_override']) : null) : $existing['custom_rules_override'],
            'custom_notes'          => array_key_exists('custom_notes', $data) ? $data['custom_notes'] : $existing['custom_notes'],
            'status'                => $data['status'] ?? $existing['status'] ?? 'Scheduled',
            'id'                    => $symposiumEventId,
        ]);
    }

    /**
     * Delete an event from a symposium.
     *
     * @param int $symposiumEventId
     * @return bool
     */
    public function deleteSymposiumEvent(int $symposiumEventId): bool
    {
        // First delete any generated certificates associated with this event to avoid FK constraints
        $stmtCert = $this->db->prepare("DELETE FROM generated_certificates WHERE symposium_event_id = :id");
        $stmtCert->execute(['id' => $symposiumEventId]);

        $sql = "DELETE FROM symposium_events WHERE symposium_event_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $symposiumEventId]);
    }

    /**
     * Delete ALL events belonging to a symposium in one operation.
     *
     * @param int $symposiumId
     * @return int  Number of rows deleted (0 if none existed)
     */
    public function deleteAllBySymposium(int $symposiumId): int
    {
        // First delete generated certificates for all events in this symposium to avoid FK constraints
        $stmtCert = $this->db->prepare("
            DELETE FROM generated_certificates 
            WHERE symposium_event_id IN (
                SELECT symposium_event_id FROM symposium_events WHERE symposium_id = :symposium_id
            )
        ");
        $stmtCert->execute(['symposium_id' => $symposiumId]);

        $sql = "DELETE FROM symposium_events WHERE symposium_id = :symposium_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);
        return $stmt->rowCount();
    }

    // =========================================================================
    // CONFLICT DETECTION & CAPACITY CHECKS (Req #7, #12)
    // =========================================================================

    /**
     * Check for venue scheduling conflict (Same venue, same date, overlapping times).
     *
     * @param int $venueId
     * @param string $eventDate
     * @param string $startTime
     * @param string $endTime
     * @param int|null $excludeId
     * @return array|false Returns conflicting event record or false
     */
    public function checkVenueConflict(int $venueId, string $eventDate, string $startTime, string $endTime, ?int $excludeId = null): array|false
    {
        $sql = "
            SELECT se.*, s.title as symposium_title, v.venue_name
            FROM symposium_events se
            JOIN symposiums s ON s.symposium_id = se.symposium_id
            LEFT JOIN venues v ON v.venue_id = se.venue_id
            WHERE se.venue_id = :venue_id
              AND se.event_date = :event_date
              AND (se.start_time < :end_time AND se.end_time > :start_time)
              AND se.status != 'Cancelled'
        ";

        $params = [
            'venue_id'   => $venueId,
            'event_date' => $eventDate,
            'start_time' => $startTime,
            'end_time'   => $endTime,
        ];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND se.symposium_event_id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * Check for faculty coordinator scheduling conflict (Same coordinator, same date, overlapping times).
     *
     * @param int $facultyUserId
     * @param string $eventDate
     * @param string $startTime
     * @param string $endTime
     * @param int|null $excludeId
     * @return array|false Returns conflicting event record or false
     */
    public function checkCoordinatorConflict(int $facultyUserId, string $eventDate, string $startTime, string $endTime, ?int $excludeId = null): array|false
    {
        $sql = "
            SELECT se.*, s.title as symposium_title, u.full_name as coordinator_name
            FROM symposium_events se
            JOIN symposiums s ON s.symposium_id = se.symposium_id
            JOIN users u ON u.user_id = se.faculty_coordinator_id
            WHERE se.faculty_coordinator_id = :faculty_id
              AND se.event_date = :event_date
              AND (se.start_time < :end_time AND se.end_time > :start_time)
              AND se.status != 'Cancelled'
        ";

        $params = [
            'faculty_id' => $facultyUserId,
            'event_date' => $eventDate,
            'start_time' => $startTime,
            'end_time'   => $endTime,
        ];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND se.symposium_event_id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    // =========================================================================
    // SCHEDULING MODULE METHODS (Phase 1)
    // =========================================================================

    /**
     * Update ONLY the scheduling fields of a symposium event.
     *
     * Sets schedule_status, scheduled_at/by (or rescheduled_at/by),
     * and the date/time/session fields. Does NOT touch snapshot data.
     *
     * @param int   $id   symposium_event_id
     * @param array $data Must include: event_date, start_time, end_time, session,
     *                    reporting_time, schedule_status, scheduled_by,
     *                    and optionally: rescheduled_at, rescheduled_by, reschedule_reason
     * @return bool
     */
    public function updateSchedule(int $id, array $data): bool
    {
        $sql = "
            UPDATE symposium_events SET
                event_date        = :event_date,
                start_time        = :start_time,
                end_time          = :end_time,
                session           = :session,
                reporting_time    = :reporting_time,
                schedule_status   = :schedule_status,
                scheduled_at      = :scheduled_at,
                scheduled_by      = :scheduled_by,
                rescheduled_at    = :rescheduled_at,
                rescheduled_by    = :rescheduled_by,
                reschedule_reason = :reschedule_reason,
                status            = :status
            WHERE symposium_event_id = :id
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'event_date'        => $data['event_date'] ?? null,
            'start_time'        => $data['start_time'] ?? null,
            'end_time'          => $data['end_time'] ?? null,
            'session'           => $data['session'] ?? null,
            'reporting_time'    => $data['reporting_time'] ?? null,
            'schedule_status'   => $data['schedule_status'] ?? 'Scheduled',
            'scheduled_at'      => $data['scheduled_at'] ?? null,
            'scheduled_by'      => $data['scheduled_by'] ?? null,
            'rescheduled_at'    => $data['rescheduled_at'] ?? null,
            'rescheduled_by'    => $data['rescheduled_by'] ?? null,
            'reschedule_reason' => $data['reschedule_reason'] ?? null,
            'status'            => $data['status'] ?? 'Draft',
            'id'                => $id,
        ]);
    }

    /**
     * Check for time overlap within the SAME symposium.
     *
     * Prevents two events in the same symposium being scheduled at overlapping times.
     *
     * @param int    $symposiumId
     * @param string $eventDate
     * @param string $startTime
     * @param string $endTime
     * @param int|null $excludeId  Current event being edited (excluded from check)
     * @return array|false  Returns conflicting event or false
     */
    public function checkTimeOverlapWithinSymposium(
        int $symposiumId,
        string $eventDate,
        string $startTime,
        string $endTime,
        ?int $excludeId = null
    ): array|false {
        $params = [
            'symposium_id_1' => $symposiumId,
            'event_date_1'   => $eventDate,
            'start_time_1'   => $startTime,
            'end_time_1'     => $endTime,
            'symposium_id_2' => $symposiumId,
            'event_date_2'   => $eventDate,
            'start_time_2'   => $startTime,
            'end_time_2'     => $endTime,
        ];

        $excludeClause1 = "";
        $excludeClause2 = "";
        if ($excludeId !== null && $excludeId > 0) {
            $excludeClause1 = " AND se.symposium_event_id != :exclude_id_1 ";
            $excludeClause2 = " AND se.symposium_event_id != :exclude_id_2 ";
            $params['exclude_id_1'] = $excludeId;
            $params['exclude_id_2'] = $excludeId;
        }

        $sql = "
            SELECT symposium_event_id, event_name, event_code, start_time, end_time, session
            FROM (
                -- 1. Main Events
                SELECT se.symposium_event_id, se.event_name, se.event_code,
                       se.start_time, se.end_time, se.session
                FROM symposium_events se
                WHERE se.symposium_id = :symposium_id_1
                  AND se.event_date   = :event_date_1
                  AND se.start_time   IS NOT NULL
                  AND se.end_time     IS NOT NULL
                  AND (se.start_time < :end_time_1 AND se.end_time > :start_time_1)
                  AND se.status != 'Cancelled'
                  $excludeClause1

                UNION ALL

                -- 2. Competition Stages
                SELECT se.symposium_event_id, 
                       CONCAT(se.event_name, ' - ', COALESCE(cs.custom_name, cs.stage_name)) AS event_name, 
                       se.event_code,
                       cs.start_time, cs.end_time, se.session
                FROM competition_stages cs
                JOIN symposium_events se ON se.symposium_event_id = cs.symposium_event_id
                WHERE se.symposium_id = :symposium_id_2
                  AND cs.stage_date   = :event_date_2
                  AND cs.start_time   IS NOT NULL
                  AND cs.end_time     IS NOT NULL
                  AND (cs.start_time < :end_time_2 AND cs.end_time > :start_time_2)
                  AND se.status != 'Cancelled'
                  AND cs.is_active = 1
                  $excludeClause2
            ) as combined_conflicts
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: false;
    }

    /**
     * Get scheduling dashboard summary stats for a symposium.
     *
     * Returns: total, scheduled, unscheduled, rescheduled, completion_pct
     *
     * @param int $symposiumId
     * @return array
     */
    public function getSchedulingDashboard(int $symposiumId): array
    {
        $sql = "
            SELECT
                COUNT(*)                                                        AS total,
                SUM(schedule_status IN ('Scheduled','Rescheduled'))             AS scheduled,
                SUM(schedule_status = 'Unscheduled')                           AS unscheduled,
                SUM(schedule_status = 'Rescheduled')                           AS rescheduled,
                SUM(schedule_status = 'Locked')                                AS locked
            FROM symposium_events
            WHERE symposium_id = :id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $symposiumId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $total     = (int) ($row['total'] ?? 0);
        $scheduled = (int) ($row['scheduled'] ?? 0);

        return [
            'total'           => $total,
            'scheduled'       => $scheduled,
            'unscheduled'     => (int) ($row['unscheduled'] ?? 0),
            'rescheduled'     => (int) ($row['rescheduled'] ?? 0),
            'locked'          => (int) ($row['locked'] ?? 0),
            'pending'         => $total - $scheduled,
            'completion_pct'  => $total > 0 ? round(($scheduled / $total) * 100) : 0,
            'is_complete'     => $total > 0 && $scheduled === $total,
        ];
    }

    /**
     * Get all unscheduled events for a symposium.
     *
     * @param int $symposiumId
     * @return array
     */
    public function getUnscheduledEvents(int $symposiumId): array
    {
        $sql = "
            SELECT se.*, v.venue_name, u.full_name AS faculty_coordinator_name
            FROM symposium_events se
            LEFT JOIN venues v ON v.venue_id = se.venue_id
            LEFT JOIN users  u ON u.user_id  = se.faculty_coordinator_id
            WHERE se.symposium_id   = :id
              AND se.schedule_status = 'Unscheduled'
            ORDER BY se.display_order ASC, se.event_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $symposiumId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get all scheduled events for a symposium.
     *
     * @param int $symposiumId
     * @return array
     */
    public function getScheduledEvents(int $symposiumId): array
    {
        $sql = "
            SELECT se.*, v.venue_name, u.full_name AS faculty_coordinator_name,
                   sb.full_name AS scheduled_by_name, rb.full_name AS rescheduled_by_name
            FROM symposium_events se
            LEFT JOIN venues v   ON v.venue_id  = se.venue_id
            LEFT JOIN users  u   ON u.user_id   = se.faculty_coordinator_id
            LEFT JOIN users  sb  ON sb.user_id  = se.scheduled_by
            LEFT JOIN users  rb  ON rb.user_id  = se.rescheduled_by
            WHERE se.symposium_id    = :id
              AND se.schedule_status IN ('Scheduled','Rescheduled','Locked')
            ORDER BY se.event_date ASC, se.start_time ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $symposiumId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get all events grouped by date then session (for report and timeline).
     *
     * Returns a nested array: [ 'YYYY-MM-DD' => ['FN' => [...events...], 'AN' => [...], 'Full Day' => [...]] ]
     *
     * @param int $symposiumId
     * @return array
     */
    public function getGroupedByDay(int $symposiumId): array
    {
        $sql = "
            SELECT se.*, v.venue_name, u.full_name AS faculty_coordinator_name
            FROM symposium_events se
            LEFT JOIN venues v ON v.venue_id = se.venue_id
            LEFT JOIN users  u ON u.user_id  = se.faculty_coordinator_id
            WHERE se.symposium_id    = :id
              AND se.schedule_status IN ('Scheduled','Rescheduled','Locked')
              AND se.event_date      IS NOT NULL
            ORDER BY se.event_date ASC, se.start_time ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $symposiumId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $grouped = [];
        foreach ($rows as $row) {
            $date    = $row['event_date'];
            $session = $row['session'] ?? 'FN';
            $grouped[$date][$session][] = $row;
        }

        // Ensure FN before AN within each day
        foreach ($grouped as $date => &$sessions) {
            $ordered = [];
            foreach (['FN', 'AN', 'Full Day'] as $s) {
                if (!empty($sessions[$s])) {
                    $ordered[$s] = $sessions[$s];
                }
            }
            $sessions = $ordered;
        }

        return $grouped;
    }

    // =========================================================================
    // STATUS LIFECYCLE MANAGEMENT
    // =========================================================================

    /**
     * Update the status of a single symposium event.
     *
     * @param int    $symposiumEventId
     * @param string $status  One of: Draft, Published, Registration Open,
     *                        Registration Closed, Running, Completed, Cancelled
     * @return bool
     */
    public function updateStatus(int $symposiumEventId, string $status): bool
    {
        $sql = "UPDATE symposium_events SET status = :status
                WHERE symposium_event_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['status' => $status, 'id' => $symposiumEventId]);
    }

    /**
     * Promote all Draft events of a symposium to Published.
     *
     * Called immediately after a symposium receives Principal approval,
     * making the events visible to students and coordinators.
     *
     * @param int $symposiumId
     * @return int  Number of rows updated
     */
    public function bulkPublishBySymposium(int $symposiumId): int
    {
        // Also catches '' (empty string) caused by old 'Scheduled' default bug
        $sql = "UPDATE symposium_events
                SET status = 'Published'
                WHERE symposium_id = :sid
                  AND status IN ('Draft', '', 'Scheduled')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sid' => $symposiumId]);
        return $stmt->rowCount();
    }

    /**
     * Return all symposium events that are eligible for automatic status
     * transitions, along with the date/time fields needed to compute them.
     *
     * Only events NOT in a terminal state (Cancelled, Completed) are returned.
     * This keeps the result set small regardless of DB size.
     *
     * @return array  Rows: symposium_event_id, symposium_id, status,
     *                      registration_start, registration_end,
     *                      event_date, start_time, end_time
     */
    public function getEventsNeedingStatusSync(): array
    {
        // JOIN symposiums so that event-level NULL registration dates fall back
        // to the parent symposium's registration window (set during symposium creation).
        $sql = "
            SELECT
                se.symposium_event_id,
                se.symposium_id,
                se.status,
                COALESCE(se.registration_start, sy.registration_start) AS registration_start,
                COALESCE(se.registration_end,   sy.registration_end)   AS registration_end,
                se.event_date,
                se.start_time,
                se.end_time
            FROM symposium_events se
            INNER JOIN symposiums sy ON sy.symposium_id = se.symposium_id
            WHERE se.status NOT IN ('Cancelled', 'Completed', 'Draft')
               OR se.status = '' /* catch the old empty-string bug */
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}


