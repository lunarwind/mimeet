<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        // Mock admin list for now (until AdminUser model is in S12)
        $admins = Cache::get('admin_users', [
            ['id' => 1, 'name' => 'Super Admin', 'email' => 'super@mimeet.tw', 'role' => 'super_admin', 'last_login_at' => now()->subHours(1)->toISOString()],
            ['id' => 2, 'name' => 'Admin 小明', 'email' => 'admin@mimeet.tw', 'role' => 'admin', 'last_login_at' => now()->subHours(5)->toISOString()],
            ['id' => 3, 'name' => 'CS 小華', 'email' => 'cs@mimeet.tw', 'role' => 'cs', 'last_login_at' => now()->subDays(1)->toISOString()],
        ]);
        return response()->json(['success' => true, 'data' => ['admins' => $admins]]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'email' => 'required|email',
            'role' => 'required|in:super_admin,admin,cs',
            'password' => 'required|string|min:8',
        ]);
        return response()->json(['success' => true, 'message' => '管理員已建立'], 201);
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $request->validate(['role' => 'required|in:super_admin,admin,cs']);
        return response()->json(['success' => true, 'message' => '角色已更新']);
    }

    public function destroy(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'message' => '管理員已刪除']);
    }
}
