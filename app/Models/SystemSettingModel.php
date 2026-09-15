<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : SystemSettingModel.php
 * Location    : app/Models/
 * Description : Manages system settings stored in the database.
 * -------------------------------------------------------------------------
 */

namespace App\Models;

use PDO;

class SystemSettingModel extends BaseModel
{
    /**
     * Get a setting value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getValue(string $key, mixed $default = null): mixed
    {
        $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :key LIMIT 1");
        $stmt->execute(['key' => $key]);
        $result = $stmt->fetchColumn();

        return $result !== false ? $result : $default;
    }

    /**
     * Update or create a setting value.
     *
     * @param string $key
     * @param string $value
     * @param string|null $description
     * @param int|null $userId
     * @return bool
     */
    public function updateValue(string $key, string $value, ?string $description = null, ?int $userId = null): bool
    {
        // Check if exists
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = :key");
        $stmt->execute(['key' => $key]);
        
        if ((int) $stmt->fetchColumn() > 0) {
            $sql = "UPDATE system_settings SET setting_value = :val, updated_by = :uid";
            $params = ['val' => $value, 'uid' => $userId, 'key' => $key];
            if ($description !== null) {
                $sql .= ", description = :desc";
                $params['desc'] = $description;
            }
            $sql .= " WHERE setting_key = :key";
            
            return $this->db->prepare($sql)->execute($params);
        }

        $sql = "INSERT INTO system_settings (setting_key, setting_value, description, updated_by) VALUES (:key, :val, :desc, :uid)";
        return $this->db->prepare($sql)->execute([
            'key' => $key,
            'val' => $value,
            'desc' => $description,
            'uid' => $userId
        ]);
    }
}
