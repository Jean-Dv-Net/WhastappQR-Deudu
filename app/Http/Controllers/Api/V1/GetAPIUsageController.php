<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Libraries\Whatsapp\Client;
use Illuminate\Http\JsonResponse;

class GetAPIUsageController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $apiUsage = Client::getAPIUsage();

        return response()->json([
            'success' => true,
            'data' => $apiUsage
        ]);
    }
}
