<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SystemSetting extends Model
{
    protected $table = 'system_settings';
    protected $primaryKey = 'key_name';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key_name', 'value', 'is_encrypted', 'description', 'value_type', 'updated_by'];

    protected $casts = ['is_encrypted' => 'boolean'];

    /**
     * 讀取設定值（含自動解密 + 60 秒快取）。
     *
     * 解密失敗時（APP_KEY 變更、資料損毀）：
     * - Log::error 記錄
     * - 回傳 $default（不 throw，確保系統不因設定損毀而崩潰）
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("sys:{$key}", 60, function () use ($key, $default) {
            $setting = static::where('key_name', $key)->first();
            if (!$setting) {
                return $default;
            }

            $value = $setting->value;

            if ($setting->is_encrypted && $value !== '' && $value !== null) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Throwable $e) {
                    Log::error('[SystemSetting] Decrypt failed', [
                        'key'   => $key,
                        'error' => $e->getMessage(),
                    ]);
                    return $default;
                }
            }

            return ($value !== '' && $value !== null) ? $value : $default;
        });
    }

    /**
     * 儲存設定（不加密，向下相容）。
     * 清除快取確保即時生效。
     */
    public static function set(string $key, mixed $value, ?int $adminId = null): void
    {
        static::updateOrCreate(
            ['key_name' => $key],
            ['value' => (string) $value, 'updated_by' => $adminId]
        );
        static::clearCache($key);
    }

    /**
     * 儲存設定（支援加密，Step 6 新增）。
     * 清除 sys: 和 setting: 兩層快取確保即時生效。
     *
     * @param bool $encrypt true = 用 Crypt::encryptString 加密儲存
     */
    public static function put(string $key, mixed $value, bool $encrypt = false, ?int $adminId = null): void
    {
        $stored = ($encrypt && $value !== '') ? Crypt::encryptString((string) $value) : (string) $value;

        static::updateOrCreate(
            ['key_name' => $key],
            ['value' => $stored, 'is_encrypted' => $encrypt, 'updated_by' => $adminId]
        );

        static::clearCache($key);
    }

    /**
     * 清除該 key 的所有快取層。
     * sys: 為 SystemSetting::get() 的快取；setting: 為 CreditScoreService::getConfig() 的快取。
     */
    public static function clearCache(string $key): void
    {
        Cache::forget("sys:{$key}");
        Cache::forget("setting:{$key}");  // CreditScoreService 的額外快取層
    }
}
