#!/bin/bash
# 驗證 API POST response 沒有 body pollution
# 用法：bash scripts/check-response-pollution.sh

set -e

echo "=== Response Pollution Check ==="

RESPONSE=$(curl -s -X POST https://api.mimeet.online/api/v1/admin/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"chuck@lunarwind.org","password":"ChangeMe@2026"}')

FIRST_CHAR=$(echo "$RESPONSE" | head -c 1)

if [ "$FIRST_CHAR" = "{" ]; then
    echo "  [OK] Response 乾淨，無污染（以 { 開頭）"
else
    echo "  [FAIL] Response 污染！開頭字元：'${FIRST_CHAR}'"
    echo "  前 150 字：${RESPONSE:0:150}"
    exit 1
fi

echo "$RESPONSE" | python3 -m json.tool > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "  [OK] JSON parse 成功"
else
    echo "  [FAIL] JSON parse 失敗"
    exit 1
fi

echo "=== Pollution Check PASSED ==="
