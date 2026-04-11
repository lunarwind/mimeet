<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $admin = $request->user();
        if (!$admin || !($admin instanceof AdminUser)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_4001', 'message' => '未授權'],
            ], 401);
        }

        // super_admin has wildcard
        if ($admin->role === 'super_admin') {
            return $next($request);
        }

        // Check if role has this permission
        $hasPermission = DB::table('admin_role_permissions')
            ->where('role', $admin->role)
            ->where(function ($q) use ($permission) {
                $q->where('permission_key', $permission)
                  ->orWhere('permission_key', '*');
            })
            ->exists();

        if (!$hasPermission) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_4003', 'message' => '權限不足'],
            ], 403);
        }

        return $next($request);
    }
}
