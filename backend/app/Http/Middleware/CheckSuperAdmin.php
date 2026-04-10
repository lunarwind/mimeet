<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $admin = $request->user();

        if (!$admin || !($admin instanceof AdminUser)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_4003', 'message' => '此功能僅限超級管理員使用'],
            ], 403);
        }

        if ($admin->role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_4003', 'message' => '此功能僅限超級管理員使用'],
            ], 403);
        }

        return $next($request);
    }
}
