# 金流環境切換機制評估報告

**報告類型：** 專項技術稽核（Payment Mode Switch）  
**執行日期：** 2026-04-27  
**稽核者：** Claude Code  
**規格來源：** docs/DEV-011_金流與發票整合規格書.md v1.0  
**程式碼基準：** `git log --oneline -1` → `f4e597f docs(prompts): 新增 Audit-A Round 3 雙版本 prompt（codex + claudecode）`  
**涵蓋入口：** 訂閱購買 / 點數購買 / 男性信用卡驗證 / mock 端點

---

## 1. 執行摘要

金流環境切換機制已完成「舊 key 遷移到新 key」的主要重構工作，後端核心讀取路徑（`ECPayService::getEnvironment()`）現在統一讀取 `ecpay_environment`，後台 UI（`PaymentSettingsTab`）也正確寫入同一 key，**主要讀寫鏈路已對齊**。但仍存在四個需要關注的問題：

1. **舊 `ECPaySettingController::index()`** 仍讀取舊格式 key（`ecpay.mode`、`ecpay.payment.*`），使得「金流設定 GET」端點回傳空值，對後台操作者可能造成誤導（🟡 Medium）。
2. **`AdminController` 中的 `ecpay_is_sandbox` 遺留值**在 `getSettings()` 回傳的 settings 陣列裡有一個硬編碼 `'1'`，雖然不影響金流實際行為，但混入系統設定視圖會產生誤導（🔵 Low）。
3. **`SystemControlController::getAppMode()`** 回傳 `ecpay_sandbox: mode === 'testing'`，暗示 `app.mode` 與 `ecpay_environment` 應聯動；但切換 `app.mode` 時完全不更新 `ecpay_environment`，造成兩者可以不一致（🟡 Medium）。
4. **mock 端點守門條件**同時存在兩層守護（路由層 `config('app.env') !== 'production'` 和 Controller 層 `config('app.env') === 'production'`），而 staging 伺服器上 `config/app.php` 預設值是 `'production'`，**若 `.env` 沒有明確設 `APP_ENV=staging`，mock 端點在 staging 上也會被封鎖**（🟠 High）。

目前線上付款功能不因以上問題中斷，但第 4 點若 `.env` 設定有誤，將導致 sandbox 測試流程完全無法使用。建議本週內確認 staging `APP_ENV` 值，並清理舊 `ECPaySettingController` 回傳邏輯。

---

## 2. 現況盤點

### 2.1 系統 key 名稱演進歷史

金流環境 key 共經歷三個版本：

| 版本 | key 名稱 | 格式 | 狀態 |
|------|---------|------|------|
| 初版 | `ecpay_is_sandbox` | `1` / `0` | 遺留（AdminController 有一筆硬編碼） |
| 中版 | `ecpay.mode` | `sandbox` / `production` | DEPRECATED（migration 已標記並於 250000 migration 刪除）|
| 現版 | `ecpay_environment` | `sandbox` / `production` | 現行（ECPayService、PaymentSettingsTab、PaymentSettingsController 均使用）|

### 2.2 各業務入口的環境讀取點

| 業務入口 | 入口 Controller | 核心 Service | 環境判斷依據 | 真實行為 | 與規格是否相符 |
|---------|----------------|-------------|------------|---------|-------------|
| 訂閱購買 | `SubscriptionController::createOrder()` | `UnifiedPaymentService::initiate()` → `ECPayService::getEnvironment()` | 讀 `system_settings.ecpay_environment` | 正確依環境選 AIO URL / 憑證 | ✅ 相符 |
| 點數購買 | `PointController::purchase()` | 同上 | 同上 | 同上 | ✅ 相符 |
| 男性 CC 驗證 | `CreditCardVerificationController::initiate()` | 同上 | 同上 | 同上 | ✅ 相符 |
| mock 訂閱付款 | `PaymentCallbackController::mock()` | `PaymentService::handleMockPayment()` | `config('app.env') === 'production'` 決定是否 abort | sandbox 模擬付款（詳見 Issue #4）| ⚠️ 有條件相符 |
| mock 點數付款 | `PaymentCallbackController::pointMock()` | 內部 `processPointPayment()` | 同上 | 同上 | ⚠️ 有條件相符 |

#### 訂閱購買流程的程式碼路徑

```php
// SubscriptionController.php:91-109
$orderNo = $this->unifiedPayment->generateOrderNo('subscription');
$order   = $this->paymentService->createOrderRecord($user, $planId, $orderNo, $method);
$result  = $this->unifiedPayment->initiate('subscription', $user, [...]);
// 回傳 aio_url: $this->ecpay->getAioUrl()

// ECPayService.php:84-87
public function getAioUrl(): string
{
    return config('ecpay.urls.' . $this->getEnvironment() . '.aio');
}

// ECPayService.php:36-40
public function getEnvironment(): string
{
    $env = SystemSetting::get('ecpay_environment', 'sandbox');
    return in_array($env, ['sandbox', 'production']) ? $env : 'sandbox';
}
```

#### mock 端點的守門條件（雙層）

```php
// routes/api.php（路由層守門）
if (config('app.env') !== 'production') {
    Route::get('/mock',       [PaymentCallbackController::class, 'mock']);
    Route::get('/point-mock', [PaymentCallbackController::class, 'pointMock']);
}

// PaymentCallbackController.php:73-74（Controller 層守門）
public function mock(Request $request): mixed
{
    if (config('app.env') === 'production') {
        abort(404);
    }
    ...
}

// PaymentCallbackController.php:139-140（相同模式）
public function pointMock(Request $request): mixed
{
    if (config('app.env') === 'production') {
        abort(404);
    }
    ...
}
```

**問題：** `config/app.php:5` 的 `APP_ENV` 預設值為 `'production'`：
```php
'env' => env('APP_ENV', 'production'),
```
若 staging `.env` 未明確設定 `APP_ENV=staging`（或 `local`），則路由層守門條件 `config('app.env') !== 'production'` 判斷為 `false`，**mock 路由根本不會被註冊**，前端嘗試呼叫 mock 端點會得到 404。

### 2.3 Grep 結果完整彙整

