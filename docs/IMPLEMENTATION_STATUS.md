# MiMeet Phase 1 (MVP) 實作狀態盤點報告

**盤點日期：** 2026-04-20
**基準文件：** MiMeet_功能清單_MVP_vs_Phase2.md、DEV-010_Phase1實作規劃.md、SDD-001_系統設計規格書.md
**掃描範圍：** backend/、frontend/、admin/

---

## 一、MVP 功能完成度總覽

| 分類 | MVP 項目數 | ✅ 完成 | ⚠️ 部分 | ❌ 缺失 | 完成率 |
|------|-----------|--------|---------|--------|--------|
| 用戶系統 (F01-F11) | 10 | 10 | 0 | 0 | 100% |
| 誠信分數 (F12-F15) | 2 (MVP) | 2 | 0 | 0 | 100% |
| 即時聊天 (F16-F17, +F18-F21 超前) | 2 (MVP) + 4 (超前) | 6 | 0 | 0 | 100% |
| QR 約會驗證 (F23-F25) | 3 | 3 | 0 | 0 | 100% |
| 搜尋配對 (F26) | 1 | 1 | 0 | 0 | 100% |
| 商業/金流 (F36-F39) | 4 | 4 | 0 | 0 | 100% |
| 安全機制 (F43, F45) | 2 | 2 | 0 | 0 | 100% |
| 後台管理 (A03-A21) | 11 | 11 | 0 | 0 | 100% |
| **合計** | **35** | **35** | **0** | **0** | **100%** |

---

## 二、逐項檢核明細

### 用戶系統

| ID | 功能 | 後端 | 前端 | 狀態 | 說明 |
|----|------|------|------|------|------|
| F01 | 註冊流程 | ✅ AuthController::register | ✅ RegisterView.vue (3 步驟) | ✅ | 完整：性別→帳號→Email 驗證 |
| F02 | Email 驗證 | ✅ AuthController::verifyEmail | ✅ RegisterView Step 3 + EmailVerifyView | ✅ | OTP 6 碼，Redis 快取 |
| F03 | SMS 手機驗證 | ✅ verifyPhoneSend/Confirm（Cache OTP 5min + 冷卻 60s + 失敗 5 次鎖）+ SmsService(testing log / mitake / twilio) | ✅ VerifyView.vue | ✅ | 2026-04-20 驗證：OTP 不外洩、confirm 有 Cache::get 比對 |
| F04 | 忘記密碼 | ✅ forgotPassword（Str::random(64) + Hash::make → password_reset_tokens）+ resetPassword | ✅ ForgotPasswordView + ResetPasswordView | ✅ | 有 rate limit、email enumeration 防護 |
| F05 | 女性進階驗證 | ✅ VerificationPhotoController | ✅ VerifyView.vue | ✅ | 隨機碼 + 自拍上傳 + 後台審核 |
| F06 | 男性信用卡驗證 | ✅ PaymentService + ECPay | ✅ ShopView.vue | ✅ | 綠界 NT$100 授權 |
| F07 | 個人資料管理 | ✅ UserController::update | ✅ AccountView.vue | ✅ | 頭像、照片、個人資訊 |
| F08 | 隱私設定 | ✅ PrivacyController | ✅ AccountView.vue 隱私 toggles | ✅ | show_online/allow_visits 等 |
| F09 | 封鎖用戶 | ✅ UserBlock 模型：block/unblock/blockedUsers，搜尋+個資頁雙向排除被封鎖對象 | ✅ BlockedView.vue + ProfileView | ✅ | 2026-04-20 驗證：有自我防護 + 404 防護 + 雙向排除 |
| F10 | 帳號刪除申請 | ✅ DeleteAccountController + GdprService | ✅ DeleteAccountView.vue | ✅ | 7 天冷靜期 + GDPR 合規 |
| F11 | 靜態法規頁面 | N/A | ✅ PrivacyView + TermsView + AntiFraudView | ✅ | 隱私/條款為 placeholder 待法律文本 |

### 誠信分數系統

