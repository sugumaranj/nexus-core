<?php
declare(strict_types=1);

namespace App\Services\Evaluation;

class ResultValidator
{
    /**
     * Validates that the evaluation matrix is complete.
     * All assigned judges must have evaluated all eligible participants.
     * 
     * @param array $participants Array of eligible application_ids
     * @param array $judges Array of assigned judge_ids
     * @param array $evaluations Matrix: [judge_id => [application_id => mark]]
     * @return array{success: bool, message: string}
     */
    public function validateCompleteMatrix(array $participants, array $judges, array $evaluations): array
    {
        if (empty($participants)) {
            return ['success' => false, 'message' => 'No eligible participants found for evaluation.'];
        }

        if (empty($judges)) {
            return ['success' => false, 'message' => 'No judges assigned to this event.'];
        }

        foreach ($judges as $jid => $jName) {
            if (!isset($evaluations[$jid])) {
                return ['success' => false, 'message' => "Judge {$jName} (ID: {$jid}) has not submitted any evaluations."];
            }

            foreach ($participants as $appId) {
                if (!isset($evaluations[$jid][$appId])) {
                    return ['success' => false, 'message' => "Judge {$jName} (ID: {$jid}) has not evaluated Participant (Application ID: {$appId})."];
                }
            }
        }

        return ['success' => true, 'message' => 'Matrix complete.'];
    }
}
