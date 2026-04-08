<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TestConversationsSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::where('id', '>', 1)->where('status', 'active')->get();
        if ($users->count() < 4) { $this->command->warn('Not enough users for conversations'); return; }

        $msgTemplates = [
            '你好！看到你的照片感覺很有氣質，想認識你 😊',
            '嗨～ 看到你的自我介紹感覺我們有很多共同點！',
            '你今天有出去玩嗎？', '最近工作很忙，終於有空上來聊天了',
            '你是哪裡人？台北嗎？', '週末有什麼計畫嗎？',
            '你喜歡吃什麼？我很愛美食 😋', '哈哈 真的嗎！好巧喔',
            '感覺你很有趣耶！', '這樣啊，那改天可以一起去 😊',
            '好啊！聽起來不錯', '哇 真的假的！太厲害了吧',
            '那你有沒有想一起喝咖啡？我知道一家不錯的地方',
            '下週末有空嗎？想邀你去看展覽', '好的，那就這樣說定了 😊',
        ];

        $pairs = [];
        $females = $users->where('gender', 'female')->values();
        $males = $users->where('gender', 'male')->values();

        // Create 15 conversations
        for ($i = 0; $i < min(15, $females->count() * $males->count()); $i++) {
            $f = $females[$i % $females->count()];
            $m = $males[$i % $males->count()];
            $minId = min($f->id, $m->id);
            $maxId = max($f->id, $m->id);
            $pairKey = "{$minId}-{$maxId}";
            if (isset($pairs[$pairKey])) continue;
            $pairs[$pairKey] = true;

            $daysAgo = $i < 10 ? rand(0, 7) : rand(14, 30);
            $msgCount = rand(5, 15);

            $conv = Conversation::create([
                'uuid' => Str::uuid()->toString(),
                'user_a_id' => $minId,
                'user_b_id' => $maxId,
                'last_message_at' => now()->subDays($daysAgo),
                'unread_count_a' => $i < 12 ? rand(0, 3) : 0,
                'unread_count_b' => $i < 12 ? rand(0, 2) : 0,
            ]);

            $lastMsg = null;
            for ($j = 0; $j < $msgCount; $j++) {
                $senderId = $j % 2 === 0 ? $minId : $maxId;
                $sentAt = now()->subDays($daysAgo)->addMinutes($j * rand(5, 60));
                $lastMsg = Message::create([
                    'uuid' => Str::uuid()->toString(),
                    'conversation_id' => $conv->id,
                    'sender_id' => $senderId,
                    'type' => 'text',
                    'content' => $msgTemplates[array_rand($msgTemplates)],
                    'is_read' => $j < $msgCount - 2 ? 1 : 0,
                    'read_at' => $j < $msgCount - 2 ? $sentAt->addMinutes(rand(1, 30)) : null,
                    'sent_at' => $sentAt,
                    'created_at' => $sentAt,
                ]);
            }

            if ($lastMsg) {
                $conv->update(['last_message_id' => $lastMsg->id, 'last_message_at' => $lastMsg->sent_at]);
            }
        }

        $this->command->info('Created ' . Conversation::count() . ' conversations, ' . Message::count() . ' messages');
    }
}
