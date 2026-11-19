<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Models\CollegeModel;
use App\Models\StudentModel;
use PDO;

final class StudentAuthService
{
    private StudentModel $studentModel;
    private CollegeModel $collegeModel;

    public function __construct()
    {
        $this->studentModel = new StudentModel();
        $this->collegeModel = new CollegeModel();
    }

    /**
     * Authenticate a student by Register Number OR Email.
     *
     * @param string $identifier Register number or email
     * @param string $password   Plain-text password
     * @return array ['success' => bool, 'message' => string]
     */
    public function login(string $identifier, string $password): array
    {
        $cleanIdentifier = trim($identifier);

        if ($cleanIdentifier === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Please provide your register number/email and password.',
            ];
        }

        $student = $this->studentModel->findByEmailOrRegisterNumber($cleanIdentifier);

        if ($student === false) {
            // Check if multiple accounts exist with this roll number across institutions
            if (!filter_var($cleanIdentifier, FILTER_VALIDATE_EMAIL)) {
                $db = \App\Database\Database::getConnection();
                $checkStmt = $db->prepare("SELECT COUNT(*) FROM students WHERE register_number = :reg");
                $checkStmt->execute(['reg' => $cleanIdentifier]);
                if ((int)$checkStmt->fetchColumn() > 1) {
                    return [
                        'success' => false,
                        'message' => 'Multiple accounts found with this register number across different colleges. Please log in using your registered email address.',
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Invalid register number/email or password.',
            ];
        }

        if (($student['account_status'] ?? '') !== 'Active') {
            return [
                'success' => false,
                'message' => 'Your account is inactive. Please contact the administrator.',
            ];
        }

        if (!password_verify($password, $student['password_hash'] ?? '')) {
            return [
                'success' => false,
                'message' => 'Invalid register number/email or password.',
            ];
        }

        Session::regenerate();

        Session::set('student', [
            'student_id'          => (int)$student['student_id'],
            'register_number'     => (string)$student['register_number'],
            'full_name'           => (string)$student['full_name'],
            'email'               => (string)($student['email'] ?? ''),
            'department_id'       => (int)($student['department_id'] ?? 0),
            'academic_year'       => (int)($student['academic_year'] ?? 1),
            'semester'            => (int)($student['semester'] ?? 1),
            'gender'              => (string)($student['gender'] ?? ''),
            'account_status'      => (string)($student['account_status'] ?? 'Active'),
            'student_type'        => (string)($student['student_type'] ?? 'Internal'),
            'college_id'          => isset($student['college_id']) ? (int)$student['college_id'] : null,
            'college_name'        => (string)($student['college_name'] ?? 'Government Arts and Science College, Veerapandi, Theni'),
            'external_department' => (string)($student['external_department'] ?? ''),
            'affiliation_status'  => (string)($student['affiliation_status'] ?? 'Verified'),
        ]);

        return [
            'success' => true,
            'message' => 'Login successful.',
        ];
    }

    /**
     * Register a new External Student from another college/institution.
     *
     * @param array $data
     * @return array
     */
    public function registerExternalStudent(array $data): array
    {
        $fullName       = trim((string)($data['full_name'] ?? ''));
        $registerNumber = trim((string)($data['register_number'] ?? ''));
        $email          = strtolower(trim((string)($data['email'] ?? '')));
        $phone          = trim((string)($data['phone'] ?? ''));
        $academicYear   = (int)($data['academic_year'] ?? 1);
        $semester       = (int)($data['semester'] ?? 1);
        $gender         = ($data['gender'] ?? '') === 'Female' ? 'Female' : 'Male';
        $dob            = !empty($data['dob']) ? trim((string)$data['dob']) : null;
        $password       = (string)($data['password'] ?? '');
        $passwordConfirm= (string)($data['password_confirm'] ?? '');
        $externalDept   = trim((string)($data['department'] ?? $data['external_department'] ?? ''));
        $collegeSelection = trim((string)($data['college_id'] ?? ''));
        $customCollegeName= trim((string)($data['custom_college_name'] ?? ''));

        // Validation
        if ($fullName === '' || mb_strlen($fullName) < 2) {
            return ['success' => false, 'message' => 'Please provide a valid full name.'];
        }

        if ($registerNumber === '') {
            return ['success' => false, 'message' => 'Please provide your official college/university register number.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please provide a valid email address.'];
        }

        if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            return ['success' => false, 'message' => 'Please provide a valid 10-digit mobile number.'];
        }

        if (mb_strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
        }

        if ($password !== $passwordConfirm) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }

        if ($externalDept === '') {
            return ['success' => false, 'message' => 'Please provide your degree/department name (e.g. B.Sc Computer Science).'];
        }

        // Resolve College
        $collegeId = null;
        $collegeName = null;
        $affiliationStatus = 'Verified';

