<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Chatbot\ChatbotNormalizer;
use App\Services\Chatbot\ChatbotIntentResolver;
use App\Services\Chatbot\ChatbotEntityExtractor;
use App\Services\Chatbot\ChatbotEntityResolver;
use App\Services\Chatbot\ChatbotContextService;
use App\Services\Chatbot\ChatbotQueryService;
use App\Services\Chatbot\ChatbotResponseBuilder;

/**
 * -------------------------------------------------------------------------
 * StudentChatbotService — Production Orchestrator
 * -------------------------------------------------------------------------
 * Orchestrates the deterministic chatbot pipeline:
 *
 *   Input → Normalize → Classify Intent → Extract Entities → Resolve Entities
 *       → Update Context → Dispatch Handler → Build Response
 *
 * Design Rules:
 * - Never guesses or fabricates data.
 * - Falls back to "please specify" instead of erroring.
 * - Context-aware: follow-up questions reuse the last resolved entities.
 * - All DB access goes through ChatbotQueryService (canonical models only).
 * -------------------------------------------------------------------------
 */
final class StudentChatbotService
{
    private ChatbotNormalizer      $normalizer;
    private ChatbotIntentResolver  $intentResolver;
    private ChatbotEntityExtractor $extractor;
    private ChatbotEntityResolver  $entityResolver;
    private ChatbotContextService  $contextService;
    private ChatbotQueryService    $queryService;
    private ChatbotResponseBuilder $responseBuilder;

    // Intents that REQUIRE a specific event to be resolved
    private const EVENT_REQUIRED_INTENTS = [
        'EVENT_DETAILS', 'EVENT_SCHEDULE', 'EVENT_VENUE', 'EVENT_STATUS',
        'EVENT_REGISTRATION_STATUS', 'EVENT_REGISTRATION_DEADLINE',
        'EVENT_TEAM_REQUIREMENTS', 'EVENT_COORDINATOR', 'FACULTY_COORDINATOR',
        'MY_REGISTRATION_STATUS', 'MY_REGISTRATION_DETAILS',
        'MY_TEAM', 'MY_TEAM_MEMBERS', 'MY_TEAM_STATUS', 'MY_TEAM_ROLE',
    ];

    // Intents that require a symposium (but NOT necessarily a specific event)
    private const SYMPOSIUM_REQUIRED_INTENTS = [
        'SYMPOSIUM_DETAILS', 'SYMPOSIUM_SCHEDULE', 'SYMPOSIUM_REGISTRATION_STATUS',
    ];

    public function __construct()
    {
        $this->normalizer      = new ChatbotNormalizer();
        $this->intentResolver  = new ChatbotIntentResolver();
        $this->extractor       = new ChatbotEntityExtractor();
        $this->entityResolver  = new ChatbotEntityResolver();
        $this->contextService  = new ChatbotContextService();
        $this->queryService    = new ChatbotQueryService();
        $this->responseBuilder = new ChatbotResponseBuilder();
    }

