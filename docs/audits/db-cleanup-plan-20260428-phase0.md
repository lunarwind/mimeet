# Staging 資料庫清空計畫（大階段 0 — 金流系統重整前置）

**取證日期：** 2026-04-28
**目標環境：** Droplet staging（mimeet-staging / 188.166.229.100）
**用途：** 大階段 1（金流環境切換）+ 大階段 2（支付紀錄完整性）的清空前置

---

## 章節 0 — 環境取證

| 項目 | 值 |
|---|---|
| 本機 branch | `develop` |
| 本機 commit | `2feb16a` fix(trial) |
| Droplet commit | `a9363e3`（merge commit，內容與本機一致）|
| 本機 vs Droplet | Droplet 領先 1 merge commit，程式碼實質相同 |
| DB container | `mimeet-db` |
| App container | `mimeet-app` |
| Redis container | `mimeet-redis` |
| Worker container | `mimeet-worker`（Up 15 min）|
| Scheduler container | `mimeet-scheduler`（Up 15 min）|
| DB 憑證 | `mimeet_user / mimeet_staging_2026` |

---

## 章節 1 — 表清單分類

**實際 COUNT(*) 查詢（非 information_schema 估算）**

注意：`information_schema.TABLE_ROWS` 顯示的是清空前的舊估算值（如 users=2、payments=11 等），這些是 InnoDB 的緩存。COUNT(*) 才是真實值。

### 🟢 保留（9 張）

| 表名 | 用途 | 實際筆數 |
|---|---|---|
| `migrations` | Laravel migration 紀錄 | 50 |
| `system_settings` | 系統設定（含 ECPay key — 階段 1 會重整）| 68 |
| `admin_permissions` | RBAC 權限定義 | 13 |
| `admin_role_permissions` | 角色-權限對應 | 15 |
| `member_level_permissions` | 會員等級功能矩陣 | 50 |
| `subscription_plans` | 訂閱方案定義 | 15 |
| `point_packages` | 點數方案定義 | 10 |
| `seo_metas` | SEO meta tags | 3 |
| `admin_users` | 管理員帳號（Q1=A 全清後 seeder 重建，現有 3 筆）| 3 |

### 🟢 uid=1 官方帳號（已重建，繼續保留）

| 欄位 | 值 |
|---|---|
| `id` | 1 |
| `email` | admin@mimeet.club |
| `nickname` | MiMeet 官方 |
| `gender` | female |
| `birth_date` | 2000-04-04 |
| `membership_level` | 3.0 |
| `credit_score` | 100 |
| `status` | active |
| `style` | intellectual |
| `dating_budget` | luxury |
| `relationship_goal` | long_term |
| `users AUTO_INCREMENT` | 3（下一個新用戶 id=3，uid=1 不會重複）|

### ✅ 已清空（26 張，全部 0）

所有業務資料表均已由前兩輪清空：

| 表名 | 實際筆數 |
|---|---|
| orders | 0 |
| payments | 0 |
| point_orders | 0 |
| point_transactions | 0 |
| credit_card_verifications | 0 |
| subscriptions | 0 |
| credit_score_histories | 0 |
| user_activity_logs | 0 |
| user_profile_visits | 0 |
| admin_operation_logs | 0 |
| broadcast_campaigns | 0 |
| conversations | 0 |
| messages | 0 |
| notifications | 0 |
| reports / report_followups / report_images | 0 |
| fcm_tokens | 0 |
| user_blocks / user_follows / user_broadcasts | 0 |
| date_invitations | 0 |
| user_verifications | 0 |
| password_reset_tokens | 0 |
| failed_jobs | 0 |

### 🟡 待最終清理（1 張）

| 表名 | 實際筆數 | 說明 |
|---|---|---|
| `personal_access_tokens` | 2 | admin 登入 session token；使用者為 admin_users，非業務用戶 token |

---

## 章節 2 — FK 依賴關係

從 `information_schema.KEY_COLUMN_USAGE` 查詢確認（Droplet 實際值）：

```
conversations → users (user_a_id, user_b_id)
credit_card_verifications → users
credit_score_histories → users
date_invitations → users × 2
fcm_tokens → users
messages → conversations, users
notifications → users
orders → subscription_plans, users
payments → users
point_orders → point_packages, users
point_transactions → users
reports → users × 2
report_followups → reports
report_images → reports
subscriptions → orders, subscription_plans, users
user_activity_logs → users
user_blocks → users × 2
user_broadcasts → users
user_follows → users × 2
user_profile_visits → users × 2
user_verifications → users
```

**由於業務表已全部清空，本輪 FK 排序無實質影響。**

---

## 章節 3 — uid=1 重建計畫

### 3.1 程式碼引用點（從本機 grep 確認）

| 檔案 | 行號 | 引用方式 | 用途 |
|---|---|---|---|
| `App/Http/Controllers/Api/V1/Admin/DatasetController.php` | 26, 125 | `User::where('id', '!=', 1)` | 儀表板統計排除官方帳號 |
| `App/Jobs/SendBroadcastJob.php` | 29 | `User::where('id', '!=', 1)` | 廣播排除官方帳號 |
| `App/Console/Commands/ResetToCleanState.php` | 67–99 | `updateOrInsert(['id' => 1], [...])` | mimeet:reset 重建邏輯 |

