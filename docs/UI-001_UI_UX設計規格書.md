# [UI-001] MiMeet UI/UX 設計規格書

**文檔版本：** v1.3  
**建立日期：** 2026年3月  
**最後更新：** 2026年4月  
**適用範圍：** 前台 Vue.js SPA + 後台 React Admin  
**審核狀態：** 已確認

---

## 版本歷史

| 版本 | 日期 | 變更說明 |
|------|------|----------|
| v1.0 | 2026年3月 | 初始版本 |
| v1.1 | 2026年4月 | Landing Page 配色調整 |
| v1.2 | 2026年4月 | 新增 §2.1.4 全域 CSS 變數規範；新增 §2.6 CSS 實作注意事項；修正按鈕在 scoped style 下配色失效的問題說明 |
| v1.3 | 2026年4月 | 修正後台主色為 #F0294E（與前台統一）；新增 §5.1.1 後台表單元素色彩規範（Checkbox 邊框加深至 #9CA3AF） |

---

## 1. 設計理念與品牌識別

### 1.1 設計哲學

MiMeet 定位為「台灣高端交友平台」，設計語言需傳達：

- **高端與信任感**：精緻、克制的視覺語言，避免俗豔
- **隱私與安全感**：暗色系輔助配色，讓用戶感到私密保護
- **現代與直覺**：Mobile-first，手勢友好，流程清晰
- **誠信透明**：誠信分數系統在視覺上清晰可讀，不隱藏

### 1.2 設計原則

| 原則 | 說明 |
|------|------|
| **Mobile First** | 所有設計以 375px 寬度手機為主，桌面為延伸 |
| **一致性** | 同一操作在不同頁面保持相同視覺與行為 |
| **空白優先** | 大量留白，不堆砌資訊，重要資訊優先 |
| **即時反饋** | 每個操作必須有視覺/觸覺反饋（loading、toast、動畫） |
| **漸進式揭露** | 依會員等級漸進式顯示功能，避免資訊爆炸 |

---

## 2. 設計系統（Design System）

### 2.1 色彩系統

#### 2.1.1 主色盤

```
Primary（主品牌色）：
  --color-primary-50:   #FFF5F7
  --color-primary-100:  #FFE4EA
  --color-primary-200:  #FFC2D0
  --color-primary-300:  #FF91A8
  --color-primary-400:  #FF5C7E
  --color-primary-500:  #F0294E   ← 主按鈕、連結、強調色
  --color-primary-600:  #D01A3C
  --color-primary-700:  #A80F2C
  --color-primary-800:  #87091F
  --color-primary-900:  #6B0618

Neutral（中性色）：
  --color-neutral-0:    #FFFFFF
  --color-neutral-50:   #F9FAFB
  --color-neutral-100:  #F3F4F6
  --color-neutral-200:  #E5E7EB
  --color-neutral-300:  #D1D5DB
  --color-neutral-400:  #9CA3AF
  --color-neutral-500:  #6B7280
  --color-neutral-600:  #4B5563
  --color-neutral-700:  #374151
  --color-neutral-800:  #1F2937
  --color-neutral-900:  #111827   ← 主文字色

Surface（背景層次）：
  --color-surface-bg:   #F9F9FB   ← App 背景
  --color-surface-card: #FFFFFF   ← 卡片背景
  --color-surface-dark: #1C1C24   ← 深色遮罩/底部 Sheet
```

#### 2.1.2 誠信分數專屬色

```
頂級（91-100）：
  --credit-top-text:    #92400E
  --credit-top-bg:      #FFFBEB
  --credit-top-border:  #FDE68A

優質（61-90）：
  --credit-good-text:   #065F46
  --credit-good-bg:     #ECFDF5
  --credit-good-border: #A7F3D0

普通（31-60）：
  --credit-normal-text: #1E40AF
  --credit-normal-bg:   #EFF6FF
  --credit-normal-border:#BFDBFE

受限（0-30）：
  --credit-low-text:    #991B1B
  --credit-low-bg:      #FEF2F2
  --credit-low-border:  #FECACA
```

#### 2.1.3 功能色

```
成功：  #10B981  (操作成功、已驗證)
警告：  #F59E0B  (注意事項、快到期)
錯誤：  #EF4444  (錯誤、扣分、停權)
資訊：  #3B82F6  (一般提示)
```

#### 2.1.4 全域 CSS 變數規範（v1.2 新增）

所有頁面元件統一使用以下 CSS 變數，集中定義於 `src/assets/variables.css`：

```css
/* src/assets/variables.css */
:root {
  --primary:        #F0294E;   /* 主色、按鈕背景、強調 */
  --primary-dark:   #D01A3C;   /* 按鈕 hover 狀態 */
  --primary-light:  #FFF5F7;   /* 淺色背景、badge 背景 */
  --primary-50:     #FFE4EA;   /* 邊框、淺色強調 */
  --gold:           #F59E0B;   /* 金色、警告 */
  --text-primary:   #111827;   /* 主要文字 */
  --text-secondary: #6B7280;   /* 次要文字 */
  --text-muted:     #9CA3AF;   /* 輔助說明文字 */
  --surface:        #F9F9FB;   /* 頁面背景 */
  --card:           #FFFFFF;   /* 卡片背景 */
  --border:         #E5E7EB;   /* 邊框、分隔線 */
  --hero-gradient-start: #F0294E;
  --hero-gradient-end:   #A80F2C;
}
```

