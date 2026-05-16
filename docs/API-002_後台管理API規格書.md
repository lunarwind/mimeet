# [API-002] MiMeet 後台管理 API 規格書

**文檔版本：** v1.4（2026年4月更新，新增刪除管理員 + 重設密碼 API）  
**建立日期：** 2026年3月  
**適用範圍：** 後台 React Admin SPA 呼叫的所有 `/api/v1/admin/*` 端點  
**前置文件：** API-001（前台 API）、DEV-001（技術架構）、DEV-004（後端規範）

> **注意：** 本文件僅涵蓋後台管理端點。前台用戶端點請參閱 API-001。

---

## 1. 共用規範

### 1.1 Base URL 與版本

```
Production:  https://api.mimeet.tw/api/v1/admin
Development: http://localhost:8000/api/v1/admin
```

### 1.2 認證方式

後台使用 **Bearer Token（Sanctum）**，與前台的 Cookie SPA 認證不同：

```http
Authorization: Bearer {admin_access_token}
Content-Type: application/json
Accept: application/json
```

Token 由後台登入 API 取得，**有效期 8 小時**（480 分鐘，與前台用戶 24h 不同，安全性更高）。
登入成功回應含 `data.expires_at`（ISO 8601），前端可顯示 session 剩餘時效。

> **實作說明（2026-04-24 F-001 修復）：** admin token 在建立後以 `forceFill(['expires_at'])` 設定為 `now()->addMinutes(480)`，獨立於全域 `SANCTUM_TOKEN_EXPIRATION`（前台 1440 min）。

### 1.3 統一回應格式

**成功回應：**
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}
```

**錯誤回應：**
```json
{
  "success": false,
  "error": {
    "code": "ADMIN_4003",
    "message": "權限不足，此操作需要 super_admin 角色",
    "detail": null
  }
}
```

### 1.4 後台專用錯誤碼

| 代碼 | HTTP Status | 說明 |
|------|-------------|------|
| ADMIN_4001 | 401 | 未登入或 Token 失效 |
| ADMIN_4003 | 403 | 無此操作的角色權限 |
| ADMIN_4041 | 404 | 操作目標不存在 |
| ADMIN_4221 | 422 | 表單驗證失敗 |
| ADMIN_5001 | 500 | 伺服器內部錯誤 |

### 1.5 操作日誌自動記錄

所有 POST / PATCH / DELETE 請求，Middleware 自動寫入 `admin_operation_logs` 表，包含：
- 操作者 admin_id、角色
- 操作類型（action）
- 操作目標（resource_type + resource_id）
- 請求摘要（不記錄完整 body，避免儲存敏感資料）
- 操作 IP、User-Agent
- 操作時間

---

## 2. 後台認證 API

### 2.1 後台登入

```
POST /api/v1/admin/auth/login
```

**無需認證（Public）**

**請求參數：**
```json
{
  "email": "chuck@lunarwind.org",
  "password": "your-password"
}
```

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "token": "1|AbCdEfGh...",
    "token_type": "Bearer",
    "expires_in": 28800,
    "admin": {
      "id": 1,
      "name": "Super Admin",
      "email": "chuck@lunarwind.org",
      "role": "super_admin",
      "permissions": ["*"]
    }
  }
}
```

**錯誤回應 401：**
```json
{
  "success": false,
  "error": {
    "code": "ADMIN_4001",
    "message": "帳號或密碼錯誤"
  }
}
```

> **安全規則：** 連續錯誤 5 次（同 IP 15 分鐘內），鎖定 15 分鐘並記錄。

---

### 2.2 取得當前管理員資訊

```
GET /api/v1/admin/auth/me
```

**需 Bearer Token**

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Super Admin",
    "email": "chuck@lunarwind.org",
    "role": "super_admin",
    "permissions": ["*"],
    "last_login_at": "2025-01-15T10:30:00Z",
    "last_login_ip": "123.456.789.0"
  }
}
```

---

### 2.3 後台登出

```
POST /api/v1/admin/auth/logout
```

**需 Bearer Token**

**成功回應 200：**
```json
{
  "success": true,
  "data": { "message": "已成功登出" }
}
```

---

## 3. 儀表板統計 API

> 所需權限：所有管理員角色均可查看（members.view 或以上）

### 3.1 取得儀表板統計摘要

> **2026-04-30 更新**：回應結構已對齊實際實作。移除舊有的 `new_registrations / new_verified / active_users / paid_members` 欄位；新增 `level_distribution`（5 組等級分布）與 `recent_payments`（最新付款列表）。

```
GET /api/v1/admin/stats/summary
```

**權限：** admin（任意角色）

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "members": {
      "total": 1234,
      "new_today": 5,
      "new_month": 87,
      "paid": 256,
      "active": 890
    },
    "revenue": {
      "subscription_month": 38500,
      "points_month": 12800,
      "points_today": 1500
    },
    "points": {
      "circulating": 45600,
      "consumed_today": 320,
      "consumed_month": 8900,
      "consumption_by_feature": { "stealth": 120, "super_like": 200 }
    },
    "pending_tickets": 7,
    "pending_verifications": 4,
    "level_distribution": [
      { "level": "Lv0",   "label": "未驗證",       "count": 120 },
      { "level": "Lv1",   "label": "基礎驗證",     "count": 450 },
      { "level": "Lv1.5", "label": "女性照片驗證", "count": 88 },
      { "level": "Lv2",   "label": "進階驗證",     "count": 320 },
      { "level": "Lv3",   "label": "完整驗證",     "count": 256 }
    ],
    "recent_payments": [
      {
        "id": 10,
        "user": "癡兒",
        "plan": "MiMeet 週費方案",
        "type": "subscription",
        "amount": 149,
        "time": "2026-04-30T11:12:59+08:00",
        "invoice_status": "pending"
      }
    ]
  }
}
```

**業務規則：**

- `members.total`：`users` 表排除 `deleted_at IS NOT NULL` 的已刪除帳號，包含 uid=1 系統帳號
- `level_distribution`：固定 5 筆，順序固定為 Lv0 → Lv1 → Lv1.5 → Lv2 → Lv3
- `level_distribution.count` 採精確分組：`membership_level` 嚴格等於 0 / 1 / 1.5 / 2，以及 `>= 3`
- `recent_payments` 過濾條件：`status = 'paid'` AND `type IN ('subscription', 'points')`（排除 `verification` 的 NT$100 押金）
- `recent_payments` 排序：`paid_at DESC`，最多 5 筆
- `recent_payments.user`：顯示 `users.nickname`；已刪除用戶回 `"已刪除用戶"`
- `recent_payments.time`：ISO 8601 格式；`paid_at = null` 時回 `null`
- `recent_payments.invoice_status`：後端原值傳遞，前端負責顯示對照

**`invoice_status` 狀態說明（含顯示文字）：**

| 值 | 前端顯示 | 業務意義 |
|---|---|---|
| `pending` | 待開立 | IssueInvoiceJob 已 dispatch，等候 worker 處理或 retry 中 |
| `issued` | 已開立 | 發票已成功開立（同時會有 `invoice_no` 值）|
| `failed` | 開立失敗 | 重試 3 次後仍失敗，需 admin 介入手動處理 |
| `not_applicable` | 不適用 | 此付款不開立發票（業主決策）|
| `null` | — | 欄位未設值（2026-04-28 migration 之前的舊資料殘留）|

---

### 3.2 取得圖表資料

```
GET /api/v1/admin/stats/chart
```

> 所需權限：`members.view`

**Query 參數：**

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `days` | integer | 否 | 回傳最近 N 天，最大 90，預設 30 |

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "labels": ["2025-01-01", "2025-01-02", "..."],
    "series": {
      "new_members": [15, 22, 18],
      "subscription_revenue": [4500, 3200, 5100],
      "point_revenue": [800, 1200, 950]
    }
  }
}
```

---

### 3.3 匯出統計資料

```
GET /api/v1/admin/stats/export
```

> 所需權限：`members.view`

**Query 參數：**

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `days` | integer | 否 | 匯出最近 N 天，最大 90，預設 30 |

**成功回應 200：**

回傳 `Content-Type: text/csv; charset=UTF-8`，串流下載。

欄位：`Date, New Members, Subscription Revenue, Point Revenue, Total Revenue`

檔名格式：`mimeet-stats-{YYYYMMDD}.csv`

---

## 4. 會員管理 API

> 所需權限：`members.view`（查看）、`members.edit`（操作）、`members.delete`（刪除）

### 4.1 取得會員列表

> **會員計數口徑規範**
>
> `meta.total`（本端點）與 `GET /admin/stats/summary` 回傳的 `data.members.total` 在
> **無篩選條件下數值必須完全一致**，計算口徑為：
>
> ```sql
> SELECT COUNT(*) FROM users WHERE deleted_at IS NULL
> ```
>
> 說明：
> - 軟刪除（`deleted_at IS NOT NULL`）的用戶不計入
> - Admin 帳號存於獨立 `admin_users` 表（Multi-Guard），不會出現在 `users` 表，無需額外排除
> - 如 CI 測試 `StatsControllerTest::summary_total_equals_members_list_meta_total` 失敗，
>   代表兩端口徑出現歧異，必須修正

```
GET /api/v1/admin/members
```

**Query 參數：**

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `page` | int | 否 | 頁碼，預設 1 |
| `per_page` | int | 否 | 每頁筆數，預設 20，最大 100 |
| `search` | string | 否 | 搜尋 UID / 暱稱 / Email（模糊） |
| `gender` | string | 否 | `male` / `female` |
| `status` | string | 否 | `active`（預設）/ `suspended` / `deleted` |
| `level` | string | 否 | `registered` / `verified` / `advanced` / `paid` |
| `recent` | bool | 否 | `true` = 僅顯示 7 天內新註冊（預設 false） |
| `sort_by` | string | 否 | `created_at`（預設）/ `credit_score` / `last_login_at` |
| `sort_dir` | string | 否 | `desc`（預設）/ `asc` |
| `dating_budget` | string | 否 | F27 快速篩選：`casual`/`moderate`/`generous`/`luxury`/`undisclosed`（精確匹配，NULL 會被排除）|
| `style` | string | 否 | F27 快速篩選（gender-strict 全 18 個值）：女 `fresh`/`sweet`/`sexy`/`intellectual`/`sporty`/`elegant`/`korean`/`pure_student`/`petite_japanese`；男 `business_elite`/`british_gentleman`/`smart_casual`/`outdoor`/`boy_next_door`/`minimalist`/`japanese`/`warm_guy`/`preppy`（精確匹配）|

> **注意：** 後台 F27 篩選為**精確匹配**（未填欄位會被排除），與前台搜尋的「寬鬆篩選（OR NULL）」行為不同，因後台是管理用途需要精準定位。

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 1001,
      "uuid": "a1b2c3d4-...",
      "nickname": "甜心寶貝",
      "email": "user@example.com",
      "gender": "female",
      "age": 23,
      "level": "advanced",
      "credit_score": 85,
      "status": "active",
      "is_paid": true,
      "subscription_expires_at": "2025-03-01T00:00:00Z",
      "last_login_at": "2025-01-15T08:00:00Z",
      "created_at": "2025-01-01T10:00:00Z",
      "profile_view_count": 128,
      "verifications": {
        "email": true,
        "phone": true,
        "advanced": true
      }
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 1524,
    "last_page": 77
  }
}
```

