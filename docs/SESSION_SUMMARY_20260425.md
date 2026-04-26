# SESSION SUMMARY 2026-04-25

## FCM 設定規範化（2026-04-26）

### 起源
既有 FCM 程式碼（FcmService 183 行 raw HTTP 自幹版、FcmTokenController、
migration、API routes）已完整實作但從未部署到 staging。

使用者準備了一份操作流程包含 SCP credential、改 .env、跑 tinker 測試，
但對照 CLAUDE.md layer 2 規範有 7 個違反點：
1. 用 root@IP 而非 mimeet-staging alias
2. 直接修改 staging .env 但缺 SOP
3. service-account.json 放 storage/app/（可能被 storage:link 暴露）
4. 缺結構化驗證
5. 規格文件未對齊
6. 缺 pre-merge-check 守護
7. Tinker 多層轉義難維護

### 決策
- 用「檔案路徑」模式（FIREBASE_CREDENTIALS_PATH）
- 路徑：`backend/storage/firebase-service-account.json`（避開 storage/app/）
- 本次只做 staging，production 待首次部署 issue
- 順手清理 staging .env 中棄用的 FCM_SERVER_KEY（FCM Legacy API 2024-06 停服）

### 改動範圍

**規範文件**：
- CLAUDE.md 新增「敏感檔案同步流程」段落（在「API Contract 一致性原則」之後）
- 涵蓋 .env、docker-compose、service-account.json 等 .gitignore 內檔案的 SOP
- 特別設計「換 credential 的處置」子段落（三件必做：清 cache、評估 token 失效、驗 project_id）

**Scripts**：
- `backend/scripts/test-fcm.php`：取代 tinker 多層轉義，含 credential 確認 +
  project_id visual confirm（換帳號時用）+ 推播測試 + 退出代碼 0/1/2/3
- `scripts/staging-setup-firebase.sh`：7 步驟 idempotent setup script：
  1. 本機檔案驗證（含 git ls-files 防呆）
  2. SCP 上傳（mimeet-staging alias）
  3. staging 端驗證（chmod 600）
  4. 清理 .env 棄用設定（FCM_SERVER_KEY）
  5. 設定 .env FIREBASE_CREDENTIALS_PATH
  6. config:cache 重建 + `cache:forget fcm_access_token`（換帳號必要）
  7. git pull + 跑 test-fcm.php 驗證

**Pre-merge-check 守護**：
- 14w：禁止 service-account.json 在 git working tree
- 14x：.env.example 必須含 FIREBASE_CREDENTIALS_PATH
- 14y：禁止 .env.example 含棄用的 FCM_SERVER_KEY

### 換帳號設計（一等公民）
1. test-fcm.php 顯示 project_id：每次測試都能 visual confirm 在用哪個 Firebase project
2. Step 6 含 cache:forget：換帳號後強制清舊 access token（FcmService 1 小時 Cache::remember）
3. CLAUDE.md「換 credential 的處置」：規範 rotation / 環境切換的完整 SOP

### 重要注意
FcmService.php 含 stub 模式（APP_ENV != production 時只記 log 不真的發送）。
Staging 的 APP_ENV=staging，test-fcm.php 的「成功」是 stub success，裝置不會收推播。
前端整合完成後若要真實測試，需另行確認 FcmService 的 env 判斷邏輯。

### 不在本次範圍
- 前端 FCM 整合（前端 SDK 安裝、token 註冊邏輯）
- 廣播系統 delivery_mode = fcm 整合
- Production 部署流程
- 既有 FcmService 程式碼（已完整不動）

---

## 全系統 Pagination 規格化 + Issue #2 合併處理（2026-04-26）

### 起源
4/25 admin 分數頁修復順手對齊 credit-logs 的 meta 結構為
`{ page, per_page, total, last_page }`（API-002 §4.4），但發現：
- API-002 規格書內部不一致（§4.4 用新格式，其他章節用舊格式）
- API-001 全書用 `pagination + current_page + total_pages` + `has_next` 等死欄位
- DEV-004 用 `meta + current_page + last_page` + `links` 物件
- 三份規格書沒有任何兩份一致

啟動全系統規格化評估，確認候選 X（精簡版）為最終格式。

### 決策
- 統一格式：`{ data, meta: { page, per_page, total, last_page } }`
- 不保留死欄位：移除 `has_next` / `has_prev` / `next_url` / `prev_url` / `links`
- 三方規格一致：DEV-004 = API-001 = API-002
- 前後端實作一致：後端回傳 = 前後端讀取

### 改動範圍

**規格書（共 3 份）**：
- DEV-004 line 427-433：`current_page` → `page`，移除 `links`
- API-001 共 18 處：`pagination` → `meta`，欄位名統一，移除死欄位
- API-002 line 2353（§13 broadcasts）：對齊新格式

