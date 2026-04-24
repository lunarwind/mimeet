<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationMail;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use App\Services\SmsService;
use App\Services\UserActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        // ── 1. 解包前端送來的 { data: {...} } wrapper ─────────��────
        $raw = $request->input('data');
        $input = is_array($raw) && !empty($raw) ? $raw : $request->all();

        // ── 2. 格式驗證（不查 DB，快速失敗）─────────────────────────
        $formatValidator = Validator::make($input, [
            'email'            => ['required', 'email'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
            'nickname'         => ['required', 'string', 'max:20'],
            'gender'           => ['required', 'in:male,female'],
            'birth_date'       => ['required', 'date', 'before:-18 years'],
            'phone'            => ['nullable', 'string', 'regex:/^09\d{8}$/'],
            'terms_accepted'   => ['required', 'accepted'],
            'privacy_accepted' => ['required', 'accepted'],
            'anti_fraud_read'  => ['required', 'accepted'],
        ]);

        if ($formatValidator->fails()) {
            $details = [];
            foreach ($formatValidator->errors()->toArray() as $field => $messages) {
                $details[] = ['field' => $field, 'message' => $messages[0]];
            }
            return response()->json([
                'success' => false,
                'code'    => 400,
                'message' => '註冊失敗',
                'errors'  => $formatValidator->errors()->toArray(),
                'error'   => ['type' => 'validation_error', 'details' => $details],
            ], 422);
        }

        // ── 3. 唯一性驗證（查 DB，排除已刪除帳號）─────────────────
        $email    = $input['email'];
        $nickname = $input['nickname'];
        $phone    = $input['phone'] ?? null;

        if (User::where('email', $email)->where('status', '!=', 'deleted')->exists()) {
            return response()->json([
                'success' => false,
                'code'    => 400,
                'message' => '註冊失敗',
                'errors'  => ['email' => ['此 Email 已被使用']],
                'error'   => ['type' => 'validation_error', 'details' => [
                    ['field' => 'email', 'message' => '此 Email 已被使用'],
                ]],
            ], 422);
        }

        if (User::where('nickname', $nickname)->where('status', '!=', 'deleted')->exists()) {
            return response()->json([
                'success' => false,
                'code'    => 400,
                'message' => '註冊失敗',
                'errors'  => ['nickname' => ['此暱稱已被使用，請換一個']],
                'error'   => ['type' => 'validation_error', 'details' => [
                    ['field' => 'nickname', 'message' => '此暱稱已被使用，請換一個'],
                ]],
            ], 422);
        }

        if (!empty($phone) && User::where('phone', $phone)->whereNotNull('phone')->where('status', '!=', 'deleted')->exists()) {
            return response()->json([
                'success' => false,
                'code'    => 400,
                'message' => '註冊失敗',
                'errors'  => ['phone' => ['此手機號碼已被使用']],
                'error'   => ['type' => 'validation_error', 'details' => [
                    ['field' => 'phone', 'message' => '此手機號碼已被使用'],
                ]],
            ], 422);
        }

        // ── 4. 建立用戶 ──────────────────────────────────────────
        try {
            $user = User::create([
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'nickname' => $input['nickname'],
                'gender' => $input['gender'],
                'birth_date' => $input['birth_date'],
                'phone' => $input['phone'] ?? null,
                'membership_level' => 0,
                'credit_score' => \App\Services\CreditScoreService::getConfig('credit_score_initial', 60),
                'status' => 'active',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'success' => false,
                    'code'    => 400,
                    'message' => '註冊失敗，此帳號資料可能已被使用。',
                    'error'   => ['type' => 'constraint_error', 'details' => []],
                ], 422);
            }
            throw $e;
        }

        $user->refresh();
        $token = $user->createToken('register')->plainTextToken;

        $verifyCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        try {
            Cache::put("email_verification:{$user->email}", $verifyCode, 600);
            try {
                Mail::to($user->email)->send(new EmailVerificationMail($user->nickname, $verifyCode));
            } catch (\Throwable $e) {
                Log::error('[Register] Email send failed', [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $cacheEx) {
            Log::error('[Register] Cache OTP failed', ['error' => $cacheEx->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'code' => 'REGISTER_SUCCESS',
            'message' => '註冊成功，請驗證信箱。',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'nickname' => $user->nickname,
                    'avatar' => $user->avatar_url,
                    'gender' => $user->gender,
                    'status' => $user->status,
                    'credit_score' => $user->credit_score,
                    'membership_level' => $user->membership_level,
                    'email_verified' => (bool) $user->email_verified,
                    'phone_verified' => (bool) $user->phone_verified,
                    'phone' => $user->phone ? $this->maskPhone($user->phone) : null,
                ],
                'token' => $token,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $request->input('email');
        $ip = $request->ip();
        $emailKey = "login_fail_email:{$email}";
        $ipKey = "login_fail_ip:{$ip}";

        // Check email lockout (5 failures → 5 min cooldown)
        $emailFails = Cache::get($emailKey, 0);
        if ($emailFails >= 5) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_LOGIN_LOCKED',
                    'message' => '登入失敗次數過多，請 5 分鐘後再試',
                ],
            ], 429);
        }

        // Check IP lockout (20 failures → 5 min cooldown)
        $ipFails = Cache::get($ipKey, 0);
        if ($ipFails >= 20) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'IP_LOGIN_LOCKED',
                    'message' => '此網路登入失敗次數過多，請稍後再試',
                ],
            ], 429);
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            // Increment failure counters (5 min TTL)
            Cache::put($emailKey, $emailFails + 1, 300);
            Cache::put($ipKey, $ipFails + 1, 300);

            $remaining = max(0, 4 - $emailFails);
            return response()->json([
                'success' => false, 'code' => 'LOGIN_FAILED', 'message' => 'Email 或密碼不正確。',
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'remaining' => $remaining,
                ],
            ], 401);
        }

        if (in_array($user->status, ['suspended', 'auto_suspended'])) {
            return response()->json([
                'success' => false, 'code' => 'ACCOUNT_SUSPENDED', 'message' => '您的帳號已被暫停使用。',
            ], 403);
        }

        // Login success — clear email failure count (keep IP count)
        Cache::forget($emailKey);

        $token = $user->createToken('login')->plainTextToken;
        $user->update(['last_active_at' => now()]);

        UserActivityLogService::logLogin($user->id, $request);

        return response()->json([
            'success' => true, 'code' => 'LOGIN_SUCCESS', 'message' => '登入成功。',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'nickname' => $user->nickname,
                    'avatar' => $user->avatar_url,
                    'gender' => $user->gender,
                    'status' => $user->status,
                    'credit_score' => $user->credit_score,
                    'membership_level' => $user->membership_level,
                    'email_verified' => (bool) $user->email_verified,
                    'phone_verified' => (bool) $user->phone_verified,
                    'phone' => $user->phone ? $this->maskPhone($user->phone) : null,
                ],
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;

        if ($request->user()?->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        if ($userId && $request->has('fcm_token')) {
            \App\Models\FcmToken::where('user_id', $userId)
                ->where('token', $request->fcm_token)
                ->delete();
        }

        return response()->json([
            'success' => true, 'code' => 'LOGOUT_SUCCESS', 'message' => '已登出。',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'code' => 'UNAUTHENTICATED', 'message' => '請先登入。'], 401);
        }

        // F40 — 當前有效訂閱（如有）
        $subscription = \App\Models\Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->orderByDesc('expires_at')
            ->first();

        $subscriptionData = null;
        if ($subscription) {
            $plan = \App\Models\SubscriptionPlan::find($subscription->plan_id);
            $daysRemaining = $subscription->expires_at
                ? max(0, (int) now()->startOfDay()->diffInDays($subscription->expires_at, false))
                : null;
            $subscriptionData = [
                'plan_slug' => $plan?->slug,
                'plan_name' => $plan?->name,
                'status' => $subscription->status,
                'started_at' => $subscription->started_at?->toISOString(),
                'expires_at' => $subscription->expires_at?->toISOString(),
                'auto_renew' => (bool) ($subscription->auto_renew ?? false),
                'days_remaining' => $daysRemaining,
            ];
        }

        return response()->json([
            'success' => true, 'code' => 'USER_PROFILE', 'message' => 'OK',
            'data' => ['user' => [
                'id' => $user->id,
                'email' => $user->email,
                'nickname' => $user->nickname,
                'avatar' => $user->avatar_url,
                'gender' => $user->gender,
                'status' => $user->status,
                'credit_score' => $user->credit_score,
                'membership_level' => $user->membership_level,
                'email_verified' => (bool) $user->email_verified,
                'phone_verified' => (bool) $user->phone_verified,
                'phone' => $user->phone ? $this->maskPhone($user->phone) : null,
                // F40
                'points_balance' => (int) ($user->points_balance ?? 0),
                'stealth_until' => $user->stealth_until?->toISOString(),
                'stealth_active' => $user->isStealthActive(),
                'subscription' => $subscriptionData,
            ]],
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'verification_code' => 'required|string|size:6',
            'email' => 'required|email',
        ]);

        $storedCode = Cache::get("email_verification:{$request->email}");

        if (!$storedCode || $storedCode !== $request->verification_code) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_CODE',
                'message' => '驗證碼錯誤或已過期，請重新申請。',
            ], 422);
        }

        // Mark email as verified
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->forceFill(['email_verified' => true])->save();
            if ($user->wasChanged('email_verified')) {
                \App\Services\CreditScoreService::adjust($user, \App\Services\CreditScoreService::getConfig('credit_add_email_verify', 5), 'email_verified', 'Email 驗證完成');
            }
        }

        Cache::forget("email_verification:{$request->email}");

        return response()->json(['success' => true, 'code' => 'EMAIL_VERIFIED', 'message' => '信箱驗證成功。']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('data.email') ?? $request->input('email');
        $nickname = '用戶'; // mock，實際上應從 DB 查詢

        // 檢查冷卻（60秒）
        $cooldownKey = "email_verification_cooldown:{$email}";
        if (Cache::has($cooldownKey)) {
            return response()->json([
                'success' => false,
                'code' => 'TOO_MANY_REQUESTS',
                'message' => '請稍後再試，60 秒內只能重新發送一次。',
            ], 429);
        }

        // 嘗試從 DB 取得 nickname
        $user = User::where('email', $email)->first();
        if ($user) {
            $nickname = $user->nickname;
        }

        // 產生新驗證碼
        $code = (string) random_int(100000, 999999);
        Cache::put("email_verification:{$email}", $code, 600);
        Cache::put($cooldownKey, true, 60);

        try {
            Mail::to($email)->send(new EmailVerificationMail($nickname, $code));
        } catch (\Throwable $e) {
            Log::warning('[ResendVerification] Email send failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'code' => 'VERIFICATION_SENT',
            'message' => '驗證碼已重新寄出。',
        ]);
    }

    public function verifyPhoneSend(Request $request): JsonResponse
    {
        $request->validate(['phone' => ['required', 'string', 'regex:/^09\d{8}$/']]);

        $phone = $request->input('phone');
        $e164 = $this->toE164($phone);

        // Cooldown: 1 send per 60 seconds (keyed by phone)
        $cooldownKey = "otp:cooldown:{$e164}";
        if (Cache::has($cooldownKey)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => '1020', 'message' => '請等待 60 秒後再重新發送'],
            ], 429);
        }

        // Generate OTP and store in Redis (5 min TTL)
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpKey = "otp:phone:{$e164}";
        $attemptsKey = "otp:fail:{$e164}";

        Cache::put($otpKey, $code, 300);
        Cache::forget($attemptsKey);
        Cache::put($cooldownKey, true, 60);

        app(SmsService::class)->sendOtp($e164, $code);

        Log::info('[PhoneVerify] OTP sent', [
            'user_id' => auth()->guard('sanctum')->user()?->id,
            'phone' => substr($phone, 0, 4) . '****',
        ]);

        return response()->json([
            'success' => true, 'code' => 'PHONE_CODE_SENT', 'message' => '驗證碼已發送。',
            'data' => ['expires_in' => 300],
        ]);
    }

    public function verifyPhoneConfirm(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^09\d{8}$/'],
            'code' => 'required|string|size:6',
        ]);

        $e164 = $this->toE164($request->input('phone'));
        $inputCode = $request->input('code');

        $otpKey = "otp:phone:{$e164}";
        $attemptsKey = "otp:fail:{$e164}";

        $stored = Cache::get($otpKey);
        if (!$stored) {
            return response()->json([
                'success' => false,
                'error' => ['code' => '1021', 'message' => '驗證碼已過期或不存在，請重新發送'],
            ], 422);
        }

        $attempts = (int) Cache::get($attemptsKey, 0);
        if ($attempts >= 5) {
            Cache::forget($otpKey);
            Cache::forget($attemptsKey);
            return response()->json([
                'success' => false,
                'error' => ['code' => '1022', 'message' => '驗證失敗次數過多，請重新發送驗證碼'],
            ], 429);
        }

        if ($stored !== $inputCode) {
            Cache::put($attemptsKey, $attempts + 1, 300);
            return response()->json([
                'success' => false,
                'error' => ['code' => '1023', 'message' => '驗證碼不正確', 'remaining' => max(0, 4 - $attempts)],
            ], 422);
        }

        // Success — update user (resolve via guard since route has no auth middleware)
        $user = auth()->guard('sanctum')->user();
        if ($user) {
            $user->phone = $e164;
            $user->phone_verified = true;
            if ($user->membership_level < 1) {
                $user->membership_level = 1;
            }
            if (!$user->save()) {
                Log::error('[PhoneVerify] Failed to save user', ['user_id' => $user->id]);
            }
            UserActivityLogService::logPhoneChange($user->id, $request);
            if ($user->wasChanged('phone_verified')) {
                \App\Services\CreditScoreService::adjust($user, \App\Services\CreditScoreService::getConfig('credit_add_phone_verify', 5), 'phone_verified', '手機驗證完成');
            }
        } else {
            // Registration flow: mark phone as verified by email lookup
            $email = $request->input('email');
            if ($email) {
                $regUser = User::where('email', $email)->first();
                if ($regUser) {
                    $regUser->phone = $e164;
                    $regUser->phone_verified = true;
                    $regUser->save();
                    if ($regUser->wasChanged('phone_verified')) {
                        \App\Services\CreditScoreService::adjust($regUser, \App\Services\CreditScoreService::getConfig('credit_add_phone_verify', 5), 'phone_verified', '手機驗證完成');
                    }
                }
            }
        }

        Cache::forget($otpKey);
        Cache::forget($attemptsKey);

        return response()->json([
            'success' => true, 'code' => 'PHONE_VERIFIED', 'message' => '手機驗證成功。',
            'data' => [
                'phone_verified' => true,
                'membership_level' => $user?->membership_level,
            ],
        ]);
    }

    private function toE164(string $phone): string
    {
        $phone = preg_replace('/[\s\-]/', '', $phone);
        if (str_starts_with($phone, '09')) {
            return '+886' . substr($phone, 1);
        }
        if (str_starts_with($phone, '+')) {
            return $phone;
        }
        return '+886' . ltrim($phone, '0');
    }

    /**
     * Mask E.164 phone for API responses: +886912345678 → 09xx-xxx-678
     */
    private function maskPhone(string $phone): string
    {
        // Convert E.164 back to local format
        if (str_starts_with($phone, '+886')) {
            $local = '0' . substr($phone, 4); // +886912345678 → 0912345678
        } else {
            $local = $phone;
        }

        if (strlen($local) === 10) {
            return substr($local, 0, 2) . 'xx-xxx-' . substr($local, -3);
        }

        // Fallback: mask middle
        return substr($local, 0, 3) . '****' . substr($local, -3);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        // Always return the same message to prevent email enumeration
        $successResponse = ['success' => true, 'code' => 'RESET_LINK_SENT', 'message' => '若此信箱已註冊，密碼重設連結已寄出。'];

        $user = User::where('email', $request->email)->where('status', '!=', 'deleted')->first();
        if (!$user) {
            return response()->json($successResponse);
        }

        // Rate limit: 1 reset email per 60 seconds per email
        $cooldownKey = "password_reset_cooldown:{$user->email}";
        if (Cache::has($cooldownKey)) {
            return response()->json($successResponse);
        }

        // Generate secure token and store in password_reset_tokens table
        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()],
        );

        // Build reset URL (frontend hash router)
        $frontendUrl = rtrim(config('app.frontend_url', 'https://mimeet.online'), '/');
        $resetUrl = $frontendUrl . '/#/reset-password?token=' . $token . '&email=' . urlencode($user->email);

        try {
            Mail::to($user->email)->send(new ResetPasswordMail($user->nickname ?? '用戶', $resetUrl));
            Cache::put($cooldownKey, true, 60);
        } catch (\Throwable $e) {
            Log::error('[ForgotPassword] Mail send failed', ['email' => $user->email, 'error' => $e->getMessage()]);
        }

        return response()->json($successResponse);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => '1010', 'message' => '重設連結已失效，請重新申請'],
            ], 422);
        }

        // Check expiry (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'success' => false,
                'error' => ['code' => '1010', 'message' => '重設連結已失效，請重新申請'],
            ], 422);
        }

        // Update password
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => ['code' => '1006', 'message' => '找不到此帳號'],
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Delete used token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Revoke all existing tokens (force re-login on all devices)
        $user->tokens()->delete();

        Log::info('[ResetPassword] Password reset successful', ['user_id' => $user->id, 'email' => $user->email]);

        return response()->json(['success' => true, 'code' => 'PASSWORD_RESET', 'message' => '密碼已重設，請重新登入。']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'PASSWORD_INCORRECT', 'message' => '目前密碼不正確'],
            ], 422);
        }

        if ($request->input('current_password') === $request->input('password')) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'PASSWORD_SAME_AS_CURRENT', 'message' => '新密碼不可與目前密碼相同'],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->input('password'))]);

        $user->tokens()->delete();

        try {
            Log::info('[Auth] User changed password', ['user_id' => $user->id]);
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'code'    => 'PASSWORD_CHANGED',
            'message' => '密碼已更新，所有裝置已登出',
        ]);
    }
}
