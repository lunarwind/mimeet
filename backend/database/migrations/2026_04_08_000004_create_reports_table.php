<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique()->default(DB::raw('(UUID())'));
            $table->unsignedBigInteger('reporter_id');
            $table->unsignedBigInteger('reported_user_id');
            $table->enum('type', ['fake_photo', 'harassment', 'spam', 'scam', 'inappropriate', 'other', 'appeal']);
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
