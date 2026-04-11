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

| 變數 | 說明 | Production |
|------|------|-----------|
| VITE_API_BASE_URL | 後端 API | https://api.mimeet.tw/api/v1 |
| VITE_WS_HOST | WebSocket host | api.mimeet.tw |
| VITE_WS_PORT | WebSocket port | 8080 |
| VITE_WS_KEY | WebSocket key | 同 REVERB_APP_KEY |
| VITE_WS_SCHEME | WebSocket scheme | wss |

## Admin 環境變數

| 變數 | 說明 | Production |
|------|------|-----------|
| VITE_API_BASE_URL | 後端 API | https://api.mimeet.tw/api/v1 |

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
