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

### 環境配置概觀

MiMeet 分兩個環境：

| 環境 | SSH alias | 用途 | 目前狀態 |
|---|---|---|---|
| Staging | `mimeet-staging` | 開發、測試、驗證 | 在用，所有部署目標 |
| Production | `mimeet-prod` | 真實用戶服務 | **目前全空**，等 staging 完全驗證後才會推 |

> **重要**：本份規範目前**只涵蓋 staging 部署流程**。Production 首次部署時將
> 另開 issue 設計專屬流程（含資料同步、首次驗證 SOP 等）。

### Domain 命名慣例

| Domain 後綴 | 環境 | 用途 |
|---|---|---|
| `.online` | Staging | 內部開發、測試、驗證（永遠不對外推廣）|
| `.club` | Production | 對外品牌、真實用戶（目前未啟用）|

> ⚠️ 兩組 domain 永遠不交換用途。Staging 永遠用 `.online`，即使 production
> 啟用後也維持此分工。寫部署 script 時 domain 必須寫死在環境對應的 script 裡，
> 不要參數化（避免「拿 staging script 部署到 production」的災難）。

### 伺服器資訊

| 項目 | 值 |
|------|-----|
| Staging Droplet | mimeet-staging（DO SGP1，2vCPU/4GB/120GB）|
| Production Droplet | （目前全空，等首次部署時補）|
| 專案路徑 | `/var/www/mimeet` |
| Staging 前台 | https://mimeet.online |
| Staging 後台 | https://admin.mimeet.online |
| Staging API | https://api.mimeet.online |
| Production 前台 | https://mimeet.club（目前未啟用）|
| Production 後台 | https://admin.mimeet.club（目前未啟用）|
| Production API | https://api.mimeet.club（目前未啟用）|
| artisan | `docker exec -u www-data mimeet-app php artisan <cmd>` |
| Queue Worker | Docker container `mimeet-worker`（`docker-compose.staging.yml`，Redis driver）|
| API 健康檢查 | `GET /api/v1/auth/me` → 401（Sanctum），不是 `/auth/user` |

> **[待補]** 上監控前需新增 `GET /api/v1/health` 端點，
> 回傳 `{ db: 'ok', redis: 'ok', queue: 'ok' }` 200 OK，
> 給 UptimeRobot / Better Stack 等外部監控用。
> 觸發條件：要上任何外部監控時。

### SSH 連線設定

部署腳本透過 SSH alias 連線，需在本機 `~/.ssh/config` 設定：

```ssh-config
# Staging（目前唯一在用）
Host mimeet-staging
    HostName 188.166.229.100
    User root
    IdentityFile ~/.ssh/<your_key>
    ServerAliveInterval 60

# Production（預留，等首次 production 部署時取消註解並補上實際資訊）
# Host mimeet-prod
#     HostName <待補>
#     User <待補>
#     IdentityFile ~/.ssh/<待補>
#     ServerAliveInterval 60
```

### 健康檢查規範

- 對外監控上線前，**必須**提供 `GET /api/v1/health`
- 回傳格式：
  ```json
  { "status": "ok", "checks": { "db": "ok", "redis": "ok", "queue": "ok" } }
  ```
- HTTP status：200 OK（任一 check 異常時可選擇 200 + 內容標示 / 503）
- **不得以 `/auth/me` 取代外部監控健康檢查**
- 觸發實作時機：上任何外部監控（UptimeRobot / Better Stack 等）之前

## 修改前必做

0. **先確認變更是否涉及 API contract / DB schema / queue / cache / build 流程**
   - 涉及任一項 → 走「API Contract 變更標準回滾流程」（見後）
   - 都不涉及 → 走標準流程
1. 先讀相關規格文件（`docs/` 目錄）
2. 修改後更新對應的規格文件：
   - API 端點 → `docs/API-001_前台API規格書.md` 或 `docs/API-002_後台管理API規格書.md`
   - 資料庫 → `docs/DEV-006_資料庫設計與遷移指南.md`
   - 功能需求 → `docs/PRD-001_MiMeet_約會產品需求規格書.md`
   - 權限邏輯 → `docs/DEV-008_誠信分數系統規格書.md`
3. 檢查修改的功能或變數是否與前台/後台其他資料關聯，有的話一併修正

## 部署流程（強制，不可跳步）

### 標準部署

