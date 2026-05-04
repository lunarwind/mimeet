# [DEV-013] MiMeet Pre-merge Check 規格書

**文檔版本：** v1.0
**建立日期：** 2026-05-05
**適用範圍：** `scripts/pre-merge-check.sh`
**目的：** 統一記錄所有 pre-merge 守護條目的意圖、歷史依據與修復指引，避免單點失敗或檢查重複退化。

---

## 0. 概述

`scripts/pre-merge-check.sh` 是 MiMeet 在 `develop → main` 之前的最後守護。
所有規格分裂、退化事故與重大漂移修復後，**都應**新增對應守護條目防止再犯。

### 0.1 設計原則

1. **正向 + 負向雙重守護**：對重要結構同時驗證「新樣貌存在」與「舊樣貌不存在」。
2. **awk/sed 切片精準範圍**：跨 method 的 grep 容易誤觸，必須切片到目標方法區段內。
3. **失敗就阻擋 merge**：任何 check fail 都不應 merge 到 main（warning 級例外）。
4. **編號接續，不重排**：新增條目編號接續最後一條（目前已到 14ag）。
5. **每條 check 必須附歷史依據**：在 script 內以 comment 註明觸發此守護的事故或規範依據。

### 0.2 check 函數契約

```bash
check() {
  local desc="$1"     # 顯示給人看的描述
  local cmd="$2"      # 要執行的 shell 命令（用 eval 執行）
  local expected="$3" # 對 cmd 結果的 grep -qE 正則
}
```

- `cmd` 結果用 `grep -qE "$expected"` 判斷；符合即 OK，否則 ERRORS+1。
- 慣例：要驗「不存在」時，cmd 算出 0/匹配數，expected `^0$`。
- Warning 級守護不走 `check()`，自行印 `[WARN xx]` 訊息但不增 ERRORS。

### 0.3 維護原則

- 每次發現規格分裂或退化事故，**必須**加 check 防止再犯。
- 編號用 `14a-1, 14a-2, 14b … 14ad, 14ae, 14af, 14ag …` 連續累積，**不重排**。
- 修改既有 check 時，連帶更新本文件對應段落。

---

## 1. 完整 check 條目清單

> 順序與 `scripts/pre-merge-check.sh` 一致。每條列出：編號、語意、歷史依據、修復方向。

### 1.1 .env 必填變數檢查

- **語意**：執行 `scripts/check-env.sh backend/.env`，critical 缺漏立即中止。
- **依據**：CLAUDE.md「敏感檔案同步流程」。
- **修復**：補齊 `.env` 必填變數（DB / Redis / Reverb / FCM 等）。

### 1.2 後端基本守護

| 編號 | 語意 |
|------|------|
| — | DatasetController uses `mimeet:reset`（指令名稱統一） |
| — | 全 codebase 不出現 `mimeet:reset-clean`（已棄用） |
| — | AdminController weight 從 user 讀（防 hardcoded null） |
| — | SubscriptionPlanSeeder 用 `updateOrInsert`（防 fresh 後消失） |
| — | ResetToCleanState reseeds subscription_plans |
| — | SendBroadcastJob 支援 DM 模式 |
| — | BroadcastController 用 async dispatch |
| — | Mock payment 回 HTML |
| — | Dockerfile.dev 設 `output_buffering=4096`（POST body 污染防護） |
| — | docker-compose.staging.yml mount `output-buffering.ini` |
| — | `backend/docker/output-buffering.ini` 存在 |

> POST body 污染歷史見 CLAUDE.md「已知陷阱 → POST response body 被污染」。

### 1.3 前端 snake_case ↔ camelCase 映射守護

- usePayment maps `expires_at`
- fetchConversations maps `other_user → targetUser`
- fetchMessages maps `sent_at → createdAt`
- VerifyView 上傳到 `/users/me/photos`
- ShopView 有 payment method selector

> 依據：CLAUDE.md「snake_case → camelCase 映射」。

### 1.4 14a-1 ~ 14g — Admin credit-logs API 結構守護

