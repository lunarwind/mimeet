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
            $this->writeEnv('MAIL_PASSWORD', $request->password);
            config(['mail.mailers.smtp.password' => $request->password]);
        }

        Log::info("[SystemControl] Mail settings updated by admin #{$admin->id}");

        return response()->json(['success' => true, 'data' => ['message' => 'Email 設定已更新']]);
    }

    public function testMail(Request $request): JsonResponse
    {
        $request->validate(['test_email' => 'required|email']);
        try {
            Mail::to($request->test_email)->send(new TestMail());
            return response()->json(['success' => true, 'data' => [
                'message' => "測試信已發送至 {$request->test_email}，請確認收信匣",
            ]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => [
                'code' => 'MAIL_SEND_FAILED',
                'message' => '發送失敗：' . $e->getMessage(),
            ]], 422);
        }
    }

    public function updateSms(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|in:mitake,twilio,every8d,disabled',
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

        if ($request->provider === 'every8d' && $request->has('every8d')) {
            $e = $request->input('every8d');
            if (!empty($e['username'])) SystemSetting::set('sms.every8d.username', $e['username'], $admin->id);
            if (!empty($e['password'])) SystemSetting::set('sms.every8d.password_encrypted', Crypt::encryptString($e['password']), $admin->id);
        }

        $labels = ['mitake' => '三竹簡訊', 'twilio' => 'Twilio', 'every8d' => '每日簡訊', 'disabled' => '停用'];
        Log::info("[SystemControl] SMS provider changed to {$request->provider} by admin #{$admin->id}");

        return response()->json(['success' => true, 'data' => [
            'provider' => $request->provider,
            'message' => "SMS 服務已切換為{$labels[$request->provider]}",
        ]]);
    }

    public function testSms(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string']);
        $driver = match (SystemSetting::get('sms.provider', 'disabled')) {
            'mitake' => new MitakeDriver(),
            'twilio' => new TwilioDriver(),
            'every8d' => new Every8dDriver(),
            default => new LogDriver(),
        };
        $success = $driver->send($request->phone, '【MiMeet】後台 SMS 設定測試，請忽略此訊息。');
        return $success
            ? response()->json(['success' => true, 'data' => ['message' => "測試簡訊已發送至 {$request->phone}"]])
            : response()->json(['success' => false, 'error' => ['code' => 'SMS_SEND_FAILED', 'message' => '發送失敗']], 422);
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
