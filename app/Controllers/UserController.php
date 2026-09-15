<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : UserController.php
 * Location    : app/Controllers/
 * Description : Handles all User Management HTTP requests.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display user list
 * • Display create user form
 * • Validate user input
 * • Create user
 * • Edit user
 * • Update user
 * • Delete user
 * • Reset password
 * • Change account status
 *
 * NOTE
 * -------------------------------------------------------------------------
 * • Controller NEVER contains SQL.
 * • Business logic belongs to UserService.
 * * Validation belongs to UserValidator.
 * • Database operations belong to UserModel.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Services\UserService;
use App\Services\EmployeeIdService;
use App\Validators\UserValidator;
use App\Services\DepartmentService;
use App\Helpers\RoleHelper;
use App\Core\Session;

final class UserController extends BaseController
{
    /**
     * User Service.
     *
     * @var UserService
     */
    private UserService $userService;

    /**
     * User Validator.
     *
     * @var UserValidator
     */
    private UserValidator $validator;

    /**
    * Department Service.
    *
    * @var DepartmentService
    */
    private DepartmentService $departmentService;

    /**
    * ---------------------------------------------------------------------
    * Constructor.
    *
    * Protect all User Management pages and initialize required services.
    * ---------------------------------------------------------------------
    */
    public function __construct()
    {
        AuthMiddleware::handle();

        $this->userService = new UserService();

        $this->validator = new UserValidator();

        $this->departmentService = new DepartmentService();
    }

