<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : EvaluationService.php
 * Location    : app/Services/
 * Description : Centralized business logic for Evaluation Management.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Validate evaluation state (Pending/Closed/Locked).
 * • Calculate Totals, Averages, and Rankings using BCMath engine.
 * • Publish results via a secure Database Transaction.
 *
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Database\Database;
use App\Models\SymposiumEventModel;
use App\Models\EvaluationModel;
use App\Models\JudgeAssignmentModel;
use App\Models\ApplicationModel;
use App\Models\AttendanceRecordModel;
use App\Services\Evaluation\EvaluationEngine;
use App\Services\Evaluation\EligibilityCalculator;
use App\Services\Evaluation\ResultValidator;
use PDO;
use Exception;

final class EvaluationService
{
    private SymposiumEventModel $sympEventModel;
    private EvaluationModel $evalModel;
    private JudgeAssignmentModel $judgeModel;
    private ApplicationModel $appModel;
    private AttendanceRecordModel $attendanceModel;
    private PDO $db;
    private EvaluationEngine $evaluationEngine;
    private EligibilityCalculator $eligibilityCalc;
    private ResultValidator $resultValidator;

    public function __construct()
    {
        $this->sympEventModel = new SymposiumEventModel();
        $this->evalModel = new EvaluationModel();
        $this->judgeModel = new JudgeAssignmentModel();
        $this->appModel = new ApplicationModel();
        $this->attendanceModel = new AttendanceRecordModel();
        $this->db = Database::getConnection();
        $this->evaluationEngine = new EvaluationEngine(16);
        $this->eligibilityCalc = new EligibilityCalculator($this->db);
        $this->resultValidator = new ResultValidator();
    }

    /**
     * Get eligible participants for evaluation based on attendance support.
     *
     * @param int $symposiumEventId
     * @return array
     */
    public function getEligibleParticipants(int $symposiumEventId): array
    {
        $event = $this->sympEventModel->findById($symposiumEventId);
        if (!$event) {
            return [];
        }

        $supportsAttendance = (bool)$event['supports_attendance'];
        return $this->eligibilityCalc->getEligibleParticipants($symposiumEventId, $supportsAttendance);
    }

    /**
     * Submit an evaluation mark for a Symposium Event (Master Event Architecture).
     *
     * @param int   $symposiumEventId
     * @param int   $applicationId
     * @param int   $judgeId
     * @param float $mark
     * @param string|null $remarks
     * @param string $status
     * @return array
     */
    public function submitEventEvaluation(
        int $symposiumEventId,
        int $applicationId,
        int $judgeId,
        float $mark,
        ?string $remarks = null,
        string $status = 'Submitted'
    ): array {
        $event = $this->sympEventModel->findById($symposiumEventId);

        if (!$event) {
            return ['success' => false, 'message' => 'Scheduled event not found.'];
        }

        if (empty($event['supports_evaluation'])) {
            return ['success' => false, 'message' => 'Evaluation scoring is disabled for this event.'];
        }

        if ($event['is_locked']) {
            return ['success' => false, 'message' => 'Evaluation is locked for this event.'];
        }

        $snapshotEval = !empty($event['snapshot_evaluation']) ? json_decode($event['snapshot_evaluation'], true) : [];
        $maxScore = (float)($snapshotEval['maximum_score'] ?? 100.00);

        if ($mark < 0 || $mark > $maxScore) {
            return ['success' => false, 'message' => "Mark must be between 0 and {$maxScore}."];
        }

        // Validate Judge Assignment
        if (!$this->judgeModel->isAssigned($symposiumEventId, $judgeId)) {
            return ['success' => false, 'message' => 'You are not assigned as an active judge for this event.'];
        }

        // Verify application belongs to event and is eligible
        $eligibleParticipants = $this->getEligibleParticipants($symposiumEventId);
        if (!isset($eligibleParticipants[$applicationId])) {
            return ['success' => false, 'message' => 'Participant is not eligible or application does not belong to this event.'];
        }

        // Check if already submitted and event might be in a state where we shouldn't modify?
        // The event lock check above handles the general lock.
        // We'll enforce that the mark is validly processed.

        $ok = $this->evalModel->upsertEvaluation(
            $applicationId,
            $judgeId,
            $mark,
            $remarks,
            $status,
            $symposiumEventId
        );

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to save evaluation mark.'];
        }

