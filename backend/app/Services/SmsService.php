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
            'every8d' => new Every8dDriver(),
            default => new LogDriver(),
        };
    }
}
