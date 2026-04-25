# SESSION SUMMARY 2026-04-25

## CreditScoreHistory.type 欄位規格化（Issue #1）

### 背景
2026-04-25 admin 分數頁修復 P4 §A.2 發現 `credit_score_histories.type` 欄位的
DB 實際值與 DEV-008 §10.3 規格定義不一致，本次對齊。

### 最終 14 個枚舉值
`email_verify` / `phone_verify` / `adv_verify_male` / `adv_verify_female` /
`date_gps` / `date_no_gps` / `date_noshow` / `report_submit` /
`report_result_refund` / `report_result_penalty`（新增）/ `admin_reward` /
`admin_penalty` / `content_violation` / `appeal_refund`（新增）

### 對照修改清單

| 舊 type 值 | 新 type 值 | 是否可精準還原 | 說明 |
|-----------|-----------|------------|------|
| `email_verified` | `email_verify` | ✅ 精準 | 單純改名 |
| `phone_verified` | `phone_verify` | ✅ 精準 | 單純改名 |
| `date_verified` | `date_gps` / `date_no_gps` | ⚠️ 舊資料還原為 date_gps | A-1 依 GPS 分路 |
| `admin_adjust` | `admin_reward` 或 `admin_penalty` | ⚠️ 合併 → admin_adjust | B-1 依 delta 符號分路 |
| `admin_set` | 同上 | ⚠️ 合併 → admin_adjust | B-1 |
| `report_filed` | `report_submit` | ⚠️ 合併 → report_filed | 合入，喪失提交者/被舉者區分 |
| `report_received` | `report_submit` | ⚠️ 合併 → report_filed | 同上 |
| `report_penalty` | `report_result_penalty` | ✅ 精準 | C-1 規格擴充，單純改名 |
| `report_dismissed` | `report_result_refund` | ✅ 精準 | 語意改名 |
| `report_cancelled` | `report_result_refund` | ⚠️ 合入 report_dismissed | C-2 合併，用戶自取消退分 |
| `appeal_approved` | `appeal_refund` | ✅ 精準 | C-1 規格擴充，單純改名 |
| `verification_approved` | `adv_verify_female` | ✅ 精準 | 單純改名 |

### 決策論證

**report_result_penalty（規格擴充，非合入）**：
與 `report_result_refund` 對稱命名，保留「檢舉屬實額外處分」的語意獨立性。
管理員在後台需要看到「因為什麼原因扣分」，若合入 `admin_penalty` 則稽核報告
無法區分系統自動處分與管理員主動懲罰，對法遵追查有實質影響。

**appeal_refund（規格擴充，非合入 admin_reward）**：
申訴核准在業務流程中是一個「特定程序」，合入 `admin_reward` 會讓申訴核准
與一般管理員獎勵無法在分數歷史中區分，影響停權/解停稽核流程的可追溯性。

### 資訊遺失說明
- `admin_adjust` / `admin_set` 合併：舊資料中的正向調整被還原為 admin_adjust，
  未來應由 admin_reward/admin_penalty 分開語意。
- `report_filed` / `report_received` 合併：提交者與被舉者的記錄統一為 report_submit，
  reason 欄位仍保留「送出檢舉」vs「被他人檢舉」的文字區分，可用 reason 輔助判斷。

### 補強項目
- `score_delta` validate rule 補上 `not_in:0`（AdminController:331）
- delta=0 在 adjust_score action 層已被 `not_in:0` 攔截，不會寫入 type 中性值

### Pre-merge 守護
新增 14i：靜態確認所有 adjust 呼叫不使用舊枚舉值

### 解鎖後續
Issue #5（scoreColumns 新增 type 欄 + 中文對照表）可以開工

---

## Model $casts 全面體檢（延伸 2026-04-25 CreditScoreHistory hotfix）

### 背景
CreditScoreHistory hotfix 後，對全部 28 個 model 進行同類體檢。
聚焦 `$timestamps = false` 的 12 個 model（Eloquent 不自動 cast created_at），
以及其他 model 的自訂 datetime 欄位（expires_at / paid_at 等）。

### 體檢結果
- **🔴 高風險（會 500）**：無
- **🟠 中風險（格式不一致）**：DateInvitation.created_at（已修復）
- **🟡 低風險（完整）**：其餘 27 個 model 全部 ✅

