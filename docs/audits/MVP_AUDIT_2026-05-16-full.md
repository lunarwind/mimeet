# MiMeet MVP / Phase 2 實作狀態審計（完整 Re-walk）

**審計日期**：2026-05-16
**基準 commit**：`f34ca42` (Merge branch 'develop')
**規格基準**：docs/MiMeet_功能清單_MVP_vs_Phase2.md（v1.3，2026-04-09；A02 已於 2026-05-16 commit `2f6ddce` 移出規劃，Phase 2 分母 31 → 30）
**前次審計**：
- docs/audits/MVP_AUDIT_2026-05-15.md（基準 commit `cceacf5`，完整 walk）
- docs/audits/MVP_AUDIT_2026-05-16.md（基準 commit `e8baa79`，delta-only）

**形式**：本份為**完整 re-walk**，不依賴 delta；每項皆現場驗證程式碼證據（routes / controllers / models / migrations / views / admin pages）。唯讀掃描，未變更 code / spec / DB / 設定。

---

## Summary

| 分類 | 總數 | ✅ | 🟡 | ❌ | 🔍 |
|---|---|---|---|---|---|
| MVP | 36 | 36 | 0 | 0 | 0 |
| Phase 2 | 30 | 22 | 0 | 8 | 0 |
| **合計** | **66** | **58** | **0** | **8** | **0** |

**MVP 完成率**：36 / 36 = **100%**
**Phase 2 完成率**：22 / 30 = **73.3%**

**vs 2026-05-16 delta audit**：完全一致（無新增 commit、無狀態異動，僅形式不同——本份為完整 walk）。

**vs 2026-05-15 full audit**：
- A02 從規劃移除（commit `2f6ddce`），Phase 2 分母 31 → 30
- A18 從 🟡 → ❌（commit `51d67bb` 註解前端 /go/:slug 路由）
- F40 內部擴充 F40-d 詳細資料通行證（commit `bea54ce`，不獨立計分）
- F07 dead code 清理 + ProfileView 詳細資料 section + UI 微調（commit `de1b32d` / `cafd099` / `8e08c93`）

---

## MVP 功能逐項

### F01 — 註冊流程
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §用戶系統 / API-001 §2.1.1
**證據**：
- `backend/routes/api.php:56` — `POST /auth/register` + `throttle:register`
- `backend/app/Http/Controllers/Api/V1/AuthController.php::register`
- `frontend/src/views/public/RegisterView.vue` — 3-step wizard（性別/暱稱/生日 → 帳號/條款 → Email 驗證）
- `backend/routes/api.php:57` — `POST /auth/check-nickname` 預檢端點

**vs 前次**：未變

---

### F02 — Email 驗證
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §用戶系統 / API-001 §2.2.1
**證據**：
- `backend/routes/api.php:60` — `POST /auth/verify-email`
- `backend/routes/api.php:61` — `POST /auth/resend-verification` + `throttle:otp`
- `frontend/src/views/public/EmailVerifyView.vue` + RegisterView Step 3
- `backend/app/Http/Controllers/Api/V1/AuthController.php::verifyEmail / resendVerification`

**vs 前次**：未變

---

### F03 — SMS 手機驗證
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §用戶系統 / API-001 §2.2.2
**證據**：
- `backend/routes/api.php:78-79` — `/verify-phone/send` + `/verify-phone/confirm`（需 auth + throttle:otp）
- `backend/app/Services/SmsService.php` + `backend/app/Services/Sms/`（多 driver）
- `frontend/src/views/app/settings/VerifyView.vue`
- `backend/app/Http/Controllers/Api/V1/AuthController.php::verifyPhoneSend / verifyPhoneConfirm`

**vs 前次**：未變

---

### F04 — 忘記密碼
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §用戶系統 / API-001 §2.3
**證據**：
- `backend/routes/api.php:62-63` — `/forgot-password`, `/reset-password`（+ `throttle:otp`）
- `frontend/src/views/public/ForgotPasswordView.vue` + `ResetPasswordView.vue`
- `backend/app/Http/Controllers/Api/V1/AuthController.php::forgotPassword / resetPassword`

**vs 前次**：未變

---

### F05 — 女性進階驗證
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §用戶系統 / API-001 §16.3-16.5 / PRD §3.1
**證據**：
- `backend/app/Http/Controllers/Api/V1/VerificationPhotoController.php`
- `backend/routes/api.php:269-271` — request / upload / status 三端點
- `frontend/src/views/app/settings/VerifyView.vue`
- `admin/src/pages/verifications/VerificationsPage.tsx`（對應 A09）
- 2026-05-09 PR-Verify-Lock：`pending_review` 鎖定狀態防止重複申請

**vs 前次**：未變

---

### F06 — 男性信用卡驗證
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §用戶系統 / API-001 §2.2.4 / PRD §3.2
**證據**：
- `backend/app/Http/Controllers/Api/V1/CreditCardVerificationController.php`
- `backend/app/Services/CreditCardVerificationService.php`
- `backend/routes/api.php:276-282` — initiate / status / callback / returnUrl
- `backend/database/migrations/2026_04_26_200000_create_credit_card_verifications_table.php`
- `frontend/src/views/app/ShopView.vue` 內 ECPay 跳轉

