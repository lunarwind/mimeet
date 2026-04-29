# Staging 資料庫清空計畫（v2）

**執行日期：** 2026-04-28（第二輪，含 uid=1 重建）
**目標環境：** Droplet staging（mimeet-staging / 188.166.229.100）
**目的：** 完整重置業務資料 + uid=1 官方帳號重建 + admin_users 全清重建

---

## 章節 0 — 環境取證

| 項目 | 值 |
|---|---|
| 本機 git branch | `develop` |
| 本機 commit | `2feb16a` |
| Droplet git commit | `a9363e3`（領先本機 1 commit，merge commit，內容相同）|
| Droplet DB container | `mimeet-db` |
| Droplet app container | `mimeet-app` |
| Droplet Redis container | `mimeet-redis` |
| DB 連線 | `mimeet_user / mimeet_staging_2026` |

**上一輪清空已完成（2026-04-28 14:01）**，本輪為差異補充：
- `users`: 0 ✅
- `orders / payments / point_transactions`: 0 ✅
- `admin_users`: **1 筆**（chuck@lunarwind.org，舊 Q1=A 保留）→ 本輪要全清 + seeder 重建
- `personal_access_tokens`: **2 筆**（admin 的 token，因清空 admin_users 後無效）→ 要清
- `users AUTO_INCREMENT`: 3（需重置為 2，以留空 id=1 給官方帳號）

---

## 章節 1 — 本輪清空範圍

### 1.1 實際仍有資料的表（COUNT(*) 查詢確認）

| 表名 | 本輪實際筆數 | 動作 |
|---|---|---|
| `admin_users` | 1（chuck@lunarwind.org）| 🔴 全清 → seeder 重建 |
| `personal_access_tokens` | 2（admin token）| 🔴 清 |
| `system_settings` | 68 | 🟢 保留 |
| `subscription_plans` | 15 | 🟢 保留 |
| `point_packages` | 10 | 🟢 保留 |
| `admin_permissions` | 13 | 🟢 保留 |
| `admin_role_permissions` | 15 | 🟢 保留 |
| `member_level_permissions` | 50 | 🟢 保留 |
| `seo_metas` | 2 | 🟢 保留（Q2=B）|
| `migrations` | 50 | 🟢 保留 |
| **其他 27 張業務表** | **全部 0** | 已由上輪清空 |
| `users` | 0，但 AUTO_INCREMENT=3 | 需重置 + 用 `mimeet:reset` 重建 uid=1 |

### 1.2 `announcements` 表

SHOW TABLES LIKE `%announce%` 結果為空：**announcements 表不存在於 Droplet**。Q2 跳過。

---

## 章節 2 — uid=1 完整分析

### 2.1 uid=1 官方帳號在 codebase 的引用點

| 檔案 | 行號 | 引用方式 | 用途 |
|---|---|---|---|
| `App/Http/Controllers/Api/V1/Admin/DatasetController.php` | 26, 125 | `User::where('id', '!=', 1)->count()` | 統計儀表板排除官方帳號 |
| `App/Jobs/SendBroadcastJob.php` | 29 | `User::where('id', '!=', 1)` | 廣播排除官方帳號 |
| `App/Console/Commands/ResetToCleanState.php` | 63, 67 | `updateOrInsert(['id' => 1], [...])` | mimeet:reset 的重建邏輯 |

**結論：** uid=1 不是用來當「FK 來源」，而是被**排除**於統計和廣播外的系統帳號。沒有其他服務對 uid=1 有硬依賴（如 from_user_id = 1 發系統訊息等）。

### 2.2 既有重建工具

`php artisan mimeet:reset --force` 已包含完整 uid=1 重建邏輯（`ResetToCleanState.php`）：

```php
// 強制 id=1
DB::table('users')->updateOrInsert(
    ['id' => 1],
    [
        'email'            => 'admin@mimeet.club',
        'password'         => bcrypt('SYSTEM_ACCOUNT_DO_NOT_LOGIN'),
        'nickname'         => 'MiMeet 官方',
        'gender'           => 'female',
        'membership_level' => 3,
        'credit_score'     => 100,
        // ... 完整 9 個 F27 欄位
    ]
);
// 設 AUTO_INCREMENT = 2（保留 id=1 給官方帳號，下一個用戶從 id=2 開始）
DB::statement('ALTER TABLE users AUTO_INCREMENT = 2');
```

### 2.3 推薦方案：方案 A — 使用 `mimeet:reset --force`

**為什麼選 A（不另寫 seeder）：**
- `mimeet:reset` 已確保 `id=1` 精確對齊（`updateOrInsert(['id' => 1])`）
- AUTO_INCREMENT 設為 2，確保下一個真實用戶從 id=2 開始
- 所有 F27 欄位都有預設值
- 是 CLAUDE.md 記載的官方工具

