# Audit Report A Round 4 — 認證與身份驗證

**執行日期：** 2026-05-01
**稽核者：** Claude Code（本機）
**Agent ID：** claudecode
**規格來源：**
  - docs/API-001_前台API規格書.md §2 + §16.3/§16.4
  - docs/PRD-001_MiMeet_約會產品需求規格書.md §4.2.1
  - docs/DEV-008_誠信分數系統規格書.md §3 §4.1
  - docs/DEV-004_後端架構與開發規範.md §3.1 §13.1
  - docs/UF-001_用戶流程圖.md UF-01 / UF-02
  - docs/UI-001_UI_UX設計規格書.md §8.2
  - docs/DEV-001_技術架構規格書.md §6.1（Multi-Guard）
**程式碼基準（Local）：** 7787b7940ee45a1ad3ca5b078ef389084c660a09
**前次稽核（不分 agent，全部都要讀）：**
  - docs/audits/audit-A-20260422.md — Round 1 第一份（H-1 ~ L-2，基準 `ecff2f9`）
  - docs/audits/audit-A-20260424.md — Round 1 第二份（#A-001 ~ #A-008，基準 `e82c698`）
  - docs/audits/audit-A-20260427.md — Round 2 補錄（#A2-009 ~ #A2-010）
  - docs/audits/audit-A-20260427-codex.md — Round 2 codex 整輪（基準 `e2f7f5f1`）
  - docs/audits/audit-A-20260427-claudecode.md — Round 3 前一份（基準 `f4e597fe`）
  - docs/audits/audit-A-20260428-codex.md — Round 3 codex（基準另計）
  - docs/audits/audit-A-20260429-claudecode.md — Round 3 完整版（基準 `2c056ae`）
**總結：** 9 issues（🔴 0 / 🟠 0 / 🟡 4 / 🔵 5）+ 23 Symmetric

> ⚠️ **與 20260429 Round 3 的關係：** 上一輪 R3 於 `2c056ae` 跑出，本輪基準 `7787b79` 已包含其後共 13 個 commit，其中關鍵者為 `e3df60e docs(audit-A): 第一波 spec sync — 對齊認證模組規格與現況`。此 commit 將 API-001 §2.1.1 register 回應、§2.2.4 CC initiate 回應、§2.3.2 reset URL、UF-001 UF-02 SMS 跳過分支、UI-001 §8.2 SMS 模板「10 分鐘」→「5 分鐘」、DEV-004 §13.1 SMS provider gate 全部對齊現行實作。前次「集中標記改規格」的 7 條 issue 中已落地 6 條。本輪聚焦於：(a) 驗證 spec sync 第一波涵蓋率；(b) 找出第一波遺漏的次級分歧；(c) 程式碼層仍未修的 5 條（L-2 / #A-004 / #A-006 / #A-007 / #A3-002 / #A4-003）。

---

## 0. 前次 Issue 回歸狀態

### 回歸判定方法

對每個前次 issue：

1. 取上一輪基準 `2c056ae`，跑 `git diff 2c056ae..HEAD -- {issue 引用的檔案}`
2. 若無 diff 且問題在程式碼面 → 維持上一輪結論
3. 若有 diff 且為 spec sync → 重新 view spec 與 code 對應段落確認結果
4. 若不同 agent 對同 issue 結論不同 → 以最新 commit 實況為準

**關鍵 diff 結果：**

```bash
$ git diff --stat 2c056ae..HEAD -- backend/app/Http/Controllers/Api/V1/AuthController.php \
  backend/app/Services/SmsService.php \
  backend/app/Http/Controllers/Api/V1/CreditCardVerificationController.php \
  backend/routes/api.php \
  frontend/src/views/public/RegisterView.vue
# (全部無輸出 — 所有後端認證程式碼自上一輪未動)

$ git diff --stat 2c056ae..HEAD -- docs/API-001_前台API規格書.md \
  docs/DEV-004_後端架構與開發規範.md \
  docs/UF-001_用戶流程圖.md \
  docs/UI-001_UI_UX設計規格書.md
# (52 / 2757 / 14 / 2 行 diff — spec sync 第一波)
```

### 回歸狀態表