**vs 前次**：未變

---

### F07 — 個人資料管理
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §用戶系統 / API-001 §3.1 / §3.3 / §16.1
**證據**：
- `backend/app/Http/Controllers/Api/V1/UserController.php::update / show / me / settings`
- `backend/app/Http/Controllers/Api/V1/UserController.php:611` — `buildProfilePhotos` helper（avatar_slots 衍生 photos response）
- `backend/app/Http/Controllers/Api/V1/UserController.php:352` — `'photos' => $this->buildProfilePhotos($user)`
- 槽位端點 `routes/api.php:101-104` — GET / POST / PATCH active / DELETE avatars
- `frontend/src/views/app/settings/AccountView.vue` — 完整編輯介面
- `frontend/src/views/app/ProfileView.vue` — 個人主頁含詳細資料 section + 肖像 carousel + UnlockDetailsModal

**vs 前次**：✅ 未升降；內部品質提升。`de1b32d` 移除 30 行 orphan photos handlers；`cafd099` 補上詳細資料 section；`8e08c93` 肖像 `object-contain + blurred bg`；`bea54ce` 詳細資料區 F40-d gating。

---

### F09 — 封鎖用戶
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §用戶系統 / API-001 §3.4
**證據**：
- `backend/app/Models/UserBlock.php` + `user_blocks` 表
- `backend/routes/api.php:111-112` — block / unblock，`:117` 列表
- `frontend/src/views/app/settings/BlockedView.vue`

**vs 前次**：未變

---

### F11 — 靜態法規頁面
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §用戶系統 / API-001 §11
**證據**：
- `frontend/src/views/public/PrivacyView.vue` / `TermsView.vue` / `AntiFraudView.vue` / `HelpView.vue`
- LandingView footer 路由配置正確

**vs 前次**：未變（內容仍為 placeholder，待法務最終文本）

---

### F12 — 誠信分數核心引擎
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §誠信分數 / DEV-008
**證據**：
- `backend/app/Services/CreditScoreService.php:15` — `adjust(User, delta, type, reason, ?operatorId)`
- `backend/app/Observers/CreditScoreObserver.php:13` — 監聽分數變化，同步觸發 F14 auto-suspend
- `backend/app/Models/CreditScoreHistory.php`
- `frontend/src/components/common/CreditScoreBadge.vue`（前端僅顯示等級分類）

**vs 前次**：未變

---

### F13 — 分數權限控管
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §誠信分數
**證據**：
- `backend/app/Models/MemberLevelPermission.php` + `member_level_permissions` 表
- `backend/app/Http/Controllers/Api/Admin/MemberLevelPermissionController.php::matrix / updateMatrix`
- `frontend/src/composables/useLevelPermissions.ts`
- `admin/src/pages/settings/SystemSettingsPage.tsx` 等級權限矩陣 Tab

**vs 前次**：未變

---

### F16 — 即時聊天（WebSocket）
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §即時聊天 / API-001 §4
**證據**：
- `backend/app/Services/ChatService.php` + Laravel Reverb 廣播
- `backend/routes/api.php:137-148` — chats CRUD + messages + mute
- `frontend/src/views/app/ChatView.vue` + `composables/useChat.ts`
- 對應廣播事件：`MessageSent` / `MessageRead` / `MessageRecalled`

**vs 前次**：未變

---

### F17 — 未讀訊息 Badge
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §即時聊天
**證據**：
- 後端 `messages.is_read` 欄位
- `frontend/src/stores/chat.ts` — `unreadCounts` Map + `totalUnread` / `unreadBadge` computed
- `frontend/src/components/layout/BottomNav.vue` — 顯示徽章

**vs 前次**：未變

---

### F23 — 約會邀請發起
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §QR 約會驗證 / API-001 §5.1
**證據**：
- `backend/app/Http/Controllers/Api/V1/DateController.php` + `DateInvitationController.php`（legacy）
- `backend/app/Services/DateService.php`
- `backend/routes/api.php:158-171` — `/date-invitations` + `/dates` 兩組（後者為主）
- `frontend/src/views/app/DatesView.vue` + `frontend/src/components/date/QRCodeDisplay.vue`

**vs 前次**：未變

---

### F24 — QR 掃碼驗證
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §QR 約會驗證 / API-001 §5.2.1
**證據**：
- `backend/app/Services/DateService.php::verify`
- `backend/routes/api.php:162, 171` — `/date-invitations/verify` 與 `/dates/verify` 雙端點
- `frontend/src/views/app/DatesView.vue`（含 QR scan view 模組）
- 64 字元 hex token + ±30 分鐘時間窗 + GPS 可選

**vs 前次**：未變

---

### F25 — 驗證結果分數獎勵
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §QR 約會驗證
**證據**：
- `backend/app/Services/DateService.php` — GPS 通過 +5、無 GPS +2
- 數值從 `system_settings` 讀取（後台可調），credit type 為 `qr_gps_pass` / `qr_no_gps_pass`

**vs 前次**：未變

---

