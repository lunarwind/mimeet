# Audit F — 後台管理 API 端點覆蓋表

> 產出日期：2026-04-24（Audit F 全輪稽核更新）
> 基準：API-002 v1.4 速查表  
> 狀態說明：✅ 存在且一致 / ❌ 缺失 / ⚠️ 部分差異

---

## §2 認證

| 規格端點 | Method | Local | Remote | 狀態 |
|---------|--------|-------|--------|------|
| `/auth/login` | POST | ✅ | ✅ | ✅ |
| `/auth/me` | GET | ✅ | ✅ | ✅ |
| `/auth/logout` | POST | ✅ | ✅ | ✅ |

## §3 儀表板統計

| 規格端點 | Method | Local | Remote | 狀態 |
|---------|--------|-------|--------|------|
| `/stats/summary` | GET | ✅ | ✅ | ✅ |
| `/stats/chart` | GET | ✅ | ✅ | ✅ |
| `/stats/export` | GET | ✅ | ✅ | ✅ |
| `/stats/server-metrics` | GET | ✅ | ✅ | ✅ |

## §4 會員管理

| 規格端點 | Method | Local | Remote | 狀態 |
|---------|--------|-------|--------|------|
| `/members` | GET | ✅ | ✅ | ✅ |
| `/members/{id}` | GET | ✅ | ✅ | ✅ |
| `/members/{id}/actions` | PATCH | ✅ | ✅ | ✅ |
| `/members/{id}/permissions` | PATCH | ✅ | ✅ | ✅ |
| `/members/{id}/profile` | PATCH | ✅ | ✅ | ✅ |
| `/members/{id}/credit-logs` | GET | ❌ | ❌ | **#F-002** |
| `/members/{id}/subscriptions` | GET | ❌ | ❌ | **#F-007** |
| `/members/{id}/chat-logs` | GET | ✅ | ✅ | ✅ |
| `/members/{id}/chat-logs/export` | GET | ✅ | ✅ | ✅ |
| `/members/{id}/change-password` | POST | ✅ | ✅ | ✅ |
| `/members/{id}/verify-email` | POST | ✅ | ✅ | ✅ |
| `/members/{id}/points` | POST | ✅ | ✅ | ✅ |
| `/members/{id}` | DELETE | ✅ | ✅ | ✅ |

## §4.x 驗證審核

| 規格端點 | Method | Local | Remote | 狀態 |
|---------|--------|-------|--------|------|
| `/verifications/pending` | GET | ✅ | ✅ | ✅ |
| `/verifications/{id}` | PATCH | ✅ | ✅ | ✅ |

## §5 公告管理

| 規格端點 | Method | Local | Remote | 狀態 |
|---------|--------|-------|--------|------|
| `/announcements` | GET | ✅ | ✅ | ✅ |
| `/announcements` | POST | ✅ | ✅ | ✅ |
| `/announcements/{id}` | PATCH | ✅ | ✅ | ✅ |
| `/announcements/{id}` | DELETE | ✅ | ✅ | ✅ |

## §6 Ticket / 申訴

| 規格端點 | Method | Local | Remote | 狀態 |
|---------|--------|-------|--------|------|
| `/tickets` | GET | ✅ | ✅ | ✅ |
| `/tickets/{id}` | GET | ❌ | ❌ | **#F-004** |
| `/tickets/{id}` | PATCH | ✅ | ✅ | ✅ |
| `/tickets/{id}/status` | PATCH | ✅ | ✅ | ✅ |
| `/tickets/{id}/reply` | POST | ✅ | ✅ | ✅ |

## §7 聊天記錄

| 規格端點 | Method | Local | Remote | 狀態 |
|---------|--------|-------|--------|------|
| `/chat-logs/search` | GET | ✅ | ✅ | ✅ |
| `/chat-logs/conversations` | GET | ✅ | ✅ | ✅ |
| `/chat-logs/export` | GET | ✅ | ✅ | ✅ |

## §8 支付管理

