<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('random_code', 10);
            $table->string('photo_url', 500)->nullable();
            $table->enum('status', ['pending_code', 'pending_review', 'approved', 'rejected', 'expired'])
                  ->default('pending_code');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_verifications');
    }
};
