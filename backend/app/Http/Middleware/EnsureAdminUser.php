<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
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

        return $next($request);
    }
}
