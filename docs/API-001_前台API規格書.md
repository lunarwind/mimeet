# [API-001] MiMeet 前台 API 規格書

**文檔版本：** v1.0（正式版）  
**確認日期：** 2025年1月  
**API架構師：**   
**後端負責人：**  
**審核狀態：** 已確認

---

## 1. API設計概述

### 1.1 設計原則

#### 1.1.1 RESTful設計標準
- **資源導向**：URL表示資源，HTTP動詞表示操作
- **無狀態設計**：每個請求都包含完整的信息
- **統一接口**：標準化的HTTP方法和狀態碼
- **分層系統**：支援負載均衡和緩存

#### 1.1.2 API版本策略
```
URL版本控制：
├─ /api/v1/users          # 版本1
├─ /api/v2/users          # 版本2（向後兼容）
└─ /api/v1/auth/login     # 統一版本前綴

Header版本控制（備選）：
├─ Accept: application/vnd.datingapi.v1+json
└─ API-Version: v1
```

#### 1.1.3 命名規範
```
資源命名：
├─ 複數名詞：/api/v1/users（不是user）
├─ 小寫字母：/api/v1/chat-messages（不是ChatMessages）
├─ 連字符：/api/v1/date-invitations（不是date_invitations）
└─ 嵌套資源：/api/v1/users/123/photos

HTTP動詞：
├─ GET：獲取資源
├─ POST：創建資源
├─ PUT：完整更新資源
├─ PATCH：部分更新資源
├─ DELETE：刪除資源
└─ OPTIONS：查詢支援的方法
```

### 1.2 通用規範

#### 1.2.1 請求格式
```json
{
  "data": {
    "user": {
      "nickname": "甜心寶貝",
      "age": 23,
      "location": "台北市"
    }
  },
  "meta": {
    "timestamp": "2024-12-20T10:30:00Z",
    "request_id": "req_1234567890abcdef",
    "client_version": "1.0.0"
  }
}
```

#### 1.2.2 回應格式
```json
{
  "success": true,
  "code": 200,
  "message": "操作成功",
  "data": {
    "user": {
      "id": 123,
      "nickname": "甜心寶貝",
      "age": 23,
      "location": "台北市",
      "created_at": "2024-12-20T10:30:00Z"
    }
  },
  "meta": {
    "timestamp": "2024-12-20T10:30:05Z",
    "request_id": "req_1234567890abcdef",
    "execution_time": "0.150s"
  }
}
```

#### 1.2.3 分頁格式
```json
{
  "success": true,
  "code": 200,
  "message": "查詢成功",
  "data": {
    "users": [
      {
        "id": 123,
        "nickname": "甜心寶貝"
      }
    ]
  },
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 156,
    "total_pages": 8,
    "has_next": true,
    "has_prev": false,
    "next_url": "/api/v1/users?page=2&per_page=20",
    "prev_url": null
  }
}
```

#### 1.2.4 錯誤格式
```json
{
  "success": false,
  "code": 400,
  "message": "請求參數錯誤",
  "error": {
    "type": "validation_error",
    "details": [
      {
        "field": "email",
        "message": "Email格式不正確",
        "code": "invalid_email_format"
      },
      {
        "field": "age",
        "message": "年齡必須在18-100之間",
        "code": "age_out_of_range"
      }
    ]
  },
  "meta": {
    "timestamp": "2024-12-20T10:30:00Z",
    "request_id": "req_1234567890abcdef"
  }
}
```

---

## 2. 認證與授權API

### 2.1 用戶認證

#### 2.1.1 用戶註冊
```http
POST /api/v1/auth/register
Content-Type: application/json
```

**請求參數：**
```json
{
  "data": {
    "email": "user@example.com",
    "password": "SecurePassword123",
    "password_confirmation": "SecurePassword123",
    "nickname": "甜心寶貝",
    "gender": "female",
    "birth_date": "2001-05-15",
    "group": 2,
    "terms_accepted": true,
    "privacy_accepted": true,
    "anti_fraud_read": true
  }
}
```

**成功回應 (201)：**
```json
{
  "success": true,
  "code": 201,
  "message": "註冊成功，請查收驗證郵件",
  "data": {
    "user": {
      "id": 123,
      "email": "user@example.com",
      "nickname": "甜心寶貝",
      "gender": "female",
      "group": 2,
      "status": "pending_verification",
      "created_at": "2024-12-20T10:30:00Z"
    },
    "verification": {
      "email_sent": true,
      "expires_at": "2024-12-20T11:30:00Z"
    }
  }
}
```

**錯誤回應 (400)：**
```json
{
  "success": false,
  "code": 400,
  "message": "註冊失敗",
  "error": {
    "type": "validation_error",
    "details": [
      {
        "field": "email",
        "message": "該Email已被註冊",
        "code": "email_already_exists"
      }
    ]
  }
}
```

#### 2.1.2 用戶登入
```http
POST /api/v1/auth/login
Content-Type: application/json
```

**請求參數：**
```json
{
  "data": {
    "email": "user@example.com",
    "password": "SecurePassword123",
    "remember_me": true,
    "device_info": {
      "type": "web",
      "name": "Chrome 120.0",
      "os": "Windows 10"
    }
  }
}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "登入成功",
  "data": {
    "user": {
      "id": 123,
      "email": "user@example.com",
      "nickname": "甜心寶貝",
      "avatar": "https://cdn.example.com/avatars/123.jpg",
      "gender": "female",
      "status": "active",
      "credit_score": 85,
      "membership_level": 2,
      "email_verified": true,
      "phone_verified": true,
      "phone": "09xx-xxx-666"
    },
    "tokens": {
      "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
      "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
      "token_type": "Bearer",
      "expires_in": 3600
    }
  }
}
```

#### 2.1.3 刷新Token
```http
POST /api/v1/auth/refresh
Authorization: Bearer {refresh_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "Token刷新成功",
  "data": {
    "tokens": {
      "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
      "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
      "token_type": "Bearer",
      "expires_in": 3600
    }
  }
}
```

#### 2.1.4 用戶登出
```http
POST /api/v1/auth/logout
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "登出成功"
}
```

### 2.2 身份驗證

#### 2.2.1 Email驗證
```http
POST /api/v1/auth/verify-email
Content-Type: application/json
```

**請求參數：**
```json
{
  "data": {
    "verification_code": "123456",
    "email": "user@example.com"
  }
}
```

#### 2.2.2 手機驗證
```http
POST /api/v1/auth/verify-phone
Authorization: Bearer {access_token}
Content-Type: application/json
```

**發送驗證碼：**
```json
{
  "data": {
    "phone": "+886912345678",
    "action": "send_code"
  }
}
```

**驗證手機號：**
```json
{
  "data": {
    "phone": "+886912345678",
    "verification_code": "123456",
    "action": "verify_code"
  }
}
```

#### 2.2.3 真人驗證（女性）
```http
POST /api/v1/auth/photo-verification
Authorization: Bearer {access_token}
Content-Type: multipart/form-data
```

**開始驗證（獲取驗證碼）：**
```http
POST /api/v1/auth/photo-verification/start
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "驗證會話創建成功",
  "data": {
    "session_id": "session_1234567890",
    "verification_code": "AB123C",
    "expires_at": "2024-12-20T10:40:00Z",
    "instructions": [
      "請手持身份證件拍攝自拍照",
      "照片中需清楚顯示驗證碼：AB123C",
      "請確保光線充足，五官清晰可見",
      "驗證碼有效時間10分鐘"
    ]
  }
}
```

**上傳驗證照片：**
```http
POST /api/v1/auth/photo-verification/upload
Content-Type: multipart/form-data

session_id: session_1234567890
verification_photo: {file}
```

#### 2.2.4 信用卡驗證（男性）
```http
POST /api/v1/auth/credit-card-verification
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{
  "data": {
    "payment_method": "green_world",
    "return_url": "https://app.example.com/verification/callback"
  }
}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "信用卡驗證初始化成功",
  "data": {
    "verification_id": "verify_1234567890",
    "payment_url": "https://payment.greenworld.com.tw/...",
    "amount": 100,
    "currency": "TWD",
    "expires_at": "2024-12-20T11:30:00Z"
  }
}
```

### 2.3 密碼重設

#### 2.3.1 申請密碼重設信件（未登入）
```http
POST /api/v1/auth/forgot-password
Content-Type: application/json
```

**請求參數：**
```json
{ "email": "user@example.com" }
```

**成功回應 (200)：**
```json
{ "success": true, "message": "若此 Email 已註冊，重設連結已寄出，有效期 60 分鐘" }
```

> 無論 Email 是否存在，均回傳相同訊息，防止用戶枚舉攻擊。

---

#### 2.3.2 提交新密碼
```http
POST /api/v1/auth/reset-password
Content-Type: application/json
```

**請求參數：**
```json
{
  "token": "7a3f9c...(from email link)",
  "email": "user@example.com",
  "password": "NewPass123!",
  "password_confirmation": "NewPass123!"
}
```

**成功回應 (200)：**
```json
{ "success": true, "message": "密碼已重設，請重新登入" }
```

**Token 失效回應 (422)：**
```json
{ "success": false, "error": { "code": "1010", "message": "重設連結已失效，請重新申請" } }
```

**Email 連結格式：**
```
https://mimeet.tw/reset-password?token={token}&email={encoded_email}
```
前端路由 `/reset-password`，讀取 query params，完成後跳轉 `/login`。

---

#### 2.3.3 修改密碼（已登入）
```http
POST /api/v1/me/change-password
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{
  "current_password": "OldPass123!",
  "password": "NewPass456!",
  "password_confirmation": "NewPass456!"
}
```

**成功回應 (200)：**
```json
{ "success": true, "message": "密碼已更新，所有裝置已登出" }
```

> **業務規則：** 密碼修改成功後，`users.tokens()` 全部刪除，強制重新登入。

---

## 3. 用戶管理API

### 3.1 用戶資料管理

#### 3.1.1 獲取用戶資料
```http
GET /api/v1/users/{user_id}
Authorization: Bearer {access_token}
```

**查詢參數：**
- `include`: 包含的關聯資料，可選值：`photos,preferences,stats`
- `fields`: 指定返回的欄位

**範例：**
```http
GET /api/v1/users/123?include=photos,stats&fields=id,nickname,age,location,photos,stats
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "查詢成功",
  "data": {
    "user": {
      "id": 123,
      "nickname": "甜心寶貝",
      "age": 23,
      "location": "台北市",
      "avatar": "https://cdn.example.com/avatars/123.jpg",
      "credit_score": 85,
      "vip_status": "premium",
      "verification_status": {
        "email_verified": true,
        "phone_verified": true,
        "photo_verified": true,
        "credit_card_verified": false
      },
      "photos": [
        {
          "id": 1,
          "url": "https://cdn.example.com/photos/123_1.jpg",
          "is_avatar": true,
          "order": 1
        }
      ],
      "stats": {
        "profile_views": 156,
        "messages_received": 23,
        "likes_received": 45
      },
      "last_active_at": "2024-12-20T09:15:00Z",
      "created_at": "2024-11-15T14:20:00Z"
    }
  }
}
```