| 規格端點 | Method | Local | Remote | 狀態 |
|---------|--------|-------|--------|------|
| `/payments` | GET | ✅ | ✅ | ✅ |
| `/payments/export` | GET | ❌ | ❌ | **#F-003** |

## §9 SEO 管理

| 規格端點 | Method | Local | Remote | 狀態 |
|---------|--------|-------|--------|------|
| `/seo/links` | GET | ❌ | ❌ | **#F-005** |
| `/seo/links` | POST | ❌ | ❌ | **#F-005** |
| `/seo/links/{id}/stats` | GET | ❌ | ❌ | **#F-005** |
| `/seo/meta` | GET | ✅ | ✅ | ✅ |
| `/seo/meta/{id}` | PATCH | ✅ | ✅ | ✅ |

## §10 系統設定

| 規格端點 | Method | Local | Remote | 狀態 |
|---------|--------|-------|--------|------|
| `/settings/subscription-plans` | GET | ✅ | ✅ | ✅ |
| `/settings/subscription-plans/{id}` | PATCH | ✅ | ✅ | ✅ |
| `/settings/trial-plan` | PATCH | ❌ | ❌ | **#F-006** |
| `/settings/roles` | GET | ✅ | ✅ | ✅ |
| `/settings/admins` | GET | ✅ | ✅ | ✅ |
| `/settings/admins` | POST | ✅ | ✅ | ✅ |
| `/settings/admins/{id}/role` | PATCH | ✅ | ✅ | ✅ |
| `/settings/admins/{id}` | DELETE | ✅ | ✅ | ✅ |
| `/settings/admins/{id}/reset-password` | POST | ✅ | ✅ | ✅ |
| `/settings/system` | GET | ⚠️ `/settings` | ✅ | ⚠️ |
| `/settings/system/{key}` | PATCH | ⚠️ 批量 PATCH | ✅ | **#F-009** |
| `/settings/member-level-permissions` | GET | ✅ | ✅ | ✅ |
| `/settings/member-level-permissions` | PATCH | ✅ | ✅ | ✅ |
| `/settings/permission-matrix` | GET | ✅ | ✅ | ✅ |
| `/settings/permission-matrix` | PATCH | ✅ | ✅ | ✅ |
| `/settings/system-control` | GET | ✅ | ✅ | ✅ |
| `/settings/app-mode` | PATCH | ✅ | ✅ | ✅ |
| `/settings/mail` | PATCH | ✅ | ✅ | ✅ |
| `/settings/mail/test` | POST | ✅ | ✅ | ✅ |
| `/settings/sms` | PATCH | ✅ | ✅ | ✅ |
| `/settings/sms/test` | POST | ✅ | ✅ | ✅ |
| `/settings/database` | PATCH | ✅ | ✅ | ✅ |
| `/settings/database/test` | POST | ✅ | ✅ | ✅ |

## §11 操作日誌

| 規格端點 | Method | Local | Remote | 狀態 |
|---------|--------|-------|--------|------|
| `/logs` | GET | ✅ | ✅ | ✅ |

## §12 匿名聊天室（Phase 2）

| 規格端點 | Method | Local | Remote | 狀態 |
|---------|--------|-------|--------|------|
| `/anon-chat/settings` | GET/PATCH | ❌ | ❌ | #F-008（Phase 2） |
| `/anon-chat/channels` | GET/POST | ❌ | ❌ | #F-008（Phase 2） |
| `/anon-chat/channels/{id}` | PATCH | ❌ | ❌ | #F-008（Phase 2） |
| `/anon-chat/messages` | GET | ❌ | ❌ | #F-008（Phase 2） |
| `/anon-chat/messages/{id}` | DELETE | ❌ | ❌ | #F-008（Phase 2） |

## §13 廣播管理

| 規格端點 | Method | Local | Remote | 狀態 |
|---------|--------|-------|--------|------|
| `/broadcasts` | GET | ✅ | ✅ | ✅ |
| `/broadcasts` | POST | ✅ | ✅ | ✅ |
| `/broadcasts/{id}` | GET | ✅ | ✅ | ✅ |
| `/broadcasts/{id}/send` | POST | ✅ | ✅ | ✅ |