---

### 4.2 取得單一會員詳情

```
GET /api/v1/admin/members/{user_id}
```

> **F27 (2026-04-20 補完)：** 回應 `data.member` 額外包含 9 個 profile 欄位 `style`、`dating_budget`、`dating_frequency`、`dating_type` (array)、`relationship_goal`、`smoking`、`drinking`、`car_owner` (boolean)、`availability` (array)。未填寫為 null。
>
> **F40 (2026-04-20 補完)：** `data.member.points_detail` 新增點數詳細資訊區塊：
> ```
> {
>   "balance": 120,                                      // 當前餘額
>   "total_purchased": 500,                              // 累計購買點數
>   "total_spent": 380,                                  // 累計消費點數
>   "purchase_amount_ntd": 1500,                         // 累計消費金額
>   "purchase_count": 3,                                 // 購買次數
>   "consumption_by_feature": {                          // 按功能分組的消費
>     "stealth": 40, "reverse_msg": 20,
>     "super_like": 6, "broadcast": 314
>   },
>   "recent_transactions": [ ... ],  // 最新 10 筆交易
>   "purchase_orders": [ ... ]        // 最新 5 筆購買訂單
> }
> ```

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "id": 1001,
    "uuid": "a1b2c3d4-...",
    "nickname": "甜心寶貝",
    "email": "user@example.com",
    "phone": "09xx-xxx-xxx",
    "gender": "female",
    "birth_date": "2001-05-20",
    "age": 23,
    "location": "台北市",
    "occupation": "上班族",
    "bio": "個人介紹...",
    "level": "advanced",
    "credit_score": 85,
    "status": "active",
    "suspend_reason": null,
    "is_paid": true,
    "subscription": {
      "plan": "monthly",
      "started_at": "2025-01-01T00:00:00Z",
      "expires_at": "2025-03-01T00:00:00Z"
    },
    "last_login_at": "2025-01-15T08:00:00Z",
    "login_ip": "123.xxx.xxx.xxx",
    "created_at": "2025-01-01T10:00:00Z",
    "verifications": {
      "email": { "verified": true, "verified_at": "2025-01-01T10:05:00Z" },
      "phone": { "verified": true, "verified_at": "2025-01-01T10:10:00Z" },
      "advanced": {
        "verified": true,
        "type": "photo",
        "verified_at": "2025-01-02T09:00:00Z",
        "reviewed_by": "Admin001"
      }
    },
    "photos": [
      { "id": 1, "url": "https://cdn.mimeet.tw/photos/...", "is_avatar": true },
      { "id": 2, "url": "https://cdn.mimeet.tw/photos/...", "is_avatar": false }
    ],
    "stats": {
      "total_messages_sent": 234,
      "total_date_invitations": 5,
      "total_date_completed": 3,
      "total_reports_received": 0,
      "total_reports_filed": 1
    },
    "admin_notes": "2025-01-10 管理員備註：此用戶曾反映登入問題，已協助處理。"
  }
}
```

---

### 4.3 對會員執行操作

```
PATCH /api/v1/admin/members/{user_id}/actions
```

> 所需權限：`members.edit`

**請求參數：**
```json
{
  "action": "adjust_credit",
  "value": -10,
  "reason": "違規行為：發送不當訊息"
}
```

**action 可選值：**

| action | 必填參數 | 說明 | 所需權限 |
|--------|---------|------|---------|
| `adjust_credit` | `value`（整數，可負）、`reason` | 調整誠信分數（後端實際 action key 為 `adjust_score`） | members.edit |
| `set_level` | `level`（registered/verified/advanced/paid）、`reason` | 手動調整會員等級 | members.edit |
| `require_reverify` | `verify_type`（phone/advanced）、`reason` | 要求重新驗證 | members.edit |
| `suspend` | `reason` | 停權帳號（誠信分數降為0） | members.edit |
| `unsuspend` | `reason` | 解除停權 | members.edit |
| `add_note` | `note`（管理員備註文字）| 新增管理員備註 | members.edit |
| `delete` | `reason` | 刪除帳號（軟刪除） | members.delete（super_admin） |

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "message": "已調整誠信分數 -10 分，目前分數：75",
    "user_id": 1001,
    "action": "adjust_credit",
    "new_credit_score": 75,
    "log_id": 5501
  }
}
```

---

### 4.3.5 刪除會員（super_admin only，立即且不可逆匿名化）

```http
DELETE /api/v1/admin/members/{id}
Authorization: Bearer {admin_token}
Content-Type: application/json
```

**RBAC**：`members.delete`（僅 `super_admin`）

**Request body（PR-2 2026-05-07 起新增 optional 欄位，向後相容）**：
```json
{
  "blacklist_email": false,
  "blacklist_mobile": false,
  "blacklist_reason": null
}
```

| 欄位 | 型別 | 必填 | 說明 |
|---|---|---|---|
| `blacklist_email` | boolean | 否(default false) | 同時將該 user 的 email 加入註冊禁止名單 |
| `blacklist_mobile` | boolean | 否(default false) | 同時將該 user 的手機加入註冊禁止名單 |
| `blacklist_reason` | string\|null | 否(max:500) | 加入禁止名單的原因 |

不傳這些欄位 = default false(行為等同 PR-1)。

**副作用（PR-1 2026-05-07 起）**：

呼叫 `GdprService::anonymizeUser($user)`，**立即且不可逆**匿名化該帳號：

1. `users.email` → `deleted_{id}@removed.mimeet`
2. `users.phone` → `null`
3. `users.phone_hash` → `null`（釋出 unique 索引讓對方可重新註冊）
4. `users.nickname` → `已刪除用戶`
5. `users.status` → `deleted`
6. `users.deleted_at` → `now()`
7. 撤銷該 user 所有 Sanctum personal_access_tokens
8. 清空該 user 的 fcm_tokens
9. 該 user 的照片移入 `storage/quarantine/{date}/`
10. 寫入 `admin_operation_logs`：`action='delete_member'` / `resource_type='member'` / `request_summary` 含 `original_email_masked` / `original_phone_masked`

實作上整個 handler 包在 `DB::transaction` 內 + `User::lockForUpdate()->find($id)` 防併發 admin 同時刪同一 user。

**重要區分**：
- 「**停權**」（`PATCH /admin/members/{id}/actions` action=`suspend`）= 禁登入但保留資料
- 「**刪除**」（本 endpoint）= 匿名化釋出資料，可被重新註冊
- 想「禁止此 email/手機重新註冊」需 PR-2 黑名單功能（待開發）

**成功回應 (200)**：
```json
{ "success": true, "message": "會員已刪除" }
```

**錯誤**：
- `404 會員不存在`
- 不可逆，無回滾機制 — 後台 UI 須強制 admin 輸入 `DELETE` 確認

**觀察 / 監控**：
- 既有殭屍 user（v3.6 之前 soft-deleted 但未匿名化）由 artisan command `php artisan users:cleanup-zombies --apply --force` 一次性清理

---

### 4.3.0 編輯會員個人資料（super_admin only）

```
PATCH /api/v1/admin/members/{user_id}/profile
```

> 所需權限：**super_admin**（比一般 `members.edit` 高一級，因可修改 `birth_date`、`gender`）

**請求參數（全部選填，`sometimes|nullable`）：**
```json
{
  "nickname": "新暱稱",
  "birth_date": "2000-01-01",
  "avatar_url": "https://...",
  "gender": "male",
  "height": 175,
  "weight": 68,
  "location": "台北市",
  "occupation": "工程師",
  "education": "bachelor",
  "bio": "...",

  "style": "intellectual",
  "dating_budget": "moderate",
  "dating_frequency": "flexible",
  "dating_type": ["dining", "travel"],
  "relationship_goal": "long_term",
  "smoking": "never",
  "drinking": "social",
  "car_owner": true,
  "availability": ["weekend", "flexible"]
}
```

