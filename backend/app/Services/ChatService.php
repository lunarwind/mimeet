<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Exceptions\DailyLimitException;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChatService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}
    public function getOrCreateConversation(int $userIdA, int $userIdB): Conversation
    {
        $min = min($userIdA, $userIdB);
        $max = max($userIdA, $userIdB);

        return Conversation::firstOrCreate(
            ['user_a_id' => $min, 'user_b_id' => $max],
            ['uuid' => Str::uuid()->toString()],
        );
    }

    public function isParticipant(int $conversationId, int $userId): bool
    {
        $conversation = Conversation::find($conversationId);
        return $conversation && $conversation->isParticipant($userId);
    }

    public function getConversationList(int $userId): Collection
    {
        return Conversation::where(function ($q) use ($userId) {
                $q->where('user_a_id', $userId)->where('deleted_by_a', 0);
            })
            ->orWhere(function ($q) use ($userId) {
                $q->where('user_b_id', $userId)->where('deleted_by_b', 0);
            })
            ->with(['userA:id,nickname,avatar_url', 'userB:id,nickname,avatar_url', 'lastMessage'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(function (Conversation $conv) use ($userId) {
                $other = $conv->getOtherUser($userId);
                $lastMsg = $conv->lastMessage;

                return [
                    'id' => $conv->id,
                    'uuid' => $conv->uuid,
                    'other_user' => [
                        'id' => $other->id,
                        'nickname' => $other->nickname,
                        'avatar_url' => $other->avatar_url,
                    ],
                    'last_message' => $lastMsg ? [
                        'content' => $lastMsg->is_recalled ? '（訊息已收回）' : $lastMsg->content,
                        'sent_at' => $lastMsg->sent_at->toISOString(),
                        'type' => $lastMsg->type,
                    ] : null,
                    'unread_count' => $conv->getUnreadCount($userId),
                    'updated_at' => $conv->last_message_at?->toISOString(),
                ];
            });
    }

    public function getMessages(int $conversationId, ?string $cursor, int $perPage = 30): array
    {
        $query = Message::where('conversation_id', $conversationId)
            ->orderByDesc('sent_at');

        if ($cursor) {
            $query->where('id', '<', $cursor);
        }

        $messages = $query->limit($perPage + 1)->get();
        $hasMore = $messages->count() > $perPage;

        if ($hasMore) {
            $messages = $messages->slice(0, $perPage);
        }

        $data = $messages->map(fn (Message $msg) => [
            'id' => $msg->id,
            'uuid' => $msg->uuid,
            'sender_id' => $msg->sender_id,
            'type' => $msg->type,
            'content' => $msg->is_recalled ? null : $msg->content,
            'image_url' => $msg->is_recalled ? null : $msg->image_url,
            'is_read' => $msg->is_read,
            'is_recalled' => $msg->is_recalled,
            'sent_at' => $msg->sent_at->toISOString(),
        ])->values();

        return [
            'data' => $data,
            'next_cursor' => $hasMore ? (string) $messages->last()->id : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * @throws DailyLimitException
     */
    public function sendMessage(int $conversationId, int $senderId, string $content): Message
    {
        $user = User::findOrFail($senderId);
        $this->checkDailyLimit($user);

        $conversation = Conversation::findOrFail($conversationId);

        // Credit score check (PRD §4.3.3):
        // - Lv3 paid members bypass this check (reverse-tier messaging privilege)
        // - Others: can only message users with equal or lower credit_score
        if ($user->membership_level < 3) {
            $receiverId = $conversation->user_a_id === $senderId
                ? $conversation->user_b_id
                : $conversation->user_a_id;
            $receiver = User::find($receiverId);

            if ($receiver && $user->credit_score < $receiver->credit_score) {
                throw new \App\Exceptions\CreditScoreRestrictionException(
                    '誠信分數不足，無法向較高分數的用戶發送訊息'
                );
            }
        }

        $message = Message::create([
            'uuid' => Str::uuid()->toString(),
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'type' => 'text',
            'content' => $content,
            'sent_at' => now(),
        ]);

        // Update conversation
        $conversation->update([
            'last_message_id' => $message->id,
            'last_message_at' => $message->sent_at,
        ]);

        // Increment unread count for the other user
        if ($conversation->user_a_id === $senderId) {
            $conversation->increment('unread_count_b');
        } else {
            $conversation->increment('unread_count_a');
        }

        // Increment daily counter
        $cacheKey = "msg_daily:{$senderId}:" . now()->format('Y-m-d');
        $count = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $count + 1, now()->endOfDay());

        // Broadcast (silent fail in test/dev)
        try {
            broadcast(new ChatMessageSent($message));
        } catch (\Exception) {
            // Broadcast driver not available
        }

        // Notify receiver
        $receiverId = $conversation->user_a_id === $senderId
            ? $conversation->user_b_id
            : $conversation->user_a_id;
        $receiver = User::find($receiverId);
        if ($receiver) {
            $this->notificationService->notifyNewMessage($receiver, $conversationId, $user);
        }

        return $message;
    }

    public function markAsRead(int $conversationId, int $userId): void
    {
        // Mark all unread messages as read (where sender is not the current user)
        Message::where('conversation_id', $conversationId)
            ->where('sender_id', '!=', $userId)
            ->where('is_read', 0)
            ->update([
                'is_read' => 1,
                'read_at' => now(),
            ]);

        // Reset unread count
        $conversation = Conversation::findOrFail($conversationId);
        if ($conversation->user_a_id === $userId) {
            $conversation->update(['unread_count_a' => 0]);
        } else {
            $conversation->update(['unread_count_b' => 0]);
        }
    }

    public function softDelete(int $conversationId, int $userId): void
    {
        $conversation = Conversation::findOrFail($conversationId);

        if ($conversation->user_a_id === $userId) {
            $conversation->update(['deleted_by_a' => 1]);
        } else {
            $conversation->update(['deleted_by_b' => 1]);
        }
    }

    /**
     * @throws DailyLimitException
     */
    private function checkDailyLimit(User $user): void
    {
        // Per-level daily message limits (PRD §4.3.3)
        $level = (float) $user->membership_level;
        if ($level >= 3) return; // Lv3 paid: unlimited

        $limits = [
            0   => 5,    // Lv0
            1   => 30,   // Lv1
            1.5 => 100,  // Lv1.5 (verified female)
            2   => 30,   // Lv2
        ];
        $limit = $limits[$level] ?? 30;

        $cacheKey = "msg_daily:{$user->id}:" . now()->format('Y-m-d');
        $count = Cache::get($cacheKey, 0);

        if ($count >= $limit) {
            throw new DailyLimitException("今日訊息已達上限（{$limit}則）");
        }
    }
}
