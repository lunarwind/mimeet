<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
     * Admin login.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false, 'code' => 401, 'message' => 'Email 或密碼不正確',
            ], 401);
        }

        $token = $user->createToken('admin-login')->plainTextToken;

        return response()->json([
            'success' => true,
            'code' => 'ADMIN_LOGIN_SUCCESS',
            'message' => '管理員登入成功。',
            'data' => [
                'admin' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->nickname,
                    'role' => 'super_admin',
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
            'score_delta' => 'required_if:action,adjust_score|integer',
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
            $user->update(['status' => 'suspended', 'suspended_at' => now()]);
        } elseif ($action === 'unsuspend') {
            $user->update(['status' => 'active']);
        }

        $messages = ['adjust_score' => '信用分數已調整。', 'suspend' => '會員已停權。', 'unsuspend' => '會員已恢復。'];

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
                    'status' => $o->status, 'paid_at' => $o->paid_at?->toISOString(),
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
