<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : SymposiumEventController.php
 * Location    : app/Controllers/
 * Description : Controller for Staff Coordinator Symposium Event Scheduling.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • List scheduled events for a symposium
 * • Add Existing Master Event (runs Snapshotting Engine)
 * • Add New Master Event (creates template in library + attaches)
 * • Edit scheduling & coordinator details
 * • Remove event from symposium
 *
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Models\SymposiumEventModel;
use App\Models\SymposiumModel;
use App\Services\SymposiumEventService;

final class SymposiumEventController extends BaseController
{
    private SymposiumEventService $service;
    private SymposiumEventModel   $model;
    private SymposiumModel        $symposiumModel;

    public function __construct()
    {
        AuthMiddleware::handle();

        $this->service        = new SymposiumEventService();
        $this->model          = new SymposiumEventModel();
        $this->symposiumModel = new SymposiumModel();
    }

    /**
     * List all events scheduled for a symposium.
     *
     * GET /symposiums/events?symposium_id=X
     */
    public function index(): void
    {
        $symposiumId = (int)($_GET['symposium_id'] ?? 0);
        $symposium   = $this->symposiumModel->findById($symposiumId);

        if (!$symposium) {
            $this->error('Symposium not found.');
            $this->redirect('/symposiums');
        }

        $events    = $this->model->getBySymposium($symposiumId);
        $canManage = $this->service->canManage($this->user(), $symposium);

        $this->render('symposiums.events.index', [
            'pageTitle'   => 'Symposium Events: ' . $symposium['title'],
            'symposium'   => $symposium,
            'events'      => $events,
            'canManage'   => $canManage,
            'currentUser' => $this->user(),
        ]);
    }

    /**
     * View scheduled symposium event details.
     *
     * GET /symposiums/events/view?id=X
     */
    public function view(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $event = $this->model->findById($id);

        if (!$event) {
            $this->error('Scheduled event not found.');
            $this->redirect('/symposiums');
        }

        $canManage = $this->service->canManage($this->user(), (int)$event['symposium_id']);
        $snapshotRules = !empty($event['snapshot_rules']) ? json_decode($event['snapshot_rules'], true) : [];
        $snapshotEval  = !empty($event['snapshot_evaluation']) ? json_decode($event['snapshot_evaluation'], true) : [];

        // Fetch assignments since faculty_coordinator_id is no longer strictly used
        $facultyModel = new \App\Models\FacultyAssignmentModel();
        $judgeModel   = new \App\Models\JudgeAssignmentModel();
        
        $facultyList = $facultyModel->getForEvent($id);
        $judgeList   = $judgeModel->getForEvent($id);

        $stageModel = new \App\Models\CompetitionStageModel();
        $stages = $stageModel->getBySymposiumEvent($id);

        $this->render('symposiums.events.view', [
            'pageTitle'     => 'Event: ' . $event['event_name'],
            'event'         => $event,
            'snapshotRules' => $snapshotRules,
            'snapshotEval'  => $snapshotEval,
            'canManage'     => $canManage,
            'facultyList'   => $facultyList,
            'judgeList'     => $judgeList,
            'stages'        => $stages,
        ]);
    }

    /**
     * Show Add Event form/modal for a symposium.
     *
     * GET /symposiums/events/create?symposium_id=X
     */
    public function create(): void
    {
        $symposiumId = (int)($_GET['symposium_id'] ?? 0);
        $symposium   = $this->symposiumModel->findById($symposiumId);

        if (!$symposium) {
            $this->error('Symposium not found.');
            $this->redirect('/symposiums');
        }

        if (!$this->service->canManage($this->user(), $symposium)) {
            $this->error('Unauthorized action. You cannot edit this symposium.');
            $this->redirect('/symposiums/events?symposium_id=' . $symposiumId);
        }

        $formData = $this->service->getFormData($symposiumId);

        $this->render('symposiums.events.create', [
            'pageTitle' => 'Add Event to ' . $symposium['title'],
            'symposium' => $symposium,
            'formData'  => $formData,
            'errors'    => [],
            'old'       => [],
        ]);
    }

