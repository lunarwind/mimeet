# Audit-F Round 2 — 後台管理 API

> 先讀 prompts/audit/_common.md。

## 規格範圍
- docs/API-002 完整全文（除 §13 匿名聊天 / §14 信用卡退款）
- docs/DEV-001 §6.2（RBAC 角色矩陣）
- docs/UI-001 §5.3（後台關鍵頁面）

## 前次稽核
- docs/audits/audit-F-20260423.md
- docs/audits/audit-F-20260424.md

## 程式碼範圍

```bash
# 主要 Admin Controllers
backend/app/Http/Controllers/Api/V1/AdminController.php
backend/app/Http/Controllers/Api/V1/Admin/  # 整個目錄
backend/app/Http/Controllers/Admin/  # 整個目錄

# 重要的 Admin Service
backend/app/Services/Admin/  # 若存在
backend/app/Models/AdminUser.php
backend/app/Models/AdminOperationLog.php
backend/app/Models/AdminPermission.php

# RBAC Middleware
backend/app/Http/Middleware/AdminPermissionMiddleware.php
backend/app/Http/Middleware/LogAdminOperation.php

# 後台前端
admin/src/api/  # 整個目錄
admin/src/pages/  # 整個目錄
admin/src/stores/authStore.ts
admin/src/router/  # 若存在
```

## 規格端點清單（P1 — API-002 §15 速查表）
74 個端點，全部對照（auth/stats/members/verifications/announcements/tickets/chat-logs/payments/seo/settings/logs/anon-chat/broadcasts/admins/system-settings/credit-card-verifications）。

## 模組特有檢查

### P6 RBAC 中介層（重點！）
```bash
# admin.permission middleware 掛載狀況
grep -nE "admin\.permission|admin\.log" backend/routes/api.php | head -50

# 對照 DEV-001 §6.2 RBAC 矩陣
# super_admin / admin / cs 三角色 × 11 個 permission key
grep -rn "permission.*=>\|->middleware\('admin\.permission" backend/

# RBAC Migration / Seeder
cat backend/database/seeders/AdminPermissionsSeeder.php  # 若存在
```

### P4 業務規則
| 規則 | 規格值 | 怎麼驗 |
|---|---|---|
| Admin token TTL | 24h | `grep -n "SANCTUM_TOKEN_EXPIRATION\|24" backend/.env.example backend/config/sanctum.php` |
| memberAction 支援動作 | adjust_score/suspend/unsuspend/set_level/add_note/require_reverify | `grep -nA 10 "function memberAction" backend/app/Http/Controllers/Api/V1/AdminController.php` |
| 統計 4 端點 | summary/chart/export/server-metrics | `grep -nE "summary\|chart\|export\|server-metrics" backend/app/Http/Controllers/Api/V1/Admin/StatsController.php` |
| 會員列表口徑 | meta.total = COUNT(users WHERE deleted_at IS NULL) | 對照 Audit-K 已修的 dashboard bug |
| 操作日誌 | 所有 PATCH/POST/DELETE 都寫入 admin_operation_logs | `grep -rn "AdminOperationLog::create\|admin\.log" backend/app/` |

### P11 模組特有
```bash
# AdminController 是否有 fat controller（>1000 行）
wc -l backend/app/Http/Controllers/Api/V1/AdminController.php

# 是否有 AdminController 與 Admin/* 子目錄並存（責任不清）
ls backend/app/Http/Controllers/Api/V1/Admin/

# action 名稱不一致（規格 adjust_credit vs 實作 adjust_score）
grep -rn "adjust_credit\|adjust_score" backend/app/ admin/src/

# admin/src/ 各 page 是否有死碼（grep export default 對應 router）
grep -rn "import.*from.*pages/" admin/src/router/

# memberAction validate 的 in: 清單 vs 實作的 switch case
grep -nA 30 "function memberAction" backend/app/Http/Controllers/Api/V1/AdminController.php
```

## 重點關注（前次 Round 1）
- F-001：admin.permission middleware 是否真的掛載到所有路由
- F-002：GET /auth/me + POST /auth/logout 是否都實作
- F-003：memberAction 6 個 action 全實作
- F-004：4 個統計端點全實作
- F-005：GET /tickets/{id} 詳情
- F-006：/members/{id}/credit-logs + /subscriptions
