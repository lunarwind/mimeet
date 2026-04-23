# Session Summary — 2026-04-23

## 本次工作範圍

完成稽核報告 A-H 全部 8 份，產出彙整報告 SUMMARY，並修復三個 Critical 問題。

---

## 稽核報告產出

| 報告 | 檔案 | 嚴重問題 |
|------|------|---------|
| Audit A | `docs/audits/audit-A-20260423.md` | — |
| Audit B | `docs/audits/audit-B-20260423.md` | — |
| Audit C | `docs/audits/audit-C-20260423.md` | — |
| Audit D | `docs/audits/audit-D-20260423.md` | D-001 🔴 /uploads 404 |
| Audit E | `docs/audits/audit-E-20260423.md` | — |
| Audit F | `docs/audits/audit-F-20260423.md` | F-001 🔴 RBAC 未掛載 |
| Audit G | `docs/audits/audit-G-20260423.md` | — |
| Audit H | `docs/audits/audit-H-20260423.md` | H-001 🔴 Report type 422 |
| 彙整 | `docs/audits/SUMMARY-20260423.md` | 78 issues 總計 |

---

## Critical 修復記錄

### CR-3 — fix(reports): report type 422

**Commit:** `c823844`
**根本原因：** 三方分歧。規格用數字 1-4，前端送數字 1-5，後端驗 string enum → 所有 `POST /api/v1/reports` 一律 422。
**決策：** 統一為後端 string enum（DB 已存字串，設計正確）。

| 受影響檔案 | 修改內容 |
|-----------|---------|
| `frontend/src/api/reports.ts` | `type` 從 `number` 改 `ReportType` string union；移除 `TYPE_LABELS` dead code |
| `frontend/src/views/app/ReportsView.vue` | `REPORT_TYPES` value 從 1-5 改 string enum；`selectedType` 型別更新 |
| `backend/app/Http/Controllers/Api/V1/ReportController.php` | validation 改 `in:harassment,impersonation,scam,inappropriate,other`；移除 `Rule::requiredIf` dead code |
| `backend/tests/Feature/ReportTest.php` | 測試資料 `'spam'`→`'harassment'`、`'fake_photo'`→`'impersonation'`、`'other'` |
| `docs/API-001_前台API規格書.md` | §8.1 type 欄位改 string enum 對照表 |

---

### CR-1 — feat(media): POST /uploads 404

**Commit:** `49b883b`
**根本原因：** `useImageUpload.ts` 已正確呼叫 `POST /api/v1/uploads`（含 `file` + `context` 欄位），但後端路由從未存在 → 所有頭像 / 個人照片 / 舉報圖片上傳全部 404。

| 受影響檔案 | 修改內容 |
|-----------|---------|
| `backend/app/Http/Controllers/Api/V1/MediaController.php` | **新建**。`upload()` 接受 `file`（JPEG/PNG/WebP ≤5MB）+ `context`（avatar/profile_photo/report_image）；finfo magic bytes 驗證；依 context 決定儲存路徑（avatar/photo 依 user id 命名空間）；avatar context 自動更新 `users.avatar_url` |
| `backend/routes/api.php` | 新增 `Route::post('/uploads', ...)->middleware(['auth:sanctum', 'throttle:upload'])` |
| `docs/API-001_前台API規格書.md` | §16.1 補上統一上傳端點完整規格 |

**備註：** `throttle:upload`（10次/分鐘，依 user id）在 `RouteServiceProvider` 已預先定義，無需新增。

---

### CR-2 — security(admin): RBAC admin.permission 未掛載

**Commit:** `a99aba1`
**根本原因：** `CheckAdminPermission` middleware 已實作並以 `admin.permission` 別名登錄在 `Kernel.php`，但 `backend/routes/api.php` 的全部 admin 路由無任何一條呼叫它 → 任何登入的管理員不論 role 都能存取所有後台功能。

