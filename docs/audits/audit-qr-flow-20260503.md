# QR 約會驗證流程診斷報告

## 0. 調查方法

- 分支：`develop`
- HEAD：`83f4278 test(payment): PR-mock — PaymentTest 改用真實 ECPay callback (10/13 turn-green)`
- 工作區狀態：非乾淨。既有未 staged 變更包含 `backend/.phpunit.result.cache`、`backend/bootstrap/cache/services.php`、`backend/storage/logs/laravel.log`、刪除 `progress/index.html`；既有 untracked 包含 `AGENTS.md`、`Fonts/`、`Gemini.md`、多份 audit / prompt 檔。
- 本報告所有結論均以上述 commit 的本機程式碼與 `docs/` 規格為依據。

已執行的強制調查命令：

```bash
git branch --show-current
git status
git log --oneline -10
grep -rln "QRCode\|qr-code\|qrcode\|qr_token\|qrToken" frontend/src
grep -rln "getUserMedia\|facingMode" frontend/src
grep -rln "jsQR\|jsqr" frontend/src
grep -rln "qr_token\|qrToken\|verify" backend/app/Http
grep -rln "DateInvitation\|date_invitation" backend/app
grep -rln "basicSsl\|https\s*:" frontend/vite.config* frontend/vue.config* 2>/dev/null
```

## 1. 程式碼地圖（實際存在的檔案）

### 1.1 前端 grep 結果

`grep -rln "QRCode\|qr-code\|qrcode\|qr_token\|qrToken" frontend/src`

- `frontend/src/components/dates/DateCard.vue`：約會卡片、顯示 QR 按鈕、掃碼入口。閱讀過 ✅
- `frontend/src/types/chat.ts`：`DateInvitation` 型別含 `qrToken` / `expiresAt`。閱讀過 ✅
- `frontend/src/api/dates.ts`：約會列表、回應邀請、掃碼驗證 API client。閱讀過 ✅

`grep -rln "getUserMedia\|facingMode" frontend/src`

- `frontend/src/views/app/QRScanView.vue`：相機啟動、jsQR 掃描、GPS 提示、手動輸入。閱讀過 ✅

`grep -rln "jsQR\|jsqr" frontend/src`

- `frontend/src/views/app/QRScanView.vue`：`jsQR` 掃描迴圈。閱讀過 ✅

另外因 `DateCard.vue` import `QRCodeDisplay.vue`，實際補讀：

- `frontend/src/components/dates/QRCodeDisplay.vue`：目前是 mock QR SVG，只吃 `expiresAt`，不吃 QR token。閱讀過 ✅
- `frontend/src/views/app/DatesView.vue`：列表載入、卡片事件、導向掃碼頁。閱讀過 ✅
- `frontend/src/router/routes/app.ts`：`/app/dates` 與 `/app/dates/scan` 路由。閱讀過 ✅
- `frontend/src/composables/useDateInviteFromProfile.ts`：個人頁發起邀請使用 `/dates`。閱讀過 ✅

### 1.2 後端 grep 結果

`grep -rln "qr_token\|qrToken\|verify" backend/app/Http`

- `backend/app/Http/Controllers/Api/V1/DateController.php`：`POST /dates`、`POST /dates/verify`。閱讀過 ✅
- `backend/app/Http/Controllers/Api/V1/DateInvitationController.php`：legacy `/date-invitations` 列表、回應、驗證。閱讀過 ✅
- `backend/app/Http/Controllers/Api/V1/AdminController.php`：命中 `verify` 相關後台/認證，不是 QR 主流程。未讀 ❌
- `backend/app/Http/Controllers/Api/V1/CreditCardVerificationController.php`：信用卡驗證，不是 QR 主流程。未讀 ❌
- `backend/app/Http/Controllers/Api/V1/Admin/VerificationController.php`：後台身份驗證，不是 QR 主流程。未讀 ❌
- `backend/app/Http/Controllers/Api/V1/AuthController.php`：認證驗證，不是 QR 主流程。未讀 ❌

`grep -rln "DateInvitation\|date_invitation" backend/app`

- `backend/app/Services/DateService.php`：QR token 產生與驗證核心。閱讀過 ✅
- `backend/app/Models/DateInvitation.php`：資料欄位與 casts。閱讀過 ✅
- `backend/routes/api.php`：`/date-invitations` 與 `/dates` 路由。閱讀過 ✅
- 其他命中：`AuthServiceProvider.php`、seed/reset/dataset/notification/policy，非三個故障的直接觸發點，本輪未讀完整內容。

### 1.3 關鍵資料來源

`backend/app/Services/DateService.php:20-30`

```php
$invitation = DateInvitation::create([
    'inviter_id' => $inviter->id,
    'invitee_id' => $data['invitee_id'],
    'date_time' => $dateTime,
    'location_name' => $data['location_name'] ?? null,
    'latitude' => $data['latitude'] ?? null,
    'longitude' => $data['longitude'] ?? null,
    'qr_token' => bin2hex(random_bytes(32)),
    'status' => 'pending',
    'expires_at' => $dateTime->copy()->addMinutes(30),
    'created_at' => now(),
]);
```

