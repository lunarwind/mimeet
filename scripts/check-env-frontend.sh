#!/usr/bin/env bash
# 前端 build 前檢查 VITE_* 必填變數
# Usage: bash scripts/check-env-frontend.sh [path/to/.env.production]

set -euo pipefail

ENV_FILE="${1:-frontend/.env.production}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "⚠️  找不到前端 env 檔案：${ENV_FILE}（跳過檢查）"
  exit 0
fi

REQUIRED=(
  "VITE_API_BASE_URL"
  "VITE_REVERB_APP_KEY"
  "VITE_REVERB_HOST"
  "VITE_REVERB_PORT"
  "VITE_REVERB_SCHEME"
)

MISSING=0
echo "📋 檢查 ${ENV_FILE} 前端必填變數..."
for key in "${REQUIRED[@]}"; do
  v=$(grep -E "^${key}\s*=" "$ENV_FILE" 2>/dev/null | head -1 | sed -E "s/^${key}\s*=//; s/^[\"']//; s/[\"']$//" || echo "")
  if [[ -z "$v" ]]; then
    echo "❌  ${key} 未設定"
    MISSING=$((MISSING + 1))
  fi
done

if [[ $MISSING -gt 0 ]]; then
  echo "❌ 前端環境變數缺漏，build 可能產生功能異常"
  exit 1
fi
echo "✅ 前端環境變數齊全"
