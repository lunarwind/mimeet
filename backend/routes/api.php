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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/v1')->group(function () {

    // ─── Auth (public) ───────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    });

    // ─── Auth (authenticated) ────────────────────────────────────────
    Route::prefix('auth')->middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/verify-phone/send', [AuthController::class, 'verifyPhoneSend']);
        Route::post('/verify-phone/confirm', [AuthController::class, 'verifyPhoneConfirm']);
    });

    // ─── Users (authenticated) ───────────────────────────────────────
    Route::prefix('users')->middleware('auth:sanctum')->group(function () {
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

    // ─── Notifications (authenticated) ───────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead']);
    });

    // ─── Admin ───────────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {
        Route::post('/auth/login', [AdminController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/members', [AdminController::class, 'members']);
            Route::get('/members/{id}', [AdminController::class, 'memberDetail']);
            Route::patch('/members/{id}/actions', [AdminController::class, 'memberAction']);
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
        });
    });
});