`backend/database/migrations/2026_04_08_000003_create_date_invitations_table.php:11-23`

```php
Schema::create('date_invitations', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('inviter_id');
    $table->unsignedBigInteger('invitee_id');
    $table->dateTime('date_time');
    $table->string('location_name', 255)->nullable();
    $table->decimal('latitude', 10, 8);
    $table->decimal('longitude', 11, 8);
    $table->string('qr_token', 100)->unique();
    $table->enum('status', ['pending', 'accepted', 'verified', 'cancelled', 'expired'])->default('pending');
```

## 2. 規格文件依據

本輪確認以下檔案存在：

- `docs/PRD-001_MiMeet_約會產品需求規格書.md`
- `docs/UF-001_用戶流程圖.md`
- `docs/UF-001_用戶流程圖.html`
- `docs/UI-001_UI_UX設計規格書.md`
- `docs/API-001_前台API規格書.md`
- `docs/IMPLEMENTATION_STATUS.md`

### 2.1 PRD：QR 系統與 GPS 流程

`docs/PRD-001_MiMeet_約會產品需求規格書.md:337-363`

```markdown
2. **QR碼特性**：
   - 時間限定：約會時間前後30分鐘內有效
   - 位置限定：GPS範圍500公尺內有效
   - 一次性使用：掃描後立即失效

3. **驗證流程**（SPEC-CONFIRM-001 B.6 更新）：
   - 雙方在約定時間地點掃描對方QR碼
   - 系統驗證時間、身份，GPS 位置驗證為**選擇性**
   - **驗證成功（GPS 通過）**：雙方各獲得 +5 分誠信分數
   - **驗證成功（GPS 未提供/失敗）**：雙方各獲得 +2 分誠信分數

**技術規格**：
- QR碼內容：加密的約會ID + 時間戳 + 用戶ID
- 有效期：約會時間前後各30分鐘
- GPS驗證：選擇性，通過標準為 ±500 公尺範圍內（Haversine 公式計算）
- 前端 GPS 流程（v1.4 含授權提示畫面）：
```

### 2.2 UF：掃碼時間窗與 API

`docs/UF-001_用戶流程圖.md:407-427`

```markdown
    BOTH_WAIT --> TIME_WINDOW{進入有效掃碼時間窗\n約會時間 -30 ~ +30 分鐘}
    TIME_WINDOW -->|未到時間| COUNTDOWN[App 顯示倒數計時器]
    TIME_WINDOW -->|超過 +30 分鐘| EXPIRED[QR Token 失效\n約會狀態 → 過期]
    TIME_WINDOW -->|進入時間窗| SCAN_READY

    subgraph SCAN_READY [雙方就位掃碼（v1.4 含 GPS 授權提示）]
        SR1[各自進入 /app/dates 約會頁]
        SR1 --> SR2[點擊「立即掃碼」按鈕]
        SR2 --> SR3[開啟全螢幕相機掃碼框]
        SR3 --> SR4[掃描對方 QR Code]
        SR4 --> SR_GPS_PROMPT[顯示 GPS 授權說明畫面\n+5分 vs +2分 對照]
    end

    SCAN_READY --> VERIFY_API[POST /api/v1/dates/verify\n{ token, latitude?, longitude? }]
```

### 2.3 UI：掃碼頁與 GPS 提示

`docs/UI-001_UI_UX設計規格書.md:445-463`

```markdown
> **QR 掃碼驗證頁（`/app/dates/scan`）— v1.4 GPS 授權提示規格**：
> 全螢幕深色背景，含相機掃碼框（4 角粉紅邊框）。
> 掃碼成功後流程（含 GPS 授權提示畫面）：
> 1. 掃碼成功 → 顯示 **GPS 授權說明畫面**（不直接觸發系統授權彈窗）
> 2. 說明畫面內容：
>    - 📍 圖示 + 標題「開啟定位可獲得更高分數」
>    - 得分對照卡（深色圓角卡片）：
>      - `+5 分`（綠色 Badge）— 允許 GPS 且在 500m 內
>      - `+2 分`（琥珀色 Badge）— 不提供 GPS 或距離超過
> 3. 用戶選「允許」→ 觸發 `navigator.geolocation`（iOS/Android 此時彈出系統授權）
> 4. 用戶選「跳過」→ 不觸發系統授權，直接送出驗證（latitude=null）
```

### 2.4 API：建立邀請與列表欄位

`docs/API-001_前台API規格書.md:1385-1409`

```markdown
**成功回應 (201)：**
...
      "status": "pending",
      "qr_token": "a3f9c2d1e8b4...",
      "qr_expires_at": "2024-12-25T20:00:00Z",
      "created_at": "2024-12-20T10:30:00Z"
...
> `qr_token` 為 64 字元 hex 字串（`bin2hex(random_bytes(32))`），非 JWT。
```

`docs/API-001_前台API規格書.md:1459-1483`

