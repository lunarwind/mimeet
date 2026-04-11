<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\SystemSetting;
use App\Services\Sms\Every8dDriver;
use App\Services\Sms\LogDriver;
use App\Services\Sms\MitakeDriver;
use App\Services\Sms\TwilioDriver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SystemControlController extends Controller
{
    public function index(): JsonResponse
    {
        $mode = SystemSetting::get('app.mode', 'testing');

        return response()->json(['success' => true, 'data' => [
            'app_mode' => [
                'mode' => $mode,
                'maintenance_mode' => (bool) (int) SystemSetting::get('app.maintenance', '0'),
                'version' => SystemSetting::get('app.version', '1.0.0'),
            ],
            'mail' => [
                'host' => SystemSetting::get('mail.host', 'mailpit'),
                'port' => (int) SystemSetting::get('mail.port', 1025),
                'encryption' => SystemSetting::get('mail.encryption', 'null'),
                'username' => SystemSetting::get('mail.username', 'null'),
                'password' => '****',
                'from_address' => SystemSetting::get('mail.from_address', 'noreply@mimeet.tw'),
                'from_name' => SystemSetting::get('mail.from_name', 'MiMeet 平台'),
                'enabled' => $mode === 'production',
            ],
            'sms' => [
                'provider' => SystemSetting::get('sms.provider', 'disabled'),
                'enabled' => $mode === 'production' && SystemSetting::get('sms.provider', 'disabled') !== 'disabled',
                'mitake' => ['username' => SystemSetting::get('sms.mitake.username', ''), 'password' => '****'],
                'twilio' => ['account_sid' => SystemSetting::get('sms.twilio.account_sid', ''), 'auth_token' => '****', 'from_number' => SystemSetting::get('sms.twilio.from_number', '')],
                'every8d' => ['username' => SystemSetting::get('sms.every8d.username', ''), 'password' => '****'],
            ],
            'database' => [
                'host' => env('DB_HOST', 'mysql'),
                'port' => (int) env('DB_PORT', 3306),
                'database' => env('DB_DATABASE', 'mimeet'),
                'username' => env('DB_USERNAME', 'mimeet_user'),
                'password' => '****',
                'connection_status' => $this->checkDbConnection() ? 'connected' : 'error',
            ],
        ]]);
    }

    public function updateAppMode(Request $request): JsonResponse
    {
        $request->validate([
            'mode' => 'required|in:testing,production',
            'confirm_password' => 'required|string',
        ]);

        $admin = $request->user();
        if (!Hash::check($request->confirm_password, $admin->password)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'PASSWORD_INCORRECT', 'message' => '密碼驗證失敗'],
            ], 422);
        }

        $oldMode = SystemSetting::get('app.mode', 'testing');
        SystemSetting::set('app.mode', $request->mode, $admin->id);

        Log::info("[SystemControl] Mode switched: {$oldMode} → {$request->mode} by admin #{$admin->id}");

        $messages = [
            'testing' => '系統已切換為測試模式，Email/SMS 服務已停用',
            'production' => '系統已切換為正式模式，Email 與 SMS 服務已啟用',
        ];

        return response()->json(['success' => true, 'data' => [
            'mode' => $request->mode,
            'message' => $messages[$request->mode],
        ]]);
    }

    public function getAppMode(): JsonResponse
    {
        $mode = SystemSetting::get('app.mode', 'testing');
        return response()->json(['success' => true, 'data' => [
            'mode' => $mode,
            'mail_enabled' => $mode === 'production',
            'sms_enabled' => $mode === 'production' && SystemSetting::get('sms.provider', 'disabled') !== 'disabled',
            'ecpay_sandbox' => $mode === 'testing',
            'description' => $mode === 'testing'
                ? '測試模式：Email/SMS 只寫 Log，綠界使用 Sandbox'
                : '正式模式：Email/SMS 實際發送，綠界使用正式環境',
        ]]);
    }

    public function updateMail(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'sometimes|string|max:255',
            'port' => 'sometimes|integer|between:1,65535',
            'encryption' => 'sometimes|in:null,tls,ssl',
            'username' => 'sometimes|string|max:255',
            'password' => 'sometimes|nullable|string',
            'from_address' => 'sometimes|email',
            'from_name' => 'sometimes|string|max:100',
        ]);

        $admin = $request->user();
        $nonSensitive = ['host', 'port', 'encryption', 'username', 'from_address', 'from_name'];
        foreach ($nonSensitive as $key) {
            if ($request->has($key)) {
                SystemSetting::set("mail.{$key}", $request->input($key), $admin->id);
            }
        }

        if ($request->filled('password')) {
            SystemSetting::set('mail.password_encrypted', Crypt::encryptString($request->password), $admin->id);
        }

        try { Log::info("[SystemControl] Mail settings updated by admin #{$admin->id}"); } catch (\Throwable) {}

        return response()->json(['success' => true, 'data' => ['message' => 'Email 設定已更新']]);
    }

    public function testMail(Request $request): JsonResponse
    {
        $request->validate(['test_email' => 'required|email']);
        $start = microtime(true);
        $debug = [];
        $success = false;
        $errorDetail = null;

        // Apply DB-stored mail settings for this request
        $host = SystemSetting::get('mail.host', config('mail.mailers.smtp.host'));
        $port = (int) SystemSetting::get('mail.port', config('mail.mailers.smtp.port'));
        $enc = SystemSetting::get('mail.encryption', config('mail.mailers.smtp.encryption'));
        $user = SystemSetting::get('mail.username', config('mail.mailers.smtp.username'));
        $from = SystemSetting::get('mail.from_address', config('mail.from.address'));
        $fromName = SystemSetting::get('mail.from_name', config('mail.from.name'));
        $passEnc = SystemSetting::get('mail.password_encrypted', '');
        $pass = $passEnc ? (function () use ($passEnc) { try { return Crypt::decryptString($passEnc); } catch (\Throwable) { return $passEnc; } })() : config('mail.mailers.smtp.password');

        $encryption = ($enc === 'null' || $enc === 'none' || !$enc) ? null : $enc;

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.mailers.smtp.username' => $user,
            'mail.mailers.smtp.password' => $pass,
            'mail.from.address' => $from,
            'mail.from.name' => $fromName,
        ]);

        // Purge cached mailer so it picks up new config
        Mail::purge('smtp');

        $debug[] = '[' . now()->format('H:i:s') . '] 開始 SMTP 測試';
        $debug[] = "  Host       : {$host}";
        $debug[] = "  Port       : {$port}";
        $debug[] = '  Encryption : ' . ($encryption ?: 'none');
        $debug[] = "  Username   : {$user}";
        $debug[] = "  From       : {$from}";
        $debug[] = '  To         : ' . $request->test_email;
        $debug[] = '  Password   : ' . ($pass ? '****（已設定）' : '（未設定）');

        try {
            Mail::mailer('smtp')->to($request->test_email)->send(new TestMail());
            $ms = round((microtime(true) - $start) * 1000);
            $success = true;
            $debug[] = "[" . now()->format('H:i:s') . "] ✅ 發送成功（{$ms}ms）";
        } catch (\Exception $e) {
            $ms = round((microtime(true) - $start) * 1000);
            $msg = $e->getMessage();
            $errorDetail = ['type' => get_class($e), 'message' => $msg, 'code' => $e->getCode()];
            $debug[] = "[" . now()->format('H:i:s') . "] ❌ 發送失敗（{$ms}ms）";
            $debug[] = "  錯誤類型 : " . get_class($e);
            $debug[] = "  錯誤訊息 : {$msg}";
            if (str_contains($msg, 'Connection refused')) $debug[] = '  診斷建議 : 無法連線，請確認 Host/Port 是否正確';
            elseif (str_contains($msg, 'Authentication')) $debug[] = '  診斷建議 : 認證失敗，請確認帳號/密碼';
            elseif (str_contains($msg, 'SSL') || str_contains($msg, 'TLS')) $debug[] = '  診斷建議 : SSL/TLS 握手失敗，嘗試切換加密方式';
            elseif (str_contains($msg, 'timeout')) $debug[] = '  診斷建議 : 連線逾時，確認防火牆是否開放 Port';
        }

        return response()->json([
            'success' => $success,
            'elapsed_ms' => round((microtime(true) - $start) * 1000),
            'debug_log' => $debug,
            'debug_text' => implode("\n", $debug),
            'error_detail' => $errorDetail,
        ], $success ? 200 : 422);
    }

    public function updateSms(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|in:mitake,twilio,disabled',
        ]);

        $admin = $request->user();
        SystemSetting::set('sms.provider', $request->provider, $admin->id);

        if ($request->provider === 'mitake' && $request->has('mitake')) {
            $m = $request->input('mitake');
            if (!empty($m['username'])) SystemSetting::set('sms.mitake.username', $m['username'], $admin->id);
            if (!empty($m['password'])) SystemSetting::set('sms.mitake.password_encrypted', Crypt::encryptString($m['password']), $admin->id);
        }

        if ($request->provider === 'twilio' && $request->has('twilio')) {
            $t = $request->input('twilio');
            if (!empty($t['account_sid'])) SystemSetting::set('sms.twilio.account_sid', $t['account_sid'], $admin->id);
            if (!empty($t['auth_token'])) SystemSetting::set('sms.twilio.auth_token_encrypted', Crypt::encryptString($t['auth_token']), $admin->id);
            if (!empty($t['from_number'])) SystemSetting::set('sms.twilio.from_number', $t['from_number'], $admin->id);
        }

        $labels = ['mitake' => '三竹簡訊', 'twilio' => 'Twilio', 'disabled' => '停用'];
        Log::info("[SystemControl] SMS provider changed to {$request->provider} by admin #{$admin->id}");

        return response()->json(['success' => true, 'data' => [
            'provider' => $request->provider,
            'message' => "SMS 服務已切換為{$labels[$request->provider]}",
        ]]);
    }

    public function testSms(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'provider_override' => 'sometimes|string|in:mitake,twilio',
            'username' => 'sometimes|string',
            'password' => 'sometimes|string',
            'from_number' => 'sometimes|string',
            'message' => 'sometimes|string|max:500',
        ]);

        $msg = $request->input('message', '【MiMeet】後台 SMS 設定測試，請忽略此訊息。');
        $providerOverride = $request->input('provider_override');
        $provider = $providerOverride ?? SystemSetting::get('sms.provider', 'disabled');

        // Mitake override (username + password provided, and provider is mitake)
        if ($provider === 'mitake' && $request->filled('username') && $request->filled('password')) {
            $driver = new MitakeDriver();
            $result = $driver->sendWithDetail(
                $request->phone, $msg,
                $request->input('username'),
                $request->input('password'),
            );
            return $this->smsTestResponse($result, 'mitake', $request->phone);
        }

        // Twilio override or system Twilio
        if ($provider === 'twilio') {
            $driver = new TwilioDriver();
            $result = $driver->sendWithDetail(
                $request->phone, $msg,
                $request->input('username'),   // SID override
                $request->input('password'),   // Auth Token override
                $request->input('from_number'),
            );
            return $this->smsTestResponse($result, 'twilio', $request->phone);
        }

        // Mitake with system credentials
        if ($provider === 'mitake') {
            $driver = new MitakeDriver();
            $result = $driver->sendWithDetail($request->phone, $msg);
            return $this->smsTestResponse($result, 'mitake', $request->phone);
        }

        // Disabled / Log fallback
        $driver = new LogDriver();
        $success = $driver->send($request->phone, $msg);

        return response()->json([
            'success' => $success,
            'data' => [
                'message' => $success ? "測試簡訊已寫入 Log（SMS 停用中）" : '發送失敗',
                'provider' => 'log',
                'raw_response' => '',
            ],
        ]);
    }

    private function smsTestResponse(array $result, string $provider, string $phone): JsonResponse
    {
        $debug = [];
        $debug[] = '[' . now()->format('H:i:s') . "] SMS 測試 — {$provider}";
        $debug[] = '  To         : ' . $phone;
        $debug[] = '  HTTP Status: ' . ($result['http_status'] ?? 'N/A');

        if ($result['success']) {
            $debug[] = '[' . now()->format('H:i:s') . '] ✅ 發送成功';
        } else {
            $debug[] = '[' . now()->format('H:i:s') . '] ❌ 發送失敗';
            $debug[] = '  錯誤訊息 : ' . ($result['error'] ?? '未知錯誤');
            if ($code = ($result['twilio_error_code'] ?? null)) {
                $debug[] = "  Twilio Code: {$code}";
                if ($code == 21608) $debug[] = '  診斷建議 : 試用帳號只能發送到已驗證號碼，請到 Twilio Console 新增';
                elseif ($code == 21211) $debug[] = '  診斷建議 : 號碼格式無效，需 E.164 格式';
                elseif ($code == 21659) $debug[] = '  診斷建議 : From 號碼不屬於此 Twilio 帳號';
                elseif ($code == 20003) $debug[] = '  診斷建議 : SID 或 Auth Token 認證失敗';
            }
        }

        if (!empty($result['raw'])) {
            $debug[] = '  Raw        : ' . substr($result['raw'], 0, 500);
        }

        return response()->json([
            'success' => $result['success'],
            'elapsed_ms' => null,
            'debug_log' => $debug,
            'debug_text' => implode("\n", $debug),
            'error_detail' => $result['success'] ? null : [
                'error' => $result['error'] ?? null,
                'twilio_error_code' => $result['twilio_error_code'] ?? null,
                'http_status' => $result['http_status'] ?? null,
            ],
            'data' => [
                'message' => $result['success']
                    ? "測試簡訊已發送至 {$phone}"
                    : '發送失敗：' . ($result['error'] ?? '未知錯誤'),
                'provider' => $provider,
                'raw_response' => $result['raw'] ?? '',
                'http_status' => $result['http_status'] ?? null,
            ],
        ], $result['success'] ? 200 : 422);
    }

    public function updateDatabase(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'sometimes|string|max:255',
            'port' => 'sometimes|integer',
            'database' => 'sometimes|string|max:100',
            'username' => 'sometimes|string|max:100',
            'password' => 'sometimes|nullable|string',
            'confirm_password' => 'required|string',
        ]);

        if (!Hash::check($request->confirm_password, $request->user()->password)) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'PASSWORD_INCORRECT', 'message' => '密碼驗證失敗',
            ]], 422);
        }

        // Test connection first
        $h = $request->input('host', env('DB_HOST'));
        $p = $request->input('port', env('DB_PORT'));
        $d = $request->input('database', env('DB_DATABASE'));
        $u = $request->input('username', env('DB_USERNAME'));
        $pw = $request->filled('password') ? $request->password : env('DB_PASSWORD');

        try {
            new \PDO("mysql:host={$h};port={$p};dbname={$d}", $u, $pw, [\PDO::ATTR_TIMEOUT => 5]);
        } catch (\PDOException $e) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'DB_CONNECTION_FAILED', 'message' => '連線測試失敗：' . $e->getMessage(),
            ]], 422);
        }

        if ($request->has('host')) $this->writeEnv('DB_HOST', $h);
        if ($request->has('port')) $this->writeEnv('DB_PORT', (string) $p);
        if ($request->has('database')) $this->writeEnv('DB_DATABASE', $d);
        if ($request->has('username')) $this->writeEnv('DB_USERNAME', $u);
        if ($request->filled('password')) $this->writeEnv('DB_PASSWORD', $pw);

        Log::info("[SystemControl] DB settings updated by admin #{$request->user()->id}");

        return response()->json(['success' => true, 'data' => [
            'message' => '資料庫設定已更新。注意：完整生效需重啟應用容器（約 30 秒）',
            'restart_required' => true,
        ]]);
    }

    public function testDatabase(Request $request): JsonResponse
    {
        $request->validate([
            'host' => 'required|string',
            'port' => 'required|integer',
            'database' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $start = microtime(true);
        try {
            $pdo = new \PDO(
                "mysql:host={$request->host};port={$request->port};dbname={$request->database}",
                $request->username, $request->password, [\PDO::ATTR_TIMEOUT => 5]
            );
            $ms = round((microtime(true) - $start) * 1000);
            $version = $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

            return response()->json(['success' => true, 'data' => [
                'status' => 'connected', 'response_ms' => $ms, 'server_version' => $version,
            ]]);
        } catch (\PDOException $e) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'DB_CONNECTION_FAILED', 'message' => '無法連線：' . $e->getMessage(),
            ]], 422);
        }
    }

    private function checkDbConnection(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception) {
            return false;
        }
    }

    private function writeEnv(string $key, string $value): void
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) return;
        $content = file_get_contents($envPath);
        $pattern = "/^{$key}=.*/m";
        $replacement = "{$key}={$value}";
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $replacement, $content);
        } else {
            $content .= "\n{$key}={$value}";
        }
        file_put_contents($envPath, $content);
    }
}
