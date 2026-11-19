<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\Contracts;

use App\Services\Prelims\Pdf\DTO\ExtractedDocumentDTO;
use App\Services\Prelims\Pdf\DTO\ParsedQuestionDTO;

interface AnswerStrategyInterface
{
    public function getName(): string;

    /**
     * Mutates $questions in place to assign answers for unresolved items.
     *
     * @param ParsedQuestionDTO[]  $questions
     * @param ExtractedDocumentDTO $doc
     * @return int Number of questions resolved by this strategy
     */
    public function apply(array &$questions, ExtractedDocumentDTO $doc): int;
}