```markdown
**成功回應 (200)：**
...
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
```

列表規格範例未包含 `qr_token` / `qr_expires_at`。

### 2.5 API：掃碼驗證

`docs/API-001_前台API規格書.md:1521-1546`

```markdown
#### 5.2.1 QR碼掃描驗證
```http
POST /api/v1/dates/verify
Authorization: Bearer {access_token}
Content-Type: application/json
```

> **注意：** token 放在 request body，非路由參數。路由為 `/dates/verify`（無 `/{id}`），QR token 中已含邀請識別資訊。

**請求參數：**
```json
{
  "token": "64字元hex QR token",
  "latitude": 25.0341,
  "longitude": 121.5646
}
```
```

`docs/API-001_前台API規格書.md:1548-1572`

```markdown
**成功回應 — 雙方都已掃碼 (200)：**
...
    "status": "completed",
    "score_awarded": 5,
    "gps_passed": true

**成功回應 — 僅一方掃碼 (200)：**
...
    "status": "waiting",
    "message": "等待對方掃碼"
```

### 2.6 實作狀態文件

`docs/IMPLEMENTATION_STATUS.md:62-68`

```markdown
### QR 約會驗證

| ID | 功能 | 後端 | 前端 | 狀態 | 說明 |
| F23 | 約會邀請發起 | ✅ DateService + DateInvitationController | ✅ DatesView.vue + ProfileView BottomSheet | ✅ | 時間/地點/QR 產生 |
| F24 | QR 掃碼驗證 | ✅ DateService::verify (時間窗 ±30 分) | ✅ QRScanView.vue (jsQR + 相機) | ✅ | GPS 提示流程 + 手動輸入 fallback |
```

此文件宣稱「時間窗 ±30 分」已完成，但本輪讀到的後端 `DateService` 只檢查 `expires_at` 是否過期，未看到「約會時間前 30 分鐘以前不得掃」的檢查；詳見第 6 節。

## 3. 現象一：點「顯示 QR」沒反應

### 3.1 觸發點

`frontend/src/components/dates/DateCard.vue:91-95`

```vue
<!-- 進行中 + 2小時內 -->
<template v-else-if="isWithin2Hours">
  <span class="date-card__countdown">⏱ {{ countdown }}</span>
  <button class="date-btn date-btn--qr" @click="showQR = !showQR">{{ showQR ? '收起 QR' : '顯示 QR' }}</button>
  <button class="date-btn date-btn--scan" @click="emit('scan', date.id)">掃碼驗證</button>
</template>
```

`frontend/src/components/dates/DateCard.vue:35-39`

```ts
const isWithin2Hours = computed(() => {
  if (props.date.status !== 'accepted') return false
  const diff = new Date(props.date.scheduledAt).getTime() - Date.now()
  return diff > 0 && diff < 2 * 3600000
})
```

已驗證事實：

- 按鈕 click handler 只切換 `showQR`，沒有呼叫 API。
- 按鈕只會在 `status === 'accepted'` 且約會時間「未來 2 小時內」顯示。
- 這個前端時間窗和規格「約會時間 -30 ~ +30 分鐘」不同。

### 3.2 渲染元件

`frontend/src/components/dates/DateCard.vue:109-115`

```vue
<!-- QR Code 展開區 -->
<div v-if="showQR && date.expiresAt" class="date-card__qr">
  <QRCodeDisplay
    :expires-at="date.expiresAt"
    :on-refresh="() => { /* mock refresh */ }"
  />
</div>
```

`frontend/src/components/dates/QRCodeDisplay.vue:4-7`

```ts
const props = defineProps<{
  expiresAt: string
  onRefresh: () => void
}>()
```

`frontend/src/components/dates/QRCodeDisplay.vue:30-36`

```vue
<template>
  <div class="qr-display">
    <!-- Mock QR -->
    <div class="qr-display__code" :class="{ 'qr-display__code--expired': isExpired }">
      <svg width="120" height="120" viewBox="0 0 120 120" fill="none">
        <!-- Mock QR pattern -->
```

已驗證事實：

- 展開區除了 `showQR`，還要求 `date.expiresAt` truthy。
- `QRCodeDisplay` 沒有 `token` / `qrToken` prop。
- `QRCodeDisplay` 顯示的是 mock SVG pattern，不是從 token 產生的可掃 QR。

### 3.3 推測的根本原因

已驗證的事實：

`frontend/src/api/dates.ts:9-17`

```ts
interface RawDateInvitation {
  id: number
  inviter: { id: number; nickname: string; avatar: string | null } | null
  invitee: { id: number; nickname: string; avatar: string | null } | null
  scheduled_at: string | null
  location: string | null
  status: string
  created_at: string
}
```

`frontend/src/api/dates.ts:19-35`

```ts
function transformInvitation(raw: RawDateInvitation): DateInvitation {
  return {
    id: raw.id,
    inviterId: raw.inviter?.id ?? 0,
    inviteeId: raw.invitee?.id ?? 0,
    ...
    qrToken: null,
    expiresAt: null,
    creditScoreChange: null,
    createdAt: raw.created_at,
  }
}
```

