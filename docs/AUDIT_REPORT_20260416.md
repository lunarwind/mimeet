# MiMeet MVP 實作檢核報告

**檢核日期：** 2026-04-16
**檢核人員：** Claude Code
**檢核範圍：** MVP 功能（排除 Phase 2 動態系統、匿名聊天室）

---

## 一、資料庫 Schema 差異

| 資料表 | 欄位/索引 | 規格書 (DEV-006) | 實際 DB | 差異說明 |
|--------|---------|-----------------|--------|---------|
| reports | type | TINYINT (1=一般,2=系統,3=匿名) | ENUM('fake_photo','harassment','spam','scam','inappropriate','other','appeal') | P1 — 規格書用數字 type，實際用 ENUM；且 ENUM 缺少 `system_issue` |
| reports | reported_user_id | BIGINT UNSIGNED **NULL** | BIGINT UNSIGNED **NOT NULL** | P1 — 系統問題回報不需被檢舉者，但 DB 不允許 NULL |
| users | status | ENUM 未明確定義 | ENUM('active','suspended','auto_suspended','pending_deletion','deleted') | P3 — 規格書未列完整 ENUM 值，但實作正確 |
| orders | (表名) | 規格書寫 `payment_records` | 實際為 `orders` | P3 — 已有備註說明，migration 順序表仍寫舊名 |
| subscription_plans | promo_* 欄位 | 規格書無此欄位 | 有 promo_type/value/start_at/end_at/note | P3 — Phase 2 超前實作，規格書未同步 |
| users | phone | 規格書寫 VARCHAR(255) | 實際 VARCHAR(255) | ✅ 一致 |
| user_follows | following_id | 規格書已修正為 following_id | 實際 following_id | ✅ 一致 |
| user_profile_visits | visited_user_id | 規格書已修正 | 實際 visited_user_id | ✅ 一致 |
| credit_score_histories | 全部欄位 | 一致 | 一致 | ✅ |
| conversations | 全部欄位 | 一致 | 一致 | ✅ |
| messages | 全部欄位 | 一致 | 一致 | ✅ |
| date_invitations | 全部欄位 | 一致 | 一致 | ✅ |

---

## 二、API 路由缺失或差異

| 規格書路由 | Method | 狀態 | 說明 |
|-----------|--------|------|------|
| /auth/register | POST | ✅ | |
| /auth/login | POST | ✅ | |
| /auth/logout | POST | ✅ | |
| /auth/me | GET | ✅ | |
| /auth/verify-email | POST | ✅ | |
| /auth/forgot-password | POST | ✅ | |
| /auth/reset-password | POST | ✅ | |
| /auth/verify-phone/send | POST | ✅ | 已移至公開路由（無需 auth） |
| /auth/verify-phone/confirm | POST | ✅ | 同上 |
| /users/search | GET | ✅ | |
| /users/me | GET | ✅ | |
| /users/me | PATCH | ✅ | |
| /users/me/following | GET | ✅ | |
| /users/me/visitors | GET | ✅ | |
| /users/me/blocked-users | GET | ✅ | 路由為 /me/blocked-users（非 /users/me/） |
| /users/{id} | GET | ✅ | |
| /users/{id}/follow | POST | ✅ | |
| /users/{id}/follow | DELETE | ✅ | |
| /users/{id}/block | POST | ✅ | |
| /users/{id}/block | DELETE | ✅ | |
| /chats | GET | ✅ | |
| /chats | POST | ✅ | |
| /chats/{id}/messages | GET | ✅ | |
| /chats/{id}/messages | POST | ✅ | |
| /dates | GET | ✅ | |
| /dates | POST | ✅ | |
| /dates/verify | POST | ✅ | 路由為 /dates/verify（非 /dates/{id}/verify） |
| /dates/{id}/accept | PATCH | ✅ | 規格書為 respond，實作拆分為 accept/decline |
| /dates/{id}/decline | PATCH | ✅ | |
| /reports | POST | ✅ | |
| /reports/history | GET | ✅ | |
| /subscriptions/plans | GET | ✅ | |
| /subscriptions/me | GET | ✅ | |
| /subscriptions/orders | POST | ✅ | |
| /subscription/trial | GET | ✅ | 注意：prefix 是 subscription（單數），非 subscriptions |
| /subscriptions/cancel-request | POST | ✅ | |
| /me/verification-photo/request | POST | ✅ | 路由不同：規格書為 /verification/initiate |
| /me/verification-photo/upload | POST | ✅ | 路由不同：規格書為 /verification/submit |
| /me/appeal | POST | ✅ | |
| /me/delete-account | POST+DELETE | ✅ | 規格書為 DELETE /me/account，實作拆分為 POST(申請)+DELETE(取消) |
| /me/privacy | GET+PATCH | ✅ | 規格書未明確列出，實作已有 |
| /notifications | GET | ✅ | 規格書未明確列出，實作已有 |

---

## 三、STUB 未實作項目

| 檔案 | 方法 | STUB 說明 |
|------|------|---------|
| （無） | （無） | **全部 Controller 方法均已實作，無殘留 STUB** |

> grep 搜尋 `// TODO` `// STUB` 結果為零。所有先前的 STUB 方法（follow/unfollow/following/block/unblock/blockedUsers/visitors/forgotPassword/resetPassword/verifyPhoneSend/verifyPhoneConfirm）均已在本次 sprint 替換為真實邏輯。

---

## 四、業務邏輯檢核