在 `src/assets/main.css` 最頂部引入：

```css
/* src/assets/main.css */
@import "./variables.css";
@import "tailwindcss";
```

| 變數名稱 | 色碼 | 主要用途 |
|---|---|---|
| `--primary` | `#F0294E` | 按鈕背景、強調色、連結 |
| `--primary-dark` | `#D01A3C` | 按鈕 hover、active |
| `--primary-light` | `#FFF5F7` | 淺色 badge 背景、hero 漸層底 |
| `--primary-50` | `#FFE4EA` | 邊框強調、淺色填充 |
| `--text-primary` | `#111827` | 所有主要文字 |
| `--text-secondary` | `#6B7280` | 次要說明文字 |
| `--text-muted` | `#9CA3AF` | 時間戳記、輔助文字 |
| `--surface` | `#F9F9FB` | App 頁面背景 |
| `--border` | `#E5E7EB` | 卡片邊框、分隔線 |

---

### 2.2 字型系統

#### 字型選擇

```
標題字型（Display）：Noto Serif TC
  - 用途：頁面主標題、Landing Page 大字
  - 風格：高端感、台灣用戶熟悉

內文字型（Body）：Noto Sans TC
  - 用途：所有正文、UI 文字
  - 風格：清晰易讀，Mobile 友好

數字字型（Numeric）：Inter
  - 用途：誠信分數、金額、日期數字
  - 風格：清晰的 tabular-nums
```

#### 字型比例（Type Scale）

```css
/* 對應 Tailwind 自訂設定 */
--text-xs:    12px / lh 16px   /* 輔助說明、時間戳記 */
--text-sm:    14px / lh 20px   /* 次要文字、標籤 */
--text-base:  16px / lh 24px   /* 正文預設 */
--text-lg:    18px / lh 28px   /* 小標題 */
--text-xl:    20px / lh 28px   /* 卡片標題 */
--text-2xl:   24px / lh 32px   /* 頁面標題 */
--text-3xl:   30px / lh 38px   /* Hero 標題 */
--text-4xl:   36px / lh 44px   /* Landing Page 大標 */
```

#### 字重規則

```
400（Regular）：正文、描述
500（Medium）：強調文字、次要按鈕文字
600（SemiBold）：標題、主按鈕文字
700（Bold）：數字強調（分數、金額）
```

---

### 2.3 間距系統

採用 4px 基礎單位：

```
4px   (1)  - 極小間距（icon 與文字間）
8px   (2)  - 小間距（行內元素）
12px  (3)  - 元件內邊距（小型）
16px  (4)  - 標準內邊距（卡片、輸入框）
20px  (5)  - 元件間距（小）
24px  (6)  - 元件間距（中）
32px  (8)  - 區塊間距
40px  (10) - 頁面區塊間距
48px  (12) - 大型區塊間距
64px  (16) - 頁面頂部/底部間距
```

---

### 2.4 圓角（Border Radius）

```
--radius-sm:   6px    - 標籤、徽章
--radius-md:   10px   - 按鈕、輸入框
--radius-lg:   14px   - 卡片
--radius-xl:   20px   - 底部 Sheet、模態框
--radius-full: 9999px - 頭像、膠囊按鈕
```

---

### 2.5 陰影系統

```css
--shadow-sm:   0 1px 2px rgba(0,0,0,0.05);
--shadow-md:   0 4px 6px -1px rgba(0,0,0,0.08), 0 2px 4px -2px rgba(0,0,0,0.06);
--shadow-lg:   0 10px 15px -3px rgba(0,0,0,0.10), 0 4px 6px -4px rgba(0,0,0,0.07);
--shadow-card: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);

/* 誠信分數頂級特效 */
--shadow-gold: 0 0 0 2px #FDE68A, 0 4px 12px rgba(234,179,8,0.25);
```

---

### 2.6 CSS 實作注意事項（v1.2 新增）

#### ⚠️ `<style scoped>` 與 CSS 變數的限制

Vue 的 `<style scoped>` 會對 CSS 加上作用域限制，**在 `<style scoped>` 內定義的 `:root` 變數無法正確生效**，導致按鈕等元件背景色顯示為預設值（白色/淡粉紅），文字不可見。

**錯誤做法 ❌：**
```vue
<style scoped>
:root {
  --primary: #F0294E;  /* 在 scoped 內定義 :root，無效！ */
}
.btn { background: var(--primary); }  /* 變數找不到，顯示為透明 */
</style>
```

**正確做法 ✅：**

方式一：CSS 變數統一在 `src/assets/variables.css` 定義（全域），頁面 `<style>` 直接使用：
```vue
<style>  /* 注意：不加 scoped */
.btn { background: var(--primary); }
</style>
```

方式二：直接使用 hardcode hex（適用於元件層級的 `<style scoped>`）：
```vue
<style scoped>
.btn { background: #F0294E; color: white; }
</style>
```

#### 各類型元件的樣式建議

