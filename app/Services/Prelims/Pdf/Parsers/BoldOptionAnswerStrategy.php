<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\Parsers;

use App\Services\Prelims\Pdf\Contracts\AnswerStrategyInterface;
use App\Services\Prelims\Pdf\DTO\ExtractedDocumentDTO;
use App\Services\Prelims\Pdf\DTO\ParsedQuestionDTO;

/**
 * Resolves answers where the PDF highlights the correct option using a Bold font
 * while sibling options use a regular font weight.
 */
final class BoldOptionAnswerStrategy implements AnswerStrategyInterface
{
    public function getName(): string
    {
        return 'Bold';
    }

    /**
     * @param ParsedQuestionDTO[] $questions
     */
    public function apply(array &$questions, ExtractedDocumentDTO $doc): int
    {
        $resolved = 0;

        foreach ($questions as $q) {
            if ($q->correctLabel !== null) {
                continue;
            }

            $boldLabels = [];
            foreach ($q->options as $label => $opt) {
                if (!empty($opt['is_bold'])) {
                    $boldLabels[] = $label;
                }
            }

            // Strictly require EXACTLY ONE bolded option among >= 2 options
            if (count($boldLabels) === 1 && count($q->options) >= 2) {
                $q->setAnswer($boldLabels[0], 'Bold', 'High');
                if ($q->correctLabel !== null) {
                    $resolved++;
                }
            }
        }

        return $resolved;
    }
}