#### 3.1.2 更新用戶資料
```http
PATCH /api/v1/users/{user_id}
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{
  "data": {
    "nickname": "新暱稱",
    "introduction": "更新的自我介紹",
    "location": "新北市",
    "height": 165,
    "weight": 50,
    "education": "master",
    "job": "軟體工程師",
    "preferences": {
      "age_range": [25, 35],
      "location_preference": ["台北市", "新北市"],
      "relationship_type": "long_term"
    }
  }
}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "資料更新成功",
  "data": {
    "user": {
      "id": 123,
      "nickname": "新暱稱",
      "introduction": "更新的自我介紹",
      "updated_at": "2024-12-20T10:30:00Z"
    }
  }
}
```

#### 3.1.3 上傳用戶照片
```http
POST /api/v1/users/{user_id}/photos
Authorization: Bearer {access_token}
Content-Type: multipart/form-data
```

**請求參數：**
```
photo: {file}
is_avatar: true|false
order: 1
```

**成功回應 (201)：**
```json
{
  "success": true,
  "code": 201,
  "message": "照片上傳成功",
  "data": {
    "photo": {
      "id": 456,
      "url": "https://cdn.example.com/photos/123_456.jpg",
      "thumbnail_url": "https://cdn.example.com/photos/thumbs/123_456.jpg",
      "is_avatar": true,
      "order": 1,
      "status": "pending_review",
      "created_at": "2024-12-20T10:30:00Z"
    }
  }
}
```

#### 3.1.4 刪除用戶照片
```http
DELETE /api/v1/users/{user_id}/photos/{photo_id}
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "照片刪除成功"
}
```

**照片規格業務規則（`§3.1` 整體適用）：**

| 規則 | 說明 |
|------|------|
| **頭像必填** | 每位用戶必須有 1 張頭像（`avatar`），不可刪除唯一頭像 |
| **相冊最少 2 張** | 個人相冊（`user_photos`）需至少上傳 **2 張**才可設定進階驗證、才可被搜尋到 |
| **相冊上限 6 張** | 包含頭像以外的相冊照片，上限 6 張（`error_code 2031`） |
| **頭像與相冊互換** | 用戶可將任一相冊照片設為新頭像（PUT `/me/photos/{id}/set-avatar`），原頭像自動移入相冊 |
| **不可留空** | 刪除照片後若相冊剩餘 < 2 張，API 回傳 `422 + error_code 2032`：`{ "message": "相冊至少需保留 2 張照片" }` |

> `PUT /api/v1/me/photos/{photo_id}/set-avatar` — 將指定相冊照片設為頭像（201 成功）

---

### 3.1.5 取得帳號設定頁初始資料

> 前端進入 `/app/settings` 時呼叫此 API，一次性取得所有需要渲染設定頁的資料，避免多次請求。

```http
GET /api/v1/me/settings
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "profile": {
      "id": 123,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "nickname": "甜心寶貝",
      "gender": "female",
      "role": "sweetie",
      "birth_date": "2001-05-15",
      "age": 23,
      "avatar_url": "https://cdn.mimeet.tw/avatars/123.webp",
      "city": "台北市",
      "job": "軟體工程師",
      "education": "bachelor",
      "introduction": "喜歡旅遊和攝影",
      "height": 165,
      "weight": 50
    },
    "account": {
      "email": "user@example.com",
      "email_verified": true,
      "phone_last4": "8888",
      "phone_verified": true
    },
    "verification": {
      "membership_level": 2,
      "advanced_verified": true,
      "advanced_verified_at": "2025-01-10T08:00:00Z"
    },
    "privacy_settings": {
      "stealth_mode": false,
      "hide_last_active": false,
      "read_receipt": true
    },
    "membership": {
      "is_paid": true,
      "expires_at": "2025-02-20T10:30:00Z",
      "days_remaining": 15
    }
  }
}
```

**欄位說明：**

| 區塊 | 用途 |
|------|------|
| `profile` | 渲染「個人資料」Section（暱稱/地區/職業/簡介等） |
| `account` | 渲染「帳號安全」Section（Email/手機驗證狀態） |
| `verification` | 渲染「身份驗證」入口的完成狀態 badge |
| `privacy_settings` | 渲染「隱私設定」Toggle 初始狀態 |
| `membership` | 渲染是否顯示付費限制鎖頭（hide_last_active / read_receipt） |

> **birth_date 不可修改：** 前端依 `birth_date` 渲染為灰色 disabled 欄位（UI-001 §4.2.12）。

---

### 3.2 搜尋與配對

#### 3.2.1 搜尋用戶
```http
GET /api/v1/users/search
Authorization: Bearer {access_token}
```

**查詢參數：**
```
# 基本
gender:             male|female
age_min / age_max:  18 ~ 99
location:           台北市（LIKE 模糊）
page / per_page:    分頁

# F27 進階篩選（2026-04-20 補完）
min_height / max_height:   身高範圍（cm）
min_weight / max_weight:   體重範圍（kg）
education:                 high_school|associate|bachelor|master|phd|other（精確）
occupation:                職業（LIKE 模糊）
style:                     fresh|sweet|sexy|intellectual|sporty
dating_budget:             casual|moderate|generous|luxury|undisclosed
dating_frequency:          occasional|weekly|flexible
dating_type:               dining|travel|companion|mentorship|undisclosed（JSON_CONTAINS 單值）
relationship_goal:         short_term|long_term|open|undisclosed
smoking:                   never|sometimes|often
drinking:                  never|social|often
car_owner:                 boolean
availability:              weekday_day|weekday_night|weekend|flexible（JSON_CONTAINS 單值）
min_credit / max_credit:   誠信分數範圍（相容舊稱 credit_score_min/max）
```

**未填欄位的使用者不會被排除：** 每個進階篩選都用 `WHERE (column = val OR column IS NULL)` 形式，避免把剛註冊、尚未完整填寫 profile 的用戶排除在外。

**排序：** 資料完整度（height / dating_budget / style / bio 非空的加分）DESC → credit_score DESC → last_active_at DESC。

**範例：**
```http
GET /api/v1/users/search?gender=male&age_min=25&age_max=40&location=台北市&credit_score_min=70&verified_only=true&sort=credit_score&sort_direction=desc&page=1&per_page=20
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "搜尋成功",
  "data": {
    "users": [
      {
        "id": 456,
        "nickname": "成功人士",
        "age": 32,
        "location": "台北市",
        "avatar": "https://cdn.example.com/avatars/456.jpg",
        "credit_score": 92,
        "vip_status": "premium",
        "verification_status": {
          "verified": true,
          "credit_card_verified": true
        },
        "online_status": "online",
        "last_active_at": "2024-12-20T10:25:00Z",
        "distance": 2.5,
        "compatibility_score": 85
      }
    ]
  },
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 156,
    "total_pages": 8
  }
}
```

**業務規則：**
- **30天未登入自動隱藏：** `last_login_at < NOW() - 30 days` 的用戶不出現在搜尋結果，除非呼叫方明確傳入 `include_inactive=true`（付費功能保留欄位）
- **信用分數區間：** 30分以下（受限）、31-60（普通）、61-90（優質）、91+（頂級）
- **跨區間傳訊限制：** 較高分數區間的用戶可主動向較低分數區間用戶發送訊息；反之 **不可**。若信用分數 ≤ 30 分（受限），**無法主動發起聊天**（嘗試時 API 回傳 `403 + error_code 2003`）
- **推薦排序預設：** 信用分數優先，次之最後上線時間；超過 30 天未登入者不在推薦列表出現


```http
GET /api/v1/users/recommendations
Authorization: Bearer {access_token}
```

**查詢參數：**
```
limit: 10
refresh: true|false  # 是否刷新推薦列表
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "推薦成功",
  "data": {
    "recommendations": [
      {
        "user": {
          "id": 789,
          "nickname": "理想對象",
          "age": 28,
          "location": "台北市",
          "avatar": "https://cdn.example.com/avatars/789.jpg",
          "credit_score": 88
        },
        "match_score": 92,
        "match_reasons": [
          "年齡偏好匹配",
          "地理位置相近",
          "共同興趣：電影"
        ],
        "recommended_at": "2024-12-20T10:30:00Z"
      }
    ],
    "next_refresh_at": "2024-12-20T22:30:00Z"
  }
}
```

---

### 3.3 個人相冊管理

#### 3.3.1 取得我的相冊
```http
GET /api/v1/me/photos
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "photos": [
      { "id": 1, "url": "https://cdn.mimeet.tw/photos/gallery/.../img.webp", "sort_order": 0, "created_at": "..." }
    ],
    "max_photos": 6
  }
}
```

---

#### 3.3.2 新增相冊照片
先呼叫 `POST /api/v1/uploads?context=profile_photo` 取得 CDN URL，再呼叫本端點。

```http
POST /api/v1/me/photos
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{ "url": "https://cdn.mimeet.tw/photos/gallery/.../img.webp" }
```

**成功回應 (201)：**
```json
{ "success": true, "data": { "id": 7, "url": "...", "sort_order": 5 } }
```

**超過上限 (422)：**
```json
{ "success": false, "error": { "code": "2031", "message": "相冊最多 6 張照片" } }
```

---

#### 3.3.3 排序相冊照片
```http
PATCH /api/v1/me/photos/sort
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{ "order": [3, 1, 2] }
```

**成功回應 (200)：**
```json
{ "success": true }
```

---

#### 3.3.4 刪除相冊照片
```http
DELETE /api/v1/me/photos/{photo_id}
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{ "success": true }
```

---

### 3.4 用戶封鎖管理

#### 3.4.1 封鎖用戶
```http
POST /api/v1/users/{user_id}/block
Authorization: Bearer {access_token}
```

**成功回應 (201)：**
```json
{ "success": true, "data": { "blocked": true } }
```

**封鎖自己 (422)：**
```json
{ "success": false, "error": { "code": "2030", "message": "不能封鎖自己" } }
```

**封鎖後業務規則：**
- 雙方不出現在對方搜尋/推薦結果
- 若已有 conversation，前端隱藏（資料不刪除）
- 被封鎖方查看封鎖者個人資料頁 → `403`
- 被封鎖方嘗試傳訊 → `400`（錯誤碼 `2002`）

---

#### 3.4.2 解除封鎖
```http
DELETE /api/v1/users/{user_id}/block
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{ "success": true, "data": { "blocked": false } }
```

---

#### 3.4.3 取得我的封鎖列表
```http
GET /api/v1/me/blocked-users
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "blocked_users": [
      { "id": 789, "nickname": "某用戶", "avatar": "...", "blocked_at": "2025-01-10T08:00:00Z" }
    ]
  }
}
```

---

### 3.5 帳號刪除

#### 3.5.1 申請帳號刪除（進入 7 天冷靜期）
```http
DELETE /api/v1/me/account
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{
  "password": "CurrentPass123!",
  "reason": "no_longer_needed"
}
```

`reason` 枚舉：`no_longer_needed` | `privacy_concern` | `bad_experience` | `found_partner` | `other`

**成功回應 (200)：**
```json
{
  "success": true,
  "message": "帳號刪除申請已送出。7 天內重新登入可取消。",
  "data": { "scheduled_deletion_at": "2025-01-22T00:00:00Z" }
}
```

**密碼錯誤 (422)：**
```json
{ "success": false, "error": { "code": "1011", "message": "密碼錯誤" } }
```

---

#### 3.5.2 取消帳號刪除申請
```http
POST /api/v1/me/account/cancel-deletion
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{ "success": true, "message": "帳號刪除申請已取消" }
```

---

### 3.6 FCM 推播 Token

#### 3.6.1 註冊 FCM Token
前端取得裝置 FCM Token 後上傳，後端才能推播通知。

