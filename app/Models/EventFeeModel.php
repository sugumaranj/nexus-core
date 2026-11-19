<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Database;
use PDO;

/**
 * -------------------------------------------------------------------------
 * NexusCore — Event Fee Model
 * -------------------------------------------------------------------------
 * Manages event-level fee configurations.
 * Enforces server-side fee calculation based on approved event rules.
 * -------------------------------------------------------------------------
 */
final class EventFeeModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Retrieve fee configuration for a symposium event.
     *
     * @param int $symposiumEventId
     * @return array|false
     */
    public function findBySymposiumEventId(int $symposiumEventId): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM event_fees WHERE symposium_event_id = :id LIMIT 1");
        $stmt->execute(['id' => $symposiumEventId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Create or update fee configuration for a symposium event.
     *
     * @param int $symposiumEventId
     * @param array $data
     * @param int|null $userId
     * @return bool
     */
    public function saveFee(int $symposiumEventId, array $data, ?int $userId = null): bool
    {
        $feeType       = ($data['fee_type'] ?? 'Free') === 'Paid' ? 'Paid' : 'Free';
        $individualFee = (float)($data['individual_fee'] ?? 0.00);
        $teamFeeBasis  = ($data['team_fee_basis'] ?? 'Flat') === 'PerParticipant' ? 'PerParticipant' : 'Flat';
        $teamFee       = (float)($data['team_fee'] ?? 0.00);
        $currency      = strtoupper(trim((string)($data['currency'] ?? 'INR')));
        $isActive      = isset($data['is_active']) ? (int)$data['is_active'] : 1;

        $existing = $this->findBySymposiumEventId($symposiumEventId);

        if ($existing) {
            $sql = "
                UPDATE event_fees
                SET fee_type = :fee_type,
                    individual_fee = :individual_fee,
                    team_fee_basis = :team_fee_basis,
                    team_fee = :team_fee,
                    currency = :currency,
                    is_active = :is_active
                WHERE symposium_event_id = :symposium_event_id
            ";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'fee_type'          => $feeType,
                'individual_fee'    => $individualFee,
                'team_fee_basis'    => $teamFeeBasis,
                'team_fee'          => $teamFee,
                'currency'          => $currency,
                'is_active'         => $isActive,
                'symposium_event_id'=> $symposiumEventId
            ]);
        }

        $sql = "
            INSERT INTO event_fees (symposium_event_id, fee_type, individual_fee, team_fee_basis, team_fee, currency, is_active, created_by)
            VALUES (:symposium_event_id, :fee_type, :individual_fee, :team_fee_basis, :team_fee, :currency, :is_active, :created_by)
        ";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'symposium_event_id'=> $symposiumEventId,
            'fee_type'          => $feeType,
            'individual_fee'    => $individualFee,
            'team_fee_basis'    => $teamFeeBasis,
            'team_fee'          => $teamFee,
            'currency'          => $currency,
            'is_active'         => $isActive,
            'created_by'        => $userId
        ]);
    }

    /**
     * Authoritative server-side fee calculation.
     * Never trusts client-submitted amounts.
     *
     * @param int $symposiumEventId
     * @param string $applicationType 'Individual' or 'Team'
     * @param int $participantCount Number of participants in team (default 1)
     * @return array [
     *     'is_fee_required' => bool,
     *     'amount'          => float,
     *     'currency'        => string,
     *     'fee_type'        => string,
     *     'description'     => string
     * ]
     */
    public function calculateFee(int $symposiumEventId, string $applicationType, int $participantCount = 1): array
    {
        $feeConfig = $this->findBySymposiumEventId($symposiumEventId);

        if (!$feeConfig || $feeConfig['fee_type'] === 'Free' || (int)$feeConfig['is_active'] !== 1) {
            return [
                'is_fee_required' => false,
                'amount'          => 0.00,
                'currency'        => 'INR',
                'fee_type'        => 'Free',
                'description'     => 'Free Registration'
            ];
        }

        $currency = $feeConfig['currency'] ?: 'INR';

        if ($applicationType === 'Team') {
            if ($feeConfig['team_fee_basis'] === 'PerParticipant') {
                $count = max(1, $participantCount);
                $perPartFee = (float)$feeConfig['team_fee'];
                $total = round($perPartFee * $count, 2);
                return [
                    'is_fee_required' => $total > 0,
                    'amount'          => $total,
                    'currency'        => $currency,
                    'fee_type'        => 'Paid',
                    'description'     => "Team Registration ({$count} participants @ {$currency} {$perPartFee})"
                ];
            } else {
                // Flat team fee
                $flatAmount = round((float)$feeConfig['team_fee'], 2);
                return [
                    'is_fee_required' => $flatAmount > 0,
                    'amount'          => $flatAmount,
                    'currency'        => $currency,
                    'fee_type'        => 'Paid',
                    'description'     => "Team Registration (Flat fee)"
                ];
            }
        }

        // Individual registration
        $indivAmount = round((float)$feeConfig['individual_fee'], 2);
        return [
            'is_fee_required' => $indivAmount > 0,
            'amount'          => $indivAmount,
            'currency'        => $currency,
            'fee_type'        => 'Paid',
            'description'     => "Individual Participant Fee"
        ];
    }
}
