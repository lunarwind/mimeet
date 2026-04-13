<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\GdprService;
use App\Services\UserActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true, 'code' => 'USER_PROFILE', 'message' => 'OK',
            'data' => ['user' => $request->user()],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        if ($request->has('birth_date') || $request->has('gender')) {
            return response()->json(['success' => false, 'code' => 'IMMUTABLE_FIELD', 'message' => '生日與性別設定後無法修改。'], 422);
        }

        $request->validate([
            'nickname' => 'sometimes|string|max:20',
            'bio' => 'sometimes|string|max:500',
            'avatar_url' => 'sometimes|string',
            'height' => 'sometimes|integer|min:100|max:250',
            'location' => 'sometimes|string|max:50',
            'occupation' => 'sometimes|string|max:50',
            'education' => 'sometimes|string|in:high_school,bachelor,master,phd,other',
            'interests' => 'sometimes|array',
        ]);

        $user = $request->user();
        $fields = $request->only(['nickname', 'bio', 'avatar_url', 'height', 'location', 'occupation', 'education', 'interests']);
        $user->update($fields);

        UserActivityLogService::logProfileUpdate($user->id, array_keys($fields), $request);

        return response()->json([
            'success' => true, 'code' => 'PROFILE_UPDATED', 'message' => '個人資料已更新。',
            'data' => ['user' => $user->fresh()],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'gender' => 'sometimes|in:male,female',
            'age_min' => 'sometimes|integer|min:18',
            'age_max' => 'sometimes|integer|max:99',
            'location' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = User::where('status', 'active')
            ->where('id', '!=', $request->user()?->id);

        // Privacy: hide users who opted out of search
        $query->where(function ($q) {
            $q->whereNull('privacy_settings')
              ->orWhereRaw("JSON_EXTRACT(privacy_settings, '$.show_in_search') != 'false'");
        });

        if ($request->filled('gender')) $query->where('gender', $request->gender);
        if ($request->filled('location')) $query->where('location', 'like', "%{$request->location}%");

        $perPage = (int) $request->input('per_page', 20);
        $users = $query->orderByDesc('last_active_at')->paginate($perPage);

        return response()->json([
            'success' => true, 'code' => 'SEARCH_RESULTS', 'message' => 'OK',
            'data' => [
                'users' => $users->map(function (User $u) use ($request) {
                    return [
                        'id' => $u->id,
                        'nickname' => $u->nickname,
                        'gender' => $u->gender,
                        'age' => $u->birth_date ? $u->birth_date->age : null,
                        'avatar' => $u->avatar_url,
                        'location' => $u->location,
                        'membership_level' => $u->membership_level,
                        'credit_score' => $u->credit_score,
                        'email_verified' => (bool) $u->email_verified,
                        'phone_verified' => (bool) $u->phone_verified,
                        'advanced_verified' => (bool) ($u->advanced_verified ?? false),
                        'online_status' => $u->last_active_at && $u->last_active_at->gt(now()->subMinutes(5)) ? 'online' : 'offline',
                        'last_active_at' => $u->last_active_at?->toIso8601String(),
                        'is_favorited' => $request->user() ? \DB::table('user_follows')->where('follower_id', $request->user()->id)->where('following_id', $u->id)->exists() : false,
                    ];
                }),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'total_pages' => $users->lastPage(),
                ],
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['success' => false, 'code' => 404, 'message' => '找不到此用戶'], 404);
        }

        return response()->json([
            'success' => true, 'code' => 'USER_DETAIL', 'message' => 'OK',
            'data' => ['user' => [
                'id' => $user->id,
                'nickname' => $user->nickname,
                'gender' => $user->gender,
                'age' => $user->birth_date ? $user->birth_date->age : null,
                'avatar' => $user->avatar_url,
                'introduction' => $user->bio,
                'location' => $user->location,
                'height' => $user->height,
                'job' => $user->occupation,
                'education' => $user->education,
                'membership_level' => $user->membership_level,
                'credit_score' => $user->credit_score,
                'email_verified' => (bool) $user->email_verified,
                'phone_verified' => (bool) $user->phone_verified,
                'advanced_verified' => (bool) ($user->advanced_verified ?? false),
                'online_status' => $user->last_active_at && $user->last_active_at->gt(now()->subMinutes(5)) ? 'online' : 'offline',
                'last_active_at' => $user->last_active_at?->toIso8601String(),
                'is_favorited' => $request->user() ? \DB::table('user_follows')->where('follower_id', $request->user()->id)->where('following_id', $user->id)->exists() : false,
                'is_blocked' => $request->user() ? \DB::table('user_blocks')->where('blocker_id', $request->user()->id)->where('blocked_id', $user->id)->exists() : false,
                'photos' => [],
                'created_at' => $user->created_at?->toIso8601String(),
            ]],
        ]);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => 'required|image|mimes:jpeg,png,gif,webp|max:5120']);

        // S13-10: Magic bytes validation (not just extension)
        $file = $request->file('photo');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file->getRealPath());
        finfo_close($finfo);
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($realMime, $allowed, true)) {
            return response()->json([
                'success' => false, 'code' => 422, 'message' => '檔案格式不合法（偽裝 MIME 偵測）',
            ], 422);
        }

        $path = Storage::disk('public')->put('photos/' . $request->user()->id, $file);

        UserActivityLogService::logPhotoChange($request->user()->id, 'upload', $request);

        return response()->json([
            'success' => true, 'code' => 'PHOTO_UPLOADED', 'message' => '照片已上傳。',
            'data' => ['photo' => [
                'url' => Storage::disk('public')->url($path),
                'is_primary' => $request->boolean('is_primary', false),
                'created_at' => now()->toISOString(),
            ]],
        ], 201);
    }

    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'success' => true, 'code' => 'USER_SETTINGS', 'message' => 'OK',
            'data' => [
                'privacy' => $user->privacy_settings,
                'account' => [
                    'email_verified' => $user->email_verified,
                    'phone_verified' => $user->phone_verified,
                ],
            ],
        ]);
    }

    public function following(Request $request): JsonResponse
    {
        // TODO: implement with user_follows table
        return response()->json([
            'success' => true, 'code' => 'FOLLOWING_LIST', 'message' => 'OK',
            'data' => ['users' => [], 'pagination' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1]],
        ]);
    }

    public function visitors(Request $request): JsonResponse
    {
        // TODO: implement with user_visitors table
        return response()->json([
            'success' => true, 'code' => 'VISITORS_LIST', 'message' => 'OK',
            'data' => ['visitors' => [], 'pagination' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1]],
        ]);
    }

    public function follow(Request $request, int $id): JsonResponse
    {
        // TODO: implement with user_follows table
        return response()->json(['success' => true, 'code' => 'FOLLOWED', 'message' => '已追蹤。']);
    }

    public function unfollow(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => true, 'code' => 'UNFOLLOWED', 'message' => '已取消追蹤。']);
    }

    public function block(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => true, 'code' => 'BLOCKED', 'message' => '已封鎖該用戶。']);
    }

    public function unblock(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => true, 'code' => 'UNBLOCKED', 'message' => '已解除封鎖。']);
    }

    public function blockedUsers(Request $request): JsonResponse
    {
        // TODO: implement with user_blocks table
        return response()->json([
            'success' => true, 'code' => 'BLOCKED_USERS', 'message' => 'OK',
            'data' => ['users' => [], 'pagination' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1]],
        ]);
    }
}
