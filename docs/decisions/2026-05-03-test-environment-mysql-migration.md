# Test Environment 改用 MySQL（停止 SQLite 支援）

**日期**：2026-05-03
**決策者**：User
**狀態**：✅ 已執行（PR-Revert）
**程式碼基準**：branch `develop` HEAD（PR-5 之後）
**相關 PR**：PR-AB（測試解鎖）、PR-3（reports 表 SQLite 修補）、PR-Revert（本 PR）

---

## 背景

User 質疑：production / staging 都是 MySQL，為什麼 `phpunit.xml` 設 `DB_CONNECTION=sqlite`？
PR-AB / PR-3 為此加了大量 SQLite 相容性 guards（migration 內 if/else），但對 production 無用。

## Audit 結果

`git show 6ab6cde -- backend/phpunit.xml` 確認 PR-AB **沒動** `DB_CONNECTION`。
DB_CONNECTION=sqlite 為**專案歷史遺留**（推測為 Laravel 預設模板，未被檢視）。

PR-AB / PR-3 順著既有 SQLite 路線補相容性 = **策略錯誤但非執行失誤**。
本 PR-Revert 矯正此策略。

## 決議

- 測試環境改用 MySQL test database（`mimeet_test`）
- Revert PR-AB / PR-3 加的 12 條 migration 中的 SQLite guards
- 未來 migration **不再考慮 SQLite 相容性**
- 開發者本機測試 = MySQL container（`docker exec mimeet-backend php artisan test`）

## 理由

1. **測試環境與 production 一致**是測試的基本要求
2. SQLite 與 MySQL 在 ENUM / JSON / UUID()/ MODIFY COLUMN 等行為不同，**可能造成「測試 pass 但 production fail」**
3. 維護兩套相容性增加長期成本（每個新 migration 都要記得加 driver guard）
4. 與 production 對齊降低未來 schema 變更風險

## 測試結果（驗證）

| 階段 | 通過 | 失敗 |
|---|---|---|
| Before PR-Revert（SQLite）| 153 | 37 |
| **After PR-Revert（MySQL）** | **170** | **20** |

**+17 通過 / -17 失敗** —— 證實 SQLite 路徑掩蓋了真實 bug：
- §A.1 admin auth pattern：17 條 → 1 條（−16，多數為 SQLite 行為差異掩蓋）
- §A.5 credit_score type 命名：2 條 → 0 條（−2）
- 其他類別維持不變（皆為與 SQLite 無關的真實議題）

剩餘 20 條失敗全部為 PR-AB 評估報告 §A 已知 backlog（ECPay HashKey、admin token revoke、mock route 已刪、StatsController system user 計入等），**無 PR-Revert 引入的新失敗**。

## 影響

- ✅ 測試 boot 改用 MySQL container，啟動稍慢但可接受（~14s vs SQLite ~17s 反而更快）
- ✅ migration 程式碼簡化（移除 SQLite 分支）
- ✅ production / staging 完全不受影響（migration 已 ran，僅移除無用分支）
- ✅ 揭露真實 bug 17 條（原本被 SQLite 行為掩蓋）

## Revert 範圍（共 12 條 migration + 2 個配置檔）

**配置**：
- `backend/phpunit.xml`：`DB_CONNECTION` sqlite → mysql + 補 DB_HOST/DB_DATABASE 等
- `backend/tests/CreatesApplication.php`：同步 testEnv array
- `backend/.env.testing.example`：新增（文件範本）

**Migration revert**：

| Pattern | 檔案 | 處理 |
|---|---|---|
| A 早期 skip | `add_appeal_fields.php` | 移除 `if (!sqlite) {}` 包覆 |
| A 早期 skip | `widen_phone_column_for_encryption.php` | 同上 |
| A 早期 skip | `fix_reports_type_enum_and_nullable_reported_user_id.php` | 同上 |
| A 早期 skip | `make_date_invitations_coords_nullable.php` (PR-AB) | 同上 |
| A 早期 skip | `add_super_like_to_notifications_type.php` | 同上 |
| A 早期 skip | `payment_records_integrity.php` (PR-AB) | 同上 |
| A 早期 skip | `make_payment_id_nullable_on_business_tables.php` (PR-AB) | 同上 |
| A 早期 skip | `add_subscription_expired_to_notifications_type.php` (PR-AB) | 同上 |
| B 包覆 | `create_users_table.php` | 移除 ALTER TABLE 的 if-mysql 包覆 |
| B 包覆 | `change_membership_level_to_decimal_in_users.php` | 同上 |
| C 雙分支 | `create_reports_table.php` (PR-3) | 移除 SQLite 分支（VARCHAR/nullable），保留 MySQL ENUM/UUID() |
| C 雙分支 | `create_notifications_and_fcm_tokens_tables.php` (PR-AB) | 移除 SQLite VARCHAR 分支，保留 MySQL ENUM |

## 未來規範

- 任何 SQLite-incompatible migration 語法（`UUID()`、`ALTER ENUM`、`MODIFY COLUMN`）一律可用，**不再加 driver guard**
- 本機開發者必須 `docker compose up mysql` 後才能跑測試
- 測試指令：`docker exec mimeet-backend php artisan test`
- pre-merge-check 可考慮加守護，禁止 migration 出現 `DB::getDriverName()` 比對（避免回歸）

## 不 revert 範圍

- `config/broadcasting.php` 的 `'reverb' → 'log'` fallback（PR-AB 改）— **保留**
  - 與 SQLite 議題無關
  - 純為避免 .env 漏設 `BROADCAST_DRIVER` 時 BroadcastServiceProvider boot crash
  - production .env 顯式設 `reverb`，pre-merge-check 守護

## 相關文件

- `backend/.env.testing.example`（新增）
- `backend/phpunit.xml` / `backend/tests/CreatesApplication.php`（已更新）
- 12 條 migration（已 revert）
