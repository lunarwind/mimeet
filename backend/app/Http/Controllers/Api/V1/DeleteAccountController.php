<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GdprService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeleteAccountController extends Controller
{
    public function __construct(
        private readonly GdprService $gdprService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        try {
            $this->gdprService->requestDeletion($request->user(), $request->input('password'));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => $e->getMessage(), 'message' => match ($e->getMessage()) {
                    'PASSWORD_INCORRECT' => '密碼不正確',
                    'DELETION_PENDING' => '已有待執行的刪除申請',
                    default => $e->getMessage(),
                }],
            ], 422);
        }

        $user = $request->user()->fresh();

        return response()->json([
            'success' => true,
            'data' => [
                'status' => 'pending_deletion',
                'delete_at' => $user->delete_requested_at->addDays(7)->toISOString(),
                'message' => '您的帳號將於 7 天後永久刪除，期間可隨時取消',
            ],
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        try {
            $this->gdprService->cancelDeletion($request->user());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => $e->getMessage(), 'message' => '目前沒有待執行的刪除申請'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => ['status' => 'active', 'message' => '刪除申請已取消，帳號恢復正常'],
        ]);
    }
}
