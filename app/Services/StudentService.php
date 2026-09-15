<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : StudentService.php
 * Location    : app/Services/
 * Description : Business logic for Student Management.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Validate business rules
 * • Prevent duplicate register numbers, roll numbers and emails
 * • Normalize student input
 * • Upload and replace student photos
 * • Remove student photos
 * • Call StudentModel
 *
 * NOTE
 * -------------------------------------------------------------------------
 * This class NEVER contains SQL.
 * SQL belongs only inside StudentModel.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Models\StudentModel;

final class StudentService
{
    /**
     * Student Model.
     *
     * @var StudentModel
     */
    private StudentModel $studentModel;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->studentModel = new StudentModel();
    }

    /**
     * ---------------------------------------------------------------------
     * Retrieve all students.
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getAllStudents(): array
    {
        return $this->studentModel->getAll();
    }

    /**
     * ---------------------------------------------------------------------
     * Search and filter students.
     *
     * @param string $search
     * @param string $departmentId
     * @param string $academicYear
     * @param string $semester
     * @param string $status
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function searchStudents(
        string $search = '',
        string $departmentId = '',
        string $academicYear = '',
        string $semester = '',
        string $status = ''
    ): array {
        $allowedStatuses = [
            'Active',
            'Inactive'
        ];

        $departmentFilter = $departmentId !== '' ? $departmentId : null;

        $academicYearFilter = $academicYear !== '' ? $academicYear : null;

        $semesterFilter = $semester !== '' ? $semester : null;

        $statusFilter = in_array($status, $allowedStatuses, true)
            ? $status
            : null;

        return $this->studentModel->search(
            $search !== '' ? $search : null,
            $departmentFilter,
            $academicYearFilter,
            $semesterFilter,
            $statusFilter
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Retrieve a list of distinct academic years present in the student table.
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getAvailableAcademicYears(): array
    {
        return $this->studentModel->getAvailableAcademicYears();
    }

    /**
     * ---------------------------------------------------------------------
     * Retrieve a list of distinct semesters present in the student table.
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getAvailableSemesters(): array
    {
        return $this->studentModel->getAvailableSemesters();
    }

    /**
     * ---------------------------------------------------------------------
     * Retrieve student by ID.
     *
     * @param int $studentId
     *
     * @return array|false
     * ---------------------------------------------------------------------
     */
    public function getStudentById(int $studentId): array|false
    {
        return $this->studentModel->findById($studentId);
    }

    /**
     * ---------------------------------------------------------------------
     * Create student.
     *
     * @param array $data
     * @param array $files
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function createStudent(array $data, array $files = []): array
    {
        $data['register_number'] = strtoupper(trim((string) ($data['register_number'] ?? '')));
        $data['roll_number'] = strtoupper(trim((string) ($data['roll_number'] ?? '')));
        $data['full_name'] = trim((string) ($data['full_name'] ?? ''));
        $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));
        $data['phone'] = trim((string) ($data['phone'] ?? ''));
        $data['gender'] = trim((string) ($data['gender'] ?? ''));
        if (!empty($data['dob_year']) && !empty($data['dob_month']) && !empty($data['dob_day'])) {
            $data['dob'] = sprintf('%04d-%02d-%02d', (int)$data['dob_year'], (int)$data['dob_month'], (int)$data['dob_day']);
        } else {
            $data['dob'] = trim((string) ($data['dob'] ?? ''));
            if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $data['dob'], $m)) {
                $data['dob'] = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            }
        }
        if ($data['dob'] === '') {
            $data['dob'] = null;
        }
        $data['academic_year'] = trim((string) ($data['academic_year'] ?? ''));
        $data['semester'] = trim((string) ($data['semester'] ?? ''));
        $data['section'] = strtoupper(trim((string) ($data['section'] ?? '')));
        if ($data['section'] === '') {
            $data['section'] = null;
        }
        $data['admission_year'] = trim((string) ($data['admission_year'] ?? ''));
        $data['graduation_year'] = trim((string) ($data['graduation_year'] ?? ''));
        // Auto-derive graduation year from admission year (3-year programme) if not provided
        if ($data['graduation_year'] === '' && $data['admission_year'] !== '' && preg_match('/^\d{4}$/', $data['admission_year'])) {
            $data['graduation_year'] = (string) ((int) $data['admission_year'] + 3);
        }
        if ($data['graduation_year'] === '') {
            $data['graduation_year'] = null;
        }
        $data['department_id'] = trim((string) ($data['department_id'] ?? ''));
        $data['account_status'] = trim((string) ($data['account_status'] ?? 'Active'));

        if ($data['register_number'] === '') {
            return [
                'success' => false,
                'message' => 'Register number is required.'
            ];
        }

        if ($this->studentModel->findByRegisterNumber($data['register_number'])) {
            return [
                'success' => false,
                'message' => 'Register number already exists.'
            ];
        }


        if ($data['email'] !== '' && $this->studentModel->findByEmail($data['email'])) {
            return [
                'success' => false,
                'message' => 'Email address already exists.'
            ];
        }

        if ($data['phone'] !== '' && $this->studentModel->findByPhone($data['phone'])) {
            return [
                'success' => false,
                'message' => 'Phone number already exists.'
            ];
        }

        $photoUpload = $this->processFileUpload(
            $files,
            'profile_photo',
            'student_',
            'uploads/student_photos',
            [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ]
        );

        if ($photoUpload['error'] !== null) {
            return [
                'success' => false,
                'message' => $photoUpload['error']
            ];
        }

        $data['profile_photo'] = $photoUpload['path'];

        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        unset(
            $data['password'],
            $data['confirm_password'],
            $data['remove_profile_photo']
        );

        $created = $this->studentModel->create($data);

        if (!$created) {
            return [
                'success' => false,
                'message' => 'Unable to create student.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Student created successfully.'
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * Update student.
     *
     * @param int   $studentId
     * @param array $data
     * @param array $files
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function updateStudent(
        int $studentId,
        array $data,
        array $files = []
    ): array {
        $existingStudent = $this->studentModel->findById($studentId);

        if (!$existingStudent) {
            return [
                'success' => false,
                'message' => 'Student not found.'
            ];
        }

        $data['register_number'] = strtoupper(trim((string) ($data['register_number'] ?? '')));
        $data['roll_number'] = strtoupper(trim((string) ($data['roll_number'] ?? '')));
        $data['full_name'] = trim((string) ($data['full_name'] ?? ''));
        $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));
        $data['phone'] = trim((string) ($data['phone'] ?? ''));
        $data['gender'] = trim((string) ($data['gender'] ?? ''));
        if (!empty($data['dob_year']) && !empty($data['dob_month']) && !empty($data['dob_day'])) {
            $data['dob'] = sprintf('%04d-%02d-%02d', (int)$data['dob_year'], (int)$data['dob_month'], (int)$data['dob_day']);
        } else {
            $data['dob'] = trim((string) ($data['dob'] ?? ''));
            if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $data['dob'], $m)) {
                $data['dob'] = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            }
        }
        if ($data['dob'] === '') {
            $data['dob'] = null;
        }
        $data['academic_year'] = trim((string) ($data['academic_year'] ?? ''));
        $data['semester'] = trim((string) ($data['semester'] ?? ''));
        $data['section'] = strtoupper(trim((string) ($data['section'] ?? '')));
        if ($data['section'] === '') {
            $data['section'] = null;
        }
        $data['admission_year'] = trim((string) ($data['admission_year'] ?? ''));
        $data['graduation_year'] = trim((string) ($data['graduation_year'] ?? ''));
        // Auto-derive graduation year from admission year (3-year programme) if not provided
        if ($data['graduation_year'] === '' && $data['admission_year'] !== '' && preg_match('/^\d{4}$/', $data['admission_year'])) {
            $data['graduation_year'] = (string) ((int) $data['admission_year'] + 3);
        }
        if ($data['graduation_year'] === '') {
            $data['graduation_year'] = null;
        }
        $data['department_id'] = trim((string) ($data['department_id'] ?? ''));
        $data['account_status'] = trim((string) ($data['account_status'] ?? 'Active'));

        $existingRegisterNumber = (string) ($existingStudent['register_number'] ?? '');
        $existingRollNumber = (string) ($existingStudent['roll_number'] ?? '');
        $existingEmail = (string) ($existingStudent['email'] ?? '');
        $existingPhone = (string) ($existingStudent['phone'] ?? '');

        if (
            $data['register_number'] !== ''
            && $data['register_number'] !== $existingRegisterNumber
            && $this->studentModel->findByRegisterNumber($data['register_number'])
        ) {
            return [
                'success' => false,
                'message' => 'Register number already exists.'
            ];
        }


        if (
            $data['email'] !== ''
            && $data['email'] !== $existingEmail
            && $this->studentModel->findByEmail($data['email'])
        ) {
            return [
                'success' => false,
                'message' => 'Email address already exists.'
            ];
        }

        if (
            $data['phone'] !== ''
            && $data['phone'] !== $existingPhone
            && $this->studentModel->findByPhone($data['phone'])
        ) {
            return [
                'success' => false,
                'message' => 'Phone number already exists.'
            ];
        }

        $deletePhoto = null;

        $existingPhotoPath = $existingStudent['profile_photo'] ?? null;

        if (!empty($data['remove_profile_photo'])) {
            $existingPhotoPath = null;
            $deletePhoto = $existingStudent['profile_photo'] ?? null;
        }

        $photoUpload = $this->processFileUpload(
            $files,
            'profile_photo',
            'student_',
            'uploads/student_photos',
            [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ],
            $existingPhotoPath
        );

        if ($photoUpload['error'] !== null) {
            return [
                'success' => false,
                'message' => $photoUpload['error']
            ];
        }

        if (
            $photoUpload['path'] !== ($existingStudent['profile_photo'] ?? null)
            && !empty($existingStudent['profile_photo'])
        ) {
            $deletePhoto = $existingStudent['profile_photo'];
        }

        $data['profile_photo'] = $photoUpload['path'];

        unset(
            $data['password'],
            $data['confirm_password'],
            $data['remove_profile_photo'],
            $data['student_id']
        );

        $updated = $this->studentModel->update($studentId, $data);

        if (!$updated) {
            return [
                'success' => false,
                'message' => 'Unable to update student.'
            ];
        }

        if (!empty($deletePhoto)) {
            $this->deleteUploadedFile($deletePhoto);
        }

        return [
            'success' => true,
            'message' => 'Student updated successfully.'
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * Delete student.
     *
     * @param int $studentId
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function deleteStudent(int $studentId): array
    {
        $existingStudent = $this->studentModel->findById($studentId);

        if (!$existingStudent) {
            return [
                'success' => false,
                'message' => 'Student not found.'
            ];
        }

        try {
            $deleted = $this->studentModel->delete($studentId);
        } catch (\PDOException $e) {
            // FK constraint violation — student has related records that prevent deletion.
            if (str_starts_with($e->getCode(), '23')) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete this student because they have related records (applications, registrations, etc.). Remove those records first.'
                ];
            }
            return [
                'success' => false,
                'message' => 'Unable to delete student. Please try again.'
            ];
        }

        if (!$deleted) {
            return [
                'success' => false,
                'message' => 'Unable to delete student.'
            ];
        }

        $this->deleteUploadedFile($existingStudent['profile_photo'] ?? null);

        return [
            'success' => true,
            'message' => 'Student deleted successfully.'
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * Count all students.
     *
     * @return int
     * ---------------------------------------------------------------------
     */
    public function countAllStudents(): int
    {
        return $this->studentModel->countAll();
    }

    /**
     * ---------------------------------------------------------------------
     * Count active students.
     *
     * @return int
     * ---------------------------------------------------------------------
     */
    public function countActiveStudents(): int
    {
        return $this->studentModel->countActive();
    }

    /**
     * ---------------------------------------------------------------------
     * Count inactive students.
     *
     * @return int
     * ---------------------------------------------------------------------
     */
    public function countInactiveStudents(): int
    {
        return $this->studentModel->countInactive();
    }

    /**
     * ---------------------------------------------------------------------
     * Process a student photo upload.
     *
     * @param array       $files
     * @param string      $fieldName
     * @param string      $prefix
     * @param string      $folder
     * @param array       $allowedExtensions
     * @param string|null $existingPath
     *
     * @return array{path: string|null, error: string|null}
     * ---------------------------------------------------------------------
     */
    private function processFileUpload(
        array $files,
        string $fieldName,
        string $prefix,
        string $folder,
        array $allowedExtensions,
        ?string $existingPath = null
    ): array {
        if (!isset($files[$fieldName]) || $files[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            return [
                'path' => $existingPath,
                'error' => null
            ];
        }

        $uploadedPath = $this->uploadImageFile(
            $files[$fieldName],
            $prefix,
            $folder,
            $allowedExtensions
        );

        if ($uploadedPath === null) {
            return [
                'path' => $existingPath,
                'error' => 'Profile photo must be a valid image file.'
            ];
        }

        return [
            'path' => $uploadedPath,
            'error' => null
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * Validate and upload an image file.
     *
     * @param array  $file
     * @param string $prefix
     * @param string $folder
     * @param array  $allowedExtensions
     *
     * @return string|null
     * ---------------------------------------------------------------------
     */
    private function uploadImageFile(
        array $file,
        string $prefix,
        string $folder,
        array $allowedExtensions
    ): ?string {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            return null;
        }

        if (@getimagesize($file['tmp_name']) === false) {
            return null;
        }

        $fileName = uniqid($prefix, true) . '.' . $extension;

        $uploadDir = dirname(__DIR__, 2) . '/public/' . $folder . '/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $folder . '/' . $fileName;
        }

        return null;
    }

    /**
     * ---------------------------------------------------------------------
     * Delete an uploaded file from public storage.
     *
     * @param string|null $relativePath
     *
     * @return void
     * ---------------------------------------------------------------------
     */
    private function deleteUploadedFile(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        if (!str_starts_with($relativePath, 'uploads/student_photos/')) {
            return;
        }

        $fullPath = dirname(__DIR__, 2) . '/public/' . $relativePath;

        if (is_file($fullPath)) {
            unlink($fullPath);
        }
    }
}