### F26 — 基礎搜尋篩選
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §搜尋配對 / API-001 §3.2.1
**證據**：
- `backend/app/Http/Controllers/Api/V1/UserController.php::search`
- `backend/routes/api.php:105` — `GET /users/search`
- `frontend/src/views/app/ExploreView.vue` + `composables/useExplore.ts`
- 支援年齡 / 性別 / 地區 / 30 天未上線自動隱藏

**vs 前次**：未變

---

### F36 — 訂閱方案
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §商業 / API-001 §7.1
**證據**：
- `backend/app/Http/Controllers/Api/V1/SubscriptionController.php::plans / createOrder / mySubscription`
- `backend/routes/api.php:121, 125-127`
- `backend/app/Services/ECPayService.php` + `UnifiedPaymentService.php`
- `frontend/src/views/app/ShopView.vue` 訂閱 Tab

**vs 前次**：未變

---

### F37 — 新手體驗價
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §商業 / API-001 §10.5 / PRD §6.2
**證據**：
- `backend/routes/api.php:132-133` — `/subscription/trial`, `/subscription/trial/purchase`
- `backend/app/Services/PaymentService.php:200` — `'auto_renew' => !$plan->is_trial`（trial 強制 false）
- `backend/app/Http/Controllers/Api/V1/SubscriptionController.php:157-163` — trial guard 回 422 `TRIAL_NOT_RENEWABLE`
- `backend/tests/Feature/Subscription/TrialAutoRenewGuardTest.php` — 4 feature tests
- `frontend/src/views/app/TrialView.vue`

**vs 前次**：未變

---

### F38 — 我的會員頁
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §商業 / API-001 §7.1.3 / §10.9
**證據**：
- `backend/app/Http/Controllers/Api/V1/SubscriptionController.php::mySubscription / update`
- `backend/app/Services/PaymentService.php:345` — payload 含 `'is_trial' => (bool) $sub->plan->is_trial`
- `frontend/src/views/app/settings/SubscriptionView.vue` — `v-if="!currentSubscription.isTrial"` 條件渲染 toggle
- `frontend/src/composables/usePayment.ts:74` — `isTrial` field mapping
- `frontend/src/composables/usePayment.ts::toggleAutoRenew` 對齊 `PATCH /subscriptions/me`

**vs 前次**：未變

---

### F39 — 取消訂閱申請
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §商業 / API-001 §10.3
**證據**：
- `backend/routes/api.php:128` — `POST /subscriptions/cancel-request`
- `backend/app/Http/Controllers/Api/V1/SubscriptionController.php::cancelRequest`
- `frontend/src/views/app/settings/SubscriptionView.vue` 取消申請表單

**vs 前次**：未變

---

### F43 — 一般用戶檢舉
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §安全 / API-001 §8.1
**證據**：
- `backend/app/Services/ReportService.php`
- `backend/app/Http/Controllers/Api/V1/ReportController.php::store`
- `backend/routes/api.php:233` — `POST /reports` (throttle:reports)
- `frontend/src/views/app/ReportsView.vue`
- 提交即時雙方 -10 分（reporter 結案後補回）

**vs 前次**：未變

---

### F45 — 系統問題回報
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §安全 / API-001 §8.1
**證據**：
- `backend/app/Http/Controllers/Api/V1/ReportController.php::store`（type 區分）
- `backend/app/Models/Report.php` + `ReportImage.php`
- `frontend/src/views/app/ReportsView.vue`
- 與 F46 共用 reports 表，type=`system_issue`

**vs 前次**：未變

---

### A03 — 會員列表與搜尋
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §後台管理 / API-002 §3 / API-001 §9.5.3
**證據**：
- `backend/routes/api.php:301` — `GET /admin/members` + `members.view` 權限
- `backend/app/Http/Controllers/Api/V1/AdminController.php::members`
- `admin/src/pages/members/MembersPage.tsx`
- 支援 uid / nickname / email / gender / level / recent_days 篩選

**vs 前次**：未變

---

### A04 — 查看用戶個人頁
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §後台管理
**證據**：
- `backend/routes/api.php:302` — `GET /admin/members/{id}`
- `AdminController::memberDetail`
- `admin/src/pages/members/MemberDetailPage.tsx`

**vs 前次**：未變

---

### A05 — 調整誠信分數
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §後台管理
**證據**：
- `AdminController::memberAction`（action=adjust_score）+ `memberCreditLogs`
- `backend/routes/api.php:303, 305` — credit-logs / actions
- `admin/src/pages/members/MemberDetailPage.tsx` 調分 Modal

**vs 前次**：未變

---

### A06 — 手動調整會員等級
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §後台管理
**證據**：
- `AdminController::updatePermissions / memberAction`（action=change_level，含 Lv1.5 支援）
- `backend/routes/api.php:306` — permissions PATCH
- `admin/src/pages/members/MemberDetailPage.tsx` 權限 Modal

**vs 前次**：未變

---

### A07 — 停權 / 刪除帳號
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §後台管理
**證據**：
- `AdminController::memberAction`（action=suspend/unsuspend）+ `deleteMember`
- `backend/routes/api.php:305, 308` — actions / delete（後者 `members.delete` 權限）

**vs 前次**：未變

---

### A08 — 要求重新驗證
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §後台管理
**證據**：
- `AdminController::memberAction`（action=require_reverify）
- 在 `MemberDetailPage.tsx` 操作選單中

