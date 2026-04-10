<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemControlController extends Controller
{
    public function status(): JsonResponse
    {
        // Return system status: app mode, DB connection, cache info
        $dbOk = true;
        try { DB::connection()->getPdo(); } catch (\Exception $e) { $dbOk = false; }

        return response()->json([
            'success' => true,
            'data' => [
                'app_mode' => Cache::get('app_mode', 'normal'),
                'db_status' => $dbOk ? 'connected' : 'error',
                'db_host' => config('database.connections.mysql.host'),
                'db_name' => config('database.connections.mysql.database'),
                'cache_driver' => config('cache.default'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
        ]);
    }

    public function setMode(Request $request): JsonResponse
    {
        $request->validate(['mode' => 'required|in:normal,maintenance']);
        Cache::put('app_mode', $request->mode, now()->addYear());

        if ($request->mode === 'maintenance') {
            // Don't actually call artisan down in dev
            Cache::put('maintenance_mode_at', now()->toISOString());
        }

        return response()->json([
            'success' => true,
            'message' => '系統模式已切換為：' . ($request->mode === 'normal' ? '正常模式' : '維護模式'),
        ]);
    }

    public function cacheClear(): JsonResponse
    {
        Cache::flush();
        return response()->json([
            'success' => true,
            'message' => '快取已清除',
        ]);
    }
}
