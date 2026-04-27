# Audit Report A — 認證與身份驗證模組

**執行日期:** 2026-04-27
**稽核者:** ChatGPT Codex（雲端）
**Agent ID:** codex
**規格來源:**
  - docs/API-001_前台API規格書.md §2、§16.3、§16.4
  - docs/PRD-001_MiMeet_約會產品需求規格書.md §4.2.1
  - docs/DEV-004_後端架構與開發規範.md §3.1、§13.1
  - docs/DEV-008_誠信分數系統規格書.md §3、§4.1
  - docs/UF-001_用戶流程圖.md UF-01/UF-02
  - docs/DEV-001_技術架構規格書.md §6.1
**程式碼基準（Local）:** e2f7f5f1c3cf33beb0e5d2c9e840846cdeb76f91
**前次稽核（不分 agent,全部都要讀）:**
  - docs/audits/audit-A-20260422.md
  - docs/audits/audit-A-20260424.md
  - docs/audits/audit-A-20260427.md
**總結:** 5 issues（🔴 0 / 🟠 2 / 🟡 3 / 🔵 0）+ 12 Symmetric

---

## 0. 前次 Issue 回歸狀態（若存在前次稽核）

### 回歸狀態表

| Issue | 來源 | 前次等級 | 前次基準 | 本輪狀態 | 備註 |
|---|---|---|---|---|---|
| #A-001 | 20260424 | 🔴 | e82c698 | ✅ 已修 | `verify-phone` 現在有 `auth:sanctum`+`throttle:otp` |
| #A-002 | 20260424 | 🟠 | e82c698 | ❌ 未修 | register 回應仍為 `REGISTER_SUCCESS` + `token`，缺 `verification` |
| #A-003 | 20260424 | 🟠 | e82c698 | ❌ 未修 | register 仍寫入 `status='active'` |
| #A-004 | 20260424 | 🟡 | e82c698 | ❌ 未修 | spec 仍要求 `group`，後端未驗證/儲存 |
| #A-005 | 20260424 | 🟡 | e82c698 | ✅ 已修 | register 已加 `password confirmed` |
| #A-006 | 20260424 | 🟡 | e82c698 | ❌ 未修 | OTP error code 仍為 `1021/1022/1023` 非 `OTP_INVALID` |
| #A-007 | 20260424 | 🔵 | e82c698 | ✅ 已修 | DEV-004 已明確 login 由應用層鎖控（非路由 throttle） |
| #A-008 | 20260424 | 🔵 | e82c698 | ✅ 已修 | forgot-password 預設網域已非舊 `mimeet.tw` |
| #A2-009 | 20260427 | 🔵 | (未記錄) | ✅ 已修 | `SmsService` 文案已為 5 分鐘 |
| #A2-010 | 20260427 | 🔵 | (未記錄) | ✅ 已修 | `verification.ts` 已移除死碼 status API export |
| #A2-001~#A2-008 | 20260427 | — | — | ⚠️ 無法判定 | `audit-A-20260427.md` 僅含 #A2-009/#A2-010，未見 001~008 編號 |

---

## 1. Pass 完成記錄

| Pass | 範圍 | Issues 發現 |
|---|---|---|
| P1 路由層 | 17 個端點 vs Local routes | #A3-005, ✅ |
| P2 請求 Payload | AuthController validate vs spec | #A3-003 |
| P3 回應結構 | register / credit-card initiate | #A3-001, #A3-005 |
| P4 業務規則 | 13 條規則對照 | ✅ |
| P5 錯誤碼 | OTP / auth error code | #A3-004 |
| P6 認證中介層 | auth:sanctum / throttle | ✅ |
| P7 前端 API 層 | auth.ts / verification.ts | #A3-001, #A3-005 |
| P8 前端 UI 層 | Register/Login/Forgot/VerifyView | ✅ |
| P9 邊界條件 | age / OTP / reset token | ✅ |
| P10 跨模組副作用 | CreditScore / Mail / Cache | #A3-002 |
| P11.1 死碼 | Controller method、前端 export | ✅ |
| P11.2 重複 | E164/OTP/Mail/verify modules | ✅ |
| P11.3 規格缺漏 | spec↔code 雙向比對 | #A3-003, #A3-005 |

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

---

## 2. Issues 索引（依等級排序）

### 🔴 Critical
- 無

### 🟠 High
- Issue #A3-001 — register 回應結構仍未與 API-001 §2.1.1 對齊
- Issue #A3-005 — 信用卡 initiate 回應規格仍為 `payment_url`，實作改為 `aio_url+params`

