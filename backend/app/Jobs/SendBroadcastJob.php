<?php

namespace App\Jobs;

use App\Models\BroadcastCampaign;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendBroadcastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly BroadcastCampaign $campaign,
    ) {}

    public function handle(): void
    {
        $filters = $this->campaign->filters ?? [];
        $query = User::where('status', 'active')->where('id', '!=', 1);

        if (!empty($filters['gender']) && $filters['gender'] !== 'all') {
            $query->where('gender', $filters['gender']);
        }
        if (isset($filters['level_min'])) {
            $query->where('membership_level', '>=', $filters['level_min']);
        }
        if (isset($filters['level_max'])) {
            $query->where('membership_level', '<=', $filters['level_max']);
        }
        if (isset($filters['credit_min'])) {
            $query->where('credit_score', '>=', $filters['credit_min']);
        }
        if (isset($filters['credit_max'])) {
            $query->where('credit_score', '<=', $filters['credit_max']);
        }

        $mode = $this->campaign->delivery_mode;
        $sentCount = 0;

        $query->select('id')->chunkById(100, function ($users) use ($mode, &$sentCount) {
            foreach ($users as $user) {
                try {
                    if ($mode === 'notification' || $mode === 'both') {
                        DB::table('notifications')->insert([
                            'user_id' => $user->id,
                            'type' => 'broadcast',
                            'title' => $this->campaign->title,
                            'body' => $this->campaign->content,
                            'is_read' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    if ($mode === 'dm' || $mode === 'both') {
                        $this->sendDm($user->id);
                    }

                    $sentCount++;
                } catch (\Throwable $e) {
                    Log::warning("Broadcast send failed for user {$user->id}", [
                        'campaign_id' => $this->campaign->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->campaign->update(['sent_count' => $sentCount]);
        });

        $this->campaign->update([
            'status' => 'completed',
            'sent_count' => $sentCount,
            'completed_at' => now(),
        ]);

        Log::info("[Broadcast] Campaign #{$this->campaign->id} completed", [
            'mode' => $mode,
            'sent_count' => $sentCount,
        ]);
    }

    private function sendDm(int $userId): void
    {
        $systemUserId = 1;

        $conversation = Conversation::where(function ($q) use ($systemUserId, $userId) {
            $q->where('user_a_id', $systemUserId)->where('user_b_id', $userId);
        })->orWhere(function ($q) use ($systemUserId, $userId) {
            $q->where('user_a_id', $userId)->where('user_b_id', $systemUserId);
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'uuid' => Str::uuid()->toString(),
                'user_a_id' => $systemUserId,
                'user_b_id' => $userId,
                'unread_count_a' => 0,
                'unread_count_b' => 0,
            ]);
        }

        $message = Message::create([
            'uuid' => Str::uuid()->toString(),
            'conversation_id' => $conversation->id,
            'sender_id' => $systemUserId,
            'type' => 'text',
            'content' => $this->campaign->content,
            'sent_at' => now(),
        ]);

        $unreadCol = $conversation->user_a_id === $systemUserId
            ? 'unread_count_b'
            : 'unread_count_a';

        $conversation->update([
            'last_message_id' => $message->id,
            'last_message_at' => now(),
            $unreadCol => DB::raw("{$unreadCol} + 1"),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->campaign->update(['status' => 'failed']);
        Log::error('Broadcast job failed', [
            'campaign_id' => $this->campaign->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
