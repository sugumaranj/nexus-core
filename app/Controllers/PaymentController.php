<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Models\ApplicationModel;
use App\Models\EventFeeModel;
use App\Services\Payment\MockPaymentGateway;
use App\Services\Payment\PaymentService;

/**
 * -------------------------------------------------------------------------
 * NexusCore — Payment Controller
 * -------------------------------------------------------------------------
 * Handles registration fee checkout, mock/gateway payment processing,
 * and asynchronous webhook notifications.
 * -------------------------------------------------------------------------
 */
final class PaymentController extends BaseController
{
    private PaymentService $paymentService;
    private ApplicationModel $appModel;
    private EventFeeModel $feeModel;

    public function __construct()
    {
        $this->paymentService = new PaymentService();
        $this->appModel       = new ApplicationModel();
        $this->feeModel       = new EventFeeModel();
    }

    /**
     * Show Checkout Page
     * GET /student/payment/checkout?application_id=...
     */
    public function showCheckout(): void
    {
        Session::start();
        $student = Session::get('student');
        if (!$student) {
            $this->redirect('/student/login');
        }

        $studentId = (int)$student['student_id'];
        $appId = (int)($_GET['application_id'] ?? 0);

        if ($appId <= 0) {
            $this->error('Invalid application specified.');
            $this->redirect('/student/my-registrations');
        }

        $app = $this->appModel->findById($appId);
        if (!$app) {
            $this->error('Application not found.');
            $this->redirect('/student/my-registrations');
        }

        if ((int)$app['student_id'] !== $studentId) {
            $this->error('You are not authorized to access this payment checkout.');
            $this->redirect('/student/my-registrations');
        }

        if ($app['payment_status'] === 'Paid' || $app['payment_status'] === 'Not Required') {
            $this->success('Payment is already completed or not required for this registration.');
            $this->redirect('/student/my-registrations');
        }

        $initRes = $this->paymentService->initiatePayment($appId, $studentId);
        if (!$initRes['success']) {
            $this->error($initRes['message']);
            $this->redirect('/student/my-registrations');
        }

        if (!empty($initRes['is_free'])) {
            $this->success('Registration confirmed! This is a free event.');
            $this->redirect('/student/my-registrations');
        }

        $this->render(
            'student.payment_checkout',
            [
                'pageTitle'    => 'Event Fee Checkout — NexusCore',
                'application'  => $app,
                'paymentData'  => $initRes,
                'student'      => $student,
            ],
            'student'
        );
    }

    /**
     * Process Gateway Callback / Mock Submission
     * POST /student/payment/process
     */
    public function processPayment(): never
    {
        Session::start();

        $formToken = $_POST['form_idempotency_token'] ?? null;
        if (!verify_form_idempotency_token($formToken)) {
            $this->error('A payment submission is already being processed.');
            $this->redirect('/student/my-registrations');
        }
        $student = Session::get('student');
        if (!$student) {
            $this->redirect('/student/login');
        }

        $paymentRef = trim((string)($_POST['payment_reference'] ?? ''));
        $appId      = (int)($_POST['application_id'] ?? 0);
        $orderId    = trim((string)($_POST['gateway_order_id'] ?? ''));
        $paymentId  = trim((string)($_POST['gateway_payment_id'] ?? ''));
        $signature  = trim((string)($_POST['gateway_signature'] ?? ''));

        // If mock payment, simulate successful payment_id and signature if not provided
        if (empty($paymentId)) {
            $paymentId = 'pay_mock_' . bin2hex(random_bytes(6));
        }
        if (empty($signature)) {
            $gateway = $this->paymentService->getGateway();
            if ($gateway instanceof MockPaymentGateway) {
                $signature = $gateway->generateSignature($orderId, $paymentId);
            }
        }

        $result = $this->paymentService->verifyAndCompletePayment($paymentRef, [
            'order_id'           => $orderId,
            'gateway_payment_id' => $paymentId,
            'gateway_signature'  => $signature
        ]);

        if (!$result['success']) {
            $this->error($result['message']);
            $this->redirect('/student/payment/checkout?application_id=' . $appId);
        }

        $this->success('Payment verified successfully! Your event registration is confirmed.');
        $this->redirect('/student/my-registrations');
    }

    /**
     * Payment Webhook Endpoint
     * POST /api/payment/webhook
     */
    public function webhook(): void
    {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true) ?: $_POST;

        $paymentRef = (string)($payload['payment_reference'] ?? '');
        if (!empty($paymentRef)) {
            $result = $this->paymentService->verifyAndCompletePayment($paymentRef, $payload);
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing payment reference']);
        exit;
    }
}
