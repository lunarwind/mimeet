<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\DateInvitationController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\AdminController;

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

    // ─── Chats (authenticated + membership level 2) ──────────────────
    Route::prefix('chats')->middleware(['auth:sanctum', 'membership:2'])->group(function () {
        Route::get('/', [ChatController::class, 'index']);
        Route::get('/{id}/messages', [ChatController::class, 'messages']);
        Route::post('/{id}/messages', [ChatController::class, 'sendMessage']);
        Route::patch('/{id}/messages/read', [ChatController::class, 'markRead']);
    });

    // ─── Date Invitations (authenticated + membership level 2) ───────
    Route::prefix('date-invitations')->middleware(['auth:sanctum', 'membership:2'])->group(function () {
        Route::post('/', [DateInvitationController::class, 'store']);
        Route::get('/', [DateInvitationController::class, 'index']);
        Route::patch('/{id}/response', [DateInvitationController::class, 'respond']);
        Route::post('/verify', [DateInvitationController::class, 'verify']);
    });

    // ─── Reports (authenticated) ─────────────────────────────────────
    Route::prefix('reports')->middleware('auth:sanctum')->group(function () {
        Route::post('/', [ReportController::class, 'store']);
        Route::get('/', [ReportController::class, 'index']);
        Route::get('/history', [ReportController::class, 'history']);
        Route::delete('/{id}', [ReportController::class, 'destroy']);
    });

    // ─── Notifications (authenticated) ───────────────────────────────
    Route::prefix('me/notifications')->middleware('auth:sanctum')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/read-all', [NotificationController::class, 'readAll']);
        Route::patch('/{id}/read', [NotificationController::class, 'markRead']);
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
            Route::get('/payments', [AdminController::class, 'payments']);
            Route::get('/settings', [AdminController::class, 'getSettings']);
            Route::patch('/settings', [AdminController::class, 'updateSettings']);
        });
    });
});
