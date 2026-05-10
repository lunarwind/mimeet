# 註冊 SMS 稍後驗證與刪除帳號釋放識別資料調查報告

## 0. 調查方法
- 分支：develop
- HEAD：831663d chore(scripts): Pre-merge Guard 強化 — 14ae/14af/14ag + DeleteAccountController mutator 修復
- 工作區狀態：
  - modified: backend/.phpunit.result.cache
  - modified: backend/bootstrap/cache/services.php
  - modified: backend/storage/logs/laravel.log
  - deleted: progress/index.html
- 本報告依據：本機 develop 分支實際程式碼與 docs/ 目錄下規格文件。

## 1. 程式碼地圖
### 1.1 註冊 / SMS 前端
- `frontend/src/views/public/RegisterView.vue`：註冊四步驟流程 UI 與邏輯（含「稍後驗證」）。 ✅
- `frontend/src/router/routes/app.ts`：App 內部路由定義與 `minLevel` 權限宣告。 ✅
- `frontend/src/router/guards.ts`：全域路由守衛，負責 `minLevel` 與 `status` 檢查。 ✅
- `frontend/src/stores/auth.ts`：前端認證狀態管理（Pinia），定義 `isVerified` 等 computed 屬性。 ✅

### 1.2 註冊 / SMS 後端
- `backend/app/Http/Controllers/Api/V1/AuthController.php`：處理註冊、登入、Email 驗證與 SMS 驗證 API。 ✅
- `backend/app/Http/Middleware/CheckSuspended.php`：阻擋停權帳號的 Middleware。 ✅
- `backend/app/Models/User.php`：用戶模型，定義 `status`、`membership_level` 與 `phone_hash` 邏輯。 ✅

### 1.3 後台刪除帳號
- `backend/app/Http/Controllers/Api/V1/AdminController.php`：後台管理 API，含 `deleteMember`。 ✅
- `backend/app/Services/GdprService.php`：負責用戶資料匿名化（Anonymize）與刪除申請邏輯。 ✅
- `backend/database/migrations/2026_04_08_000000_create_users_table.php`：定義 `email` 唯一索引與 `deleted_at` 欄位。 ✅
- `backend/database/migrations/2026_05_03_140000_add_phone_hash_to_users.php`：定義 `phone_hash` 唯一索引。 ✅

### 1.4 黑名單 / 禁止註冊
- `backend/app/Models/UserBlock.php`：用戶間封鎖邏輯，非系統黑名單。 ✅
- `backend/app/Http/Controllers/Api/V1/AuthController.php`：註冊查重邏輯（排除了 `status='deleted'`）。 ✅

## 2. 規格文件依據
- **[PRD-001] §3.2 (用戶分級體系)**：
  > `註冊會員 (Level 0) - 完成 Email 驗證後取得`  
  > `預設功能：瀏覽探索列表、有限搜尋、每日聊天 5 則`
- **[PRD-001] §4.2.1 (驗收標準)**：
  > `And 用戶完成 Email 驗證後進入手機驗證`  
  > `And 完成手機驗證後成為「驗證會員 Lv1」`
- **[API-001] §2.1.1 (註冊回應)**：
  > `status: "active"`, `membership_level: 0`, `email_verified: false`
- **[API-001] §3.2.1 (搜尋用戶)**：
  > `未填欄位的使用者不會被排除：...避免把剛註冊、尚未完整填寫 profile 的用戶排除在外。`

## 3. 問題一：SMS 驗證頁「稍後驗證」無效

### 3.1 目前使用者流程
- 檔案與行號：`frontend/src/views/public/RegisterView.vue:620`
- 現象：註冊 Step 3 (Email OTP) 完成後，若有手機號碼會進入 Step 4 (SMS OTP)。

### 3.2 「稍後驗證」觸發點
- 檔案與行號：`frontend/src/views/public/RegisterView.vue:243`
- 程式碼節錄：
  ```typescript
  function skipSmsVerification() {
    if (smsTimer) { clearInterval(smsTimer); smsTimer = null }
    if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null }
    router.push('/app/explore')
  }
  ```

### 3.3 目前 auth / route guard 狀態
- **權限衝突**：`frontend/src/router/routes/app.ts:11` 定義 `explore` 路由如下：
  ```typescript
  {
    path: 'explore',
    name: 'explore',
    component: () => import('@/views/app/ExploreView.vue'),
    meta: { requiresAuth: true, minLevel: 1 }, // <--- 要求等級 1
  }
  ```
- **死循環**：`frontend/src/router/guards.ts:40` 的守衛會將等級不足的用戶導向 `/app/shop`，但 `shop` 路由同樣要求 `minLevel: 1`。
- **結果**：Level 0 用戶嘗試進入 `/app/explore` 會被導向 `/app/shop`，再次觸發守衛後可能導致導航取消或無限跳轉。

### 3.4 已驗證事實
1. 後端註冊後 `status` 即為 `active`，故通過了 `isVerified` 檢查。
2. 阻礙跳轉的是 `minLevel: 1` 限制。
3. 新註冊用戶（Level 0）目前被關在註冊流程中，無法進入任何 App 內部頁面。

### 3.5 推測根因
- **規格與實做不對齊**：PRD §3.2 明確說明 Level 0 用戶應可「瀏覽探索列表」，但路由定義卻將其阻擋在 Level 1 之外。

### 3.6 規格 vs 實作差異
- **規格**：Level 0 用戶可進入探索頁。
- **實作**：探索頁要求 Level 1。
- **差異**：實作過於嚴格，導致「稍後驗證」後無法跳轉到目標頁面。