**業務規則：**
- 只有 super_admin 可呼叫
- 自動偵測實際有變更的欄位（array/boolean/日期的 before/after 比對），寫入 `admin_operation_logs.request_summary`
- 若無任何欄位變動，回 200 + `code: NO_CHANGES`

---

### 4.3.1 會員權限覆寫（Admin Override）（v1.2 新增）

```
PATCH /api/v1/admin/members/{user_id}/permissions
```

> 所需權限：`members.edit`（admin / super_admin）
> 稽核：自動寫入 `credit_score_histories` + `admin_operation_logs`

**請求參數：**

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `credit_score` | int | 否 | 目標誠信分數（0-100），系統自動計算 delta |
| `membership_level` | float | 否 | 目標會員等級（0 / 1 / 1.5 / 2 / 3） |
| `status` | string | 否 | 帳號狀態（`active` / `suspended`） |
| `reason` | string | 否 | 變更原因（記入稽核日誌） |

**請求範例：**
```json
{
  "credit_score": 85,
  "membership_level": 3,
  "status": "active",
  "reason": "開發測試：將測試帳號設為付費會員"
}
```

**成功回應 200：**
```json
{
  "success": true,
  "code": "MEMBER_PERMISSIONS_UPDATED",
  "message": "會員權限已更新。",
  "data": {
    "member": {
      "id": 123,
      "membership_level": 3,
      "credit_score": 85,
      "status": "active"
    },
    "changes": [
      "credit_score: 60 → 85",
      "membership_level: 1 → 3"
    ]
  }
}
```

**無變更回應 200：**
```json
{
  "success": true,
  "code": "NO_CHANGES",
  "message": "未偵測到變更。"
}
```

---

### 4.4 取得會員誠信分數紀錄

```
GET /api/v1/admin/members/{user_id}/credit-logs
```

**Query 參數：**

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `page` | int | 否 | 頁碼 |
| `per_page` | int | 否 | 每頁筆數，預設 20 |

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 5501,
      "change": -10,
      "before": 85,
      "after": 75,
      "type": "admin_penalty",
      "reason": "違規行為：發送不當訊息",
      "operator": { "id": 1, "name": "Super Admin" },
      "created_at": "2025-01-15T11:00:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 23, "last_page": 2 }
}
```

---

### 4.5 取得會員訂閱記錄

```
GET /api/v1/admin/members/{user_id}/subscriptions
```

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 201,
      "plan": "monthly",
      "plan_name": "月費方案",
      "price_paid": 399,
      "started_at": "2025-01-01T00:00:00Z",
      "expires_at": "2025-03-01T00:00:00Z",
      "status": "active",
      "payment_method": "ecpay",
      "payment_no": "ECPay20250101XXXXX"
    }
  ]
}
```

---

### 4.6 取得會員聊天記錄（管理查詢）

```
GET /api/v1/admin/members/{user_id}/chat-logs
```

> 所需權限：`chat.view`

**Query 參數：**

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `counterpart_id` | int | 否 | 指定對方 user_id，查特定兩人對話 |
| `keyword` | string | 否 | 關鍵字搜尋 |
| `page` | int | 否 | 頁碼 |

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "conversation_id": 3001,
      "counterpart": { "id": 1002, "nickname": "甜爹001" },
      "messages": [
        {
          "id": 50001,
          "sender_id": 1001,
          "sender_nickname": "甜心寶貝",
          "content": "您好",
          "type": "text",
          "sent_at": "2025-01-10T14:30:00Z"
        }
      ],
      "total_messages": 42,
      "last_message_at": "2025-01-15T10:00:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 5, "last_page": 1 }
}
```

**匯出單一對話（CSV）：**

```
GET /api/v1/admin/members/{user_id}/chat-logs/export?counterpart_id={id}&format=csv
```

---

### 4.7 審核進階驗證（女性照片）

```
GET /api/v1/admin/verifications/pending
```

> 所需權限：`members.edit`
> 對齊 `user_verifications` migration 真實欄位(2026-05-09 PR-Verify-Lock 對齊)。

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 301,
      "user_id": 1001,
      "random_code": "AB1C2D",
      "photo_url": "https://cdn.mimeet.tw/storage/photos/1001/xxx.jpg",
      "status": "pending_review",
      "expires_at": "2026-01-15T09:10:00Z",
      "reviewed_by": null,
      "reviewed_at": null,
      "reject_reason": null,
      "created_at": "2026-01-15T09:00:00Z",
      "user": { "id": 1001, "nickname": "甜心寶貝", "gender": "female", "avatar_url": "...", "membership_level": 1.0, "credit_score": 60 }
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 1, "last_page": 1 }
}
```

---

```
PATCH /api/v1/admin/verifications/{verification_id}
```

**請求參數：**
```json
{
  "result": "approved",
  "reject_reason": null
}
```

| `result` 值 | 說明 |
|------------|------|
| `approved` | 通過審核，用戶進階驗證完成，發送通知 |
| `rejected` | 未通過，需提供 `reject_reason`，用戶收到通知可重試 |

**狀態守衛(2026-05-09 PR-Verify-Lock 新增)：**
- 僅當紀錄 `status === 'pending_review'` 時可審核;其他狀態回 `VERIFICATION_ALREADY_REVIEWED` (422)
- 審核流程使用 `DB::transaction + lockForUpdate` 序列化並發 admin 操作,避免重複加分

**成功回應 200(top-level message,**非** `data.message`)：**
```json
{
  "success": true,
  "message": "驗證已核准，用戶已升級至 Lv1.5"
}
```

或拒絕時:

```json
{
  "success": true,
  "message": "驗證已拒絕"
}
```

**錯誤碼：**

| 錯誤碼 | HTTP | 觸發條件 |
|---|---|---|
| `VERIFICATION_ALREADY_REVIEWED` | 422 | 紀錄 status 已是 approved/rejected/expired/pending_code,不可再審核 |

---

## 4.8 註冊禁止名單(Registration Blacklists,PR-2 新增)

> RBAC:`blacklist.view` / `blacklist.create` / `blacklist.deactivate`(super_admin / admin 全給;cs 只給 view)
>
> 列表 response 對齊 DEV-004 §6.1 通用模板:`{ data: [], meta: { page, per_page, total, last_page } }`

### 4.8.1 取得列表

```http
GET /api/v1/admin/blacklists?type=email&status=active&q=spam&page=1&per_page=20
Authorization: Bearer {admin_token}
```

**Query**:
- `type`: `email | mobile`(optional)
- `status`: `active | inactive | expired | all`(default `all`)
- `source`: `manual | admin_delete`(optional)
- `q`: 字串(value_masked 前綴搜尋)
- `created_from` / `created_to`: ISO datetime
- `page` / `per_page`(default 20, max 100)

**Success 200**:
```json
{
  "data": [
    {
      "id": 42,
      "type": "email",
      "value_masked": "s***m@a.com",
      "reason": "詐騙集團",
      "source": "manual",
      "source_user_id": null,
      "is_active": true,
      "status": "active",
      "expires_at": null,
      "created_at": "2026-05-07T10:00:00Z",
      "created_by": 1,
      "created_by_name": "Chuck",
      "deactivated_at": null,
      "deactivated_by": null,
      "deactivated_by_name": null,
      "deactivation_reason": null
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 156, "last_page": 8 }
}
```

### 4.8.2 取得詳情

```http
GET /api/v1/admin/blacklists/{id}
```

回傳含 `source_user`(若 `source='admin_delete'` 且 `source_user_id` 存在)。

### 4.8.3 新增

```http
POST /api/v1/admin/blacklists
```

**Body**:
```json
{
  "type": "email",
  "value": "spam@example.com",
  "reason": "詐騙嫌疑",
  "expires_at": null
}
```

| 欄位 | 型別 | 必填 | 說明 |
|---|---|---|---|
| `type` | enum | ✅ | `email` 或 `mobile` |
| `value` | string | ✅ | raw value(後端做 normalize + hash);max 255 |
| `reason` | string\|null | 否 | max 500 |
| `expires_at` | datetime\|null | 否 | ISO8601,必須 after now;null = 永久 |

**Success 201**:回單筆 schema(同 4.8.1 element)。

**Errors**:
- `409 ALREADY_BLACKLISTED`:該 value 已在 active blacklist
- `422`:type 錯/value 太長/reason 太長/expires_at 非未來/手機格式無效
- `403`:權限不足(無 `blacklist.create`)

### 4.8.4 解除

```http
PATCH /api/v1/admin/blacklists/{id}/deactivate
```

**Body**:`{ "reason": "誤判,user 已聯絡客服澄清" }`(required, max 500)

**Success 200**:回更新後單筆。

**Errors**:
- `409 ALREADY_DEACTIVATED`:已是 inactive
- `404 NOT_FOUND`
- `403`:權限不足(無 `blacklist.deactivate`)

> ❌ **不提供** `DELETE /admin/blacklists/{id}`(hard delete) — 全部用 deactivate 保留審計線索。

---

## 5. 系統公告 API

> 所需權限：所有角色均可讀，`admin` 以上可寫

### 5.1 取得公告列表

