# 金流環境切換可行性評估報告

**執行日期：** 2026-04-28  
**稽核者：** Claude Code  
**規格來源：** `docs/DEV-011_金流與發票整合規格書.md` v1.0  
**程式碼基準：** 2feb16a（trial 金流流程 bug fix，develop 領先 main 1 commit）  
**報告性質：** 唯讀評估，不含任何程式碼修改

---

## 章節 0 — 取證紀錄

### 環境狀態

| 項目 | 值 |
|------|-----|
| Branch | `develop` |
| HEAD commit | `2feb16a` |
| 未提交變更 | 無（working tree clean）|
| develop vs main | develop 領先 1 commit |
| Staging APP_ENV | `staging`（`docker exec mimeet-app grep APP_ENV .env`）|

### Staging DB system_settings（ecpay/invoice/payment 相關，2026-04-28 實測）

| key_name | value（截斷至 60 char）|
|----------|----------------------|
| `ecpay_environment` | `sandbox` |
| `ecpay_is_sandbox` | `true` |（孤兒 key，無程式讀取）
| `ecpay_merchant_id` | `3002607` |（孤兒 key，無程式讀取）
| `ecpay_invoice_donation_love_code` | `168001` |
| `ecpay_invoice_enabled` | `0` |
| `ecpay_invoice_hash_iv` | `q9jcZX8Ib9LM8wYk` |
| `ecpay_invoice_hash_key` | `ejCk326UnaZWKisg` |
| `ecpay_invoice_merchant_id` | `2000132` |
| `ecpay_production_hash_iv` | `（空值）` |
| `ecpay_production_hash_key` | `（空值）` |
| `ecpay_production_merchant_id` | `（空值）` |
| `ecpay_sandbox_hash_iv` | `（Crypt 加密 base64）` |
| `ecpay_sandbox_hash_key` | `（Crypt 加密 base64）` |
| `ecpay_sandbox_merchant_id` | `3002607` |

> 注意：`ecpay_is_sandbox` 和 `ecpay_merchant_id` 為孤兒 key，在 staging DB 中存在但沒有任何程式碼讀取，僅由 `AdminController.php:897` 透過 `getSystemSettings` API 回傳給前台顯示，不影響金流行為。

### 確認 admin_operation_logs

`payment_settings_updated` action：**0 筆**（歷史上從未透過後台 PaymentSettingsTab 儲存過設定）

---

## 章節 1 — 執行摘要

**現況：** 金流環境切換的核心邏輯（`ECPayService::getEnvironment()`）設計良好，以 `system_settings.ecpay_environment` 為唯一真值，已透過 migration 從舊 `ecpay.mode` dot-notation key 遷移。主流程（訂閱、點數、信用卡驗證）全面使用 `UnifiedPaymentService` + `ECPayService::buildAioParams()`，不走自家 mock，符合決策 1。

**核心問題：** 存在四個需要處理的問題：(1) mock 端點守門條件以 `APP_ENV === 'production'` 作判斷，但 staging 的 `APP_ENV = staging`，導致 mock 端點在 staging 上永遠可訪問；(2) `config/ecpay.php` 的 `sandbox_fallback` 憑證（MID=`2000132`）與官方金流 sandbox 測試憑證（MID=`3002607`）不一致，兩者混用已在 staging DB 體現（`ecpay_sandbox_merchant_id = 3002607` 但 fallback = `2000132`）；(3) 後台 `PaymentSettingsTab` UI 只管理金流憑證（`ecpay_sandbox_*` / `ecpay_production_*`）和 `ecpay_invoice_enabled` 開關，完全沒有提供發票憑證（`ecpay_invoice_merchant_id/hash_key/hash_iv`）的設定入口，造成發票憑證只能靠 seeder 初始化，正式環境無法透過後台換憑證；(4) `app_mode`（testing/production）與 `ecpay_environment`（sandbox/production）是**完全獨立的兩個開關**，`AppModeTab` 切換 `app_mode` 不連動 `ecpay_environment`，會造成管理員誤以為切換 `app_mode` 就同時切換了金流環境。

**是否影響線上：** 目前 `ecpay_environment = sandbox`，staging 在使用真綠界 sandbox（符合決策 1）。mock 端點雖在 staging 上可訪問，但主流程已不產生指向 mock 的 URL，所以實際上不走 mock。發票 `ecpay_invoice_enabled = 0`，功能未啟用，發票憑證問題暫不影響線上。

**推薦方案：** 方案 A（最小修改）。核心架構已完整，只需修補四個具體缺口，無需大規模重構。

---

## 章節 2 — 現況盤點

### 2.1 環境判斷邏輯散落點（依五個業務入口）

| 業務入口 | Service | 判斷依據 | sandbox 行為 | production 行為 | 與決策 1 相符 |
|---------|---------|---------|------------|----------------|-------------|
| 訂閱購買（SubscriptionController） | `UnifiedPaymentService::initiate()` → `ECPayService::getAioUrl()` | `system_settings.ecpay_environment` | `https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5` | `https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5` | **相符** |
| 點數購買（PointController） | 同上 | 同上 | 同上 | 同上 | **相符** |
| 信用卡驗證（CreditCardVerificationController::initiate） | `UnifiedPaymentService::initiate()` | 同上 | 同上 | 同上 | **相符** |
| 發票開立（PaymentService::issueInvoiceForOrder） | `ECPayService::issueInvoice()` | `ECPayService::isSandbox()` → 同上 | `https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue` | `https://einvoice.ecpay.com.tw/B2CInvoice/Issue` | **相符** |
| Mock 付款（PaymentCallbackController::mock/pointMock） | 自家 mock | `config('app.env') === 'production'` | `APP_ENV = staging` → mock 可訪問 | `APP_ENV = production` → abort(404) | **不相符（見 Issue P-001）** |

#### 各入口程式碼片段

**訂閱/點數/信用卡驗證（統一入口）：**
```php
// UnifiedPaymentService.php:65-74
$params = $this->ecpay->buildAioParams([...]);
return [
    'payment' => $existing,
    'aio_url' => $this->ecpay->getAioUrl(),  // 讀 ecpay_environment
    'params'  => $params,
];
```

**ECPayService 環境讀取：**
```php
// ECPayService.php:38-39
$env = SystemSetting::get('ecpay_environment', 'sandbox');
return in_array($env, ['sandbox', 'production']) ? $env : 'sandbox';
```

