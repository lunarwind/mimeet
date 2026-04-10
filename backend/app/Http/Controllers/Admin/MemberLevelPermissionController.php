<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberLevelPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberLevelPermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = MemberLevelPermission::orderBy('level')->orderBy('permission_key')->get();
        $grouped = $permissions->groupBy('level');
        return response()->json(['success' => true, 'data' => ['permissions' => $grouped]]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'level' => 'required|numeric',
            'permission_key' => 'required|string',
            'is_allowed' => 'required|boolean',
            'config' => 'sometimes|array',
        ]);

        MemberLevelPermission::updateOrCreate(
            ['level' => $request->level, 'permission_key' => $request->permission_key],
            ['is_allowed' => $request->is_allowed, 'config' => $request->config]
        );

        return response()->json(['success' => true, 'message' => '權限已更新']);
    }
}