```
GET /api/v1/admin/announcements
```

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "content": "系統將於 1/20 02:00-04:00 進行維護，期間暫停服務。",
      "is_active": true,
      "display_start_at": "2025-01-18T00:00:00Z",
      "display_end_at": "2025-01-20T04:00:00Z",
      "created_by": "Super Admin",
      "created_at": "2025-01-15T10:00:00Z",
      "updated_at": "2025-01-15T10:00:00Z"
    }
  ]
}
```

---

### 5.2 建立公告

```
POST /api/v1/admin/announcements
```

**請求參數：**
```json
{
  "content": "公告內容，最多 500 字",
  "is_active": true,
  "display_start_at": "2025-01-18T00:00:00Z",
  "display_end_at": "2025-01-20T04:00:00Z"
}
```

> `display_end_at` 可為 null（永久顯示直到手動關閉）

**成功回應 201：**
```json
{
  "success": true,
  "data": { "id": 2, "content": "...", "is_active": true }
}
```

---

### 5.3 更新公告

```
PATCH /api/v1/admin/announcements/{id}
```

**請求參數（部分更新）：**
```json
{
  "is_active": false
}
```

---

### 5.4 刪除公告

```
DELETE /api/v1/admin/announcements/{id}
```

> 軟刪除，不影響前台顯示（前台依 `is_active` 判斷）

---

## 6. 問題回報 / 檢舉 Ticket API

> **命名說明：** 資料庫表名為 `reports`，但在 API 路由和前端中統一以 **Ticket** 稱呼。
> `type` 欄位以 DB ENUM 為事實來源，共 8 個值（詳見 DEV-006 §3.7「reports.type 值對照」）：
> - 用戶對用戶的檢舉：`fake_photo` / `harassment` / `spam` / `scam` / `inappropriate` / `other`
> - 系統問題回報：`system_issue`
> - 停權申訴（reporter 即被檢舉者本人）：`appeal`
>
> 所有類型共用同一組 CRUD 端點（`/admin/tickets`、`/admin/tickets/{id}`），以 `type` 欄位區分。
> 前端後台頁面：`TicketsPage.tsx`。
>
> **取消訂閱申請不走 reports 表**：由 `POST /api/v1/subscriptions/cancel-request` 直接設 `subscriptions.auto_renew=false`（見 PRD §4.3.7）。早期文件曾出現的 `type=unsubscribe` / `type=report` / `type=anon_report` / `type=system` 皆已棄用。

> 所需權限：`reports.view`（查看）、`reports.process`（處理）

### 6.1 取得 Ticket 列表

```
GET /api/v1/admin/tickets
```

**Query 參數：**

| 參數 | 類型 | 說明 |
|------|------|------|
| `status` | string | `pending`（預設）/ `processing` / `resolved` / `all` |
| `type` | string | DB ENUM 8 值之一：`fake_photo` / `harassment` / `spam` / `scam` / `inappropriate` / `other` / `system_issue` / `appeal`（詳見 DEV-006 §3.7） |
| `keyword` | string | 搜尋案號（R 開頭）/ 暱稱 |
| `page` | int | 頁碼 |

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 401,
      "ticket_no": "R20250115001",
      "type": "harassment",
      "type_label": "騷擾檢舉",
      "status": "pending",
      "status_label": "待處理",
      "reporter": { "id": 1001, "nickname": "甜心寶貝" },
      "reported_user": { "id": 1002, "nickname": "甜爹001" },
      "title": "對方傳送不當內容",
      "submitted_at": "2025-01-15T11:00:00Z",
      "has_images": true,
      "admin_assignee": null
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 7, "last_page": 1 }
}
```

---

### 6.2 取得 Ticket 詳情

```
GET /api/v1/admin/tickets/{ticket_id}
```

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "id": 401,
    "ticket_no": "R20250115001",
    "type": "harassment",
    "status": "pending",
    "reporter": { "id": 1001, "nickname": "甜心寶貝", "credit_score": 85 },
    "reported_user": { "id": 1002, "nickname": "甜爹001", "credit_score": 60 },
    "title": "對方傳送不當內容",
    "description": "詳細說明...",
    "images": [
      "https://cdn.mimeet.tw/reports/..."
    ],
    "submitted_at": "2025-01-15T11:00:00Z",
    "followups": [
      {
        "id": 1,
        "from": "user",
        "content": "補充說明...",
        "created_at": "2025-01-16T09:00:00Z"
      }
    ],
    "admin_replies": [
      {
        "id": 1,
        "admin_name": "Super Admin",
        "content": "已收到您的回報，正在處理中。",
        "created_at": "2025-01-16T10:00:00Z"
      }
    ]
  }
}
```

> **D.3 解耦版（2026-05-02）**：當 `type = "appeal"` 時，回應額外包含 `appeal_info` 區塊（純讀取參考資訊，不影響任何狀態變更）：
```json
{
  "appeal_info": {
    "credit_score_history": [
      { "id": 1, "delta": -10, "score_before": 30, "score_after": 20, "type": "report_submit", "reason": "被他人檢舉（待審）", "created_at": "..." },
      { "id": 2, "delta": -10, "score_before": 20, "score_after": 10, "type": "report_submit", "reason": "被他人檢舉（待審）", "created_at": "..." }
    ],
    "received_reports": [
      { "id": 12, "type": "harassment", "status": "resolved", "description": "前 100 字描述...", "created_at": "..." }
    ],
    "images": [
      "https://api.mimeet.online/storage/appeals/3/xxx.jpg"
    ]
  }
}
```
> `credit_score_history` / `received_reports` 各最近 20 筆；`received_reports` 排除本筆 ticket 自身。**僅供 admin 審查參考，不影響此 ticket 處理流程。**

---

### 6.3 更新 Ticket 狀態 / 回覆（D.3 解耦版）

> ⚠️ **D.3 變更（2026-05-02）：** 廢除 `PATCH /admin/tickets/{ticket_id}` 的 `action`/`restore_score`/`credit_adjustments` 機制。所有 ticket 狀態變更統一走 **`PATCH /admin/tickets/{ticket_id}/status`**，**不再** 連動 user.status 或 credit_score。**解停 user / 補分數須由 admin 至「會員管理頁」獨立操作。**

```
PATCH /api/v1/admin/tickets/{ticket_id}/status
```

middleware：`auth:sanctum` + `admin.auth` + `admin.permission:reports.process`

**請求參數（平鋪 body，不再 wrap `data`）：**
```json
{
  "status": "resolved",
  "admin_reply": "經查證屬實，已處理。"
}
```

| 欄位 | 型別 | 必填 | 說明 |
|---|---|---|---|
| `status` | string | ✅ | `pending` / `investigating` / `resolved` / `dismissed` |
| `admin_reply` | string | 條件必填 | 當 `status=resolved` 或 `dismissed` 時必填，max 2000 字元 |

**成功回應 200：**
```json
{
  "success": true,
  "code": "TICKET_STATUS_UPDATED",
  "message": "案件狀態已更新",
  "data": {
    "ticket": { "id": 401, "status": "resolved", "resolved_at": "2026-05-02T01:13:14Z" }
  }
}
```

**通知行為（自動觸發）：**
- 當 `status` 從非終態（pending/investigating）變為終態（resolved/dismissed）時，自動依「處理當下」`reporter.status` 發送通知：
  - `reporter.status` ∈ {`suspended`, `auto_suspended`} → 寄 email（`TicketProcessedMail`，走 queue）
  - 其他 → 站內訊息（`Notification` type=`ticket_replied`）
- 重複設定同樣終態（resolved → resolved）**不重複發送**通知

**廢棄端點與欄位（保留兼容但不再推薦使用）：**
- `PATCH /admin/tickets/{ticket_id}`（`updateTicket`）— 仍存在但不觸發新版通知；前端應改用 `/status`
- `action: approve_appeal | reject_appeal` — 已廢除
- `restore_score` 欄位 — 已廢除（補分由「會員管理 → 調整誠信分數」獨立操作）
- `credit_adjustments` 欄位 — 已廢除

---

## 7. 聊天記錄查詢 API

> **實作狀態（Sprint 7 S7-09）**：
> - ✅ `GET /api/v1/admin/chat-logs/search`
> - ✅ `GET /api/v1/admin/chat-logs/conversations`
> - ✅ `GET /api/v1/admin/chat-logs/export`
> - ✅ `GET /api/v1/admin/members/{id}/chat-logs`
>
> **權限矩陣（確認版）**：
> | 角色 | chat.view 權限 | 可使用聊天記錄查詢 |
> |------|:--------------:|:------------------:|
> | super_admin | ✅ | ✅ |
> | admin | ✅ | ✅ |
> | cs | ❌ | ❌（403） |
>
> **隱私保護**：已收回的訊息（is_recalled=true）不顯示內容，僅顯示佔位符「[已收回]」。
> 所有查詢動作自動寫入 admin_operation_logs。

> 所需權限：`chat.view`（super_admin 與 admin 專屬，cs 不可查）

### 7.1 全站關鍵字搜尋

```
GET /api/v1/admin/chat-logs/search
```

**Query 參數：**

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `keyword` | string | 是 | 搜尋關鍵字（最少 2 字） |
| `page` | int | 否 | 頁碼 |

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "message_id": 50001,
      "conversation_id": 3001,
      "sender": { "id": 1001, "nickname": "甜心寶貝" },
      "receiver": { "id": 1002, "nickname": "甜爹001" },
      "content": "...含關鍵字的訊息...",
      "sent_at": "2025-01-10T14:30:00Z"
    }
  ],
  "meta": { "total": 12, "page": 1, "last_page": 1 }
}
```

---

### 7.2 查詢兩用戶間對話

```
GET /api/v1/admin/chat-logs/conversations
```

