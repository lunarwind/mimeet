# Decision Memo — `check.suspended` 中介層架構方向

**狀態：** ✅ **已簽核（2026-05-02）— 採 Option A2 + appeal whitelist 變體（D 方案）**
**建立日期：** 2026-05-01
**簽核日期：** 2026-05-02
**作者：** Claude Code（依 Audit-A Round 4 修正執行 Prompt 1-3）
**對應 audit issue：** Codex `#A5-001` 🟠 High + Bug 3 申訴閉環死結
**程式碼基準（決策時）：** `7787b7940ee45a1ad3ca5b078ef389084c660a09`（develop）
**實作 commit：** （本次 D 方案實作 commit 補入後填）
**相關規格：** docs/DEV-004 §3.1 路由規範

---

## 簽核決議（2026-05-02）

採用 **D 方案 = Option A2（CheckSuspended middleware 讀 `$request->user()->status`）+ appeal whitelist + login 1A 結構**：

1. **Login 對 suspended 用戶改回 200 + 發正常 token**（不再回 403）—— 解決 Bug 3 申訴閉環死結
2. **新增 `CheckSuspended` middleware** 掛在 26 個 `auth:sanctum` 群組之後 —— 解決 Bug 1 立即踢出
3. **4 條 whitelist 用 `->withoutMiddleware('check.suspended')`**：
   - `POST /me/appeal`（申訴提交）
   - `GET /me/appeal/current`（申訴狀態查詢）
   - `GET /auth/me`（前端讀 status 主動跳 /suspended）
   - `POST /auth/logout`（停權者要能登出）
4. **Token ability 不額外限制**（仍是普通 Sanctum PAT），靠 middleware 攔阻。被停權當下 `c5d9d57` 仍會撤舊 token，新登入會發新 token，雙保險。
5. **WebSocket / Reverb 對 token revocation 反應**（c.2 隱憂）—— 本方案不涉及，現況仍是 token 失效時 broker 可能殘留授權；待未來如有 broker 整合需求再開新 issue。

**驗收標準：**
- 26 條 auth:sanctum 路由（除 4 條 whitelist 外）對 suspended 用戶皆回 403 ACCOUNT_SUSPENDED
- 4 條 whitelist 路由對 suspended 用戶仍 200
- AuthController::login 對 active / suspended / auto_suspended 都回 200 + token
- 整合測試 `CheckSuspendedTest` 10 條 case 全綠

**否決 Option B（保留 24h 延遲）的理由：** 對 dating app 的騷擾風險與付費糾紛場景不可接受。

**否決 Option C 單做的理由：** Token revocation 已在 `c5d9d57` 完成，但只能在「下次 API 呼叫」生效；補上 middleware（A2）才能在「同一個尚未失效的 token」之間立即攔阻（雖然此情境少見，但屬深度防禦）。


---

## a. 問題描述

DEV-004 §3.1 line 189 規範「需登入路由」的中介層應為 `['auth:sanctum', 'check.suspended']`，並在 line 240 為「申訴」端點明確 `->withoutMiddleware('check.suspended')` —— 即「停權用戶能申訴但無法使用其他 API」是規格的設計意圖。

實作層 `backend/routes/api.php` 共 26 個 `auth:sanctum` 群組（line 63 / 69 / 76 / 78 / 81 / 101 / 109 / 116 / 122 / 137 / 143 / 151 / 165 / 177 / 185 / 192 / 198 / 203 / 210 / 219 / 225 / 231 / 237 / 243 / 250 / 260 等），**無一掛載 `check.suspended`**，且 `backend/app/Http/Middleware/CheckSuspended.php` 檔案不存在。停權檢查目前僅在 `AuthController:216` login 時做一次：

```php
// AuthController.php:216-220
if (in_array($user->status, ['suspended', 'auto_suspended'])) {
    return response()->json([
        'success' => false, 'code' => 'ACCOUNT_SUSPENDED', 'message' => '您的帳號已被暫停使用。',
    ], 403);
}
```

**Token 在停權時未被撤銷**（無論是 admin 手動停權於 `AdminController:388 / 503`，或誠信分數歸零自動停權於 `CreditScoreObserver:25-31`，皆只 `forceFill(['status' => 'suspended'])`，**未呼叫** `$user->tokens()->delete()`）。

**結果：** 用戶 login 後若於 24 小時內被停權，既有 PAT 仍可呼叫 26 個 `auth:sanctum` 群組所有端點，直到 token 自然過期。

---

## b. 威脅模型分析

### b.1 Token 存活期間

`backend/config/sanctum.php:29`：
```php
'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 1440), // 24h
```

預設 1440 分鐘 = **24 小時**。`backend/.env.example:32` 亦為 1440。

