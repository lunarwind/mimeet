<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => '未認證'], 401);
        }

        // For now, check role from token or request header
        $role = $request->header('X-Admin-Role', 'admin');

        // super_admin has all permissions
        if ($role === 'super_admin') {
            return $next($request);
        }

        $allowed = DB::table('admin_role_permissions')
            ->where('role', $role)
            ->where('permission_key', $permission)
            ->where('is_allowed', true)
            ->exists();

        if (!$allowed) {
            return response()->json(['success' => false, 'message' => '無權限執行此操作', 'code' => 'PERMISSION_DENIED'], 403);
        }

        return $next($request);
    }
}