```http
POST /api/v1/me/fcm-token
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{
  "token": "fG7k_9xQ...(Firebase Device Token)",
  "platform": "web"
}
```

`platform`：`web` | `ios` | `android`

**成功回應 (200)：**
```json
{ "success": true, "message": "FCM Token 已更新" }
```

> 同一 `user_id + token` 做 upsert，避免重複。

---

#### 3.6.2 登出時移除 FCM Token
```http
DELETE /api/v1/me/fcm-token
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{ "token": "fG7k_9xQ..." }
```

**成功回應 (200)：**
```json
{ "success": true }
```

---

## 4. 聊天通訊API

### 4.1 聊天管理

#### 4.1.1 獲取聊天列表
```http
GET /api/v1/chats
Authorization: Bearer {access_token}
```

**查詢參數：**
```
status: all|active|archived
unread_only: true|false
page: 1
per_page: 20
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "聊天列表查詢成功",
  "data": {
    "chats": [
      {
        "id": 123,
        "other_user": {
          "id": 456,
          "nickname": "聊天對象",
          "avatar": "https://cdn.example.com/avatars/456.jpg",
          "online_status": "online"
        },
        "last_message": {
          "id": 789,
          "content": "你好，很高興認識你",
          "message_type": "text",
          "sender_id": 456,
          "sent_at": "2024-12-20T10:25:00Z",
          "is_read": false
        },
        "unread_count": 3,
        "is_blocked": false,
        "updated_at": "2024-12-20T10:25:00Z"
      }
    ]
  },
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 15
  }
}
```

#### 4.1.2 獲取聊天記錄
```http
GET /api/v1/chats/{chat_id}/messages
Authorization: Bearer {access_token}
```

**查詢參數：**
```
before_id: 123  # 獲取指定消息ID之前的消息
limit: 50
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "聊天記錄查詢成功",
  "data": {
    "messages": [
      {
        "id": 789,
        "sender_id": 456,
        "content": "你好，很高興認識你",
        "message_type": "text",
        "sent_at": "2024-12-20T10:25:00Z",
        "is_read": true,
        "read_at": "2024-12-20T10:26:00Z"
      },
      {
        "id": 790,
        "sender_id": 123,
        "content": "https://cdn.example.com/images/msg_790.jpg",
        "message_type": "image",
        "sent_at": "2024-12-20T10:27:00Z",
        "is_read": false,
        "read_at": null
      }
    ],
    "has_more": true,
    "next_before_id": 788
  }
}
```

#### 4.1.3 發送消息
```http
POST /api/v1/chats/{chat_id}/messages
Authorization: Bearer {access_token}
Content-Type: application/json
```

**文字消息：**
```json
{
  "data": {
    "content": "你好，很高興認識你！",
    "message_type": "text"
  }
}
```

**圖片消息：**
```http
POST /api/v1/chats/{chat_id}/messages
Authorization: Bearer {access_token}
Content-Type: multipart/form-data

message_type: image
image: {file}
```

**成功回應 (201)：**
```json
{
  "success": true,
  "code": 201,
  "message": "消息發送成功",
  "data": {
    "message": {
      "id": 791,
      "sender_id": 123,
      "content": "你好，很高興認識你！",
      "message_type": "text",
      "sent_at": "2024-12-20T10:30:00Z",
      "is_read": false,
      "read_at": null
    }
  }
}
```

#### 4.1.4 標記消息已讀
```http
PATCH /api/v1/chats/{chat_id}/messages/read
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{
  "data": {
    "message_ids": [789, 790, 791]
  }
}
```

#### 4.1.5 回收訊息（F19）
```http
DELETE /api/v1/chats/{chat_id}/messages/{message_id}
Authorization: Bearer {access_token}
```

**業務規則：**
- 僅 sender 本人可回收
- 訊息需在 **5 分鐘內**（`now() - sent_at <= 300s`）
- 訊息**尚未**被對方讀取（`is_read = false`）
- 僅**付費會員**（`membership_level >= 3`）可用；路由已掛 `membership:3`
- 成功後：`is_recalled = true`、`recalled_at = now()`；廣播 `MessageRecalled` 事件至 `private-chat.{conversation_id}` 頻道

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "訊息已收回",
  "data": { "message_id": 791, "recalled_at": "2026-04-19T10:35:00Z" }
}
```

**條件不符 (422)：**
```json
{ "success": false, "error": { "code": "RECALL_DENIED", "message": "訊息已被對方讀取，無法回收" } }
```

#### 4.1.6 聊天內關鍵字搜尋（F20）
```http
GET /api/v1/chats/{chat_id}/messages/search?keyword=xxx&per_page=20
Authorization: Bearer {access_token}
```

**Query 參數：**
| 參數 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `keyword` | string | 是 | 搜尋字串（1–100 字）|
| `per_page` | int | 否 | 每頁筆數（預設 20，上限 50）|

**業務規則：**
- 僅對話參與者可呼叫
- 排除已回收訊息（`is_recalled = false`）
- `content LIKE %keyword%`，依 `sent_at` 倒序

**成功回應 (200)：**
```json
{
  "success": true,
  "data": { "messages": [
    { "id": 791, "sender_id": 123, "type": "text", "content": "...關鍵字...", "image_url": null, "is_read": true, "is_recalled": false, "sent_at": "2026-04-19T10:00:00Z" }
  ] },
  "meta": { "total": 1, "page": 1, "per_page": 20, "last_page": 1 }
}
```

#### 4.1.7 對話靜音（F22 Part A）
```http
PATCH /api/v1/chats/{chat_id}/mute
Authorization: Bearer {access_token}
```

**業務規則：**
- Toggle 切換目前用戶對該對話的靜音狀態
- 靜音後：該對話的新訊息**仍會**走 WebSocket 廣播、仍會寫入 `notifications` 表 → 前端和 `chats` 列表 badge 仍更新；**只有** FCM 推播（行動裝置鎖屏通知）會跳過
- 前端收到 WebSocket `MessageSent` 事件後，需自行讀取 `is_muted` 決定是否播放提示音
- 對話列表 `GET /chats` 回傳每筆 `conversation` 均含 `is_muted` 欄位

**成功回應 (200)：**
```json
{ "success": true, "data": { "is_muted": true } }
```

### 4.2 WebSocket實時通訊

#### 4.2.1 連接建立
```javascript
// WebSocket連接
const ws = new WebSocket('wss://api.example.com/ws/chat');

// 認證
ws.onopen = function() {
    ws.send(JSON.stringify({
        type: 'auth',
        token: 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'
    }));
};
```

#### 4.2.2 消息格式

**發送消息：**
```json
{
  "type": "message",
  "data": {
    "chat_id": 123,
    "content": "實時消息內容",
    "message_type": "text",
    "temp_id": "temp_1234567890"
  }
}
```

**接收消息：**
```json
{
  "type": "message",
  "data": {
    "message": {
      "id": 792,
      "chat_id": 123,
      "sender_id": 456,
      "content": "收到的實時消息",
      "message_type": "text",
      "sent_at": "2024-12-20T10:30:00Z"
    }
  }
}
```

**打字狀態：**
```json
{
  "type": "typing",
  "data": {
    "chat_id": 123,
    "is_typing": true,
    "user_id": 456
  }
}
```

**在線狀態：**
```json
{
  "type": "online_status",
  "data": {
    "user_id": 456,
    "status": "online|offline|away",
    "last_seen_at": "2024-12-20T10:30:00Z"
  }
}
```

---

## 5. 約會驗證API

### 5.1 約會邀請管理

#### 5.1.1 創建約會邀請

> **觸發場景（v1.3 更新）：**
> 1. 聊天頁 QR icon（原有）
> 2. 個人資料頁「📅 邀請約會」按鈕（v1.3 新增，自動建立對話後開啟 Bottom Sheet）
> 兩個入口共用此 API，payload 格式相同。從個人資料頁發起時，latitude/longitude 傳 null。

```http
POST /api/v1/date-invitations
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{
  "data": {
    "invitee_id": 456,
    "scheduled_at": "2024-12-25T19:00:00Z",
    "location": "台北101美食街",
    "location_lat": 25.0340,
    "location_lng": 121.5645,
    "message": "邀請你一起吃晚餐，期待與你見面！",
    "estimated_duration": 120
  }
}
```

**成功回應 (201)：**
```json
{
  "success": true,
  "code": 201,
  "message": "約會邀請發送成功",
  "data": {
    "invitation": {
      "id": 123,
      "inviter_id": 123,
      "invitee_id": 456,
      "scheduled_at": "2024-12-25T19:00:00Z",
      "location": "台北101美食街",
      "location_lat": 25.0340,
      "location_lng": 121.5645,
      "status": "pending",
      "qr_code": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
      "qr_expires_at": "2024-12-25T20:00:00Z",
      "message": "邀請你一起吃晚餐，期待與你見面！",
      "created_at": "2024-12-20T10:30:00Z"
    }
  }
}
```

#### 5.1.2 回應約會邀請
```http
PATCH /api/v1/date-invitations/{invitation_id}/response
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{
  "data": {
    "response": "accepted|rejected",
    "message": "好的，我會準時到場！"
  }
}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "約會邀請回應成功",
  "data": {
    "invitation": {
      "id": 123,
      "status": "accepted",
      "response_message": "好的，我會準時到場！",
      "responded_at": "2024-12-20T10:35:00Z"
    }
  }
}
```

#### 5.1.3 獲取約會邀請列表
```http
GET /api/v1/date-invitations
Authorization: Bearer {access_token}
```

**查詢參數：**
```
status: pending|accepted|rejected|completed|expired|cancelled
type: sent|received  # 發送的邀請或收到的邀請
page: 1
per_page: 20
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "約會邀請列表查詢成功",
  "data": {
    "invitations": [
      {
        "id": 123,
        "inviter": {
          "id": 123,
          "nickname": "邀請者",
          "avatar": "https://cdn.example.com/avatars/123.jpg"
        },
        "invitee": {
          "id": 456,
          "nickname": "被邀請者",
          "avatar": "https://cdn.example.com/avatars/456.jpg"
        },
        "scheduled_at": "2024-12-25T19:00:00Z",
        "location": "台北101美食街",
        "status": "accepted",
        "created_at": "2024-12-20T10:30:00Z"
      }
    ]
  }
}
```

#### 5.1.4 接受約會邀請
```http
PATCH /api/v1/dates/{id}/accept
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "data": { "invitation": { "id": 123, "status": "accepted" } }
}
```

#### 5.1.5 拒絕約會邀請
```http
PATCH /api/v1/dates/{id}/decline
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "data": { "invitation": { "id": 123, "status": "declined" } }
}
```

---

### 5.2 約會驗證

#### 5.2.1 QR碼掃描驗證
```http
POST /api/v1/dates/verify
Authorization: Bearer {access_token}
Content-Type: application/json
```

> **注意：** token 放在 request body，非路由參數。路由為 `/dates/verify`（無 `/{id}`），QR token 中已含邀請識別資訊。

> **實作版本（v1.3 更新）**：前端掃碼後自動取得 GPS 座標（`navigator.geolocation`），
> 若用戶拒絕授權則 latitude/longitude 傳 null，後端仍接受但 GPS 驗證不通過（得 +2 而非 +5）。

**請求參數：**
```json
{
  "token": "64字元hex QR token",
  "latitude": 25.0341,
  "longitude": 121.5646
}
```

| 欄位 | 必填 | 說明 |
|------|------|------|
| `token` | 是 | QR code 掃碼取得的 token（`bin2hex(random_bytes(32))`） |
| `latitude` | 否 | GPS 緯度（`navigator.geolocation` 取得，拒絕授權時傳 null） |
| `longitude` | 否 | GPS 經度 |

**成功回應 — 雙方都已掃碼 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "驗證處理完成",
  "data": {
    "status": "completed",
    "score_awarded": 5,
    "gps_passed": true
  }
}
```

