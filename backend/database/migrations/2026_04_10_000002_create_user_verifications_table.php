<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['phone', 'photo', 'credit_card']);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('photo_url')->nullable();
            $table->string('random_code', 10)->nullable();
            $table->text('reject_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type']);
            $table->index(['status']);
        });
    }
    public function down(): void { Schema::dropIfExists('user_verifications'); }
};
