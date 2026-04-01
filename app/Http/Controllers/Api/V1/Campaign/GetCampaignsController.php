<?php

namespace App\Http\Controllers\Api\V1\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\GetCampaignsRequest;
use App\Http\Requests\PaginationRequest;
use App\Models\Campaign;
use App\Models\Channel;
use App\Models\User;
use App\Services\QueryFilterService;
use MongoDB\BSON\ObjectId;

class GetCampaignsController extends Controller
{
    public function __invoke(
        GetCampaignsRequest $request,
        PaginationRequest $pagination,
        QueryFilterService $filterService
    )
    {
        $perPage = $pagination->getPerPage();
        $filters = $request->getFilters();
        $coordinationId = $request->getValueByField('coordination_id');

        if (!empty($coordinationId)) {
            $coordinationIds = (array) $request->getValueByField('coordination_id');

            // Obtain channels grouped by coordination_id
            $channelsGrouped = Channel::whereIn('coordination_id', $coordinationIds)
                ->get(['id', 'coordination_id'])
                ->groupBy('coordination_id');

            // Obtain all channels ids
            $channelIds = $channelsGrouped
                ->flatten()
                ->pluck('id')
                ->map(fn($id) => new ObjectId($id));
            
            // Coordinations for the name (a single query)
            $coordinations = User::whereIn('id', $coordinationIds)
                ->get(['id', 'name'])
                ->keyBy('id');

            // Query base
            $campaignsQuery = Campaign::whereIn('channel_id', $channelIds)->with('statistics');

            $request->remove('coordination_id');
            $filters = $request->getFilters();

            $campaigns = $filterService->apply($campaignsQuery, $filters)->get();

            // 🔥 Agrupar campañas por coordination_id
            $result = $channelsGrouped->map(function ($channels, $coordinationId) use ($campaigns, $coordinations) {
                $channelIds = $channels->pluck('id')->map(fn($id) => new ObjectId($id));

                return [
                    'coordination_id' => $coordinationId,
                    'administration' => $coordinations->get($coordinationId)?->name ?? 'N/A',
                    'campaigns' => $campaigns->whereIn('channel_id', $channelIds)->values()
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        }

        $campaignsQuery = Campaign::query();

        $campaigns = $filterService
            ->apply($campaignsQuery, $filters);
        
        $campaigns = $campaigns
            ->paginate($perPage, ['*'], 'page', $pagination->getPage())
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $campaigns
        ]);
    }
}