**成功回應 — 僅一方掃碼 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "驗證處理完成",
  "data": {
    "status": "waiting",
    "message": "等待對方掃碼"
  }
}
```

**驗證失敗回應 (400)：**
```json
{
  "success": false,
  "code": 400,
  "message": "約會驗證失敗",
  "error": {
    "type": "verification_failed",
    "reason": "location_mismatch",
    "details": {
      "current_distance": 600.5,
      "max_allowed_distance": 500,
      "time_window_valid": true,
      "location_valid": false
    }
  }
}
```

#### 5.2.2 獲取驗證記錄
```http
GET /api/v1/date-invitations/{invitation_id}/checkins
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "驗證記錄查詢成功",
  "data": {
    "checkins": [
      {
        "id": 1,
        "user_id": 123,
        "checkin_time": "2024-12-25T19:05:00Z",
        "location_lat": 25.0341,
        "location_lng": 121.5646,
        "distance_from_target": 15.5,
        "verification_photo": "https://cdn.example.com/verifications/123_1.jpg"
      },
      {
        "id": 2,
        "user_id": 456,
        "checkin_time": "2024-12-25T19:08:00Z",
        "location_lat": 25.0340,
        "location_lng": 121.5645,
        "distance_from_target": 8.2,
        "verification_photo": "https://cdn.example.com/verifications/456_2.jpg"
      }
    ]
  }
}
```

---

## 6. 動態內容API

### 6.1 動態管理

#### 6.1.1 發布動態
```http
POST /api/v1/contents
Authorization: Bearer {access_token}
Content-Type: multipart/form-data
```

**請求參數：**
```
title: 今天心情很好
content: 天氣真不錯，出來走走
images[]: {file1}
images[]: {file2}
group: 0  # 0:公開, 1:僅粉絲
```

**成功回應 (201)：**
```json
{
  "success": true,
  "code": 201,
  "message": "動態發布成功",
  "data": {
    "content": {
      "id": 123,
      "user_id": 123,
      "title": "今天心情很好",
      "content": "天氣真不錯，出來走走",
      "images": [
        {
          "id": 1,
          "url": "https://cdn.example.com/contents/123_1.jpg",
          "thumbnail_url": "https://cdn.example.com/contents/thumbs/123_1.jpg"
        }
      ],
      "likes": 0,
      "comments": 0,
      "status": "active",
      "created_at": "2024-12-20T10:30:00Z"
    }
  }
}
```

**業務規則：**
- **發布上限：** 每位用戶最多同時存在 **3 則**有效（未刪除）動態
- **發布間隔：** 相鄰兩則動態之間隔至少 **30 分鐘**（以 `created_at` 計算）
- 超過上限回傳 `422 + error_code 3010`：`{ "message": "已達動態上限（最多 3 則）" }`
- 間隔不足回傳 `422 + error_code 3011`：`{ "message": "發布過於頻繁，請 N 分鐘後再試" }`
- 所有異性用戶均可閱讀公開動態；`following_only=true` 篩選僅顯示收藏對象的動態


```http
GET /api/v1/contents
Authorization: Bearer {access_token}
```

**查詢參數：**
```
user_id: 123      # 指定用戶的動態
following_only: true|false  # 僅顯示關注用戶的動態
page: 1
per_page: 20
sort: latest|popular
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "動態列表查詢成功",
  "data": {
    "contents": [
      {
        "id": 123,
        "user": {
          "id": 123,
          "nickname": "甜心寶貝",
          "avatar": "https://cdn.example.com/avatars/123.jpg",
          "vip_status": "premium"
        },
        "title": "今天心情很好",
        "content": "天氣真不錯，出來走走",
        "images": [
          {
            "id": 1,
            "url": "https://cdn.example.com/contents/123_1.jpg",
            "thumbnail_url": "https://cdn.example.com/contents/thumbs/123_1.jpg"
          }
        ],
        "likes": 15,
        "comments": 3,
        "is_liked": false,
        "created_at": "2024-12-20T10:30:00Z"
      }
    ]
  }
}
```

#### 6.1.3 點讚/取消點讚
```http
POST /api/v1/contents/{content_id}/like
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "點讚成功",
  "data": {
    "liked": true,
    "likes_count": 16
  }
}
```

**取消點讚：**
```http
DELETE /api/v1/contents/{content_id}/like
Authorization: Bearer {access_token}
```

#### 6.1.4 刪除動態
```http
DELETE /api/v1/contents/{content_id}
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "動態刪除成功"
}
```

### 6.2 動態留言（付費會員功能）

#### 6.2.1 取得留言列表
```http
GET /api/v1/posts/{post_id}/comments
Authorization: Bearer {access_token}
```

**查詢參數：** `page`、`per_page`（預設 20）

**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "comments": [
      {
        "id": 501,
        "post_id": 123,
        "user": { "id": 456, "nickname": "留言者", "avatar": "...", "credit_score": 82 },
        "content": "好厲害！",
        "created_at": "2025-01-15T10:30:00Z",
        "created_at_human": "15 分鐘前"
      }
    ]
  },
  "pagination": { "current_page": 1, "per_page": 20, "total": 7 }
}
```

---

#### 6.2.2 新增留言
```http
POST /api/v1/posts/{post_id}/comments
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{ "content": "好厲害！" }
```

**成功回應 (201)：**
```json
{
  "success": true,
  "data": { "comment": { "id": 501, "content": "好厲害！", "created_at": "2025-01-15T10:30:00Z" } }
}
```

**非付費會員 (403)：**
```json
{ "success": false, "error": { "code": "4031", "message": "此功能需要付費會員" } }
```

---

#### 6.2.3 刪除留言（留言者本人或發文者）
```http
DELETE /api/v1/posts/{post_id}/comments/{comment_id}
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{ "success": true, "message": "留言已刪除" }
```

---

## 7. 支付訂閱API

### 7.1 訂閱管理

#### 7.1.1 獲取訂閱方案
```http
GET /api/v1/subscription/plans
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "訂閱方案查詢成功",
  "data": {
    "plans": [
      {
        "id": 1,
        "name": "基礎月費",
        "description": "解鎖基礎功能",
        "price": 399,
        "currency": "TWD",
        "duration": "monthly",
        "features": [
          "無限聊天",
          "查看已讀狀態",
          "進階搜尋"
        ],
        "is_popular": false
      },
      {
        "id": 2,
        "name": "進階月費",
        "description": "解鎖所有功能",
        "price": 799,
        "currency": "TWD",
        "duration": "monthly",
        "features": [
          "所有基礎功能",
          "隱身模式",
          "廣播訊息",
          "VIP標誌",
          "優先客服"
        ],
        "is_popular": true
      }
    ]
  }
}
```

#### 7.1.2 創建訂閱訂單
```http
POST /api/v1/subscriptions/orders
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**

| 欄位 | 必填 | 說明 |
|------|------|------|
| `plan_id` | 是 | 方案 slug（`plan_weekly` / `plan_monthly` / `plan_quarterly` / `plan_yearly`） |
| `payment_method` | 否 | 付款方式，預設 `credit_card`（可選：`credit_card` / `atm` / `cvs`） |

```json
{
  "plan_id": "plan_monthly",
  "payment_method": "credit_card"
}
```

**成功回應 (201)：**
```json
{
  "success": true,
  "code": 201,
  "message": "訂單已建立",
  "data": {
    "order": {
      "id": 42,
      "order_number": "MM20260417123456ABCD",
      "amount": 599,
      "status": "pending",
      "expires_at": "2026-04-17T13:00:00Z"
    },
    "payment_url": "https://api.mimeet.online/api/v1/payments/ecpay/checkout/{token}"
  }
}
```

> **前台處理：** 收到 `payment_url` 後，用 `window.location.href = payment_url`
> 跳轉（不用 Vue Router，因為是跨域外部 URL）。
>
> **Sandbox 模式：** `payment_url` 為 mock 端點，訪問後模擬付款完成，
> 自動跳轉回 `https://mimeet.online/#/app/shop?payment=success`。
>
> **Production 模式：** `payment_url` 為 checkout 端點，
> 提供自動 POST 表單跳轉至綠界付款頁面（信用卡輸入界面）。

#### 7.1.3 獲取訂閱狀態
```http
GET /api/v1/subscription/status
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "訂閱狀態查詢成功",
  "data": {
    "subscription": {
      "is_active": true,
      "plan": {
        "id": 2,
        "name": "進階月費",
        "features": [
          "所有基礎功能",
          "隱身模式",
          "廣播訊息"
        ]
      },
      "started_at": "2024-11-20T10:30:00Z",
      "expires_at": "2024-12-20T10:30:00Z",
      "auto_renew": true,
      "days_remaining": 10
    }
  }
}
```

### 7.2 支付回調

#### 7.2.1 綠界科技回調
```http
POST /api/v1/payments/callbacks/green-world
Content-Type: application/x-www-form-urlencoded
```

**請求參數（綠界回傳）：**
```
MerchantTradeNo=order_1234567890
RtnCode=1
RtnMsg=Succeeded
PaymentDate=2024/12/20 10:30:00
PaymentType=Credit_CreditCard
PaymentTypeChargeFee=21
TradeAmt=749
CheckMacValue=...
```

**成功回應 (200)：**
```
1|OK
```

#### 7.2.2 其他金流回調（Phase 2 預留）

> **Phase 1 僅實作綠界科技。** Phase 2 將依業務需求擴充其他線上支付（如 Stripe、LinePay 等）。
>
> 擴充設計原則：
> - `PaymentService` 採用 Strategy Pattern，新增金流只需新增對應 `Driver` 類別
> - `payment_records.method` ENUM 屆時新增對應值
> - 新增 `/api/v1/payments/callbacks/{provider}` 路由，不影響現有綠界路由
> - Webhook 安全驗證機制由各金流 Driver 各自負責（HMAC / Signature 驗證）

---

## 8. 舉報檢舉API

### 8.1 舉報管理

#### 8.1.1 提交舉報
```http
POST /api/v1/reports
Authorization: Bearer {access_token}
Content-Type: multipart/form-data
```

**請求參數：**
```
report_user: 456
type: 1  # 1:一般檢舉, 2:系統問題, 3:匿名聊天檢舉
reason: 3  # 具體原因編號
title: 檢舉標題
content: 詳細說明檢舉原因
images[]: {file1}
images[]: {file2}
```

**成功回應 (201)：**
```json
{
  "success": true,
  "code": 201,
  "message": "舉報提交成功",
  "data": {
    "report": {
      "id": 123,
      "report_number": "R2024122001",
      "type": 1,
      "reason": 3,
      "title": "檢舉標題",
      "content": "詳細說明檢舉原因",
      "status": 1,
      "images": [
        {
          "id": 1,
          "url": "https://cdn.example.com/reports/123_1.jpg"
        }
      ],
      "created_at": "2024-12-20T10:30:00Z"
    },
    "notice": {
      "credit_score_deducted": 10,
      "estimated_review_time": "1-3個工作天"
    }
  }
}
```

