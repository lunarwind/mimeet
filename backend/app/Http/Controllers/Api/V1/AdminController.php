<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /**
     * Admin login with lockout protection using real AdminUser model.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $lockKey = 'admin_login_lock:' . $request->ip();
        $attemptsKey = 'admin_login_attempts:' . $request->ip();

        // Check if IP is locked
        if (Cache::has($lockKey)) {
            $remainingSeconds = Cache::get($lockKey) - time();
            Log::warning('Admin login attempt while locked', ['ip' => $request->ip(), 'email' => $request->email]);
            return response()->json([
                'success' => false,
                'code' => 'ADMIN_LOCKED',
                'message' => '登入嘗試過多，請 ' . ceil($remainingSeconds / 60) . ' 分鐘後再試',
            ], 429);
        }

        // Look up AdminUser by email
        $admin = AdminUser::where('email', $request->email)->first();

        // Check if admin exists and is active
        if (!$admin || !$admin->is_active) {
            $this->incrementLoginAttempts($attemptsKey, $lockKey, $request);
            return response()->json([
                'success' => false,
                'code' => 'ADMIN_LOGIN_FAILED',
                'message' => '帳號或密碼錯誤。',
            ], 401);
        }

        // Check if the admin account itself is locked
        if ($admin->isLocked()) {
            Log::warning('Admin account locked', ['email' => $request->email]);
            return response()->json([
                'success' => false,
                'code' => 'ADMIN_LOCKED',
                'message' => '帳號已鎖定，請稍後再試。',
            ], 429);
        }

        // Check password with Hash::check
        if (!Hash::check($request->password, $admin->password)) {
            // Increment failed login attempts on the AdminUser record
            $admin->increment('failed_login_attempts');

            if ($admin->failed_login_attempts >= 5) {
                $admin->update(['locked_until' => now()->addMinutes(15)]);
            }

            $this->incrementLoginAttempts($attemptsKey, $lockKey, $request);

            return response()->json([
                'success' => false,
                'code' => 'ADMIN_LOGIN_FAILED',
                'message' => '帳號或密碼錯誤。',
            ], 401);
        }

        // Successful login — clear all lockout state
        Cache::forget($attemptsKey);
        Cache::forget($lockKey);
        $admin->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        // Create a real Sanctum token
        $token = $admin->createToken('admin-token', ['role:' . $admin->role])->plainTextToken;

        Log::info('Admin login successful', ['ip' => $request->ip(), 'email' => $request->email]);

        return response()->json([
            'success' => true,
            'code' => 'ADMIN_LOGIN_SUCCESS',
            'message' => '管理員登入成功。',
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'email' => $admin->email,
                    'name' => $admin->name,
                    'role' => $admin->role,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * Helper: increment IP-based login attempts and lock if threshold reached.
     */
    private function incrementLoginAttempts(string $attemptsKey, string $lockKey, Request $request): void
    {
        $attempts = Cache::get($attemptsKey, 0) + 1;
        Cache::put($attemptsKey, $attempts, 900); // 15 min TTL

        Log::warning('Admin login failed', [
            'ip' => $request->ip(),
            'email' => $request->email,
            'attempts' => $attempts,
        ]);

        if ($attempts >= 5) {
            Cache::put($lockKey, time() + 900, 900);
        }
    }

    /**
     * Get paginated member list.
     */
    public function members(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|string|in:active,suspended,all',
            'search' => 'sometimes|string',
        ]);

        $mockMembers = [];
        for ($i = 1; $i <= 10; $i++) {
            $mockMembers[] = [
                'id' => 'usr_member' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'email' => "user{$i}@test.com",
                'nickname' => "User{$i}",
                'gender' => $i % 2 === 0 ? 'female' : 'male',
                'membership_level' => $i % 3,
                'credit_score' => max(0, 100 - ($i * 5)),
                'status' => $i === 10 ? 'suspended' : 'active',
                'created_at' => now()->subDays($i * 10)->toISOString(),
            ];
        }

        return response()->json([
            'success' => true,
            'code' => 'MEMBERS_LIST',
            'message' => 'OK',
            'data' => [
                'members' => $mockMembers,
                'pagination' => [
                    'current_page' => (int) $request->input('page', 1),
                    'per_page' => (int) $request->input('per_page', 20),
                    'total' => 150,
                    'last_page' => 8,
                ],
            ],
        ]);
    }

    /**
     * Get single member detail.
     */
    public function memberDetail(Request $request, string $id): JsonResponse
    {
        $mockMember = [
            'id' => $id,
            'email' => 'member@test.com',
            'nickname' => 'TestMember',
            'gender' => 'female',
            'birth_date' => '1997-05-20',
            'bio' => '我是一位測試會員。',
            'avatar_url' => null,
            'photos' => [],
            'location' => '台北',
            'occupation' => '設計師',
            'education' => 'bachelor',
            'interests' => ['旅行', '音樂'],
            'membership_level' => 1,
            'credit_score' => 85,
            'email_verified' => true,
            'phone_verified' => true,
            'status' => 'active',
            'created_at' => now()->subDays(60)->toISOString(),
            'last_active_at' => now()->subHours(2)->toISOString(),
            'subscription' => [
                'plan_name' => '月方案',
                'status' => 'active',
                'expires_at' => now()->addDays(20)->toISOString(),
            ],
            'reports_received' => 0,
            'reports_made' => 1,
        ];

        return response()->json([
            'success' => true,
            'code' => 'MEMBER_DETAIL',
            'message' => 'OK',
            'data' => [
                'member' => $mockMember,
            ],
        ]);
    }

    /**
     * Perform action on a member (adjust_score, suspend, unsuspend).
     */
    public function memberAction(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|string|in:adjust_score,suspend,unsuspend',
            'score_delta' => 'required_if:action,adjust_score|integer',
            'reason' => 'sometimes|string|max:500',
        ]);

        $action = $request->input('action');
        $messages = [
            'adjust_score' => '信用分數已調整。',
            'suspend' => '會員已停權。',
            'unsuspend' => '會員已恢復。',
        ];

        return response()->json([
            'success' => true,
            'code' => 'MEMBER_ACTION_' . strtoupper($action),
            'message' => $messages[$action] ?? '操作完成。',
        ]);
    }

    /**
     * Get ticket list.
     */
    public function tickets(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|string|in:open,in_progress,resolved,closed',
        ]);

        $mockTickets = [];
        $statuses = ['open', 'in_progress', 'resolved', 'closed'];
        $types = ['report', 'cancel_subscription', 'feedback', 'bug'];

        for ($i = 1; $i <= 5; $i++) {
            $mockTickets[] = [
                'id' => 'ticket_' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'type' => $types[$i % count($types)],
                'status' => $statuses[$i % count($statuses)],
                'subject' => '測試工單 #' . $i,
                'description' => '這是一個測試工單的描述。',
                'user_id' => 'usr_member' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'user_nickname' => 'User' . $i,
                'created_at' => now()->subDays($i)->toISOString(),
                'updated_at' => now()->subHours($i * 3)->toISOString(),
            ];
        }

        return response()->json([
            'success' => true,
            'code' => 'TICKETS_LIST',
            'message' => 'OK',
            'data' => [
                'tickets' => $mockTickets,
                'pagination' => [
                    'current_page' => (int) $request->input('page', 1),
                    'per_page' => (int) $request->input('per_page', 20),
                    'total' => 25,
                    'last_page' => 2,
                ],
            ],
        ]);
    }

    /**
     * Update a ticket status.
     */
    public function updateTicket(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:open,in_progress,resolved,closed',
            'admin_note' => 'sometimes|string|max:1000',
        ]);

        return response()->json([
            'success' => true,
            'code' => 'TICKET_UPDATED',
            'message' => '工單已更新。',
            'data' => [
                'ticket' => [
                    'id' => $id,
                    'status' => $request->input('status'),
                    'updated_at' => now()->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * Get payment records.
     */
    public function payments(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => 'sometimes|string|in:completed,pending,failed,refunded',
        ]);

        $mockPayments = [];
        $statuses = ['completed', 'completed', 'pending', 'failed', 'refunded'];

        for ($i = 1; $i <= 8; $i++) {
            $mockPayments[] = [
                'id' => 'pay_' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'order_id' => 'order_' . Str::random(12),
                'user_id' => 'usr_member' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'user_nickname' => 'User' . $i,
                'plan_name' => $i % 2 === 0 ? '月方案' : '季方案',
                'amount' => $i % 2 === 0 ? 599 : 1499,
                'currency' => 'TWD',
                'payment_method' => $i % 3 === 0 ? 'ATM' : ($i % 3 === 1 ? 'Credit' : 'WebATM'),
                'ecpay_trade_no' => '2026041' . str_pad($i, 7, '0', STR_PAD_LEFT),
                'ecpay_invoice_no' => $i % 2 === 0 ? ('AA' . str_pad($i * 111, 8, '0', STR_PAD_LEFT)) : null,
                'status' => $statuses[$i % count($statuses)],
                'paid_at' => now()->subDays($i * 3)->toISOString(),
                'created_at' => now()->subDays($i * 3)->toISOString(),
            ];
        }

        return response()->json([
            'success' => true,
            'code' => 'PAYMENTS_LIST',
            'message' => 'OK',
            'data' => [
                'payments' => $mockPayments,
                'pagination' => [
                    'current_page' => (int) $request->input('page', 1),
                    'per_page' => (int) $request->input('per_page', 20),
                    'total' => 80,
                    'last_page' => 4,
                ],
            ],
        ]);
    }

    /**
     * Get system settings.
     */
    public function getSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'SYSTEM_SETTINGS',
            'message' => 'OK',
            'data' => [
                'settings' => [
                    'credit_score_initial' => 100,
                    'credit_score_min' => 0,
                    'credit_score_report_deduction' => 10,
                    'credit_score_no_show_deduction' => 20,
                    'credit_score_suspend_threshold' => 20,
                    'max_photos_per_user' => 6,
                    'image_moderation_enabled' => false,
                    'ecpay_is_sandbox' => true,
                    'trial_plan_price' => 49,
                    'trial_plan_days' => 3,
                ],
            ],
        ]);
    }

    /**
     * Update system settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'credit_score_initial' => 'sometimes|integer|min:0|max:200',
            'credit_score_report_deduction' => 'sometimes|integer|min:0|max:50',
            'credit_score_no_show_deduction' => 'sometimes|integer|min:0|max:50',
            'credit_score_suspend_threshold' => 'sometimes|integer|min:0|max:100',
            'max_photos_per_user' => 'sometimes|integer|min:1|max:20',
            'image_moderation_enabled' => 'sometimes|boolean',
            'trial_plan_price' => 'sometimes|integer|min:0',
            'trial_plan_days' => 'sometimes|integer|min:1|max:30',
        ]);

        return response()->json([
            'success' => true,
            'code' => 'SETTINGS_UPDATED',
            'message' => '系統設定已更新。',
        ]);
    }

    /**
     * Confirm admin password (S9 - password confirmation for sensitive operations).
     */
    public function confirmPassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $admin = $request->user();
        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => '密碼錯誤。',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => '密碼確認成功。',
        ]);
    }

    /**
     * Get ECPay settings (S12-04).
     */
    public function getEcpaySettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'ECPAY_SETTINGS',
            'message' => 'OK',
            'data' => [
                'merchant_id' => SystemSetting::getValue('ecpay.merchant_id', config('services.ecpay.merchant_id', env('ECPAY_MERCHANT_ID', '3002607'))),
                'hash_key' => SystemSetting::getValue('ecpay.hash_key', config('services.ecpay.hash_key', env('ECPAY_HASH_KEY', ''))),
                'hash_iv' => SystemSetting::getValue('ecpay.hash_iv', config('services.ecpay.hash_iv', env('ECPAY_HASH_IV', ''))),
                'is_sandbox' => (bool) SystemSetting::getValue('ecpay.is_sandbox', env('ECPAY_IS_SANDBOX', true)),
            ],
        ]);
    }

    /**
     * Update ECPay settings (S12-04).
     */
    public function updateEcpaySettings(Request $request): JsonResponse
    {
        $request->validate([
            'merchant_id' => 'sometimes|string|max:20',
            'hash_key' => 'sometimes|string|max:100',
            'hash_iv' => 'sometimes|string|max:100',
            'is_sandbox' => 'sometimes|boolean',
        ]);

        $keyMap = [
            'merchant_id' => ['key' => 'ecpay.merchant_id', 'type' => 'string'],
            'hash_key' => ['key' => 'ecpay.hash_key', 'type' => 'string'],
            'hash_iv' => ['key' => 'ecpay.hash_iv', 'type' => 'string'],
            'is_sandbox' => ['key' => 'ecpay.is_sandbox', 'type' => 'boolean'],
        ];

        foreach ($keyMap as $field => $config) {
            if ($request->has($field)) {
                SystemSetting::setValue($config['key'], $request->input($field), $config['type'], 'ecpay', "ECPay {$field}");
            }
        }

        return response()->json([
            'success' => true,
            'code' => 'ECPAY_SETTINGS_UPDATED',
            'message' => '金流設定已更新。',
        ]);
    }

    /**
     * Update member permissions (S12-09).
     */
    public function updateMemberPermissions(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'membership_level' => 'sometimes|integer|min:0|max:3',
            'credit_score' => 'sometimes|integer|min:0|max:100',
            'status' => 'sometimes|string|in:active,suspended',
        ]);

        // In a real implementation, this would update the user model:
        // $user = \App\Models\User::findOrFail($id);
        // $user->forceFill($request->only(['membership_level', 'credit_score', 'status']))->save();

        Log::info('Member permissions updated', [
            'member_id' => $id,
            'changes' => $request->only(['membership_level', 'credit_score', 'status']),
            'admin' => $request->user()?->email,
        ]);

        return response()->json([
            'success' => true,
            'code' => 'MEMBER_PERMISSIONS_UPDATED',
            'message' => '會員權限已更新。',
            'data' => [
                'id' => $id,
                'membership_level' => $request->input('membership_level'),
                'credit_score' => $request->input('credit_score'),
                'status' => $request->input('status'),
            ],
        ]);
    }

    /**
     * Update member profile (S12-10, super_admin only).
     */
    public function updateMemberProfile(Request $request, string $id): JsonResponse
    {
        // Check super_admin role
        $admin = $request->user();
        if (!$admin || (method_exists($admin, 'isSuperAdmin') && !$admin->isSuperAdmin())) {
            // Fallback: check role property
            if ($admin && isset($admin->role) && $admin->role !== 'super_admin') {
                return response()->json([
                    'success' => false,
                    'code' => 'FORBIDDEN',
                    'message' => '僅超級管理員可編輯會員資料。',
                ], 403);
            }
        }

        $request->validate([
            'nickname' => 'sometimes|string|max:20',
            'bio' => 'sometimes|string|max:500',
            'location' => 'sometimes|string|max:50',
            'occupation' => 'sometimes|string|max:50',
            'education' => 'sometimes|string|max:50',
            'height' => 'sometimes|integer|min:100|max:250',
            'weight' => 'sometimes|integer|min:30|max:200',
        ]);

        // In a real implementation:
        // $user = \App\Models\User::findOrFail($id);
        // $user->forceFill($request->only([...]))->save();

        Log::info('Member profile updated by admin', [
            'member_id' => $id,
            'changes' => $request->only(['nickname', 'bio', 'location', 'occupation', 'education', 'height', 'weight']),
            'admin' => $admin?->email,
        ]);

        return response()->json([
            'success' => true,
            'code' => 'MEMBER_PROFILE_UPDATED',
            'message' => '會員資料已更新。',
        ]);
    }
}
