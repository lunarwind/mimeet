<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\VerificationMail;
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

        // Generate 6-digit verification code and send email
        $verifyCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("email_verify:{$user->email}", $verifyCode, 600); // 10 min TTL

        try {
            $mailService = new \App\Services\MailService();
            $mailService->send(
                $user->email,
                '【MiMeet】Email 驗證碼',
                (new VerificationMail($verifyCode, $user->nickname))->render(),
            );
        } catch (\Throwable $e) {
            // Fallback: try Laravel Mail directly
            try { Mail::to($user->email)->send(new VerificationMail($verifyCode, $user->nickname)); } catch (\Throwable) {}
            try { Log::warning('[Register] Email send: ' . $e->getMessage()); } catch (\Throwable) {}
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

        $storedCode = Cache::get("email_verify:{$request->email}");

        if (!$storedCode || $storedCode !== $request->verification_code) {
            return response()->json([
                'success' => false, 'code' => 422, 'message' => '驗證碼不正確或已過期。',
            ], 422);
        }

        // Mark email as verified
        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->forceFill(['email_verified' => true])->save();
        }

        Cache::forget("email_verify:{$request->email}");

        return response()->json(['success' => true, 'code' => 'EMAIL_VERIFIED', 'message' => '信箱驗證成功。']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['success' => true, 'message' => '若信箱已註冊，驗證碼已重新發送。']);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("email_verify:{$request->email}", $code, 600);

        try {
            Mail::to($request->email)->send(new VerificationMail($code, $user->nickname));
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'message' => '驗證碼已重新發送。']);
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