    /**
     * Handle Add Event form submit.
     *
     * POST /symposiums/events/create
     */
    public function store(): void
    {
        $symposiumId = (int)($_POST['symposium_id'] ?? 0);
        $sourceType  = $_POST['source_type'] ?? 'existing'; // 'existing' vs 'new'

        if ($sourceType === 'new') {
            $masterData = $_POST['master'] ?? [];
            $scheduleData = $_POST['schedule'] ?? [];
            $result = $this->service->addNewEvent($symposiumId, $masterData, $scheduleData, $this->user());
        } elseif ($sourceType === 'bulk_import') {
            $bulkEventIds = $_POST['bulk_event_ids'] ?? [];
            $result = $this->service->importBulkEvents($symposiumId, $bulkEventIds, $this->user());
        } else {
            $masterEventId = (int)($_POST['event_id'] ?? 0);
            $scheduleData  = $_POST;
            $result = $this->service->addExistingEvent($symposiumId, $masterEventId, $scheduleData, $this->user());
        }

        if ($result['success']) {
            $this->success($result['message']);
            $this->redirect('/symposiums/events?symposium_id=' . $symposiumId);
        }

        // If submitted from Wizard, redirect back to Wizard
        if (!empty($_POST['is_wizard'])) {
            $this->error($result['message'] . ' ' . implode(' ', $result['errors']));
            $this->redirect('/symposiums/events/wizard?symposium_id=' . $symposiumId);
        }

        $formData = $this->service->getFormData($symposiumId);
        $symposium = $this->symposiumModel->findById($symposiumId);

        $this->error($result['message']);
        $this->render('symposiums.events.create', [
            'pageTitle' => 'Add Event to ' . ($symposium['title'] ?? ''),
            'symposium' => $symposium,
            'formData'  => $formData,
            'errors'    => $result['errors'],
            'old'       => $_POST,
        ]);
    }

    /**
     * Show Edit Scheduled Event form.
     *
     * GET /symposiums/events/edit?id=X
     */
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $event = $this->model->findById($id);

        if (!$event) {
            $this->error('Scheduled event not found.');
            $this->redirect('/symposiums');
        }

        if (!$this->service->canManage($this->user(), (int)$event['symposium_id'])) {
            $this->error('Unauthorized action.');
            $this->redirect('/symposiums/events?symposium_id=' . $event['symposium_id']);
        }

        $formData = $this->service->getFormData((int)$event['symposium_id']);

        $rulesRaw = json_decode($event['snapshot_rules'] ?? '[]', true) ?: [];
        $masterService = new \App\Services\MasterEventService();
        $formattedRulesText = $masterService->formatRulesText($rulesRaw);

        // Fetch existing stages if any
        $stageModel = new \App\Models\CompetitionStageModel();
        $stages = $stageModel->getBySymposiumEvent($id);

