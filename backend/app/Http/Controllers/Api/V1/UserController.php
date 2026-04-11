<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
<<<<<<< HEAD
use App\Models\UserVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
=======
use App\Models\User;
use App\Services\GdprService;
use App\Services\UserActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
>>>>>>> develop

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
                'users' => $users->map(fn (User $u) => [
                    'id' => $u->id, 'nickname' => $u->nickname, 'gender' => $u->gender,
                    'avatar_url' => $u->avatar_url, 'location' => $u->location,
                    'membership_level' => $u->membership_level, 'credit_score' => $u->credit_score,
                ]),
                'pagination' => [
                    'current_page' => $users->currentPage(), 'per_page' => $users->perPage(),
                    'total' => $users->total(), 'last_page' => $users->lastPage(),
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

        // Record profile visit (don't record self-visits)
        $viewerId = $request->user()?->id;
        if ($viewerId && $viewerId !== $id) {
            \Illuminate\Support\Facades\DB::table('user_profile_visits')->insert([
                'visitor_id' => $viewerId,
                'visited_user_id' => $id,
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true, 'code' => 'USER_DETAIL', 'message' => 'OK',
            'data' => ['user' => [
                'id' => $user->id, 'nickname' => $user->nickname, 'gender' => $user->gender,
                'avatar_url' => $user->avatar_url, 'bio' => $user->bio, 'location' => $user->location,
                'occupation' => $user->occupation, 'education' => $user->education,
                'interests' => $user->interests, 'membership_level' => $user->membership_level,
                'credit_score' => $user->credit_score,
                'email_verified' => $user->email_verified, 'phone_verified' => $user->phone_verified,
            ]],
        ]);
    }

<<<<<<< HEAD
    /**
     * Get a user profile by ID.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $mockUser = [
            'id' => $id,
            'nickname' => 'MockUser',
            'gender' => 'female',
            'age' => 25,
            'birth_date' => '1999-03-15',
            'avatar_url' => null,
            'bio' => '這是一個測試用戶。',
            'photos' => [],
            'location' => '台北',
            'occupation' => '工程師',
            'education' => 'bachelor',
            'interests' => ['旅行', '攝影', '美食'],
            'membership_level' => 1,
            'is_following' => false,
            'is_blocked' => false,
        ];

        return response()->json([
            'success' => true,
            'code' => 'USER_DETAIL',
            'message' => 'OK',
            'data' => [
                'user' => $mockUser,
            ],
        ]);
    }

    /**
     * Upload a photo for the current user.
     * Stores on private disk; serves via signed URL.
     */
=======
>>>>>>> develop
    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => 'required|image|mimes:jpeg,png,gif,webp|max:5120']);

<<<<<<< HEAD
        $file = $request->file('photo');

        // Magic bytes validation — reject files whose bytes don't match declared type
        if (!$this->validateMagicBytes($file)) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_FILE_TYPE',
                'message' => '檔案格式驗證失敗，僅支援 JPEG、PNG、WebP。',
            ], 422);
        }

        $user = $request->user();
        $path = $file->store("users/{$user->id}/photos", 'private');

        $signedUrl = URL::temporarySignedRoute(
            'media.serve',
            now()->addMinutes(60),
            ['path' => $path],
        );

        return response()->json([
            'success' => true,
            'code' => 'PHOTO_UPLOADED',
            'message' => '照片已上傳，審核中。',
            'data' => [
                'photo' => [
                    'id' => 'photo_' . Str::random(12),
                    'url' => $signedUrl,
                    'path' => $path,
                    'is_primary' => $request->boolean('is_primary', false),
                    'status' => 'pending_review',
                    'created_at' => now()->toISOString(),
                ],
            ],
        ], 201);
    }

    /**
     * Serve a private media file via signed URL.
     */
    public function serveMedia(Request $request, string $path): \Symfony\Component\HttpFoundation\Response
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired signature.');
        }

        $disk = Storage::disk('private');

        if (!$disk->exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->file($disk->path($path));
    }

    /**
     * Validate file magic bytes to ensure actual file type matches.
     * Supports JPEG, PNG, and WebP.
     */
    private function validateMagicBytes($file): bool
    {
        $handle = fopen($file->getPathname(), 'rb');
        $bytes = fread($handle, 8);
        fclose($handle);

        $hex = bin2hex($bytes);

        // JPEG: FF D8 FF
        if (str_starts_with($hex, 'ffd8ff')) return true;
        // PNG: 89 50 4E 47
        if (str_starts_with($hex, '89504e47')) return true;
        // WebP: 52 49 46 46 ... 57 45 42 50 (bytes 8-11 are file size, bytes 8+ contain WEBP)
        if (str_starts_with($hex, '52494646') && strlen($hex) >= 24 && substr($hex, 16, 8) === '57454250') return true;

        return false;
    }

    /**
     * Get user settings page data.
     */
