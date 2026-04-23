# OPS-003 部署與 HTTPS/TLS 設定

**文檔版本：** v1.1
**建立日期：** 2026年4月（Sprint 14）
**最後更新：** 2026-04-23（G-001 完整修復：CSP + server_tokens off 上線）
**適用範圍：** MiMeet 生產環境部署

---

## 1. SSL 憑證配置

建議使用 Let's Encrypt 免費 SSL 憑證：

```bash
# 安裝 certbot
sudo apt update && sudo apt install -y certbot python3-certbot-nginx

# 取得憑證（請將 your-domain.com 替換為實際域名）
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# 驗證憑證
sudo certbot certificates
```

---

## 2. Nginx TLS 設定

在 Nginx server block 中加入以下 TLS 配置：

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;

    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;

    # TLS 協定版本（僅允許 1.2 及 1.3）
    ssl_protocols TLSv1.2 TLSv1.3;

    # 加密套件（優先使用強加密）
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers on;

    # SSL session 快取
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    ssl_session_tickets off;

    # OCSP Stapling
    ssl_stapling on;
    ssl_stapling_verify on;
    resolver 8.8.8.8 8.8.4.4 valid=300s;
    resolver_timeout 5s;
}

# HTTP -> HTTPS 自動重導
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    return 301 https://$host$request_uri;
}
```

---

## 3. HSTS 設定

Strict-Transport-Security header 已由 Laravel `SecurityHeaders` middleware 在生產環境自動加入：

```
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

- `max-age=31536000`：瀏覽器記憶 1 年內只使用 HTTPS
- `includeSubDomains`：所有子域名也強制 HTTPS
- 此 header 僅在 `APP_ENV=production` 時生效

---

## 4. Laravel 生產環境 .env

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Session 安全
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=.your-domain.com

# Sanctum
SANCTUM_STATEFUL_DOMAINS=your-domain.com,www.your-domain.com
SANCTUM_TOKEN_EXPIRATION=1440

# 加密金鑰（務必保管好，勿外洩）
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

**注意事項：**
- `APP_DEBUG` 在生產環境務必設為 `false`
- `SESSION_SECURE_COOKIE=true` 確保 cookie 僅透過 HTTPS 傳輸
- `APP_KEY` 用於 AES-256 加密（如手機號碼），遺失將導致已加密資料無法解密

---

## 5. 自動更新憑證

Let's Encrypt 憑證有效期為 90 天，建議設定自動更新：

```bash
# 測試更新流程
sudo certbot renew --dry-run

# 設定 cron job（每天凌晨 2:30 自動檢查更新）
sudo crontab -e
# 加入以下行：
30 2 * * * /usr/bin/certbot renew --quiet --post-hook "systemctl reload nginx"
```

或使用 systemd timer（推薦）：

```bash
# 確認 certbot timer 已啟用
sudo systemctl enable certbot.timer
sudo systemctl start certbot.timer
sudo systemctl status certbot.timer
```

---

## 6. Production Nginx Config 版控與同步

### 架構說明

| 檔案 | 用途 |
|------|------|
| `docker/nginx/default.conf` | 本機 Docker 開發環境（Laravel backend fastcgi） |
| `deploy/nginx/mimeet.conf` | **Production nginx config（版控來源）** |
| `/etc/nginx/sites-enabled/mimeet` | Droplet 上的實際生效檔案 |

Production nginx 跑在 Droplet host（systemctl），不是 Docker container。

### 同步步驟（每次修改 deploy/nginx/mimeet.conf 後執行）

```bash
# 1. 備份現有 config
ssh root@188.166.229.100 'cp /etc/nginx/sites-enabled/mimeet /etc/nginx/sites-enabled/mimeet.bak'

# 2. 上傳新版 config
scp deploy/nginx/mimeet.conf root@188.166.229.100:/etc/nginx/sites-enabled/mimeet

# 3. 驗證語法並 reload
ssh root@188.166.229.100 'nginx -t && nginx -s reload && echo "✅ nginx reloaded"'
```

### 當前 Security Headers（v1.1）

三個 server block（api / frontend / admin）均包含：

```nginx
server_tokens off;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self' https://api.mimeet.online wss://api.mimeet.online; font-src 'self' data:; frame-ancestors 'none';" always;
```

前台 / 後台 server block 額外有：

```nginx
add_header X-Frame-Options "DENY" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=(self)" always;
```

### 注意事項

- **HSTS 一旦發出，瀏覽器記憶 1 年**。確認 HTTPS 設定正確後才修改 HSTS 值。
- **CSP 的 `'unsafe-inline'` 僅在 style-src**，script-src 不含（Vue 3 build 不需要 inline script）。
- Certbot renew hook 已設定 `systemctl reload nginx`，憑證更新後自動 reload。

---

## 7. Docker 環境部署注意

若使用 Docker 部署，需要：

1. 將憑證掛載至 nginx 容器：
```yaml
# docker-compose.yml
services:
  nginx:
    volumes:
      - /etc/letsencrypt:/etc/letsencrypt:ro
    ports:
      - "80:80"
      - "443:443"
```

2. 更新 `docker/nginx/default.conf` 加入 SSL 配置
3. 確保 certbot 在宿主機執行，不在容器內

---

## 8. Queue Worker（Docker）

Queue Worker 以 Docker service 形式管理，**不透過 Supervisor**。

**Service 定義位置：** `docker-compose.staging.yml` → `worker` service

```yaml
worker:
  command: php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
  container_name: mimeet-worker
  restart: unless-stopped
```

**Queue driver：** Redis（非 database，無 `jobs` 資料表）

**日常操作：**
```bash
# 查看狀態
docker ps | grep mimeet-worker

# 查看即時 log
docker logs mimeet-worker --tail=30 -f

# 重啟 worker（deploy 後若有新 job class）
cd /var/www/mimeet && docker compose -f docker-compose.staging.yml restart worker

# 確認 worker 正在消化 jobs
docker exec mimeet-app php artisan tinker \
  --execute="echo app('redis')->llen('queues:default');"
```

**Droplet 重建後恢復：**
```bash
cd /var/www/mimeet
docker compose -f docker-compose.staging.yml up -d worker
```

Worker 隨 Docker daemon 自動重啟（`restart: unless-stopped`），無需手動 Supervisor 設定。

> ⚠️ 歷史誤區：CLAUDE.md 舊版寫 `supervisorctl restart mimeet-worker:*`，Droplet 上從未有此 Supervisor group。已更正為 `docker compose ... restart worker`。
