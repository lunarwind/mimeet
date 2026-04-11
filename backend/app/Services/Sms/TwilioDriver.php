<?php

namespace App\Services\Sms;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwilioDriver implements SmsDriverInterface
{
    public function send(string $phone, string $body): bool
    {
        $result = $this->sendWithDetail($phone, $body);
        return $result['success'];
    }

    /**
     * Send SMS via Twilio REST API and return detailed result.
     *
     * Twilio API: POST https://api.twilio.com/2010-04-01/Accounts/{SID}/Messages.json
     * Auth: HTTP Basic (SID:AuthToken)
     * Content-Type: application/x-www-form-urlencoded
     * Success: HTTP 201 Created
     */
    public function sendWithDetail(
        string $phone,
        string $body,
        ?string $sid = null,
        ?string $authToken = null,
        ?string $from = null,
    ): array {
        $sid = $sid ?? SystemSetting::get('sms.twilio.account_sid', '');
        $token = $authToken ?? $this->getToken();
        $from = $from ?? SystemSetting::get('sms.twilio.from_number', '');

        if (!$sid || !$token) {
            return ['success' => false, 'raw' => '', 'error' => 'Twilio Account SID 或 Auth Token 未設定'];
        }
        if (!$from) {
            return ['success' => false, 'raw' => '', 'error' => 'Twilio From 號碼未設定'];
        }

        $to = $this->toE164($phone);

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->timeout(15)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To' => $to,
                    'Body' => $body,
                ]);

            $raw = $response->body();
            $data = $response->json() ?? [];

            try { Log::info('[SMS Twilio] Response', ['status' => $response->status(), 'sid' => $data['sid'] ?? null, 'to' => $to]); } catch (\Throwable) {}

            // Twilio returns 201 on success, not 200
            $success = $response->status() === 201;

            return [
                'success' => $success,
                'raw' => $raw,
                'http_status' => $response->status(),
                'error' => $success ? null : ($data['message'] ?? 'Twilio 回應異常'),
                'twilio_error_code' => $data['code'] ?? null,
            ];
        } catch (\Exception $e) {
            try { Log::error('[SMS Twilio Error]', ['error' => $e->getMessage()]); } catch (\Throwable) {}
            return ['success' => false, 'raw' => '', 'error' => $e->getMessage()];
        }
    }

    /**
     * Convert Taiwan phone number to E.164 format.
     * 0983144094 → +886983144094
     */
    private function toE164(string $phone): string
    {
        $phone = preg_replace('/[\s\-]/', '', $phone);
        if (str_starts_with($phone, '09')) {
            return '+886' . substr($phone, 1);
        }
        if (str_starts_with($phone, '+')) {
            return $phone; // Already E.164
        }
        return '+886' . ltrim($phone, '0');
    }

    private function getToken(): string
    {
        $fromDb = SystemSetting::get('sms.twilio.auth_token_encrypted', '');
        if ($fromDb) {
            try { return Crypt::decryptString($fromDb); } catch (\Exception) { return $fromDb; }
        }
        $fromEnv = env('SMS_TWILIO_AUTH_TOKEN', '');
        if (!$fromEnv) return '';
        try { return Crypt::decryptString($fromEnv); } catch (\Exception) { return $fromEnv; }
    }
}
