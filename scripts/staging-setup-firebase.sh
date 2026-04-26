#!/usr/bin/env bash
#
# scripts/staging-setup-firebase.sh
# MiMeet Staging Firebase 一次性 setup script
#
# 用法：
#   bash scripts/staging-setup-firebase.sh <local-path-to-credential>
#
# 範例：
#   bash scripts/staging-setup-firebase.sh ~/secrets/mimeet/firebase-service-account.json
#
# 流程：
#   1. 驗證本機 credential 檔案（JSON 格式 + 必要欄位）
#   2. SCP 上傳到 staging（用 mimeet-staging alias）
#   3. 驗證 staging 端檔案存在 + 設定權限 chmod 600
#   4. 清理 staging .env 中棄用的 FCM_SERVER_KEY（FCM Legacy API 2024-06 停服）
#   5. 設定 staging .env 中的 FIREBASE_CREDENTIALS_PATH
#   6. 重建 config cache + 清舊 access token cache（換帳號時必要）
#   7. 跑 test-fcm.php 驗證
#
# Idempotent：可重複執行，已設定的步驟會跳過（換帳號時會強制覆蓋檔案）
#
# Rollback：
#   ssh mimeet-staging 'ls /var/www/mimeet/backend/.env.backup-firebase-setup-*'
#   ssh mimeet-staging 'cp /var/www/mimeet/backend/.env.backup-firebase-setup-<timestamp> /var/www/mimeet/backend/.env'
#   ssh mimeet-staging 'rm /var/www/mimeet/backend/storage/firebase-service-account.json'

set -euo pipefail

REMOTE="mimeet-staging"
REMOTE_PROJECT="/var/www/mimeet"
REMOTE_PATH="${REMOTE_PROJECT}/backend/storage/firebase-service-account.json"

# ── 引數解析 ──────────────────────────────────────────────
if [ $# -ne 1 ]; then
    echo "❌ 用法：bash $0 <path-to-firebase-service-account.json>"
    echo ""
    echo "範例：bash $0 ~/secrets/mimeet/firebase-service-account.json"
    exit 1
fi

LOCAL_PATH="$1"

# ── Step 1：本機檔案驗證 ──────────────────────────────────
echo "[1/7] 驗證本機 credential 檔案..."

if [ ! -f "$LOCAL_PATH" ]; then
    echo "  ❌ 檔案不存在：$LOCAL_PATH"
    exit 1
fi

# 驗證 JSON 格式
if command -v python3 >/dev/null 2>&1; then
    if ! python3 -c "import json; json.load(open('$LOCAL_PATH'))" 2>/dev/null; then
        echo "  ❌ 檔案不是合法 JSON"
        exit 1
    fi
fi

# 驗證必要欄位
for key in "type" "project_id" "private_key" "client_email"; do
    if ! grep -q "\"$key\"" "$LOCAL_PATH"; then
        echo "  ❌ 缺少必要欄位：$key"
        exit 1
    fi
done

# 驗證 type = service_account
if ! grep -q '"type".*"service_account"' "$LOCAL_PATH"; then
    echo "  ❌ type 不是 service_account（可能不是正確的 credential 檔案）"
    exit 1
fi

# 擷取 project_id / client_email 供確認
PROJECT_ID=$(grep -o '"project_id": *"[^"]*"' "$LOCAL_PATH" | head -1 | sed 's/.*: *"\(.*\)"/\1/')
CLIENT_EMAIL=$(grep -o '"client_email": *"[^"]*"' "$LOCAL_PATH" | head -1 | sed 's/.*: *"\(.*\)"/\1/')

echo "  ✅ 檔案合法"
echo "  project_id   : $PROJECT_ID"
echo "  client_email : $CLIENT_EMAIL"

# 防呆：確認本機 git 不追蹤此檔案
if [ -d ".git" ] && git ls-files --error-unmatch "$LOCAL_PATH" 2>/dev/null; then
    echo ""
    echo "  ⚠️  警告：此檔案在 git 追蹤中！"
    echo "     建議移到 git working tree 之外（如 ~/secrets/mimeet/）"
    read -r -p "  繼續上傳嗎？[y/N] " confirm
    case "$confirm" in
        [yY]) ;;
        *) echo "❌ 已取消"; exit 1 ;;
    esac
fi

# ── Step 2：SCP 上傳 ──────────────────────────────────────
echo ""
echo "[2/7] SCP 上傳到 ${REMOTE}:${REMOTE_PATH}..."

if scp "$LOCAL_PATH" "${REMOTE}:${REMOTE_PATH}"; then
    echo "  ✅ 上傳完成"
else
    echo "  ❌ SCP 失敗，請確認 ~/.ssh/config 含 mimeet-staging alias"
    echo "     驗證：ssh mimeet-staging 'echo OK'"
    exit 1
fi

# ── Step 3：staging 端驗證 + 設定權限 ────────────────────
echo ""
echo "[3/7] 驗證 staging 端檔案..."

ssh "$REMOTE" bash <<REMOTE_EOF
set -e
if [ ! -f '$REMOTE_PATH' ]; then
    echo "  ❌ 上傳後檔案不存在"
    exit 1
fi

