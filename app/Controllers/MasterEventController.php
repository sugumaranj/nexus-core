<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : MasterEventController.php
 * Location    : app/Controllers/
 * Description : Controller for Admin Master Event Templates Library.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • List master event templates with filtering (Category, Status, Search)
 * • Create new master event templates (with rules)
 * • Edit & update existing templates
 * • View detail page (General, Rules, Resources, History)
 * • Clone master events (e.g. Coding -> AI Coding Challenge)
 * • Soft delete master events
 * • Auto event code generator AJAX endpoint
 *
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Models\MasterEventModel;
use App\Services\MasterEventService;

final class MasterEventController extends BaseController
{
    private MasterEventService $service;
    private MasterEventModel   $model;

    public function __construct()
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator', 'HOD', 'Principal');

        $this->service = new MasterEventService();
        $this->model   = new MasterEventModel();
    }

    /**
     * Master Event Library Index (List page).
     *
     * GET /admin/events
     */
    public function index(): void
    {
        $category = $_GET['category'] ?? 'All';
        $status   = $_GET['status'] ?? 'All';
        $search   = $_GET['search'] ?? '';

        $events = $this->model->getAll($category, $status, $search);
        $formData = $this->service->getFormData();

        $this->render('admin.events.index', [
            'pageTitle'  => 'Master Event Library',
            'events'     => $events,
            'category'   => $category,
            'status'     => $status,
            'search'     => $search,
            'categories' => $formData['categories'],
            'statuses'   => $formData['statuses'],
        ]);
    }

    /**
     * View Master Event details.
     *
     * GET /admin/events/view?id=X
     */
    public function view(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $event = $this->model->findById($id);

        if (!$event) {
            $this->error('Master Event template not found.');
            $this->redirect('/admin/events');
        }

        $rules = $this->model->getRules($id);

        $this->render('admin.events.view', [
            'pageTitle' => 'Master Event: ' . $event['event_name'],
            'event'     => $event,
            'rules'     => $rules,
        ]);
    }

    /**
     * Show Create Master Event form.
     *
     * GET /admin/events/create
     */
    public function create(): void
    {
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator');
        $formData = $this->service->getFormData();

        $this->render('admin.events.create', [
            'pageTitle' => 'Create Master Event Template',
            'formData'  => $formData,
            'errors'    => [],
            'old'       => [],
        ]);
    }

    /**
     * Handle Create Master Event form submit.
     *
     * POST /admin/events/create
     */
    public function store(): void
    {
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator');
        $result = $this->service->createMasterEvent($_POST, $this->user());

        if ($result['success']) {
            $this->success($result['message']);
            $this->redirect('/admin/events/view?id=' . $result['event_id']);
        }

        $formData = $this->service->getFormData();

        $this->error($result['message']);
        $this->render('admin.events.create', [
            'pageTitle' => 'Create Master Event Template',
            'formData'  => $formData,
            'errors'    => $result['errors'],
            'old'       => $_POST,
        ]);
    }

    /**
     * Show Edit Master Event form.
     *
     * GET /admin/events/edit?id=X
     */
    public function edit(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $event = $this->model->findById($id);

        if (!$event) {
            $this->error('Master Event template not found.');
            $this->redirect('/admin/events');
        }

        $rules = $this->model->getRules($id);
        $formData = $this->service->getFormData();

        $this->render('admin.events.edit', [
            'pageTitle'          => 'Edit Master Event: ' . $event['event_name'],
            'event'              => $event,
            'rules'              => $rules,
            'formattedRulesText' => $this->service->formatRulesText($rules),
            'formData'           => $formData,
            'errors'             => [],
        ]);
    }

    /**
     * Handle Edit Master Event form submit.
     *
     * POST /admin/events/edit?id=X
     */
    public function update(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $result = $this->service->updateMasterEvent($id, $_POST, $this->user());

        if ($result['success']) {
            $this->success($result['message']);
            $this->redirect('/admin/events/view?id=' . $id);
        }

        $event = $this->model->findById($id);
        $rules = $this->model->getRules($id);
        $formData = $this->service->getFormData();

        $this->error($result['message']);
        $this->render('admin.events.edit', [
            'pageTitle'      => 'Edit Master Event: ' . ($event['event_name'] ?? ''),
            'event'          => array_merge($event ?: [], $_POST),
            'rules'          => $rules,
            'formData'       => $formData,
            'errors'         => $result['errors'],
        ]);
    }

    /**
     * Clone a Master Event.
     *
     * POST /admin/events/clone
     */
    public function clone(): void
    {
        $sourceId = (int)($_POST['source_event_id'] ?? 0);
        $newName  = $_POST['new_event_name'] ?? '';

        $result = $this->service->cloneMasterEvent($sourceId, $newName, $this->user());

        if ($result['success']) {
            $this->success($result['message']);
            $this->redirect('/admin/events/edit?id=' . $result['event_id']);
        }

        $this->error($result['message']);
        $this->redirect('/admin/events');
    }

    /**
     * Soft delete a Master Event.
     *
     * POST /admin/events/delete
     */
    public function delete(): void
    {
        $id = (int)($_POST['event_id'] ?? 0);
        $result = $this->service->deleteMasterEvent($id, $this->user());

        if ($result['success']) {
            $this->success($result['message']);
        } else {
            $this->error($result['message']);
        }

        $this->redirect('/admin/events');
    }

    /**
     * AJAX endpoint to generate event code from name.
     *
     * GET /admin/events/ajax/generate-code?name=Coding
     */
    public function generateCode(): void
    {
        header('Content-Type: application/json');

        $name = $_GET['name'] ?? '';
        if (trim($name) === '') {
            echo json_encode(['code' => '']);
            return;
        }

        $code = $this->model->generateEventCode($name);
        echo json_encode(['code' => $code]);
    }

    /**
     * AJAX endpoint to return full Master Event details as JSON for info card auto-fill.
     *
     * GET /admin/events/ajax/get-data?id=X
     */
    public function getEventData(): void
    {
        header('Content-Type: application/json');

        $id = (int)($_GET['id'] ?? 0);
        $event = $this->model->findById($id);

        if (!$event) {
            echo json_encode(['error' => 'Event not found']);
            return;
        }

        $rules = $this->model->getRules($id);
        $event['raw_rules'] = $this->service->formatRulesText($rules);

        echo json_encode($event);
    }
}
