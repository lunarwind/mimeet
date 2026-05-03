<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cleanup PR-5: 修復 phone 重複註冊漏洞。
 *
 * 既有 users.phone 是 encrypted（IV 隨機），無法用 SQL `WHERE phone = ?` 比對相同明文。
 * 結果：可重複註冊同手機號 → 同人多帳 + 反詐機制失效。
 *
 * 修法：新增 phone_hash 欄位（SHA-256 of E.164 normalized phone），
 *      註冊時改用 phone_hash 比對。
 *
 * - nullable：避免 backfill 前 migration 失敗（既有 user 待 backfill 命令補值）
 * - UNIQUE：MySQL 對 NULL 不視為衝突，多筆 NULL 可共存（讓 backfill 過程平滑）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_hash', 64)
                ->nullable()
                ->after('phone')
                ->comment('SHA-256 of E.164 normalized phone (User::computePhoneHash)');
        });

        // SQLite 上 unique index 與 MySQL 行為等價（皆允許多筆 NULL）
        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone_hash']);
            $table->dropColumn('phone_hash');
        });
    }
};