| Issue | 來源 | 前次等級 | 前次基準（short） | 本輪狀態 | 備註 |
|---|---|---|---|---|---|
| H-1（/auth/refresh 未實作） | 20260422 | 🟠 High | ecff2f9 | ✅ 已修（維持） | API-001 §2.1.3 標明未實作（line 265-270）|
| H-2（tokens vs token 結構） | 20260422 | 🟠 High | ecff2f9 | ✅ 已修（維持） | spec line 257 已用 `token` 單欄位 |
| H-3（CC 驗證未實作） | 20260422 | 🟠 High | ecff2f9 | ✅ 已修（維持） | CreditCardVerificationController 完整實作 |
| M-1（verify-phone 缺 auth:sanctum） | 20260422 | 🟡 Medium | ecff2f9 | ✅ 已修（維持） | routes/api.php:69 `['auth:sanctum', 'throttle:otp']` |
| M-2（§2.2.3 vs §16.3 路徑矛盾） | 20260422 | 🟡 Medium | ecff2f9 | ⚠️ 部分修（維持） | §2.2.3 abstract line 378 仍寫 `multipart/form-data`，§16.4 line 4257 為 `application/json`（spec 內部分歧） |
| M-3（reset-password 無 throttle） | 20260422 | 🟡 Medium | ecff2f9 | ✅ 已修（維持） | routes/api.php:58-59 `throttle:otp` |
| L-1（register 合規欄位） | 20260422 | 🔵 Low | ecff2f9 | ✅ 已修（維持） | AuthController:37-39 三欄 `required\|accepted` |
| L-2（verify-phone/confirm OTP 錯誤碼） | 20260422 | 🔵 Low | ecff2f9 | ❌ 未修 | AuthController:453/463/471 仍 `'1021'/'1022'/'1023'`，spec §2.2.2.2 line 359 為 `OTP_INVALID` |
| #A-001（verify-phone/send 缺 auth:sanctum） | 20260424 | 🔴 Critical | e82c698 | ✅ 已修（維持） | routes/api.php:69-72 |
| #A-002（register 回應 token + REGISTER_SUCCESS + verification block） | 20260424 | 🟠 High | e82c698 | ✅ 已修 | spec sync e3df60e 後 §2.1.1 line 167-196 完全對齊 code |
| #A-003（register status='active'） | 20260424 | 🟠 High | e82c698 | ✅ 已修 | spec §2.1.1 line 179 改為 `'active'` + 解釋（line 192）|
| #A-004（register 缺 group 欄位） | 20260424 | 🟡 Medium | e82c698 | ⚠️ 部分修 | spec response 已移除 group（line 173-184），但**請求 body 仍含** `"group": 2`（line 160）→ 衍生 #A5-003 |
| #A-005（register 缺 password_confirmation） | 20260424 | 🟡 Medium | e82c698 | ✅ 已修（維持） | AuthController:32 `confirmed` rule |
| #A-006（error code 格式不一致） | 20260424 | 🟡 Medium | e82c698 | ❌ 未修 | login 用字串 `INVALID_CREDENTIALS`（line 210）、verifyPhoneConfirm 用數字串 `'1023'`（line 471），混用未統一 |
| #A-007（login route 缺 throttle） | 20260424 | 🔵 Low | e82c698 | ❌ 未修 | routes/api.php:54 `Route::post('/login', ...)` 無 throttle middleware |
| #A-008（spec reset URL `mimeet.tw`） | 20260424 | 🔵 Low | e82c698 | ✅ 已修 | spec §2.3.2 line 503 改為 `https://mimeet.online/#/reset-password?...` + staging 網域註腳 |
| #A2-009（SMS 文案 10 分鐘） | 20260427 | 🔵 Low | b9cb5a7 | ✅ 已修（維持） | SmsService:17 「5 分鐘內有效」 |
| #A2-010（getCreditCardVerificationStatus 死碼） | 20260427 | 🔵 Low | b9cb5a7 | ✅ 已修（維持） | verification.ts 已移除 |
| #A3-001（CC initiate payment_url vs aio_url+params） | 20260427 codex | 🟠 / 🟡 | e2f7f5f1 / f4e597fe | ✅ 已修 | spec §2.2.4 line 401-422 改為 `payment_id + aio_url + params` 並標註 form-post 模式 |
| #A3-002（Email OTP 兩種生成 pattern） | 20260427 cc | 🔵 Low | f4e597fe | ❌ 未修 | AuthController:381 仍 `random_int(100000,999999)`；line 125/415 仍 `str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT)` |
| #A3-003（UI-001 §8.2 SMS「10 分鐘」） | 20260427 cc | 🔵 Low | f4e597fe | ✅ 已修 | UI-001 line 940 改為「5 分鐘內有效，請勿洩漏。」 |
| #A4-001（DEV-004 sms.provider gate 未文件化） | 20260429 | 🟡 Medium | 2c056ae | ✅ 已修 | DEV-004 §13.1 line 990-1003 新增「SMS Provider 控制」表格 + disabled 模式注意事項 |
| #A4-002（UF-02 SMS 跳過設計未文件化） | 20260429 | 🟡 Medium | 2c056ae | ✅ 已修 | UF-001 line 86-88 + line 119/123/130-132 新增「跳過 → EXPLORE_LV0」分支 |
| #A4-003（toE164 重複） | 20260429 | 🔵 Low | 2c056ae | ❌ 未修 | AuthController:510-520 + Sms/TwilioDriver.php:81-90 仍兩處同邏輯實作 |
| #A4-004（CC return URL 接受 POST） | 20260429 | 🔵 Low | 2c056ae | ✅ 已修 | spec §2.2.4 line 419-422 補「OrderResultURL 支援 POST」 |

**回歸修復率：** 18/24 (75.0%) — 第一波 spec sync 一次性修復 7 條（#A-002 / #A-003 / #A-008 / #A3-001 / #A3-003 / #A4-001 / #A4-002 / #A4-004，部分修 #A-004）；程式碼層仍 6 條未修。

**仍開放（程式碼層）：** L-2 / #A-006 / #A-007 / #A3-002 / #A4-003（全部 in AuthController + routes，未受 spec sync 觸及）

**仍開放（規格層）：** M-2（§2.2.3 vs §16.4）/ #A-004（spec request body 仍有 `group: 2`）

> ⚠️ Round 2 codex 與 Round 3 claudecode 對 #A3-001 結論不同（codex 🟠 High vs cc 🟡 Medium），本輪因 spec 已 sync，issue 直接結案，差異不再相關。

---

## 1. Pass 完成記錄

**程式碼範圍檔案存在性（原樣 `[ -f "$f" ] && echo ✅ || echo ❌` 輸出）：**

```
✅ backend/app/Http/Controllers/Api/V1/AuthController.php
❌ backend/app/Http/Controllers/Api/V1/VerificationController.php
❌ backend/app/Http/Controllers/Api/V1/PhoneVerificationController.php
✅ backend/app/Http/Controllers/Api/V1/CreditCardVerificationController.php
✅ backend/app/Http/Controllers/Api/V1/Admin/VerificationController.php
✅ backend/app/Services/SmsService.php
✅ backend/app/Services/CreditCardVerificationService.php
✅ backend/app/Services/CreditScoreService.php
✅ backend/app/Services/UserActivityLogService.php
✅ backend/app/Mail/EmailVerificationMail.php
✅ backend/app/Mail/ResetPasswordMail.php
❌ backend/app/Http/Middleware/CheckSuspended.php
✅ backend/app/Models/User.php
✅ backend/routes/api.php
✅ backend/config/auth.php
✅ backend/config/sanctum.php
✅ backend/app/Http/Kernel.php
✅ frontend/src/api/auth.ts
✅ frontend/src/api/verification.ts
✅ frontend/src/views/public/RegisterView.vue
✅ frontend/src/views/public/LoginView.vue
✅ frontend/src/views/public/ForgotPasswordView.vue
✅ frontend/src/views/public/ResetPasswordView.vue
✅ frontend/src/views/app/settings/VerifyView.vue
✅ frontend/src/router/index.ts
✅ frontend/src/router/guards.ts
```

**缺失說明：**
- `VerificationController.php`（前台）不存在：女性照片驗證實作於 `VerificationPhotoController`（routes/api.php:244-246）
- `PhoneVerificationController.php` 不存在：整合於 `AuthController::verifyPhoneSend/Confirm`
- `CheckSuspended.php` 不存在：`grep -rn "CheckSuspended\|check.suspended" backend/` 無輸出。停權檢查依 login 時 `if (in_array($user->status, ['suspended', 'auto_suspended']))`（AuthController:216）+ 前端路由守衛攔阻

