<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : FeedbackService.php
 * Location    : app/Services/
 * Description : Business logic for the identified student feedback module.
 *
 * Architecture
 * -------------------------------------------------------------------------
 * • Student identity is obtained from the authenticated session — never
 *   from POST input.
 * • Feedback is identified: each submission is associated with a real
 *   student_id. Authorized staff can see full student identity.
 * • Eligibility: Present OR Late in a finalized (Closed) attendance
 *   session. Absent and no-record students are not eligible.
 * • Duplicate prevention: application-level check + DB unique constraint
 *   on (symposium_event_id, student_id).
 * • Authorization: FIC and Judge may only view feedback for events they
 *   are actively assigned to.
 *
 * Removed in 2026-09-23 refactor
 * -------------------------------------------------------------------------
 * • getSecret()             — HMAC secret no longer needed
 * • hash generation method  — HMAC workflow removed
 * • anonymous review method — replaced by identified getEventFeedback()
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Models\FeedbackModel;
use App\Models\AttendanceSessionModel;
use App\Models\SymposiumEventModel;
use App\Models\FacultyAssignmentModel;
use App\Models\JudgeAssignmentModel;

class FeedbackService
{
    private FeedbackModel          $feedbackModel;
    private AttendanceSessionModel $sessionModel;
    private SymposiumEventModel    $eventModel;
    private FacultyAssignmentModel $facultyModel;
    private JudgeAssignmentModel   $judgeModel;

    public function __construct()
    {
        $this->feedbackModel = new FeedbackModel();
        $this->sessionModel  = new AttendanceSessionModel();
        $this->eventModel    = new SymposiumEventModel();
        $this->facultyModel  = new FacultyAssignmentModel();
        $this->judgeModel    = new JudgeAssignmentModel();
    }

    // =========================================================================
    // ATTENDANCE FINALIZATION
    // =========================================================================

    /**
     * Check if an event's attendance is finalized.
     *
     * Finalized = no currently Open session AND a Closed session exists.
     *
     * @param int $eventId
     * @return bool
     */
    public function isAttendanceFinalized(int $eventId): bool
    {
        $active = $this->sessionModel->getActiveEventSession($eventId);
        if ($active) {
            return false;
        }

        $finalized = $this->sessionModel->getFinalizedEventSession($eventId);
        return $finalized !== false;
    }

    /**
     * Get the finalized (Closed) attendance session for an event.
     *
     * @param int $eventId
     * @return array|false
     */
    public function getFinalizedSession(int $eventId): array|false
    {
        return $this->sessionModel->getFinalizedEventSession($eventId);
    }

    // =========================================================================
    // ELIGIBILITY
    // =========================================================================

    /**
     * Get a student's attendance status from the finalized session.
     *
     * @param int $studentId
     * @param int $eventId
     * @return string|null  null if no finalized session or no record
     */
    public function getStudentAttendanceStatus(int $studentId, int $eventId): ?string
    {
        $session = $this->getFinalizedSession($eventId);
        if (!$session) {
            return null;
        }

        return $this->feedbackModel->getStudentAttendanceStatus(
            $studentId,
            $eventId,
            (int) $session['session_id']
        );
    }

    /**
     * Check if a student is eligible to submit feedback for an event.
     *
     * Eligible: attendance finalized AND status is Present or Late.
     *
     * @param int $studentId
     * @param int $eventId
     * @return array{eligible: bool, reason: string}
     */
    public function isStudentEligible(int $studentId, int $eventId): array
    {
        if (!$this->isAttendanceFinalized($eventId)) {
            return [
                'eligible' => false,
                'reason'   => 'Feedback is not yet available. Attendance must be finalized first.',
            ];
        }

        $status = $this->getStudentAttendanceStatus($studentId, $eventId);

        if ($status === null) {
            return [
                'eligible' => false,
                'reason'   => 'You have no attendance record for this event.',
            ];
        }

        if ($status !== 'Present' && $status !== 'Late') {
            return [
                'eligible' => false,
                'reason'   => 'Feedback is unavailable because your attendance was marked as Absent.',
            ];
        }

        return ['eligible' => true, 'reason' => ''];
    }

    // =========================================================================
    // DUPLICATE CHECK
    // =========================================================================

    /**
     * Check if a student has already submitted feedback for this event.
     *
     * @param int $studentId
     * @param int $eventId
     * @return bool
     */
    public function hasStudentSubmitted(int $studentId, int $eventId): bool
    {
        return $this->feedbackModel->existsByStudent($eventId, $studentId);
    }

