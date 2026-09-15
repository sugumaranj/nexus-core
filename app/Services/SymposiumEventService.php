<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : SymposiumEventService.php
 * Location    : app/Services/
 * Description : Business logic layer for Symposium Event Scheduling & Snapshotting.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Enforce Staff Coordinator & Admin RBAC
 * • Handle Existing Master Event attachment path (runs Snapshotting Engine)
 * • Handle New Master Event creation path (saves to library + attaches)
 * • Orchestrate DB transactions and audit logs
 *
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Database\Database;
use App\Models\AuditLogModel;
use App\Models\MasterEventModel;
use App\Models\SymposiumEventModel;
use App\Models\SymposiumModel;
use App\Models\UserModel;
use App\Models\VenueModel;
use App\Validators\SymposiumEventValidator;
use PDO;

final class SymposiumEventService
{
    private SymposiumEventModel     $model;
    private MasterEventModel        $masterModel;
    private MasterEventService      $masterService;
    private SymposiumModel          $symposiumModel;
    private VenueModel              $venueModel;
    private UserModel               $userModel;
    private SymposiumEventValidator $validator;
    private AuditLogModel           $auditModel;
    private PDO                     $db;

    public function __construct()
    {
        $this->model          = new SymposiumEventModel();
        $this->masterModel     = new MasterEventModel();
        $this->masterService   = new MasterEventService();
        $this->symposiumModel  = new SymposiumModel();
        $this->venueModel      = new VenueModel();
        $this->userModel       = new UserModel();
        $this->validator      = new SymposiumEventValidator();
        $this->auditModel      = new AuditLogModel();
        $this->db              = Database::getConnection();
    }

    /**
     * Can user manage events in this symposium?
     *
     * @param array $user
     * @param array|int $symposium
     * @return bool
     */
    public function canManage(array $user, int|array $symposium): bool
    {
        $role = $user['role'] ?? '';
        if (in_array($role, ['Admin'], true)) {
            return true;
        }

        if ($role === 'Staff Coordinator') {
            return true;
        }

        return false;
    }

    /**
     * Lookup options for scheduling form.
     *
     * @param int $symposiumId
     * @return array
     */
    public function getFormData(int $symposiumId): array
    {
        $symposium = $this->symposiumModel->findById($symposiumId);

        // Fetch all published master events
        $allMasterEvents = $this->masterModel->getAll(null, 'Published');

        // Fetch already attached event IDs for this symposium
        $stmt = $this->db->prepare("SELECT event_id FROM symposium_events WHERE symposium_id = ?");
        $stmt->execute([$symposiumId]);
        $attachedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Filter out attached events
        $availableMasterEvents = array_filter($allMasterEvents, function($me) use ($attachedIds) {
            return !in_array($me['event_id'], $attachedIds);
        });

        return [
            'symposium'           => $symposium,
            'master_events'       => array_values($availableMasterEvents),
            'venues'              => $this->venueModel->getAllActive(),
            'faculty_users'       => $this->userModel->getByRole('Staff'),
            'sessions'            => ['FN', 'AN', 'Full Day'],
            'event_modes'         => ['Offline', 'Online', 'Hybrid'],
            'categories'          => ['Technical', 'Non-Technical'],
            'participation_types' => ['Individual', 'Team', 'Both'],
            'prelim_types'        => ['MCQ', 'File Submission', 'Coding Test', 'Abstract Screening', 'Custom'],
            'judging_methods'     => ['Marks', 'Rubrics', 'Voting', 'Mixed'],
            'statuses'            => ['Scheduled', 'Registration Open', 'Registration Closed', 'Running', 'Completed', 'Cancelled'],
            'rule_sections'       => ['Eligibility', 'Topics', 'Materials Required', 'Judging Criteria', 'Restrictions', 'Disqualification', 'Notes'],
        ];
    }

