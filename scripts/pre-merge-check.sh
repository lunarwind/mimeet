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

if [ $ERRORS -eq 0 ]; then
  echo "  All checks passed. Safe to merge."
else
  echo "  $ERRORS check(s) FAILED. Fix before merging."
  exit 1
fi
echo ""
