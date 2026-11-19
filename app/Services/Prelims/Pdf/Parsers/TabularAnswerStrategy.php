<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\Parsers;

use App\Services\Prelims\Pdf\Contracts\AnswerStrategyInterface;
use App\Services\Prelims\Pdf\DTO\ExtractedDocumentDTO;
use App\Services\Prelims\Pdf\DTO\ParsedQuestionDTO;

/**
 * Extracts tabular answer keys anywhere in the document, supporting:
 *   1. Pipe / tab / space-separated pair rows:  "1 | A | 2 | C | 3 | B"  or  "1   A   6   D"
 *   2. Transposed horizontal table rows:
 *        Q.No  1  2  3  4  5
 *        Ans   A  C  B  D  A
 *   3. Multi-column grids with or without an Answer Key header.
 */
final class TabularAnswerStrategy implements AnswerStrategyInterface
{
    public function getName(): string
    {
        return 'Tabular';
    }

    /**
     * @param ParsedQuestionDTO[] $questions
     */
    public function apply(array &$questions, ExtractedDocumentDTO $doc): int
    {
        $unresolvedCount = 0;
        foreach ($questions as $q) {
            if ($q->correctLabel === null) {
                $unresolvedCount++;
            }
        }
        if ($unresolvedCount === 0) {
            return 0;
        }

        $pairs = $this->extractTabularPairs($doc);
        if (empty($pairs)) {
            return 0;
        }

        $resolved = 0;
        foreach ($questions as $q) {
            if ($q->correctLabel === null && isset($pairs[$q->sourceQuestionNo])) {
                $q->setAnswer($pairs[$q->sourceQuestionNo], 'Tabular', 'High');
                if ($q->correctLabel !== null) {
                    $resolved++;
                }
            }
        }

        return $resolved;
    }

    /**
     * Parse tabular question-number -> answer mappings directly from raw text or lines.
     *
     * @return array<int, string>
     */
    public function extractPairsFromRawText(string $rawText): array
    {
        $doc = \App\Services\Prelims\Pdf\Extractors\SmalotPdfExtractor::fromOcrPageTexts([$rawText]);
        return $this->extractTabularPairs($doc);
    }

    /**
     * @return array<int, string>
     */
    private function extractTabularPairs(ExtractedDocumentDTO $doc): array
    {
        $pairs      = [];
        $lines      = $doc->lines;
        $lineCount  = count($lines);
        $inTableCtx = false;

        for ($i = 0; $i < $lineCount; $i++) {
            $text = trim($lines[$i]->text);
            if ($text === '') {
                continue;
            }

            // Detect tabular header: "Q.No  Ans", "Question No | Answer", "QNo   Key"
            if (preg_match('/(?:Q\.?\s*No\.?|Question\s*(?:No\.?|#)|S\.?\s*No\.?)\s*[|\t ]+\s*(?:Ans(?:wer)?s?|Key|Option)/iu', $text)) {
                $inTableCtx = true;
                continue;
            }

            // Pattern A: Transposed horizontal rows:
            //   Row i:   "Q.No  1  2  3  4  5"  OR  "1  2  3  4  5"
            //   Row i+1: "Ans   A  C  B  D  A"  OR  "A  C  B  D  A"
            if ($i + 1 < $lineCount) {
                $nums = $this->extractHorizontalNumberRow($text);
                if (count($nums) >= 2) {
                    $nextText = trim($lines[$i + 1]->text);
                    $letters  = $this->extractHorizontalAnswerRow($nextText);
                    if (count($letters) === count($nums)) {
                        foreach ($nums as $k => $qNo) {
                            $pairs[$qNo] = $letters[$k];
                        }
                        $i++; // consume paired answer row
                        continue;
                    }
                }
            }

            // Pattern B: Pipe-delimited or multi-column space/tab pair rows:
            //   "1 | A | 2 | B | 3 | C"  or  "1   A   11   C"  or  "1. A   2. B   3. C"
            $rowPairs = $this->extractPairCellsFromRow($text, $inTableCtx);
            if (!empty($rowPairs)) {
                foreach ($rowPairs as $qNo => $ans) {
                    $pairs[$qNo] = $ans;
                }
            } elseif ($inTableCtx && mb_strlen($text) > 55 && !preg_match('/^\d+\s*[|.\-:\s]+\(?[A-Fa-f]\)?/', $text)) {
                $inTableCtx = false;
            }
        }

        return $pairs;
    }

