<?php

namespace App\Console\Commands;

use App\Jobs\IssueInvoiceJob;
use App\Models\Payment;
use Illuminate\Console\Command;

/**
 * 為過去未開發票的 paid payments 批次補開。
 *
 * Usage:
 *   php artisan invoices:backfill --dry-run       # 僅列出，不執行
 *   php artisan invoices:backfill                 # 實際 dispatch Job
 *   php artisan invoices:backfill --type=subscription
 */
class BackfillInvoicesCommand extends Command
{
    protected $signature = 'invoices:backfill
                            {--type=all : all | subscription | points | verification}
                            {--dry-run : 只列出不執行}';

    protected $description = '為過去未開發票的 paid payments 批次補開電子發票';

    public function handle(): int
    {
        $type   = $this->option('type');
        $dryRun = (bool) $this->option('dry-run');

        $query = Payment::where('status', 'paid')
            ->whereNull('invoice_no')
            ->where('invoice_status', '!=', 'not_applicable');

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $payments = $query->get();

        $this->info("符合條件的付款記錄：{$payments->count()} 筆" . ($dryRun ? '（dry-run）' : ''));

        foreach ($payments as $p) {
            $label = "payment#{$p->id} [{$p->type}] {$p->order_no} NT\${$p->amount}";
            if ($dryRun) {
                $this->line("  [dry] {$label}");
            } else {
                IssueInvoiceJob::dispatch($p->id);
                $p->update(['invoice_status' => 'pending']);
                $this->line("  [queued] {$label}");
            }
        }

        $this->info($dryRun ? '✓ Dry-run 完成，上述記錄將在移除 --dry-run 後被排入 queue' : '✓ 全數排入 queue，worker 將逐一處理');
        return 0;
    }
}
