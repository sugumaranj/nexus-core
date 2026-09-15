<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

/**
 * -------------------------------------------------------------------------
 * ChatbotIntentResolver
 * -------------------------------------------------------------------------
 * Production-grade deterministic intent classifier for NexusCore.
 *
 * Uses a blended Overlap-Coefficient + Jaccard scoring strategy with:
 * - Exact phrase matching (score = 1.0)
 * - Token overlap scoring (Overlap Coefficient weighted 0.7, Jaccard 0.3)
 * - Context boosting (+0.1 for recently active intent)
 * - Negative keyword exclusions
 * - Configurable confidence threshold and ambiguity margin
 *
 * NO external AI/ML service. Fully deterministic.
 * -------------------------------------------------------------------------
 */
final class ChatbotIntentResolver
{
    public const INTENT_UNKNOWN   = 'UNKNOWN';
    public const INTENT_AMBIGUOUS = 'AMBIGUOUS';

    private const INTENT_REGISTRY = [

        // ─── Conversational ──────────────────────────────────────────────
        'GREETING' => [
            'phrases'   => ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'greetings', 'howdy', 'namaste'],
            'negatives' => ['how', 'what', 'when', 'who', 'where'],
        ],
        'THANKS' => [
            'phrases'   => ['thank you', 'thanks', 'thank you so much', 'appreciate it', 'great thanks', 'thx', 'ty'],
            'negatives' => [],
        ],
        'HELP' => [
            'phrases'   => ['help', 'what can you do', 'how to use', 'assist me', 'commands', 'what can you help', 'options', 'what do you know'],
            'negatives' => ['navigate', 'navigation', 'portal', 'dashboard', 'find'],
        ],
        'CONTEXT_RESET' => [
            'phrases'   => ['clear context', 'reset', 'start over', 'forget that', 'nevermind', 'start fresh', 'clear chat'],
            'negatives' => [],
        ],

        // ─── Symposiums ──────────────────────────────────────────────────
        'LIST_SYMPOSIUMS' => [
            'phrases'   => [
                'list symposiums', 'show me symposiums', 'available symposiums', 'all symposiums', 'symposium list',
                'what symposiums are there', 'what symposiums are available', 'active symposiums', 'upcoming symposiums',
                'list active symposiums', 'show symposiums', 'which symposiums',
            ],
            'negatives' => ['event', 'schedule', 'register', 'team', 'my'],
        ],
        'SYMPOSIUM_DETAILS' => [
            'phrases'   => [
                'symposium details', 'tell me about symposium', 'symposium info', 'what is symposium',
                'describe symposium', 'symposium description', 'about symposium',
            ],
            'negatives' => ['schedule', 'register', 'event', 'date'],
        ],
        'SYMPOSIUM_SCHEDULE' => [
            'phrases'   => [
                'when is the symposium', 'symposium dates', 'symposium schedule', 'symposium timing',
                'when does symposium start', 'when does symposium end', 'symposium date',
                'what date is symposium', 'what is symposium date',
            ],
            'negatives' => ['event'],
        ],
        'SYMPOSIUM_REGISTRATION_STATUS' => [
            'phrases'   => [
                'is symposium open', 'symposium registration open', 'symposium status',
                'can i register for symposium', 'symposium registration status', 'is registration open for symposium',
                'how to register for symposium',
            ],
            'negatives' => ['event'],
        ],

