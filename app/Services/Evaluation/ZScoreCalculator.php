<?php
declare(strict_types=1);

namespace App\Services\Evaluation;

/**
 * Calculates Z-Scores and Normalizes them using BCMath.
 */
class ZScoreCalculator
{
    private int $scale;
    private string $zeroSdZScore;

    public function __construct(int $scale = 16)
    {
        $this->scale = $scale;
        $this->zeroSdZScore = '0.' . str_repeat('0', $this->scale);
    }

    /**
     * Calculates Z-score for a single participant by a judge.
     * Z = (score - mean) / sd
     * If sd is 0, Z is exactly 0.00...
     * 
     * @param string $score Raw score
     * @param string $mean Judge's mean
     * @param string $sd Judge's standard deviation
     * @return string
     */
    public function calculateZScore(string $score, string $mean, string $sd): string
    {
        // If SD is exactly 0, they contributed no discriminatory info.
        // Even if bcmath string is '0.00000', bccomp handles it.
        if (bccomp($sd, '0', $this->scale) === 0) {
            return $this->zeroSdZScore;
        }

        $diff = bcsub($score, $mean, $this->scale);
        $z = bcdiv($diff, $sd, $this->scale);

        return $z;
    }

    /**
     * Calculates the Average Z-score across multiple judges.
     * 
     * @param array<string> $zScores
     * @return string
     */
    public function calculateAverageZScore(array $zScores): string
    {
        $count = count($zScores);
        if ($count === 0) {
            return $this->zeroSdZScore;
        }

        $sum = '0';
        foreach ($zScores as $z) {
            $sum = bcadd($sum, $z, $this->scale);
        }

        return bcdiv($sum, (string)$count, $this->scale);
    }
}
