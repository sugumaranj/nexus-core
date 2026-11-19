<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * -------------------------------------------------------------------------
 * NexusCore — Mock / Sandbox Payment Gateway
 * -------------------------------------------------------------------------
 * Provides high-fidelity sandbox payment simulation for testing and demo.
 * Uses HMAC-SHA256 signature generation and hash_equals() timing-safe verification.
 * -------------------------------------------------------------------------
 */
final class MockPaymentGateway implements PaymentGatewayInterface
{
    private string $secretKey;

    public function __construct(?string $secretKey = null)
    {
        $this->secretKey = $secretKey ?: (getenv('PAYMENT_MOCK_SECRET') ?: 'nexus_sandbox_secret_key_2026');
    }

    public function getProviderName(): string
    {
        return 'mock';
    }

    public function createOrder(array $orderData): array
    {
        $receipt = (string)($orderData['receipt'] ?? 'REC_' . time());
        $amount  = (float)($orderData['amount'] ?? 0.0);
        $curr    = (string)($orderData['currency'] ?? 'INR');

        $orderId = sprintf('order_mock_%s_%04d', date('YmdHis'), random_int(1000, 9999));

        return [
            'success'   => true,
            'order_id'  => $orderId,
            'amount'    => $amount,
            'currency'  => $curr,
            'receipt'   => $receipt,
            'message'   => 'Sandbox payment order created successfully.'
        ];
    }

    /**
     * Generate valid sandbox signature for a payment.
     *
     * @param string $orderId
     * @param string $paymentId
     * @return string
     */
    public function generateSignature(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId . '|' . $paymentId, $this->secretKey);
    }

    public function verifyPayment(array $payload): array
    {
        $orderId   = (string)($payload['order_id'] ?? '');
        $paymentId = (string)($payload['payment_id'] ?? '');
        $signature = (string)($payload['signature'] ?? '');

        if ($orderId === '' || $paymentId === '' || $signature === '') {
            return [
                'verified'   => false,
                'payment_id' => $paymentId,
                'message'    => 'Missing order ID, payment ID, or signature.'
            ];
        }

        // Check if signature simulation matches
        $expectedSignature = $this->generateSignature($orderId, $paymentId);

        if (!hash_equals($expectedSignature, $signature)) {
            return [
                'verified'   => false,
                'payment_id' => $paymentId,
                'message'    => 'Invalid payment signature. Verification failed.'
            ];
        }

        return [
            'verified'   => true,
            'payment_id' => $paymentId,
            'message'    => 'Payment signature verified successfully.',
            'raw'        => [
                'order_id'   => $orderId,
                'payment_id' => $paymentId,
                'status'     => 'captured',
                'verified_at'=> date('Y-m-d H:i:s')
            ]
        ];
    }
}