當前 Token 撤銷的觸發點（grep `tokens\(\)->delete\|currentAccessToken\(\)->delete`）：
- `AuthController.php:256` — logout（僅刪除當前 token）
- `AuthController.php:623` — reset-password（刪除全部 token）
- `AuthController.php:656` — change-password（刪除全部 token）

**⚠️ 停權路徑沒有任何 token 撤銷動作。**

### b.2 停權的觸發頻率與業務迫切性

| 觸發點 | 檔案 | 頻率（推估） | 是否寫 Cache 信號 |
|---|---|---|---|
| Admin 手動停權 | AdminController:388（actions 端點 `suspend`）/ AdminController:503（PATCH `/members/{id}` status 欄位） | 低（每日 0-5 次）| ❌ 否 |
| 誠信分數歸零自動停權 | CreditScoreObserver:25-31 | 中（與舉報密度相關） | ✅ 是 — `Cache::put("suspended_user:{$user->id}", true, now()->addYear())` |
| 自動恢復（分數 ≥ 解封閾值） | CreditScoreObserver:37-42 | 同上 | ✅ Cache::forget |

**⚠️ 重要副作用：** `CreditScoreObserver` 已寫入 Cache key `suspended_user:{user_id}`，但**目前沒有任何 code 讀取此 key**（grep 結果僅出現於 Observer 自身 line 30 / 42）。這是一個「半套」的設計：寫入端齊備，讀取端缺失 → CheckSuspended middleware 是其原本設計的讀取端。

### b.3 受停權影響的 API 數量

`grep -cE "auth:sanctum" backend/routes/api.php` → **26 個群組**，覆蓋範圍：
- 用戶（profile / photos / avatars / search / follow / block / visitors）
- 訂閱與付費（subscriptions / points）
- 聊天（chats / messages）
- 動態（posts / likes）
- 約會（dates / date-invitations / verify）
- 廣播（broadcasts）
- 回報（reports）
- 身份驗證（verification-photo / credit-card）
- 通知（notifications）
- 帳號操作（change-password / delete-account / blocked-users）

**結論：** 停權後攻擊面是「全平台」。若停權出於**舉報濫用、騷擾、付費糾紛**等業務迫切情境，24 小時延遲是嚴重問題。

### b.4 攻擊情境（worst case）

1. 用戶 A 騷擾用戶 B，B 連續舉報
2. CreditScoreService 因 B 群體舉報扣分，A 分數歸零 → CreditScoreObserver 自動 auto_suspend A，寫 Cache 信號
3. A 的 PAT 仍有效（最多 24h），可繼續：
   - `POST /chats/{id}/messages` 持續騷擾 B（Cache 信號未被讀取）
   - `POST /reports` 反向舉報 B 製造混亂
   - `POST /subscription/purchase` 完成付款後若被退款拒絕，留下金流糾紛

---

## c. 兩個（三個）選項的成本/效益對比

> **⚠️ 撰寫過程發現：** 原 prompt 列 Option A（補 middleware）/ Option B（修 spec）兩條，但實作端的 token 撤銷管道未做（b.1）是更直接的安全缺口。本節補上 **Option C：在停權事件中直接撤銷 token**，提供第三條路徑供決策者評估。

| 維度 | Option A（補 middleware） | Option B（修 spec 接受現狀） | Option C（停權時撤銷 token） |
|---|---|---|---|
| **安全強度** | 高（每個 auth 請求即時擋）| 低（停權後 24h 內仍可使用全平台） | 高（token 立即失效，效果同 A） |
| **實作成本** | Middleware 1 檔（~30 行）+ Kernel alias + 26 個群組改 middleware list + 整合測試 | 改 1 段 spec（DEV-004 §3.1）+ 在 §3.1 補 SOP「admin 操作後請手動撤銷 token」 | 改 2 處（AdminController suspend 路徑 + CreditScoreObserver auto_suspend），各加 `$user->tokens()->delete()` |
| **效能成本（每次 auth 請求）** | +1 次 Cache::has 查詢（若用 b.2 已存在的 Cache 信號）；或 0（若直接讀 `$request->user()->status`，已由 Sanctum hydrated）| 0（不變） | 0（一次性，停權當下執行） |
| **既有 token 處理** | 即時生效（middleware 攔阻）| 不處理（依賴 24h 自然過期） | 即時生效（DB 層 token row 被刪） |
| **WebSocket / Reverb 連線** | ❌ middleware 只擋 HTTP，已建立的 WS 連線需另外處理 | 不處理 | ⚠️ 需確認 Reverb broker 對 token revocation 的反應；Sanctum 預設不 disconnect |
| **跨團隊協調** | 前端需新增「token 仍有效但帳號被停權」錯誤分支（403 ACCOUNT_SUSPENDED 全平台都可能出現） | 無（行為不變） | 前端只需處理 401（既有的 token expired 流程）|
| **與既有 Cache 信號相容** | ✅ 直接讀 `suspended_user:{id}` 即可（CreditScoreObserver 已寫入） | N/A | ⚠️ admin 手動停權路徑無此 Cache 信號 — 需補上 |
| **可審計性 / 觀測性** | 高（每次擋下可 log） | 低 | 中（撤銷事件本身可 log，但用戶下次請求只看到 401 不知是 token 過期還是停權）|
| **回收成本（若決定回退）** | 高（需移除 26 處 middleware）| 0 | 中（只需移除兩處呼叫）|
| **規格一致性** | 與 DEV-004 §3.1 line 189 完全對齊 | 需修 DEV-004 line 189 並降低安全要求 | 規格未明確規範此實作模式，需在 DEV-004 補一段 |

