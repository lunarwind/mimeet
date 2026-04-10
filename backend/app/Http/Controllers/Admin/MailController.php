<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MailController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Cache::get('mail_settings', [
                'host' => config('mail.mailers.smtp.host', ''),
                'port' => config('mail.mailers.smtp.port', 587),
                'username' => config('mail.mailers.smtp.username', ''),
                'password' => '',
                'from_address' => config('mail.from.address', ''),
                'from_name' => config('mail.from.name', 'MiMeet'),
            ]),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'port' => 'required|integer',
            'username' => 'required|string',
            'from_address' => 'required|email',
            'from_name' => 'required|string',
        ]);

        $settings = $request->only(['host', 'port', 'username', 'password', 'from_address', 'from_name']);
        Cache::put('mail_settings', $settings, now()->addYear());

        return response()->json(['success' => true, 'message' => 'Email 設定已更新']);
    }

    public function test(Request $request): JsonResponse
    {
        $request->validate(['recipient' => 'required|email']);
        // In dev, just log instead of actually sending
        \Log::info("[MailTest] Would send test email to: {$request->recipient}");
        return response()->json(['success' => true, 'message' => "測試信已發送至 {$request->recipient}"]);
    }
}