**vs 前次**：未變

---

### A09 — 女性進階驗證審核
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §後台管理
**證據**：
- `backend/app/Http/Controllers/Api/V1/Admin/VerificationController.php`
- `backend/routes/api.php:337-339` — index / pending / review
- `admin/src/pages/verifications/VerificationsPage.tsx`

**vs 前次**：未變

---

### A12 — 系統公告管理
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §後台管理 / API-001 §9.1
**證據**：
- `backend/app/Http/Controllers/Admin/AnnouncementController.php`
- `backend/routes/api.php:346-351` — CRUD（`announcements.manage` 權限）
- `backend/routes/api.php:292` — 公開 `/announcements/active`
- `admin/src/pages/announcements/AnnouncementsPage.tsx`

**vs 前次**：未變

---

### A13 — 問題回報管理
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §後台管理 / API-001 §9.5.4
**證據**：
- `backend/app/Http/Controllers/Api/V1/TicketController.php` + `AdminController::tickets / getTicketDetail / updateTicket`
- `backend/routes/api.php:318-322` — list / detail / update / status / reply
- `admin/src/pages/tickets/TicketsPage.tsx`
- 統一 reports 表，type 區分（report/system_issue/appeal 等）

**vs 前次**：未變

---

### A15 — 支付紀錄查閱
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §後台管理 / API-001 §9.5.7
**證據**：
- `AdminController::payments` + `refundPayment`
- `backend/routes/api.php:323-325` — list / refund / issue-invoice（含 super_admin 限定）
- `admin/src/pages/payments/PaymentsPage.tsx`

**vs 前次**：未變

---

### A19 — 系統參數設定
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §後台管理
**證據**：
- `backend/app/Http/Controllers/Api/V1/Admin/SystemControlController.php`
- `backend/routes/api.php:388-413` — system-control / app-mode / mail / sms / tracking / subscription-plans / dataset / member-level-permissions / permission-matrix
- `admin/src/pages/settings/SystemSettingsPage.tsx` + `tabs/`（多 Tab：管理員 / 模式 / DB / Mail / SMS / 訂閱方案 / 系統參數 / 誠信分數 / 等級權限）

**vs 前次**：未變

---

### A20 — 管理員角色權限管理
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §後台管理 / API-001 §9.5.1
**證據**：
- `backend/app/Http/Controllers/Api/Admin/AdminCrudController.php`
- `backend/routes/api.php:417-422` — admins CRUD + roles
- 三角色：super_admin / admin / cs，細粒度 RBAC（`admin.permission:xxx` middleware）
- Admin UI 在 SystemSettingsPage Tab 1（admins）

**vs 前次**：未變

---

### A21 — 操作日誌
**狀態**：✅ Implemented
**規格**：MiMeet_功能清單 §後台管理
**證據**：
- `backend/app/Http/Controllers/Api/V1/Admin/AdminLogController.php` + `UserActivityLogController.php`
- `backend/routes/api.php:360, 363` — admin logs / user-activity-logs（`logs.view` 權限）
- `backend/app/Models/AdminOperationLog.php` + `UserActivityLog.php`
- `admin/src/pages/logs/` 對應頁面（admin.log middleware 自動寫入）

**vs 前次**：未變

---

## Phase 2 功能逐項

### F08 — 隱私設定
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §用戶系統 / API-001 §10.10
**證據**：
- `backend/app/Http/Controllers/Api/V1/PrivacyController.php`
- `backend/routes/api.php:251-252` — GET / PATCH `/me/privacy`
- `frontend/src/views/app/settings/AccountView.vue` 隱私設定 section
- 支援 show_online_status / allow_profile_visits / show_in_search / show_last_active / allow_stranger_message

**vs 前次**：未變

---

### F10 — 帳號刪除申請（GDPR）
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §用戶系統 / API-001 §10.11
**證據**：
- `backend/app/Http/Controllers/Api/V1/DeleteAccountController.php` + `backend/app/Services/GdprService.php`
- `backend/routes/api.php:263-264` — store / cancel
- `frontend/src/views/app/settings/DeleteAccountView.vue`
- 7 天冷靜期 + `pending_deletion` 狀態 + 路由守衛強制鎖定畫面

**vs 前次**：未變

---

### F14 — 自動停權機制
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §誠信分數
**證據**：
- `backend/app/Observers/CreditScoreObserver.php:13` — newScore ≤ 0 自動設 `auto_suspended`
- `backend/app/Http/Middleware/CheckSuspended.php:26` — 攔截停權用戶 API（whitelist 4 路由）
- 前端 router/guards 偵測 `suspended` / `auto_suspended` 跳轉 `/suspended`

**vs 前次**：未變

---

### F15 — 申訴流程
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §誠信分數 / API-001 §10.8
**證據**：
- `backend/app/Http/Controllers/Api/V1/AppealController.php` + `AppealService.php`
- `backend/routes/api.php:243-245` — store / current（皆 withoutMiddleware('check.suspended')）
- `frontend/src/views/suspended/AppealView.vue`
- 同停權期間限 3 次、同時 1 筆 active（APPEAL_LIMIT_REACHED / APPEAL_EXISTS）

