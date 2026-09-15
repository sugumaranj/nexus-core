<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : AttendanceRecordModel.php
 * Location    : app/Models/
 * Description : Database operations for the attendance_records table.
 *               Fully migrated to the Event-based (Symposium Event) workflow.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Mark single attendance (INSERT … ON DUPLICATE KEY UPDATE)
 * • Bulk mark in transaction
 * • Retrieve participants eligible for attendance (event registrations)
 * • Retrieve attendance records for a session
 * • Aggregate statistics (present/absent/late counts) per event
 * • Department-wise breakdown per event + session
 *
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use PDO;

final class AttendanceRecordModel extends BaseModel
{
    // =========================================================================
    // CREATE / UPSERT
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Mark attendance for a single participant.
     *
     * Uses INSERT … ON DUPLICATE KEY UPDATE so that re-marking is idempotent.
     *
     * @param array $data  Keys: session_id, symposium_event_id, application_id,
     *                           student_id, attendance_status, marked_by,
     *                           coordinator_notes (opt), sync_source (opt),
     *                           client_device_id (opt), client_timestamp (opt)
     *
     * @return int  attendance_id of inserted/updated row, 0 on failure.
     * -------------------------------------------------------------------------
     */
    public function upsertAttendance(array $data): int
    {
        $sql = "
            INSERT INTO attendance_records
                (session_id, symposium_event_id, application_id, student_id,
                 attendance_status, marked_by, marked_at,
                 coordinator_notes, sync_source,
                 client_device_id, client_timestamp)
            VALUES
                (:session_id, :symposium_event_id, :application_id, :student_id,
                 :attendance_status, :marked_by, NOW(),
                 :coordinator_notes, :sync_source,
                 :client_device_id, :client_timestamp)
            ON DUPLICATE KEY UPDATE
                attendance_status  = VALUES(attendance_status),
                updated_by         = VALUES(marked_by),
                updated_at         = NOW(),
                coordinator_notes  = VALUES(coordinator_notes),
                sync_source        = VALUES(sync_source),
                client_device_id   = VALUES(client_device_id),
                client_timestamp   = VALUES(client_timestamp)
        ";

        $stmt = $this->db->prepare($sql);
        $ok   = $stmt->execute([
            'session_id'         => (int) ($data['session_id'] ?? 0),
            'symposium_event_id' => !empty($data['symposium_event_id']) ? (int) $data['symposium_event_id'] : null,
            'application_id'     => (int) ($data['application_id'] ?? 0),
            'student_id'         => (int) ($data['student_id'] ?? 0),
            'attendance_status'  => $data['attendance_status'] ?? 'Absent',
            'marked_by'          => (int) ($data['marked_by'] ?? 0),
            'coordinator_notes'  => $data['coordinator_notes'] ?? null,
            'sync_source'        => $data['sync_source'] ?? 'Online',
            'client_device_id'   => $data['client_device_id'] ?? null,
            'client_timestamp'   => $data['client_timestamp'] ?? null,
        ]);

        if (!$ok) {
            return 0;
        }

        $lastId = (int) $this->db->lastInsertId();
        if ($lastId > 0) {
            return $lastId;
        }

        // ON DUPLICATE KEY UPDATE returns 0 for lastInsertId — fetch existing id
        $find = $this->db->prepare("
            SELECT attendance_id
            FROM attendance_records
            WHERE session_id = :session_id AND student_id = :student_id
            LIMIT 1
        ");
        $find->execute([
            'session_id' => (int) ($data['session_id'] ?? 0),
            'student_id' => (int) ($data['student_id'] ?? 0),
        ]);
        return (int) ($find->fetchColumn() ?: 0);
    }

    /**
     * -------------------------------------------------------------------------
     * Bulk mark attendance in a single transaction.
     *
     * @param array $records  Array of data arrays (same keys as upsertAttendance)
     * @return array  ['succeeded', 'failed', 'errors']
     * -------------------------------------------------------------------------
     */
    public function bulkUpsert(array $records): array
    {
        $succeeded = 0;
        $failed    = 0;
        $errors    = [];

        $this->db->beginTransaction();

        try {
            foreach ($records as $record) {
                $result = $this->upsertAttendance($record);
                if ($result > 0) {
                    $succeeded++;
                } else {
                    $failed++;
                    $errors[] = 'Failed for application_id: ' . ($record['application_id'] ?? '?');
                }
            }

            $this->db->commit();

        } catch (\Throwable $e) {
            $this->db->rollBack();
            $failed += count($records) - $succeeded;
            $errors[] = 'Transaction error: ' . $e->getMessage();
        }

        return compact('succeeded', 'failed', 'errors');
    }

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Get all registered participants for a Symposium Event, with current
     * session attendance status (LEFT JOIN — NULL means unmarked).
     *
     * Only applications not Withdrawn or Cancelled are included.
     *
     * @param int $symposiumEventId
     * @param int $sessionId     Current active session ID (0 = no session yet)
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getParticipantsForEventAttendance(int $symposiumEventId, int $sessionId = 0): array
    {
        $sql = "
            SELECT
                p.application_id,
                p.application_no,
                p.application_type,
                p.student_id,
                s.full_name         AS student_name,
                s.register_number,
                s.academic_year     AS student_year,
                s.gender,
                s.phone,
                d.department_name,
                p.manager_student_id,
                ar.attendance_id,
                ar.attendance_status,
                ar.marked_at,
                ar.coordinator_notes,
                ar.sync_source
            FROM (
                SELECT 
                    a.application_id, a.application_no, a.application_type, a.student_id, 
                    NULL AS manager_student_id
                FROM applications a
                WHERE a.symposium_event_id = :symposium_event_id 
                  AND a.application_type = 'Individual'
                  AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
                  
                UNION ALL
                
                SELECT 
                    a.application_id, a.application_no, a.application_type, tm.student_id, 
                    t.manager_student_id
                FROM team_members tm
                INNER JOIN teams t ON tm.team_id = t.team_id
                INNER JOIN applications a ON t.application_id = a.application_id
                WHERE a.symposium_event_id = :symposium_event_id2 
                  AND a.application_type = 'Team'
                  AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
            ) AS p
            INNER JOIN students s        ON s.student_id        = p.student_id
            INNER JOIN departments d     ON d.department_id     = s.department_id
            LEFT  JOIN attendance_records ar
                ON  ar.session_id           = :session_id
                AND ar.student_id           = p.student_id
                AND ar.symposium_event_id   = :symposium_event_id3
            ORDER BY d.department_name ASC, s.full_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'symposium_event_id'  => $symposiumEventId,
            'symposium_event_id2' => $symposiumEventId,
            'symposium_event_id3' => $symposiumEventId,
            'session_id'          => $sessionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * -------------------------------------------------------------------------
     * Get all attendance records for a session.
     *
     * @param int $sessionId
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getBySession(int $sessionId): array
    {
        $sql = "
            SELECT
                ar.*,
                s.full_name       AS student_name,
                s.register_number,
                d.department_name,
                u.full_name       AS marked_by_name
            FROM attendance_records ar
            INNER JOIN students    s ON s.student_id    = ar.student_id
            INNER JOIN departments d ON d.department_id = s.department_id
            LEFT  JOIN users       u ON u.user_id       = ar.marked_by
            WHERE ar.session_id = :session_id
            ORDER BY d.department_name ASC, s.full_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Get attendance summary stats for a symposium event (across all sessions).
     *
     * @param int $symposiumEventId
     * @return array  ['total_registered', 'present', 'absent', 'late', 'unmarked', 'attendance_pct']
     * -------------------------------------------------------------------------
     */
    public function getSummaryByEvent(int $symposiumEventId): array
    {
        // Total registered (not withdrawn/cancelled)
        $totalStmt = $this->db->prepare("
            SELECT COUNT(DISTINCT s_id) FROM (
                SELECT a.student_id AS s_id 
                FROM applications a
                WHERE a.symposium_event_id = :eid AND a.application_type = 'Individual'
                  AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
                UNION ALL
                SELECT tm.student_id AS s_id
                FROM team_members tm
                INNER JOIN teams t ON tm.team_id = t.team_id
                INNER JOIN applications a ON t.application_id = a.application_id
                WHERE a.symposium_event_id = :eid2 AND a.application_type = 'Team'
                  AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
            ) AS unique_students
        ");
        $totalStmt->execute(['eid' => $symposiumEventId, 'eid2' => $symposiumEventId]);
        $totalRegistered = (int) $totalStmt->fetchColumn();

        // Attendance counts from most recent session for this event
        $statsStmt = $this->db->prepare("
            SELECT
                SUM(ar.attendance_status = 'Present') AS present,
                SUM(ar.attendance_status = 'Absent')  AS absent,
                SUM(ar.attendance_status = 'Late')    AS late
            FROM attendance_records ar
            WHERE ar.symposium_event_id = :eid
              AND ar.session_id = (
                  SELECT session_id FROM attendance_sessions
                  WHERE symposium_event_id = :eid2
                  ORDER BY opened_at DESC
                  LIMIT 1
              )
        ");
        $statsStmt->execute(['eid' => $symposiumEventId, 'eid2' => $symposiumEventId]);
        $row = $statsStmt->fetch(PDO::FETCH_ASSOC);

        $present  = (int) ($row['present'] ?? 0);
        $absent   = (int) ($row['absent']  ?? 0);
        $late     = (int) ($row['late']    ?? 0);
        $marked   = $present + $absent + $late;
        $unmarked = max(0, $totalRegistered - $marked);

        return [
            'total_registered' => $totalRegistered,
            'present'          => $present,
            'absent'           => $absent,
            'late'             => $late,
            'unmarked'         => $unmarked,
            'marked'           => $marked,
            'attendance_pct'   => $totalRegistered > 0
                ? round(($present + $late) / $totalRegistered * 100, 1)
                : 0,
        ];
    }

    /**
     * -------------------------------------------------------------------------
     * Get department-wise attendance breakdown for a symposium event + session.
     *
     * @param int $symposiumEventId
     * @param int $sessionId
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getDeptBreakdownByEvent(int $symposiumEventId, int $sessionId): array
    {
        $sql = "
            SELECT
                d.department_name,
                COUNT(p.student_id)                               AS total,
                SUM(ar.attendance_status = 'Present')             AS present,
                SUM(ar.attendance_status = 'Absent')              AS absent,
                SUM(ar.attendance_status = 'Late')                AS late,
                SUM(ar.attendance_status IS NULL)                 AS unmarked
            FROM (
                SELECT 
                    a.application_id, a.student_id
                FROM applications a
                WHERE a.symposium_event_id = :symposium_event_id 
                  AND a.application_type = 'Individual'
                  AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
                  
                UNION ALL
                
                SELECT 
                    a.application_id, tm.student_id
                FROM team_members tm
                INNER JOIN teams t ON tm.team_id = t.team_id
                INNER JOIN applications a ON t.application_id = a.application_id
                WHERE a.symposium_event_id = :symposium_event_id2 
                  AND a.application_type = 'Team'
                  AND a.application_status NOT IN ('Withdrawn', 'Cancelled')
            ) AS p
            INNER JOIN students    s  ON s.student_id    = p.student_id
            INNER JOIN departments d  ON d.department_id = s.department_id
            LEFT  JOIN attendance_records ar
                ON ar.student_id     = p.student_id
               AND ar.session_id     = :session_id
            GROUP BY d.department_id, d.department_name
            ORDER BY d.department_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'symposium_event_id'  => $symposiumEventId,
            'symposium_event_id2' => $symposiumEventId,
            'session_id'          => $sessionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * -------------------------------------------------------------------------
     * Get only Present students for a finalized session for PDF generation.
     * Enforces strict cross-event and status validation.
     *
     * @param int $symposiumEventId
     * @param int $sessionId
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getPresentStudentsForClosedSession(int $symposiumEventId, int $sessionId): array
    {
        $sql = "
            SELECT
                a.application_id,
                a.application_no,
                a.application_type,
                t.team_id,
                s.register_number,
                s.full_name AS student_name,
                s.academic_year AS student_year,
                d.department_name
            FROM attendance_records ar
            INNER JOIN applications a ON a.application_id = ar.application_id
            INNER JOIN students s     ON s.student_id = ar.student_id
            INNER JOIN departments d  ON d.department_id = s.department_id
            LEFT JOIN teams t         ON t.application_id = a.application_id
            WHERE ar.session_id = :session_id
              AND ar.symposium_event_id = :symposium_event_id1
              AND a.symposium_event_id  = :symposium_event_id2
              AND ar.attendance_status  = 'Present'
              AND a.application_status  = 'Approved'
            ORDER BY a.application_id ASC, s.full_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'session_id'          => $sessionId,
            'symposium_event_id1' => $symposiumEventId,
            'symposium_event_id2' => $symposiumEventId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * -------------------------------------------------------------------------
     * Get all Approved participants for an event (for events where attendance
     * is not tracked). Used as the mark-sheet participant list fallback.
     *
     * @param int $symposiumEventId
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getApprovedParticipantsForEvent(int $symposiumEventId): array
    {
        $sql = "
            SELECT * FROM (
                -- Branch 1: Individual applications
                SELECT
                    a.application_id,
                    a.application_no,
                    a.application_type,
                    NULL AS team_id,
                    s.register_number,
                    s.full_name AS student_name,
                    s.academic_year AS student_year,
                    d.department_name
                FROM applications a
                INNER JOIN students s    ON s.student_id    = a.student_id
                INNER JOIN departments d ON d.department_id = s.department_id
                WHERE a.symposium_event_id = :id1
                  AND a.application_status = 'Approved'
                  AND a.application_type   = 'Individual'

                UNION ALL

                -- Branch 2: Team applications (all members)
                SELECT
                    a.application_id,
                    a.application_no,
                    a.application_type,
                    t.team_id,
                    s.register_number,
                    s.full_name AS student_name,
                    s.academic_year AS student_year,
                    d.department_name
                FROM applications a
                INNER JOIN teams t         ON t.application_id = a.application_id
                INNER JOIN team_members tm ON tm.team_id       = t.team_id
                INNER JOIN students s      ON s.student_id     = tm.student_id
                INNER JOIN departments d   ON d.department_id  = s.department_id
                WHERE a.symposium_event_id = :id2
                  AND a.application_status = 'Approved'
                  AND a.application_type   = 'Team'
            ) AS participants
            ORDER BY application_id ASC, student_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id1' => $symposiumEventId,
            'id2' => $symposiumEventId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

