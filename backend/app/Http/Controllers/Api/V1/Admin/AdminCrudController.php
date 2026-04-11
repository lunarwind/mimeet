<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminCrudController extends Controller
{
    /**
     * GET /api/v1/admin/settings/admins
     */
    public function index(): JsonResponse
    {
        $admins = AdminUser::orderByDesc('created_at')
            ->get()
            ->map(fn (AdminUser $a) => [
                'id' => $a->id,
                'nickname' => $a->name,
                'email' => $a->email,
                'role' => $a->role,
                'status' => $a->is_active ? 'active' : 'disabled',
                'last_active_at' => $a->last_login_at?->toISOString(),
                'created_at' => $a->created_at?->toISOString(),
            ]);

        return response()->json([
            'success' => true,
            'data' => ['admins' => $admins],
        ]);
    }

    /**
     * POST /api/v1/admin/settings/admins
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'email' => 'required|email|unique:admin_users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:super_admin,admin,cs',
        ]);

        $admin = AdminUser::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => $request->input('role'),
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'role' => $admin->role,
                ],
            ],
        ], 201);
    }

    /**
     * PATCH /api/v1/admin/settings/admins/{id}/role
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'role' => 'required|in:super_admin,admin,cs',
        ]);

        $currentAdmin = $request->user();
        if ($currentAdmin->id === $id) {
            return response()->json([
                'success' => false,
                'message' => '不可修改自己的角色',
            ], 422);
        }

        $admin = AdminUser::findOrFail($id);
        $admin->update(['role' => $request->input('role')]);

        return response()->json([
            'success' => true,
            'message' => '角色已更新',
            'data' => ['role' => $admin->role],
        ]);
    }

    /**
     * GET /api/v1/admin/settings/roles
     */
    public function roles(): JsonResponse
    {
        $permissions = DB::table('admin_permissions')->get();
        $rolePermissions = DB::table('admin_role_permissions')->get()->groupBy('role');

        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => $permissions,
                'role_permissions' => $rolePermissions,
            ],
        ]);
    }
}