**vs 前次**：未變

---

### F18 — 已讀狀態顯示
**狀態**：✅ Implemented（提前完成，付費限定）
**規格**：MiMeet_功能清單 §即時聊天
**證據**：
- `backend/app/Services/ChatService.php::markAsRead` 廣播 `MessageRead`
- `backend/routes/api.php:146` — PATCH `/chats/{id}/read`
- `frontend/src/components/chat/MessageBubble.vue` 顯示已讀狀態

**vs 前次**：未變

---

### F19 — 訊息回收
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §即時聊天 / API-001 §4.1.5
**證據**：
- `backend/routes/api.php:145` — `DELETE /chats/{id}/messages/{messageId}` + `membership:3` middleware
- 5 分鐘內、未讀、僅 sender、付費會員 + 廣播 `MessageRecalled`
- `frontend/src/components/chat/MessageBubble.vue` 長按選單

**vs 前次**：未變

---

### F20 — 聊天紀錄關鍵字搜尋
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §即時聊天 / API-001 §4.1.6
**證據**：
- `backend/routes/api.php:143` — `GET /chats/{id}/messages/search`
- `backend/app/Http/Controllers/Api/V1/ChatController.php::searchMessages`
- `frontend/src/views/app/ChatView.vue` 搜尋面板

**vs 前次**：未變

---

### F21 — 搜尋聊天對象暱稱
**狀態**：✅ Implemented（提前完成，純前端）
**規格**：MiMeet_功能清單 §即時聊天
**證據**：
- `frontend/src/views/app/MessagesView.vue` 搜尋框 + computed filter（本地過濾 conversations）

**vs 前次**：未變

---

### F22 — 免打擾模式
**狀態**：✅ Implemented（提前完成，雙模式）
**規格**：MiMeet_功能清單 §即時聊天 / API-001 §4.1.7 / §10.12
**證據**：
- 對話靜音：`backend/routes/api.php:147` — `PATCH /chats/{id}/mute`
- `backend/app/Models/Conversation.php:72` — `isMutedBy(int $userId): bool`
- 全域 DND：`backend/app/Http/Controllers/Api/V1/DndController.php` + `routes/api.php:153-154`
- `backend/app/Models/User.php:200` — `isInDndPeriod()`
- `backend/database/migrations/2026_04_20_000001_add_mute_flags_to_conversations_table.php`
- `backend/database/migrations/2026_04_20_000002_add_dnd_fields_to_users_table.php`

**vs 前次**：未變

---

### F27 — 進階綜合篩選
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §搜尋配對 / API-001 §3.2.1
**證據**：
- `backend/app/Http/Controllers/Api/V1/UserController.php::search` — 9 個 profile 欄位篩選
- `backend/database/migrations/2026_04_20_120000_add_profile_fields_to_users_table.php`
- `frontend/src/components/explore/FilterBottomSheet.vue`
- `frontend/src/composables/useExplore.ts:29-58` — `buildCreditParams`
- ProfileView 詳細資料 section（commit `cafd099`）顯示 9 欄位 + F40-d 鎖定 placeholder

**vs 前次**：未變（內部品質提升：ProfileView 集中呈現完整資料）

---

### F28 — 智能配對排序
**狀態**：✅ Implemented（基礎版，符合 spec 降階建議）
**規格**：MiMeet_功能清單 §搜尋配對
**證據**：
- `backend/app/Http/Controllers/Api/V1/UserController.php::search` — 三層 orderBy（資料完整度 → credit_score → last_active_at）
- 符合 PRD 「初期用『誠信分數 + 最後上線時間』排序足夠」決策

**vs 前次**：未變

---

### F29 — 動態發布
**狀態**：❌ Not Implemented
**規格**：MiMeet_功能清單 §社群動態 / API-001 §6.1
**證據**：
- grep `moment|user_moments|MomentController|PostController` 在 backend/app + backend/routes + backend/database/migrations + frontend/src + admin/src 全部 0 hit
- 「動態」字樣僅出現 marketing copy（LandingView / TrialView / ShopView）
- 後台 `SystemSettingsPage.tsx:270` 有 `post_moment` 權限矩陣 label 但無實作

**vs 前次**：未變

---

### F30 — 動態瀏覽（最新）
**狀態**：❌ Not Implemented
**規格**：MiMeet_功能清單 §社群動態
**證據**：同 F29（依賴 F29 之 model 與 endpoint 不存在）

**vs 前次**：未變

---

### F31 — 收藏對象動態分頁
**狀態**：❌ Not Implemented
**規格**：MiMeet_功能清單 §社群動態
**證據**：同 F29

**vs 前次**：未變

---

### F32 — 點愛心互動
**狀態**：❌ Not Implemented
**規格**：MiMeet_功能清單 §社群動態
**證據**：同 F29

**vs 前次**：未變

---

### F33 — 我的收藏（關注系統）
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §社群動態 / API-001 §10.1
**證據**：
- `backend/app/Models/UserFollow.php` + `user_follows` 表
- `backend/database/migrations/2026_04_11_000001_create_social_tables.php`
- `backend/routes/api.php:106, 109-110` — following / follow / unfollow
- `frontend/src/views/app/FavoritesView.vue`

**vs 前次**：未變

