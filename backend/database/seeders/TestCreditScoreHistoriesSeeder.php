<?php

namespace Database\Seeders;

use App\Models\CreditScoreHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestCreditScoreHistoriesSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('id', '>', 1)->get();

        $events = [
            ['type' => 'registration', 'delta' => 10, 'reason' => '完成帳號註冊'],
            ['type' => 'email_verify', 'delta' => 10, 'reason' => 'Email 驗證完成'],
            ['type' => 'photo_verify', 'delta' => 15, 'reason' => '進階照片驗證通過'],
            ['type' => 'date_verify', 'delta' => 5, 'reason' => 'QR碼約會驗證成功（GPS通過）'],
            ['type' => 'report', 'delta' => -10, 'reason' => '收到有效檢舉'],
            ['type' => 'subscription', 'delta' => 5, 'reason' => '首次訂閱付費方案'],
            ['type' => 'admin', 'delta' => -5, 'reason' => '管理員手動調整（違規警告）'],
        ];

        foreach ($users as $user) {
            $count = rand(2, 5);
            $score = 50; // starting base
            for ($i = 0; $i < $count; $i++) {
                $event = $events[array_rand($events)];
                $before = $score;
                $score = max(0, min(100, $score + $event['delta']));

                CreditScoreHistory::create([
                    'user_id' => $user->id,
                    'delta' => $event['delta'],
                    'score_before' => $before,
                    'score_after' => $score,
                    'type' => $event['type'],
                    'reason' => $event['reason'],
                    'operator_id' => $event['type'] === 'admin' ? 1 : null,
                    'created_at' => now()->subDays(rand(5, 50))->subHours(rand(0, 23)),
                ]);
            }
        }

        $this->command->info('Created ' . CreditScoreHistory::count() . ' credit score histories');
    }
}
