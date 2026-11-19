<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\Parsers;

use App\Services\Prelims\Pdf\DTO\ExtractedDocumentDTO;
use App\Services\Prelims\Pdf\DTO\ExtractedLineDTO;
use App\Services\Prelims\Pdf\DTO\ParsedQuestionDTO;

final class QuestionBlockSegmenter
{
    private TopicDetector $topicDetector;

    public function __construct(?TopicDetector $topicDetector = null)
    {
        $this->topicDetector = $topicDetector ?? new TopicDetector();
    }

    /**
     * Segment an ExtractedDocumentDTO into ParsedQuestionDTO objects, preserving
     * section boundaries, detected topics, and bold-option flags.
     *
     * @return array{questions: ParsedQuestionDTO[], topics: string[]}
     */
    public function segment(ExtractedDocumentDTO $doc, string $defaultTopic = 'General'): array
    {
        $medianFontSize = $this->computeMedianFontSize($doc->lines);
        $currentTopic   = $defaultTopic;
        $sectionIndex   = 0;
        $inAnswerBlock  = false;

        /** @var ParsedQuestionDTO[] $questions */
        $questions = [];
        /** @var ParsedQuestionDTO|null $currentQ */
        $currentQ  = null;
        $currentOptionLabel = null;
        $topicsSeen = [];

        foreach ($doc->lines as $line) {
            $text = trim($line->text);
            if ($text === '') {
                continue;
            }

            // Skip page footers like "Page 1 of 4" or standalone page numbers
            if (preg_match('/^(?:Page\s+\d+(?:\s*(?:of|\/)\s*\d+)?|\-\s*\d+\s*\-)$/i', $text)) {
                continue;
            }

            // Detect Answer Key heading (may occur in middle OR end of PDF!)
            if ($this->topicDetector->isAnswerKeyHeading($text)) {
                if ($currentQ !== null) {
                    $questions[] = $currentQ;
                    $currentQ    = null;
                    $currentOptionLabel = null;
                }
                $inAnswerBlock = true;
                continue;
            }

            // Detect Topic / Section heading (also exits a mid-document answer key block!)
            $detectedTopic = $this->topicDetector->detectTopicHeading($line, $medianFontSize);
            if ($detectedTopic !== null) {
                if ($currentQ !== null) {
                    $questions[] = $currentQ;
                    $currentQ    = null;
                    $currentOptionLabel = null;
                }
                $currentTopic  = $detectedTopic;
                $sectionIndex++;
                $inAnswerBlock = false;
                continue;
            }

            // If we're inside an Answer Key block, check whether a NEW full question starts (exiting key block)
            if ($inAnswerBlock) {
                if ($this->looksLikeFullQuestionStart($text)) {
                    $inAnswerBlock = false;
                    $sectionIndex++;
                } else {
                    continue;
                }
            }

            // Skip tabular answer key rows even if there wasn't an explicit header
            if ($this->looksLikePureAnswerKeyRow($text) && $currentQ === null) {
                continue;
            }

            // Check for new numbered Question start: "1.", "1)", "(1)", "Q1.", "Q.1", "Question 1:"
            if ($this->matchQuestionStart($text, $qNo, $qBody)) {
                // Guard: don't misread short answer key line "1. A" as a question
                if (preg_match('/^\(?[A-Fa-f]\)?$/', trim($qBody))) {
                    continue;
                }

                if ($currentQ !== null) {
                    $questions[] = $currentQ;
                }

                $currentOptionLabel = null;
                $inlineOptions = $this->extractInlineOptionsFromText($qBody, $line);

                if (!empty($inlineOptions['options'])) {
                    $qBodyClean = $inlineOptions['prefix'];
                    $currentQ   = new ParsedQuestionDTO(
                        sourceQuestionNo: $qNo,
                        topicName: $currentTopic,
                        sectionIndex: $sectionIndex,
                        questionText: trim($qBodyClean),
                        options: $inlineOptions['options'],
                    );
                    $keys = array_keys($inlineOptions['options']);
                    $currentOptionLabel = end($keys) ?: null;
                } else {
                    $currentQ = new ParsedQuestionDTO(
                        sourceQuestionNo: $qNo,
                        topicName: $currentTopic,
                        sectionIndex: $sectionIndex,
                        questionText: trim($qBody),
                        options: [],
                    );
                }

                $topicsSeen[$currentTopic] = true;
                continue;
            }

            if ($currentQ === null) {
                continue;
            }

            // Check for inline answer marker right after options (keep text attached for InlineAnswerStrategy to parse & strip)
            if (preg_match('/^(?:Ans(?:wer)?|Correct\s*(?:Answer|Option)|Right\s*Answer|Key)\s*(?:is\s*)?[:.\-–—=]?\s*(?:Option\s*)?[(\[]?([A-Fa-f])[)\]\.]?(?:\s*[:.\-–—]|\s+|$)/iu', $text, $ansM)) {
                $currentQ->setAnswer(strtoupper($ansM[1]), 'Inline', 'High');
                $currentOptionLabel = null;
                continue;
            }

            // Check for one or more options on this line (e.g., "A) Foo" or "(A) Foo   (B) Bar")
            $parsedLineOpts = $this->extractInlineOptionsFromText($text, $line);
            if (!empty($parsedLineOpts['options'])) {
                if ($parsedLineOpts['prefix'] !== '' && empty($currentQ->options)) {
                    $currentQ->questionText = trim($currentQ->questionText . ' ' . $parsedLineOpts['prefix']);
                }
                foreach ($parsedLineOpts['options'] as $lbl => $optData) {
                    $currentQ->options[$lbl] = $optData;
                    $currentOptionLabel      = $lbl;
                }
                continue;
            }

            // Continuation line: append to current option or to question stem
            if ($currentOptionLabel !== null && isset($currentQ->options[$currentOptionLabel])) {
                $currentQ->options[$currentOptionLabel]['text'] = trim(
                    $currentQ->options[$currentOptionLabel]['text'] . ' ' . $text
                );
                if ($line->boldRatio >= 0.75) {
                    $currentQ->options[$currentOptionLabel]['is_bold'] = true;
                }
            } else {
                $currentQ->questionText = trim($currentQ->questionText . ' ' . $text);
            }
        }

        if ($currentQ !== null) {
            $questions[] = $currentQ;
        }

        // Filter out malformed blocks with < 2 options and clean up inline markers & recompute hashes
        $validQuestions = [];
        foreach ($questions as $q) {
            if (count($q->options) < 2 || trim($q->questionText) === '') {
                continue;
            }
            $q->questionText = $this->cleanText($q->questionText);
            foreach ($q->options as $lbl => $opt) {
                $q->options[$lbl]['text'] = $this->cleanText($opt['text']);
            }
            $q->questionHash = ParsedQuestionDTO::computeHash($q->questionText);
            $validQuestions[] = $q;
        }

        return [
            'questions' => $validQuestions,
            'topics'    => array_keys($topicsSeen) ?: [$defaultTopic],
        ];
    }

