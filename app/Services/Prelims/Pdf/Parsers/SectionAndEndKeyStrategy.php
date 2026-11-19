<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\Parsers;

use App\Services\Prelims\Pdf\Contracts\AnswerStrategyInterface;
use App\Services\Prelims\Pdf\DTO\ExtractedDocumentDTO;
use App\Services\Prelims\Pdf\DTO\ParsedQuestionDTO;

/**
 * Locates Answer Key blocks ANYWHERE inside the document — whether at the end,
 * in the middle, or repeated per section/topic (e.g., Topic 1 Q1..10 + Key, Topic 2 Q1..10 + Key).
 */
final class SectionAndEndKeyStrategy implements AnswerStrategyInterface
{
    private TopicDetector $topicDetector;

    public function __construct(?TopicDetector $topicDetector = null)
    {
        $this->topicDetector = $topicDetector ?? new TopicDetector();
    }

    public function getName(): string
    {
        return 'Section_Key';
    }

    /**
     * @param ParsedQuestionDTO[] $questions
     */
    public function apply(array &$questions, ExtractedDocumentDTO $doc): int
    {
        $keyBlocks = $this->extractKeyBlocks($doc);
        if (empty($keyBlocks)) {
            return 0;
        }

        $resolved = 0;
        $totalBlocks = count($keyBlocks);

        // Index questions by sectionIndex -> sourceQuestionNo AND globally by sourceQuestionNo
        $bySection = [];
        $byGlobal  = [];
        foreach ($questions as $idx => $q) {
            $bySection[$q->sectionIndex][$q->sourceQuestionNo][] = $idx;
            $byGlobal[$q->sourceQuestionNo][]                    = $idx;
        }

        foreach ($keyBlocks as $blockIdx => $block) {
            $isLastBlockAtEnd = ($blockIdx === $totalBlocks - 1) && $block['at_end'];
            $sourceType       = $isLastBlockAtEnd ? 'End_Key' : 'Section_Key';
            $targetSection    = $block['preceding_section'];

            foreach ($block['pairs'] as $qNo => $ansLabel) {
                // First try matching questions belonging to the preceding section (handles mid-doc per-section keys!)
                $matched = false;
                if (!$isLastBlockAtEnd && isset($bySection[$targetSection][$qNo])) {
                    foreach ($bySection[$targetSection][$qNo] as $idx) {
                        if ($questions[$idx]->correctLabel === null) {
                            $questions[$idx]->setAnswer($ansLabel, $sourceType, 'High');
                            if ($questions[$idx]->correctLabel !== null) {
                                $resolved++;
                                $matched = true;
                            }
                        }
                    }
                }

                // Fallback: match globally by question number (handles continuous numbering across sections or end key)
                if (!$matched && isset($byGlobal[$qNo])) {
                    foreach ($byGlobal[$qNo] as $idx) {
                        if ($questions[$idx]->correctLabel === null) {
                            $questions[$idx]->setAnswer($ansLabel, $sourceType, 'High');
                            if ($questions[$idx]->correctLabel !== null) {
                                $resolved++;
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $resolved;
    }

    /**
     * Extract all Answer Key blocks anywhere in the document with their preceding section index.
     *
     * @return array<int, array{preceding_section: int, at_end: bool, pairs: array<int, string>}>
     */
    private function extractKeyBlocks(ExtractedDocumentDTO $doc): array
    {
        $blocks          = [];
        $inKey           = false;
        $currentPairs    = [];
        $currentSection  = 0;
        $medianFontSize  = 10.0;
        $totalLines      = count($doc->lines);

        if ($totalLines > 0) {
            $sizes = array_map(static fn($l): float => $l->avgFontSize, $doc->lines);
            sort($sizes);
            $medianFontSize = $sizes[intdiv($totalLines, 2)] ?? 10.0;
        }

        foreach ($doc->lines as $lineIdx => $line) {
            $text = trim($line->text);
            if ($text === '') {
                continue;
            }

            if ($this->topicDetector->isAnswerKeyHeading($text)) {
                if ($inKey && !empty($currentPairs)) {
                    $blocks[] = [
                        'preceding_section' => $currentSection,
                        'at_end'            => false,
                        'pairs'             => $currentPairs,
                    ];
                }
                $inKey        = true;
                $currentPairs = [];
                continue;
            }

            if ($this->topicDetector->detectTopicHeading($line, $medianFontSize) !== null) {
                if ($inKey && !empty($currentPairs)) {
                    $blocks[] = [
                        'preceding_section' => $currentSection,
                        'at_end'            => false,
                        'pairs'             => $currentPairs,
                    ];
                    $currentPairs = [];
                }
                $inKey = false;
                $currentSection++;
                continue;
            }

            if (!$inKey) {
                continue;
            }

            // If a full question begins after a mid-document key, close this key block
            if (preg_match('/^(?:Question\s+|Q\.?\s*)?\d{1,4}\s*[.)\]:\-]\s+.{18,}$/iu', $text)
                && !preg_match('/^\d{1,4}\s*[.)\-:|]\s*\(?[A-Fa-f]\)?(?:\s*[-–—:(].*)?$/u', $text)
            ) {
                if (!empty($currentPairs)) {
                    $blocks[] = [
                        'preceding_section' => $currentSection,
                        'at_end'            => false,
                        'pairs'             => $currentPairs,
                    ];
                    $currentPairs = [];
                }
                $inKey = false;
                $currentSection++;
                continue;
            }

            // Parse single or multiple pairs on a line:
            // "1. B", "Q1: (C)", "1 - A   2 - D   3 - B", "1. (B) Because TCP...", "1 = A", "1: Option B"
            if (preg_match_all('/(?:^|[\s,|;])(?:Q\.?\s*)?(\d{1,4})\s*[.)\-:|:=]?\s*(?:Option\s*)?[(\[]?([A-Fa-f])[)\]]?(?=\s|$|[,;|.)\-])/iu', $text, $ms, PREG_SET_ORDER)) {
                foreach ($ms as $m) {
                    $qNo = (int) $m[1];
                    $ans = strtoupper($m[2]);
                    if ($qNo > 0) {
                        $currentPairs[$qNo] = $ans;
                    }
                }
            }
        }

        if ($inKey && !empty($currentPairs)) {
            $blocks[] = [
                'preceding_section' => $currentSection,
                'at_end'            => true,
                'pairs'             => $currentPairs,
            ];
        }

        return $blocks;
    }
}
