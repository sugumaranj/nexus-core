<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\DTO;

final class ExtractedDocumentDTO
{
    /**
     * @param ExtractedLineDTO[] $lines
     * @param string[]           $pageTexts
     * @param string[]           $warnings
     */
    public function __construct(
        public readonly string $method,
        public readonly int    $pageCount,
        public readonly string $rawText,
        public readonly array  $pageTexts,
        public readonly array  $lines,
        public readonly float  $avgCharsPerPage,
        public readonly bool   $needsOcr,
        public readonly array  $warnings = [],
    ) {
    }
}
