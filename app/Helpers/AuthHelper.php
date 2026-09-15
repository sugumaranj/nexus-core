<?php

declare(strict_types=1);

namespace App\Helpers;

final class AuthHelper
{
    private const APP_SECRET = 'nx_super_secret_key_8492049281_nx';

    /**
     * Generate a stateless, signed token for offline synchronization.
     * The token is valid for 30 days and contains the user_id and role.
     */
    public static function generateOfflineSyncToken(array $user): string
    {
        $userId = $user['user_id'] ?? 0;
        $role = $user['role'] ?? '';
        $expires = time() + (30 * 24 * 60 * 60); // 30 days

        $payload = json_encode([
            'u' => $userId,
            'r' => $role,
            'e' => $expires
        ]);

        $base64Payload = base64_encode($payload);
        $signature = hash_hmac('sha256', $base64Payload, self::APP_SECRET);

        return $base64Payload . '.' . $signature;
    }

    /**
     * Validate an offline sync token and return the reconstructed user payload
     * if the token is valid and not expired. Returns null otherwise.
     */
    public static function validateOfflineSyncToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$base64Payload, $signature] = $parts;

        // Verify signature
        $expectedSignature = hash_hmac('sha256', $base64Payload, self::APP_SECRET);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decode payload
        $payloadJson = base64_decode($base64Payload);
        if (!$payloadJson) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!$payload || !isset($payload['u'], $payload['r'], $payload['e'])) {
            return null;
        }

        // Check expiration
        if (time() > $payload['e']) {
            return null;
        }

        // Return reconstructed user array
        return [
            'user_id' => (int) $payload['u'],
            'role'    => (string) $payload['r']
        ];
    }
}