**發票 URL 選擇：**
```php
// ECPayService.php:339-341
$baseUrl = $this->isSandbox()
    ? 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue'
    : 'https://einvoice.ecpay.com.tw/B2CInvoice/Issue';
```

**Mock 守門條件（問題所在）：**
```php
// PaymentCallbackController.php:73 (mock) / :139 (pointMock)
if (config('app.env') === 'production') {
    abort(404);
}
// → APP_ENV=staging 時，此 guard 不觸發，mock 永遠可訪問
```

**Route 層 mock 守門：**
```php
// routes/api.php:175-178
if (config('app.env') !== 'production') {
    Route::get('/mock',       [PaymentCallbackController::class, 'mock']);
    Route::get('/point-mock', [PaymentCallbackController::class, 'pointMock']);
}
// → 同樣的問題：APP_ENV=staging 時路由被註冊
```

---

### 2.2 system_settings key 散亂狀況

| key_name | DEV-011 規格怎麼寫 | Seeder 怎麼寫 | ECPayService 怎麼讀 | 後台 UI 管理 | Droplet 實際值 |
|----------|-----------------|-------------|-------------------|------------|--------------|
| `ecpay_environment` | 遷移後新 key | 無（由 migration 建立）| `SystemSetting::get('ecpay_environment', 'sandbox')` | PaymentSettingsTab（讀寫）| `sandbox` |
| `ecpay_sandbox_merchant_id` | 新格式 | 無 | `SystemSetting::get("ecpay_sandbox_merchant_id", '')` | PaymentSettingsTab（讀寫）| `3002607` |
| `ecpay_sandbox_hash_key` | 新格式（加密）| 無 | 同上，fallback `5294y06JbISpM5x9` | PaymentSettingsTab | 加密值（原始為 `pwFHCqoQZGmho4w6`）|
| `ecpay_sandbox_hash_iv` | 新格式（加密）| 無 | 同上，fallback `v77hoKGq4kWxNNIS` | PaymentSettingsTab | 加密值（原始為 `EkRm7iFT261dpevs`）|
| `ecpay_production_merchant_id` | 新格式 | 無 | `SystemSetting::get("ecpay_production_merchant_id", '')` | PaymentSettingsTab | **空值** |
| `ecpay_production_hash_key` | 新格式（加密）| 無 | 同上，fallback `''` | PaymentSettingsTab | **空值** |
| `ecpay_production_hash_iv` | 新格式（加密）| 無 | 同上，fallback `''` | PaymentSettingsTab | **空值** |
| `ecpay_invoice_merchant_id` | 獨立發票 key | `2000132`（sandbox 測試值）| `SystemSetting::get('ecpay_invoice_merchant_id', ...)` | **無 UI 管理** | `2000132` |
| `ecpay_invoice_hash_key` | 獨立發票 key | `ejCk326UnaZWKisg` | 同上，fallback `services.ecpay.invoice_hash_key` | **無 UI 管理** | `ejCk326UnaZWKisg` |
| `ecpay_invoice_hash_iv` | 獨立發票 key | `q9jcZX8Ib9LM8wYk` | 同上，fallback `services.ecpay.invoice_hash_iv` | **無 UI 管理** | `q9jcZX8Ib9LM8wYk` |
| `ecpay_invoice_enabled` | 發票開關 | 無（由 migration 建立）| `SystemSetting::get('ecpay_invoice_enabled', false)` | PaymentSettingsTab（Switch 控制）| `0` |
| `ecpay_invoice_donation_love_code` | 捐贈碼 | `168001` | `SystemSetting::get('ecpay_invoice_donation_love_code', '168001')` | **無 UI 管理** | `168001` |
| `ecpay_is_sandbox` | **不在規格中**（孤兒 key）| SystemSettingsSeeder 注釋提及「向下相容」| **無** | **無** | `true`（孤兒）|
| `ecpay_merchant_id` | **不在規格中**（孤兒 key）| 無 | **無** | **無** | `3002607`（孤兒）|
| `ecpay.mode` | 舊格式（已廢棄）| migration 2026-04-10 建立 | 廢棄，不再讀取 | **舊 ECPaySettingController 讀寫（但已標記 @deprecated 且 update() 直接 return 400）** | 未查（應由 migration 刪除）|
| `ecpay.payment.*` | 舊格式（已廢棄）| migration 2026-04-10 建立 | 廢棄 | 舊 ECPaySettingController 讀寫（@deprecated）| 未查（應由 migration 刪除）|

**孤兒 key 的來源：**
- `ecpay_is_sandbox = true`：由 SystemSettingsSeeder（注釋保留「向下相容」，但 comment 說「已由 PaymentSettingsTab 管理（新 key 格式）」）— 實際上沒有任何程式讀取此 key 來決定行為
- `ecpay_merchant_id = 3002607`：來源不明（可能是更早的 seeder 或手動寫入），沒有程式讀取

---

### 2.3 自家 mock 端點清單（要廢除的）

| 路徑 | Controller | 方法 | 守門條件（路由層）| 守門條件（方法層）| 用途 |
|------|-----------|------|-----------------|-----------------|------|
| `GET /api/v1/payments/ecpay/mock` | `PaymentCallbackController` | `mock()` | `config('app.env') !== 'production'`（routes/api.php:175）| `config('app.env') === 'production'` → abort(404)（PaymentCallbackController.php:73）| 訂閱 mock 付款（2 步驟 UI）|
| `GET /api/v1/payments/ecpay/point-mock` | `PaymentCallbackController` | `pointMock()` | 同上（routes/api.php:176）| 同上（PaymentCallbackController.php:139）| 點數 mock 付款（2 步驟 UI）|

**守門條件的問題：**
- Staging 環境：`APP_ENV = staging`，既不是 `production` → 路由被註冊 + 方法不 abort
- 決策 1 要求：sandbox 模式 = 真綠界 sandbox，不用自家 mock
- 影響：這兩個端點在 staging 上永遠可訪問，任何人知道 URL 可以任意標記訂單/點數為 paid，繞過真實綠界驗簽

**守門條件應改為：**
- `config('app.env') === 'local'`（或完全移除端點）

**注意：**
- `PaymentService::handleMockPayment()` 亦保留，供 mock 端點呼叫，廢棄 mock 端點後此方法可跟著廢棄

---

### 2.4 發票環境切換現況

