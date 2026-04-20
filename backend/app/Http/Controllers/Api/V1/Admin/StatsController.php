<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PointOrder;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        $revenue = [
            'subscription_month' => (int) Order::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
            'points_month' => (int) PointOrder::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
            'points_today' => (int) PointOrder::where('status', 'paid')
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
}
