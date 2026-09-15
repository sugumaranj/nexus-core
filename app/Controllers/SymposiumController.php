<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : SymposiumController.php
 * Location    : app/Controllers/
 * Description : Thin controller for the Symposium module.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Route requests to SymposiumService
 * • Pass data to views
 * • Handle redirects and flash messages
 *
 * NOTE: No business logic here. All rules live in SymposiumService.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\DepartmentService;
use App\Services\SymposiumService;
use App\Services\PdfDocumentService;
use App\Validators\SymposiumValidator;

final class SymposiumController extends BaseController
{
    private SymposiumService   $symposiumService;
    private SymposiumValidator $validator;
    private DepartmentService  $departmentService;

    public function __construct()
    {
        AuthMiddleware::handle();
        $this->symposiumService  = new SymposiumService();
        $this->validator         = new SymposiumValidator();
        $this->departmentService = new DepartmentService();
    }

    // =========================================================================
    // LIST
    // =========================================================================

    public function index(): void
    {
        $search       = trim((string) ($_GET['search']        ?? ''));
        $departmentId = trim((string) ($_GET['department_id'] ?? ''));
        $academicYear = trim((string) ($_GET['academic_year'] ?? ''));
        $type         = trim((string) ($_GET['symposium_type'] ?? ''));
        $status       = trim((string) ($_GET['status']        ?? ''));
        $quickFilter  = trim((string) ($_GET['quick_filter']  ?? ''));

        $currentUser = $this->user();

        $symposiums = $this->symposiumService->searchSymposiums(
            $search, $departmentId, $academicYear, $type, $status, $quickFilter, $currentUser
        );

        foreach ($symposiums as &$s) {
            $s['can_edit']          = $this->symposiumService->canEditSymposium($currentUser, $s);
            $s['can_delete']        = $this->symposiumService->canDeleteSymposium($currentUser, $s);
        }
        unset($s);

        $this->render('symposiums.index', [
            'pageTitle'          => 'Symposium Management',
            'symposiums'         => $symposiums,
            'search'             => $search,
            'department_id'      => $departmentId,
            'academic_year'      => $academicYear,
            'symposium_type'     => $type,
            'status'             => $status,
            'quick_filter'       => $quickFilter,
            'departments'        => $this->departmentService->getAllDepartments(),
            'academicYears'      => $this->symposiumService->getAcademicYears(),
            'symposiumTypes'     => $this->symposiumService->getAvailableTypes(),
            'symposiumStatuses'  => $this->symposiumService->getAvailableStatuses(),
            'quickFilters'       => [
                SymposiumService::STATUS_PENDING_HOD,
                SymposiumService::STATUS_PENDING_PRINCIPAL,
                SymposiumService::STATUS_REGISTRATION_OPEN,
                SymposiumService::STATUS_REGISTRATION_CLOSE,
                SymposiumService::STATUS_COMPLETED,
            ],
            'totalSymposiums'        => $this->symposiumService->countAll($currentUser),
            'pendingCount'           => $this->symposiumService->countPendingMyApproval($currentUser),
            'approvedTodayCount'     => $this->symposiumService->countApprovedToday($currentUser),
            'rejectedCount'          => $this->symposiumService->countByStatus(SymposiumService::STATUS_REJECTED_HOD, $currentUser)
                                      + $this->symposiumService->countByStatus(SymposiumService::STATUS_REJECTED_PRINCIPAL, $currentUser),
            'completedCount'         => $this->symposiumService->countByStatus(SymposiumService::STATUS_COMPLETED,         $currentUser),
            'recentActivity'         => $this->symposiumService->getRecentActivity($currentUser),
            'canCreateSymposium'     => $this->symposiumService->canCreateSymposium($currentUser),
        ]);
    }

    // =========================================================================
    // CREATE
    // =========================================================================

    public function create(): void
    {
        $currentUser = $this->user();

        if (!$this->symposiumService->canCreateSymposium($currentUser)) {
            $this->error('You are not authorized to create symposiums.');
            $this->redirect('/symposiums');
        }

        // Default organizing departments: look up CS and CA by code
        $allDepts      = $this->departmentService->getAllDepartments();
        $defaultDeptIds = $this->getDefaultOrganizingDeptIds($allDepts);

        $this->render('symposiums.create', [
            'pageTitle'         => 'Create Symposium',
            'departments'       => $allDepts,
            'defaultDeptIds'    => $defaultDeptIds,
            'academicYears'     => $this->symposiumService->getAcademicYears(),
            'symposiumTypes'    => $this->symposiumService->getAvailableTypes(),
            'old'               => [],
            'errors'            => [],
        ]);
    }

