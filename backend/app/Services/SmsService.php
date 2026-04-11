<?php

namespace App\Services;

<<<<<<< HEAD
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
=======
use App\Models\SystemSetting;
use App\Services\Sms\Every8dDriver;
use App\Services\Sms\LogDriver;
use App\Services\Sms\MitakeDriver;
use App\Services\Sms\SmsDriverInterface;
use App\Services\Sms\TwilioDriver;
>>>>>>> develop
use Illuminate\Support\Facades\Log;

class SmsService
{
<<<<<<< HEAD
    public function send(string $phone, string $message): array
    {
        $settings = Cache::get('sms_settings', ['provider' => 'disabled']);
        $provider = $settings['provider'] ?? 'disabled';

        return match ($provider) {
            'twilio' => $this->sendViaTwilio($phone, $message, $settings['twilio'] ?? []),
            'mitake' => $this->sendViaMitake($phone, $message, $settings['mitake'] ?? []),
            default => $this->logOnly($phone, $message),
        };
    }

    private function sendViaTwilio(string $phone, string $message, array $config): array
    {
        $e164Phone = $this->toE164($phone);
        $sid = $config['sid'] ?? '';
        $token = $config['auth_token'] ?? '';
        $from = $config['from_number'] ?? '';

        if (!$sid || !$token || !$from) {
            Log::warning('[SMS] Twilio credentials not configured');
            return ['success' => false, 'error' => 'Twilio credentials not configured'];
        }

        try {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To' => $e164Phone,
                    'From' => $from,
                    'Body' => $message,
                ]);

            $success = $response->status() === 201;
            Log::info('[SMS:Twilio] ' . ($success ? 'sent' : 'failed'), [
                'to' => $e164Phone, 'status' => $response->status()
            ]);

            return ['success' => $success, 'raw_response' => $response->json()];
        } catch (\Exception $e) {
            Log::error('[SMS:Twilio] Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendViaMitake(string $phone, string $message, array $config): array
    {
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        if (!$username || !$password) {
            Log::warning('[SMS] Mitake credentials not configured');
            return ['success' => false, 'error' => 'Mitake credentials not configured'];
        }

        try {
            $response = Http::asForm()->post('https://sms.mitake.com.tw/b2c/mtk/SmSend', [
                'username' => $username,
                'password' => $password,
                'dstaddr' => str_replace('+886', '0', $phone),
                'smbody' => $message,
            ]);

            $success = $response->successful();
            Log::info('[SMS:Mitake] ' . ($success ? 'sent' : 'failed'), ['to' => $phone]);

            return ['success' => $success, 'raw_response' => $response->body()];
        } catch (\Exception $e) {
            Log::error('[SMS:Mitake] Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function logOnly(string $phone, string $message): array
    {
        Log::info("[SMS:disabled] Would send to {$phone}: {$message}");
        return ['success' => true, 'provider' => 'disabled', 'note' => 'SMS disabled, message logged only'];
    }

    private function toE164(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (str_starts_with($phone, '09')) {
            return '+886' . substr($phone, 1);
        }
        if (str_starts_with($phone, '+')) {
            return $phone;
        }
        return '+886' . ltrim($phone, '0');
    }
=======
    public function sendOtp(string $phone, string $code): bool
    {
        $body = "【MiMeet】您的驗證碼為 {$code}，10 分鐘內有效，請勿洩漏。";

        if (SystemSetting::get('app.mode', 'testing') === 'testing') {
            Log::info('[SMS STUB - testing mode]', [
                'phone' => substr($phone, 0, 4) . '****',
                'code' => $code,
            ]);
            return true;
        }

        return $this->getDriver()->send($phone, $body);
    }

    public function getDriver(): SmsDriverInterface
    {
        return match (SystemSetting::get('sms.provider', 'disabled')) {
            'mitake' => new MitakeDriver(),
            'twilio' => new TwilioDriver(),
            default => new LogDriver(),
        };
    }
>>>>>>> develop
}
