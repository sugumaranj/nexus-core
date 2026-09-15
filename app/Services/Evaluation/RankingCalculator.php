<?php
declare(strict_types=1);

namespace App\Services\Evaluation;

class RankingCalculator
{
    private int $scale;

    public function __construct(int $scale = 16)
    {
        $this->scale = $scale;
    }

    /**
     * Sorts the results and assigns ranks using standard competition ranking (1, 1, 3, 4).
     * 
     * @param array &$results
     * @return bool Always returns true as ties are allowed.
     */
    public function rank(array &$results): bool
    {
        usort($results, function ($a, $b) {
            // Sort by average_score descending
            $cmp = bccomp($b['average_score'], $a['average_score'], $this->scale);
            if ($cmp !== 0) {
                return $cmp;
            }

            // Fallback deterministic order so array order is stable, 
            // but this does NOT break the rank tie.
            return $a['application_id'] <=> $b['application_id'];
        });

        $rank = 1;
        $prev = null;
        
        foreach ($results as $index => &$p) {
            if ($prev !== null) {
                $cmp = bccomp($p['average_score'], $prev['average_score'], $this->scale);
                
                if ($cmp === 0) {
                    $p['rank_position'] = $prev['rank_position']; // Shared rank
                } else {
                    $rank = $index + 1;
                    $p['rank_position'] = $rank;
                }
            } else {
                $p['rank_position'] = $rank;
            }

            $prev = $p;
        }

        return true;
    }
}
