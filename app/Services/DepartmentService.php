<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : DepartmentService.php
 * Location    : app/Services/
 * Description : Business logic for Department Management.
 *
 * Responsibilities
 * ----------------
 * • Validate business rules
 * • Prevent duplicate department codes
 * • Call DepartmentModel
 * • Return meaningful responses
 *
 * NOTE
 * ----
 * This class DOES NOT contain SQL.
 * SQL belongs only inside DepartmentModel.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Models\DepartmentModel;

final class DepartmentService
{
    /**
     * Department Model Instance.
     *
     * @var DepartmentModel
     */
    private DepartmentModel $departmentModel;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->departmentModel = new DepartmentModel();
    }

    /**
     * ---------------------------------------------------------------------
     * Get all departments.
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getAllDepartments(): array
    {
        return $this->departmentModel->getAll();
    }

    /**
     * ---------------------------------------------------------------------
     * Get department by ID.
     *
     * @param int $departmentId
     *
     * @return array|false
     * ---------------------------------------------------------------------
     */
    public function getDepartmentById(int $departmentId): array|false
    {
        return $this->departmentModel->findById($departmentId);
    }

    /**
     * ---------------------------------------------------------------------
     * Create a new department.
     *
     * Business Rules:
     * • Department code must be unique.
     * • Department name must be unique.
     *
     * @param array $data
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function createDepartment(array $data): array
    {
        /*
        |--------------------------------------------------------------------------
        | Convert Department Code to Uppercase
        |--------------------------------------------------------------------------
        */

        $data['department_code'] = strtoupper(
            trim($data['department_code'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | Remove Extra Spaces
        |--------------------------------------------------------------------------
        */

        $data['department_name'] = trim(
            $data['department_name'] ?? ''
        );

        $data['short_name'] = trim(
            $data['short_name'] ?? ''
        );

        /*
        |--------------------------------------------------------------------------
        | Duplicate Department Code
        |--------------------------------------------------------------------------
        */

        if ($this->departmentModel->existsCode($data['department_code'])) {
            return [

                'success' => false,

                'message' => 'Department code already exists.'

            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Department Name
        |--------------------------------------------------------------------------
        */

        foreach ($this->departmentModel->getAll() as $department) {

            if (
                strcasecmp(
                    $department['department_name'],
                    $data['department_name']
                ) === 0
            ) {

                return [

                    'success' => false,

                    'message' => 'Department name already exists.'

                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Save Department
        |--------------------------------------------------------------------------
        */

        $created = $this->departmentModel->create($data);

        if (!$created) {

            return [

                'success' => false,

                'message' => 'Unable to create department.'

            ];
        }

        return [

            'success' => true,

            'message' => 'Department created successfully.'

        ];
    }

    /**
     * ---------------------------------------------------------------------
     * Update Department.
     *
     * @param int   $departmentId
     * @param array $data
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function updateDepartment(
        int $departmentId,
        array $data
    ): array {
        $data['department_name'] = trim($data['department_name'] ?? '');
        $data['short_name']      = trim($data['short_name'] ?? '');

        // Duplicate code check
        if (!empty($data['department_code']) && $this->departmentModel->existsCode($data['department_code'])) {
            $existing = $this->departmentModel->findByCode($data['department_code']);
            if ($existing && (int)$existing['department_id'] !== $departmentId) {
                return [
                    'success' => false,
                    'message' => 'Department code already exists.'
                ];
            }
        }

        // Duplicate name check
        if (!empty($data['department_name'])) {
            foreach ($this->departmentModel->getAll() as $department) {
                if (
                    (int)$department['department_id'] !== $departmentId &&
                    strcasecmp($department['department_name'], $data['department_name']) === 0
                ) {
                    return [
                        'success' => false,
                        'message' => 'Department name already exists.'
                    ];
                }
            }
        }

        $updated = $this->departmentModel->update(
            $departmentId,
            $data
        );

        if (!$updated) {
            return [
                'success' => false,
                'message' => 'Unable to update department.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Department updated successfully.'
        ];
    }

    /**
     * ---------------------------------------------------------------------
     * Delete Department.
     *
     * @param int $departmentId
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function deleteDepartment(int $departmentId): array
    {
        $deleted = $this->departmentModel->delete(
            $departmentId
        );

        if (!$deleted) {

            return [

                'success' => false,

                'message' => 'Unable to delete department.'

            ];
        }

        return [

            'success' => true,

            'message' => 'Department deleted successfully.'

        ];
    }
}
