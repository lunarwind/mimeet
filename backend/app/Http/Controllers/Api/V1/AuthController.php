<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmsService;
use App\Services\UserActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'string', 'min:8', 'regex:/[a-zA-Z]/', 'regex:/[0-9]/'],
            'nickname' => 'required|string|max:20',
            'gender' => 'required|in:male,female',
            'birth_date' => 'required|date|before:-18 years',
        ]);

        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nickname' => $request->nickname,
            'gender' => $request->gender,
            'birth_date' => $request->birth_date,
            'membership_level' => 0,
            'credit_score' => 60,
            'status' => 'active',
        ]);

        $token = $user->createToken('register')->plainTextToken;

        return response()->json([
            'success' => true,
            'code' => 'REGISTER_SUCCESS',
            'message' => '註冊成功，請驗證信箱。',
            'data' => ['user' => $user, 'token' => $token],
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
            'data' => ['user' => $user, 'token' => $token],
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
            'data' => ['user' => $user],
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate(['verification_code' => 'required|string|size:6']);
        // TODO: implement real email verification with codes table
        return response()->json(['success' => true, 'code' => 'EMAIL_VERIFIED', 'message' => '信箱驗證成功。']);
    }

    public function verifyPhoneSend(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string']);
        $phone = preg_replace('/[\s\-]/', '', $request->phone);

        // Cooldown: 60 seconds between OTP sends
        $cooldownKey = "otp:cooldown:{$phone}";
        if (Cache::has($cooldownKey)) {
            return response()->json([
                'success' => false, 'code' => 429, 'message' => '請稍候再試，60 秒內只能發送一次驗證碼。',
            ], 429);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in cache (10 minutes)
        Cache::put("otp:phone:{$phone}", $code, 600);
        Cache::put($cooldownKey, true, 60);

        // Send via SmsService
        $smsService = app(SmsService::class);
        $sent = $smsService->sendOtp($phone, $code);

        Log::info("[OTP] Phone verification for {$phone}: " . ($sent ? 'sent' : 'failed'));

        return response()->json([
            'success' => true, 'code' => 'PHONE_CODE_SENT', 'message' => '驗證碼已發送。',
            'data' => ['expires_in' => 600],
        ]);
    }

    public function verifyPhoneConfirm(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string', 'code' => 'required|string|size:6']);
        $phone = preg_replace('/[\s\-]/', '', $request->phone);

        // Check failure count (max 5 attempts, lock 30 min)
        $failKey = "otp:fail:{$phone}";
        $failures = (int) Cache::get($failKey, 0);
        if ($failures >= 5) {
            return response()->json([
                'success' => false, 'code' => 429, 'message' => '驗證失敗次數過多，請 30 分鐘後再試。',
            ], 429);
        }

        $storedCode = Cache::get("otp:phone:{$phone}");
        if (!$storedCode || $storedCode !== $request->code) {
            Cache::put($failKey, $failures + 1, 1800); // Lock for 30 min
            return response()->json([
                'success' => false, 'code' => 422, 'message' => '驗證碼不正確或已過期。',
            ], 422);
        }

        // Success — clear OTP and failure count
        Cache::forget("otp:phone:{$phone}");
        Cache::forget($failKey);

        // Update user phone
        $user = $request->user();
        $user->update(['phone' => $phone, 'phone_verified' => true]);

        UserActivityLogService::logPhoneChange($user->id, $request);

        return response()->json(['success' => true, 'code' => 'PHONE_VERIFIED', 'message' => '手機驗證成功。']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        // Always return success to prevent email enumeration
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            // In production: send email with reset link
            // For now: log the token (email sending requires mail config)
            $resetUrl = env('FRONTEND_URL', 'http://localhost:5173') . "/#/reset-password?token={$token}&email=" . urlencode($request->email);
            Log::info("[Password Reset] URL for {$request->email}: {$resetUrl}");
        }

        return response()->json(['success' => true, 'code' => 'RESET_LINK_SENT', 'message' => '若此信箱已註冊，密碼重設連結已寄出。']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();
        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json([
                'success' => false, 'code' => 422, 'message' => '重設連結無效或已過期。',
            ], 422);
        }

        // Check expiry (60 minutes)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'success' => false, 'code' => 422, 'message' => '重設連結已過期，請重新申請。',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'code' => 404, 'message' => '找不到此帳號。'], 404);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Revoke all existing tokens (force re-login)
        $user->tokens()->delete();

        // Clean up reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['success' => true, 'code' => 'PASSWORD_RESET', 'message' => '密碼已重設，請重新登入。']);
    }
}