#### grep 1：`ecpay_is_sandbox` 搜尋結果

```
/home/chuck/projects/mimeet/backend/app/Http/Controllers/Api/V1/AdminController.php:897:
    'ecpay_is_sandbox'               => '1',
/home/chuck/projects/mimeet/backend/database/seeders/SystemSettingsSeeder.php:17:
    // 注意：ecpay_is_sandbox / ecpay_merchant_id 已由 PaymentSettingsTab 管理（新 key 格式），此處保留向下相容
```

**分析：** `AdminController.php:897` 的 `'ecpay_is_sandbox' => '1'` 是寫在 `getSettings()` 函式的 `$defaults` 陣列內，該值被 `SystemSetting::get($k, $v)` 讀取後回傳。若 DB 中沒有 `ecpay_is_sandbox` key（migration 250000 並未新增或刪除此 key），則預設值 `'1'` 會被回傳到後台前端。此 key **從未被 ECPayService 或 PaymentSettingsController 讀取**，不影響金流實際行為，但混在系統設定視圖裡可能造成混淆。

#### grep 2：`ecpay.mode` 搜尋結果

```
/home/chuck/projects/mimeet/backend/app/Services/ECPayService.php:34:
 * 從 ecpay_environment 讀取（Step 6 後統一新 key，舊 ecpay.mode 已透過 migration 遷移）
/home/chuck/projects/mimeet/backend/app/Http/Controllers/Api/V1/Admin/ECPaySettingController.php:18: 'ecpay.mode',
/home/chuck/projects/mimeet/backend/app/Http/Controllers/Api/V1/Admin/ECPaySettingController.php:76: 'ecpay.mode',
/home/chuck/projects/mimeet/backend/database/migrations/2026_04_26_230000_add_ecpay_unified_settings.php:30:
    $oldMode = DB::table('system_settings')->where('key_name', 'ecpay.mode')->value('value') ?? 'sandbox';
/home/chuck/projects/mimeet/backend/database/migrations/2026_04_26_230000_add_ecpay_unified_settings.php:114-116:
    ->whereIn('key_name', ['ecpay.mode', ...])
    ->update(['description' => '[DEPRECATED] 已遷移至 ecpay_* 新格式 key，請勿再使用']);
/home/chuck/projects/mimeet/backend/database/migrations/2026_04_26_250000_migrate_and_cleanup_ecpay_dot_notation_keys.php:21:
    'ecpay.mode', // 在 250000 migration 中被刪除
/home/chuck/projects/mimeet/backend/database/migrations/2026_04_26_410_000001_add_reconciliation_fields_to_orders.php:23:
    ['key_name' => 'ecpay.mode', 'value' => 'sandbox', ...] // 舊 migration，建立初始值
```

**分析：** `ECPaySettingController::index()` 仍在 `$keys` 陣列（line 18）中列出 `ecpay.mode`，查詢 DB 後回傳。但該 key 已被 migration 250000 刪除，因此查詢結果為空，回傳值為空字串 `''`。這使得 `GET /admin/settings/ecpay` 永遠回傳空的 `mode` 欄位，管理員若使用舊端點查看設定，會誤以為 mode 未設定。

#### grep 3：`ecpay_environment` 搜尋結果

```
/home/chuck/projects/mimeet/backend/app/Services/ECPayService.php:38:
    $env = SystemSetting::get('ecpay_environment', 'sandbox');
/home/chuck/projects/mimeet/backend/app/Http/Controllers/Api/V1/Admin/PaymentSettingsController.php:32:
    'ecpay_environment',
/home/chuck/projects/mimeet/backend/app/Http/Controllers/Api/V1/Admin/PaymentSettingsController.php:71:
    'ecpay_environment' => 'sometimes|string|in:sandbox,production',
/home/chuck/projects/mimeet/backend/database/migrations/2026_04_26_230000_add_ecpay_unified_settings.php:47:
    'key_name' => 'ecpay_environment',
/home/chuck/projects/mimeet/backend/config/ecpay.php:6:
 * 憑證從 system_settings 表動態讀取（key: ecpay_environment, ecpay_sandbox_merchant_id, ...)
/home/chuck/projects/mimeet/admin/src/pages/settings/tabs/PaymentSettingsTab.tsx:9:
    ecpay_environment: 'sandbox' | 'production'
/home/chuck/projects/mimeet/admin/src/pages/settings/tabs/PaymentSettingsTab.tsx:35:
    const curEnv = (d.ecpay_environment ?? 'sandbox') as 'sandbox' | 'production'
/home/chuck/projects/mimeet/admin/src/pages/settings/tabs/PaymentSettingsTab.tsx:58:
    ecpay_environment: env,
```

**分析：** 現行正確讀寫鏈路已對齊。ECPayService（讀）、PaymentSettingsController（讀/寫）、PaymentSettingsTab.tsx（顯示/寫）均使用 `ecpay_environment`。

#### grep 4：`isSandbox / is_sandbox / sandbox.*mode` 搜尋結果

```
/home/chuck/projects/mimeet/backend/app/Http/Controllers/Api/V1/Admin/SystemControlController.php:102:
    'ecpay_sandbox' => $mode === 'testing',
/home/chuck/projects/mimeet/backend/app/Http/Controllers/Api/V1/AdminController.php:897:
    'ecpay_is_sandbox' => '1',
/home/chuck/projects/mimeet/backend/app/Services/ECPayService.php:42-44:
    public function isSandbox(): bool
    {
        return $this->getEnvironment() === 'sandbox';
    }
/home/chuck/projects/mimeet/backend/app/Services/ECPayService.php:339:
    $baseUrl = $this->isSandbox()
        ? 'https://einvoice-stage.ecpay.com.tw/...'
        : 'https://einvoice.ecpay.com.tw/...';
```

**分析：** `SystemControlController::getAppMode()` (line 102) 回傳 `ecpay_sandbox: mode === 'testing'`，這是一個「計算值」（非 DB 讀取），顯示前台 AppModeTab 認為 `app.mode=testing` 就等於 `ecpay_sandbox=true`。但 `ECPayService::isSandbox()` 實際上讀的是 `ecpay_environment`，兩者未聯動（詳見 Issue #2）。

