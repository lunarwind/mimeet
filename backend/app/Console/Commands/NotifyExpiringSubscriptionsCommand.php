<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Subscription;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * subscriptions:notify-expiring
 *
 * 每日 09:00 執行（Kernel 註冊）。
 * 找出在未來 3 天內到期的 active 訂閱，發送 subscription_expiring 站內提醒。
 *
 * 去重機制：
 *   檢查 notifications 表中是否已對「該訂閱 id」發送過 subscription_expiring。
 *   採用 JSON path data->subscription_id 比對（每個訂閱實例至多通知一次）。
 *
 * 規格依據：PRD「訂閱即將到期」+ UI-001 §8.3「⚠️ 您的會員將於 3 天後到期」
 */
class NotifyExpiringSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:notify-expiring';
    protected $description = '對 3 天內到期的訂閱發送站內提醒（每日 09:00，每訂閱限通知一次）';

    public function handle(NotificationService $notifier): int
    {
        $now = now();
        $threshold = $now->copy()->addDays(3);

        $expiringSoon = Subscription::where('status', 'active')
            ->whereBetween('expires_at', [$now, $threshold])
            ->with('user', 'plan')
            ->get();

        $this->info("Found {$expiringSoon->count()} subscription(s) expiring within 3 days");

        $notified = 0;
        $skipped = 0;

        foreach ($expiringSoon as $sub) {
            if (!$sub->user) {
                $skipped++;
                continue;
            }

            // 去重：該訂閱實例是否已發過 expiring 提醒
            $alreadyNotified = Notification::where('user_id', $sub->user_id)
                ->where('type', 'subscription_expiring')
                ->where('data->subscription_id', $sub->id)
                ->exists();

            if ($alreadyNotified) {
                $skipped++;
                $this->line("  - sub#{$sub->id}: user#{$sub->user_id} already notified, skip");
                continue;
            }

            try {
                $notifier->notifySubscriptionExpiring(
                    $sub->user,
                    $sub->expires_at->toDateString(),
                    $sub->id,
                );

                Log::info('[NotifyExpiring] Notified user', [
                    'user_id'         => $sub->user_id,
                    'subscription_id' => $sub->id,
                    'expires_at'      => $sub->expires_at->toIso8601String(),
                ]);

                $notified++;
                $this->line("  ✓ sub#{$sub->id}: user#{$sub->user_id} notified");
            } catch (\Throwable $e) {
                Log::error('[NotifyExpiring] Failed to notify', [
                    'subscription_id' => $sub->id,
                    'error'           => $e->getMessage(),
                ]);
                $this->error("  ✗ sub#{$sub->id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Notified: {$notified}, Skipped: {$skipped}");
        return self::SUCCESS;
    }
}
