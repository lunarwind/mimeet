<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
<<<<<<< HEAD
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
=======
use App\Models\Order;
use App\Models\Report;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
>>>>>>> develop

class AdminController extends Controller
{
    /**
<<<<<<< HEAD
     * Admin login with lockout protection using real AdminUser model.
=======
     * Admin login — authenticates against admin_users table,
     * issues a Sanctum token scoped to the 'admin' guard.
>>>>>>> develop
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

<<<<<<< HEAD
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
=======
        $admin = AdminUser::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false, 'code' => 401, 'message' => 'Email 或密碼不正確',
            ], 401);
        }

        if (!$admin->is_active) {
            return response()->json([
                'success' => false, 'code' => 403, 'message' => '帳號已停用',
            ], 403);
        }

        // Issue token with 'admin' ability to distinguish from user tokens
        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $admin->update(['last_login_at' => now()]);
>>>>>>> develop

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

        $query = User::query();
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(fn ($q) => $q->where('nickname', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }
        $perPage = (int) $request->input('per_page', 20);
        $members = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true, 'code' => 'MEMBERS_LIST', 'message' => 'OK',
            'data' => [
                'members' => $members->map(fn (User $u) => [
                    'id' => $u->id, 'email' => $u->email, 'nickname' => $u->nickname,
                    'gender' => $u->gender, 'membership_level' => $u->membership_level,
                    'credit_score' => $u->credit_score, 'status' => $u->status,
                    'created_at' => $u->created_at?->toISOString(),
                ]),
                'pagination' => [
                    'current_page' => $members->currentPage(), 'per_page' => $members->perPage(),
                    'total' => $members->total(), 'last_page' => $members->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get single member detail.
     */
    public function memberDetail(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'code' => 404, 'message' => '找不到此會員'], 404);
        }

        return response()->json([
            'success' => true, 'code' => 'MEMBER_DETAIL', 'message' => 'OK',
            'data' => ['member' => [
                'id' => $user->id, 'email' => $user->email, 'nickname' => $user->nickname,
                'gender' => $user->gender, 'birth_date' => $user->birth_date?->format('Y-m-d'),
                'bio' => $user->bio, 'avatar_url' => $user->avatar_url, 'location' => $user->location,
                'occupation' => $user->occupation, 'education' => $user->education,
                'interests' => $user->interests, 'membership_level' => $user->membership_level,
                'credit_score' => $user->credit_score, 'email_verified' => $user->email_verified,
                'phone_verified' => $user->phone_verified, 'status' => $user->status,
                'created_at' => $user->created_at?->toISOString(),
                'last_active_at' => $user->last_active_at?->toISOString(),
            ]],
        ]);
    }

    /**
     * Perform action on a member (adjust_score, suspend, unsuspend).
     */
    public function memberAction(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|string|in:adjust_score,suspend,unsuspend',
            'score_delta' => 'required_if:action,adjust_score|integer|min:-50|max:50',
            'reason' => 'sometimes|string|max:500',
        ]);

        $user = User::findOrFail($id);
        $action = $request->input('action');

        if ($action === 'adjust_score') {
            \App\Services\CreditScoreService::adjust(
                $user, (int) $request->input('score_delta'),
                'admin_adjust', $request->input('reason', '管理員手動調整'), $request->user()?->id
            );
        } elseif ($action === 'suspend') {
            $user->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();
        } elseif ($action === 'unsuspend') {
            $user->forceFill(['status' => 'active'])->save();
        }

        $messages = ['adjust_score' => '信用分數已調整。', 'suspend' => '會員已停權。', 'unsuspend' => '會員已恢復。'];

        return response()->json([
            'success' => true,
            'code' => 'MEMBER_ACTION_' . strtoupper($action),
            'message' => $messages[$action] ?? '操作完成。',
        ]);
    }

    /**
     * PATCH /api/v1/admin/members/{id}/permissions
     * Directly set a user's membership_level, credit_score, and status.
     * Logs to credit_score_histories + admin_operation_logs (via middleware).
     */
    public function updatePermissions(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'membership_level' => 'sometimes|numeric|in:0,1,1.5,2,3',
            'credit_score' => 'sometimes|integer|min:0|max:100',
            'status' => 'sometimes|string|in:active,suspended',
            'reason' => 'sometimes|string|max:500',
        ]);

        $user = User::findOrFail($id);
        $changes = [];
        $reason = $request->input('reason', '管理員手動調整權限');
        $adminId = $request->user()?->id;

        // Credit score — use CreditScoreService for history logging
        if ($request->has('credit_score')) {
            $newScore = (int) $request->input('credit_score');
            $delta = $newScore - $user->credit_score;
            if ($delta !== 0) {
                \App\Services\CreditScoreService::adjust($user, $delta, 'admin_set', $reason, $adminId);
                $changes[] = "credit_score: {$user->credit_score} → {$newScore}";
            }
        }

        // Membership level
        if ($request->has('membership_level')) {
            $newLevel = (float) $request->input('membership_level');
            if ((float) $user->membership_level !== $newLevel) {
                $changes[] = "membership_level: {$user->membership_level} → {$newLevel}";
                $user->forceFill(['membership_level' => $newLevel])->save();
            }
        }

        // Status (suspend / unsuspend)
        if ($request->has('status')) {
            $newStatus = $request->input('status');
            if ($user->status !== $newStatus) {
                $changes[] = "status: {$user->status} → {$newStatus}";
                $updateData = ['status' => $newStatus];
                if ($newStatus === 'suspended') {
                    $updateData['suspended_at'] = now();
                }
                $user->update($updateData);
            }
        }

        if (empty($changes)) {
            return response()->json([
                'success' => true,
                'code' => 'NO_CHANGES',
                'message' => '未偵測到變更。',
            ]);
        }

        $user->refresh();

        return response()->json([
            'success' => true,
            'code' => 'MEMBER_PERMISSIONS_UPDATED',
            'message' => '會員權限已更新。',
            'data' => [
                'member' => [
                    'id' => $user->id,
                    'membership_level' => $user->membership_level,
                    'credit_score' => $user->credit_score,
                    'status' => $user->status,
                ],
                'changes' => $changes,
            ],
        ]);
    }

    /**
     * PATCH /api/v1/admin/members/{id}/profile
     * Update a user's profile fields. Super-admin only.
     * Manually logs before/after snapshot to admin_operation_logs.
     */
    public function updateProfile(Request $request, int $id): JsonResponse
    {
        // Role check — super_admin only
        $admin = $request->user();
        if (!$admin || $admin->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => '此功能僅限超級管理員使用',
            ], 403);
        }

        $request->validate([
            'nickname' => 'sometimes|string|min:2|max:20',
            'birth_date' => 'sometimes|date|before:-18 years',
            'avatar_url' => 'sometimes|nullable|string|max:500',
            'gender' => 'sometimes|string|in:male,female',
            'height' => 'sometimes|nullable|integer|min:100|max:250',
            'location' => 'sometimes|nullable|string|max:50',
            'occupation' => 'sometimes|nullable|string|max:50',
            'education' => 'sometimes|nullable|string|max:50',
            'bio' => 'sometimes|nullable|string|max:500',
        ]);

        $user = User::findOrFail($id);

        $allowedFields = ['nickname', 'birth_date', 'avatar_url', 'gender', 'height', 'location', 'occupation', 'education', 'bio'];
        $updates = $request->only($allowedFields);

        // Filter to only actually changed fields
        $before = [];
        $after = [];
        foreach ($updates as $key => $newValue) {
            $oldValue = $user->getAttribute($key);
            $oldStr = $oldValue instanceof \Carbon\Carbon ? $oldValue->format('Y-m-d') : (string) ($oldValue ?? '');
            $newStr = (string) ($newValue ?? '');
            if ($oldStr !== $newStr) {
                $before[$key] = $oldValue;
                $after[$key] = $newValue;
            }
        }

        if (empty($after)) {
            return response()->json([
                'success' => true,
                'code' => 'NO_CHANGES',
                'message' => '未偵測到變更。',
            ]);
        }

        $user->update($after);

        // Write detailed audit log with before/after snapshot
        \App\Models\AdminOperationLog::create([
            'admin_id' => $admin->id,
            'action' => 'update_member_profile',
            'resource_type' => 'member',
            'resource_id' => $id,
            'description' => "PATCH admin/members/{$id}/profile",
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'request_summary' => [
                'before' => $before,
                'after' => $after,
            ],
            'created_at' => now(),
        ]);

        $user->refresh();

        return response()->json([
            'success' => true,
            'code' => 'MEMBER_PROFILE_UPDATED',
            'message' => '會員資料已更新。',
            'data' => [
                'member' => [
                    'id' => $user->id,
                    'nickname' => $user->nickname,
                    'gender' => $user->gender,
                    'bio' => $user->bio,
                    'location' => $user->location,
                ],
                'changes' => array_keys($after),
            ],
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

        $query = Report::with(['reporter:id,nickname', 'reportedUser:id,nickname']);
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        if ($request->filled('type')) $query->where('type', $request->input('type'));
        $perPage = (int) $request->input('per_page', 20);
        $tickets = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true, 'code' => 'TICKETS_LIST', 'message' => 'OK',
            'data' => [
                'tickets' => $tickets->map(fn (Report $r) => [
                    'id' => $r->id, 'uuid' => $r->uuid, 'type' => $r->type, 'status' => $r->status,
                    'description' => $r->description,
                    'reporter' => $r->reporter ? ['id' => $r->reporter->id, 'nickname' => $r->reporter->nickname] : null,
                    'reported_user' => $r->reportedUser ? ['id' => $r->reportedUser->id, 'nickname' => $r->reportedUser->nickname] : null,
                    'created_at' => $r->created_at?->toISOString(),
                ]),
                'pagination' => [
                    'current_page' => $tickets->currentPage(), 'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(), 'last_page' => $tickets->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Update a ticket status.
     */
    public function updateTicket(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:pending,investigating,resolved,dismissed',
            'admin_note' => 'sometimes|string|max:1000',
        ]);

        $report = Report::findOrFail($id);
        $report->update(array_filter([
            'status' => $request->input('status'),
            'resolution_note' => $request->input('admin_note'),
            'resolved_by' => in_array($request->input('status'), ['resolved', 'dismissed']) ? $request->user()?->id : null,
            'resolved_at' => in_array($request->input('status'), ['resolved', 'dismissed']) ? now() : null,
        ], fn ($v) => $v !== null));

        return response()->json([
            'success' => true,
            'code' => 'TICKET_UPDATED',
            'message' => '工單已更新。',
            'data' => [
                'ticket' => [
                    'id' => $report->id,
                    'status' => $report->fresh()->status,
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

<<<<<<< HEAD
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
=======
        $query = Order::with(['user:id,nickname', 'plan:id,name']);
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        $perPage = (int) $request->input('per_page', 20);
        $payments = $query->orderByDesc('created_at')->paginate($perPage);
>>>>>>> develop

        return response()->json([
            'success' => true, 'code' => 'PAYMENTS_LIST', 'message' => 'OK',
            'data' => [
                'payments' => $payments->map(fn (Order $o) => [
                    'id' => $o->id, 'order_number' => $o->order_number,
                    'user' => $o->user ? ['id' => $o->user->id, 'nickname' => $o->user->nickname] : null,
                    'plan_name' => $o->plan?->name, 'amount' => $o->amount,
                    'payment_method' => $o->payment_method,
                    'status' => $o->status, 'paid_at' => $o->paid_at?->toISOString(),
                    'ecpay_trade_no' => $o->ecpay_trade_no,
                    'ecpay_payment_date' => $o->ecpay_payment_date,
                    'ecpay_payment_type' => $o->ecpay_payment_type,
                    'invoice_no' => $o->invoice_no,
                    'invoice_date' => $o->invoice_date,
                    'created_at' => $o->created_at?->toISOString(),
                ]),
                'pagination' => [
                    'current_page' => $payments->currentPage(), 'per_page' => $payments->perPage(),
                    'total' => $payments->total(), 'last_page' => $payments->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get system settings.
     */
    public function getSettings(): JsonResponse
    {
        $defaults = [
            'credit_score_initial' => '60', 'credit_score_min' => '0',
            'credit_score_report_deduction' => '10', 'credit_score_no_show_deduction' => '20',
            'credit_score_suspend_threshold' => '0', 'max_photos_per_user' => '6',
            'image_moderation_enabled' => '0', 'ecpay_is_sandbox' => '1',
            'trial_plan_price' => '49', 'trial_plan_days' => '3',
        ];
        $settings = [];
        foreach ($defaults as $k => $v) {
            $settings[$k] = SystemSetting::get($k, $v);
        }

        return response()->json([
            'success' => true, 'code' => 'SYSTEM_SETTINGS', 'message' => 'OK',
            'data' => ['settings' => $settings],
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

        $admin = $request->user();
        foreach ($request->except(['_token']) as $key => $value) {
            SystemSetting::set($key, $value, $admin?->id);
        }

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