| 功能 | 項目 | 狀態 | 說明 |
|------|------|------|------|
| F03 SMS | OTP 存入 Redis (otp:phone:{e164}) | ✅ | |
| F03 SMS | 60 秒冷卻 (otp:cooldown:{e164}) | ✅ | |
| F03 SMS | 最多 5 次失敗 (otp:fail:{e164}) | ✅ | |
| F03 SMS | 驗證成功 → membership_level ≥ 1 | ✅ | |
| F04 密碼重設 | token 存入 password_reset_tokens | ✅ | |
| F04 密碼重設 | 60 分鐘有效期 | ✅ | diffInMinutes > 60 |
| F04 密碼重設 | 重設後銷毀 token | ✅ | |
| F04 密碼重設 | 重設後撤銷所有 Sanctum tokens | ✅ | $user->tokens()->delete() |
| F09 封鎖 | 搜尋結果互相排除 | ✅ | blockedIds + blockerIds |
| F09 封鎖 | 被封鎖方查看個人頁 → 403 | ✅ | |
| F09 封鎖 | 被封鎖方傳訊 → 400 code 2002 | ✅ | |
| F12 誠信分數 | CreditScoreService 存在 | ✅ | |
| F12 誠信分數 | 分數 ≤ 0 自動停權 | ✅ | 由 CreditScoreObserver 處理 |
| F12 誠信分數 | 分數變更寫入 credit_score_histories | ✅ | |
| F16 聊天 | 每日訊息限制（依等級） | ✅ | DailyLimitException |
| F16 聊天 | 非參與者無法讀取 | ✅ | isParticipant 驗證 |
| F23-25 QR | QR token 時間窗驗證 | ✅ | DateService |
| F23-25 QR | 一次性使用 | ✅ | |
| F23-25 QR | GPS +5 / 無 GPS +2 | ✅ | |
| F10 刪除帳號 | 7 天冷靜期 | ✅ | GdprService |
| F10 刪除帳號 | 排程匿名化 | ✅ | ProcessGdprDeletions command |

---

## 五、前台頁面缺失

| 路由 | 檔案 | 狀態 |
|------|------|------|
| 全部 25 個 Vue 頁面 | 全部存在 | ✅ |

> RegisterView, LoginView, ForgotPasswordView, ResetPasswordView, ExploreView, ProfileView, MessagesView, ChatView, DatesView, VisitorsView, FavoritesView, ShopView, ReportsView, ReportsHistoryView, AccountView, VerifyView, BlockedView, DeleteAccountView, SuspendedView, AppealView, PrivacyView, TermsView, AntiFraudView, LandingView, QRScanView — 全部存在。

---

## 六、後台頁面缺失

| 功能 | 頁面 | 狀態 |
|------|------|------|
| 全部 13 個後台頁面 | 全部存在 | ✅ |

> MembersPage, MemberDetailPage, VerificationsPage, AnnouncementsPage, TicketsPage, PaymentsPage, SystemSettingsPage, AdminUsersPage, ActivityLogsPage, UserActivityLogsPage, ChatLogsPage, BroadcastsPage, SeoPage — 全部存在。

---

## 七、API 回應格式差異

| 端點 | 規格書格式 | 實際回應 | 差異說明 |
|------|-----------|---------|---------|
| 401 未授權 | `{ success, code, message }` | `{ success: false, code: 401, message: "未登入或 Token 已過期" }` | ✅ 符合 |
| GET /users/search | `{ data: { users: [...], pagination } }` | 同規格 | ✅ 符合 |
| POST /users/{id}/block | `{ success, data: { blocked } }` | `{ success: true, data: { blocked: true } }` | ✅ 符合 |
| POST /users/{id}/follow | `{ success, data: { followed } }` | `{ success: true, data: { followed: true } }` | ✅ 符合 |
| GET /users/me/visitors | `{ data: { visitors, total_visitors_90days }, pagination }` | 同規格 | ✅ 符合 |
| POST /auth/login | `{ data: { user, token } }` | user 缺少 `phone` 欄位 | P2 — login 回應缺 phone（me 端點已修復） |
| GET /users/search | user 物件 | 缺少 `distance` 欄位 | P3 — GPS 距離計算為 Phase 2 功能 |

---

## 八、總結

### 嚴重度分級

| 嚴重度 | 項目數 | 說明 |
|--------|-------|------|
| P0（功能完全缺失） | 0 | 無 |
| P1（功能有問題） | 2 | reports.type ENUM 缺 system_issue + reported_user_id 不可 NULL |
| P2（格式不符規格） | 1 | login 回應缺 phone 欄位 |
| P3（小差異/可接受） | 4 | 表名 payment_records→orders, promo 欄位未同步, 路由微調, distance 欄位 |

### P1 問題明細

1. **reports.type DB ENUM 缺少 `system_issue`**
   - 後端 Controller 已加入 `system_issue` 驗證
   - 但 DB ENUM 只有 `fake_photo|harassment|spam|scam|inappropriate|other|appeal`
   - 實際寫入 DB 時會因 ENUM 限制失敗
   - 需要 migration 新增 `system_issue` 到 ENUM

2. **reports.reported_user_id 不可 NULL**
   - DEV-006 規格書定義為 NULL（系統問題時無被檢舉者）
   - 實際 DB 為 NOT NULL
   - 需要 migration 改為 nullable

### 上線可行性評估

- [x] 無 P0 問題阻擋上線
- [ ] 有 2 個 P1 問題需要上線前修正（reports 表 schema）
- **整體評估：有條件通過。** 核心功能（註冊/登入/搜尋/聊天/約會/金流/封鎖/收藏/訪客/檢舉/後台管理）全部實作完成，無 STUB 殘留。reports 表的 ENUM + nullable 問題需一個 migration 即可修正。
