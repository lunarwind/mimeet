# MiMeet — Claude Code 專案規則

## 技術架構

| 層級 | 技術 |
|------|------|
| 前台 | Vue 3 + TypeScript + Tailwind CSS（`frontend/`）|
| 後台 | React 18 + Ant Design 5（`admin/`）|
| 後端 | Laravel 10 + PHP 8.2（`backend/`，依據 composer.json）|
| 資料庫 | MySQL 8.0 + Redis 7.0 |
| 容器 | Docker（mimeet-app 容器跑 PHP-FPM）|

## 基礎設施

| 項目 | 值 |
|------|-----|
| Droplet | root@188.166.229.100（DO SGP1，2vCPU/4GB/120GB）|
| 專案路徑 | /var/www/mimeet |
| 前台 | https://mimeet.online |
| 後台 | https://admin.mimeet.online |
| API | https://api.mimeet.online |
| artisan | `docker exec -u www-data mimeet-app php artisan <cmd>` |
| Queue Worker | Supervisor `mimeet-worker:*`（_00、_01）|
| API 健康檢查 | `GET /api/v1/auth/me` → 401（Sanctum），不是 `/auth/user` |

## 部署流程（強制，不可跳步）

```
1. 本機 develop 改程式碼
2. bash scripts/pre-merge-check.sh（全部 ✅ 才繼續）
3. git add + git commit + git push origin develop
4. git checkout main && git pull origin main && git merge develop --no-ff && git push origin main && git checkout develop
5. ssh root@188.166.229.100 '
   cd /var/www/mimeet && git pull origin main
   docker exec mimeet-app sh -c "touch storage/logs/laravel.log && chown www-data:www-data storage/logs/laravel.log && chmod 664 storage/logs/laravel.log"
   docker exec mimeet-app php artisan migrate --force 2>/dev/null || true
   docker exec -u www-data mimeet-app php artisan config:cache
   docker exec -u www-data mimeet-app php artisan route:cache
   cd /var/www/mimeet/frontend && npm ci --prefer-offline 2>&1 | tail -3 && npm run build 2>&1 | tail -5
   cd /var/www/mimeet/admin && npm ci --prefer-offline 2>&1 | tail -3 && npm run build 2>&1 | tail -5
   supervisorctl restart mimeet-worker:*
   echo "✅ Deploy 完成"
   '
6. Smoke Test：前台 200 / 後台 200 / API /api/v1/auth/me 401
```

## 四項禁令

1. **禁止**直接在 Droplet 上修改任何檔案
2. **禁止**在 main 上直接 commit（main 只接受從 develop merge）
3. **禁止**跳過 pre-merge-check.sh
4. **禁止** deploy 時不 rebuild 前台/後台

原因：2026-04 發生 main/develop 漂移事件，修好的 bug 反覆復發，最終用 force-reset 救回。

## 修改前必做

1. 先讀相關規格文件（`docs/` 目錄）
2. 修改後更新對應的規格文件：
   - API 端點 → `docs/API-001_前台API規格書.md` 或 `docs/API-002_後台管理API規格書.md`
   - 資料庫 → `docs/DDD-001_資料庫設計規格書.md`
   - 功能需求 → `docs/PRD-001_MiMeet_約會產品需求規格書.md`
   - 權限邏輯 → `docs/DEV-008_誠信分數系統規格書.md`
3. 檢查修改的功能或變數是否與前台/後台其他資料關聯，有的話一併修正

## 已知陷阱（歷史教訓）

### 指令名稱
- 資料庫重設指令是 `mimeet:reset`，**不是** `mimeet:reset-clean`
- DatasetController 必須呼叫 `mimeet:reset`
- 已經因為名稱不一致修了三次，每次 merge 又被覆蓋回去

### snake_case → camelCase 映射
後端回傳 snake_case，前端用 camelCase，以下是重點欄位：

| 後端 | 前端 | 備註 |
|------|------|------|
| `sent_at` | `createdAt` | messages 表專用 |
| `created_at` | `createdAt` | 通用 |
| `other_user` | `targetUser` | conversations |
| `unread_count` | `unreadCount` | conversations |
| `expires_at` | `expiresAt` | subscriptions |
| `sender_id` | `senderId` | messages |
| `is_read` | `isRead` | messages |

映射邏輯在 `usePayment` / `fetchConversations` / `fetchMessages` 內。

### 廣播系統
- `delivery_mode` 欄位名是 `delivery_mode`，**不是** `delivery_method`
- 目標性別藏在 JSON 欄位 `filters` 內：`record.filters?.gender ?? 'all'`，不是頂層欄位

### SubscriptionPlanSeeder
- 必須用 `updateOrInsert`，不能用 `insert`，否則 migrate:fresh 後方案消失

## Commit 格式

`{type}({scope}): {description}`

type: feat / fix / refactor / test / docs / chore / perf / style

## 測試帳號

| 帳號 | 密碼 | 用途 |
|------|------|------|
| chuck@lunarwind.org | ChangeMe@2026 | 後台 super_admin |
| Chengfong0404@gmail.com | Test1234 | 前台測試 |

## uid=1 官方帳號

email: admin@mimeet.club，每次 `php artisan mimeet:reset --force` 後自動重建。

