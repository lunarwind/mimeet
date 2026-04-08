<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Get current user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'code' => 'USER_PROFILE',
            'message' => 'OK',
            'data' => [
                'user' => $user,
            ],
        ]);
    }

    /**
     * Update current user profile.
     */
    public function update(Request $request): JsonResponse
    {
        // Reject immutable fields
        if ($request->has('birth_date') || $request->has('gender')) {
            return response()->json([
                'success' => false,
                'code' => 'IMMUTABLE_FIELD',
                'message' => '生日與性別設定後無法修改。',
            ], 422);
        }

        $request->validate([
            'nickname' => 'sometimes|string|max:20',
            'bio' => 'sometimes|string|max:500',
            'avatar_url' => 'sometimes|string|url',
            'height' => 'sometimes|integer|min:100|max:250',
            'location' => 'sometimes|string|max:50',
            'occupation' => 'sometimes|string|max:50',
            'education' => 'sometimes|string|in:high_school,bachelor,master,phd,other',
            'interests' => 'sometimes|array',
            'interests.*' => 'string|max:20',
        ]);

        // Mock: return updated user
        $user = $request->user();
        $updatedFields = $request->only([
            'nickname', 'bio', 'avatar_url', 'height',
            'location', 'occupation', 'education', 'interests',
        ]);

        return response()->json([
            'success' => true,
            'code' => 'PROFILE_UPDATED',
            'message' => '個人資料已更新。',
            'data' => [
                'user' => array_merge(
                    $user ? $user->toArray() : [],
                    $updatedFields
                ),
            ],
        ]);
    }

    /**
     * Search/explore users with filters.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'gender' => 'sometimes|in:male,female',
            'age_min' => 'sometimes|integer|min:18',
            'age_max' => 'sometimes|integer|max:99',
            'location' => 'sometimes|string',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        // Mock user list
        $mockUsers = [];
        for ($i = 1; $i <= 10; $i++) {
            $mockUsers[] = [
                'id' => 'usr_mock' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'nickname' => 'User' . $i,
                'gender' => $i % 2 === 0 ? 'female' : 'male',
                'age' => rand(20, 35),
                'avatar_url' => null,
                'bio' => '嗨，我是 User' . $i,
                'location' => '台北',
                'membership_level' => rand(0, 2),
            ];
        }

        return response()->json([
            'success' => true,
            'code' => 'SEARCH_RESULTS',
            'message' => 'OK',
            'data' => [
                'users' => $mockUsers,
                'pagination' => [
                    'current_page' => (int) $request->input('page', 1),
                    'per_page' => (int) $request->input('per_page', 20),
                    'total' => 100,
                    'last_page' => 5,
                ],
            ],
        ]);
    }

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
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required|image|max:5120',
            'is_primary' => 'sometimes|boolean',
        ]);

        $mockPhoto = [
            'id' => 'photo_' . Str::random(12),
            'url' => 'https://placeholder.example.com/photos/' . Str::random(8) . '.jpg',
            'is_primary' => $request->boolean('is_primary', false),
            'status' => 'pending_review',
            'created_at' => now()->toISOString(),
        ];

        return response()->json([
            'success' => true,
            'code' => 'PHOTO_UPLOADED',
            'message' => '照片已上傳，審核中。',
            'data' => [
                'photo' => $mockPhoto,
            ],
        ], 201);
    }

    /**
     * Get user settings page data.
     */
    public function settings(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'USER_SETTINGS',
            'message' => 'OK',
            'data' => [
                'notification_preferences' => [
                    'new_message' => true,
                    'new_follower' => true,
                    'date_invitation' => true,
                    'system' => true,
                ],
                'privacy' => [
                    'show_online_status' => true,
                    'allow_search' => true,
                    'show_distance' => false,
                ],
                'account' => [
                    'email_verified' => true,
                    'phone_verified' => false,
                ],
            ],
        ]);
    }

    /**
     * Get users the current user is following.
     */
    public function following(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'FOLLOWING_LIST',
            'message' => 'OK',
            'data' => [
                'users' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ],
        ]);
    }

    /**
     * Get profile visitors.
     */
    public function visitors(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'VISITORS_LIST',
            'message' => 'OK',
            'data' => [
                'visitors' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ],
        ]);
    }

    /**
     * Follow a user.
     */
    public function follow(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'FOLLOWED',
            'message' => '已追蹤。',
        ]);
    }

    /**
     * Unfollow a user.
     */
    public function unfollow(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'UNFOLLOWED',
            'message' => '已取消追蹤。',
        ]);
    }

    /**
     * Block a user.
     */
    public function block(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'BLOCKED',
            'message' => '已封鎖該用戶。',
        ]);
    }

    /**
     * Unblock a user.
     */
    public function unblock(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'UNBLOCKED',
            'message' => '已解除封鎖。',
        ]);
    }

    /**
     * Get blocked users list.
     */
    public function blockedUsers(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 'BLOCKED_USERS',
            'message' => 'OK',
            'data' => [
                'users' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 20,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ],
        ]);
    }
}