| Pass | 範圍 | Issues 發現 |
|---|---|---|
| P1 路由層 | 17 個規格端點 vs routes/api.php | 詳見附錄 A，全部存在 |
| P2 請求 Payload | formatValidator vs spec request body | 新增 #A5-003（spec request 仍有 group） |
| P3 回應結構 | code field 型別 vs spec | 新增 #A5-001（login/logout 回應 code 字串 vs spec 整數） |
| P4 業務規則 | 13 條數值 vs code | 詳見附錄 B；規則 6 維持 ⚠️（#A3-002）|
| P5 錯誤碼 | error code 字串 vs 數字 | 延續 L-2 / #A-006 |
| P6 認證中介層 | auth:sanctum / throttle 覆蓋 | 延續 #A-007 |
| P7 前端 API 層 | TS interface vs 後端回應 | ✅ |
| P8 前端 UI 層 | RegisterView / VerifyView | ✅ |
| P9 邊界條件 | null / 重複提交 / 軟刪除 | ✅ 詳附錄 B 備注 |
| P10 跨模組副作用 | CreditScore / Mail / Cache / SMS | 新增 #A5-002（every8d driver 未 wired） |
| P11.1 死碼 | AuthController public methods | ✅ 11 個 method 全部有路由引用（grep 結果一一對應）|
| P11.2 重複實作 | OTP gen / E164 / Mail | 延續 #A4-003 / #A3-002 |
| P11.3 規格缺漏 | spec ↔ code 雙向 | 新增 #A5-003（spec 多 group request 欄位）|

---

## 2. Issues 索引（本輪新發現）

### 🔴 Critical
（無）

### 🟠 High
（無）

### 🟡 Medium
- Issue #A5-001 — API-001 §2.1.2 / §2.1.4 login/logout 回應 `code` 仍寫整數 `200`，code 實際回傳字串 `LOGIN_SUCCESS` / `LOGOUT_SUCCESS`（第一波 spec sync 漏修）
- Issue #A5-002 — `Every8dDriver` 已實作 + admin 後台暴露 `every8d` 為 provider 選項，但 `SmsService::getDriver()` 的 match 沒有 case，會落入 `LogDriver` 默默吃掉
- Issue #A5-003 — API-001 §2.1.1 註冊 request body 仍有 `"group": 2`，但 AuthController formatValidator 沒有 group rule（spec sync 修了 response 漏修 request）
- Issue #A5-004（延續 #A-006）— login `error.code='INVALID_CREDENTIALS'` 字串 vs verifyPhoneConfirm `error.code='1023'` 數字串，兩種風格混用且都未在規格列出統一定義（spec §2.2.2.2 用 `OTP_INVALID`、§2.3.2 用 `'1010'`、§2.1.2 未列）

### 🔵 Low
- Issue #A5-005（延續 L-2）— verifyPhoneConfirm 仍用 `'1021'/'1022'/'1023'` 數字串錯誤碼，與 spec §2.2.2.2 line 359 `OTP_INVALID` 不符
- Issue #A5-006（延續 #A-007）— `Route::post('/login', ...)` 仍無 throttle middleware（Cache lockout 無法擋分散式撞庫）
- Issue #A5-007（延續 #A3-002）— Email OTP 三處生成 pattern 不一致：register/verifyPhoneSend 用 `str_pad(random_int(0,999999),6,'0',STR_PAD_LEFT)`，resendVerification 用 `random_int(100000,999999)`
- Issue #A5-008（延續 #A4-003）— `toE164` 重複於 AuthController + TwilioDriver
- Issue #A5-009（延續 M-2）— spec §2.2.3 line 378 寫 `multipart/form-data` 但 §16.4 line 4257 寫 `application/json`，spec 內部分歧（code 行為依 §16.4 為準）

### ✅ Symmetric（23 條）

詳見 §6。

---

## 3. Issue 詳情

### Issue #A5-001
**Pass：** P3 回應結構
**規格位置：** docs/API-001_前台API規格書.md §2.1.2（line 237-260）+ §2.1.4（line 278-285）
**規格內容：**
````
**成功回應 (200)：**
{
  "success": true,
  "code": 200,
  "message": "登入成功",
  "data": { "user": { ... }, "token": "..." }
}
````
（logout 同樣寫 `"code": 200`）
**程式碼位置：** `backend/app/Http/Controllers/Api/V1/AuthController.php:230-248`（login）+ `:265-267`（logout）
**程式碼現況：**
```php
return response()->json([
    'success' => true, 'code' => 'LOGIN_SUCCESS', 'message' => '登入成功。',
    'data' => [ 'user' => [...], 'token' => $token ],
]);
// ...
return response()->json([
    'success' => true, 'code' => 'LOGOUT_SUCCESS', 'message' => '已登出。',
]);
```
**差異說明：** spec sync e3df60e 修了 §2.1.1 register `code: 201` → `'REGISTER_SUCCESS'`（並補強 type 註腳 line 190），但 §2.1.2 login 與 §2.1.4 logout 仍寫 `code: 200`（整數）。實際 code 早已回傳字串語意碼（`LOGIN_SUCCESS` / `LOGOUT_SUCCESS` / `LOGIN_FAILED` / `ACCOUNT_LOGIN_LOCKED` / `ACCOUNT_SUSPENDED`）。前端 `frontend/src/api/auth.ts` 介面 `LoginResponse` 已用字串。屬於第一波 spec sync 的遺漏項，與 #A-002 同性質、同 root cause。
**等級：** 🟡 Medium（spec 漂移會誤導新人讀規格實作；UX 無感但與 §2.1.1 修法不一致）
**建議方案：**
- Option A（推薦）：API-001 §2.1.2 login 回應改為 `"code": "LOGIN_SUCCESS"` + 註腳「code 為字串語意碼」（與 §2.1.1 同 pattern）；§2.1.4 logout 改為 `"code": "LOGOUT_SUCCESS"`。同時建議在 §2.1 章節開頭一次性聲明「2.1.x 所有回應 `code` 一律字串語意碼」，省去逐條註腳
- Option B：改 code，回傳整數 `200`（會影響前端 9 處讀取點，性價比差）
**推薦：** A（純 docs 同步，與 e3df60e 已開頭的 spec sync 路線一致）
**相關 issue:** > 同根問題，亦見 #A-002（已修）、#A5-004（error.code 混用）

---

