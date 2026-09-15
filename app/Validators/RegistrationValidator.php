<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : RegistrationValidator.php
 * Location    : app/Validators/
 * Description : Input validation for the Registration Module.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Validate student register numbers for adding team members
 * • Validate remarks for approval/rejection
 * • Validate withdrawal requests
 *
 * NOTE
 * -------------------------------------------------------------------------
 * This class performs input validation ONLY.
 * It does NOT communicate with the database.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Validators;

final class RegistrationValidator
{
    /**
     * Validate adding a team member.
     *
     * @param array $data Input data containing register_number
     * @return array  ['valid' => bool, 'errors' => array]
     */
    public function validateAddTeamMember(array $data): array
    {
        $errors = [];

        $registerNumber = trim((string) ($data['register_number'] ?? ''));

        if ($registerNumber === '') {
            $errors['register_number'] = 'Register number is required.';
        } elseif (!preg_match('/^[A-Z0-9]+$/i', $registerNumber)) {
            $errors['register_number'] = 'Register number format is invalid.';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate approval/rejection action by staff.
     *
     * @param array $data Input data containing remarks
     * @param bool  $isRejection True if validating a rejection action
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateStaffAction(array $data, bool $isRejection = false): array
    {
        $errors = [];

        $remarks = trim((string) ($data['remarks'] ?? ''));

        if ($isRejection && $remarks === '') {
            $errors['remarks'] = 'Remarks are required when rejecting an application.';
        }

        if (strlen($remarks) > 255) {
            $errors['remarks'] = 'Remarks cannot exceed 255 characters.';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate a withdrawal request.
     *
     * @param array $data Input data containing application_id
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateWithdrawal(array $data): array
    {
        $errors = [];

        $applicationId = (int) ($data['application_id'] ?? 0);

        if ($applicationId <= 0) {
            $errors['application_id'] = 'Invalid application ID.';
        }

        return [
            'valid'  => empty($errors),
            'errors' => $errors,
        ];
    }
}
