<?php

declare(strict_types=1);

namespace App\Models;

use App\Database\Database;
use PDO;

/**
 * -------------------------------------------------------------------------
 * NexusCore — Payment Transaction Model
 * -------------------------------------------------------------------------
 * Manages payment transactions for event registrations.
 * Tracks payment references, idempotency, gateway identifiers, and timestamps.
 * -------------------------------------------------------------------------
 */
final class PaymentTransactionModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Generate unique payment reference: PAY-YYYYMMDD-XXXXXX
     *
     * @return string
     */
    public function generateReference(): string
    {
        return sprintf('PAY-%s-%06d', date('Ymd'), random_int(100000, 999999));
    }

    /**
     * Create a new payment transaction.
     *
     * @param array $data
     * @return int
     */
    public function create(array $data): int
    {
        $ref = $data['payment_reference'] ?? $this->generateReference();

        $sql = "
            INSERT INTO payment_transactions (
                payment_reference, application_id, symposium_event_id, student_id,
                amount, currency, gateway_provider, gateway_order_id,
                payment_status, idempotency_key
            ) VALUES (
                :payment_reference, :application_id, :symposium_event_id, :student_id,
                :amount, :currency, :gateway_provider, :gateway_order_id,
                :payment_status, :idempotency_key
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'payment_reference' => $ref,
            'application_id'    => (int)$data['application_id'],
            'symposium_event_id'=> (int)$data['symposium_event_id'],
            'student_id'        => (int)$data['student_id'],
            'amount'            => (float)$data['amount'],
            'currency'          => $data['currency'] ?? 'INR',
            'gateway_provider'  => $data['gateway_provider'] ?? 'mock',
            'gateway_order_id'  => $data['gateway_order_id'] ?? null,
            'payment_status'    => $data['payment_status'] ?? 'Pending',
            'idempotency_key'   => $data['idempotency_key'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Find transaction by ID.
     *
     * @param int $paymentId
     * @return array|false
     */
    public function findById(int $paymentId): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM payment_transactions WHERE payment_id = :id LIMIT 1");
        $stmt->execute(['id' => $paymentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find transaction by payment reference.
     *
     * @param string $reference
     * @return array|false
     */
    public function findByReference(string $reference): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM payment_transactions WHERE payment_reference = :ref LIMIT 1");
        $stmt->execute(['ref' => trim($reference)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find transaction by idempotency key.
     *
     * @param string $idempotencyKey
     * @return array|false
     */
    public function findByIdempotencyKey(string $idempotencyKey): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM payment_transactions WHERE idempotency_key = :key LIMIT 1");
        $stmt->execute(['key' => trim($idempotencyKey)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Find latest pending or completed payment for an application.
     *
     * @param int $applicationId
     * @return array|false
     */
    public function findByApplicationId(int $applicationId): array|false
    {
        $sql = "
            SELECT * FROM payment_transactions 
            WHERE application_id = :app_id 
            ORDER BY payment_id DESC LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['app_id' => $applicationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if an application has a verified completed payment.
     *
     * @param int $applicationId
     * @return bool
     */
    public function hasCompletedPayment(int $applicationId): bool
    {
        $sql = "
            SELECT 1 FROM payment_transactions 
            WHERE application_id = :app_id AND payment_status = 'Completed' 
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['app_id' => $applicationId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Mark transaction as Completed with gateway transaction details.
     *
     * @param int $paymentId
     * @param string $gatewayPaymentId
     * @param string|null $gatewaySignature
     * @param array $rawResponse
     * @return bool
     */
    public function markCompleted(
        int $paymentId,
        string $gatewayPaymentId,
        ?string $gatewaySignature = null,
        array $rawResponse = []
    ): bool {
        $sql = "
            UPDATE payment_transactions
            SET payment_status = 'Completed',
                gateway_payment_id = :gateway_payment_id,
                gateway_signature = :gateway_signature,
                gateway_response = :gateway_response,
                paid_at = NOW()
            WHERE payment_id = :id
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id'                => $paymentId,
            'gateway_payment_id'=> $gatewayPaymentId,
            'gateway_signature' => $gatewaySignature,
            'gateway_response'  => !empty($rawResponse) ? json_encode($rawResponse) : null,
        ]);
    }

    /**
     * Mark transaction as Failed.
     *
     * @param int $paymentId
     * @param string $errorMessage
     * @return bool
     */
    public function markFailed(int $paymentId, string $errorMessage): bool
    {
        $sql = "
            UPDATE payment_transactions
            SET payment_status = 'Failed',
                gateway_response = :response
            WHERE payment_id = :id
        ";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'id'       => $paymentId,
            'response' => json_encode(['error' => $errorMessage, 'failed_at' => date('Y-m-d H:i:s')])
        ]);
    }

    /**
     * Retrieve payment transactions report with filters.
     *
     * @param array $filters
     * @return array
     */
    public function getReport(array $filters = []): array
    {
        $sql = "
            SELECT 
                pt.*,
                a.application_no,
                a.application_type,
                se.event_name,
                sy.title as symposium_title,
                s.full_name as student_name,
                s.register_number,
                COALESCE(c.college_name, s.college_name, 'Host College') as college_name
            FROM payment_transactions pt
            INNER JOIN applications a ON pt.application_id = a.application_id
            INNER JOIN symposium_events se ON pt.symposium_event_id = se.symposium_event_id
            INNER JOIN symposiums sy ON se.symposium_id = sy.symposium_id
            INNER JOIN students s ON pt.student_id = s.student_id
            LEFT JOIN colleges c ON s.college_id = c.college_id
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filters['symposium_id'])) {
            $sql .= " AND sy.symposium_id = :symp_id";
            $params['symp_id'] = (int)$filters['symposium_id'];
        }

        if (!empty($filters['event_id'])) {
            $sql .= " AND se.symposium_event_id = :event_id";
            $params['event_id'] = (int)$filters['event_id'];
        }

        if (!empty($filters['payment_status']) && $filters['payment_status'] !== 'All') {
            $sql .= " AND pt.payment_status = :status";
            $params['status'] = $filters['payment_status'];
        }

        if (!empty($filters['college_id'])) {
            $sql .= " AND s.college_id = :college_id";
            $params['college_id'] = (int)$filters['college_id'];
        }

        $sql .= " ORDER BY pt.payment_id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Retrieve financial statistics (total collected, pending, completed count).
     *
     * @param int|null $symposiumId
     * @return array
     */
    public function getSummaryStats(?int $symposiumId = null): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_transactions,
                SUM(CASE WHEN pt.payment_status = 'Completed' THEN pt.amount ELSE 0 END) as total_collected,
                SUM(CASE WHEN pt.payment_status = 'Pending' THEN pt.amount ELSE 0 END) as total_pending,
                COUNT(CASE WHEN pt.payment_status = 'Completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN pt.payment_status = 'Pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN pt.payment_status = 'Failed' THEN 1 END) as failed_count
            FROM payment_transactions pt
            INNER JOIN symposium_events se ON pt.symposium_event_id = se.symposium_event_id
            WHERE 1=1
        ";

        $params = [];
        if ($symposiumId !== null) {
            $sql .= " AND se.symposium_id = :symp_id";
            $params['symp_id'] = $symposiumId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_transactions' => 0,
            'total_collected'    => 0,
            'total_pending'      => 0,
            'completed_count'    => 0,
            'pending_count'      => 0,
            'failed_count'       => 0
        ];
    }
}
