<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FeedbackModel;
use App\Models\AttendanceSessionModel;
use App\Models\SymposiumEventModel;
use App\Models\FacultyAssignmentModel;
use App\Models\JudgeAssignmentModel;
use App\Core\Session;

class FeedbackService
{
    private FeedbackModel $feedbackModel;
    private AttendanceSessionModel $sessionModel;
    private SymposiumEventModel $eventModel;
    private FacultyAssignmentModel $facultyModel;
    private JudgeAssignmentModel $judgeModel;

    public function __construct()
    {
        $this->feedbackModel  = new FeedbackModel();
        $this->sessionModel   = new AttendanceSessionModel();
        $this->eventModel     = new SymposiumEventModel();
        $this->facultyModel   = new FacultyAssignmentModel();
        $this->judgeModel     = new JudgeAssignmentModel();
    }

    /**
     * Get the feedback anonymization secret.
     * @return string
     */
    private function getSecret(): string
    {
        $secret = $_ENV['FEEDBACK_ANON_SECRET'] ?? '';
        if (empty($secret)) {
            throw new \RuntimeException('FEEDBACK_ANON_SECRET is not configured.');
        }
        return $secret;
    }

    /**
     * Generate respondent hash.
     * 
     * @param int $studentId
     * @param int $eventId
     * @return string
     */
    public function generateHash(int $studentId, int $eventId): string
    {
        return hash_hmac('sha256', $studentId . '|' . $eventId, $this->getSecret());
    }

    /**
     * Check if an event's attendance is finalized (no open session, and has a closed session).
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
     * Get the finalized attendance session for an event.
     * 
     * @param int $eventId
     * @return array|false
     */
    public function getFinalizedSession(int $eventId)
    {
        return $this->sessionModel->getFinalizedEventSession($eventId);
    }

    /**
     * Get student attendance status.
     * 
     * @param int $studentId
     * @param int $eventId
     * @return string|null
     */
    public function getStudentAttendanceStatus(int $studentId, int $eventId): ?string
    {
        $session = $this->getFinalizedSession($eventId);
        if (!$session) {
            return null;
        }

        return $this->feedbackModel->getStudentAttendanceStatus($studentId, $eventId, (int)$session['session_id']);
    }

    /**
     * Check if student is eligible.
     * 
     * @param int $studentId
     * @param int $eventId
     * @return array ['eligible' => bool, 'reason' => string]
     */
    public function isStudentEligible(int $studentId, int $eventId): array
    {
        if (!$this->isAttendanceFinalized($eventId)) {
            return ['eligible' => false, 'reason' => 'Attendance is not finalized for this event yet.'];
        }

        $status = $this->getStudentAttendanceStatus($studentId, $eventId);
        if ($status === null) {
            return ['eligible' => false, 'reason' => 'You have no attendance record for this event.'];
        }

        if ($status !== 'Present' && $status !== 'Late') {
            return ['eligible' => false, 'reason' => 'Feedback is unavailable because your attendance was marked as Absent.'];
        }

        return ['eligible' => true, 'reason' => ''];
    }

    /**
     * Check if student has already submitted feedback.
     * 
     * @param int $studentId
     * @param int $eventId
     * @return bool
     */
    public function hasStudentSubmitted(int $studentId, int $eventId): bool
    {
        $hash = $this->generateHash($studentId, $eventId);
        return $this->feedbackModel->existsByHash($eventId, $hash);
    }

    /**
     * Process feedback submission.
     * 
     * @param int $studentId
     * @param int $eventId
     * @param int $rating
     * @param string|null $review
     * @return array
     */
    public function submitFeedback(int $studentId, int $eventId, int $rating, ?string $review): array
    {
        if ($studentId <= 0 || $eventId <= 0) {
            return ['success' => false, 'message' => 'Invalid parameters.'];
        }

        $event = $this->eventModel->findById($eventId);
        if (!$event) {
            return ['success' => false, 'message' => 'Event not found.'];
        }

        $eligibility = $this->isStudentEligible($studentId, $eventId);
        if (!$eligibility['eligible']) {
            return ['success' => false, 'message' => $eligibility['reason']];
        }

        if (!in_array($rating, [1, 2, 3, 4, 5], true)) {
            return ['success' => false, 'message' => 'Rating must be between 1 and 5.'];
        }

        if ($review !== null) {
            $review = trim($review);
            if (mb_strlen($review) > 2000) {
                return ['success' => false, 'message' => 'Review must not exceed 2000 characters.'];
            }
            if ($review === '') {
                $review = null;
            }
        }

        $hash = $this->generateHash($studentId, $eventId);

        if ($this->feedbackModel->existsByHash($eventId, $hash)) {
            return ['success' => false, 'message' => 'You have already submitted feedback for this event.'];
        }

        try {
            $id = $this->feedbackModel->insert($eventId, $hash, $rating, $review);

            if ($id > 0) {
                return ['success' => true, 'message' => 'Feedback submitted successfully.'];
            }
            return ['success' => false, 'message' => 'Failed to save feedback.'];
        } catch (\PDOException $e) {
            // SQLSTATE 23000 is Integrity constraint violation (e.g. duplicate key)
            if ($e->getCode() === '23000') {
                return ['success' => false, 'message' => 'You have already submitted feedback for this event.'];
            }
            // Log it in a real app, hide from user
            return ['success' => false, 'message' => 'A database error occurred.'];
        }
    }

