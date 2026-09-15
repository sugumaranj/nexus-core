<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : JudgeController.php
 * Location    : app/Controllers/
 * Description : Judge portal — shows events the user is assigned to judge.
 *
 * NOTE: Evaluation module is not implemented yet.
 *       This controller only shows event assignments.
 *
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\JudgeAssignmentModel;

final class JudgeController extends BaseController
{
    private JudgeAssignmentModel $judgeModel;

    public function __construct()
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole(
            'Admin', 'Principal', 'HOD', 'Staff Coordinator', 'Staff'
        );

        $this->judgeModel = new JudgeAssignmentModel();
    }

    /**
     * GET /judge/dashboard
     * Shows a summary of events this user is assigned to judge.
     */
    public function dashboard(): void
    {
        $user   = $this->user();
        $userId = (int) ($user['user_id'] ?? 0);

        $assignedEvents = $this->judgeModel->getAssignedEventsForUser($userId);

        $stats = [
            'total_assigned' => count($assignedEvents),
            'upcoming'       => count(array_filter($assignedEvents, fn($e) => ($e['event_date'] ?? '') >= date('Y-m-d'))),
        ];

        $this->render('judge.dashboard', [
            'pageTitle'      => 'Judge Dashboard',
            'user'           => $user,
            'assignedEvents' => $assignedEvents,
            'stats'          => $stats,
        ]);
    }

    /**
     * GET /judge/competitions
     * Full list of events assigned to this judge.
     */
    public function competitions(): void
    {
        $user   = $this->user();
        $userId = (int) ($user['user_id'] ?? 0);

        $assignedEvents = $this->judgeModel->getAssignedEventsForUser($userId);

        $this->render('judge.competitions', [
            'pageTitle'      => 'My Judge Assignments',
            'user'           => $user,
            'assignedEvents' => $assignedEvents,
        ]);
    }
    
    /**
     * GET /judge/evaluate?id=
     * Show evaluation UI for a specific event
     */
    public function evaluate(): void
    {
        $user   = $this->user();
        $userId = (int) ($user['user_id'] ?? 0);
        $eventId = (int) ($_GET['id'] ?? 0);

        if (!$this->judgeModel->isAssigned($eventId, $userId)) {
            $this->redirect('/judge/dashboard', 'error', 'You are not assigned to evaluate this event.');
            return;
        }

        $eventModel = new \App\Models\SymposiumEventModel();
        $event = $eventModel->findById($eventId);
        if (!$event) {
            $this->redirect('/judge/dashboard', 'error', 'Event not found.');
            return;
        }

        $evalService = new \App\Services\EvaluationService();
        $evalModel = new \App\Models\EvaluationModel();
        
        $participants = $evalService->getEligibleParticipants($eventId);
        $resolver = new \App\Services\TeamResolverService();
        $resolvedParticipants = $resolver->resolveParticipantsForApplications(array_values($participants));
        
        $givenMarksRaw = $evalModel->getEvaluationsByJudge($eventId, $userId);
        
        // Key the marks by application_id for easy lookup in view
        $givenMarks = [];
        $hasFinalSubmission = false;
        foreach ($givenMarksRaw as $mark) {
            $givenMarks[$mark['application_id']] = $mark;
            if ($mark['status'] === 'Submitted') {
                $hasFinalSubmission = true;
            }
        }

        // --- Logic for Attendance Lock & Time Window ---
        $supportsAttendance = (bool) $event['supports_attendance'];
        $attendanceLocked = false;
        $windowExpired = false;
        $windowClosesAt = null;
        $attendancePendingMsg = '';

        if (!$supportsAttendance) {
            $attendanceLocked = true; // No attendance needed, always open for evaluation
        } else {
            $attendanceSessionModel = new \App\Models\AttendanceSessionModel();
            $activeSession = $attendanceSessionModel->getActiveEventSession($eventId);
            if ($activeSession) {
                $attendancePendingMsg = 'Attendance session is currently open. Evaluation will unlock after it is closed.';
            } else {
                $closedSession = $attendanceSessionModel->getFinalizedEventSession($eventId);
                if ($closedSession) {
                    $attendanceLocked = true;
                    // Calculate 42 hour window
                    $closedAtTime = !empty($closedSession['closed_at']) ? strtotime($closedSession['closed_at']) : time();
                    $deadline = $closedAtTime + (42 * 3600);
                    $windowClosesAt = date('Y-m-d H:i:s', $deadline);
                    if (time() > $deadline) {
                        $windowExpired = true;
                    }
                } else {
                    $attendancePendingMsg = 'Attendance has not been taken yet. Evaluation will unlock after attendance is finalized.';
                }
            }
        }
        
        $isLocked = (bool)$event['is_locked'] || $hasFinalSubmission || $windowExpired;

        $this->render('judge.evaluate', [
            'pageTitle'            => 'Evaluate - ' . $event['event_name'],
            'user'                 => $user,
            'event'                => $event,
            'participants'         => $participants,
            'resolvedParticipants' => $resolvedParticipants,
            'givenMarks'           => $givenMarks,
            'supportsAttendance'   => $supportsAttendance,
            'attendanceLocked'     => $attendanceLocked,
            'attendancePendingMsg' => $attendancePendingMsg,
            'windowClosesAt'       => $windowClosesAt,
            'windowExpired'        => $windowExpired,
            'hasFinalSubmission'   => $hasFinalSubmission,
            'isLocked'             => $isLocked,
            'maxScore'             => $event['snapshot_evaluation'] ? (json_decode($event['snapshot_evaluation'], true)['maximum_score'] ?? 100) : 100,
            'guidelines'           => $event['snapshot_evaluation'] ? (json_decode($event['snapshot_evaluation'], true)['scoring_guidelines'] ?? '') : ''
        ]);
    }

    /**
     * POST /judge/evaluate/bulk-submit?id=
     * Submit multiple marks for participants via AJAX
     */
    public function bulkSubmitMarks(): void
    {
        $user   = $this->user();
        $userId = (int) ($user['user_id'] ?? 0);
        $eventId = (int) ($_GET['id'] ?? 0);

        header('Content-Type: application/json');
        
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload || !isset($payload['marks']) || !is_array($payload['marks'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid payload.']);
            return;
        }

        $status = $payload['status'] ?? 'Draft';
        if (!in_array($status, ['Draft', 'Submitted'])) {
            $status = 'Draft';
        }

        // Re-validate time window to prevent bypass
        $eventModel = new \App\Models\SymposiumEventModel();
        $event = $eventModel->findById($eventId);
        if ($event && $event['supports_attendance']) {
            $attendanceSessionModel = new \App\Models\AttendanceSessionModel();
            $closedSession = $attendanceSessionModel->getFinalizedEventSession($eventId);
            if ($closedSession) {
                $deadline = (!empty($closedSession['closed_at']) ? strtotime($closedSession['closed_at']) : time()) + (42 * 3600);
                if (time() > $deadline) {
                    echo json_encode(['success' => false, 'message' => 'Evaluation time window (42 hours) has expired.']);
                    return;
                }
            }
        }

        // Check if already finally submitted
        $evalModel = new \App\Models\EvaluationModel();
        $givenMarksRaw = $evalModel->getEvaluationsByJudge($eventId, $userId);
        foreach ($givenMarksRaw as $m) {
            if ($m['status'] === 'Submitted') {
                echo json_encode(['success' => false, 'message' => 'You have already finalized your submission.']);
                return;
            }
        }

        $evalService = new \App\Services\EvaluationService();
        $result = $evalService->bulkSubmitEventEvaluation($eventId, $userId, $payload['marks'], $status);

        echo json_encode($result);
    }
}