**業務規則（提交時即刻扣分）：**

| `type` | 說明 | 提交時雙方扣分 | 查證屬實後 |
|--------|------|--------------|----------|
| `1` 一般檢舉 | 檢舉其他用戶違規行為 | 檢舉人 **-10 分**，被檢舉人 **-10 分** | 檢舉人補回 +10 分；管理員可酌情追加 |
| `2` 系統問題 | 回報網站功能問題（可附圖） | 無扣分 | 管理員可酌情給予 +N 分獎勵 |
| `3` 匿名聊天室檢舉 | 針對匿名聊天室違規 | 檢舉人 **-5 分**，被檢舉人 **-5 分** | 檢舉人補回 +5 分 |

> `credit_score_deducted` 回傳值會依 `type` 動態計算（type=1 為 10，type=3 為 5，type=2 為 0）。


```http
GET /api/v1/reports
Authorization: Bearer {access_token}
```

**查詢參數：**
```
status: 1|2  # 1:處理中, 2:處理完畢
type: 1|2|3
page: 1
per_page: 20
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "舉報記錄查詢成功",
  "data": {
    "reports": [
      {
        "id": 123,
        "report_number": "R2024122001",
        "type": 1,
        "reason": 3,
        "title": "檢舉標題",
        "status": 2,
        "admin_reply": "經查證屬實，已對違規用戶進行處理",
        "created_at": "2024-12-20T10:30:00Z",
        "updated_at": "2024-12-22T14:20:00Z"
      }
    ]
  }
}
```

#### 8.1.3 取消舉報
```http
DELETE /api/v1/reports/{report_id}
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "舉報已取消",
  "data": {
    "credit_score_refunded": 10
  }
}
```

---

## 9. 系統公告API

### 9.1 公告查詢

#### 9.1.1 獲取系統公告
```http
GET /api/v1/announcements
Authorization: Bearer {access_token}
```

**查詢參數：**
```
position: top|popup|sidebar
active_only: true|false
limit: 10
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "公告查詢成功",
  "data": {
    "announcements": [
      {
        "id": 1,
        "title": "聖誕節特別活動開始！",
        "content": "12月20日至12月31日期間，所有VIP方案享8折優惠！",
        "type": "success",
        "display_position": "top",
        "start_time": "2024-12-20T00:00:00Z",
        "end_time": "2024-12-31T23:59:59Z",
        "created_at": "2024-12-19T10:00:00Z"
      }
    ]
  }
}
```

#### 9.1.2 標記公告已讀
```http
POST /api/v1/announcements/{announcement_id}/read
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "code": 200,
  "message": "已標記為已讀"
}
```

---

## 9.5 後台管理員專用 API（Admin API）

> 所有後台 API 路由均需附帶有效的 `admin_token`，並通過 RBAC 權限驗證。

### 9.5.1 RBAC 角色管理

```http
# 取得所有角色與權限清單
GET /api/v1/admin/roles
Authorization: Bearer {admin_token}
```
**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "roles": [
      {
        "id": 1,
        "name": "super_admin",
        "display_name": "超級管理員",
        "permissions": ["members.delete", "settings.roles", "...所有權限"]
      },
      {
        "id": 2,
        "name": "admin",
        "display_name": "一般管理員",
        "permissions": ["members.view", "members.edit", "members.suspend", "members.adjust_score", "..."]
      },
      {
        "id": 3,
        "name": "cs",
        "display_name": "客服人員",
        "permissions": ["members.view", "reports.view", "reports.process"]
      }
    ]
  }
}
```

```http
# 指派管理員角色
PATCH /api/v1/admin/users/{admin_id}/role
Authorization: Bearer {admin_token}
Content-Type: application/json
```
**請求參數：**
```json
{ "data": { "role_id": 2 } }
```
> 僅 `super_admin` 角色可調用此 API（RBAC：`settings.roles`）

---

### 9.5.2 網站概況統計儀表板

```http
# 取得指定指標的統計圖表資料
GET /api/v1/admin/stats
Authorization: Bearer {admin_token}
```
**查詢參數：**
```
metric: new_registrations | active_users | paid_members   （必填）
range:  today | month                                     （必填）
gender: all | male | female                               （選填，預設 all）
format: json | csv                                        （選填，預設 json）
```

**`range=today` 成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "metric": "new_registrations",
    "range": "today",
    "x_axis": "hour",
    "series": [
      { "label": "all",    "data": [3,5,2,0,1,4,8,12,15,20,18,22,25,19,16,21,18,14,11,9,7,5,3,2] },
      { "label": "male",   "data": [1,2,1,0,0,2,4,6,8,11,9,11,13,10,8,10,9,7,5,4,3,2,1,1] },
      { "label": "female", "data": [2,3,1,0,1,2,4,6,7,9,9,11,12,9,8,11,9,7,6,5,4,3,2,1] }
    ],
    "total": { "all": 260, "male": 132, "female": 128 }
  }
}
```

**`range=month` 成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "metric": "new_registrations",
    "range": "month",
    "x_axis": "date",
    "period": { "start": "2024-12-01", "end": "2024-12-31" },
    "series": [
      { "label": "all",    "data": [120,95,88,110,"..."] },
      { "label": "male",   "data": [60,48,44,55,"..."] },
      { "label": "female", "data": [60,47,44,55,"..."] }
    ],
    "total": { "all": 3180, "male": 1590, "female": 1590 }
  }
}
```

```http
# CSV 匯出（支援最近 1 年資料）
GET /api/v1/admin/stats/export
Authorization: Bearer {admin_token}
```
**查詢參數：**
```
metric:     new_registrations | active_users | paid_members  （必填）
start_date: 2024-01-01   （必填，ISO 日期格式）
end_date:   2024-12-31   （必填）
```
**回應：**`Content-Type: text/csv`，檔名 `{metric}_{start}_{end}.csv`

```http
# 伺服器流量參數圖表
GET /api/v1/admin/stats/server-metrics
Authorization: Bearer {admin_token}
```
**查詢參數：**
```
range: today | month
```
**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "range": "today",
    "x_axis": "hour",
    "series": [
      { "label": "bandwidth_mbps",  "data": [12.3, 15.6, "..."] },
      { "label": "requests_per_min","data": [1200, 1450, "..."] },
      { "label": "cpu_usage_pct",   "data": [35, 42, "..."] },
      { "label": "memory_usage_pct","data": [58, 61, "..."] }
    ]
  }
}
```

---

### 9.5.3 會員列表後台管理

```http
# 取得會員列表（所有會員 / 近7日篩選）
GET /api/v1/admin/members
Authorization: Bearer {admin_token}
```
**查詢參數：**
```
recent_days: 7            （填入整數=近N天；不填=所有）
uid:         123          （UID 搜索）
nickname:    甜心          （暱稱含搜索）
email:       user@test.com （Email 精確搜索）
gender:      male | female
level:       0 | 1 | 2 | 3  （0=註冊會員 1=驗證 2=進階驗證 3=付費）
page:        1
per_page:    20
sort_by:     created_at | last_login_at | credit_score （預設 created_at DESC）
```
**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "members": [
      {
        "uid": 123,
        "nickname": "甜心寶貝",
        "gender": "female",
        "age": 23,
        "credit_score": 85,
        "level": 2,
        "level_label": "進階驗證會員",
        "last_login_at": "2024-12-20T09:15:00Z",
        "profile_views": 156,
        "registered_at": "2024-11-15T14:20:00Z",
        "email": "user@example.com",
        "status": "active"
      }
    ]
  },
  "pagination": { "current_page": 1, "per_page": 20, "total": 1542, "total_pages": 78 }
}
```

```http
# 查看會員個人頁面（管理員審查用）
GET /api/v1/admin/members/{user_id}/profile
Authorization: Bearer {admin_token}
```
**說明**：回傳與前台個人主頁相同的完整資料，但額外包含 Email、電話、驗證紀錄、誠信分數歷史。

```http
# 針對會員執行管理操作
PATCH /api/v1/admin/members/{user_id}/actions
Authorization: Bearer {admin_token}
Content-Type: application/json
```
**請求參數：**
```json
{
  "data": {
    "action": "adjust_score | suspend | unsuspend | change_level | require_reverify",
    "value": 10,
    "reason": "操作原因備注"
  }
}
```
| `action` 值 | 說明 | 所需權限 |
|---|---|---|
| `adjust_score` | 調整誠信分數（`value` 為正負整數） | `members.adjust_score` |
| `suspend` | 停權帳號 | `members.suspend` |
| `unsuspend` | 解除停權 | `members.suspend` |
| `change_level` | 手動調整會員級別（`value` 為目標 level） | `members.change_level` |
| `require_reverify` | 要求會員重新驗證 | `members.edit` |

```http
# 刪除會員帳號（僅最高權限 super_admin 可用）
DELETE /api/v1/admin/members/{user_id}
Authorization: Bearer {admin_token}
```
> RBAC 驗證：`members.delete`；操作寫入操作日誌

---

### 9.5.4 問題回報 / 檢舉 Ticket 管理

```http
# 取得案件列表
GET /api/v1/admin/tickets
Authorization: Bearer {admin_token}
```
**查詢參數：**
```
status:       1 | 2 | 3     （1=待處理 2=處理中 3=已處理；不填=全部）
type:         1 | 2 | 3     （1=一般檢舉 2=系統問題 3=匿名聊天室 4=取消訂閱申請）
ticket_number: R2024122000001
page:         1
per_page:     20
sort_by:      created_at_desc（預設）| updated_at_desc
```
**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "tickets": [
      {
        "id": 456,
        "ticket_number": "R2024122000001",
        "type": 1,
        "type_label": "一般檢舉",
        "title": "對方傳送騷擾訊息",
        "reporter": { "uid": 123, "nickname": "甜心寶貝" },
        "reported_user": { "uid": 789, "nickname": "金主大叔" },
        "status": 1,
        "status_label": "待處理",
        "created_at": "2024-12-20T10:30:00Z"
      }
    ]
  },
  "pagination": { "current_page": 1, "per_page": 20, "total": 45 }
}
```

```http
# 查看特定案件詳情（含追蹤留言）
GET /api/v1/admin/tickets/{ticket_id}
Authorization: Bearer {admin_token}
```

```http
# 更新案件狀態
PATCH /api/v1/admin/tickets/{ticket_id}
Authorization: Bearer {admin_token}
Content-Type: application/json
```
**請求參數：**
```json
{
  "data": {
    "status": 3,
    "admin_reply": "經查證屬實，已對違規用戶扣除誠信分數 15 分",
    "score_adjustments": [
      { "user_id": 789, "delta": -15, "reason": "騷擾行為屬實" },
      { "user_id": 123, "delta": 10,  "reason": "補回檢舉扣分" }
    ]
  }
}
```

```http
# 管理員對案件新增追蹤留言
POST /api/v1/admin/tickets/{ticket_id}/followups
Authorization: Bearer {admin_token}
Content-Type: application/json
```
**請求參數：**
```json
{ "data": { "content": "補充說明：已致電當事人確認" } }
```

---

### 9.5.5 聊天紀錄後台查詢

