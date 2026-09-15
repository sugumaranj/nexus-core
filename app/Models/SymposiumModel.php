<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore
 * =========================================================================
 * File        : SymposiumModel.php
 * Location    : app/Models/
 * Description : All Symposium database operations.
 *
 * Responsibilities
 * =========================================================================
 * • Generate the next unique symposium code (NEXUS-YYYY-NNN)
 * • CRUD: create, update, delete, findById, findByCode
 * • Search and filter with optional scope
 * • Count queries for dashboard statistics
 *
 * =========================================================================
 * ARCHITECTURAL PRINCIPLE — LEGACY COLUMN POLICY
 * =========================================================================
 * `symposiums.organizing_department_id` is a LEGACY backward-compatibility
 * column. It stores the FIRST department from the multi-department selection
 * for FK integrity. It is NULLABLE and IS NOT the source of truth.
 *
 * The CANONICAL source of truth is `symposium_departments` (junction table),
 * managed by SymposiumDepartmentModel. Refer to that model's header for
 * the full list of modules that MUST use the junction table.
 *
 * Rule: Never use organizing_department_id in new business logic.
 *
 * =========================================================================
 */

namespace App\Models;

use PDO;

final class SymposiumModel extends BaseModel
{
    // =========================================================================
    // CODE GENERATION
    // =========================================================================

