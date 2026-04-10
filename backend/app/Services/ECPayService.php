<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ECPayService
{
    private string $merchantId;
    private string $hashKey;
    private string $hashIv;
    private bool $isSandbox;

    public function __construct()
    {
        $this->merchantId = \App\Models\SystemSetting::getValue('ecpay.merchant_id', config('services.ecpay.merchant_id', env('ECPAY_MERCHANT_ID', '3002607')));
        $this->hashKey = \App\Models\SystemSetting::getValue('ecpay.hash_key', config('services.ecpay.hash_key', env('ECPAY_HASH_KEY', 'pwFHCqoQZGmho4w6')));
        $this->hashIv = \App\Models\SystemSetting::getValue('ecpay.hash_iv', config('services.ecpay.hash_iv', env('ECPAY_HASH_IV', 'EkRm7iFT261dpevs')));
        $this->isSandbox = (bool) \App\Models\SystemSetting::getValue('ecpay.is_sandbox', env('ECPAY_IS_SANDBOX', true));
    }

    /**
     * Build ECPay payment form HTML (auto-submit).
     */
    public function buildPaymentForm(array $params): string
    {
        $baseUrl = $this->isSandbox
            ? 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5'
            : 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5';

        $defaults = [
            'MerchantID' => $this->merchantId,
            'MerchantTradeDate' => now()->format('Y/m/d H:i:s'),
            'PaymentType' => 'aio',
            'EncryptType' => 1,
        ];

        $data = array_merge($defaults, $params);
        $data['CheckMacValue'] = $this->generateCheckMacValue($data);

        // Build auto-submit form
        $html = '<form id="ecpay-form" method="POST" action="' . $baseUrl . '">';
        foreach ($data as $key => $value) {
            $html .= '<input type="hidden" name="' . e($key) . '" value="' . e($value) . '">';
        }
        $html .= '</form><script>document.getElementById("ecpay-form").submit();</script>';

        return $html;
    }

    /**
     * Generate ECPay CheckMacValue (SHA256).
     */
    public function generateCheckMacValue(array $params): string
    {
        // Remove CheckMacValue if present
        unset($params['CheckMacValue']);

        // Sort by key
        ksort($params);

        // Build query string
        $str = 'HashKey=' . $this->hashKey;
        foreach ($params as $key => $value) {
            $str .= "&{$key}={$value}";
        }
        $str .= '&HashIV=' . $this->hashIv;

        // URL encode
        $str = urlencode($str);

        // Convert to lowercase
        $str = strtolower($str);

        // .NET URL encoding differences
        $str = str_replace(['%2d', '%5f', '%2e', '%21', '%2a', '%28', '%29'], ['-', '_', '.', '!', '*', '(', ')'], $str);

        return strtoupper(hash('sha256', $str));
    }

    /**
     * Verify ECPay callback CheckMacValue.
     */
    public function verifyCallback(array $data): bool
    {
        $receivedMac = $data['CheckMacValue'] ?? '';
        $calculatedMac = $this->generateCheckMacValue($data);

        return strtoupper($receivedMac) === strtoupper($calculatedMac);
    }

    /**
     * Get the payment URL for redirect (sandbox or production).
     */
    public function getPaymentUrl(string $merchantTradeNo, int $amount, string $itemName, string $returnUrl, string $notifyUrl): string
    {
        if ($this->isSandbox && config('app.env') !== 'production') {
            // In sandbox/dev, return a mock URL that can be used for testing
            Log::info('[ECPay SANDBOX] Payment created', compact('merchantTradeNo', 'amount', 'itemName'));
            return url("/api/v1/payments/ecpay/mock?trade_no={$merchantTradeNo}&amount={$amount}");
        }

        // Production: would return the real ECPay URL
        return "https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5";
    }

    /**
     * Build B2C invoice parameters for ECPay e-invoice API.
     * Uses AES-128-CBC encryption as required by ECPay.
     */
    public function buildInvoiceParams(array $orderData): array
    {
        $invoiceData = [
            'MerchantID' => $this->merchantId,
            'RelateNumber' => $orderData['order_number'],
            'CustomerEmail' => $orderData['customer_email'] ?? '',
            'CustomerPhone' => $orderData['customer_phone'] ?? '',
            'CustomerName' => $orderData['customer_name'] ?? 'MiMeet會員',
            'TaxType' => '1', // 應稅
            'InvType' => '07', // 一般稅額
            'CarrierType' => $orderData['carrier_type'] ?? '', // '' = 紙本, '1' = 手機條碼, '2' = 自然人憑證
            'CarrierNum' => $orderData['carrier_num'] ?? '',
            'LoveCode' => $orderData['love_code'] ?? '',
            'Print' => empty($orderData['carrier_type']) && empty($orderData['love_code']) ? '1' : '0',
            'SalesAmount' => $orderData['amount'],
            'ItemName' => $orderData['item_name'] ?? 'MiMeet訂閱服務',
            'ItemCount' => '1',
            'ItemWord' => '式',
            'ItemPrice' => $orderData['amount'],
            'ItemAmount' => $orderData['amount'],
            'vat' => '1',
            'InvoiceRemark' => 'MiMeet平台訂閱',
        ];

        return $invoiceData;
    }

    /**
     * AES-128-CBC encrypt data for ECPay invoice API.
     */
    public function encryptInvoiceData(string $data): string
    {
        $key = $this->hashKey;
        $iv = $this->hashIv;

        // URL encode first
        $data = urlencode($data);

        // AES-128-CBC encryption
        $encrypted = openssl_encrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

        // Base64 encode
        return base64_encode($encrypted);
    }

    /**
     * AES-128-CBC decrypt data from ECPay invoice API response.
     */
    public function decryptInvoiceData(string $data): string
    {
        $key = $this->hashKey;
        $iv = $this->hashIv;

        // Base64 decode
        $decoded = base64_decode($data);

        // AES-128-CBC decryption
        $decrypted = openssl_decrypt($decoded, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

        // URL decode
        return urldecode($decrypted);
    }

    /**
     * Issue a B2C invoice via ECPay API.
     * In sandbox mode, logs instead of making real API call.
     */
    public function issueInvoice(array $orderData): array
    {
        $invoiceParams = $this->buildInvoiceParams($orderData);

        if ($this->isSandbox) {
            Log::info('[ECPay Invoice SANDBOX] Would issue invoice', $invoiceParams);
            return [
                'success' => true,
                'invoice_no' => 'INV' . date('Ymd') . rand(1000, 9999),
                'invoice_date' => now()->format('Y-m-d'),
                'random_number' => str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
                'sandbox' => true,
            ];
        }

        // Production: call ECPay B2C invoice API
        $url = 'https://einvoice.ecpay.com.tw/B2CInvoice/Issue';
        $jsonData = json_encode($invoiceParams);
        $encryptedData = $this->encryptInvoiceData($jsonData);

        try {
            $response = \Illuminate\Support\Facades\Http::asForm()->post($url, [
                'MerchantID' => $this->merchantId,
                'RqHeader' => json_encode(['Timestamp' => time(), 'Revision' => '3.0.0']),
                'Data' => $encryptedData,
            ]);

            $result = $response->json();
            if (isset($result['Data'])) {
                $decrypted = $this->decryptInvoiceData($result['Data']);
                $invoiceResult = json_decode($decrypted, true);

                return [
                    'success' => ($invoiceResult['RtnCode'] ?? 0) == 1,
                    'invoice_no' => $invoiceResult['InvoiceNo'] ?? null,
                    'invoice_date' => $invoiceResult['InvoiceDate'] ?? null,
                    'random_number' => $invoiceResult['RandomNumber'] ?? null,
                ];
            }

            return ['success' => false, 'error' => $result['TransMsg'] ?? 'Unknown error'];
        } catch (\Exception $e) {
            Log::error('[ECPay Invoice] Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