**發票環境如何決定：**
```php
// ECPayService.php:339-341
$baseUrl = $this->isSandbox()
    ? 'https://einvoice-stage.ecpay.com.tw/B2CInvoice/Issue'
    : 'https://einvoice.ecpay.com.tw/B2CInvoice/Issue';
```

`isSandbox()` 呼叫 `getEnvironment()` 讀取 `ecpay_environment`。

**結論：金流環境與發票環境已綁定，符合決策 2。** 切換 `ecpay_environment` sandbox/production 時，金流 URL 和發票 URL 同步切換。

**但是：發票憑證（merchant_id/hash_key/hash_iv）未隨環境切換。**

`ECPayService` 的 `invoiceMerchantId()` / `invoiceHashKey()` / `invoiceHashIv()` 只有一組 key（`ecpay_invoice_*`），不區分 sandbox/production。
目前 staging DB 中的值是 sandbox 測試憑證（`2000132` / `ejCk326UnaZWKisg` / `q9jcZX8Ib9LM8wYk`）。
切換 `ecpay_environment` 為 production 後，**發票 URL 會改用正式域名，但憑證仍是 sandbox 測試值**，正式環境用測試憑證呼叫發票 API 必定失敗。

---

### 2.5 金鑰讀取優先順序與 fallback

#### 金流 sandbox 模式

```
1. system_settings.ecpay_sandbox_merchant_id
   → 若空白 → config('ecpay.sandbox_fallback.merchant_id') = '2000132'
                （注意：應是 '3002607'，見 Issue P-005）

1. system_settings.ecpay_sandbox_hash_key（加密儲存）
   → 若空白 → config('ecpay.sandbox_fallback.hash_key') = '5294y06JbISpM5x9'
                （注意：應是 'pwFHCqoQZGmho4w6'，見 Issue P-005）

1. system_settings.ecpay_sandbox_hash_iv（加密儲存）
   → 若空白 → config('ecpay.sandbox_fallback.hash_iv') = 'v77hoKGq4kWxNNIS'
                （注意：應是 'EkRm7iFT261dpevs'，見 Issue P-005）
```

#### 金流 production 模式

```
1. system_settings.ecpay_production_merchant_id
   → 若空白 → 回傳 ''（空字串！ECPay 會拒絕）

1. system_settings.ecpay_production_hash_key（加密儲存）
   → 若空白 → 回傳 ''（空字串！CheckMacValue 計算錯誤）

1. system_settings.ecpay_production_hash_iv（加密儲存）
   → 若空白 → 回傳 ''（空字串！CheckMacValue 計算錯誤）
```

Staging 目前 production 三個 key 均為空值，若切換 `ecpay_environment = production`，金流會立即失敗（見 Issue P-004）。

#### 發票（不區分 sandbox/production）

```
1. system_settings.ecpay_invoice_merchant_id
   → 若空白 → config('services.ecpay.invoice_merchant_id', '2000132')

1. system_settings.ecpay_invoice_hash_key
   → 若空白 → config('services.ecpay.invoice_hash_key', 'ejCk326UnaZWKisg')

1. system_settings.ecpay_invoice_hash_iv
   → 若空白 → config('services.ecpay.invoice_hash_iv', 'q9jcZX8Ib9LM8wYk')
```

> 重點缺口：`config/ecpay.php` 的 `sandbox_fallback` **不包含**發票 fallback。發票 fallback 在 `config/services.php`（舊格式），值為 sandbox 測試憑證（`2000132 / ejCk326UnaZWKisg / q9jcZX8Ib9LM8wYk`）。切換 production 後，**發票憑證無法自動跟著切換，必須手動更新 DB**，但目前沒有 UI 支援。

---

## 章節 3 — 問題清單

---

## Issue P-001

**規格位置：** 產品決策 1（本報告前言）  
**規格要求：** sandbox 模式 = 真綠界 sandbox（`payment-stage.ecpay.com.tw`），不用自家 mock  
**程式碼位置：** `backend/app/Http/Controllers/Api/V1/PaymentCallbackController.php:73,139` / `backend/routes/api.php:175-178`  
**程式碼現況：**
```php
// PaymentCallbackController.php:73
if (config('app.env') === 'production') {
    abort(404);
}
// routes/api.php:175-178
if (config('app.env') !== 'production') {
    Route::get('/mock',       [PaymentCallbackController::class, 'mock']);
    Route::get('/point-mock', [PaymentCallbackController::class, 'pointMock']);
}
```
**差異說明：** 守門條件以 `app.env === 'production'` 判斷，但 staging 的 `APP_ENV = staging`，導致 mock 端點在 staging 上可正常訪問、路由被正常註冊。任何知道 URL 的人可呼叫 `GET /api/v1/payments/ecpay/mock?trade_no=XXX&confirm=1` 直接將訂單標記為 paid，完全繞過綠界驗簽，沒有任何安全防護。  
**等級：** 🔴 Critical  
**潛在影響：** 惡意用戶可免費刷訂閱/點數（訂單 trade_no 如果可猜測）  
**與決策的關係：** 違反決策 1（mock 端點存在表示有另一條非綠界付款路線）

---

## Issue P-002

**規格位置：** 產品決策 3（本報告前言）  
**規格要求：** 金流金鑰（6個 key）與發票金鑰（6個 key）是兩組獨立憑證，分開存 system_settings  
**程式碼位置：** `admin/src/pages/settings/tabs/PaymentSettingsTab.tsx:8-17,58-59` / `backend/app/Http/Controllers/Api/V1/Admin/PaymentSettingsController.php:31-39`  
**程式碼現況：**
```tsx
// PaymentSettingsTab.tsx — EcpaySettings interface
interface EcpaySettings {
  ecpay_environment: 'sandbox' | 'production'
  ecpay_sandbox_merchant_id: string
  ecpay_sandbox_hash_key: string
  ecpay_sandbox_hash_iv: string
  ecpay_production_merchant_id: string
  ecpay_production_hash_key: string
  ecpay_production_hash_iv: string
  ecpay_invoice_enabled: string      // ← 只有開關，沒有發票憑證欄位
}
```
```php
// PaymentSettingsController.php — ALL_KEYS
private const ALL_KEYS = [
    'ecpay_environment',
    'ecpay_sandbox_merchant_id', 'ecpay_sandbox_hash_key', 'ecpay_sandbox_hash_iv',
    'ecpay_production_merchant_id', 'ecpay_production_hash_key', 'ecpay_production_hash_iv',
    'ecpay_invoice_enabled',   // ← 只有開關，沒有 ecpay_invoice_merchant_id 等
];
```
**差異說明：** 後台 PaymentSettingsTab 只提供 `ecpay_invoice_enabled` 開關，沒有 `ecpay_invoice_merchant_id`、`ecpay_invoice_hash_key`、`ecpay_invoice_hash_iv` 的 UI 輸入框。正式發票憑證（不同於 sandbox 測試值）無法透過後台設定，只能靠 seeder 初始值或手動操作 DB。  
**等級：** 🟠 High  
**潛在影響：** 切換 production 環境後，發票 API 仍使用 sandbox 測試憑證，每筆訂單發票請求必定失敗，但 `ecpay_invoice_enabled = 0` 時暫不影響；一旦啟用發票功能且切換 production，發票開不出來。  
**與決策的關係：** 違反決策 3（兩組獨立憑證需要各自的管理 UI）

