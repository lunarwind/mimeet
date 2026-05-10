# MiMeet 資安檢測報告

**檢測��期：** 2026-04-17
**檢測範圍：** 前台、後台、API

---

## 一、SEO / robots 防爬蟲設定

| 項目 | 狀態 | 說明 |
|------|------|------|
| 前台 robots.txt 存在 | ❌ | `robots.txt` 不存在（返回 index.html），爬蟲可索引所有公開頁 |
| /app/* 被 Disallow | ❌ | 無 robots.txt，未設定 Disallow |
| /suspended/* 被 Disallow | ❌ | 同上 |
| 後台 noindex 設定 | ❌ | 無 `X-Robots-Tag` header，無 `<meta name="robots" content="noindex">` |
| 前台 SPA hash mode 保護 | ✅ | 使用 `createWebHashHistory`，hash 路由不被搜索引擎索引 |

## 二、API 未授權存取

| 端點 | 預期 | 實際 | 狀態 |
|------|------|------|------|
| GET /users/me（無 token） | 401 | 401 | ✅ |
| GET /users/search（無 token） | 401 | 401 | ✅ |
| GET /chats（無 token） | 401 | 401 | ✅ |
| GET /dates（無 token） | 401 | 401 | ✅ |
| GET /subscriptions/me（無 token） | 401 | 401 | ✅ |
| GET /me/blocked-users（無 token） | 401 | 401 | ✅ |
| GET /users/me/visitors（無 token） | 401 | 401 | ✅ |
| GET /users/me/following（無 token） | 401 | 401 | ✅ |
| POST /reports（無 token） | 401 | 401 | ✅ |
| GET /admin/members（無 token） | 401 | 401 | ✅ |
| GET /admin/settings（無 token） | 401 | 401 | ✅ |
| GET /admin/payments（無 token） | 401 | 401 | ✅ |
| GET /admin/tickets（無 token） | 401 | 401 | ✅ |
| GET /admin/logs（無 token） | 401 | 401 | ✅ |
| 前台 token → /admin/members | 401 | 401 | ✅ |

## 三、HTTP 安全標頭

| 標頭 | 前台 (nginx) | API (Laravel) | 後台 (nginx) |
|------|-------------|---------------|-------------|
| Strict-Transport-Security | ✅ max-age=31536000 | ✅ max-age=31536000 | ✅ max-age=31536000 |
| X-Frame-Options | ❌ 缺少 | ✅ DENY | ❌ 缺少 |
| X-Content-Type-Options | ❌ 缺少 | ✅ nosniff | ❌ 缺少 |
| X-XSS-Protection | ❌ 缺少 | ✅ 1; mode=block | ❌ 缺少 |
| Referrer-Policy | ❌ 缺少 | ✅ strict-origin-when-cross-origin | ❌ 缺少 |
| Permissions-Policy | ❌ 缺少 | ✅ camera=(), microphone=(), geolocation=(self) | ❌ 缺少 |
| Content-Security-Policy | ❌ 缺少 | ❌ 缺少 | ❌ 缺少 |
| X-Robots-Tag (後台) | N/A | N/A | ❌ 缺少 |
| Server 版本 | ⚠️ nginx/1.24.0 | ⚠️ nginx/1.24.0 | ⚠️ nginx/1.24.0 |

> API 安全標頭由 Laravel SecurityHeaders middleware 設定，完整。前台/後台 nginx 只有 HSTS，其餘缺失。

## 四、Rate Limiting

| 端點 | 行為 | 狀態 |
|------|------|------|
| 登入（連續 6 次不同 email） | 全部 401，未觸發 429 | ⚠️ IP 層 rate limit 的 20 次門檻未觸發（不同 email 每次只計 1 次 email 失敗） |
| OTP 發送（連續 3 次） | 第 1 次 500（可能 Redis 問題），第 2-3 次 429 | ✅ 冷卻機制生效 |

## 五、敏感資訊洩漏

| 項目 | 狀態 | 說明 |
|------|------|------|
| 500 錯誤不洩漏 stack trace | ✅ | APP_DEBUG=false 生效，回傳標準 validation error |
| SQL injection 防護 | ✅ | 參數化查詢，未洩漏 SQL 錯誤 |
| /auth/me 不含 password | ✅ | password/remember_token 不在回應中 |
| phone 欄位遮罩策略 | ✅ | audit log / blacklist `value_masked` / `phone_change_histories` 場景遮罩；user-self response（register / login / me / phone-change）為 raw E.164（PR-4, 2026-05-08）|
| Server header 洩漏版本 | ⚠️ | `nginx/1.24.0 (Ubuntu)` — 建議設 `server_tokens off` |

## 六、路由守衛

| 項目 | 狀態 | 說明 |
|------|------|------|
| 前台 /app/* requiresAuth | ✅ | 全部 app 路由 `meta.requiresAuth: true` |
| 前台 minLevel 檢查 | ✅ | 聊天/約會要求 minLevel: 2 |
| 前台停權用戶限制 | ✅ | guards.ts 有停權重導邏輯 |
| 後台 ProtectedRoute | ✅ | `isLoggedIn` 檢查 + `Navigate to /login` |
| 後台 catch-all | ✅ | `path="*"` → redirect to /login |

## 七、敏感端點

| 項目 | 狀態 | 說明 |
|------|------|------|
| ECPay mock 路由 | ⚠️ | HTTP 500（不帶參數報錯），但路由存在且可被探測 |
| .env 檔案不可存取 | ✅ | 404 |
| Laravel log 不可存取 | ✅ | 404 |
| phpinfo.php 不可存取 | ✅ | 404 |
| .git/config 不可存取 | ✅ | 404（nginx deny 規則生效） |

## 八、CORS

| 項目 | 狀態 | 說明 |
|------|------|------|
| 拒絕未授權 origin | ✅ | `https://evil.com` → 無 ACAO header |
| 允許授權 origin | ✅ | `https://mimeet.online` → ACAO 正確 |

## 九、Token 安全

| 項目 | 狀態 | 說明 |
|------|------|------|
| Sanctum token 有效期 | ✅ | 1440 分鐘（24 小時） |
| 前台 token 無法存取後台 | ✅ | admin 路由獨立 guard |

---

## 十、發現問題彙整

| 嚴重度 | 項目 | 說明 | 建議修正 |
|--------|------|------|---------|
| P1 | 前台/後台 nginx 缺安全標頭 | X-Frame-Options, X-Content-Type-Options, Referrer-Policy 全缺 | nginx 加 `add_header` |
| P1 | 後台無 noindex 設定 | admin.mimeet.online 可被搜索引擎索引 | nginx 加 `X-Robots-Tag: noindex` 或 HTML meta |
| P2 | 前台無 robots.txt | 無 Disallow 規則 | 建立 `frontend/public/robots.txt` |
| P2 | Server 洩漏 nginx 版本 | `nginx/1.24.0 (Ubuntu)` | nginx.conf 加 `server_tokens off` |
| P2 | ECPay mock 路由存在 | 生產環境不應有 mock 端點 | 加環境判斷或移除 |
| P3 | CSP header 缺失 | 無 Content-Security-Policy | 建議上線後逐步導入 |

## 十一、總結評估

| 項目 | 評估 |
|------|------|
| P0 嚴重漏洞 | **0** |
| P1 需修復 | **2**（nginx 安全標頭、後台 noindex） |
| P2 建議修復 | **3**（robots.txt、server 版本、mock 路由） |
| P3 觀察 | **1**（CSP） |

**整體資安評估：良好。** 核心防護到位（auth 401 全通過、CORS 正確、無敏感洩漏、SQL injection 防護正常、HSTS 已設、路由守衛完整）。主要缺失在 nginx 層安全標頭（前台/後台未設定）和 SEO 防護（robots.txt + noindex）。