### Issue #A5-002
**Pass：** P10 跨模組副作用
**規格位置：** docs/DEV-004_後端架構與開發規範.md §13.1（line 994-999）
**規格內容：**
````
| `sms.provider` 值 | 行為 | 適用場景 |
|---|---|---|
| `disabled` | sendOtp() 回 true 但不實際發送 | staging/dev |
| `mitake`   | 透過 MitakeDriver 送出           | 生產（台灣）|
| `twilio`   | 透過 TwilioDriver 送出           | 國際 / 生產 |
| `every8d`  | 透過 Every8dDriver 送出          | 生產（台灣 Every8D）|
````
**程式碼位置：** `backend/app/Services/SmsService.php:49-56`
**程式碼現況：**
```php
public function getDriver(): SmsDriverInterface
{
    return match (SystemSetting::get('sms.provider', 'disabled')) {
        'mitake' => new MitakeDriver(),
        'twilio' => new TwilioDriver(),
        default => new LogDriver(),
    };
}
```
**驗證 grep 結果（`grep -rn "every8d\\|Every8dDriver" backend/app/`）：**
```
backend/app/Http/Controllers/Api/V1/Admin/SystemControlController.php:51:
    'every8d' => ['username' => SystemSetting::get('sms.every8d.username', ''), ...]
backend/app/Services/SmsService.php:6: use App\Services\Sms\Every8dDriver;     ← imported but unused
backend/app/Services/Sms/Every8dDriver.php:10: class Every8dDriver implements SmsDriverInterface
```
**差異說明：** `Every8dDriver` 類別實作完整（每日簡訊 API），SystemControlController（admin 後台 SMS 設定頁）也暴露 `every8d` 為合法 provider 值，DEV-004 §13.1 spec 也列入。但 `SmsService::getDriver()` 的 match 漏掉 `'every8d'` case。`SystemSetting::get('sms.provider')='every8d'` 時會落入 default → `LogDriver`，靜默吞掉所有 SMS 不報錯（尤其是 #A4-001 的 `disabled` 已是「靜默成功」設計，疊加 LogDriver 二次靜默更難察覺）。
**等級：** 🟡 Medium（admin 後台選擇 every8d 後 SMS 不發但無錯誤，運維人員會以為實際送出）
**建議方案：**
- Option A（推薦）：SmsService::getDriver() 補上 `'every8d' => new Every8dDriver()` case（單行修改，import 已在 line 6）
- Option B：移除 admin SystemControlController 的 every8d 選項與 DEV-004 §13.1 表格中的 every8d 行（若該 driver 暫不上線）
**推薦：** A（driver 已實作 + admin UI 已開放，補完 wiring 才是業務一致的方向）
**相關 issue:** > 與 #A4-001 同 root：SMS provider 切換的可觀測性不足

---

### Issue #A5-003
**Pass：** P2 請求 Payload, P11.3 規格缺漏
**規格位置：** docs/API-001_前台API規格書.md §2.1.1（line 152-164）
**規格內容：**
````
**請求參數：**
{
  "email": "user@example.com",
  "password": "SecurePassword123",
  "password_confirmation": "SecurePassword123",
  "nickname": "甜心寶貝",
  "gender": "female",
  "birth_date": "2001-05-15",
  "group": 2,
  "terms_accepted": true,
  "privacy_accepted": true,
  "anti_fraud_read": true
}
````
**程式碼位置：** `backend/app/Http/Controllers/Api/V1/AuthController.php:30-40`
**程式碼現況：**
```php
$formatValidator = Validator::make($input, [
    'email'            => ['required', 'email'],
    'password'         => ['required', 'string', 'min:8', 'confirmed'],
    'nickname'         => ['required', 'string', 'max:20'],
    'gender'           => ['required', 'in:male,female'],
    'birth_date'       => ['required', 'date', 'before:-18 years'],
    'phone'            => ['nullable', 'string', 'regex:/^09\d{8}$/'],
    'terms_accepted'   => ['required', 'accepted'],
    'privacy_accepted' => ['required', 'accepted'],
    'anti_fraud_read'  => ['required', 'accepted'],
]);
```
**差異說明：** spec sync e3df60e 從 §2.1.1 response 移除 `group` 欄位（line 173-184 user block 已無 group），但**請求 body 範例仍含 `"group": 2`**（line 160）。AuthController formatValidator 既無 group 規則，User::create() 也無寫入 group。新接前端的開發者讀規格會以為要傳 group。屬於 #A-004 的延續：spec 修一半，request 端漏修。
**等級：** 🟡 Medium（規格畫面與實際接點不符，會生產測試假陽性）
**建議方案：**
- Option A（推薦）：API-001 §2.1.1 request body 移除 `"group": 2`，與 response 同步移除 group 描述
- Option B：補一條 group rule + DB 欄位（成本高，且 #A-004 早建議移除 — 不採用）
**推薦：** A（與 spec sync 第一波同方向）
**相關 issue:** > 同根問題，亦見 #A-004（response 已修）

---

### Issue #A5-004（延續 #A-006）
**Pass：** P5 錯誤碼
**規格位置：** docs/API-001_前台API規格書.md §2.2.2.2（line 359）+ §2.3.2（line 498）+ §2.1.2（無錯誤碼表）
**規格內容：**
````
§2.2.2.2: { "success": false, "error": { "code": "OTP_INVALID", ... } }
§2.3.2:   { "success": false, "error": { "code": "1010",        ... } }
§2.1.2:   （無錯誤回應範例）
````
**程式碼位置：** `backend/app/Http/Controllers/Api/V1/AuthController.php:208-213`（login）+ `:451-472`（verifyPhoneConfirm）+ `:592-604`（resetPassword）
**程式碼現況：**
```php
// login (line 208-213)
'error' => [ 'code' => 'INVALID_CREDENTIALS', 'remaining' => $remaining ],

// verifyPhoneConfirm (line 453, 463, 471)
'error' => ['code' => '1021', 'message' => '驗證碼已過期或不存在...'],
'error' => ['code' => '1022', 'message' => '驗證失敗次數過多...'],
'error' => ['code' => '1023', 'message' => '驗證碼不正確', 'remaining' => max(0, 4 - $attempts)],

// resetPassword (line 594, 603)
'error' => ['code' => '1010', 'message' => '重設連結已失效，請重新申請'],
```
**差異說明：** code 有三種錯誤碼風格混用：(1) login 用語意字串 `INVALID_CREDENTIALS`、(2) verifyPhoneConfirm 用數字串 `'1021/1022/1023'`、(3) resetPassword 用數字串 `'1010'`。spec 也出現兩種風格 — §2.2.2.2 用語意字串 `OTP_INVALID`、§2.3.2 用 `'1010'`、§2.1.2 沒列。整體缺一致的「錯誤碼字典」。
**等級：** 🟡 Medium（前端 switch 易漏某一風格；新工程師讀規格無法預期實際格式）
**建議方案：**
- Option A（推薦）：制定統一字串語意碼（如 `OTP_INVALID` / `OTP_EXPIRED` / `OTP_LOCKED` / `RESET_TOKEN_INVALID` / `INVALID_CREDENTIALS`），同時改 code 與 spec。一次性表格列在 API-001 §1.2.4 錯誤格式之下
- Option B：保留 code 現狀，改 spec 對齊 code 三風格混用（不推薦，違反「錯誤碼集中管理」原則）
- Option C：先定 spec 字典 + 加 pre-merge-check 14r 守護，code 漸進式重構（兩階段）
**推薦：** A 或 C（二擇一，A 一次到位、C 對前端風險較低）
**相關 issue:** > 同根問題，亦見 #A-006、L-2、#A5-005

