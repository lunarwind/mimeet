<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'nickname' => 'required|string|max:20',
            'gender' => 'required|in:male,female',
            'birth_date' => 'required|date|before:-18 years',
        ]);

        // Mock response for dev
        $mockUser = [
            'id' => 'usr_' . Str::random(12),
            'email' => $request->email,
            'nickname' => $request->nickname,
            'gender' => $request->gender,
            'birth_date' => $request->birth_date,
            'avatar_url' => null,
            'bio' => null,
            'membership_level' => 0,
            'credit_score' => 100,
            'email_verified' => false,
            'phone_verified' => false,
            'status' => 'active',
            'created_at' => now()->toISOString(),
        ];

        return response()->json([
            'success' => true,
            'code' => 'REGISTER_SUCCESS',
            'message' => '註冊成功，請驗證信箱。',
            'data' => [
                'user' => $mockUser,
            ],
        ], 201);
    }

    /**
     * Login with email and password.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Dev mock: accept any login with test accounts
        $mockUsers = [
            'alice@test.com' => ['id' => 'usr_alice001', 'nickname' => 'Alice', 'gender' => 'female', 'membership_level' => 2, 'status' => 'active'],
            'bob@test.com' => ['id' => 'usr_bob002', 'nickname' => 'Bob', 'gender' => 'male', 'membership_level' => 1, 'status' => 'active'],
            'carol@test.com' => ['id' => 'usr_carol003', 'nickname' => 'Carol', 'gender' => 'female', 'membership_level' => 0, 'status' => 'active'],
            'dave@test.com' => ['id' => 'usr_dave004', 'nickname' => 'Dave', 'gender' => 'male', 'membership_level' => 2, 'status' => 'active'],
            'eve@test.com' => ['id' => 'usr_eve005', 'nickname' => 'Eve', 'gender' => 'female', 'membership_level' => 1, 'status' => 'suspended'],
        ];

        $email = $request->email;
        $mock = $mockUsers[$email] ?? [
            'id' => 'usr_' . Str::random(12),
            'nickname' => 'TestUser',
            'gender' => 'male',
            'membership_level' => 0,
            'status' => 'active',
        ];

        // Suspended users cannot login
        if ($mock['status'] === 'suspended') {
            return response()->json([
                'success' => false,
                'code' => 'ACCOUNT_SUSPENDED',
                'message' => '您的帳號已被暫停使用。',
            ], 403);
        }

        $userData = [
            'id' => $mock['id'],
            'email' => $email,
            'nickname' => $mock['nickname'],
            'gender' => $mock['gender'],
            'birth_date' => '1995-06-15',
            'avatar_url' => null,
            'bio' => null,
            'membership_level' => $mock['membership_level'],
            'credit_score' => 100,
            'email_verified' => true,
            'phone_verified' => false,
            'status' => $mock['status'],
            'created_at' => now()->subDays(30)->toISOString(),
        ];

        // In dev mode, return mock token via cookie
        $token = 'mock_token_' . Str::random(40);

        return response()->json([
            'success' => true,
            'code' => 'LOGIN_SUCCESS',
            'message' => '登入成功。',
            'data' => [
                'user' => $userData,
                'token' => $token,
            ],
        ])->cookie('mimeet_session', $token, 120, '/', config('session.domain'), false, true, false, 'lax');
    }

    /**
     * Logout the current user.
     */
    public function logout(Request $request): JsonResponse
    {
        // Clear token / session
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'code' => 'LOGOUT_SUCCESS',
            'message' => '已登出。',
        ])->cookie('mimeet_session', '', -1);
    }

    /**
     * Get the current authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'code' => 'UNAUTHENTICATED',
                'message' => '請先登入。',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'code' => 'USER_PROFILE',
            'message' => 'OK',
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    /**
     * Verify email with code.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'verification_code' => 'required|string|size:6',
        ]);

        return response()->json([
            'success' => true,
            'code' => 'EMAIL_VERIFIED',
            'message' => '信箱驗證成功。',
        ]);
    }

    /**
     * Send phone verification code.
     */
    public function verifyPhoneSend(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        // Mock: log the code
        $code = '123456';
        \Illuminate\Support\Facades\Log::info("Phone verification code for {$request->phone}: {$code}");

        return response()->json([
            'success' => true,
            'code' => 'PHONE_CODE_SENT',
            'message' => '驗證碼已發送。',
            'data' => [
                'expires_in' => 300,
            ],
        ]);
    }

    /**
     * Confirm phone verification code.
     */
    public function verifyPhoneConfirm(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        return response()->json([
            'success' => true,
            'code' => 'PHONE_VERIFIED',
            'message' => '手機驗證成功。',
        ]);
    }

    /**
     * Send password reset link.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        return response()->json([
            'success' => true,
            'code' => 'RESET_LINK_SENT',
            'message' => '若此信箱已註冊，密碼重設連結已寄出。',
        ]);
    }

    /**
     * Reset password with token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        return response()->json([
            'success' => true,
            'code' => 'PASSWORD_RESET',
            'message' => '密碼已重設，請重新登入。',
        ]);
    }
}
