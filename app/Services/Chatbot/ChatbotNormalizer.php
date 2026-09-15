<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

/**
 * -------------------------------------------------------------------------
 * ChatbotNormalizer
 * -------------------------------------------------------------------------
 * Preprocesses raw user input for intent classification and entity extraction.
 *
 * Operations (in order):
 *   1. Lowercase + trim
 *   2. Remove special characters (keep alphanumeric, hyphens, spaces)
 *   3. Collapse whitespace
 *   4. Apply targeted spelling corrections (safe, domain-specific only)
 *   5. Expand abbreviations
 *
 * IMPORTANT: This normalizer must NEVER modify valid event/symposium names.
 * Spelling corrections apply token-by-token so full names are preserved.
 * -------------------------------------------------------------------------
 */
final class ChatbotNormalizer
{
    private const SPELLING_DICTIONARY = [
        // Registration
        'regstration'   => 'registration',
        'registation'   => 'registration',
        'registraton'   => 'registration',
        // Venue
        'veneu'         => 'venue',
        'venuse'        => 'venue',
        // Symposium
        'symposiumm'    => 'symposium',
        'symposyum'     => 'symposium',
        'symopsium'     => 'symposium',
        // Coordinator
        'co-ordinator'  => 'coordinator',
        'coodinator'    => 'coordinator',
        // Events
        'hackthon'      => 'hackathon',
        'hackathon'     => 'hackathon',
        'presenation'   => 'presentation',
        'presentaion'   => 'presentation',
        // Common typos
        'avilable'      => 'available',
        'avialable'     => 'available',
        'notifcation'   => 'notification',
        'notificaton'   => 'notification',
        'scheduel'      => 'schedule',
        'schdule'       => 'schedule',
        'schedue'       => 'schedule',
        'detials'       => 'details',
        'infomation'    => 'information',
        'infomation'    => 'information',
        'registerd'     => 'registered',
        'registed'      => 'registered',
        'coordintor'    => 'coordinator',
    ];

    private const ABBREVIATIONS = [
        'reg'    => 'registration',
        'dept'   => 'department',
        'coord'  => 'coordinator',
        'prof'   => 'professor',
        'dr'     => 'doctor',
        'msg'    => 'message',
        'notif'  => 'notification',
        'sympo'  => 'symposium',
        'evt'    => 'event',
        'pp'     => 'paper presentation',
    ];

    /**
     * Returns the normalized form of the input, safe for intent/entity matching.
     */
    public function normalize(string $raw): string
    {
        // 1. Lowercase and trim
        $normalized = strtolower(trim($raw));

        // 2. Remove special characters (keep alphanumeric, spaces, hyphens)
        $normalized = preg_replace('/[^a-z0-9\s\-]/', ' ', $normalized);

        // 3. Collapse multiple spaces
        $normalized = preg_replace('/\s+/', ' ', trim($normalized));

        // 4. Apply spelling corrections token-by-token
        $tokens = explode(' ', $normalized);
        $correctedTokens = array_map(
            fn($token) => self::SPELLING_DICTIONARY[$token] ?? $token,
            $tokens
        );

        // 5. Expand abbreviations
        $expandedTokens = array_map(
            fn($token) => self::ABBREVIATIONS[$token] ?? $token,
            $correctedTokens
        );

        return implode(' ', $expandedTokens);
    }

    /**
     * Returns the original input trimmed for use in DB queries or display.
     * Never modify this value — it must reflect what the user typed.
     */
    public function preserveOriginal(string $raw): string
    {
        return trim($raw);
    }
}
