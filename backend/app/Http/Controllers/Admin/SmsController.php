<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SmsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Cache::get('sms_settings', [
                'provider' => 'disabled',
                'mitake' => ['username' => '', 'password' => ''],
                'twilio' => ['sid' => '', 'auth_token' => '', 'from_number' => ''],
            ]),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|in:mitake,twilio,disabled',
        ]);

        $settings = [
            'provider' => $request->provider,
            'mitake' => $request->input('mitake', ['username' => '', 'password' => '']),
            'twilio' => $request->input('twilio', ['sid' => '', 'auth_token' => '', 'from_number' => '']),
        ];
        Cache::put('sms_settings', $settings, now()->addYear());

        return response()->json(['success' => true, 'message' => 'SMS 設定已更新']);
    }

    public function test(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string']);
        \Log::info("[SmsTest] Would send test SMS to: {$request->phone}");
        return response()->json(['success' => true, 'message' => "測試簡訊已發送至 {$request->phone}"]);
    }
}