### 🟡 Medium
- Issue #A3-002 — register `status=active` 與規格 `pending_verification` 不一致
- Issue #A3-003 — 規格仍要求 `group`，但實作與前端未提供
- Issue #A3-004 — 手機 OTP 錯誤碼規格 `OTP_INVALID` 與實作三碼分流不一致

### 🔵 Low
- 無

### ✅ Symmetric（至少 10 條）
- `/auth/refresh` 規格標示未實作，路由亦無此端點（一致）。
- `/auth/logout` 存在且掛 `auth:sanctum`（一致）。
- `/auth/verify-phone/send` 存在且掛 `auth:sanctum`+`throttle:otp`（一致）。
- `/auth/verify-phone/confirm` 存在且掛 `auth:sanctum`+`throttle:otp`（一致）。
- `/me/verification-photo/request|upload|status` 三端點與規格一致。
- `/verification/credit-card/callback` 為 public callback，符合金流回呼模式。
- register 已驗證 `terms_accepted/privacy_accepted/anti_fraud_read`（一致）。
- register `password_confirmation` 已由 `confirmed` 規則驗證（一致）。
- 誠信分數初始值讀取 `credit_score_initial`，預設 60（一致）。
- Email OTP TTL 600、Phone OTP TTL 300、cooldown 60、失敗鎖 5（一致）。
- reset token TTL 60 分鐘（`auth.php expire=60` + controller 檢查）一致。
- SMS 文案已為「5 分鐘內有效」（與 DEV-004 §13.1 一致）。

---

## 3. Issue 詳情

### Issue #A3-001
**Pass:** P3, P7
**規格位置:** docs/API-001_前台API規格書.md §2.1.1
**規格內容:**
````
"code": 201,
"status": "pending_verification",
"verification": {
  "email_sent": true,
  "expires_at": "2024-12-20T11:30:00Z"
}
````
**程式碼位置:** `backend/app/Http/Controllers/Api/V1/AuthController.php:140-159`
**程式碼現況:**
```php
return response()->json([
    'success' => true,
    'code' => 'REGISTER_SUCCESS',
    'message' => '註冊成功，請驗證信箱。',
    'data' => [
        'user' => [ ... 'status' => $user->status, ... ],
        'token' => $token,
    ],
], 201);
```
**差異說明:** 規格要求 `code=201` 且有 `verification` 區塊，實作改為語意碼+直接發 token；前端 `auth.ts` 也已依賴 token。屬於「規格落後實作」的合約漂移。
**等級:** 🟠
**建議方案:**
- Option A: 更新 API-001 §2.1.1 回應範例為目前真實結構（pros: 低風險；cons: 需同步歷史文件）
- Option B: 回改後端為舊規格結構（pros: 符合舊文檔；cons: 破壞現有前端流程）
- Option C: 同時支援 `token` 與 `verification`（pros: 相容過渡；cons: 回應更複雜）
**推薦:** A，理由：前後端已生產使用 `token` 形態，改 spec 成本最低。

---

### Issue #A3-002
**Pass:** P4, P10
**規格位置:** docs/API-001_前台API規格書.md §2.1.1
**規格內容:**
````
"status": "pending_verification"
````
**程式碼位置:** `backend/app/Http/Controllers/Api/V1/AuthController.php:99-109`
**程式碼現況:**
```php
$user = User::create([
    'email' => $input['email'],
    ...
    'credit_score' => \App\Services\CreditScoreService::getConfig('credit_score_initial', 60),
    'status' => 'active',
]);
```
**差異說明:** 新註冊帳號直接 active，與規格期望 `pending_verification` 不同；雖有 email/phone verified 旗標，仍造成狀態語意不一致。
**等級:** 🟡
**建議方案:**
- Option A: 改規格為 `status=active`、以 verified flags 作為驗證真值（pros: 對齊現況；cons: 需補文字說明）
- Option B: 改程式碼回 `pending_verification` 並補登入閘門（pros: 嚴格驗證流；cons: 變更面大）
**推薦:** A，理由：目前整體流程已依賴 verified flags。
**相關 issue:** > 同根問題,亦見 #A3-001

---

