<?php

namespace App\StateMachines;

use App\Models\CampaignRecord;

use function in_array;

/**
 * State machine for CampaignRecord model.
 */
class CampaignRecordStateMachine
{
    private const array TRANSITIONS = [
        CampaignRecord::STATUS_PENDING   => [CampaignRecord::STATUS_SENT],
        CampaignRecord::STATUS_SENT      => [CampaignRecord::STATUS_DELIVERED, CampaignRecord::STATUS_READ],
        CampaignRecord::STATUS_DELIVERED => [CampaignRecord::STATUS_READ],
        CampaignRecord::STATUS_READ      => [], // terminal state
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /**
     * Attempts to apply the transition.
     * Returns true if applied, false if the transition is not allowed.
     */
    public static function transition(CampaignRecord $record, string $newStatus): bool
    {
        if (!self::canTransition($record->status, $newStatus)) {
            return false;
        }

        $record->update(['status' => $newStatus]);
        return true;
    }
}