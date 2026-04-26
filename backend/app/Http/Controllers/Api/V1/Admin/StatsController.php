<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PointOrder;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 後台營運數據摘要（補完 A01）
 *   GET /admin/stats/summary — 會員 / 營收 / 點數 / 待處理事項
 */
class StatsController extends Controller
{
    public function summary(): JsonResponse
    {
        $memberBase = User::where('id', '>', 1)->whereNull('deleted_at');

        $members = [
            'total' => (clone $memberBase)->count(),
            'new_today' => (clone $memberBase)->whereDate('created_at', today())->count(),
            'new_month' => (clone $memberBase)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'paid' => User::where('membership_level', '>=', 3)->count(),
            'active' => (clone $memberBase)
                ->where('last_active_at', '>=', now()->subDays(7))
                ->count(),
        ];

        // 改讀 payments 主表（金流 9 步後真實付款都在此）
        // 排除 legacy 避免 mock 測試資料污染統計
        $revenueBase = Payment::where('status', 'paid')
            ->where('environment', '!=', 'legacy')
            ->whereYear('paid_at', now()->year)
            ->whereMonth('paid_at', now()->month);

        $revenue = [
            'subscription_month' => (int) (clone $revenueBase)
                ->where('type', 'subscription')
                ->sum('amount'),
            'points_month' => (int) (clone $revenueBase)
                ->where('type', 'points')
                ->sum('amount'),
            'points_today' => (int) Payment::where('status', 'paid')
                ->where('environment', '!=', 'legacy')
                ->where('type', 'points')
                ->whereDate('paid_at', today())
                ->sum('amount'),
        ];

        $consumptionByFeature = PointTransaction::where('type', 'consume')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->selectRaw('feature, ABS(SUM(amount)) as total')
            ->groupBy('feature')
            ->pluck('total', 'feature');

        $points = [
            'circulating' => (int) User::sum('points_balance'),
            'consumed_today' => (int) abs(PointTransaction::where('type', 'consume')
                ->whereDate('created_at', today())
                ->sum('amount')),
            'consumed_month' => (int) abs(PointTransaction::where('type', 'consume')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount')),
            'consumption_by_feature' => $consumptionByFeature,
        ];

        $pendingTickets = 0;
        if (Schema::hasTable('reports')) {
            $pendingTickets = (int) DB::table('reports')->where('status', 'pending')->count();
        }
        $pendingVerifications = 0;
        if (Schema::hasTable('user_verifications')) {
            $pendingVerifications = (int) DB::table('user_verifications')
                ->where('status', 'pending')->count();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'members' => $members,
                'revenue' => $revenue,
                'points' => $points,
                'pending_tickets' => $pendingTickets,
                'pending_verifications' => $pendingVerifications,
            ],
        ]);
    }

    /**
     * GET /admin/stats/chart — daily new-member and revenue trend (last N days)
     */
    public function chart(Request $request): JsonResponse
    {
        $days = min((int) $request->input('days', 30), 90);
        $start = now()->subDays($days - 1)->startOfDay();

        $newMembers = User::where('id', '>', 1)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $subscriptionRevenue = Payment::where('status', 'paid')
            ->where('environment', '!=', 'legacy')
            ->where('type', 'subscription')
            ->where('paid_at', '>=', $start)
            ->selectRaw('DATE(paid_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $pointRevenue = Payment::where('status', 'paid')
            ->where('environment', '!=', 'legacy')
            ->where('type', 'points')
            ->where('paid_at', '>=', $start)
            ->selectRaw('DATE(paid_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $labels = [];
        $series = ['new_members' => [], 'subscription_revenue' => [], 'point_revenue' => []];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = $date;
            $series['new_members'][] = (int) ($newMembers[$date] ?? 0);
            $series['subscription_revenue'][] = (int) ($subscriptionRevenue[$date] ?? 0);
            $series['point_revenue'][] = (int) ($pointRevenue[$date] ?? 0);
        }

        return response()->json([
            'success' => true,
            'data' => ['labels' => $labels, 'series' => $series],
        ]);
    }

    /**
     * GET /admin/stats/export — download daily stats CSV (last 30 days by default)
     */
    public function export(Request $request): StreamedResponse
    {
        $days = min((int) $request->input('days', 30), 90);
        $start = now()->subDays($days - 1)->startOfDay();

        $newMembers = User::where('id', '>', 1)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $subscriptionRevenue = Payment::where('status', 'paid')
            ->where('environment', '!=', 'legacy')
            ->where('type', 'subscription')
            ->where('paid_at', '>=', $start)
            ->selectRaw('DATE(paid_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $pointRevenue = Payment::where('status', 'paid')
            ->where('environment', '!=', 'legacy')
            ->where('type', 'points')
            ->where('paid_at', '>=', $start)
            ->selectRaw('DATE(paid_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $filename = 'mimeet-stats-' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($days, $newMembers, $subscriptionRevenue, $pointRevenue) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'New Members', 'Subscription Revenue', 'Point Revenue', 'Total Revenue']);
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $sub = (int) ($subscriptionRevenue[$date] ?? 0);
                $pts = (int) ($pointRevenue[$date] ?? 0);
                fputcsv($handle, [$date, (int) ($newMembers[$date] ?? 0), $sub, $pts, $sub + $pts]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * GET /admin/stats/server-metrics — basic server health snapshot
     */
    public function serverMetrics(): JsonResponse
    {
        $dbVersion = DB::selectOne('SELECT VERSION() as version')?->version ?? 'unknown';

        $load = null;
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
        }

        $diskTotal = disk_total_space('/');
        $diskFree = disk_free_space('/');

        $redisInfo = null;
        try {
            $redis = app('redis')->connection();
            $info = $redis->info();
            $redisInfo = [
                'version' => $info['redis_version'] ?? null,
                'used_memory_human' => $info['used_memory_human'] ?? null,
                'connected_clients' => $info['connected_clients'] ?? null,
            ];
        } catch (\Throwable) {
            $redisInfo = ['error' => 'unavailable'];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'db_version' => $dbVersion,
                'load_avg' => $load ? ['1m' => round($load[0], 2), '5m' => round($load[1], 2), '15m' => round($load[2], 2)] : null,
                'disk' => [
                    'total_gb' => $diskTotal ? round($diskTotal / 1073741824, 1) : null,
                    'free_gb' => $diskFree ? round($diskFree / 1073741824, 1) : null,
                    'used_percent' => ($diskTotal && $diskFree) ? round(($diskTotal - $diskFree) / $diskTotal * 100, 1) : null,
                ],
                'redis' => $redisInfo,
                'php_version' => PHP_VERSION,
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }
}