    public function store(): void
    {
        $currentUser = $this->user();

        $errors = array_merge(
            $this->validator->validate($_POST, true),
            $this->validator->validateFiles($_FILES)
        );

        // Validate organizing department selection
        $departmentIds = $this->extractDepartmentIds($_POST);
        if (empty($departmentIds)) {
            $errors['organizing_departments'] = 'Please select at least one organizing department.';
        }

        $allDepts = $this->departmentService->getAllDepartments();

        if (!empty($errors)) {
            $this->render('symposiums.create', [
                'pageTitle'      => 'Create Symposium',
                'old'            => $_POST,
                'errors'         => $errors,
                'departments'    => $allDepts,
                'defaultDeptIds' => $departmentIds,
                'academicYears'  => $this->symposiumService->getAcademicYears(),
                'symposiumTypes' => $this->symposiumService->getAvailableTypes(),
            ]);
            return;
        }

        $result = $this->symposiumService->createSymposium($_POST, $_FILES, $currentUser, $departmentIds);

        if (!$result['success']) {
            $this->render('symposiums.create', [
                'pageTitle'      => 'Create Symposium',
                'error'          => $result['message'],
                'old'            => $_POST,
                'errors'         => [],
                'departments'    => $allDepts,
                'defaultDeptIds' => $departmentIds,
                'academicYears'  => $this->symposiumService->getAcademicYears(),
                'symposiumTypes' => $this->symposiumService->getAvailableTypes(),
            ]);
            return;
        }

        $this->success($result['message']);

        // Redirect to view so coordinator can immediately add competitions
        $redirectTo = isset($result['symposium_id'])
            ? '/symposiums/view?id=' . $result['symposium_id']
            : '/symposiums';

        $this->redirect($redirectTo);
    }

    // =========================================================================
    // EDIT
    // =========================================================================

    public function edit(): void
    {
        $symposiumId = (int) ($_GET['id'] ?? 0);
        $symposium   = $this->symposiumService->getSymposiumById($symposiumId);

        if (!$symposium) {
            $this->error('Symposium not found.');
            $this->redirect('/symposiums');
        }

        $currentUser = $this->user();

        if (!$this->symposiumService->canEditSymposium($currentUser, $symposium)) {
            $this->error('You are not authorized to edit this symposium.');
            $this->redirect('/symposiums');
        }

        $allDepts      = $this->departmentService->getAllDepartments();
        $currentDeptIds = $this->symposiumService->getOrganizingDepartments($symposiumId);
        $selectedIds    = array_column($currentDeptIds, 'department_id');

        $this->render('symposiums.edit', [
            'pageTitle'      => 'Edit Symposium',
            'symposium'      => $symposium,
            'departments'    => $allDepts,
            'selectedDeptIds' => array_map('intval', $selectedIds),
            'academicYears'  => $this->symposiumService->getAcademicYears(),
            'symposiumTypes' => $this->symposiumService->getAvailableTypes(),
            'old'            => [],
            'errors'         => [],
        ]);
    }

