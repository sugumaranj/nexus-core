<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Database\Database;
use App\Models\ApplicationModel;
use App\Models\EventFeeModel;
use App\Models\NotificationModel;
use App\Models\PaymentTransactionModel;
use App\Models\SymposiumEventModel;
use PDO;
use Throwable;

/**
 * -------------------------------------------------------------------------
 * NexusCore — Payment Service
 * -------------------------------------------------------------------------
 * Core payment business logic layer.
 * Manages order creation, signature verification, application state transitions,
 * capacity checks, idempotency, and participant notifications.
 * -------------------------------------------------------------------------
 */
final class PaymentService
{
    private PDO $db;
    private PaymentTransactionModel $paymentModel;
    private EventFeeModel $feeModel;
    private ApplicationModel $appModel;
    private SymposiumEventModel $eventModel;
    private NotificationModel $notifModel;
    private PaymentGatewayInterface $gateway;

    public function __construct(?PaymentGatewayInterface $gateway = null)
    {
        $this->db           = Database::getConnection();
        $this->paymentModel = new PaymentTransactionModel();
        $this->feeModel     = new EventFeeModel();
        $this->appModel     = new ApplicationModel();
        $this->eventModel   = new SymposiumEventModel();
        $this->notifModel   = new NotificationModel();
        $this->gateway      = $gateway ?: new MockPaymentGateway();
    }

    /**
     * Get the active gateway provider instance.
     */
    public function getGateway(): PaymentGatewayInterface
    {
        return $this->gateway;
    }

    /**
     * Initiate a payment order for an application.
     *
     * @param int $applicationId
     * @param int $studentId
     * @return array
     */
    public function initiatePayment(int $applicationId, int $studentId): array
    {
        try {
            $app = $this->appModel->findById($applicationId);
            if (!$app) {
                return ['success' => false, 'message' => 'Application not found.'];
            }

            if ((int)$app['student_id'] !== $studentId) {
                return ['success' => false, 'message' => 'Unauthorized payment request.'];
            }

            if ($app['payment_status'] === 'Paid' || $app['payment_status'] === 'Not Required') {
                return ['success' => false, 'message' => 'Payment is not required or already completed for this application.'];
            }

            $symposiumEventId = (int)$app['symposium_event_id'];
            $appType = (string)$app['application_type'];

            // Calculate participant count if team
            $partCount = 1;
            if ($appType === 'Team') {
                $memberModel = new \App\Models\TeamMemberModel();
                $teamModel = new \App\Models\TeamModel();
                $team = $teamModel->findByApplication($applicationId);
                if ($team) {
                    $partCount = count($memberModel->getByTeam((int)$team['team_id']));
                }
            }

            // Calculate authoritative fee from server rules
            $feeCalc = $this->feeModel->calculateFee($symposiumEventId, $appType, $partCount);
            if (!$feeCalc['is_fee_required'] || $feeCalc['amount'] <= 0) {
                // Event is free: mark as Not Required and Approve
                $this->db->prepare("UPDATE applications SET payment_status = 'Not Required', application_status = 'Approved', approval_status = 'Approved' WHERE application_id = :id")
                         ->execute(['id' => $applicationId]);
                return ['success' => true, 'is_free' => true, 'message' => 'Event fee is free. Registration confirmed.'];
            }

            $amount   = (float)$feeCalc['amount'];
            $currency = (string)$feeCalc['currency'];
            $ref      = $this->paymentModel->generateReference();

            // Create Gateway order
            $orderRes = $this->gateway->createOrder([
                'receipt'  => $ref,
                'amount'   => $amount,
                'currency' => $currency,
                'notes'    => [
                    'application_id'     => $applicationId,
                    'symposium_event_id' => $symposiumEventId,
                    'student_id'         => $studentId
                ]
            ]);

            if (!$orderRes['success']) {
                return ['success' => false, 'message' => 'Failed to initialize payment gateway order: ' . $orderRes['message']];
            }

            $gatewayOrderId = (string)$orderRes['order_id'];

            // Create payment transaction record
            $paymentId = $this->paymentModel->create([
                'payment_reference' => $ref,
                'application_id'    => $applicationId,
                'symposium_event_id'=> $symposiumEventId,
                'student_id'        => $studentId,
                'amount'            => $amount,
                'currency'          => $currency,
                'gateway_provider'  => $this->gateway->getProviderName(),
                'gateway_order_id'  => $gatewayOrderId,
                'payment_status'    => 'Pending',
                'idempotency_key'   => $ref . '_' . $gatewayOrderId
            ]);

            return [
                'success'           => true,
                'is_free'           => false,
                'payment_id'        => $paymentId,
                'payment_reference' => $ref,
                'gateway_order_id'  => $gatewayOrderId,
                'amount'            => $amount,
                'currency'          => $currency,
                'description'       => $feeCalc['description'],
                'application_no'    => $app['application_no'] ?? '',
                'event_name'        => $app['event_name'] ?? ''
            ];

        } catch (Throwable $e) {
            error_log('Initiate payment error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while preparing your payment. Please try again.'];
        }
    }

