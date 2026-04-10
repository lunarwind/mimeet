<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminOperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLogController extends Controller
{
    /**
     * GET /api/v1/admin/logs
     */
    public function index(Request $request): JsonResponse
    {
        $query = AdminOperationLog::orderBy('created_at', 'desc');

        if ($request->filled('action_type')) {
            $query->where('action', $request->input('action_type'));
        }
        if ($request->filled('admin_id')) {
            $query->where('admin_id', $request->input('admin_id'));
        }
        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->input('resource_type'));
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        $perPage = $request->input('per_page', 50);
        $paginated = $query->paginate($perPage);

        // IP visibility: only super_admin with show_ip=true
        $showIp = $request->boolean('show_ip')
            && ($request->user()->role ?? '') === 'super_admin';

        $logs = collect($paginated->items())->map(function ($log) use ($showIp) {
            $data = $log->toArray();
            if (!$showIp) {
                $data['ip_address'] = null;
            }

            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => ['logs' => $logs],
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'total_pages' => $paginated->lastPage(),
            ],
        ]);
    }
}
