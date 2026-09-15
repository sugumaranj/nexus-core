<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : CertificateVerificationController.php
 * Location    : app/Controllers/
 * Description : Handles the public certificate verification endpoint.
 *
 * This controller is intentionally unauthenticated — it serves the
 * public-facing QR verification URL embedded in every certificate.
 *
 * Route
 * -------------------------------------------------------------------------
 *   GET /certificates/verify?token=<64-char hex>
 *
 * Security considerations
 * -------------------------------------------------------------------------
 *   • No session or authentication check — public endpoint.
 *   • Token parameter sanitized before passing to service.
 *   • Service output DTO exposes no internal IDs, paths, or user data.
 *   • HTTP response is always 200 (the UI shows the appropriate status).
 *     Do not 404 on invalid tokens — timing-safe to avoid enumeration.
 *   • Rate limiting is expected to be configured at the web-server level.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Services\CertificateVerificationService;

final class CertificateVerificationController extends BaseController
{
    private CertificateVerificationService $verificationService;

    public function __construct()
    {
        $this->verificationService = new CertificateVerificationService();
    }

    /**
     * Handle the public verification request.
     *
     * GET /certificates/verify?token=<64-char hex>
     *
     * Always renders the verification view — the view adapts based on the
     * status string returned from the service.
     */
    public function verify(): void
    {
        // Sanitize: strip non-hex characters before passing to service.
        // Service further validates exact 64-char length + hex pattern.
        $raw   = $_GET['token'] ?? '';
        $token = preg_replace('/[^0-9a-fA-F]/', '', strtolower($raw));

        $result = $this->verificationService->verify($token);

        $this->render(
            'certificate.verify',
            [
                'pageTitle'  => 'Certificate Verification — NexusCore',
                'result'     => $result,
                'token'      => $token, // sanitized, safe for display
            ],
            'master'
        );
    }
}
