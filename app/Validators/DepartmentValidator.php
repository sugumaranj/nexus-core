<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : DepartmentValidator.php
 * Location    : app/Validators/
 * Description : Validates department data before it reaches
 *               the service and model layers.
 *
 * Responsibilities:
 * - Validate department name
 * - Validate department code
 * - Validate status
 * - Return validation errors
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Validators;

final class DepartmentValidator
{
    /**
     * ---------------------------------------------------------------------
     * Validate department data.
     *
     * @param array $data
     *
     * @return array<string, string>
     * ---------------------------------------------------------------------
     */
    public function validate(array $data): array
    {
        $errors = [];

        /*
        |--------------------------------------------------------------------------
        | Department Name
        |--------------------------------------------------------------------------
        */

        $departmentName = trim($data['department_name'] ?? '');

        if ($departmentName === '') {

            $errors['department_name'] =
                'Department name is required.';
        }
        elseif (mb_strlen($departmentName) > 150) {

            $errors['department_name'] =
                'Department name cannot exceed 150 characters.';
        }

        /*
        |--------------------------------------------------------------------------
        | Department Code
        |--------------------------------------------------------------------------
        */

        $departmentCode = strtoupper(
            trim($data['department_code'] ?? '')
        );

        if ($departmentCode === '') {

            $errors['department_code'] =
                'Department code is required.';
        }
        elseif (!preg_match('/^[A-Z0-9]{2,10}$/', $departmentCode)) {

            $errors['department_code'] =
                'Department code must contain only uppercase letters and numbers (2–10 characters).';
        }

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        $isActive = $data['is_active'] ?? null;

        if (!in_array((string)$isActive, ['0', '1'], true)) {
            $errors['is_active'] =
            'Please select a valid status.';
        }

        return $errors;
    }
}