    private function matchQuestionStart(string $text, ?int &$qNo, ?string &$qBody): bool
    {
        if (preg_match('/^(?:Question\s+|Q\.?\s*)?(\d{1,4})\s*[.)\]:\-]\s+(.+)$/iu', $text, $m)) {
            $qNo   = (int) $m[1];
            $qBody = trim($m[2]);
            return $qNo > 0;
        }
        if (preg_match('/^\((\d{1,4})\)\s+(.+)$/u', $text, $m)) {
            $qNo   = (int) $m[1];
            $qBody = trim($m[2]);
            return $qNo > 0;
        }
        return false;
    }

    private function looksLikeFullQuestionStart(string $text): bool
    {
        if (!$this->matchQuestionStart($text, $qNo, $qBody)) {
            return false;
        }
        // A real question body is longer than a 1-letter answer key entry
        return mb_strlen($qBody) >= 12 && !preg_match('/^\(?[A-Fa-f]\)?(?:\s+\d+\s*[.)\-:\s]+\(?[A-Fa-f]\)?)+$/u', $qBody);
    }

    private function looksLikePureAnswerKeyRow(string $text): bool
    {
        return (bool) preg_match('/^(?:\d{1,4}\s*[.)\-:|]\s*\(?[A-Fa-f]\)?\s*[|,\s]*){2,}$/u', $text);
    }

