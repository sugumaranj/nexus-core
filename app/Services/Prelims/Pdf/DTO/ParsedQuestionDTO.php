<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\DTO;

final class ParsedQuestionDTO
{
    /**
     * @param array<string, array{text: string, is_bold: bool}> $options Keyed by 'A'..'F'
     */
    public function __construct(
        public int     $sourceQuestionNo,
        public string  $topicName,
        public int     $sectionIndex,
        public string  $questionText,
        public array   $options = [],
        public ?string $correctLabel = null,
        public string  $answerConfidence = 'Missing',
        public string  $answerSource = 'Manual',
        public string  $questionHash = '',
        public bool    $isDuplicate = false,
    ) {
        if ($this->questionHash === '') {
            $this->questionHash = self::computeHash($questionText);
        }
    }

    public static function computeHash(string $text): string
    {
        $norm = mb_strtolower(trim($text));
        $norm = preg_replace('/[^a-z0-9]+/u', ' ', $norm) ?? $norm;
        $norm = trim(preg_replace('/\s+/', ' ', $norm) ?? $norm);
        return hash('sha256', $norm);
    }

    public function setAnswer(string $label, string $source, string $confidence = 'High'): void
    {
        $upper = strtoupper(trim($label));
        if (isset($this->options[$upper])) {
            $this->correctLabel     = $upper;
            $this->answerSource     = $source;
            $this->answerConfidence = $confidence;
        }
    }
}
