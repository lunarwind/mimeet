# Audit-A Round 2 — 認證與身份驗證

> 先讀 prompts/audit/_common.md。

## 規格範圍
- docs/API-001 §2（§2.1 用戶認證 / §2.2 身份驗證 / §2.3 密碼重設）
- docs/API-001 §16.3 §16.4（女性照片驗證）
- docs/PRD-001 §4.2.1（用戶註冊與驗證）
- docs/DEV-004 §3.1（路由規範）
- docs/DEV-008 §3 §4.1（驗證類加分 +5/+5/+15/+15）
- docs/UF-001 UF-01（註冊流程）
- docs/DEV-001 §6.1（Multi-Guard）

## 前次稽核
- docs/audits/audit-A-20260422.md
- docs/audits/audit-A-20260424.md

## 程式碼範圍

```bash
# 後端 Controllers
backend/app/Http/Controllers/Api/V1/AuthController.php
backend/app/Http/Controllers/Api/V1/VerificationController.php
backend/app/Http/Controllers/Api/V1/PhoneVerificationController.php
backend/app/Http/Controllers/Api/V1/CreditCardVerificationController.php
backend/app/Http/Controllers/Api/V1/Admin/VerificationController.php

# Services / Mail / Middleware
backend/app/Services/SmsService.php
backend/app/Services/CreditCardVerificationService.php
backend/app/Services/CreditScoreService.php
backend/app/Mail/EmailVerificationMail.php
backend/app/Http/Middleware/CheckSuspended.php
backend/app/Models/User.php

# 設定 / 路由
backend/routes/api.php
backend/config/auth.php
backend/config/sanctum.php
backend/app/Http/Kernel.php

# 前端
frontend/src/api/auth.ts
frontend/src/api/verification.ts
frontend/src/views/public/RegisterView.vue
frontend/src/views/public/LoginView.vue
frontend/src/views/public/ForgotPasswordView.vue
frontend/src/views/public/ResetPasswordView.vue
frontend/src/views/app/settings/VerifyView.vue
frontend/src/router/index.ts
```

## 規格端點清單（P1 對照）

17 個端點全要查（POST register/login/refresh/logout、verify-email/resend、verify-phone send/confirm、verification-photo request/upload/status、verification/credit-card initiate/status/callback、forgot-password、reset-password、me/change-password）。詳見 API-001 §2 + §16.3。

## 模組特有檢查

### P4 業務規則對照表
| 規則 | 規格值 | grep 指令 |
|---|---|---|
| 初始誠信分數 | 60 | `grep -n "credit_score_initial" backend/` |
| Email 驗證 +5 | 5 | `grep -nA 5 "verifyEmail" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| 手機驗證 +5 | 5 | `grep -nA 5 "verifyPhoneConfirm" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| 男 CC 驗證 +15 | 15 | `grep -nA 5 "processCallback\|adv_verify_male" backend/app/Services/CreditCardVerificationService.php` |
| 女 photo +15 | 15 | `grep -nA 5 "adv_verify_female" backend/app/Http/Controllers/Api/V1/Admin/VerificationController.php` |
| Email OTP TTL | 10 分鐘 | `grep -nE "Cache::put.*email_verification" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| 手機 OTP TTL | 5 分鐘 | `grep -nE "otp:phone" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| 手機 OTP 冷卻 | 60 秒 | `grep -n "cooldown" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| 手機 OTP 失敗 5 次鎖 | 5 | `grep -n "attempts >= 5" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| 18 歲下限 | before:-18 years | `grep -n "before:-18" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| reset token TTL | 60 分鐘 | `grep -nE "Password::sendResetLink|reset.*expire" backend/app/` |

### P11 模組特有 grep
```bash
# P11.2 重複：phone E.164 / OTP 生成 / 驗證 Mail
grep -rn "toE164\|+886\|str_starts_with.*09" backend/app/
grep -rn "random_int(0, 999999)\|str_pad.*999999" backend/app/
grep -rn "Mail::to" backend/app/Http/Controllers/Api/V1/AuthController.php backend/app/Mail/

# 是否有 PhoneVerificationController 又有 AuthController::verifyPhone* 並存
ls backend/app/Http/Controllers/Api/V1/ | grep -i "phone\|verif"
```

## 重點關注（前次 Round 1 issue 回歸測試）
- A-001：verify-phone 路由是否已加 auth:sanctum
- A-002：register 回應結構（token / verification block / status）
- A-003：register 寫入 status 是 'active' 還是 'pending_verification'
- A-006：錯誤碼字串 vs 數字 1021/1022/1023
- E-001：四個驗證加分事件是否都接到 CreditScoreService
