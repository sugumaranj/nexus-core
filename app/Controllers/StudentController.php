<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : StudentController.php
 * Location    : app/Controllers/
 * Description : Handles all Student Records HTTP requests.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Display student list
 * • Display create student form
 * • Validate student input
 * • Create student
 * • Edit student
 * • Update student
 * • Delete student
 * • View student details
 *
 * Permissions (RBAC)
 * -------------------------------------------------------------------------
 * | Action         | Admin | Staff Coord | Staff | HOD | Principal |
 * |----------------|-------|-------------|-------|-----|-----------|
 * | List / View    |  Yes  |    Yes      |  Yes  | Yes |    Yes    |
 * | Create / Store |  Yes  |    Yes      |  Yes  |  No |    No     |
 * | Edit / Update  |  Yes  |    Yes      |  Yes  |  No |    No     |
 * | Delete         |  Yes  |    Yes      |   No  |  No |    No     |
 *
 * NOTE
 * -------------------------------------------------------------------------
 * • Controller NEVER contains SQL.
 * • Business logic belongs to StudentService.
 * • Validation belongs to StudentValidator.
 * • Database operations belong to StudentModel.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Services\DepartmentService;
use App\Services\StudentService;
use App\Validators\StudentValidator;

final class StudentController extends BaseController
{
    /**
     * Student Service.
     *
     * @var StudentService
     */
    private StudentService $studentService;

    /**
     * Student Validator.
     *
     * @var StudentValidator
     */
    private StudentValidator $validator;

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
     * Protect all student pages with authentication.
     * Individual role checks are applied per action below.
     * ---------------------------------------------------------------------
     */
    public function __construct()
    {
        AuthMiddleware::handle();

        $this->studentService    = new StudentService();
        $this->validator         = new StudentValidator();
        $this->departmentService = new DepartmentService();
    }

