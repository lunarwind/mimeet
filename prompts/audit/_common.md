# Audit Common Preamble

> 此檔案被各 audit-{x}.md 引用，包含所有 audit 共用的指令與輸出規範。
> 任何 audit 開頭請先讀完此檔，再讀該 audit 自己的特定章節。
> 適用 agent：Codex CLI / ChatGPT 雲端 Codex / Claude Code。

---

## 1. 任務角色與紀律

你是 mimeet 專案的資深 code reviewer。
本任務不限定使用哪個 AI agent,請依下列規範產出報告。

任務目標:對 develop branch 執行規格 vs 程式碼稽核,產出標準格式報告。

### 1.1 絕對不允許做的事

- ❌ 不要修改任何 backend/* 或 frontend/* 或 admin/* 程式碼
- ❌ 不要編造行號、檔名或不存在的程式碼
- ❌ **每個 issue 引用的程式碼片段必須是實際 cat / view 出來的內容**,
  不允許「依印象重寫」即使邏輯上看起來合理
- ❌ **每個 grep 命令的輸出必須在報告或推理過程中至少出現一次**,
  不允許跑了 grep 卻不引用結果就直接下結論
- ❌ 不要因「規格沒寫」就忽略明顯的 bug
- ❌ 不要因「程式碼合理」就降低與規格不符的 issue 等級
- ❌ 不要跳過 self-check
- ❌ 不要 push 到 remote、不要直接 merge

### 1.2 雲端 Codex 額外限制（New）

> 此節適用所有雲端執行的 agent（ChatGPT 雲端 Codex 等）。
> 雲端 agent 從 GitHub 取最新 develop snapshot 跑 audit，與本機 Claude Code
> 修 code 的工作可能並行，必須嚴守職責邊界，避免雙頭修改造成衝突。

❌ 不允許動以下檔案/目錄（即使覺得「順手修一下」）:
   - backend/**
   - frontend/**
   - admin/**
   - docker/**
   - .github/**
   - prompts/** （prompt 由人類維護）
   - docs/** （**audit 結果以外的所有 docs**）

✅ 唯一允許新增的檔案:
   - docs/audits/audit-{代號}-{YYYYMMDD}-{agent}.md
   - docs/audits/SUMMARY-{YYYYMMDD}.md（僅當批次跑完所有 audit 才產）

✅ 唯一允許動的既有檔案:
   - 無

如果你發現 audit 過程中需要修 code 才能驗證,**停下來在報告 §5 提建議**,
不要動手修。修 code 是本機 Claude Code 的工作,不是雲端 agent 的工作。

PR 落地時:
- 目標分支 develop（不是 main）
- 不勾選 Auto-merge
- PR 描述開頭加註:「⚠️ 此為純文件 PR,不修改任何程式碼,請審查後手動合併」

### 1.3 紀律提示

- 你只在報告檔（docs/audits/audit-{代號}-{日期}-{agent}.md）裡寫東西。
  其他所有檔案是唯讀。
- 如果你不確定一個檔案的內容,先 cat / view,**不要憑印象**。
- 每跑一個 grep,把結果在腦中或暫存區留一份,之後 issue 會用到。

---

## 2. 環境檢查（每個 audit 開頭都要做）

### 2.1 確認工作目錄
````bash
pwd
ls -la | head -20  # 應該看到 backend/ admin/ frontend/ docs/ 等目錄
````
若工作目錄不對,停下來請使用者指示,**不要嘗試 cd**。

### 2.2 確認 branch、commit、無未提交變更
````bash
git status
git rev-parse HEAD
git log -1 --pretty=format:'%H %s'
````

**這個 HEAD hash 就是本輪報告的「程式碼基準」,必須完整記到報告 Header**。
讀者後續驗證 issue 時,會 `git checkout {hash}` 切到此快照重現。

### 2.3 確認本機與遠端同步（雲端 agent 略過）
````bash
git fetch origin develop
local_hash=$(git rev-parse develop)
remote_hash=$(git rev-parse origin/develop)
[ "$local_hash" = "$remote_hash" ] && echo "✅ 同步" || echo "❌ 不同步,請先 git pull"
````

### 2.4 必讀:對齊輸出格式的範本
````bash
ls -la docs/audits/
cat docs/audits/audit-A-20260427-codex.md | head -120
cat docs/audits/SUMMARY-20260424.md 2>/dev/null | head -50
````

把以上輸出記下來,填到報告 Header 區。

---

## 3. Issue 等級

| 等級 | 意義 | 範例 |
|---|---|---|
| 🔴 Critical | 安全漏洞 / 認證繞過 / 資料毀損 / 金流錯帳 | 公開端點觸發 SMS 計費;payment callback 無簽章驗證 |
| 🟠 High | 規格與實作差異導致用戶流程斷裂、API 回 500/422 | 規格端點未實作;payload 結構錯誤 |
| 🟡 Medium | 欄位名歧異、語意不對、行為微差 | `ticket_no` vs `ticket_number`;regex 寫錯但仍會通過 |
| 🔵 Low | 命名、註解、規格文字過時、輕微 UX | 文案與 API 行為小矛盾;未引用的 export |
| ✅ Symmetric | 規格與實作一致（要列出來證明已查驗,每個 audit 至少 10 條）|

---

## 4. 共用判斷規則

### 規則 A:發現「規格 vs 實作」分歧時
- 不要預設「改程式碼」,要評估雙方哪邊更合理:
  - 程式碼比規格新且已被前後端依賴 → 推薦「改規格」+ 加註修訂日期
  - 規格描述更安全/更業務正確 → 推薦「改程式碼」
- 三個以上 audit 都推薦「改規格」的同類 issue,要在報告 §4 行動優先序中
  集中標記,提示後續 PM/架構師做一次性 spec sync

### 規則 B:同一 root cause 拆成多個 issue 時
- 在 issue 詳情中加 cross-ref 行:`> 同根問題,亦見 #X-NNN`
- §4 行動優先序合併處理
- 兩個編號保留(不同 Pass 觸發合理),但要提醒讀者一起修

### 規則 C:「應用層保護 vs 路由層保護」不能混為一談
- Application-layer Cache 鎖只擋單一身份累積錯誤
- Route-level throttle middleware 才能擋分散式撞庫
- 兩者並存判 ✅
- 缺 throttle middleware:
  - 與安全強相關（登入、註冊、SMS、密碼重設）→ 🟠 High
  - 一般 API → 🟡 Medium

### 規則 D:用戶可見訊息 vs 系統行為矛盾
對於用戶會直接看到的訊息(SMS 文字、Email 主旨、Toast 文案、UI 提示),
若與系統實際行為(TTL、上限、回應碼)矛盾:
- 即使數值差距不大,影響 UX → 至少 🟡 Medium
- 客服會被打爆的情境(如「10 分鐘有效」實際 5 分鐘)→ 🟠 High

### 規則 E:Phase 2 標注的處理原則
- 規格明確標「Phase 2 / 未實作」+ Code 不存在 → ✅ Symmetric(一致)
- 規格未標 Phase 2 + Code 不存在 → 🟠 High(規格需更新或補實作)
- 規格已標 Phase 2 + Code 殘留半套實作 → 🟡 Medium(清死碼或正式啟用)

### 規則 F:「程式碼合理就降級」陷阱
- 例:規格說「訪客 90 天保留」,code 寫 60 天——即使 60 天「也合理」,仍是 🟡 Medium
- 規格未明訂的細節,code 行為合理 → ✅ Symmetric + §5 建議補規格
- 規格明訂但 code 不一致 → 不可降級到 🔵

### 規則 G:grep pattern 精確度
- `grep "stealth"` 會撈到註解、變數名、字串值,產生大量假陽性
- 用更精確的 pattern:
  - 看欄位寫入:`grep -nE "stealth_until\s*="`
  - 看欄位讀取:`grep -nE "stealth_until\s*<="`
  - 看 method 呼叫:`grep -nE "isStealthActive\("`
- 假陽性嚴重時,將原始輸出與篩選後輸出都列入工作筆記

---

## 5. 標準 10 + 1 個 Pass

| Pass | 檢查項 | 必查內容 |
|---|---|---|
| P1 路由層 | 規格端點是否存在於 routes/api.php | 對照規格端點清單,列附錄 A |
| P2 請求 Payload | Controller validate() vs 規格 request body | 必填欄位、型別、enum 範圍 |
| P3 回應結構 | Controller response JSON vs 規格 response example | 頂層欄位、巢狀 data 結構 |
| P4 業務規則 | 數值閾值、system_settings keys、狀態機 | 對照表列附錄 B |
| P5 錯誤碼 | error code 字串 vs 規格錯誤碼表 | HTTP status + code 字串 |
| P6 認證中介層 | auth:sanctum / auth:admin / 權限 middleware | 規則 C 同步檢查 |
| P7 前端 API 層 | TS interface vs 後端實際回應 | snake_case vs camelCase 轉換 |
| P8 前端 UI 層 | 頁面實作 vs UI-001 / UF-001 描述 | 必要元素、互動邏輯 |
| P9 邊界條件 | null / 上下限 / 重複提交 / 軟刪除可見性 | 至少列 8-10 個邊界 |
| P10 跨模組副作用 | Cache::forget / Mail / Event / Scheduler | 每個寫入操作都要列副作用 |
| P11 死碼/重複/規格缺漏 | grep 找未引用的 method、重複實作、規格 vs code 雙向缺漏 | 三項各至少 3 個發現 |

---

## 6. 報告輸出格式（完全對齊 audit-A-20260427-codex.md）

存到 `docs/audits/audit-{代號}-{YYYYMMDD}-{agent}.md`,必含章節。

### 6.1 檔名規範

**檔名 agent 標識規範:**

| Agent | 標識 | 何時用 |
|---|---|---|
| ChatGPT 雲端 Codex | `codex` | 雲端任務(預設) |
| Codex CLI | `codex-cli` | 需與雲端區分時 |
| Claude Code | `claudecode` | 本機 Claude Code session |
| Cursor / 其他 | `{產品名小寫}` | 如 `cursor`、`aider` |

**規則:**
- 標識小寫、無底線
- 不確定該用什麼時,**問使用者,不要自己編**
- 同一天同 agent 重跑同模組 → 加 `-r2` 後綴:
  `audit-A-20260427-codex-r2.md`
- 不要動 SUMMARY-*.md 的命名(彙整檔,無 agent 概念)

### 6.2 報告結構

````markdown
# Audit Report {代號} — {模組名稱}

**執行日期:** {today}
**稽核者:** {Codex (CLI) / ChatGPT Codex / Claude Code}
**Agent ID:** {對應檔名的 agent 標識,如 `codex` / `claudecode`}
**規格來源:**
  - {對應的 docs/...}
**程式碼基準（Local）:** {完整 git rev-parse HEAD,40 字元}
**前次稽核（不分 agent,全部都要讀）:**
  - {既有 audit-{代號}-*-*.md 連結,若有;否則填「無」}
**總結:** {N} issues(🔴 a / 🟠 b / 🟡 c / 🔵 d)+ {M} Symmetric

---

## 0. 前次 Issue 回歸狀態（若存在前次稽核）

### 回歸判定方法（New）

對每個前次 issue:

1. 取前次報告 Header 的「程式碼基準」commit hash
2. 跑 `git diff {前次 hash}..HEAD -- {issue 引用的檔案}`
3. 判定本輪狀態:
   - **無 diff** → issue 仍然有效,本輪狀態 = 前次狀態
   - **有 diff,問題仍在** → 標 ❌ 未修
   - **有 diff,問題已解決** → 標 ✅ 已修
   - **有 diff,問題部分解決** → 標 ⚠️ 部分修
   - **檔案已不存在 / 重構** → 標「⚠️ 檔案重構」並追問實作位置
4. 若不同 agent 報告對同一 issue 結論不同,以最新一份為準,
   並在備註欄注明分歧:「⚠️ 與 #X-NNN(agent A)結論不同」

### 回歸狀態表

| Issue | 前次等級 | 前次基準 | 本輪狀態 | 備註 |
|---|---|---|---|---|
| #X-NNN | 🟠 High | {short hash} | ✅ 已修 / ❌ 未修 / ⚠️ 部分修 | 一句話說明 |

---

## 1. Pass 完成記錄

| Pass | 範圍 | Issues 發現 |
|---|---|---|
| P1 路由層 | {N} 個端點 vs Local | {新發現的 issue 編號或 ✅} |
| ... | ... | ... |
| P11.1 死碼 | {範圍} | {發現} |
| P11.2 重複 | {範圍} | {發現} |
| P11.3 規格缺漏 | {範圍} | {發現} |

---

## 2. Issues 索引（依等級排序）

### 🔴 Critical
- Issue #{代號}2-NNN — {一句話標題}

### 🟠 High
- ...

### 🟡 Medium
- ...

### 🔵 Low
- ...

### ✅ Symmetric（至少 10 條）
- {端點/規則} — {一句話說明對齊狀況}
- ...

---

## 3. Issue 詳情

### Issue #{代號}{輪次}-NNN
**Pass:** P? (多 Pass 觸發以 `,` 分隔,如 `P3, P7`)
**規格位置:** docs/...:section(行 NNN-MMM)
**規格內容:**
````
{從規格實際 cat 出來的內容,不超過 10 行}
````
**程式碼位置:** `path/to/file.ext:行號`
**程式碼現況:**
```{language}
{從檔案實際 cat 出來的內容,不超過 15 行;不允許依印象重寫}
```
**差異說明:** {一段話描述差異與影響,不超過 4 行}
**等級:** 🔴/🟠/🟡/🔵
**建議方案:**
- Option A:{一句話 + pros/cons}
- Option B:{一句話 + pros/cons}
- Option C:{若有第三方案}
**推薦:** A/B/C,理由 {一句話}
**相關 issue:** {若有同根問題:> 同根問題,亦見 #X-NNN;否則省略此行}

---

## 4. 行動優先序

| 優先 | 動作 | 對象 |
|---|---|---|
| P1 | {緊急動作} | {BE / FE / PM / 架構} |
| P2 | ... | ... |

---

## 5. 下次 Audit 建議

- {建議 1}
- ...

---

## 附錄 A — P1 端點逐條檢查（每個 audit 必附）

| # | 端點 | 路由存在 | Middleware | 狀態 | 備註 |
|---|---|---|---|---|---|
| 1 | {METHOD} {path} | ✅/❌ | {middleware list} | ✅/⚠️/❌ | {一句話} |

---

## 附錄 B — P4 業務規則對照（每個 audit 必附）

| # | 規則 | 規格值 | 實作值 | 出處 | 狀態 |
|---|---|---|---|---|---|
| 1 | {規則描述} | {規格定義的值} | {從 code 讀出的值} | {file:line} | ✅/❌ |
````

---

## 7. Self-Check（在 §6 之後、commit 之前必跑）

- [ ] Header 包含完整 commit hash(40 字元)+ 規格來源 + Agent ID + 前次稽核連結(若有)
- [ ] 前次 issue 全部使用「回歸判定方法」標明本輪狀態(已修 / 未修 / 部分修)
- [ ] 規格清單中每個端點/規則都在 P1–P10 表格出現
- [ ] P11 三項都有具體發現(即使是 ✅ 無問題也要寫)
- [ ] **每個 issue 引用的程式碼是實際 cat 出來的,不是依規格反推**
- [ ] **每個 grep 命令的輸出在某處被引用過**(推理過程或附錄)
- [ ] 每個 issue 都有檔名:行號
- [ ] 每個 issue 都有 Option A/B(甚至 C)+ 推薦 + 理由
- [ ] Symmetric 區塊至少 10 條
- [ ] 報告檔名格式正確 `audit-{代號}-{YYYYMMDD}-{agent}.md`
  (代號大寫、agent 小寫)
- [ ] git diff 只新增 docs/audits/ 下的單一檔案

````bash
git status
git diff --stat
````

確認無其他檔案變動。

---

## 8. P11 標準掃描模板

各 audit 模組會在自己的 prompt 中列出特定的 P11 grep 命令。
共用模板如下:

````bash
# P11.1 死碼掃描
# 後端 Controller public method vs 路由引用
grep -nE "public function" {本 audit Controller 檔案}
# → 對每個 method 確認 routes/api.php 是否引用

# 後端 Service public method vs 外部呼叫
grep -nE "public function" {本 audit Service 檔案}
# → 對每個 method grep 整個 backend/app/ 是否有外部呼叫

# 前端 export 死碼
grep -nE "^export (async )?function|^export const|^export interface" {本 audit 前端檔案}
# → 對每個 export grep 整個 frontend/src/ admin/src/ 是否有 import

# P11.2 重複實作
# 用語意關鍵字找跨檔案重複(每個 audit 列 5-10 條語意搜尋)
grep -rn "{語意關鍵字}" backend/app/ frontend/src/ admin/src/

# P11.3 規格 vs 程式碼雙向缺漏
# - 規格列了但 code 沒實作 → 🟠 High
# - code 有但規格沒寫 → 🔵 Low(建議補規格)
# - 命名不一致(如 ticket_no vs ticket_number)→ 🟡 Medium
````

---

## 9. 完成 commit（依執行環境分流）

### 情境 A:Codex CLI / Claude Code(本機執行)
````bash
# 注意:實際檔名為 audit-{代號}-{今天日期}-{agent}.md
git add docs/audits/audit-{代號}-{今天日期}-{agent}.md
git commit -m "docs(audit): Audit-{代號} {模組} 稽核完成

- {重點發現 1}
- {重點發現 2}
- 總結:{N} issues 待處理(🔴 a / 🟠 b / 🟡 c / 🔵 d)
- 不變更任何程式碼

Agent: {Codex / Claude Code}
Base: {short hash}"
````
**不要 push、不要 merge。** 等人類確認品質後才 push。

### 情境 B:ChatGPT 雲端 Codex(自動開 PR)
- PR 標題:`docs(audit): Audit-{代號} {模組} 稽核完成`
- PR 描述:複製上面 commit message 的多行內容
- **目標分支:develop**(不是 main)
- 不要勾選「Auto-merge」
- PR 描述開頭加註:
  > ⚠️ 此為純文件 PR,不修改任何程式碼,請審查後手動合併

PR merge 規則:
- 用 **merge commit**(GitHub 預設)
- 不要用 squash and merge(會丟失基準 hash 的歷史關聯)
- 保留 commit 樹的「audit 是基於哪個 develop 狀態跑的」歷史

完成後**停下,不要自動進行下一個 audit**。等人類確認品質後才繼續。

---

## 10. 預期常見陷阱（事前提醒）

根據 Audit-A 兩輪稽核累積經驗,以下是常見錯誤:

### 10.1「規格沒提就不算 issue」陷阱
- 例:F27 進階篩選 14 個欄位,若有 1-2 個沒實作,即使規格描述含糊,仍應標 🟠 High
- 不要因為 grep 找不到就跳過;要明確標 ❌ 不存在

### 10.2「程式碼合理就降級」陷阱
- 見規則 F:規格明訂的差異不可降級到 🔵

### 10.3「Cache 鎖 = 防護完整」陷阱
- 見規則 C:應用層 Cache 鎖 ≠ Route throttle

### 10.4「私有 method 不算死碼」陷阱
- P11.1 主要看 public method
- 但 private method 若沒被該 class 內任何 method 呼叫,也是死碼

### 10.5「regex 太寬鬆造成假陽性」陷阱
- 見規則 G:`grep "stealth"` 會撈到註解、字串值、變數名

### 10.6「規格內文貼太多」陷阱
- Issue 詳情中規格內容只貼**直接相關的 5-10 行**
- 不要貼整個 §3.2.1(會讓報告膨脹到無法 review)

### 10.7「同根 issue 拆太散」陷阱
- 見規則 B:同一 root cause 拆成多個 issue 時要 cross-ref

### 10.8「Symmetric 太少導致報告失衡」陷阱
- 至少 10 條,證明你真的查驗過
- 不是只列「找到的 issue」,而是要證明「沒找到 issue 的部分也查過了」

### 10.9「跨 agent 報告結論衝突卻沒標注」陷阱(New)
- 多 agent 平行跑同模組時,可能出現結論分歧:
  - 例:claudecode 標 #B-001 為 🟠 High,codex 標同位置為 🟡 Medium
- 進行回歸測試時,務必比對所有前次報告(不分 agent)
- 結論分歧時,在 §0 備註欄注明:「⚠️ 與 #X-NNN(agent A)結論不同,本輪採 B 評估」

### 10.10「checkout 錯版本」陷阱(New)
- 雲端 agent 的 fork 可能慢於本機 develop 幾個 commit
- 報告 Header 寫「程式碼基準」時,寫**你實際在 cat 的 commit hash**,
  不是「最新 develop」這種模糊描述
- 跑 audit 過程中如果發現 git log 有新 commit 進來(不應該發生但偶有),
  停下來在報告 §5 警告

---

## 11. 開始執行流程

1. 完成 §2 環境檢查
2. 進入該 audit 自己的 prompt 檔(讀 `prompts/audit/audit-{代號}.md`)
3. 依規格範圍和檔案清單,逐 Pass 執行
4. 每完成一個 Pass,立刻記下發現(不要堆到最後)
5. 全部 Pass 完成後,進入 §6 撰寫報告
6. 對前次 issue 用「回歸判定方法」逐一比對
7. 跑 §7 Self-Check
8. 跑 §9 commit
9. 停下,等人類審查