### c.1 Option A 的兩個變體

| 變體 | 讀取來源 | 效能 | 與 admin 手動停權的相容性 |
|---|---|---|---|
| **A1** | `Cache::has("suspended_user:{$user->id}")` | 最快（Redis O(1)） | ❌ 需先補 AdminController:388/503 寫入 Cache key |
| **A2** | `$request->user()->status`（Sanctum 已 load） | 零額外 query | ✅ DB 為單一事實來源，不需同步 Cache |

**A2 偏好**：完全不依賴 Cache，避免 Cache stale 風險，且 admin 手動停權 / auto suspend 兩條路徑不需區分對待。

### c.2 Option C 的隱憂

- **Reverb / WebSocket**：若用戶已建立 WS 連線，token 撤銷後 broker 可能仍持有授權狀態。需驗證 Reverb 對 Sanctum token revocation 的反應，必要時在停權事件中明確 disconnect 連線。
- **多裝置場景**：用戶在手機 + 桌機都登入時，停權應撤銷**全部** token（`$user->tokens()->delete()`）而非單一裝置。

---

## d. 推薦方向

### d.1 短期（本 sprint，不需外部裁示）

✅ **單做 Option C 的最小版本**：在 `AdminController` 兩個停權路徑 + `CreditScoreObserver::updated` 自動停權路徑各加 `$user->tokens()->delete()`，順手補上 admin 手動停權的 Cache 信號（與 Observer 對齊）。

**理由：**
- 修復「停權後 token 仍有效 24h」的當下風險，**改動最小**（3 處各 1-2 行）
- 不引入 26 個群組的中介層改動 → 回歸測試成本低
- 不需架構決策即可執行（屬於 hot-fix 等級）

### d.2 中期（下個 sprint，需 PM / 安全簽核）

✅ **加上 Option A2 middleware（讀 `$request->user()->status`）**作為深度防禦第二層：

- **理由 1：** Token 撤銷依賴「停權事件」與「token 撤銷邏輯」兩端都不漏 → 未來新增停權路徑（例如批次停權、API 呼叫）若忘記呼叫 `tokens()->delete()`，仍有 middleware 擋住
- **理由 2：** 與 DEV-004 §3.1 spec 對齊
- **理由 3：** 提供「token 未過期但 status 已變」的即時偵測，作為 audit log 觸發點

**取捨：** A 與 C 可並存（不衝突）。**單做 C** 已達 80% 安全效益；**A + C 並存**達到「兩道閘門」標準（與 DEV-004 §13.1 SMS provider 設計同精神：應用層 + 路由層雙重）。

### d.3 否決 Option B 的理由

Option B（修 spec 為 login-time only）等於正式承認「停權後 24h 仍可使用全平台」是平台行為。對於 dating app 這類**騷擾風險高 + 金流糾紛多**的場景，這是不可接受的安全姿態。**不推薦**，僅作為「若 PM 認為 24h 延遲在業務上可接受」時的備案。

---

## e. 待決事項（請 PM / 安全填寫）

- [ ] **業務問題：** 停權的迫切性是否能容忍 24h 延遲？（影響 Option B 的可行性）
  - 建議由 PM 回答，依據過去 90 天停權事件的後續客訴與糾紛資料
- [ ] **技術問題：** Reverb / WebSocket 對 Sanctum token revocation 的反應？
  - 建議由後端工程師驗證（可在 staging 跑一個小實驗：登入 → 建立 WS → 撤 token → 觀察 WS 是否斷線）
- [ ] **產品問題：** 停權狀態下用戶看到的錯誤訊息設計？
  - 短期 Option C：用戶看到 401 Unauthenticated → 前端跳登入頁 → 重新登入時 AuthController:216 擋下 → 顯示「您的帳號已被暫停使用」
  - 中期 Option A：用戶看到 403 ACCOUNT_SUSPENDED → 前端顯示「您的帳號已被暫停使用，請聯繫客服或提交申訴」
  - 兩者 UX 不同，需設計確認