| 元件類型 | 建議做法 | 原因 |
|---|---|---|
| 頁面級元件（Views）| `<style>`（不加 scoped）| 頁面有大量 CSS 變數依賴 |
| 共用元件（Components）| `<style scoped>` + hardcode hex | 作用域隔離，避免污染 |
| Layout 元件 | `<style scoped>` + hardcode hex | 同上 |

---

## 3. 核心元件規格（Component Spec）

### 3.1 按鈕（Button）

#### 主要按鈕（Primary）
```
背景：#F0294E（--primary）
文字：白色（#FFFFFF）/ font-weight: 600 / text-sm
高度：48px（標準）/ 40px（緊湊）/ 56px（大型）
圓角：10px（--radius-md）
Padding：0 24px
Hover：背景加深至 #D01A3C（--primary-dark）
Active：scale(0.97) + 背景加深至 #A80F2C
Disabled：opacity: 0.4，cursor: not-allowed
Loading：按鈕內顯示旋轉圓圈，文字隱藏，寬度不變

全寬用途：登入/註冊/送出等主操作
固定寬度用途：確認對話框按鈕
```

#### 次要按鈕（Secondary）
```
背景：transparent
邊框：1.5px solid #E5E7EB（--border）
文字：#374151 / font-weight: 500
Hover：背景 #F9FAFB，邊框 #D1D5DB
尺寸：同主要按鈕
```

#### 危險按鈕（Danger）
```
背景：#FEF2F2
邊框：1px solid #FECACA
文字：#991B1B
用途：刪除、封鎖確認等不可逆操作
```

#### 文字按鈕（Ghost）
```
背景：transparent，無邊框
文字：#F0294E（--primary）
用途：「忘記密碼」、「查看更多」等次要操作
```

#### 圖示按鈕（Icon Button）
```
尺寸：40px × 40px（標準）/ 32px × 32px（小型）
圓角：9999px（圓形）或 10px（方形）
背景：#F3F4F6（neutral-100）
Hover：#E5E7EB（neutral-200）
Active：scale(0.93)
```

---

### 3.2 輸入框（Input）

#### 標準文字輸入框
```
高度：52px
圓角：10px（--radius-md）
邊框：1.5px solid #E5E7EB
背景：白色
Padding：16px
Font-size：16px（防止 iOS 自動縮放）
Label：懸浮式（Floating Label）/ 字體 12px 移至頂部
Focus：邊框色 #F0294E，外框陰影 0 0 0 3px rgba(240,41,78,0.12)
Error：邊框色 #EF4444，底部顯示紅色錯誤說明文字
Success：邊框色 #10B981，右側顯示打勾 icon
```

#### 密碼輸入框
```
同標準輸入框
右側加「顯示/隱藏密碼」眼睛 icon
```

#### 搜尋框
```
高度：44px
左側 Search icon（灰色）
圓角：9999px（膠囊形）
背景：#F3F4F6（neutral-100）
無邊框（扁平風格）
Clear button：有內容時右側顯示「×」
```

#### 多行文字框（Textarea）
```
最小高度：120px
Auto-grow：依內容自動延伸，最大 300px
右下角顯示字數計數（如 50/200）
圓角：10px（--radius-md）
```

---

### 3.3 卡片（Card）

#### 用戶卡片（UserCard）— 搜尋結果
```
尺寸：全寬 / 高度固定 88px
結構：
  左：頭像 56×56px（圓形），右上角顯示線上狀態圓點（綠色 8px）
  中：暱稱（font-weight:600, 16px）
      年齡 · 地區（14px, #6B7280）
      驗證徽章 row（Email/手機/進階）
  右：誠信分數徽章（見 3.5）
      收藏按鈕（心形 icon，已收藏為紅色）

> **v1.3 更新 — 個人資料頁操作按鈕區**：
> 按鈕並排佈局（flex row, gap 8px）：
> ① 💬 傳訊息（Primary）② 📅 邀請約會（Secondary，條件：自己 Lv3 + 對方 Lv1+）③ ❤️ 收藏
> 「邀請約會」點擊後開啟 Bottom Sheet（日期 + 時間 + 地點），送出 POST /api/v1/dates

背景：白色
圓角：14px（--radius-lg）
陰影：--shadow-card
Hover/Active：整體 scale(0.99)，陰影加深
```

#### 聊天列表卡片（ChatCard）
```
高度：72px
結構：
  左：頭像 48×48px + 線上狀態點
  中：暱稱（font-weight:600）
      最後訊息預覽（14px, #6B7280, 1行截斷）
  右：時間戳記（12px, #9CA3AF）
      未讀數徽章（紅底白字，最大 99+）
分隔：row 間 0.5px #F3F4F6 分隔線（左邊縮排 72px）
```

#### 動態卡片（PostCard）
```
結構（從上到下）：
  用戶資訊 row（頭像 40px + 暱稱 + 時間 / 右側「...」更多按鈕）
  圖片（若有）：最大 1:1 比例，圓角 8px，懶加載
  文字描述（14px, #374151）最多 3 行，「展開」超連結
  互動 row：❤️ 愛心數（文字按鈕）/ 💬 進入私訊按鈕
  底部分隔線
```

