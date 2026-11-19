<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Database;
use PDO;

/**
 * -------------------------------------------------------------------------
 * NexusCore — College Model
 * -------------------------------------------------------------------------
 * Handles operations on the Colleges master table.
 * Supports active college listings, search, verification workflow,
 * and retrieval of the canonical host institution.
 * -------------------------------------------------------------------------
 */
final class CollegeModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Retrieve all active colleges (optionally filtered by verification status).
     *
     * @param bool $onlyVerified
     * @return array
     */
    public function getAllActive(bool $onlyVerified = true): array
    {
        $sql = "
            SELECT college_id, college_name, college_code, city, state, is_verified, is_host
            FROM colleges
            WHERE is_active = 1
        ";

        if ($onlyVerified) {
            $sql .= " AND is_verified = 1";
        }

        $sql .= " ORDER BY is_host DESC, college_name ASC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Search colleges by name or code.
     *
     * @param string $keyword
     * @param bool $onlyVerified
     * @param int $limit
     * @return array
     */
    public function search(string $keyword, bool $onlyVerified = true, int $limit = 20): array
    {
        $sql = "
            SELECT college_id, college_name, college_code, city, state, is_verified, is_host
            FROM colleges
            WHERE is_active = 1
              AND (college_name LIKE :keyword OR college_code LIKE :keywordCode)
        ";

        if ($onlyVerified) {
            $sql .= " AND is_verified = 1";
        }

        $sql .= " ORDER BY is_host DESC, college_name ASC LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':keyword', '%' . trim($keyword) . '%');
        $stmt->bindValue(':keywordCode', '%' . trim($keyword) . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Find college by ID.
     *
     * @param int $collegeId
     * @return array|false
     */
    public function findById(int $collegeId): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM colleges WHERE college_id = :id LIMIT 1");
        $stmt->execute(['id' => $collegeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find college by exact name (case-insensitive).
     *
     * @param string $collegeName
     * @return array|false
     */
    public function findByName(string $collegeName): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM colleges WHERE LOWER(TRIM(college_name)) = LOWER(TRIM(:name)) LIMIT 1");
        $stmt->execute(['name' => $collegeName]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve the canonical Host College record.
     *
     * @return array|false
     */
    public function getHostCollege(): array|false
    {
        $stmt = $this->db->query("SELECT * FROM colleges WHERE is_host = 1 LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new college record.
     *
     * @param array $data
     * @return int College ID on success, 0 on failure
     */
    public function create(array $data): int
    {
        $sql = "
            INSERT INTO colleges (college_name, college_code, city, state, is_active, is_verified, is_host)
            VALUES (:name, :code, :city, :state, :is_active, :is_verified, :is_host)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'name'        => trim((string)($data['college_name'] ?? '')),
            'code'        => !empty($data['college_code']) ? strtoupper(trim((string)$data['college_code'])) : null,
            'city'        => !empty($data['city']) ? trim((string)$data['city']) : null,
            'state'       => !empty($data['state']) ? trim((string)$data['state']) : 'Tamil Nadu',
            'is_active'   => isset($data['is_active']) ? (int)$data['is_active'] : 1,
            'is_verified' => isset($data['is_verified']) ? (int)$data['is_verified'] : 1,
            'is_host'     => isset($data['is_host']) ? (int)$data['is_host'] : 0,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Submit an unverified college request (student self-registration).
     * Enters an explicit review state for admin verification.
     *
     * @param string $collegeName
     * @param string|null $city
     * @param string $state
     * @return int College ID
     */
    public function requestCollege(string $collegeName, ?string $city = null, string $state = 'Tamil Nadu'): int
    {
        $existing = $this->findByName($collegeName);
        if ($existing) {
            return (int)$existing['college_id'];
        }

        return $this->create([
            'college_name' => $collegeName,
            'city'         => $city,
            'state'        => $state,
            'is_active'    => 1,
            'is_verified'  => 0, // Unverified until approved by administrator
            'is_host'      => 0,
        ]);
    }

    /**
     * Find or create college safely.
     *
     * @param string $collegeName
     * @param string|null $city
     * @return array ['college_id' => int, 'college_name' => string, 'is_verified' => int]
     */
    public function findOrCreate(string $collegeName, ?string $city = null): array
    {
        $cleanName = trim($collegeName);
        $existing = $this->findByName($cleanName);
        if ($existing) {
            return [
                'college_id'  => (int)$existing['college_id'],
                'college_name'=> (string)$existing['college_name'],
                'is_verified' => (int)$existing['is_verified'],
            ];
        }

        $id = $this->requestCollege($cleanName, $city);
        return [
            'college_id'  => $id,
            'college_name'=> $cleanName,
            'is_verified' => 0,
        ];
    }

    /**
     * Verify or approve an unverified college (Admin action).
     *
     * @param int $collegeId
     * @return bool
     */
    public function verifyCollege(int $collegeId): bool
    {
        $stmt = $this->db->prepare("UPDATE colleges SET is_verified = 1 WHERE college_id = :id");
        return $stmt->execute(['id' => $collegeId]);
    }
}
