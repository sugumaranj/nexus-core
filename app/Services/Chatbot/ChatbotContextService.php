<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

final class ChatbotContextService
{
    private const SESSION_KEY = 'chatbot_context';
    private const DEFAULT_TTL = 900; // 15 minutes

    public function get(): array
    {
        $this->init();
        
        $context = $_SESSION[self::SESSION_KEY];
        
        if ($this->isStale($context)) {
            $this->reset();
            return $_SESSION[self::SESSION_KEY];
        }

        return $context;
    }

    public function update(array $delta): void
    {
        $this->init();
        
        foreach ($delta as $key => $value) {
            $_SESSION[self::SESSION_KEY][$key] = $value;
        }
        
        $_SESSION[self::SESSION_KEY]['context_timestamp'] = time();
    }

    public function reset(): void
    {
        $_SESSION[self::SESSION_KEY] = [
            'last_symposium_id' => null,
            'last_event_id'     => null,
            'last_intent'       => null,
            'context_timestamp' => time(),
        ];
    }

    private function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY])) {
            $this->reset();
        }
    }

    private function isStale(array $context): bool
    {
        $ttl = (int)getenv('CHATBOT_CONTEXT_TTL');
        if ($ttl <= 0) {
            $ttl = self::DEFAULT_TTL;
        }

        $timestamp = $context['context_timestamp'] ?? 0;
        return (time() - $timestamp) > $ttl;
    }
}