    /**
     * ---------------------------------------------------------------------
     * Display Student List.
     *
     * Accessible by: Admin, Staff Coordinator, Staff, HOD, Principal
     * ---------------------------------------------------------------------
     */
    public function index(): void
    {
        RoleMiddleware::requireRole(
            'Admin',
            'Staff Coordinator',
            'Staff',
            'HOD',
            'Principal'
        );

        $search       = trim((string) ($_GET['search'] ?? ''));
        $departmentId = trim((string) ($_GET['department_id'] ?? ''));
        $academicYear = trim((string) ($_GET['academic_year'] ?? ''));
        $semester     = trim((string) ($_GET['semester'] ?? ''));
        $status       = trim((string) ($_GET['status'] ?? ''));

        $students = $this->studentService->searchStudents(
            $search,
            $departmentId,
            $academicYear,
            $semester,
            $status
        );

        $academicYears = $this->studentService->getAvailableAcademicYears();
        $semesters     = $this->studentService->getAvailableSemesters();

        // Fallbacks if empty
        if (empty($academicYears)) {
            $academicYears = ['1', '2', '3', '4'];
        }
        if (empty($semesters)) {
            $semesters = ['1', '2', '3', '4', '5', '6', '7', '8'];
        }

        $totalStudents    = $this->studentService->countAllStudents();
        $activeStudents   = $this->studentService->countActiveStudents();
        $inactiveStudents = $this->studentService->countInactiveStudents();

        $this->render(
            'students.index',
            [
                'pageTitle'        => 'Student Records',
                'students'         => $students,
                'search'           => $search,
                'department_id'    => $departmentId,
                'academic_year'    => $academicYear,
                'semester'         => $semester,
                'status'           => $status,
                'departments'      => $this->departmentService->getAllDepartments(),
                'academicYears'    => $academicYears,
                'semesters'        => $semesters,
                'totalStudents'    => $totalStudents,
                'activeStudents'   => $activeStudents,
                'inactiveStudents' => $inactiveStudents,
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Display Create Student Form.
     *
     * Accessible by: Admin, Staff Coordinator, Staff
     * ---------------------------------------------------------------------
     */
    public function create(): void
    {
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator', 'Staff');

        $this->render(
            'students.create',
            [
                'pageTitle'   => 'Add Student',
                'departments' => $this->departmentService->getAllDepartments(),
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Store Student.
     *
     * Accessible by: Admin, Staff Coordinator, Staff
     * ---------------------------------------------------------------------
     */
    public function store(): void
    {
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator', 'Staff');

        $errors = array_merge(
            $this->validator->validate($_POST),
            $this->validator->validateFiles($_FILES)
        );

        if (!empty($errors)) {
            $this->render(
                'students.create',
                [
                    'pageTitle'   => 'Add Student',
                    'old'         => $_POST,
                    'errors'      => $errors,
                    'departments' => $this->departmentService->getAllDepartments(),
                ]
            );

            return;
        }

        $result = $this->studentService->createStudent(
            $_POST,
            $_FILES
        );

        if (!$result['success']) {
            $this->render(
                'students.create',
                [
                    'pageTitle'   => 'Add Student',
                    'error'       => $result['message'],
                    'old'         => $_POST,
                    'errors'      => [],
                    'departments' => $this->departmentService->getAllDepartments(),
                ]
            );

            return;
        }

        $this->success($result['message']);

        $this->redirect('/students');
    }

    /**
     * ---------------------------------------------------------------------
     * Display Edit Student Form.
     *
     * Accessible by: Admin, Staff Coordinator, Staff
     * ---------------------------------------------------------------------
     */
    public function edit(): void
    {
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator', 'Staff');

        $studentId = (int) ($_GET['id'] ?? 0);

        $student = $this->studentService->getStudentById($studentId);

        if (!$student) {
            $this->error('Student not found.');
            $this->redirect('/students');
        }

        $this->render(
            'students.edit',
            [
                'pageTitle'   => 'Edit Student',
                'student'     => $student,
                'departments' => $this->departmentService->getAllDepartments(),
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Update Student.
     *
     * Accessible by: Admin, Staff Coordinator, Staff
     * ---------------------------------------------------------------------
     */
    public function update(): void
    {
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator', 'Staff');

        $studentId = (int) ($_POST['student_id'] ?? 0);

        $originalStudent = $this->studentService->getStudentById($studentId);

        if (!$originalStudent) {
            $this->error('Student not found.');
            $this->redirect('/students');
        }

        $mergedStudent = array_merge($originalStudent, $_POST);

        $mergedStudent['register_number'] = (string) ($originalStudent['register_number'] ?? '');

        $errors = array_merge(
            $this->validator->validate($mergedStudent),
            $this->validator->validateFiles($_FILES)
        );

        if (!empty($errors)) {
            $this->render(
                'students.edit',
                [
                    'pageTitle'   => 'Edit Student',
                    'errors'      => $errors,
                    'student'     => $mergedStudent,
                    'departments' => $this->departmentService->getAllDepartments(),
                ]
            );

            return;
        }

        $result = $this->studentService->updateStudent(
            $studentId,
            $mergedStudent,
            $_FILES
        );

        if (!$result['success']) {
            $this->render(
                'students.edit',
                [
                    'pageTitle'   => 'Edit Student',
                    'error'       => $result['message'],
                    'errors'      => [],
                    'student'     => $mergedStudent,
                    'departments' => $this->departmentService->getAllDepartments(),
                ]
            );

            return;
        }

        $this->success($result['message']);

        $this->redirect('/students');
    }

    /**
     * ---------------------------------------------------------------------
     * View Student Details.
     *
     * Accessible by: Admin, Staff Coordinator, Staff, HOD, Principal
     * ---------------------------------------------------------------------
     */
    public function view(): void
    {
        RoleMiddleware::requireRole(
            'Admin',
            'Staff Coordinator',
            'Staff',
            'HOD',
            'Principal'
        );

        $studentId = (int) ($_GET['id'] ?? 0);

        $student = $this->studentService->getStudentById($studentId);

        if (!$student) {
            $this->error('Student not found.');
            $this->redirect('/students');
        }

        $this->render(
            'students.view',
            [
                'pageTitle' => 'Student Details',
                'student'   => $student,
            ]
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Delete Student.
     *
     * Accessible by: Admin, Staff Coordinator
     * ---------------------------------------------------------------------
     */
    public function delete(): void
    {
        RoleMiddleware::requireRole('Admin', 'Staff Coordinator');

        $studentId = (int) ($_POST['student_id'] ?? 0);

        $result = $this->studentService->deleteStudent($studentId);

        if (!$result['success']) {
            $this->error($result['message']);
            $this->redirect('/students');
        }

        $this->success($result['message']);

        $this->redirect('/students');
    }
}
