<?php

namespace App\Services\Sms;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MitakeDriver implements SmsDriverInterface
{
    public function send(string $phone, string $body): bool
    {
        try {
            $response = Http::timeout(10)->asForm()->post(
                SystemSetting::get('sms.mitake.api_url', 'https://sms.mitake.com.tw/b2c/mtk/SmSend'),
                [
                    'username' => SystemSetting::get('sms.mitake.username'),
                    'password' => $this->getPassword(),
                    'dstaddr' => $this->formatPhone($phone),
                    'smbody' => $body,
                    'CharsetURL' => 'UTF8',
                ]
            );
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('[SMS Mitake Error]', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getPassword(): string
    {
        $fromDb = SystemSetting::get('sms.mitake.password_encrypted', '');
        if ($fromDb) {
            try { return Crypt::decryptString($fromDb); } catch (\Exception) { return $fromDb; }
        }
        $fromEnv = env('SMS_MITAKE_PASSWORD', '');
        if (!$fromEnv) return '';
        try { return Crypt::decryptString($fromEnv); } catch (\Exception) { return $fromEnv; }
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[\s\-]/', '', $phone);
        if (str_starts_with($phone, '+886')) $phone = '0' . substr($phone, 4);
        return $phone;
    }
}
