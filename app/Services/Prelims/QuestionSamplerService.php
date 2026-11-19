<?php

declare(strict_types=1);

namespace App\Services\Prelims;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Hierarchical Stratified Equal-Weightage Question Sampler & Per-Student Shuffler.
 *
 * Guarantees:
 *   Level 1: Equal question weightage across all active uploaded PDFs.
 *   Level 2: Equal question weightage across all active topics within each PDF.
 *   Level 3: Cryptographically seeded per-student shuffling of selected questions
 *            AND per-question option order (option_order_json).
 */
final class QuestionSamplerService
{
    /**
     * Sample $targetCount questions from $poolBuckets ([source_id => [topic_id => QuestionItem[]]])
     * and shuffle both question order and option order using $seed.
     *
     * @param array<int, array<int, array<int, array{question_id: int, source_id: int, topic_id: int, option_ids: int[]}>>> $poolBuckets
     * @return array<int, array{question_id: int, source_id: int, topic_id: int, display_order: int, option_order: int[]}>
     */
    public function sampleAndShuffle(array $poolBuckets, int $targetCount, int $seed): array
    {
        if ($targetCount <= 0 || empty($poolBuckets)) {
            return [];
        }

        $rng = new Randomizer(new Mt19937($seed));

        // 1. Clean empty buckets and pre-shuffle every (source, topic) bucket
        $shuffledPool = [];
        $totalAvailable = 0;
        foreach ($poolBuckets as $sourceId => $topics) {
            foreach ($topics as $topicId => $items) {
                if (empty($items)) {
                    continue;
                }
                $shuffledPool[(int) $sourceId][(int) $topicId] = $rng->shuffleArray(array_values($items));
                $totalAvailable += count($items);
            }
        }

        if ($totalAvailable === 0) {
            return [];
        }

        $effectiveTarget = min($targetCount, $totalAvailable);
        $sourceIds       = array_keys($shuffledPool);

        // 2. Level 1: Allocate equal quotas across all active PDFs
        //    Shuffle source order per student so any remainder (+1) rotates fairly across students!
        $sourceCapacities = [];
        foreach ($sourceIds as $sid) {
            $cap = 0;
            foreach ($shuffledPool[$sid] as $tItems) {
                $cap += count($tItems);
            }
            $sourceCapacities[$sid] = $cap;
        }
        $pdfQuotas = $this->allocateFairQuotas($sourceCapacities, $effectiveTarget, $rng);

        // 3. Level 2: Inside each PDF, allocate its quota equally across all active topics
        $selected = [];
        foreach ($pdfQuotas as $sid => $pdfQuota) {
            if ($pdfQuota <= 0) {
                continue;
            }
            $topicCapacities = [];
            foreach ($shuffledPool[$sid] as $tid => $tItems) {
                $topicCapacities[$tid] = count($tItems);
            }
            $topicQuotas = $this->allocateFairQuotas($topicCapacities, $pdfQuota, $rng);

            foreach ($topicQuotas as $tid => $tQuota) {
                if ($tQuota <= 0) {
                    continue;
                }
                $picked = array_slice($shuffledPool[$sid][$tid], 0, $tQuota);
                foreach ($picked as $item) {
                    $selected[] = $item;
                }
            }
        }

        // 4. Level 3: Globally shuffle selected questions AND shuffle each question's options
        $selected = $rng->shuffleArray($selected);

        $paper = [];
        $order = 1;
        foreach ($selected as $item) {
            $shuffledOpts = $rng->shuffleArray(array_values($item['option_ids']));
            $paper[] = [
                'question_id'   => (int) $item['question_id'],
                'source_id'     => (int) $item['source_id'],
                'topic_id'      => (int) $item['topic_id'],
                'display_order' => $order++,
                'option_order'  => $shuffledOpts,
            ];
        }

        return $paper;
    }

