<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('phone_change_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('old_phone_hash', 64)->nullable();
            $table->string('old_phone_masked', 64)->nullable();
            $table->string('new_phone_hash', 64);
            $table->string('new_phone_masked', 64);
            $table->enum('source', ['verify', 'change', 'admin'])->default('change');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('changed_at')->useCurrent();

            // ⚠️ Audit table 設計（PR-3 v8）：
            //   - 只有 changed_at（append-only），不用 $table->timestamps()
            //   - 不加 FK 到 users（避免 user 被 anonymize 後 cascade 影響歷史紀錄）
            //   - 對齊既有 audit table 風格（如 admin_operation_logs / user_activity_logs）
            $table->index('new_phone_hash');
            $table->index('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_change_histories');
    }
};
