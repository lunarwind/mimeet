# Staging 資料庫清空計畫

**執行日期：** 2026-04-28
**目標環境：** Droplet staging（root@188.166.229.100 / mimeet.online）
**本機 docker：** 不在範圍內
**目的：** A'' 金流改造前清空業務資料，保留系統設定結構

---

## 章節 0 — 環境取證

| 項目 | 值 |
|---|---|
| 本機 git branch | `develop` |
| 本機 commit | `2feb16a` fix(trial): 修復體驗方案永遠顯示已使用過的 bug |
| Droplet git commit | `a9363e3` Merge: fix(trial)... |
| 本機與 Droplet 同步狀態 | **本機落後 1 commit（Droplet 有 merge commit）；程式碼內容一致** |
| Droplet DB container | `mimeet-db`（mysql:8.0，healthy，Up 3 days）|
| Droplet app container | `mimeet-app`（Up 3 days）|
| Droplet Redis container | `mimeet-redis`（redis:7-alpine，healthy）|
| Droplet Worker container | `mimeet-worker`（Up 56 min）|
| Droplet Scheduler container | `mimeet-scheduler`（Up 3 days）|
| DB 憑證 | user=`mimeet_user`，db=`mimeet`（從 .env 確認）|
| Docker compose | `docker-compose.staging.yml` |

**DB 連線語法（本文所有 SQL 均使用）：**
```bash
ssh mimeet-staging "docker exec mimeet-db mysql -u mimeet_user '-pmimeet_staging_2026' mimeet -e '<SQL>' 2>/dev/null"
```

---

## 章節 1 — 表清單分類

**Droplet 實際 SQL 查詢結果（`information_schema.TABLES` WHERE `TABLE_SCHEMA = 'mimeet'`）：**

共 **36 張表**。

### 🟢 保留（8 張）— 系統運作必要，不清

| 表名 | 用途 | 當前筆數 | 分類依據 |
|---|---|---|---|
| `migrations` | Laravel migration 執行紀錄 | 50 | 不清，否則下次 migrate 會重跑所有 |
| `system_settings` | 系統設定（ECPay key / app_mode / 誠信分數設定 / 會員功能矩陣等）| 68 | 保留；A'' 階段會重整 ECPay key |
| `admin_permissions` | 後台 RBAC 權限定義（11 條）| 11 | 系統定義，由 seeder 維護 |
| `admin_role_permissions` | 角色-權限對應關係 | 13 | 系統定義，由 seeder 維護 |
| `member_level_permissions` | 會員等級功能矩陣（Lv0–Lv3 各功能開關）| 50 | 系統定義，由 seeder 維護 |
| `subscription_plans` | 訂閱方案定義（月費/季費/年費/體驗）| 15 | 產品設定，不是業務資料 |
| `point_packages` | 點數包方案定義 | 10 | 產品設定，不是業務資料 |
| `seo_metas` | SEO meta tags（/ / /login / /register 三條）| 2 | 後台設定，非用戶產生資料 |

### 🔴 清空（27 張）— 業務資料，要清

| 清空順序 | 表名 | 用途 | 當前筆數 | FK 說明 |
|---|---|---|---|---|
| 1 | `messages` | 聊天訊息 | 0 | → conversations + users |
| 2 | `report_followups` | 舉報追蹤留言 | 0 | → reports |
| 3 | `report_images` | 舉報截圖 | 0 | → reports |
| 4 | `subscriptions` | 用戶訂閱記錄 | 0 | → orders + subscription_plans + users |
| 5 | `fcm_tokens` | 推播 token | 0 | → users |
| 6 | `notifications` | 站內通知 | 0 | → users |
| 7 | `user_profile_visits` | 誰來看我 | 1 | → users × 2 |
| 8 | `user_blocks` | 封鎖關係 | 0 | → users × 2 |
| 9 | `user_follows` | 收藏/關注 | 0 | → users × 2 |
| 10 | `user_broadcasts` | 用戶廣播發送記錄 | 0 | → users |
| 11 | `user_activity_logs` | 用戶行為日誌 | 4 | → users |
| 12 | `user_verifications` | 真人驗證申請 | 0 | → users |
| 13 | `credit_score_histories` | 誠信分數異動記錄 | 2 | → users |
| 14 | `credit_card_verifications` | 信用卡驗證記錄 | 1 | → users |
| 15 | `date_invitations` | 約會邀請 | 0 | → users × 2 |
| 16 | `reports` | 舉報/申訴 | 0 | → users × 2（在 report_followups/images 後）|
| 17 | `point_transactions` | 點數異動記錄 | 21 | → users |
| 18 | `point_orders` | 點數購買訂單 | 3 | → point_packages + users |
| 19 | `payments` | 統一付款記錄（payments 表）| 11 | → users |
| 20 | `conversations` | 聊天對話（在 messages 後）| 0 | → users × 2 |
| 21 | `orders` | 訂閱訂單（在 subscriptions 後）| 5 | → subscription_plans + users |
| 22 | `personal_access_tokens` | Sanctum API tokens | 14 | 無 FK（polymorphic），user 清掉即無意義 |
| 23 | `password_reset_tokens` | 密碼重設 token | 0 | 無 FK，安全性清掉 |
| 24 | `admin_operation_logs` | 後台操作日誌 | 127 | 無 FK，業務/審計資料 |
| 25 | `broadcast_campaigns` | 廣播活動記錄 | 0 | 無 FK |
| 26 | `failed_jobs` | 失敗的 Queue Jobs | 0 | **必清**：防止殭屍 job 在 users 清空後繼續嘗試執行 |
| 27 | `users` | 會員（最後清）| **2** | 父表，所有 FK 鏈頭 |

