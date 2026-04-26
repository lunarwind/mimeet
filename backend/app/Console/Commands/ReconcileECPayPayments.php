<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\ECPayService;
use App\Services\UnifiedPaymentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * payments:reconcile-ecpay
 *
 * 每 15 分鐘執行（Step 9）。
 * 找出超過 30 分鐘仍 pending 的訂單，向 ECPay 查詢真實狀態。
 * 若 ECPay 顯示已付款但 mimeet 仍 pending → 補做 callback 邏輯。
 */
class ReconcileECPayPayments extends Command
{
    protected $signature = 'payments:reconcile-ecpay';
    protected $description = '對帳：補處理 ECPay 已付款但本地仍 pending 的訂單（每 15 分鐘）';

    public function __construct(
        private ECPayService         $ecpay,
        private UnifiedPaymentService $unified,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $suspects = Payment::where('status', 'pending')
            ->where('environment', '!=', 'legacy')
            ->where('created_at', '<', now()->subMinutes(30))
            ->get();

        if ($suspects->isEmpty()) {
            $this->info('No suspects found.');
            return;
        }

        $this->info("Found {$suspects->count()} suspect payment(s).");
        $fixed = 0;

        foreach ($suspects as $payment) {
            $remote = $this->ecpay->queryTradeInfo($payment->order_no);

            if (empty($remote)) {
                $this->line("  [{$payment->order_no}] ECPay query failed or empty");
                continue;
            }

            $remoteStatus = (int) ($remote['TradeStatus'] ?? -1);

            // TradeStatus: 0=未付款, 1=已付款
            if ($remoteStatus === 1) {
                Log::warning('[ReconcileECPay] Found unprocesed paid payment, triggering callback', [
                    'payment_id' => $payment->id,
                    'order_no'   => $payment->order_no,
                    'remote'     => $remote,
                ]);
                $this->warn("  [{$payment->order_no}] ECPay=paid but mimeet=pending → retrigger callback");

                // 補做 callback 邏輯（跳過 CheckMacValue 驗簽，因為是對帳補救）
                if (isset($remote['RtnCode']) && $remote['RtnCode'] === '1') {
                    $this->unified->handleCallback($remote);
                    $fixed++;
                }
            } else {
                $this->line("  [{$payment->order_no}] ECPay status={$remoteStatus} (not paid), no action");
            }
        }

        $this->info("Reconcile complete. Fixed {$fixed} payment(s).");
    }
}
