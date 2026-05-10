# Audit-B Round 2 — Codex CLI 版

> 適用工具：Codex CLI  
> 建議檔案位置：`prompts/audit/audit-b-codex.md`  
> 輸出報告：`docs/audits/audit-B-YYYYMMDD-codex.md`

## 任務目標

請以 Codex CLI 在本機 repo 內執行一次 **Audit-B Round 2**，範圍為：用戶資料 / 搜尋 / 配對 / 訪客 / 收藏 / 封鎖 / FCM Token / 隱私設定。

本輪不是修 code，而是產出獨立稽核報告。除非使用者另行要求，不要直接修改產品程式碼。若發現可修補問題，只在報告中提出建議 patch 順序與候選 diff，不要自行套用。

Codex CLI 會讀取 repo 內 `AGENTS.md`；請遵守其中所有專案規則、部署禁令、commit 格式與 audit framework。若本 prompt 與 `AGENTS.md` 衝突，以 `AGENTS.md` 與 `_common.md` 的稽核規則優先。

## 啟動前必讀

1. 先讀 `prompts/audit/_common.md`。
2. 讀 repo 根目錄 `AGENTS.md`，確認 Codex CLI 專案規則。
3. 確認目前 git 基準：

```bash
git rev-parse HEAD
git status --short
```

4. 若工作樹已有使用者改動，不要覆蓋；報告中註明「本輪未觸碰既有變更」。

## 規格範圍

請逐一讀取並引用以下規格：

- `docs/API-001_前台API規格書.md` §3（用戶管理）
- `docs/API-001_前台API規格書.md` §3.1（個人資料 / 隱私 / 設定）
- `docs/API-001_前台API規格書.md` §3.2.1（搜尋用戶 + F27 進階篩選）
- `docs/API-001_前台API規格書.md` §3.6（FCM Token）
- `docs/API-001_前台API規格書.md` §10.1、§10.2（收藏 / 誰來看我）
- `docs/API-001_前台API規格書.md` §10.5（封鎖管理）
- `docs/PRD-001_MiMeet_約會產品需求規格書.md` §4.3.1（智能搜尋與配對）
- `docs/DEV-004_後端架構與開發規範.md` 配對算法相關段落
- `docs/UF-001_用戶流程圖.md` UF-04（探索 / 搜尋）

## 前次稽核

先找出並閱讀所有既有 Audit-B 報告：

```bash
ls -1 docs/audits/audit-B-*.md 2>/dev/null || true
```

若存在，報告必須包含「前次 Issue 回歸狀態」表。每個前次 issue 至少標示：

- 前次 issue id
- 前次等級
- 前次結論
- 本輪狀態：已修 / 未修 / 部分修 / 無法判定
- 本輪證據：規格位置 + 程式碼位置

若不存在，明確寫「未找到前次 Audit-B 報告」。

## 程式碼範圍

### 後端

```bash
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
```

### 前端

```bash
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

### 檔案存在性檢查

在報告中保留原樣輸出：

```bash
for f in \
backend/app/Http/Controllers/Api/V1/UserController.php \
backend/app/Http/Controllers/Api/V1/SearchController.php \
backend/app/Http/Controllers/Api/V1/FollowController.php \
backend/app/Http/Controllers/Api/V1/VisitorController.php \
backend/app/Http/Controllers/Api/V1/BlockController.php \
backend/app/Http/Controllers/Api/V1/FcmTokenController.php \
backend/app/Http/Controllers/Api/V1/PrivacyController.php \
backend/app/Models/User.php \
backend/app/Models/UserBlock.php \
backend/app/Models/UserProfileVisit.php \
backend/app/Models/Favorite.php \
backend/app/Models/FcmToken.php \
frontend/src/api/users.ts \
frontend/src/api/explore.ts \
frontend/src/api/visitors.ts \
frontend/src/api/favorites.ts \
frontend/src/views/app/ExploreView.vue \
frontend/src/views/app/ProfileDetailView.vue \
frontend/src/views/app/VisitorsView.vue \
frontend/src/views/app/FavoritesView.vue \
frontend/src/views/app/settings/ProfileEditView.vue \
frontend/src/views/app/settings/BlockedView.vue \
frontend/src/types/explore.ts; do
  [ -f "$f" ] && echo "✅ $f" || echo "❌ $f"