#### grep 5：`config('app.env')` 搜尋結果

```
/home/chuck/projects/mimeet/backend/app/Http/Controllers/Api/V1/PaymentCallbackController.php:73:
    if (config('app.env') === 'production') {
/home/chuck/projects/mimeet/backend/app/Http/Controllers/Api/V1/PaymentCallbackController.php:139:
    if (config('app.env') === 'production') {
```

**分析：** 僅 PaymentCallbackController 使用 `config('app.env')` 控制 mock 功能。整個 ECPay 金流的 sandbox/production 切換都透過 DB key `ecpay_environment` 管理，此處是唯一例外，使用了不同的判斷依據。

#### grep 6：mock 端點搜尋結果

```
routes/api.php:
    if (config('app.env') !== 'production') {
        Route::get('/mock',       [PaymentCallbackController::class, 'mock']);
        Route::get('/point-mock', [PaymentCallbackController::class, 'pointMock']);
    }
```

**分析：** 路由層已加上 `config('app.env') !== 'production'` 守門，這是比 Controller 層更前置的防護。但核心問題是：**staging 環境的 `APP_ENV` 應該是什麼值？** 若 `.env` 設為 `APP_ENV=production`（或未設定，套用 config/app.php 的預設值 `production`），mock 路由根本不會被掛載。

#### grep 7：`ecpay_environment / ecpay.mode / ecpay_is_sandbox`（後台 Admin UI / 後台 Admin Controllers）

```
admin/src/pages/settings/tabs/PaymentSettingsTab.tsx:9: ecpay_environment: 'sandbox' | 'production'
admin/src/pages/settings/tabs/PaymentSettingsTab.tsx:35: const curEnv = (d.ecpay_environment ?? 'sandbox')
admin/src/pages/settings/tabs/PaymentSettingsTab.tsx:58: ecpay_environment: env,
backend/app/Http/Controllers/Api/V1/Admin/ECPaySettingController.php:18: 'ecpay.mode',  (舊端點)
backend/app/Http/Controllers/Api/V1/Admin/ECPaySettingController.php:76: 'ecpay.mode',  (舊端點)
backend/app/Http/Controllers/Api/V1/Admin/PaymentSettingsController.php:32: 'ecpay_environment',  (新端點)
backend/app/Http/Controllers/Api/V1/Admin/PaymentSettingsController.php:71: 'ecpay_environment'  (新端點)
```

**分析：** 現行後台 UI 使用 `PaymentSettingsTab.tsx` → `PUT /admin/settings/payment` → `PaymentSettingsController`，正確讀寫 `ecpay_environment`。舊的 `ECPaySettingController` 仍存在但 `update()` 方法已加上攔截，回傳 400 DEPRECATED_KEY_FORMAT，無法再寫入舊 key（line 62-66）。

#### grep 8：`ecpay / payment_mode / app_mode`（SystemSettingsSeeder）

```
/home/chuck/projects/mimeet/backend/database/seeders/SystemSettingsSeeder.php:17:
    // 注意：ecpay_is_sandbox / ecpay_merchant_id 已由 PaymentSettingsTab 管理（新 key 格式），此處保留向下相容
/home/chuck/projects/mimeet/backend/database/seeders/SystemSettingsSeeder.php:18:
    ['key_name' => 'app_mode', 'value' => 'normal', 'value_type' => 'string', ...]
/home/chuck/projects/mimeet/backend/database/seeders/SystemSettingsSeeder.php:71-76:
    // 發票 key（新格式）
    ecpay_invoice_merchant_id / ecpay_invoice_hash_key / ecpay_invoice_hash_iv / ...
```

**分析：** SystemSettingsSeeder 播種 `app_mode=normal`，但 SystemControlController 讀取的是 `app.mode`（含點記號），Seeder 設定的是 `app_mode`（不含點）。這造成 seeder 播種的值永遠不被 SystemControlController 讀取。此外，Seeder **沒有播種 `ecpay_environment` key**，該 key 由 migration 230000 建立。

#### grep 9：UnifiedPayment 引用

```
backend/app/Http/Controllers/Api/V1/SubscriptionController.php:91,105: unifiedPayment
backend/app/Http/Controllers/Api/V1/PointController.php:69,82: unifiedPayment
backend/app/Http/Controllers/Api/V1/CreditCardVerificationController.php:57,67,91: unifiedPayment
backend/app/Http/Controllers/Api/V1/UnifiedPaymentController.php: 接受 /payments/callback
```

**分析：** 三個業務入口都已統一走 `UnifiedPaymentService`，環境判斷由 `ECPayService::getEnvironment()` 集中處理，架構是清晰的。

#### grep 10：ECPAY_MERCHANT / ECPAY_HASH / sandbox config

```
/home/chuck/projects/mimeet/backend/config/services.php:5-8:
    'merchant_id' => env('ECPAY_MERCHANT_ID', '3002607'),
    'hash_key' => env('ECPAY_HASH_KEY', 'pwFHCqoQZGmho4w6'),
    'hash_iv' => env('ECPAY_HASH_IV', 'EkRm7iFT261dpevs'),
    'is_sandbox' => (bool) env('ECPAY_IS_SANDBOX', true),
/home/chuck/projects/mimeet/backend/app/Http/Controllers/Api/V1/Admin/PaymentSettingsController.php:25-26:
    'ecpay_sandbox_hash_key', 'ecpay_sandbox_hash_iv',
/home/chuck/projects/mimeet/backend/app/Http/Controllers/Api/V1/Admin/PaymentSettingsController.php:33-35:
    'ecpay_sandbox_merchant_id', 'ecpay_sandbox_hash_key', ...
```

**分析：** `config/services.php` 仍保留舊格式 `.env` 讀取（`ECPAY_MERCHANT_ID` 等），但 `ECPayService` 完全沒有讀取 `config('services.ecpay.*')`，只讀取 `system_settings` 和 `config('ecpay.*')`。因此 `config/services.php` 的 ecpay 區塊是**死 code**，不影響金流行為，但佔用命名空間，可能引起混淆。

