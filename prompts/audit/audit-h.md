# Audit-H Round 2 — 通知 / 動態 / 舉報 / 匿名聊天 / Phase 標注

> 先讀 prompts/audit/_common.md。

## 規格範圍
- docs/API-001 §3.6（FCM Token）
- docs/API-001 §6（動態內容 — Phase 2）
- docs/API-001 §8（舉報檢舉）
- docs/API-001 §9.1（公告）
- docs/API-001 §10.4（歷史回報 + followups）
- docs/API-001 §10.6（匿名聊天 — Phase 2）
- docs/API-001 §10.7（通知 API）
- docs/API-001 §11（點數系統 — 已併入 Audit-D）

## 前次稽核
- docs/audits/audit-H-20260423.md

## 程式碼範圍

```bash
# 通知
backend/app/Http/Controllers/Api/V1/NotificationController.php
backend/app/Services/NotificationService.php
backend/app/Services/FcmService.php
backend/app/Models/Notification.php
backend/app/Models/FcmToken.php
backend/app/Events/NotificationReceived.php

# 舉報
backend/app/Http/Controllers/Api/V1/ReportController.php
backend/app/Services/ReportService.php
backend/app/Models/Report.php
backend/app/Models/ReportFollowup.php
backend/app/Models/ReportImage.php

# 公告 / 廣播
backend/app/Http/Controllers/Admin/AnnouncementController.php
backend/app/Http/Controllers/Api/V1/UserBroadcastController.php

# 動態（Phase 2 — 確認是否真未實作）
backend/app/Http/Controllers/Api/V1/PostController.php  # 預期 ❌
backend/app/Models/Post.php                             # 預期 ❌

# 匿名聊天（Phase 2 — 確認）
backend/app/Http/Controllers/Api/V1/AnonymousChatController.php  # 預期 ❌

# 前端
frontend/src/api/notifications.ts
frontend/src/api/reports.ts
frontend/src/views/app/NotificationsView.vue
frontend/src/views/app/ReportsView.vue
frontend/src/views/app/ReportsHistoryView.vue
frontend/src/stores/notification.ts  # 若存在
```

## 規格端點清單（P1）
- POST/DELETE /me/fcm-token
- POST/GET /reports、GET /reports/history、POST /reports/{id}/followups
- GET /announcements (active)
- GET /notifications、PATCH /notifications/{id}/read、PATCH /notifications/read-all
- GET /notifications/unread-count（規格存在但可能未實作）
- §6 動態（POST/GET/DELETE /contents、/posts/comments）— Phase 2
- §10.6 匿名聊天 — Phase 2

## 模組特有檢查

### P4 業務規則
| 規則 | 規格值 | 怎麼驗 |
|---|---|---|
| 舉報 type | string enum | `grep -n "type.*in:" backend/app/Http/Controllers/Api/V1/ReportController.php` |
| 舉報雙方扣分 | -10 / -10 | `grep -n "credit_sub_report_user" backend/app/Services/ReportService.php` |
| 通知 in-app + FCM 雙軌 | 是 | `grep -nA 20 "function notify" backend/app/Services/NotificationService.php` |
| 對話靜音時 skipPush | 是 | `grep -n "skipPush" backend/app/Services/NotificationService.php` |
| FCM 在無憑證時 fallback | log + return | `grep -nA 10 "FIREBASE_CREDENTIALS_PATH" backend/app/Services/FcmService.php` |

### P11 模組特有
```bash
# Phase 2 功能的「假實作」是否都明確標 ❌
ls backend/app/Http/Controllers/Api/V1/ | grep -iE "post|anonymous"

# 規格中標 Phase 2 但 code 仍有殘留路由
grep -rn "/contents\|/posts/" backend/routes/api.php

# 通知類型枚舉是否散落
grep -rn "'new_message'\|'new_favorite'\|'profile_visited'" backend/app/

# Notification mark-read 前後端同步
grep -nE "markAllRead\|markRead\|notif.is_read" frontend/src/views/app/NotificationsView.vue

# unread_count 計算是否多處
grep -rn "where('is_read', 0)" backend/app/
```

## 重點關注（前次 Round 1）
- H-001：舉報 type 數字 vs 字串（前端送 number / 後端要 string）
- H-002：§6 動態系統規格 vs Phase 2 標注
- H-003：Notification mark-read 是否實際呼叫後端
- H-004：FCM Token 路由實作
- H-005：Report Followup 路由
- H-006：Announcement 規格更新
