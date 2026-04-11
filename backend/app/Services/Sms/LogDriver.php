<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

class LogDriver implements SmsDriverInterface
{
    public function send(string $phone, string $body): bool
    {
        Log::info('[SMS STUB]', ['phone' => substr($phone, 0, 4) . '****', 'body' => $body]);
        return true;
    }
}
