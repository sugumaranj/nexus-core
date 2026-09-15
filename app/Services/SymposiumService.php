<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : SymposiumService.php
 * Location    : app/Services/
 * Description : Business logic layer for the Symposium module.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Auto-generate unique symposium codes (NEXUS-YYYY-NNN)
 * • CRUD with authorization checks
 * • Generic approval engine:
 *     - Organizers determined from symposium_departments junction table
 *     - HOD approvers resolved dynamically from organizing departments
 *     - Approval records created with approval_order for sequencing
 *     - Works for any symposium, not hardcoded to CS/CA
 * • Notify participants at each workflow transition
 * • Edit lock: editing after approval resets workflow to Revision Required
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Models\SymposiumModel;
use App\Models\SymposiumApprovalModel;
use App\Models\SymposiumDepartmentModel;
use App\Models\AuditLogModel;
use App\Models\NotificationModel;
use App\Models\SymposiumEventModel;
use App\Database\Database;
use App\Helpers\RoleHelper;
use PDO;

final class SymposiumService
{
    // -------------------------------------------------------------------------
    // Workflow status constants
    // -------------------------------------------------------------------------
    public const STATUS_DRAFT                = 'Draft';
    public const STATUS_SUBMITTED            = 'Submitted';
    public const STATUS_PENDING_HOD          = 'Pending HOD Approval';
    public const STATUS_PENDING_PRINCIPAL    = 'Pending Principal Approval';
    public const STATUS_APPROVED             = 'Approved';
    public const STATUS_SCHEDULING_COMPLETE  = 'Scheduling Complete';
    public const STATUS_REJECTED_HOD         = 'Rejected by HOD';
    public const STATUS_REJECTED_PRINCIPAL   = 'Rejected by Principal';
    public const STATUS_REGISTRATION_OPEN    = 'Registration Open';
    public const STATUS_REGISTRATION_CLOSE   = 'Registration Closed';
    public const STATUS_COMPLETED            = 'Completed';
    public const STATUS_CANCELLED            = 'Cancelled';

    /** Approval_order value reserved for Principal */
    private const PRINCIPAL_ORDER = 99;

    private SymposiumModel           $symposiumModel;
    private SymposiumApprovalModel   $approvalModel;
    private SymposiumDepartmentModel $deptModel;
    private AuditLogModel            $auditLog;
    private NotificationModel        $notificationModel;
    private DepartmentService        $departmentService;

    /**
     * Statuses that are part of the active workflow pipeline.
     * Editing while in these states triggers a workflow reset.
     */
    private array $lockedStatuses = [
        self::STATUS_PENDING_HOD,
        self::STATUS_PENDING_PRINCIPAL,
        self::STATUS_APPROVED,
        self::STATUS_SCHEDULING_COMPLETE,
        self::STATUS_REGISTRATION_OPEN,
        self::STATUS_REGISTRATION_CLOSE,
        self::STATUS_COMPLETED,
    ];

    /**
     * All valid statuses (for validation and dropdowns).
     */
    private array $allowedStatuses = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_PENDING_HOD,
        self::STATUS_PENDING_PRINCIPAL,
        self::STATUS_APPROVED,
        self::STATUS_SCHEDULING_COMPLETE,
        self::STATUS_REJECTED_HOD,
        self::STATUS_REJECTED_PRINCIPAL,
        self::STATUS_REGISTRATION_OPEN,
        self::STATUS_REGISTRATION_CLOSE,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    private array $allowedTypes = [
        'Intra Department',
        'Inter Department',
    ];

    public function __construct()
    {
        $this->symposiumModel    = new SymposiumModel();
        $this->approvalModel     = new SymposiumApprovalModel();
        $this->deptModel         = new SymposiumDepartmentModel();
        $this->auditLog          = new AuditLogModel();
        $this->notificationModel = new NotificationModel();
        $this->departmentService = new DepartmentService();
    }

    // =========================================================================
    // Public Accessors
    // =========================================================================

    public function getAvailableTypes(): array    { return $this->allowedTypes; }
    public function getAvailableStatuses(): array { return $this->allowedStatuses; }

    /**
     * Dynamically generate a range of academic years: currentYear-2 to currentYear+5.
     */
    public function getAcademicYears(): array
    {
        $current = (int) date('Y');
        $years   = [];

        for ($y = $current - 2; $y <= $current + 5; $y++) {
            $years[] = (string) $y;
        }

        return $years;
    }

    // =========================================================================
    // READ
    // =========================================================================

    public function getSymposiumById(int $symposiumId): array|false
    {
        $symposium = $this->symposiumModel->findById($symposiumId);

        if ($symposium) {
            // Attach organizing department list for display and logic
            $symposium['organizing_department_list'] = $this->deptModel->getDepartmentsForSymposium($symposiumId);
        }

        return $symposium;
    }

    public function searchSymposiums(
        string $search = '',
        string $departmentId = '',
        string $academicYear = '',
        string $symposiumType = '',
        string $status = '',
        string $quickFilter = '',
        array $user = []
    ): array {
        [$scopeCreatedBy, $scopeDeptIds] = $this->getScopeForUser($user);

        return $this->symposiumModel->search(
            $search       !== '' ? $search       : null,
            $academicYear !== '' ? $academicYear : null,
            $symposiumType !== '' ? $symposiumType : null,
            $status       !== '' ? $status       : null,
            $quickFilter  !== '' ? $quickFilter  : null,
            $scopeCreatedBy,
            $scopeDeptIds,
            $departmentId !== '' ? $departmentId : null
        );
    }

    public function getApprovals(int $symposiumId): array
    {
        return $this->approvalModel->getApprovalsForSymposium($symposiumId);
    }

