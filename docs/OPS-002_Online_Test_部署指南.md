# OPS-002 MiMeet Online Test 部署指南

**版本：** v2.0  
**更新日期：** 2026年4月  
**環境：** DigitalOcean Ubuntu 24.04 LTS + Docker  
**基礎域名：** mimeet.online  
**預計時間：** 45-60 分鐘

> 全程使用 HTTPS + Docker 容器化部署。Email 預設使用 Resend API，SMS 預設使用 Twilio。

---

## 1. DigitalOcean Droplet 建立

1. 登入 [DigitalOcean](https://cloud.digitalocean.com) → Create → Droplets
2. Region: **Singapore (sgp1)**
3. OS: **Ubuntu 24.04 LTS x64**
4. Plan: **4GB RAM / 2 vCPU / 80GB SSD**（$24/月）
5. Auth: SSH Key
6. Hostname: `mimeet-online-test`

連線：
```bash
ssh root@YOUR_SERVER_IP
```

---

## 2. DNS 設定

在域名 `mimeet.online` 的 DNS 管理介面新增 3 筆 A Record：

| 子網域 | Type | Value |
|--------|------|-------|
| `@` 或 `mimeet.online` | A | YOUR_SERVER_IP |
| `api` | A | YOUR_SERVER_IP |
| `admin` | A | YOUR_SERVER_IP |

對應：
- `mimeet.online` → 前台
- `api.mimeet.online` → 後端 API
- `admin.mimeet.online` → 後台管理

---

## 3. 伺服器初始設定

```bash
apt update && apt upgrade -y
timedatectl set-timezone Asia/Taipei
```

---

## 4. 安裝 Docker + Docker Compose

```bash
# Docker 官方安裝
curl -fsSL https://get.docker.com | sh
systemctl enable docker

# 確認
docker --version
docker compose version
```

---

## 5. 安裝 Nginx + Certbot（HTTPS）

Nginx 在 host 上跑，負責 SSL 終止和反向代理到 Docker 容器。

```bash
apt install -y nginx certbot python3-certbot-nginx ufw

# 防火牆
ufw allow OpenSSH && ufw allow 80 && ufw allow 443
ufw --force enable
```

---

## 6. Clone 專案 + 啟動 Docker

```bash
mkdir -p /var/www/mimeet && cd /var/www/mimeet
git clone https://github.com/lunarwind/mimeet.git .
```

### 建立 docker-compose.staging.yml

```bash
cat > docker-compose.staging.yml << 'EOF'
services:
  app:
    build:
      context: ./backend
      dockerfile: Dockerfile.dev
    container_name: mimeet-app
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - ./backend:/var/www/html
      - /var/www/html/vendor
    ports:
      - "9000:9000"
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy
    environment:
      - APP_ENV=staging
      - APP_DEBUG=true
      - DB_HOST=db
      - DB_DATABASE=mimeet
      - DB_USERNAME=mimeet_user
      - DB_PASSWORD=${DB_PASSWORD:-mimeet_staging_2026}
      - REDIS_HOST=redis
      - CACHE_DRIVER=redis
      - SESSION_DRIVER=redis
      - QUEUE_CONNECTION=redis
    networks:
      - mimeet

  db:
    image: mysql:8.0
    container_name: mimeet-db
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD:-root_staging_2026}
      MYSQL_DATABASE: mimeet
      MYSQL_USER: mimeet_user
      MYSQL_PASSWORD: ${DB_PASSWORD:-mimeet_staging_2026}
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-p${DB_ROOT_PASSWORD:-root_staging_2026}"]
      interval: 5s
      timeout: 5s
      retries: 10
    networks:
      - mimeet

  redis:
    image: redis:7-alpine
    container_name: mimeet-redis
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 5
    networks:
      - mimeet

volumes:
  db_data:

networks:
  mimeet:
    driver: bridge
EOF
```

### 建立後端 .env

```bash
cp backend/.env.example backend/.env
nano backend/.env
```

**必改項目：**
```env
APP_ENV=staging
APP_DEBUG=true
APP_URL=https://api.mimeet.online

DB_HOST=db
DB_DATABASE=mimeet
DB_USERNAME=mimeet_user
DB_PASSWORD=mimeet_staging_2026

REDIS_HOST=redis

SANCTUM_STATEFUL_DOMAINS=mimeet.online,admin.mimeet.online,api.mimeet.online
SESSION_DOMAIN=.mimeet.online

# Email — Resend API（預設）
MAIL_MAILER=resend
RESEND_API_KEY=re_YOUR_RESEND_API_KEY
MAIL_FROM_ADDRESS=noreply@mimeet.online
MAIL_FROM_NAME=MiMeet

# SMS — Twilio（預設）
SMS_PROVIDER=twilio
TWILIO_SID=YOUR_TWILIO_SID
TWILIO_AUTH_TOKEN=YOUR_TWILIO_AUTH_TOKEN
TWILIO_FROM=+1XXXXXXXXXX
```

### 啟動 Docker

```bash
cd /var/www/mimeet
docker compose -f docker-compose.staging.yml up -d

# 等待 MySQL 初始化（約 30 秒）
docker compose -f docker-compose.staging.yml logs db --tail=5
```

### 初始化後端

```bash
COMPOSE="docker compose -f docker-compose.staging.yml"

$COMPOSE exec app php artisan key:generate --force
$COMPOSE exec app php artisan migrate --force
$COMPOSE exec app php artisan db:seed --force
$COMPOSE exec app php artisan storage:link
$COMPOSE exec app php artisan config:cache
$COMPOSE exec app php artisan route:cache
$COMPOSE exec app chown -R www-data:www-data storage bootstrap/cache
$COMPOSE exec app chmod -R 775 storage bootstrap/cache
```

---

## 7. Build 前台 + Admin

需要 Node.js 22 在 host 上（用於 build，不放在 Docker 內）：

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs
node -v  # v22.x.x
```

### Build 前台

```bash
cd /var/www/mimeet/frontend
cat > .env << 'EOF'
VITE_API_BASE_URL=https://api.mimeet.online/api/v1
EOF
npm ci && npm run build
```

### Build Admin

```bash
cd /var/www/mimeet/admin
cat > .env << 'EOF'
VITE_API_BASE_URL=https://api.mimeet.online/api/v1
EOF
npm ci && npm run build
```

---

## 8. Nginx + HTTPS 設定

### 先建立 HTTP 設定（Certbot 需要）

```bash
cat > /etc/nginx/sites-available/mimeet << 'NGINX'
# ── API (api.mimeet.online) ──
server {
    listen 80;
    server_name api.mimeet.online;
    root /var/www/mimeet/backend/public;
    index index.php;
    client_max_body_size 10M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    location /storage { alias /var/www/mimeet/backend/storage/app/public; }
    location ~ /\.(env|git) { deny all; return 404; }
}

# ── Frontend (mimeet.online) ──
server {
    listen 80;
    server_name mimeet.online;
    root /var/www/mimeet/frontend/dist;
    index index.html;
    location / { try_files $uri $uri/ /index.html; }
    location ~* \.(js|css|png|jpg|ico|svg|woff2?)$ { expires 7d; }
}

# ── Admin (admin.mimeet.online) ──
server {
    listen 80;
    server_name admin.mimeet.online;
    root /var/www/mimeet/admin/dist;
    index index.html;
    location / { try_files $uri $uri/ /index.html; }
    location ~* \.(js|css|png|jpg|ico|svg|woff2?)$ { expires 7d; }
}
NGINX

ln -sf /etc/nginx/sites-available/mimeet /etc/nginx/sites-enabled/mimeet
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

### 申請 SSL 憑證

```bash
certbot --nginx \
  -d mimeet.online \
  -d api.mimeet.online \
  -d admin.mimeet.online \
  --non-interactive \
  --agree-tos \
  --email admin@mimeet.online
```

Certbot 會自動修改 Nginx 設定加入 SSL。確認：

```bash
nginx -t && systemctl reload nginx
```

### 設定自動更新憑證

```bash
certbot renew --dry-run
# 確認沒有錯誤，Certbot 會自動在到期前更新
```

---

## 9. 驗證

```bash
curl -s https://api.mimeet.online/api/v1/admin/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"admin@mimeet.tw","password":"ChangeMe@2026"}'
```

| 服務 | URL |
|------|-----|
| 前台 | https://mimeet.online |
| API | https://api.mimeet.online/api/v1 |
| Admin | https://admin.mimeet.online |

Admin 登入：`admin@mimeet.tw` / `ChangeMe@2026`

---

## 10. 更新腳本

```bash
cat > /usr/local/bin/mimeet-update << 'SCRIPT'
#!/bin/bash
set -e
COMPOSE="docker compose -f /var/www/mimeet/docker-compose.staging.yml"
cd /var/www/mimeet && git pull origin main

echo "📦 Backend..."
$COMPOSE exec app composer install --no-dev --optimize-autoloader
$COMPOSE exec app php artisan migrate --force
$COMPOSE exec app php artisan config:cache && $COMPOSE exec app php artisan route:cache
$COMPOSE exec app chown -R www-data:www-data storage bootstrap/cache

echo "🖥️ Frontend..."
cd frontend && npm ci && npm run build

echo "🖥️ Admin..."
cd ../admin && npm ci && npm run build

echo "🔄 Restart..."
$COMPOSE restart app
systemctl reload nginx
echo "✅ Done"
SCRIPT
chmod +x /usr/local/bin/mimeet-update
```

日後更新只需：`mimeet-update`

---

## 11. 常見問題

| 問題 | 排查 |
|------|------|
| 502 Bad Gateway | `docker compose -f docker-compose.staging.yml logs app` |
| 500 Error | `docker compose -f ... exec app tail -100 storage/logs/laravel.log` |
| DB 連線失敗 | `docker compose -f ... exec db mysql -u mimeet_user -p mimeet` |
| 前台空白 | 確認 `.env` 的 `VITE_API_BASE_URL` 是 `https://api.mimeet.online/api/v1` |
| Email 失敗 | 確認 Resend API key 在 admin 後台已設定 |
| SMS 失敗 | 確認 Twilio SID/Token/From 在 admin 後台已設定 |
| SSL 憑證過期 | `certbot renew` |
| Docker 容器掛掉 | `docker compose -f ... up -d` 重啟 |

---

## 12. 技術版本

| 項目 | 版本 |
|------|------|
| Ubuntu | 24.04 LTS |
| Docker | latest |
| PHP (container) | 8.3+ |
| Node.js (host) | 22.x LTS |
| MySQL (container) | 8.0 |
| Redis (container) | 7-alpine |
| Nginx (host) | latest |
| SSL | Let's Encrypt (Certbot) |

---

## 13. 預設服務設定

| 服務 | 預設 | 設定位置 |
|------|------|---------|
| Email | Resend API | Admin → 系統設定 → Email tab |
| SMS | Twilio | Admin → 系統設定 → SMS tab |
| 金流 | ECPay Sandbox | Admin → 系統設定 → 金流 tab |
