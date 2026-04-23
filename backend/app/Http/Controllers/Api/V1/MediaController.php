<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    private const STORAGE_PATHS = [
        'avatar'        => 'avatars',
        'profile_photo' => 'photos',
        'report_image'  => 'report_images',
    ];

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file'    => 'required|image|mimes:jpeg,png,webp|max:5120',
            'context' => 'required|in:avatar,profile_photo,report_image',
        ]);

        $file = $request->file('file');

        // Magic bytes validation — guards against disguised file extensions
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file->getRealPath());
        finfo_close($finfo);

        if (!in_array($realMime, self::ALLOWED_MIMES, true)) {
            return response()->json([
                'success' => false,
                'code'    => 422,
                'message' => '檔案格式不合法（偽裝 MIME 偵測）',
            ], 422);
        }

        $context = $request->input('context');
        $dir     = self::STORAGE_PATHS[$context];

        // Namespace avatar/photo by user id to avoid collisions
        if ($context !== 'report_image') {
            $dir .= '/' . $request->user()->id;
        }

        $path = Storage::disk('public')->put($dir, $file);
        $url  = Storage::disk('public')->url($path);

        if ($context === 'avatar') {
            $user = $request->user();
            $user->avatar_url = $url;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'url'               => $url,
                'original_filename' => $file->getClientOriginalName(),
            ],
        ], 201);
    }

    public function delete(Request $request): JsonResponse
    {
        $request->validate(['url' => 'required|string|url']);

        $url      = $request->input('url');
        $baseUrl  = Storage::disk('public')->url('');
        $path     = str_replace($baseUrl, '', $url);
        $segments = explode('/', ltrim($path, '/'));
        $userId   = $request->user()->id;

        // Ownership check: avatars/{id}/... and photos/{id}/... must match the user
        if (in_array($segments[0] ?? '', ['avatars', 'photos'])) {
            if (($segments[1] ?? null) !== (string) $userId) {
                return response()->json(['success' => false, 'message' => '無權限刪除此檔案'], 403);
            }
        } elseif (($segments[0] ?? '') !== 'report_images') {
            return response()->json(['success' => false, 'message' => '不允許的路徑'], 403);
        }

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['success' => false, 'message' => '檔案不存在'], 404);
        }

        Storage::disk('public')->delete($path);

        return response()->json(['success' => true, 'message' => '檔案已刪除']);
    }
}