=======
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

>>>>>>> develop
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
        $userId = $request->user()->id;
        $follows = \Illuminate\Support\Facades\DB::table('user_follows')
            ->where('follower_id', $userId)
            ->join('users', 'users.id', '=', 'user_follows.following_id')
            ->select('users.id', 'users.nickname', 'users.avatar_url', 'users.credit_score', 'users.last_active_at', 'user_follows.created_at as followed_at')
            ->orderByDesc('user_follows.created_at')
            ->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true, 'code' => 'FOLLOWING_LIST', 'message' => 'OK',
            'data' => ['users' => $follows->items()],
            'pagination' => ['current_page' => $follows->currentPage(), 'per_page' => $follows->perPage(), 'total' => $follows->total()],
        ]);
    }

    public function visitors(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $visitors = \Illuminate\Support\Facades\DB::table('user_profile_visits')
            ->where('visited_user_id', $userId)
            ->join('users', 'users.id', '=', 'user_profile_visits.visitor_id')
            ->select('users.id', 'users.nickname', 'users.avatar_url', 'users.credit_score', 'user_profile_visits.created_at as visited_at')
            ->orderByDesc('user_profile_visits.created_at')
            ->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true, 'code' => 'VISITORS_LIST', 'message' => 'OK',
            'data' => ['visitors' => $visitors->items()],
            'pagination' => ['current_page' => $visitors->currentPage(), 'per_page' => $visitors->perPage(), 'total' => $visitors->total()],
        ]);
    }

    public function follow(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        if ($userId === $id) {
            return response()->json(['success' => false, 'message' => '不能追蹤自己'], 422);
        }
        \Illuminate\Support\Facades\DB::table('user_follows')->updateOrInsert(
            ['follower_id' => $userId, 'following_id' => $id],
            ['created_at' => now()]
        );
        return response()->json(['success' => true, 'code' => 'FOLLOWED', 'message' => '已追蹤。']);
    }

    public function unfollow(Request $request, int $id): JsonResponse
    {
        \Illuminate\Support\Facades\DB::table('user_follows')
            ->where('follower_id', $request->user()->id)
            ->where('following_id', $id)
            ->delete();
        return response()->json(['success' => true, 'code' => 'UNFOLLOWED', 'message' => '已取消追蹤。']);
    }

    public function block(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        if ($userId === $id) {
            return response()->json(['success' => false, 'message' => '不能封鎖自己'], 422);
        }
        \Illuminate\Support\Facades\DB::table('user_blocks')->updateOrInsert(
            ['blocker_id' => $userId, 'blocked_id' => $id],
            ['created_at' => now()]
        );
        // Also unfollow both directions
        \Illuminate\Support\Facades\DB::table('user_follows')
            ->where(fn ($q) => $q->where(['follower_id' => $userId, 'following_id' => $id])->orWhere(['follower_id' => $id, 'following_id' => $userId]))
            ->delete();
        return response()->json(['success' => true, 'code' => 'BLOCKED', 'message' => '已封鎖該用戶。']);
    }

    public function unblock(Request $request, int $id): JsonResponse
    {
        \Illuminate\Support\Facades\DB::table('user_blocks')
            ->where('blocker_id', $request->user()->id)
            ->where('blocked_id', $id)
            ->delete();
        return response()->json(['success' => true, 'code' => 'UNBLOCKED', 'message' => '已解除封鎖。']);
    }

    public function blockedUsers(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $blocked = \Illuminate\Support\Facades\DB::table('user_blocks')
            ->where('blocker_id', $userId)
            ->join('users', 'users.id', '=', 'user_blocks.blocked_id')
            ->select('users.id', 'users.nickname', 'users.avatar_url', 'user_blocks.created_at as blocked_at')
            ->orderByDesc('user_blocks.created_at')
            ->paginate($request->query('per_page', 20));

        return response()->json([
            'success' => true, 'code' => 'BLOCKED_USERS', 'message' => 'OK',
            'data' => ['users' => $blocked->items()],
            'pagination' => ['current_page' => $blocked->currentPage(), 'per_page' => $blocked->perPage(), 'total' => $blocked->total()],
        ]);
    }

    /**
     * Get current user's verification status.
     */
    public function verificationStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        // Fetch the latest pending or approved verification
        $pending = UserVerification::where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest('submitted_at')
            ->first();

        $approved = UserVerification::where('user_id', $user->id)
            ->where('status', 'approved')
            ->latest('reviewed_at')
            ->first();

        return response()->json([
            'success' => true,
            'code' => 'VERIFICATION_STATUS',
            'message' => 'OK',
            'data' => [
                'membership_level' => $user->membership_level ?? 0,
                'pending_verification' => $pending ? [
                    'id' => $pending->id,
                    'type' => $pending->type,
                    'status' => $pending->status,
                    'submitted_at' => $pending->submitted_at?->toISOString(),
                ] : null,
                'last_approved' => $approved ? [
                    'id' => $approved->id,
                    'type' => $approved->type,
                    'reviewed_at' => $approved->reviewed_at?->toISOString(),
                ] : null,
            ],
        ]);
    }

    /**
     * Generate a 6-digit verification photo code, cached for 10 minutes.
     */
    public function verificationPhotoCode(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "verification_photo_code:{$user->id}";

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        Cache::put($cacheKey, $code, $expiresAt);

        return response()->json([
            'success' => true,
            'code' => 'PHOTO_CODE_GENERATED',
            'message' => 'OK',
            'data' => [
                'code' => $code,
                'expires_at' => $expiresAt->toISOString(),
            ],
        ]);
    }

    /**
     * Apply for advanced verification (photo or card).
     */
    public function verificationApply(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:photo,card',
            'photo_url' => 'required_if:type,photo|string|url',
            'photo_code' => 'required_if:type,photo|string|size:6',
        ]);

        $user = $request->user();

        // Check for existing pending verification
        $existing = UserVerification::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'code' => 'VERIFICATION_PENDING',
                'message' => '您已有一筆待審核的驗證申請。',
            ], 422);
        }

        // For photo type, validate the photo code against cache
        if ($request->type === 'photo') {
            $cacheKey = "verification_photo_code:{$user->id}";
            $cachedCode = Cache::get($cacheKey);

            if (!$cachedCode || $cachedCode !== $request->photo_code) {
                return response()->json([
                    'success' => false,
                    'code' => 'INVALID_PHOTO_CODE',
                    'message' => '驗證碼無效或已過期，請重新取得。',
                ], 422);
            }

            // Clear the code after use
            Cache::forget($cacheKey);
        }

        $verification = UserVerification::create([
            'user_id' => $user->id,
            'type' => $request->type,
            'status' => 'pending',
            'photo_url' => $request->photo_url,
            'random_code' => $request->photo_code,
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'code' => 'VERIFICATION_APPLIED',
            'message' => '驗證申請已提交，審核通常在 24 小時內完成。',
            'data' => [
                'verification_id' => $verification->id,
                'status' => 'pending',
                'submitted_at' => $verification->submitted_at->toISOString(),
            ],
        ], 201);
    }
}