    /**
     * Verify payment signature and confirm registration.
     *
     * @param string $paymentReference
     * @param array $payload
     * @return array
     */
    public function verifyAndCompletePayment(string $paymentReference, array $payload): array
    {
        $this->db->beginTransaction();

        try {
            $payment = $this->paymentModel->findByReference($paymentReference);
            if (!$payment) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Payment transaction record not found.'];
            }

            // Idempotency: if already completed, return success immediately
            if ($payment['payment_status'] === 'Completed') {
                $this->db->commit();
                return [
                    'success' => true,
                    'already_completed' => true,
                    'message' => 'Payment already verified and completed.',
                    'application_id' => (int)$payment['application_id']
                ];
            }

            $gatewayOrderId = (string)$payment['gateway_order_id'];
            $gatewayPayId   = (string)($payload['gateway_payment_id'] ?? $payload['payment_id'] ?? '');
            $signature      = (string)($payload['gateway_signature'] ?? $payload['signature'] ?? '');

            // Verify with Gateway
            $verifyRes = $this->gateway->verifyPayment([
                'order_id'   => $gatewayOrderId,
                'payment_id' => $gatewayPayId,
                'signature'  => $signature,
                'expected_amount' => (float)$payment['amount']
            ]);

            if (!$verifyRes['verified']) {
                $this->paymentModel->markFailed((int)$payment['payment_id'], $verifyRes['message']);
                $this->db->commit();
                return ['success' => false, 'message' => 'Payment verification failed: ' . $verifyRes['message']];
            }

            // Mark transaction Completed
            $this->paymentModel->markCompleted(
                (int)$payment['payment_id'],
                $gatewayPayId,
                $signature,
                $verifyRes['raw'] ?? []
            );

            // Confirm application registration: payment_status = 'Paid', application_status = 'Approved'
            $appSql = "
                UPDATE applications
                SET payment_status = 'Paid',
                    application_status = 'Approved',
                    approval_status = 'Approved',
                    approved_at = NOW()
                WHERE application_id = :id
            ";
            $this->db->prepare($appSql)->execute(['id' => (int)$payment['application_id']]);

            $this->db->commit();

            // Notify student
            $event = $this->eventModel->findById((int)$payment['symposium_event_id']);
            $eventName = $event['event_name'] ?? 'Event';
            $this->notifModel->createForStudent(
                (int)$payment['student_id'],
                'Payment Received & Registration Confirmed',
                "Your payment of {$payment['currency']} {$payment['amount']} for {$eventName} was successfully processed (Ref: {$payment['payment_reference']}). Your registration is now confirmed!"
            );

            return [
                'success'           => true,
                'message'           => 'Payment verified and registration confirmed successfully!',
                'application_id'    => (int)$payment['application_id'],
                'payment_reference' => $payment['payment_reference']
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Verify payment error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while verifying the payment.'];
        }
    }
}
