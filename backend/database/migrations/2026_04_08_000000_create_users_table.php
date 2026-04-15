<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('nickname', 50)->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('birth_date')->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->text('bio')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('location', 100)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('education', 50)->nullable();
            $table->json('interests')->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('email_verified')->default(false);
            $table->boolean('phone_verified')->default(false);
            $table->tinyInteger('membership_level')->default(0);
            $table->tinyInteger('credit_score')->unsigned()->default(60);
            $table->string('status', 20)->default('active');
            $table->json('privacy_settings')->nullable();
            $table->json('preferences')->nullable();
            $table->json('profile')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('delete_requested_at')->nullable();
        });

        // Seed system user id=1 — guaranteed to exist after any migrate:fresh
        DB::table('users')->insert([
            'id'               => 1,
            'email'            => 'system@mimeet.tw',
            'password'         => bcrypt('SYSTEM_ACCOUNT_DO_NOT_LOGIN'),
            'nickname'         => 'MiMeet 官方',
            'gender'           => 'male',
            'email_verified'   => true,
            'membership_level' => 3,
            'credit_score'     => 100,
            'status'           => 'active',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
        DB::statement('ALTER TABLE users AUTO_INCREMENT = 2');

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