**後端（13 個 controller methods，含遺漏的 visitors + UserActivityLogController）**：
- AdminController：members / tickets / payments（族 B nested）
- Admin/AdminLogController：index（族 C）
- Admin/BroadcastController：index（族 C）
- Admin/VerificationController：pending + index（族 C，兩個 method）
- Admin/UserActivityLogController：index（族 B nested，非原始評估列表）
- UserController：search（族 C nested → meta 移至頂層）+ following（族 B）+ visitors（遺漏補入）
- NotificationController：index（族 D）
- ReportController：index（族 D）
- DateInvitationController：index（族 D）

**前端（8 處讀取點）**：
- 後台 6 行（MembersPage / TicketsPage / PaymentsPage / ActivityLogsPage / UserActivityLogsPage / VerificationsPage）：
  `.data.pagination?.total` → `.meta?.total`，data 讀取路徑同步更新
- 前台 3 行：
  - `frontend/src/types/explore.ts`：Pagination interface（current_page → page, total_pages → last_page）
  - `frontend/src/api/users.ts`：inline interface + mapper return key/path（pagination → meta）
  - `frontend/src/composables/useExplore.ts`：`page < last_page` 計算
- `frontend/src/views/app/FavoritesView.vue`：`data?.users` → `data`（following endpoint 結構改變）

### Issue #2 合併處理
原 Issue #2「後台 4 個 API meta 統一」是本次「全系統規格化」的子集。Issue #2 自動完成。

### CLAUDE.md 新增章節
新增「API Contract 一致性原則」章節（在「API Contract 變更回滾流程」之前），
含 Pagination 標準格式禁止清單與違反歷史教訓。

### Pre-merge-check 新增守護
- 14r：禁止後端用 `'pagination'` wrapper
- 14s：禁止後端用 `'current_page'`
- 14t：禁止後端用 `'total_pages'`
- 14u：禁止前端讀 `.pagination.current_page`
- 14v：禁止前端讀 `.pagination.total_pages`

### 未預期發現（事後補修）
- `UserController::visitors` 遺漏於原始評估列表，補入本次
- `Admin/UserActivityLogController` 屬 nested tribe B 格式，補入本次

---

## CLAUDE.md 整合重寫 Layer 2（2026-04-26）

### 背景
Layer 1（三小修）完成後，推進 Layer 2：完整重寫 CLAUDE.md（312 行 → ~491 行），
補上 Worker 健康檢查盲點修復、三支部署腳本、staging vs production 環境分離、
SSH alias 標準化、pre-merge-check 14p。

### 改動範圍

**CLAUDE.md 重寫**
- 新增「環境配置概觀」表格（Staging `.online` / Production `.club`，後者目前全空）
- SSH alias `mimeet-staging` 取代所有硬編 `root@188.166.229.100`
- 部署 script 入口改為 `bash scripts/staging-deploy.sh`（含 `--yes` 旗標）
- Container 變更規則表格（restart vs up --force-recreate 選錯的後果）
- 健康檢查規範（`/api/v1/health` 端點待補，觸發條件：上任何外部監控前）
- Commit scope 清單（11 個規定 scope，禁止自創）
- 例外處置 SOP（止血 → 記錄 → 事後補文件 → 流程修正）
- Audit Framework 新增「證據要求」章節（規格引用 + 程式碼引用雙重）
- Worker 健康檢查盲點移至「已知陷阱」：supervisorctl 為空殼，改用 docker compose ps

**scripts/staging-deploy.sh（新增）**
- 本機端入口：SSH 連線確認 → 部署確認 → 觸發伺服器端腳本 → Smoke Test
- `--yes` 跳過確認，支援 CI 模式

**scripts/staging-server-deploy.sh（新增）**
- 伺服器端邏輯：git pull → storage 權限 → migrate → cache → 前後台 build → worker restart
- `set -euo pipefail` + log 寫入 `/var/log/mimeet-deploy/deploy-YYYYMMDD-HHMMSS.log`
- 失敗時自動 tail 50 行 log
- 成功後寫入 `.deploy-version`（git SHA + 時間戳 + deployer）

**scripts/staging-rollback.sh（新增）**
- 無 SHA 引數：讀 `.deploy-version` 自動找前一版
- 有 SHA 引數：直接 reset --hard 到指定 commit
- 不自動回滾 migration（附提醒指令）
- 完整重建前後端 + worker restart + Smoke Test

**scripts/pre-merge-check.sh**
- 新增 14p：`scripts/` 目錄不得出現 `supervisorctl` 字樣

