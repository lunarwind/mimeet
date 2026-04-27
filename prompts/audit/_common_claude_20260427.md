# Audit Common Preamble

> 此檔案被各 audit-*.md 引用，包含所有 audit 共用的指令與輸出規範。
> 任何 audit 開頭請先讀完此檔，再讀該 audit 自己的特定章節。

## 任務角色與紀律

你是 mimeet 專案的資深 code reviewer。
本任務目標：對 develop branch 執行規格 vs 程式碼稽核，產出標準格式報告。

**絕對不允許做的事：**
- ❌ 不要修改任何 backend/* 或 frontend/* 或 admin/* 程式碼
- ❌ 不要編造行號、檔名或不存在的程式碼
- ❌ 不要因「規格沒寫」就忽略明顯的 bug
- ❌ 不要因「程式碼合理」就降低與規格不符的 issue 等級
- ❌ 不要跳過 self-check
- ❌ 不要 push、不要 merge——只 commit 本地，等人類審查

## 環境檢查（每個 audit 開頭都要做）

```bash
# 確認 branch、commit hash、無未提交變更
git status
git rev-parse HEAD
git log -1 --pretty=format:'%H %s'

# 列出既有 audit 報告（看格式範本）
ls -la docs/audits/

# 必讀：對齊輸出格式的範本
cat docs/audits/audit-A-20260424.md | head -120
cat docs/audits/SUMMARY-20260424.md | head -50
```

## Issue 等級

| 等級 | 意義 |
|---|---|
| 🔴 Critical | 安全漏洞 / 認證繞過 / 資料毀損 / 金流錯帳 |
| 🟠 High | 規格與實作差異導致用戶流程斷裂、API 回 500/422 |
| 🟡 Medium | 欄位名歧異、語意不對、行為微差 |
| 🔵 Low | 命名、註解、規格文字過時、輕微 UX |
| ✅ Symmetric | 規格與實作一致（要列出來證明已查驗，每個 audit 至少 10 條）|

## 標準 10 + 1 個 Pass

| Pass | 檢查項 |
|---|---|
| P1 路由層 | 規格端點是否存在於 routes/api.php |
| P2 請求 Payload | Controller validate() vs 規格 request body |
| P3 回應結構 | Controller response JSON vs 規格 response example |
| P4 業務規則 | 數值閾值、system_settings keys、狀態機 |
| P5 錯誤碼 | error code 字串 vs 規格錯誤碼表 |
| P6 認證中介層 | auth:sanctum / auth:admin / 權限 middleware |
| P7 前端 API 層 | TS interface vs 後端實際回應 |
| P8 前端 UI 層 | 頁面實作 vs UI-001 / UF-001 描述 |
| P9 邊界條件 | null / 上下限 / 重複提交 / 軟刪除可見性 |
| P10 跨模組副作用 | Cache::forget / Mail / Event / Scheduler |
| P11 死碼/重複/規格缺漏 | grep 找未引用的 method、重複實作、規格 vs code 雙向缺漏 |

## 報告輸出格式（完全對齊 audit-A-20260424.md）

存到 `docs/audits/audit-{代號}-{YYYYMMDD}.md`，必含章節：

```markdown
# Audit Report {代號} — {模組名稱}

**執行日期：** {today}
**稽核者：** Claude Code
**規格來源：** {對應 docs}
**程式碼基準（Local）：** {git rev-parse HEAD}
**前次稽核：** {既有 audit-{代號}-*.md 連結，若有}
**總結：** {N} issues（🔴 a / 🟠 b / 🟡 c / 🔵 d）+ {M} Symmetric

## 0. 前次 Issue 回歸狀態（若存在前次稽核）
| Issue | 前次等級 | 本輪狀態 | 備註 |

## 1. Pass 完成記錄
| Pass | 範圍 | Issues 發現 |

## 2. Issues 索引（依等級排序）
### 🔴 Critical / 🟠 High / 🟡 Medium / 🔵 Low / ✅ Symmetric

## 3. Issue 詳情（每個 issue）
- **Pass：** P?
- **規格位置：** docs/...:section
- **規格內容：** ```...```
- **程式碼位置：** path/file.php:行號
- **程式碼現況：** ```...```
- **差異說明：** ...
- **等級：** 🔴/🟠/🟡/🔵
- **建議方案：** Option A/B/C（pros/cons 各列）
- **推薦：** A/B/C，理由

## 4. 行動優先序
| 優先 | 動作 | 對象 |

## 5. 下次 Audit 建議
- ...
```

## P11 標準掃描（共用模板）

```bash
# P11.1 死碼掃描
# 後端 Controller public method vs 路由引用
grep -nE "public function" {本 audit Controller 檔案}
# → 對每個 method 確認 routes/api.php 是否引用

# 前端 export 是否有 import
grep -nE "^export (async )?function|^export const|^export interface" {本 audit 前端檔案}
# → 對每個 export 跑：grep -rn "from.*{相對路徑}" frontend/src/ admin/src/

# P11.2 重複實作
# 用語意關鍵字找跨檔案重複
grep -rn "{語意關鍵字 1}" backend/app/
grep -rn "{語意關鍵字 2}" backend/app/

# P11.3 規格 vs 程式碼雙向缺漏
# 規格列了但 code 沒實作 → 🟠 High
# code 有但規格沒寫 → 🔵 Low（建議補規格）
# 命名不一致（如 ticket_no vs ticket_number）→ 🟡 Medium
```

## Self-Check（每個 audit 產出前必跑）

完成後自我檢查：

- [ ] Header 包含 commit hash + 規格來源 + 前次稽核連結（若有）
- [ ] 前次 issue 全部標明本輪狀態（已修 / 未修 / 部分修）
- [ ] 規格清單中每個端點/規則都在 P1–P10 表格出現
- [ ] P11 三項都有具體發現（即使是 ✅ 無問題也要寫）
- [ ] 每個 issue 都有檔名:行號
- [ ] 每個 issue 都有 Option A/B（甚至 C）+ 推薦
- [ ] Symmetric 區塊至少 10 條
- [ ] 報告檔名格式正確 `audit-{代號}-{YYYYMMDD}.md`
- [ ] git diff 只新增 docs/audits/ 下的單一檔案

```bash
git status
git diff --stat
```

確認無其他檔案變動。

## 完成 commit（不 push、不 merge）

```bash
git add docs/audits/audit-{代號}-{今天日期}.md
git commit -m "docs(audit): Audit-{代號} {模組} 稽核完成

- {重點發現 1}
- {重點發現 2}
- 總結：{N} issues 待處理（🔴 a / 🟠 b / 🟡 c / 🔵 d）
- 不變更任何程式碼"
```

完成後停下，**不要自動進行下一個 audit**，等人類確認品質後才繼續。
