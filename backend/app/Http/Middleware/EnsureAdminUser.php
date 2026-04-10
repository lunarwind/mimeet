<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user is an AdminUser (from admin_users table).
 * Used instead of auth:admin guard to avoid PHP SIGSEGV with custom Sanctum guards.
 */
class EnsureAdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        if (!$bearer) {
            return response()->json([
                'success' => false,
                'code' => 401,
                'message' => '未提供認證 Token',
            ], 401);
        }

        $token = PersonalAccessToken::findToken($bearer);
        if (!$token || $token->tokenable_type !== \App\Models\AdminUser::class) {
            return response()->json([
                'success' => false,
                'code' => 401,
                'message' => '無效的管理員 Token',
            ], 401);
        }

        $admin = $token->tokenable;
        if (!$admin || !$admin->is_active) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => '帳號已停用',
            ], 403);
        }

        // Set the authenticated user on the request
        $request->setUserResolver(fn () => $admin);

        return $next($request);
    }
}
