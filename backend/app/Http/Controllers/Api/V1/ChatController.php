<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Exceptions\CreditScoreRestrictionException;
use App\Exceptions\DailyLimitException;
use App\Models\Conversation;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    /**
     * GET /api/v1/chats — conversation list
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $chats = $this->chatService->getConversationList($userId);

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '聊天列表查詢成功',
            'data' => ['chats' => $chats],
        ]);
    }

    /**
     * POST /api/v1/chats — create or get existing conversation
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $userId = $request->user()->id;
        $targetId = (int) $request->input('user_id');

        if ($userId === $targetId) {
            return response()->json([
                'success' => false,
                'code' => 422,
                'message' => '不能和自己建立對話',
            ], 422);
        }

        $conversation = $this->chatService->getOrCreateConversation($userId, $targetId);

        return response()->json([
            'success' => true,
            'code' => 201,
            'message' => '對話建立成功',
            'data' => [
                'conversation' => [
                    'id' => $conversation->id,
                    'uuid' => $conversation->uuid,
                    'user_a_id' => $conversation->user_a_id,
                    'user_b_id' => $conversation->user_b_id,
                ],
            ],
        ], 201);
    }

    /**
     * GET /api/v1/chats/{id}/info — conversation info (other user details)
     */
    public function info(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (!$this->chatService->isParticipant($id, $user->id)) {
            return response()->json([
                'success' => false, 'code' => 403, 'message' => '您不是此對話的參與者',
            ], 403);
        }

        $conversation = Conversation::findOrFail($id);
        $otherId = $conversation->user_a_id === $user->id
            ? $conversation->user_b_id
            : $conversation->user_a_id;
        $other = User::find($otherId);

        if (!$other) {
            return response()->json([
                'success' => false, 'code' => 404, 'message' => '用戶不存在',
            ], 404);
        }

        // Online status: only visible to Lv3 paid members
        $onlineStatus = null;
        $lastActiveLabel = null;
        $isPaid = ((float) $user->membership_level) >= 3;

        if ($isPaid) {
            $privacy = $other->privacy_settings;
            $stealthMode = !($privacy['show_online_status'] ?? true);

            if ($stealthMode) {
                $onlineStatus = 'offline';
                $lastActiveLabel = '離線中';
            } elseif ($other->last_active_at) {
                $diff = now()->diffInMinutes($other->last_active_at);
                if ($diff < 5) {
                    $onlineStatus = 'online';
                    $lastActiveLabel = '線上中';
                } elseif ($diff < 60) {
                    $onlineStatus = 'away';
                    $lastActiveLabel = "{$diff}分鐘前上線";
                } elseif ($diff < 1440) {
                    $hours = intdiv($diff, 60);
                    $onlineStatus = 'away';
                    $lastActiveLabel = "{$hours}小時前上線";
                } else {
                    $days = intdiv($diff, 1440);
                    $onlineStatus = 'offline';
                    $lastActiveLabel = "{$days}天前上線";
                }
            } else {
                $onlineStatus = 'offline';
                $lastActiveLabel = '離線中';
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $other->id,
                    'nickname' => $other->nickname,
                    'avatar_url' => $other->avatar_url,
                    'online_status' => $onlineStatus,
                    'last_active_label' => $lastActiveLabel,
                    'credit_score' => $other->credit_score,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/chats/{id}/messages — message history
     */
    public function messages(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        if (!$this->chatService->isParticipant($id, $userId)) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => '您不是此對話的參與者',
            ], 403);
        }

        $cursor = $request->query('cursor');
        $perPage = (int) $request->query('per_page', 30);
        $result = $this->chatService->getMessages($id, $cursor, min($perPage, 50));

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '聊天記錄查詢成功',
            'data' => [
                'messages' => $result['data'],
                'next_cursor' => $result['next_cursor'],
                'has_more' => $result['has_more'],
            ],
        ]);
    }

    /**
     * POST /api/v1/chats/{id}/messages — send a message (text or image)
     *
     * Two request formats:
     *  - JSON: { "content": "...", "message_type": "text" }
     *  - multipart: message_type=image, image={file}
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $type = $request->input('message_type', 'text');

        if ($type === 'image') {
            $request->validate([
                'image' => 'required|file|image|mimes:jpg,jpeg,png,webp|max:5120', // 5MB
            ]);
        } else {
            $request->validate([
                'content' => 'required|string|max:2000',
            ]);
        }

        $userId = $request->user()->id;

        if (!$this->chatService->isParticipant($id, $userId)) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => '您不是此對話的參與者',
            ], 403);
        }

        // Block check: prevent messaging if either party blocked the other
        $conversation = Conversation::find($id);
        if ($conversation) {
            $otherId = $conversation->user_a_id === $userId
                ? $conversation->user_b_id
                : $conversation->user_a_id;

            $isBlocked = UserBlock::where(function ($q) use ($userId, $otherId) {
                $q->where('blocker_id', $userId)->where('blocked_id', $otherId);
            })->orWhere(function ($q) use ($userId, $otherId) {
                $q->where('blocker_id', $otherId)->where('blocked_id', $userId);
            })->exists();

            if ($isBlocked) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => '2002', 'message' => '無法傳訊息給此用戶'],
                ], 400);
            }
        }

        // Upload image if provided → produce public URL for the message
        $content = (string) $request->input('content', '');
        $imageUrl = null;

        if ($type === 'image') {
            $file = $request->file('image');
            $filename = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
            $relative = "chat-images/{$id}/{$filename}";
            Storage::disk('public')->putFileAs("chat-images/{$id}", $file, $filename);
            $imageUrl = asset('storage/' . $relative);
            // For image type, store URL as content too for backward compatibility with simple renderers
            $content = $content !== '' ? $content : $imageUrl;
        }

        try {
            $message = $this->chatService->sendMessage($id, $userId, $content, $type, $imageUrl);
        } catch (CreditScoreRestrictionException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => '2001',
                    'message' => $e->getMessage(),
                ],
            ], 403);
        } catch (DailyLimitException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MSG_LIMIT_EXCEEDED',
                    'message' => $e->getMessage(),
                ],
            ], 429);
        }

        return response()->json([
            'success' => true,
            'code' => 201,
            'message' => '消息發送成功',
            'data' => [
                'message' => [
                    'id' => $message->id,
                    'uuid' => $message->uuid,
                    'sender_id' => $message->sender_id,
                    'content' => $message->content,
                    'image_url' => $message->image_url,
                    'type' => $message->type,
                    'sent_at' => $message->sent_at->toISOString(),
                    'is_read' => false,
                    'is_recalled' => false,
                ],
            ],
        ], 201);
    }

    /**
     * DELETE /api/v1/chats/{id}/messages/{messageId} — recall message (F19)
     * Conditions enforced in ChatService::recallMessage.
     */
    public function recallMessage(Request $request, int $id, int $messageId): JsonResponse
    {
        $userId = $request->user()->id;

        if (!$this->chatService->isParticipant($id, $userId)) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => '您不是此對話的參與者',
            ], 403);
        }

        try {
            $this->chatService->recallMessage($id, $messageId, $userId);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'RECALL_DENIED', 'message' => $e->getMessage()],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '訊息已收回',
            'data' => [
                'message_id' => $messageId,
                'recalled_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * GET /api/v1/chats/{id}/messages/search?keyword=xxx — search within conversation (F20)
     */
    public function searchMessages(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'keyword' => 'required|string|min:1|max:100',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $userId = $request->user()->id;

        if (!$this->chatService->isParticipant($id, $userId)) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => '您不是此對話的參與者',
            ], 403);
        }

        $result = $this->chatService->searchMessages(
            $id,
            (string) $request->input('keyword'),
            (int) $request->input('per_page', 20),
        );

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '搜尋成功',
            'data' => [
                'messages' => $result['data'],
            ],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * PATCH /api/v1/chats/{id}/read — mark messages as read
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        if (!$this->chatService->isParticipant($id, $userId)) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => '您不是此對話的參與者',
            ], 403);
        }

        $this->chatService->markAsRead($id, $userId);

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '已標記已讀',
        ]);
    }

    /**
     * PATCH /api/v1/chats/read-all — mark all conversations as read
     */
    public function readAll(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $conversations = Conversation::where('user_a_id', $userId)
            ->orWhere('user_b_id', $userId)
            ->get();

        foreach ($conversations as $conv) {
            $this->chatService->markAsRead($conv->id, $userId);
        }

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '所有對話已標記為已讀',
            'data' => ['marked_count' => $conversations->count()],
        ]);
    }

    /**
     * PATCH /api/v1/chats/{id}/mute — toggle per-conversation mute (F22 Part A)
     */
    public function toggleMute(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        if (!$this->chatService->isParticipant($id, $userId)) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => '您不是此對話的參與者',
            ], 403);
        }

        $conversation = Conversation::findOrFail($id);
        $isMuted = $conversation->toggleMute($userId);

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => $isMuted ? '已靜音此對話' : '已取消靜音',
            'data' => ['is_muted' => $isMuted],
        ]);
    }

    /**
     * DELETE /api/v1/chats/{id} — soft delete conversation
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        if (!$this->chatService->isParticipant($id, $userId)) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => '您不是此對話的參與者',
            ], 403);
        }

        $this->chatService->softDelete($id, $userId);

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '對話已刪除',
        ]);
    }
}