```
1. 本機 develop 改程式碼
2. bash scripts/pre-merge-check.sh（全部 ✅ 才繼續）
3. git add + git commit + git push origin develop
4. git checkout main && git pull origin main && git merge develop --no-ff && git push origin main && git checkout develop
5. 執行部署 script：
   - 互動式（預設）：bash scripts/staging-deploy.sh
   - 跳過確認（熟手/CI）：bash scripts/staging-deploy.sh --yes
6. Smoke Test：前台 200 / 後台 200 / API /api/v1/auth/me 401（已含在 staging-deploy.sh 中）
```

> 部署 script 抽出在 `scripts/staging-deploy.sh` + `scripts/staging-server-deploy.sh`，
> 透過 SSH alias `mimeet-staging` 執行。詳細邏輯見 script 註解。

### 部署 script 設計原則

- `set -euo pipefail`：任何步驟失敗即中斷部署，不靜默吞錯
- 結構化 step 標記：`[1/7]`、`[2/7]`...便於診斷哪一步失敗
- log 導檔：`/var/log/mimeet-deploy/deploy-YYYYMMDD-HHMMSS.log`
- 失敗時自動 tail 50 行 log
- 部署成功後自動寫入 `.deploy-version`（含 git SHA + 時間戳 + 部署者）

### Container 變更規則

依改動性質決定 docker compose 的處理方式：

| 情境 | 指令 | 說明 |
|---|---|---|
| 僅程式碼更新 | 標準 `bash scripts/staging-deploy.sh` | 走 git pull + cache rebuild |
| compose / volume / PHP-FPM 設定變更 | `docker compose -f docker-compose.staging.yml up -d --force-recreate app` | 強制重建 container |
| worker 設定變更 | `docker compose -f docker-compose.staging.yml up -d --force-recreate worker` | 同上 |
| 單純重啟 service | `docker compose -f docker-compose.staging.yml restart <service>` | 不重建，僅重啟 process |

**選錯指令的後果**：
- 改了 volume mount 但用 `restart` → 新 volume 不會掛載，container 仍用舊設定
- 改了 PHP-FPM 設定但用 `restart` → 新設定不會載入

歷史案例：2026-04-24 新增 `fpm-output-buffering.conf` volume mount 後，誤用 `restart` 導致設定未套用，症狀為 `docker exec` 找不到掛載的檔案（NOT FOUND）。改用 `up -d --force-recreate app` 後正常。

## API Contract 變更標準回滾流程

> 適用情境：後端 API 回傳結構改變，前端必須同步更新。
> 此類改動為跨棧 atomic 改動，部署一旦失敗需要特殊回滾流程。

### 觸發條件

以下任一條件成立，本流程即適用：

- 後端 API response 的 `data` 結構變動（陣列 ↔ 物件、欄位增刪、key 重命名）
- 後端 API 的 `meta` / pagination 結構變動
- 前端 TypeScript interface 對應修改（含 nullable 改動）
- 後端新增或修改 model relation 影響 response 內容（如 N+1 修復）
- 任何「不同時部署前後端會壞畫面」的改動

### 部署前必要檢查

- [ ] **同一次 deploy 必須包含完整相容的前後端改動。**
      若屬於 API Contract 破壞性變更，應以單一 PR 或單一 merge unit 進入 main，
      避免前後端拆開上線。（commit 可以分多個，但 merge 到 main 必須是可獨立 deploy 狀態）
- [ ] commit 切分採「止血 + atomic 完整修復」雙 commit 模式
- [ ] 本地跑 `bash scripts/pre-merge-check.sh` 全部 pass
- [ ] 後端 model 涉及 datetime 欄位時，確認 `$casts` 已宣告（避免 toISOString() on string 500）
- [ ] 前端 render function 對 nullable 欄位使用 optional chaining（`?.name` 而非 `.name`）
- [ ] DB 有對應的測試資料能驗證新結構（必要時用 tinker 產生）

### 部署後監控項目（5 分鐘內必做）

- [ ] Smoke test：前台、後台、API 三個 endpoint 都 200/401（已含在 staging-deploy.sh）
- [ ] 後端 log 觀察：`ssh mimeet-staging 'docker exec mimeet-app tail -50 storage/logs/laravel.log'`
- [ ] 打開受影響的後台頁面，確認不 crash
- [ ] 檢查 nullable 邊界情境（如 `operator: null`）的渲染正確性

### 出錯時的決策樹

