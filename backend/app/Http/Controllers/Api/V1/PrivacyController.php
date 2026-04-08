<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    private const ALLOWED_KEYS = [
        'show_online_status',
        'allow_profile_visits',
        'show_in_search',
        'show_last_active',
        'allow_stranger_message',
    ];

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->privacy_settings,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string|in:' . implode(',', self::ALLOWED_KEYS),
            'value' => 'required|boolean',
        ]);

        $user = $request->user();
        // Get raw DB value, merge with update
        $raw = $user->getRawOriginal('privacy_settings');
        $settings = $raw ? (is_string($raw) ? json_decode($raw, true) : $raw) : [];
        $settings[$request->input('key')] = $request->boolean('value');

        $user->update(['privacy_settings' => json_encode($settings)]);

        return response()->json([
            'success' => true,
            'data' => [
                'key' => $request->input('key'),
                'value' => $request->boolean('value'),
            ],
        ]);
    }
}
