<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\SymposiumEventModel;
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

    private function buildRecipient(array $baseData, array $studentData): array
    {
        $genData = array_merge($baseData, [
            'student_name' => $studentData['name'] ?? '',
            'register_number' => $studentData['register_number'] ?? '',
            'department_name' => $studentData['department'] ?? '',
            'academic_year' => $this->toRomanYear($studentData['academic_year'] ?? ''),
            'gender' => $studentData['gender'] ?? '',
            'recipient_name' => $studentData['name'] ?? '',
        ]);

        return [
            'application_id' => (int)$baseData['application_id'],
            'recipient_student_id' => (int)($studentData['student_id'] ?? 0),
            'recipient_name' => $studentData['name'] ?? '',
            'rank_position' => (int)$baseData['rank_position'],
            'result_status' => $baseData['result_status'],
            'result_id' => (int)$baseData['result_id'],
            'generation_data' => $genData
        ];
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
