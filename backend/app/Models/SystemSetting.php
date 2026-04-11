<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
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
    }
}
