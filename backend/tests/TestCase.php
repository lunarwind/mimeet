<?php

namespace Tests;

use App\Models\SystemSetting;
use App\Services\ECPayService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * ECPay 官方公開沙箱憑證（developers.ecpay.com.tw）—
     * 任何測試都可重複使用，做 callback / CheckMacValue / buildAioParams 測試。
     */
    protected const ECPAY_SANDBOX_MERCHANT_ID = '2000132';
    protected const ECPAY_SANDBOX_HASH_KEY    = '5294y06JbISpM5x9';
    protected const ECPAY_SANDBOX_HASH_IV     = 'v77hoKGq4kWxNNIS';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seedEcpaySandboxCredentials();
    }

    /**
     * 把 ECPay sandbox 公開憑證寫入 system_settings，並清掉 sys: cache。
     * 同時把 is_encrypted 強制設為 false（migration 預設 true 會讓 Crypt::decryptString
     * 對 plain raw 失敗 → SystemSetting::get 回 default '' → ECPayService 拋 RuntimeException）。
     */
    private function seedEcpaySandboxCredentials(): void
    {
        $rows = [
            ['key_name' => 'ecpay_environment',          'value' => 'sandbox'],
            ['key_name' => 'ecpay_sandbox_merchant_id',  'value' => self::ECPAY_SANDBOX_MERCHANT_ID],
            ['key_name' => 'ecpay_sandbox_hash_key',     'value' => self::ECPAY_SANDBOX_HASH_KEY],
            ['key_name' => 'ecpay_sandbox_hash_iv',      'value' => self::ECPAY_SANDBOX_HASH_IV],
        ];
        foreach ($rows as $row) {
            SystemSetting::updateOrCreate(
                ['key_name' => $row['key_name']],
                ['value' => $row['value'], 'is_encrypted' => false, 'value_type' => 'string'],
            );
            Cache::forget("sys:{$row['key_name']}");
        }
    }

    /**
     * 自己用 ECPayService::generateCheckMacValue 構造合法 ECPay callback payload，
     * 打進真實 endpoint POST /api/v1/payments/callback。
     *
     * commit 53fea02 已刪除 /api/v1/payments/ecpay/mock route（sandbox 走真綠界，
     * 不用自家 mock），測試改用此 helper 走完整 callback 流程：
     *   HTTP layer + verifyCallback + handleCallback + dispatchHandler + 業務邏輯
     *
     * MerchantTradeNo 來源是 Payment.order_no（從 createOrder 回傳的
     * data.params.MerchantTradeNo 拿），不是 Order.order_number。
     */
    protected function makeEcpayCallbackPayload(string $merchantTradeNo, int $amount, array $overrides = []): array
    {
        $base = [
            'MerchantID'           => self::ECPAY_SANDBOX_MERCHANT_ID,
            'MerchantTradeNo'      => $merchantTradeNo,
            'PaymentDate'          => now()->format('Y/m/d H:i:s'),
            'PaymentType'          => 'Credit_CreditCard',
            'TotalAmount'          => $amount,
            'TradeAmt'             => $amount,
            'TradeDate'            => now()->format('Y/m/d H:i:s'),
            'TradeNo'              => 'TEST' . substr(uniqid(), -10),
            'PaymentTypeChargeFee' => '0',
            'RtnCode'              => 1,
            'RtnMsg'               => 'Succeeded',
            'SimulatePaid'         => 0,
        ];
        $params = array_merge($base, $overrides);
        $params['CheckMacValue'] = app(ECPayService::class)->generateCheckMacValue($params);
        return $params;
    }

    protected function payEcpayCallback(TestResponse $orderResponse, array $overrides = []): TestResponse
    {
        $merchantTradeNo = $orderResponse->json('data.params.MerchantTradeNo');
        $amount          = (int) $orderResponse->json('data.params.TotalAmount');
        $payload         = $this->makeEcpayCallbackPayload($merchantTradeNo, $amount, $overrides);
        return $this->postJson('/api/v1/payments/callback', $payload);
    }
}