| 受影響檔案 | 修改內容 |
|-----------|---------|
| `backend/routes/api.php` | 25 條路由加上 `->middleware('admin.permission:PERMISSION_KEY')` |

**掛載對照：**

| Permission key | 路由 |
|---------------|------|
| `members.view` | GET /members, GET /members/{id} |
| `members.edit` | PATCH /members/{id}/actions, /permissions, /profile；POST /members/{id}/change-password, /verify-email, /points |
| `members.delete` | DELETE /members/{id} |
| `reports.view` | GET /tickets |
| `reports.process` | PATCH /tickets/{id}, /tickets/{id}/status；POST /tickets/{id}/reply |
| `payments.view` | GET /payments |
| `chat.view` | GET /chat-logs/search, /chat-logs/conversations, /chat-logs/export, /members/{id}/chat-logs, /members/{id}/chat-logs/export |
| `seo.manage` | GET/PATCH /seo/meta, /seo/meta/{id} |
| `broadcasts.manage` | GET/POST /broadcasts, GET/POST /broadcasts/{id}/send |

**未掛載（理由）：**
- `/stats/summary`、`/logs`、`/user-activity-logs`、`/verifications/*`、`/announcements/*` — 無細分需求
- `/settings/*` group — 已有 `check.super_admin` middleware

**額外發現：** production `admin_role_permissions` 表為空（`AdminPermissionsSeeder` 從未在 production 執行），導致 cs/admin role 全部 403。本次 deploy 後已執行 `php artisan db:seed --class=AdminPermissionsSeeder --force`（13 rows 入庫）。

---

## 部署記錄

| 批次 | Merge commit | 內容 |
|------|-------------|------|
| 1st | `ad97970` | CR-3 + CR-1 |
| 2nd | `885e04b` | CR-2 |

## 驗收結果

| 測試 | 結果 |
|------|------|
| 前台 https://mimeet.online | ✅ 200 |
| 後台 https://admin.mimeet.online | ✅ 200 |
| API /api/v1/auth/me (unauthenticated) | ✅ 401 |
| GET /admin/payments (cs role) | ✅ 403 |
| GET /admin/tickets (cs role) | ✅ 200 |
| DELETE /admin/members/1 (cs role) | ✅ 403 |
| GET /admin/members (super_admin) | ✅ 200 |

---

## P1 安全強化記錄（2026-04-23 第二批）

### G-001 — security(nginx): HSTS + CSP + server_tokens off (VULN-004)

**Commit:** `f160add`
**受影響檔案：** `docker/nginx/default.conf`
**修復內容：** 在既有 5 個 security header 後新增 HSTS（max-age=31536000; includeSubDomains）、CSP（default-src 'self'、script-src 'self'、style-src 'self' 'unsafe-inline' 等）、server_tokens off。

**⚠️ 重要發現：** `docker/nginx/default.conf` 是本機 Docker 開發用，**不影響 production**。production nginx 跑在 Droplet host（systemctl），config 在 `/etc/nginx/sites-enabled/mimeet`，**不在 git repo 內**。
- HSTS：production 已有 ✅（三個 server block 均有）
- CSP：production 尚未加入 ❌（需手動更新 `/etc/nginx/sites-enabled/mimeet`）
- server_tokens off：production 尚未加入 ❌（`Server: nginx` header 仍暴露，無版本號）

**待辦：** 將 `/etc/nginx/sites-enabled/mimeet` 納入 git 管理，或在 deploy script 中 sync 此檔案。

---

### G-004 — security(admin): admin token IP binding (VULN-008)

**Commit:** `bf4d3d2`
**受影響檔案：**
- `backend/app/Http/Controllers/Api/V1/AdminController.php` — token name 改 `admin-token-{ip}`；response 加 `last_login_ip`
- `backend/app/Http/Middleware/EnsureAdminUser.php` — IP binding check，不符 → 401 + Log::warning
- `backend/database/migrations/2026_04_23_090045_add_last_login_ip_to_admin_users_table.php` — 新增 `last_login_ip VARCHAR(45) NULLABLE`