**⚠️ 注意：** `jobs` 表不在清單中（查詢結果未顯示），可能 queue 使用 Redis 而非 DB。確認正確。

### 🟡 待確認（1 張）

| 表名 | 用途 | 當前筆數 | 問題 |
|---|---|---|---|
| `admin_users` | 後台管理員帳號 | 5 | 全清？保留 super_admin？見 Q1 |

**目前 admin_users 內容（已從 Droplet 查詢確認）：**

| id | email | role | is_active |
|---|---|---|---|
| 1 | chuck@lunarwind.org | super_admin | ✅ 啟用 |
| 2 | admin@mimeet.tw | admin | ❌ 停用 |
| 3 | cs@mimeet.tw | cs | ✅ 啟用 |
| 4 | admin@mimeet.club | super_admin | ✅ 啟用 |
| 5 | cs-rbac-test@mimeet.club | cs | ✅ 啟用 |

**目前 users 內容（會員）：**

| id | email | gender | membership_level |
|---|---|---|---|
| 1 | admin@mimeet.club | female | Lv3 |
| 2 | chuckonpad@gmail.com | male | Lv1 |

---

## 章節 2 — FK 依賴關係

從 `information_schema.KEY_COLUMN_USAGE` 查詢（Droplet 實際結果）：

```
conversations       → users (user_a_id, user_b_id)
credit_card_verifications → users
credit_score_histories → users
date_invitations    → users (invitee_id, inviter_id)
fcm_tokens          → users
messages            → conversations, users (sender_id)
notifications       → users
orders              → subscription_plans, users
payments            → users
point_orders        → point_packages, users
point_transactions  → users
report_followups    → reports
report_images       → reports
reports             → users (reporter_id, reported_user_id)
subscriptions       → orders, subscription_plans, users
user_activity_logs  → users
user_blocks         → users (blocker_id, blocked_id)
user_broadcasts     → users (sender_id)
user_follows        → users (follower_id, following_id)
user_profile_visits → users (visitor_id, visited_user_id)
user_verifications  → users
```

**關鍵結論：**
- `users` 是最終父表，**必須最後清**
- `messages` 需在 `conversations` 前清
- `report_followups` / `report_images` 需在 `reports` 前清
- `subscriptions` 需在 `orders` 前清（subscriptions → orders FK）

---

## 章節 3 — 清空順序（含 FOREIGN_KEY_CHECKS = 0 安全措施）

即使 `SET FOREIGN_KEY_CHECKS = 0`，仍依 FK 邏輯排序，確保操作可追溯：

