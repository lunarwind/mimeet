<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Services\Sms\Every8dDriver;
use App\Services\Sms\LogDriver;
use App\Services\Sms\MitakeDriver;
use App\Services\Sms\SmsDriverInterface;
use App\Services\Sms\TwilioDriver;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function sendOtp(string $phone, string $code): bool
    {
        $body = "【MiMeet】您的驗證碼為 {$code}，5 分鐘內有效，請勿洩漏。";

        // SMS 行為由 sms.provider 決定，與 app_mode 無關
        // （app_mode 只控制 ECPay sandbox/production）
        $provider = SystemSetting::get('sms.provider', 'disabled');

        if ($provider === 'disabled') {
            Log::info('[SMS] provider=disabled — 僅寫 log，未實際發送', [
                'phone' => substr($phone, 0, 4) . '****',
                'code'  => $code,
            ]);
            return true;
        }

        try {
            $sent = $this->getDriver()->send($phone, $body);
            if (!$sent) {
                Log::warning('[SMS] driver::send 回傳 false', [
                    'provider' => $provider,
                    'phone'    => substr($phone, 0, 4) . '****',
                ]);
            }
            return $sent;
        } catch (\Throwable $e) {
            Log::error('[SMS] driver 例外', [
                'provider' => $provider,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getDriver(): SmsDriverInterface
    {
        return match (SystemSetting::get('sms.provider', 'disabled')) {
            'mitake' => new MitakeDriver(),
            'twilio' => new TwilioDriver(),
            default => new LogDriver(),
        };
    }
}