---

## 3. 問題清單

### Issue #1：`ECPaySettingController::index()` 讀取已刪除的舊 key（🟡 Medium）

**規格位置：** DEV-011 §4（金鑰管理）
```
優先順序：system_settings DB → config/services.php → .env
| 環境模式 | `ecpay.mode`（sandbox/production）|   ← 規格書未更新，仍使用舊 key 名稱
```

**程式碼位置：** `backend/app/Http/Controllers/Api/V1/Admin/ECPaySettingController.php:17-27`
```php
$keys = [
    'ecpay.mode',                       // ← 已被 migration 250000 刪除
    'ecpay.payment.merchant_id',         // ← 同上
    'ecpay.payment.hash_key',            // ← 同上
    'ecpay.payment.hash_iv',             // ← 同上
    'ecpay.invoice.merchant_id',         // ← 同上
    'ecpay.invoice.hash_key',            // ← 同上
    'ecpay.invoice.hash_iv',             // ← 同上
    'ecpay.invoice.enabled',             // ← 同上
    'ecpay.invoice.donation_love_code',  // ← 同上
];
```

**差異說明：**  
`GET /api/v1/admin/settings/ecpay` 端點讀取的 9 個 key 全部已被 migration `2026_04_26_250000_migrate_and_cleanup_ecpay_dot_notation_keys.php` 刪除或遷移。因此此端點永遠回傳空值。雖然後台 UI 已改用 `PaymentSettingsTab` → `GET /admin/settings/payment`，但舊端點仍在路由中（`routes/api.php:398`），若被呼叫會返回誤導性空白資料。

**等級：** 🟡 Medium

**潛在影響：**
- 開發者若測試此舊端點，會誤以為 ECPay 設定遺失
- 若有外部腳本或舊前端仍呼叫此端點，會得到空設定
- `update()` 方法已封鎖寫入（回傳 400），不會造成資料損壞

**建議方案：**
- Option A：更新 `ECPaySettingController::index()` 改讀新 key（`ecpay_environment` 等 8 個）
- Option B：移除整個 `ECPaySettingController`，只保留 `PaymentSettingsController`
- **推薦 B**：舊端點已廢棄，移除比修改更乾淨；但需確認無任何前端仍使用此端點

---

### Issue #2：`app.mode`（AppModeTab）與 `ecpay_environment` 切換未連動（🟡 Medium）

**規格位置：** DEV-011 §4
```
| 環境模式 | `ecpay.mode`（sandbox/production）|
後台 UI：/admin/settings → 金流與發票 Tab → 即時切換環境
```

**程式碼位置 1：** `backend/app/Http/Controllers/Api/V1/Admin/SystemControlController.php:98-106`
```php
public function getAppMode(): JsonResponse
{
    $mode = SystemSetting::get('app.mode', 'testing');
    return response()->json(['success' => true, 'data' => [
        'mode' => $mode,
        'mail_enabled' => $mode === 'production',
        'sms_enabled' => $mode === 'production' && ...,
        'ecpay_sandbox' => $mode === 'testing',   // ← 暗示聯動
        'description' => $mode === 'testing'
            ? '測試模式：Email/SMS 只寫 Log，綠界使用 Sandbox'  // ← 誤導
            : '正式模式：Email/SMS 實際發送，綠界使用正式環境',  // ← 誤導
    ]]);
}
```

**程式碼位置 2：** `backend/app/Http/Controllers/Api/V1/Admin/SystemControlController.php:79-82`
```php
$oldMode = SystemSetting::get('app.mode', 'testing');
SystemSetting::set('app.mode', $request->mode, $admin->id);
// ← 此處完全沒有更新 ecpay_environment
```

**程式碼位置 3：** `admin/src/pages/settings/tabs/AppModeTab.tsx:71`
```tsx
<Tag color={isTesting ? 'orange' : 'green'}>
    綠界 {isTesting ? '● Sandbox' : '● 正式'}
</Tag>
```

**差異說明：**  
AppModeTab 顯示「綠界 ● Sandbox」（當 mode=testing）暗示切換 app.mode 會同步影響金流環境；後端 `getAppMode()` 也回傳 `ecpay_sandbox: mode === 'testing'` 強化此誤解。但實際上切換 `app.mode` 時，後端 `updateAppMode()` 完全不觸碰 `ecpay_environment`，而 `ECPayService::getEnvironment()` 只讀取 `ecpay_environment`，兩者**完全獨立**。  

結果：管理員在 AppModeTab 切換到「正式模式」後，看到 UI 顯示「綠界 ● 正式」，但 `ecpay_environment` 仍為 `sandbox`，實際金流仍走 sandbox。

**等級：** 🟡 Medium

**潛在影響：**
- 管理員以為切換 app.mode 就完成了金流環境切換，但實際上沒有
- 正式上線時若只切換 app.mode、忘記到 PaymentSettingsTab 更新 `ecpay_environment`，將以 sandbox 憑證收取真實款項，導致付款失敗

**建議方案：**
- Option A：切換 `app.mode → production` 時，自動將 `ecpay_environment` 設為 `production`（後端連動）
- Option B：AppModeTab 移除「綠界」狀態標籤，避免誤導；`getAppMode()` 不回傳 `ecpay_sandbox` 欄位
- Option C：AppModeTab 新增警告提示：「切換正式模式後，請到『金流與發票』Tab 確認綠界環境已切換」
- **推薦 C**（短期）、**A**（長期）：C 成本最低，可立即澄清使用者預期；A 需要設計如何在不覆蓋已設定憑證的前提下安全連動

---

### Issue #3：`AdminController::getSettings()` 中 `ecpay_is_sandbox` 遺留值（🔵 Low）

**規格位置：** DEV-011 §4（無直接對應，此為廢棄 key）

**程式碼位置：** `backend/app/Http/Controllers/Api/V1/AdminController.php:894-898`
```php
$defaults = [
    // ...其他設定...
    'max_photos_per_user'            => '6',
    'image_moderation_enabled'       => '0',
    'ecpay_is_sandbox'               => '1',   // ← 已無意義的遺留值
    'trial_plan_price'               => '49',
    'trial_plan_days'                => '3',
];
```

