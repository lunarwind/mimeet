<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * 公開站點設定（不需登入）— 提供前端追蹤碼等非敏感設定
 */
class SiteConfigController extends Controller
{
    public const CACHE_KEY = 'site_config';
    public const CACHE_TTL = 60;

    public function index(): JsonResponse
    {
        $config = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return [
                'tracking' => [
                    'ga_measurement_id' => SystemSetting::get('tracking_ga_measurement_id') ?: null,
                    'fb_pixel_id' => SystemSetting::get('tracking_fb_pixel_id') ?: null,
                    'gtm_id' => SystemSetting::get('tracking_gtm_id') ?: null,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }
}
