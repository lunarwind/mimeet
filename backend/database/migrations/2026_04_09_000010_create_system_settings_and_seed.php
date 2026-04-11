<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->string('key_name', 100)->primary();
                $table->text('value')->nullable();
                $table->string('value_type', 20)->default('string');
                $table->string('description', 500)->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        $settings = [
            ['key_name'=>'app.mode','value'=>'testing','value_type'=>'string','description'=>'系統模式：testing/production'],
            ['key_name'=>'app.maintenance','value'=>'0','value_type'=>'boolean','description'=>'維護模式'],
            ['key_name'=>'app.version','value'=>'1.0.0','value_type'=>'string','description'=>'系統版本號'],
            ['key_name'=>'sms.provider','value'=>'disabled','value_type'=>'string','description'=>'SMS 服務商'],
            ['key_name'=>'sms.mitake.username','value'=>'','value_type'=>'string','description'=>'三竹帳號'],
            ['key_name'=>'sms.mitake.api_url','value'=>'https://sms.mitake.com.tw/b2c/mtk/SmSend','value_type'=>'string','description'=>'三竹 API URL'],
            ['key_name'=>'sms.twilio.account_sid','value'=>'','value_type'=>'string','description'=>'Twilio SID'],
            ['key_name'=>'sms.twilio.from_number','value'=>'','value_type'=>'string','description'=>'Twilio 號碼'],
            ['key_name'=>'sms.every8d.username','value'=>'','value_type'=>'string','description'=>'每日簡訊帳號'],
            ['key_name'=>'mail.host','value'=>'mailpit','value_type'=>'string','description'=>'SMTP 主機'],
            ['key_name'=>'mail.port','value'=>'1025','value_type'=>'integer','description'=>'SMTP Port'],
            ['key_name'=>'mail.encryption','value'=>'null','value_type'=>'string','description'=>'SMTP 加密'],
            ['key_name'=>'mail.username','value'=>'null','value_type'=>'string','description'=>'SMTP 使用者'],
            ['key_name'=>'mail.from_address','value'=>'noreply@mimeet.tw','value_type'=>'string','description'=>'寄件人'],
            ['key_name'=>'mail.from_name','value'=>'MiMeet 平台','value_type'=>'string','description'=>'寄件人名稱'],
        ];

        foreach ($settings as $s) {
            DB::table('system_settings')->updateOrInsert(
                ['key_name' => $s['key_name']],
                array_merge($s, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        // Don't drop table — other migrations may depend on it
    }
};