        if ($collegeSelection === 'other' || $collegeSelection === '') {
            if ($customCollegeName === '' || mb_strlen($customCollegeName) < 3) {
                return ['success' => false, 'message' => 'Please select your college or enter your full official college name.'];
            }
            $collegeRes = $this->collegeModel->findOrCreate($customCollegeName);
            $collegeId = $collegeRes['college_id'];
            $collegeName = $collegeRes['college_name'];
            $affiliationStatus = $collegeRes['is_verified'] === 1 ? 'Verified' : 'Pending';
        } else {
            $college = $this->collegeModel->findById((int)$collegeSelection);
            if (!$college || (int)$college['is_active'] !== 1) {
                return ['success' => false, 'message' => 'Selected college is invalid or inactive.'];
            }
            $collegeId = (int)$college['college_id'];
            $collegeName = (string)$college['college_name'];
            $affiliationStatus = (int)$college['is_verified'] === 1 ? 'Verified' : 'Pending';
        }

        // Uniqueness checks
        if ($this->studentModel->findByEmail($email)) {
            return ['success' => false, 'message' => 'An account with this email address already exists. Please log in.'];
        }

        if ($this->studentModel->findByPhone($phone)) {
            return ['success' => false, 'message' => 'An account with this phone number already exists. Please log in.'];
        }

        if ($this->studentModel->findByRegisterNumber($registerNumber, $collegeId)) {
            return ['success' => false, 'message' => 'A student with this register number is already registered for this college.'];
        }

        // Compatibility department (EXT)
        $db = \App\Database\Database::getConnection();
        $extDeptStmt = $db->query("SELECT department_id FROM departments WHERE department_code = 'EXT' LIMIT 1");
        $extDeptId = (int)$extDeptStmt->fetchColumn();
        if (!$extDeptId) {
            // Fallback to first active department
            $fallbackStmt = $db->query("SELECT department_id FROM departments WHERE is_active = 1 LIMIT 1");
            $extDeptId = (int)$fallbackStmt->fetchColumn() ?: 1;
        }

        $admissionYear = (int)date('Y');
        $gradYear = $admissionYear + 3;

        $studentData = [
            'register_number'    => $registerNumber,
            'department_id'      => $extDeptId,
            'full_name'          => $fullName,
            'email'              => $email,
            'phone'              => $phone,
            'gender'             => $gender,
            'dob'                => $dob,
            'password_hash'      => password_hash($password, PASSWORD_DEFAULT),
            'academic_year'      => max(1, min(4, $academicYear)),
            'semester'           => max(1, min(8, $semester)),
            'admission_year'     => $admissionYear,
            'graduation_year'    => $gradYear,
            'account_status'     => 'Active',
            'student_type'       => 'External',
            'college_id'         => $collegeId,
            'college_name'       => $collegeName,
            'external_department'=> $externalDept,
            'affiliation_status' => $affiliationStatus
        ];

        $created = $this->studentModel->create($studentData);

        if (!$created) {
            return ['success' => false, 'message' => 'Failed to create student account. Please try again.'];
        }

        $newStudent = $this->studentModel->findByEmail($email);
        if (!$newStudent) {
            return ['success' => true, 'message' => 'Account created successfully! Please log in.'];
        }

        // Auto login session
        Session::regenerate();
        Session::set('student', [
            'student_id'          => (int)$newStudent['student_id'],
            'register_number'     => (string)$newStudent['register_number'],
            'full_name'           => (string)$newStudent['full_name'],
            'email'               => (string)($newStudent['email'] ?? ''),
            'department_id'       => (int)($newStudent['department_id'] ?? $extDeptId),
            'academic_year'       => (int)($newStudent['academic_year'] ?? $academicYear),
            'semester'            => (int)($newStudent['semester'] ?? $semester),
            'gender'              => (string)($newStudent['gender'] ?? $gender),
            'account_status'      => 'Active',
            'student_type'        => 'External',
            'college_id'          => $collegeId,
            'college_name'        => $collegeName,
            'external_department' => $externalDept,
            'affiliation_status'  => $affiliationStatus
        ]);

        return [
            'success' => true,
            'message' => 'Registration successful! Welcome to the NexusCore Student Portal.',
            'student' => $newStudent
        ];
    }

    /**
     * Destroy the student session (logout).
     */
    public function logout(): void
    {
        Session::remove('student');
        Session::regenerate();
    }

    /**
     * Check if a student is authenticated.
     */
    public function check(): bool
    {
        return Session::has('student');
    }

    /**
     * Get the authenticated student's session data.
     */
    public function student(): ?array
    {
        return Session::get('student');
    }

    /**
     * Get the authenticated student's ID.
     */
    public function id(): ?int
    {
        $student = $this->student();
        return isset($student['student_id']) ? (int)$student['student_id'] : null;
    }
}