    public function update(): void
    {
        $symposiumId = (int) ($_POST['symposium_id'] ?? 0);
        $symposium   = $this->symposiumService->getSymposiumById($symposiumId);

        if (!$symposium) {
            $this->error('Symposium not found.');
            $this->redirect('/symposiums');
        }

        $currentUser = $this->user();

        if (!$this->symposiumService->canEditSymposium($currentUser, $symposium)) {
            $this->error('You are not authorized to update this symposium.');
            $this->redirect('/symposiums');
        }

        $errors = array_merge(
            $this->validator->validate($_POST, false),
            $this->validator->validateFiles($_FILES)
        );

        $departmentIds = $this->extractDepartmentIds($_POST);
        if (empty($departmentIds)) {
            $errors['organizing_departments'] = 'Please select at least one organizing department.';
        }

        $allDepts = $this->departmentService->getAllDepartments();

        if (!empty($errors)) {
            $this->render('symposiums.edit', [
                'pageTitle'       => 'Edit Symposium',
                'symposium'       => $symposium,
                'old'             => $_POST,
                'errors'          => $errors,
                'departments'     => $allDepts,
                'selectedDeptIds' => array_map('intval', $departmentIds),
                'academicYears'   => $this->symposiumService->getAcademicYears(),
                'symposiumTypes'  => $this->symposiumService->getAvailableTypes(),
            ]);
            return;
        }

        $result = $this->symposiumService->updateSymposium($symposiumId, $_POST, $_FILES, $currentUser, $departmentIds);

        if (!$result['success']) {
            $this->render('symposiums.edit', [
                'pageTitle'       => 'Edit Symposium',
                'symposium'       => $symposium,
                'error'           => $result['message'],
                'old'             => $_POST,
                'errors'          => [],
                'departments'     => $allDepts,
                'selectedDeptIds' => array_map('intval', $departmentIds),
                'academicYears'   => $this->symposiumService->getAcademicYears(),
                'symposiumTypes'  => $this->symposiumService->getAvailableTypes(),
            ]);
            return;
        }

        $this->success($result['message']);
        $this->redirect('/symposiums/view?id=' . $symposiumId);
    }

    // =========================================================================
    // VIEW
    // =========================================================================

    public function view(): void
    {
        $symposiumId = (int) ($_GET['id'] ?? 0);
        $symposium   = $this->symposiumService->getSymposiumById($symposiumId);

        if (!$symposium) {
            $this->error('Symposium not found.');
            $this->redirect('/symposiums');
        }

        $currentUser = $this->user();

        if (!$this->symposiumService->canViewSymposium($currentUser, $symposium)) {
            $this->error('You are not authorized to view this symposium.');
            $this->redirect('/symposiums');
        }

        $approvals  = $this->symposiumService->getApprovals($symposiumId);
        $auditLogs  = $this->symposiumService->getAuditLogs($symposiumId);

        // Staff role has zero action capabilities — force approval flag off
        $isStaffViewOnly = $this->symposiumService->isStaffViewOnly($currentUser);
        $canApprove      = !$isStaffViewOnly && $this->symposiumService->canApprove($currentUser, $symposium);

        $symposiumEventModel = new \App\Models\SymposiumEventModel();
        $symposiumEvents     = $symposiumEventModel->getBySymposium($symposiumId);

        $isCreator = (int) ($currentUser['user_id'] ?? 0) === (int) ($symposium['created_by'] ?? 0);

        // Staff can never submit — only Staff Coordinator (creator) can
        $canSubmit = !$isStaffViewOnly
            && $isCreator
            && in_array($symposium['status'], [
                SymposiumService::STATUS_DRAFT, 
                SymposiumService::STATUS_REJECTED_HOD,
                SymposiumService::STATUS_REJECTED_PRINCIPAL
            ], true);
        

        $this->render('symposiums.view', [
            'pageTitle'          => 'View Symposium — ' . ($symposium['title'] ?? ''),
            'symposium'          => $symposium,
            'symposiumEvents'    => $symposiumEvents,

            'approvals'          => $approvals,
            'auditLogs'          => $auditLogs,
            'canSubmit'          => $canSubmit,
            'canApprove'         => $canApprove,
            'canEdit'            => !$isStaffViewOnly && $this->symposiumService->canEditSymposium($currentUser, $symposium),
            'canDelete'          => !$isStaffViewOnly && $this->symposiumService->canDeleteSymposium($currentUser, $symposium),
            'isStaffViewOnly'    => $isStaffViewOnly,
            'symposiumStatuses'  => $this->symposiumService->getAvailableStatuses(),
        ]);
    }

    // =========================================================================
    // DELETE
    // =========================================================================

    public function delete(): void
    {
        $symposiumId = (int) ($_POST['symposium_id'] ?? 0);
        $currentUser = $this->user();
        $result      = $this->symposiumService->deleteSymposium($symposiumId, $currentUser);

        if (!$result['success']) {
            $this->error($result['message']);
            $this->redirect('/symposiums');
            return;
        }

        $this->success($result['message']);
        $this->redirect('/symposiums');
    }

    // =========================================================================
    // WORKFLOW ACTIONS
    // =========================================================================

    public function submit(): void
    {
        $symposiumId = (int) ($_POST['symposium_id'] ?? 0);
        $currentUser = $this->user();

        $result = $this->symposiumService->submitForApproval($symposiumId, $currentUser);

        if (!$result['success']) {
            $this->error($result['message']);
        } else {
            $this->success($result['message']);
        }

        $this->redirect('/symposiums/view?id=' . $symposiumId);
    }

