# Audit-A Round 3（認證與身份驗證）

**執行日期：** 2026-04-28  
**稽核者：** ChatGPT Codex（雲端）  
**Agent ID：** codex  
**規格來源：**
- docs/API-001 §2 + §16.3/§16.4
- docs/PRD-001 §4.2.1
- docs/DEV-008 §3 §4.1
- docs/DEV-004 §3.1 §13.1
- docs/UF-001 UF-01
**程式碼基準（Local）：** 2c056ae4cf2cddd36513a1f86644944f2de2fd95  
**前次稽核（不分 agent，全部都要讀）：**
- docs/audits/audit-A-20260422.md
- docs/audits/audit-A-20260424.md
- docs/audits/audit-A-20260427-codex.md
**總結：** 10 issues（🔴 0 / 🟠 5 / 🟡 2 / 🔵 3）+ 10 Symmetric

## §0 回歸判定（Round 1 + Round 2）

| Issue | 來源 | 前次等級 | 前次基準 | 本輪狀態 | 備註 |
|---|---|---|---|---|---|
| #A-001 | 20260422 | 🔴 Critical | ecff2f9 | ✅ 已修 | verify-phone 已在 `auth:sanctum` 群組 |
| #A-002 | 20260422 | 🟠 High | ecff2f9 | ❌ 未修 | register response 與 spec 仍不一致 |
| #A-003 | 20260422 | 🟠 High | ecff2f9 | ❌ 未修 | status `active` vs spec 仍有差距 |
| #A-004 | 20260422 | 🟡 Medium | ecff2f9 | ❌ 未修 | group 欄位仍未落地 |
| #A-005 | 20260422 | 🟡 Medium | ecff2f9 | ⚠️ 部分修 | 路由與 middleware 有改善，文檔不同步 |
| #A-006 | 20260422 | 🟠 High | ecff2f9 | ❌ 未修 | OTP 錯誤碼格式仍分歧 |
| #A-007 | 20260422 | 🔵 Low | ecff2f9 | ✅ 已修 | throttling 已到位 |
| #A-008 | 20260422 | 🔵 Low | ecff2f9 | ✅ 已修 | 前端 API 死碼問題已緩解 |
| #A2-001 | codex 20260427 | 🟠 High | e2f7f5f | ❌ 未修 | 同 #A-002 |
| #A2-002 | codex 20260427 | 🟠 High | e2f7f5f | ❌ 未修 | 同 #A-003 |
| #A2-003 | codex 20260427 | 🟠 High | e2f7f5f | ❌ 未修 | credit-card initiate response 規格待 sync |
| #A2-004 | codex 20260427 | 🟠 High | e2f7f5f | ❌ 未修 | 與 A2-008 同根（CheckSuspended 缺） |
| #A2-005 | codex 20260427 | 🟡 Medium | e2f7f5f | ❌ 未修 | register group 欄位問題延續 |
| #A2-006 | codex 20260427 | 🟠 High | e2f7f5f | ❌ 未修 | OTP error code 未統一 |
| #A2-007 | codex 20260427 | 🟡 Medium | e2f7f5f | ⚠️ 部分修 | 文件與實作仍有語義差 |
| #A2-008 | codex 20260427 | 🔵 Low | e2f7f5f | ❌ 未修 | `CheckSuspended.php` 不存在 |
| #A2-009 | codex 20260427 | 🔵 Low | e2f7f5f | ✅ 已修 | SMS 文案顯示 5 分鐘 |
| #A2-010 | codex 20260427 | 🔵 Low | e2f7f5f | ✅ 已修 | 前端 export/import 已有使用 |

## §1 Pass 完成記錄附註（原樣輸出）

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

## 附錄 A：P1 17 端點對照

（略述）17/17 端點可在 `backend/routes/api.php` 找到，其中 `/auth/refresh` 為規格列示但仍未見前台實作；`verify-phone` 已有 `auth:sanctum + throttle:otp`。

## 附錄 B：P4 13 業務規則

- 初始誠信分數 60：有。
- Email +5 / Phone +5 / 男 CC +15 / 女照 +15：皆有。
- Email OTP 6 碼 / TTL 600 秒：有。
- Phone OTP TTL 300 秒 / cooldown 60 秒 / 5 次鎖：有。
- 年齡 18+：有 `before:-18 years`。
- reset token TTL 60 分：設定檔存在；實際沿 framework config。
- SMS 文案：已為「5 分鐘內有效」。

## §5 建議（不改碼）

1. 優先做 Spec Sync：register response、status 欄位、credit-card initiate response、OTP 錯誤碼。
2. `CheckSuspended` 同根問題（A2-004/A2-008）應一次性處理：補 middleware class + Kernel alias + route 套用策略。
3. 補齊 `_common.md` 指定的 Round1 報告檔名映射（目前實際檔名為 `audit-A-20260422.md`, `audit-A-20260424.md`）。

## Symmetric（10）
- spec 有、code 無（refresh）
- code 有、spec 弱定義（credit-card initiate response）
- OTP error schema 雙方不一致
- register response block 差異
- register `group` 欄位差異
- middleware policy 與文件不同步
- callback endpoint 暴露策略需明確
- change-password 欄位命名需定義
- verification-photo 三端點 response schema 完整性
- admin/前台 verification 規格邊界待補

