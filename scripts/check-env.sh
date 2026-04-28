#!/usr/bin/env bash
# MiMeet .env 必填變數檢查器
# Usage:
#   bash scripts/check-env.sh                  # 預設檢查 backend/.env
#   bash scripts/check-env.sh path/to/.env     # 指定 .env
#   bash scripts/check-env.sh --strict         # warning 也視為失敗（CI 用）
#
# Exit code:
#   0 = 全齊
#   1 = critical 缺漏 or --strict 模式下 warning 缺漏

set -euo pipefail

ENV_FILE="backend/.env"
STRICT=0

for arg in "$@"; do
  case "$arg" in
    --strict) STRICT=1 ;;
    *)        ENV_FILE="$arg" ;;
  esac
done

REQUIRED_LIST="$(cd "$(dirname "$0")" && pwd)/.env.required"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "❌ 找不到 .env 檔案：$ENV_FILE"
  exit 1
fi
if [[ ! -f "$REQUIRED_LIST" ]]; then
  echo "❌ 找不到必填清單：$REQUIRED_LIST"
  exit 1
fi

echo "📋 檢查 ${ENV_FILE} 必填變數..."
echo ""

MISSING_CRITICAL=0
MISSING_WARNING=0

while IFS='|' read -r key desc severity; do
  # skip 空行與 # 開頭的行
  [[ -z "${key// }" ]] && continue
  [[ "$key" =~ ^# ]]   && continue

  # 去除前後空白
  key="${key## }"; key="${key%% }"

  # 抓 .env 中該 key 的值（容忍引號與前後空白）
  raw=$(grep -E "^${key}\s*=" "$ENV_FILE" 2>/dev/null | head -1 || echo "")
  value=$(echo "$raw" | sed -E "s/^${key}\s*=//; s/^[\"']//; s/[\"']\s*$//; s/^\s+//; s/\s+$//")

  if [[ -z "$value" ]]; then
    if [[ "$severity" == "critical" ]]; then
      printf "❌  %-40s %s\n" "$key" "(CRITICAL) $desc"
      MISSING_CRITICAL=$((MISSING_CRITICAL + 1))
    else
      printf "⚠️   %-39s %s\n" "$key" "(warning) $desc"
      MISSING_WARNING=$((MISSING_WARNING + 1))
    fi
  fi
done < "$REQUIRED_LIST"

echo ""
echo "───────────────────────────────────────"
echo "Critical 缺漏：${MISSING_CRITICAL}"
echo "Warning  缺漏：${MISSING_WARNING}"

if [[ $MISSING_CRITICAL -gt 0 ]]; then
  echo "❌ 部署中止：critical 變數缺漏會導致 500 錯誤"
  exit 1
fi

if [[ $STRICT -eq 1 ]] && [[ $MISSING_WARNING -gt 0 ]]; then
  echo "❌ Strict 模式：warning 變數也視為失敗"
  exit 1
fi

echo "✅ 所有必填變數齊全"
exit 0
