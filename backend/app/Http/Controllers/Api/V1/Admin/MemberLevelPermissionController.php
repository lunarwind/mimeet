<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberLevelPermission;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberLevelPermissionController extends Controller
{
    /**
     * GET /api/v1/admin/settings/member-level-permissions
     */
    public function index(): JsonResponse
    {
        $permissions = DB::table('member_level_permissions')
            ->orderBy('level')
            ->orderBy('feature_key')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['permissions' => $permissions],
        ]);
    }

    /**
     * PATCH /api/v1/admin/settings/member-level-permissions
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*.level' => 'required|numeric',
            'permissions.*.feature_key' => 'required|string|max:50',
        ]);

        $updated = 0;
        foreach ($request->input('permissions') as $item) {
            $data = [];
            if (isset($item['enabled'])) {
                $data['enabled'] = (bool) $item['enabled'];
            }
            if (array_key_exists('value', $item)) {
                $data['value'] = $item['value'];
            }
            if (empty($data)) {
                continue;
            }

            $affected = DB::table('member_level_permissions')
                ->where('level', $item['level'])
                ->where('feature_key', $item['feature_key'])
                ->update($data);

            $updated += $affected;
        }

        MemberLevelPermission::clearCache();

        // Sync JSON matrix in system_settings
        $this->syncMatrixToSystemSetting();

        return response()->json([
            'success' => true,
            'data' => ['updated' => $updated],
        ]);
    }

    /**
     * GET /api/v1/admin/settings/permission-matrix
     * Returns the JSON-based permission matrix from system_settings.
     */
    public function matrix(): JsonResponse
    {
        $raw = SystemSetting::get('membership_permission_matrix', '{}');
        $matrix = is_string($raw) ? json_decode($raw, true) : $raw;

        return response()->json([
            'success' => true,
            'data' => ['matrix' => $matrix ?? []],
        ]);
    }

    /**
     * PATCH /api/v1/admin/settings/permission-matrix
     * Save the JSON permission matrix and sync to member_level_permissions table.
     */
    public function updateMatrix(Request $request): JsonResponse
    {
        $request->validate([
            'matrix' => 'required|array',
            'matrix.*' => 'array',
            'matrix.*.*' => 'numeric',
        ]);

        $matrix = $request->input('matrix');

        // Save to system_settings
        SystemSetting::set('membership_permission_matrix', json_encode($matrix, JSON_UNESCAPED_UNICODE), $request->user()?->id);

        // Sync to member_level_permissions table
        $allLevels = [0, 1, 1.5, 2, 3];
        foreach ($matrix as $featureKey => $enabledLevels) {
            foreach ($allLevels as $level) {
                $enabled = in_array($level, $enabledLevels, false);
                DB::table('member_level_permissions')
                    ->where('level', $level)
                    ->where('feature_key', $featureKey)
                    ->update(['enabled' => $enabled ? 1 : 0]);
            }
        }

        MemberLevelPermission::clearCache();

        return response()->json([
            'success' => true,
            'message' => '權限矩陣已更新',
        ]);
    }

    /**
     * Sync current member_level_permissions table state to system_settings JSON.
     */
    private function syncMatrixToSystemSetting(): void
    {
        $perms = DB::table('member_level_permissions')->get();
        $matrix = [];
        foreach ($perms as $p) {
            if ($p->enabled) {
                $matrix[$p->feature_key][] = (float) $p->level;
            }
        }
        // Sort levels within each feature
        foreach ($matrix as &$levels) {
            sort($levels);
        }
        SystemSetting::set('membership_permission_matrix', json_encode($matrix, JSON_UNESCAPED_UNICODE));
    }
}