```
頁面 crash 或 500
  ├─ 前端 console error 明確指向某個 type 錯誤
  │    → 緊急 hotfix（小改動 push，不 rollback）
  └─ 後端錯誤或範圍模糊
       → 立即 rollback：bash scripts/staging-rollback.sh

頁面顯示異常但不 crash（空白、欄位錯位）
  ├─ 時間充裕（< 100 用戶受影響）
  │    → rollforward：寫補丁 PR，正常流程上線
  └─ 影響擴大中
       → rollback 後再從容修復

效能問題（不影響功能）
  → 不 rollback，另開 issue 優化
```

### Rollback 執行

使用 `scripts/staging-rollback.sh`：

```bash
# 回滾上一次部署（讀 .deploy-version）
bash scripts/staging-rollback.sh

# 回滾到指定 commit
bash scripts/staging-rollback.sh <commit-sha>
```

**Rollback 採「與標準部署相同的重建原則，但不執行前進型 migration」。**
若 rollback 對象含 migration，需手動評估是否 `migrate:rollback`（見「常見錯誤與正解」第 2 條）。

### 常見錯誤與正解

**錯誤 1：rollback 只跑後端 cache 不重建前端**
後果：前端仍是新版 build，讀取舊版後端結構，依舊 crash。
正解：rollback script 已包含完整重建（前後端 npm run build），不可手動簡化。

**錯誤 2：先 git revert 才 migrate:rollback**
後果：revert 後 migration class 不存在，`migrate:rollback` 找不到對應檔案。
正解：先 `migrate:rollback --step=N`，**再** `git revert`。

**錯誤 3：對 merge commit 用 `git revert <hash>` 不加 `-m 1`**
後果：報錯 `commit X is a merge but no -m option was given`。
正解：merge commit 必須用 `git revert <merge-hash> -m 1 --no-edit`。Rollback script 已自動判斷。

**錯誤 4：rollback 後沒驗收就以為結束**
後果：可能 rollback 不完全，問題仍在。
正解：每次 rollback 後跑 smoke test + 人工確認受影響頁面。

### 相關歷史紀錄

- **2026-04-25**：admin 分數頁 crash 修復，本流程的草擬源頭（見 SESSION_SUMMARY_20260425 P4 §B.4）
- **2026-04-26**：CLAUDE.md 整合重寫 layer 2，補上腳本化與 worker 健康檢查盲點修復
- [待補] 全系統 pagination 規格化：完成後在此追加日期與 SESSION_SUMMARY 連結
- 後續同類事件可在此處追加紀錄

---

## 四項禁令

1. **禁止**直接在 staging Droplet 上修改任何檔案
2. **禁止**在 main 上直接 commit（main 只接受從 develop merge）
3. **禁止**跳過 pre-merge-check.sh
4. **禁止** deploy 時不 rebuild 前台/後台

原因：2026-04 發生 main/develop 漂移事件，修好的 bug 反覆復發，最終用 force-reset 救回。

## 例外處理原則

> 若現場狀況與文件流程衝突，**以保護 staging 穩定性為優先**：
>
> **先止血、再補文件、最後做流程修正。**
>
> 任何例外處置必須在事後補進 SESSION_SUMMARY 與專案規則。

### 適用情境

- 真實 staging 事故（500、無法登入、資料丟失等）
- 流程要求的步驟在當下技術上無法執行（如 SSH 連不上時）
- 文件本身有錯（罕見，但發生過——例如 supervisorctl 健康檢查盲點）

### 例外處置 SOP

1. **止血**：用任何手段恢復服務（即使破壞流程）
2. **記錄**：SSH session log、錯誤截圖、執行的指令全部留下
3. **事後補文件**：48 小時內在 SESSION_SUMMARY 新增條目，內容含：
   - 觸發例外的情境
   - 採取的非標準步驟
   - 與標準流程的差異
   - 如何避免未來再次例外（流程是否需修正）
4. **流程修正**：若例外情境會重複發生，必須更新 CLAUDE.md 將其納入標準流程

## pre-merge-check.sh 設計原則

`bash scripts/pre-merge-check.sh` 為部署前的最後守護，必須包含以下類型 check：

### 必含檢查類型

1. **靜態程式碼**
   - PHP 語法與 artisan 指令驗證
   - admin/ 與 frontend/ 的 `npm run build`（含 TypeScript 編譯）

2. **API Contract 守護**
   - response 結構退化攔截（如 14a-1 ~ 14g 守護 credit-logs API）
   - 規格欄位名退化攔截（如 14b 守護「不可使用 `delta`」）

