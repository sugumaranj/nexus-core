<?php

declare(strict_types=1);

namespace App\Services\Payment;

/**
 * -------------------------------------------------------------------------
 * NexusCore — Payment Gateway Interface
 * -------------------------------------------------------------------------
 * Standard abstraction layer for payment gateways.
 * Keeps gateway-specific code isolated from application business logic.
 * -------------------------------------------------------------------------
 */
interface PaymentGatewayInterface
{
    /**
     * Create an order on the gateway.
     *
     * @param array $orderData [
     *     'receipt'  => string,
     *     'amount'   => float,
     *     'currency' => string,
     *     'notes'    => array
     * ]
     * @return array [
     *     'success'   => bool,
     *     'order_id'  => string,
     *     'amount'    => float,
     *     'currency'  => string,
     *     'message'   => string
     * ]
     */
    public function createOrder(array $orderData): array;

    /**
     * Verify payment signature and transaction authenticity.
     *
     * @param array $payload [
     *     'order_id'   => string,
     *     'payment_id' => string,
     *     'signature'  => string,
     *     'expected_amount' => float
     * ]
     * @return array [
     *     'verified'   => bool,
     *     'payment_id' => string,
     *     'message'    => string,
     *     'raw'        => array
     * ]
     */
    public function verifyPayment(array $payload): array;

    /**
     * Return the provider name identifier.
     *
     * @return string
     */
    public function getProviderName(): string;
}
