<?php

namespace App\Http\Resources\Campaign;

use App\Trait\FormatsDates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    use FormatsDates;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'channel_id' => (string) $this->channel_id,
            'name' => $this->name,
            'has_attachment' => (bool) $this->has_attachment,
            'template_type' => $this->template_type,
            'template' => $this->template,
            'status' => $this->status,
            
            'created_at' => $this->formatDate($this->created_at),

            'id' => (string) $this->_id,

            'statistics' => [
                'campaign_id' => (string) $this->statistics?->campaign_id,
                'pending' => $this->statistics?->pending ?? 0,
                'sent' => $this->statistics?->sent ?? 0,
                'delivered' => $this->statistics?->delivered ?? 0,
                'read' => $this->statistics?->read ?? 0,
                'failed' => $this->statistics?->failed ?? 0,
                'id' => (string) $this->statistics?->_id,
                'delivery_rate' => $this->statistics?->delivery_rate ?? 0,
                'read_rate' => $this->statistics?->read_rate ?? 0,
            ],
        ];
    }
}
