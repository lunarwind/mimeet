# MiMeet Phase 1 (MVP) 實作狀態盤點報告

**盤點日期：** 2026-04-16
**基準文件：** MiMeet_功能清單_MVP_vs_Phase2.md、DEV-010_Phase1實作規劃.md、SDD-001_系統設計規格書.md
**掃描範圍：** backend/、frontend/、admin/

---

## 一、MVP 功能完成度總覽

| 分類 | MVP 項目數 | ✅ 完成 | ⚠️ 部分 | ❌ 缺失 | 完成率 |
|------|-----------|--------|---------|--------|--------|
| 用戶系統 (F01-F11) | 10 | 6 | 3 | 1 | 65% |
| 誠信分數 (F12-F15) | 2 (MVP) | 2 | 0 | 0 | 100% |
| 即時聊天 (F16-F17, +F18-F21 超前) | 2 (MVP) + 4 (超前) | 6 | 0 | 0 | 100% |
| QR 約會驗證 (F23-F25) | 3 | 3 | 0 | 0 | 100% |
| 搜尋配對 (F26) | 1 | 1 | 0 | 0 | 100% |
| 商業/金流 (F36-F39) | 4 | 4 | 0 | 0 | 100% |
| 安全機制 (F43, F45) | 2 | 2 | 0 | 0 | 100% |
| 後台管理 (A03-A21) | 11 | 11 | 0 | 0 | 100% |
| **合計** | **35** | **31** | **3** | **1** | **90%** |

---

## 二、逐項檢核明細

### 用戶系統

| ID | 功能 | 後端 | 前端 | 狀態 | 說明 |
|----|------|------|------|------|------|
| F01 | 註冊流程 | ✅ AuthController::register | ✅ RegisterView.vue (3 步驟) | ✅ | 完整：性別→帳號→Email 驗證 |
| F02 | Email 驗證 | ✅ AuthController::verifyEmail | ✅ RegisterView Step 3 + EmailVerifyView | ✅ | OTP 6 碼，Redis 快取 |
| F03 | SMS 手機驗證 | ⚠️ STUB (verifyPhoneSend/Confirm) | ✅ VerifyView.vue 有 UI | ⚠️ | **後端只 log OTP，未實際驗證** |
| F04 | 忘記密碼 | ⚠️ STUB (forgotPassword/resetPassword) | ✅ ForgotPasswordView + ResetPasswordView | ⚠️ | **後端無 token 產生/驗證邏輯** |
| F05 | 女性進階驗證 | ✅ VerificationPhotoController | ✅ VerifyView.vue | ✅ | 隨機碼 + 自拍上傳 + 後台審核 |
| F06 | 男性信用卡驗證 | ✅ PaymentService + ECPay | ✅ ShopView.vue | ✅ | 綠界 NT$100 授權 |
| F07 | 個人資料管理 | ✅ UserController::update | ✅ AccountView.vue | ✅ | 頭像、照片、個人資訊 |
| F08 | 隱私設定 | ✅ PrivacyController | ✅ AccountView.vue 隱私 toggles | ✅ | show_online/allow_visits 等 |
| F09 | 封鎖用戶 | ❌ STUB (block/unblock/blockedUsers) | ✅ BlockedView.vue + ProfileView | ⚠️ | **前端完成，後端 STUB 無 DB 邏輯** |
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
| F16 | 即時聊天 | ✅ ChatService + ChatMessageSent event | ✅ ChatView.vue + useChat composable | ✅ | Socket.IO / Reverb 雙通道；支援文字/圖片兩種 message_type |
| F17 | 未讀 Badge | ✅ 後端追蹤 is_read | ✅ BottomNav.vue badge | ✅ | Pinia chat store 管理 |
| F18 | 已讀狀態 | ✅ markAsRead 廣播 MessageRead | ✅ MessageBubble 付費會員顯示「已讀」 | 🚀 Phase 2 超前 | 2026-04-19 補完 |
| F19 | 訊息回收 | ✅ DELETE /chats/{id}/messages/{messageId} + MessageRecalled event | ✅ MessageBubble 長按選單「收回」 | 🚀 Phase 2 超前 | sender + 5 分鐘內 + 未讀 + Lv3 付費 |
| F20 | 聊天搜尋 | ✅ GET /chats/{id}/messages/search | ✅ ChatView 搜尋面板（debounce 300ms + 跳轉高亮） | 🚀 Phase 2 超前 | |
| F21 | 搜尋聊天對象 | — | ✅ MessagesView 搜尋框 filter | 🚀 Phase 2 超前 | 純前端 computed filter |
| F22 | 免打擾模式 | ✅ Conversation.isMutedBy + User.isInDndPeriod + NotificationService 跳過 FCM | ✅ MessagesView 靜音按鈕 + AccountView DND 設定 | 🚀 Phase 2 超前 | 2026-04-20：對話靜音 (Part A) + 全域時段 DND (Part B)；Badge 仍計入，僅 FCM 推播被跳過 |

