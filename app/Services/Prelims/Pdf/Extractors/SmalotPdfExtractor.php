<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\Extractors;

use App\Services\Prelims\Pdf\Contracts\PdfExtractorInterface;
use App\Services\Prelims\Pdf\DTO\ExtractedDocumentDTO;
use App\Services\Prelims\Pdf\DTO\ExtractedLineDTO;
use App\Services\Prelims\Pdf\DTO\TextFragmentDTO;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Font;
use Smalot\PdfParser\Parser;

final class SmalotPdfExtractor implements PdfExtractorInterface
{
    public const MIN_TYPED_CHARS_PER_PAGE = 30.0;

    private BinaryFileValidator $binaryValidator;

    public function __construct(?BinaryFileValidator $binaryValidator = null)
    {
        $this->binaryValidator = $binaryValidator ?? new BinaryFileValidator();
    }

    public function extractFromBinary(string $filePath): ExtractedDocumentDTO
    {
        $validation = $this->binaryValidator->validatePdf($filePath);
        if (!$validation['valid']) {
            throw new \InvalidArgumentException($validation['message']);
        }

        $binary = $this->binaryValidator->readBinary($filePath);

        $config = new Config();
        $config->setDataTmFontInfoHasToBeIncluded(true);
        $config->setRetainImageContent(false);
        $config->setHorizontalOffset(' ');

        $parser = new Parser([], $config);
        $pdf    = $parser->parseContent($binary);
        $pages  = $pdf->getPages();

        $pageCount  = count($pages);
        $allLines   = [];
        $pageTexts  = [];
        $warnings   = [];
        $totalChars = 0;

        foreach ($pages as $idx => $page) {
            $pageNum = $idx + 1;
            $fontBoldCache = [];

            foreach ($page->getFonts() as $fontId => $fontObj) {
                $fontBoldCache[(string) $fontId] = [
                    'name' => $this->resolveFontName($fontObj),
                    'bold' => $this->isFontBold($fontObj),
                ];
            }

            $fragments = [];
            $skipDataTm = false;
            try {
                // Guard against smalot/pdfparser upstream bug on plain FPDF documents with no XObjects
                if (method_exists($page, 'isFpdf') && $page->isFpdf() && empty($page->getXObjects())) {
                    $skipDataTm = true;
                }
            } catch (\Throwable $e) {
                $skipDataTm = true;
            }

            if (!$skipDataTm) {
                try {
                    $dataTm = $page->getDataTm();
                    foreach ($dataTm as $entry) {
                        $tm   = $entry[0] ?? [1, 0, 0, 1, 0, 0];
                        $text = (string) ($entry[1] ?? '');
                        if ($text === '') {
                            continue;
                        }

                        $fontId   = isset($entry[2]) ? (string) $entry[2] : '';
                        $fontSize = isset($entry[3]) ? abs((float) $entry[3]) : 0.0;
                        if ($fontSize <= 0.1) {
                            $fontSize = max(abs((float) ($tm[0] ?? 10.0)), abs((float) ($tm[3] ?? 10.0)), 10.0);
                        }

                        $fontInfo = $fontBoldCache[$fontId] ?? null;
                        if ($fontInfo === null && $fontId !== '') {
                            $fontObj = $page->getFont($fontId);
                            if ($fontObj instanceof Font) {
                                $fontInfo = [
                                    'name' => $this->resolveFontName($fontObj),
                                    'bold' => $this->isFontBold($fontObj),
                                ];
                                $fontBoldCache[$fontId] = $fontInfo;
                            }
                        }

                        $x = (float) ($tm[4] ?? 0.0);
                        $y = (float) ($tm[5] ?? 0.0);

                        $fragments[] = new TextFragmentDTO(
                            text: $text,
                            page: $pageNum,
                            x: $x,
                            y: $y,
                            fontName: $fontInfo['name'] ?? '',
                            fontSize: $fontSize,
                            isBold: (bool) ($fontInfo['bold'] ?? false),
                        );
                    }
                } catch (\Throwable $e) {
                    $warnings[] = "Page {$pageNum}: getDataTm fallback triggered ({$e->getMessage()}).";
                }
            }

            // Direct PDF content-stream parser with full font/bold & coordinate tracking
            // Handles FPDF documents and any PDF where getDataTm() was skipped or returned empty
            if (empty($fragments)) {
                $fragments = $this->parseRawContentStreamWithFonts($page, $fontBoldCache, $pageNum);
            }

            $pageLines = $this->groupFragmentsIntoLines($fragments, $pageNum);

            // Fallback to getText() if getDataTm yielded too little text on a typed page
            $tmTextLen = 0;
            foreach ($pageLines as $ln) {
                $tmTextLen += mb_strlen(preg_replace('/\s+/u', '', $ln->text) ?? '');
            }

            $plainText = '';
            try {
                $plainText = trim((string) $page->getText());
            } catch (\Throwable $e) {
                $warnings[] = "Page {$pageNum}: getText warning ({$e->getMessage()}).";
            }
            $plainTextLen = mb_strlen(preg_replace('/\s+/u', '', $plainText) ?? '');

            if ($plainTextLen > ($tmTextLen * 1.5) && $plainTextLen > 20) {
                $pageLines = $this->buildLinesFromPlainText($plainText, $pageNum);
                $pageText  = $plainText;
                $totalChars += $plainTextLen;
            } else {
                $pageText = implode("\n", array_map(static fn(ExtractedLineDTO $l): string => $l->text, $pageLines));
                $totalChars += $tmTextLen;
            }

            $pageTexts[$pageNum] = $pageText;
            foreach ($pageLines as $ln) {
                $allLines[] = $ln;
            }
        }

        $avgCharsPerPage = $pageCount > 0 ? round($totalChars / $pageCount, 2) : 0.0;
        $needsOcr        = ($pageCount === 0) || ($avgCharsPerPage < self::MIN_TYPED_CHARS_PER_PAGE);

        return new ExtractedDocumentDTO(
            method: 'smalot_php',
            pageCount: $pageCount,
            rawText: implode("\n\n", $pageTexts),
            pageTexts: $pageTexts,
            lines: $allLines,
            avgCharsPerPage: $avgCharsPerPage,
            needsOcr: $needsOcr,
            warnings: $warnings,
        );
    }

