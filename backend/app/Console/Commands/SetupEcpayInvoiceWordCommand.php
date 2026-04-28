<?php

namespace App\Console\Commands;

use App\Services\EcpayInvoiceWordService;
use Illuminate\Console\Command;

/**
 * 一鍵設定綠界發票字軌（artisan 版，UI 版詳見後台設定頁）
 *
 * 使用範例：
 *   php artisan mimeet:ecpay-setup-word --header=ZZ --start=12345000 --end=12345049
 *   php artisan mimeet:ecpay-setup-word --header=ZZ --start=12345000 --end=12345049 --year=115 --term=3
 */
class SetupEcpayInvoiceWordCommand extends Command
{
    protected $signature = 'mimeet:ecpay-setup-word
                            {--header=ZZ        : 字軌兩個英文字母}
                            {--start=12345000   : 起號（尾數須為 00 或 50）}
                            {--end=12345049     : 迄號（尾數須為 49 或 99）}
                            {--year=            : 民國年份（預設當年）}
                            {--term=            : 期別 1-6（預設當期）}';

    protected $description = '新增並啟用綠界電子發票字軌（sandbox / production 由系統設定決定）';

    public function handle(EcpayInvoiceWordService $service): int
    {
        $rocYear     = now()->year - 1911;
        $currentTerm = (int) ceil(now()->month / 2);

        $header = strtoupper((string) $this->option('header'));
        $start  = (int) $this->option('start');
        $end    = (int) $this->option('end');
        $year   = $this->option('year') !== null ? (int) $this->option('year') : $rocYear;
        $term   = $this->option('term') !== null ? (int) $this->option('term') : $currentTerm;

        $this->info("字軌設定：{$header} {$year}年第{$term}期 {$start}-{$end}");

        $this->line('新增字軌中...');
        $result = $service->add($term, (string) $year, $header, $start, $end);

        if (!$result['ok']) {
            $this->error('❌ 新增失敗：' . $result['msg']);
            return self::FAILURE;
        }

        $trackId = $result['track_id'] ?? '(unknown)';
        $this->line("  ✓ 字軌新增成功，TrackID={$trackId}");

        if (!$trackId || $trackId === '(unknown)') {
            $this->warn('  ⚠ 未取得 TrackID，無法自動啟用，請手動在後台啟用');
            return self::SUCCESS;
        }

        $this->line('啟用字軌中...');
        $r2 = $service->setStatus($trackId, true);
        if (!$r2['ok']) {
            $this->error('❌ 啟用失敗：' . $r2['msg']);
            $this->warn('字軌已新增，請在後台手動啟用（TrackID=' . $trackId . '）');
            return self::FAILURE;
        }

        $this->info("✅ 字軌設定完成且已啟用（TrackID={$trackId}）");
        return self::SUCCESS;
    }
}
