<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite 三項不相容處理（production MySQL 路徑保持原樣）：
        //   1. UUID() 是 MySQL 函式，SQLite 沒有 → SQLite 跳掉 default
        //   2. enum() 在 SQLite 翻成 VARCHAR+CHECK，後續 ALTER ENUM migration 為 MySQL-only
        //      會 skip，CHECK 跟不上會擋住 system_issue 等新值 → SQLite 用 string
        //   3. reported_user_id 後續 nullable migration 為 MySQL-only → SQLite 直接 nullable
        $isMysql = DB::getDriverName() === 'mysql';

        Schema::create('reports', function (Blueprint $table) use ($isMysql) {
            $table->id();
            if ($isMysql) {
                $table->char('uuid', 36)->unique()->default(DB::raw('(UUID())'));
            } else {
                // SQLite 沒有 UUID() 函式，且測試常未顯式提供 uuid → nullable + unique
                // (SQLite 允許多個 NULL 在 unique 欄位)
                $table->char('uuid', 36)->nullable()->unique();
            }
            $table->unsignedBigInteger('reporter_id');
            if ($isMysql) {
                $table->unsignedBigInteger('reported_user_id');
                $table->enum('type', ['fake_photo', 'harassment', 'spam', 'scam', 'inappropriate', 'other', 'appeal']);
            } else {
                $table->unsignedBigInteger('reported_user_id')->nullable();
                $table->string('type', 50);
            }
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'investigating', 'resolved', 'dismissed'])->default('pending');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->integer('reporter_score_change')->nullable();
            $table->timestamps();

            $table->foreign('reporter_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reported_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('reporter_id');
            $table->index('reported_user_id');
            $table->index('status');
        });

        Schema::create('report_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->string('image_url', 500);

            $table->foreign('report_id')->references('id')->on('reports')->cascadeOnDelete();
        });

        Schema::create('report_followups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('report_id')->references('id')->on('reports')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_followups');
        Schema::dropIfExists('report_images');
        Schema::dropIfExists('reports');
    }
};
