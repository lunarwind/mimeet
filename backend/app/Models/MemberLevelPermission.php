<?php
<<<<<<< HEAD
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberLevelPermission extends Model
{
    protected $fillable = ['level', 'permission_key', 'is_allowed', 'config'];
    protected $casts = ['level' => 'decimal:1', 'is_allowed' => 'boolean', 'config' => 'array'];
=======

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MemberLevelPermission extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null; // composite key

    protected $fillable = ['level', 'feature_key', 'enabled', 'value'];

    protected $casts = [
        'level' => 'decimal:1',
        'enabled' => 'boolean',
    ];

    /**
     * Get all permissions grouped by level.
     */
    public static function getMatrix(): array
    {
        return Cache::remember('member_level_permissions', 60, function () {
            return self::all()->toArray();
        });
    }

    /**
     * Check if a feature is enabled for a given level.
     */
    public static function isEnabled(float $level, string $featureKey): bool
    {
        $perm = self::where('level', $level)->where('feature_key', $featureKey)->first();

        return $perm?->enabled ?? false;
    }

    /**
     * Get the value setting for a feature at a given level.
     */
    public static function getValue(float $level, string $featureKey): ?string
    {
        $perm = self::where('level', $level)->where('feature_key', $featureKey)->first();

        return $perm?->value;
    }

    public static function clearCache(): void
    {
        Cache::forget('member_level_permissions');
    }
>>>>>>> develop
}
