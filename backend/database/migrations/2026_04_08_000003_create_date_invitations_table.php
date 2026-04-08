<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('date_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inviter_id');
            $table->unsignedBigInteger('invitee_id');
            $table->dateTime('date_time');
            $table->string('location_name', 255)->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->string('qr_token', 100)->unique();
            $table->enum('status', ['pending', 'accepted', 'verified', 'cancelled', 'expired'])->default('pending');
            $table->timestamp('inviter_scanned_at')->nullable();
            $table->timestamp('invitee_scanned_at')->nullable();
            $table->decimal('inviter_gps_lat', 10, 8)->nullable();
            $table->decimal('inviter_gps_lng', 11, 8)->nullable();
            $table->tinyInteger('inviter_gps_verified')->default(0);
            $table->decimal('invitee_gps_lat', 10, 8)->nullable();
            $table->decimal('invitee_gps_lng', 11, 8)->nullable();
            $table->tinyInteger('invitee_gps_verified')->default(0);
            $table->tinyInteger('gps_verification_passed')->default(0);
            $table->tinyInteger('score_awarded')->unsigned()->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('inviter_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('invitee_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('inviter_id');
            $table->index('invitee_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('date_invitations');
    }
};
