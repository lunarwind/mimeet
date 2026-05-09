# Audit-A Round 3 — 認證與身份驗證模組（Gemini CLI 本機版）[cite: 3]

> 你是 Gemini CLI，運行於本機 mimeet repo 工作目錄。[cite: 3]
> 先讀 `prompts/audit/_common.md`，再讀本檔特定章節，依序執行。[cite: 3]

---

## 0. 任務摘要[cite: 3]

對本機 `develop` 分支的「認證與身份驗證」模組執行第三輪稽核，產出 `docs/audits/audit-A-{今天日期}-geminicli.md`。[cite: 3]

**重點**：本輪是 Round 3，必須對 Round 1（claudecode 兩份）+ Round 2（codex 一份）的所有 issue 做回歸測試，依 `_common.md` §0「回歸判定方法」嚴格判定狀態。[cite: 3]

**特別提醒**：你是本機執行，技術上能改 backend/frontend/admin，但本任務**只允許新增 audit 報告檔**。[cite: 3] 若發現 critical issue，記錄在報告 §4 行動優先序，不要動手修——修 code 開另一個 session。[cite: 3]

---

## 1. 環境檢查[cite: 3]

依 `_common.md` §2 執行：[cite: 3]

bash
pwd
ls -la | head -20
git status
git rev-parse HEAD                # → 這是本輪「程式碼基準」commit hash，必填到報告 Header
git log -1 --pretty=format:'%H %s'

# 本機與遠端同步檢查
git fetch origin develop
local_hash=$(git rev-parse develop)
remote_hash=$(git rev-parse origin/develop)
[ "$local_hash" = "$remote_hash" ] && echo "✅ 同步" || echo "❌ 請先 git pull"

ls -la docs/audits/

# 對齊輸出格式（必讀）
cat docs/audits/audit-A-20260427-codex.md | head -120


**重要**：跑 audit 前必須跟 origin/develop 同步。[cite: 3] 如果不同步，先 `git pull` 再開始。[cite: 3]

把 `git rev-parse HEAD` 的完整 40 字元 hash 記下來，待會填到報告 Header「程式碼基準（Local）」欄位。[cite: 3]

---

## 2. 規格範圍[cite: 3]

**主要規格（只看這幾個章節，不要 scope creep）：**[cite: 3]

*   `docs/API-001_前台API規格書.md`[cite: 3]
    *   §2 認證與身份驗證（§2.1 用戶認證 / §2.2 身份驗證 / §2.3 密碼重設）[cite: 3]
    *   §16.3 §16.4 女性照片驗證[cite: 3]
*   `docs/PRD-001_MiMeet_約會產品需求規格書.md` §4.2.1（用戶註冊與驗證）[cite: 3]
*   `docs/DEV-004_後端架構與開發規範.md` §3.1（路由規範）§13.1（SMS）[cite: 3]
*   `docs/DEV-008_誠信分數系統規格書.md` §3 §4.1（驗證類加分 +5/+5/+15/+15）[cite: 3]
*   `docs/UF-001_用戶流程圖.md` UF-01（註冊流程）[cite: 3]
*   `docs/DEV-001_技術架構規格書.md` §6.1（Multi-Guard）[cite: 3]

**參照規格（不主審但要交叉驗證）：**[cite: 3]

*   `docs/API-002_後台管理API規格書.md` §14（信用卡驗證後台管理）[cite: 3]

讀規格章節時，先 `view` 拿到實際內容，**不要憑印象**：[cite: 3]

bash
grep -nE "^## |^### " docs/API-001_前台API規格書.md | head -30


然後用 `view` 工具讀對應行範圍。[cite: 3]

---

## 3. 程式碼範圍[cite: 3]

執行下列指令，把實際存在/不存在的檔案列出來（不存在的標 ❌）：[cite: 3]

bash
for f in \
  backend/app/Http/Controllers/Api/V1/AuthController.php \
  backend/app/Http/Controllers/Api/V1/VerificationController.php \
  backend/app/Http/Controllers/Api/V1/PhoneVerificationController.php \
  backend/app/Http/Controllers/Api/V1/CreditCardVerificationController.php \
  backend/app/Http/Controllers/Api/V1/Admin/VerificationController.php \
  backend/app/Services/SmsService.php \
  backend/app/Services/CreditCardVerificationService.php \
  backend/app/Services/CreditScoreService.php \
  backend/app/Services/UserActivityLogService.php \
  backend/app/Mail/EmailVerificationMail.php \
  backend/app/Mail/ResetPasswordMail.php \
  backend/app/Http/Middleware/CheckSuspended.php \
  backend/app/Models/User.php \
  backend/routes/api.php \
  backend/config/auth.php \
  backend/config/sanctum.php \
  backend/app/Http/Kernel.php \
  frontend/src/api/auth.ts \
  frontend/src/api/verification.ts \
  frontend/src/views/public/RegisterView.vue \
  frontend/src/views/public/LoginView.vue \
  frontend/src/views/public/ForgotPasswordView.vue \
  frontend/src/views/public/ResetPasswordView.vue \
  frontend/src/views/app/settings/VerifyView.vue \
  frontend/src/router/index.ts \
  frontend/src/router/guards.ts