---

### Issue #A5-005（延續 L-2）
**Pass：** P5 錯誤碼
**規格位置：** docs/API-001_前台API規格書.md §2.2.2.2（line 357-360）
**規格內容：**
````
**驗證碼錯誤 (422)：**
{ "success": false, "error": { "code": "OTP_INVALID", "message": "驗證碼錯誤或已過期" } }
````
**程式碼位置：** `backend/app/Http/Controllers/Api/V1/AuthController.php:453, 463, 471`
**程式碼現況：**
```php
'error' => ['code' => '1021', 'message' => '驗證碼已過期或不存在，請重新發送'],
'error' => ['code' => '1022', 'message' => '驗證失敗次數過多，請重新發送驗證碼'],
'error' => ['code' => '1023', 'message' => '驗證碼不正確', 'remaining' => max(0, 4 - $attempts)],
```
**差異說明：** spec 規定 verifyPhoneConfirm 422 回應 `error.code='OTP_INVALID'`，但 code 三種失敗狀況分別回傳 '1021'（過期）/ '1022'（5 次鎖）/ '1023'（驗證碼不正確）。即便保留三狀態區分，也應使用語意字串（`OTP_EXPIRED` / `OTP_LOCKED` / `OTP_INVALID`）而非數字串。已連續四輪標示，未動。
**等級：** 🔵 Low（API 仍可被前端 switch 處理，但 UX 區分模糊）
**建議方案：** 同 #A5-004 Option A
**推薦：** A
**相關 issue:** > 同根問題，亦見 L-2（首次提出 2026-04-22）、#A5-004

---

### Issue #A5-006（延續 #A-007）
**Pass：** P6 認證中介層
**規格位置：** docs/DEV-004_後端架構與開發規範.md §3.1（路由規範 — 認證類端點建議 throttle）
**規格內容：**（規格未明訂 login throttle 數值，但 §3.1 路由規範要求認證類端點限流）
**程式碼位置：** `backend/routes/api.php:54`
**程式碼現況：**
```php
Route::post('/login', [AuthController::class, 'login']);  // ← 無 ->middleware('throttle:...')
```
**對比：**
```php
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:otp');
Route::post('/admin/auth/login', [AdminController::class, 'login'])->middleware('throttle:admin-login');
```
**差異說明：** 共用模板 `_common.md` 規則 C — 應用層 Cache lockout（line 175-204 5 次/email + 20 次/IP）只擋單一身份累積錯誤，無法擋分散式撞庫（同樣 IP 對不同 email 各試 4 次仍可繼續）。`/auth/register`、`/admin/auth/login` 都已加 throttle，唯獨用戶 `/auth/login` 無。
**等級：** 🔵 Low（Cache lockout 已大幅降低風險，但路由層 throttle 仍是業界 best practice）
**建議方案：**
- Option A（推薦）：`Route::post('/login', ...)->middleware('throttle:otp');`（同 forgot-password）— 單行修改
- Option B：新增 `RateLimiter::for('user-login')` 設定（如 30 次/分/IP）— 較精細
**推薦：** B（避免 OTP throttle 共用配額）
**相關 issue:** > 同根問題，亦見 #A-007（首次提出 2026-04-24）

---

### Issue #A5-007（延續 #A3-002）
**Pass：** P11.2 重複實作
**規格位置：** docs/DEV-004_後端架構與開發規範.md §13.1（line 980 「長度: 6 位數字」）
**程式碼位置（A）：** `backend/app/Http/Controllers/Api/V1/AuthController.php:125`（register Email OTP）
**程式碼現況（A）：**
```php
$verifyCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
```
**程式碼位置（B）：** `backend/app/Http/Controllers/Api/V1/AuthController.php:381`（resendVerification Email OTP）
**程式碼現況（B）：**
```php
$code = (string) random_int(100000, 999999);
```
**程式碼位置（C）：** `backend/app/Http/Controllers/Api/V1/AuthController.php:415`（verifyPhoneSend SMS OTP）
**程式碼現況（C）：**
```php
$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
```
**差異說明：** Pattern A、C 採「0–999999 + str_pad」（含「000123」前導零）；Pattern B 採「100000–999999」（無前導零）。輸出長度都是 6 位但分布不同 — A/C 模型有 1/10 機率產出含前導零的碼。前端 input 處理應已能容受兩者，但 OTP 隨機性測試（如 entropy 估算）會看到偏差。已連續多輪未修。
**等級：** 🔵 Low（行為差異對 UX 不可見，僅影響可預測性）
**建議方案：**
- Option A（推薦）：抽至 `App\Support\OtpGenerator::generate(int $length=6): string`，三處改 import
- Option B：在 AuthController 加 private helper `private function generateOtp(): string`，三處改呼叫
**推薦：** A（cross-class 復用，未來 PasswordResetController / 其他 OTP 端點也可用）
**相關 issue:** > 同根問題，亦見 #A3-002（首次提出 2026-04-27）

---