- [ ] **安全問題：** 是否需審計 log（哪些用戶在停權後仍有 token 試圖呼叫 API）？
  - 若需要 → Option A + 在 middleware 內 Log::info 攔阻事件
  - 若不需要 → Option C 即可

---

## f. 後續 prompt（待此決議後再執行）

- 若決議為 **單做 d.1（Option C 最小版）**：
  - 撰寫 prompt：在 AdminController suspend / unsuspend / status PATCH 三處 + CreditScoreObserver auto_suspend 一處新增 `$user->tokens()->delete()`，並在 admin 兩處 `Cache::put("suspended_user:{$user->id}", true, now()->addYear())` 對齊 Observer
  - 補 Feature test：suspend 後既有 token 應 401
- 若決議為 **d.1 + d.2（C + A2 並存）**：
  - 在 d.1 完成後，獨立 prompt 寫 `CheckSuspended.php` middleware（讀 `$request->user()->status`）+ Kernel alias + 26 群組改 middleware list + 整合測試
  - 申訴端點需 `->withoutMiddleware('check.suspended')` 或在 middleware 內白名單 route name
- 若決議為 **Option B**：
  - prompt 修 DEV-004 §3.1 line 189 移除 `check.suspended`，改為 `['auth:sanctum']` 並補 SOP 註腳
  - prompt 移除 §3.1 line 240 `->withoutMiddleware('check.suspended')` 引用

---

## 附錄 A — 證據摘錄

### A.1 DEV-004 §3.1 line 188-192

```php
// ── 需登入路由 ────────────────────────────────
Route::prefix('v1')->middleware(['auth:sanctum', 'check.suspended'])->group(function () {

    // 當前用戶
    Route::get('auth/me', [AuthController::class, 'me']);
```

### A.2 DEV-004 §3.1 line 238-241

```php
    // 申訴（停權中也可訪問，需排除 check.suspended）
    Route::post('appeal', [AppealController::class, 'submit'])
        ->withoutMiddleware('check.suspended');
});
```

### A.3 backend/routes/api.php:62-72（實作端）

```php
    // ─── Auth (authenticated) ────────────────────────────────────────
    Route::prefix('auth')->middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // ─── Phone verify (requires auth — A-001/G-001 fix) ─────────────
    Route::prefix('auth')->middleware(['auth:sanctum', 'throttle:otp'])->group(function () {
        Route::post('/verify-phone/send', [AuthController::class, 'verifyPhoneSend']);
        Route::post('/verify-phone/confirm', [AuthController::class, 'verifyPhoneConfirm']);
    });
```

（**無** `check.suspended`）

### A.4 AuthController:216-220（login-time check）

```php
if (in_array($user->status, ['suspended', 'auto_suspended'])) {
    return response()->json([
        'success' => false, 'code' => 'ACCOUNT_SUSPENDED', 'message' => '您的帳號已被暫停使用。',
    ], 403);
}
```

### A.5 CreditScoreObserver:25-31（auto suspend，含已存在的 Cache 信號）

```php
if ($newScore <= 0 && $oldScore > 0 && !in_array($user->status, ['auto_suspended', 'suspended', 'deleted'])) {
    // ... omitted 1 line ...
        'status' => 'auto_suspended',
        'suspended_at' => now(),
    // ... omitted 1 line ...
    Cache::put("suspended_user:{$user->id}", true, now()->addYear());
    Log::info("[AutoSuspend] user #{$user->id} suspended, score={$newScore}");
```

### A.6 AdminController:388 + 503（admin 手動停權，無 Cache 信號、無 token revoke）

```php
// AdminController.php:388 (action='suspend')
$user->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();

// AdminController.php:503 (PATCH /members/{id})
if ($newStatus === 'suspended') {
    $updateData['suspended_at'] = now();
}
$user->update($updateData);
```

### A.7 config/sanctum.php:29

```php
'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 1440), // 24h for users, admin tokens managed separately
```

### A.8 grep 結果：`suspended_user:` 唯二出現點

```
backend/app/Observers/CreditScoreObserver.php:30:Cache::put("suspended_user:{$user->id}", true, now()->addYear());
backend/app/Observers/CreditScoreObserver.php:42:Cache::forget("suspended_user:{$user->id}");
```

→ 信號**只寫不讀**，等待中介層接上。

---

## 簽核

| 角色 | 姓名 | 決議選項（A / B / C / A+C） | 日期 | 備註 |
|---|---|---|---|---|
| PM | | | | |
| 安全 / 後端 lead | | | | |
| Approver | | | | |