    /**
     * @return int[]
     */
    private function extractHorizontalNumberRow(string $line): array
    {
        $clean = preg_replace('/^(?:Q\.?\s*No\.?|Question\s*No\.?|No\.?)\s*[:|\t ]*/iu', '', $line) ?? $line;
        $tokens = preg_split('/[\s|,]+/u', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokens) < 2) {
            return [];
        }

        $nums = [];
        foreach ($tokens as $tok) {
            if (!preg_match('/^\d{1,4}$/', $tok)) {
                return [];
            }
            $nums[] = (int) $tok;
        }
        return $nums;
    }

    /**
     * @return string[]
     */
    private function extractHorizontalAnswerRow(string $line): array
    {
        $clean = preg_replace('/^(?:Ans(?:wer)?s?|Key|Option)\s*[:|\t ]*/iu', '', $line) ?? $line;
        $tokens = preg_split('/[\s|,]+/u', trim($clean), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokens) < 2) {
            return [];
        }

        $letters = [];
        foreach ($tokens as $tok) {
            if (!preg_match('/^\(?([A-Fa-f])\)?$/', $tok, $m)) {
                return [];
            }
            $letters[] = strtoupper($m[1]);
        }
        return $letters;
    }

    /**
     * @return array<int, string>
     */
    private function extractPairCellsFromRow(string $line, bool $inTableCtx): array
    {
        $pairs = [];

        // Pipe-delimited table row: "| 1 | A | 2 | B |"
        if (str_contains($line, '|')) {
            $cells = array_values(array_filter(
                array_map('trim', explode('|', $line)),
                static fn(string $c): bool => $c !== ''
            ));
            if (count($cells) >= 2 && count($cells) % 2 === 0) {
                $allPairsValid = true;
                $tmp = [];
                for ($c = 0; $c < count($cells); $c += 2) {
                    if (preg_match('/^(?:Q\.?\s*)?(\d{1,4})\.?$/i', $cells[$c], $nm)
                        && preg_match('/^\(?([A-Fa-f])\)?$/', $cells[$c + 1], $am)
                    ) {
                        $tmp[(int) $nm[1]] = strtoupper($am[1]);
                    } else {
                        $allPairsValid = false;
                        break;
                    }
                }
                if ($allPairsValid) {
                    return $tmp;
                }
            }
        }

        // Space/tab-separated pairs: "1   A   6   C" or "1. A   2. B   3. C"
        if (preg_match_all('/(?:^|[\s,;])(?:Q\.?\s*)?(\d{1,4})\s*[.):\-]?\s+\(?([A-Fa-f])\)?(?=\s|$|[,;])/u', $line, $ms, PREG_SET_ORDER)) {
            // Require either >= 2 pairs on the line OR active table header context to avoid false positives on prose
            if (count($ms) >= 2 || ($inTableCtx && count($ms) === 1)) {
                // Ensure the line consists almost entirely of these pairs (not a normal sentence)
                $remainder = trim(preg_replace('/(?:Q\.?\s*)?\d{1,4}\s*[.):\-]?\s+\(?[A-Fa-f]\)?/u', '', $line) ?? '');
                $remainder = trim($remainder, " \t|,;.-");
                if ($remainder === '') {
                    foreach ($ms as $m) {
                        $qNo = (int) $m[1];
                        if ($qNo > 0) {
                            $pairs[$qNo] = strtoupper($m[2]);
                        }
                    }
                }
            }
        }

        return $pairs;
    }
}
