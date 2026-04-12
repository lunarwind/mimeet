<?php

namespace App\Providers;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ServiceProvider;

/**
 * Overrides Laravel config with values from system_settings DB table.
 * Priority: system_settings > .env > config defaults
 */
class DynamicConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Only override in non-testing environments and after migrations have run
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            return; // Skip during artisan commands (migrate, seed, etc.)
        }

        try {
            $this->applyMailConfig();
        } catch (\Throwable) {
            // DB not ready yet (during migration) — silently skip
        }
    }

    private function applyMailConfig(): void
    {
        $host = SystemSetting::get('mail.host');
        if (!$host) return; // No DB overrides — use .env defaults

        $encryption = SystemSetting::get('mail.encryption', 'tls');
        if ($encryption === 'null' || $encryption === 'none') $encryption = null;

        $passEnc = SystemSetting::get('mail.password_encrypted', '');
        $password = null;
        if ($passEnc) {
            try { $password = Crypt::decryptString($passEnc); } catch (\Throwable) { $password = $passEnc; }
        }

        config([
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) SystemSetting::get('mail.port', config('mail.mailers.smtp.port')),
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.mailers.smtp.username' => SystemSetting::get('mail.username', config('mail.mailers.smtp.username')),
            'mail.from.address' => SystemSetting::get('mail.from_address', config('mail.from.address')),
            'mail.from.name' => SystemSetting::get('mail.from_name', config('mail.from.name')),
        ]);

        if ($password) {
            config(['mail.mailers.smtp.password' => $password]);
        }
    }
}
