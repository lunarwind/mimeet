<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Report;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Admin login — authenticates against admin_users table,
     * issues a Sanctum token scoped to the 'admin' guard.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

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

        // Issue token bound to login IP, TTL 480min (8h) per API-002 §1.2 (F-001 fix)
        $loginIp = $request->ip();
        $expiresAt = now()->addMinutes(480);
        $tokenResult = $admin->createToken("admin-token-{$loginIp}", ['admin']);
        $tokenResult->accessToken->forceFill(['expires_at' => $expiresAt])->save();
        $token = $tokenResult->plainTextToken;

        $admin->update(['last_login_at' => now(), 'last_login_ip' => $loginIp]);

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
                'expires_at' => $expiresAt->toISOString(),
                'last_login_ip' => $loginIp,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();
        return response()->json([
            'success' => true,
            'data' => [
                'id'            => $admin->id,
                'name'          => $admin->name,
                'email'         => $admin->email,
                'role'          => $admin->role,
                'last_login_at' => $admin->last_login_at?->toISOString(),
                'last_login_ip' => $admin->last_login_ip,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['success' => true, 'message' => '已登出']);
    }

    /**
     * Get paginated member list.
     */
    public function members(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:1000',
            'status' => 'sometimes|string|in:active,suspended,all',
            'search' => 'sometimes|string',
            // F27 精確篩選（後台管理用途，不走「OR NULL」寬鬆邏輯）
            'dating_budget' => 'sometimes|string|in:casual,moderate,generous,luxury,undisclosed',
            'style' => 'sometimes|string|in:fresh,sweet,sexy,intellectual,sporty',
        ]);

        $query = User::query();
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(fn ($q) => $q->where('nickname', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }
        // F27：後台精確篩選
        if ($request->filled('dating_budget')) {
            $query->where('dating_budget', $request->input('dating_budget'));
        }
        if ($request->filled('style')) {
            $query->where('style', $request->input('style'));
        }
        $perPage = (int) $request->input('per_page', 20);
        $members = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true, 'code' => 'MEMBERS_LIST', 'message' => 'OK',
            'data' => [
                'members' => $members->map(fn (User $u) => [
                    'id' => $u->id,
                    'email' => $u->email ?? '',
                    'nickname' => $u->nickname,
                    'gender' => $u->gender,
                    'avatar_url' => $u->avatar_url,
                    'membership_level' => $u->membership_level ?? 0,
                    'credit_score' => $u->credit_score ?? 60,
                    'status' => $u->status ?? 'active',
                    'email_verified' => (bool) ($u->email_verified ?? false),
                    'phone_verified' => (bool) ($u->phone_verified ?? false),
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

        // F40：訂閱 + 點數聚合
        $subscription = \App\Models\Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderByDesc('expires_at')
            ->first();

        $subscriptionData = null;
        if ($subscription) {
            $plan = \App\Models\SubscriptionPlan::find($subscription->plan_id);
            $daysRemaining = $subscription->expires_at
                ? max(0, (int) now()->startOfDay()->diffInDays($subscription->expires_at, false))
                : null;
            $subscriptionData = [
                'plan_slug' => $plan?->slug,
                'plan_name' => $plan?->name,
                'status' => $subscription->status,
                'started_at' => $subscription->started_at?->toISOString(),
                'expires_at' => $subscription->expires_at?->toISOString(),
                'days_remaining' => $daysRemaining,
            ];
        }

        $pointsStats = [
            'total_purchased' => (int) \App\Models\PointTransaction::where('user_id', $user->id)
                ->where('type', 'purchase')->sum('amount'),
            'total_spent' => (int) abs(\App\Models\PointTransaction::where('user_id', $user->id)
                ->where('type', 'consume')->sum('amount')),
            'purchase_amount_ntd' => (int) \App\Models\PointOrder::where('user_id', $user->id)
                ->where('status', 'paid')->sum('amount'),
        ];

        // F40 擴充：會員詳情用點數明細
        $pointsDetail = [
            'balance' => (int) ($user->points_balance ?? 0),
            'total_purchased' => $pointsStats['total_purchased'],
            'total_spent' => $pointsStats['total_spent'],
            'purchase_amount_ntd' => $pointsStats['purchase_amount_ntd'],
            'purchase_count' => (int) \App\Models\PointOrder::where('user_id', $user->id)
                ->where('status', 'paid')->count(),
            'consumption_by_feature' => \App\Models\PointTransaction::where('user_id', $user->id)
                ->where('type', 'consume')
                ->whereNotNull('feature')
                ->selectRaw('feature, SUM(ABS(amount)) as total')
                ->groupBy('feature')
                ->pluck('total', 'feature')
                ->map(fn ($v) => (int) $v),
            'recent_transactions' => \App\Models\PointTransaction::where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'type', 'amount', 'balance_after', 'feature', 'description', 'created_at'])
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'amount' => (int) $t->amount,
                    'balance_after' => (int) $t->balance_after,
                    'feature' => $t->feature,
                    'description' => $t->description,
                    'created_at' => $t->created_at?->toISOString(),
                ]),
            'purchase_orders' => \App\Models\PointOrder::where('user_id', $user->id)
                ->with('package:id,name')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'trade_no', 'package_id', 'points', 'amount', 'status', 'paid_at', 'created_at'])
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'trade_no' => $o->trade_no,
                    'package_name' => $o->package?->name,
                    'points' => (int) $o->points,
                    'amount' => (int) $o->amount,
                    'status' => $o->status,
                    'paid_at' => $o->paid_at?->toISOString(),
                    'created_at' => $o->created_at?->toISOString(),
                ]),
        ];

        return response()->json([
            'success' => true, 'code' => 'MEMBER_DETAIL', 'message' => 'OK',
            'data' => ['member' => [
                'subscription' => $subscriptionData,
                'points_balance' => (int) ($user->points_balance ?? 0),
                'stealth_until' => $user->stealth_until?->toISOString(),
                'stealth_active' => $user->isStealthActive(),
                'points_stats' => $pointsStats,
                'points_detail' => $pointsDetail,
                'uid' => $user->id,
                'id' => $user->id,
                'email' => $user->email ?? '',
                'nickname' => $user->nickname ?? '（未設定）',
                'gender' => $user->gender,
                'age' => $user->birth_date ? $user->birth_date->age : null,
                'birth_date' => $user->birth_date?->format('Y-m-d'),
                'avatar' => $user->avatar_url,
                'introduction' => $user->bio,
                'location' => $user->location,
                'height' => $user->height,
                'weight' => $user->weight,
                'job' => $user->occupation,
                'education' => $user->education,
                // F27 profile fields
                'style' => $user->style,
                'dating_budget' => $user->dating_budget,
                'dating_frequency' => $user->dating_frequency,
                'dating_type' => $user->dating_type,
                'relationship_goal' => $user->relationship_goal,
                'smoking' => $user->smoking,
                'drinking' => $user->drinking,
                'car_owner' => $user->car_owner,
                'availability' => $user->availability,
                'level' => $user->membership_level ?? 0,
                'membership_level' => $user->membership_level ?? 0,
                'credit_score' => $user->credit_score ?? 60,
                'status' => $user->status ?? 'active',
                'email_verified' => (bool) ($user->email_verified ?? false),
                'phone_verified' => (bool) ($user->phone_verified ?? false),
                'advanced_verified' => (bool) ($user->advanced_verified ?? false),
                'photos' => [],
                'created_at' => $user->created_at?->toISOString(),
                'last_active_at' => $user->last_active_at?->toISOString(),
            ]],
        ]);
    }

    /**
     * GET /api/v1/admin/members/{id}/credit-logs — F-002
     * Response aligned to API-002 §4.4 spec.
     */
    public function memberCreditLogs(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'code' => 404, 'message' => '找不到此會員'], 404);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);

        $logs = \App\Models\CreditScoreHistory::where('user_id', $id)
            ->with('adminUser')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->map(fn ($l) => [
                'id' => $l->id,
                'change' => $l->delta,
                'before' => $l->score_before,
                'after' => $l->score_after,
                'type' => $l->type,
                'reason' => $l->reason,
                'operator' => $l->adminUser ? [
                    'id' => $l->adminUser->id,
                    'name' => $l->adminUser->name,
                ] : null,
                'created_at' => $l->created_at?->toISOString(),
            ]),
            'meta' => [
                // [TECH DEBT] meta.page 符合 API-002 §4.4 規格（current_page 已順便校正）
                'page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * Perform action on a member (adjust_score, suspend, unsuspend).
     */
    public function memberAction(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action'      => 'required|string|in:adjust_score,suspend,unsuspend,verify_phone,unverify_phone,verify_advanced,unverify_advanced,set_level,require_reverify,add_note',
            'score_delta' => 'required_if:action,adjust_score|integer|min:-50|max:50',
            'reason'      => 'sometimes|string|max:500',
            'level'       => 'required_if:action,set_level|numeric|in:0,1,1.5,2,3',
            'verify_type' => 'required_if:action,require_reverify|in:phone,advanced',
            'note'        => 'required_if:action,add_note|string|max:500',
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
        } elseif ($action === 'verify_phone') {
            $user->forceFill(['phone_verified' => true])->save();
            if ((float) $user->membership_level < 1) {
                $user->forceFill(['membership_level' => 1])->save();
            }
        } elseif ($action === 'unverify_phone') {
            $user->forceFill(['phone_verified' => false])->save();
            if ((float) $user->membership_level === 1.0) {
                $user->forceFill(['membership_level' => 0])->save();
            }
        } elseif ($action === 'verify_advanced') {
            $target = $user->gender === 'female' ? 1.5 : 2;
            if ((float) $user->membership_level < $target) {
                $user->forceFill(['membership_level' => $target])->save();
            }
        } elseif ($action === 'unverify_advanced') {
            $current = (float) $user->membership_level;
            if ($current === 1.5 || $current === 2.0) {
                $user->forceFill(['membership_level' => 1])->save();
            }
        } elseif ($action === 'set_level') {
            $user->forceFill(['membership_level' => (float) $request->input('level')])->save();
        } elseif ($action === 'require_reverify') {
            $type = $request->input('verify_type');
            if ($type === 'phone') {
                $user->forceFill(['phone_verified' => false])->save();
            } elseif ($type === 'advanced') {
                $user->forceFill(['advanced_verified' => false])->save();
            }
        } elseif ($action === 'add_note') {
            \App\Models\AdminOperationLog::create([
                'admin_id'        => $request->user()->id,
                'action'          => 'add_note',
                'resource_type'   => 'member',
                'resource_id'     => $id,
                'description'     => $request->input('note'),
                'ip_address'      => $request->ip(),
                'user_agent'      => substr((string) $request->userAgent(), 0, 500),
                'request_summary' => ['note' => $request->input('note')],
                'created_at'      => now(),
            ]);
        }

        $messages = [
            'adjust_score' => '信用分數已調整。',
            'suspend' => '會員已停權。',
            'unsuspend' => '會員已恢復。',
            'verify_phone' => '已手動通過手機驗證。',
            'unverify_phone' => '已撤銷手機驗證。',
            'verify_advanced'   => '已手動通過進階驗證。',
            'unverify_advanced' => '已撤銷進階驗證。',
            'set_level'         => '會員等級已調整。',
            'require_reverify'  => '已要求重新驗證。',
            'add_note'          => '備註已新增。',
        ];

        return response()->json([
            'success' => true,
            'code' => 'MEMBER_ACTION_' . strtoupper($action),
            'message' => $messages[$action] ?? '操作完成。',
            'data' => [
                'phone_verified' => (bool) $user->phone_verified,
                'email_verified' => (bool) $user->email_verified,
                'membership_level' => (float) $user->membership_level,
            ],
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
     * DELETE /api/v1/admin/members/{id}
     */
    public function deleteMember(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => '會員不存在'], 404);
        }

        try { Log::info('[Admin] Member deleted', ['member_id' => $id, 'by' => $request->user()?->id]); } catch (\Throwable) {}
        $user->delete();

        return response()->json(['success' => true, 'message' => '會員已刪除']);
    }

    /**
     * POST /api/v1/admin/members/{id}/change-password
     */
    public function changeMemberPassword(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => '會員不存在'], 404);
        }

        $user->update(['password' => Hash::make($request->password)]);

        try { Log::info('[Admin] Member password changed', ['member_id' => $id, 'by' => $request->user()?->id]); } catch (\Throwable) {}

        return response()->json(['success' => true, 'message' => '密碼已變更']);
    }

    /**
     * POST /api/v1/admin/members/{id}/verify-email
     */
    public function forceVerifyEmail(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => '會員不存在'], 404);
        }

        $user->forceFill(['email_verified' => true])->save();

        try { Log::info('[Admin] Member email force-verified', ['member_id' => $id, 'by' => $request->user()?->id]); } catch (\Throwable) {}

        return response()->json(['success' => true, 'message' => 'Email 已標記為驗證完成']);
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
            'weight' => 'sometimes|nullable|integer|min:30|max:200',
            'location' => 'sometimes|nullable|string|max:50',
            'occupation' => 'sometimes|nullable|string|max:50',
            'education' => 'sometimes|nullable|string|max:50',
            'bio' => 'sometimes|nullable|string|max:500',
            // F27 profile fields
            'style'             => 'sometimes|nullable|string|in:fresh,sweet,sexy,intellectual,sporty',
            'dating_budget'     => 'sometimes|nullable|string|in:casual,moderate,generous,luxury,undisclosed',
            'dating_frequency'  => 'sometimes|nullable|string|in:occasional,weekly,flexible',
            'dating_type'       => 'sometimes|nullable|array',
            'dating_type.*'     => 'string|in:dining,travel,companion,mentorship,undisclosed',
            'relationship_goal' => 'sometimes|nullable|string|in:short_term,long_term,open,undisclosed',
            'smoking'           => 'sometimes|nullable|string|in:never,sometimes,often',
            'drinking'          => 'sometimes|nullable|string|in:never,social,often',
            'car_owner'         => 'sometimes|nullable|boolean',
            'availability'      => 'sometimes|nullable|array',
            'availability.*'    => 'string|in:weekday_day,weekday_night,weekend,flexible',
        ]);

        $user = User::findOrFail($id);

        $allowedFields = [
            'nickname', 'birth_date', 'avatar_url', 'gender', 'height', 'weight',
            'location', 'occupation', 'education', 'bio',
            'style', 'dating_budget', 'dating_frequency', 'dating_type', 'relationship_goal',
            'smoking', 'drinking', 'car_owner', 'availability',
        ];
        $updates = $request->only($allowedFields);

        // Filter to only actually changed fields
        $before = [];
        $after = [];
        $normalize = function ($v) {
            if ($v instanceof \Carbon\Carbon) return $v->format('Y-m-d');
            if (is_array($v)) return json_encode($v);
            if (is_bool($v)) return $v ? '1' : '0';
            return (string) ($v ?? '');
        };
        foreach ($updates as $key => $newValue) {
            $oldValue = $user->getAttribute($key);
            if ($normalize($oldValue) !== $normalize($newValue)) {
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
            'per_page' => 'sometimes|integer|min:1|max:1000',
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
            'per_page' => 'sometimes|integer|min:1|max:1000',
            'status' => 'sometimes|string|in:completed,pending,failed,refunded',
        ]);

        $query = Order::with(['user:id,nickname', 'plan:id,name']);
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        $perPage = (int) $request->input('per_page', 20);
        $payments = $query->orderByDesc('created_at')->paginate($perPage);

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
            // 誠信分數基準（DEV-008 §3）
            'credit_score_initial'           => '60',
            'credit_score_suspend_threshold' => '0',
            // 加分 key（DEV-008 §4）
            'credit_add_email_verify'        => '5',
            'credit_add_phone_verify'        => '5',
            'credit_add_adv_verify_male'     => '15',
            'credit_add_adv_verify_female'   => '15',
            'credit_add_date_gps'            => '5',
            'credit_add_date_no_gps'         => '2',
            // 扣分 key（DEV-008 §5，負值）
            'credit_sub_date_noshow'         => '-10',
            'credit_sub_report_user'         => '-10',
            'credit_sub_report_anon'         => '-5',
            'credit_sub_bad_content'         => '-5',
            'credit_sub_harassment'          => '-20',
            'credit_sub_additional_penalty'  => '-5',
            // 其他設定
            'max_photos_per_user'            => '6',
            'image_moderation_enabled'       => '0',
            'ecpay_is_sandbox'               => '1',
            'trial_plan_price'               => '49',
            'trial_plan_days'                => '3',
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
            // 誠信分數基準
            'credit_score_initial'           => 'sometimes|integer|min:0|max:100',
            'credit_score_suspend_threshold' => 'sometimes|integer|min:0|max:100',
            // 加分 key（DEV-008 §4）
            'credit_add_email_verify'        => 'sometimes|integer|min:0|max:50',
            'credit_add_phone_verify'        => 'sometimes|integer|min:0|max:50',
            'credit_add_adv_verify_male'     => 'sometimes|integer|min:0|max:50',
            'credit_add_adv_verify_female'   => 'sometimes|integer|min:0|max:50',
            'credit_add_date_gps'            => 'sometimes|integer|min:0|max:50',
            'credit_add_date_no_gps'         => 'sometimes|integer|min:0|max:50',
            // 扣分 key（DEV-008 §5，負值）
            'credit_sub_date_noshow'         => 'sometimes|integer|min:-100|max:0',
            'credit_sub_report_user'         => 'sometimes|integer|min:-100|max:0',
            'credit_sub_report_anon'         => 'sometimes|integer|min:-100|max:0',
            'credit_sub_bad_content'         => 'sometimes|integer|min:-100|max:0',
            'credit_sub_harassment'          => 'sometimes|integer|min:-100|max:0',
            'credit_sub_additional_penalty'  => 'sometimes|integer|min:-100|max:0',
            // 其他設定
            'max_photos_per_user'            => 'sometimes|integer|min:1|max:20',
            'image_moderation_enabled'       => 'sometimes|boolean',
            'trial_plan_price'               => 'sometimes|integer|min:0',
            'trial_plan_days'                => 'sometimes|integer|min:1|max:30',
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
}
