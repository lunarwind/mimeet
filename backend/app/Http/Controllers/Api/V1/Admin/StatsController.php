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
    /**
     * 統一「會員」定義：deleted_at IS NULL。
     *
     * 設計原則：
     * - 與 AdminController::members() 的 GET /admin/members 列表口徑完全一致
     * - Admin 帳號存於獨立 admin_users 表（Multi-Guard），不在 users 表，無需排除
     * - 後續若需調整口徑（如排除特定 status），只改此一處
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function memberBaseQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return User::whereNull('deleted_at');
    }

    public function summary(): JsonResponse
    {
        $memberBase = $this->memberBaseQuery();

        $members = [
            'total' => (clone $memberBase)->count(),
            'new_today' => (clone $memberBase)->whereDate('created_at', today())->count(),
            'new_month' => (clone $memberBase)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            // 繼承 memberBase：排除軟刪除的付費會員
            'paid' => (clone $memberBase)->where('membership_level', '>=', 3)->count(),
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

        // ── 會員等級分布（5 組精確分組，Lv1.5 以 DECIMAL(3,1) 精確比對）──────
        $levelDist = User::query()
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(CASE WHEN membership_level = 0   THEN 1 END) as lv0,
                COUNT(CASE WHEN membership_level = 1   THEN 1 END) as lv1,
                COUNT(CASE WHEN membership_level = 1.5 THEN 1 END) as lv1_5,
                COUNT(CASE WHEN membership_level = 2   THEN 1 END) as lv2,
                COUNT(CASE WHEN membership_level >= 3  THEN 1 END) as lv3
            ")
            ->first();

        $levelDistribution = [
            ['level' => 'Lv0',   'label' => '未驗證',       'count' => (int) ($levelDist->lv0   ?? 0)],
            ['level' => 'Lv1',   'label' => '基礎驗證',     'count' => (int) ($levelDist->lv1   ?? 0)],
            ['level' => 'Lv1.5', 'label' => '女性照片驗證', 'count' => (int) ($levelDist->lv1_5 ?? 0)],
            ['level' => 'Lv2',   'label' => '進階驗證',     'count' => (int) ($levelDist->lv2   ?? 0)],
            ['level' => 'Lv3',   'label' => '完整驗證',     'count' => (int) ($levelDist->lv3   ?? 0)],
        ];

        // ── 最新付款（subscription + points，排除 verification NT$100 押金）────
        $recentPayments = Payment::query()
            ->with(['user:id,nickname'])
            ->where('status', 'paid')
            ->whereIn('type', ['subscription', 'points'])
            ->orderByDesc('paid_at')
            ->limit(5)
            ->get(['id', 'user_id', 'type', 'item_name', 'amount', 'paid_at', 'invoice_status'])
            ->map(function ($p) {
                return [
                    'id'             => (int) $p->id,
                    'user'           => $p->user?->nickname ?? '已刪除用戶',
                    'plan'           => $p->item_name,
                    'type'           => $p->type,
                    'amount'         => (int) $p->amount,
                    'time'           => optional($p->paid_at)->toIso8601String(),
                    'invoice_status' => $p->invoice_status,  // 前端負責顯示對照
                ];
            })
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'members'              => $members,
                'revenue'              => $revenue,
                'points'               => $points,
                'pending_tickets'      => $pendingTickets,
                'pending_verifications'=> $pendingVerifications,
                'level_distribution'   => $levelDistribution,
                'recent_payments'      => $recentPayments,
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

        $newMembers = $this->memberBaseQuery()
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

        $newMembers = $this->memberBaseQuery()
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
