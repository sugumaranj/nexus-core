<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : DepartmentModel.php
 * Location    : app/Models/
 * Description : Handles all Department database operations.
 *
 * Responsibilities
 * ----------------
 * • Retrieve departments
 * • Retrieve one department
 * • Check duplicate department
 * • Insert department
 * • Update department
 * • Delete department
 *
 * NOTE:
 * -----
 * This class ONLY communicates with the database.
 * Business rules belong in DepartmentService.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use PDO;

final class DepartmentModel extends BaseModel
{
    /**
     * ---------------------------------------------------------------------
     * Get all departments.
     *
     * The department list page shows the database ID column, so records must
     * be returned by ID order. This keeps the visible rows arranged as
     * 1, 2, 3... instead of alphabetical order such as CA before CS.
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getAll(): array
    {
        $sql = "
            SELECT
                department_id,
                department_code,
                department_name,
                short_name,
                is_active,
                created_at,
                updated_at
            FROM departments

            -- Keep the Department Management table in natural ID order.
            ORDER BY department_id ASC
        ";

        $statement = $this->db->prepare($sql);

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * ---------------------------------------------------------------------
     * Find department by ID.
     *
     * @param int $departmentId
     *
     * @return array|false
     * ---------------------------------------------------------------------
     */
    public function findById(int $departmentId): array|false
    {
        $sql = "
            SELECT *
            FROM departments
            WHERE department_id = :department_id
        ";

        $statement = $this->db->prepare($sql);

        $statement->bindValue(
            ':department_id',
            $departmentId,
            PDO::PARAM_INT
        );

        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ---------------------------------------------------------------------
     * Find department using department code.
     *
     * @param string $departmentCode
     *
     * @return array|false
     * ---------------------------------------------------------------------
     */
    public function findByCode(string $departmentCode): array|false
    {
        $sql = "
            SELECT *
            FROM departments
            WHERE department_code = :department_code
        ";

        $statement = $this->db->prepare($sql);

        $statement->bindValue(
            ':department_code',
            strtoupper($departmentCode)
        );

        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * ---------------------------------------------------------------------
     * Create Department.
     *
     * @param array $data
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO departments
            (
                department_code,
                department_name,
                short_name,
                is_active
            )
            VALUES
            (
                :department_code,
                :department_name,
                :short_name,
                :is_active
            )
        ";

        $statement = $this->db->prepare($sql);

        return $statement->execute([

            ':department_code' => strtoupper($data['department_code']),

            ':department_name' => trim($data['department_name']),

            ':short_name' => trim($data['short_name']),

            ':is_active' => $data['is_active']

        ]);
    }

    /**
     * ---------------------------------------------------------------------
     * Update Department.
     *
     * @param int   $departmentId
     * @param array $data
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function update(
        int $departmentId,
        array $data
    ): bool {

        $sql = "
            UPDATE departments
            SET

                department_code = :department_code,

                department_name = :department_name,

                short_name = :short_name,

                is_active = :is_active

            WHERE department_id = :department_id
        ";

        $statement = $this->db->prepare($sql);

        return $statement->execute([

            ':department_code' => strtoupper($data['department_code']),

            ':department_name' => trim($data['department_name']),

            ':short_name' => trim($data['short_name']),

            ':is_active' => $data['is_active'],

            ':department_id' => $departmentId

        ]);
    }

    /**
     * ---------------------------------------------------------------------
     * Delete Department.
     *
     * @param int $departmentId
     *
     * @return bool
     * ---------------------------------------------------------------------
     */
    public function delete(int $departmentId): bool
    {
        $sql = "
            DELETE FROM departments
            WHERE department_id = :department_id
        ";

        $statement = $this->db->prepare($sql);

        return $statement->execute([

            ':department_id' => $departmentId

        ]);
    }
    /**
     * Find a department by name.
    */
    public function findByName(string $departmentName): array|false
    {
        $sql = "
           SELECT *
          FROM departments
          WHERE department_name = :department_name
           LIMIT 1
        ";

        $statement = $this->db->prepare($sql);

        $statement->execute([
            ':department_name' => trim($departmentName)
        ]);

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
    * Check whether a department code already exists.
    */
    public function existsCode(string $departmentCode): bool
    {
        return $this->findByCode($departmentCode) !== false;
    }
    /**
    * Check whether a department name already exists.
    */
    public function existsName(string $departmentName): bool
    {
        return $this->findByName($departmentName) !== false;
    }
}
