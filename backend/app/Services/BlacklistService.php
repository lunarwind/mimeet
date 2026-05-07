<?php

namespace App\Services;

use App\Models\RegistrationBlacklist;
use App\Models\User;
use App\Support\Mask;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BlacklistService
{
    /**
     * 計算 email hash:lowercase + trim → SHA-256
     * 對齊 Q2-a 選項 X(blacklist 用 lowercase,register 不變)。
     * MySQL utf8mb4_unicode_ci collation 已是 case-insensitive,
     * 所以 register 不做 lowercase 也不會產生不對稱。
     */
    public static function computeEmailHash(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    /**
     * 檢查 email/mobile 是否在 active blacklist 中(過濾 expired)。
     */
    public function isBlocked(string $type, string $valueHash): bool
    {
        return DB::table('registration_blacklists')
            ->where('type', $type)
            ->where('value_hash', $valueHash)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * 新增 blacklist 條目。
     *
     * @param  array  $data  ['type' => 'email|mobile', 'value' => raw, 'reason' => null, 'expires_at' => null,
     *                        'source' => 'manual|admin_delete', 'source_user_id' => null, 'created_by' => admin id]
     * @return RegistrationBlacklist
     * @throws \DomainException ALREADY_BLACKLISTED 若該 value 已在 active blacklist
     */
    public function add(array $data): RegistrationBlacklist
    {
        $type = $data['type'];
        $rawValue = $data['value'];

        if ($type === 'email') {
            $valueHash = self::computeEmailHash($rawValue);
            $valueMasked = Mask::email(mb_strtolower(trim($rawValue))) ?? '***';
        } elseif ($type === 'mobile') {
            $valueHash = User::computePhoneHash($rawValue);
            if (!$valueHash) {
                throw new \DomainException('INVALID_PHONE');
            }
            $valueMasked = Mask::phone(User::normalizePhone($rawValue)) ?? '***';
        } else {
            throw new \DomainException('INVALID_TYPE');
        }

        try {
            return RegistrationBlacklist::create([
                'type' => $type,
                'value_hash' => $valueHash,
                'value_masked' => $valueMasked,
                'reason' => $data['reason'] ?? null,
                'source' => $data['source'] ?? 'manual',
                'source_user_id' => $data['source_user_id'] ?? null,
                'created_by' => $data['created_by'],
                'expires_at' => $data['expires_at'] ?? null,
                'is_active' => true,
                // active_value_hash 由 Model saving event 自動同步
            ]);
        } catch (QueryException $e) {
            // SQLSTATE 23000 = duplicate / unique violation
            if ($e->getCode() === '23000') {
                throw new \DomainException('ALREADY_BLACKLISTED');
            }
            throw $e;
        }
    }

    /**
     * 解除 blacklist。
     * Model saving event 自動把 active_value_hash 設 null,釋出 unique 索引。
     *
     * @throws \DomainException ALREADY_DEACTIVATED 若該條目已是 inactive
     */
    public function deactivate(RegistrationBlacklist $blacklist, int $deactivatedBy, string $reason): RegistrationBlacklist
    {
        if (!$blacklist->is_active) {
            throw new \DomainException('ALREADY_DEACTIVATED');
        }

        $blacklist->fill([
            'is_active' => false,
            'deactivated_by' => $deactivatedBy,
            'deactivated_at' => now(),
            'deactivation_reason' => $reason,
        ])->save();

        return $blacklist->fresh();
    }
}
