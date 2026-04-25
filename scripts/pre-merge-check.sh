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

if [ $ERRORS -eq 0 ]; then
  echo "  All checks passed. Safe to merge."
else
  echo "  $ERRORS check(s) FAILED. Fix before merging."
  exit 1
fi
echo ""
