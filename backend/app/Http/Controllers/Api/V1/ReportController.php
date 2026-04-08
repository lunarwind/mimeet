<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $type = (int) $request->input('type', 1);
        $deducted = $type === 1 ? 10 : ($type === 3 ? 5 : 0);

        return response()->json([
            'success' => true,
            'code' => 201,
            'message' => '舉報提交成功',
            'data' => [
                'report' => [
                    'id' => rand(100, 999),
                    'report_number' => 'R' . date('Ymd') . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT),
                    'type' => $type,
                    'status' => 1,
                    'created_at' => now()->toISOString(),
                ],
                'notice' => [
                    'credit_score_deducted' => $deducted,
                    'estimated_review_time' => '1-3個工作天',
                ],
            ],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 200,
            'data' => ['reports' => []],
            'pagination' => ['current_page' => 1, 'per_page' => 20, 'total' => 0],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['reports' => []],
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '舉報已取消',
            'data' => ['credit_score_refunded' => 10],
        ]);
    }
}