    public function process(string $rawMessage, int $studentId): array
    {
        // ── 1. Normalize ────────────────────────────────────────────────
        $normalized = $this->normalizer->normalize($rawMessage);
        $context    = $this->contextService->get();

        // ── 2. Classify intent ─────────────────────────────────────────
        $classification = $this->intentResolver->classify($normalized, $context);
        $intent         = $classification['intent'];

        // ── 3. Handle purely conversational intents immediately ────────
        switch ($intent) {
            case ChatbotIntentResolver::INTENT_UNKNOWN:
                return $this->responseBuilder->buildText(
                    'UNKNOWN',
                    "I didn't quite understand that. You can ask me about symposiums, events, registrations, schedules, or type **help** to see what I can do.",
                    $context
                );

            case ChatbotIntentResolver::INTENT_AMBIGUOUS:
                return $this->responseBuilder->buildText(
                    'AMBIGUOUS',
                    "Your question seems to match multiple topics. Could you be more specific? For example: 'event schedule for CODING' or 'symposium registration status for Nexus Eval'.",
                    $context
                );

            case 'CONTEXT_RESET':
                $this->contextService->reset();
                return $this->responseBuilder->buildText('CONTEXT_RESET', "Context cleared! How can I help you from scratch?", []);

            case 'GREETING':
                return $this->responseBuilder->buildText('GREETING', "Hello! I'm your NexusCore Assistant. Ask me about symposiums, events, schedules, registrations, or anything about the student portal. How can I help?", $context);

            case 'THANKS':
                return $this->responseBuilder->buildText('THANKS', "You're welcome! Feel free to ask anything else.", $context);

            case 'HELP':
            case 'DASHBOARD_HELP':
                return $this->responseBuilder->buildHelp();
        }

        // ── 4. Load data from DB ───────────────────────────────────────
        $visibleSymposiums = $this->queryService->getVisibleSymposiums();

        // ── 5. Resolve entities ────────────────────────────────────────
        $sympHint  = $this->extractor->extractSymposiumHint($normalized);
        $eventHint = $this->extractor->extractEventHint($normalized);

        $resolvedSymposium = null;
        $resolvedEvent     = null;

        // A. Try to resolve symposium
        $sympResolution = $this->entityResolver->resolveSymposium(
            $sympHint ?? '',
            $visibleSymposiums,
            $context['last_symposium_id'],
            $this->normalizer
        );

        if ($sympResolution['status'] === 'AMBIGUOUS') {
            return $this->responseBuilder->buildAmbiguous(
                $intent, "Which symposium are you asking about?",
                $sympResolution['candidates'], 'title', $context
            );
        }
        if (in_array($sympResolution['status'], ['MATCH', 'CONTEXT'], true)) {
            $resolvedSymposium = $sympResolution['symposium'];
        }

        // B. Build event pool: if symposium resolved, search only that; else search all
        $eventPool = [];
        if ($resolvedSymposium !== null) {
            $eventPool = $this->queryService->getEventsForSymposium((int)$resolvedSymposium['symposium_id']);
        } else {
            foreach ($visibleSymposiums as $s) {
                $eventPool = array_merge($eventPool, $this->queryService->getEventsForSymposium((int)$s['symposium_id']));
            }
        }

        // C. Try to resolve event
        if ($eventHint !== null || in_array($intent, self::EVENT_REQUIRED_INTENTS, true)) {
            $evtResolution = $this->entityResolver->resolveEvent(
                $eventHint ?? '',
                $eventPool,
                $context['last_event_id'],
                $this->normalizer
            );

            if ($evtResolution['status'] === 'AMBIGUOUS') {
                return $this->responseBuilder->buildAmbiguous(
                    $intent, "Which event are you asking about?",
                    $evtResolution['candidates'], 'event_name', $context
                );
            }

            if (in_array($evtResolution['status'], ['MATCH', 'CONTEXT'], true)) {
                $resolvedEvent = $evtResolution['event'];
                if ($resolvedEvent !== null && $resolvedSymposium === null) {
                    $resolvedSymposium = $this->queryService->getSymposiumById((int)$resolvedEvent['symposium_id']) ?: null;
                }
            } elseif ($evtResolution['status'] === 'NOT_FOUND') {
                // If intent requires an event but we can't find one
                if (in_array($intent, self::EVENT_REQUIRED_INTENTS, true)) {
                    if ($eventHint !== null) {
                        return $this->responseBuilder->buildText(
                            'NOT_FOUND',
                            "I couldn't find an event matching \"" . htmlspecialchars($this->normalizer->preserveOriginal($eventHint)) . "\". Please check the name or ask: 'What events are available?'",
                            $context
                        );
                    }
                    // No hint, no context — ask for clarification
                    return $this->responseBuilder->buildText(
                        'CLARIFY',
                        "Which event are you asking about? You can ask 'What events are available?' to see the full list.",
                        $context
                    );
                }
            }
        }

        // ── 6. Symposium requirement check ─────────────────────────────
        if (in_array($intent, self::SYMPOSIUM_REQUIRED_INTENTS, true) && $resolvedSymposium === null) {
            // If only one symposium exists, auto-resolve
            if (count($visibleSymposiums) === 1) {
                $resolvedSymposium = $visibleSymposiums[0];
            } elseif (count($visibleSymposiums) === 0) {
                return $this->responseBuilder->buildText($intent, "There are no active symposiums at the moment.", $context);
            } else {
                return $this->responseBuilder->buildAmbiguous(
                    $intent, "Which symposium are you asking about?",
                    $visibleSymposiums, 'title', $context
                );
            }
        }

        // ── 7. Update context ──────────────────────────────────────────
        if ($resolvedSymposium !== null) {
            $this->contextService->update([
                'last_symposium_id' => $resolvedSymposium['symposium_id'],
                'last_intent'       => $intent,
            ]);
        }
        if ($resolvedEvent !== null) {
            $this->contextService->update([
                'last_event_id'     => $resolvedEvent['symposium_event_id'],
                'last_symposium_id' => $resolvedEvent['symposium_id'],
                'last_intent'       => $intent,
            ]);
        }
        if ($resolvedSymposium === null && $resolvedEvent === null) {
            $this->contextService->update(['last_intent' => $intent]);
        }

        $context = $this->contextService->get();

        // ── 8. Dispatch to intent handler ──────────────────────────────
        return $this->handleIntent($intent, $resolvedSymposium, $resolvedEvent, $studentId, $context, $visibleSymposiums, $eventPool);
    }

