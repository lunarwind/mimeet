<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_packages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique()->comment('如 pack_50, custom_01');
            $table->string('name', 50)->comment('方案名稱');
            $table->unsignedInteger('points')->comment('基本點數');
            $table->unsignedInteger('bonus_points')->default(0)->comment('贈送點數');
            $table->unsignedInteger('price')->comment('NT$ 金額');
            $table->string('description', 200)->nullable()->comment('方案說明');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_packages');
    }
};
