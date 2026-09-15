<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : UserService.php
 * Location    : app/Services/
 * Description : Business logic for User Management.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Validate business rules
 * • Prevent duplicate Employee IDs
 * • Prevent duplicate Email addresses
 * • Hash passwords
 * • Call UserModel
 *
 * NOTE
 * -------------------------------------------------------------------------
 * This class NEVER contains SQL.
 * SQL belongs only inside UserModel.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Models\UserModel;
use App\Models\AuditLogModel;
use App\Helpers\RoleHelper;
use App\Services\EmployeeIdService;
use App\Database\Database;
use PDO;
use Throwable;

final class UserService
{
    /**
     * User Model.
     *
     * @var UserModel
     */
    private UserModel $userModel;

    /**
     * Employee ID Service.
     *
     * @var EmployeeIdService
     */
    private EmployeeIdService $employeeIdService;

    /**
     * Audit Log Model.
     *
     * @var AuditLogModel
     */
    private AuditLogModel $auditLog;

    /**
     * PDO connection (for transactions).
     *
     * @var PDO
     */
    private PDO $db;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->userModel         = new UserModel();
        $this->employeeIdService = new EmployeeIdService();
        $this->auditLog          = new AuditLogModel();
        $this->db                = Database::getConnection();
    }

    /**
     * ---------------------------------------------------------------------
     * Retrieve all users.
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function getAllUsers(): array
    {
        return $this->userModel->getAll();
    }

    /**
     * ---------------------------------------------------------------------
     * Search and filter users.
     *
     * @param string $search
     * @param string $role
     * @param string $status
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function searchUsers(
        string $search = '',
        string $role = '',
        string $status = ''
    ): array {

        // Use RoleHelper as the single source of truth for all valid roles.
        $allowedRoles = RoleHelper::getAllRoles();

        $allowedStatus = [

            'Active',

            'Inactive',

            'Blocked'

        ];

        $roleFilter = in_array($role, $allowedRoles, true)
            ? $role
            : null;

        $statusFilter = in_array($status, $allowedStatus, true)
            ? $status
            : null;

        return $this->userModel->getAll(
            $search !== '' ? $search : null,
            $roleFilter,
            $statusFilter
        );
    }

    /**
     * ---------------------------------------------------------------------
     * Retrieve user by ID.
     *
     * @param int $userId
     *
     * @return array|false
     * ---------------------------------------------------------------------
     */
    public function getUserById(int $userId): array|false
    {
        return $this->userModel->findById($userId);
    }

    /**
     * ---------------------------------------------------------------------
     * Create User.
     *
     * @param array $data
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function createUser(array $data, array $files = []): array
    {
        /*
        |--------------------------------------------------------------------------
        | Normalize Input
        |--------------------------------------------------------------------------
        */

        $data['full_name'] = trim($data['full_name']);
        $data['email']     = strtolower(trim($data['email']));
        $data['phone']     = trim($data['phone'] ?? '');
        $role              = trim($data['role'] ?? '');

        /*
        |--------------------------------------------------------------------------
        | Validate Role (must exist before generating ID)
        |--------------------------------------------------------------------------
        */

        if (!RoleHelper::isValidRole($role)) {
            return [
                'success' => false,
                'message' => 'Invalid role selected.',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Email
        |--------------------------------------------------------------------------
        */

        if ($this->userModel->findByEmail($data['email'])) {
            return [
                'success' => false,
                'message' => 'Email address already exists.',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Phone
        |--------------------------------------------------------------------------
        */

        if ($data['phone'] !== '' && $this->userModel->findByPhone($data['phone'])) {
            return [
                'success' => false,
                'message' => 'Phone number already in use by another account.',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Upload Profile Photo
        |--------------------------------------------------------------------------
        */

        $profileUpload = $this->processFileUpload(
            $files,
            'profile_photo',
            'profile_',
            'uploads/profile_photos',
            ['jpg', 'jpeg', 'png', 'webp']
        );

        if ($profileUpload['error'] !== null) {
            return ['success' => false, 'message' => $profileUpload['error']];
        }

        $data['profile_photo'] = $profileUpload['path'];

        /*
        |--------------------------------------------------------------------------
        | Upload Signature
        |--------------------------------------------------------------------------
        */

        $signatureUpload = $this->processFileUpload(
            $files,
            'signature_path',
            'signature_',
            'uploads/signatures',
            ['jpg', 'jpeg', 'png']
        );

        if ($signatureUpload['error'] !== null) {
            return ['success' => false, 'message' => $signatureUpload['error']];
        }

        $data['signature_path'] = $signatureUpload['path'];

        /*
        |--------------------------------------------------------------------------
        | Hash Password
        |--------------------------------------------------------------------------
        */

        $data['password_hash'] = password_hash(
            $data['password'],
            PASSWORD_DEFAULT
        );

        unset($data['password'], $data['confirm_password']);

        /*
        |--------------------------------------------------------------------------
        | Generate Employee ID + Insert (inside a transaction)
        |--------------------------------------------------------------------------
        |
        | EmployeeIdService uses SELECT … FOR UPDATE to lock the rows, ensuring
        | no two concurrent requests can generate the same Employee ID.
        | The transaction is committed only after a successful INSERT.
        |--------------------------------------------------------------------------
        */

        $this->db->beginTransaction();

        try {

            // Atomically determine the next ID for this role.
            $generatedId = $this->employeeIdService->generate($role);

            // Guard against the (extremely unlikely) race that produces a dup.
            if ($this->userModel->findByEmployeeId($generatedId)) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Employee ID conflict detected. Please try again.',
                ];
            }

            $data['employee_id'] = $generatedId;

            $created = $this->userModel->create($data);

            if (!$created) {
                throw new \RuntimeException('Database INSERT failed.');
            }

            /*
            |----------------------------------------------------------------------
            | Audit Log — record who created which user
            |----------------------------------------------------------------------
            */

            $newUserId     = (int) $this->db->lastInsertId();
            $actorUserId   = (int) ($data['_created_by'] ?? 0); // set by controller
            $actorRole     = $data['_actor_role'] ?? 'Admin';

            $this->auditLog->log(
                'User',
                $newUserId,
                "Created user [{$generatedId}] ({$role}) by {$actorRole}",
                $actorUserId
            );

            $this->db->commit();

            return [
                'success'     => true,
                'message'     => "User created successfully. Employee ID: {$generatedId}",
                'employee_id' => $generatedId,
            ];

        } catch (Throwable $e) {

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return [
                'success' => false,
                'message' => 'Unable to create user. Please try again.',
            ];
        }
    }

    /**
     * ---------------------------------------------------------------------
     * Update User.
     *
     * @param int   $userId
     * @param array $data
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function updateUser(
        int $userId,
        array $data,
        array $files = []
    ): array {

        $existingUser = $this->userModel->findById($userId);

        if (!$existingUser) {

            return [

                'success' => false,

                'message' => 'User not found.'

            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Normalize Input
        |--------------------------------------------------------------------------
        */

        $data['full_name'] = trim(
            $data['full_name'] ?? ''
        );

        $data['email'] = strtolower(
            trim($data['email'] ?? '')
        );

        $data['phone'] = trim(
            $data['phone'] ?? ''
        );

        /*
        |--------------------------------------------------------------------------
        | Duplicate Email
        |--------------------------------------------------------------------------
        */

        if (
            $this->userModel->findByEmailExcept(
                $data['email'],
                $userId
            )
        ) {

            return [

                'success' => false,

                'message' => 'Email address already exists.'

            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Phone
        |--------------------------------------------------------------------------
        */

        if (
            $data['phone'] !== '' &&
            $this->userModel->findByPhoneExcept($data['phone'], $userId)
        ) {

            return [

                'success' => false,

                'message' => 'Phone number already in use by another account.'

            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Preserve Existing Uploads
        |--------------------------------------------------------------------------
        */

        $data['profile_photo'] = $existingUser['profile_photo'];

        $data['signature_path'] = $existingUser['signature_path'];

        /*
        |--------------------------------------------------------------------------
        | Handle Remove Flags
        |--------------------------------------------------------------------------
        | When the user checks the "Remove" checkbox we should store NULL
        | in the database and delete the old file after a successful update.
        */

        $deleteProfile = null;

        $deleteSignature = null;

        $existingProfileForProcess = $existingUser['profile_photo'];

        $existingSignatureForProcess = $existingUser['signature_path'];

        if (!empty($data['remove_profile_photo'])) {

            $existingProfileForProcess = null;

            $deleteProfile = $existingUser['profile_photo'];

        }

        if (!empty($data['remove_signature'])) {

            $existingSignatureForProcess = null;

            $deleteSignature = $existingUser['signature_path'];

        }

        /*
        |--------------------------------------------------------------------------
        | Upload Profile Photo
        |--------------------------------------------------------------------------
        */

        $profileUpload = $this->processFileUpload(
            $files,
            'profile_photo',
            'profile_',
            'uploads/profile_photos',
            [
                'jpg',

                'jpeg',

                'png',

                'webp'

            ],
            $existingProfileForProcess
        );

        if ($profileUpload['error'] !== null) {

            return [

                'success' => false,

                'message' => $profileUpload['error']

            ];
        }

        if ($profileUpload['path'] !== $existingUser['profile_photo']) {

            // Defer deletion until after successful DB update.
            $deleteProfile = $existingUser['profile_photo'];
        }

        $data['profile_photo'] = $profileUpload['path'];

        /*
        |--------------------------------------------------------------------------
        | Upload Signature
        |--------------------------------------------------------------------------
        */

        $signatureUpload = $this->processFileUpload(
            $files,
            'signature_path',
            'signature_',
            'uploads/signatures',
            [
                'jpg',

                'jpeg',

                'png'

            ],
            $existingSignatureForProcess
        );

        if ($signatureUpload['error'] !== null) {

            return [

                'success' => false,

                'message' => $signatureUpload['error']

            ];
        }

        if ($signatureUpload['path'] !== $existingUser['signature_path']) {

            // Defer deletion until after successful DB update.
            $deleteSignature = $existingUser['signature_path'];
        }

        $data['signature_path'] = $signatureUpload['path'];

        unset(
            $data['password'],
            $data['confirm_password'],
            $data['user_id']
        );

        /*
        |--------------------------------------------------------------------------
        | Save User
        |--------------------------------------------------------------------------
        */

        $updated = $this->userModel->update(
            $userId,
            $data
        );

        if (!$updated) {

            return [

                'success' => false,

                'message' => 'Unable to update user.'

            ];
        }

            /*
            |--------------------------------------------------------------------------
            | Delete Old Uploaded Files
            |--------------------------------------------------------------------------
            | Perform deletions only after the database update succeeded to avoid
            | losing files if the DB update fails.
            */

            if (!empty($deleteProfile)) {

                $this->deleteUploadedFile($deleteProfile);

            }

            if (!empty($deleteSignature)) {

                $this->deleteUploadedFile($deleteSignature);

            }

            return [

                'success' => true,

                'message' => 'User updated successfully.'

            ];
    }

    /**
     * ---------------------------------------------------------------------
     * Delete User.
     *
     * @param int $userId
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function deleteUser(int $userId): array
    {
        $existingUser = $this->userModel->findById($userId);

        if (!$existingUser) {

            return [

                'success' => false,

                'message' => 'User not found.'

            ];
        }

        $deleted = $this->userModel->delete(
            $userId
        );

        if (!$deleted) {

            return [

                'success' => false,

                'message' => 'Unable to delete user.'

            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Delete Uploaded Files
        |--------------------------------------------------------------------------
        */

        $this->deleteUploadedFile(
            $existingUser['profile_photo']
        );

        $this->deleteUploadedFile(
            $existingUser['signature_path']
        );

        return [

            'success' => true,

            'message' => 'User deleted successfully.'

        ];
    }

    /**
     * ---------------------------------------------------------------------
     * Change Account Status.
     *
     * @param int    $userId
     * @param string $status
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function updateStatus(
        int $userId,
        string $status
    ): array {

        $updated = $this->userModel->updateStatus(
            $userId,
            $status
        );

        if (!$updated) {

            return [

                'success' => false,

                'message' => 'Unable to update account status.'

            ];
        }

        return [

            'success' => true,

            'message' => 'Account status updated successfully.'

        ];
    }

    /**
     * ---------------------------------------------------------------------
     * Reset Password.
     *
     * @param int    $userId
     * @param string $password
     *
     * @return array
     * ---------------------------------------------------------------------
     */
    public function resetPassword(
        int $userId,
        string $password
    ): array {

        $updated = $this->userModel->resetPassword(

            $userId,

            password_hash(
                $password,
                PASSWORD_DEFAULT
            )

        );

        if (!$updated) {

            return [

                'success' => false,

                'message' => 'Unable to reset password.'

            ];
        }

        return [

            'success' => true,

            'message' => 'Password reset successfully.'

        ];
    }

    /**
     * ---------------------------------------------------------------------
     * Process a user file upload.
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

        if (
            !isset($files[$fieldName]) ||
            $files[$fieldName]['error'] === UPLOAD_ERR_NO_FILE
        ) {

            return [

                'path'  => $existingPath,

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

                'path'  => $existingPath,

                'error' => ucfirst(
                    str_replace('_', ' ', $fieldName)
                ) . ' must be a valid image file.'

            ];
        }

        return [

            'path'  => $uploadedPath,

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

        $extension = strtolower(
            pathinfo(
                $file['name'],
                PATHINFO_EXTENSION
            )
        );

        if (!in_array($extension, $allowedExtensions, true)) {

            return null;
        }

        if (@getimagesize($file['tmp_name']) === false) {

            return null;
        }

        $fileName =
            uniqid($prefix, true) .
            '.' .
            $extension;

        $uploadDir =
            dirname(__DIR__, 2)
            . '/public/'
            . $folder
            . '/';

        if (!is_dir($uploadDir)) {

            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . $fileName;

        if (
            move_uploaded_file(
                $file['tmp_name'],
                $destination
            )
        ) {

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
    private function deleteUploadedFile(
        ?string $relativePath
    ): void {

        if ($relativePath === null || $relativePath === '') {

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Skip Default Placeholder Images
        |--------------------------------------------------------------------------
        */

        if (
            !str_starts_with(
                $relativePath,
                'uploads/profile_photos/'
            )
            && !str_starts_with(
                $relativePath,
                'uploads/signatures/'
            )
        ) {

            return;
        }

        $fullPath =
            dirname(__DIR__, 2)
            . '/public/'
            . $relativePath;

        if (is_file($fullPath)) {

            unlink($fullPath);
        }
    }
}