**Query 參數：**

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `user_a` | int | 是 | 用戶 A 的 user_id |
| `user_b` | int | 是 | 用戶 B 的 user_id |
| `page` | int | 否 | 頁碼，每頁 50 則訊息 |

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "conversation_id": 3001,
    "user_a": { "id": 1001, "nickname": "甜心寶貝" },
    "user_b": { "id": 1002, "nickname": "甜爹001" },
    "messages": [
      {
        "id": 50001,
        "sender_id": 1001,
        "content": "您好",
        "type": "text",
        "sent_at": "2025-01-10T14:30:00Z",
        "is_read": true
      }
    ]
  },
  "meta": { "total": 42, "page": 1, "last_page": 1 }
}
```

---

### 7.3 匯出對話（CSV）

```
GET /api/v1/admin/chat-logs/export?user_a={id}&user_b={id}&format=csv
```

---

## 8. 支付記錄 API

> 所需權限：`payments.view`

### 8.1 取得支付記錄列表

```
GET /api/v1/admin/payments
```

**Query 參數：**

| 參數 | 類型 | 說明 |
|------|------|------|
| `status` | string | `success` / `failed` / `pending` / `refunded` |
| `method` | string | `ecpay` / `stripe` |
| `user_id` | int | 指定用戶 |
| `start_date` | string | YYYY-MM-DD |
| `end_date` | string | YYYY-MM-DD |
| `page` | int | 頁碼 |

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 201,
      "payment_no": "ECPay20250101XXXXX",
      "user": { "id": 1001, "nickname": "甜心寶貝" },
      "plan": "monthly",
      "plan_name": "月費方案",
      "amount": 399,
      "currency": "TWD",
      "method": "ecpay",
      "status": "success",
      "paid_at": "2025-01-01T10:05:00Z"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 450,
    "last_page": 23,
    "total_revenue_twd": 179550
  }
}
```

---

### 8.2 匯出支付記錄

```
GET /api/v1/admin/payments/export?start_date=2025-01-01&end_date=2025-01-31&format=csv
```

---

## 9. SEO 管理 API

> 所需權限：`seo.manage`
>
> **實作狀態（2026-04-19）：**
> - §9.4、§9.5（SEO Meta Tag 管理 / A17）**已實作**，路由 `GET/PATCH /api/v1/admin/seo/meta[/{id}]` 於 `admin.auth + admin.log` group 內。
> - §9.1、§9.2、§9.3（廣告跳轉連結 / A18）**保留 Phase 2**。SeoController 已保留方法骨架註解，尚未接 DB、未註冊路由，前端 tab 亦已隱藏。

### 9.1 取得廣告跳轉連結列表

```
GET /api/v1/admin/seo/links
```

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "slug": "fb-ad-jan-2025",
      "full_url": "https://mimeet.tw/go/fb-ad-jan-2025",
      "destination": "https://mimeet.tw/register",
      "title": "Facebook 廣告 1月份",
      "count_mode": "click",
      "click_count": 1234,
      "register_count": 89,
      "conversion_rate": 7.2,
      "is_active": true,
      "created_at": "2025-01-01T00:00:00Z"
    }
  ]
}
```

---

### 9.2 建立廣告跳轉連結

```
POST /api/v1/admin/seo/links
```

**請求參數：**
```json
{
  "slug": "ig-ad-feb-2025",
  "destination": "https://mimeet.tw/register",
  "title": "Instagram 廣告 2月份",
  "count_mode": "register"
}
```

> `count_mode`：`click`（點擊計數）/ `register`（僅計算完成註冊者）

**成功回應 201：**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "full_url": "https://mimeet.tw/go/ig-ad-feb-2025"
  }
}
```

---

### 9.3 取得連結點擊統計

```
GET /api/v1/admin/seo/links/{id}/stats
```

**Query 參數：**

| 參數 | 類型 | 說明 |
|------|------|------|
| `granularity` | string | `daily`（預設）/ `hourly` |
| `start_date` | string | YYYY-MM-DD |
| `end_date` | string | YYYY-MM-DD |

---

### 9.4 取得頁面 Meta 設定

```
GET /api/v1/admin/seo/meta
```

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "route": "/",
      "title": "MiMeet - 台灣高端交友平台",
      "description": "誠信讓相遇便捷可靠",
      "og_title": "MiMeet 交友平台",
      "og_description": "安全、真實、高品質的交友體驗",
      "og_image_url": "https://cdn.mimeet.tw/og/home.jpg",
      "updated_at": "2025-01-01T00:00:00Z"
    }
  ]
}
```

---

### 9.5 更新頁面 Meta

```
PATCH /api/v1/admin/seo/meta/{id}
```

**請求參數（部分更新）：**
```json
{
  "title": "MiMeet - 全台最可信賴的交友平台",
  "description": "透過誠信分數系統，找到真實可靠的對象。",
  "og_image_url": "https://cdn.mimeet.tw/og/home-v2.jpg"
}
```

---

### 9.6 追蹤碼管理（2026-04-20 新增）

> 所需權限：`super_admin`；路由位於 `settings/tracking`

#### 9.6.1 取得追蹤碼設定

```
GET /api/v1/admin/settings/tracking
Authorization: Bearer {admin_token}
```

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "ga_measurement_id": "G-XXXXXXXXXX",
    "fb_pixel_id": "",
    "gtm_id": ""
  }
}
```

#### 9.6.2 更新追蹤碼設定

```
PATCH /api/v1/admin/settings/tracking
Authorization: Bearer {admin_token}
```

**請求參數（可部分更新）：**
```json
{
  "ga_measurement_id": "G-ABC12DEF34",
  "fb_pixel_id": "",
  "gtm_id": ""
}
```

| 欄位 | 格式正則 | 空字串代表 |
|------|----------|-----------|
| `ga_measurement_id` | `^(G-[A-Z0-9]{4,20})?$` | 停用 GA4 |
| `fb_pixel_id` | `^\d{10,20}?$\|^$` | 停用 FB Pixel |
| `gtm_id` | `^(GTM-[A-Z0-9]{4,20})?$` | 停用 GTM |

**行為：** 寫入 `system_settings` 的 `tracking_*` key，並清除公開端點 `/api/v1/site-config` 的 60 秒 Cache。下一個訪客載入時即取得新值並動態插入 `<script>`。

**成功回應 200：**
```json
{
  "success": true,
  "code": "TRACKING_UPDATED",
  "message": "追蹤碼已更新（最多 60 秒內生效）。"
}
```

---

## 10. 系統設定 API

### 10.1 取得訂閱方案設定

```
GET /api/v1/admin/settings/subscription-plans
```

> 所需權限：`settings.pricing`

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "plans": [
      {
        "id": 1,
        "type": "monthly",
        "name": "月費方案",
        "price": 399,
        "duration_days": 30,
        "is_active": true
      },
      {
        "id": 2,
        "type": "quarterly",
        "name": "季費方案",
        "price": 1077,
        "original_price": 1197,
        "discount_pct": 10,
        "duration_days": 90,
        "is_active": true
      },
      {
        "id": 3,
        "type": "yearly",
        "name": "年費方案",
        "price": 3832,
        "original_price": 4788,
        "discount_pct": 20,
        "duration_days": 365,
        "is_active": true
      }
    ],
    "trial": {
      "price": 199,
      "duration_days": 30,
      "is_active": true,
      "purchase_limit": 1
    }
  }
}
```

---

### 10.2 更新訂閱方案

```
PATCH /api/v1/admin/settings/subscription-plans/{plan_id}
```

> 所需權限：`settings.pricing`

**請求參數（部分更新）：**
```json
{
  "price": 449,
  "is_active": true
}
```

> **注意：** 修改 `price` 僅影響新訂閱，不影響現有訂閱。

---

### 10.3 更新體驗價設定

```
PATCH /api/v1/admin/settings/trial-plan
```

> 所需權限：`settings.pricing`

**請求參數：**
```json
{
  "price": 199,
  "duration_days": 30,
  "is_active": true
}
```

---

### 10.4 取得角色清單

```
GET /api/v1/admin/settings/roles
```

> 所需權限：`settings.roles`（`super_admin` 專屬）

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "roles": [
      { "id": 1, "name": "super_admin", "label": "超級管理員", "admin_count": 1 },
      { "id": 2, "name": "admin",       "label": "一般管理員", "admin_count": 3 },
      { "id": 3, "name": "cs",          "label": "客服人員",   "admin_count": 2 }
    ],
    "permissions": [
      { "key": "members.view",    "name": "查看會員",     "module": "members" },
      { "key": "members.edit",    "name": "編輯會員",     "module": "members" },
      { "key": "members.delete",  "name": "刪除會員",     "module": "members" },
      { "key": "reports.view",    "name": "查看回報",     "module": "reports" },
      { "key": "reports.process", "name": "處理回報",     "module": "reports" },
      { "key": "chat.view",       "name": "查看聊天記錄", "module": "chat" },
      { "key": "payments.view",   "name": "查看支付記錄", "module": "payments" },
      { "key": "seo.manage",      "name": "SEO管理",      "module": "seo" },
      { "key": "settings.pricing","name": "定價設定",     "module": "settings" },
      { "key": "settings.roles",  "name": "角色管理",     "module": "settings" }
    ],
    "role_permissions": {
      "super_admin": ["*"],
      "admin": ["members.view","members.edit","reports.view","reports.process","chat.view","payments.view","seo.manage","settings.pricing"],
      "cs": ["members.view","reports.view","reports.process"]
    }
  }
}
```

---

### 10.5 指派管理員角色

```
PATCH /api/v1/admin/settings/admins/{admin_id}/role
```

