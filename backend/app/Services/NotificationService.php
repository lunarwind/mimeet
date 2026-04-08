<?php

namespace App\Services;

use App\Events\NotificationReceived;
use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public function __construct(
        private readonly FcmService $fcmService,
    ) {}

    public function notify(User $user, string $type, string $title, string $body, array $data = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
            'created_at' => now(),
        ]);

        // Broadcast via WebSocket (silent fail in test/dev)
        try {
            broadcast(new NotificationReceived($user->id, $notification->toArray()));
        } catch (\Exception) {
            // Broadcast driver not available
        }

        // Push via FCM
        $tokens = FcmToken::where('user_id', $user->id)->pluck('token');
        foreach ($tokens as $token) {
            $this->fcmService->send($token, $title, $body, $data);
        }

        return $notification;
    }

    // ── Quick helpers ────────────────────────────────────────

    public function notifyNewMessage(User $receiver, int $conversationId, User $sender): void
    {
        $this->notify(
            $receiver,
            'new_message',
            "{$sender->nickname} 傳訊息給你",
            '你有一則新訊息',
            ['conversation_id' => $conversationId],
        );
    }

    public function notifyNewFavorite(User $receiver, User $follower): void
    {
        $this->notify(
            $receiver,
            'new_favorite',
            "{$follower->nickname} 收藏了你",
            '',
            ['user_id' => $follower->id],
        );
    }

    public function notifyProfileVisited(User $receiver, User $visitor): void
    {
        $this->notify(
            $receiver,
            'profile_visited',
            "{$visitor->nickname} 查看了你的資料",
            '',
            ['user_id' => $visitor->id],
        );
    }

    public function notifyTicketReplied(User $reporter, int $reportId): void
    {
        $this->notify(
            $reporter,
            'ticket_replied',
            '你的回報有新回覆',
            '管理員已回覆您的檢舉案件',
            ['report_id' => $reportId],
        );
    }

    public function notifyDateInvitation(User $invitee, int $dateId, User $inviter): void
    {
        $this->notify(
            $invitee,
            'date_invitation',
            "{$inviter->nickname} 邀請你約會",
            '查看約會邀請詳情',
            ['date_id' => $dateId],
        );
    }

    public function notifyDateVerified(User $user, int $scoreChange): void
    {
        $this->notify(
            $user,
            'date_verified',
            '約會驗證成功！',
            "誠信分數 +{$scoreChange}",
            ['score_change' => $scoreChange],
        );
    }

    public function notifySubscriptionExpiring(User $user, string $expiresAt): void
    {
        $this->notify(
            $user,
            'subscription_expiring',
            '訂閱即將到期',
            "您的會員將於 {$expiresAt} 到期",
            ['expires_at' => $expiresAt],
        );
    }

    public function notifySubscriptionActivated(User $user, string $planName): void
    {
        $this->notify(
            $user,
            'subscription_activated',
            '訂閱啟用成功！',
            "已啟用 {$planName}",
            ['plan_name' => $planName],
        );
    }
}
