# Audit-C Round 2 — 聊天 / 約會驗證 / 即時通訊

> 先讀 prompts/audit/_common.md。

## 規格範圍
- docs/API-001 §4（聊天）§4.1.1 / §4.1.2 / §4.1.3 / §4.1.5
- docs/API-001 §5（約會驗證）§5.1 / §5.2
- docs/PRD-001 §4.2.3（QR碼約會驗證系統）
- docs/DEV-008 §4.2 / §8（QR 約會加分、訊息發送權限）
- docs/DEV-006 §3.2（conversations + messages schema）
- docs/UF-001 UF-05（聊天）UF-06（QR 約會）

## 前次稽核
- docs/audits/audit-C-20260422.md
- docs/audits/audit-C-*.md（若有）

## 程式碼範圍

```bash
# 後端
backend/app/Http/Controllers/Api/V1/ChatController.php
backend/app/Http/Controllers/Api/V1/DateController.php
backend/app/Http/Controllers/Api/V1/DateInvitationController.php
backend/app/Services/ChatService.php
backend/app/Services/DateService.php
backend/app/Models/Conversation.php
backend/app/Models/Message.php
backend/app/Models/DateInvitation.php
backend/app/Events/MessageSent.php
backend/app/Events/MessageRecalled.php
backend/app/Exceptions/CreditScoreRestrictionException.php
backend/app/Exceptions/DailyLimitException.php

# 前端
frontend/src/api/chats.ts
frontend/src/api/dates.ts
frontend/src/views/app/MessagesView.vue
frontend/src/views/app/ChatRoomView.vue
frontend/src/views/app/DatesView.vue
frontend/src/views/app/QRScanView.vue
frontend/src/composables/useChat.ts
frontend/src/echo.ts
```

## 規格端點清單（P1）
- GET/POST /chats（list / create）
- GET /chats/{id}/info
- GET /chats/{id}/messages
- POST /chats/{id}/messages（text + image）
- DELETE /chats/{id}/messages/{msg_id}（5 分鐘內回收）
- POST /dates、GET /dates、PATCH /dates/{id}/accept、/decline
- POST /dates/verify（token 在 body）
- POST /date-invitations/verify（legacy 相容路由）

## 模組特有檢查

### P4 業務規則對照
| 規則 | 規格值 | 怎麼驗 |
|---|---|---|
| QR token 有效期 | 約會時間 ±30 分 | `grep -n "expires_at\|date_time" backend/app/Services/DateService.php` |
| GPS 距離 | ≤ 500m | `grep -n "500\|calculateDistance" backend/app/Services/DateService.php` |
| GPS 通過加分 | +5 | `credit_add_date_gps` |
| GPS 未通過加分 | +2 | `credit_add_date_no_gps` |
| 24h 冷卻防刷 | 是 | `grep -n "date_score:.*\|cooldownKey" backend/app/Services/DateService.php` |
| 訊息字數上限 | 2000 | `grep -n "max:2000\|max:500" backend/app/Http/Controllers/Api/V1/ChatController.php` |
| 圖片訊息大小 | 5MB | `grep -n "max:5120\|5MB" backend/app/Http/Controllers/Api/V1/ChatController.php` |
| 訊息回收時限 | 5 分鐘 | `grep -nA 5 "is_recalled\|recall" backend/app/Http/Controllers/Api/V1/ChatController.php` |
| 每日訊息上限（Lv0/Lv1/Lv3）| 10/30/無限 | `grep -rn "DailyLimitException\|daily_limit\|message_limit" backend/app/` |
| 誠信區間發訊規則 | 較高可發給較低 | `grep -rn "CreditScoreRestriction" backend/app/` |

### P11 模組特有
```bash
# DateController vs DateInvitationController 兩處 verify 是否重複
grep -nA 20 "function verify" backend/app/Http/Controllers/Api/V1/DateController.php backend/app/Http/Controllers/Api/V1/DateInvitationController.php

# 訊息類型只有 text/image/qr_invite 三種，是否其他地方寫死字串
grep -rn "'text'\|'image'\|'qr_invite'" backend/app/

# WebSocket 廣播事件是否多處重複定義
grep -rn "broadcast(new\|->broadcast()" backend/app/

# 約會冷卻 24h 的 cache key 命名是否散落
grep -rn "date_score:\|date_cooldown" backend/
```

## 重點關注（前次 Round 1）
- C-010：規格 /date-invitations/* 還是 /dates/*（兩條都還在？）
- C-011：verify 錯誤碼是否補進規格 §5.4
- C-012：GET /chats 回應是否含 unread_total
- C-013：訊息回收限制是否在規格中
