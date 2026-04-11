<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
<<<<<<< HEAD
    protected $fillable = ['key', 'value', 'type', 'group', 'description'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", 300, function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            if (!$setting) return $default;

            return match ($setting->type) {
                'integer' => (int) $setting->value,
                'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'json' => json_decode($setting->value, true),
                default => $setting->value,
            };
        });
    }

    public static function setValue(string $key, mixed $value, string $type = 'string', ?string $group = null, ?string $description = null): void
    {
        $storeValue = is_array($value) ? json_encode($value) : (string) $value;

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $storeValue, 'type' => $type, 'group' => $group ?? 'general', 'description' => $description]
        );

        Cache::forget("setting:{$key}");
=======
    protected $table = 'system_settings';
    protected $primaryKey = 'key_name';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key_name', 'value', 'description', 'value_type', 'updated_by'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("sys:{$key}", 60, function () use ($key, $default) {
            $setting = static::where('key_name', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public static function set(string $key, mixed $value, ?int $adminId = null): void
    {
        static::updateOrCreate(
            ['key_name' => $key],
            ['value' => (string) $value, 'updated_by' => $adminId]
        );
        Cache::forget("sys:{$key}");
>>>>>>> develop
    }
}
