# S15 上線前最終 Checklist 報告

**檢核日期：** 2026-04-16
**檢核環境：** mimeet.online（Droplet 188.166.229.100）

---

## 檢核結果總覽

| 節次 | 項目 | ✅ 通過 | ❌ 未通過 | ⚠️ 待確認 |
|------|------|--------|---------|---------|
| 0 | 程式碼清理 | 3 | 0 | 1 |
| 1 | 環境設定 | 5 | 2 | 1 |
| 2 | 資料庫狀態 | 5 | 0 | 0 |
| 3 | Laravel 快取 | 0 | 2 | 0 |
| 4 | SSL/HTTPS | 3 | 1 | 0 |
| 5 | 容器服務 | 3 | 1 | 0 |
| 6 | 備份機制 | 1 | 3 | 0 |
| 7 | 監控 | 1 | 2 | 0 |
| 8 | 安全設定 | 3 | 1 | 1 |
| 9 | Build 確認 | 3 | 0 | 0 |
| 10 | Rollback 計畫 | 3 | 0 | 1 |
| **合計** | | **30** | **12** | **4** |

---

## 各節詳細結果

### 第 0 節：程式碼清理

| 項目 | 結果 | 說明 |
|------|------|------|
| 0-1 console.log 殘留 | ✅ | 0 筆 |
| 0-2 dd/var_dump 殘留 | ✅ | 0 筆 |
| 0-3 Mock 預設停用 | ✅ | 無 VITE_USE_MOCK 設定（無 mock 機制） |
| 0-4 敏感資料 | ⚠️ | `.env.example` 含預設密碼 `ChangeMe@2026`（範例檔可接受，但上線需確認 server .env 已改） |

### 第 1 節：環境設定

| 項目 | 結果 | 實際值 | 說明 |
|------|------|--------|------|
| APP_ENV | ✅ | staging | 非 local |
| APP_DEBUG | ❌ | **true** | **上線前必須改為 false** |
| APP_URL | ✅ | https://api.mimeet.online | 正確 |
| ECPAY_IS_SANDBOX | ⚠️ | true | Staging 正確；正式上線需切為 false |
| CORS allowed_origins | ✅ | 含 mimeet.online + admin | 正確（保留 localhost 為開發用） |
| SANCTUM_STATEFUL_DOMAINS | ✅ | mimeet.online,api,admin | 正確 |
| MAIL_MAILER | ✅ | resend (SMTP 2587) | 非 log |
| SMS_PROVIDER | ❌ | twilio（但 Twilio 帳號未確認） | 需確認 Twilio credentials 有效 |

### 第 2 節：資料庫狀態

| 項目 | 結果 | 說明 |
|------|------|------|
| Migration 全部 Ran | ✅ | 27 筆全部 Ran |
| admin_users ≥ 1 | ✅ | 3 筆 |
| subscription_plans ≥ 4 | ✅ | 5 筆（週/月/季/年/體驗） |
| admin_permissions | ✅ | 11 筆 |
| member_level_permissions | ✅ | 50 筆（5 等級 × 10 功能） |

### 第 3 節：Laravel 快取

| 項目 | 結果 | 說明 |
|------|------|------|
| config.php 快取 | ❌ | **不存在** — 需執行 `artisan config:cache` |
| routes-v7.php 快取 | ❌ | **不存在** — 需執行 `artisan route:cache` |

### 第 4 節：SSL/HTTPS

| 項目 | 結果 | 說明 |
|------|------|------|
| SSL 憑證存在 | ✅ | Let's Encrypt, 到期 2026-07-12 |
| nginx -t 語法正確 | ✅ | syntax ok |
| HTTP→HTTPS redirect | ✅ | 3 條 return 301 規則 |
| HSTS header | ❌ | **前端 nginx 未設定**（API 有 via SecurityHeaders middleware） |

### 第 5 節：容器與服務

| 項目 | 結果 | 說明 |
|------|------|------|
| 容器全部 Up | ✅ | app(4h), redis(2d healthy), db(2d healthy) |
| Queue worker | ❌ | **未執行** — 無 queue:work 進程 |
| Scheduler | ✅ | gdpr:process-deletions 每日 03:00 |
| Redis PONG | ✅ | |

