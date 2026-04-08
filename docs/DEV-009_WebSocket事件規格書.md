# [DEV-009] MiMeet WebSocket 事件規格書

**文檔版本：** v1.0  
**建立日期：** 2026年3月  
**適用範圍：** 前台 Vue.js SPA ↔ Laravel Reverb（WebSocket Server）  
**前置文件：** DEV-001（技術架構）、DEV-003（前端規範）、DEV-004（後端規範）、API-001  
**審核狀態：** 待確認

---

## 1. 架構總覽

```
前台 Vue.js SPA
  └─ Laravel Echo (socket.io-client)
        │
        │ WSS（WebSocket Secure）
        ▼
  CloudFlare（Pass-through WSS）
        │
        ▼
  Nginx（Reverse Proxy → port 8080）
        │
        ▼
  Laravel Reverb（WebSocket Server）
        │
        ├─ private-chat.{conversationId}     ← 私人聊天頻道
        ├─ private-user.{userId}             ← 個人通知頻道
        ├─ presence-online                   ← 全域在線狀態
        └─ private-anon-chat.{channelId}     ← 匿名聊天室（Phase 2）
```

### 1.1 技術選型確認

| 項目 | 選型 | 版本 |
|------|------|------|
| WS Server | Laravel Reverb | 1.x |
| 前端 WS 客戶端 | Laravel Echo + socket.io-client | Echo 1.x + Socket.IO 4.x |
| 廣播驅動 | Reverb（取代 Pusher） | — |
| 前端認證 | Sanctum Cookie（SPA 模式） | — |

---

## 2. 頻道一覽表

| 頻道名稱 | 類型 | 訂閱者 | 用途 | MVP |
|---------|------|-------|------|-----|
| `private-chat.{conversationId}` | Private | 對話雙方 | 即時訊息、訊息撤回 | ✅ |
| `private-user.{userId}` | Private | 用戶本人 | 通知、未讀數、分數異動 | ✅ |
| `presence-online` | Presence | 所有已登入用戶 | 顯示對方是否在線 | ✅ |
| `private-anon-chat.{channelId}` | Private | 符合條件的用戶 | 匿名聊天室即時訊息 | Phase 2 |

### 頻道命名規則

```
private-{domain}.{id}       ← 私有頻道（需認證，點對點或用戶專屬）
presence-{domain}           ← Presence 頻道（需認證，可感知其他在線成員）
public-{domain}             ← 公開頻道（無需認證，MVP 不使用）
```

---

## 3. 連線建立與認證

### 3.1 前端初始化（`src/plugins/echo.ts`）

```typescript
import Echo from 'laravel-echo'
import { io } from 'socket.io-client'

const echo = new Echo({
  broadcaster: 'socket.io',
  client: io,
  host: `${import.meta.env.VITE_REVERB_HOST}:${import.meta.env.VITE_REVERB_PORT}`,
  forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'wss',
  // Sanctum Cookie 模式：依靠 withCredentials 傳送認證 Cookie
  auth: {
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
    },
  },
  withCredentials: true,
})

export default echo
```

### 3.2 環境變數（`.env`）

```dotenv
# WebSocket（Laravel Reverb）
VITE_REVERB_SCHEME=wss
VITE_REVERB_HOST=api.mimeet.tw
VITE_REVERB_PORT=443          # 生產環境透過 Nginx proxy 到 8080

# 後端 Reverb Server 設定（.env Laravel）
REVERB_APP_ID=mimeet-app
REVERB_APP_KEY=your-reverb-key
REVERB_APP_SECRET=your-reverb-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http            # 後端本機 HTTP，SSL 由 Nginx 終止
BROADCAST_DRIVER=reverb
```

### 3.3 頻道授權端點（`routes/channels.php`）

```php
// 私有聊天頻道：只允許對話雙方訂閱
Broadcast::channel('chat.{conversationId}', function (User $user, int $conversationId) {
    $conv = Conversation::find($conversationId);
    return $conv && ($conv->user_a_id === $user->id || $conv->user_b_id === $user->id);
});

// 個人通知頻道：只允許本人訂閱
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});

// Presence 在線頻道：所有已登入用戶可加入
Broadcast::channel('online', function (User $user) {
    return ['id' => $user->id, 'nickname' => $user->nickname];
});

// 匿名聊天室（Phase 2）
Broadcast::channel('anon-chat.{channelId}', function (User $user, int $channelId) {
    $channel = AnonymousChannel::active()->find($channelId);
    if (!$channel) return false;
    if ($user->gender === 'female') return true;
    return $user->membership_level >= 3; // 男性需付費
});
```

