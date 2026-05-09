# Audit-B Round 2 — Gemini CLI 版

> 適用工具：Gemini CLI  
> 建議檔案位置：`prompts/audit/audit-b-gemini.md`  
> 輸出報告：`docs/audits/audit-B-YYYYMMDD-geminicli.md`

## 任務目標

請使用 Gemini CLI 對 MiMeet repo 執行一次 **Audit-B Round 2**。本輪稽核範圍是：用戶資料、搜尋、配對、訪客、收藏、封鎖、FCM Token、隱私與設定。

這是一份獨立稽核報告，不是修復任務。除非使用者明確要求，不要修改產品程式碼。可以新增一份稽核報告 markdown；不要改動 controller、model、frontend 或規格書。

Gemini CLI 可透過 `GEMINI.md` 提供專案上下文。開始前請讀取 repo 內 `GEMINI.md`（若存在）、`AGENTS.md`（若存在）與 `prompts/audit/_common.md`。若三者有衝突，以 `_common.md` 的 audit 證據要求與專案規則為優先。

## Gemini CLI 使用方式建議

為了避免漏讀，建議在 Gemini CLI 中明確帶入關鍵檔案，例如：

```text
@prompts/audit/_common.md
@docs/API-001_前台API規格書.md
@docs/PRD-001_MiMeet_約會產品需求規格書.md
@docs/DEV-004_後端架構與開發規範.md
@docs/UF-001_用戶流程圖.md
@backend/app/Http/Controllers/Api/V1/UserController.php
@backend/routes/api.php
```

如果上下文過大，分批讀取，但每個 issue 下結論前都要回到原始檔案驗證，不要只依賴摘要。

## 啟動前必做

1. 讀 `prompts/audit/_common.md`。
2. 讀 `GEMINI.md`（若存在）與 `AGENTS.md`（若存在）。
3. 確認目前 commit 與工作樹：

```bash
git rev-parse HEAD
git status --short
```

4. 若工作樹已有未提交變更，報告中註明；不要覆蓋使用者變更。

## 規格範圍

請讀取並引用以下規格章節：

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

先找既有 Audit-B：

```bash
ls -1 docs/audits/audit-B-*.md 2>/dev/null || true
```

若存在，全部讀取，並在新報告中加入「前次 Issue 回歸狀態」。不要只讀最新一份；若不同 agent 對同一 issue 結論不同，請以本輪程式碼與規格重新驗證。

若不存在，報告中寫明「未找到前次 Audit-B 報告」。

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
backend/routes/api.php
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

請在報告中保留此檢查的輸出：

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
backend/routes/api.php \
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

## P1 — 規格端點對照

請建立完整端點表，逐條檢查 routes、controller、middleware、前端 API：

- `GET /me`
- `PATCH /me`
- `PATCH /me/privacy`
- `PATCH /me/settings`
- `POST /me/photos`
- `DELETE /me/photos`
- `PATCH /me/photos/sort`
- `GET /users/search`（含 F27 14 個篩選參數）
- `GET /users/recommendations`（若規格存在但 Phase 2，請標明）
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

附錄 A 必須包含：Method、Path、Route 是否存在、Controller method、Middleware、前端 API、狀態、備註。

## P2 — Request Payload / Query Params

檢查規格、後端 validator / request access、前端 TS 型別三方是否一致：

- `PATCH /me` 個人資料欄位
- `PATCH /me/privacy` 隱私欄位
- `PATCH /me/settings` 設定欄位
- `POST /me/photos` photo payload
- `PATCH /me/photos/sort` sort payload
- `GET /users/search` F27 14 個篩選參數
- `POST /me/fcm-token` / `DELETE /me/fcm-token`
- follow / block / favorite 是否 body-free 或帶 body

若 Gemini 無法一次讀完整檔案，請用 `grep` / `sed -n` 分段確認。

## P3 — Response Structure / Type

檢查：

- pagination 是否統一為 `{ data: [], meta: { page, per_page, total, last_page } }`
- search card 與 profile detail 欄位是否一致
- visitor / favorite / following 的 user object 是否一致
- snake_case → camelCase mapping 是否集中或重複
- 前端是否讀了不存在的欄位
- 規格是否仍使用舊 pagination 或舊欄位名

## P4 — 業務規則

請逐條驗證並在附錄 B 列表：