| ID | 功能 | 後端 | 前端 | 狀態 | 說明 |
|----|------|------|------|------|------|
| F12 | 誠信分數核心引擎 | ✅ CreditScoreService | ✅ CreditScoreBadge 元件 | ✅ | 初始 60 分，0-100，歷史紀錄 |
| F13 | 分數權限控管 | ✅ MemberLevelPermission 矩陣 | ✅ useLevelPermissions composable | ✅ | 等級功能矩陣由後台控制 |

### 即時聊天

| ID | 功能 | 後端 | 前端 | 狀態 | 說明 |
|----|------|------|------|------|------|
| F16 | 即時聊天 | ✅ ChatService + ChatMessageSent event + Reverb 廣播 | ✅ ChatView.vue + useChat (laravel-echo) | ✅ | Reverb 單通道（Pusher 協定）；文字/圖片 message_type；即時 MessageSent/Read/Recalled |
| F17 | 未讀 Badge | ✅ 後端追蹤 is_read | ✅ BottomNav.vue badge | ✅ | Pinia chat store 管理 |
| F18 | 已讀狀態 | ✅ markAsRead 廣播 MessageRead | ✅ MessageBubble 付費會員顯示「已讀」 | 🚀 Phase 2 超前 | 2026-04-19 補完 |
| F19 | 訊息回收 | ✅ DELETE /chats/{id}/messages/{messageId} + MessageRecalled event | ✅ MessageBubble 長按選單「收回」 | 🚀 Phase 2 超前 | sender + 5 分鐘內 + 未讀 + Lv3 付費 |
| F20 | 聊天搜尋 | ✅ GET /chats/{id}/messages/search | ✅ ChatView 搜尋面板（debounce 300ms + 跳轉高亮） | 🚀 Phase 2 超前 | |
| F21 | 搜尋聊天對象 | — | ✅ MessagesView 搜尋框 filter | 🚀 Phase 2 超前 | 純前端 computed filter |
| F22 | 免打擾模式 | ✅ Conversation.isMutedBy + User.isInDndPeriod + NotificationService 跳過 FCM | ✅ MessagesView 靜音按鈕 + AccountView DND 設定 | 🚀 Phase 2 超前 | 2026-04-20：對話靜音 (Part A) + 全域時段 DND (Part B)；Badge 仍計入，僅 FCM 推播被跳過 |

### QR 約會驗證

| ID | 功能 | 後端 | 前端 | 狀態 | 說明 |
|----|------|------|------|------|------|
| F23 | 約會邀請發起 | ✅ DateService + DateController（list 含 qr_token + expires_at） | ✅ DatesView.vue + ProfileView BottomSheet + QRCodeDisplay 真實 QR（qrcode 套件，errorCorrectionLevel=H，120×120 retina）+ 文字代碼 + 複製按鈕 | ✅ Step 4 完成 | PR-QR Step 4：QRCodeDisplay 從 mock SVG 改 real QR；含手動輸入 fallback（顯示 64 字元 hex + 複製，含 clipboard API + execCommand fallback）；v1 完整顯示優先易用性，安全強化見 PRD §4.2.3 Phase 2 follow-up |
| F24 | QR 掃碼驗證 | ✅ DateService::verify (±30min 雙向時間窗 + SCAN_WINDOW_NOT_OPEN/TOKEN_EXPIRED) | ✅ QRScanView.vue (jsQR + 相機) | ✅ Step 3 完成 | PR-QR Step 3：後端補時間窗下限檢查、前端 isInScanWindow 對齊 ±30min |
| F25 | 分數獎勵 | ✅ GPS +5 / 無 GPS +2 (SystemSetting) | ✅ 驗證結果顯示 | ✅ | 後台可調分數值 |

### 搜尋配對