### Issue #A3-003
**Pass:** P2, P11.3
**規格位置:** docs/API-001_前台API規格書.md §2.1.1（request body）
**規格內容:**
````
"group": 2,
"terms_accepted": true,
"privacy_accepted": true,
"anti_fraud_read": true
````
**程式碼位置:** `backend/app/Http/Controllers/Api/V1/AuthController.php:30-40`
**程式碼現況:**
```php
$formatValidator = Validator::make($input, [
    ...
    'terms_accepted'   => ['required', 'accepted'],
    'privacy_accepted' => ['required', 'accepted'],
    'anti_fraud_read'  => ['required', 'accepted'],
]);
```
**差異說明:** 三個合規欄位已實作，但 `group` 仍只存在於規格，後端與前端皆未送/存；屬規格多寫欄位。
**等級:** 🟡
**建議方案:**
- Option A: 在規格註記 `group` 為 Phase 2 或移除（pros: 清晰；cons: 需 PM 確認）
- Option B: 補後端/前端 `group` 欄位完整流程（pros: 完整功能；cons: 需資料模型與 UI 一併改）
**推薦:** A，理由：目前代碼無任何 group 業務路徑，先修規格較務實。

---

### Issue #A3-004
**Pass:** P5
**規格位置:** docs/API-001_前台API規格書.md §2.2.2.2
**規格內容:**
````
{ "success": false, "error": { "code": "OTP_INVALID", "message": "驗證碼錯誤或已過期" } }
````
**程式碼位置:** `backend/app/Http/Controllers/Api/V1/AuthController.php:450-472`
**程式碼現況:**
```php
if (!$stored) {
    return response()->json(['success' => false,'error' => ['code' => '1021', ...]], 422);
}
...
if ($stored !== $inputCode) {
    ... 'error' => ['code' => '1023', 'message' => '驗證碼不正確', ...]
}
```
**差異說明:** 規格為單一錯誤碼 `OTP_INVALID`，實作拆為 1021/1022/1023 三種情境碼；前端目前未統一映射此差異。
**等級:** 🟡
**建議方案:**
- Option A: 規格補充錯誤碼分流表（pros: 保留診斷能力；cons: 規格較長）
- Option B: 後端統一回 `OTP_INVALID`（pros: 合約簡單；cons: 遺失細粒度）
**推薦:** A，理由：細分錯誤碼對風控與客服排障更有用。

---

### Issue #A3-005
**Pass:** P1, P3, P7, P11.3
**規格位置:** docs/API-001_前台API規格書.md §2.2.4
**規格內容:**
````
"data": {
  "order_no": "CCV_...",
  "payment_url": "https://payment-stage.ecpay.com.tw/..."
}
````
**程式碼位置:** `backend/app/Http/Controllers/Api/V1/CreditCardVerificationController.php:73-79`
**程式碼現況:**
```php
return response()->json([
    'success' => true,
    'data' => [
        'payment_id' => $result['payment']->id,
        'aio_url'    => $result['aio_url'],
        'params'     => $result['params'],
    ],
]);
```
**差異說明:** 規格仍寫 `payment_url` 單欄位跳轉，但實作/前端改為 ECPay AIO `aio_url + params` 提交型態，合約已漂移。
**等級:** 🟠
**建議方案:**
- Option A: 更新 API-001 §2.2.4 為 `aio_url+params`（pros: 對齊現況；cons: 需補前端提交流程示例）
- Option B: 回改後端輸出 `payment_url`（pros: 規格簡單；cons: 需改 UnifiedPayment 並可能影響簽章）
- Option C: 同時提供兩種欄位過渡（pros: 向後相容；cons: 技術債）
**推薦:** A，理由：現行流程已以 AIO 參數運作。

---

## 4. 行動優先序

| 優先 | 動作 | 對象 |
|---|---|---|
| P1 | 同步 API-001：register 回應與 credit-card initiate 回應 | PM / 架構 / BE |
| P2 | 釐清 `status` 欄位語意（active vs pending_verification）並寫入規格 | PM / BE |
| P3 | OTP 錯誤碼規格化（統一碼或分流碼表） | BE / FE |
| P4 | 決議 `group` 欄位去留（移除或 Phase2） | PM |

---

## 5. 下次 Audit 建議

- 將 `docs/audits/audit-A-20260427.md` 補齊「程式碼基準 hash」欄位，避免回歸判定歧義。
- 對 API-001 進行一次「實際回應 contract 自動抽樣」批次校準，避免同類文檔漂移。
- 若決定保留 OTP 分流碼，建議在前端加錯誤碼 mapping（1021/1022/1023→對應提示）。

---

## 附錄 A — P1 端點逐條檢查（每個 audit 必附）

