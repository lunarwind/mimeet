# Audit Report A Round 3 — 認證與身份驗證

**執行日期：** 2026-04-27
**稽核者：** Claude Code（本機）
**Agent ID：** claudecode
**規格來源：**
  - docs/API-001_前台API規格書.md §2 + §16.3/§16.4
  - docs/PRD-001_MiMeet_約會產品需求規格書.md §4.2.1
  - docs/DEV-008_誠信分數系統規格書.md §3 §4.1
  - docs/DEV-004_後端架構與開發規範.md §3.1 §13.1
  - docs/UF-001_用戶流程圖.md UF-01
  - docs/UI-001_UI_UX設計規格書.md §8.2
**程式碼基準（Local）：** f4e597fe90b249ca15c95f98927320cf05499f87
**前次稽核（不分 agent，全部都要讀）：**
  - docs/audits/audit-A-20260422.md — Round 1 第一份（H-1 ~ L-2 編號格式）
  - docs/audits/audit-A-20260424.md — Round 1 第二份（#A-001 ~ #A-008）
  - docs/audits/audit-A-20260427.md — Round 2（#A2-009 ~ #A2-010，已附修正紀錄）
**總結：** 3 issues（🔴 0 / 🟠 0 / 🟡 1 / 🔵 2）+ 25 Symmetric

---

## 0. 前次 Issue 回歸狀態

### 回歸判定說明

- Round 1 第一份（20260422）基準 commit：`ecff2f9`
- Round 1 第二份（20260424）基準 commit：`e82c698`（local）/ `847e424`（remote）
- Round 2（20260427）基準 commit：延伸 20260424 後，即 `b9cb5a7`（修正兩個 low issues）

使用 `git diff {前次 hash}..HEAD -- {file}` 判定各 issue 狀態。

### 回歸狀態表

| Issue | 來源 | 前次等級 | 前次基準（short） | 本輪狀態 | 備註 |
|---|---|---|---|---|---|
| H-1（/auth/refresh 未實作） | 20260422 | 🟠 High | ecff2f9 | ✅ 已修 | 規格 §2.1.3 已標「未實作」，code 亦無此路由 |
| H-2（tokens vs token 結構） | 20260422 | 🟠 High | ecff2f9 | ✅ 已修 | 規格 §2.1.2 已更新為 `token` 欄位 |
| H-3（CC 驗證未實作） | 20260422 | 🟠 High | ecff2f9 | ✅ 已修 | CreditCardVerificationController 已完整實作 |
| M-1（verify-phone 缺 auth:sanctum） | 20260422 | 🟡 Medium | ecff2f9 | ✅ 已修 | 路由現有 `['auth:sanctum', 'throttle:otp']` |
| M-2（§2.2.3 vs §16.3 路徑矛盾） | 20260422 | 🟡 Medium | ecff2f9 | ⚠️ 部分修 | §2.2.3 路徑已更新；但上傳格式描述仍殘留 multipart/form-data，與 §16.4 JSON body 不符 |
| M-3（reset-password 無 throttle） | 20260422 | 🟡 Medium | ecff2f9 | ✅ 已修 | routes/api.php:58-59 已加 `->middleware('throttle:otp')` |
| L-1（register 合規欄位未驗證） | 20260422 | 🔵 Low | ecff2f9 | ✅ 已修 | AuthController:37-39 現有 `terms_accepted/privacy_accepted/anti_fraud_read required\|accepted` |
| L-2（verify-phone/confirm OTP 錯誤碼） | 20260422 | 🔵 Low | ecff2f9 | ❌ 未修 | 仍回傳 `'1021'`/`'1022'`/`'1023'` 數字字串，規格定義 `OTP_INVALID` |
| #A-001（verify-phone/send 缺 auth:sanctum） | 20260424 | 🔴 Critical | e82c698 | ✅ 已修 | 路由已加 auth:sanctum；commit comment 標記「A-001/G-001 fix」|
| #A-002（register 回應結構） | 20260424 | 🟠 High | e82c698 | ❌ 未修 | status 仍回 `'active'`；仍無 `verification` block；`code` 仍是字串 `'REGISTER_SUCCESS'` |
| #A-003（register 建立 status='active'） | 20260424 | 🟠 High | e82c698 | ❌ 未修 | AuthController:108 仍 `'status' => 'active'` |
| #A-004（register 缺 group 欄位） | 20260424 | 🟡 Medium | e82c698 | ❌ 未修 | formatValidator 仍無 `group` 規則，User::create 仍無此欄位 |
| #A-005（register 缺 password_confirmation 驗證） | 20260424 | 🟡 Medium | e82c698 | ✅ 已修 | AuthController:32 已加 `'confirmed'` rule |
| #A-006（error code 格式不一致） | 20260424 | 🟡 Medium | e82c698 | ❌ 未修 | 仍混用 `INVALID_CREDENTIALS`（字串）與 `'1021'`（數字字串）|
| #A-007（login route 缺 throttle） | 20260424 | 🔵 Low | e82c698 | ❌ 未修 | routes/api.php:54 login 仍無 throttle middleware |
| #A-008（spec reset URL 舊域名） | 20260424 | 🔵 Low | e82c698 | ❌ 未修 | API-001 §2.3.2 仍顯示 `mimeet.tw`，code 正確用 `config('app.frontend_url')` |
| #A2-009（SMS 文案 10 分鐘） | 20260427 | 🔵 Low | b9cb5a7 | ✅ 已修 | SmsService:17 已改 "5 分鐘內有效" |
| #A2-010（getCreditCardVerificationStatus 死碼） | 20260427 | 🔵 Low | b9cb5a7 | ✅ 已修 | verification.ts 已移除該 export，剩留 comment 說明 |

