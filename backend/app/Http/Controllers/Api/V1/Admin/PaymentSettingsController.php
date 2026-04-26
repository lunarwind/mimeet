<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminOperationLog;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * 後台金流憑證設定（Step 6）
 *
 * GET  /api/v1/admin/settings/payment
 * PUT  /api/v1/admin/settings/payment
 *
 * 加密欄位（hash_key / hash_iv）：
 *   GET  → 回傳 ****xxxx（後 4 碼明文）
 *   PUT  → 前端沒改時傳 ****xxxx 格式，backend 跳過不覆蓋
 */
class PaymentSettingsController extends Controller
{
    private const ENCRYPTED_KEYS = [
        'ecpay_sandbox_hash_key',
        'ecpay_sandbox_hash_iv',
        'ecpay_production_hash_key',
        'ecpay_production_hash_iv',
    ];

    private const ALL_KEYS = [
        'ecpay_environment',
        'ecpay_sandbox_merchant_id',
        'ecpay_sandbox_hash_key',
        'ecpay_sandbox_hash_iv',
        'ecpay_production_merchant_id',
        'ecpay_production_hash_key',
        'ecpay_production_hash_iv',
        'ecpay_invoice_enabled',
    ];

    /**
     * GET /api/v1/admin/settings/payment
     * 回傳所有 ECPay 設定。加密欄位顯示 ****xxxx（後 4 碼）。
     */
    public function index(): JsonResponse
    {
        $result = [];

        foreach (self::ALL_KEYS as $key) {
            $setting = SystemSetting::where('key_name', $key)->first();
            $raw     = $setting?->value ?? '';

            if ($setting?->is_encrypted && $raw !== '') {
                $result[$key] = $this->maskEncrypted($raw);
            } else {
                $result[$key] = $raw;
            }
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * PUT /api/v1/admin/settings/payment
     * 批次更新。加密欄位收到 ****xxxx 格式 → 跳過（前端沒改）。
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'ecpay_environment'           => 'sometimes|string|in:sandbox,production',
            'ecpay_sandbox_merchant_id'   => 'sometimes|nullable|string|max:50',
            'ecpay_sandbox_hash_key'      => 'sometimes|nullable|string|max:200',
            'ecpay_sandbox_hash_iv'       => 'sometimes|nullable|string|max:200',
            'ecpay_production_merchant_id'=> 'sometimes|nullable|string|max:50',
            'ecpay_production_hash_key'   => 'sometimes|nullable|string|max:200',
            'ecpay_production_hash_iv'    => 'sometimes|nullable|string|max:200',
            'ecpay_invoice_enabled'       => 'sometimes|nullable|string',
        ]);

        $admin   = $request->user();
        $changes = [];

        foreach (self::ALL_KEYS as $key) {
            if (!$request->has($key)) {
                continue;
            }

            $value = $request->input($key) ?? '';

            // 加密欄位：若收到 ****xxxx 格式（前端未修改），跳過
            if (in_array($key, self::ENCRYPTED_KEYS) && str_starts_with((string) $value, '****')) {
                continue;
            }

            $isEncrypted = in_array($key, self::ENCRYPTED_KEYS);
            $oldRaw      = SystemSetting::where('key_name', $key)->value('value') ?? '';
            $changes[]   = "{$key}: " . ($isEncrypted ? '****' : $oldRaw) . ' → ' . ($isEncrypted ? '****' : $value);

            SystemSetting::put($key, (string) $value, $isEncrypted, $admin?->id);
        }

        if (!empty($changes)) {
            AdminOperationLog::create([
                'admin_id'        => $admin?->id,
                'action'          => 'payment_settings_updated',
                'resource_type'   => 'system_setting',
                'resource_id'     => 0,
                'description'     => '金流設定更新：' . implode(' | ', $changes),
                'ip_address'      => $request->ip(),
                'user_agent'      => substr((string) $request->userAgent(), 0, 500),
                'request_summary' => ['keys' => array_keys($request->only(self::ALL_KEYS))],
                'created_at'      => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '金流設定已更新，下一筆交易即生效。',
        ]);
    }

    // ── 私有方法 ──────────────────────────────────────────────────────

    private function maskEncrypted(?string $encrypted): string
    {
        if (empty($encrypted)) {
            return '';
        }
        try {
            $plain = Crypt::decryptString($encrypted);
            $len   = mb_strlen($plain);
            if ($len <= 4) {
                return '****';
            }
            return '****' . mb_substr($plain, -4);
        } catch (\Throwable $e) {
            return '****(error)';
        }
    }
}