### Issue #A5-008（延續 #A4-003）
**Pass：** P11.2 重複實作
**程式碼位置（A）：** `backend/app/Http/Controllers/Api/V1/AuthController.php:510-520`
**程式碼現況（A）：**
```php
private function toE164(string $phone): string
{
    $phone = preg_replace('/[\s\-]/', '', $phone);
    if (str_starts_with($phone, '09')) {
        return '+886' . substr($phone, 1);
    }
    if (str_starts_with($phone, '+')) {
        return $phone;
    }
    return '+886' . ltrim($phone, '0');
}
```
**程式碼位置（B）：** `backend/app/Services/Sms/TwilioDriver.php:81-90`
**程式碼現況（B）：**
```php
private function toE164(string $phone): string
{
    $phone = preg_replace('/[\s\-]/', '', $phone);
    if (str_starts_with($phone, '09')) {
        return '+886' . substr($phone, 1);
    }
    if (str_starts_with($phone, '+')) {
        return $phone;
    }
    return '+886' . ltrim($phone, '0');
}
```
**差異說明：** 兩處邏輯字節級相同。MitakeDriver.php:73 還有 reverse 版（`+886` → `0`）。AuthController 在 verifyPhoneSend/Confirm 已將 phone E.164 化後傳給 SmsService → TwilioDriver 又轉一次（無害但浪費）。
**等級：** 🔵 Low
**建議方案：**
- Option A（推薦）：抽至 `App\Support\PhoneFormatter`，提供 `toE164(string $phone): string` 與 `toLocal(string $phone): string` 兩個 static helper
- Option B：移到 SmsService 層，AuthController 不再 normalize（會改變 cooldown / fail attempts cache key 形式 → 既有 cache 失效）
**推薦：** A（pure helper class 風險最低）
**相關 issue:** > 同根問題，亦見 #A4-003（首次提出 2026-04-29）

---

### Issue #A5-009（延續 M-2）
**Pass：** P11.3 規格缺漏（spec 內部分歧）
**規格位置（A）：** `docs/API-001_前台API規格書.md §2.2.3`（line 374-381）
**規格內容（A）：**
````
**上傳驗證照片：**
POST /api/v1/me/verification-photo/upload
Authorization: Bearer {access_token}
Content-Type: multipart/form-data

verification_photo: {file}
````
**規格位置（B）：** `docs/API-001_前台API規格書.md §16.4`（line 4252-4275）
**規格內容（B）：**
````
POST /api/v1/me/verification-photo/upload
Content-Type: application/json

**Request Body：**
{ "photo_url": "https://...", "random_code": "AB1C2D" }
````
**差異說明：** 同一端點兩處規格描述完全不同 — §2.2.3 摘要寫 multipart 直傳檔案，§16.4 詳述寫先 `POST /users/me/photos` 取得 URL 再 JSON body 提交。實際 code（VerificationPhotoController）依 §16.4 為準。連續兩輪標示，未修。
**等級：** 🔵 Low（spec 內部矛盾，實際接點以 §16.4 為準）
**建議方案：**
- Option A（推薦）：API-001 §2.2.3 縮為「詳見 §16.3/§16.4」一句話，移除 multipart 範例（與 §2.2.4 已採此格式）
- Option B：兩處同步更新為 JSON 兩步驟流程
**推薦：** A（避免重複；§2.2.4 已是這個寫法）
**相關 issue:** > 同根問題，亦見 M-2（首次提出 2026-04-22）

---

## 4. 行動優先序

| 優先 | 動作 | 對象 | 對應 Issue |
|---|---|---|---|
| P1 | spec sync 第二波 — §2.1.2 login / §2.1.4 logout 回應 code 改字串；§2.1.1 request body 移除 group；§2.2.3 縮為摘要引用 §16.4 | docs / PM | #A5-001 / #A5-003 / #A5-009 |
| P1 | SmsService::getDriver() 補 every8d case（單行修） | backend | #A5-002 |
| P2 | 制定統一錯誤碼字典 — 建議在 API-001 §1.2.4 之下加錯誤碼集中表，並把 code 中的 '1021/1022/1023/1010' 全部改字串語意碼 | backend + docs | #A5-004 / #A5-005 |
| P2 | login route 加 throttle middleware | backend | #A5-006 |
| P3 | 抽 OtpGenerator helper（三處 OTP 生成 unify） | backend | #A5-007 |
| P3 | 抽 PhoneFormatter helper（toE164 / toLocal） | backend | #A5-008 |

**集中標記（連續多輪推薦但未動）：**
- L-2 / #A-006 / #A5-004 / #A5-005（錯誤碼格式統一）— 已連續 4 輪推薦，建議排為單一 sprint 任務「錯誤碼字典化」
- #A-007 / #A5-006（login throttle）— 已連續 3 輪
- #A3-002 / #A5-007（OTP gen 統一）— 已連續 2 輪
- #A4-003 / #A5-008（toE164 抽 helper）— 已連續 2 輪

---

## 5. 下次 Audit 建議

- 第二波 spec sync 後，建議在 `bash scripts/pre-merge-check.sh` 加 14r/14s 守護：
  - 14r：grep API-001 §2.1.x response 不可出現 `"code": 200` 或 `"code": 201`（必須是字串語意碼）
  - 14s：grep API-001 §2.1.1 request body 不可出現 `"group"`（已決議移除）
- 制定錯誤碼字典後（建議放在 `docs/API-001_前台API規格書.md §1.2.4` 之下或 `docs/DEV-004 §X` 新章節），加 14t 守護：grep AuthController 不可出現 `'code' => '\d+'` 模式（強制字串語意碼）
- 新增 register/login PHPUnit Feature test，鎖 response 結構（assertJsonStructure 含 `code` 字串、`token`、`user.email_verified` boolean），未來 spec 漂移直接 CI 失敗
- 考慮把 SystemControlController 的 SMS provider 選項與 SmsService::getDriver() 的 match 用 PHP enum 統一管理（`SmsProvider::Disabled / Mitake / Twilio / Every8d`），避免 #A5-002 同類遺漏

---

## 附錄 A — P1 端點逐條檢查

