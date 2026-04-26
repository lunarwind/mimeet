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
            'bio' => 'sometimes|nullable|string|max:500',
            'avatar_url' => 'sometimes|nullable|string',
            'height' => 'sometimes|nullable|integer|min:100|max:250',
            'weight' => 'sometimes|nullable|integer|min:30|max:200',
            'location' => 'sometimes|nullable|string|max:50',
            'occupation' => 'sometimes|nullable|string|max:50',
            'education' => 'sometimes|nullable|string|in:high_school,associate,bachelor,master,phd,other',
            'interests' => 'sometimes|nullable|array',

            // F27 新增的 9 個 profile 欄位 — 全部選填
            'style'             => 'sometimes|nullable|string|in:fresh,sweet,sexy,intellectual,sporty',
            'dating_budget'     => 'sometimes|nullable|string|in:casual,moderate,generous,luxury,undisclosed',
            'dating_frequency'  => 'sometimes|nullable|string|in:occasional,weekly,flexible',
            'dating_type'       => 'sometimes|nullable|array',
            'dating_type.*'     => 'string|in:dining,travel,companion,mentorship,undisclosed',
            'relationship_goal' => 'sometimes|nullable|string|in:short_term,long_term,open,undisclosed',
            'smoking'           => 'sometimes|nullable|string|in:never,sometimes,often',
            'drinking'          => 'sometimes|nullable|string|in:never,social,often',
            'car_owner'         => 'sometimes|nullable|boolean',
            'availability'      => 'sometimes|nullable|array',
            'availability.*'    => 'string|in:weekday_day,weekday_night,weekend,flexible',
        ]);

        $user = $request->user();
        $fields = $request->only([
            'nickname', 'bio', 'avatar_url', 'height', 'weight', 'location', 'occupation', 'education', 'interests',
            'style', 'dating_budget', 'dating_frequency', 'dating_type', 'relationship_goal',
            'smoking', 'drinking', 'car_owner', 'availability',
        ]);
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
            'gender'           => 'sometimes|in:male,female',
            'age_min'          => 'sometimes|integer|min:18',
            'age_max'          => 'sometimes|integer|max:99',
            'location'         => 'sometimes|string',
            'min_height'       => 'sometimes|integer|min:100',
            'max_height'       => 'sometimes|integer|max:250',
            'min_weight'       => 'sometimes|integer|min:30',
            'max_weight'       => 'sometimes|integer|max:200',
            'education'        => 'sometimes|string',
            'occupation'       => 'sometimes|string',
            'style'            => 'sometimes|string',
            'dating_budget'    => 'sometimes|string',
            'dating_frequency' => 'sometimes|string',
            'dating_type'      => 'sometimes|string',
            'relationship_goal' => 'sometimes|string',
            'smoking'          => 'sometimes|string',
            'drinking'         => 'sometimes|string',
            'car_owner'        => 'sometimes|boolean',
            'availability'     => 'sometimes|string',
            'min_credit'       => 'sometimes|integer|min:0',
            'max_credit'       => 'sometimes|integer|max:100',
            'credit_score_min' => 'sometimes|integer|min:0',  // legacy alias
            'credit_score_max' => 'sometimes|integer|max:100', // legacy alias
            'verified_only'    => 'sometimes|boolean',
            'per_page'         => 'sometimes|integer|min:1|max:50',
            'last_online'      => 'sometimes|string|in:today,3days,7days',
        ]);

        $query = User::where('status', 'active')
            ->where('id', '!=', $request->user()?->id);

        // 預設只顯示 30 天內有活動的用戶（含從未登入者保留）
        $query->where(function ($q) {
            $q->whereNull('last_active_at')
              ->orWhere('last_active_at', '>=', now()->subDays(30));
        });

        // Privacy: hide users who opted out of search
        $query->where(function ($q) {
            $q->whereNull('privacy_settings')
              ->orWhereRaw("JSON_EXTRACT(privacy_settings, '$.show_in_search') != 'false'");
        });

        // F42 隱身模式：stealth_until 未來時間的用戶不顯示（獨立於 privacy_settings）
        $query->where(function ($q) {
            $q->whereNull('stealth_until')
              ->orWhere('stealth_until', '<=', now());
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

        // ── 性別（精確）──
        if ($request->filled('gender')) $query->where('gender', $request->gender);

        // ── 僅顯示已驗證用戶（email + phone 均已驗證）──
        if ($request->boolean('verified_only')) {
            $query->where(function ($q) {
                $q->where('email_verified', true)
                  ->where('phone_verified', true);
            });
        }

        // ── 年齡：基於 birth_date 計算，未填生日者 nullable 放過 ──
        if ($request->filled('age_min')) {
            $maxDate = now()->subYears((int) $request->age_min)->toDateString();
            $query->where(function ($q) use ($maxDate) {
                $q->where('birth_date', '<=', $maxDate)->orWhereNull('birth_date');
            });
        }
        if ($request->filled('age_max')) {
            $minDate = now()->subYears(((int) $request->age_max) + 1)->toDateString();
            $query->where(function ($q) use ($minDate) {
                $q->where('birth_date', '>=', $minDate)->orWhereNull('birth_date');
            });
        }

        // ── 數值範圍：未填寫（NULL）不排除 ──
        // credit_score_min/max 舊稱同時支援（相容現有前端）
        $minCredit = $request->input('min_credit', $request->input('credit_score_min'));
        $maxCredit = $request->input('max_credit', $request->input('credit_score_max'));

        foreach ([
            ['min_height', 'max_height', 'height', $request->input('min_height'), $request->input('max_height')],
            ['min_weight', 'max_weight', 'weight', $request->input('min_weight'), $request->input('max_weight')],
            ['min_credit', 'max_credit', 'credit_score', $minCredit, $maxCredit],
        ] as [$minKey, $maxKey, $column, $minVal, $maxVal]) {
            if ($minVal !== null || $maxVal !== null) {
                $query->where(function ($q) use ($column, $minVal, $maxVal) {
                    $q->where(function ($inner) use ($column, $minVal, $maxVal) {
                        if ($minVal !== null) $inner->where($column, '>=', (int) $minVal);
                        if ($maxVal !== null) $inner->where($column, '<=', (int) $maxVal);
                    })->orWhereNull($column);
                });
            }
        }

        // ── 精確比對（未填寫不排除）──
        foreach (['education', 'style', 'dating_budget', 'dating_frequency', 'relationship_goal', 'smoking', 'drinking'] as $field) {
            if ($request->filled($field)) {
                $val = $request->input($field);
                $query->where(function ($q) use ($field, $val) {
                    $q->where($field, $val)->orWhereNull($field);
                });
            }
        }

        // ── Boolean（car_owner）──
        if ($request->has('car_owner')) {
            $val = $request->boolean('car_owner');
            $query->where(function ($q) use ($val) {
                $q->where('car_owner', $val)->orWhereNull('car_owner');
            });
        }

        // ── JSON 複選（dating_type / availability）──
        foreach (['dating_type', 'availability'] as $jsonField) {
            if ($request->filled($jsonField)) {
                $value = $request->input($jsonField);
                $query->where(function ($q) use ($jsonField, $value) {
                    $q->whereRaw("JSON_CONTAINS({$jsonField}, ?)", [json_encode($value)])
                      ->orWhereNull($jsonField);
                });
            }
        }

        // ── 最後上線時間篩選（覆蓋預設 30 天，收窄至指定範圍）──
        if ($request->filled('last_online')) {
            $cutoff = match($request->input('last_online')) {
                'today' => now()->subDay(),
                '3days' => now()->subDays(3),
                '7days' => now()->subDays(7),
                default => null,
            };
            if ($cutoff) {
                $query->where(function ($q) use ($cutoff) {
                    $q->whereNull('last_active_at')
                      ->orWhere('last_active_at', '>=', $cutoff);
                });
            }
        }

        // ── 文字模糊（occupation / location）──
        foreach (['occupation', 'location'] as $field) {
            if ($request->filled($field)) {
                $val = $request->input($field);
                $query->where(function ($q) use ($field, $val) {
                    $q->where($field, 'LIKE', "%{$val}%")->orWhereNull($field);
                });
            }
        }

        // ── 排序：資料完整度優先、誠信分數、最後上線 ──
        $query->orderByRaw('(
            CASE WHEN height IS NOT NULL THEN 1 ELSE 0 END +
            CASE WHEN dating_budget IS NOT NULL THEN 1 ELSE 0 END +
            CASE WHEN style IS NOT NULL THEN 1 ELSE 0 END +
            CASE WHEN bio IS NOT NULL AND bio != "" THEN 1 ELSE 0 END
        ) DESC');
        $query->orderByDesc('credit_score')->orderByDesc('last_active_at');

        $perPage = (int) $request->input('per_page', 20);
        $users = $query->paginate($perPage);

        // 批次查詢 is_favorited，避免 N+1（每頁一次 whereIn 取代 N 次 exists）
        $favoritedIds = collect();
        if ($request->user()) {
            $favoritedIds = \DB::table('user_follows')
                ->where('follower_id', $request->user()->id)
                ->whereIn('following_id', $users->pluck('id'))
                ->pluck('following_id');
        }

        return response()->json([
            'success' => true, 'code' => 'SEARCH_RESULTS', 'message' => 'OK',
            'data' => [
                'users' => $users->map(function (User $u) use ($favoritedIds) {
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
                        'is_favorited' => $favoritedIds->contains($u->id),
                    ];
                }),
            ],
            'meta' => [
                'page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
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
            // F42 隱身模式：瀏覽者處於隱身狀態時不留訪客記錄
            $isStealthActive = $request->user()->isStealthActive();

            if (!$stealthMode && !$isStealthActive) {
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
                'weight' => $user->weight,
                'job' => $user->occupation,
                'education' => $user->education,
                // F27 profile fields
                'style' => $user->style,
                'dating_budget' => $user->dating_budget,
                'dating_frequency' => $user->dating_frequency,
                'dating_type' => $user->dating_type,
                'relationship_goal' => $user->relationship_goal,
                'smoking' => $user->smoking,
                'drinking' => $user->drinking,
                'car_owner' => $user->car_owner,
                'availability' => $user->availability,
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
                    'weight' => $user->weight,
                    'job' => $user->occupation ?? '',
                    'education' => $user->education ?? '',
                    'introduction' => $user->bio ?? '',
                    // F27 profile fields
                    'style' => $user->style,
                    'dating_budget' => $user->dating_budget,
                    'dating_frequency' => $user->dating_frequency,
                    'dating_type' => $user->dating_type ?? [],
                    'relationship_goal' => $user->relationship_goal,
                    'smoking' => $user->smoking,
                    'drinking' => $user->drinking,
                    'car_owner' => $user->car_owner,
                    'availability' => $user->availability ?? [],
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
                'membership' => $this->buildMembershipData($user),
            ],
        ]);
    }

    private function buildMembershipData(\App\Models\User $user): array
    {
        $sub = \App\Models\Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->with('plan')
            ->first();

        if (!$sub) {
            return ['is_paid' => false, 'expires_at' => null, 'days_remaining' => 0];
        }

        return [
            'is_paid' => true,
            'plan_name' => $sub->plan?->name,
            'expires_at' => $sub->expires_at->toISOString(),
            'days_remaining' => max(0, (int) now()->startOfDay()->diffInDays($sub->expires_at, false)),
        ];
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
            'data' => $paginated->items(),
            'meta' => [
                'page' => $paginated->currentPage(),
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
            'meta' => [
                'page' => $paginated->currentPage(),
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
