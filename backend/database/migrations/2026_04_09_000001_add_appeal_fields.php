<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('last_active_at');
            }
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('updated_at');
            }
            if (!Schema::hasColumn('users', 'delete_requested_at')) {
                $table->timestamp('delete_requested_at')->nullable()->after('deleted_at');
            }
            if (!Schema::hasColumn('users', 'privacy_settings')) {
                $table->json('privacy_settings')->nullable()->after('status');
            }
            if (!Schema::hasColumn('users', 'preferences')) {
                $table->json('preferences')->nullable()->after('privacy_settings');
            }
            if (!Schema::hasColumn('users', 'profile')) {
                $table->json('profile')->nullable()->after('preferences');
            }
        });

        DB::statement("ALTER TABLE users MODIFY COLUMN status ENUM('active','suspended','auto_suspended','pending_deletion','deleted') NOT NULL DEFAULT 'active'");
        DB::statement("ALTER TABLE reports MODIFY COLUMN type ENUM('fake_photo','harassment','spam','scam','inappropriate','other','appeal') NOT NULL");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'delete_requested_at', 'privacy_settings', 'deleted_at']);
        });
    }
};