| # | Method | 端點 | 路由存在 | Middleware | 狀態 | 備註 |
|---|---|---|---|---|---|---|
| 1 | POST | /auth/register | ✅ | throttle:register | ✅ | api.php:53 |
| 2 | POST | /auth/login | ✅ | （無） | ⚠️ | 缺 throttle middleware；#A5-006 |
| 3 | POST | /auth/refresh | ✅ 不存在 | — | ✅ | 規格已標未實作（§2.1.3）|
| 4 | POST | /auth/logout | ✅ | auth:sanctum | ✅ | api.php:64 |
| 5 | POST | /auth/verify-email | ✅ | （無） | ✅ | 公開端點，設計正確 |
| 6 | POST | /auth/resend-verification | ✅ | throttle:otp | ✅ | api.php:56 |
| 7 | POST | /auth/verify-phone/send | ✅ | auth:sanctum, throttle:otp | ✅ | api.php:70（A-001 已修）|
| 8 | POST | /auth/verify-phone/confirm | ✅ | auth:sanctum, throttle:otp | ✅ | api.php:71；錯誤碼 #A5-005 |
| 9 | POST | /me/verification-photo/request | ✅ | auth:sanctum | ✅ | api.php:244 |
| 10 | POST | /me/verification-photo/upload | ✅ | auth:sanctum | ✅ | api.php:245；spec 內部分歧 #A5-009 |
| 11 | GET | /me/verification-photo/status | ✅ | auth:sanctum | ✅ | api.php:246 |
| 12 | POST | /verification/credit-card/initiate | ✅ | auth:sanctum | ✅ | spec 已對齊（#A3-001 已修）|
| 13 | GET | /verification/credit-card/status | ✅ | auth:sanctum | ✅ | api.php:252 |
| 14 | POST | /verification/credit-card/callback | ✅ | （無） | ✅ | 公開端點，ECPay S2S |
| 15 | POST | /auth/forgot-password | ✅ | throttle:otp | ✅ | api.php:57 |
| 16 | POST | /auth/reset-password | ✅ | throttle:otp | ✅ | api.php:58-59 |
| 17 | POST | /me/change-password | ✅ | auth:sanctum, throttle:otp | ✅ | api.php:232-233 |

**補充：** `Route::match(['get','post'], 'verification/credit-card/return', ...)`（api.php:257）存在且 spec §2.2.4 已標明（#A4-004 已修）。

---

## 附錄 B — P4 業務規則對照

| # | 規則 | 規格值 | 實作值 | 出處 | 狀態 |
|---|---|---|---|---|---|
| 1 | 初始誠信分數 | 60 | `getConfig('credit_score_initial', 60)`；DB seed = '60' | AuthController:107 + SystemSettingsSeeder:119 | ✅ |
| 2 | Email 驗證 +5 | 5 | `getConfig('credit_add_email_verify', 5)` | AuthController:346 | ✅ |
| 3 | 手機驗證 +5 | 5 | `getConfig('credit_add_phone_verify', 5)` | AuthController:495 | ✅ |
| 4 | 男性 CC 驗證 +15 | 15 | `getConfig('credit_add_adv_verify_male', 15)` | CreditCardVerificationService:148 | ✅ |
| 5 | 女性照片驗證 +15 | 15 | `getConfig('credit_add_adv_verify_female', 15)` | Admin/VerificationController:90 | ✅ |
| 6 | Email OTP 長度 | 6 位 | register / verifyPhoneSend `str_pad(...,6,'0',STR_PAD_LEFT)`；resendVerification `random_int(100000,999999)` | AuthController:125, 381, 415 | ⚠️ Pattern 不一致（#A5-007） |
| 7 | Email OTP TTL | 600 秒 | `Cache::put("email_verification:{...}", ..., 600)` | AuthController:127, 382 | ✅ |
| 8 | 手機 OTP TTL | 300 秒 | `Cache::put($otpKey, $code, 300)` + `expires_in: 300` 回傳 | AuthController:419, 432 | ✅ |
| 9 | 手機 OTP 冷卻 | 60 秒 | `Cache::put($cooldownKey, true, 60)` | AuthController:421 | ✅ |
| 10 | 手機 OTP 失敗 5 次鎖 | 5 | `if ($attempts >= 5)` | AuthController:458 | ✅ |
| 11 | 註冊年齡下限 | 18 | `'birth_date' => ['before:-18 years']` | AuthController:35 | ✅ |
| 12 | reset token TTL | 60 分鐘 | `'expire' => 60`（config/auth.php:15） | config/auth.php | ✅ |
| 13 | SMS 文案時間 | 5 分鐘 | "5 分鐘內有效" | SmsService:17 | ✅ |

**邊界條件備注（P9）：**
- OTP 重複提交：60 秒 cooldown ✅（AuthController:407, 421）
- OTP 5 次後鎖定：✅（AuthController:458）
- Email 枚舉防護：forgot-password 不論 email 是否存在均回相同訊息 ✅（AuthController:547）
- Login 帳號鎖：5 次 email + 20 次 IP，5 分鐘 cooldown ✅（AuthController:177-204）
- 18 歲下限：`before:-18 years` ✅
- SMS provider=disabled：sendOtp 回 true 但不發 SMS ✅（已 spec 化於 DEV-004 §13.1）
- SMS provider=every8d：應導向 Every8dDriver 但實際落入 LogDriver ❌（#A5-002）
- 軟刪除 email/phone/nickname：register 時 `where('status', '!=', 'deleted')` 排除已刪除帳號 ✅（AuthController:61, 73, 85）

---

## 6. ✅ Symmetric（23 條）

1. POST /auth/register — throttle:register；request 中 9 個必填 + 1 nullable 全部對應 spec ✅
2. POST /auth/register response — `code='REGISTER_SUCCESS'` 字串、`token` Sanctum PAT、`user.email_verified` / `phone_verified` 兩 boolean，與 spec §2.1.1 line 167-188 完全對齊（#A-002/#A-003 spec sync 已修）✅
3. POST /auth/login — Cache lockout（5/email + 20/IP）+ token 簽發 ✅
4. POST /auth/logout — auth:sanctum，currentAccessToken()->delete() + FCM token 清除 ✅
5. GET /auth/me — auth:sanctum，回傳含 points_balance / stealth_until / subscription（API-001 §11.6 擴充）✅
6. POST /auth/verify-email — 公開端點，6 碼 OTP，CreditScore +5 觸發（AuthController:344-348）✅
7. POST /auth/resend-verification — throttle:otp，60 秒 cooldown（AuthController:365-372）✅
8. POST /auth/verify-phone/send — auth:sanctum + throttle:otp（A-001 已修），300 秒 OTP TTL ✅
9. POST /auth/verify-phone/confirm — auth:sanctum，5 次失敗鎖，wasChanged() 觸發 +5 加分 ✅
10. POST /me/verification-photo/request — auth:sanctum，限 female ✅
11. POST /me/verification-photo/upload — auth:sanctum，JSON body `photo_url + random_code`（§16.4 對齊）✅
12. GET /me/verification-photo/status — auth:sanctum，回傳 status/submitted_at/reviewed_at ✅
13. POST /verification/credit-card/initiate — auth:sanctum + 三道守門（gender=male / Lv1+ / 未驗證）+ aio_url+params 結構（spec §2.2.4 已對齊，#A3-001 已修）✅
14. GET /verification/credit-card/status — auth:sanctum ✅
15. POST /verification/credit-card/callback — 公開（ECPay S2S），CheckMacValue 在 UnifiedPaymentService 驗簽 ✅
16. GET|POST /verification/credit-card/return — Route::match 接受兩種 method（spec 已對齊，#A4-004 已修）✅
17. POST /auth/forgot-password — throttle:otp，email 枚舉防護，60 分鐘 token TTL ✅
18. POST /auth/reset-password — throttle:otp（M-3 已修），tokens().delete() 強制全裝置登出 ✅
19. POST /me/change-password — auth:sanctum + throttle:otp，bcrypt + 全裝置登出 ✅
20. 初始誠信分數 = 60（getConfig，可動態調整）✅
21. 四個驗證加分事件（email/phone +5、CC/photo +15）全部掛接 CreditScoreService ✅
22. 年齡下限 18 歲（`before:-18 years`），SMS 文案「5 分鐘內有效」（A2-009 / A3-003 已修）✅
23. SMS Provider gate（disabled / mitake / twilio）已 spec 化於 DEV-004 §13.1 並對齊 SmsService::getDriver()；UF-001 UF-02 SMS 跳過分支已 spec 化（#A4-001 / #A4-002 已修）✅

