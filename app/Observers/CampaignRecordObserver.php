<?php

namespace App\Observers;

use App\Jobs\SendCampaignMessagesJob;
use App\Models\Campaign;
use App\Models\CampaignRecord;
use App\Models\CampaignStatistic;

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
            $campaign->update([
                'status' => Campaign::STATUS_SENDING,
            ]);

            // Initialize campaign statistics
            $totalRecords = $campaign->records()->count();
            CampaignStatistic::updateOrCreate(
                ['campaign_id' => $campaign->id],
                [
                    'pending'   => $totalRecords,
                    'sent'      => 0,
                    'delivered'  => 0,
                    'read'      => 0,
                    'failed'    => 0,
                ]
            );

            // Dispatch job to send campaign messages
            SendCampaignMessagesJob::dispatch($campaign);
        }
    }
}