### QR 約會驗證

| ID | 功能 | 後端 | 前端 | 狀態 | 說明 |
|----|------|------|------|------|------|
| F23 | 約會邀請發起 | ✅ DateService + DateInvitationController | ✅ DatesView.vue + ProfileView BottomSheet | ✅ | 時間/地點/QR 產生 |
| F24 | QR 掃碼驗證 | ✅ DateService::verify (時間窗 ±30 分) | ✅ QRScanView.vue (jsQR + 相機) | ✅ | GPS 提示流程 + 手動輸入 fallback |
| F25 | 分數獎勵 | ✅ GPS +5 / 無 GPS +2 (SystemSetting) | ✅ 驗證結果顯示 | ✅ | 後台可調分數值 |

### 搜尋配對

| ID | 功能 | 後端 | 前端 | 狀態 | 說明 |
|----|------|------|------|------|------|
| F26 | 基礎搜尋篩選 | ✅ UserController::search (年齡/地區/性別) | ✅ ExploreView.vue + useExplore | ✅ | 無限滾動 + 快速篩選 tags |
| F27 | 進階綜合篩選 | ✅ UserController::search 新增 9 個篩選參數 + 資料完整度排序 | ✅ FilterBottomSheet「進階篩選」可收合區塊 + AccountView 9 個新欄位 | 🚀 Phase 2 超前 | 2026-04-20：身高/學歷/風格/約會預算/關係期望/抽菸/飲酒/自備車；未填欄位不排除 |

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
| F45 | 系統問題回報 | ✅ ReportController (type 區分) | ✅ ReportsView.vue + ReportsHistoryView | ✅ | 歷史紀錄 + 管理員回覆 |

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

## 四、需修正的 Top 3 缺失項目

### ❌ 1. 用戶封鎖功能 (F09) — 前端完成，後端 STUB

**嚴重度：高** — 這是安全機制的基本需求

- `UserController::block()` / `unblock()` / `blockedUsers()` 只回傳 hardcoded success，無 DB 操作
- `user_blocks` 表已存在但未使用
- 前端 `BlockedView.vue` 和 `ProfileView.vue` 已串接 API
- **需要實作**：INSERT/DELETE `user_blocks` + 搜尋結果排除 + 聊天攔截

### ⚠️ 2. SMS 手機驗證 (F03) — 前端完成，後端 STUB

**嚴重度：高** — 影響 Lv1 升級流程

- `verifyPhoneSend()` 只 log OTP 碼，未存入 Redis/DB
- `verifyPhoneConfirm()` 未驗證 OTP，直接標記成功
- SmsService 框架已完成（Twilio/Mitake/Every8d driver），只差接入
- **需要實作**：OTP 存入 Cache + 驗證比對 + phone_verified 更新

### ⚠️ 3. 忘記密碼 (F04) — 前端完成，後端 STUB

**嚴重度：中** — 用戶忘記密碼無法自助恢復

- `forgotPassword()` 未產生 reset token，也未發送 email
- `resetPassword()` 未驗證 token，直接回成功
- password_reset_tokens 表已存在
- **需要實作**：token 產生 + email 發送 + token 驗證 + 密碼更新 + token 銷毀

---

## 五、建議修正區

### 偏離規範的實作

| 項目 | 問題 | 影響 | 建議 |
|------|------|------|------|
| UserController follow/unfollow | TODO STUB，回 success 但不寫 DB | 收藏功能無效 | 實作 user_follows INSERT/DELETE |
| UserController visitors | TODO STUB，回空陣列 | 「誰來看我」功能無效 | 實作 user_profile_visits 記錄 |
| Broadcasting listeners | Event 已定義但未註冊 listener | WebSocket 訊息通知可能不即時 | 確認 Reverb/Pusher 已啟動且 channel auth 正確 |
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

*本報告由程式碼掃描自動產出，建議定期更新。*