**差異說明：**  
`ecpay_is_sandbox` 是最早的金流環境 key，現在已被 `ecpay_environment` 取代。`ECPayService` 完全不讀取此 key，但 `getSettings()` 仍在預設值陣列中帶著它，並透過 `SystemSetting::get('ecpay_is_sandbox', '1')` 查詢後回傳，結果永遠是預設值 `'1'`（DB 中應沒有此 key）。

**等級：** 🔵 Low

**潛在影響：**
- 後台前端若有讀取 `settings.ecpay_is_sandbox`，會拿到永遠為 `'1'` 的值，但不影響金流
- 增加 API response 的雜訊，稍微污染系統設定視圖

**建議方案：**
- 從 `$defaults` 陣列移除 `ecpay_is_sandbox` 一行
- 同時確認 `updateSettings()` 的 validation rules（line 917-946）中沒有包含此 key，確認無法透過後台更新此 key（目前確實沒有）

---

### Issue #4：mock 端點守門使用 `config('app.env')` 而非 `ecpay_environment`，且 staging 可能被誤鎖（🟠 High）

**規格位置：** DEV-011 §1（架構總覽）
```
PaymentService::createOrder()
  → ECPayService::getPaymentUrl()
      ├─ sandbox → /mock（開發用）
      └─ production → AioCheckOut 表單 → cache → /checkout/{token}
```

**程式碼位置 1：** `backend/routes/api.php`（路由層守門）
```php
// Mock 端點（sandbox 測試用，兩週後一起砍）
if (config('app.env') !== 'production') {
    Route::get('/mock',       [PaymentCallbackController::class, 'mock']);
    Route::get('/point-mock', [PaymentCallbackController::class, 'pointMock']);
}
```

**程式碼位置 2：** `backend/app/Http/Controllers/Api/V1/PaymentCallbackController.php:71-75`
```php
public function mock(Request $request): mixed
{
    if (config('app.env') === 'production') {
        abort(404);
    }
    ...
}
```

**程式碼位置 3：** `backend/config/app.php:5`
```php
'env' => env('APP_ENV', 'production'),
```

**差異說明：**  
mock 端點守門用 `config('app.env')` 判斷，而非 `ecpay_environment`（DB 設定）。這造成以下問題：

1. **語意不一致：** 金流環境（sandbox/production）由 `ecpay_environment` 控制，但 mock 端點的開關用 `APP_ENV` 環境變數控制。這兩個概念在不同維度，可以出現「`ecpay_environment=sandbox` 但 `APP_ENV=production`（mock 被鎖）」的矛盾狀態。

2. **Staging 風險：** `config/app.php` 的 `APP_ENV` 預設值是 `'production'`。若 staging `.env` 的 `APP_ENV` 沒有明確設定為非 `production` 值（如 `staging`、`local`、`testing`），**mock 路由在 staging 上不會被掛載**，前端 sandbox 測試流程全面失效。

3. **Comment 自我矛盾：** 路由中的 comment「兩週後一起砍」暗示這是臨時設計，但到目前為止仍是 sandbox 測試的唯一通道。

**等級：** 🟠 High

**潛在影響：**
- 若 staging `APP_ENV=production`，`ecpay_environment=sandbox` 的情況下，無法使用 mock 測試付款流程，整個 sandbox 金流測試流程中斷
- 點數購買和訂閱的 sandbox 測試均依賴此端點
- 若管理員切換 `ecpay_environment=production` 但 `APP_ENV` 仍非 production，mock 端點仍存在，可能讓開發者誤以為真實付款通道也在運作

**建議方案：**
- Option A（最小修改）：mock 端點守門改為讀 `ecpay_environment`：`if ($this->ecpay->isSandbox())` 替代 `config('app.env')`
- Option B：保留 `config('app.env')` 守門，但確保 staging `.env` 明確設 `APP_ENV=staging`，並在部署 SOP 文件中強調此要求
- Option C：雙重守門：需要 `ecpay_environment=sandbox` **且** `APP_ENV != production` 才開放
- **推薦 A**：語意最一致，與金流環境切換邏輯同一維度，不依賴 server 環境變數配置

---

### Issue #5：`config/services.php` 的 `ecpay` 區塊是死 code（🔵 Low）

**規格位置：** DEV-011 §4（金鑰管理，優先順序）

**程式碼位置：** `backend/config/services.php:4-9`
```php
'ecpay' => [
    'merchant_id' => env('ECPAY_MERCHANT_ID', '3002607'),
    'hash_key' => env('ECPAY_HASH_KEY', 'pwFHCqoQZGmho4w6'),
    'hash_iv' => env('ECPAY_HASH_IV', 'EkRm7iFT261dpevs'),
    'is_sandbox' => (bool) env('ECPAY_IS_SANDBOX', true),
],
```

**差異說明：**  
DEV-011 §4 規格說明優先順序為「system_settings DB → config/services.php → .env」，但目前 `ECPayService` 在讀取 merchant_id / hash_key / hash_iv 時，fallback 是 `config('ecpay.sandbox_fallback.*')`（config/ecpay.php），完全不讀取 `config('services.ecpay.*')`。`services.php` 的 ecpay 區塊從來不被讀取，規格書的優先順序描述已過時。

**等級：** 🔵 Low

**潛在影響：**
- `ECPAY_MERCHANT_ID` / `ECPAY_HASH_KEY` / `ECPAY_HASH_IV` / `ECPAY_IS_SANDBOX` 等環境變數無效，設了也不會被使用
- 若運維人員依規格書設定這些 `.env` 變數，會誤以為金流已設定完成，但實際上沒有生效

**建議方案：**
- 更新 DEV-011 §4 優先順序說明，移除 `config/services.php` 這層
- 或移除 `config/services.php` 中的 ecpay 區塊（需確認無其他程式讀取）

---

### Issue #6：`SystemSettingsSeeder` 播種 `app_mode` 但 SystemControlController 讀取 `app.mode`（🔵 Low）

**規格位置：** 無直接規格對應（後台系統設定）

