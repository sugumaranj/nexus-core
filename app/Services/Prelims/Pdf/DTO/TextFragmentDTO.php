<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\DTO;

final class TextFragmentDTO
{
    public function __construct(
        public readonly string $text,
        public readonly int    $page,
        public readonly float  $x,
        public readonly float  $y,
        public readonly string $fontName = '',
        public readonly float  $fontSize = 10.0,
        public readonly bool   $isBold = false,
    ) {
    }
}
