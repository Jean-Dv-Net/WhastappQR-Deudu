<?php

namespace App\Http\Controllers\Api\V1\Campaign\Statistics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\Statistics\GetCampaignStatisticsRequest;
use App\Models\CampaignStatistic;
use App\Models\Channel;
use App\Models\Campaign;
use App\Services\QueryFilterService;
use Illuminate\Http\JsonResponse;
use MongoDB\BSON\ObjectId;

class GetCampaignStatisticsController extends Controller
{
    public function __invoke(
        GetCampaignStatisticsRequest $request,
        QueryFilterService $filterService
    ): JsonResponse
    {
        $filters = $request->getFilters();
        $coordinationId = $request->getValueByField('coordination_id');

        if ($coordinationId !== null) {
            $coordinationId = (int) $coordinationId;
            
            $channelIds = Channel::where('coordination_id', $coordinationId)
                ->pluck('id')
                ->map(fn($id) => new ObjectId($id));
            $campaignIds = Campaign::whereIn('channel_id', $channelIds)
                ->pluck('id')
                ->map(fn($id) => new ObjectId($id));
            $statistics = CampaignStatistic::whereIn('campaign_id', $campaignIds)->get();
            
            $sent = $statistics->sum('sent');
            $delivered = $statistics->sum('delivered');
            $read = $statistics->sum('read');
            
            $deliveryRate = $sent > 0 ? round(($delivered / $sent) * 100, 2) : 0;
            $readRate = $sent > 0 ? round(($read / $sent) * 100, 2) : 0;

            $aggregated = [
                'pending' => $statistics->sum('pending'),
                'sent' => $sent,
                'delivered' => $delivered,
                'read' => $read,
                'failed' => $statistics->sum('failed'),
                'delivery_rate' => $deliveryRate,
                'read_rate' => $readRate,
            ];

            return response()->json([
                'success' => true,
                'data' => $aggregated
            ]);
        }

        $query = CampaignStatistic::query();

        $filterService->withCasts([
            'campaign_id' => 'object_id'
        ])->apply($query, $filters);

        $statistics = $query->get();

        return response()->json([
            'success' => true,
            'data' => $statistics
        ]);
    }
}