3. **資料層守護**
   - model `$casts` 完整性（如 14h）

4. **健康檢查守護**
   - 禁止腳本/CLAUDE.md 出現失效的健康檢查指令（如 14p 禁止 supervisorctl）

5. **規格一致性**
   - 三方規格書的關鍵格式（DEV-004 / API-001 / API-002 / DEV-006）

### 設計原則

- **正向 + 負向雙重守護**：對重要結構同時驗證「新樣貌存在」與「舊樣貌不存在」
- **awk/sed 切片精準範圍**：跨 method 的 grep 容易誤觸，必須切片到目標方法區段內
- **失敗就阻擋 merge**：任何 check fail 都不應 merge 到 main

### 維護原則

每次發現規格分裂或退化事故後，**必須**新增對應 check 條目防止再犯。
新增條目編號接續最後一條（目前已到 14p）。

## Commit 格式

`{type}({scope}): {description}`

**type**: feat / fix / refactor / test / docs / chore / perf / style

**scope** 必須從以下清單選擇：

| Scope | 用途 |
|---|---|
| `frontend` | frontend/ 前台改動 |
| `admin` | admin/ 後台改動 |
| `backend` | backend/ Laravel 改動 |
| `api` | API contract 改動（跨棧）|
| `auth` | 認證相關（前後端皆可）|
| `payment` | 付款流程相關 |
| `chat` | 聊天相關 |
| `infra` | docker-compose / nginx / 其他基礎設施 |
| `scripts` | scripts/ 內的部署/工具腳本 |
| `docs` | 純文件改動（規格書、CLAUDE.md、SESSION_SUMMARY）|
| `db` | migration 或 schema 改動 |

**範例**：
- `fix(admin): prevent member score page crash`
- `refactor(api): unify pagination meta structure`
- `chore(scripts): add staging-deploy.sh with version lock`
- `docs: align DEV-006 references in CLAUDE.md`

不允許自創 scope（`fix(stuff)` / `chore(misc)` 等）。

## 已知陷阱（歷史教訓）

> **註**：Container 變更相關的陷阱（restart vs up -d --force-recreate）已移至
> 上方「部署流程 → Container 變更規則」段落。本節保留其他類型的歷史教訓。

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

### POST response body 被污染（POST 帶 body → 前綴 request body + 200 text/html）
- **症狀**：`curl -X POST -d '{"foo":"bar"}'` 回來的 body 變成 `{"foo":"bar"}{"success":...}`，status 200 Content-Type text/html，缺所有 Laravel headers
- **後果**：前端 axios `res.data` 解析成 request body 而非 API response → 看起來「功能無反應 / 格式錯」。明碼密碼隨 response 洩漏。
- **根因**：`mimeet-app` container 內 `output_buffering=0`。`Dockerfile.dev` 已在 `981e3d6 (2026-04-15)` 加 `output_buffering=4096`，但 image 在 `2026-04-13` 已 build，image 沒 rebuild 就永遠拿不到這個 fix。
- **永久修復**：用 volume mount 繞過 image：`docker-compose.staging.yml` app service 已 mount `./backend/docker/output-buffering.ini:/usr/local/etc/php/conf.d/zzz-output-buffering.ini:ro`。重啟 container 時 mount 會保留，不需 rebuild。
- **2026-04-24 更新修復**：PHP-FPM 中 `output_buffering` 無法透過 conf.d ini 檔案設定（PHP_INI_PERDIR 限制），需改用 FPM pool 的 `php_admin_value`。已新增 `backend/docker/fpm-output-buffering.conf` 並在 `docker-compose.staging.yml` 加入 volume mount（`/usr/local/etc/php-fpm.d/zzz-output-buffering.conf`）。
- **未來如果又 recurrence**：先 `docker exec mimeet-app php -r 'echo ini_get("output_buffering");'`，若是 `0`→ FPM pool conf 未 mount 或未 reload，檢查 `backend/docker/fpm-output-buffering.conf` 和 compose volume；若是 `4096` 還污染→ 另有別的 echo 源頭。

### Worker 健康檢查盲點（2026-04-25 修復）
- **症狀**：過去所有部署腳本與 prompt 用 `supervisorctl status mimeet-worker:*` 查 worker 健康
- **真相**：Staging 主機 `/etc/supervisor/conf.d/` 為空，主機 supervisord 跑著但未管理任何 program
- **後果**：所有 deploy 後印的「✅ worker OK」都是空話。真正的 worker 因 docker `restart: unless-stopped` 自動恢復，運氣好沒釀事故
- **修復**：全面改用 `docker compose ps worker`，並在 docker-compose.staging.yml 為 worker.depends_on 加 `redis: { condition: service_healthy }` 消除啟動競態
- **預防**：pre-merge-check 14p 禁止腳本出現 `supervisorctl` 字樣

