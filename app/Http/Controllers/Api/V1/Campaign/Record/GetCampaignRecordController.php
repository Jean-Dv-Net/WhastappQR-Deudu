<?php

namespace App\Http\Controllers\Api\V1\Campaign\Record;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\Record\GetCampaignRecordRequest;
use App\Http\Requests\PaginationRequest;
use App\Models\CampaignRecord;
use App\Services\QueryFilterService;
use Illuminate\Http\JsonResponse;

class GetCampaignRecordController extends Controller
{
    public function __invoke(
        GetCampaignRecordRequest $request,
        PaginationRequest $pagination,
        QueryFilterService $filterService
    ): JsonResponse {
        $perPage = $pagination->getPerPage();
        $page = $pagination->getPage();

        $query = CampaignRecord::query()->with('debtor');

        $filters = $request->getFilters();
        $filterService->withCasts([
            'campaign_id' => 'object_id'
        ])->apply($query, $filters);
        
        $campaignRecords = $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $campaignRecords
        ]);
    }
}
