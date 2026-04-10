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

    /**
     * Send SMS and return detailed result including raw response.
     */
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

            Log::info('[SMS Mitake] Response', [
                'status' => $response->status(),
                'body' => $raw,
                'phone' => substr($phone, 0, 4) . '****',
            ]);

            // Mitake success: response body contains a line with '[' and 'statuscode=*'
            // where statuscode starting with numbers.
            // A successful send contains 'statuscode=1' or 'statuscode=0'
            // Failed: statuscode=e (error codes like 'e' prefix)
            // Simple check: body contains '$' dollar sign = still has quota, or check statuscode
            $success = $response->successful() && str_contains($raw, 'statuscode=');

            // More precise: check for statuscode values
            // statuscode=0 or statuscode=1 = queued/sent; statuscode=4 = delivered
            // statuscode with letters (e.g. 'p') = error
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
            Log::error('[SMS Mitake Error]', ['error' => $e->getMessage()]);
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