`frontend/src/api/dates.ts:38-40`

```ts
export async function fetchDates(): Promise<DateInvitation[]> {
  const res = await client.get<{ data: { invitations: RawDateInvitation[] } }>('/date-invitations')
  return res.data.data.invitations.map(transformInvitation)
}
```

`backend/app/Http/Controllers/Api/V1/DateInvitationController.php:100-116`

```php
'invitations' => $invitations->map(fn ($inv) => [
    'id' => $inv->id,
    'inviter' => $inv->inviter ? [
        'id' => $inv->inviter->id,
        'nickname' => $inv->inviter->nickname,
        'avatar' => $inv->inviter->avatar_url,
    ] : null,
    'invitee' => $inv->invitee ? [
        'id' => $inv->invitee->id,
        'nickname' => $inv->invitee->nickname,
        'avatar' => $inv->invitee->avatar_url,
    ] : null,
    'scheduled_at' => $inv->date_time?->toISOString(),
    'location' => $inv->location_name,
    'status' => $inv->status,
    'created_at' => $inv->created_at,
]),
```

推測：

- 最直接原因是 `fetchDates()` 使用 `/date-invitations`，後端列表 response 沒有回傳 `qr_token` / `qr_expires_at`，前端轉換又把 `expiresAt` 固定設為 `null`。
- 因此按下「顯示 QR」後，`showQR` 雖然變成 true，但 `v-if="showQR && date.expiresAt"` 仍為 false，畫面看起來沒反應。
- 即使把 `expiresAt` 補上，目前 `QRCodeDisplay` 也只會顯示 mock QR，不會顯示真 token QR。

### 3.4 規格 vs 實做差異

規格事實：

- PRD 說系統生成唯一 QR 碼：`docs/PRD-001_MiMeet_約會產品需求規格書.md:326-329`
- PRD 說雙方掃描對方 QR：`docs/PRD-001_MiMeet_約會產品需求規格書.md:342-343`
- API 建立邀請 response 含 `qr_token` / `qr_expires_at`：`docs/API-001_前台API規格書.md:1398-1403`
- API 列表 response 範例沒有 `qr_token` / `qr_expires_at`：`docs/API-001_前台API規格書.md:1459-1483`

實作事實：

- `/date-invitations` 列表未回傳 QR 欄位：`backend/app/Http/Controllers/Api/V1/DateInvitationController.php:100-116`
- 前端列表轉換固定 `qrToken: null`、`expiresAt: null`：`frontend/src/api/dates.ts:31-32`
- `DateCard` 卻以 `date.expiresAt` 作為 QR 展開條件：`frontend/src/components/dates/DateCard.vue:110`

衝突：

- 「顯示 QR」需要列表資料帶 QR token / 到期時間，但 API-001 的列表規格未列這兩個欄位。
- PRD 要能掃描對方 QR，但 UI-001 只明確規範掃碼頁，沒有明確規範「顯示 QR」卡片或手動顯示 token。
- 需要你裁示：列表 API 是否應新增 `qr_token` / `qr_expires_at`，或前端點「顯示 QR」時另呼叫單筆/refresh QR API。

### 3.5 建議修改

建議修改的檔案：

- `backend/app/Http/Controllers/Api/V1/DateInvitationController.php`
- `frontend/src/api/dates.ts`
- `frontend/src/types/chat.ts`
- `frontend/src/components/dates/DateCard.vue`
- `frontend/src/components/dates/QRCodeDisplay.vue`
- `docs/API-001_前台API規格書.md`
- `docs/UI-001_UI_UX設計規格書.md`
- 視裁示可能需要 `docs/PRD-001_MiMeet_約會產品需求規格書.md`

修改方向：

- 先裁示 API contract：列表 response 是否回傳 `qr_token` / `qr_expires_at`。
- 若採列表回傳：後端 `/date-invitations` accepted/own participant response 加上 QR 欄位，前端 `RawDateInvitation` 與 `transformInvitation()` 對應 `qrToken` / `expiresAt`。
- 將 `QRCodeDisplay` 改成接收 token，使用既有套件或新增經審核的 QR 產生方案；目前不得只保留 mock SVG。
- 增加手動代碼顯示（例如 token 前後遮罩 + copy/顯示完整碼），供掃碼失敗時人工輸入。
- 調整前端 QR/掃碼可用時間窗，至少與規格 `-30 ~ +30` 對齊；目前 `isWithin2Hours` 不一致。

風險與連動影響：

- 這是 API contract 改動，前端型別、後端 response、API-001 必須同次更新。
- QR token 是驗證憑證，若列表回傳需要確認只對邀請雙方回傳，且不可出現在 admin 或 log 的非必要輸出。

## 4. 現象二：Android 無法存取相機

### 4.1 觸發點

`frontend/src/views/app/DatesView.vue:49-51`

```ts
function handleScan(_id: number) {
  router.push('/app/dates/scan')
}
```

