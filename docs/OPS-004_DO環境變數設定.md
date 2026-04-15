# OPS-003 DigitalOcean 環境變數設定清單

**建立日期：** 2026年4月  
**適用範圍：** DigitalOcean App Platform / Droplet 部署

---

## Backend 必要環境變數

### App
| 變數 | 說明 | 範例值 |
|------|------|--------|
| APP_KEY | Laravel 加密金鑰 | `php artisan key:generate` 產生 |
| APP_ENV | 環境 | production |
| APP_DEBUG | 除錯模式 | false |
| APP_URL | API 網址 | https://api.mimeet.tw |

### Database
| 變數 | 說明 |
|------|------|
| DB_HOST | MySQL host |
| DB_PORT | MySQL port（DO: 25060） |
| DB_DATABASE | 資料庫名稱 |
| DB_USERNAME | 資料庫用戶 |
| DB_PASSWORD | 資料庫密碼 |

### Redis
| 變數 | 說明 |
|------|------|
| REDIS_HOST | Redis host |
| REDIS_PASSWORD | Redis 密碼 |
| REDIS_PORT | Redis port（DO: 25061） |

### Sanctum / Session
| 變數 | 說明 |
|------|------|
| SANCTUM_STATEFUL_DOMAINS | mimeet.tw,www.mimeet.tw,admin.mimeet.tw |
| SESSION_DOMAIN | .mimeet.tw |
| SESSION_SECURE_COOKIE | true |

### WebSocket (Reverb)
| 變數 | 說明 |
|------|------|
| REVERB_APP_KEY | Reverb key |
| REVERB_APP_SECRET | Reverb secret |
| REVERB_HOST | 0.0.0.0 |
| REVERB_PORT | 8080 |

---

## Frontend 環境變數（build 時注入）

| 變數 | Online Test | Production |
|------|------------|-----------|
| VITE_API_BASE_URL | http://api.mimeet.online/api/v1 | https://api.mimeet.tw/api/v1 |
| VITE_WS_HOST | api.mimeet.online | api.mimeet.tw |
| VITE_WS_PORT | 8080 | 8080 |
| VITE_WS_KEY | 同 REVERB_APP_KEY | 同 REVERB_APP_KEY |
| VITE_WS_SCHEME | http | https |

## Admin 環境變數

| 變數 | Online Test | Production |
|------|------------|-----------|
| VITE_API_BASE_URL | http://api.mimeet.online/api/v1 | https://api.mimeet.tw/api/v1 |

---

## 部署後指令

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan storage:link
```

---

## PHP-FPM 設定（Docker 容器內）

PHP-FPM pool 設定位於 `backend/docker/php-fpm/www.conf`，在 Dockerfile 中複製為 `/usr/local/etc/php-fpm.d/zz-www.conf`。

| 參數 | 值 | 說明 |
|------|-----|------|
| `pm` | dynamic | 動態 worker 管理 |
| `pm.max_children` | 50 | 支援約 200 人同時在線 |
| `pm.start_servers` | 10 | 啟動時預建 10 個 worker |
| `pm.min_spare_servers` | 5 | 最少保持 5 個閒置 worker |
| `pm.max_spare_servers` | 20 | 最多保持 20 個閒置 worker |
| `pm.max_requests` | 500 | 每個 worker 處理 500 次請求後重啟（防 memory leak） |
| `request_terminate_timeout` | 60s | 單一請求最長 60 秒 |

### PHP INI 自訂（寫入 Dockerfile）

| 設定 | 值 | 原因 |
|------|-----|------|
| `opcache.jit` | disable | PHP 8.2 JIT 在 FPM 下可能導致 SIGSEGV |
| `output_buffering` | 4096 | 修復 POST body 被回顯到 response 的問題 |
