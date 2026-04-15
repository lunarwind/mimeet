<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationMail;
use App\Models\User;
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
            'email'      => ['required', 'email'],
            'password'   => ['required', 'string', 'min:8'],
            'nickname'   => ['required', 'string', 'max:20'],
            'gender'     => ['required', 'in:male,female'],
            'birth_date' => ['required', 'date', 'before:-18 years'],
            'phone'      => ['nullable', 'string', 'regex:/^09\d{8}$/'],
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
        $user = User::create([
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
            'nickname' => $input['nickname'],
            'gender' => $input['gender'],
            'birth_date' => $input['birth_date'],
            'phone' => $input['phone'] ?? null,
            'membership_level' => 0,
            'credit_score' => 60,
            'status' => 'active',
        ]);

        $token = $user->createToken('register')->plainTextToken;

        // ── Debug：收集 mail 診斷資訊 ⚠️ DEBUG ONLY — 問題解決後整段刪除 ──
        $debugInfo = [
            'mailer'       => config('mail.default'),
            'host'         => config('mail.mailers.' . config('mail.default') . '.host', 'N/A'),
            'port'         => config('mail.mailers.' . config('mail.default') . '.port', 'N/A'),
            'from_address' => config('mail.from.address'),
            'from_name'    => config('mail.from.name'),
            'otp_code'     => null,
            'otp_cached'   => false,
            'mail_sent'    => false,
            'mail_error'   => null,
            'timestamp'    => now()->toISOString(),
        ];

        // Generate 6-digit verification code and cache it
        $verifyCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = "email_verification:{$user->email}";

        try {
            Cache::put($cacheKey, $verifyCode, 600);
            $debugInfo['otp_code']   = $verifyCode; // ⚠️ DEBUG ONLY
            $debugInfo['otp_cached'] = true;
        } catch (\Throwable $cacheEx) {
            $debugInfo['mail_error'] = 'Cache failed: ' . $cacheEx->getMessage();
        }

        // Attempt to send verification email
        if ($debugInfo['otp_cached']) {
            try {
                Mail::to($user->email)->send(new EmailVerificationMail($user->nickname, $verifyCode));
                $debugInfo['mail_sent'] = true;
            } catch (\Throwable $e) {
                $debugInfo['mail_error'] = $e->getMessage();
                Log::error('[Register] Email send failed', [
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
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
                ],
                'token' => $token,
                '_debug' => $debugInfo, // ⚠️ DEBUG ONLY — 問題解決後整段刪除
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
                ],
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->user()?->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
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
        $request->validate(['phone' => 'required|string']);
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        // TODO: use SmsService to send real OTP
        Log::info("[OTP] Phone verification code for {$request->phone}: {$code}");

        return response()->json([
            'success' => true, 'code' => 'PHONE_CODE_SENT', 'message' => '驗證碼已發送。',
            'data' => ['expires_in' => 300],
        ]);
    }

    public function verifyPhoneConfirm(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string', 'code' => 'required|string|size:6']);
        // TODO: verify against stored OTP code

        UserActivityLogService::logPhoneChange($request->user()->id, $request);

        return response()->json(['success' => true, 'code' => 'PHONE_VERIFIED', 'message' => '手機驗證成功。']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        // TODO: send password reset email
        return response()->json(['success' => true, 'code' => 'RESET_LINK_SENT', 'message' => '若此信箱已註冊，密碼重設連結已寄出。']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string', 'email' => 'required|email', 'password' => 'required|string|min:8|confirmed']);
        // TODO: verify reset token and update password
        return response()->json(['success' => true, 'code' => 'PASSWORD_RESET', 'message' => '密碼已重設，請重新登入。']);
    }
}