`frontend/src/router/routes/app.ts:40-45`

```ts
{
  path: 'dates/scan',
  name: 'dates-scan',
  component: () => import('@/views/app/QRScanView.vue'),
  meta: { requiresAuth: true, minLevel: 2 },
},
```

`frontend/src/views/app/QRScanView.vue:160-163`

```ts
onMounted(() => {
  startCamera()
})
onUnmounted(stopCamera)
```

### 4.2 處理函式與渲染元件

`frontend/src/views/app/QRScanView.vue:23-47`

```ts
async function startCamera() {
  try {
    scanStatus.value = '正在開啟相機…'
    mediaStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } },
    })

    // 先切到 camera 狀態，讓 v-if 渲染 <video>
    viewState.value = 'camera'
    await nextTick()

    // 現在 videoRef 已經存在於 DOM
    if (videoRef.value) {
      videoRef.value.srcObject = mediaStream
      videoRef.value.onloadedmetadata = () => {
        videoRef.value!.play()
        startScanning()
      }
    }
    scanStatus.value = '對準 QR Code…'
  } catch (e) {
    viewState.value = 'denied'
  }
}
```

`frontend/src/views/app/QRScanView.vue:176-190`

```vue
<template v-if="viewState === 'camera'">
  <div class="camera-area">
    <video ref="videoRef" autoplay playsinline muted class="camera-video" />
    <canvas ref="canvasRef" class="camera-canvas" />
    <div class="camera-overlay">
      <div class="camera-frame">
        ...
      </div>
      <p class="camera-hint">{{ scanStatus || '將 QR Code 對準框內' }}</p>
      <button class="camera-manual-btn" @click="stopCamera(); viewState = 'manual'">手動輸入代碼</button>
    </div>
  </div>
</template>
```

`frontend/src/views/app/QRScanView.vue:212-226`

```vue
<template v-if="viewState === 'denied'">
  <div class="denied-area">
    ...
    <p class="denied-title">需要相機權限</p>
    <p class="denied-text">需要相機權限才能掃描 QR Code，請在瀏覽器設定中允許相機存取。</p>
    <button class="denied-btn" @click="startCamera">重試</button>
    <button class="denied-btn denied-btn--manual" @click="viewState = 'manual'">手動輸入代碼</button>
  </div>
</template>
```

### 4.3 推測的根本原因

已驗證的事實：

`frontend/vite.config.ts:5`

```ts
// import basicSsl from '@vitejs/plugin-basic-ssl'
```

`frontend/vite.config.ts:14-18`

```ts
server: {
  host: '0.0.0.0',
  port: 5173,
  // basicSsl() 自動啟用自簽 HTTPS，讓區網裝置的 getUserMedia 可用
},
```

`grep -rln "basicSsl\|https\s*:" frontend/vite.config* frontend/vue.config* 2>/dev/null` 命中 `frontend/vite.config.ts`，但內容只有註解，沒有啟用 HTTPS。

本機文件/腳本的 staging URL 多處使用 HTTPS，例如：

- `scripts/staging-deploy.sh:63-65` 使用 `https://mimeet.online`、`https://admin.mimeet.online`、`https://api.mimeet.online/api/v1/auth/me`
- `docs/OPS-002_Online_Test_部署指南.md:292-294` 記載前台/API/Admin 為 HTTPS
- `docs/OPS-006_Droplet重啟恢復SOP.md:225` 記載 SSL 憑證路徑 `/etc/letsencrypt/live/mimeet.online/`

推測：

- 若使用者是在本機 Vite LAN URL（例如 `http://<local-ip>:5173`）用 Android 實機測試，`vite.config.ts` 沒有啟用 HTTPS，是相機不可用的高機率原因。
- 若使用者是在 staging `https://mimeet.online` 測試，本機檔案顯示部署文件與腳本預期是 HTTPS，但本輪未 SSH/curl 實際 droplet，因此線上實際 HTTPS 狀態仍標為「需確認」。
- `startCamera()` catch 目前丟掉原始錯誤，只切 `denied`，無法區分 insecure context、裝置無相機、瀏覽器不支援、權限拒絕、相機被占用等原因。
- `facingMode: 'environment'` 使用一般字串，不是 `{ exact: 'environment' }`，通常較寬鬆；本輪程式碼沒有看到 Android-only 的 exact constraint。

### 4.4 規格 vs 實做差異

規格事實：

- UI 規格要求掃碼頁全螢幕相機框：`docs/UI-001_UI_UX設計規格書.md:445-447`
- UF 規格要求點立即掃碼後開全螢幕相機：`docs/UF-001_用戶流程圖.md:415-419`

實作事實：

- `QRScanView.vue` 有全螢幕相機區與手動輸入 fallback：`frontend/src/views/app/QRScanView.vue:176-190`
- 相機錯誤處理只進 denied state，不記錄/顯示錯誤原因：`frontend/src/views/app/QRScanView.vue:44-46`
- 本機 Vite HTTPS 未啟用：`frontend/vite.config.ts:5`、`frontend/vite.config.ts:14-18`

