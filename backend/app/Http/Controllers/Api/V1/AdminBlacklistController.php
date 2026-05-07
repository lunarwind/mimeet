<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminOperationLog;
use App\Models\RegistrationBlacklist;
use App\Services\BlacklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminBlacklistController extends Controller
{
    public function __construct(
        private readonly BlacklistService $blacklistService,
    ) {}

    /**
     * GET /api/v1/admin/blacklists
     * 對齊 DEV-004 §6.1 列表標準格式
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'sometimes|in:email,mobile',
            'status' => 'sometimes|in:active,inactive,expired,all',
            'source' => 'sometimes|in:manual,admin_delete',
            'q' => 'sometimes|string|max:255',
            'created_from' => 'sometimes|date',
            'created_to' => 'sometimes|date',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = RegistrationBlacklist::query()
            ->with(['creator:id,name', 'deactivator:id,name']);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }
        $status = $request->input('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true)
                  ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'expired') {
            $query->where('is_active', true)
                  ->whereNotNull('expires_at')
                  ->where('expires_at', '<=', now());
        }
        if ($request->filled('q')) {
            $query->where('value_masked', 'like', $request->input('q') . '%');
        }
        if ($request->filled('created_from')) {
            $query->where('created_at', '>=', $request->input('created_from'));
        }
        if ($request->filled('created_to')) {
            $query->where('created_at', '<=', $request->input('created_to'));
        }

        $perPage = (int) $request->input('per_page', 20);
        $items = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $items->map(fn (RegistrationBlacklist $b) => $this->serialize($b)),
            'meta' => [
                'page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/blacklists/{id}
     */
    public function show(int $id): JsonResponse
    {
        $blacklist = RegistrationBlacklist::with(['creator:id,name', 'deactivator:id,name', 'sourceUser:id,nickname'])->find($id);
        if (!$blacklist) {
            return response()->json(['data' => null, 'error' => ['code' => 'NOT_FOUND', 'message' => '找不到此名單']], 404);
        }

        return response()->json(['data' => $this->serialize($blacklist, withSourceUser: true)]);
    }

    /**
     * POST /api/v1/admin/blacklists
     */
    public function store(Request $request): JsonResponse
    {
        // PR-2 D14-a:跳過 middleware 自動 log,自己寫結構化 log
        $request->attributes->set('skip_admin_log', true);

        $request->validate([
            'type' => 'required|in:email,mobile',
            'value' => 'required|string|max:255',
            'reason' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ]);

        try {
            $blacklist = $this->blacklistService->add([
                'type' => $request->input('type'),
                'value' => $request->input('value'),
                'reason' => $request->input('reason'),
                'expires_at' => $request->input('expires_at'),
                'source' => 'manual',
                'created_by' => $request->user()->id,
            ]);
        } catch (\DomainException $e) {
            return $this->domainError($e->getMessage());
        }

        AdminOperationLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'blacklist.create',
            'resource_type' => 'blacklist',
            'resource_id' => $blacklist->id,
            'description' => "新增註冊禁止名單 (type: {$blacklist->type})",
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'request_summary' => [
                'type' => $blacklist->type,
                'value_masked' => $blacklist->value_masked,
                'reason' => $blacklist->reason,
                'source' => 'manual',
                'expires_at' => $blacklist->expires_at?->toISOString(),
            ],
            'created_at' => now(),
        ]);

        return response()->json(['data' => $this->serialize($blacklist->fresh(['creator']))], 201);
    }

    /**
     * PATCH /api/v1/admin/blacklists/{id}/deactivate
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        $request->attributes->set('skip_admin_log', true);

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $blacklist = RegistrationBlacklist::find($id);
        if (!$blacklist) {
            return response()->json(['data' => null, 'error' => ['code' => 'NOT_FOUND', 'message' => '找不到此名單']], 404);
        }

        $previousState = [
            'value_masked' => $blacklist->value_masked,
            'type' => $blacklist->type,
            'is_active' => $blacklist->is_active,
        ];

        try {
            $blacklist = $this->blacklistService->deactivate($blacklist, $request->user()->id, $request->input('reason'));
        } catch (\DomainException $e) {
            return $this->domainError($e->getMessage());
        }

        AdminOperationLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'blacklist.deactivate',
            'resource_type' => 'blacklist',
            'resource_id' => $id,
            'description' => "解除註冊禁止名單 #{$id}",
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'request_summary' => [
                'deactivation_reason' => $request->input('reason'),
                'previous_state' => $previousState,
            ],
            'created_at' => now(),
        ]);

        return response()->json(['data' => $this->serialize($blacklist->fresh(['creator', 'deactivator']))]);
    }

    private function serialize(RegistrationBlacklist $b, bool $withSourceUser = false): array
    {
        $now = now();
        $statusLabel = !$b->is_active
            ? 'inactive'
            : (($b->expires_at && $b->expires_at->lessThanOrEqualTo($now)) ? 'expired' : 'active');

        $data = [
            'id' => $b->id,
            'type' => $b->type,
            'value_masked' => $b->value_masked,
            'reason' => $b->reason,
            'source' => $b->source,
            'source_user_id' => $b->source_user_id,
            'is_active' => $b->is_active,
            'status' => $statusLabel,
            'expires_at' => $b->expires_at?->toISOString(),
            'created_at' => $b->created_at?->toISOString(),
            'created_by' => $b->created_by,
            'created_by_name' => $b->creator?->name,
            'deactivated_at' => $b->deactivated_at?->toISOString(),
            'deactivated_by' => $b->deactivated_by,
            'deactivated_by_name' => $b->deactivator?->name,
            'deactivation_reason' => $b->deactivation_reason,
        ];

        if ($withSourceUser && $b->source_user_id) {
            $data['source_user'] = $b->sourceUser ? [
                'id' => $b->sourceUser->id,
                'nickname' => $b->sourceUser->nickname,
            ] : null;
        }

        return $data;
    }

    private function domainError(string $code): JsonResponse
    {
        $map = [
            'ALREADY_BLACKLISTED' => [409, '此 email/手機已在禁止名單中'],
            'ALREADY_DEACTIVATED' => [409, '此名單已是解除狀態'],
            'INVALID_PHONE' => [422, '手機號碼格式無效'],
            'INVALID_TYPE' => [422, '無效的 type 值'],
        ];
        [$status, $message] = $map[$code] ?? [400, $code];
        return response()->json([
            'data' => null,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