    /**
     * Add an Existing Master Event to a Symposium (Existing Event Path).
     *
     * @param int $symposiumId
     * @param int $masterEventId
     * @param array $scheduleData
     * @param array $user
     * @return array ['success' => bool, 'errors' => array, 'symposium_event_id' => int|null, 'message' => string]
     */
    public function addExistingEvent(int $symposiumId, int $masterEventId, array $scheduleData, array $user): array
    {
        if (!$this->canManage($user, $symposiumId)) {
            return ['success' => false, 'errors' => [], 'message' => 'Unauthorized action. You cannot edit this symposium.'];
        }

        // Duplicate check
        if ($this->model->isDuplicate($symposiumId, $masterEventId)) {
            return ['success' => false, 'errors' => ['event_id' => 'This event template is already added to this symposium.'], 'message' => 'Duplicate event template selected.'];
        }

        $scheduleData['event_id'] = $masterEventId;
        $errors = $this->validator->validate($scheduleData, true);

        // Validate master overrides if any
        if (!empty($scheduleData['master']) && is_array($scheduleData['master'])) {
            $masterValidator = new \App\Validators\MasterEventValidator();
            $masterErrors = $masterValidator->validate($scheduleData['master'], false);
            // Prefix master errors to avoid collision
            foreach ($masterErrors as $k => $v) {
                $errors["master_$k"] = $v;
            }
        }

        // Check for Venue/Coordinator Conflicts & Capacity limits
        $conflicts = $this->checkConflicts($scheduleData);
        $errors = array_merge($errors, $conflicts);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => 'Validation failed. Please correct errors.'];
        }

        try {
            $this->db->beginTransaction();

            $id = $this->model->attachMasterEvent($symposiumId, $masterEventId, $scheduleData, (int)$user['user_id']);
            if (!$id) {
                $this->db->rollBack();
                return ['success' => false, 'errors' => [], 'message' => 'Failed to attach Master Event to Symposium.'];
            }

            // Log Audit
            $this->auditModel->log(
                'Symposium Events',
                $id,
                'ADD_SYMPOSIUM_EVENT',
                (int)$user['user_id'],
                "Attached Master Event ID {$masterEventId} to Symposium ID {$symposiumId}"
            );

            $this->db->commit();

            return [
                'success'            => true,
                'errors'             => [],
                'symposium_event_id' => $id,
                'message'            => 'Event successfully added to Symposium schedule.',
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'errors' => [], 'message' => 'Database Error: ' . $e->getMessage()];
        }
    }

    /**
     * Bulk attach all available published master events to a symposium as Draft/TBA.
     *
     * @param int $symposiumId
     * @param int $userId
     * @return int Number of events added
     */
    public function bulkAttachMasterEvents(int $symposiumId, int $userId): int
    {
        // Fetch all published master events
        $allMasterEvents = $this->masterModel->getAll(null, 'Published');

        // Fetch already attached event IDs
        $stmt = $this->db->prepare("SELECT event_id FROM symposium_events WHERE symposium_id = ?");
        $stmt->execute([$symposiumId]);
        $attachedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $addedCount = 0;
        foreach ($allMasterEvents as $me) {
            $eventId = (int)$me['event_id'];
            if (!in_array($eventId, $attachedIds)) {
                // Attach with empty/TBA schedule
                $schedule = [
                    'event_date'       => null,
                    'start_time'       => null,
                    'end_time'         => null,
                    'reporting_time'   => null,
                    'session'          => '',
                    'venue_id'         => null,
                    'faculty_coordinator_id' => null,
                    'status'           => 'Draft'
                ];
                $this->model->attachMasterEvent($symposiumId, $eventId, $schedule, $userId);
                $addedCount++;
            }
        }
        return $addedCount;
    }

    /**
     * Import specifically selected Master Events in bulk.
     *
     * @param int $symposiumId
     * @param array $masterEventIds
     * @param array $user
     * @return array
     */
    public function importBulkEvents(int $symposiumId, array $masterEventIds, array $user): array
    {
        if (!$this->canManage($user, $symposiumId)) {
            return ['success' => false, 'errors' => [], 'message' => 'Unauthorized action. You cannot edit this symposium.'];
        }

        if (empty($masterEventIds)) {
            return ['success' => false, 'errors' => [], 'message' => 'No events selected for import.'];
        }

        // Fetch already attached event IDs
        $stmt = $this->db->prepare("SELECT event_id FROM symposium_events WHERE symposium_id = ?");
        $stmt->execute([$symposiumId]);
        $attachedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $addedCount = 0;
        
        try {
            $this->db->beginTransaction();

            foreach ($masterEventIds as $idStr) {
                $eventId = (int)$idStr;
                if ($eventId > 0 && !in_array($eventId, $attachedIds)) {
                    // Attach with empty/TBA schedule
                    $schedule = [
                        'event_date'       => null,
                        'start_time'       => null,
                        'end_time'         => null,
                        'reporting_time'   => null,
                        'session'          => '',
                        'venue_id'         => null,
                        'faculty_coordinator_id' => null,
                        'status'           => 'Draft'
                    ];
                    $insertedId = $this->model->attachMasterEvent($symposiumId, $eventId, $schedule, (int)$user['user_id']);
                    
                    if ($insertedId) {
                        $this->auditModel->log(
                            'Symposium Events',
                            $insertedId,
                            'BULK_IMPORT_EVENT',
                            (int)$user['user_id'],
                            "Bulk imported Master Event ID {$eventId} to Symposium ID {$symposiumId}"
                        );
                        $addedCount++;
                    }
                }
            }

            $this->db->commit();
            return [
                'success' => true, 
                'errors' => [], 
                'message' => "Successfully imported {$addedCount} event(s)."
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'errors' => [], 'message' => 'Database error during bulk import: ' . $e->getMessage()];
        }
    }

    /**
     * Create a New Master Event AND immediately attach it to a Symposium (New Event Path).
     *
     * @param int $symposiumId
     * @param array $masterData
     * @param array $scheduleData
     * @param array $user
     * @return array
     */
    public function addNewEvent(int $symposiumId, array $masterData, array $scheduleData, array $user): array
    {
        if (!$this->canManage($user, $symposiumId)) {
            return ['success' => false, 'errors' => [], 'message' => 'Unauthorized action. You cannot edit this symposium.'];
        }

        // 1. Create Master Event first
        $masterResult = $this->masterService->createMasterEvent($masterData, $user);
        if (!$masterResult['success']) {
            return [
                'success' => false,
                'errors'  => $masterResult['errors'],
                'message' => 'Master Event Creation Failed: ' . $masterResult['message'],
            ];
        }

        $masterEventId = $masterResult['event_id'];

        // 2. Attach newly created Master Event to Symposium
        return $this->addExistingEvent($symposiumId, $masterEventId, $scheduleData, $user);
    }

    /**
     * Update an existing scheduled symposium event.
     *
     * @param int $symposiumEventId
     * @param array $data
     * @param array $user
     * @return array
     */
    public function updateEvent(int $symposiumEventId, array $data, array $user): array
    {
        $existing = $this->model->findById($symposiumEventId);
        if (!$existing) {
            return ['success' => false, 'errors' => [], 'message' => 'Scheduled event not found.'];
        }

        if (!$this->canManage($user, (int)$existing['symposium_id'])) {
            return ['success' => false, 'errors' => [], 'message' => 'Unauthorized action.'];
        }

        $errors = $this->validator->validate($data, false);
        $conflicts = $this->checkConflicts($data, $symposiumEventId);
        $errors = array_merge($errors, $conflicts);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => 'Validation failed. Please correct errors.'];
        }

        // Process overrides for Event Name, Description, and Rules
        if (isset($data['rules_text'])) {
            $masterService = new \App\Services\MasterEventService();
            $data['snapshot_rules'] = json_encode($masterService->parseRulesText($data['rules_text']), JSON_UNESCAPED_UNICODE);
        }

        // Process Prelims Configuration
        if (isset($data['prelim_decision'])) {
            if ($data['prelim_decision'] !== 'Required') {
                // If the staff coordinator changes decision to Pending/Not Required, clear existing stages
                $delStmt = $this->db->prepare("DELETE FROM competition_stages WHERE symposium_event_id = :id");
                $delStmt->execute(['id' => $symposiumEventId]);
            }
        }

        $updated = $this->model->updateSymposiumEvent($symposiumEventId, $data);

        if ($updated) {
            $this->auditModel->log(
                'Symposium Events',
                $symposiumEventId,
                'UPDATE_SYMPOSIUM_EVENT',
                (int)$user['user_id'],
                "Updated scheduling details for event: {$existing['event_name']}"
            );

            return ['success' => true, 'errors' => [], 'message' => 'Event schedule updated successfully.'];
        }

        return ['success' => false, 'errors' => [], 'message' => 'Failed to update event schedule.'];
    }

    /**
     * Check for scheduling conflicts and venue capacity limits (Req #7, #12).
     *
     * @param array $data
     * @param int|null $excludeId
     * @return array Array of conflict error strings. Empty array means clean.
     */
    public function checkConflicts(array $data, ?int $excludeId = null): array
    {
        $conflicts = [];

        $venueId   = (int)($data['venue_id'] ?? 0);
        $eventDate = $data['event_date'] ?? '';
        $startTime = $data['start_time'] ?? '';
        $endTime   = $data['end_time'] ?? '';
        $coordId   = (int)($data['faculty_coordinator_id'] ?? 0);
        $maxSeats  = !empty($data['max_participants']) ? (int)$data['max_participants'] : 0;

        if ($venueId > 0 && $eventDate !== '' && $startTime !== '' && $endTime !== '') {
            // 1. Venue Overlap Conflict
            $venueConflict = $this->model->checkVenueConflict($venueId, $eventDate, $startTime, $endTime, $excludeId);
            if ($venueConflict) {
                $conflicts['venue_id'] = "Venue Conflict: {$venueConflict['venue_name']} is already booked for '{$venueConflict['event_name']}' (" . \App\Helpers\DateHelper::time($venueConflict['start_time']) . " - " . \App\Helpers\DateHelper::time($venueConflict['end_time']) . ") in {$venueConflict['symposium_title']}.";
            }

            // 2. Venue Capacity Check (Req #7)
            if ($maxSeats > 0) {
                $venue = $this->venueModel->findById($venueId);
                if ($venue && $maxSeats > (int)$venue['seating_capacity']) {
                    $conflicts['max_participants'] = "Capacity Exceeded: Maximum seats requested ({$maxSeats}) exceeds venue seating capacity ({$venue['seating_capacity']} seats in {$venue['venue_name']}).";
                }
            }
        }

        // 3. Faculty Coordinator Overlap Conflict
        if ($coordId > 0 && $eventDate !== '' && $startTime !== '' && $endTime !== '') {
            $coordConflict = $this->model->checkCoordinatorConflict($coordId, $eventDate, $startTime, $endTime, $excludeId);
            if ($coordConflict) {
                $conflicts['faculty_coordinator_id'] = "Coordinator Conflict: {$coordConflict['coordinator_name']} is already assigned to '{$coordConflict['event_name']}' at the same time in {$coordConflict['symposium_title']}.";
            }
        }

        return $conflicts;
    }

    /**
     * Delete an event from a symposium.
     *
     * @param int $symposiumEventId
     * @param array $user
     * @return array
     */
    public function deleteEvent(int $symposiumEventId, array $user): array
    {
        $existing = $this->model->findById($symposiumEventId);
        if (!$existing) {
            return ['success' => false, 'message' => 'Scheduled event not found.'];
        }

        if (!$this->canManage($user, (int)$existing['symposium_id'])) {
            return ['success' => false, 'message' => 'Unauthorized action.'];
        }

        $deleted = $this->model->deleteSymposiumEvent($symposiumEventId);

        if ($deleted) {
            $this->auditModel->log(
                'Symposium Events',
                $symposiumEventId,
                'DELETE_SYMPOSIUM_EVENT',
                (int)$user['user_id'],
                "Removed event {$existing['event_name']} from symposium ID {$existing['symposium_id']}"
            );

            return ['success' => true, 'message' => "Event '{$existing['event_name']}' removed from symposium."];
        }

        return ['success' => false, 'message' => 'Failed to remove event from symposium.'];
    }

    /**
     * Delete ALL events from a symposium after verifying the requesting
     * user's own account password.
     *
     * @param int    $symposiumId
     * @param array  $user          Session user array (must have user_id)
     * @param string $password      Plain-text password submitted by the user
     * @return array{success: bool, message: string, deleted: int}
     */
    public function deleteAllEvents(int $symposiumId, array $user, string $password): array
    {
        // 1. Verify the symposium exists
        $symposium = $this->symposiumModel->findById($symposiumId);
        if (!$symposium) {
            return ['success' => false, 'message' => 'Symposium not found.', 'deleted' => 0];
        }

        // 2. Check management permission
        if (!$this->canManage($user, $symposium)) {
            return ['success' => false, 'message' => 'Unauthorized action.', 'deleted' => 0];
        }

        // 3. Verify the user's own password for this destructive action
        $fullUser = $this->userModel->findById((int)$user['user_id']);
        if (!$fullUser || !password_verify($password, $fullUser['password_hash'])) {
            return ['success' => false, 'message' => 'Incorrect password. Deletion cancelled.', 'deleted' => 0];
        }

        // 4. Perform the bulk delete
        $deleted = $this->model->deleteAllBySymposium($symposiumId);

        // 5. Audit log
        $this->auditModel->log(
            'Symposium Events',
            $symposiumId,
            'DELETE_ALL_SYMPOSIUM_EVENTS',
            (int)$user['user_id'],
            "Deleted all {$deleted} event(s) from symposium '{$symposium['title']}' (ID {$symposiumId})"
        );

        if ($deleted === 0) {
            return ['success' => true, 'message' => 'No events found to delete.', 'deleted' => 0];
        }

        return [
            'success' => true,
            'message' => "All {$deleted} event(s) have been permanently removed from '{$symposium['title']}'.",
            'deleted' => $deleted,
        ];
    }

    // =========================================================================
    // SCHEDULING MODULE — Phase 1
    // =========================================================================

    /**
     * Check if scheduling is allowed for a given symposium.
     * Delegates to SymposiumService::isSchedulingAllowed().
     *
     * @param array $symposium
     * @return bool
     */
    public function isSchedulingAllowed(array $symposium): bool
    {
        $symposiumService = new SymposiumService();
        return $symposiumService->isSchedulingAllowed($symposium);
    }

    /**
     * Schedule a single event (first-time scheduling).
     *
     * Validates:
     * - Symposium must be Approved
     * - event_date within symposium date range
     * - end_time > start_time
     * - No time overlap within same symposium
     *
     * @param int   $symposiumEventId
     * @param array $data  Must include event_date, start_time, end_time, session, reporting_time
     * @param array $user
     * @return array{success: bool, message: string, errors: array}
     */
    public function scheduleEvent(int $symposiumEventId, array $data, array $user): array
    {
        $event = $this->model->findById($symposiumEventId);
        if (!$event) {
            return ['success' => false, 'errors' => [], 'message' => 'Event not found.'];
        }

        $symposium = $this->symposiumModel->findById((int)$event['symposium_id']);
        if (!$symposium) {
            return ['success' => false, 'errors' => [], 'message' => 'Symposium not found.'];
        }

        // Gate: symposium must be Approved
        if (!$this->isSchedulingAllowed($symposium)) {
            return [
                'success' => false,
                'errors'  => [],
                'message' => 'Scheduling is only allowed after the symposium has been fully approved.',
            ];
        }

        if (!$this->canManage($user, $symposium)) {
            return ['success' => false, 'errors' => [], 'message' => 'Unauthorized.'];
        }

        // Validate
        $errors = $this->validateScheduleData($data, $symposium);

        // Within-symposium time overlap check
        if (empty($errors['start_time']) && !empty($data['event_date']) && !empty($data['start_time']) && !empty($data['end_time'])) {
            $overlap = $this->model->checkTimeOverlapWithinSymposium(
                (int)$event['symposium_id'],
                $data['event_date'],
                $data['start_time'],
                $data['end_time'],
                $symposiumEventId
            );
            if ($overlap) {
                $errors['start_time'] = "Time Conflict: '{$overlap['event_name']}' is already scheduled at "
                    . \App\Helpers\DateHelper::time($overlap['start_time']) . ' - '
                    . \App\Helpers\DateHelper::time($overlap['end_time'])
                    . ' on this date within the same symposium.';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => 'Validation failed.'];
        }

        $now    = date('Y-m-d H:i:s');
        $userId = (int)($user['user_id'] ?? 0);

        // Preserve the existing event status (e.g. 'Published') when the parent symposium
        // is already Approved or beyond. Only fall back to 'Draft' for pre-approval states.
        // This prevents scheduling an event from silently overwriting the 'Published' status
        // that bulkPublishBySymposium() set at approval time.
        $existingStatus = $event['status'] ?? 'Draft';
        $postApprovalSymposiumStatuses = [
            'Approved', 'Scheduling Complete', 'Registration Open',
            'Registration Closed', 'Completed',
        ];
        if (in_array($symposium['status'] ?? '', $postApprovalSymposiumStatuses, true)) {
            // Keep the current status as-is (Published / Registration Open / etc.)
            // The SymposiumEventStatusSyncService will auto-advance it via time windows.
            $eventStatusToSave = in_array($existingStatus, ['Draft', ''], true) ? 'Published' : $existingStatus;
        } else {
            $eventStatusToSave = 'Draft';
        }

        $updated = $this->model->updateSchedule($symposiumEventId, [
            'event_date'      => $data['event_date'],
            'start_time'      => $data['start_time'],
            'end_time'        => $data['end_time'],
            'session'         => $data['session'] ?? 'FN',
            'reporting_time'  => !empty($data['reporting_time']) ? $data['reporting_time'] : null,
            'schedule_status' => 'Scheduled',
            'scheduled_at'    => $now,
            'scheduled_by'    => $userId,
            'rescheduled_at'  => null,
            'rescheduled_by'  => null,
            'reschedule_reason' => null,
            'status'          => $eventStatusToSave,
        ]);

        if ($updated) {
            $this->auditModel->log(
                'Symposium Events',
                $symposiumEventId,
                'SCHEDULE_EVENT',
                $userId,
                "Scheduled '{$event['event_name']}' on {$data['event_date']} {$data['start_time']}-{$data['end_time']}"
            );
            
            // Purge PDF cache so the new schedule is reflected
            (new \App\Services\PdfDocumentService())->purgeCache((int)$event['symposium_id']);
            
            return ['success' => true, 'errors' => [], 'message' => "'{$event['event_name']}' has been scheduled successfully."];
        }

        return ['success' => false, 'errors' => [], 'message' => 'Failed to save schedule. Please try again.'];
    }

    /**
     * Reschedule an event (already has a schedule).
     *
     * Requires a mandatory reschedule_reason.
     * Validates same rules as scheduleEvent plus requires reason.
     *
     * @param int   $symposiumEventId
     * @param array $data  Must include event_date, start_time, end_time, session, reschedule_reason
     * @param array $user
     * @return array{success: bool, message: string, errors: array}
     */
    public function rescheduleEvent(int $symposiumEventId, array $data, array $user): array
    {
        $event = $this->model->findById($symposiumEventId);
        if (!$event) {
            return ['success' => false, 'errors' => [], 'message' => 'Event not found.'];
        }

        $symposium = $this->symposiumModel->findById((int)$event['symposium_id']);
        if (!$symposium) {
            return ['success' => false, 'errors' => [], 'message' => 'Symposium not found.'];
        }

        // Gate: symposium must be Approved (rescheduling locked at Registration Open)
        if (!$this->isSchedulingAllowed($symposium)) {
            return [
                'success' => false,
                'errors'  => [],
                'message' => 'Rescheduling is only allowed while the symposium is in Approved status.',
            ];
        }

        if (!$this->canManage($user, $symposium)) {
            return ['success' => false, 'errors' => [], 'message' => 'Unauthorized.'];
        }

        $errors = $this->validateScheduleData($data, $symposium);

        // Mandatory reschedule reason
        $reason = trim($data['reschedule_reason'] ?? '');
        if (empty($reason)) {
            $errors['reschedule_reason'] = 'Reschedule reason is required.';
        }

        // Within-symposium time overlap check
        if (empty($errors['start_time']) && !empty($data['event_date']) && !empty($data['start_time']) && !empty($data['end_time'])) {
            $overlap = $this->model->checkTimeOverlapWithinSymposium(
                (int)$event['symposium_id'],
                $data['event_date'],
                $data['start_time'],
                $data['end_time'],
                $symposiumEventId
            );
            if ($overlap) {
                $errors['start_time'] = "Time Conflict: '{$overlap['event_name']}' is already scheduled at "
                    . \App\Helpers\DateHelper::time($overlap['start_time']) . ' - '
                    . \App\Helpers\DateHelper::time($overlap['end_time'])
                    . ' on this date.';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'message' => 'Validation failed.'];
        }

        $now    = date('Y-m-d H:i:s');
        $userId = (int)($user['user_id'] ?? 0);

        $updated = $this->model->updateSchedule($symposiumEventId, [
            'event_date'        => $data['event_date'],
            'start_time'        => $data['start_time'],
            'end_time'          => $data['end_time'],
            'session'           => $data['session'] ?? 'FN',
            'reporting_time'    => !empty($data['reporting_time']) ? $data['reporting_time'] : null,
            'schedule_status'   => 'Rescheduled',
            'scheduled_at'      => $event['scheduled_at'],  // preserve original
            'scheduled_by'      => $event['scheduled_by'],  // preserve original
            'rescheduled_at'    => $now,
            'rescheduled_by'    => $userId,
            'reschedule_reason' => $reason,
            'status'            => $event['status'],
        ]);

        if ($updated) {
            $this->auditModel->log(
                'Symposium Events',
                $symposiumEventId,
                'RESCHEDULE_EVENT',
                $userId,
                "Rescheduled '{$event['event_name']}' to {$data['event_date']} {$data['start_time']}-{$data['end_time']}. Reason: {$reason}"
            );
            
            // Purge PDF cache so the new schedule is reflected
            (new \App\Services\PdfDocumentService())->purgeCache((int)$event['symposium_id']);
            
            return ['success' => true, 'errors' => [], 'message' => "'{$event['event_name']}' has been rescheduled."];
        }

        return ['success' => false, 'errors' => [], 'message' => 'Failed to reschedule. Please try again.'];
    }

    /**
     * Get scheduling dashboard data for a symposium (for SchedulingController).
     *
     * @param int $symposiumId
     * @return array
     */
    public function getSchedulingDashboardData(int $symposiumId): array
    {
        $allEvents   = $this->model->getBySymposium($symposiumId);
        $dashboard   = $this->model->getSchedulingDashboard($symposiumId);
        $groupedDays = $this->model->getGroupedByDay($symposiumId);

        return [
            'events'       => $allEvents,
            'dashboard'    => $dashboard,
            'grouped_days' => $groupedDays,
        ];
    }

    /**
     * AJAX: Check scheduling conflicts for a given date/time combination.
     *
     * Used by the AJAX conflict checker in the schedule form.
     *
     * @param int    $symposiumId
     * @param string $eventDate
     * @param string $startTime
     * @param string $endTime
     * @param int|null $excludeId
     * @return array{available: bool, conflicts: array}
     */
    public function checkSchedulingConflicts(
        int $symposiumId,
        string $eventDate,
        string $startTime,
        string $endTime,
        ?int $excludeId = null
    ): array {
        $conflicts = [];

        if ($eventDate && \App\Helpers\DateHelper::isWeekend($eventDate)) {
            $dayName = date('l', strtotime($eventDate));
            $conflicts[] = [
                'type'    => 'weekend',
                'message' => "Events cannot be scheduled on {$dayName}.",
            ];
        }

        if ($eventDate && $startTime && $endTime) {
            $overlap = $this->model->checkTimeOverlapWithinSymposium($symposiumId, $eventDate, $startTime, $endTime, $excludeId);
            if ($overlap) {
                $conflicts[] = [
                    'type'    => 'time_overlap',
                    'message' => "Conflict: '{$overlap['event_name']}' is already scheduled "
                        . \App\Helpers\DateHelper::time($overlap['start_time']) . ' - '
                        . \App\Helpers\DateHelper::time($overlap['end_time']),
                ];
            }
        }

        return [
            'available' => empty($conflicts),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Validate scheduling-specific fields.
     *
     * @param array $data
     * @param array $symposium
     * @return array  Field => error message
     */
    private function validateScheduleData(array $data, array $symposium): array
    {
        $errors = [];

        // Required fields
        $eventDate = trim($data['event_date'] ?? '');
        $startTime = trim($data['start_time'] ?? '');
        $endTime   = trim($data['end_time'] ?? '');
        $session   = trim($data['session'] ?? '');

        if (empty($eventDate)) {
            $errors['event_date'] = 'Event date is required.';
        }
        if (empty($startTime)) {
            $errors['start_time'] = 'Start time is required.';
        }
        if (empty($endTime)) {
            $errors['end_time'] = 'End time is required.';
        }
        if (empty($session)) {
            $errors['session'] = 'Session (FN/AN/Full Day) is required.';
        }

        // end_time > start_time
        if (!empty($startTime) && !empty($endTime)) {
            if ($endTime <= $startTime) {
                $errors['end_time'] = 'End time must be later than start time.';
            }

            // College working hours validation (10:00 AM to 3:30 PM)
            $collegeStart = '10:00:00';
            $collegeEnd   = '15:30:00';
            
            // Format input times to ensure correct string comparison (e.g., '10:00' to '10:00:00')
            $fStartTime = strlen($startTime) === 5 ? $startTime . ':00' : $startTime;
            $fEndTime   = strlen($endTime) === 5 ? $endTime . ':00' : $endTime;

            if ($fStartTime < $collegeStart) {
                $errors['start_time'] = 'Events cannot start before 10:00 AM.';
            }
            if ($fEndTime > $collegeEnd) {
                $errors['end_time'] = 'Events cannot end after 3:30 PM.';
            }
        }

        // event_date weekend validation
        if (!empty($eventDate) && \App\Helpers\DateHelper::isWeekend($eventDate)) {
            $dayName = date('l', strtotime($eventDate));
            $errors['event_date'] = "Events cannot be scheduled on {$dayName}.";
        }

        // event_date within symposium date range
        if (!empty($eventDate)) {
            $symStart = $symposium['event_start_date'] ?? '';
            $symEnd   = $symposium['event_end_date'] ?? '';
            if ($symStart && $eventDate < $symStart) {
                $errors['event_date'] = 'Event date cannot be before the symposium start date ('
                    . \App\Helpers\DateHelper::date($symStart) . ').';
            } elseif ($symEnd && $eventDate > $symEnd) {
                $errors['event_date'] = 'Event date cannot be after the symposium end date ('
                    . \App\Helpers\DateHelper::date($symEnd) . ').';
            }
        }

        return $errors;
    }
}

