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
| Queue Worker | Docker service `mimeet-worker`（`docker-compose.staging.yml`，Redis driver）|
| API 健康檢查 | `GET /api/v1/auth/me` → 401（Sanctum），不是 `/auth/user` |

> **[待補]** 上監控前需新增 `GET /api/v1/health` 端點，
> 回傳 `{ db: 'ok', redis: 'ok', queue: 'ok' }` 200 OK，
> 給 UptimeRobot / Better Stack 等外部監控用。
> 觸發條件：要上任何外部監控時。

## 部署流程（強制，不可跳步）

```
1. 本機 develop 改程式碼
2. bash scripts/pre-merge-check.sh（全部 ✅ 才繼續）
3. git add + git commit + git push origin develop
4. git checkout main && git pull origin main && git merge develop --no-ff && git push origin main && git checkout develop
5. ssh root@188.166.229.100 '
   cd /var/www/mimeet && git pull origin main
   docker exec mimeet-app sh -c "touch storage/logs/laravel.log && chown www-data:www-data storage/logs/laravel.log && chmod 664 storage/logs/laravel.log"
   docker exec mimeet-app php artisan migrate --force
   docker exec -u www-data mimeet-app php artisan config:cache
   docker exec -u www-data mimeet-app php artisan route:cache
   cd /var/www/mimeet/frontend && npm ci --prefer-offline 2>&1 | tail -3 && npm run build 2>&1 | tail -5
   cd /var/www/mimeet/admin && npm ci --prefer-offline 2>&1 | tail -3 && npm run build 2>&1 | tail -5
   docker compose -f docker-compose.staging.yml restart worker
   echo "✅ Deploy 完成"
   '
6. Smoke Test：前台 200 / 後台 200 / API /api/v1/auth/me 401
```

## API Contract 變更標準回滾流程

> 適用情境：後端 API 回傳結構改變，前端必須同步更新。  
> 此類改動為跨棧（atomic）commit，部署一旦失敗需要特殊回滾流程。

### 觸發條件

以下任一條件成立，本流程即適用：

- 後端 API response 的 `data` 結構變動（陣列 ↔ 物件、欄位增刪、key 重命名）
- 後端 API 的 `meta` / pagination 結構變動
- 前端 TypeScript interface 對應修改（含 nullable 改動）
- 後端新增或修改 model relation 影響 response 內容（如 N+1 修復）
- 任何「不同時部署前後端會壞畫面」的改動

### 部署前必要檢查

- [ ] commit 切分採「止血 + atomic 完整修復」雙 commit 模式
- [ ] 本地跑 `bash scripts/pre-merge-check.sh` 全部 pass
- [ ] 後端 model 涉及 datetime 欄位時，確認 `$casts` 已宣告（避免 toISOString() on string 500）
- [ ] 前端 render function 對 nullable 欄位使用 optional chaining（`?.name` 而非 `.name`）
- [ ] 確認 git log 中前後端改動在同一個 commit（不可跨 commit）
- [ ] DB 有對應的測試資料能驗證新結構（必要時用 tinker 產生）

### 部署後監控項目（5 分鐘內必做）

- [ ] Smoke test：前台、後台、API 三個 endpoint 都 200/401
- [ ] 後端 log 觀察：`docker exec mimeet-app tail -50 storage/logs/laravel.log`
- [ ] 打開受影響的後台頁面，確認不 crash
- [ ] 檢查 nullable 邊界情境（如 `operator: null`）的渲染正確性

### 出錯時的決策樹

```
頁面 crash 或 500
  ├─ 前端 console error 明確指向某個 type 錯誤
  │    → 緊急 hotfix（小改動 push，不 rollback）
  └─ 後端錯誤或範圍模糊
       → 立即 rollback（見下方完整腳本）

頁面顯示異常但不 crash（空白、欄位錯位）
  ├─ 時間充裕（< 100 用戶受影響）
  │    → rollforward：寫補丁 PR，正常流程上線
  └─ 影響擴大中
       → rollback 後再從容修復

效能問題（不影響功能）
  → 不 rollback，另開 issue 優化
```

### 完整 Rollback 腳本（atomic commit 適用）

```bash
# Step 1：本機建立 revert commit 並推送
git checkout main
git pull origin main
git revert <commit-hash> --no-edit
# 對 merge commit：git revert <merge-hash> -m 1 --no-edit
git push origin main

# Step 2：Droplet 完整重建（與標準部署完全對稱）
ssh root@188.166.229.100 '
cd /var/www/mimeet && git pull origin main

# ⚠️  rollback 不執行 migrate --force（前進指令，無法回滾 migration）
# 若 rollback 對象含 migration，需手動評估：
#   - 該 migration 是否可逆（有 down() 方法）
#   - 是否需要 php artisan migrate:rollback --step=1（先執行，再 revert 程式碼）
#   - 或保留新 schema、僅 revert 程式碼（需確認 schema 與舊程式碼相容）

docker exec -u www-data mimeet-app php artisan config:cache
docker exec -u www-data mimeet-app php artisan route:cache

# 🔴 前後端必須一起重建（atomic commit 含跨棧改動）
cd /var/www/mimeet/frontend && npm ci --prefer-offline 2>&1 | tail -3 && npm run build 2>&1 | tail -5
cd /var/www/mimeet/admin && npm ci --prefer-offline 2>&1 | tail -3 && npm run build 2>&1 | tail -5

supervisorctl status mimeet-worker:*
echo "✅ Rollback 完成"
'

# Step 3：Smoke Test
curl -s https://mimeet.online > /dev/null && echo "✅ 前台 OK" || echo "❌"
curl -s https://admin.mimeet.online > /dev/null && echo "✅ 後台 OK" || echo "❌"
curl -s https://api.mimeet.online/api/v1/auth/me 2>&1 | grep -q "401\|Unauthenticated" && echo "✅ API OK" || echo "❌"
```

