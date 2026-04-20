<?php

namespace App\Services;

use App\Exceptions\InsufficientPointsException;
use App\Models\PointTransaction;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * PointService — F40 點數經濟核心
 *
 * 提供所有點數進出的入口，後續的 F41/F42 功能（隱身、超級讚、廣播、逆區間）
 * 均透過本類的 consume() 扣點。
 */
class PointService
{
    public function getBalance(User $user): int
    {
        return (int) $user->points_balance;
    }

    public function canAfford(User $user, int $cost): bool
    {
        return $user->points_balance >= $cost;
    }

    /**
     * 入帳（購買成功、管理員贈送、退款回補等）
     */
    public function credit(
        User $user,
        int $amount,
        string $type = 'purchase',
        ?string $description = null,
        ?int $referenceId = null,
    ): PointTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('credit amount must be positive');
        }

        $user->increment('points_balance', $amount);
        $user->refresh();

        return PointTransaction::create([
            'user_id'       => $user->id,
            'type'          => $type,
            'amount'        => $amount,
            'balance_after' => $user->points_balance,
            'description'   => $description,
            'reference_id'  => $referenceId,
            'created_at'    => now(),
        ]);
    }

    /**
     * 扣點（F41/F42 功能使用）
     *
     * @throws InsufficientPointsException
     */
    public function consume(
        User $user,
        int $cost,
        string $feature,
        ?string $description = null,
        ?int $referenceId = null,
    ): PointTransaction {
        if ($cost <= 0) {
            throw new \InvalidArgumentException('consume cost must be positive');
        }

        if (!$this->canAfford($user, $cost)) {
            throw new InsufficientPointsException($cost, (int) $user->points_balance);
        }

        $user->decrement('points_balance', $cost);
        $user->refresh();

        return PointTransaction::create([
            'user_id'       => $user->id,
            'type'          => 'consume',
            'amount'        => -$cost,
            'balance_after' => $user->points_balance,
            'feature'       => $feature,
            'description'   => $description,
            'reference_id'  => $referenceId,
            'created_at'    => now(),
        ]);
    }

    /**
     * 管理員調整：正=贈送、負=扣除
     */
    public function adminAdjust(User $user, int $delta, ?string $description = null): PointTransaction
    {
        if ($delta === 0) {
            throw new \InvalidArgumentException('delta must be non-zero');
        }

        if ($delta > 0) {
            return $this->credit($user, $delta, 'admin_gift', $description);
        }

        $abs = abs($delta);
        if (!$this->canAfford($user, $abs)) {
            // 管理員扣除可以扣到 0（不允許負值）
            $abs = min($abs, (int) $user->points_balance);
            if ($abs === 0) {
                throw new InsufficientPointsException(1, 0);
            }
        }
        $user->decrement('points_balance', $abs);
        $user->refresh();

        return PointTransaction::create([
            'user_id'       => $user->id,
            'type'          => 'admin_deduct',
            'amount'        => -$abs,
            'balance_after' => $user->points_balance,
            'description'   => $description,
            'created_at'    => now(),
        ]);
    }

    /**
     * 取得某個功能的消費點數（從 system_settings）
     */
    public function getFeatureCost(string $feature): int
    {
        return (int) SystemSetting::get("point_cost_{$feature}", 0);
    }

    /**
     * 交易紀錄（分頁）
     */
    public function getHistory(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return PointTransaction::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
