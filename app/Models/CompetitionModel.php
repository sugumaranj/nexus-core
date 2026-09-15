<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : CompetitionModel.php
 * Location    : app/Models/
 * Description : All database operations for Competition Management.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Retrieve competitions with filters and pagination
 * • Retrieve a single competition with joins
 * • Create, update, soft-delete, archive competitions
 * • Count dashboard statistics
 * • Conflict detection (venue, coordinator, time)
 * • Retrieve related entities (types, venues, symposiums)
 *
 * NOTE
 * -------------------------------------------------------------------------
 * This class communicates ONLY with the database.
 * Business logic belongs inside CompetitionService.
 * All queries use prepared statements. No raw interpolation.
 *
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use App\Database\Database;
use PDO;

final class CompetitionModel
{
    /**
     * PDO database connection.
     *
     * @var PDO
     */
    private PDO $db;

    /**
     * Constructor — obtain shared singleton connection.
     */
    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Retrieve all competitions with optional filters.
     *
     * Returns active (non-deleted) competitions by default.
     * Pass $includeArchived = true to include archived records.
     *
     * @param string|null $search
     * @param string|null $symposiumId
     * @param string|null $status
     * @param string|null $category
     * @param string|null $mode
     * @param string|null $coordinatorId
     * @param string|null $venueId
     * @param string|null $session
     * @param string|null $eventDate
     * @param bool        $includeArchived
     * @param int|null    $scopeDepartmentId
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getAll(
        ?string $search = null,
        ?string $symposiumId = null,
        ?string $status = null,
        ?string $category = null,
        ?string $mode = null,
        ?string $coordinatorId = null,
        ?string $venueId = null,
        ?string $session = null,
        ?string $eventDate = null,
        bool $includeArchived = false,
        ?int $scopeDepartmentId = null
    ): array {
        $sql = "
            SELECT
                c.competition_id,
                c.symposium_id,
                c.competition_type_id,
                c.venue_id,
                c.competition_code,
                c.title,
                c.description,
                c.status,
                c.competition_mode,
                c.category,
                c.session,
                c.participation_type,
                c.min_team_size,
                c.max_team_size,
                c.max_participants,
                c.registration_limit,
                c.event_date,
                c.reporting_time,
                c.start_time,
                c.end_time,
                c.duration_minutes,
                c.registration_deadline,
                c.submission_deadline,
                c.has_prelims,
                c.has_digital_prelims,
                c.supports_certificates,
                c.supports_qr,
                c.judging_method,
                c.competition_mode,
                c.display_order,
                c.is_active,
                c.is_locked,
                c.is_deleted,
                c.is_archived,
                c.archived_at,
                c.published_at,
                c.created_by,
                c.created_at,
                c.updated_at,
                s.title              AS symposium_title,
                s.academic_year      AS symposium_year,
                s.organizing_department_id,
                s.created_by         AS symposium_created_by,
                ct.type_name         AS competition_type_name,
                ct.category          AS type_category,
                v.venue_name,
                v.building_name,
                -- ARCHITECTURAL PRINCIPLE: organizing_department is resolved
                -- from symposium_departments (canonical source of truth).
                -- symposiums.organizing_department_id is kept for FK compat only.
                GROUP_CONCAT(
                    DISTINCT sd_org.department_name
                    ORDER BY sd_org.department_name
                    SEPARATOR ' & '
                ) AS organizing_department,
                u.full_name          AS created_by_name,
                GROUP_CONCAT(
                    DISTINCT CONCAT(cu.full_name, ' (', cc.coordinator_type, ')')
                    ORDER BY cc.coordinator_type
                    SEPARATOR ', '
                ) AS coordinators
            FROM competitions c
            LEFT JOIN symposiums s
                ON s.symposium_id = c.symposium_id
            LEFT JOIN competition_types ct
                ON ct.competition_type_id = c.competition_type_id
            LEFT JOIN venues v
                ON v.venue_id = c.venue_id
            -- Join symposium_departments to resolve organizing dept names
            LEFT JOIN symposium_departments sd
                ON sd.symposium_id = c.symposium_id
            LEFT JOIN departments sd_org
                ON sd_org.department_id = sd.department_id
            LEFT JOIN users u
                ON u.user_id = c.created_by
            LEFT JOIN competition_coordinators cc
                ON cc.competition_id = c.competition_id
            LEFT JOIN users cu
                ON cu.user_id = cc.user_id
        ";

        $conditions = ['c.is_deleted = 0'];
        $params     = [];

        if (!$includeArchived) {
            $conditions[] = 'c.is_archived = 0';
        }

        if ($scopeDepartmentId !== null) {
            // ARCHITECTURAL PRINCIPLE: HOD scope is resolved via symposium_departments,
            // not via the legacy organizing_department_id column.
            // This correctly handles multi-department symposiums.
            $conditions[] = 'EXISTS (
                SELECT 1 FROM symposium_departments sd_scope
                WHERE sd_scope.symposium_id = c.symposium_id
                  AND sd_scope.department_id = :scope_dept
            )';
            $params['scope_dept'] = $scopeDepartmentId;
        }