**回歸修復率：** 10/18 (55.6%)
**仍開放：** L-2, #A-002, #A-003, #A-004, #A-006, #A-007, #A-008（共 7 條，本輪不重複列 issue 詳情，詳見前次報告）

---

## 1. Pass 完成記錄

**程式碼範圍確認：**
```
✅ backend/app/Http/Controllers/Api/V1/AuthController.php
❌ backend/app/Http/Controllers/Api/V1/VerificationController.php（不存在）
❌ backend/app/Http/Controllers/Api/V1/PhoneVerificationController.php（不存在，功能整合在 AuthController）
✅ backend/app/Http/Controllers/Api/V1/CreditCardVerificationController.php
✅ backend/app/Http/Controllers/Api/V1/Admin/VerificationController.php
✅ backend/app/Services/SmsService.php
✅ backend/app/Services/CreditCardVerificationService.php
✅ backend/app/Services/CreditScoreService.php
✅ backend/app/Services/UserActivityLogService.php
✅ backend/app/Mail/EmailVerificationMail.php
✅ backend/app/Mail/ResetPasswordMail.php
❌ backend/app/Http/Middleware/CheckSuspended.php（不存在）
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
- `VerificationController.php`（前台）不存在：功能已分散到 `VerificationPhotoController`
- `PhoneVerificationController.php` 不存在：手機驗證整合於 `AuthController::verifyPhoneSend/Confirm`
- `CheckSuspended.php` 不存在：已在本輪 P6 確認，中介層清單中無任何停權檢查

| Pass | 範圍 | Issues 發現 |
|---|---|---|
| P1 路由層 | 17 個規格端點 vs routes/api.php | ✅ 全部存在，詳見附錄 A |
| P2 請求 Payload | formatValidator rules vs 規格 | 延續 #A-004（group 未驗證）|
| P3 回應結構 | Controller return vs 規格 | 延續 #A-002；新增 #A3-001（CC initiate 回應）|
| P4 業務規則 | 13 條數值 vs code | ✅ 全部對齊，詳見附錄 B |
| P5 錯誤碼 | error code 格式 | 延續 #A-006（L-2 同根）|
| P6 認證中介層 | auth:sanctum / throttle 覆蓋 | 延續 #A-007；CheckSuspended 不存在但無規格要求 |
| P7 前端 API 層 | TS interface vs 後端回應 | 新增 #A3-001（CC initiate 介面不對齊）|
| P8 前端 UI 層 | VerifyView + RegisterView 視覺 | #A3-003（UI-001 §8.2 SMS 模板過時）|
| P9 邊界條件 | null / 重複提交 / 軟刪除 | ✅ 見附錄 B 備註 |
| P10 跨模組副作用 | CreditScore / Mail / Cache | ✅ 四個驗證加分事件全部掛接 |
| P11.1 死碼 | AuthController public methods | ✅ 11 個 method 全部有路由引用 |
| P11.2 重複實作 | OTP 生成 / E164 轉換 | 新增 #A3-002（OTP 生成兩種 pattern）|
| P11.3 規格缺漏 | 雙向缺漏 | 延續 M-2 部分修；本輪新增 §2.2.3 upload 格式差異 |

---

## 2. Issues 索引（本輪新發現）

### 🔴 Critical
（無）

### 🟠 High
（無）

### 🟡 Medium
- Issue #A3-001 — CC 驗證 initiate 回應：規格 `payment_url` vs 實作 `aio_url+params+payment_id`

### 🔵 Low
- Issue #A3-002 — Email OTP 生成兩種不一致 pattern（resendVerification 不允許前導零）
- Issue #A3-003 — UI-001 §8.2 SMS 模板仍顯示「10 分鐘」（A2-009 修正後未同步更新）

### ✅ Symmetric（25 條）
見第 §5 詳情。

---

## 3. Issue 詳情

### Issue #A3-001
**Pass：** P3, P7
**規格位置：** docs/API-001 §2.2.4（信用卡驗證 initiate 成功回應）
**規格內容：**
```
成功回應 (200)：
{
  "success": true,
  "data": {
    "order_no": "CCV_20260426200000_000123",
    "payment_url": "https://payment-stage.ecpay.com.tw/..."
  }
}
前端收到 payment_url 後 window.location.href = paymentUrl 跳轉。
```
**程式碼位置（後端）：** `backend/app/Http/Controllers/Api/V1/CreditCardVerificationController.php:73-80`
**程式碼現況（後端）：**
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
**程式碼位置（前端）：** `frontend/src/api/verification.ts:37-41`
**程式碼現況（前端）：**
```typescript
export interface CreditCardVerificationInitResponse {
  payment_id: number
  aio_url: string
  params: Record<string, string | number>
}
```
**差異說明：** 規格定義 `payment_url`（直接跳轉 URL），但後端實際回傳 `payment_id + aio_url + params`（ECPay form-post 所需參數）。前端 VerifyView.vue:150 呼叫 `redirectToECPay(data.aio_url, data.params)` 與後端對齊，兩者自洽。規格是唯一落後的一方。
**等級：** 🟡 Medium
**建議方案：**
- Option A（推薦）：更新 API-001 §2.2.4 成功回應範例，改為 `payment_id + aio_url + params`，刪除 `order_no / payment_url`，補充 ECPay form-post 機制說明
- Option B：後端改為組合完整 URL（ECPay aio_url + 表單序列化）直接回傳 `payment_url`（需後端多一步 HTTP GET 驗簽，繁瑣）
**推薦：** A（前後端已自洽，改規格成本最低）

---

### Issue #A3-002
**Pass：** P11.2
**規格位置：** docs/DEV-004_後端架構與開發規範.md §13.1（OTP 規格：6 碼數字）
**規格內容：**
```
OTP TTL = 5 分鐘（300 秒）
6 位數字驗證碼（不含前導零限制未明確定義）
```
**程式碼位置：** `backend/app/Http/Controllers/Api/V1/AuthController.php:125, 381, 415`
**程式碼現況：**
```php
// register（line 125）：
$verifyCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
// verifyPhoneSend（line 415）：
$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
// resendVerification（line 381）：
$code = (string) random_int(100000, 999999);  // ← 不同 pattern
```
**差異說明：** `register` 和 `verifyPhoneSend` 使用 `random_int(0, 999999)` + `str_pad`，允許 000000-099999 等前導零碼；`resendVerification` 使用 `random_int(100000, 999999)`，永不產生前導零碼。三處功能語意相同（Email OTP / Phone OTP）但生成邏輯不一致，可能在前端 6 格 OTP 輸入框造成邊界問題（如 `000123` 輸入後值為 `123`）。
**等級：** 🔵 Low
**建議方案：**
- Option A（推薦）：統一使用 `str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)`；同時確認前端 OTP 輸入框保留前導零
- Option B：統一使用 `random_int(100000, 999999)` 排除前導零（最簡單，但犧牲一部分熵）
**推薦：** A（保持行為一致且允許完整 6 碼空間）

---

### Issue #A3-003
**Pass：** P8
**規格位置：** docs/UI-001_UI_UX設計規格書.md §8.2（SMS 簡訊模板）
**規格內容：**
```
電話驗證碼：
「【MiMeet】您的驗證碼為 {CODE}，請於 10 分鐘內輸入。切勿將驗證碼透露給他人。」
```
**程式碼位置：** `backend/app/Services/SmsService.php:17`
**程式碼現況：**
```php
$body = "【MiMeet】您的驗證碼為 {$code}，5 分鐘內有效，請勿洩漏。";
```
**差異說明：** A2-009（2026-04-27）修正了 SmsService.php 與 DEV-004 §13.1，但 UI-001 §8.2 未同步更新，仍顯示「10 分鐘」。用戶若對照 UI 規格書看到 10 分鐘，與實際 5 分鐘不符，影響客服溝通與未來開發者認知。
**等級：** 🔵 Low
**建議方案：**
- Option A（推薦）：更新 UI-001 §8.2 SMS 模板為「5 分鐘內輸入」，與 SmsService 和 DEV-004 對齊
**推薦：** A（純文件同步，無程式碼風險）

---

## 4. 行動優先序

| 優先 | 動作 | 對象 | 對應 Issue |
|---|---|---|---|
| P1 | 更新 UI-001 §8.2 SMS 模板（10 分鐘→5 分鐘） | docs | #A3-003 |
| P1 | 更新 API-001 §2.2.4 CC initiate 回應格式 | docs | #A3-001 |
| P2 | 統一 AuthController OTP 生成 pattern | backend | #A3-002 |
| P3 | 解決 #A-002/#A-003：register status/response（推薦改規格，承認 active 設計） | docs or BE | 回歸 |
| P3 | 解決 #A-004：register group 欄位（建議標 Phase 2） | docs or BE | 回歸 |
| P4 | 統一 error code 格式（#A-006 + L-2）：全面改用語意字串碼 | BE | 回歸 |
| P5 | 修正 API-001 §2.3.2 reset URL domain（mimeet.tw→mimeet.online+hash router） | docs | 回歸 #A-008 |
| P5 | 修正 API-001 §2.2.3 upload 格式描述（multipart→JSON body 兩步流程） | docs | 回歸 M-2 |
| P6 | 評估是否為 login 加 throttle middleware（#A-007） | BE | 回歸 |

**集中標記（三輪以上「改規格」推薦）：** #A-002/#A-003/#A-004/#A-008、M-2，建議 PM/架構師做一次性 spec sync session，以 code 為 source of truth 重新核對 §2.1.1 / §2.2.3 / §2.3.2 整個認證章節。

---

## 5. 下次 Audit 建議

- **A 輪應開新 Round 4** 僅在以下 issue 修復後才需要：#A-002/A-003（register response）和 #A-006（error code 統一）；其他 Low issue 可待 Audit-A 全部修完後一次性關閉
- §2.2.3 upload 格式（M-2 部分修）建議加入 pre-merge-check 守護，避免規格再度分裂
- 考慮新增 P4 守護：`grep` API-001 是否含 `payment_url` 在 CC initiate 範例（退化防護）
- CheckSuspended middleware 雖不存在，但 logout+login 流程仍可攔截停權用戶；若未來要做即時停權，需另開 issue 設計 middleware

---

## 附錄 A — P1 端點逐條檢查

| # | Method | 端點 | 路由存在 | Middleware | 狀態 | 備註 |
|---|---|---|---|---|---|---|
| 1 | POST | /auth/register | ✅ | throttle:register | ✅ | api.php:53 |
| 2 | POST | /auth/login | ✅ | （無） | ⚠️ | 缺 throttle，詳見回歸 #A-007 |
| 3 | POST | /auth/refresh | ✅ 不存在 | — | ✅ | 規格已標未實作，正確 |
| 4 | POST | /auth/logout | ✅ | auth:sanctum | ✅ | api.php:64 |
| 5 | POST | /auth/verify-email | ✅ | （無） | ✅ | 公開端點，設計正確 |
| 6 | POST | /auth/resend-verification | ✅ | throttle:otp | ✅ | api.php:56 |
| 7 | POST | /auth/verify-phone/send | ✅ | auth:sanctum, throttle:otp | ✅ | api.php:70（A-001 已修）|
| 8 | POST | /auth/verify-phone/confirm | ✅ | auth:sanctum, throttle:otp | ✅ | api.php:71 |
| 9 | POST | /me/verification-photo/request | ✅ | auth:sanctum | ✅ | api.php:249 |
| 10 | POST | /me/verification-photo/upload | ✅ | auth:sanctum | ✅ | api.php:250 |
| 11 | GET | /me/verification-photo/status | ✅ | auth:sanctum | ✅ | api.php:251 |
| 12 | POST | /verification/credit-card/initiate | ✅ | auth:sanctum | ✅ | api.php:256 |
| 13 | GET | /verification/credit-card/status | ✅ | auth:sanctum | ✅ | api.php:257 |
| 14 | POST | /verification/credit-card/callback | ✅ | （無） | ✅ | 公開端點，ECPay server-to-server，正確 |
| 15 | POST | /auth/forgot-password | ✅ | throttle:otp | ✅ | api.php:57 |
| 16 | POST | /auth/reset-password | ✅ | throttle:otp | ✅ | api.php:58（M-3 已修）|
| 17 | POST | /me/change-password | ✅ | auth:sanctum | ✅ | api.php:237 |

**補充：** `GET /verification/credit-card/return`（路由 261）存在但規格未明列，屬 ECPay 瀏覽器返回端點，設計正確。

---

## 附錄 B — P4 業務規則對照

| # | 規則 | 規格值 | 實作值 | 出處 | 狀態 |
|---|---|---|---|---|---|
| 1 | 初始誠信分數 | 60 | 60 | SystemSettingsSeeder:33；AuthController:107 | ✅ |
| 2 | Email 驗證 +5 | 5 | `getConfig('credit_add_email_verify', 5)` | AuthController:346 | ✅ |
| 3 | 手機驗證 +5 | 5 | `getConfig('credit_add_phone_verify', 5)` | AuthController:495 | ✅ |
| 4 | 男性 CC 驗證 +15 | 15 | `getConfig('credit_add_adv_verify_male', 15)` | CreditCardVerificationService:148 | ✅ |
| 5 | 女性照片驗證 +15 | 15 | `getConfig('credit_add_adv_verify_female', 15)` | Admin/VerificationController:90 | ✅ |
| 6 | Email OTP 長度 | 6 位 | `str_pad(...random_int(0,999999)..., 6, '0')` | AuthController:125 | ✅ |
| 7 | Email OTP TTL | 600 秒 | `Cache::put("email_verification:...", ..., 600)` | AuthController:127、382 | ✅ |
| 8 | 手機 OTP TTL | 300 秒 | `Cache::put($otpKey, $code, 300)` | AuthController:419；`expires_in: 300` 回傳 | ✅ |
| 9 | 手機 OTP 冷卻 | 60 秒 | `Cache::put($cooldownKey, true, 60)` | AuthController:383、407+ | ✅ |
| 10 | 手機 OTP 失敗 5 次鎖 | 5 | `if ($attempts >= 5)` | AuthController:458 | ✅ |
| 11 | 註冊年齡下限 | 18 | `'birth_date' => ['before:-18 years']` | AuthController:35 | ✅ |
| 12 | reset token TTL | 60 分鐘 | `'expire' => 60` | config/auth.php:15 | ✅ |
| 13 | SMS 文案時間 | 5 分鐘 | "5 分鐘內有效" | SmsService:17（A2-009 修正後）| ✅ |

**邊界條件備注（P9）：**
- OTP 重複提交：`Cache::has($cooldownKey)` 在 verifyPhoneSend 防止 60 秒內重複請求 ✅
- OTP 5 次後鎖定：`Cache::put($attemptsKey, $attempts+1, 300)` 5 次內嘗試均有追蹤 ✅
- Email 枚舉防護：forgot-password 無論 Email 是否存在均回傳相同訊息 ✅
- 18 歲下限：`before:-18 years` rule，Laravel 精確計算 ✅

---

## §5 Symmetric（25 條）

1. POST /auth/register — throttle:register，17 個規格端點中路由全部存在 ✅
2. POST /auth/logout — auth:sanctum，token 刪除正確 ✅
3. GET /auth/me — auth:sanctum，回傳含 points_balance / stealth_until ✅（§11.6 擴充已實作）
4. POST /auth/verify-email — 公開端點，6 碼 OTP，Cache key 正確，CreditScore +5 觸發 ✅
5. POST /auth/resend-verification — throttle:otp，60 秒 cooldown ✅
6. POST /auth/verify-phone/send — auth:sanctum + throttle:otp（A-001 已修）✅
7. POST /auth/verify-phone/confirm — auth:sanctum，5 次失敗鎖，write phone_verified_at + user_activity_logs ✅
8. POST /me/verification-photo/request — auth:sanctum，僅限 female，隨機碼 6 位英數，10 分鐘有效 ✅
9. POST /me/verification-photo/upload — auth:sanctum，JSON body `photo_url + random_code`（§16.4 對齊）✅
10. GET /me/verification-photo/status — auth:sanctum，回傳 status / submitted_at / reviewed_at ✅
11. POST /verification/credit-card/initiate — auth:sanctum，僅限 male + Lv1+，未驗證才可發起 ✅
12. GET /verification/credit-card/status — auth:sanctum ✅
13. POST /verification/credit-card/callback — 公開（ECPay S2S），CheckMacValue 驗簽在 UnifiedPaymentService ✅
14. POST /auth/forgot-password — throttle:otp，email 枚舉防護，60 分鐘 token TTL ✅
15. POST /auth/reset-password — throttle:otp（M-3 修正），tokens().delete() 強制登出 ✅
16. POST /me/change-password — auth:sanctum，bcrypt，全裝置登出 ✅
17. 初始誠信分數 = 60（可動態設定）✅
18. 四個驗證加分事件（email/phone +5，CC/photo +15）全部掛接 CreditScoreService ✅
19. 年齡下限 18 歲（`before:-18 years`）✅
20. Email OTP 600 秒、手機 OTP 300 秒 ✅
21. 手機 OTP 冷卻 60 秒 ✅
22. 手機 OTP 失敗 5 次鎖（`$attempts >= 5`）✅
23. RegisterPayload 合規欄位（terms_accepted / privacy_accepted / anti_fraud_read required|accepted）✅（L-1 已修）
24. password_confirmation confirmed rule ✅（A-005 已修）
25. SMS 文案「5 分鐘內有效」✅（A2-009 已修）
