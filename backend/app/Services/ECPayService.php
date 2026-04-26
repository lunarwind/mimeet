<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ECPay 金流服務
 *
 * ─ 環境切換：讀 system_settings 的 ecpay_environment（sandbox / production）
 * ─ 憑證讀取：新格式 ecpay_{env}_merchant_id 優先，fallback 舊格式 ecpay.payment.*，
 *             sandbox 留空時 fallback 到 ECPay 官方公開沙箱測試值
 * ─ AIO 跳轉：buildAioParams() 回傳 form params 陣列，前端直接 POST 到 ECPay
 *             ⚠️ 永遠跳轉到 payment(-stage).ecpay.com.tw，不使用自家 mock
 *
 * CheckMacValue 演算法（SHA256）：
 *   1. 移除 CheckMacValue 欄位
 *   2. 按 key 排序（SORT_FLAG_CASE 不區分大小寫）
 *   3. 組合 HashKey=...&k=v...&HashIV=...
 *   4. urlencode 整字串
 *   5. .NET 相容替換 7 組（%2d→- 等）
 *   6. 全小寫
 *   7. SHA256
 *   8. 全大寫
 */
class ECPayService
{
    // ─── 環境 ─────────────────────────────────────────────────────────

    /**
     * 取得目前環境（sandbox / production）
     * 從 ecpay_environment 讀取（Step 6 後統一新 key，舊 ecpay.mode 已透過 migration 遷移）
     */
    public function getEnvironment(): string
    {
        $env = SystemSetting::get('ecpay_environment', 'sandbox');
        return in_array($env, ['sandbox', 'production']) ? $env : 'sandbox';
    }

    public function isSandbox(): bool
    {
        return $this->getEnvironment() === 'sandbox';
    }

    // ─── 憑證 ─────────────────────────────────────────────────────────

    public function getMerchantId(): string
    {
        $env = $this->getEnvironment();
        $val = SystemSetting::get("ecpay_{$env}_merchant_id", '');

        if ($val === '' && $env === 'sandbox') {
            return config('ecpay.sandbox_fallback.merchant_id', '2000132');
        }
        return (string) $val;
    }

    public function getHashKey(): string
    {
        $env = $this->getEnvironment();
        $val = SystemSetting::get("ecpay_{$env}_hash_key", '');

        if ($val === '' && $env === 'sandbox') {
            return config('ecpay.sandbox_fallback.hash_key', '5294y06JbISpM5x9');
        }
        return (string) $val;
    }

    public function getHashIV(): string
    {
        $env = $this->getEnvironment();
        $val = SystemSetting::get("ecpay_{$env}_hash_iv", '');

        if ($val === '' && $env === 'sandbox') {
            return config('ecpay.sandbox_fallback.hash_iv', 'v77hoKGq4kWxNNIS');
        }
        return (string) $val;
    }

    // ─── URL ──────────────────────────────────────────────────────────

    public function getAioUrl(): string
    {
        return config('ecpay.urls.' . $this->getEnvironment() . '.aio');
    }

    public function getQueryUrl(): string
    {
        return config('ecpay.urls.' . $this->getEnvironment() . '.query');
    }

    public function getRefundUrl(): string
    {
        return config('ecpay.urls.' . $this->getEnvironment() . '.refund');
    }

    // ═══════════════════════════════════════════════════════════════════
    //  CheckMacValue（SHA256）
    //  Ref: https://developers.ecpay.com.tw/2902/
    // ═══════════════════════════════════════════════════════════════════

    /**
     * 產生 ECPay CheckMacValue。
     *
     * .NET 相容替換規則（7 組，少一組都會驗簽失敗）：
     *   %2d → -    %5f → _    %2e → .    %21 → !
     *   %2a → *    %28 → (    %29 → )
     */
    public function generateCheckMacValue(
        array $params,
        ?string $hashKey = null,
        ?string $hashIv = null,
    ): string {
        unset($params['CheckMacValue']);

        ksort($params, SORT_STRING | SORT_FLAG_CASE);

        $str = 'HashKey=' . ($hashKey ?? $this->getHashKey());
        foreach ($params as $key => $value) {
            $str .= "&{$key}={$value}";
        }
        $str .= '&HashIV=' . ($hashIv ?? $this->getHashIV());

        $str = urlencode($str);
        $str = strtolower($str);

        // .NET URL encoding compatibility — 7 groups, order matters
        $str = str_replace(
            ['%2d', '%5f', '%2e', '%21', '%2a', '%28', '%29'],
            ['-',   '_',   '.',   '!',   '*',   '(',   ')'],
            $str,
        );

        return strtoupper(hash('sha256', $str));
    }

    /**
     * 驗證 ECPay callback CheckMacValue。
     */
    public function verifyCallback(array $data): bool
    {
        $receivedMac = $data['CheckMacValue'] ?? '';
        $calculatedMac = $this->generateCheckMacValue($data);
        return hash_equals($calculatedMac, strtoupper($receivedMac));
    }

    // ═══════════════════════════════════════════════════════════════════
    //  AIO 付款跳轉
    // ═══════════════════════════════════════════════════════════════════