```http
# 關鍵字搜尋聊天紀錄
GET /api/v1/admin/chat-logs/search
Authorization: Bearer {admin_token}
```
**查詢參數：**
```
keyword:  15000          （必填，關鍵字）
user_id:  123            （選填，限定某用戶）
page:     1
per_page: 20
```
**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "results": [
      {
        "id": 9876,
        "sender": { "uid": 123, "nickname": "甜心寶貝" },
        "receiver": { "uid": 456, "nickname": "金主大叔" },
        "content": "我每月給你 15000 怎麼樣",
        "sent_at": "2024-12-20T14:22:00Z"
      }
    ]
  }
}
```

```http
# 查詢兩位用戶之間的完整對話
GET /api/v1/admin/chat-logs/conversation
Authorization: Bearer {admin_token}
```
**查詢參數：**
```
user_a: 123   （必填）
user_b: 456   （必填）
page:   1
per_page: 50
```

```http
# 匯出特定用戶所有對話串（CSV）
GET /api/v1/admin/chat-logs/export
Authorization: Bearer {admin_token}
```
**查詢參數：**
```
user_id: 123  （必填）
format:  csv  （預設 csv）
```
**回應：**`Content-Type: text/csv`，包含 sender_id、sender_nickname、receiver_id、receiver_nickname、content、sent_at  
> 所需權限：`chat.export`

---

### 9.5.6 匿名聊天室後台查詢

```http
# 查詢匿名聊天室訊息（可用真實用戶身份篩選）
GET /api/v1/admin/anonymous-chat/messages
Authorization: Bearer {admin_token}
```
**查詢參數：**
```
channel_id: 1
user_id:    123      （可選，過濾特定真實用戶的匿名發言）
keyword:    違規文字  （關鍵字搜尋）
page:       1
per_page:   50
```
**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "messages": [
      {
        "id": 555,
        "channel": "台北地區",
        "anon_alias": "神秘訪客#0421",
        "real_user": { "uid": 123, "nickname": "甜心寶貝" },
        "content": "訊息內容",
        "created_at": "2024-12-20T15:00:00Z",
        "is_deleted": false
      }
    ]
  }
}
```

```http
# 刪除匿名聊天訊息
DELETE /api/v1/admin/anonymous-chat/messages/{message_id}
Authorization: Bearer {admin_token}
```

---

### 9.5.7 支付紀錄後台查詢

```http
# 取得支付紀錄列表
GET /api/v1/admin/payments
Authorization: Bearer {admin_token}
```
**查詢參數：**
```
user_id:      123
status:       pending | paid | failed | refunded | cancelled
payment_type: subscription | trial | point | credit_card_verify
start_date:   2024-12-01
end_date:     2024-12-31
page:         1
per_page:     20
```
**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "payments": [
      {
        "id": 9001,
        "order_number": "ORD20241220001",
        "user": { "uid": 123, "nickname": "甜心寶貝" },
        "payment_type": "subscription",
        "plan": "monthly",
        "amount": 499,
        "amount_paid": 449,
        "payment_method": "Credit",
        "status": "paid",
        "gateway_order_id": "20241220ABC123",
        "paid_at": "2024-12-20T10:35:00Z"
      }
    ],
    "summary": {
      "total_paid_amount": 45000,
      "total_transactions": 90,
      "failed_count": 5
    }
  }
}
```

---

### 9.5.8 SEO 功能後台管理

```http
# 取得廣告跳轉連結列表
GET /api/v1/admin/seo/redirect-links
Authorization: Bearer {admin_token}
```

```http
# 新增廣告跳轉連結
POST /api/v1/admin/seo/redirect-links
Authorization: Bearer {admin_token}
Content-Type: application/json
```
**請求參數：**
```json
{
  "data": {
    "slug":       "ig-ad-2024-dec",
    "target_url": "https://mimeet.tw/register",
    "campaign":   "Instagram聖誕活動",
    "source":     "instagram",
    "count_mode": "register",
    "expires_at": "2025-01-31T23:59:59Z"
  }
}
```
**成功回應 (201)：**
```json
{
  "success": true,
  "data": {
    "link": {
      "id": 10,
      "slug": "ig-ad-2024-dec",
      "redirect_url": "https://mimeet.tw/go/ig-ad-2024-dec",
      "target_url": "https://mimeet.tw/register",
      "click_count": 0,
      "register_count": 0,
      "is_active": true
    }
  }
}
```

```http
# 取得連結點擊統計
GET /api/v1/admin/seo/redirect-links/{link_id}/stats
Authorization: Bearer {admin_token}
```
**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "link_id": 10,
    "slug": "ig-ad-2024-dec",
    "total_clicks": 1240,
    "total_registers": 87,
    "conversion_rate": "7.02%",
    "daily_stats": [
      { "date": "2024-12-20", "clicks": 120, "registers": 9 }
    ]
  }
}
```

```http
# 取得前台頁面 SEO meta tag 列表
GET /api/v1/admin/seo/page-meta
Authorization: Bearer {admin_token}
```

```http
# 更新特定頁面 SEO meta tag
PATCH /api/v1/admin/seo/page-meta/{page_key}
Authorization: Bearer {admin_token}
Content-Type: application/json
```
**請求參數：**
```json
{
  "data": {
    "title":          "MiMeet - 台灣高端交友平台",
    "description":    "透過誠信分數系統，找到真實可信賴的另一半",
    "keywords":       "台灣交友,高端交友,誠信分數",
    "og_title":       "MiMeet - 台灣高端交友平台",
    "og_description": "透過誠信分數系統，找到真實可信賴的另一半",
    "og_image":       "https://cdn.mimeet.tw/og-image.jpg"
  }
}
```

---

### 9.5.9 訂閱折扣設定（後台）

```http
# 取得各方案目前折扣設定
GET /api/v1/admin/subscription/discounts
Authorization: Bearer {admin_token}
```

```http
# 更新某方案折扣
PATCH /api/v1/admin/subscription/discounts/{plan_type}
Authorization: Bearer {admin_token}
Content-Type: application/json
```
**請求參數：**
```json
{
  "data": {
    "discount_type":  "percentage",
    "discount_value": 0.85,
    "original_price": 1499,
    "start_at":       "2024-12-20T00:00:00Z",
    "end_at":         "2025-01-05T23:59:59Z",
    "note":           "聖誕跨年優惠"
  }
}
```

```http
# 取得 / 更新體驗價設定
GET    /api/v1/admin/subscription/trial-config
PATCH  /api/v1/admin/subscription/trial-config
Authorization: Bearer {admin_token}
```
**PATCH 請求參數：**
```json
{
  "data": {
    "price":         199,
    "duration_days": 30,
    "is_active":     true
  }
}
```

---

## 10. 前台補充 API

### 10.1 收藏/關注 API

```http
# 收藏（關注）某用戶
POST /api/v1/users/{user_id}/follow
Authorization: Bearer {access_token}
```
**成功回應 (201)：**
```json
{ "success": true, "code": 201, "message": "已加入收藏", "data": { "followed": true } }
```

```http
# 取消收藏（取消關注）
DELETE /api/v1/users/{user_id}/follow
Authorization: Bearer {access_token}
```

```http
# 取得我的收藏列表
GET /api/v1/users/me/following
Authorization: Bearer {access_token}
```
**查詢參數：**`nickname=甜心&page=1&per_page=20`

**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "following": [
      {
        "uid": 456,
        "nickname": "甜心寶貝",
        "avatar": "https://cdn.example.com/avatars/456.jpg",
        "age": 23,
        "credit_score": 85,
        "last_active_at": "2024-12-20T09:00:00Z",
        "followed_at": "2024-12-15T14:00:00Z"
      }
    ]
  },
  "pagination": { "current_page": 1, "per_page": 20, "total": 18 }
}
```

---

### 10.2 誰來看我 API（訪客名單）

```http
# 取得造訪我個人主頁的訪客名單
GET /api/v1/users/me/visitors
Authorization: Bearer {access_token}
```
**查詢參數：**`page=1&per_page=20`

**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "visitors": [
      {
        "uid": 789,
        "nickname": "金主大叔",
        "avatar": "https://cdn.example.com/avatars/789.jpg",
        "age": 38,
        "credit_score": 91,
        "visited_at": "2024-12-20T14:30:00Z",
        "visited_at_human": "3小時前"
      }
    ],
    "total_visitors_90days": 156
  },
  "pagination": { "current_page": 1, "per_page": 20, "total": 42 }
}
```

---

### 10.3 取消訂閱申請 API

```http
# 提交取消訂閱申請
POST /api/v1/subscriptions/cancel-request
Authorization: Bearer {access_token}
Content-Type: application/json
```
**請求參數：**
```json
{ "data": { "reason": "暫時不需要此服務" } }
```
**成功回應 (201)：**
```json
{
  "success": true,
  "code": 201,
  "message": "取消申請已送出",
  "data": {
    "ticket_number": "R2024122000042",
    "subscription_expires_at": "2025-01-19T23:59:59Z",
    "notice": "服務將持續至訂閱到期日，到期後不自動續費"
  }
}
```

---

### 10.4 歷史回報紀錄 API

```http
# 取得用戶自己的歷史回報紀錄
GET /api/v1/reports/history
Authorization: Bearer {access_token}
```
**查詢參數：**`status=1|2|3&type=1|2|3|4&page=1&per_page=20`

**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "reports": [
      {
        "id": 123,
        "ticket_number": "R2024122000001",
        "type": 1,
        "type_label": "一般檢舉",
        "title": "對方傳送騷擾訊息",
        "status": 3,
        "status_label": "已處理",
        "admin_reply": "經查證屬實，已對違規用戶進行處理",
        "created_at": "2024-12-20T10:30:00Z",
        "processed_at": "2024-12-22T14:00:00Z",
        "followups_count": 1
      }
    ]
  }
}
```

```http
# 針對既有案號補充說明
POST /api/v1/reports/{report_id}/followups
Authorization: Bearer {access_token}
Content-Type: application/json
```
**請求參數：**
```json
{ "data": { "content": "補充：對方又再次發送訊息" } }
```

---

### 10.5 體驗訂閱 API

```http
# 查詢目前體驗價方案設定
GET /api/v1/subscription/trial
Authorization: Bearer {access_token}
```
**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "trial_available": true,
    "price": 199,
    "duration_days": 30,
    "is_eligible": true,
    "notice": "每位會員限購一次，購買後不可退款，不自動續費"
  }
}
```
> `is_eligible: false` 時表示此帳號已使用過體驗訂閱

```http
# 購買體驗訂閱（串接綠界）
POST /api/v1/subscription/trial/purchase
Authorization: Bearer {access_token}
Content-Type: application/json
```
**請求參數：**
```json
{ "data": { "payment_method": "green_world", "return_url": "https://app.example.com/subscription/callback" } }
```
**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "order_number": "ORD20241220099",
    "payment_url": "https://payment.greenworld.com.tw/...",
    "amount": 199,
    "expires_in_minutes": 15
  }
}
```

---

### 10.6 匿名聊天室 API

```http
# 取得頻道列表
GET /api/v1/anonymous-chat/channels
Authorization: Bearer {access_token}
```

```http
# 取得頻道訊息（分頁）
GET /api/v1/anonymous-chat/channels/{channel_id}/messages
Authorization: Bearer {access_token}
```
**查詢參數：**`before_id=9999&limit=50`（往前翻頁）

```http
# 發送匿名訊息
POST /api/v1/anonymous-chat/channels/{channel_id}/messages
Authorization: Bearer {access_token}
Content-Type: application/json
```
**請求參數：**
```json
{ "data": { "content": "大家好！" } }
```
**成功回應 (201)：**
```json
{
  "success": true,
  "data": {
    "message": {
      "id": 12345,
      "anon_alias": "神秘訪客#0421",
      "content": "大家好！",
      "created_at": "2024-12-20T16:00:00Z"
    }
  }
}
```

---

### 10.7 通知 API

#### 10.7.1 取得通知列表
```http
GET /api/v1/me/notifications
Authorization: Bearer {access_token}
```

**查詢參數：**

| 參數 | 型態 | 說明 |
|------|------|------|
| `is_read` | int | `0`=未讀、`1`=已讀、不傳=全部 |
| `page` | int | 預設 1 |
| `per_page` | int | 預設 20，最大 50 |

**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "unread_count": 5,
    "notifications": [
      {
        "id": 301,
        "type": "new_message",
        "title": "新訊息",
        "body": "成功人士 傳了一則訊息給你",
        "action_url": "/app/messages/88",
        "is_read": false,
        "created_at": "2025-01-15T10:30:00Z",
        "created_at_human": "3 分鐘前"
      }
    ]
  },
  "pagination": { "current_page": 1, "per_page": 20, "total": 18, "total_pages": 1 }
}
```

