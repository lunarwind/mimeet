<?php

namespace App\Services;

use App\Exceptions\PhoneConflictException;
use App\Models\PhoneChangeHistory;
use App\Models\User;
use App\Support\Mask;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PhoneService
{
    public function __construct(
        private readonly BlacklistService $blacklistService,
    ) {}

    /**
     * 設定 / 變更 user 的 phone(已驗證狀態)。
     * 包含 unique + blacklist check + atomic save + history record。
     *
     * @throws PhoneConflictException 若衝突
     */
    public function setVerifiedPhone(
        User $user,
        string $rawPhone,
        string $source = 'verify',
        ?Request $request = null,
    ): PhoneChangeResult {
        $newE164 = User::normalizePhone($rawPhone);
        $newHash = User::computePhoneHash($rawPhone);
        $oldPhone = $user->phone;
        $oldHash = $user->phone_hash;

        // v8 R5 修正:normalizePhone() / computePhoneHash() 對非法 phone 會 return null
        // BlacklistService::isBlocked(string $type, string $valueHash) 收 null 會 type error
        // 即使 caller 理論上已 validate,service 層自保(防禦性編程)
        if (!$newE164 || !$newHash) {
            throw new PhoneConflictException('此手機號碼已被使用');
        }

        $phoneAlreadyTarget = ($oldHash === $newHash);
        $alreadyVerified = $user->phone_verified === true;
        $membershipOk = $user->membership_level >= 1;

        // v4 S1 修正:Blacklist check 必須在 no-op return 之前
        // 攻擊向量:register 後 verified → admin 把該 mobile hash 加 blacklist → user 重複呼叫 verify
        //          → no-op return 成功 → blacklist 完全失效
        // 所有 verify / change source 都必須做 blacklist check,不論 phone / verified 狀態
        if ($this->blacklistService->isBlocked('mobile', $newHash)) {
            // 對齊既有 register unique error 訊息(防 enumeration)
            throw new PhoneConflictException('此手機號碼已被使用');
        }

        // v2 B2 修正:僅在「真的所有 verified state 都已滿足」才 no-op
        if ($phoneAlreadyTarget && $alreadyVerified && $membershipOk) {
            return new PhoneChangeResult(
                changed: false,
                verifiedChanged: false,
                membershipChanged: false,
                oldPhoneHash: $oldHash,
                newPhoneHash: $newHash,
            );
        }

        // Unique check(排除自己)— 只在 phone 真要變動時做
        if (!$phoneAlreadyTarget) {
            $exists = User::where('phone_hash', $newHash)
                ->where('id', '!=', $user->id)
                ->where('status', '!=', 'deleted')
                ->exists();
            if ($exists) {
                // 一字不差對齊上方訊息(防 enumeration)
                throw new PhoneConflictException('此手機號碼已被使用');
            }
        }

        $verifiedChanged = !$alreadyVerified;
        $membershipChanged = !$membershipOk;
        $ipAddress = $request?->ip();

        try {
            DB::transaction(function () use ($user, $newE164, $newHash, $oldPhone, $oldHash, $source, $ipAddress, $phoneAlreadyTarget) {
                if (!$phoneAlreadyTarget) {
                    $user->phone = $newE164;
                    // phone_hash 由 User saving event 自動同步,不在此明示寫入
                }
                $user->phone_verified = true;
                if ($user->membership_level < 1) {
                    $user->membership_level = 1;
                }
                $user->save();

                // History — 只在 phone 真的變動或首次 verify 時記
                if (!$phoneAlreadyTarget || $source === 'verify') {
                    PhoneChangeHistory::create([
                        'user_id' => $user->id,
                        'old_phone_hash' => $oldHash,
                        'old_phone_masked' => $oldPhone ? Mask::phone($oldPhone) : null,
                        'new_phone_hash' => $newHash,
                        'new_phone_masked' => Mask::phone($newE164),
                        'source' => $source,
                        'ip_address' => $ipAddress,
                        'changed_at' => now(),
                    ]);
                }
            });
        } catch (QueryException $e) {
            // v2 B3 修正:DB unique race 處理
            // 兩個 request 同時通過 pre-check,第二個 save 會撞 unique
            // 轉成 friendly PhoneConflictException 對齊 enumeration 防護
            if ($e->getCode() === '23000') {
                throw new PhoneConflictException('此手機號碼已被使用');
            }
            throw $e;
        }

        return new PhoneChangeResult(
            changed: !$phoneAlreadyTarget,
            verifiedChanged: $verifiedChanged,
            membershipChanged: $membershipChanged,
            oldPhoneHash: $oldHash,
            newPhoneHash: $newHash,
        );
    }
}
