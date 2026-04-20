<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PointPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['slug' => 'pack_50',    'name' => '輕量包',   'points' => 50,   'bonus_points' => 0,   'price' => 150,  'sort_order' => 1, 'is_active' => true,  'description' => '小額嘗鮮'],
            ['slug' => 'pack_150',   'name' => '標準包',   'points' => 150,  'bonus_points' => 10,  'price' => 390,  'sort_order' => 2, 'is_active' => true,  'description' => '最受歡迎'],
            ['slug' => 'pack_500',   'name' => '豪華包',   'points' => 500,  'bonus_points' => 50,  'price' => 990,  'sort_order' => 3, 'is_active' => true,  'description' => '超值大包'],
            ['slug' => 'pack_1200',  'name' => '尊爵包',   'points' => 1200, 'bonus_points' => 200, 'price' => 1990, 'sort_order' => 4, 'is_active' => true,  'description' => '頂級尊榮'],
            ['slug' => 'custom_01',  'name' => '自訂方案 1', 'points' => 0, 'bonus_points' => 0, 'price' => 0, 'sort_order' => 5,  'is_active' => false, 'description' => null],
            ['slug' => 'custom_02',  'name' => '自訂方案 2', 'points' => 0, 'bonus_points' => 0, 'price' => 0, 'sort_order' => 6,  'is_active' => false, 'description' => null],
            ['slug' => 'custom_03',  'name' => '自訂方案 3', 'points' => 0, 'bonus_points' => 0, 'price' => 0, 'sort_order' => 7,  'is_active' => false, 'description' => null],
            ['slug' => 'custom_04',  'name' => '自訂方案 4', 'points' => 0, 'bonus_points' => 0, 'price' => 0, 'sort_order' => 8,  'is_active' => false, 'description' => null],
            ['slug' => 'custom_05',  'name' => '自訂方案 5', 'points' => 0, 'bonus_points' => 0, 'price' => 0, 'sort_order' => 9,  'is_active' => false, 'description' => null],
            ['slug' => 'custom_06',  'name' => '自訂方案 6', 'points' => 0, 'bonus_points' => 0, 'price' => 0, 'sort_order' => 10, 'is_active' => false, 'description' => null],
        ];

        foreach ($packages as $pkg) {
            DB::table('point_packages')->updateOrInsert(
                ['slug' => $pkg['slug']],
                array_merge($pkg, ['created_at' => now(), 'updated_at' => now()]),
            );
        }

        // F40 點數系統 system_settings（7 筆）
        $settings = [
            ['key_name' => 'point_cost_stealth',            'value' => '10', 'value_type' => 'integer', 'description' => '隱身模式 24h 消費點數'],
            ['key_name' => 'point_cost_reverse_msg',        'value' => '5',  'value_type' => 'integer', 'description' => '逆區間訊息消費點數'],
            ['key_name' => 'point_cost_super_like',         'value' => '3',  'value_type' => 'integer', 'description' => '超級讚消費點數'],
            ['key_name' => 'point_cost_broadcast_per_user', 'value' => '2',  'value_type' => 'integer', 'description' => '廣播每位接收者消費點數'],
            ['key_name' => 'broadcast_user_daily_limit',    'value' => '1',  'value_type' => 'integer', 'description' => '用戶每日廣播次數上限'],
            ['key_name' => 'broadcast_user_max_recipients', 'value' => '50', 'value_type' => 'integer', 'description' => '每次廣播最多接收人數'],
            ['key_name' => 'stealth_duration_hours',        'value' => '24', 'value_type' => 'integer', 'description' => '隱身持續時數'],
        ];

        foreach ($settings as $s) {
            DB::table('system_settings')->updateOrInsert(
                ['key_name' => $s['key_name']],
                array_merge($s, ['created_at' => now(), 'updated_at' => now()]),
            );
        }

        $this->command->info('  ✓ PointPackageSeeder: 10 packages + 7 settings upserted');
    }
}
