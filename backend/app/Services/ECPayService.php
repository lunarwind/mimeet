<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ECPayService
{
    // ─── Payment credentials ────────────────────────────────────────
    private function paymentMerchantId(): string
    {
        return SystemSetting::get('ecpay.payment.merchant_id')
            ?? config('services.ecpay.merchant_id', '3002607');
    }

    private function paymentHashKey(): string
    {
        return SystemSetting::get('ecpay.payment.hash_key')
            ?? config('services.ecpay.hash_key', 'pwFHCqoQZGmho4w6');
    }

    private function paymentHashIv(): string
    {
        return SystemSetting::get('ecpay.payment.hash_iv')
            ?? config('services.ecpay.hash_iv', 'EkRm7iFT261dpevs');
    }

    // ─── Invoice credentials ────────────────────────────────────────
    private function invoiceMerchantId(): string
    {
        return SystemSetting::get('ecpay.invoice.merchant_id')
            ?? config('services.ecpay.invoice_merchant_id', '2000132');
    }

    private function invoiceHashKey(): string
    {
        return SystemSetting::get('ecpay.invoice.hash_key')
            ?? config('services.ecpay.invoice_hash_key', 'ejCk326UnaZWKisg');
    }

    private function invoiceHashIv(): string
    {
        return SystemSetting::get('ecpay.invoice.hash_iv')
            ?? config('services.ecpay.invoice_hash_iv', 'q9jcZX8Ib9LM8wYk');
    }

    private function isSandbox(): bool
    {
        $mode = SystemSetting::get('ecpay.mode', 'sandbox');
        return $mode !== 'production';
    }

    private function isInvoiceEnabled(): bool
    {
        return (bool) SystemSetting::get('ecpay.invoice.enabled', false);
    }

    // ═════════════════════════════════════════════════════════════════
    //  Payment — CheckMacValue (SHA256)
    //  Ref: https://developers.ecpay.com.tw/2902/
    // ═════════════════════════════════════════════════════════════════

    /**
     * Generate ECPay CheckMacValue (SHA256).
     *
     * Steps per official spec:
     * 1. Sort params A→Z by key
     * 2. Prepend HashKey=… & append &HashIV=…
     * 3. URL-encode (RFC 1866)
     * 4. .NET encoding compatibility replacements
     * 5. Lowercase
     * 6. SHA256
     * 7. Uppercase
     */
    public function generateCheckMacValue(array $params, ?string $hashKey = null, ?string $hashIv = null): string
    {
        unset($params['CheckMacValue']);

        ksort($params, SORT_STRING | SORT_FLAG_CASE);

        $str = 'HashKey=' . ($hashKey ?? $this->paymentHashKey());
        foreach ($params as $key => $value) {
            $str .= "&{$key}={$value}";
        }
        $str .= '&HashIV=' . ($hashIv ?? $this->paymentHashIv());

        $str = urlencode($str);
        $str = strtolower($str);

        // .NET URL encoding compatibility
        $str = str_replace(
            ['%2d', '%5f', '%2e', '%21', '%2a', '%28', '%29'],
            ['-',   '_',   '.',   '!',   '*',   '(',   ')'],
            $str,
        );

        return strtoupper(hash('sha256', $str));
    }

    /**
     * Verify ECPay callback CheckMacValue.
     */
    public function verifyCallback(array $data): bool
    {
        $receivedMac = $data['CheckMacValue'] ?? '';
        $calculatedMac = $this->generateCheckMacValue($data);
        return strtoupper($receivedMac) === $calculatedMac;
    }

    // ═════════════════════════════════════════════════════════════════
    //  Payment — Build payment form / URL
    // ═════════════════════════════════════════════════════════════════

    /**
     * Get the payment URL (sandbox mock or real ECPay form redirect).
     */
    public function getPaymentUrl(string $merchantTradeNo, int $amount, string $itemName, string $returnUrl, string $notifyUrl): string
    {
        if ($this->isSandbox() && config('app.env') !== 'production') {
            Log::info('[ECPay SANDBOX] Payment created', compact('merchantTradeNo', 'amount', 'itemName'));
            return url("/api/v1/payments/ecpay/mock?trade_no={$merchantTradeNo}&amount={$amount}");
        }

        $baseUrl = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5';

        $params = [
            'MerchantID' => $this->paymentMerchantId(),
            'MerchantTradeNo' => $merchantTradeNo,
            'MerchantTradeDate' => now()->format('Y/m/d H:i:s'),
            'PaymentType' => 'aio',
            'TotalAmount' => $amount,
            'TradeDesc' => 'MiMeet 訂閱方案',
            'ItemName' => $itemName,
            'ReturnURL' => $notifyUrl,
            'OrderResultURL' => $returnUrl,
            'ChoosePayment' => 'ALL',
            'EncryptType' => 1,
            'NeedExtraPaidInfo' => 'Y',
        ];

        $params['CheckMacValue'] = $this->generateCheckMacValue($params);

        // Build auto-submit form and return as data URI (frontend will redirect)
        $html = '<html><body><form id="f" method="POST" action="' . e($baseUrl) . '">';
        foreach ($params as $key => $value) {
            $html .= '<input type="hidden" name="' . e($key) . '" value="' . e($value) . '">';
        }
        $html .= '</form><script>document.getElementById("f").submit();</script></body></html>';

        // In production mode, we store the form HTML and return a URL to serve it
        $token = bin2hex(random_bytes(16));
        cache()->put("ecpay_form:{$token}", $html, 300);

        return url("/api/v1/payments/ecpay/checkout/{$token}");
    }

    // ═════════════════════════════════════════════════════════════════
    //  Invoice — AES-128-CBC encryption/decryption
    //  Ref: https://developers.ecpay.com.tw/7958/
    // ═════════════════════════════════════════════════════════════════

    /**
     * AES-128-CBC encrypt: URLEncode → AES encrypt → Base64
     */
    private function aesEncrypt(string $plaintext, string $key, string $iv): string
    {
        $encoded = urlencode($plaintext);
        $encrypted = openssl_encrypt($encoded, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($encrypted);
    }

    /**
     * AES-128-CBC decrypt: Base64 → AES decrypt → URLDecode
     */
    private function aesDecrypt(string $ciphertext, string $key, string $iv): string
    {
        $decoded = base64_decode($ciphertext);
        $decrypted = openssl_decrypt($decoded, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return urldecode($decrypted);
    }

    // ═════════════════════════════════════════════════════════════════
    //  Invoice — Issue B2C invoice
    //  Ref: https://developers.ecpay.com.tw/7896/
    // ═════════════════════════════════════════════════════════════════

    /**
     * Issue a B2C electronic invoice via ECPay API.
     *
     * @param array $orderData Must contain: relate_number, customer_email, items[], sales_amount
     * @return array{invoice_no: string, invoice_date: string, random_number: string}|null
     */
    public function issueInvoice(array $orderData): ?array
    {
        if (!$this->isInvoiceEnabled()) {
            Log::info('[ECPay Invoice] Skipped — invoice disabled');
            return null;
        }

        $merchantId = $this->invoiceMerchantId();
        $hashKey = $this->invoiceHashKey();
        $hashIv = $this->invoiceHashIv();

        $baseUrl = $this->isSandbox()
            ? 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue'
            : 'https://einvoice.ecpay.com.tw/B2CInvoice/Issue';

        // Build inner Data payload
        $items = [];
        foreach ($orderData['items'] as $item) {
            $items[] = [
                'ItemSeq' => $item['seq'] ?? 1,
                'ItemName' => $item['name'],
                'ItemCount' => $item['count'] ?? 1,
                'ItemWord' => $item['word'] ?? '式',
                'ItemPrice' => $item['price'],
                'ItemAmount' => $item['amount'] ?? $item['price'] * ($item['count'] ?? 1),
            ];
        }

        $dataPayload = [
            'MerchantID' => $merchantId,
            'RelateNumber' => $orderData['relate_number'],
            'CustomerEmail' => $orderData['customer_email'] ?? '',
            'CustomerPhone' => $orderData['customer_phone'] ?? '',
            'Print' => '0',
            'Donation' => '0',
            'TaxType' => '1',       // 應稅
            'SalesAmount' => (int) $orderData['sales_amount'],
            'InvType' => '07',      // 一般稅額
            'vat' => '1',           // 含稅
            'Items' => $items,
        ];

        // Carrier (載具) — default to ECPay member carrier
        if (!empty($orderData['carrier_type'])) {
            $dataPayload['CarrierType'] = $orderData['carrier_type'];
            $dataPayload['CarrierNum'] = $orderData['carrier_num'] ?? '';
        }

        // Donation override
        if (!empty($orderData['donation']) && $orderData['donation']) {
            $dataPayload['Donation'] = '1';
            $dataPayload['LoveCode'] = $orderData['love_code']
                ?? SystemSetting::get('ecpay.invoice.donation_love_code', '168001');
            $dataPayload['Print'] = '0';
        }

        $dataJson = json_encode($dataPayload, JSON_UNESCAPED_UNICODE);
        $encryptedData = $this->aesEncrypt($dataJson, $hashKey, $hashIv);

        $requestBody = [
            'MerchantID' => $merchantId,
            'RqHeader' => [
                'Timestamp' => (int) now()->timestamp,
            ],
            'Data' => $encryptedData,
        ];

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($baseUrl, $requestBody);

            if (!$response->successful()) {
                Log::error('[ECPay Invoice] HTTP error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $result = $response->json();

            if (($result['TransCode'] ?? 0) !== 1) {
                Log::error('[ECPay Invoice] TransCode error', $result);
                return null;
            }

            // Decrypt response Data
            $decryptedJson = $this->aesDecrypt($result['Data'], $hashKey, $hashIv);
            $invoiceResult = json_decode($decryptedJson, true);

            if (($invoiceResult['RtnCode'] ?? 0) !== 1) {
                Log::error('[ECPay Invoice] RtnCode error', $invoiceResult);
                return null;
            }

            Log::info('[ECPay Invoice] Issued successfully', [
                'invoice_no' => $invoiceResult['InvoiceNo'] ?? '',
            ]);

            return [
                'invoice_no' => $invoiceResult['InvoiceNo'] ?? '',
                'invoice_date' => $invoiceResult['InvoiceDate'] ?? '',
                'random_number' => $invoiceResult['RandomNumber'] ?? '',
            ];
        } catch (\Exception $e) {
            Log::error('[ECPay Invoice] Exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    // ═════════════════════════════════════════════════════════════════
    //  Credit card refund / cancel authorization
    //  Ref: https://developers.ecpay.com.tw/ CreditDetail/DoAction
    // ═════════════════════════════════════════════════════════════════

    /**
     * ECPay credit card refund/cancel authorization.
     * Action: C = cancel authorization, R = refund after capture
     */
    public function doRefund(string $merchantTradeNo, string $ecpayTradeNo, int $amount, string $action = 'R'): array
    {
        $merchantId = $this->paymentMerchantId();
        $hashKey = $this->paymentHashKey();
        $hashIv = $this->paymentHashIv();

        $baseUrl = $this->isSandbox()
            ? 'https://payment-stage.ecpay.com.tw/CreditDetail/DoAction'
            : 'https://payment.ecpay.com.tw/CreditDetail/DoAction';

        $params = [
            'MerchantID' => $merchantId,
            'MerchantTradeNo' => $merchantTradeNo,
            'TradeNo' => $ecpayTradeNo,
            'Action' => $action,  // R = 申請退款, C = 取消授權
            'TotalAmount' => $amount,
        ];
        $params['CheckMacValue'] = $this->generateCheckMacValue($params, $hashKey, $hashIv);

        try {
            $response = Http::timeout(30)
                ->asForm()
                ->post($baseUrl, $params);
            $body = $response->body();
            parse_str($body, $result);
            $ok = ($result['RtnCode'] ?? '') === '1';
            if (!$ok) {
                Log::warning('[ECPay Refund] Failed', ['result' => $result, 'trade_no' => $merchantTradeNo]);
            } else {
                Log::info('[ECPay Refund] Success', ['trade_no' => $merchantTradeNo, 'action' => $action]);
            }
            return ['success' => $ok, 'result' => $result];
        } catch (\Exception $e) {
            Log::error('[ECPay Refund] Exception', ['message' => $e->getMessage(), 'trade_no' => $merchantTradeNo]);
            return ['success' => false, 'result' => [], 'error' => $e->getMessage()];
        }
    }
}
