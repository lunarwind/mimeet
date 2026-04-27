# Audit-I Round 1 — UI/UX 規格 vs 前端實作

> 先讀 prompts/audit/_common.md。
> 此 audit 是新增項目，無前次稽核可比對。

## 任務目標
比對 UI-001 描述的設計系統、頁面結構、互動規範，與前端實際實作（admin + frontend）的差異。

## 規格範圍
- docs/UI-001 完整全文
  - §1 設計理念
  - §2 設計系統（色彩、字型、間距、Component）
  - §3 前台頁面規格
  - §4 設計細節
  - §5 後台設計規格
  - §6 CSS 實作注意事項

## 程式碼範圍

```bash
# 全域樣式
frontend/src/assets/variables.css
frontend/src/assets/main.css
frontend/src/assets/theme/  # 若存在
admin/src/styles/  # 若存在

# 前台關鍵頁面
frontend/src/views/  # 整個目錄
frontend/src/components/  # 整個目錄

# 後台關鍵頁面
admin/src/pages/  # 整個目錄
admin/src/components/  # 整個目錄
admin/src/layouts/

# Tailwind / Ant Design 設定
frontend/tailwind.config.js  # 若存在
admin/src/main.tsx  # antd theme provider
```

## 模組特有檢查

### P4 設計系統一致性
| 項目 | 規格值 | grep |
|---|---|---|
| 主色 #F0294E | 前後台統一 | `grep -rn "#F0294E\|#f0294e" frontend/src/ admin/src/` |
| 主色變體（50-900） | 10 階 | `grep -rn "color-primary-" frontend/src/assets/` |
| 誠信分數色（top/good/normal/low）| 4 級 | `grep -rn "credit-top\|credit-good\|credit-normal\|credit-low" frontend/src/` |
| Neutral 灰階 | 50-900 | `grep -rn "color-neutral-" frontend/src/assets/` |
| Checkbox 邊框 | #9CA3AF | `grep -rn "#9CA3AF\|neutral-400" admin/src/` |
| Switch 開啟色 | #F0294E | `grep -rn "Switch.*color\|switch-checked" admin/src/` |

### P7 元件一致性
```bash
# 前台 Button 元件變體（primary / secondary / ghost）
grep -rn "BaseButton\|btn-primary\|btn-secondary" frontend/src/

# 後台 Antd 元件被自訂 ConfigProvider 覆寫的地方
grep -rn "ConfigProvider\|theme.*token" admin/src/

# 全站使用 emoji 的位置（規格 UI-001 §6 對 emoji 使用有規範）
grep -rn "[🌟⭐✅❌🔴🟠🟡🔵💎📢📅]" frontend/src/components/ admin/src/components/
```

### P8 頁面 vs 規格章節對照
對照 UI-001 §3（前台）和 §5.3（後台）描述的每個頁面，逐一檢查：

**前台頁面：**
- Landing Page（/）
- Login / Register
- Explore（含篩選 Bottom Sheet）
- ProfileDetail
- Messages / ChatRoom
- Dates / QRScan
- Visitors / Favorites
- Notifications
- Shop / Trial / Subscription
- Settings 系列頁

**後台頁面：**
- Dashboard（KPI 卡片 + 趨勢圖 + 圓餅圖）
- Members（列表 + 詳情）
- Tickets / Verifications / ChatLogs / Payments
- Settings（8 個 Tab：管理員 / 模式 / DB / Mail / SMS / 金流與發票 / 系統參數 / 誠信分數配分）
- Plans / Broadcasts / Announcements / SEO / Logs

每個頁面標 ✅ / ❌ / ⚠️ 部分實作。

### P11 模組特有
```bash
# 重複的 Card / Table / Form 樣式
grep -rn "ant-card\|ant-table" admin/src/pages/ | wc -l

# 前台 component 是否有「複製貼上」的兄弟元件（命名相似但實作分歧）
ls frontend/src/components/ | sort

# 規格未涵蓋但實作的頁面
ls frontend/src/views/app/ admin/src/pages/

# 未引用的 SCSS / CSS 檔
find frontend/src/ -name "*.css" -o -name "*.scss" | xargs grep -L "@import\|<style>" 2>/dev/null
```

## 重點關注
- 主色一致性（前次 UI-001 v1.3 才統一為 #F0294E）
- 後台 Checkbox/Switch 的色彩規範（v1.3 新增 §5.1.1）
- Mobile First：前台所有頁面 375px 是否正常
