<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\CreditScoreHistory;
use App\Models\DateInvitation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class DatasetController extends Controller
{
    public function stats(): JsonResponse
    {
        $counts = [
            'users' => User::where('id', '!=', 1)->count(),
            'conversations' => Conversation::count(),
            'messages' => Message::count(),
            'date_invitations' => DateInvitation::count(),
            'orders' => Order::count(),
            'subscriptions' => Subscription::count(),
            'reports' => Report::count(),
            'credit_score_histories' => CreditScoreHistory::count(),
            'notifications' => Notification::count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'is_clean' => array_sum($counts) === 0,
                'counts' => $counts,
            ],
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate(['confirm_password' => 'required|string']);

        if (!Hash::check($request->confirm_password, $request->user()->password)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PASSWORD_INCORRECT', 'message' => '密碼驗證失敗'],
            ], 422);
        }

        Log::info("[Dataset] Reset executed by admin #{$request->user()->id}");

        try {
            Artisan::call('mimeet:reset-clean', ['--force' => true]);
        } catch (\Throwable $e) {
            Log::error('[Dataset] Reset failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'RESET_FAILED', 'message' => '清空失敗：' . $e->getMessage()],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => ['message' => '資料庫已重置為乾淨狀態'],
        ]);
    }

    public function seed(Request $request): JsonResponse
    {
        $request->validate([
            'fresh' => 'sometimes|boolean',
            'confirm_password' => 'required|string',
        ]);

        if (!Hash::check($request->confirm_password, $request->user()->password)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PASSWORD_INCORRECT', 'message' => '密碼驗證失敗'],
            ], 422);
        }

        $fresh = $request->boolean('fresh');
        Log::info("[Dataset] Seed executed by admin #{$request->user()->id}" . ($fresh ? ' (fresh)' : ''));

        $options = ['--force' => true];
        if ($fresh) $options['--fresh'] = true;

        try {
            Artisan::call('mimeet:seed-test', $options);
        } catch (\Throwable $e) {
            Log::error('[Dataset] Seed failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'SEED_FAILED', 'message' => '匯入失敗：' . $e->getMessage()],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'message' => '測試資料集匯入完成',
                'counts' => [
                    'users' => User::where('id', '!=', 1)->count(),
                    'conversations' => Conversation::count(),
                    'messages' => Message::count(),
                ],
            ],
        ]);
    }
}
