<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        $announcements = Cache::get('announcements', [
            ['id' => 1, 'title' => '歡迎使用 MiMeet', 'content' => '感謝您加入 MiMeet 交友平台！', 'type' => 'info', 'is_active' => true, 'start_at' => now()->subDays(30)->toISOString(), 'end_at' => now()->addDays(30)->toISOString(), 'created_at' => now()->subDays(30)->toISOString()],
        ]);
        return response()->json(['success' => true, 'data' => ['announcements' => $announcements]]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'content' => 'required|string|max:500',
            'type' => 'sometimes|in:info,warning,success',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
        ]);
        return response()->json(['success' => true, 'message' => '公告已建立'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => true, 'message' => '公告已更新']);
    }

    public function destroy(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'message' => '公告已刪除']);
    }
}
