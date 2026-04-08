<?php

namespace App\Http\Controllers\Api\V1\Campaign;

use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\CreateCampaignRequest;
use App\Models\Campaign;
use App\Models\CampaignRecord;
use MongoDB\BSON\ObjectId;

class PostCampaignController extends Controller
{
    public function __invoke(CreateCampaignRequest $request)
    {
        $campaign = Campaign::create([
            'channel_id' => $request->channel_id,
            'name' => $request->name,
            'has_attachment' => $request->has_attachment,
            'template_type' => $request->template_type,
            'template' => $request->template,
            'status' => Campaign::STATUS_BUILDING,
        ]);

        foreach ($request->records as $record) {
            $campaign->records()->create([
                'status' => CampaignRecord::STATUS_PENDING,
                'phone_number' => $record['phone_number'],
                'identification' => $record['identification'],
                'debtor_id' => $record['debtor_id'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Campaña creada exitosamente.',
            'data' => $campaign->load('records'),
        ]);
    }
}
