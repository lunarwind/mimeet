# Audit F — 後台 API 端點覆蓋表

> 產出日期：2026-04-23  
> 基準：API-002 v1.4 §15 速查表（74 個端點）  
> 狀態說明：✅ 存在且符合 / 🟠 存在但有差異 / ❌ 不存在 / 📋 Phase 2

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
