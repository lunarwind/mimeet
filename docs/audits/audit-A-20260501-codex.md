# Audit Report A — 認證與身份驗證模組

**執行日期:** 2026-05-01  
**稽核者:** ChatGPT Codex（雲端）  
**Agent ID:** codex  
**規格來源:**
  - docs/API-001_前台API規格書.md §2 + §16.3/§16.4
  - docs/PRD-001_MiMeet_約會產品需求規格書.md §4.2.1
  - docs/DEV-008_誠信分數系統規格書.md §3 §4.1
  - docs/DEV-004_後端架構與開發規範.md §3.1 §13.1
  - docs/UF-001_用戶流程圖.md UF-01 / UF-02
  - docs/DEV-001_技術架構規格書.md §6.1
**程式碼基準（Local）:** 7787b7940ee45a1ad3ca5b078ef389084c660a09  
**前次稽核（不分 agent，全部都要讀）:**
  - docs/audits/audit-A-20260422.md
  - docs/audits/audit-A-20260424.md
  - docs/audits/audit-A-20260427.md
  - docs/audits/audit-A-20260427-codex.md
  - docs/audits/audit-A-20260427-claudecode.md
  - docs/audits/audit-A-20260428-codex.md
  - docs/audits/audit-A-20260429-claudecode.md
**總結:** 6 issues（🔴 0 / 🟠 1 / 🟡 2 / 🔵 3）+ 15 Symmetric

---

## 0. 前次 Issue 回歸狀態

`git diff 2c056ae4cf2cddd36513a1f86644944f2de2fd95..HEAD -- backend/app/Http/Controllers/Api/V1/AuthController.php backend/routes/api.php backend/app/Services/SmsService.php backend/app/Http/Controllers/Api/V1/CreditCardVerificationController.php frontend/src/views/public/RegisterView.vue frontend/src/api/verification.ts` 無輸出；本輪 auth 相關程式碼相對 2026-04-29 基準未變。差異集中在 docs：`docs/API-001_前台API規格書.md`、`docs/DEV-004_後端架構與開發規範.md`、`docs/UF-001_用戶流程圖.md`。

