<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{
    /**
     * Send email via configured driver (resend or smtp).
     */
    public function send(string $to, string $subject, string $html): bool
    {
        $driver = SystemSetting::get('mail.driver', env('MAIL_MAILER', 'smtp'));

        try {
            if ($driver === 'resend') {
                return $this->sendViaResend($to, $subject, $html);
            }
            return $this->sendViaSmtp($to, $subject, $html);
        } catch (\Throwable $e) {
            try { Log::error('[MailService] Send failed', ['driver' => $driver, 'to' => $to, 'error' => $e->getMessage()]); } catch (\Throwable) {}
            throw $e;
        }
    }

    private function sendViaResend(string $to, string $subject, string $html): bool
    {
        $apiKey = SystemSetting::get('mail.resend_api_key', env('RESEND_API_KEY', ''));
        if (!$apiKey) {
            throw new \RuntimeException('Resend API key 未設定');
        }

        $fromAddress = SystemSetting::get('mail.from_address', env('MAIL_FROM_ADDRESS', 'noreply@mimeet.tw'));
        $fromName = SystemSetting::get('mail.from_name', env('MAIL_FROM_NAME', 'MiMeet'));

        $resend = \Resend::client($apiKey);
        $result = $resend->emails->send([
            'from' => "{$fromName} <{$fromAddress}>",
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
        ]);

        try { Log::info('[MailService] Resend OK', ['to' => $to, 'id' => $result->id ?? null]); } catch (\Throwable) {}
        return true;
    }

    private function sendViaSmtp(string $to, string $subject, string $html): bool
    {
        $host = SystemSetting::get('mail.host', SystemSetting::get('mail.smtp_host', config('mail.mailers.smtp.host')));
        $port = (int) SystemSetting::get('mail.port', SystemSetting::get('mail.smtp_port', config('mail.mailers.smtp.port')));
        $enc = SystemSetting::get('mail.encryption', SystemSetting::get('mail.smtp_encryption', config('mail.mailers.smtp.encryption')));
        $user = SystemSetting::get('mail.username', SystemSetting::get('mail.smtp_username', config('mail.mailers.smtp.username')));
        $from = SystemSetting::get('mail.from_address', config('mail.from.address'));
        $fromName = SystemSetting::get('mail.from_name', config('mail.from.name'));

        // Password: try encrypted first, then plain
        $passEnc = SystemSetting::get('mail.password_encrypted', '');
        $pass = $passEnc ? (function () use ($passEnc) { try { return Crypt::decryptString($passEnc); } catch (\Throwable) { return $passEnc; } })()
            : SystemSetting::get('mail.smtp_password', config('mail.mailers.smtp.password'));

        $encryption = ($enc === 'null' || $enc === 'none' || !$enc) ? null : $enc;

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.mailers.smtp.username' => $user,
            'mail.mailers.smtp.password' => $pass,
            'mail.from.address' => $from,
            'mail.from.name' => $fromName,
        ]);

        Mail::purge('smtp');
        Mail::mailer('smtp')->to($to)->send(new \App\Mail\TestMail());

        try { Log::info('[MailService] SMTP OK', ['to' => $to]); } catch (\Throwable) {}
        return true;
    }

    /**
     * Get current config (masked secrets) for admin display.
     */
    public function getConfig(): array
    {
        return [
            'driver' => SystemSetting::get('mail.driver', env('MAIL_MAILER', 'smtp')),
            'resend_api_key' => $this->mask(SystemSetting::get('mail.resend_api_key', '')),
            'smtp_host' => SystemSetting::get('mail.host', SystemSetting::get('mail.smtp_host', '')),
            'smtp_port' => (int) SystemSetting::get('mail.port', SystemSetting::get('mail.smtp_port', 587)),
            'smtp_encryption' => SystemSetting::get('mail.encryption', SystemSetting::get('mail.smtp_encryption', 'tls')),
            'smtp_username' => SystemSetting::get('mail.username', SystemSetting::get('mail.smtp_username', '')),
            'from_address' => SystemSetting::get('mail.from_address', env('MAIL_FROM_ADDRESS', 'noreply@mimeet.tw')),
            'from_name' => SystemSetting::get('mail.from_name', env('MAIL_FROM_NAME', 'MiMeet')),
        ];
    }

    private function mask(string $value): string
    {
        if (strlen($value) <= 8) return $value ? '****' : '';
        return substr($value, 0, 8) . str_repeat('*', 12);
    }
}