| # | 端點 | 路由存在 | Middleware | 狀態 | 備註 |
|---|---|---|---|---|---|
| 1 | POST /auth/register | ✅ | throttle:register | ✅ | 存在 |
| 2 | POST /auth/login | ✅ | 無 | ✅ | login 保護改用應用層鎖 |
| 3 | POST /auth/refresh | ❌ | — | ✅ | 規格已標未實作 |
| 4 | POST /auth/logout | ✅ | auth:sanctum | ✅ | 存在 |
| 5 | POST /auth/verify-email | ✅ | 公開 | ✅ | 存在 |
| 6 | POST /auth/resend-verification | ✅ | throttle:otp | ✅ | 存在 |
| 7 | POST /auth/verify-phone/send | ✅ | auth:sanctum + throttle:otp | ✅ | Round1 critical 已修 |
| 8 | POST /auth/verify-phone/confirm | ✅ | auth:sanctum + throttle:otp | ✅ | Round1 critical 已修 |
| 9 | POST /me/verification-photo/request | ✅ | auth:sanctum | ✅ | 存在 |
| 10 | POST /me/verification-photo/upload | ✅ | auth:sanctum | ✅ | 存在 |
| 11 | GET /me/verification-photo/status | ✅ | auth:sanctum | ✅ | 存在 |
| 12 | POST /verification/credit-card/initiate | ✅ | auth:sanctum | ⚠️ | 回應結構與規格不符（見 #A3-005） |
| 13 | GET /verification/credit-card/status | ✅ | auth:sanctum | ✅ | 存在 |
| 14 | POST /verification/credit-card/callback | ✅ | public callback | ✅ | 合理 |
| 15 | POST /auth/forgot-password | ✅ | throttle:otp | ✅ | 存在 |
| 16 | POST /auth/reset-password | ✅ | throttle:otp | ✅ | 存在 |
| 17 | POST /me/change-password | ✅ | auth:sanctum + throttle:otp | ✅ | 存在 |

---

## 附錄 B — P4 業務規則對照（每個 audit 必附）

| # | 規則 | 規格值 | 實作值 | 出處 | 狀態 |
|---|---|---|---|---|---|
| 1 | 初始誠信分數 | 60 | `getConfig(...,60)` | AuthController:107 | ✅ |
| 2 | Email 驗證 +5 | +5 | `credit_add_email_verify` default 5 | AuthController:345-347 | ✅ |
| 3 | 手機驗證 +5 | +5 | `credit_add_phone_verify` default 5 | AuthController:494-496 | ✅ |
| 4 | 男性 CC 驗證 +15 | +15 | `credit_add_adv_verify_male` default 15 | CreditCardVerificationService:148-149 | ✅ |
| 5 | 女性照片驗證 +15 | +15 | `credit_add_adv_verify_female` default 15 | Admin/VerificationController:90 | ✅ |
| 6 | Email OTP 長度 | 6 位 | `str_pad(random_int...,6)` | AuthController:125/415 | ✅ |
| 7 | Email OTP TTL | 600 秒 | Cache::put(...,600) | AuthController:127/382 | ✅ |
| 8 | 手機 OTP TTL | 300 秒 | Cache::put($otpKey,...,300) | AuthController:419 | ✅ |
| 9 | 手機 OTP 冷卻 | 60 秒 | Cache::put($cooldownKey,true,60) | AuthController:421 | ✅ |
| 10 | 手機 OTP 失敗鎖定 | 5 次 | `if ($attempts >= 5)` | AuthController:458 | ✅ |
| 11 | 註冊年齡下限 | 18 | `before:-18 years` | AuthController:35 | ✅ |
| 12 | reset token TTL | 60 分鐘 | auth.php expire=60 + controller 60m | config/auth.php:15 + AuthController:599 | ✅ |
| 13 | SMS 文案時間 | 5 分鐘 | 「5 分鐘內有效」 | SmsService:17 | ✅ |

---

## Self-check

- [x] Header 含完整 40 字元 hash、規格來源、Agent ID、前次稽核連結
- [x] 前次 issue 逐條回歸（含缺失編號註記）
- [x] 17 端點均在附錄 A
- [x] 13 規則均在附錄 B
- [x] P11.1/P11.2/P11.3 均有發現
- [x] issue 程式碼皆取自實際檔案片段
- [x] grep 輸出均已被引用
- [x] 每個 issue 皆有 Option A/B（或 C）與推薦
- [x] Symmetric >= 10
- [x] 檔名符合 `audit-A-20260427-codex.md`
- [x] 本輪僅新增 docs/audits/ 單一檔案
