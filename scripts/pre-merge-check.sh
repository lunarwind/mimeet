#!/bin/bash
# ============================================================
# MiMeet pre-merge checklist
# Verifies critical fixes haven't been reverted before merging.
# Usage: bash scripts/pre-merge-check.sh
# ============================================================

set -e
ERRORS=0

echo ""
echo "  MiMeet Pre-Merge Checklist"
echo "  =========================="
echo ""

# ── .env 必填變數檢查（critical 缺漏立即中止）──────────────────
if [[ -f backend/.env ]]; then
  echo "  [ENV] 檢查 backend/.env 必填變數..."
  if ! bash scripts/check-env.sh backend/.env; then
    echo "  [FAIL] .env 必填變數缺漏，請補齊後再 merge"
    exit 1
  fi
else
  echo "  [WARN] backend/.env 不存在，跳過 .env 檢查（Staging 部署前必須補）"
fi

check() {
  local desc="$1"
  local cmd="$2"
  local expected="$3"

  result=$(eval "$cmd" 2>/dev/null || true)
  if echo "$result" | grep -qE "$expected"; then
    echo "  [OK] $desc"
  else
    echo "  [FAIL] $desc"
    echo "       expected: $expected"
    echo "       got: $result"
    ERRORS=$((ERRORS + 1))
  fi
}

echo "-- Backend --"

check \
  "DatasetController uses mimeet:reset" \
  "grep 'Artisan::call' backend/app/Http/Controllers/Api/V1/Admin/DatasetController.php | head -1" \
  "mimeet:reset"

check \
  "No mimeet:reset-clean anywhere" \
  "grep -rn 'mimeet:reset-clean' backend --include='*.php' | grep -v vendor | wc -l | tr -d ' '" \
  "^0$"

check \
  "AdminController weight reads from user (not hardcoded null)" \
  "grep \"'weight'\" backend/app/Http/Controllers/Api/V1/AdminController.php" \
  "user->weight"

check \
  "SubscriptionPlanSeeder uses updateOrInsert" \
  "grep 'updateOrInsert' backend/database/seeders/SubscriptionPlanSeeder.php | head -1" \
  "updateOrInsert"

check \
  "ResetToCleanState reseeds subscription_plans if empty" \
  "grep 'subscription_plans' backend/app/Console/Commands/ResetToCleanState.php" \
  "subscription_plans"

check \
  "SendBroadcastJob supports DM mode" \
  "grep -c 'sendDm' backend/app/Jobs/SendBroadcastJob.php" \
  "[1-9]"

check \
  "BroadcastController uses async dispatch" \
  "grep 'dispatch' backend/app/Http/Controllers/Api/V1/Admin/BroadcastController.php | head -1" \
  "dispatch"

check \
  "Mock payment returns HTML" \
  "grep 'text/html' backend/app/Http/Controllers/Api/V1/PaymentCallbackController.php | head -1" \
  "text/html"

check \
  "Dockerfile.dev sets output_buffering=4096 (防 POST response body 被 echo request body 污染)" \
  "grep 'output_buffering=4096' backend/Dockerfile.dev" \
  "output_buffering=4096"

check \
  "compose.staging 有 mount output-buffering.ini (確保 restart 後仍生效，不依賴 image rebuild)" \
  "grep 'output-buffering.ini' docker-compose.staging.yml | head -1" \
  "output-buffering.ini"

check \
  "backend/docker/output-buffering.ini 存在" \
  "test -f backend/docker/output-buffering.ini && echo yes" \
  "yes"

echo ""
echo "-- Frontend --"

check \
  "usePayment maps snake_case expiresAt" \
  "grep 'expires_at' frontend/src/composables/usePayment.ts" \
  "expires_at"

check \
  "fetchConversations maps other_user to targetUser" \
  "grep 'other_user' frontend/src/api/chat.ts" \
  "other_user"

check \
  "fetchMessages maps sent_at to createdAt" \
  "grep 'sent_at' frontend/src/api/chat.ts" \
  "sent_at"

check \
  "VerifyView uploads to /users/me/photos" \
  "grep '/users/me/photos' frontend/src/views/app/settings/VerifyView.vue" \
  "/users/me/photos"

check \
  "ShopView has payment method selector" \
  "grep 'selectedPaymentMethod' frontend/src/views/app/ShopView.vue | head -1" \
  "selectedPaymentMethod"

