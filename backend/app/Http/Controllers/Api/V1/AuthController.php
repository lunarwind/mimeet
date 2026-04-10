<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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
