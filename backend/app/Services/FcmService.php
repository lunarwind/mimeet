<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FcmService
{
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        if (config('app.env') !== 'production') {
            Log::info('[FCM STUB]', compact('token', 'title', 'body', 'data'));
            return true;
        }

        // TODO: 生產環境 FCM HTTP v1 API
        // $serverKey = config('services.fcm.server_key');
        return false;
    }
}