echo ""
echo "-- Admin credit-logs API structure (14a-1~14g) --"

# ============================================================
# 第 14 項：memberCreditLogs API 結構守護（方案 B 退化防護）
# ============================================================
# 使用 awk 精準切出 memberCreditLogs 方法區段，避免對其他方法誤觸。
# 邊界條件：遇到下一個同級 public/private/protected function 即停止。

CREDIT_LOG_SLICE="awk '/^    public function memberCreditLogs/ { flag=1 } flag && /^    (public|private|protected) function / && !/memberCreditLogs/ { exit } flag' backend/app/Http/Controllers/Api/V1/AdminController.php"

check \
  "14a-1 memberCreditLogs data 直接由 \$logs->map 衍生（非包裝 array）" \
  "eval \"$CREDIT_LOG_SLICE\" | grep \"data.*->map(\"" \
  "data.*->map"

check \
  "14a-2 memberCreditLogs 不存在 'logs' 包裝層退化" \
  "eval \"$CREDIT_LOG_SLICE\" | grep -c \"'logs' =>\" | tr -d ' '" \
  "^0$"

check \
  "14b memberCreditLogs 使用 'change' 欄位（非 'delta'）" \
  "eval \"$CREDIT_LOG_SLICE\" | grep \"'change' =>\"" \
  "'change' =>"

check \
  "14c memberCreditLogs 不使用 score_before / score_after 舊欄位名" \
  "eval \"$CREDIT_LOG_SLICE\" | grep -cE \"'score_before' =>|'score_after' =>\" | tr -d ' '" \
  "^0$"

check \
  "14d memberCreditLogs operator 回傳物件（非 operator_id 整數）" \
  "eval \"$CREDIT_LOG_SLICE\" | grep -c \"'operator_id' =>\" | tr -d ' '" \
  "^0$"

check \
  "14e memberCreditLogs 使用 with adminUser eager loading（防 N+1）" \
  "eval \"$CREDIT_LOG_SLICE\" | grep \"with.*adminUser\"" \
  "adminUser"

check \
  "14f memberCreditLogs meta 使用 'page'（非 'current_page'，符合 API-002 §4.4）" \
  "eval \"$CREDIT_LOG_SLICE\" | grep \"'page' =>\"" \
  "'page' =>"

check \
  "14g MemberDetailPage scoreColumns operator 欄使用 optional chaining（防 runtime crash）" \
  "grep \"op?\\.name\" admin/src/pages/members/MemberDetailPage.tsx" \
  "op\\?\\."

echo ""
echo "-- Model datetime casts integrity (14h) --"

# ============================================================
# 第 14h 項：Model $casts datetime 完整性守護
# ============================================================
# 防止 $timestamps = false 的 model 在 fillable 有 datetime 欄位但缺 cast
# 導致 controller 呼叫 ->toISOString() 等方法時 500

check \
  "14h DateInvitation created_at 有 datetime cast（\$timestamps=false model 守護）" \
  "grep \"'created_at' => 'datetime'\" backend/app/Models/DateInvitation.php" \
  "datetime"

check \
  "14i CreditScoreHistory type 不使用舊枚舉值（DEV-008 §10.3 規格化守護）" \
  "grep -rn \"CreditScoreService::adjust\" backend/app/ | grep -cE \"'email_verified'|'phone_verified'|'date_verified'|'admin_adjust'|'admin_set'|'report_filed'|'report_received'|'report_dismissed'|'report_cancelled'|'report_penalty'|'appeal_approved'|'verification_approved'\" | tr -d ' '" \
  "^0$"

echo ""
echo "-- Worker health check guard (14p) --"

# ============================================================
# 第 14p 項：禁止腳本或 CLAUDE.md 出現 supervisorctl
# ============================================================
# Staging 主機 supervisord 未管任何 program，supervisorctl 回傳空結果。
# 所有 worker 健康檢查必須改用 docker compose ps。

check \
  "14p scripts/ 中不出現 supervisorctl（worker 健康檢查盲點守護）" \
  "grep -r \"supervisorctl\" scripts/ --exclude=\"pre-merge-check.sh\" | grep -cv \"^Binary file\" | tr -d ' '" \
  "^0$"

echo ""
echo "-- TypeScript strict mode guard (14q) --"

