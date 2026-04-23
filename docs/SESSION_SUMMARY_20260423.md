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

## 待處理（Sprint 14 P1+）

詳見 `docs/audits/SUMMARY-20260423.md`。次高優先：

- **H-003 🟠** — NotificationsView markAllRead / handleClick 只更新 local state，不呼叫後端
- **H-004 🟡** — FCM Token 路由 POST/DELETE /me/fcm-token 未實作（B-003 對應）
- **E-001 🟠** — 手機/Email 驗證後未呼叫 credit score +10 API
- **G-001 🟠** — nginx 缺 HSTS + CSP + server_tokens off
