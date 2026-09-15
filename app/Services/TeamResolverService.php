<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use PDO;

class TeamResolverService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Resolves the canonical participant data for a given application ID.
     * This handles both Individual and Team applications.
     */
    public function resolveParticipant(int $applicationId): ?array
    {
        $appSql = "
            SELECT a.application_id, a.application_type, a.symposium_event_id, a.application_status
            FROM applications a
            WHERE a.application_id = :id
        ";
        $stmt = $this->db->prepare($appSql);
        $stmt->execute(['id' => $applicationId]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$app) {
            return null;
        }

        if ($app['application_type'] === 'Individual') {
            return $this->resolveIndividualParticipant($app);
        } else {
            return $this->resolveTeamParticipant($app);
        }
    }

    private function resolveIndividualParticipant(array $app): array
    {
        $sql = "
            SELECT 
                s.student_id, s.register_number, s.full_name as name, 
                d.short_name as department, s.academic_year, s.gender
            FROM applications a
            JOIN students s ON a.student_id = s.student_id
            JOIN departments d ON s.department_id = d.department_id
            WHERE a.application_id = :id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => (int)$app['application_id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'application_id' => (int)$app['application_id'],
            'symposium_event_id' => (int)$app['symposium_event_id'],
            'participant_type' => 'individual',
            'participant' => [
                'student_id' => (int)$student['student_id'],
                'register_number' => $student['register_number'],
                'name' => $student['name'],
                'department' => $student['department'],
                'academic_year' => $student['academic_year'],
                'gender' => $student['gender'],
            ],
            'members' => [
                [
                    'student_id' => (int)$student['student_id'],
                    'register_number' => $student['register_number'],
                    'name' => $student['name'],
                    'department' => $student['department'],
                    'academic_year' => $student['academic_year'],
                    'gender' => $student['gender'],
                ]
            ]
        ];
    }

    private function resolveTeamParticipant(array $app): array
    {
        $sql = "
            SELECT t.team_id, t.manager_student_id
            FROM teams t
            WHERE t.application_id = :id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => (int)$app['application_id']]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$team) {
            return [
                'application_id' => (int)$app['application_id'],
                'symposium_event_id' => (int)$app['symposium_event_id'],
                'participant_type' => 'team',
                'team_id' => null,
                'manager' => null,
                'members' => [],
            ];
        }

        $membersSql = "
            SELECT 
                s.student_id, s.register_number, s.full_name as name, 
                d.short_name as department, s.academic_year, s.gender
            FROM team_members tm
            JOIN students s ON tm.student_id = s.student_id
            JOIN departments d ON s.department_id = d.department_id
            WHERE tm.team_id = :tid
            ORDER BY s.full_name ASC
        ";
        $mStmt = $this->db->prepare($membersSql);
        $mStmt->execute(['tid' => (int)$team['team_id']]);
        $members = $mStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Find the manager's data from the members list
        $manager = null;
        foreach ($members as $m) {
            if ($m['student_id'] == $team['manager_student_id']) {
                $manager = $m;
                break;
            }
        }

        return [
            'application_id' => (int)$app['application_id'],
            'symposium_event_id' => (int)$app['symposium_event_id'],
            'participant_type' => 'team',
            'team_id' => (int)$team['team_id'],
            'manager' => $manager,
            'members' => $members,
        ];
    }
    public function resolveParticipantsForApplications(array $applications): array
    {
        $resolved = [];
        $teamAppIds = [];
        $individualApps = [];

        foreach ($applications as $app) {
            $appId = (int)$app['application_id'];
            if (($app['application_type'] ?? '') === 'Team') {
                $teamAppIds[] = $appId;
            } else {
                $individualApps[] = $app;
            }
        }

        // Process Individuals (they are often pre-joined in $app, but let's fetch canonically to be safe if needed,
        // or just use resolveIndividualParticipant in a loop if few, but to avoid N+1 we can fetch all in one query)
        if (!empty($individualApps)) {
            $ids = array_map(fn($a) => (int)$a['application_id'], $individualApps);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $sql = "
                SELECT 
                    a.application_id, a.symposium_event_id,
                    s.student_id, s.register_number, s.full_name as name, 
                    d.short_name as department, s.academic_year, s.gender
                FROM applications a
                JOIN students s ON a.student_id = s.student_id
                JOIN departments d ON s.department_id = d.department_id
                WHERE a.application_id IN ($placeholders)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($ids);
            $individuals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($individuals as $ind) {
                $appId = (int)$ind['application_id'];
                $resolved[$appId] = [
                    'application_id' => $appId,
                    'symposium_event_id' => (int)$ind['symposium_event_id'],
                    'participant_type' => 'individual',
                    'participant' => [
                        'student_id' => (int)$ind['student_id'],
                        'register_number' => $ind['register_number'],
                        'name' => $ind['name'],
                        'department' => $ind['department'],
                        'academic_year' => $ind['academic_year'],
                        'gender' => $ind['gender'],
                    ],
                    'members' => [
                        [
                            'student_id' => (int)$ind['student_id'],
                            'register_number' => $ind['register_number'],
                            'name' => $ind['name'],
                            'department' => $ind['department'],
                            'academic_year' => $ind['academic_year'],
                            'gender' => $ind['gender'],
                        ]
                    ]
                ];
            }
        }

        // Process Teams
        if (!empty($teamAppIds)) {
            $placeholders = str_repeat('?,', count($teamAppIds) - 1) . '?';
            
            // Get teams
            $sql = "
                SELECT t.team_id, t.application_id, t.manager_student_id, a.symposium_event_id
                FROM teams t
                JOIN applications a ON t.application_id = a.application_id
                WHERE t.application_id IN ($placeholders)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($teamAppIds);
            $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($teams)) {
                $teamIds = array_map(fn($t) => (int)$t['team_id'], $teams);
                $tPlaceholders = str_repeat('?,', count($teamIds) - 1) . '?';
                
                // Get all members for these teams
                $mSql = "
                    SELECT 
                        tm.team_id,
                        s.student_id, s.register_number, s.full_name as name, 
                        d.short_name as department, s.academic_year, s.gender
                    FROM team_members tm
                    JOIN students s ON tm.student_id = s.student_id
                    JOIN departments d ON s.department_id = d.department_id
                    WHERE tm.team_id IN ($tPlaceholders)
                    ORDER BY s.full_name ASC
                ";
                $mStmt = $this->db->prepare($mSql);
                $mStmt->execute($teamIds);
                $allMembers = $mStmt->fetchAll(PDO::FETCH_ASSOC);

                // Group members by team_id
                $membersByTeam = [];
                foreach ($allMembers as $m) {
                    $membersByTeam[$m['team_id']][] = [
                        'student_id' => (int)$m['student_id'],
                        'register_number' => $m['register_number'],
                        'name' => $m['name'],
                        'department' => $m['department'],
                        'academic_year' => $m['academic_year'],
                        'gender' => $m['gender'],
                    ];
                }

                // Build resolved team array
                foreach ($teams as $t) {
                    $appId = (int)$t['application_id'];
                    $tId = (int)$t['team_id'];
                    $members = $membersByTeam[$tId] ?? [];
                    
                    $manager = null;
                    foreach ($members as $m) {
                        if ($m['student_id'] == $t['manager_student_id']) {
                            $manager = $m;
                            break;
                        }
                    }

                    $resolved[$appId] = [
                        'application_id' => $appId,
                        'symposium_event_id' => (int)$t['symposium_event_id'],
                        'participant_type' => 'team',
                        'team_id' => $tId,
                        'manager' => $manager,
                        'members' => $members,
                    ];
                }
            }
        }

        // Fill in any applications that didn't resolve (e.g. invalid state)
        foreach ($applications as $app) {
            $appId = (int)$app['application_id'];
            if (!isset($resolved[$appId])) {
                $resolved[$appId] = [
                    'application_id' => $appId,
                    'symposium_event_id' => (int)($app['symposium_event_id'] ?? 0),
                    'participant_type' => strtolower($app['application_type'] ?? 'unknown'),
                    'team_id' => null,
                    'manager' => null,
                    'members' => [],
                    'participant' => null,
                ];
            }
        }

        return $resolved;
    }
}
