<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\SymposiumEventModel;
use App\Models\GeneratedCertificateModel;
use App\Models\AttendanceSessionModel;
use App\Models\AttendanceRecordModel;
use PDO;
use Exception;

class CertificateEligibilityService
{
    private PDO $db;
    private TeamResolverService $teamResolver;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->teamResolver = new TeamResolverService();
    }

    /**
     * Determine eligible recipients for an event.
     * Enforces that the event must be locked and results published.
     * Returns a flat array of recipients (one per student), generating multiple entries
     * for a team application.
     *
     * @deprecated Preserved for backward compatibility. Use determineWinnerRecipients() or determineParticipantRecipients().
     */
    public function getEligibleRecipients(int $symposiumEventId, bool $includeParticipation = false): array
    {
        // 1. Verify Event is Locked and Published
        $eventModel = new SymposiumEventModel();
        $event = $eventModel->findById($symposiumEventId);
        
        if (!$event || !$event['is_locked']) {
            throw new Exception("Event must be locked and results published before determining certificate eligibility.");
        }

        // 2. Fetch published results that are eligible
        $sql = "
            SELECT r.*, a.application_type, a.symposium_event_id, 
                   e.event_name, e.event_date, v.venue_name, 
                   COALESCE(s.title, e.event_name) as symposium_title
            FROM competition_results r
            JOIN applications a ON r.application_id = a.application_id
            JOIN symposium_events e ON r.symposium_event_id = e.symposium_event_id
            JOIN symposiums s ON e.symposium_id = s.symposium_id
            LEFT JOIN venues v ON e.venue_id = v.venue_id
            WHERE r.symposium_event_id = :event_id
              AND r.published = 1
        ";
        
        if (!$includeParticipation) {
            // Only ranks 1, 2, 3
            $sql .= " AND r.rank_position <= 3 ";
        } else {
            // Disqualified participants never get certificates
            $sql .= " AND r.result_status != 'Disqualified' ";
        }
        
        $sql .= " ORDER BY r.rank_position ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['event_id' => $symposiumEventId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return [];
        }

        // 3. Resolve Team/Individual members
        $applications = array_map(function($r) {
            return [
                'application_id' => $r['application_id'],
                'application_type' => $r['application_type'],
                'symposium_event_id' => $r['symposium_event_id']
            ];
        }, $results);

        $resolvedParticipants = $this->teamResolver->resolveParticipantsForApplications($applications);

        // 4. Flatten into recipient targets
        $recipients = [];

        foreach ($results as $result) {
            $appId = (int)$result['application_id'];
            $participant = $resolvedParticipants[$appId] ?? null;
            
            if (!$participant) {
                continue;
            }

            $baseData = $result; // event and result data

            if ($participant['participant_type'] === 'individual') {
                $indiv = $participant['participant'] ?? [];
                if (!empty($indiv)) {
                    $recipients[] = $this->buildRecipient($baseData, $indiv);
                }
            } else {
                $members = $participant['members'] ?? [];
                foreach ($members as $member) {
                    $recipients[] = $this->buildRecipient($baseData, $member);
                }
            }
        }

        return $recipients;
    }

    /**
     * Return all eligible Winner certificate recipients for an event.
     * Eligibility: rank_position IN (1,2,3) AND results published AND not Disqualified.
     * Does NOT filter on result_status text — only rank_position.
     * Does NOT require attendance.
     *
     * Each returned element contains:
     *   application_id, recipient_student_id, recipient_name, rank_position,
     *   rank_label (from CertificateRankService), result_id, result_status,
     *   certificate_type = 'Winner', generation_data (all event/student fields)
     *
     * @param int $eventId
     * @return array
     * @throws \RuntimeException if event not found or results not published
     */
    public function determineWinnerRecipients(int $eventId): array
    {
        // Verify event exists
        $eventModel = new SymposiumEventModel();
        $event = $eventModel->findById($eventId);

        if (!$event) {
            throw new \RuntimeException("Event not found: {$eventId}");
        }

        // Fetch published winner results (rank 1, 2, 3) — not Disqualified
        $sql = "
            SELECT
                r.result_id, r.application_id, r.rank_position, r.result_status,
                r.published,
                a.application_type, a.symposium_event_id,
                e.event_name, e.event_date,
                COALESCE(sym.title, e.event_name) AS symposium_title
            FROM competition_results r
            JOIN applications a       ON r.application_id      = a.application_id
            JOIN symposium_events e   ON r.symposium_event_id  = e.symposium_event_id
            JOIN symposiums sym        ON e.symposium_id        = sym.symposium_id
            WHERE r.symposium_event_id = :eid
              AND r.published = 1
              AND r.rank_position IN (1, 2, 3)
              AND (r.result_status IS NULL OR r.result_status != 'Disqualified')
            ORDER BY r.rank_position ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['eid' => $eventId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($results)) {
            return [];
        }

        // Build application stubs for TeamResolver
        $applications = array_map(fn($r) => [
            'application_id'    => $r['application_id'],
            'application_type'  => $r['application_type'],
            'symposium_event_id'=> $r['symposium_event_id'],
        ], $results);

        $resolvedParticipants = $this->teamResolver->resolveParticipantsForApplications($applications);

        $rankService = new CertificateRankService();
        $recipients  = [];

        foreach ($results as $result) {
            $appId       = (int)$result['application_id'];
            $rankPos     = (int)$result['rank_position'];
            $participant = $resolvedParticipants[$appId] ?? null;

            if (!$participant) {
                continue;
            }

            $rankLabel = $rankService->getCertificateDisplayLabel('Winner', $rankPos);

            $members = $participant['members'] ?? [];
            if ($participant['participant_type'] === 'individual') {
                $indiv = $participant['participant'] ?? [];
                if (!empty($indiv)) {
                    $members = [$indiv];
                } else {
                    $members = [];
                }
            }

            foreach ($members as $member) {
                $genData = $this->buildWinnerGenerationData($result, $member, $rankLabel);

                $recipients[] = [
                    'application_id'      => $appId,
                    'recipient_student_id'=> (int)($member['student_id'] ?? 0),
                    'recipient_name'      => $member['name'] ?? '',
                    'rank_position'       => $rankPos,
                    'rank_label'          => $rankLabel,
                    'result_id'           => (int)$result['result_id'],
                    'result_status'       => $result['result_status'],
                    'certificate_type'    => 'Winner',
                    'generation_data'     => $genData,
                ];
            }
        }

        return $recipients;
    }

    /**
     * Return all eligible Participant certificate recipients for an event.
     * Eligibility: attendance_status IN ('Present','Late') in the most recently
     * closed attendance session for this event.
     * Does NOT require a competition result to exist.
     *
     * Each returned element contains:
     *   application_id, recipient_student_id, recipient_name,
     *   rank_position = null, result_id = null, result_status = null,
     *   certificate_type = 'Participant',
     *   attendance_session_id (the authoritative session id),
     *   attendance_status ('Present' or 'Late'),
     *   generation_data (all event/student fields)
     *
     * @param int $eventId
     * @return array
     * @throws \RuntimeException if no closed attendance session found
     */
    public function determineParticipantRecipients(int $eventId): array
    {
        $sessionModel = new AttendanceSessionModel();
        $session = $sessionModel->getFinalizedEventSession($eventId);

        if (!$session) {
            throw new \RuntimeException('No finalized attendance session found for event.');
        }

        $sessionId = (int)$session['session_id'];

        // Fetch attendance records for that session with Present or Late status,
        // joined to applications, students, departments, symposium_events
        $sql = "
            SELECT
                ar.attendance_id, ar.attendance_status,
                ar.application_id, ar.student_id,
                a.application_type, a.symposium_event_id,
                e.event_name, e.event_date,
                COALESCE(sym.title, e.event_name) AS symposium_title
            FROM attendance_records ar
            JOIN applications a      ON ar.application_id     = a.application_id
            JOIN symposium_events e  ON ar.symposium_event_id = e.symposium_event_id
            JOIN symposiums sym       ON e.symposium_id        = sym.symposium_id
            WHERE ar.session_id         = :session_id
              AND ar.symposium_event_id = :eid
              AND ar.attendance_status IN ('Present', 'Late')
            ORDER BY ar.application_id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['session_id' => $sessionId, 'eid' => $eventId]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($records)) {
            return [];
        }

        // Build application stubs for TeamResolver
        $applications = array_map(fn($r) => [
            'application_id'    => $r['application_id'],
            'application_type'  => $r['application_type'],
            'symposium_event_id'=> $r['symposium_event_id'],
        ], $records);

        // Deduplicate by application_id (same app could appear multiple times for teams in attendance)
        $uniqueApps = [];
        foreach ($applications as $app) {
            $uniqueApps[(int)$app['application_id']] = $app;
        }
        $uniqueApps = array_values($uniqueApps);

        $resolvedParticipants = $this->teamResolver->resolveParticipantsForApplications($uniqueApps);

        // Build a map: application_id => attendance_status (first match wins)
        $attendanceStatusByApp = [];
        foreach ($records as $rec) {
            $appId = (int)$rec['application_id'];
            if (!isset($attendanceStatusByApp[$appId])) {
                $attendanceStatusByApp[$appId] = [
                    'attendance_status' => $rec['attendance_status'],
                    'row'               => $rec,
                ];
            }
        }

        // Fetch winner recipient IDs to exclude them from participants
        $winnerStudentIds = [];
        try {
            $winners = $this->determineWinnerRecipients($eventId);
            foreach ($winners as $w) {
                if (!empty($w['recipient_student_id'])) {
                    $winnerStudentIds[] = (int)$w['recipient_student_id'];
                }
            }
        } catch (\Throwable $e) {
            // If winner resolution fails (e.g., results not published yet), we don't block participant generation,
            // but we cannot filter them.
        }
        $winnerStudentIds = array_unique($winnerStudentIds);

        $recipients = [];

        foreach ($attendanceStatusByApp as $appId => $atInfo) {
            $participant = $resolvedParticipants[$appId] ?? null;
            if (!$participant) {
                continue;
            }

            $record = $atInfo['row'];
            $attendanceStatus = $atInfo['attendance_status'];

            $members = $participant['members'] ?? [];
            if ($participant['participant_type'] === 'individual') {
                $indiv = $participant['participant'] ?? [];
                if (!empty($indiv)) {
                    $members = [$indiv];
                } else {
                    $members = [];
                }
            }

            foreach ($members as $member) {
                $studentId = (int)($member['student_id'] ?? 0);
                
                // CRITICAL BUGFIX: "for winners - there should not generate participant certificate"
                if ($studentId > 0 && in_array($studentId, $winnerStudentIds, true)) {
                    continue; // Skip this student, they are already a winner
                }

                $genData = $this->buildParticipantGenerationData(
                    $record,
                    $member,
                    $sessionId,
                    $attendanceStatus
                );

                $recipients[] = [
                    'application_id'       => $appId,
                    'recipient_student_id' => $studentId,
                    'recipient_name'       => $member['name'] ?? '',
                    'rank_position'        => null,
                    'rank_label'           => 'Participation',
                    'result_id'            => null,
                    'result_status'        => null,
                    'certificate_type'     => 'Participant',
                    'attendance_session_id'=> $sessionId,
                    'attendance_status'    => $attendanceStatus,
                    'generation_data'      => $genData,
                ];
            }
        }

        return $recipients;
    }

    /**
     * Return preflight counts for Winner certificates for an event.
     *
     * @param int $eventId
     * @return array{eligible: int, generated: int, missing: int, failed: int, error: string|null}
     */
    public function getWinnerPreflight(int $eventId): array
    {
        $eligible = 0;
        $error    = null;

        try {
            $recipients = $this->determineWinnerRecipients($eventId);
            $eligible   = count($recipients);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $certModel = new GeneratedCertificateModel();
        $generated = $certModel->countByEvent($eventId, 'Winner');

        // Count Failed separately
        $failed = $this->countFailedByEventAndType($eventId, 'Winner');

        return [
            'eligible'  => $eligible,
            'generated' => $generated,
            'missing'   => max(0, $eligible - $generated),
            'failed'    => $failed,
            'error'     => $error,
        ];
    }

    /**
     * Return preflight counts for Participant certificates for an event.
     *
     * @param int $eventId
     * @return array{eligible: int, generated: int, missing: int, failed: int, error: string|null}
     */
    public function getParticipantPreflight(int $eventId): array
    {
        $eligible = 0;
        $error    = null;

        try {
            $recipients = $this->determineParticipantRecipients($eventId);
            $eligible   = count($recipients);
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $certModel = new GeneratedCertificateModel();
        $generated = $certModel->countByEvent($eventId, 'Participant');

        // Count Failed separately
        $failed = $this->countFailedByEventAndType($eventId, 'Participant');

        return [
            'eligible'  => $eligible,
            'generated' => $generated,
            'missing'   => max(0, $eligible - $generated),
            'failed'    => $failed,
            'error'     => $error,
        ];
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function buildRecipient(array $baseData, array $studentData): array
    {
        $genData = array_merge($baseData, [
            'student_name'    => $studentData['name'] ?? '',
            'register_number' => $studentData['register_number'] ?? '',
            'department_name' => $studentData['department'] ?? '',
            'academic_year'   => $this->toRomanYear($studentData['academic_year'] ?? ''),
            'gender'          => $studentData['gender'] ?? '',
            'recipient_name'  => $studentData['name'] ?? '',
        ]);

        return [
            'application_id'       => (int)$baseData['application_id'],
            'recipient_student_id' => (int)($studentData['student_id'] ?? 0),
            'recipient_name'       => $studentData['name'] ?? '',
            'rank_position'        => (int)$baseData['rank_position'],
            'result_status'        => $baseData['result_status'],
            'result_id'            => (int)$baseData['result_id'],
            'generation_data'      => $genData
        ];
    }

    private function buildWinnerGenerationData(array $result, array $member, string $rankLabel): array
    {
        return [
            'event_name'           => $result['event_name'] ?? '',
            'symposium_title'      => $result['symposium_title'] ?? '',
            'event_date'           => $result['event_date'] ?? '',
            'department_name'      => $member['department'] ?? '',
            'academic_year'        => $this->toRomanYear($member['academic_year'] ?? ''),
            'register_number'      => $member['register_number'] ?? '',
            'recipient_name'       => $member['name'] ?? '',
            'student_name'         => $member['name'] ?? '',
            'gender'               => $member['gender'] ?? '',
            'rank_position'        => (int)$result['rank_position'],
            'result_status'        => $result['result_status'],
            'rank_label'           => $rankLabel,
            'certificate_type'     => 'Winner',
            'attendance_status'    => null,
            'attendance_session_id'=> null,
            // Pass through raw result fields for downstream consumers
            'result_id'            => (int)$result['result_id'],
            'application_id'       => (int)$result['application_id'],
            'symposium_event_id'   => (int)$result['symposium_event_id'],
        ];
    }

    private function buildParticipantGenerationData(
        array $record,
        array $member,
        int $sessionId,
        string $attendanceStatus
    ): array {
        return [
            'event_name'           => $record['event_name'] ?? '',
            'symposium_title'      => $record['symposium_title'] ?? '',
            'event_date'           => $record['event_date'] ?? '',
            'department_name'      => $member['department'] ?? '',
            'academic_year'        => $this->toRomanYear($member['academic_year'] ?? ''),
            'register_number'      => $member['register_number'] ?? '',
            'recipient_name'       => $member['name'] ?? '',
            'student_name'         => $member['name'] ?? '',
            'gender'               => $member['gender'] ?? '',
            'rank_position'        => null,
            'result_status'        => null,
            'rank_label'           => 'Participation',
            'certificate_type'     => 'Participant',
            'attendance_status'    => $attendanceStatus,
            'attendance_session_id'=> $sessionId,
            // Pass through raw record fields for downstream consumers
            'result_id'            => null,
            'application_id'       => (int)$record['application_id'],
            'symposium_event_id'   => (int)$record['symposium_event_id'],
        ];
    }

    /**
     * Count Failed-status certificates for an event and type.
     */
    private function countFailedByEventAndType(int $eventId, string $certType): int
    {
        $sql = "
            SELECT COUNT(*) 
            FROM generated_certificates 
            WHERE symposium_event_id = :event_id 
              AND certificate_type   = :cert_type 
              AND generation_status  = 'Failed'
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['event_id' => $eventId, 'cert_type' => $certType]);
        return (int)$stmt->fetchColumn();
    }

    private function toRomanYear(int|string $year): string
    {
        $map = [
            '1' => 'I',  '2' => 'II',  '3' => 'III',  '4' => 'IV',
            'I' => 'I',  'II' => 'II', 'III' => 'III', 'IV' => 'IV',
        ];
        $trimmed = trim((string)$year);
        return $map[$trimmed] ?? $trimmed;
    }
}
