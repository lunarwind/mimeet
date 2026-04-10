<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserActivityLogController extends Controller
{
    /**
     * GET /api/v1/admin/user-activity-logs
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'sometimes|integer',
            'action' => 'sometimes|string|max:100',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = UserActivityLog::with('user:id,nickname,email')
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        $perPage = (int) $request->input('per_page', 20);
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $logs->map(fn (UserActivityLog $log) => [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'user_nickname' => $log->user?->nickname ?? '—',
                    'user_email' => $log->user?->email ?? '—',
                    'action' => $log->action,
                    'metadata' => $log->metadata,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at?->toISOString(),
                ]),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
            ],
        ]);
    }
}