    /**
     * 建立 AIO 付款表單參數陣列（含 CheckMacValue）。
     * 前端收到後直接 POST 到 getAioUrl()，不再走自家 mock。
     *
     * @param array{order_no: string, amount: int, item_name: string, description?: string} $data
     * @return array<string, mixed>
     */
    public function buildAioParams(array $data): array
    {
        $params = [
            'MerchantID'        => $this->getMerchantId(),
            'MerchantTradeNo'   => $data['order_no'],
            'MerchantTradeDate' => now()->format('Y/m/d H:i:s'),
            'PaymentType'       => 'aio',
            'TotalAmount'       => (int) $data['amount'],
            'TradeDesc'         => urlencode(mb_substr($data['description'] ?? $data['item_name'], 0, 200)),
            'ItemName'          => mb_substr($data['item_name'], 0, 200),
            'ReturnURL'         => url('/api/v1/payments/callback'),
            'OrderResultURL'    => url('/api/v1/payments/return'),
            'ClientBackURL'     => config('app.frontend_url', env('FRONTEND_URL', 'https://mimeet.online')) . '/#/payment/result',
            'ChoosePayment'     => 'Credit',
            'EncryptType'       => 1,
            'NeedExtraPaidInfo' => 'Y',
            'IgnorePayment'     => 'ATM#CVS#BARCODE#WebATM',
        ];

        $params['CheckMacValue'] = $this->generateCheckMacValue($params);
        return $params;
    }

    /**
     * @deprecated 使用 buildAioParams() + getAioUrl()。
     *             保留以相容舊有 CreditCardVerificationService / PaymentService 呼叫，
     *             Step 5 統一更新 controller 後可移除。
     */
    public function getPaymentUrl(
        string $merchantTradeNo,
        int $amount,
        string $itemName,
        string $returnUrl,
        string $notifyUrl,
    ): string {
        $params = $this->buildAioParams([
            'order_no'  => $merchantTradeNo,
            'amount'    => $amount,
            'item_name' => $itemName,
        ]);
        // Override URLs per caller's explicit values
        $params['ReturnURL']      = $notifyUrl;
        $params['OrderResultURL'] = $returnUrl;
        // Recompute CheckMacValue after override
        unset($params['CheckMacValue']);
        $params['CheckMacValue'] = $this->generateCheckMacValue($params);

        // Build auto-submit form and cache it
        $aioUrl = $this->getAioUrl();
        $html = '<html><head><meta charset="UTF-8"></head><body>'
              . '<form id="f" method="POST" action="' . e($aioUrl) . '" accept-charset="UTF-8">';
        foreach ($params as $key => $value) {
            $html .= '<input type="hidden" name="' . e($key) . '" value="' . e((string) $value) . '">';
        }
        $html .= '</form><script>document.getElementById("f").submit();</script></body></html>';

        $token = bin2hex(random_bytes(16));
        cache()->put("ecpay_form:{$token}", $html, 300);
        return url("/api/v1/payments/ecpay/checkout/{$token}");
    }

    // ═══════════════════════════════════════════════════════════════════
    //  退款
    // ═══════════════════════════════════════════════════════════════════

