<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_metas', function (Blueprint $table) {
            $table->id();
            $table->string('route', 100)->unique()->comment('對應前台路由，如 / 或 /login');
            $table->string('title', 70);
            $table->string('description', 200);
            $table->string('og_title', 70)->nullable();
            $table->string('og_description', 200)->nullable();
            $table->string('og_image_url', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metas');
    }
};