---

## 4. 事件完整規格

### 4.1 頻道：`private-chat.{conversationId}`

#### Event: `MessageSent`（新訊息）

**觸發時機：** `ChatController@store` 訊息儲存成功後

**後端廣播：**
```php
class MessageSent implements ShouldBroadcast
{
    public function broadcastOn(): array
    {
        return [new PrivateChannel("chat.{$this->message->conversation_id}")];
    }

    public function broadcastAs(): string { return 'MessageSent'; }

    public function broadcastWith(): array
    {
        return [
            'id'              => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id'       => $this->message->sender_id,
            'content'         => $this->message->content,
            'type'            => $this->message->type,    // 'text' | 'image' | 'system'
            'is_read'         => false,
            'created_at'      => $this->message->created_at->toISOString(),
        ];
    }
}
```

**前端接收 Payload：**
```typescript
interface MessageSentPayload {
  id: number
  conversation_id: number
  sender_id: number
  content: string
  type: 'text' | 'image' | 'system'
  is_read: boolean
  created_at: string  // ISO 8601
}
```

**前端訂閱範例（`useChat.ts`）：**
```typescript
echo.private(`chat.${conversationId}`)
  .listen('MessageSent', (payload: MessageSentPayload) => {
    chatStore.appendMessage(payload)
    if (payload.sender_id !== authStore.userId) {
      chatStore.incrementUnread(payload.conversation_id)
    }
  })
```

---

#### Event: `MessageRecalled`（訊息撤回）

**觸發時機：** 發訊者在 5 分鐘內呼叫 `DELETE /api/v1/chats/{id}/messages/{messageId}`

**後端廣播 Payload：**
```typescript
interface MessageRecalledPayload {
  message_id: number
  conversation_id: number
  recalled_at: string  // ISO 8601
}
```

**前端行為：**
- 找到對應 `message_id`，將訊息內容替換為「此訊息已撤回」
- 若訊息已在畫面外（歷史），下次載入時 API 回傳 `is_recalled: true`

---

#### Event: `MessageRead`（已讀回執）

**觸發時機：** 對方呼叫 `PATCH /api/v1/chats/{conversationId}/messages/read`

**後端廣播 Payload：**
```typescript
interface MessageReadPayload {
  conversation_id: number
  reader_id: number
  read_at: string        // ISO 8601
  last_read_message_id: number
}
```

**前端行為：**
- 對 `last_read_message_id` 以前（含）所有由自己發的訊息，標記為已讀
- 顯示「已讀」文字（僅付費會員可見，前端在顯示前判斷 `auth.isPaid`）

---

### 4.2 頻道：`private-user.{userId}`

此頻道為「個人事件推播」頻道，涵蓋所有需即時通知用戶本人的事件。

#### Event: `NotificationCreated`（新通知）

**觸發時機：** 任何需要通知用戶的系統事件

**Payload：**
```typescript
interface NotificationCreatedPayload {
  id: number
  type: NotificationType
  title: string
  body: string
  action_url: string | null   // 點擊後跳轉的前台路由
  created_at: string
}

type NotificationType =
  | 'new_message'          // 新訊息（非聊天室內時）
  | 'new_follower'         // 有人收藏你
  | 'new_visitor'          // 有人查看你的主頁
  | 'date_invite'          // 收到約會邀請
  | 'date_result'          // 約會驗證結果（成功/失效）
  | 'ticket_reply'         // 問題回報有回覆
  | 'subscription_expiring'// 訂閱即將到期（3天前）
  | 'verification_result'  // 進階驗證審核結果
  | 'system_announcement'  // 系統公告
```

**前端行為：**
- 右上角通知鈴鐺數字 +1
- 若用戶目前在對應頁面，自動刷新資料

---

#### Event: `UnreadCountUpdated`（未讀數更新）

**觸發時機：** 收到新訊息時，同步更新未讀數 Badge

**Payload：**
```typescript
interface UnreadCountUpdatedPayload {
  conversation_id: number
  unread_count: number        // 該對話的未讀總數
  total_unread: number        // 全部對話的未讀總數（用於底部 Tab Badge）
}
```

**前端行為：**
- 更新 `chatStore.unreadCounts`
- 更新底部導覽列「訊息」Tab 的 Badge 數字

---

#### Event: `CreditScoreChanged`（誠信分數異動）

**觸發時機：** `CreditScoreService@adjust` 執行完成後