---

### F34 — 誰來看我（訪客名單）
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §社群動態 / API-001 §10.2
**證據**：
- `backend/app/Models/UserProfileVisit.php` + `user_profile_visits` 表
- `backend/routes/api.php:107` — `/users/me/visitors`
- `frontend/src/views/app/VisitorsView.vue`
- `frontend/src/composables/useVisitorClick.ts` — 付費男性 vs 女性訪客點擊邏輯

**vs 前次**：未變

---

### F35 — 匿名聊天室
**狀態**：❌ Not Implemented
**規格**：MiMeet_功能清單 §社群動態 / API-001 §10.6（規格已寫，明確標示 Phase 4）
**證據**：
- grep `anonymous_chat|anon_chat|AnonymousChat|匿名聊天` 在 backend / frontend / admin 唯一 hit 為 `admin/src/pages/settings/tabs/CreditScoreTab.tsx:36` 之等級權限矩陣標籤（標示「⚪ Phase 2 未上線」）
- 無對應 model / route / migration / view
- API-001 §10.6 明確標註「Phase 4 實作」

**vs 前次**：未變

---

### F40 — 點數加值服務
**狀態**：✅ Implemented（提前完成，含 a/b/c/d 全部子功能）
**規格**：MiMeet_功能清單 §商業 / API-001 §11
**證據**：
- 核心：`backend/app/Models/PointPackage.php` + `PointOrder.php` + `PointTransaction.php` + `backend/app/Services/PointService.php` + `PointController.php`
- F40-a 隱身：`backend/app/Http/Controllers/Api/V1/StealthController.php` + `routes/api.php:201-203`
- F40-b 逆區間：`ChatController::sendMessage` + `use_points` 欄位（PRD §4.3.3 突破）
- F40-c 超級讚：`backend/app/Http/Controllers/Api/V1/SuperLikeController.php` + `routes/api.php:219`
- F40-d 詳細資料通行證：`backend/app/Http/Controllers/Api/V1/ProfileDetailsPassController.php` + `routes/api.php:208`
- F40-d DB：`backend/database/migrations/2026_05_15_120000_add_details_pass_until_to_users_table.php`
- F40-d Frontend：`frontend/src/components/user/UnlockDetailsModal.vue` + `frontend/src/api/users.ts:53` `unlockProfileDetails()` + `ProfileView.vue:575` 引用
- F40-d Gating：`UserController::canSeeProfileDetails` (line 384) + `details_unlocked` response (line 353)
- F40-d 拒重複購買：方案 B → 422 `DETAILS_PASS_ACTIVE` + remaining seconds

**vs 前次**：未變（F40-d 為 2026-05-15 commit `bea54ce` 落地，視為 F40 內部擴充）

---

### F41 — 廣播功能（付費點數）
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §商業 / API-001 §11.8
**證據**：
- `backend/app/Models/UserBroadcast.php` + `user_broadcasts` 表
- `backend/database/migrations/2026_04_20_220000_create_user_broadcasts_table.php`
- `backend/app/Http/Controllers/Api/V1/UserBroadcastController.php`
- `backend/routes/api.php:223-226` — preview / send / my（membership:2+）
- `frontend/src/components/broadcast/BroadcastModal.vue`
- 規則：每日 1 次、最多 50 人、2 點/人

**vs 前次**：未變

---

### F42 — VIP 隱身模式
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §商業 / API-001 §11.5
**證據**：
- 共用 `StealthController` — Lv3 免費 / 非 Lv3 扣 10 點 24h
- `users.stealth_until` 欄位獨立於 `privacy_settings.show_in_search`（兩套 OR 連接）
- `User::isInDndPeriod` 同類獨立狀態欄位設計

**vs 前次**：未變

---

### F44 — 匿名聊天室檢舉
**狀態**：❌ Not Implemented
**規格**：MiMeet_功能清單 §安全
**證據**：
- 依賴 F35（匿名聊天室）存在；F35 未實作
- reports.type enum 實際為 `fake_photo / harassment / spam / scam / inappropriate / other / appeal / system_issue` 8 值，**不含** `anon_report`（前次 2026-05-13 audit 誤記 enum 含此值，2026-05-15 已更正）

**vs 前次**：未變（證據已對齊更正後內容）

---

### F46 — 歷史回報紀錄
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §安全 / API-001 §10.4
**證據**：
- `backend/routes/api.php:235, 237` — `/reports/history` + `/reports/{id}/followups`
- `backend/app/Http/Controllers/Api/V1/ReportController.php::history / addFollowup`
- `frontend/src/views/app/ReportsHistoryView.vue`

**vs 前次**：未變

---

### A01 — 儀表板統計
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §後台管理 / API-001 §9.5.2
**證據**：
- `backend/app/Http/Controllers/Api/V1/Admin/StatsController.php::summary / chart / export`
- `backend/routes/api.php:367-369` — summary / chart / export
- `admin/src/pages/dashboard/DashboardPage.tsx`

**vs 前次**：未變

---

### A10 — 聊天紀錄查閱
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §後台管理 / API-001 §9.5.5
**證據**：
- `backend/app/Http/Controllers/Api/Admin/ChatLogController.php`
- `backend/routes/api.php:330-334` — search / conversations / export / memberChatLogs / memberChatLogsExport
- `admin/src/pages/chat-logs/ChatLogsPage.tsx`

