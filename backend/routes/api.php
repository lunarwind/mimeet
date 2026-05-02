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
use App\Http\Controllers\Api\V1\PointController;
use App\Http\Controllers\Api\V1\StealthController;
use App\Http\Controllers\Api\V1\SuperLikeController;
use App\Http\Controllers\Api\V1\UserBroadcastController;
use App\Http\Controllers\Api\V1\SiteConfigController;
use App\Http\Controllers\Api\V1\Admin\AdminPointController;
use App\Http\Controllers\Api\V1\Admin\StatsController;
use App\Http\Controllers\Api\Admin\ChatLogController;
use App\Http\Controllers\Api\V1\AppealController;
use App\Http\Controllers\Api\V1\PrivacyController;
use App\Http\Controllers\Api\V1\DndController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\DeleteAccountController;
use App\Http\Controllers\Api\V1\Admin\SystemControlController;
use App\Http\Controllers\Api\V1\Admin\DatasetController;
use App\Http\Controllers\Api\V1\Admin\MemberLevelPermissionController;
use App\Http\Controllers\Api\V1\Admin\VerificationController;
use App\Http\Controllers\Api\V1\Admin\BroadcastController;
use App\Http\Controllers\Api\V1\FcmTokenController;
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

    // ─── Public site config (不需登入) ───────────────────────────────────
    Route::get('/site-config', [SiteConfigController::class, 'index']);

    // ─── Auth (public, rate-limited) ────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:otp');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:otp');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:otp');
    });

    // ─── Auth (authenticated) ────────────────────────────────────────
    // Whitelist：/auth/logout 與 /auth/me 不擋停權帳號（停權者要能讀自己 status + 登出）
    Route::prefix('auth')->middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])
            ->withoutMiddleware('check.suspended');
        Route::get('/me', [AuthController::class, 'me'])
            ->withoutMiddleware('check.suspended');
    });

    // ─── Phone verify (requires auth — A-001/G-001 fix) ─────────────
    Route::prefix('auth')->middleware(['auth:sanctum', 'check.suspended', 'throttle:otp'])->group(function () {
        Route::post('/verify-phone/send', [AuthController::class, 'verifyPhoneSend']);
        Route::post('/verify-phone/confirm', [AuthController::class, 'verifyPhoneConfirm']);
    });

    // ─── Media upload (authenticated, upload rate-limited) ──────────────
    Route::post('/uploads', [MediaController::class, 'upload'])
        ->middleware(['auth:sanctum', 'check.suspended', 'throttle:upload']);
    Route::delete('/uploads', [MediaController::class, 'delete'])
        ->middleware(['auth:sanctum', 'check.suspended']);

    // ─── Users (authenticated, rate-limited) ───────────────────────────
    Route::prefix('users')->middleware(['auth:sanctum', 'check.suspended', 'throttle:api'])->group(function () {
        Route::get('/me', [UserController::class, 'me']);
        Route::patch('/me', [UserController::class, 'update']);
        Route::get('/me/settings', [UserController::class, 'settings']);
        Route::post('/me/photos', [UserController::class, 'uploadPhoto']);
        Route::get('/me/avatars', [UserController::class, 'getAvatarSlots']);
        Route::post('/me/avatars', [UserController::class, 'uploadAvatar']);
        Route::patch('/me/avatars/active', [UserController::class, 'setActiveAvatar']);
        Route::delete('/me/avatars', [UserController::class, 'deleteAvatar']);
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
    Route::middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::get('/me/blocked-users', [UserController::class, 'blockedUsers']);
    });

    // ─── Subscriptions plans (public — D-001 fix) ────────────────────
    Route::get('/subscriptions/plans', [SubscriptionController::class, 'plans']);

    // ─── Subscriptions (authenticated) ───────────────────────────────
    Route::prefix('subscriptions')->middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::get('/me', [SubscriptionController::class, 'mySubscription']);
        Route::post('/orders', [SubscriptionController::class, 'createOrder']);
        Route::patch('/me', [SubscriptionController::class, 'update']);
        Route::post('/cancel-request', [SubscriptionController::class, 'cancelRequest']);
    });

    Route::prefix('subscription')->middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::get('/trial', [SubscriptionController::class, 'trial']);
        Route::post('/trial/purchase', [SubscriptionController::class, 'trialPurchase']);
    });

    // ─── Chats (authenticated) ─────────────────────────────────────────
    Route::prefix('chats')->middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::get('/', [ChatController::class, 'index'])->middleware('membership:2');
        Route::post('/', [ChatController::class, 'store'])->middleware('membership:2');
        Route::patch('/read-all', [ChatController::class, 'readAll']);
        Route::get('/{id}/info', [ChatController::class, 'info']);
        Route::get('/{id}/messages', [ChatController::class, 'messages']);
        Route::get('/{id}/messages/search', [ChatController::class, 'searchMessages']);
        Route::post('/{id}/messages', [ChatController::class, 'sendMessage'])->middleware('membership:2');
        Route::delete('/{id}/messages/{messageId}', [ChatController::class, 'recallMessage'])->middleware('membership:3');
        Route::patch('/{id}/read', [ChatController::class, 'markRead']);
        Route::patch('/{id}/mute', [ChatController::class, 'toggleMute']);
        Route::delete('/{id}', [ChatController::class, 'destroy']);
    });

    // ─── DND / Do Not Disturb (F22 Part B) ─────────────────────
    Route::middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::get('me/dnd', [DndController::class, 'show']);
        Route::patch('me/dnd', [DndController::class, 'update']);
    });

    // ─── Date Invitations (legacy) ─────────────────────────────────────
    Route::prefix('date-invitations')->middleware(['auth:sanctum', 'check.suspended', 'membership:2'])->group(function () {
        Route::post('/', [DateInvitationController::class, 'store']);
        Route::get('/', [DateInvitationController::class, 'index']);
        Route::patch('/{id}/response', [DateInvitationController::class, 'respond']);
        Route::post('/verify', [DateInvitationController::class, 'verify']);
    });

    // ─── Dates (authenticated) ──────────────────────────────────────────
    Route::prefix('dates')->middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::get('/', [DateController::class, 'index'])->middleware('membership:2');
        Route::post('/', [DateController::class, 'store'])->middleware('membership:2');
        Route::patch('/{id}/accept', [DateController::class, 'accept']);
        Route::patch('/{id}/decline', [DateController::class, 'decline']);
        Route::post('/verify', [DateController::class, 'verify']);
    });

    // ─── 統一金流 Callback（新 ECPay NotifyURL）──────────────────────
    Route::post('payments/callback', [\App\Http\Controllers\Api\V1\UnifiedPaymentController::class, 'callback']);
    // OrderResultURL：綠界以 POST 送瀏覽器 redirect，同時支援 GET（手動測試）
    Route::match(['get', 'post'], 'payments/return', [\App\Http\Controllers\Api\V1\UnifiedPaymentController::class, 'returnUrl']);
    // 訂單查詢（前端結果頁 polling 用，需登入）
    Route::get('payments/{order_no}', [\App\Http\Controllers\Api\V1\UnifiedPaymentController::class, 'show'])
         ->middleware(['auth:sanctum', 'check.suspended']);

    // ─── ECPay alias 路由（過渡期，ECPay 後台 NotifyURL 改指 /payments/callback 後可移除）
    Route::prefix('payments/ecpay')->group(function () {
        Route::post('/notify', [\App\Http\Controllers\Api\V1\UnifiedPaymentController::class, 'callback']);
        // OrderResultURL alias — 綠界以 POST 送，同時支援 GET
        Route::match(['get', 'post'], '/return', [\App\Http\Controllers\Api\V1\UnifiedPaymentController::class, 'returnUrl']);
        Route::get('/checkout/{token}', [PaymentCallbackController::class, 'checkout']);
        // mock / point-mock 已刪除（A' 階段：sandbox 走真綠界，不用自家 mock）
    });

    // ─── F40 Points (authenticated) ────────────────────────────────
    Route::prefix('points')->middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::get('/packages', [PointController::class, 'packages']);
        Route::post('/purchase', [PointController::class, 'purchase']);
        Route::get('/balance', [PointController::class, 'balance']);
        Route::get('/history', [PointController::class, 'history']);
    });

    // ─── F42 Stealth Mode (authenticated) ─────────────────────────
    Route::middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::get('me/stealth', [StealthController::class, 'status']);
        Route::post('me/stealth', [StealthController::class, 'activate']);
        Route::delete('me/stealth', [StealthController::class, 'deactivate']);
    });

    // ─── FCM Push Token (B-003/H-004) ─────────────────────────────
    Route::middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::post('me/fcm-token', [FcmTokenController::class, 'store']);
        Route::delete('me/fcm-token', [FcmTokenController::class, 'destroy']);
    });

    // ─── F40-c Super Like (authenticated) ─────────────────────────
    Route::middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::post('users/{id}/super-like', [SuperLikeController::class, 'store']);
    });

    // ─── F41 User Broadcasts (authenticated, membership:2+) ───────
    Route::prefix('broadcasts')->middleware(['auth:sanctum', 'check.suspended', 'membership:2'])->group(function () {
        Route::post('/preview', [UserBroadcastController::class, 'preview']);
        Route::post('/send', [UserBroadcastController::class, 'send']);
        Route::get('/my', [UserBroadcastController::class, 'history']);
    });

    // ─── Reports (authenticated) ─────────────────────────────────────
    Route::prefix('reports')->middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::post('/', [ReportController::class, 'store']);
        Route::get('/', [ReportController::class, 'index']);
        Route::get('/history', [ReportController::class, 'history']);
        Route::delete('/{id}', [ReportController::class, 'destroy']);
        Route::post('/{id}/followups', [ReportController::class, 'addFollowup']);
    });

    // ─── Appeal (authenticated — suspended users CAN access via withoutMiddleware) ─────────
    // 申訴是停權者唯一的對外介面，必須繞過 check.suspended
    Route::middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::post('me/appeal', [AppealController::class, 'store'])
            ->withoutMiddleware('check.suspended');
        Route::get('me/appeal/current', [AppealController::class, 'current'])
            ->withoutMiddleware('check.suspended');
    });

    // ─── Privacy (authenticated) ────────────────────────────────────
    Route::middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::get('me/privacy', [PrivacyController::class, 'index']);
        Route::patch('me/privacy', [PrivacyController::class, 'update']);
    });

    // ─── Change Password (authenticated) ────────────────────────────
    Route::middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::post('me/change-password', [AuthController::class, 'changePassword'])
            ->middleware('throttle:otp');
    });

    // ─── Account Deletion (authenticated) ───────────────────────────
    Route::middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::post('me/delete-account', [DeleteAccountController::class, 'store']);
        Route::delete('me/delete-account', [DeleteAccountController::class, 'cancel']);
    });

    // ─── Verification Photo / Lv1.5 (Sprint 11) ─────────────────────
    Route::middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::post('me/verification-photo/request', [VerificationPhotoController::class, 'request']);
        Route::post('me/verification-photo/upload', [VerificationPhotoController::class, 'upload']);
        Route::get('me/verification-photo/status', [VerificationPhotoController::class, 'status']);
    });

    // ─── Credit Card Verification (男性進階驗證) ──────────────────────
    Route::middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::post('verification/credit-card/initiate', [\App\Http\Controllers\Api\V1\CreditCardVerificationController::class, 'initiate']);
        Route::get('verification/credit-card/status', [\App\Http\Controllers\Api\V1\CreditCardVerificationController::class, 'status']);
    });
    // Public callbacks (ECPay server-to-server + browser return)
    Route::post('verification/credit-card/callback', [\App\Http\Controllers\Api\V1\CreditCardVerificationController::class, 'callback']);
    // OrderResultURL：綠界以 POST 送瀏覽器 redirect，同時支援 GET
    Route::match(['get', 'post'], 'verification/credit-card/return', [\App\Http\Controllers\Api\V1\CreditCardVerificationController::class, 'returnUrl']);

    // ─── Notifications (authenticated) ───────────────────────────────
    Route::middleware(['auth:sanctum', 'check.suspended'])->group(function () {
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead']);
    });

    // ─── Announcements (public) ─────────────────────────────────────
    Route::get('announcements/active', [\App\Http\Controllers\Admin\AnnouncementController::class, 'getActive']);

    // ─── Admin ───────────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {
        Route::post('/auth/login', [AdminController::class, 'login'])->middleware('throttle:admin-login');

        Route::middleware(['admin.auth', 'admin.log'])->group(function () {
            Route::get('/auth/me', [AdminController::class, 'me']);
            Route::post('/auth/logout', [AdminController::class, 'logout']);
            Route::get('/members', [AdminController::class, 'members'])->middleware('admin.permission:members.view');
            Route::get('/members/{id}', [AdminController::class, 'memberDetail'])->middleware('admin.permission:members.view');
            Route::get('/members/{id}/credit-logs', [AdminController::class, 'memberCreditLogs'])->middleware('admin.permission:members.view');
            Route::get('/members/{id}/subscriptions', [AdminController::class, 'memberSubscriptions'])->middleware('admin.permission:members.view');
            Route::patch('/members/{id}/actions', [AdminController::class, 'memberAction'])->middleware('admin.permission:members.edit');
            Route::patch('/members/{id}/permissions', [AdminController::class, 'updatePermissions'])->middleware('admin.permission:members.edit');
            Route::patch('/members/{id}/profile', [AdminController::class, 'updateProfile'])->middleware('admin.permission:members.edit');
            Route::delete('/members/{id}', [AdminController::class, 'deleteMember'])->middleware('admin.permission:members.delete');
            Route::post('/members/{id}/change-password', [AdminController::class, 'changeMemberPassword'])->middleware('admin.permission:members.edit');
            Route::post('/members/{id}/verify-email', [AdminController::class, 'forceVerifyEmail'])->middleware('admin.permission:members.edit');
            Route::get('/tickets', [AdminController::class, 'tickets'])->middleware('admin.permission:reports.view');
            Route::patch('/tickets/{id}', [AdminController::class, 'updateTicket'])->middleware('admin.permission:reports.process');
            Route::patch('/tickets/{id}/status', [TicketController::class, 'updateStatus'])->middleware('admin.permission:reports.process');
            Route::post('/tickets/{id}/reply', [TicketController::class, 'reply'])->middleware('admin.permission:reports.process');
            Route::get('/payments', [AdminController::class, 'payments'])->middleware('admin.permission:payments.view');
            Route::post('/payments/{id}/refund', [AdminController::class, 'refundPayment'])->middleware('check.super_admin');
            Route::post('/payments/{id}/issue-invoice', [\App\Http\Controllers\Api\V1\Admin\PaymentInvoiceController::class, 'issueInvoice'])->middleware('check.super_admin');
            Route::get('/settings', [AdminController::class, 'getSettings']);
            Route::patch('/settings', [AdminController::class, 'updateSettings']);

            // Chat logs (admin only)
            Route::get('/chat-logs/search', [ChatLogController::class, 'search'])->middleware('admin.permission:chat.view');
            Route::get('/chat-logs/conversations', [ChatLogController::class, 'conversations'])->middleware('admin.permission:chat.view');
            Route::get('/chat-logs/export', [ChatLogController::class, 'export'])->middleware('admin.permission:chat.view');
            Route::get('/members/{userId}/chat-logs', [ChatLogController::class, 'memberChatLogs'])->middleware('admin.permission:chat.view');
            Route::get('/members/{userId}/chat-logs/export', [ChatLogController::class, 'memberChatLogsExport'])->middleware('admin.permission:chat.view');

            // Verification review (Sprint 11)
            Route::get('/verifications', [VerificationController::class, 'index']);
            Route::get('/verifications/pending', [VerificationController::class, 'pending']);
            Route::patch('/verifications/{id}', [VerificationController::class, 'review']);

            // SEO Meta 管理（A17）— A18 廣告跳轉連結保留 Phase 2
            Route::get('/seo/meta', [\App\Http\Controllers\Admin\SeoController::class, 'metaIndex'])->middleware('admin.permission:seo.manage');
            Route::patch('/seo/meta/{id}', [\App\Http\Controllers\Admin\SeoController::class, 'metaUpdate'])->middleware('admin.permission:seo.manage');

            // Announcements
            Route::middleware('admin.permission:announcements.manage')->group(function () {
                Route::get('/announcements', [\App\Http\Controllers\Admin\AnnouncementController::class, 'index']);
                Route::post('/announcements', [\App\Http\Controllers\Admin\AnnouncementController::class, 'store']);
                Route::patch('/announcements/{id}', [\App\Http\Controllers\Admin\AnnouncementController::class, 'update']);
                Route::delete('/announcements/{id}', [\App\Http\Controllers\Admin\AnnouncementController::class, 'destroy']);
            });

            // Broadcasts (Sprint 11)
            Route::get('/broadcasts', [BroadcastController::class, 'index'])->middleware('admin.permission:broadcasts.manage');
            Route::post('/broadcasts', [BroadcastController::class, 'store'])->middleware('admin.permission:broadcasts.manage');
            Route::get('/broadcasts/{id}', [BroadcastController::class, 'show'])->middleware('admin.permission:broadcasts.manage');
            Route::post('/broadcasts/{id}/send', [BroadcastController::class, 'send'])->middleware('admin.permission:broadcasts.manage');

            // Operation logs (Sprint 11)
            Route::get('/logs', [AdminLogController::class, 'index'])->middleware('admin.permission:logs.view');

            // User activity logs (super_admin only)
            Route::get('/user-activity-logs', [UserActivityLogController::class, 'index'])->middleware('admin.permission:logs.view');

            // F40 點數管理（後台）— 補完 A01 儀表板
            Route::middleware('admin.permission:members.view')->group(function () {
                Route::get('/stats/summary', [StatsController::class, 'summary']);
                Route::get('/stats/chart', [StatsController::class, 'chart']);
                Route::get('/stats/export', [StatsController::class, 'export']);
            });
            Route::get('/stats/server-metrics', [StatsController::class, 'serverMetrics'])->middleware('admin.permission:settings.roles');
            Route::middleware('admin.permission:settings.pricing')->group(function () {
                Route::get('/point-packages', [AdminPointController::class, 'packages']);
                Route::patch('/point-packages/{id}', [AdminPointController::class, 'updatePackage']);
                Route::get('/point-transactions', [AdminPointController::class, 'transactions']);
            });
            Route::post('/members/{id}/points', [AdminPointController::class, 'adjustPoints'])->middleware('admin.permission:members.edit');
            // 信用卡驗證管理已整合至 /admin/payments（Step 9）

            // 誠信分數配分（super_admin only）
            Route::middleware('check.super_admin')->prefix('settings')->group(function () {
                Route::get('credit-score', [\App\Http\Controllers\Api\V1\AdminController::class, 'getCreditScoreSettings']);
                Route::put('credit-score', [\App\Http\Controllers\Api\V1\AdminController::class, 'updateCreditScoreSettings']);
                Route::post('credit-score/reset', [\App\Http\Controllers\Api\V1\AdminController::class, 'resetCreditScoreSettings']);
            });

            // System Control (super_admin only)
            Route::middleware('check.super_admin')->prefix('settings')->group(function () {
                Route::get('system-control', [SystemControlController::class, 'index']);
                Route::patch('app-mode', [SystemControlController::class, 'updateAppMode']);
                Route::get('system/app-mode', [SystemControlController::class, 'getAppMode']);
                Route::patch('mail', [SystemControlController::class, 'updateMail']);
                Route::post('mail/test', [SystemControlController::class, 'testMail']);
                Route::patch('sms', [SystemControlController::class, 'updateSms']);
                Route::post('sms/test', [SystemControlController::class, 'testSms']);
                Route::get('tracking', [SystemControlController::class, 'getTracking']);
                Route::patch('tracking', [SystemControlController::class, 'updateTracking']);
                Route::post('database/test', [SystemControlController::class, 'testDatabase']);
                Route::get('database/export', [SystemControlController::class, 'exportDatabase']);

                // Subscription plans management
                Route::get('subscription-plans', [SystemControlController::class, 'getSubscriptionPlans']);
                Route::patch('subscription-plans/{id}', [SystemControlController::class, 'updateSubscriptionPlan']);

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
                // 舊格式（dot-notation key），保留向下相容
                Route::get('ecpay', [ECPaySettingController::class, 'index']);
                Route::post('ecpay', [ECPaySettingController::class, 'update']);
                // 新格式（Step 6，加密儲存）
                Route::get('payment', [\App\Http\Controllers\Api\V1\Admin\PaymentSettingsController::class, 'index']);
                Route::put('payment', [\App\Http\Controllers\Api\V1\Admin\PaymentSettingsController::class, 'update']);
                // 電子發票字軌管理（super_admin only，service 內已守門）
                Route::prefix('ecpay/invoice-words')->group(function () {
                    Route::get('/', [\App\Http\Controllers\Api\V1\Admin\EcpayInvoiceWordController::class, 'index']);
                    Route::post('/', [\App\Http\Controllers\Api\V1\Admin\EcpayInvoiceWordController::class, 'store']);
                    Route::patch('/{trackId}/status', [\App\Http\Controllers\Api\V1\Admin\EcpayInvoiceWordController::class, 'updateStatus']);
                });
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
