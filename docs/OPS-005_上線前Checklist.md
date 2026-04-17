# OPS-005 上線前 Checklist

## 0. 程式碼清理確認
- [x] DatabaseSeeder 不建立 admin email 的普通用戶
- [x] AdminUserSeeder 已整合進 DatabaseSeeder
- [x] Admin 帳密從 .env 讀取（不寫死在程式碼）
- [x] 所有 console.log 已移除
- [x] 前台 Mock 預設停用（VITE_USE_MOCK=false）
- [x] 後端無 dd() / var_dump() 殘留
- [x] .gitignore 完整
- [x] 無敏感資料被 commit

## 1. 環境設定
- [ ] .env 所有變數已設定
- [ ] APP_ENV=production
- [ ] APP_DEBUG=false
- [ ] APP_URL 設定為正式域名
- [ ] DB 連線設定完成
- [ ] Redis 連線設定完成
- [ ] ECPAY_IS_SANDBOX=false（正式上線時）
- [ ] SUPER_ADMIN_PASSWORD 已改為強密碼

## 2. 資料庫
- [ ] php artisan migrate --force
- [ ] php artisan db:seed --class=AdminUserSeeder
- [ ] php artisan db:seed --class=SubscriptionPlanSeeder
- [ ] php artisan db:seed --class=AdminPermissionsSeeder
- [ ] php artisan db:seed --class=MemberLevelPermissionsSeeder

### 2.1 資料庫清空功能驗證
- [ ] 執行 `php artisan mimeet:reset --force` 確認清空功能正常
- [ ] 確認 uid=1（admin@mimeet.club）資料正確重建
- [ ] 確認 phone_verified=1, membership_level=3

## 3. 快取
- [ ] php artisan config:cache
- [ ] php artisan route:cache
- [ ] php artisan view:cache

## 4. SSL/HTTPS
- [ ] SSL 憑證已安裝（Let's Encrypt 或其他）
- [ ] Nginx 設定 HTTPS redirect
- [ ] HSTS header 已啟用

## 5. 監控
- [ ] Log rotation 設定
- [ ] 錯誤通知設定（Email/Slack）
- [ ] 伺服器監控（CPU/Memory/Disk）

## 6. 備份
- [ ] 資料庫自動備份排程
- [ ] 媒體檔案備份策略
- [ ] Rollback 計畫文件化

## 7. Queue Worker
- [ ] Supervisor 已安裝並設定開機自啟（`systemctl is-enabled supervisor`）
- [ ] `/etc/supervisor/conf.d/mimeet-worker.conf` 存在
- [ ] `supervisorctl status mimeet-worker:*` 顯示 RUNNING
- [ ] 監控腳本 `/opt/scripts/check-worker.sh` 存在
- [ ] crontab 每 5 分鐘執行監控腳本
- [ ] `failed_jobs` 表為空（無失敗 Job）

## 8. 測試
- [ ] php artisan test 全數通過
- [ ] Frontend build 成功
- [ ] Admin build 成功
- [ ] 手動測試核心流程（註冊/登入/聊天/付費）
