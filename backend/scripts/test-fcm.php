<?php

/**
 * backend/scripts/test-fcm.php
 *
 * FCM 推播測試 script。取代 tinker 多層轉義地獄。
 *
 * 用法（staging）：
 *   ssh mimeet-staging 'docker exec mimeet-app php /var/www/html/scripts/test-fcm.php'
 *
 * 用法（本機）：
 *   docker exec mimeet-app php /var/www/html/scripts/test-fcm.php
 *
 * 自動行為：
 * - 找最新的 fcm_tokens record
 * - 顯示當前 credential 的 project_id（換帳號後 visual confirm 用）
 * - 嘗試發送測試推播
 * - 顯示結果（成功/失敗 + 原因）
 *
 * 退出代碼：
 *   0 = 成功
 *   1 = fcm_tokens 表為空（沒有 token 可測，前端尚未註冊）
 *   2 = credentials 未設定或檔案不存在
 *   3 = 發送失敗
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "════════════════════════════════════════\n";
echo "  FCM 推播測試\n";
echo "════════════════════════════════════════\n\n";

// Step 1：找最新 token
$tokenRecord = \App\Models\FcmToken::latest()->first();

if (!$tokenRecord) {
    echo "⚠️  fcm_tokens 表為空。\n";
    echo "   請先從前端/手機 app 登入並註冊 FCM token。\n";
    echo "   API 端點：POST /api/v1/users/me/fcm-token\n";
    exit(1);
}

echo "✓ 找到測試 token\n";
echo "  user_id  : {$tokenRecord->user_id}\n";
echo "  platform : {$tokenRecord->platform}\n";
echo "  token    : " . substr($tokenRecord->token, 0, 30) . "...\n\n";

// Step 2：確認 credentials 已設定
$path = config('services.fcm.credentials_path');
$json = config('services.fcm.credentials_json');

if (!$path && !$json) {
    echo "❌ FCM credentials 未設定。\n";
    echo "   請設定 .env 中的 FIREBASE_CREDENTIALS_PATH 或 FIREBASE_CREDENTIALS_JSON\n";
    echo "   並執行：docker exec -u www-data mimeet-app php artisan config:cache\n";
    exit(2);
}

if ($path && !file_exists($path)) {
    echo "❌ FCM credentials path 設定但檔案不存在：{$path}\n";
    echo "   請確認檔案已上傳到 staging 對應位置。\n";
    exit(2);
}

echo "✓ FCM credentials 已設定\n";
echo "  模式：" . ($path ? "檔案路徑（{$path}）" : "JSON 內容") . "\n";

// 顯示目前在用的 project_id（換帳號後 visual confirm 用）
$rawCred = $path ? @file_get_contents($path) : $json;
$decoded = $rawCred ? json_decode($rawCred, true) : null;
if (is_array($decoded)) {
    echo "  project_id   : " . ($decoded['project_id'] ?? '未知') . "\n";
    echo "  client_email : " . ($decoded['client_email'] ?? '未知') . "\n";
}
echo "\n";

// Step 3：發送測試
echo "▶ 發送測試推播...\n";

$fcm = app(\App\Services\FcmService::class);
$ok = $fcm->send(
    $tokenRecord->token,
    'MiMeet 測試',
    '推播功能正常運作 ✅',
    ['type' => 'test', 'timestamp' => (string) now()->timestamp],
);

echo "\n";
echo "════════════════════════════════════════\n";
if ($ok) {
    echo "  ✅ FCM 發送成功\n";
    echo "════════════════════════════════════════\n";

    if (config('app.env') !== 'production') {
        echo "  ⚠️  注意：APP_ENV=" . config('app.env') . "，FcmService 可能為 stub 模式\n";
        echo "     （只記 log，裝置不會真的收到推播）\n";
        echo "     如需真實發送，請確認 FcmService.php 的 env 判斷邏輯\n";
    } else {
        echo "  請在裝置上確認收到推播。\n";
    }

    echo "  若裝置未收到，可能原因：\n";
    echo "    - APP_ENV 不是 production（stub 模式）\n";
    echo "    - token 已失效（FcmService 會自動清理 404）\n";
    echo "    - 裝置處於勿擾模式或無網路\n";
    echo "    - 推播權限被關閉\n";
    exit(0);
} else {
    echo "  ❌ FCM 發送失敗\n";
    echo "════════════════════════════════════════\n";
    echo "  查看詳細錯誤：\n";
    echo "    docker exec mimeet-app tail -50 /var/www/html/storage/logs/laravel.log | grep -i fcm\n";
    echo "  常見原因：\n";
    echo "    - service-account.json 內容錯誤或 private_key 解析失敗\n";
    echo "    - access token 取得失敗（Google OAuth 端點）\n";
    echo "    - 網路連線問題\n";
    exit(3);
}
