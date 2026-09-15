<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Database;
use PDO;

final class CertificateTemplateModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findById(int $id): array|false
    {
        $sql = "
            SELECT t.*, 
                   (SELECT COUNT(*) FROM certificate_template_versions v WHERE v.certificate_template_id = t.certificate_template_id) as version_count
            FROM certificate_templates t
            WHERE t.certificate_template_id = :id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll(bool $activeOnly = false, bool $includeArchived = false): array
    {
        $sql = "
            SELECT t.*, v.version_id, v.version_number, v.field_config, v.locked
            FROM certificate_templates t
            LEFT JOIN certificate_template_versions v ON v.certificate_template_id = t.certificate_template_id 
                AND v.version_number = (
                    SELECT MAX(version_number) 
                    FROM certificate_template_versions 
                    WHERE certificate_template_id = t.certificate_template_id
                )
            WHERE 1=1
        ";
        
        if (!$includeArchived) {
            $sql .= " AND t.is_archived = 0";
        }
        
        if ($activeOnly) {
            $sql .= " AND t.is_active = 1";
        }
        $sql .= " ORDER BY t.template_name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO certificate_templates (
                template_name, description, file_path, file_hash, 
                page_width_pt, page_height_pt, page_orientation, 
                rank_display_mode, team_cert_mode, is_active, 
                is_archived, created_by, created_at, updated_at
            ) VALUES (
                :template_name, :description, :file_path, :file_hash,
                :page_width_pt, :page_height_pt, :page_orientation,
                :rank_display_mode, :team_cert_mode, :is_active,
                0, :created_by, NOW(), NOW()
            )
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'template_name' => $data['template_name'],
            'description' => $data['description'] ?? null,
            'file_path' => $data['file_path'] ?? null,
            'file_hash' => $data['file_hash'] ?? null,
            'page_width_pt' => $data['page_width_pt'] ?? null,
            'page_height_pt' => $data['page_height_pt'] ?? null,
            'page_orientation' => $data['page_orientation'] ?? null,
            'rank_display_mode' => $data['rank_display_mode'] ?? 'none',
            'team_cert_mode' => $data['team_cert_mode'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'created_by' => $data['created_by'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = "
            UPDATE certificate_templates
            SET template_name = :template_name,
                description = :description,
                rank_display_mode = :rank_display_mode,
                team_cert_mode = :team_cert_mode,
                updated_at = NOW()
            WHERE certificate_template_id = :id
        ";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'template_name' => $data['template_name'],
            'description' => $data['description'] ?? null,
            'rank_display_mode' => $data['rank_display_mode'] ?? 'none',
            'team_cert_mode' => $data['team_cert_mode'] ?? null,
            'id' => $id
        ]);
    }

    public function setActive(int $id, bool $active): bool
    {
        $sql = "UPDATE certificate_templates SET is_active = :active, updated_at = NOW() WHERE certificate_template_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }

    public function archive(int $id): bool
    {
        $sql = "UPDATE certificate_templates SET is_archived = 1, updated_at = NOW() WHERE certificate_template_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function unarchive(int $id): bool
    {
        $sql = "UPDATE certificate_templates SET is_archived = 0, updated_at = NOW() WHERE certificate_template_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM certificate_template_versions WHERE certificate_template_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);

        $sql = "DELETE FROM certificate_templates WHERE certificate_template_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    public function hasGeneratedCertificates(int $templateId): bool
    {
        $sql = "SELECT 1 FROM generated_certificates WHERE template_id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $templateId]);
        return (bool)$stmt->fetchColumn();
    }

    public function deleteVersion(int $versionId): bool
    {
        $sql = "DELETE FROM certificate_template_versions WHERE version_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $versionId]);
    }

    public function createVersion(int $templateId, array $fieldConfig, int $userId): int
    {
        $sql = "SELECT COALESCE(MAX(version_number), 0) + 1 FROM certificate_template_versions WHERE certificate_template_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $templateId]);
        $versionNumber = (int)$stmt->fetchColumn();

        $sql = "
            INSERT INTO certificate_template_versions (
                certificate_template_id, version_number, field_config, locked, created_by, created_at
            ) VALUES (
                :template_id, :version_number, :field_config, 0, :created_by, NOW()
            )
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'template_id' => $templateId,
            'version_number' => $versionNumber,
            'field_config' => json_encode($fieldConfig),
            'created_by' => $userId
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getActiveVersion(int $templateId): array|false
    {
        $sql = "
            SELECT * FROM certificate_template_versions 
            WHERE certificate_template_id = :id 
            ORDER BY locked ASC, version_number DESC 
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $templateId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getVersionById(int $versionId): array|false
    {
        $sql = "SELECT * FROM certificate_template_versions WHERE version_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $versionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function lockVersion(int $versionId): bool
    {
        $sql = "UPDATE certificate_template_versions SET locked = 1, locked_at = NOW() WHERE version_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $versionId]);
    }

    public function getVersionsForTemplate(int $templateId): array
    {
        $sql = "SELECT * FROM certificate_template_versions WHERE certificate_template_id = :id ORDER BY version_number DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEventConfig(int $symposiumEventId): array|false
    {
        $sql = "SELECT * FROM certificate_event_config WHERE symposium_event_id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $symposiumEventId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function setEventConfig(int $symposiumEventId, int $templateId, int $userId): bool
    {
        $sql = "
            INSERT INTO certificate_event_config (symposium_event_id, template_id, created_by, created_at)
            VALUES (:event_id, :template_id, :created_by, NOW())
            ON DUPLICATE KEY UPDATE template_id = :template_id
        ";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'event_id' => $symposiumEventId,
            'template_id' => $templateId,
            'created_by' => $userId
        ]);
    }

    public function removeEventConfig(int $symposiumEventId): bool
    {
        $sql = "DELETE FROM certificate_event_config WHERE symposium_event_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $symposiumEventId]);
    }

    public function getSystemDefaultTemplate(): array|false
    {
        $sql = "
            SELECT * FROM certificate_templates 
            WHERE is_active = 1 AND is_archived = 0 
            ORDER BY updated_at DESC LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