    public function getAuditLogs(int $symposiumId): array
    {
        return $this->auditLog->getLogsForEntity('Symposium', $symposiumId);
    }

    public function getOrganizingDepartments(int $symposiumId): array
    {
        return $this->deptModel->getDepartmentsForSymposium($symposiumId);
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    /**
     * Create a new symposium.
     *
     * - Auto-generates the symposium code
     * - Links organizing departments from the junction table
     * - Brochure / Circular upload is NOT allowed at creation
     *
     * @param array $data          Form data (no symposium_code expected)
     * @param array $files         $_FILES (only banner is accepted)
     * @param array $user          Session user
     * @param array $departmentIds Organizing department IDs (from config/form)
     *
     * @return array{success: bool, message: string, symposium_id?: int}
     */
    public function createSymposium(array $data, array $files, array $user, array $departmentIds): array
    {
        if (!$this->canCreateSymposium($user)) {
            return ['success' => false, 'message' => 'You are not authorized to create symposiums.'];
        }

        if (empty($departmentIds)) {
            return ['success' => false, 'message' => 'At least one organizing department is required.'];
        }

        // Validate that all department IDs exist
        foreach ($departmentIds as $deptId) {
            if (!$this->departmentService->getDepartmentById((int) $deptId)) {
                return ['success' => false, 'message' => 'One or more selected departments do not exist.'];
            }
        }

        $academicYear = (int) trim((string) ($data['academic_year'] ?? 0));
        if ($academicYear === 0) {
            return ['success' => false, 'message' => 'Academic year is required.'];
        }

        // Auto-generate code
        $symposiumCode = $this->symposiumModel->generateNextCode($academicYear);

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // Use the primary department (first in list) for backward-compat FK
            $primaryDeptId = (int) $departmentIds[0];

            $newId = $this->symposiumModel->create([
                'symposium_code'           => $symposiumCode,
                'title'                    => trim((string) ($data['title'] ?? '')),
                'symposium_type'           => trim((string) ($data['symposium_type'] ?? '')),
                'organizing_department_id' => $primaryDeptId,
                'academic_year'            => $academicYear,
                'description'              => trim((string) ($data['description'] ?? '')),
                'brochure_path'            => null,
                'circular_path'            => null,
                'registration_start'       => $this->normalizeDateTime(trim((string) ($data['registration_start'] ?? ''))),
                'registration_end'         => $this->normalizeDateTime(trim((string) ($data['registration_end'] ?? ''))),
                'event_start_date'         => trim((string) ($data['event_start_date'] ?? '')),
                'event_end_date'           => trim((string) ($data['event_end_date'] ?? '')),
                'status'                   => self::STATUS_DRAFT,
                'created_by'               => (int) ($user['user_id'] ?? 0),
            ]);

            if ($newId === 0) {
                throw new \RuntimeException('Failed to insert symposium record.');
            }

            // Link organizing departments in junction table
            foreach ($departmentIds as $deptId) {
                $this->deptModel->add($newId, (int) $deptId, 'Organizer');
            }

            $this->auditLog->log('Symposium', $newId, 'Created', (int) ($user['user_id'] ?? 0));

            $db->commit();

            return [
                'success'       => true,
                'message'       => "Symposium created successfully. Code: {$symposiumCode}",
                'symposium_id'  => $newId,
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Unable to create symposium: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // UPDATE (with edit lock)
    // =========================================================================

    /**
     * Update a symposium's basic details.
     *
     * If the symposium is in a locked status (e.g., Approved), editing it
     * will reset the status to Revision Required and clear the approval queue.
     *
     * @param int   $symposiumId
     * @param array $data
     * @param array $files
     * @param array $user
     * @param array $departmentIds  New organizing department IDs
     *
     * @return array{success: bool, message: string}
     */
    public function updateSymposium(int $symposiumId, array $data, array $files, array $user, array $departmentIds): array
    {
        $symposium = $this->getSymposiumById($symposiumId);
        if (!$symposium) {
            return ['success' => false, 'message' => 'Symposium not found.'];
        }

        if (!$this->canEditSymposium($user, $symposium)) {
            return ['success' => false, 'message' => 'You are not authorized to edit this symposium.'];
        }

        if (empty($departmentIds)) {
            return ['success' => false, 'message' => 'At least one organizing department is required.'];
        }

        $currentStatus = $symposium['status'];
        $editLockTriggered = in_array($currentStatus, $this->lockedStatuses, true);

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $this->symposiumModel->update($symposiumId, [
                'title'              => trim((string) ($data['title'] ?? '')),
                'symposium_type'     => trim((string) ($data['symposium_type'] ?? '')),
                'academic_year'      => (int) ($data['academic_year'] ?? 0),
                'description'        => trim((string) ($data['description'] ?? '')),
                'registration_start' => $this->normalizeDateTime(trim((string) ($data['registration_start'] ?? ''))),
                'registration_end'   => $this->normalizeDateTime(trim((string) ($data['registration_end'] ?? ''))),
                'event_start_date'   => trim((string) ($data['event_start_date'] ?? '')),
                'event_end_date'     => trim((string) ($data['event_end_date'] ?? '')),
            ]);

            // Sync organizing departments
            $this->deptModel->syncDepartments($symposiumId, $departmentIds, 'Organizer');

            if ($editLockTriggered) {
                // Reset workflow — approval restarts from scratch
                $this->approvalModel->deleteForSymposium($symposiumId);
                $this->symposiumModel->updateStatus($symposiumId, self::STATUS_DRAFT);
                $this->auditLog->log('Symposium', $symposiumId, 'Edited after approval — workflow reset to Draft', (int) ($user['user_id'] ?? 0));
                $message = 'Symposium updated. Since it was already approved, the workflow has been reset to Draft. Please re-submit for approval.';
            } else {
                $this->auditLog->log('Symposium', $symposiumId, 'Updated', (int) ($user['user_id'] ?? 0));
                $message = 'Symposium updated successfully.';
            }

            $db->commit();

            // Purge cached PDFs so the next download re-renders with fresh data
            (new \App\Services\PdfDocumentService())->purgeCache($symposiumId);

            return ['success' => true, 'message' => $message];
        } catch (\Throwable $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Unable to update symposium: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // DELETE
    // =========================================================================

    public function deleteSymposium(int $symposiumId, array $user): array
    {
        $symposium = $this->getSymposiumById($symposiumId);
        if (!$symposium) {
            return ['success' => false, 'message' => 'Symposium not found.'];
        }

        if (!$this->canDeleteSymposium($user, $symposium)) {
            return ['success' => false, 'message' => 'You are not authorized to delete this symposium.'];
        }

        if ($this->symposiumModel->countCompetitions($symposiumId) > 0) {
            return ['success' => false, 'message' => 'Cannot delete symposium. Remove all scheduled events first.'];
        }


        if (!$this->symposiumModel->delete($symposiumId)) {
            return ['success' => false, 'message' => 'Unable to delete symposium.'];
        }

        return ['success' => true, 'message' => 'Symposium deleted successfully.'];
    }

    // =========================================================================
    // APPROVAL WORKFLOW — GENERIC ENGINE
    // =========================================================================

    /**
     * Submit a symposium for approval.
     *
     * Validates readiness (competitions, venues, coordinators).
     * Resolves HODs from the organizing departments.
     * Creates pending approval records with approval_order.
     * Notifies all HODs simultaneously.
     *
     * @param int   $symposiumId
     * @param array $user
     *
     * @return array{success: bool, message: string}
     */
    public function submitForApproval(int $symposiumId, array $user): array
    {
        $symposium = $this->getSymposiumById($symposiumId);
        if (!$symposium) {
            return ['success' => false, 'message' => 'Symposium not found.'];
        }

        if (!in_array($symposium['status'], [
            self::STATUS_DRAFT, 
            self::STATUS_REJECTED_HOD, 
            self::STATUS_REJECTED_PRINCIPAL
        ], true)) {
            return ['success' => false, 'message' => 'Symposium cannot be submitted from current status.'];
        }

        if (!$this->canEditSymposium($user, $symposium)) {
            return ['success' => false, 'message' => 'Unauthorized.'];
        }

        // --- Readiness validation ---
        $validation = $this->validateSubmissionReadiness($symposiumId);
        if (!$validation['ready']) {
            return ['success' => false, 'message' => $validation['message']];
        }

        // --- Resolve HODs from organizing departments ---
        $hods = $this->deptModel->getHodsForSymposium($symposiumId);
        if (empty($hods)) {
            return ['success' => false, 'message' => 'No HOD found for the organizing departments. Cannot submit.'];
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // Do NOT clear previous approval records (immutable history)
            // $this->approvalModel->deleteForSymposium($symposiumId);

            // Create a pending approval record and notification for EVERY required HOD
            foreach ($hods as $hod) {
                $stage = 'HOD-' . $hod['department_code'];
                
                $this->approvalModel->createPending([
                    'symposium_id'     => $symposiumId,
                    'approval_level'   => 'HOD',
                    'approval_stage'   => $stage,
                    'approval_order'   => 1, // Parallel approvals share the same logical order
                    'department_id'    => (int) $hod['department_id'],
                    'approver_user_id' => (int) $hod['user_id'],
                ]);

                $this->notificationModel->create(
                    (int) $hod['user_id'],
                    'Symposium Pending Your Approval',
                    "Symposium '{$symposium['title']}' has been submitted and requires your approval.",
                    "/symposiums/view?id={$symposiumId}",
                    (int) ($user['user_id'] ?? 0)
                );
            }

            // Update symposium status to the unified Pending HOD Approval
            $this->symposiumModel->updateStatus($symposiumId, self::STATUS_PENDING_HOD);
            $this->symposiumModel->updateSubmission($symposiumId, (int) ($user['user_id'] ?? 0));
            
            $this->auditLog->log('Symposium', $symposiumId, 'Submitted for HOD Approval', (int) ($user['user_id'] ?? 0));

            // Notify Staff Coordinator
            $this->notificationModel->create(
                (int) ($symposium['created_by'] ?? 0),
                'Symposium Submitted',
                "Your symposium '{$symposium['title']}' has been submitted and is pending HOD approval.",
                "/symposiums/view?id={$symposiumId}",
                (int) ($user['user_id'] ?? 0)
            );

            $db->commit();

            return ['success' => true, 'message' => 'Symposium submitted successfully.'];

        } catch (\Throwable $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Submission failed: ' . $e->getMessage()];
        }
    }

    /**
     * Approve a symposium at the current stage.
     *
     * HOD approval:
     *   - Finds the pending approval record for this user (or their department)
     *   - Marks it Approved
     *   - If all HOD approvals are done → escalates to Principal
     *
     * Principal approval:
     *   - Marks approved
     *   - Status → Approved
     *   - Notifies Staff Coordinator and Admin
     *
     * @param int    $symposiumId
     * @param string $remarks
     * @param array  $user
     *
     * @return array{success: bool, message: string}
     */
    public function approve(int $symposiumId, string $remarks, array $user): array
    {
        $symposium = $this->getSymposiumById($symposiumId);
        if (!$symposium) {
            return ['success' => false, 'message' => 'Symposium not found.'];
        }

        $currentStatus = $symposium['status'];
        $userId        = (int) ($user['user_id'] ?? 0);
        $userRole      = $user['role'] ?? '';

        // ---- HOD Stage ----
        if ($currentStatus === self::STATUS_PENDING_HOD) {
            if ($userRole !== 'HOD') {
                return ['success' => false, 'message' => 'Only a HOD can approve at this stage.'];
            }

            // Find the pending approval for this HOD
            $pendingApproval = $this->approvalModel->getPendingApprovalForUser($symposiumId, $userId);

            // Fallback: match by department if approver_user_id was not pre-assigned
            if (!$pendingApproval && $userRole === 'HOD') {
                $userDeptId = (int) ($user['department_id'] ?? 0);
                $pendingApproval = $this->approvalModel->getPendingApprovalForDepartment($symposiumId, $userDeptId);
            }

            if (!$pendingApproval) {
                return ['success' => false, 'message' => 'No pending HOD approval record found for you on this symposium.'];
            }

            $db = Database::getConnection();
            $db->beginTransaction();

            try {
                $this->approvalModel->updateApproval($pendingApproval['approval_id'], [
                    'status'           => 'Approved',
                    'approver_user_id' => $userId,
                    'remarks'          => $remarks,
                    'ip_address'       => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);

                $stage = $pendingApproval['approval_stage'];
                $this->auditLog->log(
                    'Symposium', $symposiumId,
                    "{$stage} Approved (Dept: {$pendingApproval['department_id']})",
                    $userId, $remarks
                );
                
                // Notify Staff Coordinator
                $this->notificationModel->create(
                    (int) ($symposium['created_by'] ?? 0),
                    'Symposium Stage Approved',
                    "Your symposium '{$symposium['title']}' has been approved by {$stage}.",
                    "/symposiums/view?id={$symposiumId}",
                    $userId
                );

                // Check if ALL required HODs have approved
                $pendingCount = $this->approvalModel->countPendingHodApprovals($symposiumId);

                if ($pendingCount > 0) {
                    $db->commit();
                    return ['success' => true, 'message' => "Approved successfully. Waiting for {$pendingCount} other HOD(s)."];
                } else {
                    // All HODs approved → escalate to Principal
                    $this->approvalModel->createPending([
                        'symposium_id'     => $symposiumId,
                        'approval_level'   => 'Principal',
                        'approval_stage'   => 'Principal',
                        'approval_order'   => self::PRINCIPAL_ORDER,
                        'department_id'    => null,
                        'approver_user_id' => null,
                    ]);

                    $this->symposiumModel->updateStatus($symposiumId, self::STATUS_PENDING_PRINCIPAL);
                    $this->auditLog->log('Symposium', $symposiumId, 'All HODs Approved — Escalated to Principal', $userId);

                    // Notify Principal
                    $this->notifyByRole('Principal', null,
                        'Symposium Pending Final Approval',
                        "Symposium '{$symposium['title']}' has been approved by all HODs and awaits your final approval.",
                        "/symposiums/view?id={$symposiumId}",
                        $userId
                    );
                    
                    // Notify Staff Coordinator
                    $this->notificationModel->create(
                        (int) ($symposium['created_by'] ?? 0),
                        'Symposium Pending Final Approval',
                        "Your symposium '{$symposium['title']}' has been approved by all HODs and awaits Principal approval.",
                        "/symposiums/view?id={$symposiumId}",
                        $userId
                    );

                    $db->commit();
                    return ['success' => true, 'message' => 'Approved. All HODs have approved — escalated to Principal.'];
                }

            } catch (\Throwable $e) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Approval failed: ' . $e->getMessage()];
            }
        }

        // ---- Principal Stage ----
        if ($currentStatus === self::STATUS_PENDING_PRINCIPAL) {
            if ($userRole !== 'Principal') {
                return ['success' => false, 'message' => 'Only the Principal can approve at this stage.'];
            }

            $pendingApproval = $this->approvalModel->getPendingPrincipalApproval($symposiumId);
            if (!$pendingApproval) {
                return ['success' => false, 'message' => 'No pending Principal approval record found.'];
            }

            $db = Database::getConnection();
            $db->beginTransaction();

            try {
                $this->approvalModel->updateApproval($pendingApproval['approval_id'], [
                    'status'           => 'Approved',
                    'approver_user_id' => $userId,
                    'remarks'          => $remarks,
                    'ip_address'       => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);

                $this->symposiumModel->updateStatus($symposiumId, self::STATUS_APPROVED);
                $this->auditLog->log('Symposium', $symposiumId, 'Principal Approved', $userId, $remarks);
                
                // Automatic Post-Approval Actions
                $this->auditLog->log('Symposium', $symposiumId, 'Official Circular Generated', $userId);
                $this->auditLog->log('Symposium', $symposiumId, 'Official Brochure Generated', $userId);
                $this->auditLog->log('Symposium', $symposiumId, 'Registration Portal Enabled', $userId);

                // Notify Staff Coordinator (creator)
                $this->notificationModel->create(
                    (int) $symposium['created_by'],
                    'Symposium Approved!',
                    "Your symposium '{$symposium['title']}' has been fully approved by the Principal. Circular and Brochure are now available. Registrations are open.",
                    "/symposiums/view?id={$symposiumId}",
                    $userId
                );

                // Notify Admin
                $this->notifyByRole('Admin', null,
                    'Symposium Approved',
                    "Symposium '{$symposium['title']}' has been approved by the Principal.",
                    "/symposiums/view?id={$symposiumId}",
                    $userId
                );

                $db->commit();

                // ── Trigger 1: Bulk-publish all Draft symposium events ────────────
                // Promotes every event attached to this symposium from Draft → Published
                // so they immediately appear to students and coordinators.
                try {
                    $seModel     = new SymposiumEventModel();
                    $published   = $seModel->bulkPublishBySymposium($symposiumId);

                    // Force an immediate time-based sync in case registration
                    // windows have already opened at the moment of approval.
                    $syncSvc     = new SymposiumEventStatusSyncService();
                    $syncSvc->syncAll();

                    $this->auditLog->log(
                        'Symposium', $symposiumId,
                        "Events Published: {$published} event(s) promoted Draft→Published",
                        $userId
                    );
                } catch (\Throwable $publishEx) {
                    // Non-critical — approval is already committed.
                    $this->auditLog->log(
                        'Symposium', $symposiumId,
                        'Event auto-publish failed (non-critical): ' . $publishEx->getMessage(),
                        $userId
                    );
                }

                // ── Trigger 2: Automated student email notifications ──────────────
                // Enqueues email jobs for all eligible students.
                // MUST be called AFTER $db->commit() so approval is permanent.
                // Email failure NEVER rolls back the approval.
                try {
                    $symEmailSvc = new \App\Services\SymposiumEmailService();
                    $symEmailSvc->sendApprovalAnnouncementEmails($symposiumId, $userId);
                } catch (\Throwable $emailEx) {
                    // Log silently — approval is already committed.
                    $this->auditLog->log(
                        'EmailNotification', $symposiumId,
                        'Email queue failed (non-critical): ' . $emailEx->getMessage(),
                        $userId
                    );
                }

                return ['success' => true, 'message' => 'Symposium has been fully approved! Events are now published.'];


            } catch (\Throwable $e) {
                $db->rollBack();
                return ['success' => false, 'message' => 'Approval failed: ' . $e->getMessage()];
            }
        }

        return ['success' => false, 'message' => 'Symposium is not currently pending approval.'];
    }

    /**
     * Request revision (reject) at any approval stage.
     *
     * Resets status to Revision Required and notifies the Staff Coordinator.
     *
     * @param int    $symposiumId
     * @param string $remarks    Must not be empty.
     * @param array  $user
     *
     * @return array{success: bool, message: string}
     */
    public function reject(int $symposiumId, string $remarks, array $user): array
    {
        $symposium = $this->getSymposiumById($symposiumId);
        if (!$symposium) {
            return ['success' => false, 'message' => 'Symposium not found.'];
        }

        $currentStatus = $symposium['status'];
        $userId        = (int) ($user['user_id'] ?? 0);
        $userRole      = $user['role'] ?? '';

        if ($currentStatus !== self::STATUS_PENDING_HOD && $currentStatus !== self::STATUS_PENDING_PRINCIPAL) {
            return ['success' => false, 'message' => 'Symposium is not pending approval.'];
        }

        // Verify authority
        if ($currentStatus === self::STATUS_PENDING_HOD) {
            if ($userRole !== 'HOD') {
                return ['success' => false, 'message' => 'Unauthorized.'];
            }
            $pendingApproval = $this->approvalModel->getPendingApprovalForUser($symposiumId, $userId);
            if (!$pendingApproval && $userRole === 'HOD') {
                $pendingApproval = $this->approvalModel->getPendingApprovalForDepartment($symposiumId, (int) ($user['department_id'] ?? 0));
            }
            if (!$pendingApproval) {
                return ['success' => false, 'message' => 'No pending HOD approval record found.'];
            }
            $approvalStage = $pendingApproval['approval_stage'];
            $newStatus = self::STATUS_REJECTED_HOD;
        } else {
            if ($userRole !== 'Principal') {
                return ['success' => false, 'message' => 'Unauthorized.'];
            }
            $pendingApproval = $this->approvalModel->getPendingPrincipalApproval($symposiumId);
            if (!$pendingApproval) {
                return ['success' => false, 'message' => 'No pending Principal approval record found.'];
            }
            $approvalStage   = 'Principal';
            $newStatus = self::STATUS_REJECTED_PRINCIPAL;
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            if ($pendingApproval) {
                $this->approvalModel->updateApproval($pendingApproval['approval_id'], [
                    'status'           => 'Rejected',
                    'approver_user_id' => $userId,
                    'remarks'          => $remarks,
                    'ip_address'       => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            }
            
            // Cancel any remaining parallel pending approvals (if HOD rejects, the others shouldn't stay pending)
            if ($currentStatus === self::STATUS_PENDING_HOD) {
                $stmt = $db->prepare("UPDATE symposium_approvals SET status = 'Cancelled' WHERE symposium_id = ? AND status = 'Pending'");
                $stmt->execute([$symposiumId]);
            }

            $this->symposiumModel->updateStatus($symposiumId, $newStatus);
            $this->auditLog->log('Symposium', $symposiumId, "Rejected by $approvalStage", $userId, $remarks);

            // Notify Staff Coordinator
            $this->notificationModel->create(
                (int) $symposium['created_by'],
                'Symposium Rejected',
                "Your symposium '{$symposium['title']}' has been rejected by {$approvalStage}. Remarks: {$remarks}",
                "/symposiums/view?id={$symposiumId}",
                $userId
            );

            $db->commit();
            return ['success' => true, 'message' => 'Symposium rejected. The coordinator has been notified.'];

        } catch (\Throwable $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Operation failed: ' . $e->getMessage()];
        }
    }

    // =========================================================================
    // SCHEDULING COMPLETE WORKFLOW
    // =========================================================================

    /**
     * Mark a symposium as Scheduling Complete.
     *
     * Transitions: Approved → Scheduling Complete.
     * All events must be scheduled before this is allowed.
     *
     * @param int   $symposiumId
     * @param array $user
     *
     * @return array{success: bool, message: string}
     */
    public function markSchedulingComplete(int $symposiumId, array $user): array
    {
        $symposium = $this->getSymposiumById($symposiumId);
        if (!$symposium) {
            return ['success' => false, 'message' => 'Symposium not found.'];
        }

        if ($symposium['status'] !== self::STATUS_APPROVED) {
            return ['success' => false, 'message' => 'Scheduling Complete can only be set when status is Approved.'];
        }

        if (!$this->canEditSymposium($user, $symposium)) {
            return ['success' => false, 'message' => 'Unauthorized. Only the Staff Coordinator can mark scheduling complete.'];
        }

        // Check all events have been scheduled
        $db   = Database::getConnection();
        $stmt = $db->prepare('SELECT COUNT(*) FROM symposium_events WHERE symposium_id = ? AND schedule_status = \'Unscheduled\'');
        $stmt->execute([$symposiumId]);
        $unscheduledCount = (int) $stmt->fetchColumn();

        if ($unscheduledCount > 0) {
            return [
                'success' => false,
                'message' => "Cannot mark as complete. {$unscheduledCount} event(s) are still unscheduled. Please schedule all events first.",
            ];
        }

        // Also check total events exist
        $stmt2 = $db->prepare('SELECT COUNT(*) FROM symposium_events WHERE symposium_id = ?');
        $stmt2->execute([$symposiumId]);
        $totalEvents = (int) $stmt2->fetchColumn();

        if ($totalEvents === 0) {
            return ['success' => false, 'message' => 'Cannot mark complete. No events have been added to this symposium.'];
        }

        $db->beginTransaction();
        try {
            $this->symposiumModel->updateStatus($symposiumId, self::STATUS_SCHEDULING_COMPLETE);
            $this->auditLog->log('Symposium', $symposiumId, 'Scheduling Complete — All events scheduled', (int) ($user['user_id'] ?? 0));

            // Notify Admin
            $this->notifyByRole('Admin', null,
                'Symposium Scheduling Complete',
                "Symposium '{$symposium['title']}' has completed event scheduling. Registration can now be opened.",
                "/symposiums/view?id={$symposiumId}",
                (int) ($user['user_id'] ?? 0)
            );

            $db->commit();

            return ['success' => true, 'message' => 'Scheduling marked as complete! You can now download the schedule PDF.'];
        } catch (\Throwable $e) {
            $db->rollBack();
            return ['success' => false, 'message' => 'Failed: ' . $e->getMessage()];
        }
    }


    /**
     * Check if scheduling is allowed for this symposium.
     * Scheduling is ONLY allowed when status is 'Approved'.
     *
     * @param array $symposium
     * @return bool
     */
    public function isSchedulingAllowed(array $symposium): bool
    {
        return in_array($symposium['status'] ?? '', [
            self::STATUS_APPROVED,
            self::STATUS_SCHEDULING_COMPLETE,
            self::STATUS_REGISTRATION_OPEN,
            self::STATUS_REGISTRATION_CLOSE
        ], true);
    }

    /**
     * Check if scheduling is locked (registration already started).
     *
     * @param array $symposium
     * @return bool
     */
    public function isSchedulingLocked(array $symposium): bool
    {
        return in_array($symposium['status'] ?? '', [
            self::STATUS_SCHEDULING_COMPLETE,
            self::STATUS_REGISTRATION_OPEN,
            self::STATUS_REGISTRATION_CLOSE,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ], true);
    }

    // =========================================================================
    // AUTHORIZATION
    // =========================================================================

    /**
     * Only Staff Coordinator can CREATE a symposium.
     * Admin is view-only for the symposium module.
     */
    public function canCreateSymposium(array $user): bool
    {
        return RoleHelper::canCreateSymposium($user['role'] ?? '');
    }

    public function canViewSymposium(array $user, array $symposium): bool
    {
        $role = $user['role'] ?? '';

        if (in_array($role, ['Admin', 'Principal'], true)) {
            return true;
        }

        if ($role === 'HOD') {
            // HOD can view if they are an approver OR if they organize it (including mapped departments)
            if ($this->canApprove($user, $symposium)) {
                return true;
            }

            $userDeptId = (int) ($user['department_id'] ?? 0);
            $db = Database::getConnection();
            $stmt = $db->prepare("
                SELECT d.department_id 
                FROM departments d 
                LEFT JOIN department_approvers da ON da.department_id = d.department_id 
                WHERE COALESCE(da.approver_department_id, d.department_id) = :dept_id
            ");
            $stmt->execute([':dept_id' => $userDeptId]);
            $deptIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            if (empty($deptIds)) {
                $deptIds = [$userDeptId];
            }
            
            $orgDepts = $this->deptModel->getDepartmentsForSymposium((int) ($symposium['symposium_id'] ?? 0));
            $orgDeptIds = array_column($orgDepts, 'department_id');
            
            return !empty(array_intersect($deptIds, $orgDeptIds));
        }

        if (in_array($role, ['Staff Coordinator', 'Staff'], true)) {
            // Both Staff and Staff Coordinator can view any symposium
            return true;
        }

        return false;
    }

    /**
     * Only the Staff Coordinator who CREATED the symposium can edit it.
     * Admin has view-only access; editing is NOT permitted for Admin.
     */
    public function canEditSymposium(array $user, array $symposium): bool
    {
        $role = $user['role'] ?? '';

        // Staff Coordinator can edit ANY symposium.
        if ($role === RoleHelper::STAFF_COORDINATOR) {
            return true;
        }

        return false;
    }

    /**
     * Staff Coordinator can delete only their own Draft or Rejected symposiums.
     * Admin cannot delete symposiums.
     */
    public function canDeleteSymposium(array $user, array $symposium): bool
    {
        $role = $user['role'] ?? '';

        $deletableStatuses = [
            self::STATUS_DRAFT,
            self::STATUS_REJECTED_HOD,
            self::STATUS_REJECTED_PRINCIPAL
        ];

        return $role === RoleHelper::STAFF_COORDINATOR
            && in_array($symposium['status'] ?? '', $deletableStatuses, true);
    }

    /**
     * Admin, Principal and Staff are view-only in the Symposium module.
     *
     * Admin and Principal can approve/reject (Principal) but cannot create/edit/delete.
     * Staff can only READ symposiums — no workflow actions whatsoever.
     *
     * @param array $user
     *
     * @return bool
     */
    public function canViewOnlySymposium(array $user): bool
    {
        return RoleHelper::hasRole($user, RoleHelper::ADMIN, RoleHelper::PRINCIPAL, RoleHelper::STAFF);
    }

    /**
     * Check if the user is a Staff member with strict view-only access.
     *
     * Staff can see all symposiums but cannot create, edit, delete, submit,
     * approve, or take any action on them.
     *
     * @param array $user
     *
     * @return bool
     */
    public function isStaffViewOnly(array $user): bool
    {
        return RoleHelper::isStaff($user);
    }

    /**
     * Check if the current user has a pending approval action on this symposium.
     *
     * @param array $user
     * @param array $symposium
     *
     * @return bool
     */
    public function canApprove(array $user, array $symposium): bool
    {
        $role   = $user['role'] ?? '';
        $status = $symposium['status'] ?? '';
        $userId = (int) ($user['user_id'] ?? 0);
        $symId  = (int) ($symposium['symposium_id'] ?? 0);

        if ($status === self::STATUS_PENDING_HOD && $role === 'HOD') {
            $pending = $this->approvalModel->getPendingApprovalForUser($symId, $userId);
            if (!$pending && $role === 'HOD') {
                $pending = $this->approvalModel->getPendingApprovalForDepartment($symId, (int) ($user['department_id'] ?? 0));
            }
            return $pending !== false;
        }

        if ($status === self::STATUS_PENDING_PRINCIPAL && $role === 'Principal') {
            return true;
        }

        return false;
    }

    // =========================================================================
    // DASHBOARD COUNTS
    // =========================================================================

    public function countAll(array $user): int
    {
        [$scopeCreatedBy, $scopeDeptIds] = $this->getScopeForUser($user);
        return $this->symposiumModel->countAll($scopeCreatedBy, $scopeDeptIds);
    }

    public function countByStatus(string $status, array $user): int
    {
        [$scopeCreatedBy, $scopeDeptIds] = $this->getScopeForUser($user);
        return $this->symposiumModel->countByStatus($status, $scopeCreatedBy, $scopeDeptIds);
    }

    /**
     * Count symposiums where the current user has a pending approval.
     *
     * @param array $user
     *
     * @return int
     */
    public function countPendingMyApproval(array $user): int
    {
        $role = $user['role'] ?? '';
        
        if ($role === 'Principal') {
            return $this->countPendingPrincipalApproval();
        }
        
        if ($role === 'HOD') {
            $userId = (int) ($user['user_id'] ?? 0);
            $deptId = (int) ($user['department_id'] ?? 0);

            $db  = Database::getConnection();
            $sql = "
                SELECT COUNT(DISTINCT sa.symposium_id) AS cnt
                FROM symposium_approvals sa
                WHERE sa.status = 'Pending'
                  AND sa.approval_level = 'HOD'
                  AND (sa.approver_user_id = :user_id OR sa.department_id = :dept_id)
            ";
            $stmt = $db->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'dept_id' => $deptId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int) ($row['cnt'] ?? 0);
        }
        
        return 0;
    }

    /**
     * Count symposiums where Principal has a pending approval.
     *
     * @return int
     */
    public function countPendingPrincipalApproval(): int
    {
        $db  = Database::getConnection();
        $sql = "SELECT COUNT(*) AS cnt FROM symposium_approvals WHERE approval_level = 'Principal' AND status = 'Pending'";
        $stmt = $db->query($sql);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['cnt'] ?? 0);
    }
    
    public function countApprovedToday(array $user): int
    {
        [$scopeCreatedBy, $scopeDeptIds] = $this->getScopeForUser($user);
        $db = Database::getConnection();
        
        $sql = "SELECT COUNT(DISTINCT s.symposium_id) AS cnt FROM symposiums s WHERE s.status = 'Approved' AND DATE(s.updated_at) = CURDATE()";
        
        if ($scopeCreatedBy !== null) {
            $sql .= " AND s.created_by = " . (int)$scopeCreatedBy;
        }
        
        if (!empty($scopeDeptIds)) {
            $sql .= " AND EXISTS (SELECT 1 FROM symposium_departments sd2 WHERE sd2.symposium_id = s.symposium_id AND sd2.department_id IN (" . implode(',', array_map('intval', $scopeDeptIds)) . "))";
        }
        
        $stmt = $db->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['cnt'] ?? 0);
    }

    public function getRecentActivity(array $user): array
    {
        // For recent activity, fetch latest audit logs related to symposiums scoped by user role
        [$scopeCreatedBy, $scopeDeptIds] = $this->getScopeForUser($user);

        $db = Database::getConnection();
        $sql = "
            SELECT DISTINCT al.*, u.full_name as user_name 
            FROM audit_logs al 
            LEFT JOIN users u ON al.user_id = u.user_id 
            LEFT JOIN symposiums s ON al.record_id = s.symposium_id
        ";
        $conditions = ["al.table_name = 'Symposium'"];
        $params = [];

        if (!empty($scopeDeptIds)) {
            $ids = implode(',', array_map('intval', $scopeDeptIds));
            $sql .= " LEFT JOIN symposium_departments sd ON sd.symposium_id = s.symposium_id";
            $conditions[] = "sd.department_id IN ($ids)";
        }

        if ($scopeCreatedBy !== null) {
            $conditions[] = "s.created_by = :created_by";
            $params['created_by'] = $scopeCreatedBy;
        }

        $sql .= " WHERE " . implode(' AND ', $conditions) . " ORDER BY al.action_time DESC LIMIT 5";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Validate that a symposium is ready for submission.
     *
     * Checks:
     *   - At least one competition exists
     *   - All competitions have a venue
     * @param int $symposiumId
     *
     * @return array{ready: bool, message: string}
     */
    private function validateSubmissionReadiness(int $symposiumId): array
    {
        $db = Database::getConnection();

        // Check master events count
        $stmt = $db->prepare('SELECT COUNT(*) FROM symposium_events WHERE symposium_id = ?');
        $stmt->execute([$symposiumId]);
        $seCount = (int) $stmt->fetchColumn();

        if ($seCount > 0) {
            // By user request: Allow submitting for approval without strict scheduling.
            // Venues, faculty coordinators, and times can be assigned later.
            
            return ['ready' => true, 'message' => 'Symposium is ready for submission.'];
        }


        // Fallback check for legacy competitions
        $stmt = $db->prepare('SELECT COUNT(*) FROM competitions WHERE symposium_id = ? AND is_deleted = 0');
        $stmt->execute([$symposiumId]);
        if ((int) $stmt->fetchColumn() === 0) {
            return ['ready' => false, 'message' => 'Add at least one scheduled event before submitting.'];
        }

        return ['ready' => true, 'message' => 'Symposium is ready for submission.'];
    }

    /**
     * Determine the scope (creator and dept) filters based on user role.
     *
     * @param array $user
     *
     * @return array  [scopeCreatedBy|null, scopeDeptIds[]]
     */
    private function getScopeForUser(array $user): array
    {
        $role = $user['role'] ?? '';

        // Admin and Principal see everything
        if (in_array($role, ['Admin', 'Principal'], true)) {
            return [null, []];
        }

        // HOD sees symposiums organized by their department and mapped sub-departments
        if ($role === 'HOD') {
            $deptId = (int) ($user['department_id'] ?? 0);
            if ($deptId > 0) {
                $db = Database::getConnection();
                $stmt = $db->prepare("
                    SELECT d.department_id 
                    FROM departments d 
                    LEFT JOIN department_approvers da ON da.department_id = d.department_id 
                    WHERE COALESCE(da.approver_department_id, d.department_id) = :dept_id
                ");
                $stmt->execute([':dept_id' => $deptId]);
                $deptIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                
                if (empty($deptIds)) {
                    $deptIds = [$deptId];
                }
                return [null, array_map('intval', $deptIds)];
            }
            return [null, []];
        }

        // Staff Coordinator sees ALL symposiums (full management access)
        if ($role === 'Staff Coordinator') {
            return [null, []];
        }

        // Staff sees ALL symposiums (strict view-only access — no create/edit/delete)
        // Staff cannot create symposiums, so scoping by created_by would show nothing.
        if ($role === 'Staff') {
            return [null, []];
        }

        return [null, []];
    }

    /**
     * Notify all users of a given role (and optionally department) in-app.
     *
     * @param string      $role
     * @param int|null    $departmentId
     * @param string      $title
     * @param string      $message
     * @param string      $url
     * @param int         $senderId
     */
    private function notifyByRole(
        string $role,
        ?int $departmentId,
        string $title,
        string $message,
        string $url,
        int $senderId
    ): void {
        $db  = Database::getConnection();
        $sql = "SELECT user_id FROM users WHERE role = :role AND account_status = 'Active'";
        $params = ['role' => $role];

        if ($departmentId !== null) {
            $sql .= ' AND department_id = :dept_id';
            $params['dept_id'] = $departmentId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($userIds as $uid) {
            $this->notificationModel->create((int) $uid, $title, $message, $url, $senderId);
        }
    }

    private function normalizeDateTime(string $value): string
    {
        return str_replace('T', ' ', trim($value));
    }

    private function processFileUpload(
        array $files,
        string $fieldName,
        string $folder,
        array $allowedExtensions,
        ?string $existingPath = null,
        bool $removeExisting = false
    ): array {
        if ($removeExisting && $existingPath !== null && $existingPath !== '') {
            $this->deleteUploadedFile($existingPath);
            $existingPath = null;
        }

        if (!isset($files[$fieldName]) || $files[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            return ['path' => $existingPath, 'error' => null];
        }

        $uploadedPath = $this->uploadFile($files[$fieldName], $folder, $allowedExtensions);
        if ($uploadedPath === null) {
            return ['path' => $existingPath, 'error' => ucfirst($fieldName) . ' upload failed.'];
        }

        if ($existingPath !== null && $existingPath !== '') {
            $this->deleteUploadedFile($existingPath);
        }

        return ['path' => $uploadedPath, 'error' => null];
    }

    private function uploadFile(array $file, string $folder, array $allowedExtensions): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            return null;
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/' . trim($folder, '/') . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = uniqid('symposium_', true) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
            return null;
        }

        return trim($folder, '/') . '/' . $fileName;
    }

    private function deleteUploadedFile(?string $relativePath): void
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return;
        }
        if (!str_starts_with($relativePath, 'uploads/symposiums/')) {
            return;
        }
        $filePath = dirname(__DIR__, 2) . '/public/' . $relativePath;
        if (is_file($filePath)) {
            unlink($filePath);
        }
    }
}
