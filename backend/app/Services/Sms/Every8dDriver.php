<?php

namespace App\Services\Sms;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Every8dDriver implements SmsDriverInterface
{
    public function send(string $phone, string $body): bool
    {
        try {
            $response = Http::timeout(10)->post('https://api.every8d.com/API21/HTTP/sendSMS.ashx', [
                'UID' => SystemSetting::get('sms.every8d.username'),
                'PWD' => $this->getPassword(),
                'RETYPED' => 0,
                'MSG' => $body,
                'DEST' => $phone,
            ]);
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('[SMS Every8d Error]', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getPassword(): string
    {
        $fromDb = SystemSetting::get('sms.every8d.password_encrypted', '');
        if ($fromDb) {
            try { return Crypt::decryptString($fromDb); } catch (\Exception) { return $fromDb; }
        }
        $fromEnv = env('SMS_EVERY8D_PASSWORD', '');
        if (!$fromEnv) return '';
        try { return Crypt::decryptString($fromEnv); } catch (\Exception) { return $fromEnv; }
    }
}