    // =========================================================================
    // SUBMISSION
    // =========================================================================

    /**
     * Process a student's feedback submission.
     *
     * studentId MUST come from the authenticated session — never from POST.
     *
     * @param int         $studentId  From session only
     * @param int         $eventId    From POST (server-validated)
     * @param int         $rating     1–5
     * @param string|null $review     Optional, max 2000 chars
     * @return array{success: bool, message: string}
     */
    public function submitFeedback(int $studentId, int $eventId, int $rating, ?string $review): array
    {
        // 1. Basic parameter validation
        if ($studentId <= 0 || $eventId <= 0) {
            return ['success' => false, 'message' => 'Invalid parameters.'];
        }

        // 2. Verify event exists
        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Event not found.'];
        }

        // 3. Eligibility check (attendance finalized + Present/Late)
        $eligibility = $this->isStudentEligible($studentId, $eventId);
        if (!$eligibility['eligible']) {
            return ['success' => false, 'message' => $eligibility['reason']];
        }

        // 4. Rating validation (1–5 only)
        if (!in_array($rating, [1, 2, 3, 4, 5], true)) {
            return ['success' => false, 'message' => 'Rating must be between 1 and 5.'];
        }

        // 5. Review sanitization
        if ($review !== null) {
            $review = trim($review);
            if (mb_strlen($review) > 2000) {
                return ['success' => false, 'message' => 'Review must not exceed 2000 characters.'];
            }
            if ($review === '') {
                $review = null;
            }
        }

        // 6. Application-level duplicate check
        if ($this->feedbackModel->existsByStudent($eventId, $studentId)) {
            return ['success' => false, 'message' => 'You have already submitted feedback for this event.'];
        }

