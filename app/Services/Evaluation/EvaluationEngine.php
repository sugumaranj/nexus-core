<?php
declare(strict_types=1);

namespace App\Services\Evaluation;

class EvaluationEngine
{
    private RankingCalculator $rankingCalc;
    private SnapshotAuditor $snapshotAuditor;
    private int $scale;

    public function __construct(int $scale = 16)
    {
        $this->scale = $scale;
        $this->rankingCalc = new RankingCalculator($scale);
        $this->snapshotAuditor = new SnapshotAuditor();
    }

    /**
     * Executes the full mathematical pipeline on the frozen matrix.
     * 
     * @param int $symposiumEventId
     * @param float $maxScore
     * @param array $participants Array of participants (id => [...])
     * @param array $judges Array of judge ids
     * @param array $evaluations Matrix: [judgeId => [appId => mark]]
     * @return array Contains 'results', 'snapshot', 'success', 'message'
     */
    public function runPipeline(int $symposiumEventId, float $maxScore, array $participants, array $judges, array $evaluations): array
    {
        $snapshotParticipants = [];
        $results = [];
        $judgeCount = count($judges);

        if ($judgeCount === 0) {
             return ['success' => false, 'message' => 'No judges assigned. Cannot evaluate.'];
        }
        if (count($participants) === 0) {
             return ['success' => false, 'message' => 'No eligible participants found.'];
        }

        foreach ($participants as $appId => $pData) {
            $rawTotal = '0';
            $judgeMarks = [];
            foreach ($judges as $jid) {
                // Ensure mark is retrieved and formatted for bcmath
                $mark = isset($evaluations[$jid][$appId]) ? $evaluations[$jid][$appId] : 0;
                $markStr = number_format((float)$mark, 2, '.', '');
                $rawTotal = bcadd($rawTotal, $markStr, $this->scale);
                $judgeMarks[$jid] = $markStr;
            }
            $rawAvg = bcdiv($rawTotal, (string)$judgeCount, $this->scale);

            $res = [
                'application_id' => $appId,
                'total_score' => $rawTotal,
                'average_score' => number_format((float)$rawAvg, 2, '.', ''),
                'average_z_score' => '0.0000000000000000', // Hardcoded legacy 0
                'raw_average' => $rawAvg,
                'judge_marks' => $judgeMarks
            ];
            $results[] = $res;
        }

        // Ranking
        $this->rankingCalc->rank($results);

        // Status mapping
        foreach ($results as &$res) {
            if ($res['rank_position'] === 1) {
                $res['result_status'] = 'First Place';
            } elseif ($res['rank_position'] === 2) {
                $res['result_status'] = 'Second Place';
            } elseif ($res['rank_position'] === 3) {
                $res['result_status'] = 'Third Place';
            } else {
                $res['result_status'] = 'Participant';
            }

            $snapshotParticipants[$res['application_id']] = [
                'raw_scores' => $res['judge_marks'],
                'raw_average' => $res['raw_average'],
                'average_z_score' => $res['average_z_score'],
                'rank' => $res['rank_position'],
                'result_status' => $res['result_status']
            ];
        }
        unset($res);

        $snapshot = [
            'methodology' => 'Unified Arithmetic Average',
            'symposium_event_id' => $symposiumEventId,
            'max_score' => $maxScore,
            'participant_count' => count($participants),
            'judge_count' => $judgeCount,
            'precision_policy' => ['raw_input' => 2, 'internal_calculation' => $this->scale, 'ranking_comparison' => $this->scale, 'display' => 2],
            'policies' => ['tie_hierarchy' => ['bcmath_arithmetic_average'], 'ranking' => 'competition_shared_ranks'],
            'judges' => $judges,
            'participants' => $snapshotParticipants
        ];

        return $this->finalize($snapshot, $results);
    }

    private function finalize(array $snapshot, array $results): array
    {
        $snapshot['calculated_at'] = gmdate('Y-m-d\TH:i:s\Z');
        
        $canon = $this->snapshotAuditor->canonicalizeAndHash($snapshot);
        
        return [
            'success' => true,
            'results' => $results,
            'snapshot_json' => $canon['json'],
            'snapshot_hash' => $canon['hash']
        ];
    }
}
