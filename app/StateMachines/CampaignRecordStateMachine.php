<?php

namespace App\StateMachines;

use App\Models\CampaignRecord;
use Illuminate\Support\Facades\Log;

use function in_array;

/**
 * State machine for CampaignRecord model.
 */
class CampaignRecordStateMachine
{
    private const array TRANSITIONS = [
        CampaignRecord::STATUS_PENDING   => [CampaignRecord::STATUS_SENT],
        CampaignRecord::STATUS_READY     => [CampaignRecord::STATUS_SENT, CampaignRecord::STATUS_DELIVERED, CampaignRecord::STATUS_READ],
        CampaignRecord::STATUS_SENT      => [CampaignRecord::STATUS_DELIVERED, CampaignRecord::STATUS_READ],
        CampaignRecord::STATUS_DELIVERED => [CampaignRecord::STATUS_READ],
        CampaignRecord::STATUS_READ      => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Attempts to apply the transition.
     * Returns true if applied, false if the transition is not allowed.
     */
    public static function transition(CampaignRecord &$record, string $newStatus): bool
    {
        $canTransition = self::canTransition($record->status, $newStatus);

        Log::debug('[CampaignRecordStateMachine] Transition attempt', [
            'from'           => $record->status,
            'to'             => $newStatus,
            'can_transition' => $canTransition,
            'transitions'    => self::TRANSITIONS[$record->status] ?? 'status not found in map',
        ]);

        if (!$canTransition) {
            return false;
        }
        
        $record->setStatus($newStatus);
        $record->save();

        Log::debug('[CampaignRecordStateMachine] Transition applied', [
            'from'           => $record->status,
            'to'             => $newStatus,
            'can_transition' => $canTransition,
            'record'         => $record,
        ]);
        
        return true;
    }
}