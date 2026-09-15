<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\StudentAuthMiddleware;
use App\Services\StudentAuthService;
use App\Services\StudentChatbotService;

/**
 * Handles the secure deterministic student assistant API.
 */
class ChatbotController extends BaseController
{
    private StudentChatbotService $chatbotService;

    public function __construct()
    {
        StudentAuthMiddleware::handle();
        $this->chatbotService = new StudentChatbotService();
    }

    /**
     * Handle the incoming chat POST request.
     */
    public function handle(): void
    {
        // 1. Rate Limiting (20 requests per minute)
        $this->enforceRateLimit();

        // 2. Validate CSRF Token
        $submittedCsrf = $_POST['csrf_token'] ?? '';
        $storedCsrf    = Session::get('csrf_token', '');
        
        if (empty($submittedCsrf) || !hash_equals($storedCsrf, $submittedCsrf)) {
            $this->jsonResponse(['success' => false, 'response_type' => 'ERROR', 'message' => 'Invalid session. Please refresh the page.']);
            return;
        }

        // 3. Get User Input
        $message = trim($_POST['message'] ?? '');
        if (empty($message)) {
            $this->jsonResponse(['success' => false, 'response_type' => 'ERROR', 'message' => 'Please ask a question.']);
            return;
        }

        if (strlen($message) > 300) {
            $this->jsonResponse(['success' => false, 'response_type' => 'ERROR', 'message' => 'Message too long. Please keep it under 300 characters.']);
            return;
        }

        // 4. Delegate to Orchestrator
        $studentAuth = new StudentAuthService();
        $student = $studentAuth->student();
        $studentId = (int)($student['student_id'] ?? 0);

        $responseObj = $this->chatbotService->process($message, $studentId);

        // Save to session history (Store the structured DTO, not HTML strings)
        $history = Session::get('chatbot_history', []);
        
        $history[] = [
            'success' => true,
            'response_type' => 'USER',
            'message' => strip_tags($message)
        ];
        $history[] = $responseObj;
        
        // Keep last 50 messages
        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }
        Session::set('chatbot_history', $history);

        $this->jsonResponse($responseObj);
    }

    /**
     * Retrieve chat history for the current session.
     */
    public function getHistory(): void
    {
        $history = Session::get('chatbot_history', []);
        
        // Convert old string-based history to clear format if necessary, 
        // or just clear it if it's the old format to prevent rendering errors.
        if (!empty($history) && isset($history[0]['text'])) {
            Session::set('chatbot_history', []);
            $history = [];
        }

        header('Content-Type: application/json');
        echo json_encode(['history' => $history]);
        exit;
    }

    /**
     * Enforce max 20 requests per minute per user session.
     */
    private function enforceRateLimit(): void
    {
        $currentTime = time();
        $rateData = Session::get('chatbot_rate', ['count' => 0, 'start' => $currentTime]);

        // Reset if 60 seconds have passed
        if ($currentTime - $rateData['start'] > 60) {
            $rateData = ['count' => 0, 'start' => $currentTime];
        }

        $rateData['count']++;
        Session::set('chatbot_rate', $rateData);

        if ($rateData['count'] > 20) {
            $this->jsonResponse(['success' => false, 'response_type' => 'ERROR', 'message' => 'You are sending messages too quickly. Please wait a minute.']);
            exit;
        }
    }

    /**
     * Output JSON and exit
     */
    private function jsonResponse(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
