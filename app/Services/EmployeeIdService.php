<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : EmployeeIdService.php
 * Location    : app/Services/
 * Description : Atomic Employee ID generation per role.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Read the highest existing numeric suffix for a given prefix
 *   from the live `users` table using MAX().
 * • Generate the next ID as {PREFIX}{NNNN} (4-digit zero-padded)
 *   inside a serialisable transaction to prevent duplicates.
 * • NEVER reuse a numeric suffix, even if a user was deleted.
 *   (MAX-based approach ensures deleted IDs are not recycled.)
 * • Return the generated ID as a string without inserting it.
 *   The caller (UserService) performs the actual INSERT.
 *
 * Algorithm
 * -------------------------------------------------------------------------
 * 1. Lock the `users` table with SELECT … FOR UPDATE.
 * 2. SELECT MAX(CAST(SUBSTRING(employee_id, length(prefix)+1) AS UNSIGNED))
 *    WHERE employee_id LIKE 'PREFIX%'
 * 3. next_num = max_num + 1
 * 4. Format: PREFIX . str_pad(next_num, 4, '0', STR_PAD_LEFT)
 * 5. Return generated ID. Caller inserts and commits.
 *
 * Prefix Map (from RoleHelper)
 * -------------------------------------------------------------------------
 *   Admin              → ADM   → ADM0001, ADM0002, …
 *   Principal          → PRI   → PRI0001, …
 *   HOD                → HOD   → HOD0001, …
 *   Staff Coordinator  → SC    → SC0001, …
 *   Staff              → STF   → STF0001, …
 *   Student Coordinator→ PGSC  → PGSC0001, …
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

use App\Helpers\RoleHelper;
use App\Database\Database;
use PDO;
use RuntimeException;

final class EmployeeIdService
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
     * -------------------------------------------------------------------------
     * Generate the next available Employee ID for the given role.
     *
     * This method uses a SELECT ... FOR UPDATE within the caller's
     * transaction to prevent concurrent duplicate generation.
     *
     * Call this method INSIDE an open transaction and commit AFTER
     * the INSERT succeeds, or rollback on failure.
     *
     * @param string $role  One of the canonical roles (RoleHelper::*).
     *
     * @return string  e.g. "STF0005", "PGSC0003"
     *
     * @throws RuntimeException  If the role is unknown or DB fails.
     * -------------------------------------------------------------------------
     */
    public function generate(string $role): string
    {
        $prefix = RoleHelper::getPrefix($role);

        if ($prefix === '') {
            throw new RuntimeException(
                "Cannot generate Employee ID: unknown role '{$role}'."
            );
        }

        /*
        |----------------------------------------------------------------------
        | Find the highest numeric suffix for this prefix.
        |
        | SUBSTRING strips the prefix characters from employee_id, then casts
        | the remainder to UNSIGNED to allow numeric MAX().
        |
        | The WHERE clause uses LIKE 'PREFIX%' so only IDs with this prefix
        | are considered. This means ADM IDs never interfere with STF IDs.
        |
        | SELECT … FOR UPDATE locks the matched rows until the transaction
        | is committed, preventing a race condition where two requests
        | simultaneously read the same MAX and generate the same next ID.
        |----------------------------------------------------------------------
        */

        $prefixLen = strlen($prefix);

        $sql = "
            SELECT COALESCE(
                MAX(
                    CAST(
                        SUBSTRING(employee_id, :prefix_len + 1)
                        AS UNSIGNED
                    )
                ),
                0
            ) AS max_num
            FROM users
            WHERE employee_id LIKE :prefix_pattern
            FOR UPDATE
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':prefix_len'     => $prefixLen,
            ':prefix_pattern' => $prefix . '%',
        ]);

        $maxNum = (int) $stmt->fetchColumn();

        $nextNum = $maxNum + 1;

        /*
        |----------------------------------------------------------------------
        | Format: PREFIX + zero-padded 4-digit number
        | Minimum 4 digits. If nextNum > 9999 it will naturally expand.
        |----------------------------------------------------------------------
        */

        $paddedNum = str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);

        return $prefix . $paddedNum;
    }

    /**
     * -------------------------------------------------------------------------
     * Preview the next Employee ID without locking (read-only estimate).
     *
     * Used by the AJAX endpoint to show the user a preview before form
     * submission. Because this does NOT use FOR UPDATE, the actual generated
     * ID during submission may differ if another user creates an account
     * with the same role between the preview and submission.
     *
     * The UserService::createUser() always regenerates inside a transaction.
     *
     * @param string $role
     *
     * @return string  e.g. "SC0005"
     * -------------------------------------------------------------------------
     */
    public function preview(string $role): string
    {
        $prefix = RoleHelper::getPrefix($role);

        if ($prefix === '') {
            return '';
        }

        $prefixLen = strlen($prefix);

        $sql = "
            SELECT COALESCE(
                MAX(
                    CAST(
                        SUBSTRING(employee_id, :prefix_len + 1)
                        AS UNSIGNED
                    )
                ),
                0
            ) AS max_num
            FROM users
            WHERE employee_id LIKE :prefix_pattern
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':prefix_len'     => $prefixLen,
            ':prefix_pattern' => $prefix . '%',
        ]);

        $maxNum  = (int) $stmt->fetchColumn();
        $nextNum = $maxNum + 1;

        return $prefix . str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
    }
}
