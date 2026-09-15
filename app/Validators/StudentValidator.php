<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : StudentValidator.php
 * Location    : app/Validators/
 * Description : Validates student input before it reaches the
 *               service and model layers.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Validate register number
 * • Validate roll number
 * • Validate department
 * • Validate full name
 * • Validate email
 * • Validate phone
 * • Validate gender
 * • Validate DOB
 * • Validate academic year
 * • Validate semester
 * • Validate section
 * • Validate admission year
 * • Validate graduation year
 * • Validate profile photo
 *
 * NOTE
 * -------------------------------------------------------------------------
 * This class performs only input validation.
 * It does NOT communicate with the database.
 * Business rules belong in StudentService.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Validators;

final class StudentValidator
{
    /**
     * ---------------------------------------------------------------------
     * Validate student data.
     *
     * @param array $data
     *
     * @return array<string, string>
     * ---------------------------------------------------------------------
     */
    public function validate(array $data): array
    {
        $errors = [];

        $registerNumber = strtoupper(trim((string) ($data['register_number'] ?? '')));

        if ($registerNumber === '') {
            $errors['register_number'] = 'Register number is required.';
        }
        elseif (!preg_match('/^[A-Z0-9-]{2,25}$/', $registerNumber)) {
            $errors['register_number'] = 'Register number must contain only letters, numbers and dashes (2-25 characters).';
        }

        $rollNumber = strtoupper(trim((string) ($data['roll_number'] ?? '')));

        if ($rollNumber !== '' && !preg_match('/^[A-Z0-9-]{2,25}$/', $rollNumber)) {
            $errors['roll_number'] = 'Roll number must contain only letters, numbers and dashes (2-25 characters).';
        }

        $departmentId = trim((string) ($data['department_id'] ?? ''));

        if ($departmentId === '') {
            $errors['department_id'] = 'Please select a department.';
        }
        elseif (!ctype_digit($departmentId) || (int) $departmentId <= 0) {
            $errors['department_id'] = 'Please select a valid department.';
        }

        $fullName = trim((string) ($data['full_name'] ?? ''));

        if ($fullName === '') {
            $errors['full_name'] = 'Full name is required.';
        }
        elseif (mb_strlen($fullName) > 120) {
            $errors['full_name'] = 'Full name cannot exceed 120 characters.';
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($email === '') {
            $errors['email'] = 'Email address is required.';
        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        $phone = trim((string) ($data['phone'] ?? ''));

        if ($phone === '') {
            $errors['phone'] = 'Phone number is required.';
        }
        elseif (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            $errors['phone'] = 'Phone number must contain 10 to 15 digits.';
        }

        $gender = trim((string) ($data['gender'] ?? ''));

        if (!in_array($gender, ['Male', 'Female'], true)) {
            $errors['gender'] = 'Please select a valid gender.';
        }

        $dob = trim((string) ($data['dob'] ?? ''));

        if ($dob !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                $errors['dob'] = 'Date of birth must be in YYYY-MM-DD format.';
            } elseif (strtotime($dob) === false) {
                $errors['dob'] = 'Please enter a valid date of birth.';
            }
        }

        $academicYear = trim((string) ($data['academic_year'] ?? ''));

        if ($academicYear === '') {
            $errors['academic_year'] = 'Academic year is required.';
        }
        elseif (!in_array($academicYear, ['1', '2', '3'], true)) {
            $errors['academic_year'] = 'Academic year must be 1 (First Year), 2 (Second Year) or 3 (Third Year).';
        }

        $semester = trim((string) ($data['semester'] ?? ''));

        if ($semester === '') {
            $errors['semester'] = 'Semester is required.';
        }
        elseif (!preg_match('/^[1-6]$/', $semester)) {
            $errors['semester'] = 'Semester must be between 1 and 6.';
        }

        if ($semester !== '' && $academicYear !== '' && preg_match('/^[1-6]$/', $semester)) {
            $allowedSemesters = [
                '1' => ['1', '2'],
                '2' => ['3', '4'],
                '3' => ['5', '6']
            ];

            if (isset($allowedSemesters[$academicYear]) && !in_array($semester, $allowedSemesters[$academicYear], true)) {
                $errors['semester'] = 'Semester does not match the selected academic year.';
            }
        }

        $section = strtoupper(trim((string) ($data['section'] ?? '')));

        if ($section !== '' && !preg_match('/^[A-Z0-9]$/', $section)) {
            $errors['section'] = 'Section must be a single letter or number.';
        }

        $admissionYear = trim((string) ($data['admission_year'] ?? ''));

        if ($admissionYear === '') {
            $errors['admission_year'] = 'Admission year is required.';
        }
        elseif (!preg_match('/^\d{4}$/', $admissionYear)) {
            $errors['admission_year'] = 'Admission year must be a 4-digit year.';
        }

        $graduationYear = trim((string) ($data['graduation_year'] ?? ''));

        if ($graduationYear !== '' && !preg_match('/^\d{4}$/', $graduationYear)) {
            $errors['graduation_year'] = 'Graduation year must be a 4-digit year.';
        }

        if (empty($data['student_id'])) {
            $password = (string) ($data['password'] ?? '');
            $confirmPassword = (string) ($data['confirm_password'] ?? '');

            if ($password === '') {
                $errors['password'] = 'Password is required.';
            }
            elseif (strlen($password) < 8) {
                $errors['password'] = 'Password must contain at least 8 characters.';
            }

            if ($confirmPassword === '') {
                $errors['confirm_password'] = 'Please confirm the password.';
            }
            elseif ($password !== $confirmPassword) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }
        }

        $accountStatus = trim((string) ($data['account_status'] ?? ''));

        if (!in_array($accountStatus, ['Active', 'Inactive'], true)) {
            $errors['account_status'] = 'Please select a valid account status.';
        }

        return $errors;
    }

    /**
     * ---------------------------------------------------------------------
     * Validate uploaded profile photo.
     *
     * @param array $files
     *
     * @return array<string, string>
     * ---------------------------------------------------------------------
     */
    public function validateFiles(array $files): array
    {
        $errors = [];

        $maxFileSize = 2 * 1024 * 1024;

        if (isset($files['profile_photo']) && $files['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $profileError = $this->validateUploadedFile(
                $files['profile_photo'],
                [
                    'jpg',
                    'jpeg',
                    'png',
                    'webp'
                ],
                $maxFileSize,
                'Profile photo'
            );

            if ($profileError !== null) {
                $errors['profile_photo'] = $profileError;
            }
        }

        return $errors;
    }

    /**
     * ---------------------------------------------------------------------
     * Validate a single uploaded file.
     *
     * @param array  $file
     * @param array  $allowedExtensions
     * @param int    $maxFileSize
     * @param string $label
     *
     * @return string|null
     * ---------------------------------------------------------------------
     */
    private function validateUploadedFile(
        array $file,
        array $allowedExtensions,
        int $maxFileSize,
        string $label
    ): ?string {
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            return $label . ' exceeds the maximum allowed file size of 2 MB.';
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return $label . ' upload failed. Please try again.';
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            return $label . ' must be a ' . implode(', ', $allowedExtensions) . ' file.';
        }

        if ($file['size'] > $maxFileSize) {
            return $label . ' exceeds the maximum allowed file size of 2 MB.';
        }

        if (@getimagesize($file['tmp_name']) === false) {
            return $label . ' must be a valid image file.';
        }

        return null;
    }
}