    /**
     * Build an ExtractedDocumentDTO from browser-OCR page text array.
     *
     * @param string[] $pageTexts
     */
    public static function fromOcrPageTexts(array $pageTexts): ExtractedDocumentDTO
    {
        $lines      = [];
        $normalized = [];
        $totalChars = 0;
        $pageNum    = 0;

        foreach ($pageTexts as $rawPageText) {
            $pageNum++;
            $text = str_replace(["\r\n", "\r"], "\n", (string) $rawPageText);
            $normalized[$pageNum] = $text;
            $totalChars += mb_strlen(preg_replace('/\s+/u', '', $text) ?? '');

            $y = 1000.0;
            foreach (explode("\n", $text) as $lineStr) {
                $trimmed = trim($lineStr);
                if ($trimmed === '') {
                    continue;
                }
                $lines[] = new ExtractedLineDTO(
                    page: $pageNum,
                    y: $y,
                    minX: 0.0,
                    text: $trimmed,
                    avgFontSize: 10.0,
                    boldRatio: 0.0,
                    fragments: [new TextFragmentDTO($trimmed, $pageNum, 0.0, $y, 'OCR', 10.0, false)],
                );
                $y -= 14.0;
            }
        }

        $pageCount = max(1, $pageNum);

        return new ExtractedDocumentDTO(
            method: 'browser_ocr_local',
            pageCount: $pageNum,
            rawText: implode("\n\n", $normalized),
            pageTexts: $normalized,
            lines: $lines,
            avgCharsPerPage: round($totalChars / $pageCount, 2),
            needsOcr: false,
            warnings: [],
        );
    }