    /**
     * ---------------------------------------------------------------------
     * AJAX — Generate Employee ID Preview.
     *
     * Called via GET /users/generate-employee-id?role={role}
     * Returns JSON: {"employee_id": "STF0005"} or {"error": "..."}.
     * ---------------------------------------------------------------------
     */
    public function generateEmployeeId(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Admin');

        header('Content-Type: application/json; charset=utf-8');

        $role = trim($_GET['role'] ?? '');

        if (!RoleHelper::isValidRole($role)) {
            echo json_encode(['error' => 'Invalid role.']);
            return;
        }

        try {
            $service    = new EmployeeIdService();
            $employeeId = $service->preview($role);

            echo json_encode([
                'employee_id' => $employeeId,
                'prefix'      => RoleHelper::getPrefix($role),
                'badge_colour'=> RoleHelper::getBadgeColour($role),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Could not generate Employee ID.']);
        }
    }

    /**
     * ---------------------------------------------------------------------
     * Display User List.
     * ---------------------------------------------------------------------
     */
    public function index(): void
    {
        RoleMiddleware::requireRole('Admin');
        $search = trim($_GET['search'] ?? '');

        $role = trim($_GET['role'] ?? '');

        $status = trim($_GET['status'] ?? '');

        $users = $this->userService->searchUsers(
            $search,
            $role,
            $status
        );

        $this->render(
            'users.index',
            [
                'pageTitle'   => 'User Management',

                'users'       => $users,

                'search'      => $search,

                'role'        => $role,

                'status'      => $status,

                'roles'       => RoleHelper::getAllRoles(),

                'departments' => $this->departmentService->getAllDepartments(),
            ]
        );
    }

    /**
    * ---------------------------------------------------------------------
    * Display Create User Form.
    * ---------------------------------------------------------------------
    */
    public function create(): void
    {
        RoleMiddleware::requireRole('Admin');

        $departments = $this->departmentService->getAllDepartments();

        $this->render(
            'users.create',
            [
                'pageTitle'   => 'Create User',
                'departments' => $departments,
                'roles'       => RoleHelper::getAllRoles(),
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Store User.
     * ---------------------------------------------------------------------
     */
    public function store(): void
    {
        RoleMiddleware::requireRole('Admin');

        $sessionUser = Session::get('user', []);

        // Inject actor identity so UserService can write audit log.
        $_POST['_created_by'] = (int) ($sessionUser['user_id'] ?? 0);
        $_POST['_actor_role'] = $sessionUser['role'] ?? 'Admin';

        // Pass department_code so validator can enforce PG-only restriction.
        $deptId = (int) ($_POST['department_id'] ?? 0);
        if ($deptId > 0) {
            $dept = $this->departmentService->getDepartmentById($deptId);
            $_POST['department_code'] = $dept['department_code'] ?? '';
        }

        $errors = array_merge(
            $this->validator->validate($_POST),
            $this->validator->validateFiles($_FILES)
        );

        if (!empty($errors)) {

            $this->render(
                'users.create',
                [
                    'pageTitle'   => 'Create User',
                    'old'         => $_POST,
                    'errors'      => $errors,
                    'departments' => $this->departmentService->getAllDepartments(),
                    'roles'       => RoleHelper::getAllRoles(),
                ]
            );

            return;
        }

        $result = $this->userService->createUser($_POST, $_FILES);

        if (!$result['success']) {

            $this->render(
                'users.create',
                [
                    'pageTitle'   => 'Create User',
                    'error'       => $result['message'],
                    'old'         => $_POST,
                    'errors'      => [],
                    'departments' => $this->departmentService->getAllDepartments(),
                    'roles'       => RoleHelper::getAllRoles(),
                ]
            );

            return;
        }

        $this->success($result['message']);

        $this->redirect('/users');
    }

    /**
     * ---------------------------------------------------------------------
     * Display Edit User Form.
     * ---------------------------------------------------------------------
     */
    public function edit(): void
    {
        RoleMiddleware::requireRole('Admin');
        $userId = (int) ($_GET['id'] ?? 0);

        $user = $this->userService->getUserById($userId);

        if (!$user) {
            $this->error('User not found.');
            $this->redirect('/users');
        }

        $this->render(
            'users.edit',
            [
                'pageTitle'   => 'Edit User',
                'user'        => $user,
                'departments' => $this->departmentService->getAllDepartments(),
                'roles'       => RoleHelper::getAllRoles(),
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Update User.
     * ---------------------------------------------------------------------
     */
    public function update(): void
    {
        RoleMiddleware::requireRole('Admin');
        $userId = (int) ($_POST['user_id'] ?? 0);

        $originalUser = $this->userService->getUserById($userId);

        if (!$originalUser) {

            $this->error('User not found.');

            $this->redirect('/users');
        }

        /*
        |--------------------------------------------------------------------------
        | Merge Database Values With Submitted POST
        |--------------------------------------------------------------------------
        |
        | Readonly fields such as employee_id and timestamps are not present
        | in $_POST. Merge preserves them while keeping submitted input.
        |
        */

        $mergedUser = array_merge($originalUser, $_POST);

        $errors = array_merge(
            $this->validator->validate($mergedUser),
            $this->validator->validateFiles($_FILES)
        );

        if (!empty($errors)) {

            $this->render(
                'users.edit',
                 [
                    'pageTitle'   => 'Edit User',

                    'errors'      => $errors,

                    'user'        => $mergedUser,

                    'departments' => $this
                        ->departmentService
                        ->getAllDepartments(),

                    'roles'       => RoleHelper::getAllRoles(),
                ]
            );

            return;
        }

        $result = $this->userService->updateUser(
            $userId,
            $_POST,
            $_FILES
        );

        if (!$result['success']) {

            $this->render(
                'users.edit',
                [
                    'pageTitle'   => 'Edit User',
                    'error'       => $result['message'],
                    'user'        => $mergedUser,
                    'departments' => $this->departmentService->getAllDepartments(),
                    'roles'       => RoleHelper::getAllRoles(),
                ]
            );

            return;
        }

        $this->success($result['message']);

        $this->redirect('/users');
    }

    /**
     * ---------------------------------------------------------------------
     * View User Details.
     * ---------------------------------------------------------------------
     */
    public function view(): void
    {
        RoleMiddleware::requireRole('Admin');
        $userId = (int) ($_GET['id'] ?? 0);

        $user = $this->userService->getUserById($userId);

        if (!$user) {

            $this->error('User not found.');

            $this->redirect('/users');
        }

        $this->render(
            'users.view',
            [
                'pageTitle' => 'User Details',
                'user'      => $user
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Delete User.
     * ---------------------------------------------------------------------
     */
    public function delete(): void
    {
        RoleMiddleware::requireRole('Admin');
        $userId = (int) ($_POST['user_id'] ?? 0);

        $result = $this->userService->deleteUser($userId);

        if (!$result['success']) {

            $this->error($result['message']);

            $this->redirect('/users');
        }

        $this->success($result['message']);

        $this->redirect('/users');
    }

    /**
     * ---------------------------------------------------------------------
     * Update User Account Status.
     * ---------------------------------------------------------------------
     */
    public function changeStatus(): void
    {
        RoleMiddleware::requireRole('Admin');
        $userId = (int) ($_POST['user_id'] ?? 0);

        $status = trim($_POST['account_status'] ?? '');

        $result = $this->userService->updateStatus(
            $userId,
            $status
        );

        if (!$result['success']) {

            $this->error($result['message']);

            $this->redirect('/users');
        }

        $this->success($result['message']);

        $this->redirect('/users');
    }

    /**
     * ---------------------------------------------------------------------
     * Reset User Password.
     * ---------------------------------------------------------------------
     */
    public function resetPassword(): void
    {
        RoleMiddleware::requireRole('Admin');
        $userId = (int) ($_POST['user_id'] ?? 0);

        $password = $_POST['password'] ?? '';

        $result = $this->userService->resetPassword(
            $userId,
            $password
        );

        if (!$result['success']) {

            $this->error($result['message']);

            $this->redirect('/users');
        }

        $this->success($result['message']);

        $this->redirect('/users');
    }
}