do
  [ -f "$f" ] && echo "✅ $f" || echo "❌ $f"
done


把這份輸出**原樣**放進報告 §1 Pass 完成記錄附註。[cite: 3]

---

## 4. 前次稽核（必讀，回歸測試的基礎）[cite: 3]

依 `_common.md` §0「回歸判定方法」對所有前次 issue 做回歸：[cite: 3]

bash
# 列出所有前次 audit-A 報告（不分 agent）
ls -la docs/audits/audit-A-*.md


預期看到：[cite: 3]

*   `docs/audits/audit-A-20260422-claudecode.md` — Round 1 第一份[cite: 3]
*   `docs/audits/audit-A-20260424-claudecode.md` — Round 1 第二份[cite: 3]
*   `docs/audits/audit-A-20260427-codex.md` — Round 2[cite: 3]

**必須做的事**：[cite: 3]

1.  用 `view` 工具讀完所有三份報告的「Issues 索引」與「Issue 詳情」區段[cite: 3]
2.  提取每份報告的「程式碼基準」commit hash[cite: 3]
3.  對每個 issue 跑：[cite: 3]
    `git diff {前次 hash}..HEAD -- {issue 引用的檔案}`[cite: 3]
4.  依 `_common.md` §0 判定本輪狀態（已修 / 未修 / 部分修 / 重構）[cite: 3]

**特別注意**：Round 1（claudecode 跑的）與 Round 2（codex 跑的）對同 issue 可能結論不同。[cite: 3] 若有分歧，本輪採最新 commit 的實況為準，並在備註欄注明：[cite: 3]

> ⚠️ 與 #X-NNN（agent A）結論不同，本輪採 Gemini CLI Round 3 評估

回歸表填到報告 §0：[cite: 3]

markdown
| Issue | 來源 | 前次等級 | 前次基準 | 本輪狀態 | 備註 |
|---|---|---|---|---|---|
| #A-001 | claudecode 20260422 | 🔴 Critical | {short hash} | ✅ 已修 | verify-phone 路由已加 auth:sanctum |
| #A-002 | claudecode 20260422 | 🟠 High | {short hash} | ❌ 未修 | register 仍缺 verification block |
| ... |
| #A2-001 | codex 20260427 | 🟠 High | d0e54a6 | ❌ 未修 | 同 #A-002 |
| #A2-009 | codex 20260427 | 🔵 Low | d0e54a6 | ✅ 已修 | SMS 文案已改 5 分鐘（commit X） |
| #A2-010 | codex 20260427 | 🔵 Low | d0e54a6 | ✅ 已修 | 死碼 export 已移除（commit Y） |
| ... |


---

## 5. 規格端點清單（P1 對照表）[cite: 3]

17 個規格端點，每個都要查（即使前輪查過）：[cite: 3]

| # | Method | 端點 | 規格章節 |
|---|---|---|---|
| 1 | POST | /auth/register | API-001 §2.1.1 |
| 2 | POST | /auth/login | API-001 §2.1.2 |
| 3 | POST | /auth/refresh | API-001 §2.1.3（規格已標未實作）|
| 4 | POST | /auth/logout | API-001 §2.1.4 |
| 5 | POST | /auth/verify-email | API-001 §2.2.1 |
| 6 | POST | /auth/resend-verification | API-001 §2.2.1 |
| 7 | POST | /auth/verify-phone/send | API-001 §2.2.2.1 |
| 8 | POST | /auth/verify-phone/confirm | API-001 §2.2.2.2 |
| 9 | POST | /me/verification-photo/request | API-001 §2.2.3 / §16.3 |
| 10 | POST | /me/verification-photo/upload | API-001 §2.2.3 / §16.3 |
| 11 | GET | /me/verification-photo/status | API-001 §16.4 |
| 12 | POST | /verification/credit-card/initiate | API-001 §2.2.4 |
| 13 | GET | /verification/credit-card/status | API-001 §2.2.4 |
| 14 | POST | /verification/credit-card/callback | API-001 §2.2.4 |
| 15 | POST | /auth/forgot-password | API-001 §2.3.1 |
| 16 | POST | /auth/reset-password | API-001 §2.3.2 |
| 17 | POST | /me/change-password | API-001 §2.3.3 |

**P1 grep（必跑、必引用輸出）：**[cite: 3]

