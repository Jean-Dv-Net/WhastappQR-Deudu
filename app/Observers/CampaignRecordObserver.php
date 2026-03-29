<?php

namespace App\Observers;

use App\Models\Campaign;
use App\Models\CampaignRecord;

class CampaignRecordObserver
{
    /**
     * Handle the CampaignRecord "updated" event.
     */
    public function updated(CampaignRecord $campaignRecord): void
    {
        // Only act if the status change to ready
        if (!$campaignRecord->wasChanged('status') 
            || $campaignRecord->status !== CampaignRecord::STATUS_READY) {
            return;
        }

        $campaign = $campaignRecord->campaign;

        if ($campaign->isReady()) {
            // TODO: Dispatch job to send campaign
            $campaign->update([
                'status' => Campaign::STATUS_SENDING,
            ]);
        }
    }
}