    /**
     * Extract 1 or multiple options from a line (supports "A. Foo", "(a) Foo   (b) Bar", "*B) Foo").
     *
     * @return array{prefix: string, options: array<string, array{text: string, is_bold: bool}>}
     */
    private function extractInlineOptionsFromText(string $text, ExtractedLineDTO $line): array
    {
        // Match option markers at start of string OR preceded by 2+ spaces / tab
        $pattern = '/(?:^|\s{2,}|\t)([*✓✔]?\s*(?:\(([A-Fa-f])\)|\[([A-Fa-f])\]|([A-Fa-f])\s*[.)\]:\-]))\s+/u';
        if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return ['prefix' => $text, 'options' => []];
        }

        $count = count($matches[0]);
        if ($count === 0) {
            return ['prefix' => $text, 'options' => []];
        }

        // Guard: if there's only 1 match and it's mid-sentence (not at offset 0 and not preceded by 2+ spaces), skip
        $firstOffset = (int) $matches[0][0][1];
        $prefix      = trim(substr($text, 0, $firstOffset));
        $options     = [];

        for ($i = 0; $i < $count; $i++) {
            $fullMarker  = (string) $matches[0][$i][0];
            $markerStart = (int) $matches[0][$i][1];
            $valStart    = $markerStart + strlen($fullMarker);
            $valEnd      = ($i + 1 < $count) ? (int) $matches[0][$i + 1][1] : strlen($text);

            $label = strtoupper((string) ($matches[2][$i][0] !== '' ? $matches[2][$i][0] : ($matches[3][$i][0] !== '' ? $matches[3][$i][0] : $matches[4][$i][0])));
            $rawVal = trim(substr($text, $valStart, $valEnd - $valStart));

            // Keep leading asterisk/checkmark if present so InlineAnswerStrategy can detect it
            $starPrefix = str_contains($fullMarker, '*') || str_contains($fullMarker, '✓') || str_contains($fullMarker, '✔')
                ? '* '
                : '';

            $isBold = $this->isOptionBoldOnLine($rawVal, $label, $line, $count === 1);

            $options[$label] = [
                'text'    => $starPrefix . $rawVal,
                'is_bold' => $isBold,
            ];
        }

        return [
            'prefix'  => $prefix,
            'options' => $options,
        ];
    }

    /**
     * Determine whether an option's text fragments on this line are bold.
     */
    private function isOptionBoldOnLine(string $optText, string $label, ExtractedLineDTO $line, bool $isSingleOptionLine): bool
    {
        if (empty($line->fragments)) {
            return false;
        }

        if ($isSingleOptionLine) {
            // Check fragments excluding the option label prefix itself (some PDFs bold only "A)" on all options)
            $bodyBold  = 0;
            $bodyTotal = 0;
            foreach ($line->fragments as $frag) {
                $t = trim($frag->text);
                if ($t === '' || preg_match('/^\(?[A-Fa-f]\)?[.)\]]?$/u', $t)) {
                    continue;
                }
                $len = mb_strlen(preg_replace('/\s+/u', '', $t) ?? '');
                $bodyTotal += $len;
                if ($frag->isBold) {
                    $bodyBold += $len;
                }
            }
            if ($bodyTotal > 0) {
                return ($bodyBold / $bodyTotal) >= 0.65;
            }
            return $line->boldRatio >= 0.7;
        }

        // Multi-option horizontal line: match fragments whose text occurs inside $optText
        $needleNorm = mb_strtolower(trim($optText));
        if ($needleNorm === '') {
            return false;
        }

        $matchedBold  = 0;
        $matchedTotal = 0;
        foreach ($line->fragments as $frag) {
            $ft = mb_strtolower(trim($frag->text));
            if ($ft === '' || preg_match('/^\(?[a-f]\)?[.)\]]?$/u', $ft)) {
                continue;
            }
            if (str_contains($needleNorm, $ft)) {
                $len = mb_strlen(preg_replace('/\s+/u', '', $ft) ?? '');
                $matchedTotal += $len;
                if ($frag->isBold) {
                    $matchedBold += $len;
                }
            }
        }

        return $matchedTotal > 0 && ($matchedBold / $matchedTotal) >= 0.65;
    }

    /**
     * @param ExtractedLineDTO[] $lines
     */
    private function computeMedianFontSize(array $lines): float
    {
        if (empty($lines)) {
            return 10.0;
        }
        $sizes = array_map(static fn(ExtractedLineDTO $l): float => $l->avgFontSize, $lines);
        sort($sizes);
        $mid = intdiv(count($sizes), 2);
        return $sizes[$mid] ?? 10.0;
    }

    private function cleanText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
