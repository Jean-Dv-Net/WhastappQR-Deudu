<?php

namespace App\Http\Controllers\Api\V1\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\GetCampaignsRequest;
use App\Http\Requests\PaginationRequest;
use App\Models\Campaign;
use App\Models\Channel;
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
            $coordinationId = (int) $coordinationId;
            
            $channelIds = Channel::where('coordination_id', $coordinationId)
                ->pluck('id')
                ->map(fn($id) => new ObjectId($id));

            /** @var \Illuminate\Database\Eloquent\Builder|\MongoDB\Laravel\Eloquent\Builder $campaignsQuery */
            $campaignsQuery = Campaign::whereIn('channel_id', $channelIds);

            $request->remove('coordination_id');
            $filters = $request->getFilters();

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