| ID | 功能 | 後端 | 前端 | 狀態 | 說明 |
|----|------|------|------|------|------|
| F26 | 基礎搜尋篩選 | ✅ UserController::search (年齡/地區/性別) | ✅ ExploreView.vue + useExplore | ✅ | 無限滾動 + 快速篩選 tags |
| F27 | 進階綜合篩選 | ✅ UserController::search 新增 9 個篩選參數 + 資料完整度排序 | ✅ FilterBottomSheet「進階篩選」可收合區塊 + AccountView 9 個新欄位 | 🚀 Phase 2 超前 | 2026-04-20：身高/學歷/風格/約會預算/關係期望/抽菸/飲酒/自備車；未填欄位不排除 |
| A03 | 會員列表（含 F27 篩選）| ✅ AdminController::members 加 dating_budget + style 精確篩選 | ✅ MembersPage 2 個快速篩選下拉 | ✅ 2026-04-20 補完 | 後台篩選走精確匹配（非寬鬆），與前台不同 |
| A04 | 會員詳情（含 F27 欄位）| ✅ AdminController::memberDetail 回傳 9 個新欄位 | ✅ MemberDetailPage 3 組新 Descriptions + 編輯 Drawer 新增 9 欄 | ✅ 2026-04-20 補完 | labelMaps.ts 中英對照 |
| F40 | 點數系統基礎建設 | ✅ 3 張表 + users.points_balance/stealth_until + PointService + PointController + 綠界 point-mock | ✅ ShopView 2 Tab + usePoints + 點數包卡片 + 交易紀錄 Modal + AccountView 會員狀態卡片 + MemberDetailPage 會員狀態 | 🚀 Phase 2 超前 | 2026-04-20：建地基（購買/餘額/紀錄） |
| F40-a | 隱身模式（點數消費）| ✅ StealthController status/activate/deactivate；Lv3 免費、非 Lv3 扣點；搜尋 + 訪客過濾 | ✅ useStealth composable + AccountView 控制區塊 + ExploreView 提示條 + 確認/餘額不足 Modal | 🚀 Phase 2 超前 | 2026-04-20：疊加延長不重置；privacy_settings 與 stealth_until 獨立判斷 |
| F42 | VIP 隱身模式 | ✅ Lv3 `membership_level >= 3` 免費路徑 | ✅ 同 F40-a UI，顯示「VIP 免費」 | 🚀 Phase 2 超前 | 共用 StealthController；不改訂閱權限、不改 privacy 功能 |
| F40-c | 超級讚 | ✅ SuperLikeController POST /users/{id}/super-like；24h 冷卻、所有等級扣點（預設 3） | ✅ ProfileView 金色按鈕 + 確認/餘額不足 Modal + NotificationsView 特殊金色樣式 | 🚀 Phase 2 超前 | 2026-04-20：notifications ENUM 加 super_like |
| F40-b | 逆區間訊息 | ✅ ChatService::sendMessage 加 $bypassScoreCheck；ChatController 接 use_points + 扣 5 點 | ✅ ChatView 分數不足 → 逆區間確認 Modal → 重送帶 use_points；餘額不足導向儲值 | 🚀 Phase 2 超前 | 只在「現有 CreditScoreRestrictionException 被擋」時啟動，不改其他邏輯 |
| F41 | 用戶廣播 | ✅ user_broadcasts 表 + UserBroadcastController (preview/send/history) + ProcessUserBroadcast Job | ✅ ExploreView 📢 入口 + BroadcastModal 3 步驟 | 🚀 Phase 2 超前 | 以 sender 本人名義發私訊；每日 1 次 / 最多 50 人 / 2 點/人（system_settings 可調）|
| A01 | 儀表板 API | ✅ StatsController GET /admin/stats/summary（members + revenue + points + pending）| ✅ DashboardPage 改用 stats API + 第二排 KPI + 點數消費分布 Pie | ✅ 2026-04-20 補完 | 原聚合邏輯保留當 fallback |
| - | 後台點數管理 | ✅ AdminPointController：packages/updatePackage/adjustPoints/transactions | ✅ PointTransactionsPage 篩選+分頁 + MemberDetailPage 贈送/扣除 Modal + sidebar 💎 點數交易 | ✅ 2026-04-20 | 操作寫入 admin_operation_logs |
| - | 💰 方案設定獨立頁 | ✅ AdminController::memberDetail 擴充 points_detail（balance/purchase_count/consumption_by_feature/recent_transactions/purchase_orders）| ✅ PlanSettingsPage 2 Tab（訂閱方案 + 點數方案）+ SystemParamsTab 💎 點數消費設定（7 項 InputNumber，debounce 300ms）+ MemberDetailPage 💎 點數資訊 Card（Progress bars + 最近 10 筆交易 + 最新 5 筆訂單 + 查看全部連結）| ✅ 2026-04-20 | 搬移 PricingTab + 新增點數方案 CRUD + sidebar `💰 方案設定`（super_admin）|