#### 約會卡片（DateCard）
```
背景：漸層（#F0294E → #A80F2C）
文字：白色
圓角：20px（--radius-xl）
結構：
  頂部：QR 碼圖示 + 狀態標籤
  中間：對方暱稱 + 約定時間 + 地點名稱
  底部：倒數計時（即將見面時顯示）/ 掃碼按鈕
```

> **QR 掃碼驗證頁（`/app/dates/scan`）— v1.4 GPS 授權提示規格**：
> 全螢幕深色背景，含相機掃碼框（4 角粉紅邊框）。
> 掃碼成功後流程（含 GPS 授權提示畫面）：
> 1. 掃碼成功 → 顯示 **GPS 授權說明畫面**（不直接觸發系統授權彈窗）
> 2. 說明畫面內容：
>    - 📍 圖示 + 標題「開啟定位可獲得更高分數」
>    - 得分對照卡（深色圓角卡片）：
>      - `+5 分`（綠色 Badge）— 允許 GPS 且在 500m 內
>      - `+2 分`（琥珀色 Badge）— 不提供 GPS 或距離超過
>    - 提示文字：「系統將在您按下按鈕後請求定位權限，您可以隨時拒絕。」
>    - 「📍 允許定位並驗證（推薦）」Primary 按鈕（#F0294E）
>    - 「跳過定位，直接驗證」Ghost 按鈕（透明邊框）
> 3. 用戶選「允許」→ 觸發 `navigator.geolocation`（iOS/Android 此時彈出系統授權）
> 4. 用戶選「跳過」→ 不觸發系統授權，直接送出驗證（latitude=null）
> 5. 驗證中 spinner：「正在取得 GPS 定位…」或「驗證中…」
> 6. 結果畫面：
>    - 僅一方掃碼：「⏳ 已掃碼，等待對方」+ GPS 狀態
>    - 雙方完成 GPS 通過：「✅ +5」+「📍 GPS 驗證通過（500m 內）」（綠色）
>    - 雙方完成 GPS 未通過：「✅ +2」+「📍 GPS 未通過」（橙色）

---

### 3.4 導覽列

#### 底部導覽（Bottom Navigation）— 前台主要導覽
```
高度：64px + 系統安全區域（iPhone Home Indicator）
背景：白色 / 頂部 0.5px #E5E7EB 分隔線
圖示尺寸：24×24px
Tab 項目（5 個）：
  1. 探索    - Search icon
  2. 訊息    - Chat icon + 未讀數徽章
  3. 約會    - Calendar icon + 待處理徽章
  4. 通知    - Bell icon + 未讀數徽章
  5. 我的    - User icon
Tab 選中狀態：圖示與文字變為 #F0294E（--primary）
Tab 未選中：圖示與文字為 #9CA3AF
未讀數徽章：紅底（#F0294E）白字，最大 99+，右上角顯示
```

---

### 3.5 誠信分數徽章（CreditScoreBadge）

```
形狀：膠囊（border-radius: 9999px）
Padding：2px 10px
字型：Inter，12px，font-weight: 600
邊框：1px solid

頂級（91-100）：背景 #FFFBEB，文字 #92400E，邊框 #FDE68A
優質（61-90）： 背景 #ECFDF5，文字 #065F46，邊框 #A7F3D0
普通（31-60）： 背景 #EFF6FF，文字 #1E40AF，邊框 #BFDBFE
受限（0-30）：  背景 #FEF2F2，文字 #991B1B，邊框 #FECACA
```

---

## 4. 頁面規格（Page Specifications）

### 4.1 公開頁面

#### 4.1.1 首頁 / Landing Page（`/`）

**目的：** 吸引新訪客，引導註冊

**版面結構（手機）：**
```
[狀態列]
[Topbar] Logo（左）+ 登入/立即加入按鈕（右），滾動後加投影
[Hero 區塊]
  背景：白色 + 右下角/左上角 radial-gradient 粉紅暈染
  主標題：「找到你值得信賴的另一半」（Noto Serif TC, 32-52px clamp）
  副標題：「誠信分數系統，讓每一次相遇都真實可靠」（16px, #6B7280）
  CTA 按鈕：「立即免費加入」（#F0294E 背景，白色文字）+ 「已有帳號？登入」（ghost）
  Trust Row：5,000+ 認證會員 / 98% 真實照片 / 3層 身份驗證
  Hero 插圖：手機 Mockup + 浮動徽章動畫

[三大特色卡片]
  ① QR碼約會驗證
  ② 誠信分數系統
  ③ 多重身份驗證

[三步驟流程]
  01 建立帳號 → 02 探索配對 → 03 QR碼見面

[最終 CTA 區塊]
  「準備好開始了嗎？」+ 免費加入按鈕

[Footer]
```

**版面結構（桌面 ≥768px）：**
```
Hero 左右分欄：左側文字，右側手機 Mockup
三大特色卡片：三欄排列
```

#### 4.1.2 登入頁（`/login`）

**版面結構：**
```
[Logo + 標題「歡迎回來」+ 副標題「登入你的 MiMeet 帳號」]
[Email 輸入框]
[密碼輸入框 + 顯示/隱藏切換]
[忘記密碼連結]（右對齊，14px）
[登入按鈕]（全寬，#F0294E 背景，白色文字）
[分隔線：「或」]
[前往註冊連結]
[頁尾：隱私權政策 · 使用者條款]
```