| 編號 | 語意 | 依據 |
|------|------|------|
| 14a-1 | memberCreditLogs `data` 直接由 `$logs->map` 衍生 | API-002 §4.4 |
| 14a-2 | memberCreditLogs 不存在 `'logs' =>` 包裝層 | 同上 |
| 14b | 使用 `'change' =>`（非 `'delta'`） | API-002 §4.4 |
| 14c | 不使用 `score_before`/`score_after` 舊欄位 | API-002 §4.4 |
| 14d | operator 回物件（非 `'operator_id'` 整數） | API-002 §4.4 |
| 14e | 使用 `with('adminUser')` eager loading（防 N+1） | DEV-004 |
| 14f | meta 使用 `'page' =>`（非 `'current_page'`） | API-002 §4.4 / Pagination 規格 |
| 14g | MemberDetailPage `op?.name` optional chaining | 防 runtime crash |

> 用 awk 切片 `memberCreditLogs` 方法區段，避免誤觸其他方法。

### 1.5 14h ~ 14i — Model 與枚舉守護

| 編號 | 語意 | 依據 |
|------|------|------|
| 14h | DateInvitation `created_at` 有 datetime cast | `$timestamps=false` model `toISOString()` 500 防護 |
| 14i | CreditScoreHistory.type 不用舊枚舉值 | DEV-008 §10.3 規格化 |

### 1.6 14p — Worker 健康檢查盲點

- **14p**：`scripts/` 中不出現 `supervisorctl`。
- **依據**：CLAUDE.md「Worker 健康檢查盲點」（2026-04-25 修復）。
- **修復**：改用 `docker compose -f docker-compose.staging.yml ps worker`。

### 1.7 14q — TypeScript strict mode

- **14q**：`admin/tsconfig.app.json` 啟用 `strict: true`。
- **依據**：admin 分數頁 crash 修復（2026-04-25）。

### 1.8 14r ~ 14v — Pagination 統一守護

| 編號 | 語意 |
|------|------|
| 14r | 後端 list API 不用 `'pagination'` wrapper |
| 14s | 後端 list API 不用 `'current_page'` 欄位 |
| 14t | 後端 list API 不用 `'total_pages'` 欄位 |
| 14u | 前端不讀 `.pagination.current_page` |
| 14v | 前端不讀 `.pagination.total_pages` |

> 依據：CLAUDE.md「API Contract 一致性原則 → Pagination 標準格式」（2026-04-26 全系統規格化）。

### 1.9 14w ~ 14y — 敏感檔案守護

| 編號 | 語意 |
|------|------|
| 14w | 禁止 `service-account.json` 在 git working tree |
| 14x | `.env.example` 含 `FIREBASE_CREDENTIALS_PATH` |
| 14y | `.env.example` 不含棄用 `FCM_SERVER_KEY`（FCM Legacy API 已停服） |

> 依據：CLAUDE.md「敏感檔案同步流程」。

### 1.10 14z — DB 寫入守護

- **14z**：`SystemControlController` 不應有 `writeEnv` 或 `file_put_contents .env`。
- **依據**：CLAUDE.md「敏感檔案同步流程 → 歷史教訓」（2026-04-26 移除危險 PATCH /database endpoint）。

### 1.11 14aa ~ 14ab — Code quality

| 編號 | 語意 |
|------|------|
| 14aa | `CreditScoreHistory.type` 不使用 `test_*` prefix（預防測試殘留） |
| 14ab | `frontend/` 不使用 `catch (err: any)` / `catch (e: any)` |

### 1.12 14ac ~ 14ad — Register payload

| 編號 | 語意 | 依據 |
|------|------|------|
| 14ac | `frontend/src/api/auth.ts` 不 hardcode 勾選欄位為 true | 避免「用戶未勾被視為同意」的法律風險（2026-04-26 修復） |
| 14ad | `RegisterPayload` 含 `password_confirmation` 欄位 | 防 register 422 復活 |

### 1.13 14ae ~ 14ag — QR flow drift guards（**本次新增**）

#### 14ae — QR 命名漂移守護