**程式碼位置 1：** `backend/database/seeders/SystemSettingsSeeder.php:18`
```php
['key_name' => 'app_mode', 'value' => 'normal', 'value_type' => 'string', 'description' => '系統運作模式'],
```

**程式碼位置 2：** `backend/app/Http/Controllers/Api/V1/Admin/SystemControlController.php:26`
```php
$mode = SystemSetting::get('app.mode', 'testing');
```

**差異說明：**  
Seeder 播種 `key_name = 'app_mode'`（不含點），但 SystemControlController 讀取 `'app.mode'`（含點）。`SystemSetting::get('app.mode', 'testing')` 在 DB 找不到此 key 時會回傳預設值 `'testing'`。這意味著 Seeder 的 `app_mode` 資料從來不被讀取，系統永遠是初始的 `'testing'` 模式（除非手動設定過 `app.mode`）。

**等級：** 🔵 Low

**潛在影響：**
- `migrate:fresh` 後系統設定顯示為 testing 模式，與 Seeder 設定的 `app_mode=normal` 不一致
- 輕微混淆，但因 testing 是安全預設值，不影響生產安全

**建議方案：**
- Seeder 改為播種 `key_name = 'app.mode'`，或確認 SystemControlController 讀取鍵改為 `'app_mode'`（二擇一）
- **推薦**：統一使用不含點的格式（`app_mode`），點記號格式與其他系統設定 key 風格不一致

---

### Issue #7：是否有切換動作的 Audit Log（✅ 已實作，但部分入口遺漏）

**規格位置：** 無明確規格要求

**程式碼位置：** `backend/app/Http/Controllers/Api/V1/Admin/PaymentSettingsController.php:103-115`
```php
if (!empty($changes)) {
    AdminOperationLog::create([
        'admin_id'        => $admin?->id,
        'action'          => 'payment_settings_updated',
        'resource_type'   => 'system_setting',
        'description'     => '金流設定更新：' . implode(' | ', $changes),
        ...
    ]);
}
```

**分析：** `PUT /admin/settings/payment`（`PaymentSettingsController`）有完整的 `AdminOperationLog` 記錄，包含哪些 key 被改動（加密 key 顯示 `****`）。但 `PATCH /admin/settings/app-mode`（`SystemControlController::updateAppMode()`）只寫 Laravel Log（`Log::info()`），沒有寫入 `AdminOperationLog`，audit trail 不完整。

**等級：** 🔵 Low（`PaymentSettingsTab` 金流環境切換有記錄，App Mode 切換沒有）

---

## 4. 影響分析

| Issue | 使用者影響 | 資料影響 | 可逆性 | 發生機率 |
|-------|---------|---------|-------|---------|
| #1 舊 ECPaySettingController 讀空值 | 管理員查看舊設定頁看到空值（誤導） | 無資料損壞 | 容易修復 | 高（每次訪問舊端點） |
| #2 AppModeTab 與 ecpay_environment 未連動 | 管理員以為切換 app.mode 就完成了金流切換，但實際沒有 | 可能導致用 sandbox 憑證嘗試真實收款 | 需手動到 PaymentSettingsTab 修正 | 中（操作人員認知差異） |
| #3 ecpay_is_sandbox 遺留值 | 後台 API 回傳誤導性欄位 | 無 | 移除一行即可 | 低（幾乎不會有人讀這個欄位） |
| #4 mock 端點可能被 APP_ENV 鎖住 | sandbox 測試流程全面中斷 | 無資料損壞，但測試無法進行 | 需修改守門邏輯或 .env | 中高（取決於 staging APP_ENV 設定） |
| #5 services.php 死 code | 運維人員設 ECPAY_MERCHANT_ID 等 .env 變數但無效 | 無 | 清理即可 | 低 |
| #6 Seeder key 名稱不一致 | migrate:fresh 後 app.mode 回到 testing 預設值 | 無 | 修改 Seeder | 低 |
| #7 App Mode 切換無 Audit Log | 無使用者影響 | 稽核紀錄不完整 | 補 AdminOperationLog 即可 | 中（每次切換都缺記錄） |

---

## 5. 解決方案

### 方案 A：最小修改（統一守門 key、清理遺留 code）

**核心想法：** 在不改變架構的前提下，修正語意衝突和遺留殘留，讓現有讀寫鏈路更清晰。

**改動範圍：**
| 檔案 | 改動類型 | 預估行數 |
|------|---------|---------|
| `PaymentCallbackController.php` | mock 守門改讀 `ECPayService::isSandbox()` | ~4 行 |
| `AdminController.php` | 移除 `ecpay_is_sandbox` 預設值 | ~1 行 |
| `ECPaySettingController.php` | 更新 `$keys` 改讀新格式，或直接移除此 Controller | ~50 行 |
| `SystemControlController.php` | 移除 `ecpay_sandbox` 欄位，更新 description | ~5 行 |
| `AppModeTab.tsx` | 移除「綠界」狀態標籤，或加上警告提示 | ~10 行 |
| `SystemSettingsSeeder.php` | `app_mode` → `app.mode` | ~1 行 |
| `config/services.php` | 移除或保留（加 comment 說明已無效） | ~5 行 |

**資料遷移需求：** 無（DB 資料不需變動）

**回滾難度：** 極低（純程式碼改動，無 migration）

**優點：**
1. 改動範圍小，風險極低
2. 不需要 DB migration，部署簡單
3. 立即消除誤導性 UI（AppModeTab 的「綠界 ●」標籤）

**缺點：**
1. 未解決 AppModeTab 與 ecpay_environment 的語意連動問題
2. 管理員仍需手動到兩個地方分別切換（app.mode 和 ecpay_environment）
3. 只是清理而非結構性改善

**適合場景：** 時間緊迫、需要快速止血，不想動大架構的情況。

---

### 方案 B：AppModeTab 與 ECPay 環境連動重構

**核心想法：** 讓 `app.mode` 切換自動同步 `ecpay_environment`，同時清理所有遺留 code。

