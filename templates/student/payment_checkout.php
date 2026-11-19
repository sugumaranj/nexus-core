<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : payment_checkout.php
 * Location    : templates/student/
 * Description : Event Registration Fee Checkout & Payment Gateway Page.
 *
 * Layout      : student (templates/layouts/student.php)
 * Controller  : PaymentController::showCheckout()
 * Route       : GET /student/payment/checkout?application_id=...
 * -------------------------------------------------------------------------
 */

use App\Core\Session;

$error   = Session::getFlash('error');
$success = Session::getFlash('success');

$app     = $application ?? [];
$pay     = $paymentData ?? [];
$stud    = $student ?? [];

$amountFormatted = number_format((float)($pay['amount'] ?? 0), 2);
?>

<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="<?= base_url(); ?>/student/dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url(); ?>/student/my-registrations">My Registrations</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Payment Checkout</li>
                </ol>
            </nav>
            <h3 class="fw-bold mb-0">Event Fee Checkout</h3>
        </div>
        <a href="<?= base_url(); ?>/student/my-registrations" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Registrations
        </a>
    </div>

    <!-- Flash Messages -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Left Column: Registration Summary -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="bi bi-receipt me-2"></i>Registration Details
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted small">Application Number</span>
                            <div class="fw-bold fs-6 font-monospace">
                                <?= htmlspecialchars($app['application_no'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small">Registration Type</span>
                            <div>
                                <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                                    <?= htmlspecialchars($app['application_type'] ?? 'Individual', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-12">
                            <span class="text-muted small">Event Name</span>
                            <div class="fw-bold fs-5 text-dark">
                                <?= htmlspecialchars($app['event_name'] ?? 'Symposium Event', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <?php if (!empty($app['symposium_title'])): ?>
                                <small class="text-muted">
                                    <i class="bi bi-award me-1"></i><?= htmlspecialchars($app['symposium_title'], ENT_QUOTES, 'UTF-8') ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small">Applicant Name</span>
                            <div class="fw-semibold">
                                <?= htmlspecialchars($stud['full_name'] ?? $app['student_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <span class="text-muted small">Institution</span>
                            <div class="fw-semibold">
                                <?= htmlspecialchars($stud['college_name'] ?? $app['college_name'] ?? 'Host Institution', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Fee Breakdown -->
                    <h6 class="fw-bold text-dark mb-3">Fee Breakdown</h6>
                    <table class="table table-sm table-borderless">
                        <tbody>
                            <tr>
                                <td class="text-muted"><?= htmlspecialchars($pay['description'] ?? 'Registration Fee', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-end fw-semibold">₹<?= $amountFormatted ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Platform Processing Fee (Sandbox / Sponsored)</td>
                                <td class="text-end text-success fw-semibold">₹0.00</td>
                            </tr>
                            <tr class="border-top">
                                <td class="fw-bold fs-5 pt-3">Total Payable</td>
                                <td class="text-end fw-bold fs-5 text-primary pt-3">₹<?= $amountFormatted ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column: Payment Gateway Simulation -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-3 border-top border-4 border-primary">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-credit-card-2-front text-primary me-2"></i>Online Payment Gateway
                    </h5>
                </div>
                <div class="card-body p-4">

                    <div class="alert alert-info py-2 px-3 small rounded-3 mb-4">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Secure Sandbox Gateway:</strong> Transactions are processed in sandbox test mode. No actual debit will occur from your bank account.
                    </div>

                    <div class="bg-light p-3 rounded-3 mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Order Reference:</span>
                            <span class="font-monospace small fw-semibold"><?= htmlspecialchars($pay['payment_reference'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted small">Gateway Order ID:</span>
                            <span class="font-monospace small text-muted"><?= htmlspecialchars($pay['gateway_order_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Amount:</span>
                            <span class="fw-bold text-success">₹<?= $amountFormatted ?> <?= htmlspecialchars($pay['currency'] ?? 'INR', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>

                    <form id="payment-process-form" method="POST" action="<?= base_url(); ?>/student/payment/process">
                        <?= form_idempotency_field(); ?>
                        <input type="hidden" name="application_id" value="<?= (int)($app['application_id'] ?? 0) ?>">
                        <input type="hidden" name="payment_reference" value="<?= htmlspecialchars($pay['payment_reference'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="gateway_order_id" value="<?= htmlspecialchars($pay['gateway_order_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Select Payment Method</label>
                            <div class="form-check p-2 border rounded mb-2">
                                <input class="form-check-input ms-1" type="radio" name="payment_mode" id="pay_upi" value="UPI" checked>
                                <label class="form-check-label ms-2 fw-semibold" for="pay_upi">
                                    <i class="bi bi-qr-code text-primary me-1"></i> UPI / QR Code / NetBanking
                                </label>
                            </div>
                            <div class="form-check p-2 border rounded">
                                <input class="form-check-input ms-1" type="radio" name="payment_mode" id="pay_card" value="Card">
                                <label class="form-check-label ms-2 fw-semibold" for="pay_card">
                                    <i class="bi bi-credit-card text-success me-1"></i> Debit / Credit Card
                                </label>
                            </div>
                        </div>

                        <button type="submit" id="btn-pay-now" class="btn btn-primary btn-lg w-100 py-3 fw-bold shadow-sm">
                            <i class="bi bi-shield-check me-2"></i> Pay ₹<?= $amountFormatted ?> & Confirm Registration
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="bi bi-lock-fill text-secondary me-1"></i> 256-Bit SSL Encrypted Verification
                        </small>
                    </div>

                </div>
            </div>
        </div>

    </div>

</div>

<script>
document.getElementById('payment-process-form').addEventListener('submit', function() {
    const btn = document.getElementById('btn-pay-now');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing Payment...';
});
</script>
