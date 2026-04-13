<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BroadcastCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BroadcastController extends Controller
{
    public function index(): JsonResponse
    {
        $campaigns = BroadcastCampaign::orderBy('created_at', 'desc')->paginate(20);
        return response()->json(['success' => true, 'data' => $campaigns]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'content' => 'required|string|max:1000',
            'delivery_mode' => 'required|in:notification,dm,both',
            'target_gender' => 'sometimes|in:male,female,all',
            'target_level' => 'sometimes|string',
            'target_credit_min' => 'sometimes|integer|min:0',
            'target_credit_max' => 'sometimes|integer|max:100',
        ]);

        $campaign = BroadcastCampaign::create(array_merge(
            $request->only(['title', 'content', 'delivery_mode', 'target_gender', 'target_level', 'target_credit_min', 'target_credit_max']),
            ['created_by' => $request->user()?->id ?? 0, 'status' => 'draft']
        ));

        return response()->json(['success' => true, 'message' => '廣播已建立', 'data' => ['campaign' => $campaign]], 201);
    }

    public function show(int $id): JsonResponse
    {
        $campaign = BroadcastCampaign::findOrFail($id);
        return response()->json(['success' => true, 'data' => ['campaign' => $campaign]]);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $campaign = BroadcastCampaign::findOrFail($id);
        if ($campaign->status !== 'draft') {
            return response()->json(['success' => false, 'message' => '只有草稿狀態的廣播可以發送'], 422);
        }

        $campaign->update(['status' => 'completed', 'completed_at' => now(), 'sent_count' => $campaign->target_count ?: 0]);

        return response()->json(['success' => true, 'message' => '廣播已開始發送']);
    }
}