        // ─── Events ──────────────────────────────────────────────────────
        'LIST_EVENTS' => [
            'phrases'   => [
                'list events', 'what events', 'show me events', 'available events', 'events in symposium', 'all events',
                'what event are available', 'what event is available', 'which events', 'show all events',
                'list all events', 'what events are there', 'events available', 'events list',
            ],
            'negatives' => ['my', 'registered', 'status'],
        ],
        'EVENT_DETAILS' => [
            'phrases'   => [
                'event details', 'tell me about event', 'event info', 'describe event', 'event description',
                'what is this event', 'event information', 'about the event',
            ],
            'negatives' => ['schedule', 'date', 'venue', 'coordinator', 'registration', 'team'],
        ],
        'EVENT_SCHEDULE' => [
            'phrases'   => [
                'when is the event', 'event date', 'event time', 'event schedule', 'what time is event',
                'when is event scheduled', 'what day is event', 'event timing', 'when does event start',
                'what time does event start', 'event start time', 'event happening when',
                'when is the event schedule', 'event schedule timing',
            ],
            'negatives' => ['symposium', 'registration'],
        ],
        'EVENT_VENUE' => [
            'phrases'   => [
                'where is the event', 'event venue', 'event location', 'event place', 'which lab', 'which hall',
                'event room', 'where will event be held', 'where is event happening', 'venue for event',
            ],
            'negatives' => [],
        ],
        'EVENT_STATUS' => [
            'phrases'   => [
                'is event running', 'event status', 'has event completed', 'event current status',
                'is event still on', 'is event cancelled', 'what is event status',
            ],
            'negatives' => ['registration', 'my'],
        ],
        'EVENT_REGISTRATION_STATUS' => [
            'phrases'   => [
                'is event registration open', 'can i register for event', 'event registration status',
                'is registration open for event', 'event open for registration', 'how to register for event',
                'registration status for event',
            ],
            'negatives' => ['my'],
        ],
        'EVENT_REGISTRATION_DEADLINE' => [
            'phrases'   => [
                'event registration deadline', 'when does event registration close', 'last date for event',
                'registration last date', 'deadline to register', 'when is registration deadline',
                'when does registration close',
            ],
            'negatives' => [],
        ],
        'EVENT_TEAM_REQUIREMENTS' => [
            'phrases'   => [
                'event team size', 'is it a team event', 'team requirements', 'how many members in event',
                'individual or team event', 'team or solo event', 'team size requirement',
                'can i participate alone', 'max team size', 'min team size',
            ],
            'negatives' => ['my'],
        ],
        'EVENT_COORDINATOR' => [
            'phrases'   => [
                'who is organizing event', 'event coordinator', 'staff for event', 'event incharge',
                'who manages event', 'who is in charge of event', 'faculty for event',
                'who is the coordinator', 'event faculty coordinator',
            ],
            'negatives' => [],
        ],

        // ─── My Registrations / Team ──────────────────────────────────────
        'MY_REGISTRATIONS' => [
            'phrases'   => [
                'my registrations', 'what am i registered for', 'my events', 'events i joined',
                'show my registrations', 'my registered events', 'events registered',
                'what events did i register', 'which events am i in', 'my event list',
            ],
            'negatives' => ['status', 'team'],
        ],
        'MY_REGISTRATION_STATUS' => [
            'phrases'   => [
                'my registration status', 'is my registration approved', 'did i get selected',
                'was i accepted', 'my application status', 'status of my registration',
                'has my registration been approved', 'was i selected', 'am i selected',
            ],
            'negatives' => [],
        ],
        'MY_REGISTRATION_DETAILS' => [
            'phrases'   => [
                'my registration details', 'my application info', 'registration info', 'details of my registration',
            ],
            'negatives' => [],
        ],
        'MY_TEAM' => [
            'phrases'   => ['my team info', 'show my team details', 'team overview'],
            'negatives' => ['create', 'join', 'status', 'requirements', 'size', 'members', 'mates'],
        ],
        'MY_TEAM_MEMBERS' => [
            'phrases'   => [
                'who is in my team', 'my team members', 'team mates', 'show team members',
                'who are my team members', 'list team members', 'my teammates',
                'my team list', 'members of my team',
            ],
            'negatives' => ['requirements', 'size', 'how many', 'event'],
        ],
        'MY_TEAM_STATUS' => [
            'phrases'   => ['my team status', 'is my team complete', 'team formation status'],
            'negatives' => [],
        ],
        'MY_TEAM_ROLE' => [
            'phrases'   => [
                'am i the team leader', 'my role in team', 'who is team leader',
                'am i team leader', 'my team role',
            ],
            'negatives' => [],
        ],

        // ─── Faculty / Judges ──────────────────────────────────────────
        'FACULTY_COORDINATOR' => [
            'phrases'   => [
                'who is the faculty coordinator', 'faculty incharge', 'faculty coordinator',
                'who is the faculty incharge', 'which faculty is handling', 'faculty details',
            ],
            'negatives' => [],
        ],
        'JUDGE_INFORMATION' => [
            'phrases'   => ['who is the judge', 'event judge', 'judges for event', 'judge details'],
            'negatives' => [],
        ],

        // ─── Notifications ──────────────────────────────────────────────
        'MY_NOTIFICATIONS' => [
            'phrases'   => [
                'my notifications', 'do i have notifications', 'unread messages', 'show notifications',
                'any notifications', 'check notifications', 'latest notifications', 'new notifications',
                'do i have any notifications',
            ],
            'negatives' => [],
        ],

