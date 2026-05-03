<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * notifications.type ENUM 加 subscription_expired 值。
 *
 * 訂閱到期降級流程（subscriptions:expire）發送的站內通知 type。
 * MySQL ENUM 加值只能走 ALTER TABLE MODIFY COLUMN。
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM("
            . "'new_message','new_favorite','profile_visited','ticket_replied',"
            . "'date_invitation','date_verified','subscription_expiring',"
            . "'subscription_activated','credit_score_changed','system',"
            . "'super_like','subscription_expired'"
            . ") NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE notifications MODIFY COLUMN type ENUM("
            . "'new_message','new_favorite','profile_visited','ticket_replied',"
            . "'date_invitation','date_verified','subscription_expiring',"
            . "'subscription_activated','credit_score_changed','system',"
            . "'super_like'"
            . ") NOT NULL");
    }
};