> 所需權限：`settings.roles`（`super_admin` 專屬）

**請求參數：**
```json
{
  "role": "admin"
}
```

---

### 10.6 取得管理員列表

```
GET /api/v1/admin/settings/admins
```

> 所需權限：`settings.roles`（`super_admin` 專屬）

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Super Admin",
      "email": "chuck@lunarwind.org",
      "role": "super_admin",
      "last_login_at": "2025-01-15T10:30:00Z",
      "created_at": "2025-01-01T00:00:00Z"
    }
  ]
}
```

---

### 10.7 建立管理員帳號

```
POST /api/v1/admin/settings/admins
```

> 所需權限：`settings.roles`（`super_admin` 專屬）

**請求參數：**
```json
{
  "name": "客服小明",
  "email": "cs-ming@mimeet.tw",
  "password": "初始密碼（8字以上）",
  "role": "cs"
}
```

---

### 10.8 取得系統控制中心設定（分類）

```
GET /api/v1/admin/settings/system-control
```

> 所需權限：`super_admin`（硬編碼，不走 RBAC 權限節點）
> 此端點一次回傳所有系統控制設定，依類別分組

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "app_mode": {
      "mode": "testing",
      "maintenance_mode": false,
      "version": "1.0.0",
      "mode_switched_at": "2026-04-08T10:00:00Z",
      "mode_switched_by": "Super Admin"
    },
    "mail": {
      "host": "smtp.sendgrid.net",
      "port": 587,
      "encryption": "tls",
      "username": "apikey",
      "password": "****",
      "from_address": "noreply@mimeet.tw",
      "from_name": "MiMeet 平台",
      "enabled": false
    },
    "sms": {
      "provider": "disabled",
      "enabled": false,
      "providers_available": ["mitake", "twilio", "every8d", "disabled"]
    },
    "database": {
      "host": "mimeet_mysql",
      "port": 3306,
      "database": "mimeet",
      "username": "mimeet_user",
      "password": "****",
      "connection_status": "connected"
    }
  }
}
```

> **安全規則：** password / auth_token / api_key 等敏感欄位永遠回傳 `****`，不回傳明文。前端只允許「覆寫」，不允許「讀取」密碼。

---

### 10.9 更新系統運作模式

```
PATCH /api/v1/admin/settings/app-mode
```

> 所需權限：`super_admin`
> 切換模式時需再次驗證管理員密碼（二次確認）

**請求參數：**
```json
{
  "mode": "production",
  "confirm_password": "管理員當前密碼"
}
```

`mode` 可選值：`testing` | `production`

**行為說明：**
- `testing` → Email/SMS 只寫 Log，ECPay 用 Sandbox
- `production` → Email/SMS 實際發送，ECPay 用正式環境
- 切換後立即寫入 `system_settings`（`app.mode` 鍵），服務即時生效
- 自動記錄到 `admin_operation_logs`（不記錄密碼）

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "mode": "production",
    "message": "系統已切換為正式模式，Email 與 SMS 服務已啟用"
  }
}
```

**密碼錯誤 422：**
```json
{ "success": false, "error": { "code": "PASSWORD_INCORRECT", "message": "密碼驗證失敗" } }
```

---

### 10.10 更新 Email（SMTP）設定

```
PATCH /api/v1/admin/settings/mail
```

> 所需權限：`super_admin`

**請求參數（部分更新，只傳要修改的欄位）：**
```json
{
  "host": "smtp.sendgrid.net",
  "port": 587,
  "encryption": "tls",
  "username": "apikey",
  "password": "SG.新的API_Key（選填，不傳則保留現有密碼）",
  "from_address": "noreply@mimeet.tw",
  "from_name": "MiMeet 平台"
}
```

**行為：** 密碼 AES-256 加密後存 `.env`；非敏感欄位存 `system_settings`。寫入後 `config()->set()` 即時生效。

**成功回應 200：**
```json
{ "success": true, "data": { "message": "Email 設定已更新" } }
```

---

### 10.10.1 發送 Email 測試信

```
POST /api/v1/admin/settings/mail/test
```

> 所需權限：`super_admin`

**請求參數：**
```json
{ "test_email": "admin@example.com" }
```

**成功 200：**
```json
{ "success": true, "data": { "message": "測試信已發送至 admin@example.com" } }
```

**失敗 422：**
```json
{ "success": false, "error": { "code": "MAIL_SEND_FAILED", "message": "SMTP 連線失敗：認證錯誤" } }
```

---

### 10.11 更新 SMS 服務設定

```
PATCH /api/v1/admin/settings/sms
```

> 所需權限：`super_admin`

**請求參數：**
```json
{ "provider": "mitake", "mitake": { "username": "your_account", "password": "選填" } }
```

或停用：`{ "provider": "disabled" }`

**行為：** provider 存 `system_settings`；密碼 AES-256 加密後存 `.env`。

**成功 200：**
```json
{ "success": true, "data": { "provider": "mitake", "message": "SMS 服務已切換為三竹簡訊" } }
```

---

### 10.11.1 發送 SMS 測試簡訊

```
POST /api/v1/admin/settings/sms/test
```

> 所需權限：`super_admin`

**請求參數：**
```json
{ "phone": "0912345678" }
```

**成功 200：**
```json
{ "success": true, "data": { "message": "測試簡訊已發送至 0912345678" } }
```

---

### 10.12.1 測試資料庫連線

```
POST /api/v1/admin/settings/database/test
```

> 所需權限：`super_admin`

**請求參數：**
```json
{ "host": "mimeet_mysql", "port": 3306, "database": "mimeet", "username": "mimeet_user", "password": "待測試的密碼" }
```

**成功 200：**
```json
{ "success": true, "data": { "status": "connected", "response_ms": 24, "server_version": "8.0.32" } }
```

**失敗 422：**
```json
{ "success": false, "error": { "code": "DB_CONNECTION_FAILED", "message": "無法連線：Access denied" } }
```

---

### 10.A 會員等級功能設定 API（Sprint 11 新增）

> 所需權限：`super_admin`

#### 取得會員等級功能權限

```
GET /api/v1/admin/settings/member-level-permissions
```

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "permissions": [
      {
        "level": "registered",
        "feature_key": "send_message",
        "enabled": false,
        "value": null
      },
      {
        "level": "verified",
        "feature_key": "send_message",
        "enabled": true,
        "value": 10
      }
    ]
  }
}
```

---

#### 批次更新會員等級功能權限

```
PATCH /api/v1/admin/settings/member-level-permissions
```

**請求參數：**
```json
[
  { "level": "registered", "feature_key": "send_message", "enabled": true, "value": 5 },
  { "level": "verified", "feature_key": "view_profile", "enabled": true }
]
```

> 每個項目至少需包含 `level` 與 `feature_key`，`enabled` 與 `value` 為選填（部分更新）。

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "updated": 2
  }
}
```

---

### 10.A.1 權限矩陣 JSON 簡化介面（v1.2 新增）

> 所需權限：`super_admin`

```
GET /api/v1/admin/settings/permission-matrix
```

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "matrix": {
      "browse": [0, 1, 1.5, 2, 3],
      "basic_search": [0, 1, 1.5, 2, 3],
      "advanced_search": [1, 1.5, 2, 3],
      "view_full_profile": [1.5, 2, 3],
      "read_receipt": [3],
      "vip_invisible": [3]
    }
  }
}
```

> 每個 key 為功能名稱，value 為允許使用該功能的會員等級陣列。

```
PATCH /api/v1/admin/settings/permission-matrix
```

**請求參數：**
```json
{
  "matrix": {
    "vip_invisible": [2, 3],
    "read_receipt": [2, 3]
  }
}
```

> 更新後自動同步到 `member_level_permissions` 資料表 + `system_settings` JSON。

---

### 10.B 女性驗證審核 API（Sprint 11 新增）

> 規格主檔以 §4.7 為準。本段保留作為 §10 章節的索引,詳細欄位、錯誤碼、狀態守衛、回應格式請參考 §4.7。

#### 取得待審核驗證列表

```
GET /api/v1/admin/verifications/pending
```

詳細欄位 / response shape 見 §4.7。

#### 審核驗證申請

```
PATCH /api/v1/admin/verifications/{id}
```

**狀態守衛(2026-05-09 PR-Verify-Lock):**
- 僅當紀錄 `status === 'pending_review'` 時可審核;其他狀態回 `VERIFICATION_ALREADY_REVIEWED` (422)
- 審核流程使用 `DB::transaction + lockForUpdate` 序列化並發 admin 操作

**成功回應 200(top-level message,**非** `data.message`):**
```json
{
  "success": true,
  "message": "驗證已核准，用戶已升級至 Lv1.5"
}
```

詳細請求/錯誤碼見 §4.7。

---

### 10.C 廣播訊息 API（Sprint 11 新增）

> 詳見 §13 廣播管理 API。

---

### 10.D 操作日誌 API（Sprint 11 新增）

> 所需權限：`admin` 以上均可查詢

#### 取得操作日誌

```
GET /api/v1/admin/logs
```

**Query 參數：**

| 參數 | 類型 | 說明 |
|------|------|------|
| `action_type` | string | 篩選操作類型 |
| `admin_id` | int | 指定操作者（僅 super_admin 可用） |
| `resource_type` | string | 篩選操作對象 |
| `date_from` | string | 起始日期 YYYY-MM-DD |
| `date_to` | string | 結束日期 YYYY-MM-DD |
| `show_ip` | bool | `true` = 顯示完整 IP（僅 `super_admin` 有效）；預設 `false` |
| `page` | int | 頁碼 |

