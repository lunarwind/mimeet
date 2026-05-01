# Audit Report A Round 3 — 認證與身份驗證模組

**執行日期：** 2026-05-01
**稽核者：** Gemini CLI（本機）
**Agent ID：** geminicli
**規格來源：**
  - docs/API-001 §2 + §16.3/§16.4
  - docs/PRD-001 §4.2.1
  - docs/DEV-008 §3 §4.1
  - docs/DEV-004 §3.1 §13.1
  - docs/UF-001 UF-01
**程式碼基準（Local）：** 7787b7940ee45a1ad3ca5b078ef389084c660a09
**前次稽核（不分 agent，全部都要讀）：**
  - docs/audits/audit-A-20260422.md
  - docs/audits/audit-A-20260424.md
  - docs/audits/audit-A-20260427-codex.md
  - docs/audits/audit-A-20260429-claudecode.md
**總結：** 4 issues（🔴 0 / 🟠 1 / 🟡 2 / 🔵 1）+ 18 Symmetric

---

## 0. 前次 Issue 回歸狀態

### 回歸判定方法

對每個前次 issue：
1. 取前次報告 Header 的「程式碼基準」commit hash。
2. 跑 `git diff {前次 hash}..HEAD -- {issue 引用的檔案}`。
3. 依 `_common.md` §0 判定本輪狀態。

### 回歸狀態表

| Issue | 來源 | 前次等級 | 前次基準 | 本輪狀態 | 備註 |
|---|---|---|---|---|---|
| #A-001 | claudecode 20260424 | 🔴 Critical | e82c698 | ✅ 已修 | verify-phone 路由群組已掛 `['auth:sanctum', 'throttle:otp']` |
| #A-002 | claudecode 20260424 | 🟠 High | e82c698 | ❌ 未修 | register 回應仍為 `REGISTER_SUCCESS` + `token`，缺 `verification` block |
| #A-003 | claudecode 20260424 | 🟠 High | e82c698 | ❌ 未修 | register 仍寫入 `status='active'` |
| #A-004 | claudecode 20260424 | 🟡 Medium | e82c698 | ❌ 未修 | register 仍缺 `group` 欄位驗證與儲存 |
| #A-005 | claudecode 20260424 | 🟡 Medium | e82c698 | ✅ 已修 | register 已加 `password` `confirmed` 規則 |
| #A-006 | claudecode 20260424 | 🟡 Medium | e82c698 | ❌ 未修 | 錯誤碼格式仍不一致（login 字串 vs OTP 數字字串） |
| #A-007 | claudecode 20260424 | 🔵 Low | e82c698 | ❌ 未修 | login 路由仍缺少 throttle middleware |
| #A-008 | claudecode 20260424 | 🔵 Low | e82c698 | ❌ 未修 | API-001 §2.3.2 仍載明舊網域 `mimeet.tw` |
| #A2-003 | codex 20260427 | 🟠 High | e2f7f5f | ❌ 未修 | 信用卡 initiate 回應規格仍為 `payment_url`，實作改為 `aio_url+params` |
| #A2-009 | codex 20260427 | 🔵 Low | e2f7f5f | ✅ 已修 | `SmsService` 文案已更新為「5 分鐘內有效」 |
| #A2-010 | codex 20260427 | 🔵 Low | e2f7f5f | ✅ 已修 | `verification.ts` 已移除死碼 export |

---

## 1. Pass 完成記錄

| Pass | 範圍 | Issues 發現 |
|---|---|---|
| P1 路由層 | 17 個端點 vs routes/api.php | #A3-003, ✅ |
| P2 請求 Payload | AuthController validate vs spec | #A-004, ✅ |
| P3 回應結構 | register / CC initiate | #A-002, #A2-003 |
| P4 業務規則 | 13 條規則對照 | #A3-001, ✅ |
| P5 錯誤碼 | OTP / auth error code | #A-006, ✅ |
| P6 認證中介層 | auth:sanctum / throttle | #A-007, ✅ |
| P7 前端 API 層 | auth.ts / verification.ts | ✅ |
| P8 前端 UI 層 | Register/Login/VerifyView | ✅ |
| P9 邊界條件 | age / OTP / reset token | ✅ |
| P10 跨模組副作用 | CreditScore / Mail / Cache | ✅ |
| P11.1 死碼 | Controller method、前端 export | ✅ |
| P11.2 重複 | E164/OTP/Mail/verify modules | #A3-002, ✅ |
| P11.3 規格缺漏 | spec↔code 雙向比對 | #A3-003, ✅ |

