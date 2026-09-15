<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : AttendanceService.php
 * Location    : app/Services/
 * Description : Business logic for the Attendance Module.
 *               Fully migrated to the Event-based (Symposium Event) workflow.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Open / close attendance sessions for symposium events
 * • Mark single attendance (online)
 * • Bulk mark attendance (online)
 * • Process offline sync batches (validate, insert/update)
 * • RBAC via FacultyAssignmentModel (FIC ownership enforcement)
 * • Audit logging for every attendance action
 * • Notifications on session open / close
 *
 * Offline Sync Strategy
 * -------------------------------------------------------------------------
 * 1. Client POSTs a JSON batch to POST /attendance/sync
 * 2. Server validates CSRF + session + FIC authorization
 * 3. For each record:
 *    - Validate required fields
 *    - Lookup application → must belong to this symposium_event_id
 *    - Look up or fall back to most recent session for this event
 *    - Upsert attendance_records
 * 4. Log the batch in offline_sync_queue
 * 5. Return JSON summary to client
 *
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Models\ApplicationModel;
use App\Models\AttendanceRecordModel;
use App\Models\AttendanceSessionModel;
use App\Models\AuditLogModel;
use App\Models\FacultyAssignmentModel;
use App\Models\NotificationModel;
use App\Models\SyncQueueModel;
use App\Models\SymposiumEventModel;

final class AttendanceService
{
    private AttendanceSessionModel $sessionModel;
    private AttendanceRecordModel  $recordModel;
    private SyncQueueModel         $syncModel;
    private SymposiumEventModel    $eventModel;
    private FacultyAssignmentModel $facultyModel;
    private ApplicationModel       $appModel;
    private AuditLogModel          $auditModel;
    private NotificationModel      $notifModel;