        // 7. Persist (DB unique constraint provides final duplicate protection)
        try {
            $id = $this->feedbackModel->insertFeedback($eventId, $studentId, $rating, $review);

            if ($id > 0) {
                return ['success' => true, 'message' => 'Feedback submitted successfully.'];
            }

            return ['success' => false, 'message' => 'Failed to save feedback. Please try again.'];
        } catch (\PDOException $e) {
            // SQLSTATE 23000 = unique constraint violation (concurrent duplicate)
            if ($e->getCode() === '23000') {
                return ['success' => false, 'message' => 'You have already submitted feedback for this event.'];
            }
            return ['success' => false, 'message' => 'A database error occurred. Please try again.'];
        }
    }

    // =========================================================================
    // STUDENT FEEDBACK PAGE DATA
    // =========================================================================

    /**
     * Compute per-event feedback status for a student (bulk, avoids N+1).
     *
     * Returns a map of [event_id => status_string] where status is one of:
     *   'not_finalized' | 'no_record' | 'absent' | 'available' | 'submitted'
     *
     * @param int   $studentId
     * @param array $eventIds
     * @return array<int, string>
     */
    public function getStudentFeedbackStatusesForEvents(int $studentId, array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }

        // 1. Resolve finalized sessions and active sessions in bulk
        $finalizedSessions = [];
        $activeEvents      = [];

        foreach ($eventIds as $eid) {
            if ($this->sessionModel->getActiveEventSession($eid)) {
                $activeEvents[$eid] = true;
            } else {
                $session = $this->sessionModel->getFinalizedEventSession($eid);
                if ($session) {
                    $finalizedSessions[$eid] = (int) $session['session_id'];
                }
            }
        }

        // 2. Bulk fetch attendance statuses
        $sessionIds        = array_values($finalizedSessions);
        $attendanceStatuses = $this->feedbackModel->getStudentAttendanceStatusesForSessions(
            $studentId,
            $sessionIds
        );

        // 3. Determine status per event
        $results         = [];
        $eligibleEventIds = [];

        foreach ($eventIds as $eid) {
            if (isset($activeEvents[$eid])) {
                $results[$eid] = 'not_finalized';
                continue;
            }

            if (!isset($finalizedSessions[$eid])) {
                $results[$eid] = 'not_finalized';
                continue;
            }

            $sessionId  = $finalizedSessions[$eid];
            $attStatus  = $attendanceStatuses[$sessionId] ?? null;

            if ($attStatus === null) {
                $results[$eid] = 'no_record';
            } elseif ($attStatus !== 'Present' && $attStatus !== 'Late') {
                $results[$eid] = 'absent';
            } else {
                $eligibleEventIds[] = $eid;
            }
        }

        // 4. Bulk check submission for eligible events
        $submittedMap = $this->feedbackModel->existsByStudentBulk($studentId, $eligibleEventIds);

        foreach ($eligibleEventIds as $eid) {
            $results[$eid] = ($submittedMap[$eid] ?? false) ? 'submitted' : 'available';
        }

        return $results;
    }

    /**
     * Build the full data needed for the student feedback page.
     *
     * Returns ['pending' => [...], 'submitted' => [...]]
     * Each submitted entry includes '_submitted_rating'.
     *
     * @param int   $studentId
     * @param array $applications
     * @return array
     */
    public function getStudentFeedbackPageData(int $studentId, array $applications): array
    {
        $eventIds = [];
        foreach ($applications as $app) {
            if (!empty($app['symposium_event_id'])) {
                $eventIds[] = (int) $app['symposium_event_id'];
            }
        }

        $statuses = $this->getStudentFeedbackStatusesForEvents($studentId, $eventIds);

        $pending          = [];
        $submitted        = [];
        $submittedEventIds = [];

        foreach ($applications as $app) {
            $eid    = (int) ($app['symposium_event_id'] ?? 0);
            $status = $statuses[$eid] ?? 'not_finalized';

            if ($status === 'available') {
                $pending[] = $app;
            } elseif ($status === 'submitted') {
                $submittedEventIds[]   = $eid;
                $app['_feedback_status'] = 'submitted';
                $submitted[$eid]         = $app;
            }
        }

        // Bulk fetch submitted ratings (one query)
        $ratings = $this->feedbackModel->getRatingsByStudentBulk($studentId, $submittedEventIds);
        foreach ($ratings as $eid => $rating) {
            if (isset($submitted[$eid])) {
                $submitted[$eid]['_submitted_rating'] = $rating;
            }
        }

        return [
            'pending'   => $pending,
            'submitted' => array_values($submitted),
        ];
    }

    // =========================================================================
    // STAFF-FACING FEEDBACK RETRIEVAL
    // =========================================================================

    /**
     * Get identified feedback for an event (for authorized staff).
     *
     * Returns: full_name, register_number, department_name,
     *          academic_year, rating, review.
     * Does NOT return: email, phone, DOB, password, student_id.
     *
     * @param int $eventId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getEventFeedback(int $eventId, int $limit = 50, int $offset = 0): array
    {
        return $this->feedbackModel->getEventFeedback($eventId, $limit, $offset);
    }

    /**
     * Get event feedback summary (response count, avg rating, response rate,
     * rating distribution, eligible count).
     *
     * @param int $eventId
     * @return array
     */
    public function getEventFeedbackSummary(int $eventId): array
    {
        $summary      = $this->feedbackModel->getSummaryByEvent($eventId);
        $distribution = $this->feedbackModel->getRatingDistribution($eventId);
        $eligibleCount = $this->feedbackModel->getEligibleParticipantCount($eventId);

        $responseRate = 0.0;
        if ($eligibleCount > 0) {
            $responseRate = round(($summary['response_count'] / $eligibleCount) * 100, 1);
        }

        return [
            'response_count' => $summary['response_count'],
            'avg_rating'     => $summary['avg_rating'],
            'eligible_count' => $eligibleCount,
            'response_rate'  => $responseRate,
            'distribution'   => $distribution,
        ];
    }

    // =========================================================================
    // AUTHORIZATION — FIC AND JUDGE
    // =========================================================================

    /**
     * Check if a Faculty In-Charge may view feedback for this event.
     *
     * Uses existing FacultyAssignmentModel::isAssigned() — checks
     * faculty_assignments.is_active = 1.
     *
     * @param int $eventId
     * @param int $userId
     * @return bool
     */
    public function canFICViewFeedback(int $eventId, int $userId): bool
    {
        return $this->facultyModel->isAssigned($eventId, $userId);
    }

    /**
     * Check if a Judge may view feedback for this event.
     *
     * Uses existing JudgeAssignmentModel::isAssigned().
     *
     * @param int $eventId
     * @param int $userId
     * @return bool
     */
    public function canJudgeViewFeedback(int $eventId, int $userId): bool
    {
        return $this->judgeModel->isAssigned($eventId, $userId);
    }
}