差異：

- 規格沒有明確寫本機實機測試需要 HTTPS 或 dev server SSL 啟用方式。
- UI 規格沒有要求錯誤原因分流，但實機診斷需要更細的 error handling。

### 4.5 建議修改

建議修改的檔案：

- `frontend/src/views/app/QRScanView.vue`
- `frontend/vite.config.ts` 或前端本機開發文件
- `docs/DEV-003_前端架構與開發規範.md` 或 `docs/DEV-002_開發環境建置指南.md`
- 視裁示補 `docs/UI-001_UI_UX設計規格書.md`

修改方向：

- 在 `startCamera()` catch 中保留 `DOMException.name` / message，對 `NotAllowedError`、`NotFoundError`、`NotReadableError`、`OverconstrainedError`、`SecurityError`、`navigator.mediaDevices` 不存在做不同提示。
- 在進入 denied/error state 時保留「手動輸入代碼」入口。
- 本機實機測試流程要有 HTTPS：可裁示是否啟用 `@vitejs/plugin-basic-ssl`，或文件化只用 staging HTTPS 測試相機。
- 線上需確認 `https://mimeet.online` 在實機瀏覽器是否真為 HTTPS secure context；本輪未做遠端驗證。

風險與連動影響：

- 啟用 Vite SSL 可能涉及套件與憑證信任流程；本任務明確禁止 `npm install`，本輪不修改。
- 相機錯誤文案屬 UI 行為改動，需同步 UI 規格。

## 5. 現象三：沒有手動 QR 代碼的選項或畫面

### 5.1 觸發點

掃碼頁的手動輸入入口存在於兩個 state。

`frontend/src/views/app/QRScanView.vue:189`

```vue
<button class="camera-manual-btn" @click="stopCamera(); viewState = 'manual'">手動輸入代碼</button>
```

`frontend/src/views/app/QRScanView.vue:225`

```vue
<button class="denied-btn denied-btn--manual" @click="viewState = 'manual'">手動輸入代碼</button>
```

手動輸入畫面：

`frontend/src/views/app/QRScanView.vue:194-209`

```vue
<!-- DEV 手動輸入 -->
<template v-if="viewState === 'manual'">
  <div class="manual-area">
    ...
    <p class="manual-title">手動輸入</p>
    <p class="manual-hint">請輸入約會 QR Code</p>
    <input v-model="manualCode" type="text" class="manual-input" placeholder="輸入 QR Code…" @keyup.enter="handleManualSubmit" />
    <button class="manual-btn" @click="handleManualSubmit" :disabled="!manualCode.trim()">驗證</button>
    ...
    <button class="manual-btn manual-btn--camera" @click="startCamera">
      ...
      開啟相機掃碼
    </button>
  </div>
</template>
```

### 5.2 渲染元件

手動 submit：

`frontend/src/views/app/QRScanView.vue:153-155`

```ts
function handleManualSubmit() {
  handleVerify(manualCode.value)
}
```

`frontend/src/views/app/QRScanView.vue:100-105`

```ts
function handleVerify(code: string) {
  if (!code.trim()) return
  pendingToken.value = code
  viewState.value = 'gps-prompt'
}
```

但 QR 顯示端沒有手動代碼：

`frontend/src/components/dates/QRCodeDisplay.vue:4-7`

```ts
const props = defineProps<{
  expiresAt: string
  onRefresh: () => void
}>()
```

`frontend/src/components/dates/QRCodeDisplay.vue:31-35`

```vue
<div class="qr-display">
  <!-- Mock QR -->
  <div class="qr-display__code" :class="{ 'qr-display__code--expired': isExpired }">
    <svg width="120" height="120" viewBox="0 0 120 120" fill="none">
      <!-- Mock QR pattern -->
```

### 5.3 推測的根本原因

已驗證的事實：

- 掃碼頁有「手動輸入代碼」入口，但只在 `viewState === 'camera'` 或 `viewState === 'denied'` 出現。
- `viewState === 'error'` 只有「重試」，沒有手動輸入。
- QR 顯示端 `QRCodeDisplay` 沒有顯示任何 token 文字，也沒有 copy/manual code 區塊。
- 因第 3 節原因，使用者點「顯示 QR」時展開區通常不出現；就算出現，內容也是 mock QR，不含手動代碼。

`frontend/src/views/app/QRScanView.vue:283-290`

```vue
<!-- 失敗 -->
<template v-if="viewState === 'error'">
  <div class="result-area">
    <div class="result-icon result-icon--error">❌</div>
    <h2 class="result-title">驗證失敗</h2>
    <p class="result-text">{{ errorMsg }}</p>
    <button class="result-btn" @click="viewState = 'camera'">重試</button>
  </div>
</template>
```

推測：

- 如果使用者說的是「掃碼者沒有手動輸入入口」，程式碼顯示入口存在，但只在 camera/denied state。若畫面卡在 error/gps/verifying/success，或根本沒有進入 `/app/dates/scan`，就看不到入口。
- 如果使用者說的是「出示 QR 的人沒有手動 QR 代碼可讓對方輸入」，目前確實沒有實作。`QRCodeDisplay` 不接 token、不顯示 token、不提供 copy。