**docs/DEV-001_技術架構規格書.md**
- line 536：`DDD-001` → `DEV-006`（錯誤文件 ID 更正）

### 設計決策

**「不自動回滾 migration」原則**：
若先 git revert 才 migrate:rollback，migration class 不存在會報錯（常見錯誤 2）。
正確順序：先 migrate:rollback --step=N，再 git revert。腳本不自動化此步驟，
因為回滾幾步 migration 需人工判斷，強制自動化反而危險。

**supervisorctl 廢棄**：
`scripts/` 中的 supervisorctl 呼叫一律改用 `docker compose ps worker`。
14p 守護確保不會在未來的腳本中復發。

---

## Admin 分數頁新增 type 欄位（Issue #5，2026-04-26）

### 背景
延伸 Issue #1（type 規格化）的 UX 兌現。P4 §A.4 因 type 不符規格暫緩，
Issue #1 完成 14 個枚舉值對齊後，本次完成 scoreColumns type 欄與中文對照。

### 改動範圍
- `admin/src/constants/creditScoreTypes.ts`（新增）：14 個 type 的中文標籤與 Ant Design Tag 顏色，`getCreditScoreTypeMeta()` 含 console.warn fallback
- `admin/src/types/admin.ts`：ScoreRecord.type 從 `string` 收緊為 `CreditScoreType` union
- `admin/src/pages/members/MemberDetailPage.tsx`：scoreColumns 新增「類型」欄（位於「分數變化」與「原因」之間）
- `docs/DEV-008_誠信分數系統規格書.md` §10.3：擴充中文標籤與 Tag 顏色欄位

### 顏色語意設計
- `green`：加分（系統自動）
- `gold`：加分（管理員，區隔系統 vs 人為）
- `blue`：中性退還（檢舉/申訴，非獎勵）
- `red`：扣分（系統觸發）
- `volcano`：扣分（管理員，深紅警示）

### 未來新增 type 的維護程序
1. 後端 `CreditScoreService::adjust()` 新增 type 字串
2. `DEV-008 §10.3` 補充中文標籤與顏色
3. `admin/src/constants/creditScoreTypes.ts` 新增對應條目（CreditScoreType union + CREDIT_SCORE_TYPE_META）
4. TypeScript 的 Record exhaustiveness check 會在步驟 3 遺漏時編譯報錯

若忘記步驟 3，前端 console.warn 提示但不 crash（fallback 顯示原始字串）。

---

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

## Layer 2 收尾驗證烏龍（2026-04-26）

### 事件
Layer 2 收尾驗證階段，誤判 staging 上 docker-compose.staging.yml 沒有
redis depends_on，引發數輪追查。最終確認：staging 上**確實有** redis
depends_on，兩邊檔案 hash 完全相同。

### 誤判過程
1. 用 `ssh mimeet-staging 'grep -A 15 "^  worker:" docker-compose.staging.yml'`
2. -A 15 從 worker: 那行起算 15 行，剛好截在 db.condition 之後
3. redis depends_on 落在第 16-17 行被截掉
4. 看到「沒有 redis」誤判為真實遺漏
5. 後續追查 .gitignore、git status 等才慢慢釐清

### 真相
- Layer 2 整合 prompt §3.2（修改 docker-compose worker.depends_on）**有正確執行**
- 本機與 staging 上的檔案內容**完全一致**且**含 redis depends_on**
- 兩邊一致並非透過 git（因 docker-compose.staging.yml 在 .gitignore 中）

### 教訓
1. **`grep -A N` 行數要有餘裕**：N 必須 ≥ 預期目標的最後一行位置
   - 改善做法：用 `grep -A 50 "^  worker:" ... | head -30` 留足夠 buffer
2. **驗證指令要對齊「想證明的命題」**：
   - 想證明「worker 段落有 redis」→ 應 grep redis 字串範圍是 worker 段
   - 不該用「看不到 redis 就推論沒有」（可能只是 grep 範圍不夠）
3. **不要在烏龍診斷時隨意連帶 .gitignore 設計討論**：
   .gitignore docker-compose 是另一個議題，跟「redis 是否已加」無關，
   把兩件事混在一起讓追查更亂

### 衍生：docker-compose.staging.yml 在 .gitignore 中（待處理）
診斷過程意外發現 docker-compose.staging.yml 與 docker-compose.yml 都在
.gitignore 中。這意味著：
- 本機改動不會被 commit/push
- Staging 上的檔案實質上是「兩地獨立維護」
- 過去兩邊一致是巧合或人工同步

未來改動 docker-compose 時必須意識到這點。是否從 .gitignore 移除是另一個
issue（涉及敏感資訊處理策略），不在 layer 2 範圍內。
