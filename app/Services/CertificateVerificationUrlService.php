<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : CertificateVerificationUrlService.php
 * Location    : app/Services/
 * Description : Constructs the canonical public verification URL for a
 *               certificate given its opaque verification_token.
 *
 * Responsibilities
 * -------------------------------------------------------------------------
 * • Build the public verification URL from config base_url + route + token.
 * • Provide a single authoritative location for the route path, so that
 *   if the route ever changes, only this service needs updating.
 *
 * Public URL format:
 *   {base_url}/certificates/verify?token={verification_token}
 *
 * The token is passed as a query parameter (not a path segment) because
 * the NexusCore Router uses exact-match routing with no path-parameter
 * support.
 *
 * Project     : NexusCore
 * -------------------------------------------------------------------------
 */

namespace App\Services;

final class CertificateVerificationUrlService
{
    /**
     * The public verification route path.
     * Single source of truth — matches the route registered in index.php.
     */
    public const VERIFY_ROUTE = '/certificates/verify';

    /**
     * Build the complete public verification URL for a given token.
     *
     * @param  string $token  The verification_token value (64-char hex).
     * @return string         Absolute URL safe to embed in a QR code.
     */
    public function buildUrl(string $token): string
    {
        return rtrim(base_url(self::VERIFY_ROUTE), '/') . '?token=' . urlencode($token);
    }
}