bash
grep -nE "auth/|/me/verification|/verification/credit-card|/me/change-password" \
  backend/routes/api.php | grep -v "^\s*//"

grep -n "throttle" backend/app/Http/Kernel.php backend/routes/api.php | grep -iE "otp|login"

# 檢查 verify-phone 路由 middleware（Round 1 #A-001 是 Critical 點）
grep -B2 -A4 "verify-phone" backend/routes/api.php


填附錄 A 表格，每一行標 ✅ / ⚠️ middleware 不符 / ❌ 不存在。[cite: 3]

---

## 6. 業務規則對照（P4 附錄 B）[cite: 3]

13 條，每條跑指令、引用輸出、判斷實作值：[cite: 3]

| # | 規則 | 規格值 | grep 命令 |
|---|---|---|---|
| 1 | 初始誠信分數 | 60 | `grep -rn "credit_score_initial" backend/` |
| 2 | Email 驗證 +5 | 5 | `grep -nA 5 "email_verify\|wasChanged.*email_verified" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| 3 | 手機驗證 +5 | 5 | `grep -nA 5 "phone_verify\|wasChanged.*phone_verified" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| 4 | 男性 CC 驗證 +15 | 15 | `grep -nA 5 "adv_verify_male" backend/app/Services/CreditCardVerificationService.php` |
| 5 | 女性照片驗證 +15 | 15 | `grep -nA 5 "adv_verify_female" backend/app/Http/Controllers/Api/V1/Admin/VerificationController.php` |
| 6 | Email OTP 長度 | 6 位 | `grep -n "random_int.*999999\|str_pad" backend/app/Http/Controllers/Api/V1/AuthController.php \| head -10` |
| 7 | Email OTP TTL | 600 秒 | `grep -n "Cache::put.*email_verification" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| 8 | 手機 OTP TTL | 300 秒 | `grep -n "otp:phone\|Cache::put.*otpKey.*300" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| 9 | 手機 OTP 冷卻 | 60 秒 | `grep -n "cooldownKey\|cooldown.*60" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| 10 | 手機 OTP 失敗 5 次鎖 | 5 | `grep -n "attempts >= 5" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| 11 | 註冊年齡下限 | 18 | `grep -n "before:-18\|18 years" backend/app/Http/Controllers/Api/V1/AuthController.php` |
| 12 | reset token TTL | 60 分鐘 | `grep -nE "Password::sendResetLink\|reset.*expire\|expire.*60" backend/config/auth.php backend/app/` |
| 13 | SMS 文案時間 | 5 分鐘 | `grep -n "分鐘內有效" backend/app/Services/SmsService.php` |

---

## 7. 模組特有 P11 grep（死碼/重複/規格缺漏）[cite: 3]

bash
# P11.1 死碼掃描
# Auth Controller public method 對 routes/api.php 的引用
grep -nE "public function" backend/app/Http/Controllers/Api/V1/AuthController.php
# 對每個 method 跑 grep -n "AuthController::class.*'method'\|->method" backend/routes/api.php

# 前端 export 是否被 import
grep -nE "^export (async )?function|^export const|^export interface" \
  frontend/src/api/auth.ts frontend/src/api/verification.ts

# 對每個 export 跑：grep -rn "from.*api/auth\|from.*api/verification" frontend/src/

# P11.2 重複實作（語意搜尋）
# 1. phone E.164 轉換
grep -rn "toE164\|+886\|str_starts_with.*'09'" backend/app/

# 2. OTP 6 碼隨機生成
grep -rn "random_int(0, 999999)\|random_int(100000, 999999)" backend/app/

# 3. Mail 發送邏輯
grep -rn "Mail::to" backend/app/Http/Controllers/Api/V1/AuthController.php backend/app/Mail/

# 4. CheckSuspended middleware
ls backend/app/Http/Middleware/ | grep -i suspend
grep -rn "CheckSuspended\|check.suspended" backend/

# 5. PhoneVerification Controller vs AuthController::verifyPhone* 並存
ls backend/app/Http/Controllers/Api/V1/ | grep -iE "phone|verif"

# P11.3 規格 vs 程式碼雙向缺漏
# 規格列了但 code 沒實作（從 P1 對照表抽出 ❌ 端點）
# code 有但規格沒寫
grep -nE "Route::(post|get|patch|delete)" backend/routes/api.php | \
  grep -E "auth/|verification|me/change|me/verification" | head -20
# 對每條檢查 API-001 §2 是否有對應描述


---

## 8. 重點關注（Round 3 必查）[cite: 3]

### 8.1 規格 vs 實作分歧的 spec sync 進度[cite: 3]
Round 1 + Round 2 多次推薦「改規格」的 issue 是否已落地：[cite: 3]