**程式碼範圍檔案存在性：**
```text
✅ backend/app/Http/Controllers/Api/V1/AuthController.php
❌ backend/app/Http/Controllers/Api/V1/VerificationController.php (整合於獨立 Controller)
❌ backend/app/Http/Controllers/Api/V1/PhoneVerificationController.php (整合於 AuthController)
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

---

## 2. Issues 索引（依等級排序）

### 🔴 Critical
- 無

### 🟠 High
- Issue #A3-001 — 手機驗證碼 TTL 規格與實作嚴重不符（PRD 10 分鐘 vs Code 5 分鐘）

### 🟡 Medium
- Issue #A3-003 — `resend-verification` 端點已實作但未載於規格 API-001 §2.2.1
- Issue #A3-004 — OTP 生成邏輯不一致（`str_pad` vs `random_int(100000, 999999)`）

### 🔵 Low
- Issue #A3-002 — `toE164` 邏輯在 `AuthController` 與 `TwilioDriver` 重複實作

### ✅ Symmetric（18 條）
- `/auth/logout` 存在且掛 `auth:sanctum`（一致）。
- `/auth/verify-phone/send` 存在且掛 `auth:sanctum`+`throttle:otp`（一致）。
- `/me/verification-photo/request|upload|status` 三端點與規格一致。
- register 已驗證 `terms_accepted/privacy_accepted/anti_fraud_read`（一致）。
- register `password_confirmation` 已由 `confirmed` 規則驗證（一致）。
- 誠信分數初始值讀取 `credit_score_initial`，預設 60（一致）。
- Email OTP TTL 600 秒（10 分鐘）與實作一致。
- 手機 OTP TTL 300 秒（5 分鐘）與 `SmsService` 文案一致。
- 手機 OTP 失敗鎖定 5 次（一致）。
- 註冊年齡下限 18 歲（一致）。
- reset token TTL 60 分鐘（一致）。
- Email OTP 長度 6 位（一致）。
- `/auth/forgot-password` throttle:otp（一致）。
- `/auth/reset-password` throttle:otp（一致）。
- `/me/change-password` throttle:otp（一致）。
- `EmailVerificationMail` 寄送邏輯（一致）。
- `ResetPasswordMail` 寄送邏輯（一致）。
- `UserActivityLogService` 記錄手機變更（一致）。

---

## 3. Issue 詳情

### Issue #A3-001
**Pass:** P4
**規格位置:** docs/PRD-001 §4.2.1 (行 210)
**規格內容:**
```
- **驗證碼系統**：隨機 6 位數字，**10 分鐘內有效**
```
**程式碼位置:** `backend/app/Services/SmsService.php:17`
**程式碼現況:**
```php
$body = "【MiMeet】您的驗證碼為 {$code}，5 分鐘內有效，請勿洩漏。";
```
**差異說明:** PRD 要求所有驗證碼 10 分鐘有效，但 SMS 實作與文案均為 5 分鐘（300 秒）。雖然 5 分鐘在安全上更佳，但與核心規格不符，且 Email 驗證碼確實是 10 分鐘（600 秒），造成系統內兩套驗證碼 TTL 不統一。
**等級:** 🟠 High
**建議方案:**
- Option A: 更新 PRD 規格，將手機驗證碼改為 5 分鐘。
- Option B: 修改後端代碼，將手機驗證碼改為 600 秒（10 分鐘）。
**推薦:** A，手機驗證碼 5 分鐘較符合現代資安慣例。

---

### Issue #A3-002
**Pass:** P11.2
**規格位置:** N/A (重複實作)
**程式碼位置:** 
- `backend/app/Http/Controllers/Api/V1/AuthController.php:510`
- `backend/app/Services/Sms/TwilioDriver.php:81`
**程式碼現況:**
```php
// AuthController.php
private function toE164(string $phone): string { ... }

