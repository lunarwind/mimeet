# [API-002] MiMeet 後台管理 API 規格書

**文檔版本：** v1.2（2026年4月更新，新增會員權限覆寫 API）  
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

Token 由後台登入 API 取得，**有效期 8 小時**（與前台用戶 24h 不同，安全性更高）。

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
  "email": "admin@mimeet.tw",
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
      "email": "admin@mimeet.tw",
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
    "email": "admin@mimeet.tw",
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

### 3.1 取得今日 / 本月統計數字

```
GET /api/v1/admin/stats/summary
```

**Query 參數：**

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `period` | string | 否 | `today`（預設）/ `month` / `yesterday` |

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "period": "today",
    "new_registrations": {
      "total": 42,
      "male": 18,
      "female": 24,
      "vs_yesterday_pct": 12.5
    },
    "new_verified": {
      "total": 15,
      "male": 7,
      "female": 8
    },
    "active_users": {
      "total": 320,
      "male": 140,
      "female": 180
    },
    "paid_members": {
      "total": 89,
      "new_today": 3
    },
    "pending_tickets": 7,
    "pending_verifications": 4
  }
}
```

---

### 3.2 取得圖表資料

```
GET /api/v1/admin/stats/chart
```

**Query 參數：**

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `type` | string | 是 | `registrations` / `active_users` / `paid_members` |
| `granularity` | string | 是 | `hourly`（今日）/ `daily`（近30天）/ `monthly`（近12月）|
| `date` | string | 否 | 指定日期 YYYY-MM-DD，`granularity=hourly` 時使用，預設今天 |

**成功回應 200（type=registrations, granularity=daily）：**
```json
{
  "success": true,
  "data": {
    "type": "registrations",
    "granularity": "daily",
    "labels": ["2025-01-01", "2025-01-02", "..."],
    "series": [
      { "name": "總數", "data": [15, 22, 18, "..."] },
      { "name": "男性", "data": [7, 10, 8, "..."] },
      { "name": "女性", "data": [8, 12, 10, "..."] }
    ]
  }
}
```

---

### 3.3 匯出統計資料

```
GET /api/v1/admin/stats/export
```

**Query 參數：**

| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `type` | string | 是 | `registrations` / `active_users` / `paid_members` |
| `start_date` | string | 是 | YYYY-MM-DD |
| `end_date` | string | 是 | YYYY-MM-DD，最多跨度1年 |
| `format` | string | 否 | `csv`（預設）/ `xlsx` |

**成功回應 200：**

回傳 Content-Type: `text/csv` 或 `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`

檔案名稱格式：`mimeet_stats_{type}_{start_date}_{end_date}.{format}`

---

### 3.4 取得伺服器指標

```
GET /api/v1/admin/stats/server-metrics
```

> 所需權限：`super_admin` 專屬

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "cpu_usage_pct": 42.5,
    "memory_usage_pct": 68.3,
    "disk_usage_pct": 31.2,
    "mysql_connections": 24,
    "redis_memory_mb": 156,
    "queue_jobs_pending": 3,
    "queue_jobs_failed": 0,
    "collected_at": "2025-01-15T10:30:00Z"
  }
}
```

> **實作說明：** 此端點呼叫 shell 指令 (`df`, `free`, `top`) 並從 Redis 讀取 Horizon 統計，回傳即時數據。請在前端加 30 秒 polling，不建議更頻繁。

---

## 4. 會員管理 API

> 所需權限：`members.view`（查看）、`members.edit`（操作）、`members.delete`（刪除）

### 4.1 取得會員列表

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
| `adjust_credit` | `value`（整數，可負）、`reason` | 調整誠信分數 | members.edit |
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

**Query 參數：**