**改動範圍：**
| 檔案 | 改動類型 | 預估行數 |
|------|---------|---------|
| `SystemControlController.php::updateAppMode()` | 切換 `app.mode=production` 時同步設 `ecpay_environment=production`（反之亦然）| ~10 行 |
| `SystemControlController.php::getAppMode()` | `ecpay_sandbox` 改為讀 DB 實際值，不計算 | ~3 行 |
| `AppModeTab.tsx` | 更新 UI：顯示實際 `ecpay_environment` 值，加上「自動同步說明」| ~20 行 |
| （同方案 A 的清理項目）| | 同上 |

**資料遷移需求：** 無

**回滾難度：** 低（純邏輯，無 migration）

**優點：**
1. 管理員操作直覺：切換 app.mode 一步搞定 email/SMS/ECPay
2. 消除兩個獨立設定之間的語意衝突
3. AppModeTab 顯示資訊準確可信

**缺點：**
1. 連動可能造成意外覆蓋：若管理員已在 PaymentSettingsTab 設定好正式憑證但保持 sandbox 模式，切換 app.mode 到 production 時會強制改變 ecpay_environment
2. 反向同步也需要處理（production → testing 時是否同步回 sandbox？）
3. PaymentSettingsTab 和 AppModeTab 功能重疊，UI 設計需要協調
4. 連動邏輯需要仔細測試邊界情況

**適合場景：** 有充足測試時間，且業務流程希望「一個開關控制所有環境切換」。

---

### 方案 C：Strategy Pattern 重構（長期架構改善）

**核心想法：** 引入 `PaymentGatewayInterface`，sandbox / production 各是一個 Driver；`UnifiedPaymentService` 依 `ecpay_environment` 注入正確 Driver，不再有散落的 if-else 判斷。

**改動範圍：**
| 新增/修改檔案 | 改動類型 | 預估行數 |
|-------------|---------|---------|
| `PaymentGatewayInterface.php`（新） | 定義 `getAioUrl()`, `getMerchantId()`, `buildAioParams()` 等介面 | ~30 行 |
| `SandboxPaymentGateway.php`（新） | 實作沙箱邏輯，getAioUrl 回 mock URL | ~80 行 |
| `ProductionPaymentGateway.php`（新） | 實作正式邏輯 | ~80 行 |
| `ECPayService.php`（修改） | 改為根據環境委派到對應 Driver | ~50 行修改 |
| `UnifiedPaymentService.php`（修改） | 調整建構子注入 | ~10 行 |
| `AppServiceProvider.php`（修改） | 根據 `ecpay_environment` 綁定正確 Driver | ~20 行 |
| （同方案 A + B 的清理項目） | | 同上 |

**資料遷移需求：** 無

**回滾難度：** 中（需要整個重構完整），但可分步進行

**優點：**
1. 架構清晰，每個環境職責明確分離
2. 便於新增其他金流（Stripe / LinePay）
3. 測試更容易（可注入 Mock Gateway）
4. 消除所有 if-else 環境判斷散落問題

**缺點：**
1. 改動範圍大，需要完整回歸測試
2. 短期內不會有明顯用戶體驗改善
3. 需要協調 AppServiceProvider 的 runtime 注入（Laravel DI 不支援 runtime 切換 binding，需要 factory 或 resolver 模式）
4. 現有三個入口都依賴 `ECPayService`，需要仔細追蹤所有注入點

**適合場景：** 計劃新增多金流服務、有充足重構時間的版本，非緊急情況。

---

## 6. 推薦方案與理由

### 推薦：分階段執行（方案 A 先行，再考慮 B）

**立即執行方案 A（本週）：**

1. **修正 mock 端點守門**（Issue #4，🟠 High）：  
   `PaymentCallbackController.php` 的兩個 `if (config('app.env') === 'production')` 改為 `if (!app(ECPayService::class)->isSandbox())`。同時確認 staging `.env` 的 `APP_ENV` 值，若為 `production` 也需修正。

2. **清理 ECPaySettingController**（Issue #1，🟡 Medium）：  
   移除 `ECPaySettingController.php` 的 `index()` 方法中的舊 key 列表，或直接在路由中標記為 deprecated（回傳 410 Gone），避免繼續回傳誤導性空值。

3. **移除 AppModeTab 的「綠界」狀態標籤**（Issue #2 緩解）：  
   避免管理員誤以為切換 app.mode 就完成了 ECPay 環境切換。加上明確說明：「金流環境需另至『金流與發票』Tab 設定」。

4. **清理遺留 key**（Issue #3, #5, #6，🔵 Low）：  
   移除 `AdminController` 的 `ecpay_is_sandbox` 預設值、修正 `SystemSettingsSeeder` 的 key 名稱、在 `config/services.php` 加注說明已無效。

**方案 B vs A 的對比分析：**

方案 A 是最低風險的清理，不引入新邏輯；方案 B（連動）雖然操作直覺，但引入了「切換 app.mode 會自動修改 ecpay_environment」的副作用，在已有 `PaymentSettingsTab` 可以精細控制 ecpay 環境的情況下，連動反而容易出現意外覆蓋。

建議在方案 A 完成、staging 穩定運行一段時間後，再評估是否需要方案 B 的連動設計。

方案 C 屬於長期架構改善，**不建議在當前版本執行**，等有多金流需求時再說。

### 上線前驗收關鍵點（至少需確認）

1. **確認 staging `APP_ENV` 值**：SSH 到 staging，執行 `docker exec mimeet-app php artisan env`，確認輸出不是 `production`
2. **確認 mock 端點可訪問**：在 `ecpay_environment=sandbox` 且 `APP_ENV!=production` 的情況下，`GET /api/v1/payments/ecpay/mock?trade_no=xxx` 回傳模擬付款頁面而非 404
3. **確認 ecpay_environment 切換生效**：在後台 PaymentSettingsTab 切換到 production，確認 `ECPayService::getAioUrl()` 回傳 `payment.ecpay.com.tw`（非 `payment-stage.ecpay.com.tw`）
4. **確認舊端點 GET /admin/settings/ecpay 不再誤導**：回傳 410 Gone 或正確資料，而非空值
5. **確認 AppModeTab 切換不會影響 ecpay_environment**（在方案 A 下）：切換 app.mode 後，到 PaymentSettingsTab 確認 `ecpay_environment` 值未被修改
6. **確認 AdminOperationLog 記錄 ecpay_environment 變更**：改動 PaymentSettingsTab 後查看 DB `admin_operation_logs` 表，確認有對應記錄

