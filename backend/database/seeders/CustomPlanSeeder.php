<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomPlanSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $slug = 'plan' . str_pad($i, 2, '0', STR_PAD_LEFT);

            DB::table('subscription_plans')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name'             => $slug,
                    'price'            => 0,
                    'original_price'   => 0,
                    'currency'         => 'TWD',
                    'duration_days'    => 30,
                    'membership_level' => 2,
                    'features'         => json_encode([]),
                    'is_trial'         => false,
                    'is_active'        => false,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ],
            );
        }

        $this->command->info('Inserted/updated plan01 ~ plan10 (inactive, price=0)');
    }
}
