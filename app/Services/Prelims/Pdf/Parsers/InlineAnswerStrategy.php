<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\Parsers;

use App\Services\Prelims\Pdf\Contracts\AnswerStrategyInterface;
use App\Services\Prelims\Pdf\DTO\ExtractedDocumentDTO;
use App\Services\Prelims\Pdf\DTO\ParsedQuestionDTO;

final class InlineAnswerStrategy implements AnswerStrategyInterface
{
    public function getName(): string
    {
        return 'Inline';
    }

    /**
     * @param ParsedQuestionDTO[] $questions
     */
    public function apply(array &$questions, ExtractedDocumentDTO $doc): int
    {
        $resolved = 0;

        foreach ($questions as $q) {
            // 1. Check for inline "Answer: X" appended to the tail of any option (usually the last option)
            foreach ($q->options as $label => &$opt) {
                $text = $opt['text'];

                if (preg_match('/\s*(?:Ans(?:wer)?|Correct\s*(?:Answer|Option)|Right\s*Answer|Key)\s*(?:is\s*)?[:.\-–—=]?\s*(?:Option\s*)?[(\[]?([A-Fa-f])[)\]\.]?\s*$/iu', $text, $m)) {
                    $cleaned = trim(preg_replace('/\s*(?:Ans(?:wer)?|Correct\s*(?:Answer|Option)|Right\s*Answer|Key)\s*(?:is\s*)?[:.\-–—=]?\s*(?:Option\s*)?[(\[]?[A-Fa-f][)\]\.]?\s*$/iu', '', $text) ?? $text);
                    $opt['text'] = $cleaned;
                    if ($q->correctLabel === null) {
                        $q->setAnswer(strtoupper($m[1]), 'Inline', 'High');
                        $resolved++;
                    }
                }
            }
            unset($opt);

            // 2. Check for leading asterisk/checkmark or trailing [Correct] / (Correct) / * on an option
            $markedLabels = [];
            foreach ($q->options as $label => &$opt) {
                $text = $opt['text'];
                $hit  = false;

                if (preg_match('/^[*✓✔]\s*/u', $text)) {
                    $opt['text'] = trim(preg_replace('/^[*✓✔]\s*/u', '', $text) ?? $text);
                    $hit = true;
                }
                if (preg_match('/\s*(?:\[(?:Correct|Ans(?:wer)?|✓|✔)\]|\((?:Correct|Ans(?:wer)?)\)|[✓✔])\s*$/iu', $opt['text'])) {
                    $opt['text'] = trim(preg_replace('/\s*(?:\[(?:Correct|Ans(?:wer)?|✓|✔)\]|\((?:Correct|Ans(?:wer)?)\)|[✓✔])\s*$/iu', '', $opt['text']) ?? $opt['text']);
                    $hit = true;
                }

                if ($hit) {
                    $markedLabels[] = $label;
                }
            }
            unset($opt);

            if ($q->correctLabel === null && count($markedLabels) === 1) {
                $q->setAnswer($markedLabels[0], 'Inline', 'High');
                $resolved++;
            }
        }

        return $resolved;
    }
}