# ============================================================
# 第 14q 項：admin/tsconfig.app.json 必須啟用 strict
# ============================================================
# Issue #4 啟用 strict mode 後的退化防護。
# strict: true 是 admin/ 編譯期 null crash 防護的核心。
# （延伸 2026-04-25 admin 分數頁 crash 教訓）

check \
  "14q admin/tsconfig.app.json 啟用 strict mode（防 null crash 退化）" \
  "grep -c '\"strict\": true' admin/tsconfig.app.json | tr -d ' '" \
  "^1$"

echo ""
echo "-- Pagination unification guards (14r-14v) --"

# ============================================================
# 14r：後端 list API 不使用 'pagination' wrapper key
# ============================================================
# 全系統 pagination 規格化（2026-04-26）後的退化防護。
# 所有 list API 必須使用 meta wrapper。

check \
  "14r 後端 list API 不使用 'pagination' wrapper key" \
  "grep -rn \"'pagination'\\s*=>\" backend/app/Http/Controllers/ 2>/dev/null | wc -l | tr -d ' '" \
  "^0$"

# ============================================================
# 14s：後端 list API 不使用 'current_page' 欄位名
# ============================================================

check \
  "14s 後端 list API 不使用 'current_page' 欄位" \
  "grep -rn \"'current_page'\\s*=>\" backend/app/Http/Controllers/ 2>/dev/null | wc -l | tr -d ' '" \
  "^0$"

# ============================================================
# 14t：後端 list API 不使用 'total_pages' 欄位名
# ============================================================

check \
  "14t 後端 list API 不使用 'total_pages' 欄位" \
  "grep -rn \"'total_pages'\\s*=>\" backend/app/Http/Controllers/ 2>/dev/null | wc -l | tr -d ' '" \
  "^0$"

# ============================================================
# 14u/14v：前端不使用 .pagination.current_page / .pagination.total_pages
# ============================================================

check \
  "14u 前端不讀 .pagination.current_page" \
  "grep -rn '\\.pagination\\.current_page' frontend/src/ admin/src/ 2>/dev/null | wc -l | tr -d ' '" \
  "^0$"

check \
  "14v 前端不讀 .pagination.total_pages" \
  "grep -rn '\\.pagination\\.total_pages' frontend/src/ admin/src/ 2>/dev/null | wc -l | tr -d ' '" \
  "^0$"

echo ""
echo "-- Sensitive file guards (14w-14y) --"

# ============================================================
# 14w：禁止 service-account.json 在 git working tree
# ============================================================
# secret 檔案必須放在 ~/secrets/<project>/，不可在 repo 中
# 詳見 CLAUDE.md「敏感檔案同步流程」段落。

check \
  "14w 禁止 service-account.json 在 git working tree" \
  "git ls-files | grep -E '(service-account|firebase-credentials)' 2>/dev/null | wc -l | tr -d ' '" \
  "^0$"

# ============================================================
# 14x：.env.example 必須含 FIREBASE_CREDENTIALS_PATH
# ============================================================
# 防止 .env.example 被誤改回舊版 FCM_SERVER_KEY 或刪掉 FIREBASE 設定。

check \
  "14x .env.example 含 FIREBASE_CREDENTIALS_PATH 規範" \
  "grep -c '^FIREBASE_CREDENTIALS_PATH=' backend/.env.example 2>/dev/null | tr -d ' '" \
  "^1$"

# ============================================================
# 14y：.env.example 不含棄用 FCM_SERVER_KEY
# ============================================================
# FCM Legacy API（FCM_SERVER_KEY）2024-06-20 已停服。

check \
  "14y .env.example 不含棄用 FCM_SERVER_KEY" \
  "grep -c '^FCM_SERVER_KEY' backend/.env.example 2>/dev/null | tr -d ' '" \
  "^0$"

echo ""
echo "-- DB write guard (14z) --"

# ============================================================
# 14z：禁止 SystemControlController 重新出現 writeEnv 或寫 .env
# ============================================================
# 2026-04-26 移除後守護退化。admin「資料庫設定」UI 已改 read-only，
# 不應透過 web UI 寫 .env。反覆 500（www-data 無法寫 root 擁有的 .env）
# 的根本治療。詳見 CLAUDE.md「敏感檔案同步流程 → 歷史教訓」。

