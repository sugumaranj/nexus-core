<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : FacultyInChargeService.php
 * Location    : app/Services/
 * Description : Service for the Faculty In-Charge (FIC) portal.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Retrieve all events where a user is assigned as FIC
 * • Get full event detail with current judges and available staff
 * • Compute FIC dashboard statistics
 *
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Models\FacultyAssignmentModel;
use App\Models\JudgeAssignmentModel;
use App\Models\UserModel;
use App\Models\CompetitionStageModel;
use App\Database\Database;
use PDO;
use Throwable;

final class FacultyInChargeService
{
    private FacultyAssignmentModel $facultyModel;
    private JudgeAssignmentModel   $judgeModel;
    private UserModel              $userModel;
    private CompetitionStageModel  $stageModel;
    private ResourceAllocationService $allocService;
    private PDO                    $db;

    public function __construct()
    {
        $this->facultyModel = new FacultyAssignmentModel();
        $this->judgeModel   = new JudgeAssignmentModel();
        $this->userModel    = new UserModel();
        $this->stageModel   = new CompetitionStageModel();
        $this->allocService = new ResourceAllocationService();
        $this->db           = Database::getConnection();
    }

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    /**
     * Get all events the user is assigned to as Faculty In-Charge.
     *
     * @param int $userId
     * @return array
     */
    public function getAssignedEvents(int $userId): array
    {
        return $this->facultyModel->getAssignedEventsForUser($userId);
    }

    /**
     * Get dashboard statistics for a FIC user.
     *
     * @param int $userId
     * @return array
     */
    public function getFicDashboardStats(int $userId): array
    {
        $events = $this->getAssignedEvents($userId);

        $total       = count($events);
        $withJudge   = 0;
        $noJudge     = 0;
        $upcoming    = 0;
        $today       = date('Y-m-d');

        foreach ($events as $e) {
            $judgeCount = (int) ($e['judge_count'] ?? 0);
            if ($judgeCount > 0) {
                $withJudge++;
            } else {
                $noJudge++;
            }

            $eventDate = $e['event_date'] ?? '';
            if ($eventDate >= $today) {
                $upcoming++;
            }
        }

        // Judge assignments for this user
        $judgeEvents = $this->judgeModel->getAssignedEventsForUser($userId);

        return [
            'fic_total_events'   => $total,
            'fic_with_judge'     => $withJudge,
            'fic_no_judge'       => $noJudge,
            'fic_upcoming'       => $upcoming,
            'judge_total_events' => count($judgeEvents),
        ];
    }

    // =========================================================================
    // EVENT DETAIL
    // =========================================================================

