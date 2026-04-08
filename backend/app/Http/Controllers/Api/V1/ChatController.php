<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Exceptions\DailyLimitException;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * POST /api/v1/chats/{id}/messages — send a message
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $userId = $request->user()->id;

        if (!$this->chatService->isParticipant($id, $userId)) {
            return response()->json([
                'success' => false,
                'code' => 403,
                'message' => '您不是此對話的參與者',
            ], 403);
        }

        try {
            $message = $this->chatService->sendMessage($id, $userId, $request->input('content'));
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
                    'type' => $message->type,
                    'sent_at' => $message->sent_at->toISOString(),
                    'is_read' => false,
                ],
            ],
        ], 201);
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
