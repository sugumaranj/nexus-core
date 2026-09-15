<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : DepartmentController.php
 * Location    : app/Controllers/
 * Description : Handles all Department HTTP requests.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display department list
 * • Display create department form
 * • Validate user input
 * • Call DepartmentService
 * • Render department views
 * • Update department
 * • Delete department
 *
 * NOTE
 * -------------------------------------------------------------------------
 * • Controller NEVER contains SQL.
 * • Business logic belongs to DepartmentService.
 * • Validation belongs to DepartmentValidator.
 * • Database operations belong to DepartmentModel.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Services\DepartmentService;
use App\Validators\DepartmentValidator;

final class DepartmentController extends BaseController
{
    /**
     * Department Service.
     *
     * @var DepartmentService
     */
    private DepartmentService $departmentService;

    /**
     * Department Validator.
     *
     * @var DepartmentValidator
     */
    private DepartmentValidator $validator;

    /**
     * ---------------------------------------------------------------------
     * Constructor.
     *
     * Protect all department pages.
     * ---------------------------------------------------------------------
     */
    public function __construct()
    {
        AuthMiddleware::handle();

        $this->departmentService = new DepartmentService();

        $this->validator = new DepartmentValidator();
    }

    /**
     * ---------------------------------------------------------------------
     * Display Department List.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function index(): void
    {
        $departments = $this->departmentService->getAllDepartments();

        $this->render(
            'departments.index',
            [
                'pageTitle'   => 'Departments',
                'departments' => $departments
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Display Create Department Form.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function create(): void
    {
        $this->render(
            'departments.create',
            [
                'pageTitle' => 'Add Department'
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Store Department.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function store(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Validate Input
        |--------------------------------------------------------------------------
        */

        $errors = $this->validator->validate($_POST);

        if (!empty($errors)) {

            $this->render(
                'departments.create',
                [
                    'pageTitle' => 'Add Department',
                    'errors'    => $errors,
                    'old'       => $_POST
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Create Department
        |--------------------------------------------------------------------------
        */

        $result = $this->departmentService->createDepartment($_POST);

        if (!$result['success']) {

            $this->render(
                'departments.create',
                [
                    'pageTitle' => 'Add Department',
                    'error'     => $result['message'],
                    'old'       => $_POST
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Success Message
        |--------------------------------------------------------------------------
        */

        $this->success(
            'Department created successfully.'
        );

        $this->redirect('/departments');
    }

    /**
     * ---------------------------------------------------------------------
     * Display Edit Department Form.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function edit(): void
    {
        $departmentId = (int) ($_GET['id'] ?? 0);

        $department = $this->departmentService
            ->getDepartmentById($departmentId);

        if (!$department) {

            $this->error('Department not found.');

            $this->redirect('/departments');
        }

        $this->render(
            'departments.edit',
            [
                'pageTitle'  => 'Edit Department',
                'department' => $department
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Update Department.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function update(): void
    {
        $departmentId = (int) ($_POST['department_id'] ?? 0);

        /*
        |--------------------------------------------------------------------------
        | Validate Input
        |--------------------------------------------------------------------------
        */

        $errors = $this->validator->validate($_POST);

        if (!empty($errors)) {

            $this->render(
                'departments.edit',
                [
                    'pageTitle'  => 'Edit Department',
                    'errors'     => $errors,
                    'department' => $_POST
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Update Department
        |--------------------------------------------------------------------------
        */

        $result = $this->departmentService->updateDepartment(
            $departmentId,
            $_POST
        );

        if (!$result['success']) {

            $this->render(
                'departments.edit',
                [
                    'pageTitle'  => 'Edit Department',
                    'error'      => $result['message'],
                    'department' => $_POST
                ]
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Success Message
        |--------------------------------------------------------------------------
        */

        $this->success(
            'Department updated successfully.'
        );

        $this->redirect('/departments');
    }

    /**
     * ---------------------------------------------------------------------
     * Delete Department.
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    public function delete(): void
    {
        $departmentId = (int) ($_POST['department_id'] ?? 0);

        $result = $this->departmentService->deleteDepartment(
            $departmentId
        );

        if (!$result['success']) {

            $this->error($result['message']);

            $this->redirect('/departments');
        }

        $this->success(
            'Department deleted successfully.'
        );

        $this->redirect('/departments');
    }
}