done
```

## P1 — 規格端點清單對照

請逐條對照 routes、controller、前端 API 呼叫與規格。缺一不可。

- `GET /me`
- `PATCH /me`
- `PATCH /me/privacy`
- `PATCH /me/settings`
- `POST /me/photos`
- `DELETE /me/photos`
- `PATCH /me/photos/sort`
- `GET /users/search`（含 F27 14 個篩選參數）
- `GET /users/recommendations`（若規格存在但 Phase 2，必須標示狀態）
- `GET /users/{id}`
- `POST /users/{id}/follow`
- `DELETE /users/{id}/follow`
- `GET /users/me/following`
- `GET /users/me/visitors`
- `POST /users/{id}/block`
- `DELETE /users/{id}/block`
- `GET /me/blocks`
- `POST /me/fcm-token`
- `DELETE /me/fcm-token`

報告附錄 A 必須有端點逐條表：Method、Path、Route 是否存在、Controller method、Middleware、前端 API 是否存在、狀態、備註。

## P2 — Request Payload / Validation 對照

檢查每個 endpoint 的 request body / query string 是否與 API-001 一致，尤其：

- `PATCH /me` 可更新欄位
- `PATCH /me/privacy` 隱私欄位
- `PATCH /me/settings` 通知 / 搜尋 / 顯示設定
- `POST /me/photos` 上傳或 photo URL 格式
- `PATCH /me/photos/sort` 排序 payload
- `GET /users/search` F27 14 個篩選參數
- FCM token 註冊 / 刪除 payload
- block / follow 是否需要額外 body

請用 `grep` / `rg` / `sed -n` 或直接讀檔確認 validator、request access 與 TS type。

## P3 — Response Structure / Type 對照

檢查 response 是否符合 API-001 與前端 type：

- pagination 是否使用統一 `{ data: [], meta: { page, per_page, total, last_page } }`
- user card / profile detail 欄位是否一致
- explore list 與 profile detail 是否使用同一套欄位命名
- 後端 snake_case 到前端 camelCase 是否有一致 mapping
- error response 是否符合專案錯誤格式

若發現同一 endpoint 規格、後端、前端三者任兩者不一致，必須列 issue。

## P4 — 業務規則檢查

請至少驗證下列規則，並在附錄 B 做表格：規則、規格值、實作值、出處、狀態。

| 規則 | 規格值 | 建議驗證指令 |
|---|---|---|
| 搜尋預設只顯示 30 天內活動 | 是 | `grep -n "subDays(30)" backend/app/Http/Controllers/Api/V1/UserController.php` |
| F27 篩選未填欄位不排除 | OR NULL | `grep -nE "whereNull.*orWhere\|orWhereNull" backend/app/Http/Controllers/Api/V1/UserController.php` |
| 隱身用戶不出現搜尋 | `stealth_until <= now` 或 null | `grep -n "stealth_until" backend/app/Http/Controllers/Api/V1/UserController.php` |
| 訪客紀錄忽略停權者 | 是 | `grep -nA 5 "UserProfileVisit" backend/app/Http/Controllers/Api/V1/UserController.php` |
| 收藏上限 | 500 | `grep -rn "500\|favorite_limit" backend/app/Models/Favorite.php backend/app/Http/Controllers/` |
| 看訪客需付費（男）/ 不需（女） | Lv3 male / 任意 female | `grep -rn "membership_level\|gender" backend/app/Http/Controllers/Api/V1/VisitorController.php` |
| 排序 | 完整度 → credit_score → last_active_at | `grep -nA 10 "orderBy" backend/app/Http/Controllers/Api/V1/UserController.php` |

## P5 — Middleware / Security / Privacy

檢查：

- 所有需要登入的端點是否有 `auth:sanctum`
- blocked user 是否從 search / recommendation / profile detail 排除或阻擋
- stealth user 是否從搜尋、推薦、訪客紀錄中一致處理
- suspended / deleted user 是否被排除
- FCM token 是否限制目前 user，只能刪自己的 token
- privacy 設定是否真的影響 profile/search/visitor 可見性

## P6 — 前端串接檢查

檢查：

- `frontend/src/api/users.ts`
- `frontend/src/api/explore.ts`
- `frontend/src/api/visitors.ts`
- `frontend/src/api/favorites.ts`
- `frontend/src/types/explore.ts`
- `ExploreView.vue`
- `ProfileDetailView.vue`
- `VisitorsView.vue`
- `FavoritesView.vue`
- `ProfileEditView.vue`
- `BlockedView.vue`

重點：

- 前端呼叫 path 是否與 routes 一致
- query param 名稱是否與後端 validator 一致
- pagination 欄位是否讀 `meta.page` / `meta.last_page`
- 後端回傳 snake_case 時，前端是否轉 camelCase
- 是否有 dead export / dead method

## P7 — 跨模組副作用

檢查以下互動：

- follow / favorite 是否影響推薦、排序或 profile card
- block 是否同時影響搜尋、訪客、收藏、聊天入口
- privacy / stealth 是否同時影響搜尋、訪客紀錄、profile detail
- FCM token 是否與 login/logout 或 account deletion 有一致清理策略
- photo sort 是否影響 profile cover / search card 第一張圖

## P11 — 模組特有掃描

請在報告中納入以下掃描結果與判讀：

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

## Issue 等級判定

沿用 `_common.md` 與專案 audit framework：

- 🔴 Critical：安全漏洞、線上功能壞掉、資料錯誤，已登入用戶可感知
- 🟠 High：規格要求功能未實作，或行為與規格明顯不符
- 🟡 Medium：命名 / 結構與規格不一致，但功能大致可使用
- 🔵 Low：維護性、重複實作、規格小漂移，不影響主要 UX
- ✅ Symmetric：規格與實作完全一致

## 報告格式

請產出：

```markdown
# Audit Report B Round 2 — 用戶資料 / 搜尋 / 配對 / 訪客 / 收藏 / 封鎖

