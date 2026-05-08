<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PhoneConflictException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BlacklistService;
use App\Services\PhoneService;
use App\Services\SmsService;
use App\Support\Mask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class PhoneChangeController extends Controller
{
    public function __construct(
        private readonly PhoneService $phoneService,
        private readonly BlacklistService $blacklistService,
        private readonly SmsService $smsService,
    ) {}

    private function guardVerifiedUser(Request $request): ?JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->phone_verified || empty($user->phone)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PHONE_NOT_VERIFIED', 'message' => '請先完成手機驗證後再進行換號流程'],
            ], 422);
        }
        return null;
    }

    public function initiate(Request $request): JsonResponse
    {
        if ($err = $this->guardVerifiedUser($request)) return $err;

        $request->validate([
            'new_phone' => ['required', 'string', 'regex:/^09\d{8}$/'],
        ]);

        $user = $request->user();
        $newPhone = $request->input('new_phone');
        $newE164 = User::normalizePhone($newPhone);
        $newHash = User::computePhoneHash($newPhone);

        if (!$newE164 || !$newHash) {
            return $this->phoneError('此手機號碼已被使用');
        }

        // 同號碼擋下
        if ($user->phone_hash === $newHash) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'SAME_AS_CURRENT', 'message' => '新號碼與目前號碼相同'],
            ], 422);
        }

        // Unique check
        $exists = User::where('phone_hash', $newHash)
            ->where('id', '!=', $user->id)
            ->where('status', '!=', 'deleted')
            ->exists();
        if ($exists) {
            return $this->phoneError('此手機號碼已被使用');
        }

        // Blacklist check
        if ($this->blacklistService->isBlocked('mobile', $newHash)) {
            return $this->phoneError('此手機號碼已被使用');
        }

        $userId = $user->id;
        $cooldownOldKey = "phone_change:{$userId}:cooldown:old";
        $stateKey = "phone_change:{$userId}:state";
        $oldOtpKey = "phone_change:{$userId}:old:otp";
        $oldFailKey = "phone_change:{$userId}:old:fail";
        $newOtpKey = "phone_change:{$userId}:new:otp";
        $newFailKey = "phone_change:{$userId}:new:fail";
        $cooldownNewKey = "phone_change:{$userId}:cooldown:new";

        // v6 C3: cooldown 期間直接回 429,不破壞 state
        if (Cache::has($cooldownOldKey)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'COOLDOWN_ACTIVE', 'message' => '請等待 60 秒後再重新發送'],
            ], 429);
        }

        // v6 C3:依 state.step 決定半重啟或完整重啟
        $existingState = Cache::get($stateKey);
        $reuseOldOtp = false;
        if ($existingState) {
            if (($existingState['step'] ?? null) === 'old_otp_sent') {
                // 半重啟:清新號相關 + state,保留 old OTP / fail
                Cache::forget($stateKey);
                Cache::forget($newOtpKey);
                Cache::forget($newFailKey);
                Cache::forget($cooldownNewKey);
                $reuseOldOtp = Cache::has($oldOtpKey);
            } else {
                // step='old_verified_new_otp_sent' or unknown → 完整重啟
                Cache::forget($stateKey);
                Cache::forget($oldOtpKey);
                Cache::forget($oldFailKey);
                Cache::forget($newOtpKey);
                Cache::forget($newFailKey);
                Cache::forget($cooldownNewKey);
            }
        }

        // 發 OTP 到舊號(若 reuse 則跳過發送但仍寫 cooldown)
        if (!$reuseOldOtp) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put($oldOtpKey, $code, 300);
            Cache::forget($oldFailKey);
            $this->smsService->sendOtp(User::normalizePhone($user->phone), $code);
        }
        Cache::put($cooldownOldKey, true, 60);

        // 寫 state JSON(不含 raw phone)
        $state = [
            'step' => 'old_otp_sent',
            'old_phone_hash' => $user->phone_hash,
            'new_phone_ciphertext' => Crypt::encryptString($newE164),
            'new_phone_hash' => $newHash,
            'new_phone_masked' => Mask::phone($newE164),
            'created_at' => now()->toISOString(),
        ];
        Cache::put($stateKey, $state, 600);

        return response()->json([
            'success' => true,
            'data' => [
                'step' => 'old_otp_sent',
                'new_phone_masked' => $state['new_phone_masked'],
                'expires_in' => 600,
            ],
        ]);
    }

    public function verifyOld(Request $request): JsonResponse
    {
        if ($err = $this->guardVerifiedUser($request)) return $err;

        $request->validate(['old_otp' => 'required|string|size:6']);

        $user = $request->user();
        $userId = $user->id;
        $stateKey = "phone_change:{$userId}:state";
        $oldOtpKey = "phone_change:{$userId}:old:otp";
        $oldFailKey = "phone_change:{$userId}:old:fail";
        $cooldownNewKey = "phone_change:{$userId}:cooldown:new";
        $newOtpKey = "phone_change:{$userId}:new:otp";
        $newFailKey = "phone_change:{$userId}:new:fail";

        $state = Cache::get($stateKey);
        if (!$state || ($state['step'] ?? null) !== 'old_otp_sent') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_STEP', 'message' => '請從頭開始換號流程'],
            ], 422);
        }

        // belt-and-suspenders:確保舊號 hash 沒被外部變更
        if (($state['old_phone_hash'] ?? null) !== $user->phone_hash) {
            Cache::forget($stateKey);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PHONE_CHANGED', 'message' => '帳號狀態已變更,請重新開始'],
            ], 422);
        }

        $stored = Cache::get($oldOtpKey);
        if (!$stored) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'OTP_EXPIRED', 'message' => '驗證碼已過期或不存在,請重新發送'],
            ], 422);
        }

        $attempts = (int) Cache::get($oldFailKey, 0);
        if ($attempts >= 5) {
            Cache::forget($oldOtpKey);
            Cache::forget($oldFailKey);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'OTP_TOO_MANY', 'message' => '驗證失敗次數過多,請重新發送'],
            ], 429);
        }

        if ($stored !== $request->input('old_otp')) {
            Cache::put($oldFailKey, $attempts + 1, 300);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'OTP_INVALID', 'message' => '驗證碼不正確'],
            ], 422);
        }

        // pass:發 OTP 到新號
        $newE164 = Crypt::decryptString($state['new_phone_ciphertext']);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($newOtpKey, $code, 300);
        Cache::forget($newFailKey);
        Cache::put($cooldownNewKey, true, 60);
        $this->smsService->sendOtp($newE164, $code);

        // 更新 state
        $state['step'] = 'old_verified_new_otp_sent';
        Cache::put($stateKey, $state, 600);
        Cache::forget($oldOtpKey);
        Cache::forget($oldFailKey);

        return response()->json([
            'success' => true,
            'data' => [
                'step' => 'old_verified_new_otp_sent',
                'new_phone_masked' => $state['new_phone_masked'],
                'expires_in' => 300,
            ],
        ]);
    }

    public function verifyNew(Request $request): JsonResponse
    {
        if ($err = $this->guardVerifiedUser($request)) return $err;

        $request->validate(['new_otp' => 'required|string|size:6']);

        $user = $request->user();
        $userId = $user->id;
        $stateKey = "phone_change:{$userId}:state";
        $newOtpKey = "phone_change:{$userId}:new:otp";
        $newFailKey = "phone_change:{$userId}:new:fail";

        $state = Cache::get($stateKey);
        if (!$state || ($state['step'] ?? null) !== 'old_verified_new_otp_sent') {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_STEP', 'message' => '請從頭開始換號流程'],
            ], 422);
        }

        $stored = Cache::get($newOtpKey);
        if (!$stored) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'OTP_EXPIRED', 'message' => '驗證碼已過期或不存在,請重新發送'],
            ], 422);
        }

        $attempts = (int) Cache::get($newFailKey, 0);
        if ($attempts >= 5) {
            Cache::forget($newOtpKey);
            Cache::forget($newFailKey);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'OTP_TOO_MANY', 'message' => '驗證失敗次數過多,請重新發送'],
            ], 429);
        }

        if ($stored !== $request->input('new_otp')) {
            Cache::put($newFailKey, $attempts + 1, 300);
            return response()->json([
                'success' => false,
                'error' => ['code' => 'OTP_INVALID', 'message' => '驗證碼不正確'],
            ], 422);
        }

        // pass:用 PhoneService 寫入(再做一次 unique + blacklist check)
        $newE164 = Crypt::decryptString($state['new_phone_ciphertext']);
        try {
            $this->phoneService->setVerifiedPhone($user, $newE164, 'change', $request);
        } catch (PhoneConflictException $e) {
            return $this->phoneError($e->getMessage(), 'new_phone');
        }

        // 清 cache
        Cache::forget($stateKey);
        Cache::forget($newOtpKey);
        Cache::forget($newFailKey);
        Cache::forget("phone_change:{$userId}:cooldown:old");
        Cache::forget("phone_change:{$userId}:cooldown:new");

        return response()->json([
            'success' => true,
            'data' => [
                'step' => 'completed',
                'phone' => Mask::phone($newE164),
            ],
        ]);
    }

    private function phoneError(string $message, string $field = 'phone'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 400,
            'message' => '操作失敗',
            'errors' => [$field => [$message]],
            'error' => ['type' => 'validation_error', 'details' => [
                ['field' => $field, 'message' => $message],
            ]],
        ], 422);
    }
}