## 測試帳號

| 帳號 | 密碼 | 用途 |
|------|------|------|
| chuck@lunarwind.org | ChangeMe@2026 | 後台 super_admin |

> ⚠️ **測試帳號限制**
> - 僅限 staging / local 開發環境使用
> - 禁止在 production 沿用相同密碼
> - 若此密碼疑似外流，立即輪替（admin 後台 → 帳號管理 → 重設密碼）

## uid=1 官方帳號

email: admin@mimeet.club，password: Test1234，每次 `php artisan mimeet:reset --force` 後自動重建。

## 稽核框架（Audit Framework）

### 1 稽核等級

| 等級 | 定義 |
|------|------|
| 🔴 Critical | 安全漏洞、線上功能壞掉、資料錯誤（已登入用戶可感知）|
| 🟠 High | 規格要求的功能未實作，或行為與規格明顯不符 |
| 🟡 Medium | 命名/結構與規格不一致，但功能可正常使用 |
| 🔵 Low | 規格與實作有微小差異，不影響用戶體驗 |
| ✅ Symmetric | 規格與程式碼完全一致 |

### 2 證據要求

每個 audit issue **必須**附以下兩種證據：

1. **規格引用**：明確指向某份規格書的章節與行號
   - 範例：「依 DEV-006 §3.2 line 145，operator_id 應為 nullable」
2. **程式碼引用**：明確指向實作檔案的行號或函式
   - 範例：「但 backend/app/Models/CreditScoreHistory.php:47 未宣告 nullable」

若判定為 ✅ Symmetric（規格與實作對齊），同樣需列出對照依據：

```
✅ Symmetric
- 規格依據：API-002 §4.4 (line 645)
- 實作對照：backend/app/Http/Controllers/Api/V1/AdminController.php::memberCreditLogs (line 297)
- 結論：response 結構 `{ data: [], meta: { page, ... } }` 完全一致
```

避免「憑印象寫」的 audit 結論。

### 3 每輪 Report 格式

產出到 `docs/audits/audit-{X}-{YYYYMMDD}.md`：

```markdown
# Audit Report {X} — {領域}
**執行日期：**
**稽核者：** Claude Code
**規格來源：** docs/{實際讀取的規格文件名稱與版本號}
**程式碼基準：** git log --oneline -1（當前 HEAD commit）
**總結：** {N} issues（🔴 N / 🟠 N / 🟡 N / 🔵 N）+ {M} Symmetric

---

## 規格文件摘要（本輪讀到的）

> 列出本輪從 docs/ 讀到的規格章節標題 + 版本號，
> 作為「稽核基準」的正式記錄。

---

## 索引

🔴 Critical
- Issue #{X}-001 — [摘要]

✅ Symmetric
- [列出對照一致的端點/功能]

---

## Issue #{X}-001

**規格位置：** docs/API-001 §X.Y（第 N 行）
**規格內容：** [直接引用規格原文，不超過 5 行]
**程式碼位置：** backend/routes/api.php:N 或 frontend/src/api/xxx.ts:N
**程式碼現況：** [直接引用程式碼片段，不超過 5 行]
**差異說明：** [具體描述差異]
**等級：** 🔴 Critical
**建議方案：**
- Option A：改規格
- Option B：改程式碼
- Option C：雙向修改
**推薦：** B（理由）
```

### 4 八輪稽核範圍分工

| Audit | 主要規格文件 | 章節範圍 |
|-------|-----------|---------|
| A | API-001 | §2 認證與身份驗證 |
| B | API-001 | §3 用戶管理 |
| C | API-001 | §4 聊天、§5 約會驗證 |
| D | API-001 | §7 訂閱付費、§10.3/§10.5/§10.9/§16 |
| E | DEV-008、API-001 | §10.8/§10.10/§10.11 誠信/停權/隱私/刪除 |
| F | API-002 | 全部章節（後台管理）|
| G | DEV-006、DEV-012 | 36 張表 Schema + 安全漏洞 |
| H | API-001 | §3.6/§6/§8/§9.1/§10.1/§10.2/§10.4/§10.6/§10.7/§11 |
