<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SeoController extends Controller
{
    public function metaIndex(): JsonResponse
    {
        $metas = Cache::get('seo_meta_tags', [
            ['id' => 1, 'page_key' => 'landing', 'title' => 'MiMeet - 台灣高端交友平台', 'description' => '透過誠信分數系統，找到真實可信賴的另一半', 'og_image' => ''],
            ['id' => 2, 'page_key' => 'login', 'title' => 'MiMeet - 登入', 'description' => '登入你的 MiMeet 帳號', 'og_image' => ''],
            ['id' => 3, 'page_key' => 'register', 'title' => 'MiMeet - 註冊', 'description' => '立即加入 MiMeet 交友平台', 'og_image' => ''],
        ]);
        return response()->json(['success' => true, 'data' => ['metas' => $metas]]);
    }

    public function metaUpdate(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:70',
            'description' => 'required|string|max:200',
            'og_image' => 'sometimes|string',
        ]);
        return response()->json(['success' => true, 'message' => 'SEO Meta 已更新']);
    }

    public function linkIndex(): JsonResponse
    {
        $links = Cache::get('seo_links', [
            ['id' => 1, 'slug' => 'ig-2024', 'target_url' => 'https://mimeet.tw/register', 'campaign' => 'Instagram廣告', 'click_count' => 342, 'register_count' => 28, 'is_active' => true, 'created_at' => now()->subDays(30)->toISOString()],
            ['id' => 2, 'slug' => 'fb-spring', 'target_url' => 'https://mimeet.tw/register', 'campaign' => 'Facebook春季活動', 'click_count' => 156, 'register_count' => 12, 'is_active' => true, 'created_at' => now()->subDays(15)->toISOString()],
        ]);
        return response()->json(['success' => true, 'data' => ['links' => $links]]);
    }

    public function linkStore(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => 'required|string|max:50',
            'target_url' => 'required|url',
            'campaign' => 'sometimes|string|max:100',
        ]);
        return response()->json(['success' => true, 'message' => '跳轉連結已建立', 'data' => [
            'link' => ['id' => rand(10,99), 'slug' => $request->slug, 'target_url' => $request->target_url, 'campaign' => $request->campaign, 'click_count' => 0, 'register_count' => 0, 'is_active' => true, 'created_at' => now()->toISOString()]
        ]], 201);
    }

    public function linkUpdate(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => true, 'message' => '連結已更新']);
    }

    public function linkDestroy(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'message' => '連結已刪除']);
    }

    public function linkStats(int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'total_clicks' => 342, 'total_registers' => 28, 'conversion_rate' => '8.19%',
            'daily_stats' => array_map(fn($i) => ['date' => now()->subDays($i)->format('Y-m-d'), 'clicks' => rand(5, 30), 'registers' => rand(0, 5)], range(0, 6))
        ]]);
    }

    // Public endpoint for /go/:slug redirect
    public function redirect(string $slug): \Illuminate\Http\RedirectResponse|JsonResponse
    {
        // In production, look up from DB. For now use cache/mock.
        $links = Cache::get('seo_links', []);
        $link = collect($links)->firstWhere('slug', $slug);
        if (!$link || !($link['is_active'] ?? false)) {
            return response()->json(['success' => false, 'message' => '連結不存在或已停用'], 404);
        }
        return redirect($link['target_url'], 302);
    }
}