check \
  "14z SystemControlController 不應有 writeEnv 或 file_put_contents .env" \
  "grep -cE 'function writeEnv|file_put_contents.*\.env' backend/app/Http/Controllers/Api/V1/Admin/SystemControlController.php 2>/dev/null | tr -d ' '" \
  "^0$"

echo ""
echo "-- Code quality guards (14aa-14ab) --"

# ============================================================
# 14aa：禁止 CreditScoreHistory.type 出現 test_* prefix
# ============================================================
# 預防性守護：避免測試殘留進入 production code。

check \
  "14aa CreditScoreHistory.type 不使用 test_* prefix（預防測試殘留）" \
  "grep -rnE \"CreditScoreService::adjust.*'test_|CreditScoreHistory::create.*'test_\" backend/app/ 2>/dev/null | wc -l | tr -d ' '" \
  "^0$"

# ============================================================
# 14ab：禁止 frontend/ 出現 catch (err: any) 或 catch (e: any)
# ============================================================
# 配合本次 17 處 catch any → unknown 統一，加此守護防退化。

check \
  "14ab frontend/ 不使用 catch (err: any) 或 catch (e: any)" \
  "grep -rnE 'catch\s*\(\s*[a-z]+\s*:\s*any\s*\)' frontend/src --include='*.ts' --include='*.vue' 2>/dev/null | wc -l | tr -d ' '" \
  "^0$"

echo ""
echo "-- Register payload guards (14ac-14ad) --"

# ============================================================
# 14ac：禁止 register 函數 hardcode 強制覆蓋勾選欄位
# ============================================================
# 2026-04-26 移除強制 terms_accepted/privacy_accepted/anti_fraud_read = true
# 的 hardcode 邏輯，避免用戶未勾但被視為同意的法律風險。

check \
  "14ac auth.ts register 不 hardcode 勾選欄位為 true" \
  "grep -cE 'terms_accepted: *true|privacy_accepted: *true|anti_fraud_read: *true' frontend/src/api/auth.ts 2>/dev/null | tr -d ' '" \
  "^0$"

# ============================================================
# 14ad：RegisterPayload interface 必須含 password_confirmation
# ============================================================
# 防止 interface 退化為缺欄位的鬆散型別（造成 register 422 bug 復活）。

check \
  "14ad RegisterPayload 含 password_confirmation 欄位" \
  "grep -c 'password_confirmation' frontend/src/api/auth.ts 2>/dev/null | tr -d ' '" \
  "^[1-9]"

echo ""
echo "-- QR flow drift guards (14ae-14ag) --"

# ============================================================
# 14ae：QR 命名漂移守護
# ============================================================
# wire format 統一採 qr_token / expires_at（對齊 DB schema 與 PHP model）。
# 早期文件用過 qr_code / qrCode / qrExpiresAt / qr_expires_at，PR-QR Step 2
# 已全面汰換。本檢查防止任何端的命名漂移再次出現。
# 詳見 API-001 §5.1（Endpoint 主從關係）。

check \
  "14ae 不出現舊命名 qr_code/qrCode/qrExpiresAt/qr_expires_at" \
  "grep -rnE 'qr_code|qrCode|qrExpiresAt|qr_expires_at' backend/app frontend/src admin/src 2>/dev/null | wc -l | tr -d ' '" \
  "^0$"

# ============================================================
# 14af：Carbon datetime mutator 守護
# ============================================================
# Eloquent model 帶 datetime cast 的屬性回傳的是 Carbon 實例，
# 直接呼叫 ->addDays() / ->subMinutes() 等會 mutate 該屬性，
# 後續對同一 instance 讀取會拿到被改過的值。修法：先 ->copy() 再 mutate。
# 此 grep 只攔截 $var->attr->mutator() 形式（兩段以上 -> 鏈），
# 自然排除 now()->subMinutes() / Carbon::parse(...)->addDays() 等合法用法。

check \
  "14af 禁止對 model datetime attribute 直接 mutate（需 ->copy()/->clone()）" \
  "grep -rnE '\\\$[a-zA-Z_][a-zA-Z_0-9]*->[a-zA-Z_][a-zA-Z_0-9]*->(sub|add)(Seconds|Minutes|Hours|Days|Weeks|Months|Years)\\(' backend/app 2>/dev/null | grep -vE 'copy\\(\\)->|clone\\(\\)->' | wc -l | tr -d ' '" \
  "^0$"

