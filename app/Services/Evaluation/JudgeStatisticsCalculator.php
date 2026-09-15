<?php
declare(strict_types=1);

namespace App\Services\Evaluation;

/**
 * Calculates Judge Statistics (Mean and Population Standard Deviation) using BCMath.
 */
class JudgeStatisticsCalculator
{
    private int $scale;

    public function __construct(int $scale = 16)
    {
        $this->scale = $scale;
    }

    /**
     * Calculates Mean and Population Standard Deviation.
     * All inputs must be exact decimal strings or floats cast to strings.
     * 
     * @param array<string> $scores Array of raw score strings
     * @return array{mean: string, sd: string, count: int}
     */
    public function calculateStatistics(array $scores): array
    {
        $count = count($scores);
        if ($count === 0) {
            return [
                'mean' => '0.' . str_repeat('0', $this->scale),
                'sd' => '0.' . str_repeat('0', $this->scale),
                'count' => 0
            ];
        }

        // 1. Calculate Mean
        $sum = '0';
        foreach ($scores as $score) {
            $sum = bcadd($sum, (string)$score, $this->scale);
        }
        $mean = bcdiv($sum, (string)$count, $this->scale);

        // 2. Calculate Population Standard Deviation: sqrt( sum( (x - mean)^2 ) / N )
        $sumOfSquaredDifferences = '0';
        foreach ($scores as $score) {
            $diff = bcsub((string)$score, $mean, $this->scale);
            $squaredDiff = bcmul($diff, $diff, $this->scale);
            $sumOfSquaredDifferences = bcadd($sumOfSquaredDifferences, $squaredDiff, $this->scale);
        }

        $variance = bcdiv($sumOfSquaredDifferences, (string)$count, $this->scale);
        $sd = bcsqrt($variance, $this->scale);

        return [
            'mean' => $mean,
            'sd' => $sd,
            'count' => $count
        ];
    }
}
