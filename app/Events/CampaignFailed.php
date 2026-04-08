<?php

namespace App\Events;

use App\Http\Resources\Campaign\CampaignResource;
use App\Models\Campaign;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $campaignId,
        public string $coordinationId
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('campaigns.finished.' . $this->coordinationId),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $campaign = Campaign::find($this->campaignId);
        return [
            'campaign_id' => $this->campaignId,
            'coordination_id' => $this->coordinationId,
            'campaign' => new CampaignResource($campaign),
        ];
    }

    /**
     * Name of the event.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'campaign.failed';
    }
}