# ============================================================
# 14ag：Transformer hardcoded null 警告（warning，不阻擋 merge）
# ============================================================
# frontend/src/api/*.ts 的 transformer 內若出現裸 `field: null,` 賦值，
# 多半代表 API 該欄位被遺漏映射或暫以 null 占位（如 list endpoint 不返回
# 該欄位）。少數情況是合法的（如 dates.ts 的 creditScoreChange），故僅警告。

WARN_14AG=$(grep -rnE ':[[:space:]]*null,?[[:space:]]*(//|$)' frontend/src/api 2>/dev/null || true)
if [ -n "$WARN_14AG" ]; then
  echo "  [WARN 14ag] frontend/src/api transformer 出現 hardcoded null（請確認是否為刻意 fallback）："
  echo "$WARN_14AG" | sed 's/^/    /'
else
  echo "  [OK] 14ag frontend/src/api transformer 無 hardcoded null"
fi

echo ""
echo "-- PR-1 guards (14ai-14aj) --"

# ============================================================
# 14ai：強守護 deleteMember 必須走 anonymizeUser，不能裸 $user->delete()
# ============================================================
# 2026-05 PR-1：Admin delete 必須匿名化以釋出 email/phone unique 索引。
# 用 awk 切方法區段，避免註解誤觸 + 防退化。

DELETE_MEMBER_SLICE=$(awk '/public function deleteMember/{f=1} f && /^    (public|private|protected) function /&&!/deleteMember/{exit} f' backend/app/Http/Controllers/Api/V1/AdminController.php)

# 14ai-1：deleteMember 區段必須含 anonymizeUser 呼叫
if ! echo "$DELETE_MEMBER_SLICE" | grep -qE '\$.*->anonymizeUser\('; then
  echo "  [FAIL] 14ai-1: deleteMember 必須呼叫 anonymizeUser（GDPR 匿名化路徑）"
  ERRORS=$((ERRORS + 1))
else
  echo "  [OK] 14ai-1 deleteMember 呼叫 anonymizeUser"
fi

# 14ai-2：deleteMember 區段不能出現裸 $user->delete() / $user->forceDelete()
if echo "$DELETE_MEMBER_SLICE" | grep -qE '\$user->(force)?[Dd]elete\(\)'; then
  echo "  [FAIL] 14ai-2: deleteMember 不能裸調用 \$user->delete() / \$user->forceDelete()"
  ERRORS=$((ERRORS + 1))
else
  echo "  [OK] 14ai-2 deleteMember 無裸 delete()"
fi

# ============================================================
# 14aj：ReportService system_issue 區段必須有 Cache rate limit 機制
#       + sms_verification category 標記
# ============================================================
# 2026-05 PR-1：SMS 驗證問題回報沿用 system_issue type，須有 24h cache 防 spam
# + metadata 中 category=sms_verification 供未來 admin filter 用。

REPORT_SLICE=$(awk '/system_issue/,/^    [a-z]|^}/' backend/app/Services/ReportService.php)

# 14aj-1：必須有 Cache 讀取（檢查 cooldown）
if ! echo "$REPORT_SLICE" | grep -qE 'Cache::(has|get)\('; then
  echo "  [FAIL] 14aj-1: ReportService 對 system_issue 區段必須有 Cache::has/get（rate limit 防護讀取）"
  ERRORS=$((ERRORS + 1))
else
  echo "  [OK] 14aj-1 system_issue 有 Cache::has/get"
fi

# 14aj-2：必須有 Cache 寫入（成功後標記 cooldown）
if ! echo "$REPORT_SLICE" | grep -qE 'Cache::(put|add)\('; then
  echo "  [FAIL] 14aj-2: ReportService 對 system_issue 區段必須有 Cache::put/add（成功後寫入 cooldown）"
  ERRORS=$((ERRORS + 1))
else
  echo "  [OK] 14aj-2 system_issue 有 Cache::put/add"
fi

# 14aj-3：必須有 sms_verification category 標記
if ! echo "$REPORT_SLICE" | grep -qE 'sms_verification'; then
  echo "  [FAIL] 14aj-3: ReportService 對 system_issue 區段必須含 sms_verification 字串（[META] category 標記）"
  ERRORS=$((ERRORS + 1))
