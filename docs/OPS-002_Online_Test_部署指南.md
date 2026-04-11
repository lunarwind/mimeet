# OPS-002 MiMeet Online Test 部署指南

**版本：** v1.0  
**建立日期：** 2026年4月  
**環境：** DigitalOcean Ubuntu 24.04 LTS  
**預計時間：** 60-90 分鐘

> ⚠️ Online Test 環境：HTTP（非 HTTPS）、無 CDN、單台伺服器。

---

## 1. DigitalOcean Droplet 建立

1. 登入 [DigitalOcean](https://cloud.digitalocean.com) → Create → Droplets
2. Region: **Singapore (sgp1)**
3. OS: **Ubuntu 24.04 LTS x64**
4. Plan: **4GB RAM / 2 vCPU / 80GB SSD**（$24/月）
5. Auth: SSH Key
6. Hostname: `mimeet-test`

連線：
```bash
ssh root@YOUR_IP
```

---

## 2. 伺服器初始設定

```bash
apt update && apt upgrade -y
timedatectl set-timezone Asia/Taipei
```

---

## 3. 安裝套件

### PHP 8.2
```bash
add-apt-repository ppa:ondrej/php -y && apt update
apt install -y php8.2 php8.2-fpm php8.2-cli php8.2-mysql php8.2-redis \
  php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath \
  php8.2-intl php8.2-gd php8.2-fileinfo php8.2-pdo
```

### Composer
```bash
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

### Node.js 20
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
```

### 其他
```bash
apt install -y nginx mysql-server redis-server git supervisor ufw
```

### 防火牆
```bash
ufw allow OpenSSH && ufw allow 80 && ufw allow 3000 && ufw allow 3001 && ufw allow 8080
ufw --force enable
```

---

## 4. MySQL

```bash
mysql_secure_installation
mysql -u root -p
```

```sql
CREATE DATABASE mimeet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mimeet_user'@'localhost' IDENTIFIED BY 'YOUR_DB_PASSWORD';
GRANT ALL PRIVILEGES ON mimeet.* TO 'mimeet_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 5. Redis

```bash
systemctl enable redis-server
redis-cli ping  # → PONG
```

---

## 6. 部署後端

```bash
mkdir -p /var/www/mimeet && cd /var/www/mimeet
git clone https://github.com/lunarwind/mimeet.git .
cd backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env
```

**必改項目：**
```env
APP_ENV=staging
APP_DEBUG=true
APP_URL=http://YOUR_IP
DB_PASSWORD=YOUR_DB_PASSWORD
SANCTUM_STATEFUL_DOMAINS=YOUR_IP,YOUR_IP:3000,YOUR_IP:3001
MAIL_MAILER=log
SMS_PROVIDER=disabled
```

```bash
php artisan key:generate
chown -R www-data:www-data /var/www/mimeet/backend
chmod -R 775 storage bootstrap/cache
php artisan storage:link
php artisan config:cache && php artisan route:cache
```

---

## 7. 部署前台

```bash
cd /var/www/mimeet/frontend
cat > .env << EOF
VITE_API_BASE_URL=http://YOUR_IP/api/v1
EOF
npm ci && npm run build
```

---

## 8. 部署 Admin

```bash
cd /var/www/mimeet/admin
cat > .env << EOF
VITE_API_BASE_URL=http://YOUR_IP/api/v1
EOF
npm ci && npm run build
```

---

## 9. Nginx 設定

```bash
cat > /etc/nginx/sites-available/mimeet << 'EOF'
# API
server {
    listen 80;
    server_name _;
    root /var/www/mimeet/backend/public;
    index index.php;
    client_max_body_size 10M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400;
    }
    location ~ /\.(env|git) { deny all; return 404; }
}

# Frontend
server {
    listen 3000;
    root /var/www/mimeet/frontend/dist;
    index index.html;
    location / { try_files $uri $uri/ /index.html; }
}

# Admin
server {
    listen 3001;
    root /var/www/mimeet/admin/dist;
    index index.html;
    location / { try_files $uri $uri/ /index.html; }
}
EOF

ln -sf /etc/nginx/sites-available/mimeet /etc/nginx/sites-enabled/mimeet
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

---

## 10. Supervisor

```bash
cat > /etc/supervisor/conf.d/mimeet.conf << 'EOF'
[program:mimeet-queue]
command=php /var/www/mimeet/backend/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=2
process_name=%(program_name)s_%(process_num)02d
stdout_logfile=/var/www/mimeet/backend/storage/logs/queue.log

[program:mimeet-reverb]
command=php /var/www/mimeet/backend/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/www/mimeet/backend/storage/logs/reverb.log
EOF

supervisorctl reread && supervisorctl update && supervisorctl status
```

---

## 11. Migrations + Seeders

```bash
cd /var/www/mimeet/backend
php artisan migrate --force
php artisan db:seed --force
```

---

## 12. 驗證

```bash
curl -s http://YOUR_IP/api/v1/admin/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@mimeet.tw","password":"ChangeMe@2026"}'
```

| 服務 | URL |
|------|-----|
| API | http://YOUR_IP/api/v1/... |
| 前台 | http://YOUR_IP:3000 |
| Admin | http://YOUR_IP:3001 |

---

## 13. 更新腳本

```bash
cat > /usr/local/bin/mimeet-update << 'SCRIPT'
#!/bin/bash
set -e
cd /var/www/mimeet && git pull origin main
cd backend && COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader
php artisan migrate --force && php artisan config:cache && php artisan route:cache
chown -R www-data:www-data storage bootstrap/cache
cd ../frontend && npm ci && npm run build
cd ../admin && npm ci && npm run build
supervisorctl restart mimeet-queue mimeet-reverb
systemctl reload php8.2-fpm nginx
echo "✅ Done"
SCRIPT
chmod +x /usr/local/bin/mimeet-update
```

日後更新只需：`mimeet-update`

---

## 14. 常見問題

| 問題 | 排查 |
|------|------|
| 502 Bad Gateway | `systemctl status php8.2-fpm` + 檢查 socket |
| 500 Error | `tail -100 storage/logs/laravel.log` |
| DB 連線失敗 | `mysql -u mimeet_user -p mimeet` 測試 |
| 前台空白 | 確認 `.env` 的 `VITE_API_BASE_URL` 正確 |
| Queue 不動 | `supervisorctl restart mimeet-queue` |
| WebSocket 斷線 | `supervisorctl restart mimeet-reverb` + 確認 port 8080 |
| 權限問題 | `chown -R www-data:www-data storage` |

---

## Online Test vs Production

| 項目 | Online Test | Production |
|------|------------|------------|
| APP_DEBUG | true | false |
| HTTPS | 無 | Let's Encrypt |
| Mail | log | 真實 SMTP |
| SMS | disabled | twilio/mitake |
| 備份 | 無 | DO 自動備份 |
| CDN | 無 | Cloudflare |
| APP_ENV | staging | production |
