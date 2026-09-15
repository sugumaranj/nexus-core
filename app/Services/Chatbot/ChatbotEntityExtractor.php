<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

/**
 * -------------------------------------------------------------------------
 * ChatbotEntityExtractor
 * -------------------------------------------------------------------------
 * Extracts entity hints (event name, symposium name) from normalized input.
 *
 * Strips intent/structural keywords to leave behind only the entity name.
 * Returns null if no meaningful entity token remains.
 * -------------------------------------------------------------------------
 */
final class ChatbotEntityExtractor
{
    /**
     * Extracts potential event name hints from the normalized input.
     */
    public function extractEventHint(string $normalized): ?string
    {
        static $stopwords = [
            // Question words
            'when', 'where', 'who', 'how', 'which', 'what', 'show', 'list', 'tell', 'give', 'get', 'find',
            // Intent keywords
            'event', 'events', 'schedule', 'venue', 'status', 'registration', 'registrations', 'register',
            'team', 'members', 'coordinator', 'faculty', 'judge', 'incharge',
            // Possessives / nav
            'my', 'me', 'i', 'open', 'closed', 'deadline', 'date', 'time', 'location', 'place', 'lab', 'hall',
            'running', 'completed', 'cancelled', 'size', 'requirements', 'organizing', 'approved', 'selected',
            'info', 'application', 'leader', 'role', 'details', 'about',
            // Prepositions etc.
            'is', 'are', 'am', 'for', 'in', 'at', 'to', 'the', 'a', 'an', 'of', 'on', 'by', 'was', 'be',
            'symposium', 'symposiums',
        ];

        $tokens   = explode(' ', $normalized);
        $filtered = array_filter($tokens, fn($t) => $t !== '' && strlen($t) > 2 && !in_array($t, $stopwords, true));

        return empty($filtered) ? null : implode(' ', $filtered);
    }

    /**
     * Extracts potential symposium name hints from the normalized input.
     */
    public function extractSymposiumHint(string $normalized): ?string
    {
        static $stopwords = [
            'when', 'where', 'who', 'how', 'which', 'what', 'show', 'list', 'tell', 'give', 'get', 'find',
            'symposium', 'symposiums', 'schedule', 'status', 'registration', 'register', 'open', 'closed',
            'my', 'me', 'i', 'all', 'available', 'info', 'dates', 'timing',
            'is', 'are', 'am', 'for', 'in', 'at', 'to', 'the', 'a', 'an', 'of', 'on', 'by', 'was',
            'event', 'events', 'details', 'about', 'active', 'upcoming', 'inactive',
        ];

        $tokens   = explode(' ', $normalized);
        $filtered = array_filter($tokens, fn($t) => $t !== '' && strlen($t) > 2 && !in_array($t, $stopwords, true));

        return empty($filtered) ? null : implode(' ', $filtered);
    }

    /**
     * Extracts date intent keywords like 'today', 'tomorrow', 'upcoming'.
     */
    public function extractDateIntent(string $normalized): ?string
    {
        if (str_contains($normalized, 'today'))    return 'today';
        if (str_contains($normalized, 'tomorrow')) return 'tomorrow';
        if (str_contains($normalized, 'this week')) return 'this_week';
        if (str_contains($normalized, 'next week')) return 'next_week';
        if (str_contains($normalized, 'upcoming'))  return 'upcoming';
        return null;
    }
}