- **語意**：`backend/app frontend/src admin/src` 三個目錄都不應出現舊命名 `qr_code` / `qrCode` / `qrExpiresAt` / `qr_expires_at`。
- **依據**：API-001 §5.1 確立 wire format 採 `qr_token` + `expires_at`，對齊 DB schema 與 PHP model。早期文件用過 `qr_code` 等命名，PR-QR Step 2（Cleanup, 2026-05-04）已全面汰換。
- **觸發背景**：QR flow 在 PR-QR Step 1–6 多次補丁中，混用過至少 4 種命名變體（`qr_token` / `qr_code` / `qrCode` / camelCase），造成前後端讀取點不一致。
- **修復**：統一使用 `qr_token`（snake_case wire / DB）與 `qrToken`（前端 camelCase）。
- **指令**：

  ```bash
  grep -rnE 'qr_code|qrCode|qrExpiresAt|qr_expires_at' backend/app frontend/src admin/src 2>/dev/null \
    | wc -l | tr -d ' '
  # expected: ^0$
  ```

#### 14af — Carbon datetime mutator 守護

- **語意**：禁止對 Eloquent model 帶 datetime cast 的 attribute 直接 `->subX()` / `->addX()`。
- **依據**：Eloquent datetime cast 回傳 Carbon 實例的引用；對該屬性直接呼叫 mutator 會修改 model attribute，導致同 instance 後續讀取拿到被改過的值。修法是先 `->copy()`（或 `->clone()`）再 mutate。
- **觸發背景**：QR flow Step 5–6 曾為了倒數計時直接 `$invitation->expires_at->subMinutes(...)`，導致 admin 列表顯示的 `expires_at` 在 controller 處理過程中被「移走」。本次 Pre-merge Guard 強化任務同步發現 `DeleteAccountController.php:41` 也有相同 drift（`$user->delete_requested_at->addDays(7)`），於同一 commit 修復。
- **修復**：把 `$x->attr->mutator()` 改成 `$x->attr->copy()->mutator()` 或 `->clone()->mutator()`。
- **指令**（只攔截 `$var->attr->mutator()` 形式，自然排除 `now()->...` / `Carbon::parse(...)->...` / `$carbonVar->mutator()` 等合法用法）：

  ```bash
  grep -rnE '\$[a-zA-Z_][a-zA-Z_0-9]*->[a-zA-Z_][a-zA-Z_0-9]*->(sub|add)(Seconds|Minutes|Hours|Days|Weeks|Months|Years)\(' backend/app 2>/dev/null \
    | grep -vE 'copy\(\)->|clone\(\)->' \
    | wc -l | tr -d ' '
  # expected: ^0$
  ```

- **掃描範圍**：僅 `backend/app`（不含 tests，因測試常以 fixture 直接組 Carbon）。

#### 14ag — 前端 transformer hardcoded null 警告（warning 級）

- **語意**：`frontend/src/api/*.ts` 中 transformer 內出現裸 `field: null,` 賦值，極可能代表 API 該欄位未映射或暫以 null 占位。
- **嚴重度**：**Warning，不阻擋 merge**。少數情況是合法的（如 `dates.ts:36` 的 `creditScoreChange: null`，理由：list endpoint 不返回該欄位，transformer fallback 為 null）。
- **依據**：QR flow Step 6 曾因前端 transformer 在 list API 漏映射 `qrToken` / `expiresAt`（hardcode 為 null），導致 DateCard「顯示 QR」按鈕展開後 QR 圖空白但 console 不報錯，bug 直到手動測試才浮現。
- **修復方向**：
  - 若是漏映射 → 加入正確的 `field: x.field` 映射。
  - 若是合法 fallback → 在註解明示原因，例如 `creditScoreChange: null, // list API 不返回，detail 才有`。
