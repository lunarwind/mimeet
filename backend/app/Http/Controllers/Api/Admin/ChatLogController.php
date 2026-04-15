<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatLogController extends Controller
{
    /**
     * GET /api/v1/admin/chat-logs/search — keyword search across all messages
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'keyword' => 'required|string|min:2',
            'user_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $keyword = $request->input('keyword');
        $userId = $request->input('user_id');
        $perPage = (int) $request->input('per_page', 20);

        $query = Message::with(['sender:id,nickname,avatar_url', 'conversation'])
            ->where('is_recalled', false)
            ->where('content', 'LIKE', "%{$keyword}%")
            ->when($userId, fn ($q) => $q->where('sender_id', $userId))
            ->orderByDesc('sent_at');

        $paginated = $query->paginate($perPage);

        $data = $paginated->map(function (Message $msg) {
            $conv = $msg->conversation;
            $receiverId = $conv->user_a_id === $msg->sender_id ? $conv->user_b_id : $conv->user_a_id;
            $receiver = User::select('id', 'nickname')->find($receiverId);

            return [
                'message_id' => $msg->id,
                'conversation_id' => $msg->conversation_id,
                'sender' => $msg->sender ? ['id' => $msg->sender->id, 'nickname' => $msg->sender->nickname] : null,
                'receiver' => $receiver ? ['id' => $receiver->id, 'nickname' => $receiver->nickname] : null,
                'content' => $msg->content,
                'type' => $msg->type,
                'sent_at' => $msg->sent_at->toISOString(),
                'is_read' => (bool) $msg->is_read,
            ];
        });

        Log::info('[AdminLog] chat-logs/search', [
            'admin_id' => $request->user()->id,
            'keyword' => $keyword,
            'results' => $paginated->total(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $paginated->total(),
                'page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'last_page' => $paginated->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/chat-logs/conversations — messages between two users
     */
    public function conversations(Request $request): JsonResponse
    {
        $request->validate([
            'user_a' => 'required|integer',
            'user_b' => 'required|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $userA = (int) $request->input('user_a');
        $userB = (int) $request->input('user_b');
        $minId = min($userA, $userB);
        $maxId = max($userA, $userB);

        $conversation = Conversation::where('user_a_id', $minId)
            ->where('user_b_id', $maxId)
            ->first();

        if (!$conversation) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'CONVERSATION_NOT_FOUND', 'message' => '找不到兩人間的對話'],
            ], 404);
        }

        $perPage = (int) $request->input('per_page', 50);
        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('sent_at', 'asc')
            ->paginate($perPage);

        $userAData = User::select('id', 'nickname', 'avatar_url')->find($minId);
        $userBData = User::select('id', 'nickname', 'avatar_url')->find($maxId);

        $msgData = $messages->map(fn (Message $msg) => [
            'id' => $msg->id,
            'sender_id' => $msg->sender_id,
            'content' => $msg->is_recalled ? null : $msg->content,
            'type' => $msg->type,
            'is_recalled' => (bool) $msg->is_recalled,
            'recalled_at' => $msg->is_recalled ? $msg->recalled_at?->toISOString() : null,
            'sent_at' => $msg->sent_at->toISOString(),
            'is_read' => (bool) $msg->is_read,
            'read_at' => $msg->read_at?->toISOString(),
        ]);

        Log::info('[AdminLog] chat-logs/conversations', [
            'admin_id' => $request->user()->id,
            'user_a' => $userA,
            'user_b' => $userB,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'conversation_id' => $conversation->id,
                'user_a' => $userAData,
                'user_b' => $userBData,
                'messages' => $msgData,
            ],
            'meta' => [
                'total' => $messages->total(),
                'page' => $messages->currentPage(),
                'per_page' => $messages->perPage(),
                'last_page' => $messages->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/chat-logs/export — CSV export of conversation
     */
    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'user_a' => 'required|integer',
            'user_b' => 'required|integer',
        ]);

        $userA = (int) $request->input('user_a');
        $userB = (int) $request->input('user_b');
        $minId = min($userA, $userB);
        $maxId = max($userA, $userB);

        $conversation = Conversation::where('user_a_id', $minId)
            ->where('user_b_id', $maxId)
            ->firstOrFail();

        $userAData = User::select('id', 'nickname')->find($minId);
        $userBData = User::select('id', 'nickname')->find($maxId);

        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('sent_at', 'asc')
            ->get();

        Log::info('[AdminLog] chat-logs/export', [
            'admin_id' => $request->user()->id,
            'user_a' => $userA,
            'user_b' => $userB,
            'message_count' => $messages->count(),
        ]);

        $date = now()->format('Ymd');
        $filename = "chat_export_{$userA}_{$userB}_{$date}.csv";

        return response()->streamDownload(function () use ($messages, $minId, $maxId, $userAData, $userBData) {
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['message_id', 'sender_id', 'sender_nickname', 'receiver_id', 'receiver_nickname', 'content', 'type', 'is_read', 'sent_at', 'read_at']);

            foreach ($messages as $msg) {
                $isSenderA = $msg->sender_id === $minId;
                $senderNick = $isSenderA ? $userAData->nickname : $userBData->nickname;
                $receiverId = $isSenderA ? $maxId : $minId;
                $receiverNick = $isSenderA ? $userBData->nickname : $userAData->nickname;

                fputcsv($out, [
                    $msg->id,
                    $msg->sender_id,
                    $senderNick,
                    $receiverId,
                    $receiverNick,
                    $msg->is_recalled ? '[已收回]' : $msg->content,
                    $msg->type,
                    $msg->is_read ? 'Y' : 'N',
                    $msg->sent_at->toISOString(),
                    $msg->read_at?->toISOString() ?? '',
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }

    /**
     * GET /api/v1/admin/members/{userId}/chat-logs/export — export member's conversation CSV
     */
    public function memberChatLogsExport(Request $request, int $userId): StreamedResponse
    {
        $counterpartId = (int) $request->input('counterpart_id');
        if (!$counterpartId) {
            abort(400, 'counterpart_id is required for export');
        }

        $minId = min($userId, $counterpartId);
        $maxId = max($userId, $counterpartId);

        $conversation = Conversation::where('user_a_id', $minId)
            ->where('user_b_id', $maxId)
            ->firstOrFail();

        $userAData = User::select('id', 'nickname')->find($minId);
        $userBData = User::select('id', 'nickname')->find($maxId);

        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('sent_at', 'asc')
            ->get();

        Log::info('[AdminLog] members/chat-logs/export', [
            'admin_id' => $request->user()->id,
            'user_id' => $userId,
            'counterpart_id' => $counterpartId,
            'message_count' => $messages->count(),
        ]);

        $date = now()->format('Ymd');
        $filename = "member_{$userId}_chat_{$counterpartId}_{$date}.csv";

        return response()->streamDownload(function () use ($messages, $minId, $maxId, $userAData, $userBData) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['message_id', 'sender_id', 'sender_nickname', 'receiver_id', 'receiver_nickname', 'content', 'type', 'is_read', 'sent_at']);

            foreach ($messages as $msg) {
                $isSenderA = $msg->sender_id === $minId;
                fputcsv($out, [
                    $msg->id,
                    $msg->sender_id,
                    $isSenderA ? $userAData->nickname : $userBData->nickname,
                    $isSenderA ? $maxId : $minId,
                    $isSenderA ? $userBData->nickname : $userAData->nickname,
                    $msg->is_recalled ? '[已收回]' : $msg->content,
                    $msg->type,
                    $msg->is_read ? 'Y' : 'N',
                    $msg->sent_at->toISOString(),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * GET /api/v1/admin/members/{userId}/chat-logs — user's conversation list
     */
    public function memberChatLogs(Request $request, int $userId): JsonResponse
    {
        $counterpartId = $request->input('counterpart_id');

        if ($counterpartId) {
            // Return messages between specific pair
            $minId = min($userId, (int) $counterpartId);
            $maxId = max($userId, (int) $counterpartId);

            $conversation = Conversation::where('user_a_id', $minId)
                ->where('user_b_id', $maxId)
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'meta' => ['total' => 0, 'page' => 1, 'per_page' => 50, 'last_page' => 1],
                ]);
            }

            $messages = Message::where('conversation_id', $conversation->id)
                ->orderBy('sent_at', 'asc')
                ->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $messages->map(fn (Message $msg) => [
                    'id' => $msg->id,
                    'sender_id' => $msg->sender_id,
                    'content' => $msg->is_recalled ? null : $msg->content,
                    'type' => $msg->type,
                    'is_recalled' => (bool) $msg->is_recalled,
                    'sent_at' => $msg->sent_at->toISOString(),
                    'is_read' => (bool) $msg->is_read,
                ]),
                'meta' => [
                    'total' => $messages->total(),
                    'page' => $messages->currentPage(),
                    'per_page' => $messages->perPage(),
                    'last_page' => $messages->lastPage(),
                ],
            ]);
        }

        // Return conversation list for user
        $conversations = Conversation::where(function ($q) use ($userId) {
                $q->where('user_a_id', $userId)->where('deleted_by_a', 0);
            })
            ->orWhere(function ($q) use ($userId) {
                $q->where('user_b_id', $userId)->where('deleted_by_b', 0);
            })
            ->with(['lastMessage'])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        $data = $conversations->map(function (Conversation $conv) use ($userId) {
            $counterpartId = $conv->user_a_id === $userId ? $conv->user_b_id : $conv->user_a_id;
            $counterpart = User::select('id', 'nickname', 'avatar_url')->find($counterpartId);
            $totalMessages = Message::where('conversation_id', $conv->id)->count();

            return [
                'conversation_id' => $conv->id,
                'counterpart' => $counterpart ? [
                    'id' => $counterpart->id,
                    'nickname' => $counterpart->nickname,
                    'avatar_url' => $counterpart->avatar_url,
                ] : null,
                'last_message' => $conv->lastMessage ? [
                    'content' => $conv->lastMessage->is_recalled ? '[已收回]' : $conv->lastMessage->content,
                    'sent_at' => $conv->lastMessage->sent_at->toISOString(),
                ] : null,
                'total_messages' => $totalMessages,
            ];
        });

        Log::info('[AdminLog] members/chat-logs', [
            'admin_id' => $request->user()->id,
            'target_user_id' => $userId,
        ]);

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'page' => $conversations->currentPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
                'last_page' => $conversations->lastPage(),
            ],
        ]);
    }
}