```sql
-- 第一梯次：深子表（有多層 FK 依賴的）
DELETE FROM messages;           -- 1
DELETE FROM report_followups;   -- 2
DELETE FROM report_images;      -- 3
DELETE FROM subscriptions;      -- 4

-- 第二梯次：直接 FK → users 的子表
DELETE FROM fcm_tokens;         -- 5
DELETE FROM notifications;      -- 6
DELETE FROM user_profile_visits;-- 7
DELETE FROM user_blocks;        -- 8
DELETE FROM user_follows;       -- 9
DELETE FROM user_broadcasts;    -- 10
DELETE FROM user_activity_logs; -- 11
DELETE FROM user_verifications; -- 12
DELETE FROM credit_score_histories; -- 13
DELETE FROM credit_card_verifications; -- 14
DELETE FROM date_invitations;   -- 15
DELETE FROM reports;            -- 16（在 followups/images 後）
DELETE FROM point_transactions; -- 17
DELETE FROM point_orders;       -- 18（→ point_packages + users，point_packages 保留）
DELETE FROM payments;           -- 19
DELETE FROM conversations;      -- 20（在 messages 後）
DELETE FROM orders;             -- 21（→ subscription_plans + users，在 subscriptions 後）

-- 第三梯次：無 FK 的業務表
DELETE FROM personal_access_tokens;  -- 22
DELETE FROM password_reset_tokens;   -- 23
DELETE FROM admin_operation_logs;    -- 24
DELETE FROM broadcast_campaigns;     -- 25
DELETE FROM failed_jobs;             -- 26

-- 最後：父表
DELETE FROM users;  -- 27（LAST）
-- admin_users 依 Q1 決定

-- AUTO_INCREMENT 重置（所有清空的表）
ALTER TABLE messages AUTO_INCREMENT = 1;
ALTER TABLE report_followups AUTO_INCREMENT = 1;
ALTER TABLE report_images AUTO_INCREMENT = 1;
ALTER TABLE subscriptions AUTO_INCREMENT = 1;
ALTER TABLE fcm_tokens AUTO_INCREMENT = 1;
ALTER TABLE notifications AUTO_INCREMENT = 1;
ALTER TABLE user_profile_visits AUTO_INCREMENT = 1;
ALTER TABLE user_blocks AUTO_INCREMENT = 1;
ALTER TABLE user_follows AUTO_INCREMENT = 1;
ALTER TABLE user_broadcasts AUTO_INCREMENT = 1;
ALTER TABLE user_activity_logs AUTO_INCREMENT = 1;
ALTER TABLE user_verifications AUTO_INCREMENT = 1;
ALTER TABLE credit_score_histories AUTO_INCREMENT = 1;
ALTER TABLE credit_card_verifications AUTO_INCREMENT = 1;
ALTER TABLE date_invitations AUTO_INCREMENT = 1;
ALTER TABLE reports AUTO_INCREMENT = 1;
ALTER TABLE point_transactions AUTO_INCREMENT = 1;
ALTER TABLE point_orders AUTO_INCREMENT = 1;
ALTER TABLE payments AUTO_INCREMENT = 1;
ALTER TABLE conversations AUTO_INCREMENT = 1;
ALTER TABLE orders AUTO_INCREMENT = 1;
ALTER TABLE personal_access_tokens AUTO_INCREMENT = 1;
ALTER TABLE admin_operation_logs AUTO_INCREMENT = 1;
ALTER TABLE broadcast_campaigns AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;
-- admin_users AUTO_INCREMENT = 1; -- 依 Q1 決定
```

---

## 章節 4 — 預期影響

| 指標 | 值 |
|---|---|
| 需清空的表總數（不含待確認）| 27 張（+ admin_users 待確認）|
| 需清空的筆數合計（估算）| ~191 筆（主要是 admin_operation_logs 127 + point_transactions 21 + payments 11 + personal_access_tokens 14）|
| 清空後 DB 大小 | 約從 ~1.1 MB 縮至 ~0.6 MB（目前資料量本身就很小）|
| 清空後 admin 後台是否可登入 | **取決於 Q1**；若保留 id=1 (chuck@lunarwind.org) 則可 |
| 清空後 users 總數 | 0（或依 Q3）|
| 測試者重新註冊流程 | 是（除非 Q3 選 C 保留現有測試帳號）|
| Redis queue 殭屍 job | **潛在風險**（jobs 表不存在代表 queue driver = Redis），需清 |

---

## 章節 5 — 待使用者確認的問題

### Q1：admin_users 處理方式？

目前有 5 筆，其中 id=1 `chuck@lunarwind.org / super_admin` 是主要管理員帳號。

| 選項 | 說明 | 建議 |
|---|---|---|
| **A：只保留 id=1（chuck@lunarwind.org）** | 刪 id=2,3,4,5，保留唯一真正在用的 super_admin | **建議** |
| B：保留全部 5 筆 | 保留現有設定（含已停用的測試帳號）| 保守 |
| C：全清 + seeder 重建 | 清空後 seeder 重建，密碼 `ChangeMe@2026` | 最乾淨，但需確認 AdminUserSeeder 存在 |