| 規則 | 規格值 | 建議驗證指令 |
|---|---|---|
| 搜尋預設只顯示 30 天內活動 | 是 | `grep -n "subDays(30)" backend/app/Http/Controllers/Api/V1/UserController.php` |
| F27 篩選未填欄位不排除 | OR NULL | `grep -nE "whereNull.*orWhere\|orWhereNull" backend/app/Http/Controllers/Api/V1/UserController.php` |
| 隱身用戶不出現搜尋 | `stealth_until <= now` 或 null | `grep -n "stealth_until" backend/app/Http/Controllers/Api/V1/UserController.php` |
| 訪客紀錄忽略停權者 | 是 | `grep -nA 5 "UserProfileVisit" backend/app/Http/Controllers/Api/V1/UserController.php` |
| 收藏上限 | 500 | `grep -rn "500\|favorite_limit" backend/app/Models/Favorite.php backend/app/Http/Controllers/` |
| 看訪客需付費（男）/ 不需（女） | Lv3 male / 任意 female | `grep -rn "membership_level\|gender" backend/app/Http/Controllers/Api/V1/VisitorController.php` |
| 排序 | 完整度 → credit_score → last_active_at | `grep -nA 10 "orderBy" backend/app/Http/Controllers/Api/V1/UserController.php` |

## P5 — Privacy / Block / Stealth / Security

請特別確認：

- 所有登入後端點是否有 `auth:sanctum`
- block 是否阻擋搜尋、profile detail、訪客紀錄、收藏與互動入口
- stealth 是否阻擋搜尋 / 推薦 / 訪客紀錄
- suspended / deleted user 是否排除
- privacy settings 是否真的影響可見性
- FCM token 是否只能新增 / 刪除自己的 token

## P6 — Frontend Integration

檢查前端：

- API path 是否與 routes 一致
- query param 名稱是否與後端一致
- pagination 是否讀 `meta.page`、`meta.last_page`
- user card mapping 是否一致
- ExploreView / ProfileDetailView 是否重複 transform 邏輯
- VisitorsView / FavoritesView 是否正確處理權限與空資料
- BlockedView 是否與 `/me/blocks` / unblock API 一致

## P7 — Cross-module Side Effects

檢查：

- follow / favorite 是否影響推薦排序或 profile card
- block 是否從 search、visitor、favorite、chat entry 全部排除
- privacy / stealth 是否同步影響 search、visitor、profile detail
- FCM token 是否在 logout / account deletion 時清理
- photo sort 是否影響 profile cover 與 search card 第一張圖

## P11 — 模組特有掃描

請執行或等價檢查以下命令，並在附錄 C 放「原始輸出 + 判讀」。

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

## Issue 等級

- 🔴 Critical：安全漏洞、線上功能壞掉、資料錯誤，已登入用戶可感知
- 🟠 High：規格要求功能未實作，或行為與規格明顯不符
- 🟡 Medium：命名 / 結構與規格不一致，但功能大致可使用
- 🔵 Low：維護性、重複實作、規格小漂移，不影響主要 UX
- ✅ Symmetric：規格與實作完全一致

## 輸出報告格式

```markdown
# Audit Report B Round 2 — 用戶資料 / 搜尋 / 配對 / 訪客 / 收藏 / 封鎖

**執行日期：** YYYY-MM-DD
**稽核者：** Gemini CLI（本機）
**Agent ID：** geminicli
**規格來源：**
- ...
**程式碼基準（Local）：** <完整 40 字元 commit hash>
**前次稽核：** <讀到的 audit-B 檔案，或未找到>
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
- [ ] 前次 issue 全部回歸，若無前次報告則明確說明
- [ ] P1 端點全部逐條列出
- [ ] P4 業務規則全部逐條列出
- [ ] P11 掃描有原始輸出與判讀
- [ ] 每個 issue 有規格證據 + 程式碼證據
- [ ] 每個 issue 有 Option A/B + 推薦
- [ ] Symmetric 至少 10 條
- [ ] 報告檔名為 `docs/audits/audit-B-YYYYMMDD-geminicli.md`
- [ ] 未修改產品程式碼
```

## Gemini CLI 防誤判提醒

- 不要只根據前次 audit 判定「未修」；必須重新讀最新規格與最新 code。
- 若規格剛被 sync，請以本輪 HEAD 的 docs 為準。
- 若找不到某 controller，先查 routes 指向哪個 controller，不要立即判定缺失。
- 若一個功能可能 Phase 2，請查規格是否已標示未實作；若已標示，列為 Symmetric 或備註，不要當 bug。
- 每個 issue 都要有「規格證據」與「程式碼證據」；缺任一項只能列 observation，不可列正式 issue。