### 補齊清單
- `DateInvitation`：新增 `'created_at' => 'datetime'` cast
  - 問題：`$timestamps = false`，`created_at` 在 `$fillable` 但不在 `$casts`
  - 影響：`DateInvitationController.php:62,115` 和 `DateController.php:75` 回傳原始 MySQL 字串格式（`"2026-04-25 06:10:27"`）而非 ISO 8601
  - 修復後：回傳 ISO 8601（`"2026-04-25T...Z"`），與其他 API 格式一致
  - **行為變更**：僅影響 created_at 的序列化格式，前端若已用 dayjs/new Date 解析兩種格式均可接受

### Pre-merge 守護
新增 14h：靜態確認 DateInvitation 有 created_at datetime cast

### 未處理項目（獨立追蹤）
- $dates 舊寫法統一遷移到 $casts（Laravel 11 deprecated），28 個 model 中無使用 $dates
- migration 中 timestamp 欄位 nullable 一致性（另立 issue）
- Message.sent_at 等非 null-safe 的 toISOString() 呼叫（null-safety issue，非 cast issue）

---

## Admin 會員分數頁 Crash 修復

### 問題
`GET /api/v1/admin/members/{id}/credit-logs` 回傳結構與規格 API-002 §4.4
不一致（後端多了 `data.current_score + data.logs` 包裝層，欄位名也不符規格），
導致 `MemberDetailPage` 的 Ant Design Table 拿到物件而非陣列，crash。

### 解法
分兩個 commit（方案 A1 止血 + 方案 B 完整修復）：
- **Commit 1（止血）**：前端對齊現況後端結構（`res.data.data?.logs`）
- **Commit 2（atomic 完整修復）**：後端改為規格結構，前端同步更新

### 後端 Commit 2 變更摘要
- `data` 直接回傳 array（移除 `current_score` + `logs` 包裝層）
- 欄位重命名：`delta→change`, `score_before→before`, `score_after→after`
- `operator_id`（int）→ `operator: { id, name } | null`（JOIN admin_users）
- N+1 修復：`with('adminUser')` eager loading
- `meta.current_page` → `meta.page`（對齊 API-002 §4.4 規格）
- 新增 `CreditScoreHistory::adminUser()` belongsTo 關聯

### 評估排除項（獨立 issue）
- **type 欄位顯示**：DB 值不符規格（`email_verified` vs 規格 `email_verify`），需先做 type 規格化
- **TypeScript strict mode**：admin/ 既有 3 處 as any，拆獨立 issue 整體啟用
- **AdminUser 軟刪除/FK**：無區分「系統觸發 null」vs「已刪管理員 null」的需求，統一顯示「—」
- **operator_id 寫入端**：全面檢查無漏傳，不需補修

### 後台架構決策
admin 後台**不引入** mapper 層（與前台 `frontend/src/api/chat.ts` 等分散式 mapper 不同）。
後台直接消費後端回傳的 snake_case 欄位，TypeScript interface 也以 snake_case 定義。
若未來後端大規模改欄位命名風格，或有多版本 API 需兼容，才評估引入 admin mapper 層。

### meta 分頁欄位決策
`credit-logs` meta 欄位名已順便從 `current_page` 校正為 `page`（符合 API-002 §4.4），
因為前端不讀 meta（pagination 硬編），校正成本為 1 行，徹底消除技術債。

### Pre-merge 守護
新增 14a-1, 14a-2, 14b–14f, 14g 共 7 條 check（見 `scripts/pre-merge-check.sh`）：
- data 結構（直接 array、無 logs 包裝層）
- 欄位名（change/before/after/operator/page）
- N+1 修復（with adminUser）
- 前端 optional chaining 守護（14g：`op?.name`）

### CreditScoreHistory operator_id null 三類情境
1. 系統自動觸發（email/phone 驗證、QR 約會、檢舉雙方扣分等）→ 預期 null
2. 管理員觸發（手動調整、申訴核准、女性驗證審核等）→ 傳入 admin_id（已驗證無漏傳）
3. 已刪除管理員（AdminUser 無軟刪除、operator_id 無 FK SET NULL）
   → JOIN 結果 null，與情境 1 無法區分，本次採「統一顯示 —」簡化策略