    /**
     * Evaluate whether a quiz pool can satisfy equal weightage cleanly before publishing.
     *
     * @param array<int, array<int, array<int, mixed>>> $poolBuckets
     * @return array{ok: bool, total_approved: int, source_count: int, topic_count: int, warnings: string[]}
     */
    public function auditPoolSufficiency(array $poolBuckets, int $targetCount): array
    {
        $totalApproved = 0;
        $sourceCount   = 0;
        $topicCount    = 0;
        $warnings      = [];

        $sourceTotals = [];
        foreach ($poolBuckets as $sid => $topics) {
            $srcSum = 0;
            $activeTopicsInSrc = 0;
            foreach ($topics as $tid => $items) {
                $cnt = count($items);
                if ($cnt > 0) {
                    $srcSum += $cnt;
                    $activeTopicsInSrc++;
                    $topicCount++;
                }
            }
            if ($srcSum > 0) {
                $sourceCount++;
                $totalApproved += $srcSum;
                $sourceTotals[$sid] = ['total' => $srcSum, 'topics' => $activeTopicsInSrc, 'map' => $topics];
            }
        }

        if ($totalApproved < $targetCount) {
            $warnings[] = "Approved question pool ({$totalApproved}) is smaller than configured questions per student ({$targetCount}).";
            return [
                'ok'             => false,
                'total_approved' => $totalApproved,
                'source_count'   => $sourceCount,
                'topic_count'    => $topicCount,
                'warnings'       => $warnings,
            ];
        }

        if ($sourceCount > 0) {
            $idealPerPdf = (int) floor($targetCount / $sourceCount);
            foreach ($sourceTotals as $sid => $info) {
                if ($info['total'] < $idealPerPdf) {
                    $warnings[] = "PDF Source #{$sid} has {$info['total']} approved Qs (ideal equal quota: {$idealPerPdf}). Deficit will be redistributed fairly.";
                }
                if ($info['topics'] > 0) {
                    $idealPerTopic = (int) floor(min($info['total'], $idealPerPdf) / $info['topics']);
                    foreach ($info['map'] as $tid => $items) {
                        if (count($items) < $idealPerTopic) {
                            $warnings[] = "Topic #{$tid} in Source #{$sid} has " . count($items) . " Qs (ideal equal quota: {$idealPerTopic}).";
                        }
                    }
                }
            }
        }

        return [
            'ok'             => true,
            'total_approved' => $totalApproved,
            'source_count'   => $sourceCount,
            'topic_count'    => $topicCount,
            'warnings'       => $warnings,
        ];
    }

    /**
     * Water-filling equal quota allocator:
     * Gives every bucket an equal share up to its capacity, iteratively redistributing
     * any deficit or remainder in a student-seeded fair order.
     *
     * @param array<int, int> $capacities  bucketId => maxCapacity
     * @return array<int, int>             bucketId => allocatedQuota
     */
    private function allocateFairQuotas(array $capacities, int $target, Randomizer $rng): array
    {
        $alloc = array_fill_keys(array_keys($capacities), 0);
        $remaining = $target;

        // Shuffle bucket keys once per student so remainder distribution is fair across students
        $activeKeys = $rng->shuffleArray(array_keys($capacities));

        while ($remaining > 0 && !empty($activeKeys)) {
            $numBuckets = count($activeKeys);
            $baseShare  = intdiv($remaining, $numBuckets);
            $extra      = $remaining % $numBuckets;

            $nextActive = [];
            $progress   = 0;

            foreach ($activeKeys as $idx => $key) {
                $want  = $baseShare + ($idx < $extra ? 1 : 0);
                $avail = $capacities[$key] - $alloc[$key];
                $give  = min($want, $avail);

                if ($give > 0) {
                    $alloc[$key] += $give;
                    $remaining   -= $give;
                    $progress    += $give;
                }
                if ($alloc[$key] < $capacities[$key]) {
                    $nextActive[] = $key;
                }
            }

            if ($progress === 0) {
                break;
            }
            $activeKeys = $nextActive;
        }

        return $alloc;
    }
}