SIZE=\$(stat -c%s '$REMOTE_PATH')
if [ "\$SIZE" -lt 1000 ]; then
    echo "  ❌ 檔案大小異常（\${SIZE} bytes，預期 >1000）"
    exit 1
fi

chmod 600 '$REMOTE_PATH'
chown root:root '$REMOTE_PATH'

echo "  ✅ 大小：\${SIZE} bytes"
echo "  ✅ 權限：\$(stat -c '%a (%U:%G)' '$REMOTE_PATH')"
REMOTE_EOF

# ── Step 4：清理 .env 棄用設定 ───────────────────────────
echo ""
echo "[4/7] 清理 staging .env 棄用的 FCM_SERVER_KEY..."

ssh "$REMOTE" bash <<'REMOTE_EOF'
cd /var/www/mimeet/backend

if grep -qE "^FCM_SERVER_KEY=|^# FCM Push Notification" .env; then
    cp .env ".env.backup-firebase-setup-$(date +%Y%m%d-%H%M%S)"
    sed -i '/^# FCM Push Notification/d; /^FCM_SERVER_KEY=/d' .env
    echo "  ✅ 已移除棄用的 FCM_SERVER_KEY 設定（已備份 .env）"
else
    echo "  ⏭️  無棄用設定需清理"
fi
REMOTE_EOF

# ── Step 5：設定 .env ─────────────────────────────────────
echo ""
echo "[5/7] 設定 staging .env..."

ssh "$REMOTE" bash <<'REMOTE_EOF'
cd /var/www/mimeet/backend

if grep -q "^FIREBASE_CREDENTIALS_PATH=" .env; then
    EXISTING=$(grep "^FIREBASE_CREDENTIALS_PATH=" .env | head -1 | cut -d= -f2-)
    if [ "$EXISTING" = "storage/firebase-service-account.json" ]; then
        echo "  ⏭️  FIREBASE_CREDENTIALS_PATH 已設定且值正確"
    else
        echo "  ⚠️  FIREBASE_CREDENTIALS_PATH 已設定但值不同：$EXISTING"
        echo "     如要更新請手動編輯 .env"
    fi
else
    printf '\n# Firebase / FCM Push Notification (HTTP v1 API)\nFIREBASE_CREDENTIALS_PATH=storage/firebase-service-account.json\nFIREBASE_CREDENTIALS_JSON=\n' >> .env
    echo "  ✅ 已加入 FIREBASE_CREDENTIALS_PATH 設定"
fi
REMOTE_EOF

# ── Step 6：重建 config cache + 清舊 access token ─────────
echo ""
echo "[6/7] 重建 config cache + 清舊 fcm_access_token cache..."

ssh "$REMOTE" bash <<'REMOTE_EOF'
# config:cache 使 .env 變更生效
docker exec -u www-data mimeet-app php artisan config:cache 2>&1 | tail -2

# 強制清掉舊的 fcm_access_token cache（換帳號時必要）
# FcmService 用 Cache::remember 快取 access token 1 小時，
# 換 credential 後若不清，新推播會繼續用舊 token 直到 cache 自然過期。
docker exec -u www-data mimeet-app php artisan cache:forget fcm_access_token 2>&1 | tail -2

echo "  ✅ config cache 已重建"
echo "  ✅ fcm_access_token cache 已清除（下次推播會用新 credential 重新換 token）"
REMOTE_EOF

# ── Step 7：git pull + 跑 test-fcm.php 驗證 ─────────────
echo ""
echo "[7/7] git pull 並執行 test-fcm.php 驗證..."

ssh "$REMOTE" bash <<'REMOTE_EOF'
cd /var/www/mimeet
git pull origin main 2>&1 | tail -3
REMOTE_EOF

ssh "$REMOTE" docker exec mimeet-app php /var/www/html/scripts/test-fcm.php || {
    EXIT_CODE=$?
    echo ""
    echo "  test-fcm.php 退出代碼：$EXIT_CODE"
    case "$EXIT_CODE" in
        1) echo "  ⚠️  退出碼 1：fcm_tokens 表為空（正常，前端尚未註冊 token）" ;;
        2) echo "  ❌ 退出碼 2：credentials 設定問題，請檢查上方輸出" ;;
        3) echo "  ❌ 退出碼 3：發送失敗" ;;
        *) echo "  ❌ 退出碼 $EXIT_CODE：未知錯誤" ;;
    esac
}

echo ""
echo "════════════════════════════════════════"
echo "  ✅ Firebase 設定流程執行完成"
echo "════════════════════════════════════════"
echo ""
echo "後續手動驗收："
echo "  1. 從前端登入並呼叫 POST /api/v1/users/me/fcm-token 註冊 token"
echo "  2. 重新跑 test-fcm.php："
echo "     ssh $REMOTE 'docker exec mimeet-app php /var/www/html/scripts/test-fcm.php'"
echo "  3. 確認裝置收到推播（注意：APP_ENV 非 production 時為 stub 模式）"
echo ""
echo "如需移除設定："
echo "  ssh $REMOTE 'rm /var/www/mimeet/backend/storage/firebase-service-account.json'"
echo "  ssh $REMOTE 'ls /var/www/mimeet/backend/.env.backup-firebase-setup-*'"