**狀態處理：**
```
空白提交：各欄位顯示「此欄位為必填」
Email 格式錯誤：inline 紅字說明
密碼錯誤：顯示「Email 或密碼不正確」toast（不指定哪個錯，避免資安問題）
帳號停權：跳至 /suspended 並顯示原因
連續錯誤 5 次（同 IP）：顯示「請稍後再試，您已嘗試過多次」
```

#### 4.1.3 註冊頁（`/register`）

**多步驟設計（3 Steps）：**

```
Step 1：基本資料
  進度指示：「1 / 3」+ 步驟點
  欄位：
    ● 性別（必選）：「甜爹（Male）」/「甜心（Female）」兩個大按鈕卡片
          甜爹配圖：男性符號 icon；甜心配圖：女性符號 icon
          選中：#F0294E 邊框 + 淺粉紅背景
    ● 暱稱（2-20字）
    ● 生日（年/月/日 三欄，需 ≥ 18 歲驗證）
  下一步按鈕

Step 2：帳號資料
  欄位：
    ● Email
    ● 密碼（8字以上）
    ● 確認密碼
    ● 手機（09xxxxxxxx 格式）
  靜態頁面勾選：
    ☑ 我已閱讀並同意《隱私權政策》及《使用者條款》（連結可點）
    ☑ 我確認年滿18歲
  注意：兩個勾選框必須均勾選才能繼續
  下一步按鈕

Step 3：Email 驗證
  說明文字：「驗證碼已發至 {email}」
  6 位數驗證碼輸入框（自動分欄 OTP 樣式）
  倒數計時：「60秒後可重新發送」→ 可點「重新發送」
  完成後跳至 /app/explore
```

**步驟過渡動畫：** slide from right，duration: 300ms

#### 4.1.4 Email 驗證頁（`/verify-email`）

```
狀態A：等待驗證中
  信封圖示（動畫：上下浮動）
  說明：「請至您的信箱點擊驗證連結」
  「重新發送」按鈕（有 60 秒冷卻計時）
  「修改 Email」連結

狀態B：驗證成功
  打勾圓圈動畫（SVG stroke animation，綠色）
  文字：「Email 已驗證！」
  自動 3 秒後跳至 /app/explore 或手動點「開始使用」按鈕
```

---

### 4.2 需登入頁面（/app/*）

**共用版面框架（AppLayout）：**
```
[頂部 TopBar] - 依頁面動態顯示，高度 56px，白色背景
[頁面內容區] - height: calc(100dvh - 56px - 64px)，可捲動，背景 #F9F9FB
[底部 BottomNav] - 固定在底部，高度 64px
```

#### 4.2.1 探索頁（`/app/explore`）

**版面：**
```
TopBar：
  左：「探索」標題
  右：篩選 icon（有篩選條件時顯示小紅點）

[搜尋框] - 輸入後即時搜尋暱稱，膠囊形，灰色背景

[快速篩選標籤列]（水平捲動）
  「全部」「台北市」「新北市」「台中市」「高雄市」「其他縣市」
  選中：#F0294E 填充，白色文字；未選：#F3F4F6 背景

[用戶列表]
  每行一個 UserCard（高度 88px）
  無限捲動（Intersection Observer）
  每次載入 20 筆，底部顯示 spinner

[空狀態]
  插圖 + 「沒有符合條件的用戶，請調整篩選條件」
```

**篩選 Bottom Sheet：**
```
觸發：點擊漏斗 icon
內容：
  年齡區間（Range Slider：18-50）
  性別（Radio：全部/男/女）
  誠信分數區間（四個 Chip 選擇）
  地區（Checkbox 多選，縣市列表）
  學歷（Chip 多選：高中/高職、專科、大學、碩士、博士）
  最後上線（Radio：今天/3天內/7天內/全部）
  [重設篩選]  [套用篩選（n項）]
```

---

## 5. 後台設計規格

後台採用 React 18 + Ant Design 5.x，以效率優先，不追求精緻感。

### 5.1 後台色彩

```
主色：#F0294E（與前台統一品牌色）
背景：#001529（Ant Design Sider 預設深藍）
全域邊框：#D1D5DB（neutral-300，確保表單元素在白色背景上清晰可辨）
```

#### 5.1.1 後台表單元素色彩規範

| 元素 | 狀態 | 色彩 |
|------|------|------|
| Checkbox 邊框 | 未勾選 | `#9CA3AF`（neutral-400，確保在白色表格背景上清晰可見） |
| Checkbox 填色 | 已勾選 | `#F0294E`（主色） |
| Checkbox 圓角 | — | 4px |
| Input 邊框 | 預設 | `#D1D5DB`（neutral-300） |
| Input 邊框 | Focus | `#F0294E`（主色） |
| Switch | 開啟 | `#F0294E`（主色） |
| Switch | 關閉 | `#D1D5DB`（neutral-300） |

### 5.2 後台版面框架