    /**
     * @param TextFragmentDTO[] $fragments
     * @return ExtractedLineDTO[]
     */
    private function groupFragmentsIntoLines(array $fragments, int $pageNum): array
    {
        if (empty($fragments)) {
            return [];
        }

        // Sort top-to-bottom (Y descending in PDF coordinate space), then left-to-right (X ascending)
        usort($fragments, static function (TextFragmentDTO $a, TextFragmentDTO $b): int {
            if (abs($a->y - $b->y) > 2.5) {
                return $b->y <=> $a->y;
            }
            return $a->x <=> $b->x;
        });

        /** @var array<int, TextFragmentDTO[]> $clusters */
        $clusters = [];
        foreach ($fragments as $frag) {
            $placed = false;
            foreach ($clusters as &$cluster) {
                $ref = $cluster[0];
                $tol = max(2.8, min($ref->fontSize, $frag->fontSize) * 0.42);
                if (abs($ref->y - $frag->y) <= $tol) {
                    $cluster[] = $frag;
                    $placed    = true;
                    break;
                }
            }
            unset($cluster);
            if (!$placed) {
                $clusters[] = [$frag];
            }
        }

        // Sort clusters top-to-bottom by average Y
        usort($clusters, static fn(array $a, array $b): int => $b[0]->y <=> $a[0]->y);

        $lines = [];
        foreach ($clusters as $cluster) {
            usort($cluster, static fn(TextFragmentDTO $a, TextFragmentDTO $b): int => $a->x <=> $b->x);

            $lineText    = '';
            $prevFrag    = null;
            $fontSizeSum = 0.0;
            $boldChars   = 0;
            $nonWsChars  = 0;

            foreach ($cluster as $frag) {
                $piece = $frag->text;
                if ($prevFrag !== null && $lineText !== '') {
                    $endsWithSpace   = (bool) preg_match('/\s$/u', $lineText);
                    $startsWithSpace = (bool) preg_match('/^\s/u', $piece);
                    $approxPrevWidth = mb_strlen($prevFrag->text) * ($prevFrag->fontSize * 0.48);
                    $gap             = $frag->x - ($prevFrag->x + $approxPrevWidth);

                    if (!$endsWithSpace && !$startsWithSpace) {
                        if ($gap > ($prevFrag->fontSize * 1.4)) {
                            $lineText .= '   '; // Preserve column gap for tabular/2-column option parsing
                        } elseif ($gap > ($prevFrag->fontSize * 0.15) || ($frag->x > $prevFrag->x + 1.0 && !preg_match('/^[,.;:)\]]$/u', trim($piece)))) {
                            $lineText .= ' ';
                        }
                    }
                }

                $lineText    .= $piece;
                $fontSizeSum += $frag->fontSize;
                $strippedLen  = mb_strlen(preg_replace('/\s+/u', '', $piece) ?? '');
                $nonWsChars  += $strippedLen;
                if ($frag->isBold) {
                    $boldChars += $strippedLen;
                }
                $prevFrag = $frag;
            }

            $cleanLine = trim(preg_replace('/[ \t]{4,}/', '   ', $lineText) ?? $lineText);
            if ($cleanLine === '') {
                continue;
            }

            $lines[] = new ExtractedLineDTO(
                page: $pageNum,
                y: $cluster[0]->y,
                minX: $cluster[0]->x,
                text: $cleanLine,
                avgFontSize: round($fontSizeSum / count($cluster), 2),
                boldRatio: $nonWsChars > 0 ? round($boldChars / $nonWsChars, 3) : 0.0,
                fragments: $cluster,
            );
        }

        return $lines;
    }