---

## 7. 不採取行動的風險（Do-Nothing Risk）

**最壞情況描述：**

若以上問題均不處理，可能發生的最嚴重情境：

1. **正式上線時（ecpay_environment 應切換至 production）：** 管理員只在 AppModeTab 切換「正式模式」，誤以為綠界也切換了。但實際上 `ecpay_environment` 仍是 `sandbox`。所有前台訂閱購買請求將以測試憑證送到綠界沙箱，用戶看起來流程正常（沙箱不收真實款項），但沒有任何人實際付款，營收為零。此問題可能數天內才被發現（需要手動對帳）。

2. **Staging 測試受阻（APP_ENV=production 導致 mock 被鎖）：** 若 staging `.env` 未設定 `APP_ENV`，預設值是 `production`，mock 路由不會被掛載。這意味著目前在 staging 上的訂閱 sandbox 測試可能已經全面失效（前端訪問 mock URL 得 404），但外部看起來訂閱流程「一直在失敗」，不易與其他 bug 區分。

3. **合規風險：** 若在切換到正式環境時發生雙重計費或對帳不一致，可能需要手動處理多筆訂單，影響用戶信任。

---

## 8. 待確認問題（Open Questions）

### Q1：`app.mode` 與 `ecpay_environment` 是否應連動？

**問題：** AppModeTab 切換「正式模式」時，是否應自動將 `ecpay_environment` 設為 `production`？

**不連動的影響：**  
- 管理員需手動到兩個地方分別設定，認知負擔較重
- 若有遺漏，可能出現「模式是正式，但金流還是 sandbox」的矛盾狀態

**連動的影響：**  
- 若管理員已精細設定好 ecpay 環境，切換 app.mode 會意外覆蓋
- 反向操作（切回 testing）也需決定是否自動回到 sandbox

**建議問業主：** 商業流程上，正式模式和 ECPay 正式環境是否必須同步？是否允許「app 在正式模式但 ECPay 仍走 sandbox 做壓力測試」？

---

### Q2：切換 `ecpay_environment` 是否需要二次確認（Modal）？

**問題：** 目前 PaymentSettingsTab 切換 `ecpay_environment` 後直接儲存，沒有二次確認。

**不加二次確認的影響：**  
- 誤操作風險（手滑切換到 production，下一筆就是真實收款）

**加二次確認的影響：**  
- 增加使用摩擦，但防止誤操作
- AppModeTab 已有密碼確認，可以類比

**建議：** 切換到 `production` 時要求輸入管理員密碼或顯示明確警告（「此操作將開始向用戶收取真實款項，確認嗎？」）。

---

### Q3：Sandbox 模式下 Staging 是否允許走 mock？

**問題：** 目前 mock 端點守門是 `config('app.env') !== 'production'`，建議改為 `ECPayService::isSandbox()`。若改動後，只要 `ecpay_environment=sandbox`，mock 在任何 server 環境下都可訪問。

**改動後的影響：**  
- Staging 環境永遠可以用 mock（只要 ecpay_environment=sandbox）
- 若 staging `.env` 有誤將 `ecpay_environment` 設為 `production`，mock 就消失，這反而是合理行為
- 萬一正式環境有人將 `ecpay_environment` 設回 `sandbox`，mock 端點也會回來（需評估是否可接受）

**建議：** 接受此設計，因為 mock 是測試工具，應與金流環境設定同步，而非與 server 部署環境綁定。

---

### Q4：點數購買 production 流程是否需要在本次補上？

**問題：** 目前點數購買在 sandbox 走 `/payments/ecpay/point-mock`（mock 端點），在 production 應走 ECPay 正式 AIO 流程。但目前 `PointController::purchase()` 回傳的是 `aio_url` + `params`（UnifiedPaymentService 的新架構），前端需要用這些參數直接 POST 到 ECPay，**不是** point-mock URL。

確認：前端 ShopView 在點數購買時，收到 `aio_url + params` 的處理邏輯是否已就緒？還是仍依賴舊的 `payment_url` 欄位跳轉到 point-mock？

---

### Q5：是否需要「金鑰未填就切換到 production 的防護」？

**問題：** 目前 PaymentSettingsTab 有 `prodWarning` 顯示警告，但不強制阻止儲存。若 `ecpay_production_merchant_id` 為空，切換到 production 後金流請求將帶空 merchant_id，ECPay 會回傳錯誤。

**不加硬擋的影響：**  
- 會有付款失敗的交易，需要人工處理

**加硬擋的影響：**  
- 可能阻礙合法的漸進式設定流程（例如先設憑證，後切環境）

**建議：** 在後端 `PUT /admin/settings/payment` 中，若 `ecpay_environment=production` 且 `ecpay_production_merchant_id` 為空，回傳 422 並明確說明原因。

---

### Q6：是否需要補齊 App Mode 切換的 Audit Log？

**問題：** `PATCH /admin/settings/app-mode` 目前只寫 Laravel Log（`Log::info`），沒有寫 `AdminOperationLog`。

**不補的影響：**  
- 若日後發生「正式模式被切回測試模式」的問題，無法從 DB 查誰在什麼時間做了操作

**補上的影響：**  
- 完整稽核紀錄，符合 GDPR / 內部稽核要求

**建議：** 在 `updateAppMode()` 函式中加入 `AdminOperationLog::create(...)` 記錄，與 PaymentSettingsController 保持一致。

---

## 報告自我檢查

- [x] 每個 Issue 都有程式碼引用（含行號）
- [x] 每個方案都有改動範圍估算（檔案數 + 大致行數）
- [x] 推薦方案有對比說明（方案 A vs B vs C）
- [x] 待確認問題都有「不同答案的影響」說明
- [x] 沒有出現「我已修改了...」（唯讀分析，未改任何 code）
- [x] 所有 grep 結果已整合進現況盤點章節
- [x] 報告覆蓋七大 Issue（含等級標記）