*   A-002 / A2-001：register 回應結構（規格更新）[cite: 3]
*   A-003 / A2-002：status='active' 是否規格已調整[cite: 3]
*   A2-003：credit-card initiate 回應（payment_url vs aio_url+params 規格更新）[cite: 3]
*   A-004 / A2-005：register 是否仍缺 group 欄位（推薦改規格廢止）[cite: 3]
*   A-006 / A2-006：OTP 錯誤碼格式[cite: 3]

→ 對每條 grep `docs/API-001_前台API規格書.md` 看規格內容是否已修[cite: 3]

### 8.2 同根問題回歸：CheckSuspended[cite: 3]
*   A2-004（middleware 缺）+ A2-008（檔案不存在）是同根[cite: 3]
*   檢查：[cite: 3]
    ```bash
    ls backend/app/Http/Middleware/CheckSuspended.php
    grep -n "check.suspended" backend/app/Http/Kernel.php
    grep -n "check.suspended" backend/routes/api.php
    ```
*   回歸時兩個 issue 共用一個結論[cite: 3]

### 8.3 上一輪 quick win 是否真的修完[cite: 3]
*   A2-009（SMS 文案）：應該已改為 5 分鐘[cite: 3]
*   A2-010（死碼 export）：應該已移除[cite: 3]
*   對應修正應已 commit，本輪應該標 ✅ 已修[cite: 3]

---

## 9. 報告輸出[cite: 3]

依 `_common.md` §6 完整格式產出，存到：[cite: 3]
docs/audits/audit-A-{今天日期}-geminicli.md


**Header 必填欄位**：[cite: 3]

markdown
**執行日期：** {today}
**稽核者：** Gemini CLI（本機）
**Agent ID：** geminicli
**規格來源：**
  - docs/API-001 §2 + §16.3/§16.4
  - docs/PRD-001 §4.2.1
  - docs/DEV-008 §3 §4.1
  - docs/DEV-004 §3.1 §13.1
  - docs/UF-001 UF-01
**程式碼基準（Local）：** {完整 40 字元 hash}
**前次稽核（不分 agent，全部都要讀）：**
  - docs/audits/audit-A-20260422-claudecode.md
  - docs/audits/audit-A-20260424-claudecode.md
  - docs/audits/audit-A-20260427-codex.md
**總結：** {N} issues（🔴 a / 🟠 b / 🟡 c / 🔵 d）+ {M} Symmetric


---

## 10. Self-Check（產出前必跑）[cite: 3]

依 `_common.md` §7 全部檢查項：[cite: 3]

*   [ ] Header 包含完整 commit hash + Agent ID + 三份前次稽核連結[cite: 3]
*   [ ] §0 對所有前次 issue（A-001 ~ A-008、A2-001 ~ A2-010）全部用回歸判定方法標明本輪狀態[cite: 3]
*   [ ] 17 個規格端點全部出現在附錄 A[cite: 3]
*   [ ] 13 條業務規則全部出現在附錄 B[cite: 3]
*   [ ] P11.1 / P11.2 / P11.3 三項都有具體發現[cite: 3]
*   [ ] 每個 issue 引用的程式碼是實際讀檔出來的[cite: 3]
*   [ ] 每個 grep 命令的輸出在報告中至少出現一次[cite: 3]
*   [ ] 每個 issue 都有 file:line + Option A/B + 推薦[cite: 3]
*   [ ] Symmetric ≥ 10 條[cite: 3]
*   [ ] 報告檔名 `audit-A-{YYYYMMDD}-geminicli.md`[cite: 3]
*   [ ] git diff 只新增 docs/audits/ 下的單一檔案[cite: 3]

bash
git status
git diff --stat


---

## 11. 完成 commit（不 push）[cite: 3]

依 `_common.md` §9 情境 A：[cite: 3]

bash
git add docs/audits/audit-A-{YYYYMMDD}-geminicli.md
git commit -m "docs(audit): Audit-A Round 3 認證與身份驗證模組稽核完成

- 對 Round 1（claudecode x2）+ Round 2（codex）全部 issue 做回歸測試
- 17 個端點 + 13 條業務規則對照
- P11 死碼/重複/規格缺漏掃描
- 重點：CheckSuspended 同根問題、SMS 文案 quick win 回歸
- 總結：{N} issues 待處理（🔴 a / 🟠 b / 🟡 c / 🔵 d）
- 不變更任何程式碼

Agent: Gemini CLI
Base: {short hash}"


**不要 push、不要 merge**。[cite: 3] 等人類審查報告品質後才繼續。[cite: 3]

---

## 12. 完成後[cite: 3]

報告完成且 commit 後，**停下，不要自動進行下一個 audit**。[cite: 3]
等人類確認品質後告訴你下一步。[cite: 3]