    /**
     * Get full event detail for the FIC event management page.
     *
     * Returns:
     *  - event: the symposium_event row with venue + symposium info
     *  - current_judges: active judges for this event
     *  - assignable_staff: all staff eligible for judge assignment, with availability
     *  - is_fic: whether the requesting user is truly the FIC
     *
     * @param int $symposiumEventId
     * @param int $requestingUserId
     * @return array|null  null if not found
     */
    public function getEventDetail(int $symposiumEventId, int $requestingUserId): ?array
    {
        // Load event with venue + symposium
        $eventStmt = $this->db->prepare("
            SELECT
                se.*,
                s.title             AS symposium_title,
                s.symposium_code,
                s.status            AS symposium_status,
                s.event_start_date  AS symposium_start_date,
                s.event_end_date    AS symposium_end_date,
                v.venue_name,
                v.building_name,
                v.floor
            FROM symposium_events se
            JOIN symposiums  s ON s.symposium_id = se.symposium_id
            LEFT JOIN venues v ON v.venue_id     = se.venue_id
            WHERE se.symposium_event_id = :id
            LIMIT 1
        ");
        $eventStmt->execute(['id' => $symposiumEventId]);
        $event = $eventStmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            return null;
        }

        // Verify FIC
        $isFic = $this->facultyModel->isAssigned($symposiumEventId, $requestingUserId);

        // Current FICs for this event
        $ficStmt = $this->db->prepare("
            SELECT fa.*, u.full_name, u.email, u.role AS user_role, d.department_name
            FROM faculty_assignments fa
            JOIN users u ON u.user_id = fa.user_id
            LEFT JOIN departments d ON d.department_id = u.department_id
            WHERE fa.symposium_event_id = :event_id
              AND fa.is_active = 1
        ");
        $ficStmt->execute(['event_id' => $symposiumEventId]);
        $currentFic = $ficStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Current judges for this event
        $judgeStmt = $this->db->prepare("
            SELECT cj.*, u.full_name, u.email, u.role AS user_role, d.department_name
            FROM competition_judges cj
            JOIN users u ON u.user_id = cj.user_id
            LEFT JOIN departments d ON d.department_id = u.department_id
            WHERE cj.symposium_event_id = :event_id
              AND cj.is_active = 1
        ");
        $judgeStmt->execute(['event_id' => $symposiumEventId]);
        $currentJudges = $judgeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Assignable staff (all active staff + coordinators + HODs)
        // Delegate to ResourceAllocationService to perfectly match the conflict engine
        $allStaff = $this->allocService->getStaffAvailability($symposiumEventId);
        
        // Remove people who are already Judges for THIS event, so they don't appear in the dropdown
        // (If they are the FIC, they will simply be marked as 'busy' because they have an assignment today).
        $assignableStaff = array_filter($allStaff, function($s) {
            return !$s['is_judge'];
        });
        $assignableStaff = array_values($assignableStaff);

        // Registration count
        $regCount = (int) $this->db->query(
            "SELECT COUNT(*) FROM applications WHERE symposium_event_id = $symposiumEventId"
        )->fetchColumn();

        // Competition Stages (Prelims, etc.)
        $stages = $this->stageModel->getBySymposiumEvent($symposiumEventId);

        return [
            'event'           => $event,
            'current_fic'     => $currentFic,
            'current_judges'  => $currentJudges,
            'assignable_staff'=> $assignableStaff,
            'is_fic'          => $isFic,
            'registration_count' => $regCount,
            'stages'          => $stages,
        ];
    }

    // =========================================================================
    // AUTH CHECK
    // =========================================================================

    /**
     * Check if the user is the active FIC for a given event.
     *
     * @param int $symposiumEventId
     * @param int $userId
     * @return bool
     */
    /**
     * Check if the user is the active FIC for a given event.
     *
     * @param int $symposiumEventId
     * @param int $userId
     * @return bool
     */
    public function isFic(int $symposiumEventId, int $userId): bool
    {
        return $this->facultyModel->isAssigned($symposiumEventId, $userId);
    }

    /**
     * Save prelims configuration.
     * Updates the `requires_prelims` flag on `symposium_events` and syncs `competition_stages`.
     *
     * @param int $symposiumEventId
     * @param int $requiresPrelims
     * @param array $stagesData
     * @return bool
     */
    public function savePrelimsConfig(int $symposiumEventId, string $prelimDecision, array $stagesData): array
    {
        try {
            // Fetch event to check status and timings
            $eventStmt = $this->db->prepare("
                SELECT se.symposium_id, se.status, se.prelim_decision, se.event_date, se.start_time AS main_start_time, s.event_start_date, s.event_end_date 
                FROM symposium_events se
                JOIN symposiums s ON s.symposium_id = se.symposium_id
                WHERE se.symposium_event_id = :id
            ");
            $eventStmt->execute(['id' => $symposiumEventId]);
            $symData = $eventStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$symData) {
                return ['success' => false, 'message' => 'Event not found.'];
            }

            // ENFORCE WORKFLOW: Registration must be closed.
            // Allowed states: Registration Closed, Running, Completed (though Completed/Running shouldn't be edited normally)
            // Draft, Published, Registration Open are NOT allowed to decide prelims.
            $allowedDecideStates = [\App\Services\SymposiumEventStatusSyncService::STATUS_REGISTRATION_CLOSED, \App\Services\SymposiumEventStatusSyncService::STATUS_RUNNING, \App\Services\SymposiumEventStatusSyncService::STATUS_COMPLETED];
            if (!in_array($symData['status'], $allowedDecideStates)) {
                return ['success' => false, 'message' => 'Prelim decision can only be made after Registration is Closed. Current status: ' . $symData['status']];
            }
            
            // LOCKING WORKFLOW: If changing from Required -> Not Required/Pending, we must ensure stages are safely deleted.
            // (The code below already deletes stages, which serves as the reset).

            $symposiumId = (int)$symData['symposium_id'];
            $symStart = $symData['event_start_date'] ?? '';
            $symEnd = $symData['event_end_date'] ?? '';

            // 0. Conflict Validation
            if ($prelimDecision === 'Required' && !empty($stagesData)) {
                $eventModel = new \App\Models\SymposiumEventModel();

                foreach ($stagesData as $stage) {
                    if (!empty($stage['stage_date'])) {
                        // Date boundary validation against Symposium dates
                        if ($symStart && $stage['stage_date'] < $symStart) {
                            return ['success' => false, 'message' => "Stage date cannot be before the symposium start date (" . \App\Helpers\DateHelper::date($symStart) . ")."];
                        }
                        if ($symEnd && $stage['stage_date'] > $symEnd) {
                            return ['success' => false, 'message' => "Stage date cannot be after the symposium end date (" . \App\Helpers\DateHelper::date($symEnd) . ")."];
                        }

                        // Chronological validation against the Main Event
                        if (!empty($symData['event_date'])) {
                            if ($stage['stage_date'] > $symData['event_date']) {
                                return ['success' => false, 'message' => "Stages must be scheduled on or before the Main Event date (" . \App\Helpers\DateHelper::date($symData['event_date']) . ")."];
                            }
                            if ($stage['stage_date'] === $symData['event_date']) {
                                // If on the same date, ensure stage ends before main event starts
                                if (!empty($symData['main_start_time']) && !empty($stage['end_time'])) {
                                    $fStageEnd   = strlen($stage['end_time']) === 5 ? $stage['end_time'] . ':00' : $stage['end_time'];
                                    $fMainStart  = strlen($symData['main_start_time']) === 5 ? $symData['main_start_time'] . ':00' : $symData['main_start_time'];
                                    
                                    if ($fStageEnd >= $fMainStart) {
                                        return ['success' => false, 'message' => "On the same day, stages must end before the Main Event starts (" . \App\Helpers\DateHelper::time($symData['main_start_time']) . ")."];
                                    }
                                }
                            }
                        }
                        
                        // Weekend validation
                        if (\App\Helpers\DateHelper::isWeekend($stage['stage_date'])) {
                            $dayName = date('l', strtotime($stage['stage_date']));
                            return ['success' => false, 'message' => "Stages cannot be scheduled on {$dayName}s."];
                        }

                        if (!empty($stage['start_time']) && !empty($stage['end_time'])) {
                            $startTime = $stage['start_time'];
                            $endTime = $stage['end_time'];

                            if ($endTime <= $startTime) {
                                return ['success' => false, 'message' => 'End time must be later than start time.'];
                            }

                            // College working hours validation (10:00 AM to 3:30 PM)
                            $collegeStart = '10:00:00';
                            $collegeEnd   = '15:30:00';
                            
                            $fStartTime = strlen($startTime) === 5 ? $startTime . ':00' : $startTime;
                            $fEndTime   = strlen($endTime) === 5 ? $endTime . ':00' : $endTime;

                            if ($fStartTime < $collegeStart) {
                                return ['success' => false, 'message' => 'Stages cannot start before 10:00 AM.'];
                            }
                            if ($fEndTime > $collegeEnd) {
                                return ['success' => false, 'message' => 'Stages cannot end after 3:30 PM.'];
                            }

                            // Time overlap validation
                            $conflict = $eventModel->checkTimeOverlapWithinSymposium(
                                $symposiumId,
                                $stage['stage_date'],
                                $stage['start_time'],
                                $stage['end_time'],
                                $symposiumEventId
                            );

                            if ($conflict) {
                                return [
                                    'success' => false,
                                    'message' => "Conflict: '{$conflict['event_name']}' is already scheduled " . \App\Helpers\DateHelper::time($conflict['start_time']) . " - " . \App\Helpers\DateHelper::time($conflict['end_time'])
                                ];
                            }
                        }
                    }
                }
            }

            $this->db->beginTransaction();

            // 1. Update the symposium_events table
            $stmt = $this->db->prepare("UPDATE symposium_events SET prelim_decision = :dec WHERE symposium_event_id = :id");
            $stmt->execute(['dec' => $prelimDecision, 'id' => $symposiumEventId]);

            // 2. Clear existing stages for this event (sync/replace strategy)
            $delStmt = $this->db->prepare("DELETE FROM competition_stages WHERE symposium_event_id = :id");
            $delStmt->execute(['id' => $symposiumEventId]);

            // 3. Insert new stages if prelimDecision is 'Required'
            if ($prelimDecision === 'Required' && !empty($stagesData)) {
                $order = 1;
                foreach ($stagesData as $stage) {
                    $stageName = $stage['stage_name'] ?? 'Custom';
                    // Validate stage_name is a valid ENUM value
                    $validStageNames = ['Digital Prelims', 'Offline Prelims', 'Quarter Finals', 'Semi Finals', 'Finals', 'Custom'];
                    if (!in_array($stageName, $validStageNames)) {
                        $stageName = 'Custom';
                    }

                    $insertData = [
                        'symposium_event_id' => $symposiumEventId,
                        'stage_name'         => $stageName,
                        'custom_name'        => !empty($stage['custom_name']) ? trim($stage['custom_name']) : null,
                        'stage_order'        => $order++,
                        'stage_date'         => !empty($stage['stage_date']) ? $stage['stage_date'] : null,
                        'start_time'         => !empty($stage['start_time']) ? $stage['start_time'] : null,
                        'end_time'           => !empty($stage['end_time']) ? $stage['end_time'] : null,
                        'venue_id'           => !empty($stage['venue_id']) ? (int) $stage['venue_id'] : null,
                        'description'        => !empty($stage['description']) ? $stage['description'] : null,
                        'is_active'          => 1,
                    ];

                    // Use addStage() — the correct method name in CompetitionStageModel
                    $result = $this->stageModel->addStage($insertData);
                    if ($result === false) {
                        throw new \RuntimeException("Failed to insert stage: {$stageName}");
                    }
                }
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Configuration saved successfully.'];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $errMsg = $e->getMessage();
            error_log("Failed to save prelims config for event {$symposiumEventId}: {$errMsg}");
            return ['success' => false, 'message' => 'Failed to save configuration. ' . $errMsg];
        }
    }
}