    /**
     * -----------------------------------------------------------------------
     * Generate the next unique symposium code for an academic year.
     *
     * Format: NEXUS-{YEAR}-{NNN}
     * Example: NEXUS-2027-001, NEXUS-2027-002, NEXUS-2028-001
     *
     * @param int $academicYear
     *
     * @return string
     * -----------------------------------------------------------------------
     */
    public function generateNextCode(int $academicYear): string
    {
        $prefix = 'NEXUS-' . $academicYear . '-';

        // Find the highest existing sequence for this year
        $sql = '
            SELECT symposium_code
            FROM symposiums
            WHERE academic_year = :year
              AND symposium_code LIKE :prefix
            ORDER BY symposium_id DESC
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'year'   => $academicYear,
            'prefix' => $prefix . '%',
        ]);

        $lastCode = $stmt->fetchColumn();

        if ($lastCode) {
            // Extract sequence number from last code, e.g., NEXUS-2027-003 → 3
            $parts    = explode('-', (string) $lastCode);
            $lastSeq  = (int) end($parts);
            $nextSeq  = $lastSeq + 1;
        } else {
            $nextSeq = 1;
        }

        // Format with zero-padding: 001, 002, ...
        $code = $prefix . str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);

        // Safety: if somehow this code already exists, keep incrementing
        while ($this->findByCode($code) !== false) {
            $nextSeq++;
            $code = $prefix . str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    // =========================================================================
    // READ OPERATIONS
    // =========================================================================

    /**
     * -----------------------------------------------------------------------
     * Retrieve symposiums with optional search and filters.
     *
     * @param string|null $search
     * @param string|null $academicYear
     * @param string|null $symposiumType
     * @param string|null $status
     * @param string|null $quickFilter
     * @param int|null    $scopeCreatedBy
     * @param array       $scopeDepartmentIds  Filter by organizing dept IDs
     *
     * @return array
     * -----------------------------------------------------------------------
     */
    public function getAll(
        ?string $search = null,
        ?string $academicYear = null,
        ?string $symposiumType = null,
        ?string $status = null,
        ?string $quickFilter = null,
        ?int $scopeCreatedBy = null,
        array $scopeDepartmentIds = [],
        ?string $filterDepartmentId = null
    ): array {
        $sql = '
            SELECT
                s.symposium_id,
                s.symposium_code,
                s.title,
                s.symposium_type,
                s.academic_year,
                s.description,
                s.brochure_path,
                s.circular_path,
                s.registration_start,
                s.registration_end,
                s.event_start_date,
                s.event_end_date,
                s.status,
                s.submitted_at,
                s.created_by,
                s.created_at,
                s.updated_at,
                u.full_name AS created_by_name,
                GROUP_CONCAT(d.department_name ORDER BY sd.id SEPARATOR \' & \') AS organizing_departments
            FROM symposiums s
            LEFT JOIN users u ON u.user_id = s.created_by
            LEFT JOIN symposium_departments sd ON sd.symposium_id = s.symposium_id
            LEFT JOIN departments d ON d.department_id = sd.department_id
        ';

        $conditions = [];
        $params     = [];

        // Scope by creator (Staff Coordinator sees only their own)
        if ($scopeCreatedBy !== null) {
            $conditions[] = 's.created_by = :scope_created_by';
            $params['scope_created_by'] = $scopeCreatedBy;
        }

        // Filter by user-selected department
        if ($filterDepartmentId !== null && trim($filterDepartmentId) !== '') {
            $conditions[] = "EXISTS (
                SELECT 1 FROM symposium_departments sd3
                WHERE sd3.symposium_id = s.symposium_id
                  AND sd3.department_id = :filter_dept_id
            )";
            $params['filter_dept_id'] = (int) trim($filterDepartmentId);
        }

        // Scope by organizing departments (HODs see symposiums their dept organizes)
        if (!empty($scopeDepartmentIds)) {
            $placeholders = implode(',', array_fill(0, count($scopeDepartmentIds), '?'));
            $conditions[] = "EXISTS (
                SELECT 1 FROM symposium_departments sd2
                WHERE sd2.symposium_id = s.symposium_id
                  AND sd2.department_id IN ($placeholders)
            )";
            // These positional params must be added separately — use a subquery approach
        }

        // Full-text search
        if ($search !== null && trim($search) !== '') {
            $keyword      = '%' . trim($search) . '%';
            $conditions[] = '(
                LOWER(s.symposium_code) LIKE LOWER(:kw_code)
                OR LOWER(s.title)       LIKE LOWER(:kw_title)
                OR CAST(s.academic_year AS CHAR) LIKE :kw_year
                OR LOWER(s.status)      LIKE LOWER(:kw_status)
                OR LOWER(s.description) LIKE LOWER(:kw_desc)
            )';
            $params['kw_code']   = $keyword;
            $params['kw_title']  = $keyword;
            $params['kw_year']   = $keyword;
            $params['kw_status'] = $keyword;
            $params['kw_desc']   = $keyword;
        }

        if ($academicYear !== null && trim($academicYear) !== '') {
            $conditions[] = 's.academic_year = :academic_year';
            $params['academic_year'] = (int) trim($academicYear);
        }

        if ($symposiumType !== null && trim($symposiumType) !== '') {
            $conditions[] = 's.symposium_type = :symposium_type';
            $params['symposium_type'] = trim($symposiumType);
        }

        if ($status !== null && trim($status) !== '') {
            $conditions[] = 's.status = :status';
            $params['status'] = trim($status);
        }

        if ($quickFilter !== null && trim($quickFilter) !== '') {
            $conditions[] = $this->buildQuickFilterCondition(trim($quickFilter));
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' GROUP BY s.symposium_id ORDER BY s.event_start_date DESC, s.symposium_code ASC';

        // Handle scopeDepartmentIds with positional params (PDO limitation)
        if (!empty($scopeDepartmentIds)) {
            // Rebuild with positional params for the IN clause
            $sql = $this->buildScopeQuery($scopeDepartmentIds, $search, $academicYear, $symposiumType, $status, $quickFilter, $scopeCreatedBy, $filterDepartmentId);
            $params = [];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * -----------------------------------------------------------------------
     * Retrieve a single symposium by ID, with organizing departments.
     *
     * @param int $symposiumId
     *
     * @return array|false
     * -----------------------------------------------------------------------
     */
    public function findById(int $symposiumId): array|false
    {
        $sql = '
            SELECT
                s.symposium_id,
                s.symposium_code,
                s.title,
                s.symposium_type,
                s.organizing_department_id,
                s.academic_year,
                s.description,
                s.brochure_path,
                s.circular_path,
                s.registration_start,
                s.registration_end,
                s.event_start_date,
                s.event_end_date,
                s.status,
                s.submitted_at,
                s.submitted_by,
                s.created_by,
                s.created_at,
                s.updated_at,
                u.full_name  AS created_by_name,
                su.full_name AS submitted_by_name,
                GROUP_CONCAT(d.department_name ORDER BY sd.id SEPARATOR \' & \') AS organizing_departments
            FROM symposiums s
            LEFT JOIN users u  ON u.user_id  = s.created_by
            LEFT JOIN users su ON su.user_id = s.submitted_by
            LEFT JOIN symposium_departments sd ON sd.symposium_id = s.symposium_id
            LEFT JOIN departments d ON d.department_id = sd.department_id
            WHERE s.symposium_id = :symposium_id
            GROUP BY s.symposium_id
            LIMIT 1
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':symposium_id', $symposiumId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * -----------------------------------------------------------------------
     * Find a symposium by its unique code.
     *
     * @param string $symposiumCode
     *
     * @return array|false
     * -----------------------------------------------------------------------
     */
    public function findByCode(string $symposiumCode): array|false
    {
        $sql = 'SELECT * FROM symposiums WHERE symposium_code = :code LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':code', strtoupper(trim($symposiumCode)));
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // WRITE OPERATIONS
    // =========================================================================

    /**
     * -----------------------------------------------------------------------
     * Create a new symposium record.
     *
     * @param array $data
     *
     * @return int  The new symposium_id, or 0 on failure.
     * -----------------------------------------------------------------------
     */
    public function create(array $data): int
    {
        $sql = '
            INSERT INTO symposiums (
                symposium_code,
                title,
                symposium_type,
                organizing_department_id,
                academic_year,
                description,
                brochure_path,
                circular_path,
                registration_start,
                registration_end,
                event_start_date,
                event_end_date,
                status,
                created_by
            ) VALUES (
                :symposium_code,
                :title,
                :symposium_type,
                :organizing_department_id,
                :academic_year,
                :description,
                :brochure_path,
                :circular_path,
                :registration_start,
                :registration_end,
                :event_start_date,
                :event_end_date,
                :status,
                :created_by
            )
        ';

        $stmt = $this->db->prepare($sql);
        $ok   = $stmt->execute([
            'symposium_code'           => $data['symposium_code'],
            'title'                    => $data['title'],
            'symposium_type'           => $data['symposium_type'],
            // LEGACY: organizing_department_id stores the first selected department
            // for FK backward compatibility. NOT the source of truth.
            // All business logic must use symposium_departments instead.
            'organizing_department_id' => $data['organizing_department_id'] ?? null,
            'academic_year'            => $data['academic_year'],
            'description'              => $data['description'],
            'brochure_path'            => $data['brochure_path'] ?? null,
            'circular_path'            => $data['circular_path'] ?? null,
            'registration_start'       => $data['registration_start'],
            'registration_end'         => $data['registration_end'],
            'event_start_date'         => $data['event_start_date'],
            'event_end_date'           => $data['event_end_date'],
            'status'                   => $data['status'] ?? 'Draft',
            'created_by'               => $data['created_by'],
        ]);

        return $ok ? (int) $this->db->lastInsertId() : 0;
    }

    /**
     * -----------------------------------------------------------------------
     * Update an existing symposium's editable fields.
     *
     * Status is NOT updated here — use updateStatus() instead.
     *
     * @param int   $symposiumId
     * @param array $data
     *
     * @return bool
     * -----------------------------------------------------------------------
     */
    public function update(int $symposiumId, array $data): bool
    {
        $sql = '
            UPDATE symposiums SET
                title              = :title,
                symposium_type     = :symposium_type,
                academic_year      = :academic_year,
                description        = :description,
                brochure_path      = :brochure_path,
                circular_path      = :circular_path,
                registration_start = :registration_start,
                registration_end   = :registration_end,
                event_start_date   = :event_start_date,
                event_end_date     = :event_end_date
            WHERE symposium_id = :symposium_id
        ';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'title'              => $data['title'],
            'symposium_type'     => $data['symposium_type'],
            'academic_year'      => $data['academic_year'],
            'description'        => $data['description'],
            'brochure_path'      => $data['brochure_path'] ?? null,
            'circular_path'      => $data['circular_path'] ?? null,
            'registration_start' => $data['registration_start'],
            'registration_end'   => $data['registration_end'],
            'event_start_date'   => $data['event_start_date'],
            'event_end_date'     => $data['event_end_date'],
            'symposium_id'       => $symposiumId,
        ]);
    }

    /**
     * -----------------------------------------------------------------------
     * Update only the status column.
     *
     * @param int    $symposiumId
     * @param string $status
     *
     * @return bool
     * -----------------------------------------------------------------------
     */
    public function updateStatus(int $symposiumId, string $status): bool
    {
        $sql  = 'UPDATE symposiums SET status = :status WHERE symposium_id = :symposium_id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':symposium_id', $symposiumId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * -----------------------------------------------------------------------
     * Record submission timestamp and submitter.
     *
     * @param int $symposiumId
     * @param int $userId
     *
     * @return bool
     * -----------------------------------------------------------------------
     */
    public function updateSubmission(int $symposiumId, int $userId): bool
    {
        $sql  = '
            UPDATE symposiums
            SET submitted_at = NOW(), submitted_by = :user_id
            WHERE symposium_id = :symposium_id
        ';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'user_id'       => $userId,
            'symposium_id'  => $symposiumId,
        ]);
    }

    /**
     * -----------------------------------------------------------------------
     * Delete a symposium.
     *
     * @param int $symposiumId
     *
     * @return bool
     * -----------------------------------------------------------------------
     */
    public function delete(int $symposiumId): bool
    {
        $sql  = 'DELETE FROM symposiums WHERE symposium_id = :symposium_id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':symposium_id', $symposiumId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // =========================================================================
    // FILTER / COUNT HELPERS
    // =========================================================================

    /**
     * -----------------------------------------------------------------------
     * Alias for getAll() for backward compatibility.
     * -----------------------------------------------------------------------
     */
    public function search(
        ?string $search = null,
        ?string $academicYear = null,
        ?string $symposiumType = null,
        ?string $status = null,
        ?string $quickFilter = null,
        ?int $scopeCreatedBy = null,
        array $scopeDepartmentIds = [],
        ?string $filterDepartmentId = null
    ): array {
        return $this->getAll(
            $search, $academicYear, $symposiumType,
            $status, $quickFilter, $scopeCreatedBy, $scopeDepartmentIds, $filterDepartmentId
        );
    }

    /**
     * -----------------------------------------------------------------------
     * Get all distinct academic years stored in the table.
     *
     * @return array<string>
     * -----------------------------------------------------------------------
     */
    public function getDistinctAcademicYears(): array
    {
        $sql   = 'SELECT DISTINCT academic_year FROM symposiums ORDER BY academic_year DESC';
        $stmt  = $this->db->query($sql);
        $years = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!empty($row['academic_year'])) {
                $years[] = (string) $row['academic_year'];
            }
        }

        return $years;
    }

    /**
     * -----------------------------------------------------------------------
     * Count all symposiums with optional scope.
     *
     * @param int|null $scopeCreatedBy
     * @param array    $scopeDepartmentIds
     *
     * @return int
     * -----------------------------------------------------------------------
     */
    public function countAll(?int $scopeCreatedBy = null, array $scopeDepartmentIds = []): int
    {
        return $this->countByCondition('', [], $scopeCreatedBy, $scopeDepartmentIds);
    }

    /**
     * -----------------------------------------------------------------------
     * Count symposiums by a specific status.
     *
     * @param string   $status
     * @param int|null $scopeCreatedBy
     * @param array    $scopeDepartmentIds
     *
     * @return int
     * -----------------------------------------------------------------------
     */
    public function countByStatus(
        string $status,
        ?int $scopeCreatedBy = null,
        array $scopeDepartmentIds = []
    ): int {
        return $this->countByCondition(
            's.status = :status',
            ['status' => $status],
            $scopeCreatedBy,
            $scopeDepartmentIds
        );
    }

    /**
     * -----------------------------------------------------------------------
     * Count competitions linked to a symposium.
     *
     * @param int $symposiumId
     *
     * @return int
     * -----------------------------------------------------------------------
     */
    public function countCompetitions(int $symposiumId): int
    {
        $sql  = 'SELECT COUNT(*) FROM symposium_events WHERE symposium_id = :symposium_id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);

        return (int) $stmt->fetchColumn();
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Build a quick-filter SQL condition fragment.
     *
     * @param string $quickFilter
     *
     * @return string
     */
    private function buildQuickFilterCondition(string $quickFilter): string
    {
        return match ($quickFilter) {
            'Registration Open'   => "(s.status = 'Registration Open' OR (NOW() BETWEEN s.registration_start AND s.registration_end))",
            'Registration Closed' => "(s.status = 'Registration Closed' OR (NOW() > s.registration_end AND s.event_start_date > CURDATE()))",
            'Upcoming'            => "(s.event_start_date > CURDATE() AND s.status NOT IN ('Completed','Cancelled'))",
            'Completed'           => "(s.status = 'Completed' OR s.event_end_date < CURDATE())",
            \App\Services\SymposiumService::STATUS_PENDING_HOD       => "s.status = '" . \App\Services\SymposiumService::STATUS_PENDING_HOD . "'",
            \App\Services\SymposiumService::STATUS_PENDING_PRINCIPAL => "s.status = '" . \App\Services\SymposiumService::STATUS_PENDING_PRINCIPAL . "'",
            default               => '1 = 1',
        };
    }

    /**
     * Build a scoped query when department IDs need filtering via IN clause.
     * Necessary because PDO can't bind array params directly.
     *
     * @param array       $deptIds
     * @param string|null $search
     * @param string|null $academicYear
     * @param string|null $symposiumType
     * @param string|null $status
     * @param string|null $quickFilter
     * @param int|null    $scopeCreatedBy
     *
     * @return string
     */
    private function buildScopeQuery(
        array $deptIds,
        ?string $search,
        ?string $academicYear,
        ?string $symposiumType,
        ?string $status,
        ?string $quickFilter,
        ?int $scopeCreatedBy,
        ?string $filterDepartmentId = null
    ): string {
        $ids  = implode(',', array_map('intval', $deptIds));
        $sql  = "
            SELECT
                s.symposium_id, s.symposium_code, s.title, s.symposium_type,
                s.academic_year, s.description, s.brochure_path, s.circular_path,
                s.registration_start, s.registration_end,
                s.event_start_date, s.event_end_date,
                s.status, s.submitted_at, s.created_by, s.created_at, s.updated_at,
                u.full_name AS created_by_name,
                GROUP_CONCAT(d.department_name ORDER BY sd.id SEPARATOR ' & ') AS organizing_departments
            FROM symposiums s
            LEFT JOIN users u ON u.user_id = s.created_by
            LEFT JOIN symposium_departments sd ON sd.symposium_id = s.symposium_id
            LEFT JOIN departments d ON d.department_id = sd.department_id
            WHERE EXISTS (
                SELECT 1 FROM symposium_departments sd2
                WHERE sd2.symposium_id = s.symposium_id
                  AND sd2.department_id IN ($ids)
            )
        ";

        if ($filterDepartmentId !== null && trim($filterDepartmentId) !== '') {
            $fId = (int) trim($filterDepartmentId);
            $sql .= " AND EXISTS (
                SELECT 1 FROM symposium_departments sd3
                WHERE sd3.symposium_id = s.symposium_id
                  AND sd3.department_id = $fId
            )";
        }

        if ($scopeCreatedBy !== null) {
            $sql .= " AND s.created_by = $scopeCreatedBy";
        }
        if ($search !== null && trim($search) !== '') {
            $kw   = addslashes(trim($search));
            $sql .= " AND (LOWER(s.symposium_code) LIKE LOWER('%$kw%') OR LOWER(s.title) LIKE LOWER('%$kw%'))";
        }
        if ($academicYear !== null && trim($academicYear) !== '') {
            $sql .= ' AND s.academic_year = ' . (int) trim($academicYear);
        }
        if ($symposiumType !== null && trim($symposiumType) !== '') {
            $t    = addslashes(trim($symposiumType));
            $sql .= " AND s.symposium_type = '$t'";
        }
        if ($status !== null && trim($status) !== '') {
            $st   = addslashes(trim($status));
            $sql .= " AND s.status = '$st'";
        }
        if ($quickFilter !== null && trim($quickFilter) !== '') {
            $sql .= ' AND ' . $this->buildQuickFilterCondition(trim($quickFilter));
        }

        $sql .= ' GROUP BY s.symposium_id ORDER BY s.event_start_date DESC, s.symposium_code ASC';

        return $sql;
    }

    /**
     * Internal count helper.
     *
     * @param string   $extraCondition
     * @param array    $extraParams
     * @param int|null $scopeCreatedBy
     * @param array    $scopeDepartmentIds
     *
     * @return int
     */
    private function countByCondition(
        string $extraCondition,
        array $extraParams,
        ?int $scopeCreatedBy = null,
        array $scopeDepartmentIds = []
    ): int {
        $sql        = 'SELECT COUNT(DISTINCT s.symposium_id) FROM symposiums s';
        $conditions = [];
        $params     = $extraParams;

        if (!empty($scopeDepartmentIds)) {
            $ids  = implode(',', array_map('intval', $scopeDepartmentIds));
            $sql .= " LEFT JOIN symposium_departments sd ON sd.symposium_id = s.symposium_id";
            $conditions[] = "sd.department_id IN ($ids)";
        }

        if ($extraCondition !== '') {
            $conditions[] = $extraCondition;
        }

        if ($scopeCreatedBy !== null) {
            $conditions[] = 's.created_by = :scope_created_by';
            $params['scope_created_by'] = $scopeCreatedBy;
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($row['COUNT(DISTINCT s.symposium_id)'] ?? 0);
    }

    /**
     * Get all symposiums that are visible to students
     * (Status: Approved, Registration Open, Registration Closed, Completed)
     *
     * @return array
     */
    public function getApprovedForStudents(): array
    {
        $sql = "
            SELECT 
                symposium_id, symposium_code, title, symposium_type, academic_year, 
                description, brochure_path, circular_path, 
                registration_start, registration_end, event_start_date, event_end_date, status
            FROM symposiums
            WHERE status IN ('Approved', 'Scheduling Complete', 'Registration Open', 'Registration Closed', 'Completed')
            ORDER BY event_start_date DESC, title ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
