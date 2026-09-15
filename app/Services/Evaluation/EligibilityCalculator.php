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
     * Determines and freezes the participant set.
     * Only Approved applications are eligible.
     * If attendance is supported by the event, they must also be Present.
     * 
     * @return array Array of eligible application data, keyed by application_id
     */
    public function getEligibleParticipants(int $symposiumEventId, bool $requiresAttendance): array
    {
        if ($requiresAttendance) {
            $sql = "
                SELECT a.*, a.application_no, s.full_name as student_name, s.register_number, d.department_name
                FROM applications a
                JOIN students s ON a.student_id = s.student_id
                JOIN departments d ON s.department_id = d.department_id
                WHERE a.symposium_event_id = :eid 
                  AND a.application_status = 'Approved' 
                  AND EXISTS (
                      SELECT 1 FROM attendance_records ar 
                      WHERE ar.application_id = a.application_id 
                        AND ar.attendance_status = 'Present'
                  )
                ORDER BY a.application_id ASC
            ";
        } else {
            $sql = "
                SELECT a.*, a.application_no, s.full_name as student_name, s.register_number, d.department_name
                FROM applications a
                JOIN students s ON a.student_id = s.student_id
                JOIN departments d ON s.department_id = d.department_id
                WHERE a.symposium_event_id = :eid 
                  AND a.application_status = 'Approved'
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
}
