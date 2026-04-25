#!/usr/bin/env bash
# staging-deploy.sh — 本機端部署入口（Staging 環境）
#
# 用法：
#   bash scripts/staging-deploy.sh           # 互動式（預設，部署前確認）
#   bash scripts/staging-deploy.sh --yes     # 跳過確認（熟手 / CI）
#
# 前提：
#   1. 已完成 pre-merge-check.sh
#   2. 已 push origin main（main branch 為部署基準）
#   3. ~/.ssh/config 已設定 mimeet-staging alias
#
# 此腳本只負責「本機端驗收 → SSH 進入 staging → 執行伺服器端 script → Smoke Test」。
# 所有伺服器端操作在 scripts/staging-server-deploy.sh 中。

set -euo pipefail

REMOTE="mimeet-staging"
REMOTE_PROJECT="/var/www/mimeet"
REMOTE_SCRIPT="$REMOTE_PROJECT/scripts/staging-server-deploy.sh"
YES=false

# ── 引數解析 ──────────────────────────────────────────────
for arg in "$@"; do
  case "$arg" in
    --yes|-y) YES=true ;;
    *) echo "❌ 未知引數：$arg"; exit 1 ;;
  esac
done

# ── Step 1/5：確認 SSH 可達 ────────────────────────────────
echo "[1/5] 確認 SSH 連線 ($REMOTE)..."
if ! ssh -o ConnectTimeout=8 -o BatchMode=yes "$REMOTE" "echo ok" &>/dev/null; then
  echo "❌ 無法連線到 $REMOTE。請確認 ~/.ssh/config 已設定 mimeet-staging alias 且金鑰正確。"
  exit 1
fi
echo "  ✅ SSH OK"

# ── Step 2/5：顯示本機 main HEAD ──────────────────────────
echo ""
echo "[2/5] 準備部署以下 commit（本機 main HEAD）："
git log main -1 --oneline
echo ""

# ── Step 3/5：確認 ─────────────────────────────────────────
if [ "$YES" = false ]; then
  read -r -p "  確定部署到 Staging ($REMOTE)？[y/N] " confirm
  case "$confirm" in
    [yY]|[yY][eE][sS]) ;;
    *) echo "  已取消。"; exit 0 ;;
  esac
fi

# ── Step 4/5：執行伺服器端 script ─────────────────────────
echo ""
echo "[4/5] 執行伺服器端部署..."
ssh "$REMOTE" "bash $REMOTE_SCRIPT"

# ── Step 5/5：Smoke Test ───────────────────────────────────
echo ""
echo "[5/5] Smoke Test..."

FRONTEND_URL="https://mimeet.online"
ADMIN_URL="https://admin.mimeet.online"
API_URL="https://api.mimeet.online/api/v1/auth/me"

SMOKE_FAIL=0

frontend_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$FRONTEND_URL" || echo "000")
if [ "$frontend_status" = "200" ]; then
  echo "  ✅ 前台 $FRONTEND_URL → $frontend_status"
else
  echo "  ❌ 前台 $FRONTEND_URL → $frontend_status"
  SMOKE_FAIL=1
fi

admin_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$ADMIN_URL" || echo "000")
if [ "$admin_status" = "200" ]; then
  echo "  ✅ 後台 $ADMIN_URL → $admin_status"
else
  echo "  ❌ 後台 $ADMIN_URL → $admin_status"
  SMOKE_FAIL=1
fi

api_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$API_URL" || echo "000")
if [ "$api_status" = "401" ]; then
  echo "  ✅ API  $API_URL → $api_status (Sanctum, 正常)"
else
  echo "  ❌ API  $API_URL → $api_status (預期 401)"
  SMOKE_FAIL=1
fi

echo ""
if [ $SMOKE_FAIL -eq 0 ]; then
  echo "✅ 部署完成，Smoke Test 全部通過。"
else
  echo "⚠️  部署完成，但 Smoke Test 有失敗項目，請立即檢查。"
  echo "   如需回滾：bash scripts/staging-rollback.sh"
  exit 1
fi
