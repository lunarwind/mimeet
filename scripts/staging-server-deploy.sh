#!/usr/bin/env bash
# staging-server-deploy.sh — 伺服器端部署邏輯（由 staging-deploy.sh 透過 SSH 呼叫）
#
# ⚠️  不要直接在本機執行此腳本。本機入口是 staging-deploy.sh。
#
# 此腳本在 Staging Droplet 上執行：
#   - git pull origin main
#   - DB migration
#   - Laravel config/route cache
#   - 前台 + 後台 npm build
#   - Worker restart
#   - 寫入 .deploy-version（git SHA + 時間戳 + 部署者）
#
# 所有輸出同時寫入 /var/log/mimeet-deploy/deploy-YYYYMMDD-HHMMSS.log。
# 任何步驟失敗時自動 tail 50 行 log 並退出。

set -euo pipefail

PROJECT="/var/www/mimeet"
LOG_DIR="/var/log/mimeet-deploy"
LOG_FILE="$LOG_DIR/deploy-$(date +%Y%m%d-%H%M%S).log"
COMPOSE_FILE="$PROJECT/docker-compose.staging.yml"
STEPS=7

mkdir -p "$LOG_DIR"

# ── 雙重輸出：stdout + log 檔 ──────────────────────────────
exec > >(tee -a "$LOG_FILE") 2>&1

trap 'echo ""; echo "💥 部署失敗（exit $?）。最後 50 行 log："; echo "---"; tail -50 "$LOG_FILE"' ERR

cd "$PROJECT"

echo "=========================================="
echo " MiMeet Staging Deploy — $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================="

# ── Step 1/7：git pull ────────────────────────────────────
echo ""
echo "[$( echo 1)/$STEPS] git pull origin main..."
git pull origin main
echo "  HEAD: $(git log -1 --oneline)"

# ── Step 2/7：修正 storage 目錄權限（避免 log 寫入失敗）──
echo ""
echo "[2/$STEPS] 修正 storage 目錄權限..."
docker exec mimeet-app sh -c \
  "touch storage/logs/laravel.log && \
   chown www-data:www-data storage/logs/laravel.log && \
   chmod 664 storage/logs/laravel.log"
echo "  ✅ OK"

# ── Step 3/7：DB migration ────────────────────────────────
echo ""
echo "[3/$STEPS] DB migration..."
if docker exec -u www-data mimeet-app php artisan migrate --force; then
  echo "  ✅ Migration OK"
else
  echo "  ⚠️  Migration 失敗，中止部署。如需回滾，使用 staging-rollback.sh"
  exit 1
fi

# ── Step 4/7：Laravel cache ───────────────────────────────
echo ""
echo "[4/$STEPS] Laravel config/route cache..."
docker exec -u www-data mimeet-app php artisan config:cache
docker exec -u www-data mimeet-app php artisan route:cache
echo "  ✅ Cache OK"

# ── Step 5/7：前台 build ──────────────────────────────────
echo ""
echo "[5/$STEPS] 前台 npm build..."
cd "$PROJECT/frontend"
npm ci --prefer-offline 2>&1 | tail -3
npm run build 2>&1 | tail -5
echo "  ✅ 前台 build OK"
cd "$PROJECT"

# ── Step 6/7：後台 build ──────────────────────────────────
echo ""
echo "[6/$STEPS] 後台 npm build..."
cd "$PROJECT/admin"
npm ci --prefer-offline 2>&1 | tail -3
npm run build 2>&1 | tail -5
echo "  ✅ 後台 build OK"
cd "$PROJECT"

# ── Step 7/7：Worker restart ──────────────────────────────
echo ""
echo "[7/$STEPS] Worker restart..."
docker compose -f "$COMPOSE_FILE" restart worker
sleep 3

worker_status=$(docker compose -f "$COMPOSE_FILE" ps worker --format "{{.Status}}" 2>/dev/null || echo "unknown")
echo "  Worker status: $worker_status"
if echo "$worker_status" | grep -qi "up\|running"; then
  echo "  ✅ Worker OK"
else
  echo "  ⚠️  Worker 狀態異常，請手動確認："
  docker compose -f "$COMPOSE_FILE" ps worker
fi

# ── 寫入 .deploy-version ──────────────────────────────────
GIT_SHA=$(git log -1 --format="%H")
GIT_SHORT=$(git log -1 --format="%h")
DEPLOY_TIME=$(date '+%Y-%m-%dT%H:%M:%S%z')
DEPLOYER=$(git log -1 --format="%ae" 2>/dev/null || echo "unknown")

cat > "$PROJECT/.deploy-version" <<EOF
sha=$GIT_SHA
short=$GIT_SHORT
deployed_at=$DEPLOY_TIME
deployed_by=$DEPLOYER
log=$LOG_FILE
EOF

echo ""
echo "=========================================="
echo "✅ 部署完成"
echo "   Commit : $GIT_SHORT"
echo "   時間   : $DEPLOY_TIME"
echo "   Log    : $LOG_FILE"
echo "=========================================="
