<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\DTO;

final class ExtractedLineDTO
{
    /**
     * @param TextFragmentDTO[] $fragments
     */
    public function __construct(
        public readonly int    $page,
        public readonly float  $y,
        public readonly float  $minX,
        public readonly string $text,
        public readonly float  $avgFontSize,
        public readonly float  $boldRatio,
        public readonly array  $fragments = [],
    ) {
    }
}