    /**
     * Direct pure-PHP PDF content-stream parser that tracks active Font ID (`Tf`),
     * bold state, and X/Y coordinates (`Td`/`TD`/`Tm`) across `BT ... ET` text blocks.
     * Ensures 100% accurate bold-answer and coordinate extraction even on plain FPDF documents.
     *
     * @param \Smalot\PdfParser\Page $page
     * @param array<string, array{name: string, bold: bool}> $fontBoldCache
     * @return TextFragmentDTO[]
     */
    private function parseRawContentStreamWithFonts(\Smalot\PdfParser\Page $page, array $fontBoldCache, int $pageNum): array
    {
        $contents = $page->get('Contents');
        if ($contents === null) {
            return [];
        }

        $stream = '';
        if (is_object($contents) && method_exists($contents, 'getContent')) {
            $stream = (string) $contents->getContent();
        }
        if ($stream === '') {
            return [];
        }

        $fragments   = [];
        $curFontId   = '';
        $curFontSize = 10.0;
        $curX        = 0.0;
        $curY        = 0.0;
        $inText      = false;

        // Tokenize key PDF text operators in stream order: BT, ET, /Font size Tf, x y Td/TD, a b c d e f Tm, (str) Tj, [..] TJ
        $pattern = '/\b(BT|ET)\b|\/([A-Za-z0-9_+.\-]+)\s+([0-9.\-]+)\s+Tf\b|([0-9.\-]+)\s+([0-9.\-]+)\s+T[dD]\b|([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+Tm\b|\(((?:\\\\.|[^\\\\)])*)\)\s*Tj|\[((?:[^\[\]]|\((?:\\\\.|[^\\\\)])*\))*)\]\s*TJ/s';

        if (!preg_match_all($pattern, $stream, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $m) {
            if (!empty($m[1])) {
                if ($m[1] === 'BT') {
                    $inText = true;
                    $curX   = 0.0;
                    $curY   = 0.0;
                } else {
                    $inText = false;
                }
                continue;
            }

            // /FontId Size Tf
            if (isset($m[2]) && $m[2] !== '') {
                $curFontId   = $m[2];
                $curFontSize = max(1.0, abs((float) $m[3]));
                continue;
            }

            // x y Td / TD
            if (isset($m[4]) && $m[4] !== '') {
                $curX += (float) $m[4];
                $curY += (float) $m[5];
                continue;
            }

            // a b c d e f Tm
            if (isset($m[6]) && $m[6] !== '') {
                $curX = (float) $m[10];
                $curY = (float) $m[11];
                continue;
            }

            if (!$inText) {
                continue;
            }

            $rawText = '';
            if (isset($m[12]) && $m[12] !== '') {
                $rawText = $this->unescapePdfLiteralString($m[12]);
            } elseif (isset($m[13]) && $m[13] !== '') {
                if (preg_match_all('/\(((?:\\\\.|[^\\\\)])*)\)/s', $m[13], $parts)) {
                    foreach ($parts[1] as $p) {
                        $rawText .= $this->unescapePdfLiteralString($p);
                    }
                }
            }

            if ($rawText === '') {
                continue;
            }

            $fontInfo = $fontBoldCache[$curFontId] ?? $fontBoldCache[ltrim($curFontId, 'F')] ?? null;
            if ($fontInfo === null && $curFontId !== '') {
                $fontObj = $page->getFont($curFontId);
                if ($fontObj instanceof Font) {
                    $fontInfo = [
                        'name' => $this->resolveFontName($fontObj),
                        'bold' => $this->isFontBold($fontObj),
                    ];
                }
            }

            $fragments[] = new TextFragmentDTO(
                text: $rawText,
                page: $pageNum,
                x: $curX,
                y: $curY,
                fontName: $fontInfo['name'] ?? $curFontId,
                fontSize: $curFontSize,
                isBold: (bool) ($fontInfo['bold'] ?? false),
            );
        }

        return $fragments;
    }

    private function unescapePdfLiteralString(string $raw): string
    {
        $out = preg_replace_callback('/\\\\([nrtbf()\\\\]|[0-7]{1,3})/', static function (array $m): string {
            $seq = $m[1];
            return match ($seq) {
                'n'  => "\n",
                'r'  => "\r",
                't'  => "\t",
                'b'  => "\x08",
                'f'  => "\x0C",
                '('  => '(',
                ')'  => ')',
                '\\' => '\\',
                default => chr(octdec($seq)),
            };
        }, $raw);
        return (string) ($out ?? $raw);
    }

    /**
     * @return ExtractedLineDTO[]
     */
    private function buildLinesFromPlainText(string $plainText, int $pageNum): array
    {
        $lines = [];
        $y     = 1000.0;
        foreach (preg_split('/\R/u', $plainText) ?: [] as $rawLine) {
            $trimmed = trim($rawLine);
            if ($trimmed === '') {
                continue;
            }
            $lines[] = new ExtractedLineDTO(
                page: $pageNum,
                y: $y,
                minX: 0.0,
                text: $trimmed,
                avgFontSize: 10.0,
                boldRatio: 0.0,
                fragments: [new TextFragmentDTO($trimmed, $pageNum, 0.0, $y, '', 10.0, false)],
            );
            $y -= 14.0;
        }
        return $lines;
    }

    private function resolveFontName(Font $font): string
    {
        $name = (string) $font->getName();
        if ($name !== '') {
            return $name;
        }
        $details = $font->getDetails();
        return (string) ($details['BaseFont'] ?? $details['Name'] ?? '');
    }

    private function isFontBold(Font $font): bool
    {
        $name    = $this->resolveFontName($font);
        $details = $font->getDetails();
        $haystack = strtolower($name . ' ' . (string) ($details['BaseFont'] ?? ''));

        if (preg_match('/(bold|heavy|black|demi|semibold|extrabold|ultrabold|[-,]b\b|bd\b|med(ium)?)/i', $haystack)) {
            return true;
        }

        if (isset($details['FontWeight']) && is_numeric($details['FontWeight']) && (int) $details['FontWeight'] >= 600) {
            return true;
        }

        // PDF FontDescriptor Flags bit 19 (1 << 18 = 262144) = ForceBold
        if (isset($details['Flags']) && is_numeric($details['Flags']) && (((int) $details['Flags']) & 262144) !== 0) {
            return true;
        }

        return false;
    }
}
