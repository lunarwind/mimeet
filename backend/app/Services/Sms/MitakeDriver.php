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
        $result = $this->sendWithDetail($phone, $body);
        return $result['success'];
    }

    public function sendWithDetail(string $phone, string $body, ?string $username = null, ?string $password = null): array
    {
        $apiUrl = SystemSetting::get('sms.mitake.api_url', 'https://sms.mitake.com.tw/b2c/mtk/SmSend');
        $user = $username ?? SystemSetting::get('sms.mitake.username', '');
        $pass = $password ?? $this->getPassword();

        if (!$user || !$pass) {
            return ['success' => false, 'raw' => '', 'error' => '三竹帳號或密碼未設定'];
        }

        try {
            $response = Http::timeout(15)->asForm()->post($apiUrl, [
                'username' => $user,
                'password' => $pass,
                'dstaddr' => $this->formatPhone($phone),
                'smbody' => $body,
                'CharsetURL' => 'UTF8',
            ]);

            $raw = $response->body();

            try { Log::info('[SMS Mitake] Response', ['status' => $response->status(), 'phone' => substr($phone, 0, 4) . '****']); } catch (\Throwable) {}

            $success = $response->successful() && str_contains($raw, 'statuscode=');
            if (preg_match('/statuscode=(\w+)/', $raw, $m)) {
                $statusCode = $m[1];
                $success = is_numeric($statusCode) || $statusCode === '*';
            }

            return [
                'success' => $success,
                'raw' => $raw,
                'http_status' => $response->status(),
                'error' => $success ? null : '三竹回應異常',
            ];
        } catch (\Exception $e) {
            try { Log::error('[SMS Mitake Error]', ['error' => $e->getMessage()]); } catch (\Throwable) {}
            return ['success' => false, 'raw' => '', 'error' => $e->getMessage()];
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
