<?php

namespace App\Http\Resources\Campaign;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoordinationCampaignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'coordination_id' => $this['coordination_id'],
            'coordination_name' => $this['coordination_name'],

            'campaigns' => CampaignResource::collection($this['campaigns']),
        ];
    }
}
