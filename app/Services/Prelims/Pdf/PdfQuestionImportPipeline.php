<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf;

use App\Services\Prelims\Pdf\Contracts\AnswerStrategyInterface;
use App\Services\Prelims\Pdf\DTO\ExtractedDocumentDTO;
use App\Services\Prelims\Pdf\DTO\ParsedQuestionDTO;
use App\Services\Prelims\Pdf\Extractors\SmalotPdfExtractor;
use App\Services\Prelims\Pdf\Parsers\BoldOptionAnswerStrategy;
use App\Services\Prelims\Pdf\Parsers\InlineAnswerStrategy;
use App\Services\Prelims\Pdf\Parsers\QuestionBlockSegmenter;
use App\Services\Prelims\Pdf\Parsers\SectionAndEndKeyStrategy;
use App\Services\Prelims\Pdf\Parsers\TabularAnswerStrategy;

final class PdfQuestionImportPipeline
{
    private SmalotPdfExtractor     $extractor;
    private QuestionBlockSegmenter $segmenter;

    /** @var AnswerStrategyInterface[] */
    private array $strategies;

    /**
     * @param AnswerStrategyInterface[]|null $strategies
     */
    public function __construct(
        ?SmalotPdfExtractor $extractor = null,
        ?QuestionBlockSegmenter $segmenter = null,
        ?array $strategies = null
    ) {
        $this->extractor = $extractor ?? new SmalotPdfExtractor();
        $this->segmenter = $segmenter ?? new QuestionBlockSegmenter();
        $this->strategies = $strategies ?? [
            new InlineAnswerStrategy(),
            new SectionAndEndKeyStrategy(),
            new TabularAnswerStrategy(),
            new BoldOptionAnswerStrategy(),
        ];
    }

    /**
     * Full typed-PDF pipeline from a binary PDF file on disk.
     * Automatically detects scanned/image-only PDFs and signals `needs_ocr = true`.
     *
     * @return array{
     *   needs_ocr: bool,
     *   method: string,
     *   page_count: int,
     *   avg_chars_per_page: float,
     *   topics: string[],
     *   questions: ParsedQuestionDTO[],
     *   strategy_counts: array<string, int>,
     *   dominant_strategy: string,
     *   resolved_count: int,
     *   missing_answer_count: int,
     *   duplicate_count: int,
     *   warnings: string[]
     * }
     */
    public function importFromBinaryPdf(string $binaryPdfPath, string $defaultTopic = 'General'): array
    {
        $doc = $this->extractor->extractFromBinary($binaryPdfPath);

        if ($doc->needsOcr) {
            return [
                'needs_ocr'            => true,
                'method'               => $doc->method,
                'page_count'           => $doc->pageCount,
                'avg_chars_per_page'   => $doc->avgCharsPerPage,
                'topics'               => [],
                'questions'            => [],
                'strategy_counts'      => [],
                'dominant_strategy'    => 'None',
                'resolved_count'       => 0,
                'missing_answer_count' => 0,
                'duplicate_count'      => 0,
                'warnings'             => array_merge(
                    $doc->warnings,
                    ['Low selectable text density detected (scanned/image PDF). Triggering local browser OCR fallback.']
                ),
            ];
        }

        return $this->parseExtractedDocument($doc, $defaultTopic);
    }

    /**
     * Pipeline entry point for text produced by local browser OCR (pdf.js + tesseract.js).
     *
     * @param string[] $pageTexts
     */
    public function importFromOcrPages(array $pageTexts, string $defaultTopic = 'General'): array
    {
        $doc = SmalotPdfExtractor::fromOcrPageTexts($pageTexts);
        return $this->parseExtractedDocument($doc, $defaultTopic);
    }

    /**
     * Parse a separate answer key (either raw text or binary PDF) and return QNo => 'A'..'F' mapping.
     *
     * @return array<int, string>
     */
    public function parseSeparateAnswerKeyText(string $rawKeyText): array
    {
        $pairs = [];

        // 1. Tabular parser
        $tabular = new TabularAnswerStrategy();
        foreach ($tabular->extractPairsFromRawText($rawKeyText) as $qNo => $ans) {
            $pairs[$qNo] = $ans;
        }

        // 2. General list patterns: "1. A", "Q1: (B)", "1) C", "1 - D", "1 = A", "1: Option B"
        if (preg_match_all('/(?:^|[\s,|;])(?:Q\.?\s*)?(\d{1,4})\s*[.)\-:|:=]?\s*(?:Option\s*)?[(\[]?([A-Fa-f])[)\]]?(?=\s|$|[,;|.)\-])/iu', $rawKeyText, $ms, PREG_SET_ORDER)) {
            foreach ($ms as $m) {
                $qNo = (int) $m[1];
                if ($qNo > 0 && !isset($pairs[$qNo])) {
                    $pairs[$qNo] = strtoupper($m[2]);
                }
            }
        }

        return $pairs;
    }

    /**
     * @return array{
     *   needs_ocr: bool,
     *   method: string,
     *   page_count: int,
     *   avg_chars_per_page: float,
     *   topics: string[],
     *   questions: ParsedQuestionDTO[],
     *   strategy_counts: array<string, int>,
     *   dominant_strategy: string,
     *   resolved_count: int,
     *   missing_answer_count: int,
     *   duplicate_count: int,
     *   warnings: string[]
     * }
     */
    public function parseExtractedDocument(ExtractedDocumentDTO $doc, string $defaultTopic = 'General'): array
    {
        $segmented = $this->segmenter->segment($doc, $defaultTopic);
        /** @var ParsedQuestionDTO[] $questions */
        $questions = $segmented['questions'];
        $topics    = $segmented['topics'];

        $strategyCounts = [];
        foreach ($this->strategies as $strategy) {
            $count = $strategy->apply($questions, $doc);
            if ($count > 0) {
                $strategyCounts[$strategy->getName()] = $count;
            }
        }

        // Flag intra-document duplicates & compute summary counters
        $seenHashes     = [];
        $resolvedCount  = 0;
        $missingCount   = 0;
        $duplicateCount = 0;

        foreach ($questions as $q) {
            if (isset($seenHashes[$q->questionHash])) {
                $q->isDuplicate = true;
                $duplicateCount++;
            } else {
                $seenHashes[$q->questionHash] = true;
            }

            if ($q->correctLabel !== null) {
                $resolvedCount++;
            } else {
                $missingCount++;
            }
        }

        arsort($strategyCounts);
        $dominant = !empty($strategyCounts) ? (string) array_key_first($strategyCounts) : 'Manual';

        $warnings = $doc->warnings;
        if (empty($questions)) {
            $warnings[] = 'No numbered multiple-choice questions were automatically detected. Please review the PDF format or add questions manually.';
        } elseif ($missingCount > 0) {
            $warnings[] = "{$missingCount} question(s) did not have a detected answer key and are flagged as Needs Review.";
        }
        if ($duplicateCount > 0) {
            $warnings[] = "{$duplicateCount} duplicate question(s) detected within this PDF.";
        }

        return [
            'needs_ocr'            => false,
            'method'               => $doc->method,
            'page_count'           => $doc->pageCount,
            'avg_chars_per_page'   => $doc->avgCharsPerPage,
            'topics'               => $topics,
            'questions'            => $questions,
            'strategy_counts'      => $strategyCounts,
            'dominant_strategy'    => $dominant,
            'resolved_count'       => $resolvedCount,
            'missing_answer_count' => $missingCount,
            'duplicate_count'      => $duplicateCount,
            'warnings'             => $warnings,
        ];
    }
}
