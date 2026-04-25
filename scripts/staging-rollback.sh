#!/usr/bin/env bash
# staging-rollback.sh — Staging 回滾腳本（本機端入口）
#
# 用法：
#   bash scripts/staging-rollback.sh              # 回滾到上一版（讀 .deploy-version）
#   bash scripts/staging-rollback.sh <commit-sha> # 回滾到指定 commit
#
# 行為：
#   1. SSH 進入 staging，取得當前版本（或使用指定 commit）
#   2. 在 server 上執行回滾（git reset → 完整重建前後端 → worker restart）
#   3. 本機執行 Smoke Test
#
# ⚠️  Migration 不自動回滾。
#     若回滾的 commit 含 migration，需先手動評估是否 migrate:rollback，
#     再執行此腳本。詳見 CLAUDE.md「API Contract 回滾流程 → 常見錯誤」。

set -euo pipefail

REMOTE="mimeet-staging"
REMOTE_PROJECT="/var/www/mimeet"
TARGET_SHA="${1:-}"

# ── Step 1/5：確認 SSH 可達 ────────────────────────────────
echo "[1/5] 確認 SSH 連線 ($REMOTE)..."
if ! ssh -o ConnectTimeout=8 -o BatchMode=yes "$REMOTE" "echo ok" &>/dev/null; then
  echo "❌ 無法連線到 $REMOTE。請確認 ~/.ssh/config 已設定 mimeet-staging alias。"
  exit 1
fi
echo "  ✅ SSH OK"

# ── Step 2/5：取得回滾目標 SHA ────────────────────────────
echo ""
echo "[2/5] 確認回滾目標..."

if [ -z "$TARGET_SHA" ]; then
  # 讀 .deploy-version 取上一版
  PREV_SHA=$(ssh "$REMOTE" "cat $REMOTE_PROJECT/.deploy-version 2>/dev/null | grep '^sha=' | cut -d= -f2" || echo "")
  if [ -z "$PREV_SHA" ]; then
    echo "❌ 無法讀取 .deploy-version，請手動指定 commit SHA："
    echo "   bash scripts/staging-rollback.sh <commit-sha>"
    exit 1
  fi
  # 找 PREV_SHA 的前一個 commit（即上上版）
  ROLLBACK_SHA=$(git log --format="%H" "$PREV_SHA~1" -1 2>/dev/null || echo "")
  if [ -z "$ROLLBACK_SHA" ]; then
    echo "❌ 無法從 $PREV_SHA 取得前一版 commit。"
    exit 1
  fi
  echo "  當前版本 : $(echo "$PREV_SHA" | cut -c1-8)"
  echo "  回滾目標 : $(echo "$ROLLBACK_SHA" | cut -c1-8) ($(git log --format='%s' "$ROLLBACK_SHA" -1 2>/dev/null || echo 'unknown'))"
else
  ROLLBACK_SHA="$TARGET_SHA"
  echo "  指定回滾目標 : $ROLLBACK_SHA"
fi

echo ""
read -r -p "  確定回滾 Staging 到 $ROLLBACK_SHA？[y/N] " confirm
case "$confirm" in
  [yY]|[yY][eE][sS]) ;;
  *) echo "  已取消。"; exit 0 ;;
esac

# ── Step 3/5：伺服器端回滾 ────────────────────────────────
echo ""
echo "[3/5] 執行伺服器端回滾..."

ssh "$REMOTE" bash <<REMOTE_EOF
set -euo pipefail
PROJECT="$REMOTE_PROJECT"
COMPOSE_FILE="\$PROJECT/docker-compose.staging.yml"
LOG_DIR="/var/log/mimeet-deploy"
LOG_FILE="\$LOG_DIR/rollback-\$(date +%Y%m%d-%H%M%S).log"
mkdir -p "\$LOG_DIR"

exec > >(tee -a "\$LOG_FILE") 2>&1
trap 'echo ""; echo "💥 回滾失敗。最後 50 行 log："; echo "---"; tail -50 "\$LOG_FILE"' ERR

cd "\$PROJECT"

echo "=========================================="
echo " MiMeet Staging ROLLBACK — \$(date '+%Y-%m-%d %H:%M:%S')"
echo " 目標 commit: $ROLLBACK_SHA"
echo "=========================================="

echo ""
echo "[R-1/5] git reset --hard $ROLLBACK_SHA..."
git fetch origin
git reset --hard "$ROLLBACK_SHA"
echo "  HEAD: \$(git log -1 --oneline)"

echo ""
echo "[R-2/5] Laravel cache..."
docker exec -u www-data mimeet-app php artisan config:cache
docker exec -u www-data mimeet-app php artisan route:cache
echo "  ✅ Cache OK"

echo ""
echo "[R-3/5] 前台 npm build..."
cd "\$PROJECT/frontend"
npm ci --prefer-offline 2>&1 | tail -3
npm run build 2>&1 | tail -5
echo "  ✅ 前台 build OK"
cd "\$PROJECT"

echo ""
echo "[R-4/5] 後台 npm build..."
cd "\$PROJECT/admin"
npm ci --prefer-offline 2>&1 | tail -3
npm run build 2>&1 | tail -5
echo "  ✅ 後台 build OK"
cd "\$PROJECT"

echo ""
echo "[R-5/5] Worker restart..."
docker compose -f "\$COMPOSE_FILE" restart worker
sleep 3

GIT_SHA=\$(git log -1 --format="%H")
GIT_SHORT=\$(git log -1 --format="%h")
cat > "\$PROJECT/.deploy-version" <<VEOF
sha=\$GIT_SHA
short=\$GIT_SHORT
deployed_at=\$(date '+%Y-%m-%dT%H:%M:%S%z')
deployed_by=rollback
log=\$LOG_FILE
VEOF

echo ""
echo "=========================================="
echo "✅ 回滾完成：commit \$GIT_SHORT"
echo "   Log : \$LOG_FILE"
echo "=========================================="
REMOTE_EOF

# ── Step 4/5：⚠️  Migration 提醒 ─────────────────────────
echo ""
echo "[4/5] ⚠️  Migration 提醒"
echo "  若回滾的版本含 DB migration，需手動評估是否執行 migrate:rollback。"
echo "  指令：ssh mimeet-staging 'docker exec -u www-data mimeet-app php artisan migrate:rollback --step=N'"
echo "  （此腳本不自動回滾 migration，避免資料遺失）"

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
  echo "✅ 回滾完成，Smoke Test 全部通過。"
else
  echo "❌ Smoke Test 有失敗項目，請立即人工確認！"
  exit 1
fi
