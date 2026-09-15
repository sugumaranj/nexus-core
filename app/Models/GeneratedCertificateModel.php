<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Database;
use PDO;

final class GeneratedCertificateModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findById(int $certId): array|false
    {
        $sql = "SELECT * FROM generated_certificates WHERE certificate_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $certId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByApplicationAndEvent(int $appId, int $eventId, int $studentId): array|false
    {
        $sql = "SELECT * FROM generated_certificates WHERE application_id = :app_id AND symposium_event_id = :event_id AND recipient_student_id = :student_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['app_id' => $appId, 'event_id' => $eventId, 'student_id' => $studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findActiveByApplicationAndEvent(int $appId, int $eventId, int $studentId): array|false
    {
        $sql = "
            SELECT * FROM generated_certificates 
            WHERE application_id = :app_id AND symposium_event_id = :event_id AND recipient_student_id = :student_id AND generation_status != 'Regenerated' 
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['app_id' => $appId, 'event_id' => $eventId, 'student_id' => $studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function insert(array $data): int
    {
        $sql = "
            INSERT INTO generated_certificates (
                symposium_event_id, application_id, recipient_student_id, result_id, template_id, version_id,
                recipient_name, rank_position, result_status, file_path, file_hash,
                verification_token, certificate_hash, canonical_snapshot,
                generation_status, generation_notes, generated_by, generated_at, previous_cert_id
            ) VALUES (
                :symposium_event_id, :application_id, :recipient_student_id, :result_id, :template_id, :version_id,
                :recipient_name, :rank_position, :result_status, :file_path, :file_hash,
                :verification_token, :certificate_hash, :canonical_snapshot,
                :generation_status, :generation_notes, :generated_by, NOW(), :previous_cert_id
            )
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'symposium_event_id'   => $data['symposium_event_id'] ?? null,
            'application_id'       => $data['application_id'] ?? null,
            'recipient_student_id' => $data['recipient_student_id'] ?? null,
            'result_id'            => $data['result_id'] ?? null,
            'template_id'          => $data['template_id'] ?? null,
            'version_id'           => $data['version_id'] ?? null,
            'recipient_name'       => $data['recipient_name'] ?? null,
            'rank_position'        => $data['rank_position'] ?? null,
            'result_status'        => $data['result_status'] ?? null,
            'file_path'            => $data['file_path'] ?? null,
            'file_hash'            => $data['file_hash'] ?? null,
            'verification_token'   => $data['verification_token'] ?? null,
            'certificate_hash'     => $data['certificate_hash'] ?? null,
            'canonical_snapshot'   => isset($data['canonical_snapshot'])
                ? (is_array($data['canonical_snapshot'])
                    ? json_encode($data['canonical_snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : $data['canonical_snapshot'])
                : null,
            'generation_status'    => $data['generation_status'] ?? 'Generated',
            'generation_notes'     => $data['generation_notes'] ?? null,
            'generated_by'         => $data['generated_by'] ?? null,
            'previous_cert_id'     => $data['previous_cert_id'] ?? null,
        ]);

        $certId = (int)$this->db->lastInsertId();

        // Generate and set the certificate number
        $certNumber = $this->generateCertificateNumber($certId);
        $updateSql  = "UPDATE generated_certificates SET certificate_number = :cert_number WHERE certificate_id = :id";
        $updateStmt = $this->db->prepare($updateSql);
        $updateStmt->execute(['cert_number' => $certNumber, 'id' => $certId]);

        return $certId;
    }
    
    private function generateCertificateNumber(int $certId): string
    {
        return 'CERT-' . date('Y') . '-' . str_pad((string)$certId, 6, '0', STR_PAD_LEFT);
    }

    public function updateStatus(int $certId, string $status, ?string $notes = null, ?string $fileHash = null): bool
    {
        $sql = "UPDATE generated_certificates SET generation_status = :status";
        $params = ['status' => $status, 'id' => $certId];
        
        if ($notes !== null) {
            $sql .= ", generation_notes = :notes";
            $params['notes'] = $notes;
        }
        
        if ($fileHash !== null) {
            $sql .= ", file_hash = :hash";
            $params['hash'] = $fileHash;
        }
        
        $sql .= " WHERE certificate_id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function getByEvent(int $symposiumEventId): array
    {
        $sql = "SELECT * FROM generated_certificates WHERE symposium_event_id = :event_id AND generation_status != 'Regenerated' ORDER BY generated_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['event_id' => $symposiumEventId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBySymposium(int $symposiumId): array
    {
        $sql = "
            SELECT c.* 
            FROM generated_certificates c
            JOIN symposium_events e ON c.symposium_event_id = e.symposium_event_id
            WHERE e.symposium_id = :symposium_id AND c.generation_status != 'Regenerated'
            ORDER BY c.generated_at DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['symposium_id' => $symposiumId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByEvent(int $symposiumEventId): int
    {
        $sql = "SELECT COUNT(*) FROM generated_certificates WHERE symposium_event_id = :event_id AND generation_status = 'Generated'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['event_id' => $symposiumEventId]);
        return (int)$stmt->fetchColumn();
    }

    public function countByTemplate(int $templateId): int
    {
        $sql = "SELECT COUNT(*) FROM generated_certificates WHERE template_id = :template_id AND generation_status = 'Generated'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['template_id' => $templateId]);
        return (int)$stmt->fetchColumn();
    }

    public function getByIds(array $certIds): array
    {
        if (empty($certIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($certIds), '?'));
        $sql = "SELECT * FROM generated_certificates WHERE certificate_id IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($certIds));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark an old certificate as Regenerated (superseded).
     * The new cert's previous_cert_id should be set via insert() or update().
     */
    public function markRegenerated(int $oldCertId): bool
    {
        $sql = "UPDATE generated_certificates SET generation_status = 'Regenerated' WHERE certificate_id = :old_id AND generation_status = 'Generated'";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['old_id' => $oldCertId]);
    }
    /**
     * Alias for insert() to match service call pattern.
     */
    public function create(array $data): int
    {
        return $this->insert($data);
    }

    /**
     * Update specific fields on a generated_certificates row.
     *
     * @param int   $certId
     * @param array $data  Associative array of column => value
     * @return bool
     */
    public function update(int $certId, array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        $allowedColumns = [
            'file_path', 'file_hash', 'generation_status', 'generation_notes',
            'previous_cert_id', 'regenerated_count',
            'verification_token', 'certificate_hash', 'canonical_snapshot',
        ];
        $sets = [];
        $params = ['id' => $certId];

        foreach ($data as $col => $val) {
            if (!in_array($col, $allowedColumns, true)) {
                continue;
            }
            $sets[] = "{$col} = :{$col}";
            $params[$col] = $val;
        }

        if (empty($sets)) {
            return true;
        }

        $sql = 'UPDATE generated_certificates SET ' . implode(', ', $sets) . ' WHERE certificate_id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Find a certificate by its opaque public verification token.
     * Used exclusively by the public verification endpoint.
     *
     * @param  string $token  64-char hex verification_token value.
     * @return array|false
     */
    public function findByVerificationToken(string $token): array|false
    {
        $sql  = 'SELECT * FROM generated_certificates WHERE verification_token = :token LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