**結論：** uid=1 沒有「作為 FK 來源」的引用，只被用來**排除**於統計和廣播之外。

### 3.2 重建方案評估

| 方案 | 說明 | 可行性 |
|---|---|---|
| **A（採用）** | `php artisan mimeet:reset --force` | ✅ 已實作，完整 id=1 updateOrInsert |
| B | 新寫 OfficialUserSeeder | 不必要，A 已足夠 |

**推薦 A：`mimeet:reset --force`。** `ResetToCleanState.php` 已包含：
- `updateOrInsert(['id' => 1], [...])` — 9 個 F27 欄位齊備
- `ALTER TABLE users AUTO_INCREMENT = 2` — uid=1 預留

### 3.3 現況確認

**uid=1 已在前一輪清空後透過 `mimeet:reset --force` 重建完成。**
- email: admin@mimeet.club ✅
- membership_level: 3 ✅
- credit_score: 100 ✅
- 所有 F27 欄位設置完整 ✅
- users AUTO_INCREMENT = 3（uid=1 已佔用，不會被新用戶覆蓋）✅

---

## 章節 4 — announcements 表

`SHOW TABLES LIKE '%announce%'` 結果為空：**announcements 表不存在於 Droplet**。Q2 跳過。

---

## 章節 5 — system_settings ECPay 現況（Phase 1 預覽）

目前 system_settings 有**新舊兩套 ECPay key 並存**：

### 舊格式（Phase 1 將刪除）

| key_name | 問題 |
|---|---|
| `app.mode` | dot notation，legacy |
| `ecpay_is_sandbox` | boolean 格式，已被 ecpay_environment 取代 |
| `ecpay_merchant_id` | 無環境前綴，孤兒 key |
| `ecpay_invoice_merchant_id` | 無環境前綴 |
| `ecpay_invoice_hash_key` | 明文，無環境前綴 |
| `ecpay_invoice_hash_iv` | 明文，無環境前綴 |

### 新格式（Phase 1 將完善）

| key_name | 現值 | 狀態 |
|---|---|---|
| `ecpay_environment` | sandbox | ✅ |
| `ecpay_sandbox_merchant_id` | 3002607 | ✅ |
| `ecpay_sandbox_hash_key` | 加密 | ✅ |
| `ecpay_sandbox_hash_iv` | 加密 | ✅ |
| `ecpay_production_merchant_id` | 空 | ✅（待後台填）|
| `ecpay_production_hash_key` | 空 | ✅ |
| `ecpay_production_hash_iv` | 空 | ✅ |
| `ecpay_invoice_enabled` | 0 | ✅ |
| `ecpay_invoice_donation_love_code` | 168001 | ✅ |
| `app_mode` | normal | ⚠️（Phase 1 改為 testing）|
| `ecpay_invoice_*_{env}` | 缺 sandbox/production 分組 | ⚠️（Phase 1 補齊）|

---

## 章節 6 — 預期影響

| 項目 | 現況 |
|---|---|
| 業務資料 | **全部為 0**（前兩輪已清空）|
| uid=1 官方帳號 | **已重建**（admin@mimeet.club，Lv3）|
| admin_users | **已重建**（3 筆：super_admin + admin + cs）|
| Redis | **已 FLUSHALL**（前一輪）|
| 備份 | **已建立** `/root/db-backups/mimeet-pre-cleanup-20260428-141433.sql.gz` SHA256: `32fedeeef5d...` |
| personal_access_tokens | 2 筆（admin session token，可選清理）|

**大階段 0 結論：實質上已完成。** 僅餘 `personal_access_tokens` 2 筆待確認是否清除。

---

## 章節 7 — 大階段 0 最終行動

### 7.1 唯一待執行項目

```sql
-- 清除 admin session token（可選，2 筆，admin 下次登入會重新取得）
DELETE FROM personal_access_tokens;
ALTER TABLE personal_access_tokens AUTO_INCREMENT = 1;
```

### 7.2 不需執行

- ❌ 不需再次 mysqldump（備份已建立）
- ❌ 不需停 worker（上輪已重啟，目前 Up）
- ❌ 不需重跑 uid=1 seeder（已重建完成）
- ❌ 不需重跑 admin_users seeder（已完成，3 筆）

---

## 狀態追蹤

| 項目 | 狀態 |
|---|---|
| 業務資料清空 | ✅ 已完成（前兩輪）|
| uid=1 重建 | ✅ 已完成（admin@mimeet.club）|
| admin_users 重建 | ✅ 已完成（3 筆）|
| Redis FLUSHALL | ✅ 已完成（前一輪）|
| 備份 + 7 天 cron | ✅ 已完成（前一輪）|
| personal_access_tokens 清理 | ⏳ 待確認 |
| 大階段 0 完成確認 | ⏳ 等使用者確認 |
