<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\DateInvitationController;
use App\Http\Controllers\Api\V1\DateController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\PaymentCallbackController;
use App\Http\Controllers\Api\Admin\ChatLogController;
use App\Http\Controllers\Api\V1\AppealController;
use App\Http\Controllers\Api\V1\PrivacyController;
use App\Http\Controllers\Api\V1\DeleteAccountController;
use App\Http\Controllers\Api\V1\Admin\SystemControlController;
use App\Http\Controllers\Api\V1\Admin\DatasetController;
use App\Http\Controllers\Api\V1\Admin\MemberLevelPermissionController;
use App\Http\Controllers\Api\V1\Admin\VerificationController;
use App\Http\Controllers\Api\V1\Admin\BroadcastController;
use App\Http\Controllers\Api\V1\Admin\AdminLogController;
use App\Http\Controllers\Api\V1\Admin\AdminCrudController;
use App\Http\Controllers\Api\V1\VerificationPhotoController;
use App\Http\Controllers\Api\V1\Admin\ECPaySettingController;
use App\Http\Controllers\Api\V1\Admin\UserActivityLogController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1')->group(function () {

    // ─── Auth (public, rate-limited) ────────────────────────────────────
    Route::prefix('auth')->middleware('throttle:10,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // ─── Auth (authenticated) ────────────────────────────────────────
    Route::prefix('auth')->middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/verify-phone/send', [AuthController::class, 'verifyPhoneSend'])->middleware('throttle:5,1');
        Route::post('/verify-phone/confirm', [AuthController::class, 'verifyPhoneConfirm'])->middleware('throttle:5,1');
    });

    // ─── Users (authenticated, rate-limited) ───────────────────────────
    Route::prefix('users')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('/me', [UserController::class, 'me']);
        Route::patch('/me', [UserController::class, 'update']);
        Route::get('/me/settings', [UserController::class, 'settings']);
        Route::post('/me/photos', [UserController::class, 'uploadPhoto']);
        Route::get('/search', [UserController::class, 'search']);
        Route::get('/me/following', [UserController::class, 'following']);
        Route::get('/me/visitors', [UserController::class, 'visitors']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/{id}/follow', [UserController::class, 'follow']);
        Route::delete('/{id}/follow', [UserController::class, 'unfollow']);
        Route::post('/{id}/block', [UserController::class, 'block']);
        Route::delete('/{id}/block', [UserController::class, 'unblock']);
    });

    // ─── Me (authenticated) — blocked users ──────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me/blocked-users', [UserController::class, 'blockedUsers']);
    });

    // ─── Subscriptions (authenticated) ───────────────────────────────
    Route::prefix('subscriptions')->middleware('auth:sanctum')->group(function () {
        Route::get('/plans', [SubscriptionController::class, 'plans']);
        Route::get('/me', [SubscriptionController::class, 'mySubscription']);
        Route::post('/orders', [SubscriptionController::class, 'createOrder']);
        Route::patch('/me', [SubscriptionController::class, 'update']);
        Route::post('/cancel-request', [SubscriptionController::class, 'cancelRequest']);
    });

    Route::prefix('subscription')->middleware('auth:sanctum')->group(function () {
        Route::get('/trial', [SubscriptionController::class, 'trial']);
        Route::post('/trial/purchase', [SubscriptionController::class, 'trialPurchase']);
    });

    // ─── Chats (authenticated) ─────────────────────────────────────────
    Route::prefix('chats')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [ChatController::class, 'index'])->middleware('membership:2');
        Route::post('/', [ChatController::class, 'store'])->middleware('membership:2');
        Route::get('/{id}/messages', [ChatController::class, 'messages']);
        Route::post('/{id}/messages', [ChatController::class, 'sendMessage'])->middleware('membership:2');
        Route::patch('/{id}/read', [ChatController::class, 'markRead']);
        Route::delete('/{id}', [ChatController::class, 'destroy']);
    });

    // ─── Date Invitations (legacy) ─────────────────────────────────────
    Route::prefix('date-invitations')->middleware(['auth:sanctum', 'membership:2'])->group(function () {
        Route::post('/', [DateInvitationController::class, 'store']);
        Route::get('/', [DateInvitationController::class, 'index']);
        Route::patch('/{id}/response', [DateInvitationController::class, 'respond']);
        Route::post('/verify', [DateInvitationController::class, 'verify']);
    });

    // ─── Dates (authenticated) ──────────────────────────────────────────
    Route::prefix('dates')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [DateController::class, 'index'])->middleware('membership:2');
        Route::post('/', [DateController::class, 'store'])->middleware('membership:2');
        Route::patch('/{id}/accept', [DateController::class, 'accept']);
        Route::patch('/{id}/decline', [DateController::class, 'decline']);
        Route::post('/verify', [DateController::class, 'verify']);
    });

    // ─── Payment Callbacks (public — ECPay server-to-server) ──────────
    Route::prefix('payments/ecpay')->group(function () {
        Route::post('/notify', [PaymentCallbackController::class, 'notify']);
        Route::get('/return', [PaymentCallbackController::class, 'returnUrl']);
        Route::get('/mock', [PaymentCallbackController::class, 'mock']);
        Route::get('/checkout/{token}', [PaymentCallbackController::class, 'checkout']);
    });

    // ─── Reports (authenticated) ─────────────────────────────────────
    Route::prefix('reports')->middleware('auth:sanctum')->group(function () {
        Route::post('/', [ReportController::class, 'store']);
        Route::get('/', [ReportController::class, 'index']);
        Route::get('/history', [ReportController::class, 'history']);
        Route::delete('/{id}', [ReportController::class, 'destroy']);
    });

    // ─── Appeal (authenticated — suspended users can access) ─────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('me/appeal', [AppealController::class, 'store']);
        Route::get('me/appeal/current', [AppealController::class, 'current']);
    });

    // ─── Privacy (authenticated) ────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me/privacy', [PrivacyController::class, 'index']);
        Route::patch('me/privacy', [PrivacyController::class, 'update']);
    });

    // ─── Account Deletion (authenticated) ───────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('me/delete-account', [DeleteAccountController::class, 'store']);
        Route::delete('me/delete-account', [DeleteAccountController::class, 'cancel']);
    });

    // ─── Verification Photo / Lv1.5 (Sprint 11) ─────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('me/verification-photo/request', [VerificationPhotoController::class, 'request']);
        Route::post('me/verification-photo/upload', [VerificationPhotoController::class, 'upload']);
        Route::get('me/verification-photo/status', [VerificationPhotoController::class, 'status']);
    });

    // ─── Notifications (authenticated) ───────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead']);
    });

    // ─── Admin ───────────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {
        Route::post('/auth/login', [AdminController::class, 'login'])->middleware('throttle:5,1');

        Route::middleware(['admin.auth', 'admin.log'])->group(function () {
            Route::get('/members', [AdminController::class, 'members']);
            Route::get('/members/{id}', [AdminController::class, 'memberDetail']);
            Route::patch('/members/{id}/actions', [AdminController::class, 'memberAction']);
            Route::patch('/members/{id}/permissions', [AdminController::class, 'updatePermissions']);
            Route::patch('/members/{id}/profile', [AdminController::class, 'updateProfile']);
            Route::delete('/members/{id}', [AdminController::class, 'deleteMember']);
            Route::get('/tickets', [AdminController::class, 'tickets']);
            Route::patch('/tickets/{id}', [AdminController::class, 'updateTicket']);
            Route::patch('/tickets/{id}/status', [TicketController::class, 'updateStatus']);
            Route::post('/tickets/{id}/reply', [TicketController::class, 'reply']);
            Route::get('/payments', [AdminController::class, 'payments']);
            Route::get('/settings', [AdminController::class, 'getSettings']);
            Route::patch('/settings', [AdminController::class, 'updateSettings']);

            // Chat logs (admin only)
            Route::get('/chat-logs/search', [ChatLogController::class, 'search']);
            Route::get('/chat-logs/conversations', [ChatLogController::class, 'conversations']);
            Route::get('/chat-logs/export', [ChatLogController::class, 'export']);
            Route::get('/members/{userId}/chat-logs', [ChatLogController::class, 'memberChatLogs']);

            // Verification review (Sprint 11)
            Route::get('/verifications', [VerificationController::class, 'index']);
            Route::get('/verifications/pending', [VerificationController::class, 'pending']);
            Route::patch('/verifications/{id}', [VerificationController::class, 'review']);

            // Broadcasts (Sprint 11)
            Route::get('/broadcasts', [BroadcastController::class, 'index']);
            Route::post('/broadcasts', [BroadcastController::class, 'store']);
            Route::get('/broadcasts/{id}', [BroadcastController::class, 'show']);
            Route::post('/broadcasts/{id}/send', [BroadcastController::class, 'send']);

            // Operation logs (Sprint 11)
            Route::get('/logs', [AdminLogController::class, 'index']);

            // User activity logs (super_admin only)
            Route::get('/user-activity-logs', [UserActivityLogController::class, 'index']);

            // System Control (super_admin only)
            Route::middleware('check.super_admin')->prefix('settings')->group(function () {
                Route::get('system-control', [SystemControlController::class, 'index']);
                Route::patch('app-mode', [SystemControlController::class, 'updateAppMode']);
                Route::get('system/app-mode', [SystemControlController::class, 'getAppMode']);
                Route::patch('mail', [SystemControlController::class, 'updateMail']);
                Route::post('mail/test', [SystemControlController::class, 'testMail']);
                Route::patch('sms', [SystemControlController::class, 'updateSms']);
                Route::post('sms/test', [SystemControlController::class, 'testSms']);
                Route::patch('database', [SystemControlController::class, 'updateDatabase']);
                Route::post('database/test', [SystemControlController::class, 'testDatabase']);

                // Dataset management
                Route::get('dataset/stats', [DatasetController::class, 'stats']);
                Route::post('dataset/reset', [DatasetController::class, 'reset']);
                Route::post('dataset/seed', [DatasetController::class, 'seed']);

                // Member level permissions (Sprint 11)
                Route::get('member-level-permissions', [MemberLevelPermissionController::class, 'index']);
                Route::patch('member-level-permissions', [MemberLevelPermissionController::class, 'update']);

                // Permission matrix JSON (simplified view)
                Route::get('permission-matrix', [MemberLevelPermissionController::class, 'matrix']);
                Route::patch('permission-matrix', [MemberLevelPermissionController::class, 'updateMatrix']);

                // Admin CRUD (Sprint 11)
                Route::get('admins', [AdminCrudController::class, 'index']);
                Route::post('admins', [AdminCrudController::class, 'store']);
                Route::patch('admins/{id}/role', [AdminCrudController::class, 'updateRole']);
                Route::delete('admins/{id}', [AdminCrudController::class, 'destroy']);
                Route::post('admins/{id}/reset-password', [AdminCrudController::class, 'resetPassword']);
                Route::get('roles', [AdminCrudController::class, 'roles']);

                // ECPay settings (Sprint 13)
                Route::get('ecpay', [ECPaySettingController::class, 'index']);
                Route::post('ecpay', [ECPaySettingController::class, 'update']);
            });
        });
    });

    // ─── Dev endpoints (local only) ─────────────────────────────────
    if (app()->environment('local')) {
        Route::get('dev/test-accounts', function () {
            return response()->json([
                'success' => true,
                'data' => \App\Models\User::where('email', 'like', '%@test.tw')
                    ->select('id', 'email', 'nickname', 'gender', 'membership_level', 'credit_score', 'status')
                    ->get(),
            ]);
        });
    }
});