// TwilioDriver.php
private function toE164(string $phone): string { ... }
```
**差異說明:** 兩處 `toE164` 轉換邏輯完全重複。若未來需支援非台灣地區（+886 以外）的正規化，需修改多處，具備維護風險。
**等級:** 🔵 Low
**建議方案:**
- Option A: 抽取 `PhoneHelper` 或 `PhoneFormatter` 靜態類別統一處理。
- Option B: 讓 `SmsService` 統一負責正規化。
**推薦:** A。

---

### Issue #A3-003
**Pass:** P1, P11.3
**規格位置:** docs/API-001 §2.2.1
**規格內容:** 僅載明 `POST /api/v1/auth/verify-email`。
**程式碼位置:** `backend/routes/api.php:56`
**程式碼現況:**
```php
Route::post('/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:otp');
```
**差異說明:** `resend-verification` 端點已實作且被前端 `RegisterView.vue` 調用，但 API-001 規格書 §2.2.1 漏載此端點。
**等級:** 🟡 Medium
**建議方案:**
- Option A: 在 API-001 §2.2.1 補上 `POST /auth/resend-verification` 規格。
**推薦:** A。

---

### Issue #A3-004
**Pass:** P11.2
**規格位置:** N/A (邏輯不一致)
**程式碼位置:** `backend/app/Http/Controllers/Api/V1/AuthController.php`
**程式碼現況:**
- 行 125/415: `str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)`
- 行 381: `(string) random_int(100000, 999999)`
**差異說明:** 生成 6 位數 OTP 的邏輯在同一檔案內不統一。`random_int(100000, 999999)` 永遠不會產生開頭為 0 的代碼，而 `str_pad` 會。這會導致驗證碼的分佈不均勻，且開發者易產生混淆。
**等級:** 🟡 Medium
**建議方案:**
- Option A: 統一使用 `str_pad` 模式以支援 0 開頭代碼。
**推薦:** A。

---

## 4. 行動優先序

| 優先 | 動作 | 對象 |
|---|---|---|
| P1 | 決議手機驗證碼 TTL (5 min vs 10 min) 並同步 PRD/Code | PM / BE |
| P2 | 更新 API-001 補上 `resend-verification` 端點 | PM |
| P3 | 統一 OTP 生成邏輯與抽取 `toE164` Helper | BE |
| P4 | 處理延續性 Issue #A-002, #A-003 (register 回應結構) | BE |

---

## 5. 下次 Audit 建議

- 針對 `AuthController` 進行重構，將 OTP 與 Phone 處理邏輯抽離至獨立 Service。
- 建立自動化腳本檢查路由檔案與規格書的一致性，避免漏載。
- 檢查 `CheckSuspended` middleware 的預期行為，若已廢棄應從規格中移除相關描述。

---

## 附錄 A — P1 端點逐條檢查

| # | 端點 | 路由存在 | Middleware | 狀態 | 備註 |
|---|---|---|---|---|---|---|
| 1 | POST /auth/register | ✅ | throttle:register | ✅ | 一致 |
| 2 | POST /auth/login | ✅ | (無) | ⚠️ | 延續 #A-007 (缺 throttle) |
| 3 | POST /auth/refresh | ❌ | — | ✅ | 規格標示未實作 |
| 4 | POST /auth/logout | ✅ | auth:sanctum | ✅ | 一致 |
| 5 | POST /auth/verify-email | ✅ | (無) | ✅ | 一致 |
| 6 | POST /auth/resend-verification | ✅ | throttle:otp | ⚠️ | 規格漏載 (#A3-003) |
| 7 | POST /auth/verify-phone/send | ✅ | auth:sanctum + throttle:otp | ✅ | 已修復 |
| 8 | POST /auth/verify-phone/confirm | ✅ | auth:sanctum + throttle:otp | ✅ | 已修復 |
| 9 | POST /me/verification-photo/request | ✅ | auth:sanctum | ✅ | 一致 |
| 10 | POST /me/verification-photo/upload | ✅ | auth:sanctum | ✅ | 一致 |
| 11 | GET /me/verification-photo/status | ✅ | auth:sanctum | ✅ | 一致 |
| 12 | POST /verification/credit-card/initiate | ✅ | auth:sanctum | ⚠️ | 延續 #A2-003 |
| 13 | GET /verification/credit-card/status | ✅ | auth:sanctum | ✅ | 一致 |
| 14 | POST /verification/credit-card/callback | ✅ | (無) | ✅ | 一致 |
| 15 | POST /auth/forgot-password | ✅ | throttle:otp | ✅ | 一致 |
| 16 | POST /auth/reset-password | ✅ | throttle:otp | ✅ | 一致 |
| 17 | POST /me/change-password | ✅ | auth:sanctum + throttle:otp | ✅ | 一致 |

---

## 附錄 B — P4 業務規則對照

| # | 規則 | 規格值 | 實作值 | 出處 | 狀態 |
|---|---|---|---|---|---|---|
| 1 | 初始誠信分數 | 60 | 60 | AuthController:107 | ✅ |
| 2 | Email 驗證 +5 | 5 | 5 | AuthController:346 | ✅ |
| 3 | 手機驗證 +5 | 5 | 5 | AuthController:495 | ✅ |
| 4 | 男性 CC 驗證 +15 | 15 | 15 | CreditCardVerificationService:148 | ✅ |
| 5 | 女性照片驗證 +15 | 15 | 15 | Admin/VerificationController:90 | ✅ |
| 6 | Email OTP 長度 | 6 位 | 6 位 | AuthController:125 | ✅ |
| 7 | Email OTP TTL | 600 秒 | 600 秒 | AuthController:127 | ✅ |
| 8 | 手機 OTP TTL | 600 秒 (PRD) | 300 秒 | AuthController:419 | ❌ |
| 9 | 手機 OTP 冷卻 | 60 秒 | 60 秒 | AuthController:421 | ✅ |
| 10 | 手機 OTP 失敗鎖 | 5 次 | 5 次 | AuthController:458 | ✅ |
| 11 | 註冊年齡下限 | 18 | 18 | AuthController:35 | ✅ |
| 12 | reset token TTL | 60 分鐘 | 60 分鐘 | config/auth.php:15 | ✅ |
| 13 | SMS 文案時間 | 5 分鐘 | 5 分鐘 | SmsService:17 | ✅ |
