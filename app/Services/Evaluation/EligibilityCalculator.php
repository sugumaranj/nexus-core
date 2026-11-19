<?php
declare(strict_types=1);

namespace App\Services\Evaluation;

use PDO;

class EligibilityCalculator
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Determines and freezes the participant set for Main Event Evaluation.
     *
     * Rules:
     * 1. Only Approved applications are eligible.
     * 2. If a finalized Prelims Shortlist exists for this event, ONLY applications
     *    with selection_status = 'Selected' are eligible for the Main Event.
     * 3. If attendance is supported by the event, they must be marked Present or Late
     *    in a Main-round attendance session (never confused with Prelims attendance).
     *
     * @return array Array of eligible application data, keyed by application_id
     */
    public function getEligibleParticipants(int $symposiumEventId, bool $requiresAttendance): array
    {
        $hasFinalizedShortlist = $this->hasFinalizedPrelimsShortlist($symposiumEventId);
        $supportsRoundColumn   = $this->attendanceHasRoundType();

        $shortlistJoin = '';
        $shortlistWhere = '';
        if ($hasFinalizedShortlist) {
            $shortlistJoin = "
                INNER JOIN prelims_shortlist ps
                    ON ps.application_id = a.application_id
                   AND ps.symposium_event_id = a.symposium_event_id
                   AND ps.selection_status = 'Selected'
            ";
        }

        if ($requiresAttendance) {
            if ($supportsRoundColumn) {
                $attendanceExists = "
                  AND EXISTS (
                      SELECT 1
                      FROM attendance_records ar
                      INNER JOIN attendance_sessions ats ON ats.session_id = ar.session_id
                      WHERE ar.application_id = a.application_id
                        AND ats.symposium_event_id = a.symposium_event_id
                        AND ats.round_type = 'Main'
                        AND ar.attendance_status IN ('Present', 'Late')
                  )
                ";
            } else {
                $attendanceExists = "
                  AND EXISTS (
                      SELECT 1 FROM attendance_records ar
                      WHERE ar.application_id = a.application_id
                        AND ar.attendance_status IN ('Present', 'Late')
                  )
                ";
            }

            $sql = "
                SELECT a.*, a.application_no, s.full_name as student_name, s.register_number, d.department_name
                FROM applications a
                {$shortlistJoin}
                JOIN students s ON a.student_id = s.student_id
                JOIN departments d ON s.department_id = d.department_id
                WHERE a.symposium_event_id = :eid
                  AND a.application_status = 'Approved'
                  {$shortlistWhere}
                  {$attendanceExists}
                ORDER BY a.application_id ASC
            ";
        } else {
            $sql = "
                SELECT a.*, a.application_no, s.full_name as student_name, s.register_number, d.department_name
                FROM applications a
                {$shortlistJoin}
                JOIN students s ON a.student_id = s.student_id
                JOIN departments d ON s.department_id = d.department_id
                WHERE a.symposium_event_id = :eid
                  AND a.application_status = 'Approved'
                  {$shortlistWhere}
                ORDER BY a.application_id ASC
            ";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['eid' => $symposiumEventId]);

        $participants = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $participants[(int)$row['application_id']] = $row;
        }

        return $participants;
    }

    private function hasFinalizedPrelimsShortlist(int $symposiumEventId): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM prelims_shortlist
                WHERE symposium_event_id = :eid
                  AND selection_status = 'Selected'
            ");
            $stmt->execute(['eid' => $symposiumEventId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function attendanceHasRoundType(): bool
    {
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM attendance_sessions LIKE 'round_type'");
            return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
