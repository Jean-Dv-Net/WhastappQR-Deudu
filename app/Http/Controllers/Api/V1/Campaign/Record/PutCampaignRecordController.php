<?php

namespace App\Http\Controllers\Api\V1\Campaign\Record;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\Record\UpdateCampaignRecordRequest;
use App\Models\Campaign;
use App\Models\CampaignRecord;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PutCampaignRecordController extends Controller
{
    public function __invoke(UpdateCampaignRecordRequest $request, CampaignRecord $record): JsonResponse
    {
        if ($request->has('status')) {
            $record->update([
                'status' => $request->get('status'),
                'observation' => $request->get('observation'),
            ]);
        } else {
            $record->update([
                'message' => $request->get('message'),
                'attachment_url' => $request->get('attachment_url'),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registro actualizado correctamente.',
            'data' => $record,
        ], Response::HTTP_OK);
    }
}