| Issue | 來源 | 前次等級 | 前次基準 | 本輪狀態 | 備註 |
|---|---|---|---|---|---|
| H-1 / /auth/refresh 未實作 | 20260422 | 🟠 High | ecff2f9 | ✅ 已修 | API-001 §2.1.3 已標未實作，路由無 refresh 一致 |
| H-2 / token 結構 | 20260422 | 🟠 High | ecff2f9 | ✅ 已修 | API-001 §2.1.1/2.1.2 已採 `token` |
| H-3 / CC 驗證未實作 | 20260422 | 🟠 High | ecff2f9 | ✅ 已修 | CC controller/routes 已存在 |
| M-1 / verify-phone 缺 auth | 20260422 | 🟡 Medium | ecff2f9 | ✅ 已修 | `routes/api.php:69-71` 有 `auth:sanctum` + `throttle:otp` |
| M-2 / verification-photo 規格矛盾 | 20260422 | 🟡 Medium | ecff2f9 | ✅ 已修 | API-001 §2.2.3 指向 §16.3/§16.4 |
| M-3 / reset-password 無 throttle | 20260422 | 🟡 Medium | ecff2f9 | ✅ 已修 | `routes/api.php:58-59` 有 `throttle:otp` |
| L-1 / register 合規欄位 | 20260422 | 🔵 Low | ecff2f9 | ✅ 已修 | `AuthController.php:37-39` 三欄 required/accepted |
| L-2 / OTP 錯誤碼 | 20260422 | 🔵 Low | ecff2f9 | ❌ 未修 | 規格 `OTP_INVALID`，code 仍 `1021/1022/1023` |
| #A-001 | 20260424 | 🔴 Critical | e82c698 | ✅ 已修 | verify-phone 群組有 auth |
| #A-002 | 20260424 | 🟠 High | e82c698 | ✅ 已修 | API-001 已改為 `REGISTER_SUCCESS` + `token`，移除 verification block |
| #A-003 | 20260424 | 🟠 High | e82c698 | ✅ 已修 | API-001 已說明 `status=active` 是帳號可用性 |
| #A-004 | 20260424 | 🟡 Medium | e82c698 | ❌ 未修 | API-001 request 仍列 `group`，FE/BE 不送不驗 |
| #A-005 | 20260424 | 🟡 Medium | e82c698 | ✅ 已修 | `password` 有 `confirmed` |
| #A-006 | 20260424 | 🟡 Medium | e82c698 | ❌ 未修 | error code schema 仍混用 |
| #A-007 | 20260424 | 🔵 Low | e82c698 | ❌ 未修 | login route 無 framework throttle；應用層 lock 存在 |
| #A-008 | 20260424 | 🔵 Low | e82c698 | ✅ 已修 | reset URL spec 已為 `mimeet.online/#/reset-password` |
| #A2-001 | codex 20260427 | 🟠 High | e2f7f5f | ✅ 已修 | 同 #A-002，spec 已同步 |
| #A2-002 | codex 20260427 | 🟠 High | e2f7f5f | ✅ 已修 | 同 #A-003，spec 已同步 |
| #A2-003 | codex 20260427 | 🟠 High | e2f7f5f | ✅ 已修 | API-001 §2.2.4 已改 `payment_id + aio_url + params` |
| #A2-004 | codex 20260427 | 🟠 High | e2f7f5f | ❌ 未修 | DEV-004 仍要求 `check.suspended`，code 無 alias/class |
| #A2-005 | codex 20260427 | 🟡 Medium | e2f7f5f | ❌ 未修 | 同 #A-004 |
| #A2-006 | codex 20260427 | 🟠 High | e2f7f5f | ❌ 未修 | OTP code 未統一；本輪降為 🟡，因行為可用但 contract 不一致 |
| #A2-007 | codex 20260427 | 🟡 Medium | e2f7f5f | ✅ 已修 | register/status spec sync 已落地 |
| #A2-008 | codex 20260427 | 🔵 Low | e2f7f5f | ❌ 未修 | 同 #A2-004，`CheckSuspended.php` 不存在 |
| #A2-009 | codex 20260427 | 🔵 Low | e2f7f5f | ✅ 已修 | `SmsService.php:17` 為 5 分鐘 |
| #A2-010 | codex 20260427 | 🔵 Low | e2f7f5f | ✅ 已修 | verification.ts status API export 已移除 |
| #A3-001 | 20260427/29 | 🟠/🟡 | e2f7f5f/f4e597f | ✅ 已修 | CC initiate response spec 已同步；與 0427 codex High 結論不同，本輪採最新 spec |
| #A3-002 | 20260427 claudecode | 🔵 Low | f4e597f | ❌ 未修 | Email OTP 仍兩種生成 pattern |
| #A3-003 | 20260427 claudecode | 🔵 Low | f4e597f | ✅ 已修 | UI-001 §8.2 已為 5 分鐘 |
| #A4-001 | 20260429 | 🟡 Medium | 2c056ae | ✅ 已修 | DEV-004 §13.1 已補 `sms.provider` disabled 行為 |
| #A4-002 | 20260429 | 🟡 Medium | 2c056ae | ✅ 已修 | UF-001 已標 SMS 手機驗證（選用）與稍後再驗證 |
| #A4-003 | 20260429 | 🔵 Low | 2c056ae | ❌ 未修 | `toE164` 仍重複於 AuthController/TwilioDriver |
| #A4-004 | 20260429 | 🔵 Low | 2c056ae | ✅ 已修 | API-001 已註明 OrderResultURL 支援 POST |

---

## 1. Pass 完成記錄

| Pass | 範圍 | Issues 發現 |
|---|---|---|
| P1 路由層 | 17 個規格端點 vs `backend/routes/api.php` | ✅ 除 `/auth/refresh` 規格標未實作外皆存在 |
| P2 請求 Payload | register / phone / reset / CC validate | #A5-002 |
| P3 回應結構 | register / CC initiate / OTP errors | #A5-003 |
| P4 業務規則 | 13 條數值規則 | ✅ 詳附錄 B |
| P5 錯誤碼 | OTP / login / reset | #A5-003 |
| P6 認證中介層 | `auth:sanctum` / `throttle` / suspended | #A5-001, #A5-006 |
| P7 前端 API 層 | `auth.ts` / `verification.ts` | #A5-002 |
| P8 前端 UI 層 | Register/Login/Forgot/Reset/Verify | ✅ SMS skip 已文件化 |
| P9 邊界條件 | OTP TTL/cooldown/attempts、age、soft delete | ✅ |
| P10 跨模組副作用 | Cache/Mail/SMS/CreditScore | ✅ |
| P11.1 死碼 | AuthController public methods + FE exports | ✅ 未見新增死碼 |
| P11.2 重複 | E.164、OTP、Mail、Verification modules | #A5-004, #A5-005 |
| P11.3 規格缺漏 | spec ↔ code 雙向缺漏 | #A5-001, #A5-002, #A5-003 |