> **IP 顯示規則：** `show_ip=true` 僅限 `super_admin` 使用，其他角色傳此參數無效（IP 欄位回傳 `null`）。

---

### 10.E 管理員帳號 CRUD（Sprint 11 新增）

> 所需權限：`settings.roles`（`super_admin` 專屬）

#### 取得管理員列表

```
GET /api/v1/admin/settings/admins
```

回傳所有管理員帳號（含角色、最後登入時間等）。

---

#### 建立管理員帳號

```
POST /api/v1/admin/settings/admins
```

**請求參數：**
```json
{
  "name": "新管理員",
  "email": "new-admin@mimeet.tw",
  "password": "初始密碼（8字以上）",
  "role": "cs"
}
```

---

#### 變更管理員角色

```
PATCH /api/v1/admin/settings/admins/{id}/role
```

**請求參數：**
```json
{
  "role": "admin"
}
```

---

#### 刪除管理員帳號

```
DELETE /api/v1/admin/settings/admins/{id}
```

**限制：**
- `super_admin` 角色不可被刪除
- 不可刪除自己的帳號

**成功回應 200：**
```json
{ "success": true, "message": "管理員已刪除" }
```

**錯誤回應：**
- `403`：目標為超級管理員，或嘗試刪除自己
- `404`：管理員不存在

---

#### 重設管理員密碼

```
POST /api/v1/admin/settings/admins/{id}/reset-password
```

**請求參數：**
```json
{
  "password": "新密碼（至少8字元）",
  "password_confirmation": "確認新密碼"
}
```

**成功回應 200：**
```json
{ "success": true, "message": "密碼已重設" }
```

---

#### 取得角色與權限矩陣

```
GET /api/v1/admin/settings/roles
```

回傳角色清單及各角色對應的權限矩陣。

---

## 11. 操作日誌 API

> 所需權限：所有管理員角色均可查自己的日誌；`super_admin` 可查全部

### 11.1 取得操作日誌

```
GET /api/v1/admin/logs
```

**Query 參數：**

| 參數 | 類型 | 說明 |
|------|------|------|
| `admin_id` | int | 指定操作者（僅 super_admin 可用） |
| `action_type` | string | 篩選操作類型（adjust_credit / suspend / etc.）|
| `resource_type` | string | 篩選操作對象（user / ticket / etc.）|
| `start_date` | string | YYYY-MM-DD |
| `end_date` | string | YYYY-MM-DD |
| `show_ip` | bool | `true` = 顯示完整 IP；預設 `false`（IP 欄位回傳 `null`） |
| `page` | int | 頁碼 |

> **IP 顯示規則：** 後台介面預設**不顯示 IP 欄位**，管理員可手動開啟「顯示 IP」Toggle 後重新查詢。`show_ip=true` 僅限 `super_admin` 使用。

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 9001,
      "admin": { "id": 1, "name": "Super Admin", "role": "super_admin" },
      "action_type": "adjust_credit",
      "resource_type": "user",
      "resource_id": 1001,
      "description": "調整誠信分數 -10 分：違規行為",
      "ip_address": "123.xxx.xxx.xxx",
      "user_agent": "Mozilla/5.0...",
      "created_at": "2025-01-15T11:00:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 30, "total": 256, "last_page": 9 }
}
```

---


## 12. 匿名聊天室管理 API（⏸️ Phase 4 — 營運穩定後實作）

> ⏸️ **實作狀態：尚未實作**
> API 規格已設計完成，待 Phase 4 時實作。目前呼叫此 API 會回傳 404。

> 所需權限：`admin` 以上

### 12.1 取得匿名聊天室設定

```
GET /api/v1/admin/anon-chat/settings
```

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "message_retention_days": 180,
    "suggested_channel_count": 25,
    "active_user_count_30d": 500,
    "current_channel_count": 10
  }
}
```

---

### 12.2 更新匿名聊天室設定

```
PATCH /api/v1/admin/anon-chat/settings
```

> 所需權限：`super_admin`；值必須為 30 的倍數或 0（永久保留）

**請求參數：**
```json
{ "message_retention_days": 90 }
```

---

### 12.3 取得頻道列表

```
GET /api/v1/admin/anon-chat/channels
```

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "甜蜜相遇",
      "is_active": true,
      "male_require_paid": true,
      "female_require_paid": false,
      "today_message_count": 142,
      "member_count_snapshot": 500
    }
  ]
}
```

---

### 12.4 建立 / 更新頻道

```
POST  /api/v1/admin/anon-chat/channels
PATCH /api/v1/admin/anon-chat/channels/{id}
```

**請求參數：**
```json
{
  "name": "夢幻約會",
  "description": "分享你的約會計畫",
  "is_active": true,
  "sort_order": 2,
  "male_require_paid": true,
  "female_require_paid": false
}
```

---

### 12.5 查詢頻道訊息（供查證）

```
GET /api/v1/admin/anon-chat/messages
```

**Query 參數：** `channel_id`、`keyword`、`page`

---

### 12.6 刪除匿名聊天訊息

```
DELETE /api/v1/admin/anon-chat/messages/{id}
```

**請求參數：**
```json
{ "reason": "內容違規" }
```

---

## 13. 廣播管理 API（SPEC-CONFIRM-001 B.5 新增）

> 所需權限：`admin` 以上

### 13.1 取得廣播任務列表

```
GET /api/v1/admin/broadcasts
```

**Query 參數：** `status`（`draft` / `sending` / `completed` / `failed`）、`page`、`per_page`

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "broadcasts": [
      {
        "id": 1,
        "title": "農曆新年活動通知",
        "content": "MiMeet 新年快樂！",
        "delivery_mode": "both",
        "filters": { "gender": "all" },
        "status": "completed",
        "target_count": 1234,
        "sent_count": 1231,
        "created_by": 1,
        "completed_at": "2026-01-15T10:05:00Z",
        "created_at": "2026-01-15T10:00:00Z"
      }
    ]
  },
  "meta": { "page": 1, "per_page": 20, "total": 3, "last_page": 1 }
}
```

**欄位說明：**

| 欄位 | 說明 |
|------|------|
| `delivery_mode` | `notification`（站內通知）/ `dm`（私訊）/ `both`（通知 + 私訊） |
| `filters` | JSON 篩選條件，包含 `gender`（`all`/`male`/`female`）、`level_min`、`level_max`、`credit_min`、`credit_max` |
| `status` | `draft`（草稿）/ `sending`（發送中）/ `completed`（已完成）/ `failed`（失敗） |
| `target_count` | 符合篩選條件的目標人數（建立時計算） |
| `sent_count` | 實際已發送數 |

> **注意：** 目標性別不是頂層欄位 `target_gender`，而是 `filters.gender`。

---

### 13.2 建立廣播任務

```
POST /api/v1/admin/broadcasts
Content-Type: application/json
```

**請求參數：**

| 欄位 | 必填 | 說明 |
|------|------|------|
| `title` | 是 | 標題（max 200） |
| `content` | 是 | 內容 |
| `delivery_mode` | 是 | `notification` / `dm` / `both` |
| `filters` | 否 | 篩選條件 JSON |
| `filters.gender` | 否 | `all`（預設）/ `male` / `female` |
| `filters.level_min` | 否 | 最低會員等級（0-3） |
| `filters.level_max` | 否 | 最高會員等級（0-3） |
| `filters.credit_min` | 否 | 最低誠信分數（0-100） |
| `filters.credit_max` | 否 | 最高誠信分數（0-100） |

```json
{
  "title": "農曆新年活動通知",
  "content": "MiMeet 新年快樂！即日起至 2/14，新訂閱享 85 折優惠。",
  "delivery_mode": "both",
  "filters": {
    "gender": "all",
    "credit_min": 31
  }
}
```

> 建立後狀態為 `draft`，需呼叫 send 端點才會實際發送。

**成功回應 201：**
```json
{
  "success": true,
  "data": {
    "broadcast": {
      "id": 2,
      "title": "農曆新年活動通知",
      "delivery_mode": "both",
      "filters": { "gender": "all", "credit_min": 31 },
      "status": "draft",
      "target_count": 982,
      "created_by": 1,
      "created_at": "2026-01-15T10:00:00Z"
    }
  }
}
```

---

### 13.3 取得廣播詳情

```
GET /api/v1/admin/broadcasts/{id}
```

---

### 13.4 發送廣播

```
POST /api/v1/admin/broadcasts/{id}/send
```

> 只有 `status=draft` 的廣播可執行。同步執行完成後回傳結果。

**成功回應 200：**
```json
{
  "success": true,
  "message": "廣播已發送完成",
  "data": {
    "broadcast": {
      "id": 2,
      "status": "completed",
      "sent_count": 120,
      "completed_at": "2026-04-18T03:49:31Z"
    }
  }
}
```

---

### 13.5 廣播接收位置（前台）

| delivery_mode | 前台位置 | 說明 |
|--------------|---------|------|
| `notification` | `/app/notifications` | 站內通知頁（type=`system`） |
| `dm` | `/app/messages` | 訊息頁（由 uid=1 官方帳號發送） |
| `both` | 兩個地方都有 | 通知 + 私訊同時送出 |

> 廣播建立後 status 為 `draft`，需點擊「發送」按鈕後
> 同步執行，status 變為 `completed`（或 `failed`）。
> uid=1（系統帳號）不會收到廣播。

---

## 14. 系統全域設定 API（新增）

> 所需權限：`super_admin`

### 14.1 取得全域設定