        return ['success' => true, 'message' => 'Evaluation submitted successfully!'];
    }

    /**
     * Bulk submit evaluation marks for a Symposium Event.
     *
     * @param int   $symposiumEventId
     * @param int   $judgeId
     * @param array $marks Array of ['application_id' => int, 'mark' => float, 'remarks' => string]
     * @param string $status
     * @return array
     */
    public function bulkSubmitEventEvaluation(
        int $symposiumEventId,
        int $judgeId,
        array $marks,
        string $status = 'Draft'
    ): array {
        $event = $this->sympEventModel->findById($symposiumEventId);

        if (!$event) {
            return ['success' => false, 'message' => 'Scheduled event not found.'];
        }

        if (empty($event['supports_evaluation'])) {
            return ['success' => false, 'message' => 'Evaluation scoring is disabled for this event.'];
        }

        if ($event['is_locked']) {
            return ['success' => false, 'message' => 'Evaluation is locked for this event.'];
        }

        $snapshotEval = !empty($event['snapshot_evaluation']) ? json_decode($event['snapshot_evaluation'], true) : [];
        $maxScore = (float)($snapshotEval['maximum_score'] ?? 100.00);

        // Validate Judge Assignment
        if (!$this->judgeModel->isAssigned($symposiumEventId, $judgeId)) {
            return ['success' => false, 'message' => 'You are not assigned as an active judge for this event.'];
        }

        $eligibleParticipants = $this->getEligibleParticipants($symposiumEventId);
        
        $validMarks = [];
        foreach ($marks as $markData) {
            $appId = (int) ($markData['application_id'] ?? 0);
            if (!isset($eligibleParticipants[$appId])) {
                continue; // Skip invalid participants
            }
            $mark = (float) ($markData['mark'] ?? -1);
            if ($mark < 0 || $mark > $maxScore) {
                return ['success' => false, 'message' => "Mark for Application #$appId must be between 0 and {$maxScore}."];
            }
            $validMarks[] = [
                'application_id' => $appId,
                'mark' => $mark,
                'remarks' => trim($markData['remarks'] ?? '')
            ];
        }

        if (empty($validMarks)) {
            return ['success' => true, 'message' => 'No marks to save.'];
        }

        $ok = $this->evalModel->bulkUpsertEvaluations(
            $symposiumEventId,
            $judgeId,
            $validMarks,
            $status
        );

        if (!$ok) {
            return ['success' => false, 'message' => 'Failed to save evaluation marks.'];
        }

        $msg = $status === 'Submitted' ? 'Final evaluation submitted successfully!' : 'Evaluation draft saved successfully!';
        return ['success' => true, 'message' => $msg];
    }

    /**
     * Publish results (calculates Z-scores, sets ranks, saves JSON snapshot, locks evaluation).
     */
    public function publishResults(int $symposiumEventId, int $publishedBy): array
    {
        $event = $this->sympEventModel->findById($symposiumEventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Event not found.'];
        }

        if ((bool)$event['is_locked']) {
            return ['success' => false, 'message' => 'Results are already published and locked.'];
        }

        // 1. Freeze Matrix
        $participants = $this->getEligibleParticipants($symposiumEventId);
        $judgesList = $this->judgeModel->getForEvent($symposiumEventId);
        
        $participantIds = array_keys($participants);
        $judgeMap = array_column($judgesList, 'full_name', 'user_id');
        
        // 2. Fetch raw marks
        $matrix = $this->evalModel->getSubmittedEvaluationsForEvent($symposiumEventId);

        // 3. Complete Matrix Validation
        $validation = $this->resultValidator->validateCompleteMatrix($participantIds, $judgeMap, $matrix);
        if (!$validation['success']) {
            return $validation; // Block publication
        }

        $snapshotEval = !empty($event['snapshot_evaluation']) ? json_decode($event['snapshot_evaluation'], true) : [];
        $maxScore = (float)($snapshotEval['maximum_score'] ?? 100.00);

        // START TRANSACTION
        $this->db->beginTransaction();

        try {
            // 4. Run BCMath Engine
            $engineResult = $this->evaluationEngine->runPipeline(
                $symposiumEventId,
                $maxScore,
                $participants,
                array_keys($judgeMap),
                $matrix
            );

            if (!$engineResult['success']) {
                $this->db->rollBack();
                return [
                    'success'      => false,
                    'message'      => $engineResult['message'] ?? 'Evaluation calculation failed.',
                ];
            }

            // 5. Save Results
            $stmt = $this->db->prepare("
                INSERT INTO competition_results 
                (application_id, symposium_event_id, total_score, average_score, rank_position, result_status,
                 evaluated_by, evaluation_time, published, published_at, judge_marks, statistical_snapshot, snapshot_hash)
                VALUES (:app_id, :event_id, :total, :average, :rank, :status,
                        :evaluator, CURRENT_TIMESTAMP, 1, CURRENT_TIMESTAMP, :marks, :snapshot, :hash)
                ON DUPLICATE KEY UPDATE 
                total_score = VALUES(total_score),
                average_score = VALUES(average_score),
                rank_position = VALUES(rank_position),
                result_status = VALUES(result_status),
                evaluated_by = VALUES(evaluated_by),
                evaluation_time = VALUES(evaluation_time),
                published = VALUES(published),
                published_at = VALUES(published_at),
                judge_marks = VALUES(judge_marks),
                statistical_snapshot = VALUES(statistical_snapshot),
                snapshot_hash = VALUES(snapshot_hash)
            ");

            foreach ($engineResult['results'] as $data) {
                $stmt->execute([
                    'app_id' => $data['application_id'],
                    'event_id' => $symposiumEventId,
                    'total' => $data['total_score'],
                    'average' => $data['average_score'],
                    'rank' => $data['rank_position'],
                    'status' => $data['result_status'],
                    'evaluator' => $publishedBy,
                    'marks' => json_encode($data['judge_marks']),
                    'snapshot' => $engineResult['snapshot_json'],
                    'hash' => $engineResult['snapshot_hash']
                ]);
            }

            // 6. Lock Event
            $lockStmt = $this->db->prepare("UPDATE symposium_events SET is_locked = 1, status = 'Completed' WHERE symposium_event_id = :id");
            $lockStmt->execute(['id' => $symposiumEventId]);

            $this->db->commit();
            return ['success' => true, 'message' => 'Results published securely and evaluation locked.'];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // TIE RESOLVER
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Compute tie groups for an event and load any saved manual overrides.
     *
     * Called by FacultyInChargeController::tieResolver()
     *
     * Returns a structured array consumed directly by tie_resolver.php:
     * [
     *   'success'        => bool,
     *   'message'        => string,
     *   'has_ties'       => bool,
     *   'all_resolved'   => bool,
     *   'tie_groups'     => [ ['rank'=>int, 'score'=>string, 'z_score'=>string,
     *                          'application_ids'=>int[]], ... ],
     *   'participants'   => [ applicationId => metadataRow ],
     *   'saved_overrides'=> [ applicationId => overrideRow ],
     *   'engine_results' => [...],
     * ]
     * -------------------------------------------------------------------------
     */
    public function getTieGroups(int $symposiumEventId): array
    {
        $errorResult = static fn(string $msg): array => [
            'success'        => false,
            'message'        => $msg,
            'has_ties'       => false,
            'all_resolved'   => true,
            'tie_groups'     => [],
            'participants'   => [],
            'saved_overrides'=> [],
            'engine_results' => [],
        ];

        // 1. Load event
        $event = $this->sympEventModel->findById($symposiumEventId);
        if (!$event) {
            return $errorResult('Event not found.');
        }
        if (empty($event['supports_evaluation'])) {
            return $errorResult('Evaluation scoring is not enabled for this event.');
        }

        // 2. Participants & judges
        $participants = $this->getEligibleParticipants($symposiumEventId);
        if (empty($participants)) {
            return $errorResult('No eligible participants found. Ensure attendance is finalised and evaluations are submitted.');
        }

        $judgesList = $this->judgeModel->getForEvent($symposiumEventId);
        $judgeMap   = array_column($judgesList, 'full_name', 'user_id');
        if (empty($judgeMap)) {
            return $errorResult('No active judges found for this event.');
        }

        // 3. Evaluation matrix
        $matrix = $this->evalModel->getSubmittedEvaluationsForEvent($symposiumEventId);

        // 4. Completeness check
        $validation = $this->resultValidator->validateCompleteMatrix(
            array_keys($participants),
            $judgeMap,
            $matrix
        );
        if (!$validation['success']) {
            return $errorResult(
                $validation['message']
                ?? 'Evaluation matrix is incomplete. Ensure all judges have submitted their scores.'
            );
        }

        // 5. Run engine (read-only — no DB writes)
        $snapshotEval = !empty($event['snapshot_evaluation'])
            ? json_decode($event['snapshot_evaluation'], true)
            : [];
        $maxScore = (float) ($snapshotEval['maximum_score'] ?? 100.00);

        $engineResult = $this->evaluationEngine->runPipeline(
            $symposiumEventId,
            $maxScore,
            $participants,
            array_keys($judgeMap),
            $matrix
        );

        // Engine may return success=false on an unresolved tie; we still use its results array.
        $results = $engineResult['results'] ?? [];

        // 6. Find shared-rank positions (tie groups)
        $rankBuckets = [];
        foreach ($results as $r) {
            $pos = (int) ($r['rank_position'] ?? 0);
            $rankBuckets[$pos][] = $r;
        }

        $tieGroups = [];
        foreach ($rankBuckets as $rank => $group) {
            // Only surface ties that affect meaningful positions (1st, 2nd, 3rd).
            // Ties at rank ≥ 4 are all "Participant" and require no manual resolution.
            if (count($group) > 1 && $rank <= 3) {
                $tieGroups[] = [
                    'rank'            => $rank,
                    'score'           => number_format((float) ($group[0]['average_score']   ?? 0), 2),
                    'z_score'         => number_format((float) ($group[0]['average_z_score'] ?? 0), 4),
                    'application_ids' => array_column($group, 'application_id'),
                ];
            }
        }

        $hasTies = !empty($tieGroups);

        // 7. Fetch application metadata for display
        $participantIds  = array_keys($participants);
        $participantMeta = [];
        if (!empty($participantIds)) {
            $ph   = implode(',', array_fill(0, count($participantIds), '?'));
            $stmt = $this->db->prepare("
                SELECT
                    a.application_id,
                    a.application_no,
                    a.application_type,
                    s.full_name       AS student_name,
                    s.register_number,
                    d.short_name      AS department_name
                FROM applications a
                LEFT JOIN students    s ON a.student_id    = s.student_id
                LEFT JOIN departments d ON s.department_id = d.department_id
                WHERE a.application_id IN ($ph)
            ");
            $stmt->execute($participantIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $participantMeta[(int) $row['application_id']] = $row;
            }
        }

        // 8. Load any previously saved manual overrides
        $savedOverrides = [];
        if ($hasTies && !empty($participantIds)) {
            $ph   = implode(',', array_fill(0, count($participantIds), '?'));
            $stmt = $this->db->prepare("
                SELECT
                    cr.application_id,
                    cr.manual_rank_override,
                    cr.manual_rank_reason,
                    cr.override_at,
                    u.full_name AS override_by_name
                FROM competition_results cr
                LEFT JOIN users u ON cr.override_by = u.user_id
                WHERE cr.symposium_event_id = ?
                  AND cr.application_id     IN ($ph)
                  AND cr.manual_rank_override IS NOT NULL
            ");
            $stmt->execute(array_merge([$symposiumEventId], $participantIds));
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $savedOverrides[(int) $row['application_id']] = $row;
            }
        }

        // 9. Determine if all groups are fully resolved
        $allResolved = true;
        foreach ($tieGroups as $group) {
            foreach ($group['application_ids'] as $appId) {
                if (!isset($savedOverrides[(int) $appId])) {
                    $allResolved = false;
                    break 2;
                }
            }
        }

        return [
            'success'        => true,
            'message'        => $hasTies
                ? ($allResolved ? 'All ties resolved.' : 'Unresolved ties detected.')
                : 'No ties detected.',
            'has_ties'       => $hasTies,
            'all_resolved'   => $allResolved,
            'tie_groups'     => $tieGroups,
            'participants'   => $participantMeta,
            'saved_overrides'=> $savedOverrides,
            'engine_results' => $results,
        ];
    }

    /**
     * -------------------------------------------------------------------------
     * Persist manual rank overrides for tied participants.
     *
     * Called by FacultyInChargeController::saveTieResolution()
     *
     * @param int   $symposiumEventId
     * @param array $overrides  [ ['application_id'=>int, 'rank'=>int, 'reason'=>string], … ]
     * @param int   $userId     User performing the override (audit)
     * @return array ['success'=>bool, 'message'=>string]
     * -------------------------------------------------------------------------
     */
    public function saveManualTieRanks(int $symposiumEventId, array $overrides, int $userId): array
    {
        if (empty($overrides)) {
            return ['success' => false, 'message' => 'No overrides provided.'];
        }
        if ($symposiumEventId <= 0) {
            return ['success' => false, 'message' => 'Invalid event ID.'];
        }

        // Guard: must not already be locked
        $event = $this->sympEventModel->findById($symposiumEventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Event not found.'];
        }
        if (!empty($event['is_locked'])) {
            return ['success' => false, 'message' => 'Results are already published. Tie overrides cannot be changed.'];
        }

        // Validate: all ranks must be within 1–3 and unique within this save batch.
        // Manual tie resolution only applies to top-3 positions. Assigning a rank > 3 would
        // be nonsensical because positions 4+ are all classified as "Participant" regardless.
        $usedRanks = [];
        foreach ($overrides as $o) {
            $appId = (int) ($o['application_id'] ?? 0);
            $rank  = (int) ($o['rank']           ?? 0);
            if ($appId <= 0) {
                return ['success' => false, 'message' => 'Invalid application ID in override payload.'];
            }
            if ($rank < 1) {
                return ['success' => false, 'message' => "Rank must be ≥ 1 (application #{$appId})."];
            }
            if ($rank > 3) {
                return ['success' => false, 'message' => "Rank must be 1, 2, or 3. Only top-3 positions require manual tie resolution (application #{$appId})."];
            }
            if (isset($usedRanks[$rank])) {
                return ['success' => false, 'message' => "Duplicate rank {$rank} detected within the same save batch. Each participant must receive a unique rank."];
            }
            $usedRanks[$rank] = $appId;
        }

        // Upsert inside a transaction
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO competition_results
                    (application_id, symposium_event_id,
                     manual_rank_override, manual_rank_reason,
                     override_by, override_at,
                     published)
                VALUES
                    (:app_id, :event_id,
                     :rank, :reason,
                     :by, NOW(),
                     0)
                ON DUPLICATE KEY UPDATE
                    manual_rank_override = VALUES(manual_rank_override),
                    manual_rank_reason   = VALUES(manual_rank_reason),
                    override_by          = VALUES(override_by),
                    override_at          = VALUES(override_at)
            ");

            foreach ($overrides as $o) {
                $stmt->execute([
                    'app_id'   => (int)    $o['application_id'],
                    'event_id' => $symposiumEventId,
                    'rank'     => (int)    $o['rank'],
                    'reason'   => substr(trim((string) ($o['reason'] ?? '')), 0, 255),
                    'by'       => $userId,
                ]);
            }

            $this->db->commit();
            return [
                'success' => true,
                'message' => 'Tie ranks saved successfully. Return to the event page to publish results.',
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Failed to save tie ranks: ' . $e->getMessage()];
        }
    }
}