---

## Issue P-003

**規格位置：** 產品決策 2（本報告前言）  
**規格要求：** 金流環境 = 發票環境，同一個 `ecpay_environment` 開關  
**程式碼位置：** `backend/app/Services/ECPayService.php:296-313`  
**程式碼現況：**
```php
// ECPayService.php:296-313 — 發票憑證讀取（單一 key，不分 sandbox/production）
private function invoiceMerchantId(): string
{
    return SystemSetting::get('ecpay_invoice_merchant_id',
        config('services.ecpay.invoice_merchant_id', '2000132'));
}
private function invoiceHashKey(): string
{
    return SystemSetting::get('ecpay_invoice_hash_key',
        config('services.ecpay.invoice_hash_key', 'ejCk326UnaZWKisg'));
}
private function invoiceHashIv(): string
{
    return SystemSetting::get('ecpay_invoice_hash_iv',
        config('services.ecpay.invoice_hash_iv', 'q9jcZX8Ib9LM8wYk'));
}
```
**差異說明：** 發票 URL 已正確跟隨 `ecpay_environment`（`isSandbox()` 判斷，URL 用 stage/prod 域名，**符合決策 2**）。但發票憑證只有一組，不區分 sandbox/production slot。切換 `ecpay_environment = production` 時，發票用 `https://einvoice.ecpay.com.tw`（正式域名），但憑證是 `2000132 / ejCk326UnaZWKisg / q9jcZX8Ib9LM8wYk`（sandbox 測試值），必然失敗。  
**等級：** 🟠 High  
**潛在影響：** 切換 production 後啟用發票，每筆付款後發票呼叫失敗（回傳 TransCode 非 1），`invoice_no` 欄位空白，用戶無發票。  
**與決策的關係：** 決策 2 部分相符（URL 正確跟隨）但決策 3 隱含需要兩套發票憑證（sandbox 用 sandbox 值、production 用 production 值）

---

## Issue P-004

**規格位置：** `docs/DEV-011_金流與發票整合規格書.md` §4「金鑰管理」  
**規格要求：** 上線前正式憑證必填  
**程式碼位置：** `backend/app/Services/ECPayService.php:49-80` / staging DB  
**程式碼現況：**
```php
// ECPayService.php:73-80
public function getHashKey(): string
{
    $env = $this->getEnvironment();
    $val = SystemSetting::get("ecpay_{$env}_hash_key", '');
    if ($val === '' && $env === 'sandbox') {
        return config('ecpay.sandbox_fallback.hash_key', '5294y06JbISpM5x9');
    }
    return (string) $val;  // ← production 且空值時，直接回傳空字串
}
```
**差異說明：** Staging DB 中 `ecpay_production_merchant_id`、`ecpay_production_hash_key`、`ecpay_production_hash_iv` **均為空值**。若現在切換 `ecpay_environment = production`，`getMerchantId()` 回傳空字串，`getHashKey()` 回傳空字串，所有金流 CheckMacValue 都會計算錯誤，ECPay 拒絕所有請求，金流完全失效。後台 PaymentSettingsTab 有 `prodWarning` 警示邏輯（切換到 production 時若憑證未填會顯示紅框），但這只是 UI 警示，**不是寫入前的強制守門**（`handleSave()` 不會因 `prodWarning = true` 而阻止儲存）。  
**等級：** 🟠 High  
**潛在影響：** 誤切換 production 後所有付款請求失敗，訂閱/點數功能完全中斷。  
**與決策的關係：** 無直接違反，但缺少切換前的 guard

---

## Issue P-005

**規格位置：** `docs/DEV-011_金流與發票整合規格書.md` §6「測試金鑰（Sandbox）」  
**規格要求：** 金流 sandbox: MID=`3002607`, HashKey=`pwFHCqoQZGmho4w6`, HashIV=`EkRm7iFT261dpevs`  
**程式碼位置：** `backend/config/ecpay.php:25-29`  
**程式碼現況：**
```php
// config/ecpay.php:25-29
'sandbox_fallback' => [
    'merchant_id' => '2000132',      // ← 應為 3002607
    'hash_key'    => '5294y06JbISpM5x9',  // ← 應為 pwFHCqoQZGmho4w6
    'hash_iv'     => 'v77hoKGq4kWxNNIS',  // ← 應為 EkRm7iFT261dpevs
],
```
**差異說明：** `sandbox_fallback` 的值（`2000132 / 5294y06JbISpM5x9 / v77hoKGq4kWxNNIS`）與官方金流 sandbox 測試憑證（`3002607 / pwFHCqoQZGmho4w6 / EkRm7iFT261dpevs`）完全不同。`2000132` 是發票 sandbox MID，不是金流 sandbox MID。若 `ecpay_sandbox_*` key 全部被清空（如 fresh migration），fallback 會使用錯誤的發票憑證打金流 API，導致所有付款失敗。Staging 目前的 `ecpay_sandbox_merchant_id = 3002607` 是正確的（從 services.php 或手動設定而來），所以 fallback 目前不會被觸發，但此錯誤的 fallback 值是潛在的定時炸彈。  
**等級：** 🟡 Medium  
**潛在影響：** fresh deploy 後若憑證 key 為空，金流 sandbox 會用發票測試值驗簽，所有付款請求失敗。  
**與決策的關係：** 違反決策 1（sandbox 應使用真綠界金流 sandbox 憑證）

---

## Issue P-006

