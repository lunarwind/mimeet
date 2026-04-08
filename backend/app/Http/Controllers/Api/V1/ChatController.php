<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '聊天列表查詢成功',
            'data' => ['chats' => []],
            'pagination' => ['current_page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 0],
        ]);
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '聊天記錄查詢成功',
            'data' => ['messages' => [], 'has_more' => false],
        ]);
    }

    public function sendMessage(Request $request, int $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'code' => 201,
            'message' => '消息發送成功',
            'data' => [
                'message' => [
                    'id' => rand(1000, 9999),
                    'sender_id' => 1,
                    'content' => $request->input('data.content', ''),
                    'message_type' => 'text',
                    'sent_at' => now()->toISOString(),
                    'is_read' => false,
                ],
            ],
        ], 201);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        return response()->json(['success' => true, 'code' => 200, 'message' => '已標記已讀']);
    }
}