```
GET /api/v1/admin/settings/system
```

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    { "key": "subscription.auto_renew_default",   "value": "0",            "value_type": "boolean", "description": "新訂閱預設是否開啟自動續訂（0=關閉，1=開啟）" },
    { "key": "anon_chat.message_retention_days",  "value": "180",          "value_type": "integer", "description": "匿名聊天訊息保留天數（30的倍數；0=永久）" },
    { "key": "broadcast.delivery_mode",           "value": "notification", "value_type": "string",  "description": "廣播訊息送達方式：notification/dm/both" },
    { "key": "date_verify.score_with_gps",        "value": "5",            "value_type": "integer", "description": "QR約會驗證 GPS 通過得分" },
    { "key": "date_verify.score_without_gps",     "value": "2",            "value_type": "integer", "description": "QR約會驗證 GPS 未通過得分" },
    { "key": "visitor.click_requires_paid",       "value": "1",            "value_type": "boolean", "description": "點擊訪客進入對方資料頁需付費（女性不受限）" }
  ]
}
```

---

### 14.2 更新全域設定

```
PATCH /api/v1/admin/settings/system/{key}
```

**請求參數：**
```json
{ "value": "1" }
```

**驗證規則：**
- `subscription.auto_renew_default`：`0` 或 `1`
- `anon_chat.message_retention_days`：0 或 30 的倍數
- `broadcast.delivery_mode`：`notification` / `dm` / `both`
- `date_verify.score_with_gps`：正整數，必須 ≥ `score_without_gps`
- `visitor.click_requires_paid`：`0` 或 `1`

---

### 14.3 取得 app.mode 目前狀態

```
GET /api/v1/admin/settings/system/app-mode
```

> 所需權限：`super_admin`

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "mode": "testing",
    "mail_enabled": false,
    "sms_enabled": false,
    "ecpay_sandbox": true,
    "description": "測試模式：Email/SMS 只寫 Log，綠界使用 Sandbox"
  }
}
```

---

## 15. 後台 API 速查表（最終版）

| 功能模組 | 端點 | Method | 所需權限 |
|---------|------|--------|---------|
| 後台登入 | `/auth/login` | POST | Public |
| 取得自身資訊 | `/auth/me` | GET | 已登入 |
| 登出 | `/auth/logout` | POST | 已登入 |
| 摘要統計 | `/stats/summary` | GET | 已登入 |
| 圖表資料 | `/stats/chart` | GET | 已登入 |
| 匯出統計 | `/stats/export` | GET | 已登入 |
| 會員列表 | `/members` | GET | members.view |
| 會員詳情 | `/members/{id}` | GET | members.view |
| 會員操作 | `/members/{id}/actions` | PATCH | members.edit |
| 會員權限覆寫 | `/members/{id}/permissions` | PATCH | members.edit |
| 分數紀錄 | `/members/{id}/credit-logs` | GET | members.view |
| 訂閱記錄 | `/members/{id}/subscriptions` | GET | members.view |
| 聊天記錄 | `/members/{id}/chat-logs` | GET | chat.view |
| 對話匯出 | `/members/{id}/chat-logs/export` | GET | chat.view |
| 驗證待審 | `/verifications/pending` | GET | members.edit |
| 審核驗證 | `/verifications/{id}` | PATCH | members.edit |
| 公告列表 | `/announcements` | GET | 已登入 |
| 建立公告 | `/announcements` | POST | admin+ |
| 更新公告 | `/announcements/{id}` | PATCH | admin+ |
| 刪除公告 | `/announcements/{id}` | DELETE | admin+ |
| Ticket列表 | `/tickets` | GET | reports.view |
| Ticket詳情 | `/tickets/{id}` | GET | reports.view |
| 處理Ticket | `/tickets/{id}` | PATCH | reports.process |
| 申訴列表 | `/tickets?type=appeal` | GET | reports.view |
| 處理申訴 | `/tickets/{id}` | PATCH | reports.process |
| 聊天搜尋 | `/chat-logs/search` | GET | chat.view |
| 兩人對話 | `/chat-logs/conversations` | GET | chat.view |
| 全站對話匯出 | `/chat-logs/export` | GET | chat.view |
| 支付列表 | `/payments` | GET | payments.view |
| 支付匯出 | `/payments/export` | GET | payments.view |
| SEO連結 | `/seo/links` | GET/POST | seo.manage |
| SEO連結統計 | `/seo/links/{id}/stats` | GET | seo.manage |
| Meta設定 | `/seo/meta` | GET | seo.manage |
| 更新Meta | `/seo/meta/{id}` | PATCH | seo.manage |
| 訂閱方案設定 | `/settings/subscription-plans` | GET | settings.pricing |
| 更新訂閱方案 | `/settings/subscription-plans/{id}` | PATCH | settings.pricing |
| 體驗價設定 | `/settings/trial-plan` | PATCH | settings.pricing |
| 角色清單 | `/settings/roles` | GET | settings.roles |
| 管理員列表 | `/settings/admins` | GET | settings.roles |
| 建立管理員 | `/settings/admins` | POST | settings.roles |
| 指派角色 | `/settings/admins/{id}/role` | PATCH | settings.roles |
| 全域設定查詢 | `/settings/system` | GET | super_admin |
| 全域設定更新 | `/settings/system/{key}` | PATCH | super_admin |
| 操作日誌 | `/logs` | GET | 已登入 |
| 匿名聊天設定 | `/anon-chat/settings` | GET/PATCH | super_admin |
| 匿名聊天頻道 | `/anon-chat/channels` | GET/POST | admin+ |
| 更新頻道 | `/anon-chat/channels/{id}` | PATCH | admin+ |
| 頻道訊息查詢 | `/anon-chat/messages` | GET | admin+ |
| 刪除訊息 | `/anon-chat/messages/{id}` | DELETE | admin+ |
| 廣播列表 | `/broadcasts` | GET | admin+ |
| 建立廣播 | `/broadcasts` | POST | admin+ |
| 廣播詳情 | `/broadcasts/{id}` | GET | admin+ |
| 發送廣播 | `/broadcasts/{id}/send` | POST | admin+ |
| 系統控制中心總覽 | `/settings/system-control` | GET | super_admin |
| 切換系統模式 | `/settings/app-mode` | PATCH | super_admin |
| Email 設定 | `/settings/mail` | PATCH | super_admin |
| 發送測試信 | `/settings/mail/test` | POST | super_admin |
| SMS 設定 | `/settings/sms` | PATCH | super_admin |
| 發送測試簡訊 | `/settings/sms/test` | POST | super_admin |
| 測試 DB 連線 | `/settings/database/test` | POST | super_admin |
| app.mode 狀態 | `/settings/system/app-mode` | GET | super_admin |
| **Sprint 11 新增** | | | |
| 會員等級權限查詢 | `/settings/member-level-permissions` | GET | super_admin |
| 會員等級權限更新 | `/settings/member-level-permissions` | PATCH | super_admin |
| 權限矩陣 JSON 查詢 | `/settings/permission-matrix` | GET | super_admin |
| 權限矩陣 JSON 更新 | `/settings/permission-matrix` | PATCH | super_admin |
| 驗證待審列表 | `/verifications/pending` | GET | members.edit |
| 審核驗證 | `/verifications/{id}` | PATCH | members.edit |
| 廣播列表 | `/broadcasts` | GET | admin+ |
| 建立廣播 | `/broadcasts` | POST | admin+ |
| 廣播詳情 | `/broadcasts/{id}` | GET | admin+ |
| 發送廣播 | `/broadcasts/{id}/send` | POST | admin+ |
| 操作日誌 | `/logs` | GET | admin+ |
| 管理員列表 | `/settings/admins` | GET | settings.roles |
| 建立管理員 | `/settings/admins` | POST | settings.roles |
| 變更角色 | `/settings/admins/{id}/role` | PATCH | settings.roles |
| 角色權限矩陣 | `/settings/roles` | GET | settings.roles |

---

## §14 信用卡驗證管理（2026-04-26）

> 男性進階驗證（信用卡 NT$100）的後台管理端點。

### 14.1 查詢信用卡驗證列表

```
GET /api/v1/admin/credit-card-verifications
```

所需權限：`members.view`

**查詢參數：**
```
status: pending | paid | refunded | failed | refund_failed（選填）
user_id: 整數（選填）
page: 分頁
per_page: 每頁筆數（預設 20）
```

**成功回應 (200)：**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user": { "id": 123, "nickname": "測試用戶", "email": "..." },
      "order_no": "CCV_20260426200000_000123",
      "amount": 100,
      "status": "paid",
      "gateway_trade_no": "2026042612345678",
      "card_last4": "1234",
      "paid_at": "2026-04-26T12:00:00Z",
      "refunded_at": null,
      "created_at": "2026-04-26T11:59:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 5, "last_page": 1 }
}
```

### 14.2 手動觸發退款

```
POST /api/v1/admin/credit-card-verifications/{id}/refund
```

所需權限：`members.edit`

> 僅限 `status = paid` 的紀錄可退款。退款失敗時狀態改為 `refund_failed`。

**成功回應 (200)：**
```json
{ "success": true, "message": "退款成功" }
```

**失敗回應 (422)：**
```json
{ "success": false, "message": "退款失敗，請查看系統日誌" }
```

| 功能 | 路徑 | 方法 | 權限 |
|---|---|---|---|
| 信用卡驗證列表 | `/credit-card-verifications` | GET | members.view |
| 手動退款 | `/credit-card-verifications/{id}/refund` | POST | members.edit |

---

*本文件涵蓋後台所有 API 端點。前台用戶 API 請參閱 API-001。*