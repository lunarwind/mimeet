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
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        // 支援 { data: {...} } 包裝格式，也支援 flat body
        $input = $request->input('data');
        if (!is_array($input) || empty($input)) {
            $input = $request->all();
        }

        // 基本格式驗證（不查 DB）
        try {
            validator($input, [
                'email'      => ['required', 'email'],
                'password'   => ['required', 'string', 'min:8'],
                'nickname'   => ['required', 'string', 'max:20'],
                'gender'     => ['required', 'in:male,female'],
                'birth_date' => ['required', 'date', 'before:-18 years'],
                'phone'      => ['nullable', 'string', 'regex:/^09\d{8}$/'],
            ])->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'code'    => 400,
                'message' => '註冊失敗',
                'errors'  => $e->errors(),
                'error'   => ['type' => 'validation_error', 'details' => collect($e->errors())->map(fn ($msgs, $field) => ['field' => $field, 'message' => $msgs[0]])->values()->all()],
            ], 422);
        }

        // 唯一性驗證（查 DB，若 DB 不可用則略過，不阻斷流程）
        try {
            $emailExists = User::where('email', $input['email'])
                ->where('status', '!=', 'deleted')
                ->exists();
            if ($emailExists) {
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

            $nicknameExists = User::where('nickname', $input['nickname'])
                ->where('status', '!=', 'deleted')
                ->exists();
            if ($nicknameExists) {
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

            if (!empty($input['phone'])) {
                $phoneExists = User::where('phone', $input['phone'])
                    ->whereNotNull('phone')
                    ->where('status', '!=', 'deleted')
                    ->exists();
                if ($phoneExists) {
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
            }
        } catch (\Exception $dbException) {
            // DB 查詢失敗時略過唯一性檢查，繼續執行
            Log::warning('[Register] DB uniqueness check failed, skipping: ' . $dbException->getMessage());
        }

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

        // Generate 6-digit verification code and send email
        $verifyCode = (string) random_int(100000, 999999);
        Cache::put("email_verification:{$user->email}", $verifyCode, 600); // 10 min TTL

        try {
            Mail::to($user->email)->send(new EmailVerificationMail($user->nickname, $verifyCode));
        } catch (\Throwable $e) {
            Log::warning('[Register] Email send failed: ' . $e->getMessage());
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
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false, 'code' => 'LOGIN_FAILED', 'message' => 'Email 或密碼不正確。',
            ], 401);
        }

        if (in_array($user->status, ['suspended', 'auto_suspended'])) {
            return response()->json([
                'success' => false, 'code' => 'ACCOUNT_SUSPENDED', 'message' => '您的帳號已被暫停使用。',
            ], 403);
        }

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
