# Audit-G Round 2 — 資料庫 schema / Migration / Seeder / 安全設定

> 先讀 prompts/audit/_common.md。
> 此 audit 不對 API 端點，主審 DB 與設定一致性。

## 規格範圍
- docs/DEV-006（資料庫設計與遷移指南）完整
- docs/DDD-001（若存在，資料字典）
- docs/SDD-001 §3 §4（系統設計 - 安全/資料）
- docs/OPS-003（部署與 HTTPS/TLS）

## 前次稽核
- docs/audits/audit-G-20260423.md
- docs/audits/audit-G-20260424.md

## 程式碼範圍

```bash
# 全部 migrations
backend/database/migrations/

# 全部 seeders
backend/database/seeders/

# 全部 Models（schema 反映）
backend/app/Models/

# 環境設定
backend/.env.example
backend/config/

# Nginx / Docker
docker/nginx/
docker-compose.yml
```

## 模組特有檢查

### P1（DDL 對照規格）
對照 DEV-006 列的所有資料表（user/users_profile/messages/conversations/orders/subscriptions/notifications/reports/credit_score_logs 等），列：

| 表 | 規格欄位數 | 實際 migration 欄位數 | 差異 |
|---|---|---|---|

```bash
# 列出所有表
ls backend/database/migrations/ | grep -E "create_.*_table"

# 對應 DEV-006 §3 各小節
grep -E "^####" docs/DEV-006_資料庫設計與遷移指南.md
```

### P4 索引 / 約束檢查
```bash
# 所有 idx_ 索引
grep -rn "INDEX idx_\|->index(" backend/database/migrations/ | head -50

# 所有外鍵
grep -rn "FOREIGN KEY\|->foreign(" backend/database/migrations/

# 唯一鍵
grep -rn "UNIQUE\|->unique(" backend/database/migrations/

# 軟刪除欄位
grep -rn "deleted_at\|softDeletes" backend/database/migrations/
```

### P10 Seeder 一致性
```bash
# SystemSettingsSeeder 中所有 key vs 規格 DEV-008 §4 §5 + DEV-011 §4
cat backend/database/seeders/SystemSettingsSeeder.php

# 比對每一筆 default value 與規格
# ⚠️ 既有 audit 已發現：credit_score_initial 從 100 改 60
```

### P11 模組特有
```bash
# Phase 2 預埋表是否有對應 migration（posts / anonymous_channels）
ls backend/database/migrations/ | grep -E "post|anonymous"

# Migration 是否與 Model fillable 一致
for model in backend/app/Models/User.php backend/app/Models/Order.php; do
  echo "=== $model ==="
  grep -A 30 "fillable" "$model"
done

# 重複的 column 定義（同欄位被多次 alter）
grep -rn "->string('email'\|->integer('user_id'" backend/database/migrations/

# Seeder 是否有 hardcode 帳號（uid=1 預設帳號）
grep -rn "User::create\|User::factory" backend/database/seeders/
```

### P4 安全設定對照（OPS-003）
| 項目 | 規格值 | 怎麼驗 |
|---|---|---|
| APP_DEBUG production | false | `grep "APP_DEBUG" backend/.env.example` |
| SESSION_SECURE_COOKIE | true | `grep "SESSION_SECURE_COOKIE" backend/.env.example` |
| HSTS header | max-age=31536000 | `grep -rn "Strict-Transport-Security" backend/app/Http/Middleware/` |
| CORS_ALLOWED_ORIGINS | 預設安全 | `grep -rn "CORS\|Access-Control-Allow-Origin" backend/config/` |
| Sanctum stateful domains | 限定域名 | `grep "SANCTUM_STATEFUL_DOMAINS" backend/.env.example` |

## 重點關注（前次 Round 1）
- G-001：Seeder credit_score_initial 對齊規格（已修）
- G-003：Phase 2 表標注（posts/anonymous_*）
- G-006：point 系統三表 DDL 是否補進 DEV-006
- G-007：seo_metas 命名一致
- G-008：messages.content FULLTEXT index