### 5.4 規格 vs 實做差異

規格事實：

- `docs/IMPLEMENTATION_STATUS.md:67` 宣稱「手動輸入 fallback」完成。
- UI-001 QR 掃碼頁規格 `docs/UI-001_UI_UX設計規格書.md:445-463` 沒有明確列「手動輸入代碼」入口。
- PRD/API 都要求 token 作為 QR code 掃碼取得的驗證值：`docs/API-001_前台API規格書.md:1542-1546`

實作事實：

- 掃碼頁 manual fallback 有做，但不是所有 state 都可見：`frontend/src/views/app/QRScanView.vue:189`、`225`、`283-290`
- 顯示端沒有 manual code：`frontend/src/components/dates/QRCodeDisplay.vue:4-7`、`31-35`

差異：

- `IMPLEMENTATION_STATUS.md` 說 fallback 完成，但實作只涵蓋掃碼頁的局部 state，沒有涵蓋 QR 顯示端的手動代碼，也沒有在 error state 保留入口。
- 規格文件沒有明確定義「手動 QR 代碼」應出現在掃碼端、顯示端，或兩者都要。

### 5.5 建議修改

建議修改的檔案：

- `frontend/src/views/app/QRScanView.vue`
- `frontend/src/components/dates/QRCodeDisplay.vue`
- `frontend/src/components/dates/DateCard.vue`
- `frontend/src/api/dates.ts`
- `docs/UI-001_UI_UX設計規格書.md`
- `docs/IMPLEMENTATION_STATUS.md`

修改方向：

- 補規格：明確定義「手動代碼」入口位置。建議同時涵蓋掃碼端 manual input 與顯示端 token fallback。
- 掃碼頁：error state 加「手動輸入代碼」按鈕；camera 初始化期間若失敗，denied state 已有入口但需更清楚。
- 顯示端：`QRCodeDisplay` 接收 `qrToken`，在 QR 下方提供可展開/複製的手動代碼。
- 這依賴第 3 節 API contract 補 token。

風險與連動影響：

- 顯示 token 會增加被旁人取得 token 的風險；需裁示是否遮罩、點擊顯示、限制只在有效時間窗內顯示。
- 若 token 是一次性或雙方共用同一 token，手動輸入會和掃碼等價，後端不需新增 endpoint。

## 6. 連動影響盤點

### 6.1 前端關聯

精準 grep：

`grep -rn "qrToken\|expiresAt\|DateInvitation" frontend/src admin/src backend/app/Http 2>/dev/null`

直接 QR/date invitation 命中：

- `frontend/src/types/chat.ts:49-63`：`DateInvitation` 型別宣告 `qrToken` / `expiresAt`。
- `frontend/src/api/dates.ts:9-40`：Raw type 與 transform 目前未讀 QR 欄位，固定 null。
- `frontend/src/components/dates/DateCard.vue:110-113`：以 `date.expiresAt` 控制 QR 展開。
- `frontend/src/components/dates/QRCodeDisplay.vue:5-14`：只使用 `expiresAt`。
- `frontend/src/views/app/DatesView.vue:13-34`：`allDates` 由 `fetchDates()` 取得。

其他 `expiresAt` 命中多為 payment/subscription，非 `DateInvitation` 欄位：

- `frontend/src/composables/usePayment.ts`
- `frontend/src/types/payment.ts`
- `frontend/src/views/payment/ResultView.vue`
- `frontend/src/views/app/ShopView.vue`
- `frontend/src/views/app/settings/SubscriptionView.vue`

### 6.2 後端 API contract

`backend/routes/api.php:145-160`

```php
Route::prefix('date-invitations')->middleware(['auth:sanctum', 'check.suspended', 'membership:2'])->group(function () {
    Route::post('/', [DateInvitationController::class, 'store']);
    Route::get('/', [DateInvitationController::class, 'index']);
    Route::patch('/{id}/response', [DateInvitationController::class, 'respond']);
    Route::post('/verify', [DateInvitationController::class, 'verify']);
});

Route::prefix('dates')->middleware(['auth:sanctum', 'check.suspended'])->group(function () {
    Route::get('/', [DateController::class, 'index'])->middleware('membership:2');
    Route::post('/', [DateController::class, 'store'])->middleware('membership:2');
    Route::patch('/{id}/accept', [DateController::class, 'accept']);
    Route::patch('/{id}/decline', [DateController::class, 'decline']);
    Route::post('/verify', [DateController::class, 'verify']);
});
```

目前前端混用：

- `fetchDates()` 使用 `/date-invitations`：`frontend/src/api/dates.ts:38-40`
- `respondToDate()` 使用 `/date-invitations/{id}/response`：`frontend/src/api/dates.ts:43-45`
- `verifyDateQR()` 使用 `/dates/verify`：`frontend/src/api/dates.ts:54-65`
- 個人頁發起邀請使用 `/dates`：`frontend/src/composables/useDateInviteFromProfile.ts:52-58`