### 商業/金流

| ID | 功能 | 後端 | 前端 | 狀態 | 說明 |
|----|------|------|------|------|------|
| F36 | 訂閱方案 | ✅ SubscriptionController + ECPayService | ✅ ShopView.vue | ✅ | 週/月/季/年，綠界串接 |
| F37 | 新手體驗價 | ✅ SubscriptionController::trial | ✅ TrialView.vue | ✅ | 每帳號限一次 |
| F38 | 我的會員頁 | ✅ SubscriptionController::mySubscription | ✅ SubscriptionView.vue | ✅ | 等級/到期日/自動續訂 |
| F39 | 取消訂閱申請 | ✅ SubscriptionController::cancelRequest | ✅ SubscriptionView.vue 取消 Modal | ✅ | 5 秒倒數確認 |

### 安全機制

| ID | 功能 | 後端 | 前端 | 狀態 | 說明 |
|----|------|------|------|------|------|
| F43 | 一般用戶檢舉 | ✅ ReportService (雙方扣分) | ✅ ReportsView.vue | ✅ | 圖片上傳 + 案號 |
| F45 | 系統問題回報 | ✅ ReportController (type 區分;2026-05-07 PR-1 補上 `system_issue` validation 與 `[META]` prefix metadata) | ✅ ReportsView.vue + ReportsHistoryView + VerifyView 問題回報 modal (PR-1) | ✅ | 歷史紀錄 + 管理員回覆;PR-1 加 SMS 驗證 ticket 子流程,24h cache rate limit |
| F-blacklist | 註冊禁止名單(PR-2 2026-05-07) | ✅ AdminBlacklistController + BlacklistService + RegistrationBlacklist model + register gate + admin delete checkbox 整合(2026-05-08 PR-3:補上 verify 流程 blacklist gate,no-op verify 也走 check) | ✅ admin BlacklistsPage + Form/Deactivate Modals + MembersPage 刪除 dialog 擴充 | ✅ | 方案 C race protection (active_value_hash UNIQUE);RBAC blacklist.view/create/deactivate;14ak~14an guards |
| F-phone-change | 手機驗證強化 + 換號流程(PR-3 2026-05-08) | ✅ PhoneService(unique+blacklist+race+atomic) + PhoneConflictException + PhoneChangeResult + PhoneChangeHistory + PhoneChangeController(3-step OTP) + AuthController::verifyPhoneSend/Confirm 移除 user-controlled phone 參數 | ✅ PhoneChangeView + utils/mask.ts + VerifyView/RegisterView 改用 authStore.user.phone + 不再傳 phone 給 verify endpoints | ✅ | 14ao~14ar guards;phone_change_histories 表;authStore.user.phone 已是 backend masked 字串(不前端再 mask) |

### 後台管理

