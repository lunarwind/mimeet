# Audit-B Round 2 — 用戶資料 / 搜尋 / 配對 / 訪客 / 收藏 / 封鎖

> 先讀 prompts/audit/_common.md。

## 規格範圍
- docs/API-001 §3（用戶管理）
- docs/API-001 §3.1（個人資料 / 隱私 / 設定）
- docs/API-001 §3.2.1（搜尋用戶 + F27 進階篩選）
- docs/API-001 §3.6（FCM Token）
- docs/API-001 §10.1 §10.2（收藏 / 誰來看我）
- docs/API-001 §10.5（封鎖管理）
- docs/PRD-001 §4.3.1（智能搜尋與配對）
- docs/DEV-004 配對算法
- docs/UF-001 UF-04（探索/搜尋）

## 前次稽核
- docs/audits/audit-B-*.md（若存在）

## 程式碼範圍

```bash
# 後端
backend/app/Http/Controllers/Api/V1/UserController.php
backend/app/Http/Controllers/Api/V1/SearchController.php
backend/app/Http/Controllers/Api/V1/FollowController.php
backend/app/Http/Controllers/Api/V1/VisitorController.php
backend/app/Http/Controllers/Api/V1/BlockController.php
backend/app/Http/Controllers/Api/V1/FcmTokenController.php
backend/app/Http/Controllers/Api/V1/PrivacyController.php
backend/app/Models/User.php
backend/app/Models/UserBlock.php
backend/app/Models/UserProfileVisit.php
backend/app/Models/Favorite.php
backend/app/Models/FcmToken.php

# 前端
frontend/src/api/users.ts
frontend/src/api/explore.ts
frontend/src/api/visitors.ts
frontend/src/api/favorites.ts
frontend/src/views/app/ExploreView.vue
frontend/src/views/app/ProfileDetailView.vue
frontend/src/views/app/VisitorsView.vue
frontend/src/views/app/FavoritesView.vue
frontend/src/views/app/settings/ProfileEditView.vue
frontend/src/views/app/settings/BlockedView.vue
frontend/src/types/explore.ts
```

## 規格端點清單（P1 對照）
- GET /me、PATCH /me、PATCH /me/privacy、PATCH /me/settings
- POST/DELETE /me/photos、PATCH /me/photos/sort
- GET /users/search（含 F27 14 個篩選參數）
- GET /users/recommendations（規格存在但可能 Phase 2）
- GET /users/{id}
- POST/DELETE /users/{id}/follow、GET /users/me/following
- GET /users/me/visitors
- POST/DELETE /users/{id}/block、GET /me/blocks
- POST/DELETE /me/fcm-token

## 模組特有檢查

### P4 業務規則
| 規則 | 規格值 | 怎麼驗 |
|---|---|---|
| 搜尋預設只顯示 30 天內活動 | 是 | `grep -n "subDays(30)" backend/app/Http/Controllers/Api/V1/UserController.php` |
| F27 篩選未填欄位不排除 | OR NULL | `grep -nE "whereNull.*orWhere\|orWhereNull" backend/app/Http/Controllers/Api/V1/UserController.php` |
| 隱身用戶不出現搜尋 | stealth_until <= now | `grep -n "stealth_until" backend/app/Http/Controllers/Api/V1/UserController.php` |
| 訪客紀錄忽略停權者 | 是 | `grep -nA 5 "UserProfileVisit" backend/app/Http/Controllers/Api/V1/UserController.php` |
| 收藏上限 500 | 500 | `grep -rn "500\|favorite_limit" backend/app/Models/Favorite.php backend/app/Http/Controllers/` |
| 看訪客需付費（男）/ 不需（女）| Lv3 male / 任意 female | `grep -rn "membership_level\|gender" backend/app/Http/Controllers/Api/V1/VisitorController.php` |
| 排序：完整度 → credit_score → last_active_at | 三層 | `grep -nA 10 "orderBy" backend/app/Http/Controllers/Api/V1/UserController.php` |

### P11 模組特有
```bash
# 是否有 SearchController + UserController::search 兩處重複
grep -rn "function search" backend/app/Http/Controllers/Api/V1/

# 隱身判斷散落幾處
grep -rn "stealth_until\|isStealthActive\|stealth_mode" backend/app/

# 用戶轉換：是否兩處都在做 ExploreUser DTO（ExploreView + ProfileDetailView）
grep -rn "transformUser" frontend/src/

# F27 篩選 14 個欄位，是否每個都實作（grep validate）
grep -nA 30 "validate" backend/app/Http/Controllers/Api/V1/UserController.php | grep -E "height|education|style|dating_budget|relationship_goal|smoking|drinking|car_owner|availability"
```