| 參數 | 類型 | 說明 |
|------|------|------|
| `type` | string | `photo`（女性）/ `credit_card`（男性，通常自動）|

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 301,
      "user_id": 1001,
      "user_nickname": "甜心寶貝",
      "user_age": 23,
      "type": "photo",
      "verification_photo_url": "https://cdn.mimeet.tw/verifications/...",
      "random_code": "739284",
      "submitted_at": "2025-01-15T09:00:00Z",
      "expires_at": "2025-01-15T09:10:00Z"
    }
  ]
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

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "message": "驗證已通過",
    "user_id": 1001,
    "credit_score_added": 15
  }
}
```

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

> 所需權限：`reports.view`（查看）、`reports.process`（處理）

### 6.1 取得 Ticket 列表

```
GET /api/v1/admin/tickets
```

**Query 參數：**

| 參數 | 類型 | 說明 |
|------|------|------|
| `status` | string | `pending`（預設）/ `processing` / `resolved` / `all` |
| `type` | string | `system`（系統問題）/ `report`（一般檢舉）/ `anon_report`（匿名聊天檢舉）/ `unsubscribe`（取消訂閱）/ `appeal`（停權申訴，Sprint 8 新增） |
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
      "type": "report",
      "type_label": "一般檢舉",
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
    "type": "report",
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

> **Sprint 8 新增**：當 `type = "appeal"` 時，回應額外包含：
```json
{
  "appeal_info": {
    "suspended_at": "2026-04-08T09:00:00Z",
    "suspension_reason": "誠信分數歸零（最後一筆記錄：被檢舉 -10 分）",
    "credit_score_at_suspension": 0,
    "credit_score_history": [
      { "delta": -10, "reason": "被檢舉 (Ticket R20260408001)", "created_at": "..." },
      { "delta": -10, "reason": "被檢舉 (Ticket R20260401002)", "created_at": "..." }
    ]
  }
}
```

---

### 6.3 更新 Ticket 狀態 / 回覆

```
PATCH /api/v1/admin/tickets/{ticket_id}
```

> 狀態從 `pending` 改為 `processing` 時，系統自動記錄操作者為 assignee。

**請求參數：**
```json
{
  "status": "resolved",
  "admin_reply": "經查證屬實，已對違規用戶停權處理。感謝您的回報，已補回 10 分誠信分數。",
  "credit_adjustments": [
    { "user_id": 1001, "change": 10, "reason": "檢舉屬實，退回扣除分數並補獎勵" },
    { "user_id": 1002, "change": -15, "reason": "檢舉屬實，違規懲罰" }
  ]
}
```

> `credit_adjustments` 為選填。若填寫，後端自動對對應用戶執行誠信分數調整並記錄。

> **Sprint 8 新增** — 申訴專用 action（當 type=appeal 時使用）：
```json
// 申訴核准
{
  "status": "resolved",
  "action": "approve_appeal",
  "restore_score": 35,
  "admin_reply": "申訴審核通過，已補回誠信分數，請注意未來行為。"
}

// 申訴駁回
{
  "status": "dismissed",
  "action": "reject_appeal",
  "admin_reply": "申訴理由不充分，維持停權決定。"
}
```
> `restore_score` 最低值 30（系統強制驗證），低於 30 無法自動解停。

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "ticket_no": "R20250115001",
    "status": "resolved",
    "credit_adjustments_applied": 2
  }
}
```

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
      "description": "誠信分數系統，讓每一次相遇都值得信賴。",
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
      "email": "admin@mimeet.tw",
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

### 10.12 更新資料庫連線設定

```
PATCH /api/v1/admin/settings/database
```

> 所需權限：`super_admin`
> ⚠️ 高風險操作，需再次輸入管理員密碼確認

**請求參數：**
```json
{
  "host": "mimeet_mysql",
  "port": 3306,
  "database": "mimeet",
  "username": "mimeet_user",
  "password": "新密碼（選填）",
  "confirm_password": "管理員當前登入密碼（必填）"
}
```