> **選項 A 的執行 SQL（精確刪除指定行）：**
> ```sql
> DELETE FROM admin_users WHERE id != 1;
> ```

---

### Q2：announcements 表

`announcements` 表**不在 Droplet 的 36 張表中**（查詢結果未出現）。跳過此問題。

---

### Q3：是否 seed 測試會員？

清空後 `users` 為空，測試者需要帳號才能走完付款流程。

| 選項 | 說明 | 建議 |
|---|---|---|
| **A：不 seed，測試者自行從前台註冊** | 最真實，測試完整的 onboarding 流程 | **建議** |
| B：Seed 標準測試組合（1男Lv1.5 + 1女Lv2 + 1男Lv0）| 需確認 TestUsersSeeder 是否存在或需要新寫 | 快速但需開發 |
| C：保留現有 2 筆用戶 | chuckonpad@gmail.com (Lv1) + admin@mimeet.club (Lv3) | 快速，但帳號跟 admin 帳號重疊，可能混淆 |

---

### Q4：備份保留多久？

建議 30 天，存於 Droplet `/root/db-backups/`。
> **確認：是否同意 30 天？** 是

---

### Q5：是否清 Redis（queue + cache）？

**背景：** `jobs` 表不在 DB 中（36 張表無此表），確認 queue driver = Redis。

可能有殘留的 queue jobs（如 `RefundCreditCardVerificationJob`），清空 users 後這些 job 跑起來會 fail（user_id 不存在）。

| 選項 | 說明 | 建議 |
|---|---|---|
| **A：FLUSHDB（清全部 Redis keys）** | 徹底清乾淨：cache + session + queue + rate limits | **建議** |
| B：只清 queue-related keys | 保留 cache + session — 但 session 裡的 user_id 指向已清的 user，訪問時 500 | 不建議 |
| C：不清 Redis | 最高風險：殭屍 job + 失效 session | 不建議 |

---

## 章節 6 — 備份資訊（階段 2 完成）

### DB 備份

| 項目 | 值 |
|---|---|
| 備份路徑 | `/root/db-backups/mimeet-pre-cleanup-20260428-140104.sql.gz` |
| 備份大小 | 24K |
| SHA256 | `f658edf7982682925de665c8903d075f7749dbfdeb7ac84d5a1c55d30031d7f6` |
| 完整性驗證 | ✅ gzip -t 通過 |
| 內容驗證 | ✅ users / system_settings / subscription_plans / admin_users / admin_permissions 全有 INSERT |

### Redis 備份

| 項目 | 值 |
|---|---|
| 備份路徑 | `/root/redis-backups/redis-pre-cleanup-20260428-140124.rdb` |
| 備份大小 | 1.9K |

### 應急還原指令（僅在需要時執行）

```bash
ssh mimeet-staging '
# 1. 還原 DB
BACKUP="/root/db-backups/mimeet-pre-cleanup-20260428-140104.sql.gz"
echo "Restoring DB from: $BACKUP"
gunzip -c "$BACKUP" | docker exec -i mimeet-db mysql -u mimeet_user -pmimeet_staging_2026 mimeet

# 2. 清除 Laravel cache
docker exec -u www-data mimeet-app php artisan cache:clear
docker exec -u www-data mimeet-app php artisan config:cache

# 3. 出 maintenance mode
docker exec -u www-data mimeet-app php artisan up

# 4. 還原 Redis（如需要）
# docker cp /root/redis-backups/redis-pre-cleanup-20260428-140124.rdb mimeet-redis:/data/dump.rdb
# docker restart mimeet-redis
'
```

---

## 狀態追蹤

| 階段 | 狀態 | 備份 SHA256 |
|---|---|---|
| 階段 1：盤點（本文）| ✅ 完成 | — |
| 階段 2：備份 | ✅ 完成（2026-04-28 14:01）| `f658edf798...` |
| 階段 3：清空 | ✅ 完成（2026-04-28 14:0x）| — |
| 階段 4：重建 + 驗證 | ✅ 完成（2026-04-28 14:0x）| — |

### 使用者確認的決策

| 問題 | 答案 |
|---|---|
| Q1 admin_users | **A：只保留 id=1（chuck@lunarwind.org）** |
| Q3 測試會員 | **A：不 seed，測試者自行從前台註冊** |
| Q4 備份保留 | **30 天** |
| Q5 清 Redis | **A：FLUSHDB（全清）** |
