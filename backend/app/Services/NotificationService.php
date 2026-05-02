<?php

namespace App\Services;

use App\Events\NotificationReceived;
use App\Models\Conversation;
use App\Models\FcmToken;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public function __construct(
        private readonly FcmService $fcmService,
    ) {}

    /**
     * @param bool $skipPush  F22：為 true 時跳過 FCM 推播（站內 + WebSocket 仍會發出）
     */
    public function notify(User $user, string $type, string $title, string $body, array $data = [], bool $skipPush = false): Notification
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

        // Push via FCM — skip if receiver is muted for this source or in global DND
        if (!$skipPush) {
            $tokens = FcmToken::where('user_id', $user->id)->pluck('token');
            foreach ($tokens as $token) {
                $this->fcmService->send($token, $title, $body, $data);
            }
        }

        return $notification;
    }

    // ── Quick helpers ────────────────────────────────────────

    public function notifyNewMessage(User $receiver, int $conversationId, User $sender): void
    {
        // F22：對話靜音或全域 DND 時，跳過 FCM 推播（站內通知仍寫入）
        $muted = Conversation::find($conversationId)?->isMutedBy($receiver->id) ?? false;
        $skipPush = $muted || $receiver->isInDndPeriod();

        $this->notify(
            $receiver,
            'new_message',
            "{$sender->nickname} 傳訊息給你",
            '你有一則新訊息',
            ['conversation_id' => $conversationId],
            $skipPush,
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

    public function notifySubscriptionExpiring(User $user, string $expiresAt, ?int $subscriptionId = null): void
    {
        $data = ['expires_at' => $expiresAt];
        if ($subscriptionId !== null) {
            $data['subscription_id'] = $subscriptionId;
        }
        $this->notify(
            $user,
            'subscription_expiring',
            '訂閱即將到期',
            "您的會員將於 {$expiresAt} 到期",
            $data,
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

    public function notifySubscriptionExpired(User $user, string $planName, ?int $subscriptionId = null): void
    {
        $data = ['plan_name' => $planName];
        if ($subscriptionId !== null) {
            $data['subscription_id'] = $subscriptionId;
        }
        $this->notify(
            $user,
            'subscription_expired',
            '您的訂閱已到期',
            "您的「{$planName}」會員資格已恢復為一般用戶。如需繼續享有付費功能，請至會員商城購買訂閱。",
            $data,
        );
    }
}
