<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * F41：以 sender 本人名義，對每個 recipient 建立/取得 conversation 並發送 content 訊息。
 */
class ProcessUserBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public readonly int $broadcastId,
        public readonly array $recipientIds,
    ) {}

    public function handle(): void
    {
        $broadcast = UserBroadcast::find($this->broadcastId);
        if (!$broadcast) return;

        $sender = User::find($broadcast->sender_id);
        if (!$sender) {
            $broadcast->update(['status' => 'failed']);
            return;
        }

        $successCount = 0;

        foreach ($this->recipientIds as $rid) {
            $recipient = User::find($rid);
            if (!$recipient || $recipient->status !== 'active') continue;

            try {
                $min = min($sender->id, $recipient->id);
                $max = max($sender->id, $recipient->id);
                $conv = Conversation::firstOrCreate(
                    ['user_a_id' => $min, 'user_b_id' => $max],
                    ['uuid' => (string) Str::uuid()],
                );

                $msg = Message::create([
                    'uuid' => (string) Str::uuid(),
                    'conversation_id' => $conv->id,
                    'sender_id' => $sender->id,
                    'type' => 'text',
                    'content' => $broadcast->content,
                    'sent_at' => now(),
                ]);

                $conv->update([
                    'last_message_id' => $msg->id,
                    'last_message_at' => $msg->sent_at,
                ]);
                if ($conv->user_a_id === $sender->id) {
                    $conv->increment('unread_count_b');
                } else {
                    $conv->increment('unread_count_a');
                }

                $successCount++;
            } catch (\Throwable $e) {
                Log::error("[UserBroadcast #{$broadcast->id}] failed for user {$rid}: " . $e->getMessage());
            }
        }

        $broadcast->update([
            'status' => 'completed',
            'sent_at' => now(),
            'recipient_count' => $successCount,
        ]);
    }
}
