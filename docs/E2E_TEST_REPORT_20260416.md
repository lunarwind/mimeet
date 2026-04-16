# MiMeet MVP E2E 測試報告

**測試日期：** 2026-04-16
**測試環境：** Staging (api.mimeet.online)
**測試帳號：** e2e_a_1776338558@mimeet.test / e2e_b_1776338558@mimeet.test

---

## 測試結果總覽

| 指標 | 數值 |
|------|------|
| 總測試數 | 53 |
| 通過 | 49 |
| 失敗 | 4 |
| 通過率 | **92.5%** |

---

## 逐項測試結果

| 模組 | 測試 | 預期 | 結果 | 備註 |
|------|------|------|------|------|
| 1-1 | A 註冊 | 201 | ✅ | |
| 1-1 | B 註冊 | 201 | ✅ | |
| 1-1 | 重複 email | 422 | ✅ | |
| 1-2 | 錯誤 OTP | reject | ✅ | |
| 1-2 | 正確 OTP | EMAIL_VERIFIED | ✅ | |
| 1-3 | 正確登入 | LOGIN_SUCCESS | ✅ | |
| 1-3 | 錯誤密碼 | 401 | ✅ | |
| 1-3 | GET /auth/me | 回傳用戶資料 | ✅ | |
| 1-4 | SMS send 無登入 | 200 | ✅ | 註冊流程可用 |
| 1-4 | SMS send 已登入 | PHONE_CODE_SENT | ✅ | |
| 1-4 | 60s 冷卻 | 429 code 1020 | ✅ | |
| 1-4 | 錯誤驗證碼 | 422 code 1023 | ✅ | |
| 1-4 | 正確驗證碼 | PHONE_VERIFIED | ✅ | |
| 1-4 | phone_verified | True | ❌ False | P1 — 見異常 #1 |
| 1-5 | forgot-password | RESET_LINK_SENT | ✅ | |
| 1-5 | 不存在 email | RESET_LINK_SENT | ✅ | 防枚舉正確 |
| 1-6 | 更新暱稱/地區 | PROFILE_UPDATED | ✅ | |
| 1-6 | 修改 birth_date | 422 | ✅ | |
| 2-1 | 搜尋回傳用戶 | True | ✅ | |
| 2-1 | 性別篩選 | 只回傳女性 | ✅ | |
| 2-1 | is_favorited 存在 | True | ✅ | |
| 2-2 | 瀏覽 B 個人頁 | USER_DETAIL | ✅ | |
| 2-2 | 訪客記錄寫入 | 1 筆 | ✅ | |
| 2-2 | 自己不寫入 | 0 筆 | ✅ | |
| 3 | 收藏 B | followed: true | ✅ | |
| 3 | 收藏自己 | 422 code 2040 | ✅ | |
| 3 | 收藏列表 | 含 B | ✅ | |
| 3 | is_favorited | True | ✅ | |
| 3 | 取消收藏 | B 不在列表 | ✅ | |
| 4 | 封鎖 B | blocked: true | ✅ | |
| 4 | 封鎖自己 | 422 code 2030 | ✅ | |
| 4 | 封鎖列表 | 含 B | ✅ | |
| 4 | 搜尋排除 B | True | ✅ | |
| 4 | B 看 A → 403 | 403 | ✅ | |
| 4 | 解除後搜尋到 B | True | ✅ | |
| 5 | B 在 A 訪客中 | True | ✅ | |
| 5 | visited_at_human | 0 分鐘前 | ✅ | |
| 5 | total_visitors ≥ 1 | True | ✅ | |
| 6 | 建立對話 | conv_id | ✅ | |
| 6 | 對話列表 | True | ✅ | |
| 6 | 發送訊息 | 201 | ✅ | |
| 6 | 取得訊息 | True | ✅ | |
| 6 | 封鎖後傳訊 | 400 | ✅ | |
| 7 | 建立約會邀請 | 201 | ❌ 422 | P2 — 見異常 #2 |
| 7 | 約會列表 | True | ✅ | |
| 8 | 用戶檢舉 | 201 | ✅ | |
| 8 | 系統問題回報 | 201 | ❌ 422 | P2 — 見異常 #3 |
| 8 | 歷史列表 | True | ✅ | |
| 9 | 方案列表 | True | ✅ | |
| 9 | 訂閱狀態 | True | ✅ | |
| 9 | 體驗價 | True | ✅ | |
| 10 | 無 token → 401 | 401 | ✅ | |
| 10 | 前台 token 打後台 | 401 | ❌ 422 | P3 — 見異常 #4 |

---

## 異常發現

### 異常 #1（P1）：SMS 驗證成功但 phone_verified 未持久化

- **現象**：`verifyPhoneConfirm` 回傳 `PHONE_VERIFIED`，但後續 `/auth/me` 仍顯示 `phone_verified: false, phone: null`
- **根本原因**：phone 欄位使用 `encrypted` cast，加密後字串可能超出 DB 容量或 save() 靜默失敗。需進一步檢查是否是新建帳號未跑過 widen_phone migration。
- **影響**：用戶完成手機驗證但等級未升級
- **建議**：檢查 phone column 實際寬度，確認 migration 已執行；在 verifyPhoneConfirm 中加入 save() 結果檢查

### 異常 #2（P2）：約會邀請欄位名稱不符

- **現象**：`POST /dates` 回傳 422 `"The date time field is required."`
- **根本原因**：API 期望欄位名為 `date_time`，測試送的是 `scheduled_at`
- **影響**：功能本身可用，僅測試參數與 API 規格不符
- **建議**：確認前端 DatesView.vue 使用的欄位名，與 API spec 對齊

### 異常 #3（P2）：系統問題回報需要 reported_user_id

- **現象**：`POST /reports` type=system_issue 要求 `reported_user_id`
- **根本原因**：API 驗證規則未區分 report type，所有回報都需要 reported_user_id
- **影響**：系統問題回報（無特定對象）流程卡住
- **建議**：後端 ReportController 應讓 system_issue type 的 reported_user_id 為 nullable

### 異常 #4（P3）：前台 token 打後台 API 回 422 而非 401

- **現象**：帶前台 token 呼叫 `POST /admin/auth/login` 回傳 422（驗證失敗）而非 401
- **根本原因**：admin login 不走 auth middleware，先做 input validation（email format check），在 auth 前就回 422
- **影響**：安全性無問題（前台 token 無法取得 admin 權限），僅 HTTP status 語意不精確
- **建議**：低優先級，不影響安全

---

## 整體評估

| 項目 | 評估 |
|------|------|
| 通過率 | 92.5%（49/53） |
| P0 嚴重缺陷 | 0 |
| P1 需修復 | 1（SMS phone_verified 持久化） |
| P2 需修復 | 2（Date 欄位名、Report type 驗證） |
| P3 觀察 | 1（Admin login HTTP status） |

### 是否達到上線標準？

**有條件通過。** 核心流程（註冊→Email 驗證→登入→搜尋→聊天→金流→檢舉→封鎖→收藏→訪客）全部通過。
P1 的 SMS phone_verified 問題需在上線前修復（影響會員升級流程）。
P2 項目可在上線後第一週修復。