**驗收：** 登入後 token name = `admin-token-220.135.209.213` ✅；`last_login_ip` 欄位 migration 執行成功 ✅

---

### G-007 — security(cors): allowed_origins via env (VULN-011)

**Commit:** `7d4e58f`
**受影響檔案：**
- `backend/config/cors.php` — `allowed_origins` 改讀 `CORS_ALLOWED_ORIGINS` env，預設值僅含三個正式域名
- `backend/.env.example` — 補 CORS 說明

**驗收：**
- `Origin: http://localhost:5173` → 無 ACAO header ✅
- `Origin: https://mimeet.online` → `Access-Control-Allow-Origin: https://mimeet.online` ✅
- Droplet `.env` 無 `CORS_ALLOWED_ORIGINS` key → 使用安全預設值 ✅

---

## P2 業務功能缺口修復（2026-04-23 第三批）

Merge commit: `f852f44` — 10 個問題，6 個 commits on develop。

### 修復清單

| ID | 問題 | Commit | 受影響檔案 |
|----|------|--------|-----------|
| H-003 | 通知已讀只改 local state，不呼叫後端 | `b3594a6` | `NotificationsView.vue` |
| F-002 | 後台缺 GET /admin/auth/me + POST /auth/logout | `b3594a6` | `AdminController.php`、`api.php` |
| E-001 | Email/手機驗證後未加 credit score +5 | `4944471` | `AuthController.php` |
| E-003 | 帳號停權/解停後未寄 Email 通知 | `4944471` | `CreditScoreObserver.php`、`AccountAutoSuspendedMail.php`（新建）、`AccountReactivatedMail.php`（新建）、2 個 blade view |
| B-003/H-004 | FCM Token 路由全缺（POST/DELETE /me/fcm-token） | `29bbd8f` | `FcmTokenController.php`（新建）、`api.php` |
| D-003 | DELETE /uploads 路由缺失（上傳後無法刪除） | `5410d58` | `MediaController.php`、`api.php` |
| H-005 | POST /reports/{id}/followups 路由缺失 | `5410d58` | `ReportController.php`、`api.php` |
| E-002 | CreditScore 所有 delta 全 hardcode，無法後台調整 | `0c84887` | `CreditScoreService.php`（加 `getConfig()`）、`ReportService.php`、`AuthController.php`、`VerificationController.php`、`ReportController.php` |
| F-003 | memberAction 支援三個未實作的 action（set_level / require_reverify / add_note） | `0c84887` | `AdminController.php` |
| F-004 | /stats/chart、/stats/export、/stats/server-metrics 路由全缺 | `c1fc42a` | `StatsController.php`、`api.php`、`API-002_後台管理API規格書.md` |

### 實作細節紀錄

**H-003 修正路徑：** 後端路由為 `PATCH /notifications/{id}/read` 和 `PATCH /notifications/read-all`（無 `me/` 前綴，HTTP method 為 PATCH 非 POST）。

**E-001 雙重防護：** 用 `$user->wasChanged('email_verified')` / `wasChanged('phone_verified')` 在 save 後判斷，防止重複加分。手機驗證走登入分支時，需改用 `find() + save()` 取代原有的 bulk `User::where()->update()`（`wasChanged` 必須在 Eloquent model 上才有效）。

**E-002 getConfig 設計：** `CreditScoreService::getConfig(string $key, int $default)` 讀 `system_settings.credit_score.{$key}`；refund 用 `-getConfig('report_filed_deduct', -10)` 自動取反，後台調整扣分值時 refund 也同步變動。

**F-003 add_note 欄位映射：** `AdminOperationLog` 的 fillable 無 `note` 欄位，改用 `description`（傳入 note 文字）+ `request_summary` JSON（`['note' => ...]`）儲存。

