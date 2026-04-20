<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 追蹤碼設定（空字串 = 未啟用）
 *
 * 管理員在後台 SeoPage 填入後，透過 SystemControlController::updateTracking
 * 更新，前端透過 GET /api/v1/site-config（60s Cache）取得。
 */
class TrackingSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key_name' => 'tracking_ga_measurement_id',
                'value' => '',
                'value_type' => 'string',
                'description' => 'Google Analytics 4 Measurement ID（格式：G-XXXXXXXXXX）',
            ],
            [
                'key_name' => 'tracking_fb_pixel_id',
                'value' => '',
                'value_type' => 'string',
                'description' => 'Facebook Pixel ID（純數字，未來擴充）',
            ],
            [
                'key_name' => 'tracking_gtm_id',
                'value' => '',
                'value_type' => 'string',
                'description' => 'Google Tag Manager ID（格式：GTM-XXXXXXX）',
            ],
        ];

        foreach ($defaults as $row) {
            DB::table('system_settings')->updateOrInsert(
                ['key_name' => $row['key_name']],
                array_merge($row, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
