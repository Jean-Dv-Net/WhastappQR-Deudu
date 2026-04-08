<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use Illuminate\Http\JsonResponse;

/**
 * Get Setting Controller.
 * 
 * This controller is responsible for retrieving system settings.
 */
class GetSettingsController extends Controller
{
    /**
     * Get system settings.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(string $key): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => SystemConfig::getByKey($key)
        ]);
    }
}
