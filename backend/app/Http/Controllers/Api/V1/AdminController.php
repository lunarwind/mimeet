<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminOperationLog;
use App\Models\AdminUser;
use App\Models\Order;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BlacklistService;
use App\Services\GdprService;
use App\Services\UserActivityLogService;
use App\Support\Mask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(
        private readonly GdprService $gdprService,
        private readonly BlacklistService $blacklistService,
    ) {}

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
            'style' => 'sometimes|string|in:fresh,sweet,sexy,intellectual,sporty,elegant,korean,pure_student,petite_japanese,business_elite,british_gentleman,smart_casual,outdoor,boy_next_door,minimalist,japanese,warm_guy,preppy',
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
            'data' => $members->map(fn (User $u) => [
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
            'meta' => [
                'page' => $members->currentPage(), 'per_page' => $members->perPage(),
                'total' => $members->total(), 'last_page' => $members->lastPage(),
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
     * GET /api/v1/admin/members/{id}/subscriptions
     * 取得會員訂閱紀錄（規格：API-002 §13）
     */
    public function memberSubscriptions(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'code' => 404, 'message' => '找不到此會員'], 404);
        }

        $subscriptions = Subscription::where('user_id', $id)
            ->with(['plan:id,slug,name', 'order:id,amount,payment_method,ecpay_trade_no,order_number'])
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (Subscription $sub) => [
                'id'             => $sub->id,
                'plan'           => $sub->plan?->slug,
                'plan_name'      => $sub->plan?->name ?? '未知方案',
                'price_paid'     => $sub->order?->amount,
                'started_at'     => $sub->started_at?->toISOString(),
                'expires_at'     => $sub->expires_at?->toISOString(),
                'status'         => $sub->status,
                'payment_method' => $sub->order?->payment_method,
                'payment_no'     => $sub->order?->ecpay_trade_no ?? $sub->order?->order_number,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $subscriptions,
        ]);
    }

    /**
     * Perform action on a member (adjust_score, suspend, unsuspend).
     */
    public function memberAction(Request $request, int $id): JsonResponse
    {
        $rewardMax  = (int) \App\Models\SystemSetting::get('credit_admin_reward_max',  20);
        $penaltyMax = (int) \App\Models\SystemSetting::get('credit_admin_penalty_max', 20);

        $request->validate([
            'action'      => 'required|string|in:adjust_score,suspend,unsuspend,verify_phone,unverify_phone,verify_advanced,unverify_advanced,set_level,require_reverify,add_note',
            'score_delta' => ['required_if:action,adjust_score', 'integer', "min:-{$penaltyMax}", "max:{$rewardMax}", 'not_in:0'],
            'reason'      => 'sometimes|string|max:500',
            'level'       => 'required_if:action,set_level|numeric|in:0,1,1.5,2,3',
            'verify_type' => 'required_if:action,require_reverify|in:phone,advanced',
            'note'        => 'required_if:action,add_note|string|max:500',
        ], [
            'score_delta.min'    => "扣分不可超過 {$penaltyMax} 分",
            'score_delta.max'    => "獎勵不可超過 {$rewardMax} 分",
            'score_delta.not_in' => '調整值不可為 0',
        ]);

        $user = User::findOrFail($id);
        $action = $request->input('action');

        if ($action === 'adjust_score') {
            $scoreDelta = (int) $request->input('score_delta');
            $adjustType = $scoreDelta > 0 ? 'admin_reward' : 'admin_penalty';
            \App\Services\CreditScoreService::adjust(
                $user, $scoreDelta,
                $adjustType, $request->input('reason', '管理員手動調整'), $request->user()?->id
            );
        } elseif ($action === 'suspend') {
            $user->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();
            $user->tokens()->delete();
            UserActivityLogService::log($user->id, 'account_suspended_by_admin', [
                'admin_id' => auth()->id(),
                'tokens_revoked' => true,
            ], $request);
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
            $updates = [];
            if ((float) $user->membership_level < $target) {
                $updates['membership_level'] = $target;
            }
            // 男性：同步寫 credit_card_verified_at（Cleanup PR-C 決議）。
            // admin 手動授權等同於系統認可信用卡可用，與正常 NT$100 驗證一致。
            if ($user->gender === 'male' && $user->credit_card_verified_at === null) {
                $updates['credit_card_verified_at'] = now();
            }
            if (!empty($updates)) {
                $user->forceFill($updates)->save();
            }
        } elseif ($action === 'unverify_advanced') {
            $current = (float) $user->membership_level;
            $updates = [];
            if ($current === 1.5 || $current === 2.0) {
                $updates['membership_level'] = 1;
            }
            // 男性：同步清 credit_card_verified_at（弱化版設計，PR-C）。
            // 注意：base level 推導擴充後，已有 paid payment 的 user 仍會回 Lv2，
            // admin UI 已加 confirm modal 提示此行為。
            if ($user->gender === 'male' && $user->credit_card_verified_at !== null) {
                $updates['credit_card_verified_at'] = null;
            }
            if (!empty($updates)) {
                $user->forceFill($updates)->save();
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
                \App\Services\CreditScoreService::adjust($user, $delta, $delta > 0 ? 'admin_reward' : 'admin_penalty', $reason, $adminId);
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
                if ($newStatus === 'suspended') {
                    $user->tokens()->delete();
                    UserActivityLogService::log($user->id, 'account_suspended_by_admin', [
                        'admin_id' => auth()->id(),
                        'tokens_revoked' => true,
                        'via' => 'patch_members',
                    ], $request);
                }
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
     *
     * v3.6: 改走 GdprService::anonymizeUser 立即匿名化（不可逆），
     * 釋放 email / phone_hash unique 索引讓對方可重新註冊。
     * 整個 handler 包在 DB::transaction 內讓 lockForUpdate 真的鎖到 row，
     * 防併發 admin 同時刪同一個 user。
     */
    public function deleteMember(Request $request, int $id): JsonResponse
    {
        // PR-2: 接受 optional blacklist 欄位(向後相容,缺欄位等同 false)
        $request->validate([
            'blacklist_email' => 'sometimes|boolean',
            'blacklist_mobile' => 'sometimes|boolean',
            'blacklist_reason' => 'sometimes|nullable|string|max:500',
        ]);

        // PR-2 D14:在 deleteMember 既有 log 流程內補欄位,不另開新 log call。
        // skip middleware 自動 log 因為下方手動 create 已涵蓋(避免兩筆)。
        $request->attributes->set('skip_admin_log', true);

        $blacklistEmail = (bool) $request->input('blacklist_email', false);
        $blacklistMobile = (bool) $request->input('blacklist_mobile', false);
        $blacklistReason = $request->input('blacklist_reason');

        return DB::transaction(function () use ($request, $id, $blacklistEmail, $blacklistMobile, $blacklistReason) {
            $user = User::lockForUpdate()->find($id);
            if (!$user) {
                return response()->json(['success' => false, 'message' => '會員不存在'], 404);
            }

            // PR-1 v3.6:在 anonymize 前抓原值用於 audit log 與 PR-2 blacklist 寫入
            $originalEmail = $user->email;
            $originalPhone = $user->phone; // encrypted cast → plaintext
            $originalEmailMasked = Mask::email($originalEmail);
            $originalPhoneMasked = Mask::phone($originalPhone);

            // PR-2:在 anonymize 之前寫 blacklist(原值還在)
            $blacklistedEmail = false;
            $blacklistedMobile = false;
            if ($blacklistEmail && $originalEmail) {
                try {
                    $this->blacklistService->add([
                        'type' => 'email',
                        'value' => $originalEmail,
                        'reason' => $blacklistReason,
                        'source' => 'admin_delete',
                        'source_user_id' => $user->id,
                        'created_by' => $request->user()->id,
                    ]);
                    $blacklistedEmail = true;
                } catch (\DomainException $e) {
                    // ALREADY_BLACKLISTED 視為已達目的,不阻塞刪除
                    if ($e->getMessage() !== 'ALREADY_BLACKLISTED') {
                        throw $e;
                    }
                    $blacklistedEmail = true; // 已存在等同達成意圖
                }
            }
            if ($blacklistMobile && $originalPhone) {
                try {
                    $this->blacklistService->add([
                        'type' => 'mobile',
                        'value' => $originalPhone,
                        'reason' => $blacklistReason,
                        'source' => 'admin_delete',
                        'source_user_id' => $user->id,
                        'created_by' => $request->user()->id,
                    ]);
                    $blacklistedMobile = true;
                } catch (\DomainException $e) {
                    if (!in_array($e->getMessage(), ['ALREADY_BLACKLISTED', 'INVALID_PHONE'], true)) {
                        throw $e;
                    }
                    $blacklistedMobile = $e->getMessage() === 'ALREADY_BLACKLISTED';
                }
            }

            $this->gdprService->anonymizeUser($user);

            AdminOperationLog::create([
                'admin_id' => $request->user()?->id,
                'action' => 'delete_member',
                'resource_type' => 'member',
                'resource_id' => $id,
                'description' => "DELETE admin/members/{$id} (anonymized)",
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'request_summary' => [
                    'original_email_masked' => $originalEmailMasked,
                    'original_phone_masked' => $originalPhoneMasked,
                    'anonymized' => true,
                    // PR-2 新增
                    'blacklisted_email' => $blacklistedEmail,
                    'blacklisted_mobile' => $blacklistedMobile,
                    'blacklist_reason' => $blacklistReason,
                ],
                'created_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => '會員已刪除']);
        });
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

        // 先 find user 再 validate,以便依「最終 gender」(本次 request 改 gender 或 user 既有 gender)
        // 決定 style 可用的 enum 範圍。
        $user = User::findOrFail($id);
        $targetGender = $request->input('gender', $user->gender);
        $styleEnum = $targetGender === 'male'
            ? ['business_elite', 'british_gentleman', 'smart_casual', 'outdoor', 'boy_next_door', 'minimalist', 'japanese', 'warm_guy', 'preppy']
            : ['fresh', 'sweet', 'sexy', 'intellectual', 'sporty', 'elegant', 'korean', 'pure_student', 'petite_japanese'];

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
            'style'             => ['sometimes', 'nullable', 'string', Rule::in($styleEnum)],
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
            'data' => $tickets->map(fn (Report $r) => [
                'id' => $r->id, 'uuid' => $r->uuid, 'type' => $r->type, 'status' => $r->status,
                'description' => $r->description,
                'reporter' => $r->reporter ? ['id' => $r->reporter->id, 'nickname' => $r->reporter->nickname] : null,
                'reported_user' => $r->reportedUser ? ['id' => $r->reportedUser->id, 'nickname' => $r->reportedUser->nickname] : null,
                'created_at' => $r->created_at?->toISOString(),
            ]),
            'meta' => [
                'page' => $tickets->currentPage(), 'per_page' => $tickets->perPage(),
                'total' => $tickets->total(), 'last_page' => $tickets->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/tickets/{id} — single ticket detail with appeal_info (when type='appeal').
     *
     * D.3 解耦版：純讀取，不變更任何資料。
     * appeal_info 區塊僅在 type='appeal' 時返回，含：
     *   - credit_score_history：reporter 最近 20 筆誠信分數變動
     *   - received_reports：reporter 被檢舉的最近 20 筆（排除自身這筆）
     *   - images：申訴附圖 URL 陣列
     */
    public function getTicketDetail(Request $request, int $id): JsonResponse
    {
        $report = Report::with(['reporter:id,email,nickname,status,credit_score', 'reportedUser:id,email,nickname,status'])
            ->findOrFail($id);

        $data = [
            'id' => $report->id,
            'uuid' => $report->uuid ?? null,
            'type' => $report->type,
            'status' => $report->status,
            'description' => $report->description,
            'admin_reply' => $report->resolution_note,
            'reporter' => $report->reporter ? [
                'id' => $report->reporter->id,
                'email' => $report->reporter->email,
                'nickname' => $report->reporter->nickname,
                'status' => $report->reporter->status,
                'credit_score' => $report->reporter->credit_score,
            ] : null,
            'reported_user' => $report->reportedUser ? [
                'id' => $report->reportedUser->id,
                'email' => $report->reportedUser->email,
                'nickname' => $report->reportedUser->nickname,
                'status' => $report->reportedUser->status,
            ] : null,
            'created_at' => $report->created_at?->toISOString(),
            'resolved_at' => $report->resolved_at?->toISOString(),
        ];

        if ($report->type === 'appeal' && $report->reporter) {
            $reporterId = $report->reporter->id;

            $creditHistory = \App\Models\CreditScoreHistory::where('user_id', $reporterId)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'delta', 'score_before', 'score_after', 'type', 'reason', 'created_at'])
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'delta' => $h->delta,
                    'score_before' => $h->score_before,
                    'score_after' => $h->score_after,
                    'type' => $h->type,
                    'reason' => $h->reason,
                    'created_at' => $h->created_at?->toISOString(),
                ]);

            $receivedReports = Report::where('reported_user_id', $reporterId)
                ->where('id', '!=', $report->id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'type', 'status', 'description', 'created_at'])
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'type' => $r->type,
                    'status' => $r->status,
                    'description' => mb_substr($r->description ?? '', 0, 100),
                    'created_at' => $r->created_at?->toISOString(),
                ]);

            $images = $report->images()->pluck('image_url')->all();

            $data['appeal_info'] = [
                'credit_score_history' => $creditHistory,
                'received_reports' => $receivedReports,
                'images' => $images,
            ];
        }

        return response()->json([
            'success' => true,
            'code' => 'TICKET_DETAIL',
            'message' => 'OK',
            'data' => $data,
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
            'page'        => 'sometimes|integer|min:1',
            'per_page'    => 'sometimes|integer|min:1|max:1000',
            'status'      => 'sometimes|string',
            'type'        => 'sometimes|string|in:verification,subscription,points',
            'environment' => 'sometimes|string|in:sandbox,production',
            'search'      => 'sometimes|string|max:100',
            'date_from'   => 'sometimes|date',
            'date_to'     => 'sometimes|date',
        ]);

        $query = \App\Models\Payment::with('user:id,nickname,email')
            ->when($request->filled('status'),      fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'),        fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('environment'), fn ($q) => $q->where('environment', $request->input('environment')))
            ->when($request->filled('date_from'),   fn ($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'),     fn ($q) => $q->whereDate('created_at', '<=', $request->input('date_to')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->input('search');
                $q->where(fn ($inner) => $inner
                    ->where('order_no', 'like', "%{$s}%")
                    ->orWhere('gateway_trade_no', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$s}%"))
                );
            })
            ->orderByDesc('created_at');

        $perPage  = (int) $request->input('per_page', 20);
        $paginated = $query->paginate($perPage);

        $statsBase = \App\Models\Payment::query();
        if ($request->filled('environment')) {
            $statsBase->where('environment', $request->input('environment'));
        }
        if ($request->filled('type')) {
            $statsBase->where('type', $request->input('type'));
        }
        $totalRevenue = (clone $statsBase)->where('status', 'paid')->sum('amount');
        $totalPaid    = (clone $statsBase)->where('status', 'paid')->count();
        $totalOrders  = (clone $statsBase)->count();

        return response()->json([
            'success' => true, 'code' => 'PAYMENTS_LIST', 'message' => 'OK',
            'data' => $paginated->map(fn (\App\Models\Payment $p) => [
                'id'                   => $p->id,
                'order_no'             => $p->order_no,
                'type'                 => $p->type,
                'type_label'           => match ($p->type) {
                    'subscription' => '訂閱方案',
                    'points'       => '點數購買',
                    'verification' => '信用卡驗證',
                    default        => $p->type,
                },
                'item_name'            => $p->item_name,
                'environment'          => $p->environment,
                'user'                 => $p->user ? ['id' => $p->user->id, 'nickname' => $p->user->nickname, 'email' => $p->user->email] : null,
                'amount'               => $p->amount,
                'currency'             => $p->currency,
                'status'               => $p->status,
                'gateway'              => $p->gateway,
                'gateway_trade_no'     => $p->gateway_trade_no,
                'payment_method'       => $p->payment_method,
                'card_country'         => $p->card_country,
                'paid_at'              => $p->paid_at?->toISOString(),
                'refunded_at'          => $p->refunded_at?->toISOString(),
                'refund_trade_no'      => $p->refund_trade_no,
                'invoice_applicable'   => true,   // 所有付款類型都嘗試開發票（含驗證）
                'invoice_no'           => $p->invoice_no,
                'invoice_issued_at'    => $p->invoice_issued_at?->toISOString(),
                'invoice_status'       => $p->invoice_status ?? 'pending',
                'failure_reason'       => $p->failure_reason,
                'created_at'           => $p->created_at?->toISOString(),
            ]),
            'meta' => [
                'page'          => $paginated->currentPage(),
                'per_page'      => $paginated->perPage(),
                'total'         => $paginated->total(),
                'last_page'     => $paginated->lastPage(),
                'total_revenue' => (int) $totalRevenue,
                'total_paid'    => $totalPaid,
                'total_orders'  => $totalOrders,
            ],
        ]);
    }

    /**
     * POST /admin/payments/{id}/refund
     * 手動觸發退款（super_admin only，Step 9）
     */
    public function refundPayment(Request $request, int $id): JsonResponse
    {
        $payment = \App\Models\Payment::findOrFail($id);

        if ($payment->status !== 'paid' || $payment->refunded_at !== null) {
            return response()->json(['success' => false, 'message' => '此筆付款無法退款（狀態不符或已退款）'], 422);
        }

        \App\Jobs\RefundPaymentJob::dispatch($payment->id);

        return response()->json(['success' => true, 'message' => '退款已排入 Queue，請稍後查看狀態']);
    }

    /**
     * Get system settings.
     */
    public function getSettings(): JsonResponse
    {
        $defaults = [
            // 誠信分數基準（DEV-008 §3）
            'credit_score_initial'           => '60',
            'credit_score_unblock_threshold' => '30',
            // 加分 key（DEV-008 §3.2）
            'credit_add_email_verify'        => '5',
            'credit_add_phone_verify'        => '5',
            'credit_add_adv_verify_male'     => '15',
            'credit_add_adv_verify_female'   => '15',
            'credit_add_date_gps'            => '5',
            'credit_add_date_no_gps'         => '2',
            'credit_add_report_refund'       => '10',
            // 扣分 key（DEV-008 §3.3，正值！Service 內轉負）
            'credit_sub_date_noshow'         => '10',
            'credit_sub_report_user'         => '10',
            'credit_sub_report_anon'         => '5',
            'credit_sub_report_penalty'      => '5',
            'credit_sub_bad_content'         => '5',
            'credit_sub_harassment'          => '20',
            // 管理員裁量範圍（對稱 ±20）
            'credit_admin_reward_min'        => '1',
            'credit_admin_reward_max'        => '20',
            'credit_admin_penalty_min'       => '1',
            'credit_admin_penalty_max'       => '20',
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
            'credit_score_unblock_threshold' => 'sometimes|integer|min:0|max:100',
            // 加分 key（DEV-008 §3.2）
            'credit_add_email_verify'        => 'sometimes|integer|min:0|max:50',
            'credit_add_phone_verify'        => 'sometimes|integer|min:0|max:50',
            'credit_add_adv_verify_male'     => 'sometimes|integer|min:0|max:50',
            'credit_add_adv_verify_female'   => 'sometimes|integer|min:0|max:50',
            'credit_add_date_gps'            => 'sometimes|integer|min:0|max:50',
            'credit_add_date_no_gps'         => 'sometimes|integer|min:0|max:50',
            'credit_add_report_refund'       => 'sometimes|integer|min:0|max:50',
            // 扣分 key（DEV-008 §3.3，正值！UI 輸正數，Service 內轉負）
            'credit_sub_date_noshow'         => 'sometimes|integer|min:0|max:100',
            'credit_sub_report_user'         => 'sometimes|integer|min:0|max:100',
            'credit_sub_report_anon'         => 'sometimes|integer|min:0|max:100',
            'credit_sub_report_penalty'      => 'sometimes|integer|min:0|max:100',
            'credit_sub_bad_content'         => 'sometimes|integer|min:0|max:100',
            'credit_sub_harassment'          => 'sometimes|integer|min:0|max:100',
            // 管理員裁量範圍
            'credit_admin_reward_min'        => 'sometimes|integer|min:1|max:50',
            'credit_admin_reward_max'        => 'sometimes|integer|min:1|max:50',
            'credit_admin_penalty_min'       => 'sometimes|integer|min:1|max:50',
            'credit_admin_penalty_max'       => 'sometimes|integer|min:1|max:50',
            // 其他設定
            'max_photos_per_user'            => 'sometimes|integer|min:1|max:20',
            'image_moderation_enabled'       => 'sometimes|boolean',
            'trial_plan_price'               => 'sometimes|integer|min:0',
            'trial_plan_days'                => 'sometimes|integer|min:1|max:30',
        ]);

        $admin = $request->user();
        foreach ($request->except(['_token']) as $key => $value) {
            SystemSetting::set($key, $value, $admin?->id);
            // 雙層快取清除：SystemSetting 已清 "sys:{$key}"，
            // 這裡額外清 CreditScoreService::getConfig 的 "setting:{$key}" 層（TTL 300s）
            \Illuminate\Support\Facades\Cache::forget("setting:{$key}");
        }

        return response()->json([
            'success' => true,
            'code' => 'SETTINGS_UPDATED',
            'message' => '系統設定已更新。',
        ]);
    }

    // ─── 誠信分數配分管理 API ────────────────────────────────────────

    /** 規格預設值（雙保險 default，與 seeder + DEV-008 三方一致）*/
    private const CREDIT_SCORE_SPEC_DEFAULTS = [
        'credit_score_initial'           => 60,
        'credit_score_unblock_threshold' => 30,
        'credit_add_email_verify'        => 5,
        'credit_add_phone_verify'        => 5,
        'credit_add_adv_verify_male'     => 15,
        'credit_add_adv_verify_female'   => 15,
        'credit_add_date_gps'            => 5,
        'credit_add_date_no_gps'         => 2,
        'credit_add_report_refund'       => 10,
        'credit_sub_date_noshow'         => 10,
        'credit_sub_report_user'         => 10,
        'credit_sub_report_anon'         => 5,
        'credit_sub_report_penalty'      => 5,
        'credit_sub_bad_content'         => 5,
        'credit_sub_harassment'          => 20,
        'credit_admin_reward_min'        => 1,
        'credit_admin_reward_max'        => 20,
        'credit_admin_penalty_min'       => 1,
        'credit_admin_penalty_max'       => 20,
    ];

    /**
     * GET /api/v1/admin/settings/credit-score
     */
    public function getCreditScoreSettings(): JsonResponse
    {
        $result = [];
        foreach (self::CREDIT_SCORE_SPEC_DEFAULTS as $key => $specDefault) {
            $dbRow = SystemSetting::where('key_name', $key)->first();
            $result[] = [
                'key'         => $key,
                'value'       => $dbRow ? (int) $dbRow->value : $specDefault,
                'spec_default'=> $specDefault,
                'description' => $dbRow?->description ?? '',
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * PUT /api/v1/admin/settings/credit-score
     * Body: { "settings": [{"key": "credit_add_email_verify", "value": 6}, ...] }
     */
    public function updateCreditScoreSettings(Request $request): JsonResponse
    {
        $request->validate([
            'settings'              => 'required|array',
            'settings.*.key'        => 'required|string',
            'settings.*.value'      => 'required|integer|min:0|max:100',
        ]);

        $admin = $request->user();
        $allowed = array_keys(self::CREDIT_SCORE_SPEC_DEFAULTS);

        foreach ($request->input('settings') as $item) {
            $key   = $item['key'];
            $value = (int) $item['value'];

            if (!in_array($key, $allowed)) {
                continue; // 跳過非白名單 key
            }

            $old = SystemSetting::where('key_name', $key)->value('value') ?? 'null';
            SystemSetting::updateOrCreate(
                ['key_name' => $key],
                ['value' => (string) $value, 'updated_by' => $admin?->id]
            );
            // 雙層快取清除
            \Illuminate\Support\Facades\Cache::forget("sys:{$key}");
            \Illuminate\Support\Facades\Cache::forget("setting:{$key}");

            // Audit log
            \App\Models\AdminOperationLog::create([
                'admin_id'        => $admin?->id,
                'action'          => 'update_credit_setting',
                'resource_type'   => 'system_setting',
                'resource_id'     => 0,
                'description'     => "誠信分數配分更新：{$key} {$old} → {$value}",
                'ip_address'      => $request->ip(),
                'user_agent'      => substr((string) $request->userAgent(), 0, 500),
                'request_summary' => ['key' => $key, 'old' => $old, 'new' => $value],
                'created_at'      => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '誠信分數配分已更新，下一筆觸發即生效。',
        ]);
    }

    /**
     * POST /api/v1/admin/settings/credit-score/reset
     * 還原全部 key 為規格預設值。
     */
    public function resetCreditScoreSettings(Request $request): JsonResponse
    {
        $admin = $request->user();

        foreach (self::CREDIT_SCORE_SPEC_DEFAULTS as $key => $specDefault) {
            SystemSetting::updateOrCreate(
                ['key_name' => $key],
                ['value' => (string) $specDefault, 'updated_by' => $admin?->id]
            );
            \Illuminate\Support\Facades\Cache::forget("sys:{$key}");
            \Illuminate\Support\Facades\Cache::forget("setting:{$key}");
        }

        \App\Models\AdminOperationLog::create([
            'admin_id'        => $admin?->id,
            'action'          => 'reset_credit_settings',
            'resource_type'   => 'system_setting',
            'resource_id'     => 0,
            'description'     => '誠信分數配分已全部還原為規格預設值',
            'ip_address'      => $request->ip(),
            'user_agent'      => substr((string) $request->userAgent(), 0, 500),
            'request_summary' => [],
            'created_at'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '所有誠信分數配分已還原為規格預設值。',
        ]);
    }
}
