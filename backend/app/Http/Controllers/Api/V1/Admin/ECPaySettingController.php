<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ECPaySettingController extends Controller
{
    /**
     * GET /api/v1/admin/settings/ecpay — read all ECPay settings
     */
    public function index(): JsonResponse
    {
        $keys = [
            'ecpay.mode',
            'ecpay.payment.merchant_id',
            'ecpay.payment.hash_key',
            'ecpay.payment.hash_iv',
            'ecpay.invoice.merchant_id',
            'ecpay.invoice.hash_key',
            'ecpay.invoice.hash_iv',
            'ecpay.invoice.enabled',
            'ecpay.invoice.donation_love_code',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $setting = SystemSetting::where('key_name', $key)->first();
            $shortKey = str_replace('ecpay.', '', $key);

            // Mask secrets — only show last 4 chars
            $value = $setting->value ?? '';
            if ($setting && $setting->value_type === 'secret' && strlen($value) > 4) {
                $value = str_repeat('*', strlen($value) - 4) . substr($value, -4);
            }

            $settings[$shortKey] = [
                'value' => $value,
                'description' => $setting->description ?? '',
            ];
        }

        return response()->json([
            'success' => true,
            'data' => ['settings' => $settings],
        ]);
    }

    /**
     * POST /api/v1/admin/settings/ecpay — update ECPay settings
     *
     * @deprecated 舊格式端點（ecpay.* dot-notation key）
     *   新格式請使用 PUT /api/v1/admin/settings/payment
     *   舊 key 已不再有效，寫入會被攔截並回 400。
     */
    public function update(Request $request): JsonResponse
    {
        // 攔截所有舊格式寫入嘗試
        return response()->json([
            'success' => false,
            'code'    => 'DEPRECATED_KEY_FORMAT',
            'message' => '此端點已停用。請改用 PUT /api/v1/admin/settings/payment 設定金流憑證（新格式 ecpay_* key）。',
        ], 400);

        // 以下舊邏輯保留以供參考，不再執行：
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|starts_with:ecpay.',
            'settings.*.value' => 'required|string',
        ]);

        $allowedKeys = [
            'ecpay.mode',
            'ecpay.payment.merchant_id',
            'ecpay.payment.hash_key',
            'ecpay.payment.hash_iv',
            'ecpay.invoice.merchant_id',
            'ecpay.invoice.hash_key',
            'ecpay.invoice.hash_iv',
            'ecpay.invoice.enabled',
            'ecpay.invoice.donation_love_code',
        ];

        $updated = [];

        foreach ($request->input('settings') as $item) {
            $key = $item['key'];
            $value = $item['value'];

            if (!in_array($key, $allowedKeys, true)) {
                continue;
            }

            // Skip masked values (no actual change)
            if (str_contains($value, '****')) {
                continue;
            }

            SystemSetting::set($key, $value, $request->user()?->id);
            $updated[] = $key;
        }

        return response()->json([
            'success' => true,
            'message' => '綠界設定已更新',
            'data' => ['updated_keys' => $updated],
        ]);
    }
}