    public function approve(): void
    {
        $symposiumId = (int) ($_POST['symposium_id'] ?? 0);
        $remarks     = trim((string) ($_POST['remarks'] ?? ''));
        $currentUser = $this->user();

        $result = $this->symposiumService->approve($symposiumId, $remarks, $currentUser);

        if (!$result['success']) {
            $this->error($result['message']);
        } else {
            $this->success($result['message']);
        }

        $this->redirect('/symposiums/view?id=' . $symposiumId);
    }

    public function reject(): void
    {
        $symposiumId = (int) ($_POST['symposium_id'] ?? 0);
        $remarks     = trim((string) ($_POST['remarks'] ?? ''));

        if (empty($remarks)) {
            $this->error('Remarks are required when requesting revision.');
            $this->redirect('/symposiums/view?id=' . $symposiumId);
            return;
        }

        $currentUser = $this->user();
        $result      = $this->symposiumService->reject($symposiumId, $remarks, $currentUser);

        if (!$result['success']) {
            $this->error($result['message']);
        } else {
            $this->success($result['message']);
        }

        $this->redirect('/symposiums/view?id=' . $symposiumId);
    }

    // =========================================================================
    // PDF GENERATION
    // =========================================================================

    public function generatePdf(): void
    {
        $symposiumId = (int) ($_GET['id'] ?? 0);
        $type        = trim((string) ($_GET['type'] ?? ''));
        $currentUser = $this->user();
        $symposium   = $this->symposiumService->getSymposiumById($symposiumId);

        if (!$symposium) {
            $this->error('Symposium not found.');
            $this->redirect('/symposiums');
        }

        // PDFs are only available after full approval
        $approvedStatuses = [
            SymposiumService::STATUS_APPROVED,
            SymposiumService::STATUS_SCHEDULING_COMPLETE,
            SymposiumService::STATUS_REGISTRATION_OPEN,
            SymposiumService::STATUS_REGISTRATION_CLOSE,
            SymposiumService::STATUS_COMPLETED,
        ];

        $isApproved = in_array($symposium['status'], $approvedStatuses, true);

        // If not approved, only authorized users can view drafts
        if (!$isApproved && !$this->symposiumService->canViewSymposium($currentUser, $symposium)) {
            $this->error('Not authorized.');
            $this->redirect('/symposiums');
        }
        
        // If they are authorized but it's not approved yet
        if (!$isApproved) {
            $this->error('Documents are only available after the symposium has been approved.');
            $this->redirect('/symposiums/view?id=' . $symposiumId);
        }

        $pdfService = new PdfDocumentService();
        $pdfService->generateSymposiumDocument($symposiumId, $type);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Extract organizing department IDs from POST data.
     *
     * Supports both multi-select (`organizing_departments[]`) and
     * comma-separated string (`organizing_departments`).
     *
     * @param array $post
     *
     * @return array<int>
     */
    private function extractDepartmentIds(array $post): array
    {
        $raw = $post['organizing_departments'] ?? [];

        if (is_string($raw)) {
            // Comma-separated fallback
            $raw = array_filter(array_map('trim', explode(',', $raw)));
        }

        return array_values(array_filter(array_map('intval', (array) $raw)));
    }

    /**
     * Get default organizing department IDs for the Create Symposium form.
     *
     * Returns an empty array — no departments are pre-selected by default.
     *
     * ARCHITECTURAL PRINCIPLE: Department codes must NEVER be hardcoded
     * in application logic. Pre-selecting specific department codes
     * (e.g., 'PGCS', 'PGCA') embeds institution-specific knowledge
     * into the codebase, which violates the single source of truth
     * principle and makes the system non-portable.
     *
     * The user selects the appropriate organizing departments at
     * symposium creation time.
     *
     * @param array $departments  All departments from DB (unused — kept for API compatibility)
     *
     * @return array<int>  Always empty — no default pre-selection
     */
    private function getDefaultOrganizingDeptIds(array $departments): array
    {
        // No default pre-selection.
        // Returning an empty array is intentional — do not add hardcoded
        // department codes here. See ARCHITECTURAL PRINCIPLE above.
        return [];
    }
}
