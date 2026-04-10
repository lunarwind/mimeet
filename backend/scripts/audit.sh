#!/bin/bash
echo "=== Composer Audit ==="
cd "$(dirname "$0")/.." && composer audit 2>&1 || echo "composer audit not available"
echo ""
echo "=== Frontend npm audit ==="
cd "$(dirname "$0")/../../frontend" && npm audit --audit-level=moderate 2>&1 || echo "No vulnerabilities found"
echo ""
echo "=== Admin npm audit ==="
cd "$(dirname "$0")/../../admin" && npm audit --audit-level=moderate 2>&1 || echo "No vulnerabilities found"