**F-004 StreamedResponse 說明：** `/stats/export` 用 `response()->streamDownload()` 直接串流 CSV，不寫暫存檔；`/stats/server-metrics` 用 PHP 原生 `sys_getloadavg()` + `disk_free_space()` + Redis `info()` 組合，Redis 不可用時 fallback 為 `['error' => 'unavailable']`。

### 已知差異（不影響功能）

- `supervisorctl status mimeet-worker:*` → "no such group"：Droplet 上 Supervisor 設定不含 mimeet-worker，Queue Worker 未以 Supervisor 管理（pre-existing 問題，E-003 的 Mail::queue 需此 worker，後續需補設定）。
- 本批不含 migration（無新表 / 新欄位）。

### 驗收結果

| 測試 | 結果 |
|------|------|
| 前台 https://mimeet.online | ✅ 200 |
| 後台 https://admin.mimeet.online | ✅ 200 |
| API /api/v1/auth/me (unauthenticated) | ✅ 401 |
| GET /admin/auth/me (unauthenticated) | ✅ 401 |
| POST /me/fcm-token (unauthenticated) | ✅ 401 |
| GET /admin/stats/chart (unauthenticated) | ✅ 401 |
| GET /admin/stats/export (unauthenticated) | ✅ 401 |
| GET /admin/stats/server-metrics (unauthenticated) | ✅ 401 |
| CreditScoreService::getConfig('email_verified', 5) | ✅ 5 |

---

## P3 規格文件修正（2026-04-23 第四批）

Merge commit: `13072ca`（含 P3 + P4 所有變更）

### P3 修正清單

| ID | 文件 | 修正內容 |
|----|------|---------|
| P3-1 | API-001 §2.1.2 | `data.tokens{}` → `data.token` plain string；補 Sanctum PAT 24h 說明 |
| P3-2 | API-001 §2.1.3 | 標記 refresh token 為「未實作」並說明 Sanctum PAT 無 refresh 機制 |
| P3-3 | DEV-009 v1.0→v2.0 | 全文改寫：socket.io-client → Laravel Echo + pusher-js；頻道前綴自動規則；前端接線範例；Nginx `/app` vs `/apps` 說明 |
| P3-4 | API-001 §2.2.3 | photo-verify 路徑 `/auth/photo-verification/*` → `/me/verification-photo/*` |
| P3-5 | API-001 §2.2.4 | 信用卡驗證標記為「Phase 2 未實作」 |
| P3-6 | API-001 §3.1.1 | `verification_status{}` 展開為個別欄位；photos 固定 `[]`；移除 stats |
| P3-7 | API-001 §3.1.2 | `introduction` → `bio`；`job` → `occupation` |
| P3-8 | API-001 §3.3 | 改寫為 Avatar Slots 系統說明，移除不存在的 `/me/photos` 端點 |
| P3-9 | API-001 §4.1.2 | `before_id` → `cursor`；`next_before_id` → `next_cursor` |
| P3-10 | API-001 §4.1.4 | 路由改 `PATCH /chats/{id}/read`；移除 `message_ids` body |
| P3-11 | API-001 §5.1.1 | 移除 `message`/`estimated_duration`；`qr_code` → `qr_token`（hex 說明）|
| P3-12 | API-001 §7.2.1 | callback 路徑 `callbacks/green-world` → `payments/ecpay/notify` |
| P3-13 | API-001 §9.1 | 公告端點改 `/announcements/active`（公開）；已讀狀態說明改 localStorage |
| P3-14 | API-001 §10.1 | following 清單 key `following` → `users` |
| P3-15 | API-001 §10.2 | `uid` → `id`；`avatar` → `avatar_url` |
| P3-16 | API-001 §10.7 | 通知路徑移除 `/me/` 前綴；`POST read-all` → `PATCH read-all` |

---

## P4 細節對齊（2026-04-23 第五批）

### 程式碼修改

