# OPS-002 上線前 Checklist

## 0. 程式碼清理確認
- [x] DatabaseSeeder 不建立 admin email 的普通用戶
- [x] AdminUserSeeder 已整合進 DatabaseSeeder
- [x] Admin 帳密從 .env 讀取（不寫死在程式碼）
- [x] 所有 console.log 已移除
- [x] 前台 Mock 預設停用（VITE_USE_MOCK=false）
- [x] 後端無 dd() / var_dump() 殘留
- [x] .gitignore 完整
- [x] 無敏感資料被 commit

---

## 1. 伺服器部署

### 1-1. Clone 與安裝

```bash
# Clone 專案（私有 repo，需先設定 SSH key 或 Personal Access Token）
mkdir -p /var/www/mimeet && cd /var/www/mimeet
git clone git@github.com:lunarwind/mimeet.git .
# 若無 SSH key，可用 HTTPS + PAT：
# git clone https://<GITHUB_USER>:<TOKEN>@github.com/lunarwind/mimeet.git .

# 後端
cd backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
nano .env   # 編輯所有環境變數（見下方 §2）

# Laravel 初始化
php artisan key:generate
php artisan migrate --force
php artisan db:seed              # 執行所有 Seeder
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

# 前台 build
cd ../frontend
npm ci
npm run build

# Admin build
cd ../admin
npm ci
npm run build
```

### 1-2. 後續更新流程

```bash
cd /var/www/mimeet
git pull origin main

# 後端
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 前台
cd ../frontend
npm ci
npm run build

# Admin
cd ../admin
npm ci
npm run build
```

---

## 2. 環境設定（.env）

`cp .env.example .env` 後，以下為**必須修改**的項目：

```env
# ── 基本 ──
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.mimeet.lunarwind.org   # 改為你的正式域名

# ── 資料庫 ──
DB_HOST=127.0.0.1
DB_DATABASE=mimeet
DB_USERNAME=your_db_user
DB_PASSWORD=your_strong_db_password

# ── Redis ──
REDIS_PASSWORD=your_redis_password

# ── Sanctum（重要！前後端跨域認證） ──
# 填入所有前端域名，逗號分隔，不含 http/https
SANCTUM_STATEFUL_DOMAINS=mimeet.lunarwind.org,admin.mimeet.lunarwind.org,api.mimeet.lunarwind.org

# ── Mail ──
# 測試階段用 log（不發信），正式用 smtp
MAIL_MAILER=log
# 正式 SMTP 範例：
# MAIL_MAILER=smtp
# MAIL_HOST=smtp.gmail.com
# MAIL_PORT=587
# MAIL_USERNAME=your@gmail.com
# MAIL_PASSWORD=app-password
# MAIL_ENCRYPTION=tls

# ── SMS ──
# disabled = 不發簡訊（只寫 log），正式環境改為 twilio 或 mitake
SMS_PROVIDER=disabled

# ── ECPay ──
ECPAY_IS_SANDBOX=true   # 測試保持 true，正式上線改 false 並填入正式金鑰
# ECPAY_MERCHANT_ID=你的正式商店代號
# ECPAY_HASH_KEY=你的正式 HashKey
# ECPAY_HASH_IV=你的正式 HashIV

# ── Admin 帳號（務必修改！） ──
SUPER_ADMIN_PASSWORD=一個很強的密碼
ADMIN_PASSWORD=另一個強密碼
CS_PASSWORD=另一個強密碼
```

完整變數列表參考 `backend/.env.example`。

---

## 3. 資料庫

首次部署使用 `php artisan db:seed` 即可執行所有 Seeder。
若需個別執行：

- [ ] `php artisan db:seed --class=AdminUserSeeder`
- [ ] `php artisan db:seed --class=SubscriptionPlanSeeder`
- [ ] `php artisan db:seed --class=AdminPermissionsSeeder`
- [ ] `php artisan db:seed --class=MemberLevelPermissionsSeeder`
- [ ] `php artisan db:seed --class=SystemSettingsSeeder`

---

## 4. 快取

- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`

---

## 5. SSL / HTTPS

- [ ] SSL 憑證已安裝（Let's Encrypt 或其他）
- [ ] Nginx 設定 HTTP → HTTPS redirect
- [ ] HSTS header 已啟用（見 `docker/nginx/default.conf`）
- [ ] 確認 `APP_URL` 使用 `https://`

---

## 6. 監控

- [ ] Log rotation 設定（`/backend/storage/logs/`）
- [ ] 錯誤通知設定（Email / Slack）
- [ ] 伺服器監控（CPU / Memory / Disk）

---

## 7. 備份

- [ ] 資料庫自動備份排程（建議每日）
- [ ] 媒體檔案備份策略
- [ ] Rollback 計畫文件化

---

## 8. 測試驗證

- [ ] `php artisan test` 全數通過
- [ ] Frontend `npm run build` 成功
- [ ] Admin `npm run build` 成功
- [ ] 手動測試核心流程：
  - [ ] 前台註冊 / 登入 / 探索 / 聊天 / 付費
  - [ ] 後台登入（super@mimeet.tw）/ 會員管理 / Ticket 處理
  - [ ] 停權 / 申訴 / 帳號刪除流程

---

## 9. Admin 預設帳號

| 角色 | Email | 預設密碼（開發用） | .env 變數 |
|------|-------|-------------------|-----------|
| Super Admin | super@mimeet.tw | mimeet2024! | `SUPER_ADMIN_PASSWORD` |
| Admin | admin@mimeet.tw | password | `ADMIN_PASSWORD` |
| CS | cs@mimeet.tw | password | `CS_PASSWORD` |

> 正式環境務必透過 `.env` 設定強密碼，不要使用預設值。
