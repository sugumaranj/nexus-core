<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

/**
 * -------------------------------------------------------------------------
 * ChatbotResponseBuilder
 * -------------------------------------------------------------------------
 * Builds structured JSON-serializable response DTOs for the chatbot API.
 *
 * Response types:
 *   TEXT      — single message string
 *   LIST      — message + array of string items
 *   AMBIGUOUS — message + array of {id, name} options for the user to pick
 *   HELP      — standard help menu
 *   ERROR     — error message (success = false)
 *
 * The 'items' array contains only strings (safe for textContent rendering).
 * -------------------------------------------------------------------------
 */
final class ChatbotResponseBuilder
{
    private function base(string $intent, string $type, string $message, array $context): array
    {
        return [
            'success'       => true,
            'intent'        => $intent,
            'response_type' => $type,
            'message'       => $message,
            'context'       => $context,
        ];
    }

    /**
     * Build a plain text response.
     */
    public function buildText(string $intent, string $message, array $context = []): array
    {
        return $this->base($intent, 'TEXT', $message, $context);
    }

    /**
     * Build a list response. $items must be an array of strings.
     */
    public function buildList(string $intent, string $message, array $items, array $context = []): array
    {
        $response          = $this->base($intent, 'LIST', $message, $context);
        $response['items'] = array_values(array_filter(array_map('strval', $items)));
        return $response;
    }

    /**
     * Build an ambiguous response with clickable options for the user to pick.
     */
    public function buildAmbiguous(string $intent, string $message, array $candidates, string $nameKey, array $context = []): array
    {
        $response            = $this->base($intent, 'AMBIGUOUS', $message, $context);
        $response['options'] = array_map(function ($c) use ($nameKey) {
            return [
                'id'   => $c['symposium_event_id'] ?? $c['symposium_id'] ?? null,
                'name' => $c[$nameKey] ?? 'Unknown',
            ];
        }, array_values($candidates));
        return $response;
    }

    /**
     * Build an error response.
     */
    public function buildError(string $message): array
    {
        return [
            'success'       => false,
            'intent'        => 'ERROR',
            'response_type' => 'ERROR',
            'message'       => $message,
            'context'       => [],
        ];
    }

    /**
     * Build the help menu.
     */
    public function buildHelp(): array
    {
        return [
            'success'       => true,
            'intent'        => 'HELP',
            'response_type' => 'LIST',
            'message'       => "Here is what I can help you with:",
            'items'         => [
                '📋 List active symposiums',
                '📅 Event schedules and dates',
                '📍 Event venues and locations',
                '🔖 My registrations and status',
                '👥 My team members',
                '🔔 My notifications',
                '📝 Registration deadlines',
                '👨‍🏫 Faculty coordinator for an event',
                '🏆 Event team requirements',
                '🧭 How to navigate the portal',
            ],
            'context'       => [],
        ];
    }
}