| ID | 問題 | Commit | 受影響檔案 |
|----|------|--------|-----------|
| P4-1 A-M3 | reset-password 路由未套 throttle | `9a2a068` | `backend/routes/api.php` |
| P4-2 A-L1 | register 缺合規欄位驗證 | `9a2a068` | `AuthController.php`；`frontend/src/api/auth.ts`；`RegisterView.vue`（hotfix `9f0eceb`）|
| P4-3 B-006 | 搜尋缺 verified_only + 30 天未登入過濾 | `abfc636` | `UserController.php::search()` |
| P4-4 E-009 | appeal ticket_no 非零填充格式 | `abfc636` | `AppealController.php` store() + current() |
| P4-6 G-008 | tracking.ts 殘留 console.warn | `9a2a068` | `frontend/src/utils/tracking.ts` |

### 文件修改

| ID | 問題 | Commit | 修正內容 |
|----|------|--------|---------|
| P4-5 C-008 | DailyLimitException 429 未記載 | `81effff` | API-001 §4.1.3 補 429 + 業務規則；§3.2.1 修正 `last_active_at` 欄位名稱 |

### P4-2 Hotfix 說明

`RegisterPayload` interface 加入三個 `true` literal fields 後，`RegisterView.vue` 呼叫點未同步補上，導致前台 TypeScript build 型別檢查失敗（`error TS2345`）。Vite bundle 因 pipe 吞掉 exit code 而繼續建置，**dist 實際功能正常但型別不安全**。Hotfix commit `9f0eceb` 補上三個 literal 欄位，重新 build 後型別檢查通過。

---

## P3 + P4 部署記錄

| 項目 | 結果 |
|------|------|
| Merge commit (main) | `13072ca`（P3+P4）、`847e424`（P4-2 hotfix）|
| Migration | Nothing to migrate |
| config:cache | ✅ |
| route:cache | ✅ |
| Frontend build | ✅ 8m 28s（hotfix 後型別檢查通過）|
| Admin build | ✅ |
| Worker status | `mimeet-worker Up About a minute` ✅ |

## P3 + P4 驗收結果

| 測試 | 結果 |
|------|------|
| 前台 https://mimeet.online | ✅ 200 |
| 後台 https://admin.mimeet.online | ✅ 200 |
| API /api/v1/auth/me (unauthenticated) | ✅ 401 |
| P4-2: POST /auth/register 缺合規欄位 | ✅ 422（errors: terms_accepted, privacy_accepted, anti_fraud_read）|
| P4-4: ticket_no 零填充 | ✅ A000000001 |
| P4-3: verified_only filter | ✅（code review 驗證，測試帳號密碼已失效）|

---

## Queue Worker 狀態修正（P2 補記）

調查發現 Queue Worker 並非 Supervisor 管理，而是 **Docker service `mimeet-worker`**（`docker-compose.staging.yml`），使用 Redis driver，`restart: unless-stopped`。`Mail::queue()` 驗證 end-to-end 正常。

- CLAUDE.md 已更正 Queue Worker 欄位
- OPS-003 §8 已補 Queue Worker 操作說明
- deploy 腳本中 `supervisorctl restart` 已改為 `docker compose restart worker`

---

## 全面稽核週期正式完成

| 批次 | 內容 | 狀態 |
|------|------|------|
| P0 | 稽核報告 A-H 8 份 + SUMMARY | ✅ 完成 |
| P1 | 安全強化（G-001, G-004, G-007）| ✅ 完成 |
| P2 | 業務功能缺口（10 issues）| ✅ 完成 |
| P3 | 規格文件修正（16 items，API-001, DEV-009）| ✅ 完成 |
| P4 | 細節對齊（6 fixes）| ✅ 完成 |

---

## 待處理（未完成）

- **G-001 部分** — production nginx CSP 和 server_tokens off 尚未生效（`/etc/nginx/sites-enabled/mimeet` 需更新，不在 git 管理內）
