<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'refund_attempts')) {
                $table->unsignedTinyInteger('refund_attempts')->default(0)->after('refund_failure_reason')
                      ->comment('退款嘗試次數（≥3 且仍失敗 → requires_manual_review）');
            }
            if (!Schema::hasColumn('payments', 'requires_manual_review')) {
                $table->boolean('requires_manual_review')->default(false)->after('refund_attempts')
                      ->comment('true = 退款連續失敗需人工處理');
                $table->index('requires_manual_review');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['refund_attempts', 'requires_manual_review']);
        });
    }
};
