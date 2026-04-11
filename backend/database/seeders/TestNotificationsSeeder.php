<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestNotificationsSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('id', '>', 1)->where('status', 'active')->take(10)->get();

        $templates = [
            ['type' => 'new_message', 'title' => '你有一則新訊息', 'body' => '有人傳訊息給你了'],
            ['type' => 'new_favorite', 'title' => '有人收藏了你', 'body' => '快去看看是誰吧'],
            ['type' => 'profile_visited', 'title' => '有人查看了你的資料', 'body' => ''],
            ['type' => 'date_invitation', 'title' => '你收到了約會邀請', 'body' => '查看約會邀請詳情'],
            ['type' => 'date_verified', 'title' => '約會驗證成功！', 'body' => '誠信分數 +5'],
            ['type' => 'credit_score_changed', 'title' => '誠信分數變更', 'body' => '你的誠信分數已更新'],
            ['type' => 'system', 'title' => '歡迎來到 MiMeet', 'body' => '完成個人資料可獲得誠信分數加分'],
            ['type' => 'subscription_activated', 'title' => '訂閱啟用成功', 'body' => '已啟用月方案'],
        ];

        foreach ($users as $user) {
            $count = rand(2, 5);
            for ($i = 0; $i < $count; $i++) {
                $tpl = $templates[array_rand($templates)];
                Notification::create([
                    'user_id' => $user->id,
                    'type' => $tpl['type'],
                    'title' => $tpl['title'],
                    'body' => $tpl['body'],
                    'is_read' => $i < $count - 1 ? 1 : 0,
                    'read_at' => $i < $count - 1 ? now()->subHours(rand(1, 48)) : null,
                    'created_at' => now()->subDays(rand(0, 20))->subHours(rand(0, 23)),
                ]);
            }
        }

        $this->command->info('Created ' . Notification::count() . ' notifications');
    }
}