**規格位置：** 無明確規格（但為金流安全基本要求）  
**規格要求：** 孤兒 key 不應出現在系統設定中，避免混淆維護人員  
**程式碼位置：** staging DB（2026-04-28 實測）/ `backend/app/Http/Controllers/Api/V1/AdminController.php:897`  
**程式碼現況：**
```php
// AdminController.php:897 — getSystemSettings() 包含的舊格式 key
'ecpay_is_sandbox' => '1',
// 此 key 的值 hardcode 為 '1'，不讀 system_settings
```
**差異說明：** `AdminController::getSystemSettings()` 在 response 中回傳 `ecpay_is_sandbox`（hardcode `'1'`），不讀取 DB 值，也不影響任何金流行為。但 Staging DB 中實際存有 `ecpay_is_sandbox = true` 和 `ecpay_merchant_id = 3002607` 兩個孤兒 key（無任何程式讀取），可能造成未來維護人員混淆，誤認為這些 key 還在使用。同時 `SystemSettingsSeeder` 的注釋說「此處保留向下相容」，但實際上沒有程式依賴這些孤兒 key。  
**等級：** 🔵 Low  
**潛在影響：** 維護混淆，未來可能被誤改；`ecpay_is_sandbox` 的值不反映真實環境，可能誤導 debug。  
**與決策的關係：** 無直接違反，但違反乾淨架構原則

---

## Issue P-007

**規格位置：** CLAUDE.md §四項禁令（需要可追溯的變更紀錄）  
**規格要求：** 重要設定變更應有操作日誌  
**程式碼位置：** `backend/app/Http/Controllers/Api/V1/Admin/PaymentSettingsController.php:103-115`  
**程式碼現況：**
```php
// PaymentSettingsController.php:103-115 — 有寫入 admin_operation_logs
if (!empty($changes)) {
    AdminOperationLog::create([...action => 'payment_settings_updated'...]);
}
```
**差異說明：** 程式碼已正確實作 `admin_operation_logs` 記錄。但 Staging DB 查詢 `payment_settings_updated` action 回傳 **0 筆**。表示金流設定的 `ecpay_sandbox_merchant_id = 3002607`、`ecpay_sandbox_hash_key`、`ecpay_sandbox_hash_iv` 等是由 migration（2026_04_26_230000）直接寫入，從未透過後台 UI 操作。換句話說，這些值的來源沒有操作日誌，無法追溯是誰、何時設定的。  
**等級：** 🔵 Low  
**潛在影響：** 安全稽核時無法確認誰設定了現在使用的憑證  
**與決策的關係：** 低風險，架構設計上正確，只是 migration 繞過了 audit trail

---

## 章節 4 — 影響分析

| Issue | 使用者影響 | 資料影響 | 可逆性 | 發生機率 |
|-------|-----------|---------|--------|---------|
| P-001 Mock 端點在 staging 可訪問 | 高：惡意用戶可免費刷訂閱/點數 | 中：orders/subscriptions/point_orders 被污染 | 中：需人工 rollback 資料 | 中：需要知道 trade_no，但 trade_no 格式可預測（PTS + 日期 + 3 碼隨機）|
| P-002 發票憑證無 UI 管理 | 低（目前 invoice disabled）| 無（目前）/ 高（啟用後）| 高：補 UI 後可設定 | 中：啟用 invoice 時必然觸發 |
| P-003 發票憑證不分 sandbox/production | 無（目前 invoice disabled）| 無（目前）| 高：修改讀取邏輯後可切換 | 高：切換 production + 啟用 invoice 時必然觸發 |
| P-004 production 憑證空值無 hard guard | 高：所有付款失敗 | 中：pending 訂單累積 | 高：填入正確憑證即可 | 低：需要人為誤操作 |
| P-005 sandbox_fallback 憑證錯誤 | 中：fresh deploy 後 sandbox 失效 | 低：只影響 sandbox 測試 | 高：修改 config 值即可 | 低：只在 sandbox key 被清空時觸發 |
| P-006 孤兒 key | 無 | 無 | 高：清理 DB 即可 | 不適用（已存在）|
| P-007 migration 繞過 audit trail | 無 | 無 | 不可逆（歷史記錄） | 不適用（已發生）|

---

## 章節 5 — 解決方案

---

### 方案 A：最小修改（保守派）

**核心想法：** 針對四個具體缺口做點對點修補，不動既有架構。

**具體改動清單：**

1. **修復 P-001 mock 端點守門條件**（最高優先）
   - `routes/api.php:175`：改 `config('app.env') !== 'production'` 為 `app()->environment('local')`
   - `PaymentCallbackController.php:73,139`：改 `config('app.env') === 'production'` 為 `!app()->environment('local')`
   - 改動範圍：3 行

2. **修復 P-005 sandbox_fallback 錯誤憑證**
   - `config/ecpay.php:26-28`：改為正確的金流 sandbox 值（`3002607 / pwFHCqoQZGmho4w6 / EkRm7iFT261dpevs`）
   - 改動範圍：3 行

3. **修復 P-002/P-003 發票憑證 UI 管理 + 分環境儲存**
   - `admin/src/pages/settings/tabs/PaymentSettingsTab.tsx`：新增「發票沙箱憑證」和「發票正式憑證」Card，類似金流 sandbox/production 的兩欄結構
   - `EcpaySettings` interface 新增：`ecpay_invoice_sandbox_merchant_id`、`ecpay_invoice_sandbox_hash_key`、`ecpay_invoice_sandbox_hash_iv`、`ecpay_invoice_production_merchant_id`、`ecpay_invoice_production_hash_key`、`ecpay_invoice_production_hash_iv`
   - `PaymentSettingsController.php`：擴充 `ALL_KEYS` 和 `ENCRYPTED_KEYS` 加入 invoice 分環境 key
   - `ECPayService.php`：`invoiceMerchantId()` 等方法改為依 `getEnvironment()` 讀取對應 slot
   - migration：新增 6 個 `ecpay_invoice_{sandbox|production}_{merchant_id|hash_key|hash_iv}` key
   - 改動範圍：~5 個檔案，約 80-120 行

4. **補強 P-004 production 憑證未填的強制 guard（選做）**
   - `PaymentSettingsController::update()`：若 `ecpay_environment = production` 且任一 production key 為空，回傳 422 with message
   - 改動範圍：1 個檔案，約 15 行

5. **清理 P-006 孤兒 key（選做）**
   - 新增 migration 刪除 `ecpay_is_sandbox` 和 `ecpay_merchant_id`
   - `AdminController::getSystemSettings()` 移除 `ecpay_is_sandbox` 回傳
   - 改動範圍：2 個檔案，約 5 行

