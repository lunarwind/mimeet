<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * subscriptions:expire
 *
 * 每日 00:05 執行（Kernel 註冊）。
 * 處理已過期的 active 訂閱：
 *   1. subscriptions.status: active → expired
 *   2. user.membership_level → User::getBaseMembershipLevel()（避免誤刪驗證升級成果）
 *   3. 發送站內 subscription_expired 通知
 *
 * 防呆：若用戶仍有其他 active 且未過期的訂閱（早期續訂等情境），不降級。
 *
 * 規格依據：PRD §4.3.7「到期日後系統自動將用戶降級為驗證會員」
 *           PRD §4.4.3「到期後自動降回驗證會員」
 */
class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = '處理已過期的訂閱：標記 expired + 降級 user.membership_level（每日 00:05）';

    public function handle(NotificationService $notifier): int
    {
        $now = now();

        $expired = Subscription::where('status', 'active')
            ->where('expires_at', '<', $now)
            ->with('user', 'plan')
            ->get();

        $this->info("Found {$expired->count()} expired subscription(s)");

        $downgraded = 0;
        $skipped = 0;

        foreach ($expired as $sub) {
            try {
                DB::transaction(function () use ($sub, $notifier, &$downgraded, &$skipped) {
                    // 標記訂閱已過期（無論用戶是否存在）
                    $sub->update(['status' => 'expired']);

                    $user = $sub->user;
                    if (!$user) {
                        $this->line("  - sub#{$sub->id}: user missing, marked expired only");
                        $skipped++;
                        return;
                    }

                    // 防呆：用戶若另有 active 未過期訂閱（早期續訂），不降級
                    $hasOtherActive = Subscription::where('user_id', $user->id)
                        ->where('id', '!=', $sub->id)
                        ->where('status', 'active')
                        ->where('expires_at', '>', now())
                        ->exists();

                    if ($hasOtherActive) {
                        $this->line("  - sub#{$sub->id}: user#{$user->id} has another active sub, skip downgrade");
                        $skipped++;
                        return;
                    }

                    $oldLevel = (float) $user->membership_level;
                    $newLevel = $user->getBaseMembershipLevel();

                    if ($oldLevel !== $newLevel) {
                        $user->forceFill(['membership_level' => $newLevel])->save();
                    }

                    $planName = $sub->plan?->name ?? '訂閱方案';
                    $notifier->notifySubscriptionExpired($user, $planName, $sub->id);

                    Log::info('[ExpireSubscriptions] Downgraded user', [
                        'user_id'         => $user->id,
                        'subscription_id' => $sub->id,
                        'plan_id'         => $sub->plan_id,
                        'is_trial'        => (bool) ($sub->plan->is_trial ?? false),
                        'level_change'    => "{$oldLevel} → {$newLevel}",
                        'expired_at'      => $sub->expires_at?->toIso8601String(),
                    ]);

                    $downgraded++;
                    $this->line("  ✓ sub#{$sub->id}: user#{$user->id} {$oldLevel} → {$newLevel}");
                });
            } catch (\Throwable $e) {
                Log::error('[ExpireSubscriptions] Failed to process subscription', [
                    'subscription_id' => $sub->id,
                    'error'           => $e->getMessage(),
                ]);
                $this->error("  ✗ sub#{$sub->id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. Downgraded: {$downgraded}, Skipped: {$skipped}");
        return self::SUCCESS;
    }
}
