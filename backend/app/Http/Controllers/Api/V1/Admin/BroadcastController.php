<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBroadcastJob;
use App\Models\BroadcastCampaign;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BroadcastController extends Controller
{
    /**
     * GET /api/v1/admin/broadcasts
     */
    public function index(Request $request): JsonResponse
    {
        $query = BroadcastCampaign::orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginated = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => ['broadcasts' => $paginated->items()],
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'total_pages' => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/broadcasts
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'delivery_mode' => 'required|in:notification,dm,both',
            'filters' => 'nullable|array',
            'filters.gender' => 'nullable|in:all,male,female',
            'filters.level_min' => 'nullable|numeric|min:0|max:3',
            'filters.level_max' => 'nullable|numeric|min:0|max:3',
            'filters.credit_min' => 'nullable|integer|min:0|max:100',
            'filters.credit_max' => 'nullable|integer|min:0|max:100',
        ]);

        $targetCount = $this->calculateTargetCount($request->input('filters', []));

        $campaign = BroadcastCampaign::create([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'filters' => $request->input('filters'),
            'delivery_mode' => $request->input('delivery_mode'),
            'target_count' => $targetCount,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['broadcast' => $campaign],
        ], 201);
    }

    /**
     * GET /api/v1/admin/broadcasts/{id}
     */
    public function show(int $id): JsonResponse
    {
        $campaign = BroadcastCampaign::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => ['broadcast' => $campaign],
        ]);
    }

    /**
     * POST /api/v1/admin/broadcasts/{id}/send
     */
    public function send(int $id): JsonResponse
    {
        $campaign = BroadcastCampaign::findOrFail($id);

        if ($campaign->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只有草稿狀態的廣播可以發送',
            ], 422);
        }

        $campaign->update(['status' => 'sending']);

        SendBroadcastJob::dispatch($campaign);

        return response()->json([
            'success' => true,
            'message' => '廣播已開始發送',
            'data' => ['broadcast' => $campaign->fresh()],
        ]);
    }

    private function calculateTargetCount(array $filters): int
    {
        $query = User::where('status', 'active');

        if (!empty($filters['gender']) && $filters['gender'] !== 'all') {
            $query->where('gender', $filters['gender']);
        }
        if (isset($filters['level_min'])) {
            $query->where('membership_level', '>=', $filters['level_min']);
        }
        if (isset($filters['level_max'])) {
            $query->where('membership_level', '<=', $filters['level_max']);
        }
        if (isset($filters['credit_min'])) {
            $query->where('credit_score', '>=', $filters['credit_min']);
        }
        if (isset($filters['credit_max'])) {
            $query->where('credit_score', '<=', $filters['credit_max']);
        }

        return $query->count();
    }
}