### 常見錯誤與正解

**錯誤 1：rollback 只跑後端 cache 不重建前端**  
後果：前端仍是新版 build，讀取舊版後端結構，依舊 crash。  
正解：rollback 必須完整跑一次部署腳本（含 npm run build），不能簡化。

**錯誤 2：先 git revert 才 migrate:rollback**  
後果：revert 後 migration class 不存在，`migrate:rollback` 找不到對應檔案。  
正解：先 `migrate:rollback --step=N`，**再** `git revert`。

**錯誤 3：對 merge commit 用 `git revert <hash>` 不加 `-m 1`**  
後果：報錯 `commit X is a merge but no -m option was given`。  
正解：merge commit 必須用 `git revert <merge-hash> -m 1 --no-edit`。

**錯誤 4：rollback 後沒驗收就以為結束**  
後果：可能 rollback 不完全（cache 沒清乾淨），問題仍在。  
正解：每次 rollback 後跑 smoke test + 人工確認受影響頁面。

**錯誤 5：`docker compose restart app` 取代 `up -d --force-recreate app`**  
後果：新增的 volume mount（如 fpm-output-buffering.conf）不會生效，問題仍在。  
正解：新增 volume mount 後必須用 `up -d --force-recreate app`，而非 restart。

### 相關歷史紀錄

- **2026-04-25**：admin 分數頁 crash 修復，本流程的草擬源頭（見 SESSION_SUMMARY_20260425 P4 §B.4）
- [待補] 全系統 pagination 規格化：完成後在此追加日期與 SESSION_SUMMARY 連結
- 後續同類事件可在此處追加紀錄

---

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

### POST response body 被污染（POST 帶 body → 前綴 request body + 200 text/html）
- **症狀**：`curl -X POST -d '{"foo":"bar"}'` 回來的 body 變成 `{"foo":"bar"}{"success":...}`，status 200 Content-Type text/html，缺所有 Laravel headers
- **後果**：前端 axios `res.data` 解析成 request body 而非 API response → 看起來「功能無反應 / 格式錯」。明碼密碼隨 response 洩漏。
- **根因**：`mimeet-app` container 內 `output_buffering=0`。`Dockerfile.dev` 已在 `981e3d6 (2026-04-15)` 加 `output_buffering=4096`，但 image 在 `2026-04-13` 已 build，image 沒 rebuild 就永遠拿不到這個 fix。
- **永久修復**：用 volume mount 繞過 image：`docker-compose.staging.yml` app service 已 mount `./backend/docker/output-buffering.ini:/usr/local/etc/php/conf.d/zzz-output-buffering.ini:ro`。重啟 container 時 mount 會保留，不需 rebuild。
- **2026-04-24 更新修復**：PHP-FPM 中 `output_buffering` 無法透過 conf.d ini 檔案設定（PHP_INI_PERDIR 限制），需改用 FPM pool 的 `php_admin_value`。已新增 `backend/docker/fpm-output-buffering.conf` 並在 `docker-compose.staging.yml` 加入 volume mount（`/usr/local/etc/php-fpm.d/zzz-output-buffering.conf`）。
- **未來如果又 recurrence**：先 `docker exec mimeet-app php -r 'echo ini_get("output_buffering");'`，若是 `0`→ FPM pool conf 未 mount 或未 reload，檢查 `backend/docker/fpm-output-buffering.conf` 和 compose volume；若是 `4096` 還污染→ 另有別的 echo 源頭。
- **⚠️ 關鍵：`restart` 不套用新 volume**：`docker compose restart app` 只重啟 process，不重建容器。若新增了 volume mount（如 fpm-output-buffering.conf）但容器是舊的，必須執行 `docker compose -f docker-compose.staging.yml up -d --force-recreate app` 才會真正套用。症狀：`docker exec` 找不到掛載的檔案（NOT FOUND）。

## Commit 格式

`{type}({scope}): {description}`

type: feat / fix / refactor / test / docs / chore / perf / style

## 測試帳號

| 帳號 | 密碼 | 用途 |
|------|------|------|
| chuck@lunarwind.org | ChangeMe@2026 | 後台 super_admin |

## uid=1 官方帳號

email: admin@mimeet.club，password: Test1234，每次 `php artisan mimeet:reset --force` 後自動重建。

## 稽核框架（Audit Framework）

### 1 稽核等級

| 等級 | 定義 |
|------|------|
| 🔴 Critical | 安全漏洞、線上功能壞掉、資料錯誤（已登入用戶可感知） |
| 🟠 High | 規格要求的功能未實作，或行為與規格明顯不符 |
| 🟡 Medium | 命名/結構與規格不一致，但功能可正常使用 |
| 🔵 Low | 規格與實作有微小差異，不影響用戶體驗 |
| ✅ Symmetric | 規格與程式碼完全一致 |

### 2 每輪 Report 格式

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

### 3 八輪稽核範圍分工

| Audit | 主要規格文件 | 章節範圍 |
|-------|-----------|---------|
| A | API-001 | §2 認證與身份驗證 |
| B | API-001 | §3 用戶管理 |
| C | API-001 | §4 聊天、§5 約會驗證 |
| D | API-001 | §7 訂閱付費、§10.3/§10.5/§10.9/§16 |
| E | DEV-008、API-001 | §10.8/§10.10/§10.11 誠信/停權/隱私/刪除 |
| F | API-002 | 全部章節（後台管理） |
| G | DEV-006（DDD）、DEV-012 | 36 張表 Schema + 安全漏洞 |
| H | API-001 | §3.6/§6/§8/§9.1/§10.1/§10.2/§10.4/§10.6/§10.7/§11 |