**改動範圍：** 7 個檔案，約 110-150 行  
**資料遷移需求：** 需要新增 migration 建立 6 個新發票 key（可設預設值）  
**回滾難度：** 低（migration down 可刪除新 key，程式碼可 git revert）

**優點：**
1. 改動小，影響範圍有限，不破壞現有測試
2. 可分批執行（P-001 最優先，發票 UI 次之）
3. 保留既有 ECPayService 架構，熟悉度高
4. 直接對應問題，不過度設計

**缺點：**
1. `ECPayService` 發票方法會因為分環境 slot 而行數增加，有些重複性代碼
2. 沒有系統性解決「增加環境判斷的入口就需要改很多地方」的問題
3. 測試覆蓋需要各別補充

**適合場景：** Sprint 時間緊、希望快速上線、架構已趨穩定

**是否符合決策 1-5：**
- 決策 1（真綠界 sandbox）：✅ P-001 修復後符合
- 決策 2（金流=發票環境）：✅ 已符合
- 決策 3（兩組獨立憑證）：✅ P-002/P-003 修復後符合
- 決策 4（sandbox 發票 URL 用 stage 域名）：✅ 已符合
- 決策 5（不考慮資料遷移）：✅ 用 migration 新增，無舊資料遷移

---

### 方案 B：抽象方法（中等改動）

**核心想法：** 在 `ECPayService` 加入統一的環境感知方法，所有憑證讀取透過環境參數分流，減少散落的讀取邏輯。

**主要新增/修改：**
1. `ECPayService` 加入 `getInvoiceMerchantId(?string $env = null): string`、`getInvoiceHashKey()`、`getInvoiceHashIv()`，支援環境參數（不傳則用 `getEnvironment()`）
2. `getAioUrl()` 已符合此模式，無需改動
3. `config/ecpay.php` 仿照 `urls` 結構，新增 `invoice.urls`（sandbox/production）
4. UI 改動與方案 A 相同

**改動範圍：** 6 個檔案，約 120-180 行  
**資料遷移需求：** 同方案 A  
**回滾難度：** 低

**優點：**
1. `ECPayService` 的環境感知更一致（金流/發票都用同樣的模式）
2. 測試更容易（mock `getEnvironment()` 可控制所有行為）
3. 新增環境時（假設未來有 staging 特殊環境）只需改 config

**缺點：**
1. 比方案 A 多 20-40 行，但帶來的收益有限（現在只有 sandbox/production 兩種模式）
2. config 結構需要重整（`ecpay.php` 加 invoice urls 區段，目前發票 URL 是 hardcode 在 Service 裡）
3. 介紹新 convention 需要文件更新

**適合場景：** 希望增強測試能力和可維護性，預期未來有更多環境

**是否符合決策 1-5：**
- 決策 1：✅ P-001 修復後符合
- 決策 2：✅ 已符合
- 決策 3：✅ 修復後符合
- 決策 4：✅ 已符合
- 決策 5：✅ 無舊資料遷移

---

### 方案 C：Strategy Pattern（較大改動）

**核心想法：** 定義 `PaymentGatewayInterface`（含 `getAioUrl()`, `getMerchantId()`, `getHashKey()`, `getHashIV()`, `buildParams()`）和 `InvoiceGatewayInterface`（含 `getApiUrl()`, `getMerchantId()`, `getHashKey()`, `getHashIV()`），Sandbox/Production 各實作一套具體 Driver，透過 `ECPayService` 或 DI Container 根據 `ecpay_environment` 注入對應的 Driver。

**主要新增/修改：**
1. 新介面：`App\Contracts\PaymentGatewayInterface`
2. 新介面：`App\Contracts\InvoiceGatewayInterface`
3. 新類別：`App\Services\Gateways\ECPaySandboxGateway`（金流 sandbox 實作）
4. 新類別：`App\Services\Gateways\ECPayProductionGateway`（金流 production 實作）
5. 新類別：`App\Services\Gateways\ECPayInvoiceSandboxGateway`
6. 新類別：`App\Services\Gateways\ECPayInvoiceProductionGateway`
7. `ECPayService` 重構為 orchestrator，依 `ecpay_environment` 選擇注入哪個 Driver
8. UI、migration 改動同方案 A

**改動範圍：** 12+ 個檔案，約 250-400 行  
**資料遷移需求：** 同方案 A  
**回滾難度：** 高（大量新類別，若有問題難以快速 revert）

**優點：**
1. 符合 SOLID（每個 Driver 負責單一環境的完整實作）
2. 測試極易（mock interface）
3. 新增金流/發票提供商（如付呗、藍新）時改動最小

**缺點：**
1. 現在只有 ECPay 一家供應商，Strategy Pattern 是 over-engineering
2. 大量 interface + driver 增加架構複雜度
3. 學習曲線高，新進開發者需要時間理解
4. 目前 `ECPayService` 只有 ~430 行，重構後架構複雜度倍增
5. 上線前需要完整的集成測試才安全

**適合場景：** 確定未來要接多家金流/發票供應商、有充足測試時間

**是否符合決策 1-5：**
- 決策 1：✅ P-001 修復後符合
- 決策 2：✅ 已符合
- 決策 3：✅ 修復後符合
- 決策 4：✅ 已符合
- 決策 5：✅ 無舊資料遷移

---

## 章節 6 — 推薦方案與理由

**推薦：方案 A（最小修改，但 P-001 必須立即執行）**

### 理由

1. **P-001 是 Critical 安全問題，需要最快修復**。Mock 端點在 staging 可訪問，方案 A 的修復只需改 3 行，今天就能上線。方案 B/C 都包含這個修復，但需要更多準備時間，增加的複雜度與緊迫性不成比例。

2. **架構主體已正確**。`ECPayService::getEnvironment()` 的環境讀取設計良好，`UnifiedPaymentService` 的三入口統一走法正確，主要問題是細節補全（發票憑證 UI、守門條件、fallback 值），不需要大規模重構。

3. **方案 B 的抽象收益有限**。目前只有 ECPay 一個供應商，方法層的環境感知（`?string $env = null`）帶來的可測試性提升不足以支撐額外的 30-50 行 overhead；ECPay 近期也不會更換，Strategy Pattern 更不適合。

4. **發票功能目前未啟用（`ecpay_invoice_enabled = 0`）**，P-002/P-003 有緩衝時間。建議先修 P-001（今天），發票 UI 補充（本 Sprint 內）。

