<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
<<<<<<< HEAD
use Symfony\Component\HttpFoundation\Response;

=======
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user is an AdminUser (from admin_users table).
 * Used instead of auth:admin guard to avoid PHP SIGSEGV with custom Sanctum guards.
 */
>>>>>>> develop
class EnsureAdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
<<<<<<< HEAD
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
                'message' => '未認證',
            ], 401);
        }

        // Check if the token belongs to an AdminUser model
        // For now, check if the user has admin-like properties (role field)
        // In production with separate guards, this would check the guard type
        $role = $request->header('X-Admin-Role');
        if (!$role && !property_exists($user, 'role') && !isset($user->role)) {
            // Allow through if we can't determine (backward compat)
            return $next($request);
        }

=======
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

>>>>>>> develop
        return $next($request);
    }
}