---

## 覆蓋率統計

| 類別 | 規格數 | 已實作 | 缺失 | 差異 |
|------|--------|--------|------|------|
| 認證 | 3 | 3 | 0 | 0 |
| 統計 | 4 | 4 | 0 | 0 |
| 會員管理 | 13 | 11 | 2 | 0 |
| 驗證審核 | 2 | 2 | 0 | 0 |
| 公告 | 4 | 4 | 0 | 0 |
| Ticket | 5 | 4 | 1 | 0 |
| 聊天記錄 | 3 | 3 | 0 | 0 |
| 支付 | 2 | 1 | 1 | 0 |
| SEO | 5 | 2 | 3 | 0 |
| 系統設定 | 24 | 22 | 1 | 1 |
| 操作日誌 | 1 | 1 | 0 | 0 |
| 匿名聊天（Phase 2）| 5 | 0 | 5 | 0 |
| 廣播 | 4 | 4 | 0 | 0 |
| **合計** | **75** | **61** | **13** | **1** |

**覆蓋率（含 Phase 2）：** 61/75 = 81.3%  
**覆蓋率（排除 Phase 2）：** 61/70 = **87.1%**

| 端點 | Method | 路由存在 | 狀態 | 備註 |
|------|--------|---------|------|------|
| `/auth/login` | POST | ✅ | ✅ | throttle: admin-login |
| `/auth/me` | GET | ❌ | 🔴 | Issue F-002 |
| `/auth/logout` | POST | ❌ | 🟠 | Issue F-002 |
| `/stats/summary` | GET | ✅ | ✅ | |
| `/stats/chart` | GET | ❌ | 🟠 | Issue F-004 |
| `/stats/export` | GET | ❌ | 🟠 | Issue F-004 |
| `/stats/server-metrics` | GET | ❌ | 🟠 | Issue F-004 |
| `/members` | GET | ✅ | ✅ | |
| `/members/{id}` | GET | ✅ | ✅ | |
| `/members/{id}/actions` | PATCH | ✅ | 🟠 | action 名稱不符，Issue F-003 |
| `/members/{id}/permissions` | PATCH | ✅ | ✅ | |
| `/members/{id}/profile` | PATCH | ✅ | ✅ | super_admin only |
| `/members/{id}` | DELETE | ✅ | ✅ | |
| `/members/{id}/change-password` | POST | ✅ | ✅ | |
| `/members/{id}/verify-email` | POST | ✅ | ✅ | |
| `/members/{id}/credit-logs` | GET | ❌ | 🟡 | Issue F-006 |
| `/members/{id}/subscriptions` | GET | ❌ | 🟡 | Issue F-006 |
| `/members/{id}/chat-logs` | GET | ✅ | ✅ | |
| `/members/{id}/chat-logs/export` | GET | ✅ | ✅ | |
| `/members/{id}/points` | POST | ✅ | ✅ | 點數調整 |
| `/verifications/pending` | GET | ✅ | ✅ | |
| `/verifications/{id}` | PATCH | ✅ | ✅ | |
| `/announcements` | GET | ✅ | ✅ | |
| `/announcements` | POST | ✅ | ✅ | |
| `/announcements/{id}` | PATCH | ✅ | ✅ | |
| `/announcements/{id}` | DELETE | ✅ | ✅ | |
| `/tickets` | GET | ✅ | ✅ | |
| `/tickets/{id}` | GET | ❌ | 🟡 | Issue F-005 |
| `/tickets/{id}` | PATCH | ✅ | ✅ | |
| `/tickets/{id}/status` | PATCH | ✅ | ✅ | 規格未列，但已實作 |
| `/tickets/{id}/reply` | POST | ✅ | ✅ | 規格未列，但已實作 |
| `/chat-logs/search` | GET | ✅ | ✅ | |
| `/chat-logs/conversations` | GET | ✅ | ✅ | |
| `/chat-logs/export` | GET | ✅ | ✅ | |
| `/payments` | GET | ✅ | ✅ | |
| `/payments/export` | GET | ❌ | 🔵 | Issue F-010 |
| `/seo/meta` | GET | ✅ | ✅ | |
| `/seo/meta/{id}` | PATCH | ✅ | ✅ | |
| `/seo/links` | GET | ❌ | 📋 | Phase 2，Issue F-007 |
| `/seo/links` | POST | ❌ | 📋 | Phase 2 |
| `/seo/links/{id}/stats` | GET | ❌ | 📋 | Phase 2 |
| `/settings/subscription-plans` | GET | ✅ | ✅ | |
| `/settings/subscription-plans/{id}` | PATCH | ✅ | ✅ | |
| `/settings/trial-plan` | PATCH | ❌ | 🔵 | Issue F-009，可用 subscription-plans |
| `/settings/roles` | GET | ✅ | ✅ | |
| `/settings/admins` | GET | ✅ | ✅ | |
| `/settings/admins` | POST | ✅ | ✅ | |
| `/settings/admins/{id}/role` | PATCH | ✅ | ✅ | |
| `/settings/admins/{id}` | DELETE | ✅ | ✅ | |
| `/settings/admins/{id}/reset-password` | POST | ✅ | ✅ | |
| `/settings/system` | GET | ✅ | ✅ | → system-control |
| `/settings/system/{key}` | PATCH | ✅ | ✅ | → app-mode / mail / sms / database |
| `/settings/system/app-mode` | GET | ✅ | ✅ | |
| `/settings/mail` | PATCH | ✅ | ✅ | |
| `/settings/mail/test` | POST | ✅ | ✅ | |
| `/settings/sms` | PATCH | ✅ | ✅ | |
| `/settings/sms/test` | POST | ✅ | ✅ | |
| `/settings/database` | PATCH | ✅ | ✅ | |
| `/settings/database/test` | POST | ✅ | ✅ | |
| `/settings/member-level-permissions` | GET | ✅ | ✅ | |
| `/settings/member-level-permissions` | PATCH | ✅ | ✅ | |
| `/settings/permission-matrix` | GET | ✅ | ✅ | |
| `/settings/permission-matrix` | PATCH | ✅ | ✅ | |
| `/logs` | GET | ✅ | ✅ | |
| `/anon-chat/settings` | GET/PATCH | ❌ | 📋 | Phase 2，Issue F-008 |
| `/anon-chat/channels` | GET/POST | ❌ | 📋 | Phase 2 |
| `/anon-chat/channels/{id}` | PATCH | ❌ | 📋 | Phase 2 |
| `/anon-chat/messages` | GET | ❌ | 📋 | Phase 2 |
| `/anon-chat/messages/{id}` | DELETE | ❌ | 📋 | Phase 2 |
| `/broadcasts` | GET | ✅ | ✅ | |
| `/broadcasts` | POST | ✅ | ✅ | delivery_mode + filters.gender ✅ |
| `/broadcasts/{id}` | GET | ✅ | ✅ | |
| `/broadcasts/{id}/send` | POST | ✅ | ✅ | |
| `/point-packages` | GET | ✅ | ✅ | |
| `/point-packages/{id}` | PATCH | ✅ | ✅ | |
| `/point-transactions` | GET | ✅ | ✅ | |
| `/settings/ecpay` | GET/POST | ✅ | ✅ | 規格速查表未列，已實作 |

---

## 統計摘要

| 狀態 | 端點數 |
|------|-------|
| ✅ 存在且符合 | 49 |
| 🟠 存在但有差異 | 4（auth/me, auth/logout, stats×3, memberAction） |
| 🟡 缺失（Medium） | 5（tickets/{id}, credit-logs, subscriptions） |
| 🔵 缺失（Low） | 2（payments/export, trial-plan） |
| 📋 Phase 2（計畫中） | 10（anon-chat×5, seo/links×3） |
| **總計** | **70 / 74 個端點已確認** |