### 與其他方案對比

| | 方案 A | 方案 B | 方案 C |
|--|--------|--------|--------|
| P-001 修復時間 | 立即 | 立即 | 需要更多設計 |
| 改動量 | 小 | 中 | 大 |
| 回滾難度 | 低 | 低 | 高 |
| 過度設計風險 | 無 | 低 | 高 |
| 測試覆蓋需求 | 中 | 中 | 高 |

### 建議分階段執行

**階段 1（立即，今天）：**
- 修復 P-001 mock 端點守門條件（3 行）
- 修復 P-005 sandbox_fallback 錯誤值（3 行）

**階段 2（本 Sprint 內）：**
- 補充發票憑證 UI 和後端 API（P-002/P-003，約 80-120 行）
- 新增發票 key 分環境 migration
- 補強 production 切換前的強制 guard（P-004，約 15 行）

**階段 3（下個 Sprint，低優先）：**
- 清理孤兒 key（P-006）

### 上線前驗收關鍵點

1. **Mock 端點守門**：`APP_ENV=staging` 時 `GET /api/v1/payments/ecpay/mock` 應回傳 404
2. **Mock 端點守門**：`APP_ENV=local` 時 mock 端點應正常回應（開發用）
3. **Sandbox 金流**：`ecpay_environment = sandbox` 時，所有付款跳轉 URL 包含 `payment-stage.ecpay.com.tw`
4. **Production 金流**：`ecpay_environment = production` 且憑證填寫後，CheckMacValue 計算應通過綠界驗簽（可用綠界測試工具驗證）
5. **Sandbox 發票 URL**：`ecpay_environment = sandbox` 時，`issueInvoice()` 呼叫的 URL 應為 `einvoice-stage.ecpay.com.tw`
6. **Production 發票 URL**：`ecpay_environment = production` 時，`issueInvoice()` 應呼叫 `einvoice.ecpay.com.tw`
7. **發票憑證 UI 儲存**：後台可儲存並讀取 `ecpay_invoice_sandbox_*` 和 `ecpay_invoice_production_*` key
8. **Sandbox fallback**：清空 `ecpay_sandbox_merchant_id` 後，fallback 應使用 `3002607`（修復 P-005 後）
9. **Production 憑證守門**：切換 `ecpay_environment = production` 且任一憑證為空時，後台應顯示警告並阻止儲存
10. **Admin operation log**：每次透過後台 UI 修改憑證，`admin_operation_logs` 應有對應記錄

---

## 章節 7 — 上線前驗收清單

### 金流

- [ ] sandbox 所有付款跳轉 URL 指向 `payment-stage.ecpay.com.tw`
- [ ] production 所有付款跳轉 URL 指向 `payment.ecpay.com.tw`
- [ ] sandbox CheckMacValue 使用正確的 `3002607 / pwFHCqoQZGmho4w6 / EkRm7iFT261dpevs`（修復 P-005 後）
- [ ] production 切換前，後台有清楚的「憑證未填」警告且阻止切換
- [ ] 訂閱購買（SubscriptionController）回傳的 `aio_url` 與 `ecpay_environment` 一致
- [ ] 點數購買（PointController）回傳的 `aio_url` 與 `ecpay_environment` 一致
- [ ] 信用卡驗證（CreditCardVerificationController）回傳的 `aio_url` 與 `ecpay_environment` 一致
- [ ] RefundJob 使用的 refund URL 與 `ecpay_environment` 一致

### 發票

- [ ] `ecpay_environment = sandbox` 時 `issueInvoice()` URL 為 `einvoice-stage.ecpay.com.tw`
- [ ] `ecpay_environment = production` 時 `issueInvoice()` URL 為 `einvoice.ecpay.com.tw`
- [ ] sandbox 發票憑證可透過後台 UI 設定（`ecpay_invoice_sandbox_*`，修復 P-002 後）
- [ ] production 發票憑證可透過後台 UI 設定（`ecpay_invoice_production_*`，修復 P-002 後）
- [ ] 切換環境後，`issueInvoice()` 使用對應環境的發票憑證（修復 P-003 後）
- [ ] `ecpay_invoice_enabled = 0` 時，`issueInvoice()` 快速 return null，不呼叫發票 API

### 三個業務入口各模式

| 業務入口 | Sandbox 驗收 | Production 驗收 |
|---------|------------|----------------|
| 訂閱 | mock 回傳 404（非 local），付款跳轉到 stage.ecpay | 付款跳轉到 payment.ecpay，callback 驗簽通過 |
| 點數 | 同上 | 同上 |
| 信用卡驗證 | 同上 | 同上，退款 URL 正確 |
| Mock（僅 local）| `APP_ENV=local` 時可訪問 | 不適用（production 不應有 mock）|

---

## 章節 8 — 不採取行動的風險

### 最壞情況：不修復任何 Issue

**P-001 繼續存在（最高風險）：**
- 惡意用戶發現 `GET /api/v1/payments/ecpay/mock?trade_no=PTS202404261234XXX&confirm=1`（訂單號格式可逆推），直接觸發 `processPointPayment()`，標記點數訂單為 paid，導致 `PointService::credit()` 幫用戶入帳點數
- 同樣路徑：`/ecpay/mock?trade_no=MM202404261234XXXX&confirm=1` 觸發 `handleMockPayment()`，激活訂閱
- 影響：財務損失（免費點數/訂閱）、資料污染、稽核風險

**P-003/P-002 繼續存在：**
- 一旦 PM 要求啟用電子發票功能（`ecpay_invoice_enabled = 1`）並切換 production，每一筆訂閱/點數付款成功後呼叫 `issueInvoice()` 都會失敗
- `issueInvoice()` 失敗是 silent fail（回傳 null，不影響付款），用戶收到訂閱但收不到發票
- 若客服開始接到「為什麼我沒有發票」的投訴，需要補開 invoice 的工作量很大

**P-005 繼續存在：**
- 若某次 `migrate:fresh` 後，seeder 沒有正確設定 `ecpay_sandbox_*` key，金流 sandbox 會用錯誤的憑證（發票 MID `2000132`）驗簽，所有開發/測試付款全部失敗，可能需要 2-4 小時診斷原因

---

## 章節 9 — 待確認問題

### Q1：切換 `ecpay_environment` 是否需要二次密碼確認？

