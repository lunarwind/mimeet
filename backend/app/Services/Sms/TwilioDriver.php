<?php

namespace App\Services\Sms;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class TwilioDriver implements SmsDriverInterface
{
    public function send(string $phone, string $body): bool
    {
        // Note: requires twilio/sdk package
        // For now, use HTTP API directly
        try {
            $sid = SystemSetting::get('sms.twilio.account_sid');
            $token = $this->getToken();
            $from = SystemSetting::get('sms.twilio.from_number');

            $response = \Illuminate\Support\Facades\Http::withBasicAuth($sid, $token)
                ->timeout(10)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To' => $phone,
                    'Body' => $body,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('[SMS Twilio Error]', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getToken(): string
    {
        $encrypted = env('SMS_TWILIO_AUTH_TOKEN', '');
        if (!$encrypted) return '';
        try { return Crypt::decryptString($encrypted); } catch (\Exception) { return $encrypted; }
    }
}
