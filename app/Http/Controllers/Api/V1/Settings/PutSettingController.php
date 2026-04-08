<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CreateOrUpdateSettingRequest;
use App\Models\SystemConfig;
use Illuminate\Http\JsonResponse;

class PutSettingController extends Controller
{
    public function __invoke(string $key, CreateOrUpdateSettingRequest $request): JsonResponse
    {
        $setting = SystemConfig::updateOrCreate(
            ['key' => $key],
            [
                'settings'   => $request->input('settings')
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $setting->settings
        ]);
    }
}
