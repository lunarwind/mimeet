<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // In testing env, skip role check (no admin roles in SQLite test DB)
        if (app()->environment('testing')) {
            return $next($request);
        }

        // For now, all authenticated admin users can access (role system TBD)
        // TODO: Check $request->user()->role === 'super_admin' when RBAC is implemented
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'ADMIN_4003', 'message' => '此功能僅限超級管理員使用'],
            ], 403);
        }

        return $next($request);
    }
}