    /**
     * ECPay 信用卡退款 / 取消授權（DoAction）
     * Action: R = 申請退款, C = 取消授權
     */
    public function doRefund(
        string $merchantTradeNo,
        string $ecpayTradeNo,
        int $amount,
        string $action = 'R',
    ): array {
        $params = [
            'MerchantID'      => $this->getMerchantId(),
            'MerchantTradeNo' => $merchantTradeNo,
            'TradeNo'         => $ecpayTradeNo,
            'Action'          => $action,
            'TotalAmount'     => $amount,
        ];
        $params['CheckMacValue'] = $this->generateCheckMacValue($params);

        try {
            $response = Http::timeout(30)->asForm()->post($this->getRefundUrl(), $params);
            $body = $response->body();
            parse_str($body, $result);
            $ok = ($result['RtnCode'] ?? '') === '1';

            if ($ok) {
                Log::info('[ECPay Refund] Success', compact('merchantTradeNo', 'action'));
            } else {
                Log::warning('[ECPay Refund] Failed', ['result' => $result, 'order' => $merchantTradeNo]);
            }

            return ['success' => $ok, 'result' => $result];
        } catch (\Throwable $e) {
            Log::error('[ECPay Refund] Exception', ['message' => $e->getMessage(), 'order' => $merchantTradeNo]);
            return ['success' => false, 'result' => [], 'error' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  查詢交易（對帳用）
    // ═══════════════════════════════════════════════════════════════════

    public function queryTradeInfo(string $merchantTradeNo): array
    {
        $params = [
            'MerchantID'      => $this->getMerchantId(),
            'MerchantTradeNo' => $merchantTradeNo,
            'TimeStamp'       => (string) time(),
        ];
        $params['CheckMacValue'] = $this->generateCheckMacValue($params);

        try {
            $response = Http::timeout(15)->asForm()->post($this->getQueryUrl(), $params);
            parse_str($response->body(), $result);
            return $result;
        } catch (\Throwable $e) {
            Log::error('[ECPay Query] Exception', ['message' => $e->getMessage(), 'order' => $merchantTradeNo]);
            return [];
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  發票（保留現狀，功能待啟用 ecpay_invoice_enabled=true）
    //  Ref: https://developers.ecpay.com.tw/7896/
    // ═══════════════════════════════════════════════════════════════════

    public function isInvoiceEnabled(): bool
    {
        return (bool) SystemSetting::get('ecpay_invoice_enabled', false);
    }

    private function invoiceMerchantId(): string
    {
        // 新 key 格式（舊 ecpay.invoice.merchant_id 已在 migration 遷移並刪除）
        return SystemSetting::get('ecpay_invoice_merchant_id',
            config('services.ecpay.invoice_merchant_id', '2000132'));
    }

    private function invoiceHashKey(): string
    {
        return SystemSetting::get('ecpay_invoice_hash_key',
            config('services.ecpay.invoice_hash_key', 'ejCk326UnaZWKisg'));
    }

    private function invoiceHashIv(): string
    {
        return SystemSetting::get('ecpay_invoice_hash_iv',
            config('services.ecpay.invoice_hash_iv', 'q9jcZX8Ib9LM8wYk'));
    }

    private function aesEncrypt(string $plaintext, string $key, string $iv): string
    {
        $encoded = urlencode($plaintext);
        $encrypted = openssl_encrypt($encoded, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode((string) $encrypted);
    }

    private function aesDecrypt(string $ciphertext, string $key, string $iv): string
    {
        $decoded = base64_decode($ciphertext);
        $decrypted = openssl_decrypt($decoded, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return urldecode((string) $decrypted);
    }

    public function issueInvoice(array $orderData): ?array
    {
        if (!$this->isInvoiceEnabled()) {
            Log::info('[ECPay Invoice] Skipped — invoice disabled');
            return null;
        }

        $merchantId = $this->invoiceMerchantId();
        $hashKey    = $this->invoiceHashKey();
        $hashIv     = $this->invoiceHashIv();
        $baseUrl    = $this->isSandbox()
            ? 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue'
            : 'https://einvoice.ecpay.com.tw/B2CInvoice/Issue';

        $items = [];
        foreach ($orderData['items'] as $item) {
            $items[] = [
                'ItemSeq'    => $item['seq'] ?? 1,
                'ItemName'   => $item['name'],
                'ItemCount'  => $item['count'] ?? 1,
                'ItemWord'   => $item['word'] ?? '式',
                'ItemPrice'  => $item['price'],
                'ItemAmount' => $item['amount'] ?? $item['price'] * ($item['count'] ?? 1),
            ];
        }

        $dataPayload = [
            'MerchantID'    => $merchantId,
            'RelateNumber'  => $orderData['relate_number'],
            'CustomerEmail' => $orderData['customer_email'] ?? '',
            'CustomerPhone' => $orderData['customer_phone'] ?? '',
            'Print'         => '0',
            'Donation'      => '0',
            'TaxType'       => '1',
            'SalesAmount'   => (int) $orderData['sales_amount'],
            'InvType'       => '07',
            'vat'           => '1',
            'Items'         => $items,
        ];

        if (!empty($orderData['carrier_type'])) {
            $dataPayload['CarrierType'] = $orderData['carrier_type'];
            $dataPayload['CarrierNum']  = $orderData['carrier_num'] ?? '';
        }

        if (!empty($orderData['donation'])) {
            $dataPayload['Donation'] = '1';
            $dataPayload['LoveCode'] = $orderData['love_code']
                ?? SystemSetting::get('ecpay_invoice_donation_love_code', '168001');
            $dataPayload['Print'] = '0';
        }

        $encryptedData = $this->aesEncrypt(
            json_encode($dataPayload, JSON_UNESCAPED_UNICODE),
            $hashKey, $hashIv,
        );

        $requestBody = [
            'MerchantID' => $merchantId,
            'RqHeader'   => ['Timestamp' => (int) now()->timestamp],
            'Data'       => $encryptedData,
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($baseUrl, $requestBody);

            if (!$response->successful()) {
                Log::error('[ECPay Invoice] HTTP error', ['status' => $response->status()]);
                return null;
            }

            $result = $response->json();
            if (($result['TransCode'] ?? 0) !== 1) {
                Log::error('[ECPay Invoice] TransCode error', $result);
                return null;
            }

            $invoiceResult = json_decode(
                $this->aesDecrypt($result['Data'], $hashKey, $hashIv),
                true
            );

            if (($invoiceResult['RtnCode'] ?? 0) !== 1) {
                Log::error('[ECPay Invoice] RtnCode error', $invoiceResult ?? []);
                return null;
            }

            return [
                'invoice_no'    => $invoiceResult['InvoiceNo'] ?? '',
                'invoice_date'  => $invoiceResult['InvoiceDate'] ?? '',
                'random_number' => $invoiceResult['RandomNumber'] ?? '',
            ];
        } catch (\Throwable $e) {
            Log::error('[ECPay Invoice] Exception', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
