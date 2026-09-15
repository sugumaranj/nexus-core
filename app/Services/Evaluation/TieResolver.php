<?php
declare(strict_types=1);

namespace App\Services\Evaluation;

class TieResolver
{
    /**
     * Resolves ties based on the defined hierarchy:
     * 1. Average Z-Score (already compared before calling this)
     * 2. Raw Average (16 dec)
     * 3. Configured criterion (if any) -> Manual for now if unresolved
     *
     * @param array $p1 Participant 1 data
     * @param array $p2 Participant 2 data
     * @return int -1 if p1 > p2, 1 if p2 > p1, 0 if still tied
     */
    public function resolve(array $p1, array $p2): int
    {
        // 1. Raw Average Comparison (16 decimal string comparison)
        $rawAvgCmp = bccomp($p2['raw_average'], $p1['raw_average'], 16);
        if ($rawAvgCmp !== 0) {
            return $rawAvgCmp;
        }

        // 2. Highest individual raw score tie-breaker
        if (isset($p1['judge_marks']) && isset($p2['judge_marks'])) {
            $p1Marks = array_values($p1['judge_marks']);
            $p2Marks = array_values($p2['judge_marks']);
            
            // Sort descending as strings using bccomp
            usort($p1Marks, function($a, $b) { return bccomp((string)$b, (string)$a, 16); });
            usort($p2Marks, function($a, $b) { return bccomp((string)$b, (string)$a, 16); });
            
            $count = min(count($p1Marks), count($p2Marks));
            for ($i = 0; $i < $count; $i++) {
                $cmp = bccomp((string)$p2Marks[$i], (string)$p1Marks[$i], 16);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
        }

        // 3. No other configured logic yet -> Return tied (Manual Resolution Required)
        return 0;
    }
}