    /**
     * Get single student feedback status for UI logic.
     * 
     * @param int $studentId
     * @param int $eventId
     * @return string
     */
    public function getStudentFeedbackStatusForEvent(int $studentId, int $eventId): string
    {
        if (!$this->isAttendanceFinalized($eventId)) {
            return 'not_finalized';
        }

        $status = $this->getStudentAttendanceStatus($studentId, $eventId);
        if ($status === null) {
            return 'no_record';
        }

        if ($status !== 'Present' && $status !== 'Late') {
            return 'absent';
        }

        if ($this->hasStudentSubmitted($studentId, $eventId)) {
            return 'submitted';
        }

        return 'available';
    }

    /**
     * Get bulk feedback statuses (avoids N+1 query problem).
     * 
     * @param int $studentId
     * @param array $eventIds
     * @return array
     */
    public function getStudentFeedbackStatusesForEvents(int $studentId, array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }

        // 1. Get finalized sessions for these events
        // Optimization: For simplicity here, we'll iterate, but it's small loops.
        // A full SQL IN() could be written in session model, but this is fine since it's cached / fast.
        $finalizedSessions = [];
        $activeEvents = [];
        
        foreach ($eventIds as $eid) {
            if ($this->sessionModel->getActiveEventSession($eid)) {
                $activeEvents[$eid] = true;
            } else {
                $session = $this->sessionModel->getFinalizedEventSession($eid);
                if ($session) {
                    $finalizedSessions[$eid] = (int)$session['session_id'];
                }
            }
        }

        // 2. Load attendance statuses in bulk
        $sessionIds = array_values($finalizedSessions);
        $attendanceStatuses = $this->feedbackModel->getStudentAttendanceStatusesForSessions($studentId, $sessionIds);

        // 3. Prepare hashes for eligible events
        $hashMap = [];
        $results = [];

        foreach ($eventIds as $eid) {
            if (isset($activeEvents[$eid])) {
                $results[$eid] = 'not_finalized';
                continue;
            }

            if (!isset($finalizedSessions[$eid])) {
                $results[$eid] = 'not_finalized'; // or no finalized session
                continue;
            }

            $sessionId = $finalizedSessions[$eid];
            $attStatus = $attendanceStatuses[$sessionId] ?? null;

            if ($attStatus === null) {
                $results[$eid] = 'no_record';
            } elseif ($attStatus !== 'Present' && $attStatus !== 'Late') {
                $results[$eid] = 'absent';
            } else {
                // Eligible, so compute hash for bulk check
                $hashMap[$eid] = $this->generateHash($studentId, $eid);
            }
        }

        // 4. Bulk check if already submitted
        $submittedEvents = $this->feedbackModel->existsByHashes($hashMap);

        foreach ($hashMap as $eid => $hash) {
            if ($submittedEvents[$eid] ?? false) {
                $results[$eid] = 'submitted';
            } else {
                $results[$eid] = 'available';
            }
        }

        return $results;
    }

    /**
     * Get feedback data for student page (Pending & Submitted).
     * 
     * @param int $studentId
     * @param array $applications
     * @return array
     */
    public function getStudentFeedbackPageData(int $studentId, array $applications): array
    {
        $eventIds = [];
        foreach ($applications as $app) {
            if (!empty($app['symposium_event_id'])) {
                $eventIds[] = (int)$app['symposium_event_id'];
            }
        }

        $statuses = $this->getStudentFeedbackStatusesForEvents($studentId, $eventIds);

        $pending = [];
        $submitted = [];
        
        // We also need rating for submitted ones
        $submittedHashMap = [];
        foreach ($applications as $app) {
            $eid = (int)($app['symposium_event_id'] ?? 0);
            if (!$eid) continue;

            $status = $statuses[$eid] ?? 'not_finalized';

            if ($status === 'available') {
                $pending[] = $app;
            } elseif ($status === 'submitted') {
                $submittedHashMap[$eid] = $this->generateHash($studentId, $eid);
                $app['_feedback_status'] = 'submitted';
                $submitted[$eid] = $app; // keyed by eid for rating lookup
            }
        }
        
        $ratings = $this->feedbackModel->getRatingsByHashes($submittedHashMap);
        foreach ($ratings as $eid => $rating) {
            if (isset($submitted[$eid])) {
                $submitted[$eid]['_submitted_rating'] = $rating;
            }
        }

        return [
            'pending' => $pending,
            'submitted' => array_values($submitted),
        ];
    }

    /**
     * Get feedback summary for an event.
     * 
     * @param int $eventId
     * @return array
     */
    public function getEventFeedbackSummary(int $eventId): array
    {
        $summary = $this->feedbackModel->getSummaryByEvent($eventId);
        $distribution = $this->feedbackModel->getRatingDistribution($eventId);
        $eligibleCount = $this->feedbackModel->getEligibleParticipantCount($eventId);

        $responseRate = 0;
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

    /**
     * Get anonymous reviews for an event.
     * 
     * @param int $eventId
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getEventAnonymousReviews(int $eventId, int $limit = 50, int $offset = 0): array
    {
        return $this->feedbackModel->getAnonymousReviews($eventId, $limit, $offset);
    }

    /**
     * Check if user can view FIC feedback.
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
     * Check if user can view Judge feedback.
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
