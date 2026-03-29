<?php

namespace App\Http\Controllers\Api\V1\Campaign\Record;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\Record\UpdateCampaignRecordRequest;
use App\Models\CampaignRecord;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PutCampaignRecordController extends Controller
{
    public function __invoke(UpdateCampaignRecordRequest $request, CampaignRecord $record): JsonResponse
    {
        $record->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Registro actualizado correctamente.',
            'data' => $record,
        ], Response::HTTP_OK);
    }
}