else
  echo "  [OK] 14aj-3 system_issue 含 sms_verification 標記"
fi

echo ""
echo "-- PR-2 guards (14ak-14an) --"

# ============================================================
# 14ak：AuthController::register 必須查 blacklist
# ============================================================
# 防退化:有人移除 blacklist gate,讓 banned email/mobile 可重新註冊。
# 用 awk 切方法區段。

REGISTER_SLICE=$(awk '/public function register/{f=1} f && /^    (public|private|protected) function /&&!/register/{exit} f' \
  backend/app/Http/Controllers/Api/V1/AuthController.php)

if ! echo "$REGISTER_SLICE" | grep -qE 'BlacklistService|blacklistService|RegistrationBlacklist'; then
  echo "  [FAIL] 14ak: AuthController::register 必須 reference BlacklistService/RegistrationBlacklist (防退化)"
  ERRORS=$((ERRORS + 1))
else
  echo "  [OK] 14ak register 含 blacklist 守護"
fi

# ============================================================
# 14al：AdminController::deleteMember 必須處理 blacklist 參數
# ============================================================

DELETE_MEMBER_SLICE_AL=$(awk '/public function deleteMember/{f=1} f && /^    (public|private|protected) function /&&!/deleteMember/{exit} f' \
  backend/app/Http/Controllers/Api/V1/AdminController.php)

if ! echo "$DELETE_MEMBER_SLICE_AL" | grep -qE 'blacklist_email|blacklist_mobile'; then
  echo "  [FAIL] 14al: deleteMember 必須處理 blacklist_email/blacklist_mobile 參數"
  ERRORS=$((ERRORS + 1))
else
  echo "  [OK] 14al deleteMember 含 blacklist 處理"
fi

# ============================================================
# 14am：registration_blacklists migration 必須含 is_active + active_value_hash
#       且不可有 SoftDeletes
# ============================================================
# 防止有人改回 SoftDeletes,重蹈 PR-1 user soft delete + unique 衝突的覆轍。

MIGRATION=$(ls backend/database/migrations/*create_registration_blacklists_table.php 2>/dev/null | head -1)
if [ -z "$MIGRATION" ]; then
  echo "  [FAIL] 14am: 找不到 registration_blacklists migration"
  ERRORS=$((ERRORS + 1))
elif ! grep -qE "is_active|->boolean\('is_active'" "$MIGRATION"; then
  echo "  [FAIL] 14am: registration_blacklists migration 必須含 is_active 欄位 (D8:不可改 SoftDeletes)"
  ERRORS=$((ERRORS + 1))
elif ! grep -qE "active_value_hash" "$MIGRATION"; then
  echo "  [FAIL] 14am: registration_blacklists migration 必須含 active_value_hash 欄位 (方案 C race protection)"
  ERRORS=$((ERRORS + 1))
elif grep -qE "softDeletes\(\)|deleted_at" "$MIGRATION"; then
  echo "  [FAIL] 14am: registration_blacklists migration 不可有 softDeletes/deleted_at (D8)"
  ERRORS=$((ERRORS + 1))
else
  echo "  [OK] 14am blacklist migration 用 is_active + active_value_hash"
fi

# ============================================================
# 14an：LogAdminOperation 必須支援 skip_admin_log + AdminBlacklistController 必須使用
# ============================================================

if ! grep -qE "skip_admin_log" backend/app/Http/Middleware/LogAdminOperation.php 2>/dev/null; then
  echo "  [FAIL] 14an-1: LogAdminOperation 必須支援 skip_admin_log attribute (D14-a)"
  ERRORS=$((ERRORS + 1))
else
  echo "  [OK] 14an-1 LogAdminOperation 支援 skip_admin_log"
fi

if ! grep -qE "skip_admin_log" backend/app/Http/Controllers/Api/V1/AdminBlacklistController.php 2>/dev/null; then
  echo "  [FAIL] 14an-2: AdminBlacklistController 必須使用 skip_admin_log + 手動 AdminOperationLog::create"
  ERRORS=$((ERRORS + 1))
else
  echo "  [OK] 14an-2 AdminBlacklistController 使用 skip_admin_log"
fi

echo ""

if [ $ERRORS -eq 0 ]; then
  echo "  All checks passed. Safe to merge."
else
  echo "  $ERRORS check(s) FAILED. Fix before merging."
  exit 1
fi
echo ""
