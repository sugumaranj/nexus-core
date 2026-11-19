<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\Contracts;

use App\Services\Prelims\Pdf\DTO\ExtractedDocumentDTO;

interface PdfExtractorInterface
{
    public function extractFromBinary(string $filePath): ExtractedDocumentDTO;
}