        $this->render('symposiums.events.edit', [
            'pageTitle'          => 'Edit Schedule: ' . $event['event_name'],
            'event'              => $event,
            'formData'           => $formData,
            'formattedRulesText' => $formattedRulesText,
            'stages'             => $stages,
            'errors'             => [],
        ]);
    }

    /**
     * Handle Edit Scheduled Event form submit.
     *
     * POST /symposiums/events/edit?id=X
     */
    public function update(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $event = $this->model->findById($id);

        if (!$event) {
            $this->error('Scheduled event not found.');
            $this->redirect('/symposiums');
        }

        $result = $this->service->updateEvent($id, $_POST, $this->user());

        if ($result['success']) {
            $this->success($result['message']);
            if (isset($_GET['return_to']) && $_GET['return_to'] === 'allocation') {
                $this->redirect('/symposiums/allocation?symposium_id=' . $event['symposium_id']);
            }
            $this->redirect('/symposiums/events?symposium_id=' . $event['symposium_id']);
        }

        $formData = $this->service->getFormData((int)$event['symposium_id']);
        
        $rulesRaw = json_decode($event['snapshot_rules'] ?? '[]', true) ?: [];
        $masterService = new \App\Services\MasterEventService();
        $formattedRulesText = $_POST['rules_text'] ?? $masterService->formatRulesText($rulesRaw);

        $this->error($result['message']);
        $this->render('symposiums.events.edit', [
            'pageTitle'          => 'Edit Schedule: ' . $event['event_name'],
            'event'              => array_merge($event, $_POST),
            'formData'           => $formData,
            'formattedRulesText' => $formattedRulesText,
            'errors'             => $result['errors'],
        ]);
    }

    /**
     * Remove an event from a symposium.
     *
     * POST /symposiums/events/delete
     */
    public function delete(): void
    {
        $id = (int)($_POST['symposium_event_id'] ?? 0);
        $event = $this->model->findById($id);

        if (!$event) {
            $this->error('Scheduled event not found.');
            $this->redirect('/symposiums');
        }

        $symposiumId = (int)$event['symposium_id'];
        $result = $this->service->deleteEvent($id, $this->user());

        if ($result['success']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

        $this->redirect('/symposiums/events?symposium_id=' . $symposiumId);
    }

    /**
     * Delete ALL events from a symposium (password-protected).
     *
     * POST /symposiums/events/delete-all
     */
    public function deleteAll(): void
    {
        $symposiumId     = (int)($_POST['symposium_id'] ?? 0);
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($symposiumId <= 0) {
            $this->error('Invalid symposium.');
            $this->redirect('/symposiums');
        }

        if (trim($confirmPassword) === '') {
            $this->error('Password is required to perform this action.');
            $this->redirect('/symposiums/events?symposium_id=' . $symposiumId);
        }

        $result = $this->service->deleteAllEvents($symposiumId, $this->user(), $confirmPassword);

        if ($result['success']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

        $this->redirect('/symposiums/events?symposium_id=' . $symposiumId);
    }

    /**
     * Show 7-Step Coordinator Event Builder Wizard.
     *
     * GET /symposiums/events/wizard?symposium_id=X
     */
    public function wizard(): void
    {
        $symposiumId = (int)($_GET['symposium_id'] ?? 0);
        $symposium   = $this->symposiumModel->findById($symposiumId);

        if (!$symposium) {
            $this->error('Symposium not found.');
            $this->redirect('/symposiums');
        }

        if (!$this->service->canManage($this->user(), $symposium)) {
            $this->error('Unauthorized action. You cannot edit this symposium.');
            $this->redirect('/symposiums/events?symposium_id=' . $symposiumId);
        }

        $formData = $this->service->getFormData($symposiumId);

        $this->render('symposiums.events.wizard', [
            'pageTitle' => '7-Step Event Builder Wizard',
            'symposium' => $symposium,
            'formData'  => $formData,
            'errors'    => [],
            'old'       => [],
        ]);
    }

    /**
     * AJAX endpoint to check venue & coordinator scheduling conflicts in real-time.
     *
     * POST /symposiums/events/ajax/check-conflict
     */
    public function checkConflict(): void
    {
        header('Content-Type: application/json');

        $excludeId = !empty($_POST['symposium_event_id']) ? (int)$_POST['symposium_event_id'] : null;
        $conflicts = $this->service->checkConflicts($_POST, $excludeId);

        if (!empty($conflicts)) {
            echo json_encode([
                'has_conflict' => true,
                'conflicts'    => array_values($conflicts),
                'errors'       => $conflicts,
            ]);
            return;
        }

        echo json_encode([
            'has_conflict' => false,
            'message'      => 'No scheduling conflicts detected.',
        ]);
    }
    /**
     * POST /symposiums/events/bulk-add
     */
    public function bulkAdd(): void
    {
        $symposiumId = (int)($_POST['symposium_id'] ?? 0);
        $symposium   = $this->symposiumModel->findById($symposiumId);

        if (!$symposium) {
            $this->error('Symposium not found.');
            $this->redirect('/symposiums');
        }

        // Must be staff coordinator
        $user = $this->user();
        if ($user['role'] !== 'Staff Coordinator') {
            $this->error('Unauthorized. Only Staff Coordinators can bulk add events.');
            $this->redirect('/symposiums/events?symposium_id=' . $symposiumId);
        }

        $addedCount = $this->service->bulkAttachMasterEvents($symposiumId, (int)$user['user_id']);

        if ($addedCount > 0) {
            $this->success("Successfully added {$addedCount} event(s) to the schedule as TBA.");
        } else {
            $this->success("All published master events are already added.");
        }
        
        $this->redirect('/symposiums/events?symposium_id=' . $symposiumId);
    }
}