**vs 前次**：未變

---

### A11 — 匿名聊天紀錄查閱
**狀態**：❌ Not Implemented
**規格**：MiMeet_功能清單 §後台管理
**證據**：
- 依賴 F35；F35 未實作
- `ChatLogController` 只處理一般 1-on-1 對話，無匿名對應 endpoint
- API-001 §9.5.6 為規劃骨架，未實作

**vs 前次**：未變

---

### A14 — 後台廣播訊息
**狀態**：✅ Implemented（Sprint 11 基礎版）
**規格**：MiMeet_功能清單 §後台管理
**證據**：
- `backend/app/Http/Controllers/Api/V1/Admin/BroadcastController.php`
- `backend/app/Models/BroadcastCampaign.php`
- `backend/routes/api.php:354-357` — index / store / show / send（`broadcasts.manage` 權限）
- `admin/src/pages/broadcasts/BroadcastsPage.tsx`

**vs 前次**：未變

---

### A16 — 訂閱折扣管理
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §後台管理 / API-001 §9.5.9
**證據**：
- `backend/database/migrations/2026_04_15_100000_add_promo_fields_to_subscription_plans.php`
- `backend/app/Http/Controllers/Api/V1/Admin/SystemControlController.php::getSubscriptionPlans / updateSubscriptionPlan`
- `backend/routes/api.php:401-402`
- `admin/src/pages/plans/PlanSettingsPage.tsx` — PricingTab

**vs 前次**：未變

---

### A17 — SEO Meta Tag 管理
**狀態**：✅ Implemented（提前完成）
**規格**：MiMeet_功能清單 §後台管理 / API-001 §9.5.8
**證據**：
- `backend/database/migrations/2026_04_19_210000_create_seo_metas_table.php`
- `backend/app/Models/SeoMeta.php`
- `backend/app/Http/Controllers/Admin/SeoController.php::metaIndex / metaUpdate`
- `backend/routes/api.php:342-343` — meta GET / PATCH（`seo.manage` 權限）
- `admin/src/pages/seo/SeoPage.tsx`

**vs 前次**：未變

---

### A18 — 廣告跳轉連結管理
**狀態**：❌ Not Implemented
**規格**：MiMeet_功能清單 §後台管理 / API-001 §12
**證據**：
- `backend/app/Http/Controllers/Admin/SeoController.php:61-62` — `linkIndex` / `linkStore` 仍為 commented stub
- `backend/routes/api.php` grep `/go/` 與 `seo/links` 仍 0 後端路由命中
- `frontend/src/router/routes/public.ts:64-75` — `/go/:slug` 路由整段已註解（commit `51d67bb`），含 4 步驟恢復條件 comment
- `frontend/src/views/public/GoRedirectView.vue` 仍存在但無入口（dead code，未來恢復用）
- `admin/src/pages/seo/SeoPage.tsx:274` — JSX 註解區塊「後端方法尚未實作」

**vs 前次**：狀態 🟡 → ❌ 已於 2026-05-16 delta audit 落定。**非退化**——只是把「會 404 fallback 的死路徑」靜默化。

---

### A19（MVP，已列於 MVP 區）

---

### A20（MVP，已列於 MVP 區）

---

### A21（MVP，已列於 MVP 區）

---

## 跨功能觀察

### 1. 變化趨勢

自 2026-05-15 完整 audit 以來累計 8 個 commit（housekeeping sprint 三 stage + F40-d 四 commit + 早期 docs），所有 ✅ 項目本輪仍 ✅，無新增 ❌ 也無新增 🟡。

兩大 cluster：
- **F40-d 詳細資料通行證**（4 commits, `de1b32d` / `8e08c93` / `cafd099` / `bea54ce`）：F07/F27 UI 精修、ProfileView 詳細資料 section、F40-d 完整商業功能（DB + Backend + Frontend + Modal + Spec 三方同步 + pre-merge 14az-1/14az-2 guards）
- **Housekeeping sprint**（4 commits）：A18 前端路由註解（`51d67bb`）、A02 連根拔除（`2f6ddce`）、過時 docs 清理（`0270208`）、含 docs 上層調整

### 2. 退化偵測

**0 項退化**。所有前次 ✅ 項目本輪仍為 ✅；A18 從 🟡 → ❌ 不算退化（前次的 🟡 本就是「前端路徑指向不存在的後端」即 404 fallback，本次降為 ❌ 是承認該功能未實作，前端註解只是把死路徑靜默化）。

### 3. 孤兒（Orphan）統計

| Orphan 類型 | 2026-05-13 | 2026-05-15 | 2026-05-16 |
|---|---|---|---|
| Backend 孤兒（A02 server-metrics） | 1 | 1 | **0**（已刪除） |
| Frontend 孤兒（A18 /go/:slug 路由） | 1 | 1 | **0**（已註解，view component 保留無入口） |

**孤兒清零** ✅。

### 4. 文件對齊現況

