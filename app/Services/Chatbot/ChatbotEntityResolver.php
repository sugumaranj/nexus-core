<?php

declare(strict_types=1);

namespace App\Services\Chatbot;

final class ChatbotEntityResolver
{
    private const ALIAS_DICTIONARY = [
        'paper'   => 'Paper Presentation',
        'project' => 'Project Presentation',
        'hack'    => 'Hackathon',
        'quiz'    => 'Technical Quiz',
        'bgmi'    => 'BGMI Tournament',
        'freefire'=> 'Free Fire Tournament',
        'dance'   => 'Group Dance',
    ];

    /**
     * @param string $hint The extracted entity hint string
     * @param array $candidates Array of event records to search within
     * @param int|null $contextEventId Fallback from context
     * @param ChatbotNormalizer $normalizer
     * @return array ['status' => 'MATCH|AMBIGUOUS|NOT_FOUND|CONTEXT', 'event' => array|null, 'candidates' => array]
     */
    public function resolveEvent(string $hint, array $candidates, ?int $contextEventId, ChatbotNormalizer $normalizer): array
    {
        if (empty(trim($hint))) {
            if ($contextEventId !== null) {
                foreach ($candidates as $event) {
                    if ((int)$event['symposium_event_id'] === $contextEventId || (int)$event['event_id'] === $contextEventId) {
                        return ['status' => 'CONTEXT', 'event' => $event, 'candidates' => []];
                    }
                }
            }
            return ['status' => 'NOT_FOUND', 'event' => null, 'candidates' => []];
        }

        $hintLower = strtolower(trim($hint));
        $hintNorm  = $normalizer->normalize($hint);

        // Level 1: Exact name match (case-insensitive)
        $matches = [];
        foreach ($candidates as $event) {
            if (strtolower(trim($event['event_name'])) === $hintLower) {
                $matches[] = $event;
            }
        }
        if (count($matches) === 1) return ['status' => 'MATCH', 'event' => $matches[0], 'candidates' => []];
        if (count($matches) > 1) return ['status' => 'AMBIGUOUS', 'event' => null, 'candidates' => $matches];

        // Level 2: Normalized match
        $matches = [];
        foreach ($candidates as $event) {
            if ($normalizer->normalize($event['event_name']) === $hintNorm) {
                $matches[] = $event;
            }
        }
        if (count($matches) === 1) return ['status' => 'MATCH', 'event' => $matches[0], 'candidates' => []];
        if (count($matches) > 1) return ['status' => 'AMBIGUOUS', 'event' => null, 'candidates' => $matches];

        // Level 3: Event code match
        $matches = [];
        foreach ($candidates as $event) {
            if (strtolower(trim($event['event_code'] ?? '')) === $hintLower) {
                $matches[] = $event;
            }
        }
        if (count($matches) === 1) return ['status' => 'MATCH', 'event' => $matches[0], 'candidates' => []];
        if (count($matches) > 1) return ['status' => 'AMBIGUOUS', 'event' => null, 'candidates' => $matches];

        // Level 4: Alias Dictionary match
        if (isset(self::ALIAS_DICTIONARY[$hintLower])) {
            $aliasTarget = strtolower(self::ALIAS_DICTIONARY[$hintLower]);
            $matches = [];
            foreach ($candidates as $event) {
                if (strtolower(trim($event['event_name'])) === $aliasTarget) {
                    $matches[] = $event;
                }
            }
            if (count($matches) === 1) return ['status' => 'MATCH', 'event' => $matches[0], 'candidates' => []];
            if (count($matches) > 1) return ['status' => 'AMBIGUOUS', 'event' => null, 'candidates' => $matches];
        }
        
        // Level 5: Substring match (Only if strictly unique)
        $matches = [];
        foreach ($candidates as $event) {
            $eventNameNorm = $normalizer->normalize($event['event_name']);
            if (strpos($eventNameNorm, $hintNorm) !== false || strpos($hintNorm, $eventNameNorm) !== false) {
                $matches[] = $event;
            }
        }
        if (count($matches) === 1) return ['status' => 'MATCH', 'event' => $matches[0], 'candidates' => []];
        if (count($matches) > 1) return ['status' => 'AMBIGUOUS', 'event' => null, 'candidates' => $matches];

        // Level 6: Context fallback (if hint completely missed, but we have a context event)
        // We only do this if the hint was a common word that might have bypassed extraction, 
        // but typically if we got here and had a hint, the hint is invalid.
        // Returning NOT_FOUND forces clarification.
        
        return ['status' => 'NOT_FOUND', 'event' => null, 'candidates' => []];
    }
    
    /**
     * @param string $hint The extracted symposium hint
     * @param array $candidates Array of symposium records to search within
     * @param int|null $contextSymposiumId
     * @param ChatbotNormalizer $normalizer
     * @return array
     */
    public function resolveSymposium(string $hint, array $candidates, ?int $contextSymposiumId, ChatbotNormalizer $normalizer): array
    {
        if (empty(trim($hint))) {
            if ($contextSymposiumId !== null) {
                foreach ($candidates as $symp) {
                    if ((int)$symp['symposium_id'] === $contextSymposiumId) {
                        return ['status' => 'CONTEXT', 'symposium' => $symp, 'candidates' => []];
                    }
                }
            }
            return ['status' => 'NOT_FOUND', 'symposium' => null, 'candidates' => []];
        }

        $hintLower = strtolower(trim($hint));
        $hintNorm  = $normalizer->normalize($hint);

        // Level 1: Exact title match
        $matches = [];
        foreach ($candidates as $symp) {
            if (strtolower(trim($symp['title'])) === $hintLower) {
                $matches[] = $symp;
            }
        }
        if (count($matches) === 1) return ['status' => 'MATCH', 'symposium' => $matches[0], 'candidates' => []];
        if (count($matches) > 1) return ['status' => 'AMBIGUOUS', 'symposium' => null, 'candidates' => $matches];

        // Level 2: Code match
        $matches = [];
        foreach ($candidates as $symp) {
            if (strtolower(trim($symp['symposium_code'])) === $hintLower) {
                $matches[] = $symp;
            }
        }
        if (count($matches) === 1) return ['status' => 'MATCH', 'symposium' => $matches[0], 'candidates' => []];
        if (count($matches) > 1) return ['status' => 'AMBIGUOUS', 'symposium' => null, 'candidates' => $matches];

        // Level 3: Normalized substring match
        $matches = [];
        foreach ($candidates as $symp) {
            $titleNorm = $normalizer->normalize($symp['title']);
            if (strpos($titleNorm, $hintNorm) !== false || strpos($hintNorm, $titleNorm) !== false) {
                $matches[] = $symp;
            }
        }
        if (count($matches) === 1) return ['status' => 'MATCH', 'symposium' => $matches[0], 'candidates' => []];
        if (count($matches) > 1) return ['status' => 'AMBIGUOUS', 'symposium' => null, 'candidates' => $matches];
        
        return ['status' => 'NOT_FOUND', 'symposium' => null, 'candidates' => []];
    }
}