**Payload：**
```typescript
interface CreditScoreChangedPayload {
  old_score: number
  new_score: number
  delta: number          // 正數為加分，負數為扣分
  reason: string         // 人類可讀說明，如「QR碼約會驗證成功」
  type: string           // credit_score_logs.type 對應值
}
```

**前端行為：**
- 顯示 Toast 通知：`delta > 0` → 綠色「誠信分數 +N」；`delta < 0` → 紅色「誠信分數 -N」
- 更新 `authStore.user.credit_score`（避免需要重新 fetch）
- 若 `new_score <= 0` → 前端強制導向 `/suspended` 頁面

---

#### Event: `SubscriptionStatusChanged`（訂閱狀態異動）

**觸發時機：** 訂閱到期、付款成功、取消訂閱處理完成

**Payload：**
```typescript
interface SubscriptionStatusChangedPayload {
  event: 'activated' | 'expired' | 'cancelled'
  plan: string               // 'weekly' | 'monthly' | 'quarterly' | 'yearly' | 'trial'
  expires_at: string | null  // ISO 8601，cancelled 時為 null
  membership_level: number   // 最新的會員等級（1=未驗證, 2=驗證, 3=付費）
}
```

**前端行為：**
- 更新 `authStore.user.membership_level`
- 若 `event === 'expired'`：顯示 Toast「您的付費會員已到期」，功能按鈕即時變灰

---

### 4.3 頻道：`presence-online`

#### Presence 事件（Laravel Echo 內建）

```typescript
echo.join('online')
  .here((users: OnlineUser[]) => {
    // 初始加入：取得目前所有在線用戶列表
    presenceStore.setOnlineUsers(users)
  })
  .joining((user: OnlineUser) => {
    // 有用戶上線
    presenceStore.addOnlineUser(user)
  })
  .leaving((user: OnlineUser) => {
    // 有用戶離線
    presenceStore.removeOnlineUser(user.id)
  })

interface OnlineUser {
  id: number
  nickname: string
}
```

**使用情境：**
- 聊天列表：在對方頭像右下角顯示綠點（表示對方在線）
- 個人主頁：顯示「剛剛上線」或「N分鐘前上線」

**隱私注意：**
> 若用戶開啟「隱身模式」（加值服務），後端在 Channel Auth 時回傳 `false` 阻止加入 presence 頻道，
> 但用戶仍可接收個人頻道的私人事件（不影響收訊）。

---

### 4.4 頻道：`private-anon-chat.{channelId}`（Phase 2）

#### Event: `AnonMessageReceived`（匿名聊天室新訊息）

**Payload：**
```typescript
interface AnonMessageReceivedPayload {
  id: number
  channel_id: number
  anon_alias: string     // '神秘訪客#1234'，不含真實身份
  content: string
  created_at: string
}
```

> 後端在廣播前，將 `user_id` 替換為 `anon_alias`，確保真實身份不洩露給前端。
> 後台查詢時使用 REST API（`/api/v1/admin/anon-chat/messages`），可取得完整 `user_id`。

---

## 5. 連線中斷與重連策略

### 5.1 前端重連邏輯

```typescript
// src/plugins/echo.ts 補充設定
const socket = io(host, {
  reconnection: true,
  reconnectionDelay: 1000,         // 首次重連等待 1 秒
  reconnectionDelayMax: 10000,     // 最長等待 10 秒
  reconnectionAttempts: Infinity,  // 無限重試（由用戶手動離開頁面才停止）
  timeout: 20000,                  // 連線逾時 20 秒
})
```

### 5.2 離線期間訊息補償（Message Catch-up）

WebSocket 斷線期間，後端不保存 WS 事件，重連後前端需主動拉取：

```typescript
// src/composables/useChat.ts
socket.on('connect', async () => {
  // 重連成功後，拉取斷線期間的新訊息
  const lastMessageId = chatStore.getLastMessageId(conversationId)
  if (lastMessageId) {
    const { data } = await api.get(`/api/v1/chats/${conversationId}/messages`, {
      params: { after_id: lastMessageId }
    })
    chatStore.prependMessages(data.messages)
  }
})
```

### 5.3 前台斷線狀態提示

```
前台頂部出現橙色 Banner：「連線已中斷，嘗試重新連線中…」
重連成功後 Banner 消失，並觸發 Message Catch-up
```

---

## 6. 前端頻道訂閱時機

