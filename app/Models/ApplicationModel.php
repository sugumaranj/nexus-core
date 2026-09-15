<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : ApplicationModel.php
 * Location    : app/Models/
 * Description : All database operations for the applications table.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use App\Database\Database;
use PDO;

final class ApplicationModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Insert a new application row.
     *
     * @param array $data
     * @return int
     */
    public function insert(array $data): int
    {
        $status = $data['application_status'] ?? 'Approved';
        $approvalStatus = $data['approval_status'] ?? 'Approved';
        $approvedAt = ($status === 'Approved') ? date('Y-m-d H:i:s') : null;

        $sql = "
            INSERT INTO applications
                (application_no, competition_id, symposium_event_id, student_id, application_type,
                 application_status, approval_status, approved_at)
            VALUES
                (:application_no, :competition_id, :symposium_event_id, :student_id, :application_type,
                 :application_status, :approval_status, :approved_at)
        ";

        $stmt = $this->db->prepare($sql);

        $ok = $stmt->execute([
            'application_no'     => 'TEMP-' . uniqid(),
            'competition_id'     => !empty($data['competition_id']) ? (int)$data['competition_id'] : null,
            'symposium_event_id' => !empty($data['symposium_event_id']) ? (int)$data['symposium_event_id'] : null,
            'student_id'         => (int) ($data['student_id'] ?? 0),
            'application_type'   => $data['application_type'] ?? 'Individual',
            'application_status' => $status,
            'approval_status'    => $approvalStatus,
            'approved_at'        => $approvedAt,
        ]);

        if (!$ok) {
            return 0;
        }

        return (int) $this->db->lastInsertId();
    }

    /**
     * Set the permanent application_no on an existing row.
     *
     * @param int $applicationId
     * @return string
     */
    public function setApplicationNo(int $applicationId): string
    {
        $applicationNo = sprintf('APP-%d-%06d', (int) date('Y'), $applicationId);

        $stmt = $this->db->prepare(
            "UPDATE applications SET application_no = :no WHERE application_id = :id"
        );

        $stmt->execute([
            'no' => $applicationNo,
            'id' => $applicationId,
        ]);

        return $applicationNo;
    }

    /**
     * Find a single application by its primary key.
     *
     * @param int $applicationId
     * @return array|false
     */
    public function findById(int $applicationId): array|false
    {
        $sql = "
            SELECT
                a.*,
                s.full_name       AS creator_name,
                s.register_number AS creator_register_number,
                s.email           AS student_email,
                s.gender          AS student_gender,
                s.academic_year   AS student_year,
                s.semester        AS student_semester,
                d.department_name AS student_department,
                c.title           AS competition_title,
                c.competition_code,
                c.participation_type,
                c.min_team_size,
                c.max_team_size,
                c.max_participants,
                c.registration_limit,
                c.registration_deadline,
                c.event_date,
                c.start_time,
                c.end_time,
                c.status          AS competition_status,
                sy.title          AS symposium_title,
                sy.status         AS symposium_status,
                sy.registration_start,
                sy.registration_end,
                u.full_name       AS approved_by_name
            FROM applications a
            INNER JOIN students s    ON s.student_id    = a.student_id
            LEFT  JOIN teams t       ON t.application_id = a.application_id
            INNER JOIN departments d ON d.department_id = s.department_id
            LEFT  JOIN competitions c ON c.competition_id = a.competition_id
            LEFT  JOIN symposiums sy  ON sy.symposium_id  = c.symposium_id OR sy.symposium_id = a.symposium_event_id
            LEFT  JOIN users u        ON u.user_id        = a.approved_by
            WHERE a.application_id = :application_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':application_id', $applicationId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find an application by student + competition.
     *
     * @param int $studentId
     * @param int $competitionId
     * @return array|false
     */
    public function findByStudentAndCompetition(int $studentId, int $competitionId): array|false
    {
        $sql = "
            SELECT *
            FROM applications
            WHERE student_id     = :student_id
              AND competition_id = :competition_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'student_id'     => $studentId,
            'competition_id' => $competitionId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find an application by student + symposium_event_id.
     *
     * @param int $studentId
     * @param int $symposiumEventId
     * @return array|false
     */
    public function findByStudentAndSymposiumEvent(int $studentId, int $symposiumEventId): array|false
    {
        $sql = "
            SELECT *
            FROM applications
            WHERE student_id         = :student_id
              AND symposium_event_id = :symposium_event_id
              AND application_status NOT IN ('Withdrawn', 'Cancelled')
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'student_id'         => $studentId,
            'symposium_event_id' => $symposiumEventId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Count active registrations for a competition.
     *
     * @param int $competitionId
     * @return int
     */
    public function countActiveByCompetition(int $competitionId): int
    {
        $sql = "
            SELECT COUNT(*) AS total
            FROM applications
            WHERE competition_id     = :competition_id
              AND application_status NOT IN ('Withdrawn', 'Cancelled')
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':competition_id', $competitionId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Count active registrations for a symposium event.
     *
     * @param int $symposiumEventId
     * @return int
     */
    public function countActiveBySymposiumEvent(int $symposiumEventId): int
    {
        $sql = "
            SELECT COUNT(*) AS total
            FROM applications
            WHERE symposium_event_id = :symposium_event_id
              AND application_status NOT IN ('Withdrawn', 'Cancelled')
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':symposium_event_id', $symposiumEventId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Get the active registered competitions for a student.
     *
     * @param int $studentId
     * @return array
     */
    public function getActiveCompetitionSchedule(int $studentId): array
    {
        $sql = "
            SELECT
                a.application_id,
                a.competition_id,
                c.title        AS competition_title,
                c.event_date,
                c.start_time,
                c.end_time
            FROM applications a
            INNER JOIN competitions c ON c.competition_id = a.competition_id
            WHERE a.student_id         = :student_id
              AND a.application_status NOT IN ('Withdrawn', 'Cancelled', 'Rejected')
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve all applications for a student (history view).
     *
     * @param int $studentId
     * @return array
     */
    public function getByStudent(int $studentId): array
    {
        $sql = "
            SELECT
                a.*,
                c.title           AS competition_title,
                c.competition_code,
                c.event_date,
                c.participation_type,
                sy.title          AS symposium_title,
                u.full_name       AS approved_by_name,
                se.event_name,
                se.event_code
            FROM applications a
            LEFT  JOIN teams t        ON t.application_id = a.application_id
            LEFT  JOIN competitions c ON c.competition_id = a.competition_id
            LEFT  JOIN symposium_events se ON se.symposium_event_id = a.symposium_event_id
            LEFT  JOIN symposiums sy  ON sy.symposium_id  = c.symposium_id OR sy.symposium_id = se.symposium_id
            LEFT  JOIN users u        ON u.user_id        = a.approved_by
            WHERE a.student_id = :student_id
            ORDER BY a.applied_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve all applications with optional filters.
     *
     * @param string|null $status
     * @param int|null    $competitionId
     * @param int|null    $symposiumId
     * @param string|null $search
     * @param int|null    $coordinatorUserId
     * @return array
     */
    public function getAll(
        ?string $status = null,
        ?int    $competitionId = null,
        ?int    $symposiumId = null,
        ?string $search = null,
        ?int    $coordinatorUserId = null
    ): array {

        $conditions = ['1=1'];
        $params     = [];

        if ($status !== null && $status !== '') {
            $conditions[] = 'a.application_status = :status';
            $params['status'] = $status;
        }

        if ($competitionId !== null) {
            $conditions[] = 'a.competition_id = :competition_id';
            $params['competition_id'] = $competitionId;
        }

        if ($symposiumId !== null) {
            $conditions[] = '(c.symposium_id = :symposium_id OR se.symposium_id = :symposium_id)';
            $params['symposium_id'] = $symposiumId;
        }

        if ($search !== null && trim($search) !== '') {
            $conditions[] = '(s.full_name LIKE :search OR s.register_number LIKE :search2)';
            $params['search']  = '%' . trim($search) . '%';
            $params['search2'] = '%' . trim($search) . '%';
        }

        $coordinatorJoin = '';
        if ($coordinatorUserId !== null) {
            $coordinatorJoin = "
                INNER JOIN competition_coordinators cc
                    ON cc.competition_id = c.competition_id
                   AND cc.user_id        = :coordinator_user_id
            ";
            $params['coordinator_user_id'] = $coordinatorUserId;
        }

        $where = implode(' AND ', $conditions);

        $sql = "
            SELECT
                a.*,
                s.full_name         AS creator_name,
                s.register_number   AS creator_register_number,
                s.gender            AS student_gender,
                d.department_name   AS student_department,
                c.title             AS competition_title,
                c.competition_code,
                c.event_date,
                c.participation_type,
                sy.title            AS symposium_title,
                u.full_name         AS approved_by_name
            FROM applications a
            INNER JOIN students    s  ON s.student_id    = a.student_id
            LEFT  JOIN teams       t  ON t.application_id = a.application_id
            INNER JOIN departments d  ON d.department_id = s.department_id
            LEFT  JOIN competitions c ON c.competition_id = a.competition_id
            LEFT  JOIN symposium_events se ON se.symposium_event_id = a.symposium_event_id
            LEFT  JOIN symposiums   sy ON sy.symposium_id = c.symposium_id OR sy.symposium_id = se.symposium_id
            LEFT  JOIN users        u  ON u.user_id       = a.approved_by
            {$coordinatorJoin}
            WHERE {$where}
            ORDER BY a.applied_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update the application status.
     *
     * @param int         $applicationId
     * @param string      $status
     * @param int|null    $approvedBy
     * @param string|null $remarks
     * @return bool
     */
    public function updateStatus(
        int     $applicationId,
        string  $status,
        ?int    $approvedBy = null,
        ?string $remarks = null
    ): bool {

        $approvalStatus = match ($status) {
            'Approved' => 'Approved',
            'Rejected' => 'Rejected',
            default    => 'Pending',
        };

        $approvedAt = ($approvedBy !== null) ? date('Y-m-d H:i:s') : null;

        $sql = "
            UPDATE applications
            SET
                application_status = :application_status,
                approval_status    = :approval_status,
                approved_by        = :approved_by,
                approved_at        = :approved_at,
                remarks            = :remarks
            WHERE application_id   = :application_id
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'application_status' => $status,
            'approval_status'    => $approvalStatus,
            'approved_by'        => $approvedBy,
            'approved_at'        => $approvedAt,
            'remarks'            => $remarks,
            'application_id'     => $applicationId,
        ]);
    }

    /**
     * Get for report
     *
     * @param int $competitionId
     * @param array $filters
     * @return array
     */
    public function getForReport(int $competitionId, array $filters = []): array
    {
        $conditions = ['a.competition_id = :competition_id'];
        $params     = ['competition_id' => $competitionId];

        if (!empty($filters['department_id'])) {
            $conditions[] = 's.department_id = :department_id';
            $params['department_id'] = (int) $filters['department_id'];
        }
        if (!empty($filters['academic_year'])) {
            $conditions[] = 's.academic_year = :academic_year';
            $params['academic_year'] = (int) $filters['academic_year'];
        }
        if (!empty($filters['application_status']) && $filters['application_status'] !== 'All') {
            $conditions[] = 'a.application_status = :application_status';
            $params['application_status'] = $filters['application_status'];
        } else {
            $conditions[] = "a.application_status NOT IN ('Withdrawn', 'Cancelled')";
        }
        if (!empty($filters['application_type']) && $filters['application_type'] !== 'All') {
            $conditions[] = 'a.application_type = :application_type';
            $params['application_type'] = $filters['application_type'];
        }

        $where = implode(' AND ', $conditions);
        $sql = "
            SELECT
                a.application_id,
                a.application_no,
                a.application_type,
                a.application_status,
                a.approval_status,
                a.applied_at,
                s.student_id,
                s.full_name         AS student_name,
                s.register_number,
                s.gender            AS student_gender,
                s.phone             AS student_phone,
                s.academic_year     AS student_year,
                s.semester          AS student_semester,
                s.profile_photo,
                d.department_name   AS student_department,
                d.department_id     AS student_department_id,
                t.team_id,
                t.manager_student_id
            FROM applications a
            INNER JOIN students    s  ON s.student_id    = a.student_id
            INNER JOIN departments d  ON d.department_id = s.department_id
            LEFT  JOIN teams       t  ON t.application_id = a.application_id
            WHERE {$where}
            ORDER BY
                s.academic_year   ASC,
                d.department_name ASC,
                s.full_name       ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get team members for report
     *
     * @param int $competitionId
     * @return array
     */
    public function getTeamMembersForReport(int $competitionId): array
    {
        $sql = "
            SELECT
                t.application_id,
                t.team_id,
                tm.team_member_id,
                s.full_name       AS member_name,
                s.register_number AS member_register_number,
                s.academic_year   AS member_year,
                s.gender          AS member_gender,
                d.department_name AS member_department
            FROM applications a
            INNER JOIN teams        t  ON t.application_id = a.application_id
            INNER JOIN team_members tm ON tm.team_id       = t.team_id
            INNER JOIN students     s  ON s.student_id     = tm.student_id
            INNER JOIN departments  d  ON d.department_id  = s.department_id
            WHERE a.competition_id = :competition_id
            ORDER BY
                t.application_id ASC,
                s.full_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':competition_id', $competitionId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['application_id']][] = $row;
        }
        return $indexed;
    }

    /**
     * Get status counts by competition
     *
     * @param int $competitionId
     * @return array
     */
    public function getStatusCountsByCompetition(int $competitionId): array
    {
        $sql = "
            SELECT application_status, COUNT(*) AS total
            FROM applications
            WHERE competition_id = :competition_id
            GROUP BY application_status
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':competition_id', $competitionId, PDO::PARAM_INT);
        $stmt->execute();

        $rows   = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['application_status']] = (int) $row['total'];
        }
        return $result;
    }

    /**
     * Get dashboard stats
     *
     * @param int|null $coordinatorUserId
     * @return array
     */
    public function getDashboardStats(?int $coordinatorUserId = null): array
    {
        $coordinatorJoin = '';
        $params          = [];

        if ($coordinatorUserId !== null) {
            $coordinatorJoin = "
                INNER JOIN competition_coordinators cc
                    ON cc.competition_id = a.competition_id
                   AND cc.user_id        = :coordinator_user_id
            ";
            $params['coordinator_user_id'] = $coordinatorUserId;
        }

        $sql = "
            SELECT
                SUM(a.application_status NOT IN ('Withdrawn', 'Cancelled')) AS total,
                SUM(a.application_status = 'Pending')                AS pending,
                SUM(a.application_status = 'Approved')               AS approved,
                SUM(a.application_status = 'Rejected')               AS rejected,
                SUM(a.application_status = 'Withdrawn')              AS withdrawn,
                SUM(a.application_status = 'Cancelled')              AS cancelled
            FROM applications a
            {$coordinatorJoin}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'     => (int) ($row['total']     ?? 0),
            'pending'   => (int) ($row['pending']   ?? 0),
            'approved'  => (int) ($row['approved']  ?? 0),
            'rejected'  => (int) ($row['rejected']  ?? 0),
            'withdrawn' => (int) ($row['withdrawn'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
        ];
    }

    // =========================================================================
    // NEW METHODS FOR EVENT-BASED REGISTRATION
    // =========================================================================

    /**
     * Get all event-based registrations for a student.
     *
     * @param int $studentId
     * @return array
     */
    public function getByStudentEventBased(int $studentId): array
    {
        $sql = "
            SELECT
                a.*,
                se.event_name,
                se.event_code,
                se.category,
                se.participation_type,
                se.min_team_size,
                se.max_team_size,
                se.event_date,
                se.start_time,
                se.end_time,
                sy.title AS symposium_title,
                sy.symposium_id,
                sy.registration_start,
                sy.registration_end,
                t.team_id,
                v.venue_name,
                v.venue_code,
                v.building_name,
                v.floor
            FROM applications a
            LEFT JOIN symposium_events se ON a.symposium_event_id = se.symposium_event_id
            LEFT JOIN symposiums sy ON se.symposium_id = sy.symposium_id
            LEFT JOIN venues v ON se.venue_id = v.venue_id
            LEFT JOIN teams t ON a.application_id = t.application_id
            WHERE (a.student_id = :creator_id OR t.team_id IN (SELECT team_id FROM team_members WHERE student_id = :member_id))
              AND a.symposium_event_id IS NOT NULL
            ORDER BY a.applied_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'creator_id' => $studentId,
            'member_id'  => $studentId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all applications for a specific symposium event (coordinator view).
     *
     * @param int $symposiumEventId
     * @param string|null $status
     * @param string|null $search
     * @return array
     */
    public function getBySymposiumEvent(int $symposiumEventId, ?string $status = null, ?string $search = null): array
    {
        $conditions = ['a.symposium_event_id = :id'];
        $params = ['id' => $symposiumEventId];

        if ($status !== null && $status !== '' && $status !== 'All') {
            $conditions[] = 'a.application_status = :status';
            $params['status'] = $status;
        } else {
            $conditions[] = "a.application_status NOT IN ('Withdrawn', 'Cancelled')";
        }

        if ($search !== null && trim($search) !== '') {
            $conditions[] = '(s.full_name LIKE :search OR s.register_number LIKE :search2)';
            $params['search']  = '%' . trim($search) . '%';
            $params['search2'] = '%' . trim($search) . '%';
        }

        $where = implode(' AND ', $conditions);

        $sql = "
            SELECT
                a.*,
                s.full_name AS student_name,
                s.register_number,
                s.academic_year,
                s.semester,
                s.gender,
                d.department_name,
                t.team_id
            FROM applications a
            INNER JOIN students s ON a.student_id = s.student_id
            INNER JOIN departments d ON s.department_id = d.department_id
            LEFT JOIN teams t ON a.application_id = t.application_id
            WHERE {$where}
            ORDER BY a.applied_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all applications across all events of a symposium.
     *
     * @param int $symposiumId
     * @param string|null $status
     * @return array
     */
    public function getBySymposium(int $symposiumId, ?string $status = null): array
    {
        $conditions = ['se.symposium_id = :symposium_id'];
        $params = ['symposium_id' => $symposiumId];

        if ($status !== null && $status !== '' && $status !== 'All') {
            $conditions[] = 'a.application_status = :status';
            $params['status'] = $status;
        } else {
            $conditions[] = "a.application_status NOT IN ('Withdrawn', 'Cancelled')";
        }

        $where = implode(' AND ', $conditions);

        $sql = "
            SELECT
                a.*,
                se.event_name,
                se.event_code,
                se.participation_type,
                s.full_name AS student_name,
                s.register_number,
                d.department_name
            FROM applications a
            INNER JOIN symposium_events se ON a.symposium_event_id = se.symposium_event_id
            INNER JOIN students s ON a.student_id = s.student_id
            INNER JOIN departments d ON s.department_id = d.department_id
            LEFT JOIN teams t ON a.application_id = t.application_id
            WHERE {$where}
            ORDER BY se.event_name ASC, s.full_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count applications by status for one event.
     *
     * @param int $symposiumEventId
     * @return array
     */
    public function getStatsForEvent(int $symposiumEventId): array
    {
        // Application-level counts (withdrawn/cancelled still tracked per application)
        $appSql = "
            SELECT
                SUM(application_status NOT IN ('Withdrawn', 'Cancelled')) AS applications,
                SUM(application_status = 'Withdrawn')  AS withdrawn,
                SUM(application_status = 'Cancelled')  AS cancelled
            FROM applications
            WHERE symposium_event_id = :symposium_event_id
        ";
        $stmt = $this->db->prepare($appSql);
        $stmt->execute(['symposium_event_id' => $symposiumEventId]);
        $appRow = $stmt->fetch(PDO::FETCH_ASSOC);

        // Participant-level counts: each team member counts individually.
        // This ensures a team of 2 shows Total=2, not Total=1.
        $partSql = "
            SELECT
                COUNT(*)                                AS total,
                SUM(application_status = 'Approved')   AS approved,
                SUM(application_status = 'Pending')    AS pending
            FROM (
                SELECT a.student_id, a.application_status
                FROM applications a
                WHERE a.symposium_event_id = :id
                  AND a.application_type   = 'Individual'
                  AND a.application_status NOT IN ('Withdrawn', 'Cancelled')

                UNION ALL

                SELECT tm.student_id, a.application_status
                FROM applications a
                INNER JOIN teams        t  ON t.application_id = a.application_id
                INNER JOIN team_members tm ON tm.team_id       = t.team_id
                WHERE a.symposium_event_id = :id2
                  AND a.application_type   = 'Team'
                  AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
            ) AS participants
        ";
        $pStmt = $this->db->prepare($partSql);
        $pStmt->execute(['id' => $symposiumEventId, 'id2' => $symposiumEventId]);
        $partRow = $pStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total'        => (int)($partRow['total']    ?? 0),
            'approved'     => (int)($partRow['approved'] ?? 0),
            'pending'      => (int)($partRow['pending']  ?? 0),
            'withdrawn'    => (int)($appRow['withdrawn'] ?? 0),
            'cancelled'    => (int)($appRow['cancelled'] ?? 0),
            'applications' => (int)($appRow['applications'] ?? 0),
        ];
    }

    /**
     * Count total applications, approved, pending, withdrawn, events covered.
     *
     * @param int $symposiumId
     * @return array
     */
    public function getStatsForSymposium(int $symposiumId): array
    {
        $sql = "
            SELECT
                SUM(a.application_status NOT IN ('Withdrawn', 'Cancelled')) AS total,
                SUM(a.application_status = 'Approved') AS approved,
                SUM(a.application_status = 'Pending') AS pending,
                SUM(a.application_status = 'Withdrawn') AS withdrawn,
                COUNT(DISTINCT IF(a.application_status NOT IN ('Withdrawn', 'Cancelled'), a.symposium_event_id, NULL)) AS event_count,
                SUM(a.application_status NOT IN ('Withdrawn', 'Cancelled') AND a.application_type = 'Individual') AS individual_count,
                SUM(a.application_status NOT IN ('Withdrawn', 'Cancelled') AND a.application_type = 'Team') AS team_count
            FROM applications a
            INNER JOIN symposium_events se ON a.symposium_event_id = se.symposium_event_id
            WHERE se.symposium_id = :symposium_id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate student_count including team members
        $studentSql = "
            SELECT COUNT(DISTINCT s_id) AS student_count FROM (
                SELECT a.student_id AS s_id 
                FROM applications a
                INNER JOIN symposium_events se ON a.symposium_event_id = se.symposium_event_id
                WHERE se.symposium_id = :sym_id AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
                UNION
                SELECT tm.student_id AS s_id 
                FROM team_members tm
                INNER JOIN teams t ON tm.team_id = t.team_id
                INNER JOIN applications a ON t.application_id = a.application_id
                INNER JOIN symposium_events se ON a.symposium_event_id = se.symposium_event_id
                WHERE se.symposium_id = :sym_id2 AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
            ) AS unique_students
        ";
        $stmt2 = $this->db->prepare($studentSql);
        $stmt2->execute(['sym_id' => $symposiumId, 'sym_id2' => $symposiumId]);
        $studentCount = (int)$stmt2->fetchColumn();

        return [
            'total'            => (int)($row['total'] ?? 0),
            'approved'         => (int)($row['approved'] ?? 0),
            'pending'          => (int)($row['pending'] ?? 0),
            'withdrawn'        => (int)($row['withdrawn'] ?? 0),
            'event_count'      => (int)($row['event_count'] ?? 0),
            'student_count'    => $studentCount,
            'individual_count' => (int)($row['individual_count'] ?? 0),
            'team_count'       => (int)($row['team_count'] ?? 0),
        ];
    }

    /**
     * Get ALL active students who have NOT registered for ANY event in this symposium.
     *
     * @param int $symposiumId
     * @param array $filters Options: 'department_id', 'academic_year'
     * @return array
     */
    public function getUnregisteredStudents(int $symposiumId, array $filters = []): array
    {
        $params = ['sym_id' => $symposiumId, 'sym_id_tm' => $symposiumId];
        
        $sql = "
            SELECT
                s.student_id,
                s.full_name,
                s.register_number,
                s.academic_year,
                s.semester,
                d.department_name
            FROM students s
            INNER JOIN departments d ON s.department_id = d.department_id
            WHERE s.department_id IN (1, 2)
            AND s.student_id NOT IN (
                SELECT a.student_id
                FROM applications a
                WHERE a.symposium_event_id IN (
                    SELECT symposium_event_id
                    FROM symposium_events
                    WHERE symposium_id = :sym_id
                )
                AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
            )
            AND s.student_id NOT IN (
                SELECT tm.student_id
                FROM team_members tm
                INNER JOIN teams t ON tm.team_id = t.team_id
                INNER JOIN applications a ON t.application_id = a.application_id
                WHERE a.symposium_event_id IN (
                    SELECT symposium_event_id
                    FROM symposium_events
                    WHERE symposium_id = :sym_id_tm
                )
                AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
            )
            AND s.account_status = 'Active'
        ";

        if (!empty($filters['department_id']) && $filters['department_id'] !== 'All') {
            $sql .= " AND s.department_id = :dept_id ";
            $params['dept_id'] = $filters['department_id'];
        }
        
        if (!empty($filters['academic_year']) && $filters['academic_year'] !== 'All') {
            $sql .= " AND s.academic_year = :year ";
            $params['year'] = $filters['academic_year'];
        }

        $sql .= " ORDER BY d.department_name ASC, s.academic_year ASC, s.full_name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count registrations grouped by department for a symposium.
     *
     * @param int $symposiumId
     * @return array
     */
    public function getDepartmentBreakdown(int $symposiumId): array
    {
        $sql = "
            SELECT
                d.department_name,
                COUNT(participants.student_id) AS total,
                SUM(participants.is_approved) AS approved
            FROM (
                SELECT 
                    a.student_id, 
                    IF(a.application_status = 'Approved', 1, 0) AS is_approved
                FROM applications a
                INNER JOIN symposium_events se ON a.symposium_event_id = se.symposium_event_id
                WHERE se.symposium_id = :sym_id AND a.application_type = 'Individual' AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
                
                UNION ALL
                
                SELECT 
                    tm.student_id,
                    IF(a.application_status = 'Approved', 1, 0) AS is_approved
                FROM team_members tm
                INNER JOIN teams t ON tm.team_id = t.team_id
                INNER JOIN applications a ON t.application_id = a.application_id
                INNER JOIN symposium_events se ON a.symposium_event_id = se.symposium_event_id
                WHERE se.symposium_id = :sym_id2 AND a.application_type = 'Team' AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
            ) AS participants
            INNER JOIN students s ON participants.student_id = s.student_id
            INNER JOIN departments d ON s.department_id = d.department_id
            GROUP BY d.department_name
            ORDER BY d.department_name ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sym_id' => $symposiumId, 'sym_id2' => $symposiumId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Summary table: one row per event showing registration counts.
     *
     * @param int $symposiumId
     * @return array
     */
    public function getEventWiseSummary(int $symposiumId): array
    {
        $sql = "
            SELECT
                se.symposium_event_id,
                se.event_name,
                se.event_code,
                se.participation_type,
                COUNT(a.application_id) AS total,
                SUM(a.application_type = 'Individual') AS individual_count,
                COUNT(DISTINCT IF(a.application_type = 'Team', t.team_id, NULL)) AS team_registrations,
                COUNT(DISTINCT IF(a.application_type = 'Team', tm.team_member_id, NULL)) AS team_member_count
            FROM symposium_events se
            LEFT JOIN applications a ON a.symposium_event_id = se.symposium_event_id
                AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
            LEFT JOIN teams t ON t.application_id = a.application_id
            LEFT JOIN team_members tm ON tm.team_id = t.team_id
            WHERE se.symposium_id = :symposium_id
            GROUP BY se.symposium_event_id
            ORDER BY se.event_name ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Full team list with all members for a symposium.
     *
     * @param int $symposiumId
     * @return array
     */
    public function getTeamReport(int $symposiumId): array
    {
        $sql = "
            SELECT
                t.team_id,
                t.manager_student_id,
                a.application_no,
                se.event_name,
                se.event_code,
                tm.student_id,
                s.full_name AS member_name,
                s.register_number AS member_reg_no,
                d.department_name AS member_dept,
                s.academic_year AS member_year,
                tm.joined_at
            FROM applications a
            INNER JOIN symposium_events se ON a.symposium_event_id = se.symposium_event_id
            INNER JOIN teams t ON a.application_id = t.application_id
            INNER JOIN team_members tm ON t.team_id = tm.team_id
            INNER JOIN students s ON tm.student_id = s.student_id
            INNER JOIN departments d ON s.department_id = d.department_id
            WHERE se.symposium_id = :id
              AND a.application_type = 'Team'
              AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
            ORDER BY se.event_name ASC, t.team_id ASC, s.full_name ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $symposiumId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Full participant list for PDF report generation.
     *
     * @param int $symposiumEventId
     * @param array $filters
     * @return array
     */
    public function getForEventReport(int $symposiumEventId, array $filters = []): array
    {
        $params = [
            'id'  => $symposiumEventId,
            'id2' => $symposiumEventId,
        ];

        // Filters are applied to the outer wrapper so they work per-participant,
        // not just per-application (critical for team events).
        $outerConds = [];

        if (!empty($filters['department_id'])) {
            $outerConds[] = 'participant_department_id = :department_id';
            $params['department_id'] = (int) $filters['department_id'];
        }
        if (!empty($filters['academic_year'])) {
            $outerConds[] = 'student_year = :academic_year';
            $params['academic_year'] = (int) $filters['academic_year'];
        }
        if (!empty($filters['application_status']) && $filters['application_status'] !== 'All') {
            $outerConds[] = 'application_status = :application_status';
            $params['application_status'] = $filters['application_status'];
        } else {
            $outerConds[] = "application_status NOT IN ('Withdrawn', 'Cancelled')";
        }
        if (!empty($filters['application_type']) && $filters['application_type'] !== 'All') {
            $outerConds[] = 'application_type = :application_type';
            $params['application_type'] = $filters['application_type'];
        }

        $outerWhere = !empty($outerConds) ? 'WHERE ' . implode(' AND ', $outerConds) : '';

        // UNION strategy: one row per participant.
        // Branch 1 → Individual applications (one row each).
        // Branch 2 → Team applications (one row per team_member, including the team leader).
        // This ensures every member (e.g. Joel) is always listed.
        $sql = "
            SELECT *
            FROM (
                -- Branch 1: Individual applications
                SELECT
                    a.application_id,
                    a.application_no,
                    a.application_type,
                    a.application_status,
                    a.applied_at,
                    a.student_id,
                    s.full_name        AS student_name,
                    s.register_number,
                    s.gender           AS student_gender,
                    s.phone            AS student_phone,
                    s.academic_year    AS student_year,
                    s.semester         AS student_semester,
                    d.department_name  AS student_department,
                    d.department_id    AS participant_department_id,
                    NULL               AS team_id,
                    a.student_id       AS creator_student_id,
                    1                  AS is_team_leader
                FROM applications a
                INNER JOIN students    s ON s.student_id    = a.student_id
                INNER JOIN departments d ON d.department_id = s.department_id
                WHERE a.symposium_event_id = :id
                  AND a.application_type   = 'Individual'

                UNION ALL

                -- Branch 2: Team members — one row per member
                SELECT
                    a.application_id,
                    a.application_no,
                    a.application_type,
                    a.application_status,
                    a.applied_at,
                    tm.student_id,
                    s.full_name        AS student_name,
                    s.register_number,
                    s.gender           AS student_gender,
                    s.phone            AS student_phone,
                    s.academic_year    AS student_year,
                    s.semester         AS student_semester,
                    d.department_name  AS student_department,
                    d.department_id    AS participant_department_id,
                    t.team_id,
                    a.student_id       AS creator_student_id,
                    IF(tm.student_id = a.student_id, 1, 0) AS is_team_leader
                FROM applications a
                INNER JOIN teams        t  ON t.application_id  = a.application_id
                INNER JOIN team_members tm ON tm.team_id        = t.team_id
                INNER JOIN students     s  ON s.student_id      = tm.student_id
                INNER JOIN departments  d  ON d.department_id   = s.department_id
                WHERE a.symposium_event_id = :id2
                  AND a.application_type   = 'Team'
            ) AS all_participants
            {$outerWhere}
            ORDER BY student_year ASC, student_department ASC, student_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get team members for event report
     *
     * @param int $symposiumEventId
     * @return array
     */
    public function getTeamMembersForEventReport(int $symposiumEventId): array
    {
        $sql = "
            SELECT
                t.application_id,
                t.team_id,
                tm.team_member_id,
                s.full_name       AS member_name,
                s.register_number AS member_register_number,
                s.academic_year   AS member_year,
                s.gender          AS member_gender,
                d.department_name AS member_department
            FROM applications a
            INNER JOIN teams        t  ON t.application_id = a.application_id
            INNER JOIN team_members tm ON tm.team_id       = t.team_id
            INNER JOIN students     s  ON s.student_id     = tm.student_id
            INNER JOIN departments  d  ON d.department_id  = s.department_id
            WHERE a.symposium_event_id = :id
            ORDER BY
                t.application_id ASC,
                s.full_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $symposiumEventId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['application_id']][] = $row;
        }
        return $indexed;
    }

    /**
     * Get distinct academic years for filtering event registrations
     *
     * @param int $symposiumEventId
     * @return array
     */
    public function getDistinctAcademicYearsForEvent(int $symposiumEventId): array
    {
        $sql = "
            SELECT DISTINCT s.academic_year
            FROM applications a
            INNER JOIN students s ON a.student_id = s.student_id
            WHERE a.symposium_event_id = :id
              AND s.academic_year IS NOT NULL
            ORDER BY s.academic_year ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $symposiumEventId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $years = [];
        foreach ($rows as $row) {
            $years[] = (int) $row['academic_year'];
        }
        return $years;
    }

    /**
     * Get a comprehensive breakdown of symposium registrations and participants
     * for the Staff Coordinator Department Report.
     *
     * @param int $symposiumId
     * @param array $filters
     * @return array
     */
    public function getComprehensiveDepartmentReport(int $symposiumId, array $filters = []): array
    {
        $conditions = ['se.symposium_id = :symposium_id'];
        $params = ['symposium_id' => $symposiumId];

        if (!empty($filters['department_id'])) {
            $conditions[] = 's.department_id = :department_id';
            $params['department_id'] = (int) $filters['department_id'];
        }
        if (!empty($filters['academic_year'])) {
            $conditions[] = 's.academic_year = :academic_year';
            $params['academic_year'] = (int) $filters['academic_year'];
        }
        if (!empty($filters['gender']) && $filters['gender'] !== 'All') {
            $conditions[] = 's.gender = :gender';
            $params['gender'] = $filters['gender'];
        }
        if (!empty($filters['application_status']) && $filters['application_status'] !== 'All') {
            $conditions[] = 'a.application_status = :application_status';
            $params['application_status'] = $filters['application_status'];
        } else {
            $conditions[] = "a.application_status NOT IN ('Withdrawn', 'Cancelled')";
        }
        if (!empty($filters['application_type']) && $filters['application_type'] !== 'All') {
            $conditions[] = 'a.application_type = :application_type';
            $params['application_type'] = $filters['application_type'];
        }

        $whereSql = implode(' AND ', $conditions);

        $sql = "
            SELECT 
                d.department_name,
                d.department_id,
                s.academic_year,
                s.gender,
                a.application_type,
                a.application_status,
                a.application_id,
                s.student_id
            FROM applications a
            INNER JOIN symposium_events se ON a.symposium_event_id = se.symposium_event_id
            LEFT JOIN teams t ON a.application_type = 'Team' AND a.application_id = t.application_id
            LEFT JOIN team_members tm ON t.team_id = tm.team_id
            INNER JOIN students s ON s.student_id = COALESCE(tm.student_id, a.student_id)
            INNER JOIN departments d ON s.department_id = d.department_id
            WHERE {$whereSql}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $report = [
            'overall' => [
                'total_applications' => 0,
                'total_participants' => 0,
                'male' => 0,
                'female' => 0,
                'individual' => 0,
                'team' => 0,
                'approved' => 0,
                'pending' => 0,
                'rejected' => 0,
                'cancelled' => 0,
                'withdrawn' => 0,
            ],
            'departments' => [],
            'years' => []
        ];

        // Process data
        $uniqueApplications = [];
        $uniqueParticipants = [];
        
        // Structures to track distincts per grouping
        $deptApps = [];
        $deptParts = [];
        $yearApps = [];
        $yearParts = [];

        foreach ($rawRows as $row) {
            $deptName = $row['department_name'];
            $year = (int)$row['academic_year'];
            $gender = $row['gender'];
            $type = $row['application_type'];
            $status = strtolower($row['application_status']);
            $appId = $row['application_id'];
            $studentId = $row['student_id'];

            // Initialize department structure
            if (!isset($report['departments'][$deptName])) {
                $report['departments'][$deptName] = [
                    'department_name' => $deptName,
                    'total_applications' => 0,
                    'total_participants' => 0,
                    'male' => 0, 'female' => 0,
                    'individual' => 0, 'team' => 0,
                    'approved' => 0, 'pending' => 0, 'rejected' => 0, 'cancelled' => 0, 'withdrawn' => 0,
                    'years' => []
                ];
                $deptApps[$deptName] = [];
                $deptParts[$deptName] = [];
            }

            // Initialize year structure overall
            if (!isset($report['years'][$year])) {
                $report['years'][$year] = [
                    'academic_year' => $year,
                    'total_applications' => 0,
                    'total_participants' => 0,
                    'male' => 0, 'female' => 0,
                    'individual' => 0, 'team' => 0,
                    'approved' => 0, 'pending' => 0, 'rejected' => 0, 'cancelled' => 0, 'withdrawn' => 0,
                ];
                $yearApps[$year] = [];
                $yearParts[$year] = [];
            }

            // Initialize year structure inside department
            if (!isset($report['departments'][$deptName]['years'][$year])) {
                $report['departments'][$deptName]['years'][$year] = [
                    'academic_year' => $year,
                    'male' => 0, 'female' => 0,
                    'total' => 0
                ];
            }

            // 1. Participant-level counting (Overall, Dept, Year)
            $participantKey = $appId . '_' . $studentId;
            
            if (!isset($uniqueParticipants[$participantKey])) {
                $uniqueParticipants[$participantKey] = true;
                $report['overall']['total_participants']++;
                $report['overall'][strtolower($gender)]++;
                if (isset($report['overall'][$status])) $report['overall'][$status]++;
            }

            if (!isset($deptParts[$deptName][$participantKey])) {
                $deptParts[$deptName][$participantKey] = true;
                $report['departments'][$deptName]['total_participants']++;
                $report['departments'][$deptName][strtolower($gender)]++;
                if (isset($report['departments'][$deptName][$status])) $report['departments'][$deptName][$status]++;
                
                // Detailed breakdown
                $report['departments'][$deptName]['years'][$year][strtolower($gender)]++;
                $report['departments'][$deptName]['years'][$year]['total']++;
            }

            if (!isset($yearParts[$year][$participantKey])) {
                $yearParts[$year][$participantKey] = true;
                $report['years'][$year]['total_participants']++;
                $report['years'][$year][strtolower($gender)]++;
                if (isset($report['years'][$year][$status])) $report['years'][$year][$status]++;
            }

            // 2. Application-level counting (Overall, Dept, Year)
            // Note: An application is counted towards a department/year if ANY of its members belong to it.
            if (!isset($uniqueApplications[$appId])) {
                $uniqueApplications[$appId] = true;
                $report['overall']['total_applications']++;
                $report['overall'][strtolower($type)]++;
            }

            if (!isset($deptApps[$deptName][$appId])) {
                $deptApps[$deptName][$appId] = true;
                $report['departments'][$deptName]['total_applications']++;
                $report['departments'][$deptName][strtolower($type)]++;
            }

            if (!isset($yearApps[$year][$appId])) {
                $yearApps[$year][$appId] = true;
                $report['years'][$year]['total_applications']++;
                $report['years'][$year][strtolower($type)]++;
            }
        }
        
        ksort($report['departments']);
        ksort($report['years']);
        foreach ($report['departments'] as &$d) {
            ksort($d['years']);
        }

        return $report;
    }
}
