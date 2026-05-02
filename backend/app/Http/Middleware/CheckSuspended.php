<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 阻擋停權帳號使用受保護 API。
 *
 * 設計重點：
 * - 必須掛在 auth:sanctum 之後（依賴 $request->user() 已 hydrated）
 * - 未登入交給 auth:sanctum 處理（不在這裡回 401）
 * - 回應 shape 必須與 AuthController::login 對停權的 fallback shape 完全一致，
 *   讓 frontend client.ts 的 ACCOUNT_SUSPENDED 攔截器命中
 *
 * Whitelist 路由（請在 routes/api.php 用 ->withoutMiddleware('check.suspended')）：
 *   - POST /me/appeal           → 停權者要能送申訴
 *   - GET  /me/appeal/current   → 停權者要能查申訴狀態
 *   - GET  /auth/me             → 前端要能讀 user.status 主動跳 /suspended
 *   - POST /auth/logout         → 停權者要能登出
 *
 * Refs: docs/decisions/2026-05-01-check-suspended-decision.md (Option A2 + appeal whitelist)
 */
class CheckSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->status, ['suspended', 'auto_suspended'], true)) {
            return response()->json([
                'success' => false,
                'code' => 'ACCOUNT_SUSPENDED',
                'message' => '您的帳號已被暫停使用。',
            ], 403);
        }

        return $next($request);
    }
}