    public function __construct()
    {
        $this->sessionModel = new AttendanceSessionModel();
        $this->recordModel  = new AttendanceRecordModel();
        $this->syncModel    = new SyncQueueModel();
        $this->eventModel   = new SymposiumEventModel();
        $this->facultyModel = new FacultyAssignmentModel();
        $this->appModel     = new ApplicationModel();
        $this->auditModel   = new AuditLogModel();
        $this->notifModel   = new NotificationModel();
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function error(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }

    private function ok(string $message, array $extra = []): array
    {
        return array_merge(['success' => true, 'message' => $message], $extra);
    }

    // =========================================================================
    // AUTHORIZATION
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Check whether a user may MARK attendance for a symposium event.
     *
     * Rules:
     *  - Admin, Principal, HOD → view/report only (NOT marking)
     *  - Staff, Staff Coordinator → must be actively assigned as FIC
     *
     * @param int    $symposiumEventId
     * @param int    $userId
     * @param string $role
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function canMarkEventAttendance(
        int    $symposiumEventId,
        int    $userId,
        string $role
    ): bool {
        // Anyone explicitly assigned as Faculty In-Charge can mark attendance,
        // even if they hold a high-level role like HOD or Admin.
        return $this->facultyModel->isAssigned($symposiumEventId, $userId);
    }

    /**
     * -------------------------------------------------------------------------
     * Check whether a user may VIEW attendance for a symposium event.
     *
     * @param int    $symposiumEventId
     * @param int    $userId
     * @param string $role
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function canViewEventAttendance(
        int    $symposiumEventId,
        int    $userId,
        string $role
    ): bool {
        if (in_array($role, ['Admin', 'Principal', 'HOD', 'Staff Coordinator'], true)) {
            return true;
        }

        return $this->facultyModel->isAssigned($symposiumEventId, $userId);
    }

    // =========================================================================
    // SESSION MANAGEMENT
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Open an attendance session for a symposium event.
     *
     * @param int    $symposiumEventId
     * @param int    $userId
     * @param string $role
     * @param string $notes
     * @return array  ['success', 'message', 'session_id'?]
     * -------------------------------------------------------------------------
     */
    public function openEventAttendance(
        int    $symposiumEventId,
        int    $userId,
        string $role,
        string $notes = ''
    ): array {
        // GATE 1: FIC authorization
        if (!$this->canMarkEventAttendance($symposiumEventId, $userId, $role)) {
            return $this->error('You are not authorized to manage attendance for this event.');
        }

        // GATE 2: Event exists
        $event = $this->eventModel->findById($symposiumEventId);
        if (!$event) {
            return $this->error('Scheduled event not found.');
        }

        // GATE 3: Event supports attendance
        if (empty($event['supports_attendance'])) {
            return $this->error('Attendance tracking is disabled for this event.');
        }

        // GATE 4: No open session already
        $existing = $this->sessionModel->getActiveEventSession($symposiumEventId);
        if ($existing) {
            return $this->error('An attendance session is already open for this event. Close it before opening a new one.');
        }

        // Open session
        $sessionId = $this->sessionModel->openEventSession(
            $symposiumEventId,
            $userId,
            $notes ?: null
        );

        if (!$sessionId) {
            return $this->error('Failed to open attendance session. Please try again.');
        }

        // Audit log
        $this->auditModel->log(
            'Attendance',
            $sessionId,
            'Attendance Session Opened',
            $userId,
            'Event: ' . $event['event_name']
        );

        // Notify other FIC members for this event
        $ficList = $this->facultyModel->getForEvent($symposiumEventId);
        foreach ($ficList as $fic) {
            if ((int) $fic['user_id'] !== $userId) {
                $this->notifModel->create(
                    (int) $fic['user_id'],
                    'Attendance Opened',
                    'Attendance has been opened for: ' . $event['event_name']
                );
            }
        }

        return $this->ok('Attendance session opened successfully.', ['session_id' => $sessionId]);
    }

    /**
     * -------------------------------------------------------------------------
     * Close an attendance session for a symposium event.
     *
     * @param int    $sessionId
     * @param int    $symposiumEventId
     * @param int    $userId
     * @param string $role
     * @return array
     * -------------------------------------------------------------------------
     */
    public function closeEventAttendance(
        int    $sessionId,
        int    $symposiumEventId,
        int    $userId,
        string $role
    ): array {
        // Authorization
        if (!$this->canMarkEventAttendance($symposiumEventId, $userId, $role)) {
            return $this->error('You are not authorized to close this attendance session.');
        }

        // Validate session belongs to this event
        $session = $this->sessionModel->getSessionById($sessionId);
        if (!$session || (int) $session['symposium_event_id'] !== $symposiumEventId) {
            return $this->error('Attendance session not found for this event.');
        }

        if ($session['status'] === 'Closed') {
            return $this->error('This attendance session is already closed.');
        }

        $ok = $this->sessionModel->closeSession($sessionId, $userId);
        if (!$ok) {
            return $this->error('Failed to close the attendance session.');
        }

        $event   = $this->eventModel->findById($symposiumEventId);
        $summary = $this->recordModel->getSummaryByEvent($symposiumEventId);

        // Audit log
        $this->auditModel->log(
            'Attendance',
            $sessionId,
            'Attendance Session Closed',
            $userId,
            'Event: ' . ($event['event_name'] ?? $symposiumEventId)
        );

        // Notify all FIC members
        $ficList = $this->facultyModel->getForEvent($symposiumEventId);
        foreach ($ficList as $fic) {
            $this->notifModel->create(
                (int) $fic['user_id'],
                'Attendance Completed',
                sprintf(
                    'Attendance closed for "%s". Present: %d, Absent: %d, Late: %d.',
                    $event['event_name'] ?? 'Event',
                    $summary['present'],
                    $summary['absent'],
                    $summary['late']
                )
            );
        }

        return $this->ok('Attendance session closed successfully.', ['summary' => $summary]);
    }

    // =========================================================================
    // MARKING — ONLINE
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Mark attendance for a single participant (online path).
     *
     * @param int    $sessionId
     * @param int    $applicationId
     * @param string $status          'Present' | 'Absent' | 'Late'
     * @param int    $markedBy
     * @param int    $symposiumEventId
     * @param string $role
     * @param string $notes
     * @return array
     * -------------------------------------------------------------------------
     */
    public function markEventAttendance(
        int    $sessionId,
        int    $applicationId,
        int    $studentId,
        string $status,
        int    $markedBy,
        int    $symposiumEventId,
        string $role,
        string $notes = ''
    ): array {
        // Authorization
        if (!$this->canMarkEventAttendance($symposiumEventId, $markedBy, $role)) {
            return $this->error('You are not authorized to mark attendance for this event.');
        }

        // Validate status
        if (!in_array($status, ['Present', 'Absent', 'Late'], true)) {
            return $this->error('Invalid attendance status. Must be Present, Absent, or Late.');
        }

        // Validate session belongs to this event
        $session = $this->sessionModel->getSessionById($sessionId);
        if (!$session || (int) $session['symposium_event_id'] !== $symposiumEventId) {
            return $this->error('Invalid attendance session for this event.');
        }
        if ($session['status'] === 'Closed') {
            return $this->error('This attendance session is already closed.');
        }

        // Validate application
        $app = $this->appModel->findById($applicationId);
        if (!$app) {
            return $this->error('Application not found.');
        }
        if ((int) $app['symposium_event_id'] !== $symposiumEventId) {
            return $this->error('Application does not belong to this event.');
        }
        if (in_array($app['application_status'], ['Withdrawn', 'Cancelled'], true)) {
            return $this->error('Attendance cannot be marked for a withdrawn or cancelled application.');
        }

        // Upsert
        $attendanceId = $this->recordModel->upsertAttendance([
            'session_id'         => $sessionId,
            'symposium_event_id' => $symposiumEventId,
            'application_id'     => $applicationId,
            'student_id'         => $studentId,
            'attendance_status'  => $status,
            'marked_by'          => $markedBy,
            'coordinator_notes'  => $notes ?: null,
            'sync_source'        => 'Online',
        ]);

        if (!$attendanceId) {
            return $this->error('Failed to save attendance record.');
        }

        // Audit log
        $this->auditModel->log(
            'Attendance',
            $attendanceId,
            'Attendance Marked: ' . $status,
            $markedBy,
            'Application: ' . $app['application_no'] . ' | Event ID: ' . $symposiumEventId
        );

        return $this->ok(
            'Attendance marked as ' . $status . '.',
            ['attendance_id' => $attendanceId, 'status' => $status]
        );
    }

    /**
     * -------------------------------------------------------------------------
     * Bulk mark all registered participants in one operation.
     *
     * @param int    $sessionId
     * @param int    $symposiumEventId
     * @param string $defaultStatus
     * @param array  $overrides   [application_id => 'Present'|'Absent'|'Late']
     * @param int    $markedBy
     * @param string $role
     * @return array
     * -------------------------------------------------------------------------
     */
    public function bulkMarkEventAttendance(
        int    $sessionId,
        int    $symposiumEventId,
        string $defaultStatus,
        array  $overrides,
        int    $markedBy,
        string $role
    ): array {
        if (!$this->canMarkEventAttendance($symposiumEventId, $markedBy, $role)) {
            return $this->error('You are not authorized to mark attendance for this event.');
        }

        if (!in_array($defaultStatus, ['Present', 'Absent', 'Late'], true)) {
            return $this->error('Invalid default attendance status.');
        }

        $session = $this->sessionModel->getSessionById($sessionId);
        if (!$session || $session['status'] === 'Closed') {
            return $this->error('Attendance session is closed or not found.');
        }
        if ((int) $session['symposium_event_id'] !== $symposiumEventId) {
            return $this->error('Session does not belong to this event.');
        }

        // Get all participants for this event
        $participants = $this->recordModel->getParticipantsForEventAttendance(
            $symposiumEventId,
            $sessionId
        );

        if (empty($participants)) {
            return $this->error('No registered participants found for this event.');
        }

        // Build records
        $records = [];
        foreach ($participants as $p) {
            $appId = (int) $p['application_id'];
            $stuId = (int) $p['student_id'];
            $status = $overrides[$stuId] ?? $defaultStatus;
            if (!in_array($status, ['Present', 'Absent', 'Late'], true)) {
                $status = $defaultStatus;
            }

            $records[] = [
                'session_id'         => $sessionId,
                'symposium_event_id' => $symposiumEventId,
                'application_id'     => $appId,
                'student_id'         => (int) $p['student_id'],
                'attendance_status'  => $status,
                'marked_by'          => $markedBy,
                'coordinator_notes'  => null,
                'sync_source'        => 'Online',
            ];
        }

        $result = $this->recordModel->bulkUpsert($records);

        $this->auditModel->log(
            'Attendance',
            $sessionId,
            'Bulk Attendance Marked',
            $markedBy,
            sprintf(
                'Default: %s | Event ID: %d | Succeeded: %d | Failed: %d',
                $defaultStatus,
                $symposiumEventId,
                $result['succeeded'],
                $result['failed']
            )
        );

        return $this->ok(
            sprintf('Bulk attendance completed. %d marked, %d failed.', $result['succeeded'], $result['failed']),
            [
                'succeeded' => $result['succeeded'],
                'failed'    => $result['failed'],
            ]
        );
    }

    // =========================================================================
    // OFFLINE SYNC
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Process an offline sync batch submitted from a client device.
     *
     * @param array  $records           Array of attendance record payloads
     * @param int    $userId
     * @param string $role
     * @param int    $symposiumEventId
     * @param string $deviceId
     * @return array  ['success', 'message', 'succeeded', 'failed', 'conflicts', 'queue_id']
     * -------------------------------------------------------------------------
     */
    public function syncOfflineEventRecords(
        array  $records,
        int    $userId,
        string $role,
        int    $symposiumEventId,
        string $deviceId = ''
    ): array {
        // Authorization
        if (!$this->canMarkEventAttendance($symposiumEventId, $userId, $role)) {
            return $this->error('You are not authorized to sync attendance for this event.');
        }

        if (empty($records)) {
            return $this->error('No records received in sync payload.');
        }

        // Compute payload hash
        $appIds      = array_column($records, 'application_id');
        sort($appIds);
        $payloadHash = hash('sha256', $symposiumEventId . ':' . implode(',', $appIds));

        // Create sync queue entry
        $queueId = $this->syncModel->createEventEntry(
            $symposiumEventId,
            $userId,
            $payloadHash,
            count($records),
            $deviceId
        );

        // Get active session, or fall back to most recent session
        $session = $this->sessionModel->getActiveEventSession($symposiumEventId);
        if (!$session) {
            $sessions = $this->sessionModel->getSessionsByEvent($symposiumEventId);
            $session  = !empty($sessions) ? $sessions[0] : null;
        }

        $sessionId = $session ? (int) $session['session_id'] : 0;
        $sessionChangedInBatch = false;
        $succeeded = 0;
        $failed    = 0;
        $conflicts = 0;
        $errors    = [];
        $failedApps = [];

        // Pre-flight check: if there is no session AND the batch doesn't contain an OPEN_SESSION, fail.
        $hasOpenSessionCmd = false;
        foreach ($records as $rec) {
            if (($rec['application_id'] ?? 0) == 0 && ($rec['attendance_status'] ?? '') === 'OPEN_SESSION') {
                $hasOpenSessionCmd = true;
                break;
            }
        }
        if ($sessionId === 0 && !$hasOpenSessionCmd) {
            $this->syncModel->updateEntry($queueId, 0, count($records), 0, 'Failed',
                'No attendance session found for this event.');
            return $this->error('No attendance session found. Please open a session first.');
        }

        // Process records in strict chronological order (as captured on client)
        foreach ($records as $rec) {
            $appId        = (int) ($rec['application_id'] ?? 0);
            $status       = $rec['attendance_status'] ?? 'Absent';
            
            // If the record has a session ID AND we haven't changed sessions in this batch, use it. 
            // Otherwise fall back to the currently tracked $sessionId
            $recSessionId = (!empty($rec['session_id']) && !$sessionChangedInBatch) ? (int) $rec['session_id'] : $sessionId;

            // Handle offline session actions
            if ($appId === 0) {
                if ($status === 'OPEN_SESSION') {
                    $openResult = $this->openEventAttendance($symposiumEventId, $userId, $role, 'Opened offline');
                    if ($openResult['success']) {
                        // Update active sessionId for subsequent records in this batch
                        $sessionId = (int) ($openResult['session_id'] ?? 0);
                        $sessionChangedInBatch = true;
                        $succeeded++;
                    } else {
                        $failed++;
                        $errors[] = 'Failed to open session: ' . ($openResult['message'] ?? 'Unknown error');
                    }
                } elseif ($status === 'CLOSE_SESSION') {
                    $closeResult = $this->closeEventAttendance($recSessionId, $symposiumEventId, $userId, $role);
                    if ($closeResult['success'] || str_contains($closeResult['message'] ?? '', 'already closed')) {
                        $sessionId = 0; // Session is now closed, no active session
                        $sessionChangedInBatch = true;
                        $succeeded++;
                    } else {
                        $failed++;
                        $errors[] = 'Failed to close session: ' . ($closeResult['message'] ?? 'Unknown error');
                    }
                }
                continue;
            }

            // If a record appears before OPEN_SESSION and there's no active session, skip it
            if ($recSessionId === 0) {
                $failed++;
                $failedApps[] = $appId;
                $errors[] = "No active session available for application {$appId}";
                continue;
            }

            // Validate status
            if (!in_array($status, ['Present', 'Absent', 'Late'], true)) {
                $failed++;
                $failedApps[] = $appId;
                $errors[] = "Invalid status for application {$appId}";
                continue;
            }

            // Validate application belongs to this event
            $app = $this->appModel->findById($appId);
            if (!$app || (int) $app['symposium_event_id'] !== $symposiumEventId) {
                $failed++;
                $failedApps[] = $appId;
                $errors[] = "Application {$appId} not found or wrong event.";
                continue;
            }
            if (in_array($app['application_status'], ['Withdrawn', 'Cancelled'], true)) {
                $failed++;
                $failedApps[] = $appId;
                $errors[] = "Application {$appId} is withdrawn/cancelled.";
                continue;
            }

            // Parse client timestamp
            $clientTs = null;
            if (!empty($rec['client_timestamp'])) {
                $ts       = @strtotime($rec['client_timestamp']);
                $clientTs = $ts ? date('Y-m-d H:i:s', $ts) : null;
            }

            // Upsert
            $attendanceId = $this->recordModel->upsertAttendance([
                'session_id'         => $recSessionId,
                'symposium_event_id' => $symposiumEventId,
                'application_id'     => $appId,
                'student_id'         => !empty($rec['student_id']) ? (int) $rec['student_id'] : (int) $app['student_id'],
                'attendance_status'  => $status,
                'marked_by'          => $userId,
                'coordinator_notes'  => $rec['coordinator_notes'] ?? null,
                'sync_source'        => 'Offline',
                'client_device_id'   => $deviceId ?: null,
                'client_timestamp'   => $clientTs,
            ]);

            if ($attendanceId > 0) {
                $succeeded++;
            } else {
                $failed++;
                $failedApps[] = $appId;
                $errors[] = "DB insert failed for application {$appId}";
            }
        }

        // Determine final status
        $finalStatus = match(true) {
            $failed === 0 && $conflicts === 0 => 'Completed',
            $succeeded === 0                  => 'Failed',
            default                           => 'Partial',
        };

        // Update sync queue
        $this->syncModel->updateEntry(
            $queueId,
            $succeeded,
            $failed,
            $conflicts,
            $finalStatus,
            implode('; ', $errors)
        );

        // Audit log
        $this->auditModel->log(
            'Attendance',
            $queueId,
            'Offline Sync Completed',
            $userId,
            sprintf(
                'Event: %d | Device: %s | Submitted: %d | Succeeded: %d | Failed: %d | Status: %s',
                $symposiumEventId,
                $deviceId ?: 'unknown',
                count($records),
                $succeeded,
                $failed,
                $finalStatus
            )
        );

        $message = match($finalStatus) {
            'Completed' => "All {$succeeded} records synced successfully.",
            'Failed'    => "Sync failed. {$failed} records could not be saved.",
            default     => "Partial sync: {$succeeded} succeeded, {$failed} failed.",
        };

        return $this->ok($message, [
            'succeeded'    => $succeeded,
            'failed'       => $failed,
            'conflicts'    => $conflicts,
            'queue_id'     => $queueId,
            'final_status' => $finalStatus,
            'failed_apps'  => $failedApps,
            'errors'       => $errors,
        ]);
    }

    // =========================================================================
    // QUERIES
    // =========================================================================

    /**
     * Get all registered participants with their attendance status for a session.
     */
    public function getEventParticipantsWithAttendance(
        int $symposiumEventId,
        int $sessionId
    ): array {
        return $this->recordModel->getParticipantsForEventAttendance($symposiumEventId, $sessionId);
    }

    /**
     * Get attendance summary statistics for a symposium event.
     */
    public function getEventSummary(int $symposiumEventId): array
    {
        return $this->recordModel->getSummaryByEvent($symposiumEventId);
    }

    /**
     * Get department-wise attendance breakdown for a symposium event and session.
     */
    public function getEventDeptBreakdown(int $symposiumEventId, int $sessionId): array
    {
        return $this->recordModel->getDeptBreakdownByEvent($symposiumEventId, $sessionId);
    }

    /**
     * Get session history for a symposium event.
     */
    public function getEventSessionHistory(int $symposiumEventId): array
    {
        return $this->sessionModel->getSessionsByEvent($symposiumEventId);
    }
}