**執行日期：** YYYY-MM-DD
**稽核者：** Codex CLI（本機）
**Agent ID：** codex
**規格來源：**
- ...
**程式碼基準（Local）：** <完整 40 字元 commit hash>
**前次稽核：** <列出讀到的 audit-B 檔案，或未找到>
**總結：** N issues（🔴 N / 🟠 N / 🟡 N / 🔵 N）+ M Symmetric

---

## 0. 前次 Issue 回歸狀態

## 1. Pass 完成記錄

## 2. Issues 索引（依等級排序）

## 3. Issue 詳情

### Issue #B2-001
**Pass：** P?
**規格位置：** docs/...:line 或章節
**規格內容：**
```text
...
```
**程式碼位置：** path:line
**程式碼現況：**
```php
...
```
**差異說明：** ...
**等級：** 🔴/🟠/🟡/🔵
**建議方案：**
- Option A：...
- Option B：...
**推薦：** A/B，原因...

## 4. 行動優先序

## 5. 下次 Audit 建議

## 附錄 A — P1 端點逐條檢查

## 附錄 B — P4 業務規則對照

## 附錄 C — P11 掃描記錄

## Self-check
- [ ] Header 包含完整 commit hash、Agent ID、規格來源、前次稽核
- [ ] 前次 issue 全部回歸
- [ ] P1 端點全部逐條列出
- [ ] P4 業務規則全部逐條列出
- [ ] P11 掃描有原始輸出與判讀
- [ ] 每個 issue 有規格證據 + 程式碼證據
- [ ] 每個 issue 有 Option A/B + 推薦
- [ ] Symmetric 至少 10 條
- [ ] 報告檔名為 `docs/audits/audit-B-YYYYMMDD-codex.md`
- [ ] 未修改產品程式碼
```

## Codex CLI 執行提醒

- 優先使用 `rg`，必要時使用 `grep` / `sed -n`。
- 任何結論都要能用檔案路徑與行號驗證。
- 不要憑前次報告沿用結論；每條都要在本輪 commit 重新驗證。
- 若規格與實作衝突，不要自動改 code；先在報告中列出改規格與改程式碼兩種方案。
- 若需要建立報告檔，只新增 `docs/audits/audit-B-YYYYMMDD-codex.md`，不要順手格式化其他檔案。
