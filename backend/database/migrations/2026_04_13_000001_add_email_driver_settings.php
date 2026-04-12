<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key_name' => 'mail.driver', 'value' => 'resend', 'value_type' => 'string', 'description' => 'Email 驅動：smtp 或 resend'],
            ['key_name' => 'mail.resend_api_key', 'value' => '', 'value_type' => 'string', 'description' => 'Resend API Key'],
        ];

        foreach ($settings as $s) {
            DB::table('system_settings')->updateOrInsert(
                ['key_name' => $s['key_name']],
                array_merge($s, ['updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key_name', ['mail.driver', 'mail.resend_api_key'])->delete();
    }
};
