<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $admins = AdminUser::select('id', 'name', 'email', 'role', 'last_login_at')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return response()->json(['success' => true, 'data' => ['admins' => $admins]]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'email' => 'required|email|unique:admin_users,email',
            'role' => 'required|in:super_admin,admin,cs',
            'password' => 'required|string|min:8',
        ]);

        $admin = AdminUser::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);

        return response()->json(['success' => true, 'message' => '管理員已建立', 'data' => ['admin' => $admin]], 201);
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $request->validate(['role' => 'required|in:super_admin,admin,cs']);

        $admin = AdminUser::findOrFail($id);
        $admin->update(['role' => $request->role]);

        return response()->json(['success' => true, 'message' => '角色已更新']);
    }

    public function destroy(int $id): JsonResponse
    {
        $admin = AdminUser::findOrFail($id);
        $admin->update(['is_active' => false]);

        return response()->json(['success' => true, 'message' => '管理員已刪除']);
    }
}
