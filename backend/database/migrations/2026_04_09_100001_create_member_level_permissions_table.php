<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_level_permissions', function (Blueprint $table) {
            $table->decimal('level', 3, 1)->unsigned()->comment('0.0/1.0/1.5/2.0/3.0');
            $table->string('feature_key', 50)->comment('browse/basic_search/advanced_search/daily_message_limit/view_full_profile/post_moment/read_receipt/qr_date/vip_invisible/broadcast');
            $table->boolean('enabled')->default(true);
            $table->string('value', 20)->nullable()->comment('數值型設定（如訊息額度），NULL=布林型');
            $table->primary(['level', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_level_permissions');
    }
};
