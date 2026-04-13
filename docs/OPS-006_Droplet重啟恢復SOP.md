# OPS-006 Droplet 重啟後恢復 SOP

**版本：** v1.0  
**更新日期：** 2026年4月  
**適用環境：** DigitalOcean Droplet（Ubuntu 24.04 LTS + Docker）  
**基礎域名：** mimeet.online  
**預計時間：** 5-10 分鐘

---

## 前提

MiMeet 所有服務設定為 `restart: unless-stopped`，正常 reboot 後 Docker 容器會自動重啟。本 SOP 適用於容器未自動恢復、或需要手動確認的情況。

---

## Step 1：SSH 連線

```bash
ssh root@188.166.229.100
```

---

## Step 2：確認 Docker 服務正在執行

```bash
systemctl status docker
```

如果 Docker 沒在跑：

```bash
systemctl start docker
systemctl enable docker
```

---

## Step 3：啟動所有容器

```bash
cd /var/www/mimeet
docker compose -f docker-compose.staging.yml up -d
```

等待 MySQL 初始化（約 30 秒），確認三個容器都是 Up 狀態：

```bash
docker compose -f docker-compose.staging.yml ps
```

預期輸出：

| NAME | STATUS |
|------|--------|
| mimeet-app | Up |
| mimeet-db | Up (healthy) |
| mimeet-redis | Up (healthy) |

如果 db 顯示 `(health: starting)`，等待 30 秒後再次查看。

---

## Step 4：確認 Nginx 正在執行

```bash
systemctl status nginx
```

如果沒在跑：

```bash
nginx -t && systemctl start nginx
```

---

## Step 5：確認 SSL 憑證有效

```bash
certbot certificates
```

如果憑證過期：

```bash
certbot renew
systemctl reload nginx
```

---

## Step 6：驗證服務

### 6.1 API 健康檢查

```bash
curl -s -X POST -H "Content-Type: application/json" -d '{"email":"chuck@lunarwind.org","password":"ChangeMe@2026"}' https://api.mimeet.online/api/v1/admin/auth/login
```

應回傳包含 `"success":true` 的 JSON。

### 6.2 瀏覽器驗證

| 服務 | URL | 預期 |
|------|-----|------|
| 前台 | https://mimeet.online | Landing Page 正常顯示 |
| API | https://api.mimeet.online/api/v1 | JSON 回應（可能 404，但非 502） |
| Admin | https://admin.mimeet.online | 登入頁面正常顯示 |

### 6.3 Admin 登入

- URL：`https://admin.mimeet.online`
- Email：`chuck@lunarwind.org`
- 密碼：`ChangeMe@2026`

---

## Step 7：如果容器未正常啟動

### 7.1 查看容器 log

```bash
COMPOSE="docker compose -f docker-compose.staging.yml"

# PHP-FPM app
$COMPOSE logs app --tail=50

# MySQL
$COMPOSE logs db --tail=50

# Redis
$COMPOSE logs redis --tail=20
```

### 7.2 常見問題排查

| 問題 | 排查指令 |
|------|----------|
| 502 Bad Gateway | `$COMPOSE logs app --tail=50` |
| 500 Error | `$COMPOSE exec app tail -100 storage/logs/laravel.log` |
| DB 連線失敗 | `$COMPOSE exec db mysql -u mimeet_user -pmimeet_staging_2026 mimeet` |
| 前台空白 | 確認 `frontend/.env` 有 `VITE_API_BASE_URL=https://api.mimeet.online/api/v1` |
| Email 不能寄 | `$COMPOSE exec app php artisan tinker --execute="(new \App\Services\MailService())->send('test@example.com','Test','<p>Test</p>');"` |
| Redis 不通 | `$COMPOSE exec redis redis-cli ping` — 應回 PONG |
| Nginx 設定錯誤 | `nginx -t` |
| 權限問題 | `$COMPOSE exec app chown -R www-data:www-data storage bootstrap/cache` |

### 7.3 完全重建容器（最後手段）

```bash
cd /var/www/mimeet
docker compose -f docker-compose.staging.yml down
docker compose -f docker-compose.staging.yml up -d --build

# 等待 DB 健康後初始化
$COMPOSE exec app php artisan migrate --force
$COMPOSE exec app php artisan db:seed --force
$COMPOSE exec app php artisan config:cache
$COMPOSE exec app php artisan route:cache
$COMPOSE exec app chown -R www-data:www-data storage bootstrap/cache
```

---

## Step 8：拉取最新代碼並部署（如果需要更新）

```bash
cd /var/www/mimeet && git pull origin main

# 後端
COMPOSE="docker compose -f docker-compose.staging.yml"
$COMPOSE exec app composer install --no-dev --optimize-autoloader
$COMPOSE exec app php artisan migrate --force
$COMPOSE exec app php artisan config:cache
$COMPOSE exec app php artisan route:cache
$COMPOSE exec app chown -R www-data:www-data storage bootstrap/cache

# 前台
cd frontend && npm ci && npm run build

# Admin
cd ../admin && npm ci && npm run build

# 重啟
$COMPOSE restart app
systemctl reload nginx
```

或直接使用更新腳本：

```bash
mimeet-update
```

---

## 快速恢復 Checklist

```
[ ] SSH 連線成功
[ ] docker compose ps — 3 個容器都 Up
[ ] nginx -t — syntax ok
[ ] curl API login — success: true
[ ] 瀏覽器 mimeet.online — 正常
[ ] 瀏覽器 admin.mimeet.online — 可登入
[ ] certbot certificates — 憑證未過期
```

---

## 重要檔案位置

| 檔案 | 路徑 |
|------|------|
| Docker Compose | `/var/www/mimeet/docker-compose.staging.yml` |
| 後端 .env | `/var/www/mimeet/backend/.env`（容器內 `/var/www/html/.env`） |
| 前台 build | `/var/www/mimeet/frontend/dist/` |
| Admin build | `/var/www/mimeet/admin/dist/` |
| Nginx 設定 | `/etc/nginx/sites-available/mimeet` |
| SSL 憑證 | `/etc/letsencrypt/live/mimeet.online/` |
| Laravel log | 容器內 `storage/logs/laravel.log` |
| MySQL 資料 | Docker volume `db_data` |

---

## 服務架構

```
Internet
  ↓ HTTPS (443)
Nginx (Host)
  ├─ mimeet.online       → frontend/dist/ (靜態檔案)
  ├─ admin.mimeet.online → admin/dist/ (靜態檔案)
  └─ api.mimeet.online   → PHP-FPM (Docker :9000)
                              ├─ MySQL (Docker)
                              └─ Redis (Docker)
```
