# Audit-E Round 2 — 誠信分數 / 停權 / 申訴 / 隱私 / GDPR

> 先讀 prompts/audit/_common.md。

## 規格範圍
- docs/DEV-008 完整全文（規格 = code default）
- docs/API-001 §10.8（停權申訴）
- docs/API-001 §10.10（隱私設定）
- docs/API-001 §10.11（帳號刪除）
- docs/PRD-001 §4.4.4（自動停權）§4.4.6（申訴流程）
- docs/UF-001 UF-09（誠信扣分）UF-11（GDPR 帳號刪除）

## 前次稽核
- docs/audits/audit-E-20260423.md
- docs/audits/audit-E-20260424.md

## 程式碼範圍

```bash
# 誠信分數核心
backend/app/Services/CreditScoreService.php
backend/app/Observers/CreditScoreObserver.php
backend/app/Http/Middleware/CheckSuspended.php
backend/app/Models/CreditScoreLog.php
backend/app/Models/CreditScoreHistory.php

# 申訴
backend/app/Http/Controllers/Api/V1/AppealController.php
backend/app/Models/Report.php  # type=appeal

# 隱私 / GDPR
backend/app/Http/Controllers/Api/V1/PrivacyController.php
backend/app/Services/GdprService.php
backend/app/Console/Commands/ProcessGdprDeletions.php

# Mail
backend/app/Mail/AccountAutoSuspendedMail.php
backend/app/Mail/AccountReactivatedMail.php

# Seeder
backend/database/seeders/SystemSettingsSeeder.php

# 前端
frontend/src/api/appeals.ts
frontend/src/views/app/SuspendedView.vue
frontend/src/views/app/AppealView.vue
frontend/src/views/app/settings/PrivacyView.vue
frontend/src/views/app/settings/DeleteAccountView.vue
```

## 規格端點清單（P1）
- POST /me/appeal、GET /me/appeal/current
- GET/PATCH /me/privacy
- POST /me/delete-account（申請）、DELETE /me/delete-account（取消）

## 模組特有檢查

### P4 業務規則對照（DEV-008 全表）
| key | 規格值 | grep |
|---|---|---|
| credit_score_initial | 60 | `grep -n "credit_score_initial" backend/database/seeders/` |
| credit_score_unblock_threshold | 30 | `grep -n "unblock_threshold\|>= 30" backend/app/Observers/CreditScoreObserver.php` |
| credit_add_email_verify | 5 | `grep -n "credit_add_email_verify" backend/app/Services/CreditScoreService.php backend/database/seeders/` |
| credit_add_phone_verify | 5 | 同上 |
| credit_add_adv_verify_male | 15 | 同上 |
| credit_add_adv_verify_female | 15 | 同上 |
| credit_add_date_gps | 5 | 同上 |
| credit_add_date_no_gps | 2 | 同上 |
| credit_sub_report_user | 10 | 同上 |
| credit_sub_date_noshow | 10 | 同上 |
| credit_sub_bad_content | 5 | 同上 |
| credit_sub_harassment | 20 | 同上 |
| 自動停權門檻 | <= 0 | `grep -nA 10 "auto_suspended" backend/app/Observers/CreditScoreObserver.php` |
| 解停門檻 | >= 30 | 同上 |
| 數據保留天數 | 180 | `grep -rn "data_retention_days" backend/` |
| 帳號刪除冷靜期 | 7 天 | `grep -rn "pending_deletion\|7.*days\|delete_requested_at" backend/` |
| 女性照片永久保留 | 是 | `grep -nA 5 "verification_photo\|永久" backend/app/Services/GdprService.php` |

### P11 模組特有
```bash
# 誠信分數 hardcode（規格禁止）
grep -rn "->adjust(.*[+-][0-9]" backend/app/  # 看 adjust 是否有 hardcode 數字
grep -rn "credit_score.*=.*[0-9]" backend/app/

# system_settings key 命名格式不一致
grep -rn "credit_score\.\|credit_add\.\|credit_sub\." backend/app/  # 點記法（舊）
grep -rn "credit_score_\|credit_add_\|credit_sub_" backend/app/    # 底線（新）

# CreditScoreService::adjust 呼叫者
grep -rn "CreditScoreService::adjust\|->adjust(" backend/app/

# CheckSuspended 是否阻擋「停權但停權後可訪問的路由」（appeal）
grep -nA 30 "withoutMiddleware\|except" backend/routes/api.php | grep -B2 -A2 "appeal\|suspended"

# CreditScoreChanged WebSocket 廣播是否實作
grep -rn "CreditScoreChanged" backend/app/
```

## 重點關注（前次 Round 1）
- E-001：system_settings key 命名是否統一為底線格式
- E-002：DateService 約會加分是否走 SystemSetting 而非 config()
- E-003：申訴 images 前端送出格式
- E-004：CreditScoreChanged 是否實作
- E-005：CreditScoreService.adjust() 參數是否包含 operatorType + relatedId
