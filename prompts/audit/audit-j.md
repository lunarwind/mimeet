# Audit-J Round 1 — 死碼 / 重複實作 / 冗餘程式碼

> 先讀 prompts/audit/_common.md。
> 此 audit 是「跨模組品質掃描」，不對單一規格章節，產出全 repo 重構建議清單。

## 任務目標
找出：
1. **死碼**：未被引用的 Controller method、Service method、TS export、Vue/React component
2. **重複實作**：同一語意的邏輯散落多處（未抽 helper / 未繼承 base class）
3. **過時實作**：規格已標 Phase 2 但 code 殘留
4. **命名歧異**：同一概念用多個名字（ticket_no vs ticket_number）
5. **規格 vs code 雙向缺漏**：規格寫了沒實作 / 實作了沒寫規格

## 工具搭配（強烈建議先跑）

```bash
# Backend (PHP) — 安裝後跑
composer require --dev nunomaduro/phpinsights --no-interaction
./vendor/bin/phpinsights analyse backend/app/ --no-interaction --format=console > /tmp/phpinsights.txt 2>&1 || true

# Frontend (TS)
cd frontend && npx ts-prune --error 2>&1 | tee /tmp/ts-prune-frontend.txt
cd ../admin && npx ts-prune --error 2>&1 | tee /tmp/ts-prune-admin.txt

# 跨語言重複程式碼
cd ..
npx jscpd ./backend/app ./admin/src ./frontend/src \
  --min-lines 5 --min-tokens 50 \
  --reporters console,markdown \
  --output /tmp/jscpd 2>&1 | tee /tmp/jscpd.txt

# 把這三份輸出列入報告附錄
```

## 程式碼範圍
跨整個 repo，不限模組。但建議聚焦在：

```bash
backend/app/
admin/src/
frontend/src/
```

## 模組特有檢查（11 個面向）

### J-1 後端 Controller 死碼
```bash
# 列出所有 public method
for f in backend/app/Http/Controllers/Api/V1/*.php; do
  echo "=== $f ==="
  grep -nE "public function" "$f"
done

# 對每個 method，檢查 routes/api.php 是否引用
# 沒被引用的標記為「可疑死碼」（注意：可能透過 invoke 或 magic call）
```

### J-2 Service 死碼
```bash
for f in backend/app/Services/*.php; do
  grep -nE "public function" "$f" > /tmp/methods.txt
  while IFS= read -r line; do
    method=$(echo "$line" | grep -oE "function [a-zA-Z_]+" | sed 's/function //')
    count=$(grep -rn "$method(" backend/app/ --include="*.php" | grep -v "$f:" | wc -l)
    [ "$count" -eq 0 ] && echo "❓ $f::$method 無外部呼叫"
  done < /tmp/methods.txt
done
```

### J-3 前端 export 死碼
```bash
# ts-prune 已產出，這裡補一些手工檢查
grep -rnE "^export (async )?function|^export const|^export interface" frontend/src/api/ admin/src/api/
# 對應 grep -rn "from.*api/{filename}" 看是否有 import
```

### J-4 重複實作（語意搜尋）
```bash
# 1. CheckMacValue / SHA256 雜湊
grep -rn "hash('sha256'\|sha256(" backend/app/

# 2. E.164 phone 轉換
grep -rn "+886\|toE164\|str_starts_with.*09" backend/app/

# 3. OTP 6 碼隨機生成
grep -rn "random_int(0, 999999)\|random_int(100000, 999999)" backend/app/

# 4. 信用分數讀取
grep -rn "SystemSetting::get\|getConfig" backend/app/Services/

# 5. Cache key 命名規範
grep -rn 'Cache::(put|get|forget)\(["\x27][a-z_]+:' backend/app/

# 6. 軟刪除查詢
grep -rn "whereNull\('deleted_at'\)\|withTrashed" backend/app/

# 7. 軟刪除 vs 排除 user.id 過濾
grep -rn "where('id', '>', 1)\|where('id', '!='" backend/app/

# 8. JSON 回應結構（success/code/message）
grep -rn "'success' => true.*'code'.*'message'" backend/app/Http/Controllers/ | wc -l

# 9. 頭像 URL 組裝
grep -rn "avatar_url\|getAvatarUrl" backend/app/

# 10. 訂單 / order_no 生成
grep -rn "MM\|order_number\|generateOrderNo" backend/app/
```

