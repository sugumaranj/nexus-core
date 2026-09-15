<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : StudentModel.php
 * Location    : app/Models/
 * Description : Handles all Student database operations.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Retrieve students
 * • Retrieve a single student
 * • Find student by register number
 * • Find student by roll number
 * • Create student
 * • Update student
 * • Delete student
 * • Update student photo
 * • Remove student photo
 * • Count students
 *
 * NOTE
 * -------------------------------------------------------------------------
 * This class communicates ONLY with the database.
 * Business rules belong inside StudentService.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use App\Database\Database;
use PDO;

final class StudentModel
{
    /**
     * Database connection.
     *
     * @var PDO
     */
    private PDO $db;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * ---------------------------------------------------------------------
     * Retrieve all students.
     *
     * @param string|null $search
     * @param string|null $departmentId
     * @param string|null $academicYear
     * @param string|null $semester
     * @param string|null $status
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getAll(
        ?string $search = null,
        ?string $departmentId = null,
        ?string $academicYear = null,
        ?string $semester = null,
        ?string $status = null
    ): array {
        $sql = "
            SELECT
                " . $this->getSelectColumns() . ",
                d.department_name
            FROM students s
            LEFT JOIN departments d
                ON d.department_id = s.department_id
        ";

        $conditions = [];

        $params = [];

        if ($search !== null && trim($search) !== '') {
            $searchConditions = [];

            if ($this->hasColumn('register_number')) {
                $searchConditions[] = 's.register_number LIKE :keyword_register_number';
                $params['keyword_register_number'] = '%' . trim($search) . '%';
            }

            if ($this->hasColumn('roll_number')) {
                $searchConditions[] = 's.roll_number LIKE :keyword_roll_number';
                $params['keyword_roll_number'] = '%' . trim($search) . '%';
            }

            if ($this->hasColumn('full_name')) {
                $searchConditions[] = 's.full_name LIKE :keyword_full_name';
                $params['keyword_full_name'] = '%' . trim($search) . '%';
            }

            if ($this->hasColumn('email')) {
                $searchConditions[] = 's.email LIKE :keyword_email';
                $params['keyword_email'] = '%' . trim($search) . '%';
            }

            if ($this->hasColumn('phone')) {
                $searchConditions[] = 's.phone LIKE :keyword_phone';
                $params['keyword_phone'] = '%' . trim($search) . '%';
            }

            if ($this->hasColumn('department_id')) {
                $searchConditions[] = 'd.department_name LIKE :keyword_department';
                $params['keyword_department'] = '%' . trim($search) . '%';
            }

            if (!empty($searchConditions)) {
                $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
            }
        }

        if ($departmentId !== null && trim($departmentId) !== '') {
            $conditions[] = 's.department_id = :department_id';
            $params['department_id'] = (int) trim($departmentId);
        }

        if ($academicYear !== null && trim($academicYear) !== '') {
            if ($this->hasColumn('academic_year')) {
                $conditions[] = 's.academic_year = :academic_year';
                $params['academic_year'] = trim($academicYear);
            }
        }

        if ($semester !== null && trim($semester) !== '') {
            if ($this->hasColumn('semester')) {
                $conditions[] = 's.semester = :semester';
                $params['semester'] = trim($semester);
            }
        }

        if ($status !== null && trim($status) !== '') {
            if ($this->hasColumn('account_status')) {
                $conditions[] = 's.account_status = :status';
                $params['status'] = trim($status);
            }
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY s.full_name ASC, s.register_number ASC';

        $statement = $this->db->prepare($sql);

        $statement->execute($params);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
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
        $sql = "SELECT DISTINCT academic_year FROM students WHERE academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year ASC";
        $statement = $this->db->prepare($sql);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_COLUMN);
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
        $sql = "SELECT DISTINCT semester FROM students WHERE semester IS NOT NULL AND semester != '' ORDER BY CAST(semester AS UNSIGNED) ASC";
        $statement = $this->db->prepare($sql);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * ---------------------------------------------------------------------
     * Retrieve a student by ID.
     *
     * @param int $studentId
     *
     * @return array|false
     * ---------------------------------------------------------------------
     */
    public function findById(int $studentId): array|false
    {
        $sql = "
            SELECT
                " . $this->getSelectColumns() . ",
                d.department_name
            FROM students s
            LEFT JOIN departments d
                ON d.department_id = s.department_id
            WHERE s.student_id = :student_id
            LIMIT 1
        ";

        $statement = $this->db->prepare($sql);

        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);

        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ---------------------------------------------------------------------
     * Find student by register number.
     *
     * @param string $registerNumber
     *
     * @return array|false
     * ---------------------------------------------------------------------
     */
    public function findByRegisterNumber(string $registerNumber): array|false
    {
        if (!$this->hasColumn('register_number')) {
            return false;
        }

        $sql = "
            SELECT *
            FROM students
            WHERE register_number = :register_number
            LIMIT 1
        ";

        $statement = $this->db->prepare($sql);

        $statement->bindValue(':register_number', trim($registerNumber));

        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ---------------------------------------------------------------------
     * Find student by roll number.
     *
     * @param string $rollNumber
     *
     * @return array|false
     * ---------------------------------------------------------------------
     */
    public function findByRollNumber(string $rollNumber): array|false
    {
        if (!$this->hasColumn('roll_number')) {
            return false;
        }

        $sql = "
            SELECT *
            FROM students
            WHERE roll_number = :roll_number
            LIMIT 1
        ";

        $statement = $this->db->prepare($sql);

        $statement->bindValue(':roll_number', trim($rollNumber));

        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ---------------------------------------------------------------------
     * Find student by email.
     *
     * @param string $email
     *
     * @return array|false
     * ---------------------------------------------------------------------
     */
    public function findByEmail(string $email): array|false
    {
        if (!$this->hasColumn('email')) {
            return false;
        }

        $sql = "
            SELECT *
            FROM students
            WHERE email = :email
            LIMIT 1
        ";

        $statement = $this->db->prepare($sql);

        $statement->bindValue(':email', strtolower(trim($email)));

        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ---------------------------------------------------------------------
     * Find student by phone.
     *
     * @param string $phone
     *
     * @return array|false
     * ---------------------------------------------------------------------
     */
    public function findByPhone(string $phone): array|false
    {
        if (!$this->hasColumn('phone')) {
            return false;
        }

        $sql = "
            SELECT *
            FROM students
            WHERE phone = :phone
            LIMIT 1
        ";

        $statement = $this->db->prepare($sql);

        $statement->bindValue(':phone', trim($phone));

        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ---------------------------------------------------------------------
     * Create a new student.
     *
     * @param array $data
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function create(array $data): bool
    {
        $fields = $this->getCreateFields($data);

        if (empty($fields)) {
            return false;
        }

        $sql = "
            INSERT INTO students
            (
                " . implode(', ', array_keys($fields)) . "
            )
            VALUES
            (
                " . implode(', ', array_map(static fn (string $field): string => ':' . $field, array_keys($fields))) . "
            )
        ";

        $statement = $this->db->prepare($sql);

        return $statement->execute($this->prepareBindings($fields));
    }

    /**
     * ---------------------------------------------------------------------
     * Update an existing student.
     *
     * @param int   $studentId
     * @param array $data
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function update(int $studentId, array $data): bool
    {
        $fields = $this->getUpdateFields($data);

        if (empty($fields)) {
            return false;
        }

        $assignments = [];

        foreach (array_keys($fields) as $field) {
            $assignments[] = $field . ' = :' . $field;
        }

        $sql = "
            UPDATE students
            SET " . implode(', ', $assignments) . "
            WHERE student_id = :student_id
        ";

        $bindings = $this->prepareBindings($fields);

        $bindings['student_id'] = $studentId;

        $statement = $this->db->prepare($sql);

        return $statement->execute($bindings);
    }

    /**
     * ---------------------------------------------------------------------
     * Delete a student.
     *
     * @param int $studentId
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function delete(int $studentId): bool
    {
        $sql = "
            DELETE FROM students
            WHERE student_id = :student_id
        ";

        $statement = $this->db->prepare($sql);

        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);

        return $statement->execute();
    }

    /**
     * ---------------------------------------------------------------------
     * Search students.
     *
     * @param string|null $search
     * @param string|null $departmentId
     * @param string|null $academicYear
     * @param string|null $semester
     * @param string|null $status
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function search(
        ?string $search = null,
        ?string $departmentId = null,
        ?string $academicYear = null,
        ?string $semester = null,
        ?string $status = null
    ): array {
        return $this->getAll(
            $search,
            $departmentId,
            $academicYear,
            $semester,
            $status
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Update student profile photo.
     *
     * @param int         $studentId
     * @param string|null $photoPath
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function updatePhoto(int $studentId, ?string $photoPath): bool
    {
        if (!$this->hasColumn('profile_photo')) {
            return false;
        }

        $sql = "
            UPDATE students
            SET profile_photo = :profile_photo
            WHERE student_id = :student_id
        ";

        $statement = $this->db->prepare($sql);

        $statement->bindValue(':profile_photo', $photoPath);
        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);

        return $statement->execute();
    }

    /**
     * ---------------------------------------------------------------------
     * Remove student profile photo.
     *
     * @param int $studentId
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function removePhoto(int $studentId): bool
    {
        if (!$this->hasColumn('profile_photo')) {
            return false;
        }

        $sql = "
            UPDATE students
            SET profile_photo = NULL
            WHERE student_id = :student_id
        ";

        $statement = $this->db->prepare($sql);

        $statement->bindValue(':student_id', $studentId, PDO::PARAM_INT);

        return $statement->execute();
    }

    /**
     * ---------------------------------------------------------------------
     * Count all students.
     *
     * @return int
     * ---------------------------------------------------------------------
     */
    public function countAll(): int
    {
        $sql = 'SELECT COUNT(*) AS total FROM students';

        $statement = $this->db->query($sql);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['total'] ?? 0);
    }

    /**
     * ---------------------------------------------------------------------
     * Count active students.
     *
     * @return int
     * ---------------------------------------------------------------------
     */
    public function countActive(): int
    {
        if (!$this->hasColumn('account_status')) {
            return $this->countAll();
        }

        $sql = "
            SELECT COUNT(*) AS total
            FROM students
            WHERE account_status = :status
        ";

        $statement = $this->db->prepare($sql);

        $statement->bindValue(':status', 'Active');

        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['total'] ?? 0);
    }

    /**
     * ---------------------------------------------------------------------
     * Count inactive students.
     *
     * @return int
     * ---------------------------------------------------------------------
     */
    public function countInactive(): int
    {
        if (!$this->hasColumn('account_status')) {
            return 0;
        }

        $sql = "
            SELECT COUNT(*) AS total
            FROM students
            WHERE account_status = :status
        ";

        $statement = $this->db->prepare($sql);

        $statement->bindValue(':status', 'Inactive');

        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['total'] ?? 0);
    }

    /**
     * ---------------------------------------------------------------------
     * Build the SELECT columns used in student queries.
     *
     * @return string
     * ---------------------------------------------------------------------
     */
    private function getSelectColumns(): string
    {
        $columns = [];

        $columns[] = 's.student_id';
        $columns[] = 's.department_id';

        if ($this->hasColumn('register_number')) {
            $columns[] = 's.register_number';
        }

        if ($this->hasColumn('roll_number')) {
            $columns[] = 's.roll_number';
        }

        if ($this->hasColumn('full_name')) {
            $columns[] = 's.full_name';
        }

        if ($this->hasColumn('email')) {
            $columns[] = 's.email';
        }

        if ($this->hasColumn('phone')) {
            $columns[] = 's.phone';
        }

        if ($this->hasColumn('gender')) {
            $columns[] = 's.gender';
        }

        if ($this->hasColumn('dob')) {
            $columns[] = 's.dob';
        }

        if ($this->hasColumn('academic_year')) {
            $columns[] = 's.academic_year';
        }

        if ($this->hasColumn('semester')) {
            $columns[] = 's.semester';
        }

        if ($this->hasColumn('section')) {
            $columns[] = 's.section';
        }

        if ($this->hasColumn('admission_year')) {
            $columns[] = 's.admission_year';
        }

        if ($this->hasColumn('graduation_year')) {
            $columns[] = 's.graduation_year';
        }

        if ($this->hasColumn('profile_photo')) {
            $columns[] = 's.profile_photo';
        }

        if ($this->hasColumn('account_status')) {
            $columns[] = 's.account_status';
        }

        if ($this->hasColumn('created_at')) {
            $columns[] = 's.created_at';
        }

        if ($this->hasColumn('updated_at')) {
            $columns[] = 's.updated_at';
        }

        return implode(', ', $columns);
    }

    /**
     * ---------------------------------------------------------------------
     * Collect only supported fields for insert operations.
     *
     * @param array $data
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    private function getCreateFields(array $data): array
    {
        $allowedFields = [
            'register_number',
            'roll_number',
            'department_id',
            'full_name',
            'email',
            'phone',
            'gender',
            'dob',
            'password_hash',
            'academic_year',
            'semester',
            'section',
            'admission_year',
            'graduation_year',
            'profile_photo',
            'account_status'
        ];

        $fields = [];

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if (!$this->hasColumn($field)) {
                continue;
            }

            $fields[$field] = $data[$field];
        }

        return $fields;
    }

    /**
     * ---------------------------------------------------------------------
     * Collect only supported fields for update operations.
     *
     * @param array $data
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    private function getUpdateFields(array $data): array
    {
        $fields = $this->getCreateFields($data);

        unset($fields['password_hash']);

        return $fields;
    }

    /**
     * ---------------------------------------------------------------------
     * Prepare PDO bindings for field values.
     *
     * @param array $fields
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    private function prepareBindings(array $fields): array
    {
        $bindings = [];

        foreach ($fields as $field => $value) {
            $bindings[$field] = $value;
        }

        return $bindings;
    }

    /**
     * ---------------------------------------------------------------------
     * Check whether the students table contains a specific column.
     *
     * @param string $columnName
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    private function hasColumn(string $columnName): bool
    {
        static $columns = null;

        if ($columns === null) {
            $statement = $this->db->query('SHOW COLUMNS FROM students');

            $columns = [];

            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $columns[] = $column['Field'];
            }
        }

        return in_array($columnName, $columns, true);
    }
}
