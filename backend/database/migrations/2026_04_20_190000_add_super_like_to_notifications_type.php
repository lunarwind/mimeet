<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F40-c：notifications.type ENUM 加 super_like 值
 * （MySQL ENUM 加值只能走 ALTER TABLE MODIFY COLUMN）
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // SQLite 不是 ENUM，不需要改
        }

        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM("
            . "'new_message','new_favorite','profile_visited','ticket_replied',"
            . "'date_invitation','date_verified','subscription_expiring',"
            . "'subscription_activated','credit_score_changed','system',"
            . "'super_like'"
            . ") NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM("
            . "'new_message','new_favorite','profile_visited','ticket_replied',"
            . "'date_invitation','date_verified','subscription_expiring',"
            . "'subscription_activated','credit_score_changed','system'"
            . ") NOT NULL");
    }
};
