<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('member_level_permissions', function (Blueprint $table) {
            $table->id();
            $table->decimal('level', 3, 1)->index();
            $table->string('permission_key', 50);
            $table->boolean('is_allowed')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
            $table->unique(['level', 'permission_key']);
        });
    }
    public function down(): void { Schema::dropIfExists('member_level_permissions'); }
};
