<?php

namespace App\Services;

use App\Mail\TicketProcessedMail;
use App\Models\Report;
use Illuminate\Support\Facades\Mail;

/**
 * D.3 雙軌通知（用戶決策 Q4-Q8）：
 * - active user → 站內訊息（既有 NotificationService）
 * - suspended/auto_suspended user → email（Resend，既有設施）
 *
 * 不在這裡判斷 user.status — 由 caller 傳入 $useEmail（race-safe snapshot 留在 caller 端）。
 */
class TicketNotificationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function notifyTicketProcessed(
        Report $ticket,
        string $newStatus,
        string $adminReply,
        bool $useEmail,
    ): void {
        $reporter = $ticket->reporter;
        if ($reporter === null) {
            return;
        }

        $isAppeal = $ticket->type === 'appeal';

        if ($useEmail) {
            // 情境 2：email（queued via TicketProcessedMail::ShouldQueue）
            Mail::to($reporter->email)
                ->queue(new TicketProcessedMail(
                    ticket: $ticket,
                    newStatus: $newStatus,
                    adminReply: $adminReply,
                    isAppeal: $isAppeal,
                    reporterNickname: $reporter->nickname ?? $reporter->email,
                ));
            return;
        }

        // 情境 1：站內訊息（重用既有 NotificationService::notify）
        // type='ticket_replied' 已在 notifications ENUM 內
        $this->notificationService->notify(
            $reporter,
            'ticket_replied',
            $this->buildInAppTitle($newStatus, $isAppeal),
            $this->buildInAppBody($newStatus, $adminReply, $isAppeal),
            [
                'ticket_id' => $ticket->id,
                'ticket_status' => $newStatus,
                'ticket_type' => $ticket->type,
            ],
        );
    }

    private function buildInAppTitle(string $status, bool $isAppeal): string
    {
        if ($isAppeal) {
            return $status === 'resolved' ? '您的申訴已核准' : '您的申訴未通過';
        }
        return '您的回報已處理';
    }

    private function buildInAppBody(string $status, string $adminReply, bool $isAppeal): string
    {
        if ($isAppeal && $status === 'resolved') {
            $reply = $adminReply !== '' ? "\n\n管理員回覆：{$adminReply}" : '';
            return "您的申訴已核准。如需恢復帳號使用，請聯繫客服或等待管理員後續處理。{$reply}";
        }
        if ($isAppeal && $status === 'dismissed') {
            return "您的申訴未獲通過。\n\n理由：{$adminReply}";
        }
        // 一般 ticket（system_issue / report 等）
        return "您的回報已完成處理。\n\n管理員回覆：{$adminReply}";
    }
}