**`type` 枚舉值：**

| type | 說明 |
|------|------|
| `new_message` | 新聊天訊息 |
| `new_visitor` | 有人查看了你的資料 |
| `new_follower` | 有人收藏了你 |
| `date_invite` | 收到約會邀請 |
| `date_accepted` | 約會邀請被接受 |
| `date_verified` | 約會驗證成功 |
| `credit_changed` | 誠信分數異動 |
| `subscription_expiry` | 訂閱 3 天後到期提醒 |
| `verification_result` | 進階驗證審核結果 |
| `ticket_replied` | 問題回報有新回覆 |
| `announcement` | 平台系統公告 |

---

#### 10.7.2 取得未讀通知數（Badge 用）
```http
GET /api/v1/me/notifications/unread-count
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{ "success": true, "data": { "unread_count": 5 } }
```

---

#### 10.7.3 標記單筆已讀
```http
PATCH /api/v1/me/notifications/{id}/read
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{ "success": true }
```

---

#### 10.7.4 全部標記已讀
```http
POST /api/v1/me/notifications/read-all
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{ "success": true, "data": { "marked_count": 5 } }
```

---

### 10.8 停權申訴 API（Sprint 8 更新）

#### 10.8.1 提交申訴
```http
POST /api/v1/me/appeal
Authorization: Bearer {access_token}
Content-Type: multipart/form-data
```
middleware: auth:sanctum（停權用戶也可呼叫）

**請求參數（multipart/form-data）：**
- `reason`: string（必填，max:500）
- `images[]`: file（選填，最多 3 張，每張 max 5MB，jpg/png/webp）

**成功回應 (201)：**
```json
{
  "success": true,
  "data": {
    "ticket_no": "A202600001",
    "message": "申訴已送出，我們將在 3 個工作天內回覆"
  }
}
```

**錯誤回應：**
```json
// 非停權用戶
{ "success": false, "error": { "code": "NOT_SUSPENDED", "message": "帳號目前非停權狀態" } }

// 同一停權期間重複提交
{ "success": false, "error": { "code": "APPEAL_EXISTS", "message": "此停權期間已有進行中的申訴" } }
```

#### 10.8.2 取得我的申訴狀態
```http
GET /api/v1/me/appeal/current
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "ticket_no": "A202600001",
    "status": "pending",
    "submitted_at": "2026-04-08T10:00:00Z",
    "admin_reply": null,
    "replied_at": null
  }
}
```

> **業務規則：** 申訴建立後在 `reports` 表以 `type = 'appeal'` 儲存，reporter_id = reported_user_id = 自己。觸發 Admin 後台通知。同一停權期間限提交一次申訴。

---

### 10.9 訂閱自動續訂設定

```http
PATCH /api/v1/me/subscription/auto-renew
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{ "auto_renew": true }
```

**成功回應 (200)：**
```json
{
  "success": true,
  "data": { "auto_renew": true, "message": "已開啟自動續訂" }
}
```

**非付費會員 (403)：**
```json
{ "success": false, "error": { "code": "4032", "message": "無有效訂閱" } }
```

---

### 10.10 隱私設定 API（Sprint 8 新增）

#### 10.10.1 取得隱私設定
```http
GET /api/v1/me/privacy
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "show_online_status": true,
    "allow_profile_visits": true,
    "show_in_search": true,
    "show_last_active": true,
    "allow_stranger_message": true
  }
}
```

#### 10.10.2 更新隱私設定（單項）
```http
PATCH /api/v1/me/privacy
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{ "key": "show_in_search", "value": false }
```
key 可選值：`show_online_status` / `allow_profile_visits` / `show_in_search` / `show_last_active` / `allow_stranger_message`

**成功回應 (200)：**
```json
{ "success": true, "data": { "key": "show_in_search", "value": false } }
```

---

### 10.11 帳號刪除 API（Sprint 8 補完）

#### 10.11.1 提交刪除申請
```http
POST /api/v1/me/delete-account
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{ "password": "用戶當前密碼（用於驗證身份）" }
```

**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "status": "pending_deletion",
    "delete_at": "2026-04-15T03:00:00Z",
    "message": "您的帳號將於 7 天後永久刪除，期間可隨時取消"
  }
}
```

**錯誤回應：**
```json
// 密碼錯誤
{ "success": false, "error": { "code": "PASSWORD_INCORRECT", "message": "密碼不正確" } }

// 已有進行中申請
{ "success": false, "error": { "code": "DELETION_PENDING", "message": "已有待執行的刪除申請" } }
```

#### 10.11.2 取消刪除申請
```http
DELETE /api/v1/me/delete-account
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{ "success": true, "data": { "status": "active", "message": "刪除申請已取消，帳號恢復正常" } }
```

**錯誤回應：**
```json
{ "success": false, "error": { "code": "NO_PENDING_DELETION", "message": "目前沒有待執行的刪除申請" } }
```

---

### 10.12 免打擾模式 API（F22 Part B）

全域時段型 DND：使用者設定「每天 22:00 → 08:00 不要收推播」等規則，後端判斷當下是否處於時段內，跳過 FCM 推播。WebSocket 廣播 + 站內通知 (`notifications` 表) **仍會** 寫入與發送。

**資料表：** `users` 新增 `dnd_enabled` BOOL、`dnd_start` TIME、`dnd_end` TIME。`dnd_start > dnd_end` 代表跨午夜時段。

#### 10.12.1 取得 DND 設定
```http
GET /api/v1/me/dnd
Authorization: Bearer {access_token}
```

**成功回應 (200)：**
```json
{
  "success": true,
  "data": {
    "dnd_enabled": true,
    "dnd_start": "22:00",
    "dnd_end": "08:00",
    "currently_active": true
  }
}
```

`currently_active`：後端依據伺服器時區（Asia/Taipei）計算目前是否在時段內，方便前端直接套用提示 UI。

#### 10.12.2 更新 DND 設定
```http
PATCH /api/v1/me/dnd
Authorization: Bearer {access_token}
Content-Type: application/json
```

**請求參數：**
```json
{ "dnd_enabled": true, "dnd_start": "22:00", "dnd_end": "08:00" }
```

**驗證：**
- `dnd_enabled`：必填，boolean
- `dnd_start` / `dnd_end`：`dnd_enabled=true` 時必填，格式 `H:i`（如 `22:00`）
- 允許 `dnd_start > dnd_end`（跨午夜）

**成功回應 (200)：** 格式同 §10.12.1。

---

## 11. 點數系統（F40）

> **實作日期：** 2026-04-20  
> **資料表：** `point_packages` / `point_orders` / `point_transactions` + `users.points_balance` + `users.stealth_until`  
> **綠界付款：** trade_no 以 `PTS_` 前綴與訂閱（`SUB_`）區分。Sandbox/Staging 走 `/payments/ecpay/point-mock`。

### 11.1 取得點數方案
```http
GET /api/v1/points/packages
Authorization: Bearer {access_token}
```
**成功回應 200：**
```json
{
  "success": true,
  "data": [
    { "id": 1, "slug": "pack_50", "name": "輕量包", "points": 50, "bonus_points": 0, "total_points": 50, "price": 150, "cost_per_point": 3.0, "description": "小額嘗鮮", "sort_order": 1 }
  ]
}
```

### 11.2 購買點數（產生訂單 + 跳轉付款）
```http
POST /api/v1/points/purchase
Authorization: Bearer {access_token}
Content-Type: application/json
```
**請求：**
```json
{ "package_slug": "pack_150", "payment_method": "credit_card" }
```
**成功回應 201：**
```json
{
  "success": true,
  "data": {
    "order": { "id": 42, "trade_no": "PTS_...", "points": 160, "amount": 390, "status": "pending" },
    "payment_url": "https://api.mimeet.online/api/v1/payments/ecpay/point-mock?trade_no=PTS_..."
  }
}
```
前端收到 `payment_url` 用 `window.location.href` 跳轉（非 Vue Router，因為是跨域）。

### 11.3 餘額
```http
GET /api/v1/points/balance
Authorization: Bearer {access_token}
```
**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "points_balance": 160,
    "stealth_until": null,
    "stealth_active": false
  }
}
```

