<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Console\Commands\ResetToCleanState;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DatasetController extends Controller
{
    /**
     * GET /api/v1/admin/settings/dataset/stats
     *
     * 對齊 ResetToCleanState::TRUNCATE_TABLES 的核心業務表(扣掉 token/queue/log 類)
     * 讓 is_clean 反映「業務資料是否真的乾淨」,避免只看 9 張表誤判。
     */
    public function stats(): JsonResponse
    {
        // 對齊 reset 的核心業務範圍(不含 token/queue/log 類純清理表)
        $businessTables = [
            'users',                    // 特別處理:扣掉 id=1
            'conversations', 'messages',
            'date_invitations',
            'orders', 'subscriptions',
            'point_orders', 'point_transactions',
            'payments', 'credit_card_verifications',
            'reports', 'report_followups', 'report_images',
            'credit_score_histories',
            'notifications',
            'fcm_tokens',
            'user_profile_visits', 'user_follows', 'user_blocks',
            'user_activity_logs', 'user_verifications',
            'user_broadcasts', 'broadcast_campaigns',
            // PR-2 / PR-3 新增(2026-05-07/08)
            'registration_blacklists',
            'phone_change_histories',
        ];

        $counts = [];
        foreach ($businessTables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $query = DB::table($table);
            if ($table === 'users') {
                $query->where('id', '!=', 1);
            }
            $counts[$table] = $query->count();
        }

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

        $admin = $request->user();
        $superAdminEmail = env('SUPER_ADMIN_EMAIL', 'chuck@lunarwind.org');

        // ── Email 守門:僅系統預設 super-admin 可執行 ──────────────────
        if ($admin->email !== $superAdminEmail) {
            Log::warning('[Dataset] Reset blocked — non-default super admin attempted', [
                'attempted_by' => $admin->email,
                'admin_id'     => $admin->id,
            ]);
            return response()->json([
                'success' => false,
                'error' => [
                    'code'    => 'PERMISSION_DENIED',
                    'message' => "此操作僅限系統預設超級管理員({$superAdminEmail})執行",
                ],
            ], 403);
        }

        // ── 密碼驗證 ──────────────────────────────────────────────────
        if (!Hash::check($request->confirm_password, $admin->password)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PASSWORD_INCORRECT', 'message' => '密碼驗證失敗'],
            ], 422);
        }

        Log::info("[Dataset] Reset executed by admin #{$admin->id} ({$admin->email})");

        try {
            $exitCode = Artisan::call('mimeet:reset', ['--force' => true]);
            $output = Artisan::output();

            if ($exitCode !== 0) {
                Log::error('[Dataset] Reset failed', ['exit_code' => $exitCode, 'output' => $output]);
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code'    => 'RESET_FAILED',
                        'message' => '清空失敗(exit ' . $exitCode . ')',
                        'detail'  => $output,
                    ],
                ], 500);
            }

            Log::info('[Dataset] Reset completed', ['output' => $output]);

            // ── AdminOperationLog(reset 完成後寫入)──────────────────
            // Note: admin_operation_logs 也在 truncate 清單內,但 reset 後仍可 insert。
            // description 用動態 count(原本寫死「19 張」已 drift,現在透過 const 對齊)。
            try {
                $tableCount = count(ResetToCleanState::TRUNCATE_TABLES);
                \App\Models\AdminOperationLog::create([
                    'admin_id'        => $admin->id,
                    'action'          => 'database_reset',
                    'resource_type'   => 'system',
                    'resource_id'     => 0,
                    'description'     => "清空業務資料:truncate {$tableCount} 張業務表,重建 uid=1,admin_users 僅保留 {$superAdminEmail}",
                    'ip_address'      => $request->ip(),
                    'user_agent'      => substr((string) $request->userAgent(), 0, 500),
                    'request_summary' => ['email' => $admin->email, 'truncate_count' => $tableCount],
                    'created_at'      => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('[Dataset] AdminOperationLog write failed (non-fatal)', [
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[Dataset] Reset exception', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'RESET_FAILED', 'message' => '清空失敗:' . $e->getMessage()],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'message'           => '資料庫已重置為乾淨狀態,uid=1 官方帳號已重建,admin_users 僅保留 ' . $superAdminEmail,
                'token_invalidated' => true,
                'redirect_to_login' => true,
            ],
        ]);
    }
}