| ID | 功能 | 後端 | 前端 (Admin) | 狀態 | 說明 |
|----|------|------|-------------|------|------|
| A03 | 會員列表搜尋 | ✅ AdminController::members | ✅ MembersPage | ✅ | 篩選/搜尋/分頁 |
| A04 | 查看用戶個人頁 | ✅ AdminController::memberDetail | ✅ MemberDetailPage | ✅ | 完整個人資料 + Tab 分頁 |
| A05 | 調整誠信分數 | ✅ AdminController::memberAction | ✅ MemberDetailPage 調分 Modal | ✅ | 含原因記錄 |
| A06 | 手動調整等級 | ✅ AdminController::updatePermissions | ✅ MemberDetailPage 權限 Modal | ✅ | Lv0-Lv3 + Lv1.5 |
| A07 | 停權/刪除帳號 | ✅ AdminController::memberAction | ✅ MembersPage + DetailPage | ✅ | 停權=admin，刪除=super_admin |
| A08 | 要求重新驗證 | ✅ AdminController::memberAction (require_reverify) | ✅ MemberDetailPage | ✅ | |
| A09 | 女性驗證審核 | ✅ VerificationController | ✅ VerificationsPage | ✅ | 核准/拒絕 + 分數獎勵 |
| A12 | 系統公告 | ✅ (AnnouncementsPage API) | ✅ AnnouncementsPage | ✅ | CRUD 完整 |
| A13 | 問題回報管理 | ✅ TicketController | ✅ TicketsPage | ✅ | 狀態更新 + 回覆 + 追蹤留言；F43~F46 亦共用此 Ticket 系統（DB 表 `reports`，`type` 區分）|
| A15 | 支付紀錄 | ✅ AdminController::payments | ✅ PaymentsPage | ✅ | 篩選 + CSV 匯出 |
| A19 | 系統參數設定 | ✅ SystemControlController | ✅ SystemSettingsPage (7 Tabs) | ✅ | 分數規則/等級矩陣/方案/mail/sms/db |
| A20 | 角色權限管理 | ✅ AdminCrudController + RBAC | ✅ AdminUsersPage | ✅ | super_admin/admin/cs |
| A21 | 操作日誌 | ✅ AdminLogController | ✅ ActivityLogsPage | ✅ | 含 IP + 操作類型篩選 |

---

## 三、Phase 2 超前實作項目 (🚀)

以下功能原定 Phase 2，但已在目前 codebase 提前完成：

| ID | 功能 | 原定階段 | 實作狀態 | 說明 |
|----|------|---------|---------|------|
| F14 | 自動停權 (分數 ≤ 0) | Phase 2 | 🚀 後端 CreditScoreService 有 auto_suspend | 觸發後自動停權 |
| F15 | 申訴流程 | Phase 2 | 🚀 AppealController + AppealView.vue | 前後端完整 |
| F46 | 回報歷史 | Phase 2 | 🚀 ReportsHistoryView.vue | 前端已實作 |
| A10 | 聊天記錄查詢 | Phase 2 | 🚀 ChatLogController + ChatLogsPage (3 Tabs) | 搜尋/對話/匯出 |
| A11 | 聊天記錄匯出 | Phase 2 | 🚀 CSV export API | 含 BOM UTF-8 |
| A14 | 廣播訊息 | Phase 2 | 🚀 BroadcastController + BroadcastsPage | 建立/發送/篩選目標 |
| A16 | 訂閱折扣管理 | Phase 2 | 🚀 PricingTab + promo_* 欄位 | 百分比/固定/期限 |
| A17 | SEO Meta 管理 | Phase 2 | ✅ 2026-04-19 補完 | `seo_metas` 表 + SeoController::metaIndex/metaUpdate + SeoPage Meta tab + SeoMetaSeeder 3 筆（/、/login、/register）|
| A18 | 廣告跳轉追蹤 | Phase 2 | ⏳ 保留 Phase 2 | SeoController 有骨架註解；SeoPage links tab 已隱藏（註解保留）；無 Migration/Route |

---

## 四、曾標記為 Top 3 缺失，2026-04-20 實作驗證後確認皆已完成

### ✅ 1. 用戶封鎖功能 (F09)

- `UserController::block()` / `unblock()` / `blockedUsers()` 使用 `UserBlock` 模型，自我防護 + 404 防護
- 搜尋 + 個人資料頁雙向排除被封鎖對象（line 120-122, 254-256, 314）
- 前端 `BlockedView.vue` 和 `ProfileView.vue` 已串接並正常運作

