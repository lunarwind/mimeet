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
}
