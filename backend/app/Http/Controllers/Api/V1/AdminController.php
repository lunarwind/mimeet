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

        // Issue token with 'admin' ability to distinguish from user tokens
        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        $admin->update(['last_login_at' => now()]);

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
     * Get paginated member list.
     */
    public function members(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:1000',
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
                    'gender' => $u->gender, 'avatar_url' => $u->avatar_url,
                    'membership_level' => $u->membership_level,
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
}
