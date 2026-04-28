<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminOperationLog;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

/**
 * 後台金流憑證設定
 *
 * GET  /api/v1/admin/settings/payment
 * PUT  /api/v1/admin/settings/payment
 *
 * 環境切換防護：
 *   sandbox → production：必須提供 confirm_password（管理員密碼）
 *   production → sandbox：Modal 確認即可（後端無密碼要求）
 *   切到 production 時：金流正式憑證必須齊全；啟用發票時發票正式憑證也必須齊全
 */
class PaymentSettingsController extends Controller
{
    private const ENCRYPTED_KEYS = [
        'ecpay_sandbox_hash_key',
        'ecpay_sandbox_hash_iv',
        'ecpay_production_hash_key',
        'ecpay_production_hash_iv',
        'ecpay_invoice_sandbox_hash_key',
        'ecpay_invoice_sandbox_hash_iv',
        'ecpay_invoice_production_hash_key',
        'ecpay_invoice_production_hash_iv',
    ];

    private const ALL_KEYS = [
        'ecpay_environment',
        // 金流憑證
        'ecpay_sandbox_merchant_id',
        'ecpay_sandbox_hash_key',
        'ecpay_sandbox_hash_iv',
        'ecpay_production_merchant_id',
        'ecpay_production_hash_key',
        'ecpay_production_hash_iv',
        // 發票
        'ecpay_invoice_enabled',
        'ecpay_invoice_donation_love_code',
        'ecpay_invoice_sandbox_merchant_id',
        'ecpay_invoice_sandbox_hash_key',
        'ecpay_invoice_sandbox_hash_iv',
        'ecpay_invoice_production_merchant_id',
        'ecpay_invoice_production_hash_key',
        'ecpay_invoice_production_hash_iv',
    ];

    /**
     * GET /api/v1/admin/settings/payment
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
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'ecpay_environment'                     => 'sometimes|string|in:sandbox,production',
            'ecpay_sandbox_merchant_id'             => 'sometimes|nullable|string|max:50',
            'ecpay_sandbox_hash_key'                => 'sometimes|nullable|string|max:500',
            'ecpay_sandbox_hash_iv'                 => 'sometimes|nullable|string|max:500',
            'ecpay_production_merchant_id'          => 'sometimes|nullable|string|max:50',
            'ecpay_production_hash_key'             => 'sometimes|nullable|string|max:500',
            'ecpay_production_hash_iv'              => 'sometimes|nullable|string|max:500',
            'ecpay_invoice_enabled'                 => 'sometimes|nullable|string',
            'ecpay_invoice_donation_love_code'      => 'sometimes|nullable|string|max:20',
            'ecpay_invoice_sandbox_merchant_id'     => 'sometimes|nullable|string|max:50',
            'ecpay_invoice_sandbox_hash_key'        => 'sometimes|nullable|string|max:500',
            'ecpay_invoice_sandbox_hash_iv'         => 'sometimes|nullable|string|max:500',
            'ecpay_invoice_production_merchant_id'  => 'sometimes|nullable|string|max:50',
            'ecpay_invoice_production_hash_key'     => 'sometimes|nullable|string|max:500',
            'ecpay_invoice_production_hash_iv'      => 'sometimes|nullable|string|max:500',
            'confirm_password'                      => 'sometimes|nullable|string',
        ]);

        $admin      = $request->user();
        $currentEnv = SystemSetting::get('ecpay_environment', 'sandbox');
        $incomingEnv = $request->input('ecpay_environment', $currentEnv);

        // ── sandbox → production：必須密碼確認 ─────────────────────
        if ($currentEnv === 'sandbox' && $incomingEnv === 'production') {
            $confirmPassword = $request->input('confirm_password');
            if (empty($confirmPassword)) {
                return response()->json([
                    'success' => false,
                    'code'    => 422,
                    'message' => '切換到正式環境需要密碼確認',
                    'errors'  => ['confirm_password' => '請輸入您的管理員密碼'],
                ], 422);
            }
            if (!Hash::check($confirmPassword, $admin->password)) {
                return response()->json([
                    'success' => false,
                    'code'    => 401,
                    'message' => '密碼錯誤',
                    'errors'  => ['confirm_password' => '密碼錯誤，請重新輸入'],
                ], 401);
            }
        }

        // ── 切到 production：憑證齊全 hard guard ────────────────────
        if ($incomingEnv === 'production') {
            $errors = [];

            foreach (['ecpay_production_merchant_id', 'ecpay_production_hash_key', 'ecpay_production_hash_iv'] as $key) {
                $val = $request->input($key) ?? SystemSetting::get($key, '');
                if (empty($val) || str_starts_with((string) $val, '****')) {
                    $errors[$key] = '切換正式環境前，金流正式憑證必須完整填寫';
                }
            }

            $invoiceEnabled = filter_var(
                $request->input('ecpay_invoice_enabled') ?? SystemSetting::get('ecpay_invoice_enabled', '0'),
                FILTER_VALIDATE_BOOLEAN,
            );
            if ($invoiceEnabled) {
                foreach (['ecpay_invoice_production_merchant_id', 'ecpay_invoice_production_hash_key', 'ecpay_invoice_production_hash_iv'] as $key) {
                    $val = $request->input($key) ?? SystemSetting::get($key, '');
                    if (empty($val) || str_starts_with((string) $val, '****')) {
                        $errors[$key] = '啟用發票時，發票正式憑證必須完整填寫';
                    }
                }
            }

            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'code'    => 422,
                    'message' => '正式環境憑證未完整設定',
                    'errors'  => $errors,
                ], 422);
            }
        }

        // ── 儲存 ─────────────────────────────────────────────────────
        $changes = [];
        foreach (self::ALL_KEYS as $key) {
            if (!$request->has($key)) {
                continue;
            }
            $value = $request->input($key) ?? '';

            if (in_array($key, self::ENCRYPTED_KEYS, true) && str_starts_with((string) $value, '****')) {
                continue; // 前端顯示的 mask 格式，跳過
            }

            $isEncrypted = in_array($key, self::ENCRYPTED_KEYS, true);
            $oldRaw      = SystemSetting::where('key_name', $key)->value('value') ?? '';
            $changes[]   = "{$key}: " . ($isEncrypted ? '[encrypted]' : $oldRaw) . ' → ' . ($isEncrypted ? '[encrypted]' : $value);

            SystemSetting::put($key, (string) $value, $isEncrypted, $admin?->id);
        }

        // ── 環境切換獨立 audit log ──────────────────────────────────
        if ($currentEnv !== $incomingEnv) {
            AdminOperationLog::create([
                'admin_id'        => $admin?->id,
                'action'          => 'ecpay_environment_switched',
                'resource_type'   => 'system_setting',
                'resource_id'     => 0,
                'description'     => "金流環境切換：{$currentEnv} → {$incomingEnv}",
                'ip_address'      => $request->ip(),
                'user_agent'      => substr((string) $request->userAgent(), 0, 500),
                'request_summary' => ['from' => $currentEnv, 'to' => $incomingEnv],
                'created_at'      => now(),
            ]);
            \Illuminate\Support\Facades\Cache::forget('setting:ecpay_environment');
        }

        // ── 一般設定更新 log ────────────────────────────────────────
        if (!empty($changes)) {
            AdminOperationLog::create([
                'admin_id'        => $admin?->id,
                'action'          => 'payment_settings_updated',
                'resource_type'   => 'system_setting',
                'resource_id'     => 0,
                'description'     => '金流與發票設定更新：' . implode(' | ', $changes),
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
        } catch (\Throwable) {
            return '****(error)';
        }
    }
}