### J-5 過時實作（Phase 2 殘留）
```bash
# 動態 / 匿名聊天 / FCM 實際完整度
grep -rn "Post::class\|Anonymous\|FcmService" backend/app/

# 規格已標但 code 殘留
grep -rn "TODO\|FIXME\|XXX\|legacy\|deprecated" backend/app/ frontend/src/ admin/src/
```

### J-6 命名歧異
```bash
# ticket_no vs ticket_number
grep -rn "ticket_no\|ticket_number" backend/ frontend/ admin/

# user_id vs uid
grep -rn "'user_id'\|'uid'" backend/app/Http/

# created_at_human vs createdAtHuman
grep -rn "created_at_human\|createdAtHuman" frontend/src/ admin/src/

# adjust_credit vs adjust_score
grep -rn "adjust_credit\|adjust_score" backend/app/ admin/src/
```

### J-7 設定散落
```bash
# 同個值同時在 .env / config / system_settings 三處
grep -rn "ECPAY\|ecpay_" backend/.env.example backend/config/ backend/database/seeders/

# 開發/測試 fixture 寫死的常數
grep -rn "'admin@\|test@\|Test1234\|password123'" backend/
```

### J-8 規格 vs code 缺漏盤點

讀 docs/audits/SUMMARY-20260424.md「規格書需修改清單」，逐項檢查目前是否仍有差異：

```bash
# 規格列了 19 項待更新規格的項目
grep -A 30 "規格書需修改清單" docs/audits/SUMMARY-20260424.md
```

對每一項：
- ✅ 規格已修正
- ⏳ 仍未修正
- ❓ 需重新評估

### J-9 Migration 累贅
```bash
# 同一欄位被多次 alter（add → modify → drop → re-add）
grep -rn "alter.*ADD\|alter.*DROP\|->dropColumn\|->renameColumn" backend/database/migrations/ | head -30

# 已被 drop 的欄位是否還在 Model fillable 中
```

### J-10 前端冗餘元件
```bash
# component 是否有 v1 / v2 / -old / -new 後綴
find frontend/src/components admin/src/components -name "*v1*" -o -name "*v2*" -o -name "*-old*" -o -name "*-new*"

# .vue / .tsx 檔但 0 import
find frontend/src/ admin/src/ -name "*.vue" -o -name "*.tsx" | while read f; do
  base=$(basename "$f" | sed 's/\.[a-z]*$//')
  count=$(grep -rn "import.*$base" frontend/src/ admin/src/ 2>/dev/null | wc -l)
  [ "$count" -eq 0 ] && echo "❓ $f 未被 import"
done
```

### J-11 開發/測試殘留
```bash
# console.log / dd() / dump()
grep -rn "console\.log\|console\.warn" frontend/src/ admin/src/ | grep -v "//.*console" | head -20
grep -rn "dd(\|var_dump\|print_r" backend/app/ | head -10

# 註解掉的程式碼大區塊
grep -rB 2 -A 2 "^\s*//.*function\|^\s*/\*" backend/app/ frontend/src/ admin/src/ | head -30
```

## 報告輸出特殊要求
- 把 phpinsights / ts-prune / jscpd 的關鍵摘要列入「附錄 A 工具輸出」
- 每個 J-N 面向至少列 3 個具體發現（即使是 ✅ 無問題也要寫）
- Issue 等級判定：
  - 🔴 安全性死碼（如未被擋住的 admin endpoint）
  - 🟠 業務邏輯重複（兩處算同一個值卻會分歧）
  - 🟡 命名歧異
  - 🔵 真死碼、註解、console.log

## 完成

```bash
git add docs/audits/audit-J-*.md
git commit -m "docs(audit): Audit-J 跨模組品質掃描完成

- 死碼: {N} 處
- 重複實作: {N} 處
- 過時程式碼: {N} 處
- 命名歧異: {N} 處
- 規格 vs code 缺漏: {N} 處
- 工具輸出已附錄"
```