### ✅ 2. SMS 手機驗證 (F03)

- `verifyPhoneSend()` 存 Cache 5 分鐘 + 60 秒冷卻 + 呼叫 SmsService
- `verifyPhoneConfirm()` Cache::get 比對 + 失敗計數（5 次鎖）+ 更新 phone_verified
- SmsService 支援 testing（log）/ mitake / twilio / disabled 四種 driver
- OTP 不在 response 中外洩

### ✅ 3. 忘記密碼 (F04)

- `forgotPassword()` 生成 `Str::random(64)` → `Hash::make` 存 `password_reset_tokens` 表
- Email enumeration 防護（找不到也回同樣訊息）+ 60 秒冷卻
- `resetPassword()` token 驗證 + 密碼更新 + token 銷毀

---

## 五、建議修正區

### 偏離規範的實作

| 項目 | 問題 | 影響 | 建議 |
|------|------|------|------|
| ~~UserController follow/unfollow~~ | ✅ 2026-04-20 已驗證：UserFollow::firstOrCreate + 完整 following 列表 | — | — |
| ~~UserController visitors~~ | ✅ 2026-04-20 已驗證：UserProfileVisit join users 分頁（90 天內） | — | — |
| ~~Broadcasting listeners~~ | ✅ 2026-04-20：Reverb container（port 8080）+ Nginx /app proxy + Echo + useChat 重寫，chat.{id} / user.{id} 即時連通 | — | — |
| User Model 缺 relationships | 無 Eloquent 關聯定義 | Controller 重複查詢 | 新增 conversations/follows/blocks 關聯 |

---

## 六、附錄：檔案統計

| 目錄 | 檔案數 | 說明 |
|------|--------|------|
| backend/app/Http/Controllers/Api/V1/ | 12 | 前台 API controllers |
| backend/app/Http/Controllers/Api/V1/Admin/ | 8 | 後台 API controllers |
| backend/app/Http/Controllers/Api/Admin/ | 1 | ChatLogController |
| backend/app/Services/ | 13 | 業務邏輯服務 |
| backend/app/Models/ | 20+ | Eloquent models |
| frontend/src/views/ | 32 | Vue 3 頁面元件 |
| frontend/src/api/ | 8 | API 呼叫層 |
| frontend/src/composables/ | 9 | 可重用邏輯 |
| admin/src/pages/ | 20 | React 18 頁面 |

---

## 七、Pre-merge Guard Follow-ups（2026-05-05 新增）

### 14ag — Transformer hardcoded null（warning 級）

- **狀態**：已實作為 warning，不阻擋 merge。
- **目前命中**：`frontend/src/api/dates.ts:36 — creditScoreChange: null`（合法 fallback：list endpoint 不返回該欄位）。
- **追蹤項**：日後若新增類似 transformer，需在 PR 描述中說明命中原因；reviewer 在 PR 審查時人工判斷是漏映射還是刻意 fallback。
- **升級為 fail 級的觸發條件**：當 codebase 內合法 fallback 全部加上 `// reason` 註解後，可改成「裸 null（不帶註解）」才報 fail。本次暫不啟用。

### 14ah — IMPLEMENTATION_STATUS 一致性守護（待實作）

- **狀態**：**TODO**，本份 IMPLEMENTATION_STATUS.md 結構需先標準化才能機械比對。
- **目標語意**：當 PRD / API-001 / API-002 中標 `[實作]` / `Phase 1` 的功能與本檔的條目不一致時，pre-merge 提示。
- **前置條件**：
  1. 本檔每個功能行需有穩定的 ID（如 F01 / A03）— 已具備。
  2. 規格書內 `[實作]` 標記需採固定格式（待規範化）。
  3. 設計 awk/grep 切片從規格書抽出 `[實作]` 項目，與本檔比對 ID 集合。
- **後續責任**：規格書標記格式統一後，由 pre-merge-check 維護者新增 14ah 條目。

---

*本報告由程式碼掃描自動產出，建議定期更新。*
