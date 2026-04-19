<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeoController extends Controller
{
    /**
     * GET /api/v1/admin/seo/meta — list all page meta entries
     * 對應 A17 SEO Meta Tag 管理（API-002 §9.4）
     */
    public function metaIndex(): JsonResponse
    {
        $metas = SeoMeta::orderBy('id')->get();

        return response()->json([
            'success' => true,
            'data' => $metas,
        ]);
    }

    /**
     * PATCH /api/v1/admin/seo/meta/{id} — update single page meta (partial)
     * 對應 A17 SEO Meta Tag 管理（API-002 §9.5）
     */
    public function metaUpdate(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'title'          => 'sometimes|required|string|max:70',
            'description'    => 'sometimes|required|string|max:200',
            'og_title'       => 'sometimes|nullable|string|max:70',
            'og_description' => 'sometimes|nullable|string|max:200',
            'og_image_url'   => 'sometimes|nullable|url|max:500',
        ]);

        $meta = SeoMeta::findOrFail($id);
        $meta->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'SEO Meta 已更新',
            'data' => $meta->fresh(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // [Phase 2] A18 廣告跳轉連結管理
    // 以下方法保留為 Phase 2 骨架，尚未實作。
    // 啟用時需補：
    //   - seo_links / seo_click_logs migrations + models
    //   - 路由註冊（見 routes/api.php）
    //   - 前端 SeoPage 廣告連結 tab 解除隱藏
    //   - 前台 /go/{slug} 公開路由 (redirect)
    // 規格：docs/API-002_後台管理API規格書.md §9.1–9.3
    // ─────────────────────────────────────────────────────────────

    // public function linkIndex(): JsonResponse { ... }
    // public function linkStore(Request $request): JsonResponse { ... }
    // public function linkUpdate(Request $request, int $id): JsonResponse { ... }
    // public function linkDestroy(int $id): JsonResponse { ... }
    // public function linkStats(int $id): JsonResponse { ... }
    // public function redirect(string $slug) { ... }
}
