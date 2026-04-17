<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserFollow;
use App\Models\UserProfileVisit;
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

        // Exclude blocked users (both directions)
        if ($request->user()) {
            $myId = $request->user()->id;
            $blockedIds = UserBlock::where('blocker_id', $myId)->pluck('blocked_id');
            $blockerIds = UserBlock::where('blocked_id', $myId)->pluck('blocker_id');
            $excludeIds = $blockedIds->merge($blockerIds)->unique();
            if ($excludeIds->isNotEmpty()) {
                $query->whereNotIn('id', $excludeIds);
            }
        }

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

        // Check if target user has blocked me
        if ($request->user()) {
            $amBlocked = UserBlock::where('blocker_id', $id)
                ->where('blocked_id', $request->user()->id)
                ->exists();
            if ($amBlocked) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => '2031', 'message' => '無法查看此用戶'],
                ], 403);
            }
        }

        // Record profile visit
        if ($request->user() && $request->user()->id !== $id) {
            $privacy = $request->user()->privacy_settings;
            $stealthMode = is_array($privacy) && ($privacy['stealth_mode'] ?? false);

            if (!$stealthMode) {
                UserProfileVisit::updateOrCreate(
                    ['visitor_id' => $request->user()->id, 'visited_user_id' => $id],
                    ['created_at' => now()],
                );
            }
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
                'is_blocked' => $request->user() ? UserBlock::where('blocker_id', $request->user()->id)->where('blocked_id', $user->id)->exists() : false,
                'photos' => [],
                'created_at' => $user->created_at?->toIso8601String(),
            ]],
        ]);
    }

    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => 'required|image|mimes:jpeg,png,gif,webp|max:5120']);

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

    public function getAvatarSlots(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'data' => [
                'current_avatar' => $user->avatar_url,
                'slots' => $user->avatar_slots ?? [],
            ],
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,webp|max:5120',
            'set_active' => 'sometimes|boolean',
        ]);

        $user = $request->user();
        $slots = $user->avatar_slots ?? [];

        if (count($slots) >= 3) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AVATAR_SLOTS_FULL', 'message' => '頭像槽位已滿，請先刪除一個'],
            ], 422);
        }

        $path = Storage::disk('public')->put('avatars/' . $user->id, $request->file('photo'));
        $url = Storage::disk('public')->url($path);

        $slots[] = $url;
        $user->avatar_slots = $slots;

        if ($request->boolean('set_active', true)) {
            $user->avatar_url = $url;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'data' => ['url' => $url, 'current_avatar' => $user->avatar_url, 'slots' => $user->avatar_slots],
        ], 201);
    }

    public function setActiveAvatar(Request $request): JsonResponse
    {
        $request->validate(['url' => 'required|string']);

        $user = $request->user();
        $slots = $user->avatar_slots ?? [];

        if (!in_array($request->url, $slots)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'AVATAR_NOT_FOUND', 'message' => '頭像不在槽位中'],
            ], 422);
        }

        $user->avatar_url = $request->url;
        $user->save();

        return response()->json([
            'success' => true,
            'data' => ['current_avatar' => $user->avatar_url],
        ]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $request->validate(['url' => 'required|string']);

        $user = $request->user();
        $slots = $user->avatar_slots ?? [];

        if ($user->avatar_url === $request->url && count($slots) <= 1) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CANNOT_DELETE_ACTIVE', 'message' => '無法刪除使用中的唯一頭像'],
            ], 422);
        }

        $slots = array_values(array_filter($slots, fn($s) => $s !== $request->url));
        $user->avatar_slots = $slots;

        if ($user->avatar_url === $request->url && count($slots) > 0) {
            $user->avatar_url = $slots[0];
        }

        $user->save();

        return response()->json([
            'success' => true,
            'data' => ['current_avatar' => $user->avatar_url, 'slots' => $user->avatar_slots],
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true, 'code' => 'USER_SETTINGS', 'message' => 'OK',
            'data' => [
                'profile' => [
                    'id' => $user->id,
                    'nickname' => $user->nickname ?? '',
                    'gender' => $user->gender ?? '',
                    'birth_date' => $user->birth_date?->toDateString(),
                    'avatar_url' => $user->avatar_url,
                    'city' => $user->location ?? '',
                    'height' => $user->height,
                    'weight' => null,
                    'job' => $user->occupation ?? '',
                    'education' => $user->education ?? '',
                    'introduction' => $user->bio ?? '',
                ],
                'account' => [
                    'email' => $user->email,
                    'email_verified' => (bool) $user->email_verified,
                    'phone_verified' => (bool) $user->phone_verified,
                ],
                'verification' => [
                    'membership_level' => (float) $user->membership_level,
                ],
                'privacy_settings' => $user->privacy_settings ?? [
                    'show_online_status' => true,
                    'allow_profile_visits' => true,
                    'show_in_search' => true,
                    'show_last_active' => true,
                    'allow_stranger_message' => true,
                ],
            ],
        ]);
    }

    public function following(Request $request): JsonResponse
    {
        $query = UserFollow::where('follower_id', $request->user()->id)
            ->join('users', 'users.id', '=', 'user_follows.following_id')
            ->select(
                'users.id', 'users.nickname', 'users.avatar_url',
                'users.credit_score', 'users.last_active_at',
                'user_follows.created_at as followed_at'
            );

        if ($request->filled('nickname')) {
            $query->where('users.nickname', 'like', '%' . $request->input('nickname') . '%');
        }

        $perPage = (int) $request->input('per_page', 20);
        $paginated = $query->orderByDesc('user_follows.created_at')->paginate($perPage);

        return response()->json([
            'success' => true, 'code' => 'FOLLOWING_LIST', 'message' => 'OK',
            'data' => ['users' => $paginated->items()],
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    public function visitors(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $since = now()->subDays(90);

        $query = UserProfileVisit::where('user_profile_visits.visited_user_id', $userId)
            ->where('user_profile_visits.created_at', '>=', $since)
            ->join('users', 'users.id', '=', 'user_profile_visits.visitor_id')
            ->select(
                'users.id', 'users.nickname', 'users.avatar_url',
                'users.birth_date', 'users.credit_score',
                'user_profile_visits.created_at as visited_at'
            )
            ->orderByDesc('user_profile_visits.created_at');

        $perPage = (int) $request->input('per_page', 20);
        $paginated = $query->paginate($perPage);

        $totalDistinct = UserProfileVisit::where('visited_user_id', $userId)
            ->where('created_at', '>=', $since)
            ->distinct('visitor_id')
            ->count('visitor_id');

        $visitors = collect($paginated->items())->map(function ($v) {
            $visitedAt = \Carbon\Carbon::parse($v->visited_at);
            $diffMinutes = (int) now()->diffInMinutes($visitedAt);
            $diffHours = (int) now()->diffInHours($visitedAt);

            if ($diffMinutes < 60) {
                $human = "{$diffMinutes} 分鐘前";
            } elseif ($diffHours < 24) {
                $human = "{$diffHours} 小時前";
            } elseif ($visitedAt->isYesterday()) {
                $human = '昨天';
            } else {
                $human = $visitedAt->format('n月j日');
            }

            return [
                'id' => $v->id,
                'nickname' => $v->nickname,
                'avatar_url' => $v->avatar_url,
                'age' => $v->birth_date ? \Carbon\Carbon::parse($v->birth_date)->age : null,
                'credit_score' => $v->credit_score,
                'visited_at' => $visitedAt->toIso8601String(),
                'visited_at_human' => $human,
            ];
        });

        return response()->json([
            'success' => true, 'code' => 'VISITORS_LIST', 'message' => 'OK',
            'data' => [
                'visitors' => $visitors,
                'total_visitors_90days' => $totalDistinct,
            ],
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    public function follow(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        if ($userId === $id) {
            return response()->json([
                'success' => false,
                'error' => ['code' => '2040', 'message' => '不能收藏自己'],
            ], 422);
        }

        if (!User::where('id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => '404', 'message' => '找不到此用戶'],
            ], 404);
        }

        $count = UserFollow::where('follower_id', $userId)->count();
        if ($count >= 500) {
            return response()->json([
                'success' => false,
                'error' => ['code' => '2041', 'message' => '收藏已達上限（500人）'],
            ], 422);
        }

        UserFollow::firstOrCreate([
            'follower_id' => $userId,
            'following_id' => $id,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['followed' => true],
        ], 201);
    }

    public function unfollow(Request $request, int $id): JsonResponse
    {
        UserFollow::where('follower_id', $request->user()->id)
            ->where('following_id', $id)
            ->delete();

        return response()->json([
            'success' => true,
            'data' => ['followed' => false],
        ]);
    }

    public function block(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        if ($userId === $id) {
            return response()->json([
                'success' => false,
                'error' => ['code' => '2030', 'message' => '不能封鎖自己'],
            ], 422);
        }

        if (!User::where('id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => ['code' => '404', 'message' => '找不到此用戶'],
            ], 404);
        }

        UserBlock::firstOrCreate([
            'blocker_id' => $userId,
            'blocked_id' => $id,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['blocked' => true],
        ], 201);
    }

    public function unblock(Request $request, int $id): JsonResponse
    {
        UserBlock::where('blocker_id', $request->user()->id)
            ->where('blocked_id', $id)
            ->delete();

        return response()->json([
            'success' => true,
            'data' => ['blocked' => false],
        ]);
    }

    public function blockedUsers(Request $request): JsonResponse
    {
        $blocks = UserBlock::where('blocker_id', $request->user()->id)
            ->join('users', 'users.id', '=', 'user_blocks.blocked_id')
            ->select('users.id', 'users.nickname', 'users.avatar_url as avatar', 'user_blocks.created_at as blocked_at')
            ->orderByDesc('user_blocks.created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['blocked_users' => $blocks],
        ]);
    }
}
