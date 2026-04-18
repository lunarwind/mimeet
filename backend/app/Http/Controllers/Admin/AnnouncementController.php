<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AnnouncementController extends Controller
{
    private const CACHE_KEY = 'system_announcements';

    public function index(): JsonResponse
    {
        $announcements = Cache::get(self::CACHE_KEY, []);

        return response()->json([
            'success' => true,
            'data' => ['announcements' => array_values($announcements)],
        ]);
    }

    /**
     * GET /api/v1/announcements/active — public, returns only active announcements within date range
     */
    public function getActive(): JsonResponse
    {
        $all = Cache::get(self::CACHE_KEY, []);
        $now = now();

        $active = array_values(array_filter($all, function ($a) use ($now) {
            if (!($a['is_active'] ?? false)) return false;
            if (!empty($a['start_at']) && $now->lt($a['start_at'])) return false;
            if (!empty($a['end_at']) && $now->gt($a['end_at'])) return false;
            return true;
        }));

        return response()->json([
            'success' => true,
            'data' => ['announcements' => $active],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'content' => 'required|string|max:500',
            'type' => 'sometimes|in:info,warning,success',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
        ]);

        $announcements = Cache::get(self::CACHE_KEY, []);
        $id = count($announcements) > 0 ? max(array_column($announcements, 'id')) + 1 : 1;

        $announcement = [
            'id' => $id,
            'title' => $data['title'],
            'content' => $data['content'],
            'type' => $data['type'] ?? 'info',
            'is_active' => true,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'created_at' => now()->toISOString(),
        ];

        $announcements[] = $announcement;
        Cache::forever(self::CACHE_KEY, $announcements);

        Log::info('[Announcement] Created', ['id' => $id, 'title' => $data['title']]);

        return response()->json([
            'success' => true,
            'message' => '公告已建立',
            'data' => ['announcement' => $announcement],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:100',
            'content' => 'sometimes|string|max:500',
            'type' => 'sometimes|in:info,warning,success',
            'is_active' => 'sometimes|boolean',
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date',
        ]);

        $announcements = Cache::get(self::CACHE_KEY, []);
        $found = false;

        foreach ($announcements as &$a) {
            if ($a['id'] === $id) {
                $a = array_merge($a, $data);
                $found = true;
                break;
            }
        }

        if (!$found) {
            return response()->json(['success' => false, 'message' => '公告不存在'], 404);
        }

        Cache::forever(self::CACHE_KEY, $announcements);
        Log::info('[Announcement] Updated', ['id' => $id]);

        return response()->json(['success' => true, 'message' => '公告已更新']);
    }

    public function destroy(int $id): JsonResponse
    {
        $announcements = Cache::get(self::CACHE_KEY, []);
        $announcements = array_filter($announcements, fn($a) => $a['id'] !== $id);
        Cache::forever(self::CACHE_KEY, array_values($announcements));

        Log::info('[Announcement] Deleted', ['id' => $id]);

        return response()->json(['success' => true, 'message' => '公告已刪除']);
    }
}