**背景：** 目前切換 `app_mode`（AppModeTab）需要輸入管理員密碼確認。但切換 `ecpay_environment`（PaymentSettingsTab）只需按儲存，沒有二次確認機制。

**不同答案的影響：**
- 若「需要」：需在 PaymentSettingsTab 加入 confirm 密碼輸入，並在後端 `/admin/settings/payment` PUT 驗證密碼。實作約 20 行。
- 若「不需要」：維持現狀，但建議至少在切換到 production 前要求一次確認 modal（比密碼驗證更輕量）。

---

### Q2：`app_mode`（testing/production）與 `ecpay_environment`（sandbox/production）是否應該連動？

**背景：** 目前兩者完全獨立。`AppModeTab` 的描述寫「測試模式：綠界使用 Sandbox」，但切換 `app_mode = production` 並**不會**同步更改 `ecpay_environment`。反之，手動切換 `ecpay_environment = production` 也不影響 `app_mode`。Staging AppModeTab 顯示的「綠界 ● Sandbox」標籤依賴 `ecpay_sandbox` 回傳欄位，而此欄位是 `app.mode === 'testing'` 計算的（`SystemControlController.php:102`），與真實的 `ecpay_environment` 完全脫鉤。

**不同答案的影響：**
- 若「完全連動」（切 app_mode 自動切 ecpay_environment）：`AppModeTab` 需要呼叫 `PUT /admin/settings/payment` 同步切換 `ecpay_environment`，兩個設定合併管理。但決策 2 已確認「金流環境 = 發票環境」，若再加上「金流環境 = app_mode」則三者都綁在一起，切換 app_mode 就改變金流行為，風險較高。
- 若「獨立管理」：需要修正 AppModeTab 的「綠界 ● Sandbox」標籤，改讀 `ecpay_environment` 而非 `app_mode`，避免管理員看到錯誤的環境狀態。這是改動最小的正確解法。
- 若「連動但可覆蓋」（app_mode 切換後仍可手動修改 ecpay_environment）：最靈活但也最複雜，可能造成更多混淆。

---

### Q3：切換到 production 時，是否需要 guard 防止金鑰未填就成功切換？

**背景：** 目前 PaymentSettingsTab 有 UI 層的 `prodWarning`（紅框提示），但後端 `PaymentSettingsController::update()` 不驗證 production 憑證是否完整，可以成功儲存「environment=production + 憑證空值」的狀態。

**不同答案的影響：**
- 若「需要 backend guard」：後端 PUT 端點加驗證：若 `ecpay_environment = production` 且任一 production 憑證為空，回傳 422。約 15 行。
- 若「只要 frontend 警示」：維持現狀，操作員需自行注意。若操作員忽略警示並切換，금流立即失效。
- 建議：至少 backend 回傳 422，UI 層禁用「儲存」按鈕（當前狀態）外加 modal 確認，形成雙層防護。

---

### Q4：是否需要在 `admin_operation_logs` 記錄 `ecpay_environment` 切換的獨立事件？

**背景：** 目前 `PaymentSettingsController::update()` 已有 `action = 'payment_settings_updated'`，記錄所有 key 的變更（含 `ecpay_environment`）。但 `ecpay_environment` 切換是特別敏感的事件（sandbox → production 代表真實金流上線）。

**不同答案的影響：**
- 若「需要獨立記錄」：在 `update()` 中判斷 `ecpay_environment` 是否有變更，若有則額外寫一筆 `action = 'ecpay_environment_switched'`，方便後續稽核搜尋。約 8 行。
- 若「現有 log 已足夠」：維持現狀（description 中有「ecpay_environment: sandbox → production」字串，搜尋 `payment_settings_updated` 仍可找到）。

---

### Q5：後台是否需要「快速填入測試值」按鈕？

**背景：** 每次 fresh deploy 後，開發人員需要手動在後台填入 sandbox 測試憑證（`3002607 / pwFHCqoQZGmho4w6 / EkRm7iFT261dpevs`）。目前 PaymentSettingsTab 的 input placeholder 有提示「2000132（ECPay 公開測試 ID）」，但這個值是錯的（應是 `3002607`，見 P-005），且不是「一鍵填入」。

**不同答案的影響：**
- 若「需要快速填入按鈕」：在 sandbox card 右側加「填入官方測試值」按鈕，點擊後自動填充 `3002607 / pwFHCqoQZGmho4w6 / EkRm7iFT261dpevs`。開發效率高，但需注意 production 環境下不能顯示此按鈕。
- 若「不需要」：靠 migration/seeder 初始化，手動填入。現狀就是這樣，有效但麻煩。

---

### Q6：MiMeet 是「平台商」還是「一般特店」？

**背景：** 綠界的特店 vs 平台商設定不同，MID 格式和 API 行為也不同。

**不同答案的影響：**
- 若「一般特店」：目前架構正確，無需調整。
- 若「平台商」：需要在 `buildAioParams()` 加入 `PlatformID`，`doRefund()` 的端點和參數也不同。目前程式碼沒有 `PlatformID` 欄位，若 MiMeet 是平台商，所有付款請求都會被綠界拒絕。

---

### Q7：sandbox 發票是否需要在後台 UI 顯示明顯的警告（「此為測試發票，不送財政部」）？

**背景：** 決策 4 說「sandbox 發票會在綠界系統開出但不送財政部」。目前 PaymentSettingsTab 啟用發票的 Switch 文字是「每筆付款將自動開立電子發票」，沒有說明 sandbox 下的行為。

**不同答案的影響：**
- 若「需要顯示警告」：在 sandbox + invoice_enabled 時顯示 Alert（「目前為 sandbox 環境，發票為測試性質，不送財政部」），避免操作員誤解。約 5 行 TSX。
- 若「不需要」：維持現狀，依賴操作員對綠界 sandbox 機制的了解。

---

## 報告品質自我檢查

- [x] 章節 0 取證紀錄完整（branch/commit/working tree/staging DB 快照）
- [x] 每個 Issue 有檔名+行號+實際程式碼片段
- [x] 每個方案有改動範圍估算（檔案數+大致行數）
- [x] 每個方案標示是否符合 5 個決策
- [x] 推薦方案有對比說明
- [x] 待確認問題都有「不同答案的影響」（7 題）
- [x] 沒有「我已經修改了...」字句（唯讀報告）
- [x] 報告涵蓋金流 AND 發票（決策 2+3）
- [x] 上線驗收清單區分金流/發票/業務入口
