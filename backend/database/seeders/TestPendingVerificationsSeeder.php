<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Database\Seeder;

/**
 * 測試女性照片驗證 pending 情境
 *
 * - 取最多 5 位 Lv1 女性用戶（已驗證 email，但尚未升 Lv1.5）
 * - 建立 pending 狀態的 user_verifications 記錄
 * - 讓後台驗證審核佇列有資料可操作
 *
 * 假設 fresh 模式（先呼叫 mimeet:reset）
 */
class TestPendingVerificationsSeeder extends Seeder
{
    public function run(): void
    {
        $candidates = User::where('id', '>', 1)
            ->where('gender', 'female')
            ->where('membership_level', 1)
            ->where('email_verified', true)
            ->take(5)
            ->get();

        if ($candidates->isEmpty()) {
            $this->command->warn('[TestPendingVerificationsSeeder] 無符合條件的 Lv1 女性用戶，跳過');
            return;
        }

        foreach ($candidates as $user) {
            UserVerification::create([
                'user_id'     => $user->id,
                'random_code' => str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT),
                'photo_url'   => 'https://placehold.co/400x600?text=Verify+' . $user->id,
                'status'      => 'pending',
                'expires_at'  => now()->addDays(7),
            ]);
        }

        $this->command->info(sprintf(
            '[TestPendingVerificationsSeeder] ✅ 建立 %d 筆 pending 女性照片驗證',
            $candidates->count(),
        ));
    }
}