    private function handleIntent(
        string $intent,
        ?array $symposium,
        ?array $event,
        int $studentId,
        array $context,
        array $visibleSymposiums,
        array $eventPool
    ): array {
        switch ($intent) {

            // ── Symposium intents ────────────────────────────────────────

            case 'LIST_SYMPOSIUMS':
                if (empty($visibleSymposiums)) {
                    return $this->responseBuilder->buildText($intent, "There are currently no active symposiums.", $context);
                }
                $items = array_map(fn($s) => $s['title'] . ' (' . $s['status'] . ')', $visibleSymposiums);
                return $this->responseBuilder->buildList($intent, "Here are the active symposiums:", $items, $context);

            case 'SYMPOSIUM_DETAILS':
                if (!$symposium) return $this->responseBuilder->buildText($intent, "Please specify which symposium you mean.", $context);
                $desc = $symposium['description'] ? (' — ' . $symposium['description']) : '';
                return $this->responseBuilder->buildText(
                    $intent,
                    "**{$symposium['title']}** is a {$symposium['symposium_type']} for academic year {$symposium['academic_year']}{$desc}. Status: {$symposium['status']}.",
                    $context
                );

            case 'SYMPOSIUM_SCHEDULE':
                if (!$symposium) return $this->responseBuilder->buildText($intent, "Please specify which symposium you mean.", $context);
                $start = $symposium['event_start_date'] ? date('d M Y', strtotime($symposium['event_start_date'])) : 'TBA';
                $end   = $symposium['event_end_date']   ? date('d M Y', strtotime($symposium['event_end_date']))   : 'TBA';
                return $this->responseBuilder->buildText(
                    $intent,
                    "**{$symposium['title']}** runs from **{$start}** to **{$end}**.",
                    $context
                );

            case 'SYMPOSIUM_REGISTRATION_STATUS':
                if (!$symposium) return $this->responseBuilder->buildText($intent, "Please specify which symposium you mean.", $context);
                $regEnd = $symposium['registration_end'] ? date('d M Y', strtotime($symposium['registration_end'])) : 'not set';
                return $this->responseBuilder->buildText(
                    $intent,
                    "Registration for **{$symposium['title']}** is currently **{$symposium['status']}**. Registration closes: {$regEnd}.",
                    $context
                );

            // ── Event listing ─────────────────────────────────────────────

            case 'LIST_EVENTS':
                if ($symposium) {
                    $events = $this->queryService->getEventsForSymposium((int)$symposium['symposium_id']);
                    if (empty($events)) return $this->responseBuilder->buildText($intent, "No events are scheduled for **{$symposium['title']}** yet.", $context);
                    $items = array_map(fn($e) => $e['event_name'] . ' (' . $e['category'] . ')', $events);
                    return $this->responseBuilder->buildList($intent, "Events in **{$symposium['title']}**:", $items, $context);
                }
                // No symposium specified — list all
                if (empty($eventPool)) return $this->responseBuilder->buildText($intent, "No events are currently available.", $context);
                $items = array_map(fn($e) => $e['event_name'] . ' (' . ($e['symposium_title'] ?? 'Unknown') . ')', $eventPool);
                return $this->responseBuilder->buildList($intent, "Here are all available events:", $items, $context);

            // ── Specific event intents ─────────────────────────────────────

            case 'EVENT_DETAILS':
                if (!$event) return $this->responseBuilder->buildText($intent, "Please specify which event you mean. Ask 'What events are available?' to see the list.", $context);
                $teamInfo = $event['participation_type'] === 'Individual'
                    ? 'Individual event'
                    : "Team event ({$event['min_team_size']}–{$event['max_team_size']} members)";
                return $this->responseBuilder->buildText(
                    $intent,
                    "**{$event['event_name']}** is a {$event['category']} event. Type: {$teamInfo}. Status: {$event['status']}.",
                    $context
                );

            case 'EVENT_SCHEDULE':
                if (!$event) {
                    // If no event specified, show schedules for all events in pool
                    if (empty($eventPool)) return $this->responseBuilder->buildText($intent, "No events are currently scheduled.", $context);
                    $scheduled = array_filter($eventPool, fn($e) => !empty($e['event_date']));
                    if (empty($scheduled)) {
                        return $this->responseBuilder->buildText($intent, "Schedules have not been announced yet for the current events.", $context);
                    }
                    $items = [];
                    foreach ($scheduled as $e) {
                        $d = date('d M Y', strtotime($e['event_date']));
                        $t = !empty($e['start_time']) ? ' at ' . date('h:i A', strtotime($e['start_time'])) : '';
                        $items[] = $e['event_name'] . ': ' . $d . $t;
                    }
                    return $this->responseBuilder->buildList($intent, "Here are the event schedules:", $items, $context);
                }
                if (empty($event['event_date'])) {
                    return $this->responseBuilder->buildText($intent, "The schedule for **{$event['event_name']}** has not been announced yet.", $context);
                }
                $d = date('d M Y', strtotime($event['event_date']));
                $t = !empty($event['start_time']) ? date('h:i A', strtotime($event['start_time'])) : 'TBA';
                return $this->responseBuilder->buildText($intent, "**{$event['event_name']}** is scheduled for **{$d}** at **{$t}**.", $context);

            case 'EVENT_VENUE':
                if (!$event) return $this->responseBuilder->buildText($intent, "Please specify which event you mean.", $context);
                $venue = !empty($event['venue_name']) ? $event['venue_name'] : null;
                if (!$venue) {
                    return $this->responseBuilder->buildText($intent, "The venue for **{$event['event_name']}** has not been assigned yet.", $context);
                }
                return $this->responseBuilder->buildText($intent, "**{$event['event_name']}** will be held at **{$venue}**.", $context);

            case 'EVENT_STATUS':
                if (!$event) return $this->responseBuilder->buildText($intent, "Please specify which event you mean.", $context);
                return $this->responseBuilder->buildText($intent, "The current status of **{$event['event_name']}** is: **{$event['status']}**.", $context);

            case 'EVENT_REGISTRATION_STATUS':
                if (!$event) return $this->responseBuilder->buildText($intent, "Please specify which event you mean.", $context);
                $msg = match (true) {
                    $event['status'] === 'Registration Open'   => "Registration for **{$event['event_name']}** is currently **open**! You can register now.",
                    $event['status'] === 'Registration Closed' => "Registration for **{$event['event_name']}** is **closed**.",
                    $event['status'] === 'Completed'           => "**{$event['event_name']}** has already been completed.",
                    default                                    => "Registration status for **{$event['event_name']}**: {$event['status']}.",
                };
                return $this->responseBuilder->buildText($intent, $msg, $context);

            case 'EVENT_REGISTRATION_DEADLINE':
                if (!$event) return $this->responseBuilder->buildText($intent, "Please specify which event you mean.", $context);
                if (empty($event['registration_end'])) {
                    return $this->responseBuilder->buildText($intent, "The registration deadline for **{$event['event_name']}** has not been announced yet.", $context);
                }
                $d = date('d M Y', strtotime($event['registration_end']));
                return $this->responseBuilder->buildText($intent, "Registration for **{$event['event_name']}** closes on **{$d}**.", $context);

            case 'EVENT_TEAM_REQUIREMENTS':
                if (!$event) return $this->responseBuilder->buildText($intent, "Please specify which event you mean.", $context);
                if ($event['participation_type'] === 'Individual') {
                    return $this->responseBuilder->buildText($intent, "**{$event['event_name']}** is an **individual** event — no team needed.", $context);
                }
                return $this->responseBuilder->buildText(
                    $intent,
                    "**{$event['event_name']}** is a **team** event requiring **{$event['min_team_size']}–{$event['max_team_size']} members**.",
                    $context
                );

            case 'EVENT_COORDINATOR':
            case 'FACULTY_COORDINATOR':
                if (!$event) return $this->responseBuilder->buildText($intent, "Please specify which event you mean.", $context);
                $faculty = $this->queryService->getFacultyForEvent((int)$event['symposium_event_id']);
                if (empty($faculty)) {
                    return $this->responseBuilder->buildText($intent, "No faculty coordinator has been assigned to **{$event['event_name']}** yet.", $context);
                }
                $names = implode(', ', array_column($faculty, 'full_name'));
                return $this->responseBuilder->buildText($intent, "The faculty coordinator(s) for **{$event['event_name']}**: **{$names}**.", $context);

            case 'JUDGE_INFORMATION':
                return $this->responseBuilder->buildText($intent, "Judge details are not published to students for fairness reasons.", $context);

            // ── My registrations & team ────────────────────────────────────

            case 'MY_REGISTRATIONS':
                $registrations = $this->queryService->getStudentRegistrations($studentId);
                if (empty($registrations)) {
                    return $this->responseBuilder->buildText($intent, "You haven't registered for any events yet. Browse symposiums and register from the dashboard.", $context);
                }
                $items = array_map(fn($r) => $r['event_name'] . ' — ' . $r['application_status'], $registrations);
                return $this->responseBuilder->buildList($intent, "Here are your registered events:", $items, $context);

            case 'MY_REGISTRATION_STATUS':
            case 'MY_REGISTRATION_DETAILS':
                if (!$event) {
                    // Show status for all registrations
                    $registrations = $this->queryService->getStudentRegistrations($studentId);
                    if (empty($registrations)) {
                        return $this->responseBuilder->buildText($intent, "You haven't registered for any events yet.", $context);
                    }
                    $items = array_map(fn($r) => $r['event_name'] . ': ' . $r['application_status'], $registrations);
                    return $this->responseBuilder->buildList($intent, "Your registration statuses:", $items, $context);
                }
                $reg = $this->queryService->getStudentRegistrationForEvent($studentId, (int)$event['symposium_event_id']);
                if (!$reg) {
                    return $this->responseBuilder->buildText($intent, "You are not registered for **{$event['event_name']}**.", $context);
                }
                return $this->responseBuilder->buildText(
                    $intent,
                    "Your registration status for **{$event['event_name']}**: **{$reg['application_status']}**.",
                    $context
                );

            case 'MY_TEAM':
            case 'MY_TEAM_MEMBERS':
            case 'MY_TEAM_STATUS':
            case 'MY_TEAM_ROLE':
                if (!$event) {
                    return $this->responseBuilder->buildText($intent, "Please specify which event you'd like team details for. Ask 'What events am I registered for?' first.", $context);
                }
                if ($event['participation_type'] === 'Individual') {
                    return $this->responseBuilder->buildText($intent, "**{$event['event_name']}** is an individual event — no team required.", $context);
                }
                $reg = $this->queryService->getStudentRegistrationForEvent($studentId, (int)$event['symposium_event_id']);
                if (!$reg) {
                    return $this->responseBuilder->buildText($intent, "You are not registered for **{$event['event_name']}**.", $context);
                }
                $team = $this->queryService->getTeamForApplication((int)$reg['application_id']);
                if (!$team) {
                    return $this->responseBuilder->buildText($intent, "No team found for your registration in **{$event['event_name']}**.", $context);
                }
                $members = $this->queryService->getTeamMembers((int)$team['team_id']);
                if (empty($members)) {
                    return $this->responseBuilder->buildText($intent, "Your team for **{$event['event_name']}** has no members yet.", $context);
                }
                $names = implode(', ', array_column($members, 'full_name'));
                return $this->responseBuilder->buildText($intent, "Your team for **{$event['event_name']}**: **{$names}**.", $context);

            // ── Notifications ──────────────────────────────────────────────

            case 'MY_NOTIFICATIONS':
                $notifs = $this->queryService->getStudentNotifications($studentId, 5);
                if (empty($notifs)) {
                    return $this->responseBuilder->buildText($intent, "You have no new notifications.", $context);
                }
                $items = array_map(fn($n) => $n['notification_title'] ?? $n['notification_message'] ?? 'Notification', $notifs);
                return $this->responseBuilder->buildList($intent, "Your recent notifications:", $items, $context);

            // ── Navigation / Account ───────────────────────────────────────

            case 'NAVIGATION':
                return $this->responseBuilder->buildText(
                    $intent,
                    "You can navigate using the top menu:\n• **Symposiums** — Browse and view all symposiums\n• **My Registrations** — View your registered events\n• **Dashboard** — Overview of your activity",
                    $context
                );

            case 'ACCOUNT_INFORMATION':
                return $this->responseBuilder->buildText(
                    $intent,
                    "Your account details are visible on your Student Dashboard profile. You can see your register number, year, semester, and department there.",
                    $context
                );

            default:
                return $this->responseBuilder->buildText(
                    $intent,
                    "I'm not sure how to answer that. Try asking about symposiums, events, schedules, or registrations. Type **help** for a list of things I can do.",
                    $context
                );
        }
    }
}