```
[左側側邊欄] 固定 220px 寬，深色背景
  Logo + 平台名稱
  Navigation Menu（Ant Design Menu 元件）

[頂部 Header] 56px 高，白色背景
  左：Hamburger（摺疊側邊欄）
  右：管理員暱稱 + 角色 Badge + 登出按鈕

[主內容區] 剩餘空間，padding 24px，overflow-y: auto
  Breadcrumb 導覽
  頁面標題
  頁面內容
```

### 5.3 後台關鍵頁面

#### 統計儀表板（`/admin/dashboard`）

```
[頂部 KPI 卡片列]（4 欄）
  ① 今日新註冊（數字 + vs 昨日箭頭）
  ② 今日活躍會員
  ③ 付費會員總數
  ④ 待處理 Ticket 數（警示橙色若 > 10）

[圖表區]
  左（2/3 寬）：折線圖 — 30天 新增/活躍/付費 趨勢（ECharts）
  右（1/3 寬）：圓餅圖 — 男/女比例

[匯出按鈕] 右上角，CSV/Excel 格式選擇

[伺服器狀態] 底部，CPU/Memory 進度條（從 API 拉取）
```

#### 會員列表（`/admin/members`）

```
[篩選行]
  搜尋框（Email/暱稱）
  狀態篩選（下拉：全部/正常/停權/已刪除）
  性別篩選
  驗證等級篩選
  [搜尋] [清除]

[Table 表格]（Ant Design Table，支援排序）
  欄位：ID / 頭像+暱稱 / 性別 / 會員等級 / 誠信分數（排序）/ 狀態 / 最後上線 / 操作
  操作：[查看] [調整分數] [停權/解停] [刪除]（依角色權限顯示）

  誠信分數欄：同前台色系的小徽章
  狀態欄：Badge（正常=綠 / 停權=紅 / 已刪除=灰）
```

#### 會員詳情（`/admin/members/:id`）

```
[Tab 切換]：基本資料 / 誠信分數紀錄 / 訂閱記錄 / 聊天紀錄

基本資料 Tab：
  左：頭像 + 照片列 + 驗證狀態
  右：所有個人資料欄位（只讀）

誠信分數紀錄 Tab：
  Table：時間 / 變化 / 類型 / 原因 / 操作者

[快捷操作浮動按鈕]（右下角）
  調整誠信分數 / 停權 / 備註 / 刪除
```

#### 系統設定（`/admin/settings`）— Sprint 10 新增

後台設定頁以 Ant Design Tabs 分隔 7 個功能區，僅 `super_admin` 可見 Tab 2-5（系統控制類）。

```
Tab 1：管理員帳號（settings.roles 權限）
  [+ 新增管理員] 按鈕（右上角）
  Table：姓名 / Email / 角色 Badge / 狀態 / 最後登入 / 操作（編輯）
  新增/編輯 Drawer：姓名、Email、密碼（新增必填）、角色 Select、啟用 Switch

Tab 2：系統運作模式（super_admin 專屬）
  目前模式 Card：大型 Badge 顯示「🟡 測試模式」或「🟢 正式模式」
  各服務狀態列：Email ● SMS ● 綠界 ●（紅=停用，綠=啟用）
  [切換模式] 按鈕 → Modal 確認（需輸入管理員密碼）
  維護模式 Switch（開啟後前台顯示維護中公告）
  版本號顯示（唯讀）

Tab 3：資料庫設定（super_admin 專屬，唯讀）
  唯讀表單：主機 / Port / 資料庫名 / 使用者名稱（欄位 disabled，不可修改）
  [🔌 測試連線] 按鈕 → 顯示連線狀態和回應時間
  資料庫匯出區塊 → 下載完整 SQL 備份
  ℹ️ 提示：「如需修改 DB 連線，請透過 SSH 直接編輯 .env」
  （移除「儲存設定」按鈕——透過 web UI 改 DB 連線風險過高且 docker-compose 環境變數會覆蓋 .env）

Tab 4：Email 設定（super_admin 專屬）
  服務商快捷選擇：[SendGrid] [其他 SMTP] → 自動填入常用值
  表單：主機 / Port / 加密 / 使用者名稱 / 密碼（****）/ 寄件人地址 / 寄件人名稱
  [📧 發送測試信] 區塊：輸入收件地址 + 送出按鈕 + 結果顯示
  [儲存設定] 按鈕

Tab 5：SMS 設定（super_admin 專屬）
  服務商 Select：三竹簡訊 / Twilio / 每日簡訊 / 停用
  依選擇顯示對應欄位（服務商帳號、密碼 ****）
  [📱 發送測試簡訊] 區塊：輸入手機號 + 送出按鈕 + 結果顯示
  [儲存設定] 按鈕

Tab 6：訂閱方案（settings.pricing，原有功能）

Tab 7：系統參數（super_admin）
  區塊 1：資料保留政策（Data Retention）— 設定資料物理銷毀期限（30-730 天）
  區塊 2：點數消費設定（F40）— 7 個點數相關費率設定
  ※ 「誠信分數規則」已移除：原本此處有 5 個假欄位（QR GPS 得分、扣分等），按下儲存
     不會真正生效。誠信分數唯一調整入口改為「⭐ 誠信分數配分」Tab（新增於 Sprint 11+）。
```