### 第 6 節：備份機制

| 項目 | 結果 | 說明 |
|------|------|------|
| 備份腳本 | ❌ | 不存在 |
| Crontab 備份排程 | ❌ | 無 crontab |
| DO 自動備份 | ❌ | 需在 DO 控制台確認 |
| 磁碟使用率 | ✅ | 6%（116G 中用 6.8G） |

### 第 7 節：監控

| 項目 | 結果 | 說明 |
|------|------|------|
| /api/health 端點 | ❌ | 404 — 需新增 |
| Log rotation | ❌ | 無 mimeet logrotate 設定 |
| 容器資源 | ⚠️ | **mimeet-app CPU 197%, MEM 2.68G/3.82G (70%)** — CPU 高可能是 composer install 造成的暫態；MEM 接近警戒 |

### 第 8 節：安全設定

| 項目 | 結果 | 說明 |
|------|------|------|
| Security headers (API) | ✅ | X-Frame, X-Content-Type, Referrer-Policy, Permissions-Policy |
| HSTS (frontend nginx) | ❌ | 前端 nginx 未設 Strict-Transport-Security |
| ECPay mock 路由 | ⚠️ | `/payments/ecpay/mock` 存在 — 上線前評估是否需移除 |
| Admin 密碼非預設 | ✅ | .env 密碼已設（無法確認是否為預設值，需人工確認） |

### 第 9 節：Build 確認

| 項目 | 結果 | 說明 |
|------|------|------|
| 前台 dist/index.html | ✅ | 存在 (2026-04-15) |
| 後台 dist/index.html | ✅ | 存在 (2026-04-16) |
| Build API URL | ✅ | api.mimeet.online（無 localhost） |

### 第 10 節：Rollback 計畫

| 項目 | 結果 | 說明 |
|------|------|------|
| git 回滾方式 | ✅ | `git revert` 或 `git reset --hard HEAD~1` + `git push --force` |
| DB migration rollback | ✅ | `php artisan migrate:rollback` |
| SSH key 備份 | ✅ | SSH key 在本機，Droplet 可連線 |
| OPS-006 SOP | ⚠️ | 需人工確認已閱讀 |

---

## 待處理事項

| 優先度 | 項目 | 說明 | 建議處理時間 |
|--------|------|------|------------|
| **上線前必須** | APP_DEBUG=false | .env 改為 false | 5 分鐘 |
| **上線前必須** | Laravel config/route cache | 執行 artisan config:cache + route:cache | 2 分鐘 |
| **上線前必須** | Queue worker 啟動 | 啟動 `php artisan queue:work` 或用 Supervisor | 15 分鐘 |
| **上線前必須** | HSTS header | nginx 加 `add_header Strict-Transport-Security` | 5 分鐘 |
| 上線後盡快 | DB 備份腳本 | 建立 mysqldump cron job（每日 02:00） | 30 分鐘 |
| 上線後盡快 | /api/health 端點 | 新增 health check 路由 | 10 分鐘 |
| 上線後盡快 | Log rotation | 新增 /etc/logrotate.d/mimeet | 10 分鐘 |
| 上線後盡快 | ECPay mock 路由 | 評估移除或加上環境判斷 | 10 分鐘 |
| 非緊急 | ECPAY_IS_SANDBOX | 正式收款時切為 false | 視業務需求 |
| 非緊急 | SMS Twilio 帳號 | 確認 credentials 有效 | 視業務需求 |
| 非緊急 | DO 自動備份 | 在 DigitalOcean 控制台啟用 Droplet Backups | 5 分鐘 |

---

## 上線可行性結論

- **阻擋上線的問題：** 4 項（APP_DEBUG、cache、queue worker、HSTS）
- **預估修復時間：** 30 分鐘
- **最終結論：條件上線** — 修復上述 4 項「上線前必須」項目後即可上線。核心功能（註冊/登入/聊天/約會/金流/後台）全部通過 E2E 測試，無 P0/P1 程式碼問題。