---

## Self-check

- [x] Header 含完整 40 字元 hash（`7787b7940ee45a1ad3ca5b078ef389084c660a09`）+ 規格來源 + Agent ID + 7 份前次稽核連結
- [x] 前次 24 條 issue 全部用「回歸判定方法」標明本輪狀態（已修 / 未修 / 部分修）
- [x] 17 個端點均在附錄 A
- [x] 13 條業務規則均在附錄 B
- [x] P11.1 / P11.2 / P11.3 三項皆有具體發現
- [x] 每個 issue 引用的程式碼是實際 view 出來的
- [x] 每個 grep 命令的輸出在報告或推理過程中至少出現一次
- [x] 每個 issue 都有 file:line + Option A/B（含 C 時）+ 推薦
- [x] Symmetric 23 條 ≥ 10
- [x] 報告檔名 `audit-A-20260501-claudecode.md`
- [x] git diff 只新增 docs/audits/ 下的單一檔案

---

## Errata 2026-05-01（Prompt 0-1 補登）

**追加觸發：** 修正執行 prompts §0-1 要求驗證 Gemini 報告對 PRD-001 §4.2.1 SMS TTL 的 🟠 High 結論。實際 view PRD 後確認 **Gemini 方向正確**，本輪 Symmetric 表第 22 條（SMS 文案 / OTP TTL 對齊）需局部修正：規格與程式碼對 **phone OTP** 不一致。新增 issue。

### Issue #A5-010
**Pass：** P4 業務規則 / P11.3 規格缺漏
**規格位置：** docs/PRD-001_MiMeet_約會產品需求規格書.md §4.2.1（line 210, 221）
**規格內容：**
```
210:  - **驗證碼系統**：隨機 6 位數字，**10 分鐘內有效**
...
221:  When 系統顯示隨機 6 位驗證碼（10 分鐘有效）
```
（line 210 為 §4.2.1「身份驗證」段落最後一句，未區分 email / phone / photo；line 221 在 Acceptance Criteria 中針對女性進階驗證的隨機碼。）
**程式碼位置：**
- `backend/app/Services/SmsService.php:17` — SMS 文案「5 分鐘內有效」
- `backend/app/Http/Controllers/Api/V1/AuthController.php:419` — `Cache::put($otpKey, $code, 300)`（phone OTP TTL = 300s）
- `backend/app/Http/Controllers/Api/V1/AuthController.php:127, 382` — Email OTP TTL = 600s
- `backend/app/Http/Controllers/Api/V1/VerificationPhotoController.php:42` — 女性驗證隨機碼 `now()->addMinutes(10)`（600s）
**差異說明：** PRD line 210「10 分鐘」是統一聲明，但實作分三組：
- Email OTP = 10 分鐘 ✅ 對齊
- 女性驗證隨機碼 = 10 分鐘 ✅ 對齊（line 221 也是這個）
- **手機 SMS OTP = 5 分鐘 ❌ 不對齊**
PRD 與 SmsService / AuthController phone OTP 衝突。Gemini 報告判定 🟠 High 方向正確（但細節有出入：實際是「PRD 一律寫 10 分鐘但 phone 為 5 分鐘」，並非「PRD 全部 10 分鐘 vs Code 全部 5 分鐘」）。
**等級：** 🟡 Medium（前次 Symmetric 結論需收回；Gemini 的 🟠 評級略偏高，因為 email/photo 都對齊，僅 phone 出入；用戶端不會看到 PRD，但 PM 與工程師讀規格會誤判）
**建議方案：**
- Option A（推薦）：PRD §4.2.1 line 210 改為：「**驗證碼系統**：隨機 6 位數字。**Email 驗證碼 10 分鐘有效；手機 SMS OTP 5 分鐘有效；女性進階驗證隨機碼 10 分鐘有效**。」放入 Prompt 1-2 spec sync 第二波 modification 6 一起執行。
- Option B：把手機 OTP TTL 改為 10 分鐘以對齊 PRD（不推薦：5 分鐘是 SMS 業界慣例，且 SmsService:17 簡訊文案、DEV-004 §13.1 已明確 5 分鐘）
**推薦：** A
**相關 issue:** > 同根問題，亦見 Gemini 報告中對 §4.2.1 的引用

### 對前次 Symmetric 表的修正

§6 第 22 條（「SMS 文案『5 分鐘內有效』...」）的判定範圍應收窄為「DEV-004 §13.1 + SmsService 實作對齊」；PRD-001 §4.2.1 的對齊狀態以 #A5-010 為準。

### 三方稽核交叉驗證結論

| 項目 | Claude Code | Codex | Gemini | 仲裁 |
|---|---|---|---|---|
| PRD §4.2.1 SMS TTL | ✅ Symmetric | ✅ Symmetric | 🟠 High | Gemini 對方向正確 → 列為 #A5-010 🟡 Medium |

Gemini 因方法論差異未附 git diff 證據（見執行 prompts 附錄 B），但本條結論可獨立透過 view PRD 與 view code 兩端原文驗證為真。本輪三方稽核合計 issue 數從 9 條更新為 **10 條**。