        if ($symposiumId !== null && trim($symposiumId) !== '') {
            $conditions[] = 'c.symposium_id = :symposium_id';
            $params['symposium_id'] = (int) $symposiumId;
        }

        if ($status !== null && trim($status) !== '') {
            $conditions[] = 'c.status = :status';
            $params['status'] = trim($status);
        }

        if ($category !== null && trim($category) !== '') {
            $conditions[] = 'c.category = :category';
            $params['category'] = trim($category);
        }

        if ($mode !== null && trim($mode) !== '') {
            $conditions[] = 'c.competition_mode = :comp_mode';
            $params['comp_mode'] = trim($mode);
        }

        if ($session !== null && trim($session) !== '') {
            $conditions[] = 'c.session = :session';
            $params['session'] = trim($session);
        }

        if ($venueId !== null && trim($venueId) !== '') {
            $conditions[] = 'c.venue_id = :venue_id';
            $params['venue_id'] = (int) $venueId;
        }

        if ($eventDate !== null && trim($eventDate) !== '') {
            $conditions[] = 'c.event_date = :event_date';
            $params['event_date'] = trim($eventDate);
        }

        if ($coordinatorId !== null && trim($coordinatorId) !== '') {
            $conditions[] = 'cc.user_id = :coord_id';
            $params['coord_id'] = (int) $coordinatorId;
        }

