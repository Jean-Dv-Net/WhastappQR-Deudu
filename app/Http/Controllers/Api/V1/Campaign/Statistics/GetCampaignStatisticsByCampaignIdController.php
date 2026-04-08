<?php

namespace App\Http\Controllers\Api\V1\Campaign\Statistics;

use App\Http\Controllers\Controller;
use App\Models\CampaignStatistic;
use Illuminate\Http\JsonResponse;
use MongoDB\BSON\ObjectId;
use Symfony\Component\HttpFoundation\Response;

class GetCampaignStatisticsByCampaignIdController extends Controller
{
    public function __invoke(string $campaignId): JsonResponse
    {
        $statistic = CampaignStatistic::where('campaign_id', new ObjectId($campaignId))->first();

        if (!$statistic) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron estadísticas para esta campaña.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data'    => $statistic,
        ], Response::HTTP_OK);
    }
}