`docs/MiMeet_功能清單_MVP_vs_Phase2.md` v1.3 仍標 2026-04-09 「Sprint 11 補完」，A02 列已於 commit `2f6ddce` 刪除。
頂部已加「文件分工」note，明確指引讀者去 `docs/audits/`。
過時的 `MiMeet_功能檢核表_20260420.md` 與 `IMPLEMENTATION_STATUS.md` 兩份檔案已於 commit `0270208` 刪除（兩者均與 audit 累積 drift）。

API-001 / API-002 / PRD-001 / UI-001 / DEV-004 / SDD-001 之 A02 殘留均已隨 `2f6ddce` 同步清乾淨。F40-d 對應 API-001 §3.1.1 / §11.7 / §11.6（auth/me payload）三段已同步補。

**單一事實源確立**：`docs/audits/MVP_AUDIT_YYYY-MM-DD.md` 是實作狀態唯一來源；`MiMeet_功能清單_MVP_vs_Phase2.md` 留為規劃層。

### 5. 規格對齊狀況（spec drift）

- F44 證據糾正鞏固：`reports.type` enum 八值，不含 `anon_report`（前次 2026-05-13 誤記，2026-05-15 已修正本輪維持正確記述）
- F40-d 解鎖條件四項（看自己 / 女性 Lv1.5+ / 男性訂閱中 / 通行證未過期）程式碼與 spec 對齊
- F37/F38 trial auto-renew 三層守護（API guard / payload 欄位 / UI 條件渲染）有 4 個 feature test + pre-merge 14ay 守護

### 6. Pre-merge-check guards 累積

近期新增（自 2026-05-15 以來）：
- 14ay-1..5：trial auto-renew 不退化（SubscriptionController guard / PaymentService payload / UI 條件渲染 ×3）
- 14az-1：ProfileView 詳細資料 section 必須 `v-if="profile.details_unlocked"`
- 14az-2：UserController::show 必須含 `details_unlocked` 遮罩

---

## 建議下一步（依優先序）

### MVP ❌ 補完
**無**。所有 36 個 MVP 項目皆 ✅。

### MVP 🟡 完整化
**無**。

### Phase 2 重點補完（依商業判斷決定優先序）

1. **社群動態系統打包**（F29 / F30 / F31 / F32 + AI 內容審核）— 仍未動，建議獨立 sprint。需含內容審核 pipeline（AI 自動過濾 + 人工複審），是 4 個項目中最大塊
2. **匿名聊天室子系統**（F35 + F44 + A11）— 大功能，搭配內容審核能量；API-001 §10.6 已明確標 Phase 4
3. **A18 真正完成或永久棄用** — 兩個方向擇一：
   - (a) 補完後端 SeoController::linkIndex / linkStore / linkUpdate / linkDestroy / linkStats + `/go/{slug}` route + admin SEO Tab JSX 取消註解 → 取消前端 router 註解
   - (b) 比照 A02 連根拔起：刪 GoRedirectView.vue + SeoController stub comments + 規格層 §12 / §9.5.8 link 系列

### F40-d follow-up（短期）

- 本機 phpunit 設置（`cd backend && composer install`）方便本機跑 F40-d feature test（目前 0 test 覆蓋，deploy 後僅 manual smoke test）
- 上線 1 週後評估 5 點定價合理性；目前無 dashboard widget 顯示通行證 sales（可結合 admin/dashboard 點數消費分布）

### 規格層 housekeeping

- `docs/MVP_AUDIT_2026-05-13.md` 仍在 `docs/` 根目錄，建議下次清理時 `git mv` 進 `docs/audits/` 統一位置
- 規劃層文件（MiMeet_功能清單_MVP_vs_Phase2.md）日期戳 2026-04-09 已過時，下次更新規劃時請同步刷新

### 🔍 規格對齊確認

**無**。本輪未發現偏離規格意圖的實作。

---

## 附錄：本輪搜尋詞索引

主要 grep 詞（按審計順序）：

- **A02 殘留**：`serverMetrics|/admin/stats/server|server-metrics`（0 hits）
- **A18 狀態**：`go/:slug|GoRedirectView|linkIndex|linkStore`（僅 stub comment + 路由註解 hits）
- **F29-F32 社群動態**：`moment|user_moments|MomentController|動態.*發布|PostController`（0 hits in active code）
- **F35/F44/A11 匿名聊天**：`anonymous_chat|anon_chat|AnonymousChat|匿名聊天`（僅後台權限矩陣標籤 hit）
- **F40-d**：`details_pass_until|details_unlocked|ProfileDetailsPass|canSeeProfileDetails|unlockProfileDetails|UnlockDetailsModal`
- **F37/F38 trial guard**：`is_trial|TRIAL_NOT_RENEWABLE|isTrial|TrialAutoRenewGuardTest`
- **F44 enum**：`anon_report`（0 hits）
- **F07 photos**：`buildProfilePhotos|'photos' =>`
- **路由與控制器目錄**：`Route::` 列舉 + `ls backend/app/Http/Controllers/Api/V1/`
- **Migration 完整性**：`ls backend/database/migrations/`

---

*本報告為唯讀掃描，未變更任何 code / spec / DB / 設定。檔案產出於 docs/audits/MVP_AUDIT_2026-05-16-full.md，未被 git add，由使用者決定是否 commit。*