**行為：** 儲存前先以新設定測試連線（失敗則 422 不儲存）。⚠️ 變更後需重啟容器。

**成功 200：**
```json
{
  "success": true,
  "data": { "message": "資料庫設定已更新。注意：完整生效需重啟應用容器", "restart_required": true }
}
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

> 所需權限：`members.edit`

#### 取得待審核驗證列表

```
GET /api/v1/admin/verifications/pending
```

**Query 參數：**

| 參數 | 類型 | 說明 |
|------|------|------|
| `page` | int | 頁碼 |

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 301,
      "user_id": 1001,
      "user_nickname": "甜心寶貝",
      "user_age": 23,
      "type": "photo",
      "verification_photo_url": "https://cdn.mimeet.tw/verifications/...",
      "random_code": "739284",
      "submitted_at": "2026-04-09T09:00:00Z"
    }
  ],
  "meta": { "page": 1, "per_page": 20, "total": 4, "last_page": 1 }
}
```

---

#### 審核驗證申請

```
PATCH /api/v1/admin/verifications/{id}
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
| `approved` | 通過：`membership_level` 升至 1.5、`credit_score` +15，發送通知 |
| `rejected` | 駁回：狀態改為 rejected，儲存 `reject_reason`，用戶收到通知可重試 |

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "message": "驗證已通過",
    "user_id": 1001,
    "new_membership_level": 1.5,
    "credit_score_added": 15
  }
}
```

---

### 10.C 廣播訊息 API（Sprint 11 新增）

> 所需權限：`admin` 以上

#### 取得廣播列表

```
GET /api/v1/admin/broadcasts
```

**Query 參數：** `status`（draft/sending/completed/failed）、`page`

---

#### 建立廣播草稿

```
POST /api/v1/admin/broadcasts
```

建立後狀態為 `draft`，需呼叫 send 端點才會實際發送。

---

#### 取得廣播詳情

```
GET /api/v1/admin/broadcasts/{id}
```

---

#### 觸發發送廣播

```
POST /api/v1/admin/broadcasts/{id}/send
```

> 只有 `status=draft` 的廣播可執行。非同步以 Queue Job 批次發出。

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


## 12. 匿名聊天室管理 API（Phase 2）

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

**Query 參數：** `status`（draft/sending/completed/failed）、`page`

**成功回應 200：**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "農曆新年活動通知",
      "delivery_mode": "both",
      "status": "completed",
      "target_count": 1234,
      "sent_count": 1231,
      "created_by": "Super Admin",
      "completed_at": "2025-01-15T10:05:00Z"
    }
  ]
}
```

---

### 13.2 建立廣播任務

```
POST /api/v1/admin/broadcasts
```

**請求參數：**
```json
{
  "title": "農曆新年活動通知",
  "content": "MiMeet 新年快樂！即日起至 2/14，新訂閱享 85 折優惠。",
  "delivery_mode": "both",
  "target_gender": "all",
  "target_level": "all",
  "target_credit_min": 31,
  "target_credit_max": 100,
  "scheduled_at": null
}
```

> `delivery_mode` 預設值從 `system_settings.broadcast.delivery_mode` 取得，可覆蓋。  
> `scheduled_at` 為 null 表示立即發送（建立後呼叫 send 端點）。

**成功回應 201：**
```json
{
  "success": true,
  "data": { "id": 2, "status": "draft", "target_count": 982 }
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

> 只有 `status=draft` 的廣播可執行。非同步以 Queue Job 批次發出，避免 timeout。

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "message": "廣播任務已排入佇列，預計完成時間視收件人數而定",
    "broadcast_id": 2,
    "status": "sending"
  }
}
```

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
| 伺服器指標 | `/stats/server-metrics` | GET | super_admin |
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
| 資料庫設定 | `/settings/database` | PATCH | super_admin |
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

*本文件涵蓋後台所有 API 端點（共 74 個）。前台用戶 API 請參閱 API-001。*