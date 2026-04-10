<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminOperationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AdminOperationLog::orderBy('created_at', 'desc');

        if ($request->has('action_type') && $request->action_type !== 'all') {
            $query->where('action_type', $request->action_type);
        }
        if ($request->has('admin_id')) {
            $query->where('admin_id', $request->admin_id);
        }
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }
        if ($request->has('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $logs = $query->paginate($request->input('per_page', 20));

        return response()->json(['success' => true, 'data' => $logs]);
    }
}