---

## 章節 3 — 本輪執行計畫

### 3.1 決策確認

| Q | 答案 | 本輪動作 |
|---|---|---|
| Q1 admin_users | **A：全清 + seeder 重建 super_admin** | `DELETE FROM admin_users` + `AdminUserSeeder` |
| Q2 announcements | **B：保留** | 表不存在，跳過 |
| Q3 測試會員 | **A：不 seed** | 不執行 TestUsersSeeder |
| Q4 備份保留 | **7 天** | 設 cron 自動清理 |
| Q5 Redis | **A：FLUSHALL** | `redis-cli FLUSHALL` |
| uid=1 | **重建** | `mimeet:reset --force` |

### 3.2 完整執行 SQL（本輪差異部分）

本輪有效資料只剩 admin_users(1) + personal_access_tokens(2)，
`mimeet:reset` 會處理業務表和 uid=1。
額外手動 SQL 僅需清這兩張表：

```sql
START TRANSACTION;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM admin_users;               -- 全清（Q1=A），seeder 重建
DELETE FROM personal_access_tokens;    -- admin token 清掉
ALTER TABLE admin_users AUTO_INCREMENT = 1;
ALTER TABLE personal_access_tokens AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
```

### 3.3 完整執行順序

```
[1] 備份（mysqldump + Redis RDB + 設 7 天 cron）
[2] 進 maintenance mode
[3] 停 worker + scheduler
[4] 執行補充 SQL（清 admin_users + personal_access_tokens）
[5] docker exec mimeet-app php artisan mimeet:reset --force
    → 清所有業務表、重建 uid=1、AUTO_INCREMENT = 2
[6] docker exec mimeet-app php artisan db:seed --class=AdminUserSeeder --force
    → 重建 super_admin（chuck@lunarwind.org / ChangeMe@2026）
    → 另外會建 admin@mimeet.tw 和 cs@mimeet.tw（三個 env 預設帳號）
[7] Redis FLUSHALL
[8] cache:clear + config:cache + route:cache
[9] 重啟 worker + scheduler
[10] 出 maintenance mode
[11] Smoke test + uid=1 驗證
```

**關於 AdminUserSeeder 建 3 個帳號：** 這是 seeder 的設計，3 個帳號均為系統管理帳號，非用戶業務資料。接受。

---

## 章節 4 — FK 依賴圖（本輪僅供參考，已無實質影響）

所有子表均已清空（0 筆），本輪只清 admin_users（無 FK）和 personal_access_tokens（無 FK），無排序需求。

mimeet:reset 內部已正確處理 FK 順序（SET FOREIGN_KEY_CHECKS=0 + TRUNCATE 順序）。

---

## 章節 5 — 備份資訊

### DB 備份
| 項目 | 值 |
|---|---|
| 路徑 | `/root/db-backups/mimeet-pre-cleanup-20260428-141433.sql.gz` |
| 大小 | 14K |
| SHA256 | `32fedeeef5d53f67a216188ba1ccdd902f38288a7457dfa1738c59d8e4ad7697` |
| gzip 驗證 | ✅ |
| 內容驗證 | ✅ 36 張 CREATE TABLE + system_settings/subscription_plans/admin_permissions/admin_users 全有 INSERT |

### Redis 備份
| 項目 | 值 |
|---|---|
| 路徑 | `/root/redis-backups/redis-pre-cleanup-20260428-141534.rdb` |
| 大小 | 14K |

### 7 天自動清理 cron
- `/etc/cron.d/db-backup-cleanup` 已設定
- 每日 03:00 自動刪除 7 天前的備份

### 應急還原指令

```bash
ssh mimeet-staging '
BACKUP="/root/db-backups/mimeet-pre-cleanup-20260428-141433.sql.gz"
echo "Restoring from: $BACKUP (SHA256: 32fedeeef5...)"
gunzip -c "$BACKUP" | docker exec -i mimeet-db mysql -u mimeet_user -pmimeet_staging_2026 mimeet
docker exec -u www-data mimeet-app php artisan cache:clear
docker exec -u www-data mimeet-app php artisan config:cache
docker exec -u www-data mimeet-app php artisan up
'
```

---

## 狀態追蹤

| 階段 | 狀態 |
|---|---|
| 階段 1：盤點（本文）| ✅ 完成 |
| 階段 2：備份 | ✅ 完成（2026-04-28 14:14）|
| 階段 3：清空 | ✅ 完成（2026-04-28 14:18）|
| 階段 4：重建 + 驗證 | ✅ 完成（2026-04-28 14:20）|