**Tab 7 Sprint 11 新增區塊：會員等級功能矩陣**
```
[會員等級功能設定] 標題卡片
  Ant Design Table，欄：功能項目 | Lv0 | Lv1 | Lv1.5 | Lv2 | Lv3
  Switch 元件（非 super_admin 則全部 disabled）
  daily_message_limit / post_moment 欄 → InputNumber（0=無限，灰色=停用）
  [儲存設定] 按鈕（一次批次 PATCH，成功後顯示 success message）
```

#### 驗證審核（`/admin/verifications`）— Sprint 11 新增

```
[Tab 切換]：待審核 / 已處理
[Table]：申請時間 / 用戶暱稱 / 性別 / 目前等級 / 操作[審核]
[審核 Drawer]：
  左：用戶頭像 + 暱稱 + 目前等級 + 誠信分數
  右：驗證照片（大圖可縮放）/ 隨機碼說明
  按鈕：[核准] [拒絕]
  拒絕時顯示 Input（原因必填）
```

#### 廣播訊息（`/admin/broadcasts`）— Sprint 11 新增

```
[Table]：標題 / 發送模式 / 目標人數 / 已送達 / 狀態 Badge / 建立時間
[+ 新增廣播] → 建立表單：
  標題（必填）、內容（Textarea 必填）
  發送模式 Radio：系統通知 / 私訊
  篩選條件（Collapse）：性別 / 會員等級 / 誠信分數區間
  預估目標人數（即時計算）
  [儲存草稿] [立即發送]
  發送確認 Modal
```

---

## 6. 響應式設計規格

### 6.1 斷點定義

```
xs:  < 375px    - 舊款手機（如 iPhone SE）
sm:  375px+     - 標準手機（設計基準）
md:  768px+     - 平板 / 橫向手機
lg:  1024px+    - 桌面（前台非主要使用場景）
xl:  1280px+    - 後台標準解析度
2xl: 1536px+    - 後台寬螢幕
```

### 6.2 前台響應式規則

```
手機（sm）：單欄，底部導覽，全寬卡片
平板（md）：雙欄探索頁，側邊導覽列出現，卡片 max-width: 680px
桌面（lg）：三欄，聊天頁左右分欄（列表 + 對話），限制內容寬度 max-width: 480px（居中）
```

### 6.3 安全區域處理

```css
/* 底部導覽支援 iPhone Home Indicator */
padding-bottom: env(safe-area-inset-bottom);
```

---

## 7. 互動動效規格

### 7.1 頁面切換

```
前進（/app/profiles/:id 等子頁面）：
  新頁面從右側 slideIn（translateX: 100% → 0），duration: 280ms，ease-out
返回：
  當前頁面 slideOut 至右（translateX: 0 → 100%），duration: 250ms，ease-in

底部 Tab 切換：
  淡入淡出（opacity + scale: 0.97 → 1），duration: 200ms
```

### 7.2 核心微互動

```
按鈕點擊：scale(0.96) → 1，duration: 100ms
卡片 Tap：scale(0.98) → 1 + 陰影加深，duration: 150ms
收藏愛心：點擊後 scale(1.3) → 1，填色動畫，duration: 300ms
誠信分數進度條：頁面載入後以 SVG stroke-dasharray 動畫填充，duration: 1200ms，ease-out
聊天訊息發送：訊息 bubble 從右側 slideIn，duration: 200ms
新訊息提示：頭像輕微 bounce 動畫
```

### 7.3 手勢支援

```
聊天列表：左滑顯示操作（封鎖 / 刪除）
圖片輪播：左右 swipe，慣性滾動
列表：下拉重新整理（Pull to Refresh），顯示旋轉 icon
```

---

## 8. 通知模板規格

### 8.1 Email 通知模板

所有 Email 統一格式：

```
[Header] Logo（左對齊）+ 平台名稱
[主內容區] 白色背景，max-width: 600px，居中
[Footer] 「您收到此信是因為您在 MiMeet 有帳號」+ 取消訂閱連結

字型：系統字型（-apple-system, Arial, sans-serif）
主色按鈕：背景 #F0294E，白色文字，圓角 8px
```

**各類型模板：**

| 類型 | 主旨 | 內容摘要 |
|------|------|----------|
| Email 驗證 | 【MiMeet】請驗證您的電子信箱 | 6位驗證碼 + 說明「10分鐘內有效」 |
| 忘記密碼 | 【MiMeet】密碼重設請求 | 重設連結 + 說明「1小時內有效，若非本人操作請忽略」 |
| 帳號停權 | 【MiMeet】您的帳號已暫停使用 | 停權原因 + 申訴管道連結 |
| Ticket 已處理 | 【MiMeet】您的回報 #{ticket_no} 已有回覆 | 回覆摘要 + 查看詳情按鈕 |
| 訂閱成功 | 【MiMeet】訂閱成功，歡迎升級！ | 方案 / 到期日 / 功能說明 |
| 訂閱即將到期 | 【MiMeet】您的會員即將在 3 天後到期 | 到期日 + 續訂按鈕 |
| 新訊息通知 | 【MiMeet】您有一則新訊息 | 「{暱稱} 傳送了一則訊息給您」+ 查看按鈕（不顯示訊息內容，保護隱私）|
| 進階驗證通過 | 【MiMeet】身份驗證已完成！ | 恭喜語 + 說明解鎖功能 |
| 進階驗證未通過 | 【MiMeet】身份驗證未通過 | 未通過原因 + 重試說明 |