### 11.4 交易紀錄（分頁）
```http
GET /api/v1/points/history?page=1&per_page=20
Authorization: Bearer {access_token}
```
**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "transactions": [
      { "id": 1, "type": "purchase", "amount": 160, "balance_after": 160, "feature": null,
        "description": "購買點數...", "reference_id": 42, "created_at": "2026-04-20T..." }
    ]
  },
  "meta": { "page": 1, "per_page": 20, "total": 1, "last_page": 1 }
}
```

### 11.5 `/auth/me` 回傳擴充（F40）

`GET /api/v1/auth/me` 現在額外包含：

| 欄位 | 說明 |
|------|------|
| `points_balance` | 目前點數餘額 |
| `stealth_until` | F42 隱身到期時間（ISO 8601）|
| `stealth_active` | 是否目前為隱身狀態 |
| `subscription` | 當前有效訂閱（含 `plan_name` / `expires_at` / `days_remaining` / `auto_renew`），無則為 null |

---

## 12. 前台公開 SEO 跳轉路由

```http
# 廣告跳轉連結（無需登入）
GET /go/{slug}
```
**行為**：
1. 查詢 `seo_redirect_links` 確認 `slug` 存在且 `is_active=true` 且未過期
2. 若 `count_mode=click`：立即計數 `click_count++`，並寫入 `seo_click_logs`
3. 若 `count_mode=register`：僅寫入 `seo_click_logs`，待用戶完成註冊後由 Webhook 補計 `register_count++`
4. HTTP 301 / 302 跳轉至 `target_url`

---

## 12. 錯誤處理與狀態碼

### 10.1 HTTP狀態碼規範

| 狀態碼 | 含義 | 使用場景 |
|--------|------|----------|
| 200 | OK | 請求成功 |
| 201 | Created | 資源創建成功 |
| 204 | No Content | 請求成功但無返回內容 |
| 400 | Bad Request | 請求參數錯誤 |
| 401 | Unauthorized | 未認證或認證失效 |
| 403 | Forbidden | 已認證但無權限 |
| 404 | Not Found | 資源不存在 |
| 409 | Conflict | 資源衝突 |
| 422 | Unprocessable Entity | 參數驗證失敗 |
| 429 | Too Many Requests | 請求頻率過高 |
| 500 | Internal Server Error | 服務器內部錯誤 |

### 10.2 錯誤代碼規範

#### 10.2.1 認證相關錯誤 (1000-1999)
```json
{
  "1001": "invalid_credentials",
  "1002": "account_not_verified", 
  "1003": "account_suspended",
  "1004": "token_expired",
  "1005": "token_invalid",
  "1006": "account_not_found",
  "1007": "password_too_weak",
  "1008": "email_already_exists",
  "1009": "verification_code_invalid",
  "1010": "verification_code_expired"
}
```

#### 10.2.2 業務邏輯錯誤 (2000-2999)
```json
{
  "2001": "insufficient_credit_score",
  "2002": "user_blocked",
  "2003": "message_limit_exceeded", 
  "2004": "subscription_required",
  "2005": "verification_required",
  "2006": "photo_upload_failed",
  "2007": "date_invitation_conflict",
  "2008": "qr_code_expired",
  "2009": "location_verification_failed",
  "2010": "payment_failed"
}
```

#### 10.2.3 系統錯誤 (5000-5999)
```json
{
  "5001": "database_connection_error",
  "5002": "external_service_unavailable",
  "5003": "file_storage_error", 
  "5004": "notification_service_error",
  "5005": "cache_service_error"
}
```

### 10.3 錯誤回應範例

#### 10.3.1 參數驗證錯誤 (422)
```json
{
  "success": false,
  "code": 422,
  "message": "參數驗證失敗",
  "error": {
    "type": "validation_error",
    "details": [
      {
        "field": "email",
        "message": "Email格式不正確",
        "code": "invalid_email_format",
        "value": "invalid-email"
      },
      {
        "field": "age", 
        "message": "年齡必須在18-100之間",
        "code": "age_out_of_range",
        "value": 15
      }
    ]
  }
}
```

#### 10.3.2 業務邏輯錯誤 (400)
```json
{
  "success": false,
  "code": 400,
  "message": "誠信分數不足，無法發送消息",
  "error": {
    "type": "business_logic_error",
    "code": "insufficient_credit_score",
    "details": {
      "current_score": 45,
      "required_score": 60,
      "suggestion": "完成身份驗證可獲得15分"
    }
  }
}
```

#### 10.3.3 權限錯誤 (403)
```json
{
  "success": false,
  "code": 403,
  "message": "需要VIP會員才能使用此功能",
  "error": {
    "type": "permission_error",
    "code": "subscription_required", 
    "details": {
      "required_subscription": "premium",
      "current_subscription": "free",
      "upgrade_url": "/api/v1/subscription/plans"
    }
  }
}
```

---

## 13. API安全與限流

### 11.1 認證機制

#### 11.1.1 JWT Token格式
```json
{
  "header": {
    "typ": "JWT",
    "alg": "HS256"
  },
  "payload": {
    "sub": "123",
    "email": "user@example.com", 
    "role": "user",
    "exp": 1703123456,
    "iat": 1703120456,
    "jti": "token_unique_id"
  }
}
```

#### 11.1.2 請求簽名驗證
```javascript
// 請求簽名算法
function generateSignature(method, url, params, timestamp, secret) {
    const stringToSign = [
        method.toUpperCase(),
        url,
        JSON.stringify(params),
        timestamp
    ].join('\n');
    
    return crypto.createHmac('sha256', secret)
        .update(stringToSign)
        .digest('hex');
}

// 請求Header
{
    "Authorization": "Bearer {access_token}",
    "X-Timestamp": "1703123456",
    "X-Signature": "a1b2c3d4e5f6...",
    "Content-Type": "application/json"
}
```

### 11.2 限流策略

#### 11.2.1 限流規則
```yaml
# 全局限流
global_rate_limit:
  requests_per_minute: 1000
  requests_per_hour: 10000

# 用戶級別限流
user_rate_limit:
  requests_per_minute: 100
  requests_per_hour: 1000

# API特定限流  
api_specific_limits:
  "/api/v1/auth/login":
    requests_per_minute: 5
    requests_per_hour: 20
    
  "/api/v1/chats/*/messages":
    requests_per_minute: 30
    requests_per_hour: 500
    
  "/api/v1/users/search":
    requests_per_minute: 20
    requests_per_hour: 200
```

#### 11.2.2 限流回應
```json
{
  "success": false,
  "code": 429,
  "message": "請求頻率過高，請稍後再試",
  "error": {
    "type": "rate_limit_exceeded",
    "details": {
      "limit": 100,
      "remaining": 0,
      "reset_time": "2024-12-20T10:31:00Z",
      "retry_after": 60
    }
  }
}
```

### 11.3 安全Headers

#### 11.3.1 必需的安全Headers
```http
# 請求Headers
Authorization: Bearer {token}
X-API-Version: v1
X-Client-Version: 1.0.0
X-Request-ID: {uuid}
X-Timestamp: {unix_timestamp}
Content-Type: application/json
User-Agent: DatingApp/1.0.0 (iOS; iPhone; 15.0)

# 回應Headers  
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 99
X-RateLimit-Reset: 1703123456
X-Request-ID: {uuid}
Content-Security-Policy: default-src 'self'
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
```

---

## 14. API測試與文檔

### 12.1 API測試規範

#### 12.1.1 Postman測試集合
```json
{
  "info": {
    "name": "Dating Platform API",
    "version": "v1.0.0"
  },
  "variable": [
    {
      "key": "base_url",
      "value": "https://api.example.com"
    },
    {
      "key": "access_token", 
      "value": ""
    }
  ],
  "auth": {
    "type": "bearer",
    "bearer": [
      {
        "key": "token",
        "value": "{{access_token}}"
      }
    ]
  }
}
```

#### 12.1.2 自動化測試
```javascript
// 測試腳本範例
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has success field", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('success');
    pm.expect(jsonData.success).to.eql(true);
});

pm.test("Response data structure is correct", function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData.data).to.have.property('user');
    pm.expect(jsonData.data.user).to.have.property('id');
    pm.expect(jsonData.data.user).to.have.property('nickname');
});

// 設定環境變數
if (pm.response.code === 200) {
    const jsonData = pm.response.json();
    pm.environment.set("user_id", jsonData.data.user.id);
}
```

### 12.2 API文檔生成

#### 12.2.1 OpenAPI規範
```yaml
openapi: 3.0.3
info:
  title: Dating Platform API
  description: 交友約會平台API文檔
  version: 1.0.0
  contact:
    name: API Support
    email: api-support@example.com
    
servers:
  - url: https://api.example.com/api/v1
    description: Production server
  - url: https://staging-api.example.com/api/v1
    description: Staging server

components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
      
  schemas:
    User:
      type: object
      properties:
        id:
          type: integer
          example: 123
        nickname:
          type: string
          example: "甜心寶貝"
        age:
          type: integer
          minimum: 18
          maximum: 100
          example: 23
          
paths:
  /users/{user_id}:
    get:
      summary: 獲取用戶資料
      security:
        - bearerAuth: []
      parameters:
        - name: user_id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: 成功獲取用戶資料
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/User'
```

---

## 15. 總結

### 13.1 API設計亮點

1. **RESTful標準化**：嚴格遵循REST設計原則
2. **一致性設計**：統一的請求/回應格式和錯誤處理
3. **安全性保障**：JWT認證、請求簽名、限流保護
4. **擴展性設計**：版本控制、分頁、包含關聯資料
5. **實時通訊**：WebSocket支援實時聊天和狀態更新

### 13.2 開發指南

1. **認證流程**：註冊 → Email驗證 → 獲取Token → API調用
2. **錯誤處理**：統一錯誤格式，詳細錯誤碼和描述
3. **限流策略**：不同API不同限制，合理分配請求頻率
4. **安全防護**：HTTPS、Token認證、請求簽名、參數驗證

### 13.3 實施優先級

**Phase 1: 核心API**
- 用戶認證和管理
- 基礎聊天功能
- 搜尋和配對

**Phase 2: 業務API**
- QR碼約會驗證
- 動態內容管理
- 支付訂閱功能

**Phase 3: 完善API**
- 舉報檢舉系統
- 系統公告管理
- 統計分析接口

---

## 16. 媒體上傳 API

> 本節整合自 DEV-008 Part A。

### 16.1 照片上傳端點

> **⚠️ 實作說明：** 目前無統一 `/uploads` 端點。各類照片使用專用端點上傳。

#### 個人相冊 / 驗證照片上傳

```
POST /api/v1/users/me/photos
Authorization: Bearer {access_token}
Content-Type: multipart/form-data
```

| 欄位 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `photo` | File | 是 | 圖片檔案（jpeg/png/gif/webp，最大 5MB） |

> **注意：** 上傳 field name 為 `photo`（非 `file`）。

**成功回應 (201)：**
```json
{
  "success": true,
  "data": {
    "photo": {
      "url": "https://api.mimeet.online/storage/photos/3/xxx.jpg",
      "is_primary": false,
      "created_at": "2026-04-18T10:00:00Z"
    }
  }
}
```

**錯誤 422（格式/大小不符）：**
```json
{ "success": false, "code": 422, "message": "檔案格式不合法（偽裝 MIME 偵測）" }
```

#### 頭像上傳

```
POST /api/v1/users/me/avatars
Authorization: Bearer {access_token}
Content-Type: multipart/form-data
```

| 欄位 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `photo` | File | 是 | 圖片檔案（jpeg/png/webp，最大 5MB） |

---

### 16.2 刪除媒體

```
DELETE /api/v1/uploads
```

**Request Body：**
```json
{ "url": "https://cdn.mimeet.tw/photos/gallery/a1b2c3/img_1234567890.webp", "context": "profile_photo" }
```

> 系統驗證該 URL 確實屬於當前登入用戶才允許刪除。

---

### 16.3 取得驗證隨機碼（女性進階驗證）

```
POST /api/v1/me/verification-photo/request
Authorization: Bearer {access_token}
```

> 僅限女性會員（`gender=female`），已通過 Lv1.5 驗證者會回傳 422。

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "verification_id": 42,
    "random_code": "AB1C2D",
    "expires_at": "2026-04-18T10:10:00Z",
    "remaining_seconds": 600
  }
}
```

> 每次呼叫重新生成 6 位英數隨機碼，10 分鐘有效。舊的 pending_code 自動過期。

---

### 16.4 提交驗證照片（女性進階驗證）

```
POST /api/v1/me/verification-photo/upload
Authorization: Bearer {access_token}
Content-Type: application/json
```

**流程：**
1. 先呼叫 `POST /api/v1/users/me/photos`（field: `photo`）上傳照片取得 URL
2. 再呼叫此端點提交 URL + 隨機碼

**Request Body：**
```json
{ "photo_url": "https://api.mimeet.online/storage/photos/3/xxx.jpg", "random_code": "AB1C2D" }
```

**成功回應 200：**
```json
{
  "success": true,
  "data": { "status": "pending_review", "message": "照片已送出，審核通常在 24 小時內完成", "submitted_at": "2026-04-18T10:00:00Z" }
}
```

---

### 16.5 查詢驗證狀態

```
GET /api/v1/me/verification-photo/status
Authorization: Bearer {access_token}
```

**成功回應 200：**
```json
{
  "success": true,
  "data": {
    "status": "pending_review",
    "submitted_at": "2026-04-18T10:00:00Z",
    "reviewed_at": null,
    "reject_reason": null
  }
}
```

---

*