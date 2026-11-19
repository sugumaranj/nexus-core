<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\Parsers;

use App\Services\Prelims\Pdf\DTO\ExtractedLineDTO;

final class TopicDetector
{
    /**
     * Detect whether a visual line represents a Topic / Unit / Section / Part heading
     * inside a single PDF so equal weightage can be maintained per topic.
     */
    public function detectTopicHeading(ExtractedLineDTO $line, float $medianFontSize = 10.0): ?string
    {
        $text = trim($line->text);
        if ($text === '' || mb_strlen($text) < 3 || mb_strlen($text) > 120) {
            return null;
        }

        // Never mistake a question, option, or answer-key heading for a topic
        if ($this->isAnswerKeyHeading($text)) {
            return null;
        }
        if (preg_match('/^(?:Q\.?\s*\d+|\d+\s*[.)\]]|\(\d+\)|[A-Fa-f]\s*[.)\]]|\([A-Fa-f]\))/u', $text)) {
            return null;
        }
        if (preg_match('/^(?:Ans(?:wer)?s?|Correct\s+Answer|Key|Explanation)\s*[:.\-]/iu', $text)) {
            return null;
        }

        // Explicit prefix patterns: Topic 1: ..., Unit II - ..., Section A: ..., Part 2: ..., Module 1: ...
        if (preg_match('/^(?:Topic|Unit|Section|Part|Module|Chapter|Category|Subject|Domain)\s*[A-Z0-9IVX]*\s*[:.\-–—]\s*(.+)$/iu', $text, $m)) {
            $clean = trim($m[1], " \t\n\r\0\x0B:-–—");
            return $clean !== '' ? $this->normalizeTopicName($clean) : $this->normalizeTopicName($text);
        }

        // Standalone "Topic 1" / "Section A" without subtitle
        if (preg_match('/^(?:Topic|Unit|Section|Part|Module|Chapter)\s+[A-Z0-9IVX]+$/iu', $text)) {
            return $this->normalizeTopicName($text);
        }

        // Larger-font bold heading (e.g., 14pt bold "DATA STRUCTURES" when body is 10pt)
        $isLargeBold = ($line->avgFontSize >= ($medianFontSize + 1.8) && $line->boldRatio >= 0.7);
        $isShortAllCaps = (
            mb_strlen($text) >= 4
            && mb_strlen($text) <= 60
            && $text === mb_strtoupper($text, 'UTF-8')
            && preg_match('/[A-Z]{3,}/', $text)
            && !str_contains($text, '?')
            && !preg_match('/^(?:PAGE|QUIZ|TEST|EXAM|PRELIMS|INSTRUCTIONS|NOTE|TIME|MARKS|TOTAL)\b/i', $text)
        );

        if (($isLargeBold || ($isShortAllCaps && $line->boldRatio >= 0.6)) && !str_ends_with($text, '?')) {
            return $this->normalizeTopicName($text);
        }

        return null;
    }

    public function isAnswerKeyHeading(string $text): bool
    {
        $t = trim($text);
        // Never treat an inline per-question answer ("Answer: B", "Ans: (C)", "Key - D") as a section heading
        if (preg_match('/^(?:Ans(?:wer)?|Correct\s*Answer|Right\s*Answer|Key)\s*[:.\-–—]\s*\(?[A-Fa-f]\)?(?:\s*[-–—.(].*)?$/iu', $t)) {
            return false;
        }
        return (bool) preg_match(
            '/^(?:Answer\s*Key|Answers?\s*Sheet|Correct\s*Answers?|Answers|Key\s*to\s*Questions|Solutions?|Marking\s*Key)\b(?:\s*[:.\-–—(].*)?$/iu',
            $t
        );
    }

    private function normalizeTopicName(string $raw): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
        return mb_substr($clean, 0, 150);
    }
}