        if ($search !== null && trim($search) !== '') {
            $keyword = '%' . trim($search) . '%';
            $conditions[] = '(
                LOWER(c.competition_code) LIKE LOWER(:kw_code)
                OR LOWER(c.title)         LIKE LOWER(:kw_title)
                OR LOWER(c.status)        LIKE LOWER(:kw_status)
                OR LOWER(ct.type_name)    LIKE LOWER(:kw_type)
                OR LOWER(s.title)         LIKE LOWER(:kw_sym)
                OR LOWER(v.venue_name)    LIKE LOWER(:kw_venue)
            )';
            $params['kw_code']   = $keyword;
            $params['kw_title']  = $keyword;
            $params['kw_status'] = $keyword;
            $params['kw_type']   = $keyword;
            $params['kw_sym']    = $keyword;
            $params['kw_venue']  = $keyword;
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions);
        $sql .= ' GROUP BY c.competition_id';
        $sql .= ' ORDER BY c.event_date ASC, c.display_order ASC, c.competition_code ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Find a competition by primary key with full join details.
     *
     * @param int $competitionId
     *
     * @return array|false
     * -------------------------------------------------------------------------
     */
    public function findById(int $competitionId): array|false
    {
        $sql = "
            SELECT
                c.*,
                s.title              AS symposium_title,
                s.academic_year      AS symposium_year,
                s.organizing_department_id,
                s.created_by         AS symposium_created_by,
                ct.type_name         AS competition_type_name,
                ct.category          AS type_category,
                v.venue_name,
                v.building_name,
                v.floor              AS venue_floor,
                v.seating_capacity,
                -- ARCHITECTURAL PRINCIPLE: organizing_department resolved
                -- from symposium_departments (canonical source of truth).
                GROUP_CONCAT(
                    DISTINCT sd_org.department_name
                    ORDER BY sd_org.department_name
                    SEPARATOR ' & '
                ) AS organizing_department,
                u.full_name          AS created_by_name,
                pu.full_name         AS published_by_name,
                au.full_name         AS archived_by_name
            FROM competitions c
            LEFT JOIN symposiums s
                ON s.symposium_id = c.symposium_id
            LEFT JOIN competition_types ct
                ON ct.competition_type_id = c.competition_type_id
            LEFT JOIN venues v
                ON v.venue_id = c.venue_id
            -- Join symposium_departments to resolve organizing dept names
            LEFT JOIN symposium_departments sd
                ON sd.symposium_id = c.symposium_id
            LEFT JOIN departments sd_org
                ON sd_org.department_id = sd.department_id
            LEFT JOIN users u
                ON u.user_id = c.created_by
            LEFT JOIN users pu
                ON pu.user_id = c.published_by
            LEFT JOIN users au
                ON au.user_id = c.archived_by
            WHERE c.competition_id = :id
            GROUP BY c.competition_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $competitionId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Find a competition by its unique code.
     *
     * @param string   $code
     * @param int|null $excludeId  Competition ID to exclude (for update checks)
     *
     * @return array|false
     * -------------------------------------------------------------------------
     */
    public function findByCode(string $code, ?int $excludeId = null): array|false
    {
        $sql = "
            SELECT competition_id, competition_code, title
            FROM competitions
            WHERE competition_code = :code
              AND is_deleted = 0
        ";

        $params = ['code' => strtoupper(trim($code))];

        if ($excludeId !== null) {
            $sql .= ' AND competition_id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // CREATE / UPDATE
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Create a new competition record.
     *
     * @param array $data
     *
     * @return int|false  Returns the new competition_id on success, false on failure
     * -------------------------------------------------------------------------
     */
    public function create(array $data): int|false
    {
        $sql = "
            INSERT INTO competitions (
                symposium_id, competition_type_id, venue_id,
                competition_code, title, description,
                status, competition_mode, category, session,
                participation_type, min_team_size, max_team_size,
                max_participants, registration_limit, eligibility_note,
                has_prelims, has_digital_prelims, prelim_type,
                supports_evaluation, supports_attendance,
                supports_qr, supports_certificates, supports_tie_break,
                supports_waiting_list, judging_method,
                online_platform, meeting_link, submission_url,
                event_date, reporting_time, start_time, end_time,
                duration_minutes, registration_deadline, submission_deadline,
                maximum_score, display_order,
                rules, created_by
            ) VALUES (
                :symposium_id, :competition_type_id, :venue_id,
                :competition_code, :title, :description,
                :status, :competition_mode, :category, :session,
                :participation_type, :min_team_size, :max_team_size,
                :max_participants, :registration_limit, :eligibility_note,
                :has_prelims, :has_digital_prelims, :prelim_type,
                :supports_evaluation, :supports_attendance,
                :supports_qr, :supports_certificates, :supports_tie_break,
                :supports_waiting_list, :judging_method,
                :online_platform, :meeting_link, :submission_url,
                :event_date, :reporting_time, :start_time, :end_time,
                :duration_minutes, :registration_deadline, :submission_deadline,
                :maximum_score, :display_order,
                :rules, :created_by
            )
        ";

        $stmt = $this->db->prepare($sql);

        $result = $stmt->execute([
            'symposium_id'         => (int) $data['symposium_id'],
            'competition_type_id'  => (int) $data['competition_type_id'],
            'venue_id'             => (int) $data['venue_id'],
            'competition_code'     => strtoupper(trim($data['competition_code'])),
            'title'                => trim($data['title']),
            'description'          => trim($data['description'] ?? ''),
            'status'               => $data['status'],
            'competition_mode'     => $data['competition_mode'],
            'category'             => $data['category'],
            'session'              => $data['session'],
            'participation_type'   => $data['participation_type'],
            'min_team_size'        => (int) ($data['min_team_size'] ?? 1),
            'max_team_size'        => (int) ($data['max_team_size'] ?? 1),
            'max_participants'     => $data['max_participants'] !== '' ? (int) $data['max_participants'] : null,
            'registration_limit'   => $data['registration_limit'] !== '' ? (int) $data['registration_limit'] : null,
            'eligibility_note'     => trim($data['eligibility_note'] ?? ''),
            'has_prelims'          => (int) ($data['has_prelims'] ?? 0),
            'has_digital_prelims'  => (int) ($data['has_digital_prelims'] ?? 0),
            'prelim_type'          => $data['prelim_type'] ?? null,

            'supports_evaluation'  => (int) ($data['supports_evaluation'] ?? 1),
            'supports_attendance'  => (int) ($data['supports_attendance'] ?? 1),
            'supports_qr'          => (int) ($data['supports_qr'] ?? 0),
            'supports_certificates'=> (int) ($data['supports_certificates'] ?? 1),
            'supports_tie_break'   => (int) ($data['supports_tie_break'] ?? 0),
            'supports_waiting_list'=> (int) ($data['supports_waiting_list'] ?? 0),
            'judging_method'       => $data['judging_method'] ?? 'Marks',
            'online_platform'      => $data['online_platform'] ?? null,
            'meeting_link'         => trim($data['meeting_link'] ?? '') ?: null,
            'submission_url'       => trim($data['submission_url'] ?? '') ?: null,
            'event_date'           => $data['event_date'],
            'reporting_time'       => $data['reporting_time'] ?? '08:30:00',
            'start_time'           => $data['start_time'],
            'end_time'             => $data['end_time'],
            'duration_minutes'     => (int) ($data['duration_minutes'] ?? 60),
            'registration_deadline'=> $data['registration_deadline'],
            'submission_deadline'  => $data['submission_deadline'] ?? null,
            'maximum_score'        => (float) ($data['maximum_score'] ?? 100.00),
            'display_order'        => (int) ($data['display_order'] ?? 1),
            'rules'                => $data['rules'] ?? null,
            'created_by'           => (int) $data['created_by'],
        ]);

        if (!$result) {
            return false;
        }

        return (int) $this->db->lastInsertId();
    }

    /**
     * -------------------------------------------------------------------------
     * Update an existing competition.
     *
     * @param int   $competitionId
     * @param array $data
     *
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function update(int $competitionId, array $data): bool
    {
        $sql = "
            UPDATE competitions SET
                symposium_id          = :symposium_id,
                competition_type_id   = :competition_type_id,
                venue_id              = :venue_id,
                title                 = :title,
                description           = :description,
                status                = :status,
                competition_mode      = :competition_mode,
                category              = :category,
                session               = :session,
                participation_type    = :participation_type,
                min_team_size         = :min_team_size,
                max_team_size         = :max_team_size,
                max_participants      = :max_participants,
                registration_limit    = :registration_limit,
                eligibility_note      = :eligibility_note,
                has_prelims           = :has_prelims,
                has_digital_prelims   = :has_digital_prelims,
                prelim_type           = :prelim_type,

                supports_evaluation   = :supports_evaluation,
                supports_attendance   = :supports_attendance,
                supports_qr           = :supports_qr,
                supports_certificates = :supports_certificates,
                supports_tie_break    = :supports_tie_break,
                supports_waiting_list = :supports_waiting_list,
                judging_method        = :judging_method,
                online_platform       = :online_platform,
                meeting_link          = :meeting_link,
                submission_url        = :submission_url,
                event_date            = :event_date,
                reporting_time        = :reporting_time,
                start_time            = :start_time,
                end_time              = :end_time,
                duration_minutes      = :duration_minutes,
                registration_deadline = :registration_deadline,
                submission_deadline   = :submission_deadline,
                maximum_score         = :maximum_score,
                display_order         = :display_order,
                last_modified_by      = :last_modified_by
            WHERE competition_id = :competition_id
              AND is_deleted = 0
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'symposium_id'         => (int) $data['symposium_id'],
            'competition_type_id'  => (int) $data['competition_type_id'],
            'venue_id'             => (int) $data['venue_id'],
            'title'                => trim($data['title']),
            'description'          => trim($data['description'] ?? ''),
            'status'               => $data['status'],
            'competition_mode'     => $data['competition_mode'],
            'category'             => $data['category'],
            'session'              => $data['session'],
            'participation_type'   => $data['participation_type'],
            'min_team_size'        => (int) ($data['min_team_size'] ?? 1),
            'max_team_size'        => (int) ($data['max_team_size'] ?? 1),
            'max_participants'     => $data['max_participants'] !== '' ? (int) $data['max_participants'] : null,
            'registration_limit'   => $data['registration_limit'] !== '' ? (int) $data['registration_limit'] : null,
            'eligibility_note'     => trim($data['eligibility_note'] ?? ''),
            'has_prelims'          => (int) ($data['has_prelims'] ?? 0),
            'has_digital_prelims'  => (int) ($data['has_digital_prelims'] ?? 0),
            'prelim_type'          => $data['prelim_type'] ?? null,

            'supports_evaluation'  => (int) ($data['supports_evaluation'] ?? 1),
            'supports_attendance'  => (int) ($data['supports_attendance'] ?? 1),
            'supports_qr'          => (int) ($data['supports_qr'] ?? 0),
            'supports_certificates'=> (int) ($data['supports_certificates'] ?? 1),
            'supports_tie_break'   => (int) ($data['supports_tie_break'] ?? 0),
            'supports_waiting_list'=> (int) ($data['supports_waiting_list'] ?? 0),
            'judging_method'       => $data['judging_method'] ?? 'Marks',
            'online_platform'      => $data['online_platform'] ?? null,
            'meeting_link'         => trim($data['meeting_link'] ?? '') ?: null,
            'submission_url'       => trim($data['submission_url'] ?? '') ?: null,
            'event_date'           => $data['event_date'],
            'reporting_time'       => $data['reporting_time'] ?? '08:30:00',
            'start_time'           => $data['start_time'],
            'end_time'             => $data['end_time'],
            'duration_minutes'     => (int) ($data['duration_minutes'] ?? 60),
            'registration_deadline'=> $data['registration_deadline'],
            'submission_deadline'  => $data['submission_deadline'] ?? null,
            'maximum_score'        => (float) ($data['maximum_score'] ?? 100.00),
            'display_order'        => (int) ($data['display_order'] ?? 1),
            'last_modified_by'     => (int) $data['last_modified_by'],
            'competition_id'       => $competitionId,
        ]);
    }

    /**
     * -------------------------------------------------------------------------
     * Update competition status only.
     *
     * @param int    $competitionId
     * @param string $status
     * @param int    $userId
     *
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function updateStatus(int $competitionId, string $status, int $userId): bool
    {
        $extraSql = '';
        $params   = [
            'status'         => $status,
            'last_mod'       => $userId,
            'competition_id' => $competitionId,
        ];

        // Track publish timestamp automatically
        if ($status === 'Registration Open' || $status === 'Scheduled') {
            $extraSql = ', published_at = NOW(), published_by = :pub_by';
            $params['pub_by'] = $userId;
        }

        $sql = "
            UPDATE competitions
            SET status = :status, last_modified_by = :last_mod {$extraSql}
            WHERE competition_id = :competition_id
              AND is_deleted = 0
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * -------------------------------------------------------------------------
     * Soft-delete a competition (sets is_deleted = 1).
     *
     * @param int $competitionId
     * @param int $userId
     *
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function softDelete(int $competitionId, int $userId): bool
    {
        $sql = "
            UPDATE competitions
            SET is_deleted = 1,
                deleted_at = NOW(),
                deleted_by = :deleted_by
            WHERE competition_id = :competition_id
              AND is_deleted = 0
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'deleted_by'     => $userId,
            'competition_id' => $competitionId,
        ]);
    }

    /**
     * -------------------------------------------------------------------------
     * Archive or unarchive a competition.
     *
     * @param int  $competitionId
     * @param int  $userId
     * @param bool $archive  true = archive, false = restore
     *
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function setArchive(int $competitionId, int $userId, bool $archive = true): bool
    {
        if ($archive) {
            $sql = "
                UPDATE competitions
                SET is_archived  = 1,
                    archived_at  = NOW(),
                    archived_by  = :user_id,
                    status       = 'Cancelled'
                WHERE competition_id = :competition_id
                  AND is_deleted = 0
            ";
        } else {
            $sql = "
                UPDATE competitions
                SET is_archived  = 0,
                    archived_at  = NULL,
                    archived_by  = NULL,
                    status       = 'Draft'
                WHERE competition_id = :competition_id
                  AND is_deleted = 0
            ";
        }

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id'        => $userId,
            'competition_id' => $competitionId,
        ]);
    }

    // =========================================================================
    // STATISTICS / COUNTS
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Get dashboard statistics.
     *
     * Returns counts for each status, category, mode, and total.
     *
     * @param int|null $scopeDepartmentId
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getDashboardStats(?int $scopeDepartmentId = null): array
    {
        $departmentJoin  = '';
        $departmentWhere = '';
        $params          = [];

        if ($scopeDepartmentId !== null) {
            // ARCHITECTURAL PRINCIPLE: Department scope resolved via
            // symposium_departments (canonical source of truth), not
            // via the legacy organizing_department_id column.
            $departmentJoin  = "INNER JOIN symposium_departments sd_scope ON sd_scope.symposium_id = c.symposium_id";
            $departmentWhere = "AND sd_scope.department_id = :scope_dept";
            $params['scope_dept'] = $scopeDepartmentId;
        }

        $sql = "
            SELECT
                COUNT(*)                                                     AS total,
                SUM(c.category = 'Technical')                                AS technical,
                SUM(c.category = 'Non-Technical')                            AS non_technical,
                SUM(c.status = 'Registration Open')                          AS registration_open,
                SUM(c.status = 'Completed')                                  AS completed,
                SUM(c.status = 'Running')                                    AS running,
                SUM(c.status = 'Draft')                                      AS draft,
                SUM(c.status = 'Scheduled')                                  AS scheduled,
                SUM(c.status = 'Cancelled')                                  AS cancelled,
                SUM(c.competition_mode = 'Online')                           AS online_count,
                SUM(c.competition_mode = 'Offline')                          AS offline_count,
                SUM(c.competition_mode = 'Hybrid')                           AS hybrid_count,
                SUM(c.has_prelims = 1)                                       AS with_prelims,
                SUM(DATE(c.event_date) = CURDATE() AND c.status != 'Cancelled') AS today_events
            FROM competitions c
            {$departmentJoin}
            WHERE c.is_deleted = 0
              AND c.is_archived = 0
              {$departmentWhere}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * -------------------------------------------------------------------------
     * Count competitions by status.
     *
     * @param string   $status
     * @param int|null $scopeDepartmentId
     *
     * @return int
     * -------------------------------------------------------------------------
     */
    public function countByStatus(string $status, ?int $scopeDepartmentId = null): int
    {
        $join   = '';
        $where  = '';
        $params = ['status' => $status];

        if ($scopeDepartmentId !== null) {
            // ARCHITECTURAL PRINCIPLE: Department scope resolved via
            // symposium_departments (canonical source of truth), not
            // via the legacy organizing_department_id column.
            $join  = "INNER JOIN symposium_departments sd_scope ON sd_scope.symposium_id = c.symposium_id";
            $where = "AND sd_scope.department_id = :scope_dept";
            $params['scope_dept'] = $scopeDepartmentId;
        }

        $sql = "
            SELECT COUNT(*) AS cnt
            FROM competitions c {$join}
            WHERE c.is_deleted = 0 AND c.is_archived = 0
              AND c.status = :status {$where}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['cnt'] ?? 0);
    }

    // =========================================================================
    // CONFLICT DETECTION
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Check for venue/time conflicts on the given date.
     *
     * Returns an array of conflicting competition IDs (empty = no conflict).
     *
     * @param int         $venueId
     * @param string      $eventDate   (YYYY-MM-DD)
     * @param string      $startTime   (HH:MM)
     * @param string      $endTime     (HH:MM)
     * @param int|null    $excludeId   Competition to exclude (for edit)
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function checkVenueConflict(
        int $venueId,
        string $eventDate,
        string $startTime,
        string $endTime,
        ?int $excludeId = null
    ): array {
        $sql = "
            SELECT competition_id, title, start_time, end_time
            FROM competitions
            WHERE venue_id        = :venue_id
              AND event_date      = :event_date
              AND is_deleted      = 0
              AND is_archived     = 0
              AND status         != 'Cancelled'
              AND (
                  (start_time < :end_time AND end_time > :start_time)
              )
        ";

        $params = [
            'venue_id'   => $venueId,
            'event_date' => $eventDate,
            'start_time' => $startTime,
            'end_time'   => $endTime,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND competition_id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Check if a coordinator is already assigned to another competition
     * at the same date/time.
     *
     * @param int         $userId
     * @param string      $eventDate
     * @param string      $startTime
     * @param string      $endTime
     * @param int|null    $excludeId
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function checkCoordinatorConflict(
        int $userId,
        string $eventDate,
        string $startTime,
        string $endTime,
        ?int $excludeId = null
    ): array {
        $sql = "
            SELECT c.competition_id, c.title, c.start_time, c.end_time
            FROM competitions c
            INNER JOIN competition_coordinators cc
                ON cc.competition_id = c.competition_id
            WHERE cc.user_id    = :user_id
              AND c.event_date  = :event_date
              AND c.is_deleted  = 0
              AND c.status     != 'Cancelled'
              AND (
                  (c.start_time < :end_time AND c.end_time > :start_time)
              )
        ";

        $params = [
            'user_id'    => $userId,
            'event_date' => $eventDate,
            'start_time' => $startTime,
            'end_time'   => $endTime,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND c.competition_id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // RELATED ENTITY LOOKUPS
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Get all active symposiums for dropdowns.
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getActiveSymposiums(): array
    {
        $sql = "
            SELECT
                s.symposium_id,
                s.symposium_code,
                s.title,
                s.academic_year,
                s.event_start_date,
                s.event_end_date,
                s.status,
                -- ARCHITECTURAL PRINCIPLE: organizing_department resolved
                -- from symposium_departments (canonical source of truth).
                GROUP_CONCAT(
                    d.department_name ORDER BY sd.id SEPARATOR ' & '
                ) AS organizing_department
            FROM symposiums s
            LEFT JOIN symposium_departments sd ON sd.symposium_id = s.symposium_id
            LEFT JOIN departments d ON d.department_id = sd.department_id
            WHERE s.status NOT IN ('Cancelled', 'Completed')
            GROUP BY s.symposium_id
            ORDER BY s.academic_year DESC, s.title ASC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Get all symposiums (including historical) for dropdowns.
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getAllSymposiums(): array
    {
        $sql = "
            SELECT
                s.symposium_id,
                s.symposium_code,
                s.title,
                s.academic_year,
                s.event_start_date,
                s.event_end_date,
                s.status,
                -- ARCHITECTURAL PRINCIPLE: organizing_department resolved
                -- from symposium_departments (canonical source of truth).
                GROUP_CONCAT(
                    d.department_name ORDER BY sd.id SEPARATOR ' & '
                ) AS organizing_department
            FROM symposiums s
            LEFT JOIN symposium_departments sd ON sd.symposium_id = s.symposium_id
            LEFT JOIN departments d ON d.department_id = sd.department_id
            GROUP BY s.symposium_id
            ORDER BY s.academic_year DESC, s.title ASC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Get all active competition types.
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getCompetitionTypes(): array
    {
        $sql = "
            SELECT
                competition_type_id,
                type_code,
                type_name,
                category,
                is_team_event,

                supports_prelims,
                default_team_size
            FROM competition_types
            WHERE is_active = 1
            ORDER BY category ASC, type_name ASC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Get all active venues.
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getActiveVenues(): array
    {
        $sql = "
            SELECT
                venue_id,
                venue_code,
                venue_name,
                building_name,
                floor,
                seating_capacity,
                is_computer_lab
            FROM venues
            WHERE is_active = 1
            ORDER BY building_name ASC, venue_name ASC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Get all active staff users for coordinator assignment.
     *
     * @param string|null $search  Search term to filter by name/email
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getStaffUsers(?string $search = null): array
    {
        $sql = "
            SELECT
                user_id,
                employee_id,
                full_name,
                email,
                role,
                d.department_name
            FROM users u
            LEFT JOIN departments d ON d.department_id = u.department_id
            WHERE u.account_status = 'Active'
              AND u.role IN ('Admin', 'HOD', 'Staff', 'Principal')
        ";

        $params = [];

        if ($search !== null && trim($search) !== '') {
            $keyword = '%' . trim($search) . '%';
            $sql .= " AND (u.full_name LIKE :kw OR u.email LIKE :kw2 OR u.employee_id LIKE :kw3)";
            $params['kw']  = $keyword;
            $params['kw2'] = $keyword;
            $params['kw3'] = $keyword;
        }

        $sql .= ' ORDER BY u.full_name ASC LIMIT 50';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Get distinct academic years present in competitions.
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getDistinctAcademicYears(): array
    {
        $sql = "
            SELECT DISTINCT s.academic_year
            FROM competitions c
            INNER JOIN symposiums s ON s.symposium_id = c.symposium_id
            WHERE c.is_deleted = 0
            ORDER BY s.academic_year DESC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * -------------------------------------------------------------------------
     * Get upcoming competitions for the dashboard timeline.
     *
     * @param int $limit
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getUpcoming(int $limit = 10): array
    {
        $sql = "
            SELECT
                c.competition_id,
                c.competition_code,
                c.title,
                c.event_date,
                c.start_time,
                c.end_time,
                c.session,
                c.status,
                c.category,
                c.competition_mode,
                v.venue_name,
                s.title AS symposium_title
            FROM competitions c
            LEFT JOIN venues v ON v.venue_id = c.venue_id
            LEFT JOIN symposiums s ON s.symposium_id = c.symposium_id
            WHERE c.is_deleted  = 0
              AND c.is_archived = 0
              AND c.event_date >= CURDATE()
              AND c.status NOT IN ('Cancelled', 'Completed')
            ORDER BY c.event_date ASC, c.start_time ASC
            LIMIT :lim
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Get today's competitions.
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getTodayEvents(): array
    {
        $sql = "
            SELECT
                c.competition_id,
                c.competition_code,
                c.title,
                c.start_time,
                c.end_time,
                c.session,
                c.status,
                c.category,
                c.competition_mode,
                v.venue_name,
                s.title AS symposium_title
            FROM competitions c
            LEFT JOIN venues v ON v.venue_id = c.venue_id
            LEFT JOIN symposiums s ON s.symposium_id = c.symposium_id
            WHERE c.is_deleted  = 0
              AND c.is_archived = 0
              AND DATE(c.event_date) = CURDATE()
            ORDER BY c.start_time ASC
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Retrieve recently created competitions for dashboard activity feed.
     *
     * @param int $limit
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getRecent(int $limit = 8): array
    {
        $sql = "
            SELECT
                c.competition_id,
                c.competition_code,
                c.title,
                c.status,
                c.category,
                c.created_at,
                u.full_name AS created_by_name
            FROM competitions c
            LEFT JOIN users u ON u.user_id = c.created_by
            WHERE c.is_deleted = 0
            ORDER BY c.created_at DESC
            LIMIT :lim
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Count competitions by category for chart data.
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getCategoryChartData(): array
    {
        $sql = "
            SELECT category, COUNT(*) AS cnt
            FROM competitions
            WHERE is_deleted = 0 AND is_archived = 0
            GROUP BY category
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Count competitions by status for chart data.
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getStatusChartData(): array
    {
        $sql = "
            SELECT status, COUNT(*) AS cnt
            FROM competitions
            WHERE is_deleted = 0 AND is_archived = 0
            GROUP BY status
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Get audit log entries for a competition.
     *
     * @param int $competitionId
     * @param int $limit
     *
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getAuditLogs(int $competitionId, int $limit = 20): array
    {
        $sql = "
            SELECT
                al.audit_log_id,
                al.action,
                al.module_name,
                al.ip_address,
                al.action_time,
                u.full_name AS user_name,
                u.role
            FROM audit_logs al
            LEFT JOIN users u ON u.user_id = al.user_id
            WHERE al.table_name = 'competitions'
              AND al.record_id  = :record_id
            ORDER BY al.action_time DESC
            LIMIT :lim
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':record_id', $competitionId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Update evaluation status of a competition.
     *
     * @param int $competitionId
     * @param string $status ('Pending', 'Open', 'Closed', 'Published', 'Locked')
     *
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function updateEvaluationStatus(int $competitionId, string $status): bool
    {
        $sql = "
            UPDATE competitions
            SET evaluation_status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE competition_id = :id
        ";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'status' => $status,
            'id' => $competitionId
        ]);
    }
}