後端 response 差異：

- `DateController::store()` 回傳 `qr_token` / `expires_at`：`backend/app/Http/Controllers/Api/V1/DateController.php:69-75`
- `DateInvitationController::store()` 回傳 `qr_code` / `qr_expires_at`：`backend/app/Http/Controllers/Api/V1/DateInvitationController.php:51-62`
- `DateInvitationController::index()` 不回 QR 欄位：`backend/app/Http/Controllers/Api/V1/DateInvitationController.php:100-116`

這是 API contract drift，需要先裁示標準欄位名：`qr_token` 還是 `qr_code`，`expires_at` 還是 `qr_expires_at`。

### 6.3 後台 admin 關聯

精準 grep `qr_token\|qr_code\|qr_expires_at\|date_invitations` 命中 admin：

- `admin/src/pages/settings/SystemSettingsPage.tsx:149` 只把 `date_invitations` 顯示為資料集統計「約會」。

本輪未看到 admin 顯示 QR token 欄位。若只改前台列表 QR 欄位，admin 直接連動低；若改 DB 欄位或資料集統計則需另查 admin。

### 6.4 規格文件需同步

- `docs/API-001_前台API規格書.md`：統一 `/dates` vs `/date-invitations`、列表 QR 欄位、建立邀請欄位名、response envelope。
- `docs/UI-001_UI_UX設計規格書.md`：補「顯示 QR」與「手動代碼」畫面規格、相機錯誤狀態。
- `docs/PRD-001_MiMeet_約會產品需求規格書.md`：若 token 內容不再是「加密的約會ID + 時間戳 + 用戶ID」，需更新；目前實作是 64 字元 random hex。
- `docs/UF-001_用戶流程圖.md`：若前端時間窗改為 -30/+30，需要同步按鈕狀態；若保留 2 小時則需更新規格。
- `docs/IMPLEMENTATION_STATUS.md`：目前宣稱時間窗 ±30 與手動 fallback 完成，需依實際修正後更新。

### 6.5 是否需要 pre-merge-check

若進入修改階段，建議新增守護：

- `/date-invitations` 列表 response 不得遺失 QR 顯示所需欄位（若裁示要列表回傳）。
- 前端 `transformInvitation()` 不得固定 `qrToken: null` / `expiresAt: null`。
- `QRCodeDisplay` 不得保留 mock-only QR。
- `IMPLEMENTATION_STATUS.md` 不得宣稱與實作相反的時間窗。

### 6.6 Staging rollback 計畫

若修改 API response / 前端型別 / QR 顯示流程，屬 API contract + 前端行為改動，應走 AGENTS.md 的 API Contract 變更標準回滾流程。

## 7. 未驗證項目（誠實清單）

- 線上 `https://mimeet.online` 在 Android / iOS 實機是否實際為 HTTPS secure context：本輪只讀本機文件與腳本，未 SSH/curl droplet。
- 使用者實際測試的是 staging HTTPS、local Vite HTTP、還是 in-app webview：本機程式碼無法判斷。
- Android 手機型號、Chrome/Firefox/Samsung Internet 版本、是否 WebView 內開啟：本機程式碼無法判斷。
- 使用者是否在約會時間「未來 2 小時內」看到「顯示 QR」按鈕：本機程式碼只能確認按鈕顯示條件。
- 實際資料庫內 accepted 約會的 `expires_at` 是否已過期：本輪未連 DB。
- `QRCodeDisplay` mock SVG 在實機是否有視覺顯示但不可掃：本輪未跑前端實機。
- 是否已有未 grep 到的後端單筆 QR refresh endpoint：本輪 grep 指定範圍與關鍵字，未發現。
- 是否允許列表 API 回傳 QR token：這涉及安全/產品裁示，不能由本輪自行決定。

## 8. 建議的修改順序

1. **規格裁示 commit（docs）**：先決定 QR 顯示端的資料來源與欄位名。需要裁示：列表是否回傳 `qr_token` / `qr_expires_at`，或新增/使用單筆 QR endpoint；同時定義手動代碼出現位置。
2. **後端 API commit（backend + API-001）**：依裁示補齊 QR 顯示所需 response，統一 `qr_token` / `qr_code` drift，補測試或 pre-merge guard。
3. **前端 QR 顯示 commit（frontend + UI-001）**：更新 `RawDateInvitation` / `transformInvitation()`，讓 `DateCard` 傳入真 token，`QRCodeDisplay` 產生真 QR 並提供手動代碼 fallback。
4. **掃碼頁韌性 commit（frontend + UI-001）**：改善 `startCamera()` error handling，error/denied state 都保留手動輸入；補本機 HTTPS 實機測試說明。
5. **時間窗與狀態一致性 commit（api/frontend/docs）**：對齊規格 `-30 ~ +30` 或更新規格為實際產品決策；同步 `IMPLEMENTATION_STATUS.md`，必要時補 `pre-merge-check.sh` 守護。
