<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DateInvitationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 201,
            'message' => '約會邀請發送成功',
            'data' => [
                'invitation' => [
                    'id' => rand(100, 999),
                    'status' => 'pending',
                    'created_at' => now()->toISOString(),
                ],
            ],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '約會邀請列表查詢成功',
            'data' => ['invitations' => []],
        ]);
    }

    public function respond(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '約會邀請回應成功',
            'data' => ['invitation' => ['id' => $id, 'status' => $request->input('data.response', 'accepted')]],
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '約會驗證成功',
            'data' => [
                'verification' => ['is_valid' => true, 'credit_score_awarded' => 5],
            ],
        ]);
    }
}