### 3.7 解決方案選項
- **Option A：修正路由權限 (對齊 PRD)**
  - 需改檔案：`frontend/src/router/routes/app.ts`
  - 修改方向：將 `explore`、`profiles`、`shop` 等基礎路由的 `minLevel` 降為 `0`。
  - 優點：完全符合 PRD，用戶體驗連貫。
- **Option B：新增 Level 0 引導頁**
  - 需改檔案：`frontend/src/views/app/WelcomeView.vue` (需新建)
  - 優點：可強烈引導用戶去驗證，而非直接開始使用。
- **Option C：稍後驗證 → 回到登錄頁**
  - 修改方向：`skipSmsVerification` 改為 `router.push('/login')`。
  - 缺點：用戶剛註冊完又被踢出去，體驗極差。

### 3.8 推薦方案
**推薦 Option A**。應對齊 PRD，允許 Level 0 用戶進入探索頁進行「有限瀏覽」，並在探索頁上方顯示「請驗證手機以獲得更多功能」的提示。

## 4. 問題二：後台刪除使用者後 email/mobile 沒釋放

### 4.1 後台刪除流程
- 檔案與行號：`backend/app/Http/Controllers/Api/V1/AdminController.php:629`
- 程式碼節錄：
  ```php
  public function deleteMember(Request $request, int $id): JsonResponse
  {
      ...
      $user->delete(); // <--- 僅執行 Soft Delete
      return response()->json(['success' => true, 'message' => '會員已刪除']);
  }
  ```

### 4.2 註冊查重流程
- 檔案與行號：`backend/app/Http/Controllers/Api/V1/AuthController.php:73`
- 程式碼節錄：
  ```php
  if (User::where('email', $email)->where('status', '!=', 'deleted')->exists()) { ... }
  ```
- **事實**：雖然 `exists()` 排除 `status='deleted'`，但資料庫層級的 `UNIQUE INDEX` 並不知道 Laravel 的 `SoftDeletes` 邏輯。

### 4.3 DB unique constraint / soft delete 狀態
- 檔案：`backend/database/migrations/2026_04_08_000000_create_users_table.php:14`
- 程式碼節錄：`$table->string('email')->unique();`
- **衝突點**：Soft Delete 只是在 `deleted_at` 填值，該列仍存在，故 `email` 唯一索引會阻擋相同 Email 的新註冊。

### 4.4 黑名單現況
- **本機程式碼未找到既有系統級黑名單實作**。目前「禁止使用」是透過 `status = 'suspended'` 配合 `CheckSuspended` middleware 實作，並無獨立的 `blacklists` 資料表。

### 4.5 已驗證事實
1. 管理員刪除會員時，未對 Email / Phone 進行匿名化處理。
2. 資料庫 `UNIQUE` 索引導致重複 Email/Phone 無法寫入，即便原用戶已 `soft deleted`。
3. `GdprService` 已有現成的 `anonymizeUser` 方法可釋放索引，但後台刪除未調用它。

### 4.6 推測根因
- 後台刪除邏輯實做不完全，僅執行了軟刪除，未同步進行資料匿名化。

### 4.7 規格 vs 實作差異
- **規格**：除非黑名單，否則刪除後應釋放。
- **實作**：刪除後資料保留，持續佔用唯一索引。

### 4.8 解決方案選項
- **Option A：後台刪除改用 GdprService::anonymizeUser**
  - 需改檔案：`backend/app/Http/Controllers/Api/V1/AdminController.php`
  - 修改方向：將 `deleteMember` 內的 `$user->delete()` 改為調用 `GdprService::anonymizeUser($user)`。
  - 優點：代碼複用，且能立即釋放 `email` 與 `phone_hash`（GdprService 內已有處理）。
- **Option B：註冊時自動處理同名已刪除帳號**
  - 修改方向：若發現衝突的 user 已刪除，則將舊 user 強制 anonymize 後再建立新 user。
  - 風險：較複雜，且可能產生 race condition。
- **Option C：建立 blacklist 表**
  - 針對真正需要永久禁止的資料（如詐騙犯）建立 blacklist，一般刪除則一律 anonymize。

### 4.9 推薦方案
**推薦 Option A**。管理員執行「刪除會員」時，應預設執行資料匿名化（如 GdprService 所實做），這符合 GDPR 精神也能解決索引佔用問題。

## 5. 連動影響盤點
- **前端**：`RegisterView.vue` 可安全移除「稍後驗證」的無效疑慮；`guards.ts` 需確認 `minLevel` 調整後的安全性。
- **後端 API contract**：後台刪除 API 外部行為不變（回傳 success），但內部 DB 資料會變。
- **DB schema / migration**：無需改動，現有 `phone_hash` 已支援 NULL 索引釋放。
- **admin**：管理員刪除後將無法再看到該用戶的 Email（會變為 `deleted_xxx@removed.mimeet`）。
- **docs**：需更新 `API-002` 關於刪除會員的描述，註明會執行匿名化。

## 6. 未驗證項目
- **線上資料庫現況**：無法確定 production 環境是否已有大量「軟刪除但未匿名化」的髒資料，若有，可能需要一次性的 SQL 修正（Backfill anonymize）。
- **黑名單定義**：需向產品端確認，是否需要一個明確的「管理員手動列入黑名單」功能（獨立於刪除之外）。

## 7. 建議修改順序
1. **[Backend]** 修改 `AdminController::deleteMember` 調用 `GdprService::anonymizeUser`。
2. **[Frontend]** 修改 `frontend/src/router/routes/app.ts` 將基礎頁面 `minLevel` 設為 `0`。
3. **[Frontend]** 在 `ExploreView.vue` 增加對 Level 0 用戶的引導 Banner（Optional but recommended）。
4. **[Backend/Docs]** 更新 API 文件與執行測試驗證（刪除後重新註冊測試）。
