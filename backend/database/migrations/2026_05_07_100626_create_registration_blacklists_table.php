<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('registration_blacklists', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['email', 'mobile']);
            $table->string('value_hash', 64)->comment('normalize 後 SHA-256');
            $table->string('value_masked', 64)->comment('顯示用，例如 c***k@example.com / 09xx-xxx-678');
            $table->text('reason')->nullable();
            $table->enum('source', ['manual', 'admin_delete'])->default('manual');
            $table->unsignedBigInteger('source_user_id')->nullable()->comment('若來自刪除，指向被刪 user 的舊 id');
            $table->unsignedBigInteger('created_by')->comment('admin_users.id');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            // 方案 C：active 時 = value_hash;deactivated 時 = null。
            // MySQL UNIQUE 允許多 NULL → 可保留完整歷史(每組 type+hash 多筆 inactive、僅一筆 active)
            $table->string('active_value_hash', 64)->nullable()->comment('與 value_hash 同步維護（Model saving event）；deactivate 時設 null');
            $table->unsignedBigInteger('deactivated_by')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->text('deactivation_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // schema-level race protection：同 type+active_value_hash 只能一筆 active
            $table->unique(['type', 'active_value_hash']);
            // 查詢用
            $table->index(['type', 'value_hash']);
            $table->index('expires_at');
            $table->index('source_user_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_blacklists');
    }
};
