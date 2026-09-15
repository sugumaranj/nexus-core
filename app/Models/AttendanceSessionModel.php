<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : AttendanceSessionModel.php
 * Location    : app/Models/
 * Description : Database operations for the attendance_sessions table.
 *               Fully migrated to the Event-based (Symposium Event) workflow.
 *
 * Business Rule
 * -------------------------------------------------------------------------
 * A symposium event can have at most ONE Open session at a time.
 * Enforcement is done via getActiveEventSession() check in AttendanceService.
 *
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use PDO;

final class AttendanceSessionModel extends BaseModel
{
    // =========================================================================
    // CREATE
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Open a new attendance session for a Symposium Event.
     *
     * @param int         $symposiumEventId
     * @param int         $openedBy          User ID of the FIC opening the session
     * @param string|null $notes
     * @return int  New session_id, or 0 on failure.
     * -------------------------------------------------------------------------
     */
    public function openEventSession(int $symposiumEventId, int $openedBy, ?string $notes = null): int
    {
        $sql = "
            INSERT INTO attendance_sessions
                (symposium_event_id, opened_by, status, notes)
            VALUES
                (:symposium_event_id, :opened_by, 'Open', :notes)
        ";

        $stmt = $this->db->prepare($sql);
        $ok   = $stmt->execute([
            'symposium_event_id' => $symposiumEventId,
            'opened_by'          => $openedBy,
            'notes'              => $notes,
        ]);

        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Close an attendance session.
     *
     * @param int $sessionId
     * @param int $closedBy   User ID of the FIC closing the session
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function closeSession(int $sessionId, int $closedBy): bool
    {
        $sql = "
            UPDATE attendance_sessions
            SET
                status    = 'Closed',
                closed_by = :closed_by,
                closed_at = NOW()
            WHERE session_id = :session_id
              AND status     = 'Open'
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'session_id' => $sessionId,
            'closed_by'  => $closedBy,
        ]);
    }

    // =========================================================================
    // READ
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Get the currently Open session for a Symposium Event.
     *
     * @param int $symposiumEventId
     * @return array|false
     * -------------------------------------------------------------------------
     */
    public function getActiveEventSession(int $symposiumEventId): array|false
    {
        $sql = "
            SELECT s.*, u.full_name AS opened_by_name
            FROM attendance_sessions s
            LEFT JOIN users u ON u.user_id = s.opened_by
            WHERE s.symposium_event_id = :symposium_event_id
              AND s.status             = 'Open'
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':symposium_event_id', $symposiumEventId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Get a session by its primary key.
     *
     * @param int $sessionId
     * @return array|false
     * -------------------------------------------------------------------------
     */
    public function getSessionById(int $sessionId): array|false
    {
        $sql = "
            SELECT s.*,
                   uo.full_name AS opened_by_name,
                   uc.full_name AS closed_by_name
            FROM attendance_sessions s
            LEFT JOIN users uo ON uo.user_id = s.opened_by
            LEFT JOIN users uc ON uc.user_id = s.closed_by
            WHERE s.session_id = :session_id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * -------------------------------------------------------------------------
     * Get all sessions for a Symposium Event (newest first).
     *
     * @param int $symposiumEventId
     * @return array
     * -------------------------------------------------------------------------
     */
    public function getSessionsByEvent(int $symposiumEventId): array
    {
        $sql = "
            SELECT s.*,
                   uo.full_name AS opened_by_name,
                   uc.full_name AS closed_by_name
            FROM attendance_sessions s
            LEFT JOIN users uo ON uo.user_id = s.opened_by
            LEFT JOIN users uc ON uc.user_id = s.closed_by
            WHERE s.symposium_event_id = :symposium_event_id
            ORDER BY s.opened_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':symposium_event_id', $symposiumEventId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * -------------------------------------------------------------------------
     * Get the single finalized session for a Symposium Event.
     * Selects the most recently closed session.
     * Note: Controller should also verify no active open session overrides this.
     *
     * @param int $symposiumEventId
     * @return array|false
     * -------------------------------------------------------------------------
     */
    public function getFinalizedEventSession(int $symposiumEventId): array|false
    {
        $sql = "
            SELECT s.*,
                   uo.full_name AS opened_by_name,
                   uc.full_name AS closed_by_name
            FROM attendance_sessions s
            LEFT JOIN users uo ON uo.user_id = s.opened_by
            LEFT JOIN users uc ON uc.user_id = s.closed_by
            WHERE s.symposium_event_id = :symposium_event_id
              AND s.status = 'Closed'
            ORDER BY s.closed_at DESC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':symposium_event_id', $symposiumEventId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