**程式碼範圍檔案存在性（原樣）：**

```text
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

**關鍵 grep 輸出摘要：**

```text
grep -n "throttle" backend/app/Http/Kernel.php backend/routes/api.php | grep -iE "otp|login"
backend/routes/api.php:56 resend-verification -> throttle:otp
backend/routes/api.php:57 forgot-password -> throttle:otp
backend/routes/api.php:59 reset-password -> throttle:otp
backend/routes/api.php:69 verify-phone group -> auth:sanctum, throttle:otp
backend/routes/api.php:233 me/change-password -> throttle:otp
backend/routes/api.php:271 admin login -> throttle:admin-login

rg -n "CheckSuspended|check\.suspended" backend/
# 無輸出
```

---

## 2. Issues 索引（依等級排序）

### 🔴 Critical
（無）

### 🟠 High
- Issue #A5-001 — DEV-004 要求登入路由群組套 `check.suspended`，但後端沒有 middleware class / alias

### 🟡 Medium
- Issue #A5-002 — register request spec 仍要求 `group`，前端型別與後端 validator 均不存在
- Issue #A5-003 — 手機 OTP 錯誤碼規格 `OTP_INVALID` 與實作 `1021/1022/1023` 不一致

### 🔵 Low
- Issue #A5-004 — `toE164` 在 AuthController 與 TwilioDriver 重複實作
- Issue #A5-005 — Email OTP 生成存在兩種 pattern，resend 不產生前導零
- Issue #A5-006 — 前台 login route 仍無 framework-level throttle middleware

### ✅ Symmetric（15 條）
- `/auth/register` 成功回應：API-001 已同步為 `REGISTER_SUCCESS` + `data.token`。
- `/auth/register` status：API-001 已說明 `status=active` 是帳號可用性。
- `/auth/refresh`：規格標未實作，路由無此端點。
- `/auth/logout`：路由存在並在 `auth:sanctum` 群組。
- `/auth/verify-phone/send`：路由存在並掛 `auth:sanctum` + `throttle:otp`。
- `/auth/verify-phone/confirm`：路由存在並掛 `auth:sanctum` + `throttle:otp`。
- `/auth/forgot-password`、`/auth/reset-password`：皆有 `throttle:otp`。
- `/me/change-password`：存在、需登入、加 `throttle:otp`。
- 女性照片驗證三端點：request/upload/status 均存在於登入群組。
- 男性信用卡 initiate/status：存在於登入群組。
- 信用卡 callback/return：public callback 與 GET|POST return 已在 API-001 文件化。
- 初始誠信分數：`credit_score_initial` 預設 60。
- Email/Phone/CC/Photo 驗證加分：+5/+5/+15/+15 均有對應實作。
- SMS 文案與 TTL：文案 5 分鐘，phone OTP TTL 300 秒。
- SMS provider disabled：DEV-004 已文件化 `sendOtp()` 回 true 但不實際發送。

---

## 3. Issue 詳情

### Issue #A5-001
**Pass:** P6, P11.3  
**規格位置:** `docs/DEV-004_後端架構與開發規範.md:188`  
**規格內容:**
```text
188 // ── 需登入路由
189 Route::prefix('v1')->middleware(['auth:sanctum', 'check.suspended'])->group(function () {
190
191     // 當前用戶
192     Route::get('auth/me', [AuthController::class, 'me']);
```
**程式碼位置:** `backend/app/Http/Middleware/CheckSuspended.php`（不存在）；`backend/routes/api.php:62`  
**程式碼現況:**
```php
// ─── Auth (authenticated) ────────────────────────────────────────
Route::prefix('auth')->middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
```
**差異說明:** `rg -n "CheckSuspended|check\.suspended" backend/` 無輸出。login 時有檢查 suspended，但已核發 token 的使用者後續打其他登入路由不會經過規格要求的 route-level suspended middleware。  
**等級:** 🟠 High  
**建議方案:**
- Option A: 補 `CheckSuspended` middleware、Kernel alias，並套到所有 `auth:sanctum` 前台群組；優點是符合 DEV-004，缺點是需完整回歸登入後 API。
- Option B: 修 DEV-004，明確改為僅 login 檢查 + 前端 guard；優點是改動小，缺點是安全邊界弱。
**推薦:** A，因為停權應阻斷已核發 token 的後續 API。

### Issue #A5-002
**Pass:** P2, P7, P11.3  
**規格位置:** `docs/API-001_前台API規格書.md:151`  
**規格內容:**
```json
{
  "email": "user@example.com",
  "password": "SecurePassword123",
  "password_confirmation": "SecurePassword123",
  "nickname": "甜心寶貝",
  "gender": "female",
  "birth_date": "2001-05-15",
  "group": 2
}
```
**程式碼位置:** `backend/app/Http/Controllers/Api/V1/AuthController.php:30`; `frontend/src/api/auth.ts:57`  
**程式碼現況:**
```php
$formatValidator = Validator::make($input, [
    'email' => ['required', 'email'],
    'password' => ['required', 'string', 'min:8', 'confirmed'],
    'nickname' => ['required', 'string', 'max:20'],
    'gender' => ['required', 'in:male,female'],
    'birth_date' => ['required', 'date', 'before:-18 years'],
]);
```
```ts
export interface RegisterPayload {
  email: string
  password: string
  password_confirmation: string
  nickname: string
  gender: 'male' | 'female'
  birth_date: string
}
```
**差異說明:** `group` 仍存在於 API-001 register request，但前端 payload、後端 validator、User::create 都沒有此欄位。這會讓照規格串接的 client 以為 `group` 有業務效果。  
**等級:** 🟡 Medium  
**建議方案:**
- Option A: 從 API-001 §2.1.1 移除 register `group`，若代表社群可見性則移到 profile/social 模組。
- Option B: 後端補 validator 與資料欄位，前端補 UI；優點是規格保留，缺點是目前產品流程沒有入口。
**推薦:** A，因為既有前後端均未依賴此欄位。

### Issue #A5-003
**Pass:** P3, P5  
**規格位置:** `docs/API-001_前台API規格書.md:357`  
**規格內容:**
```json
{ "success": false, "error": { "code": "OTP_INVALID", "message": "驗證碼錯誤或已過期" } }
```
**程式碼位置:** `backend/app/Http/Controllers/Api/V1/AuthController.php:449`  
**程式碼現況:**
```php
if (!$stored) {
    return response()->json([
        'success' => false,
        'error' => ['code' => '1021', 'message' => '驗證碼已過期或不存在，請重新發送'],
    ], 422);
}
```
另 `AuthController.php:463` 回 `1022`，`AuthController.php:471` 回 `1023`。  
**差異說明:** 規格定義單一 semantic code `OTP_INVALID`，實作切成數字字串。前端若依規格判斷錯誤碼，會漏處理部分錯誤分支。  
**等級:** 🟡 Medium  
**建議方案:**
- Option A: API-001 改列 `1021/1022/1023` 的 meaning，保留目前細分。
- Option B: 後端統一回 `OTP_INVALID`，把細節放在 message/remaining。
**推薦:** A，目前實作資訊量較高且已穩定，應同步規格。

### Issue #A5-004
**Pass:** P11.2  
**規格位置:** 無直接規格；屬重複實作風險  
**規格內容:**
```text
P11.2 要求檢查 phone E.164 轉換重複實作。
```
**程式碼位置:** `backend/app/Http/Controllers/Api/V1/AuthController.php:510`; `backend/app/Services/Sms/TwilioDriver.php:81`  
**程式碼現況:**
```php
private function toE164(string $phone): string
{
    $phone = preg_replace('/[\s\-]/', '', $phone);
    if (str_starts_with($phone, '09')) {
        return '+886' . substr($phone, 1);
    }
```
**差異說明:** `rg -n "toE164|\+886|str_starts_with.*'09'" backend/app/` 顯示 AuthController 與 TwilioDriver 各自維護同一段台灣手機轉 E.164 邏輯，後續國碼規則調整容易漂移。  
**等級:** 🔵 Low  
**建議方案:**
- Option A: 抽到 `PhoneNumberNormalizer` service/value object，兩處共用。
- Option B: 保留重複，但在兩處加測試鎖住相同行為。
**推薦:** A，改動小且降低後續 SMS driver 差異。

### Issue #A5-005
**Pass:** P11.2  
**規格位置:** `docs/API-001_前台API規格書.md:196`  
**規格內容:**
```text
Email 驗證碼由後端非同步寄出，TTL 10 分鐘。
```
**程式碼位置:** `backend/app/Http/Controllers/Api/V1/AuthController.php:125`; `backend/app/Http/Controllers/Api/V1/AuthController.php:381`  
**程式碼現況:**
```php
$verifyCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
```
```php
$code = (string) random_int(100000, 999999);
```
**差異說明:** register path 允許前導零，resend path 不會產生前導零；兩者都是 6 碼但生成規則不同。這不是當前功能錯誤，但會讓測試資料與統計分佈不一致。  
**等級:** 🔵 Low  
**建議方案:**
- Option A: 抽 `generateOtpCode()`，兩處統一用 `str_pad(random_int(0, 999999), 6)`。
- Option B: 統一改成 `random_int(100000, 999999)`，並在規格註明第一位不為 0。
**推薦:** A，符合「6 位數」直覺並保留現有 register 行為。

### Issue #A5-006
**Pass:** P6  
**規格位置:** `docs/DEV-004_後端架構與開發規範.md:819`  
**規格內容:**
```text
| 帳號層 | 同一 email 失敗 5 次 | 5 分鐘 | login_fail_email:{email} |
| IP 層 | 同一 IP 失敗 20 次 | 5 分鐘 | login_fail_ip:{ip} |
```
**程式碼位置:** `backend/routes/api.php:52`  
**程式碼現況:**
```php
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'login']);
```
**差異說明:** AuthController 有 email/IP 應用層失敗鎖，但 login route 沒有 Laravel throttle middleware。依 `_common.md` 規則 C，應用層鎖與 route-level throttle 是不同防線。  
**等級:** 🔵 Low  
**建議方案:**
- Option A: 補 `throttle:login` 並保留現有 email/IP lock。
- Option B: 在 DEV-004 明確說 login 僅採應用層 lock，不使用 route throttle。
**推薦:** A，兩層防護並存較符合共用稽核規則。

---

## 4. 行動優先序

| 優先 | 動作 | 對象 |
|---|---|---|
| P1 | 補 `CheckSuspended` middleware 或正式修訂 DEV-004 安全邊界 | BE / 架構 |
| P2 | 做一次 spec sync：移除 register `group`、文件化 OTP 細分錯誤碼 | PM / BE |
| P3 | 統一 phone normalizer 與 OTP generator helper | BE |
| P4 | 決定 login 是否補 route throttle | BE / Security |

## 5. 下次 Audit 建議

- 下次先比對 docs 變更：本輪多數前次 issue 已由 spec sync 解掉。
- 若補 `CheckSuspended`，需用已登入但後續停權的 token 做回歸測試。
- OTP 錯誤碼若採 `1021/1022/1023`，請同步前端錯誤處理與 API-001 error code 表。

---

## 附錄 A — P1 端點逐條檢查

| # | 端點 | 路由存在 | Middleware | 狀態 | 備註 |
|---|---|---|---|---|---|
| 1 | POST /auth/register | ✅ | `throttle:register` | ✅ | `routes/api.php:53` |
| 2 | POST /auth/login | ✅ | 無 route throttle | ⚠️ | #A5-006 |
| 3 | POST /auth/refresh | ❌ | N/A | ✅ | 規格標未實作 |
| 4 | POST /auth/logout | ✅ | `auth:sanctum` | ✅ | `routes/api.php:63-64` |
| 5 | POST /auth/verify-email | ✅ | public | ✅ | `routes/api.php:55` |
| 6 | POST /auth/resend-verification | ✅ | `throttle:otp` | ✅ | `routes/api.php:56` |
| 7 | POST /auth/verify-phone/send | ✅ | `auth:sanctum`, `throttle:otp` | ✅ | `routes/api.php:69-70` |
| 8 | POST /auth/verify-phone/confirm | ✅ | `auth:sanctum`, `throttle:otp` | ✅ | `routes/api.php:69-71` |
| 9 | POST /me/verification-photo/request | ✅ | `auth:sanctum` | ✅ | `routes/api.php:243-244` |
| 10 | POST /me/verification-photo/upload | ✅ | `auth:sanctum` | ✅ | `routes/api.php:243-245` |
| 11 | GET /me/verification-photo/status | ✅ | `auth:sanctum` | ✅ | `routes/api.php:243-246` |
| 12 | POST /verification/credit-card/initiate | ✅ | `auth:sanctum` | ✅ | `routes/api.php:250-251` |
| 13 | GET /verification/credit-card/status | ✅ | `auth:sanctum` | ✅ | `routes/api.php:250-252` |
| 14 | POST /verification/credit-card/callback | ✅ | public | ✅ | `routes/api.php:255` |
| 15 | POST /auth/forgot-password | ✅ | `throttle:otp` | ✅ | `routes/api.php:57` |
| 16 | POST /auth/reset-password | ✅ | `throttle:otp` | ✅ | `routes/api.php:58-59` |
| 17 | POST /me/change-password | ✅ | `auth:sanctum`, `throttle:otp` | ✅ | `routes/api.php:231-233` |

## 附錄 B — P4 業務規則對照

| # | 規則 | 規格值 | 實作值 | 出處 | 狀態 |
|---|---|---|---|---|---|
| 1 | 初始誠信分數 | 60 | 60 | `SystemSettingsSeeder.php:119`, `AuthController.php:107` | ✅ |
| 2 | Email 驗證 +5 | 5 | 5 | `AuthController.php:345-346` | ✅ |
| 3 | 手機驗證 +5 | 5 | 5 | `AuthController.php:494-495` | ✅ |
| 4 | 男性 CC 驗證 +15 | 15 | 15 | `CreditCardVerificationService.php:148-149` | ✅ |
| 5 | 女性照片驗證 +15 | 15 | 15 | `Admin/VerificationController.php:90` | ✅ |
| 6 | Email OTP 長度 | 6 位 | 6 位 | `AuthController.php:125`, `AuthController.php:381` | ✅ |
| 7 | Email OTP TTL | 600 秒 | 600 秒 | `AuthController.php:127`, `AuthController.php:382` | ✅ |
| 8 | 手機 OTP TTL | 300 秒 | 300 秒 | `AuthController.php:419` | ✅ |
| 9 | 手機 OTP 冷卻 | 60 秒 | 60 秒 | `AuthController.php:406-421` | ✅ |
| 10 | 手機 OTP 失敗 5 次鎖 | 5 | 5 | `AuthController.php:458` | ✅ |
| 11 | 註冊年齡下限 | 18 | `before:-18 years` | `AuthController.php:35` | ✅ |
| 12 | reset token TTL | 60 分鐘 | 60 分鐘 | `backend/config/auth.php:15` | ✅ |
| 13 | SMS 文案時間 | 5 分鐘 | 5 分鐘 | `SmsService.php:17` | ✅ |

## 附錄 C — P11 掃描記錄

```text
grep -nE "public function" backend/app/Http/Controllers/Api/V1/AuthController.php
23 register; 163 login; 251 logout; 270 me; 324 verifyEmail; 355 resendVerification;
398 verifyPhoneSend; 436 verifyPhoneConfirm; 542 forgotPassword; 581 resetPassword; 630 changePassword

grep -nE "^export (async )?function|^export const|^export interface" frontend/src/api/auth.ts frontend/src/api/verification.ts
auth.ts exports login/logout/getMe/forgotPassword/resetPassword/register/verifyEmail/resendVerification/sendPhoneCode/verifyPhoneCode/changePassword;
verification.ts exports requestVerificationCode/uploadVerificationPhoto/getVerificationStatus/initiateCreditCardVerification

rg -n "toE164|\+886|str_starts_with.*'09'" backend/app/
AuthController.php:510-519 and Sms/TwilioDriver.php:81-90 duplicate E.164 conversion.

rg -n "random_int\(0, 999999\)|random_int\(100000, 999999\)" backend/app/
AuthController.php:125,381,415 show two OTP generation patterns.

rg -n "CheckSuspended|check\.suspended" backend/
# 無輸出
```

## Self-Check

- [x] Header 包含完整 commit hash、Agent ID、前次稽核連結
- [x] 前次 issue 已標明本輪狀態
- [x] 17 個規格端點全部出現在附錄 A
- [x] 13 條業務規則全部出現在附錄 B
- [x] P11.1 / P11.2 / P11.3 有具體發現
- [x] 每個 issue 有實際檔名:行號與程式碼片段
- [x] 每個 issue 有 Option A/B + 推薦
- [x] Symmetric ≥ 10
- [x] 報告檔名 `audit-A-20260501-codex.md`
- [x] 完成後以 `git status` / `git diff --stat` 確認：本次新增檔案僅本檔；工作樹另有既存未追蹤/刪除項目（`progress/index.html`、`AGENTS.md`、`Gemini.md`、`prompts/audit/audit-a-gemini.md`）未觸碰