| 頻道 | 訂閱時機 | 取消訂閱時機 |
|------|---------|------------|
| `presence-online` | `App.vue` mounted（登入後） | `auth.logout()` |
| `private-user.{id}` | `App.vue` mounted（登入後） | `auth.logout()` |
| `private-chat.{id}` | 進入聊天室頁面 | 離開聊天室頁面（onUnmounted）|
| `private-anon-chat.{id}` | 進入匿名聊天室頁面 | 離開匿名聊天室頁面 |

```typescript
// App.vue（全域持久訂閱，登入後執行一次）
onMounted(() => {
  if (authStore.isLoggedIn) {
    subscribeGlobalChannels()
  }
})

function subscribeGlobalChannels() {
  // 個人通知頻道
  echo.private(`user.${authStore.userId}`)
    .listen('NotificationCreated', handleNotification)
    .listen('UnreadCountUpdated', handleUnreadUpdate)
    .listen('CreditScoreChanged', handleCreditChange)
    .listen('SubscriptionStatusChanged', handleSubscriptionChange)

  // 全域在線狀態
  echo.join('online')
    .here(users => presenceStore.setOnlineUsers(users))
    .joining(user => presenceStore.addOnlineUser(user))
    .leaving(user => presenceStore.removeOnlineUser(user.id))
}
```

---

## 7. 後端 Event 類別清單

| Event 類別（`app/Events/`） | broadcastAs | 頻道 | 排隊廣播 |
|--------------------------|------------|------|---------|
| `MessageSent` | `MessageSent` | `private-chat.{id}` | ❌ 同步 |
| `MessageRecalled` | `MessageRecalled` | `private-chat.{id}` | ❌ 同步 |
| `MessageRead` | `MessageRead` | `private-chat.{id}` | ❌ 同步 |
| `NotificationCreated` | `NotificationCreated` | `private-user.{id}` | ✅ Queue |
| `UnreadCountUpdated` | `UnreadCountUpdated` | `private-user.{id}` | ❌ 同步 |
| `CreditScoreChanged` | `CreditScoreChanged` | `private-user.{id}` | ✅ Queue |
| `SubscriptionStatusChanged` | `SubscriptionStatusChanged` | `private-user.{id}` | ✅ Queue |
| `AnonMessageReceived` | `AnonMessageReceived` | `private-anon-chat.{id}` | ❌ 同步 |

> **排隊廣播**（`ShouldBroadcast` 加上 `implements ShouldQueue`）：
> 用於不需要立即送達的通知類事件，避免阻塞 HTTP 回應。
> 聊天訊息類需要即時性，不使用 Queue。

---

## 8. Nginx WebSocket 代理設定

```nginx
# /etc/nginx/sites-available/mimeet.conf 補充 WS 區塊
location /app/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_read_timeout 3600s;    # WebSocket 長連線，避免 Nginx 60s 超時斷線
    proxy_send_timeout 3600s;
}
```

---

## 9. 常見錯誤與排查

| 錯誤現象 | 可能原因 | 排查步驟 |
|---------|---------|---------|
| 前端連不上 WS | Nginx 未設定 Upgrade header | 檢查 §8 Nginx 設定 |
| 頻道訂閱 403 | Sanctum Cookie 未傳送 | 確認 `withCredentials: true` + CORS 設定 |
| 事件收不到 | broadcastAs() 名稱不符 | 後端 `broadcastAs()` 需與前端 `.listen('...')` 完全一致 |
| 重連後訊息遺失 | 未實作 Message Catch-up | 實作 §5.2 補償邏輯 |
| Presence 頻道人數不準確 | 用戶快速重連 | Laravel Reverb 有內建 grace period，屬正常現象 |

---

## 10. MVP 實作優先序

```
Sprint 6（已完成）：
  [x] Laravel Reverb 基礎設定與 Nginx 代理
  [x] 頻道授權（channels.php）
  [x] 前端 Echo 初始化 + 全域訂閱（App.vue）

Sprint 7（已完成 2026-04-08）：
  [x] private-chat.{id}：ChatMessageSent（S7-03 ChatService）
  [x] private-user.{id}：NotificationReceived（S7-06 NotificationService）
  [x] 廣播 try/catch 防護（測試環境無 Reverb 時靜默失敗）

待實作：
  [ ] private-chat.{id}：MessageRecalled
  [ ] private-user.{id}：CreditScoreChanged
  [ ] presence-online（在線狀態）
  [ ] 斷線重連 + Message Catch-up
  [ ] private-user.{id}：SubscriptionStatusChanged
  [ ] private-anon-chat.{id}（Phase 2 功能）
```

---

*本文件版本 v1.0，如後續業主確認訊息撤回時間窗（目前假設 5 分鐘）或其他細節，請同步更新。*