- **不增 ERRORS**：守護腳本以 `[WARN 14ag]` 列出所有命中行，由 reviewer 人工判斷。
- **指令**：

  ```bash
  WARN_14AG=$(grep -rnE ':[[:space:]]*null,?[[:space:]]*(//|$)' frontend/src/api 2>/dev/null || true)
  if [ -n "$WARN_14AG" ]; then
    echo "  [WARN 14ag] frontend/src/api transformer 出現 hardcoded null（請確認是否為刻意 fallback）："
    echo "$WARN_14AG" | sed 's/^/    /'
  fi
  ```

### 1.14 14ah — IMPLEMENTATION_STATUS 一致性（待實作）

- **語意**（草案）：當 PRD / API-001 / API-002 中標 `[實作]` / `Phase 1` 的功能與 `IMPLEMENTATION_STATUS.md` 條目不一致時，pre-merge 提示。
- **狀態**：**待實作**。`IMPLEMENTATION_STATUS.md` 結構需先標準化才能機械比對。
- **追蹤**：見 `docs/IMPLEMENTATION_STATUS.md` 的 follow-up 條目。

---

## 2. 修復指引

### 2.1 14ae fail 怎麼辦

1. `grep -rn 'qr_code\|qrCode\|qrExpiresAt\|qr_expires_at' backend/app frontend/src admin/src` 列出所有命中。
2. 統一改成 `qr_token`（snake_case wire / DB）或 `qrToken`（前端 camelCase）。
3. 前端 transformer：`qrToken: x.qr_token`、`expiresAt: x.expires_at`。
4. 重跑 `bash scripts/pre-merge-check.sh` 確認 14ae OK。

### 2.2 14af fail 怎麼辦

1. 看 fail 訊息找出檔案與行號。
2. 把 `$x->attr->mutator()` 改成 `$x->attr->copy()->mutator()`。
3. 若是有意修改 model attribute（極罕見），請改用 `$x->attr = $x->attr->copy()->mutator()` 並用 `$x->save()` 顯式持久化。
4. **不要**改成 `Carbon::parse($x->attr)->mutator()` —— 這雖然能繞過 grep，但語意上是「重新解析字串」，與「複製既有 Carbon 物件」不同。

### 2.3 14ag warning 怎麼辦

- 檢視 transformer 命中的欄位：
  - **漏映射** → 加上 `field: x.field`。
  - **合法 fallback** → 加註解說明，例：

    ```typescript
    creditScoreChange: null, // list API 不返回，detail 才有
    ```

- Warning 不阻擋 merge，但 reviewer 應在 PR 審查時帶過確認。

---

## 3. 違反此規範的歷史教訓

- **2026-04-25**：admin 分數頁 crash → 觸發 14a-1 ~ 14g + 14h + 14q（SESSION_SUMMARY_20260425）
- **2026-04-26**：全系統 pagination 規格化 → 觸發 14r ~ 14v
- **2026-04-26**：FCM 設定規範化 → 觸發 14w ~ 14y
- **2026-04-26**：admin DB UI 反覆 500 → 觸發 14z（移除危險 writeEnv）
- **2026-04-26**：register hardcode terms = true → 觸發 14ac ~ 14ad
- **2026-05-04**：QR flow Cleanup PR-QR Step 2，wire format 統一 `qr_token` + `expires_at`，移除舊命名 → 觸發 14ae
- **2026-05-04**：QR flow Step 5–6 expires_at mutator drift；本次擴大掃描發現 DeleteAccountController.php:41 同樣問題 → 觸發 14af + 同 commit 修復
- **2026-05-04**：QR flow list endpoint transformer 漏映射 qrToken → 觸發 14ag

---

## 4. 文件關聯

| 文件 | 說明 |
|------|------|
| `CLAUDE.md` | 專案總則，包含部署流程、API contract 一致性、敏感檔案、四項禁令 |
| `scripts/pre-merge-check.sh` | 守護實作 |
| `scripts/check-env.sh` | .env 必填變數檢查（被 pre-merge-check 呼叫） |
| `docs/IMPLEMENTATION_STATUS.md` | 實作狀態追蹤；14ah 機械比對守護待此檔結構標準化後實作 |
| `AGENTS.md` | 多 agent 協作規範，含 API Contract 變更回滾流程 |