### 8.2 SMS 簡訊模板

```
電話驗證碼：
「【MiMeet】您的驗證碼為 {CODE}，5 分鐘內有效，請勿洩漏。」

密碼重設：
「【MiMeet】您的密碼重設碼為 {CODE}，1小時內有效。若非本人操作，請忽略此訊息。」
```

### 8.3 站內推送通知（In-App Notification）

```
格式：icon + 標題（18px bold）+ 內容（14px）+ 時間戳

通知類型與文案：
  新訊息：    「💬 {暱稱} 傳訊息給您」
  新收藏：    「❤️ {暱稱} 收藏了您」
  訪客紀錄：  「👀 {暱稱} 查看了您的個人資料」
  Ticket 回覆：「📋 您的回報 #{ticket_no} 已有回覆」
  約會邀請：  「📅 {暱稱} 向您發起見面邀請」
  QR碼驗證成功：「✅ 見面驗證完成！誠信分數 +5」
  訂閱到期提醒：「⚠️ 您的會員將於 3 天後到期」

點擊導向：依類型跳至對應頁面
```

---

## 9. 空狀態與錯誤設計

### 9.1 各頁面空狀態

| 頁面 | 圖示風格 | 文案 | 操作 |
|------|---------|------|------|
| 探索（無結果） | 放大鏡 + 問號 | 「沒有符合條件的用戶，試試調整篩選條件？」 | [調整篩選] |
| 訊息列表（無對話） | 聊天泡泡 | 「還沒有任何對話，去探索心儀的對象吧！」 | [去探索] |
| 動態（無動態） | 照片 icon | 「還沒有動態，來發布第一則吧！」 | [+ 發布] |
| 收藏（無收藏） | 愛心輪廓 | 「還沒有收藏任何人」 | [去探索] |
| 訪客（無記錄） | 眼睛 icon | 「還沒有訪客記錄，完善個人資料增加曝光度！」 | [編輯資料] |
| 約會（無約會） | 日曆 | 「還沒有任何約會邀請，傳訊息給心儀對象發起邀請吧！」 | [去訊息] |

**空狀態圖示設計原則：**
- 使用 SVG 線條插圖（非圖片）
- 配色：#E5E7EB（線條）+ #F3F4F6（填充）
- 尺寸：120×120px
- 每個頁面使用獨特的圖示，避免重複

### 9.2 全域錯誤頁

```
404 Not Found：
  圖示：迷路的人物
  標題：「找不到這個頁面」
  [回到首頁] 按鈕

500 Server Error：
  圖示：螺絲扳手
  標題：「系統暫時出了一點問題」
  說明：「我們正在處理中，請稍後重試。」
  [重新整理] 按鈕

網路斷線：
  頂部出現橙色 Banner：「網路連線中斷，請檢查您的網路設定」
  自動偵測重新連線後 Banner 消失
```

---

## 10. 無障礙設計規範（Accessibility）

| 項目 | 規格 |
|------|------|
| 色彩對比 | 文字對背景對比度 ≥ 4.5:1（WCAG AA） |
| 觸控目標 | 所有可點擊元素最小 44×44px |
| 圖片 Alt | 所有 `<img>` 必須有 alt 屬性 |
| 表單 Label | 每個 input 必須有對應 label 或 aria-label |
| Keyboard Navigation | Tab 順序邏輯，Focus 有明顯視覺環 |
| 螢幕閱讀器 | 動態內容更新使用 aria-live region |

---

## 11. 靜態頁面內容規格

以下靜態頁面需由業主提供法律文本，開發時先放 Placeholder：

| 頁面 | 路徑 | 備注 |
|------|------|------|
| 隱私權政策 | `/privacy` | 需法律顧問撰寫，涵蓋個資法要求 |
| 使用者條款 | `/terms` | 需說明平台規則、年齡限制（18歲以上）、禁止行為 |
| 防詐指南 | `/anti-fraud` | 提醒用戶常見詐騙手法，建議由平台運營人員撰寫 |

**靜態頁面版面規格：**
```
TopBar：← 返回按鈕 + 頁面標題
內容區：padding 24px，text-sm，#111827
標題層級：H2（font-weight:600）/ H3（font-weight:500）/ 正文
最後更新日期：文末顯示，12px, #9CA3AF
```

---

## 12. 文件關聯索引

| 文件 | 說明 |
|------|------|
| PRD-001_產品需求規格書 | 功能需求、用戶故事、驗收標準 |
| DEV-003_前端架構與開發規範 | Vue.js 元件實作規範、Tailwind 設定 |
| DEV-010_Phase1實作規劃 | Sprint 任務分解、週次規劃 |

---

*本文件定義 MiMeet 平台的 UI/UX 設計規格，開發人員應以此為準實作各頁面與元件。若有未明確規範之細節，請參考 PRD-001 的驗收標準，並與業主確認後再行實作。*