        // ─── Dashboard / Navigation ───────────────────────────────────
        'DASHBOARD_HELP' => [
            'phrases'   => [
                'how to use dashboard', 'dashboard help', 'explain dashboard', 'dashboard guide',
                'how does this work', 'how to use portal',
            ],
            'negatives' => [],
        ],
        'NAVIGATION' => [
            'phrases'   => [
                'take me to', 'go to', 'navigate to', 'open page',
                'help me navigate', 'navigate portal', 'navigate dashboard',
                'portal navigation', 'how do i navigate', 'show me navigation',
            ],
            'negatives' => [],
        ],
        'ACCOUNT_INFORMATION' => [
            'phrases'   => [
                'my account', 'my profile', 'who am i', 'my info', 'my details',
                'my student info', 'my register number', 'my year', 'my semester',
            ],
            'negatives' => [],
        ],
    ];

    private const CONFIDENCE_THRESHOLD = 0.45;
    private const AMBIGUITY_MARGIN     = 0.10;

    /**
     * Classifies the normalized input string into an intent.
     */
    public function classify(string $normalizedInput, array $context = []): array
    {
        if (empty(trim($normalizedInput))) {
            return ['intent' => self::INTENT_UNKNOWN, 'confidence' => 0.0, 'candidates' => []];
        }

        $inputTokens = $this->tokenize($normalizedInput);
        if (empty($inputTokens)) {
            return ['intent' => self::INTENT_UNKNOWN, 'confidence' => 0.0, 'candidates' => []];
        }

        $scores = [];

        foreach (self::INTENT_REGISTRY as $intent => $data) {
            // Negative keyword exclusion
            $hasNegative = false;
            foreach ($data['negatives'] as $neg) {
                if (in_array($neg, $inputTokens, true)) {
                    $hasNegative = true;
                    break;
                }
            }
            if ($hasNegative) {
                $scores[$intent] = 0.0;
                continue;
            }

            $maxScoreForIntent = 0.0;

            foreach ($data['phrases'] as $phrase) {
                $phraseTokens = $this->tokenize($phrase);

                // Exact phrase match
                if ($normalizedInput === $phrase || strpos($normalizedInput, $phrase) !== false) {
                    $score = 1.0;
                } else {
                    $intersection = array_intersect($inputTokens, $phraseTokens);
                    $overlapCount = count($intersection);

                    if ($overlapCount > 0) {
                        $minLen            = min(count($inputTokens), count($phraseTokens));
                        $overlapCoefficient = $overlapCount / $minLen;
                        $unionCount        = count(array_unique(array_merge($inputTokens, $phraseTokens)));
                        $jaccard           = $overlapCount / $unionCount;
                        $score             = ($overlapCoefficient * 0.7) + ($jaccard * 0.3);
                    } else {
                        $score = 0.0;
                    }
                }

                if ($score > $maxScoreForIntent) {
                    $maxScoreForIntent = $score;
                }
            }

            // Context boost
            if (($context['last_intent'] ?? '') === $intent) {
                $maxScoreForIntent = min(1.0, $maxScoreForIntent + 0.08);
            }

            $scores[$intent] = $maxScoreForIntent;
        }

        arsort($scores);
        $topIntents = array_keys($scores);

        $topIntent = $topIntents[0];
        $topScore  = $scores[$topIntent];

        if ($topScore < self::CONFIDENCE_THRESHOLD) {
            return ['intent' => self::INTENT_UNKNOWN, 'confidence' => $topScore, 'candidates' => $scores];
        }

        if (count($topIntents) > 1) {
            $secondIntent = $topIntents[1];
            $secondScore  = $scores[$secondIntent];

            if ($secondScore >= self::CONFIDENCE_THRESHOLD && ($topScore - $secondScore) <= self::AMBIGUITY_MARGIN) {
                return ['intent' => self::INTENT_AMBIGUOUS, 'confidence' => $topScore, 'candidates' => $scores];
            }
        }

        return ['intent' => $topIntent, 'confidence' => $topScore, 'candidates' => $scores];
    }

    private function tokenize(string $str): array
    {
        static $stopwords = ['is', 'the', 'of', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'am', 'are', 'do', 'does', 'did', 'was', 'were', 'it', 'its', 'has', 'have', 'had', 'will', 'would', 'can', 'could', 'should', 'shall'];

        return array_values(array_filter(
            explode(' ', trim($str)),
            fn($t) => $t !== '' && !in_array($t, $stopwords, true)
        ));
    }
}
