<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminOperationLog;
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
     * 是否可看到「已收回」訊息的原始 content。
     * 依 Option C 分級權限：super_admin 在 retention 期內可看原文；
     * admin / cs 看佔位符。對應 API-002 §7 隱私保護段、DEV-001 §6.3.1。
     */
    private function canViewRecalledContent(Request $request): bool
    {
        return $request->user()?->role === 'super_admin';
    }

    /**
     * super_admin 看到 recalled 訊息原文時，寫入結構化稽核軌跡。
     * 注意：chat-logs 是 GET 端點，不會經過 LogAdminOperation middleware
     * （middleware 只 log POST/PATCH/PUT/DELETE），所以這裡主動寫入。
     */
    private function logRecalledContentView(Request $request, string $action, array $context): void
    {
        AdminOperationLog::create([
            'admin_id' => $request->user()->id,
            'action' => $action,
            'resource_type' => $context['resource_type'] ?? 'chat',
            'resource_id' => $context['resource_id'] ?? null,
            'description' => $request->method() . ' ' . $request->path() . ' (viewed recalled content)',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'request_summary' => array_merge(
                ['viewed_recalled_content' => true],
                $context,
            ),
            'created_at' => now(),
        ]);
    }

    /**
     * GET /api/v1/admin/chat-logs/search — keyword search across all messages
     *
     * 三分級行為（v1.6）：
     *  - super_admin：搜尋包含 recalled、可用原文關鍵字命中
     *  - admin：搜尋排除 recalled（避免關鍵字反推被遮蔽內容）
     *  - cs：無 chat.view 權限，路由 middleware 已擋
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
        $canSeeRecalled = $this->canViewRecalledContent($request);

        $query = Message::with(['sender:id,nickname,avatar_url', 'conversation'])
            ->where('content', 'LIKE', "%{$keyword}%")
            ->when($userId, fn ($q) => $q->where('sender_id', $userId))
            ->orderByDesc('sent_at');

        if (!$canSeeRecalled) {
            // admin：排除已收回訊息（避免關鍵字反推遮蔽內容）
            $query->where('is_recalled', false);
        }

        $paginated = $query->paginate($perPage);

        // 批次查詢 receiver，避免 N+1（一次 whereIn 取代 N 次 find）
        $receiverIds = $paginated->map(function (Message $msg) {
            $conv = $msg->conversation;
            return $conv->user_a_id === $msg->sender_id ? $conv->user_b_id : $conv->user_a_id;
        })->filter()->unique();

        $receivers = User::select('id', 'nickname')
            ->whereIn('id', $receiverIds)
            ->get()
            ->keyBy('id');

        $data = $paginated->map(function (Message $msg) use ($receivers, $canSeeRecalled) {
            $conv = $msg->conversation;
            $receiverId = $conv->user_a_id === $msg->sender_id ? $conv->user_b_id : $conv->user_a_id;
            $receiver = $receivers->get($receiverId);
            $isRecalled = (bool) $msg->is_recalled;
            $isContentVisible = !$isRecalled || $canSeeRecalled;

            return [
                'message_id' => $msg->id,
                'conversation_id' => $msg->conversation_id,
                'sender' => $msg->sender ? ['id' => $msg->sender->id, 'nickname' => $msg->sender->nickname] : null,
                'receiver' => $receiver ? ['id' => $receiver->id, 'nickname' => $receiver->nickname] : null,
                'content' => $isContentVisible ? $msg->content : null,
                'type' => $msg->type,
                'sent_at' => $msg->sent_at->toISOString(),
                'is_read' => (bool) $msg->is_read,
                'is_recalled' => $isRecalled,
                'recalled_at' => $isRecalled ? $msg->recalled_at?->toISOString() : null,
                'is_content_visible' => $isContentVisible,
            ];
        });

        $recalledHitCount = $data->where('is_recalled', true)->count();

        Log::info('[AdminLog] chat-logs/search', [
            'admin_id' => $request->user()->id,
            'keyword' => $keyword,
            'results' => $paginated->total(),
            'recalled_hits' => $recalledHitCount,
        ]);

        if ($canSeeRecalled && $recalledHitCount > 0) {
            $this->logRecalledContentView($request, 'chat_logs_search_recalled', [
                'resource_type' => 'chat',
                'resource_id' => null,
                'keyword' => $keyword,
                'recalled_message_count' => $recalledHitCount,
            ]);
        }

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

        $canSeeRecalled = $this->canViewRecalledContent($request);

        $msgData = $messages->map(function (Message $msg) use ($canSeeRecalled) {
            $isRecalled = (bool) $msg->is_recalled;
            $isContentVisible = !$isRecalled || $canSeeRecalled;
            return [
                'id' => $msg->id,
                'sender_id' => $msg->sender_id,
                'content' => $isContentVisible ? $msg->content : null,
                'type' => $msg->type,
                'is_recalled' => $isRecalled,
                'recalled_at' => $isRecalled ? $msg->recalled_at?->toISOString() : null,
                'is_content_visible' => $isContentVisible,
                'sent_at' => $msg->sent_at->toISOString(),
                'is_read' => (bool) $msg->is_read,
                'read_at' => $msg->read_at?->toISOString(),
            ];
        });

        $recalledCount = $msgData->where('is_recalled', true)->count();

        Log::info('[AdminLog] chat-logs/conversations', [
            'admin_id' => $request->user()->id,
            'user_a' => $userA,
            'user_b' => $userB,
            'recalled_in_window' => $recalledCount,
        ]);

        if ($canSeeRecalled && $recalledCount > 0) {
            $this->logRecalledContentView($request, 'chat_logs_view_recalled', [
                'resource_type' => 'conversation',
                'resource_id' => $conversation->id,
                'user_a' => $userA,
                'user_b' => $userB,
                'recalled_message_count' => $recalledCount,
            ]);
        }

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

        $canSeeRecalled = $this->canViewRecalledContent($request);
        $recalledCount = $messages->where('is_recalled', true)->count();

        Log::info('[AdminLog] chat-logs/export', [
            'admin_id' => $request->user()->id,
            'user_a' => $userA,
            'user_b' => $userB,
            'message_count' => $messages->count(),
            'recalled_in_export' => $recalledCount,
            'role_can_see_recalled' => $canSeeRecalled,
        ]);

        if ($canSeeRecalled && $recalledCount > 0) {
            $this->logRecalledContentView($request, 'chat_logs_export_recalled', [
                'resource_type' => 'conversation',
                'resource_id' => $conversation->id,
                'user_a' => $userA,
                'user_b' => $userB,
                'recalled_message_count' => $recalledCount,
            ]);
        }

        $date = now()->format('Ymd');
        $filename = "chat_export_{$userA}_{$userB}_{$date}.csv";

        return response()->streamDownload(function () use ($messages, $minId, $maxId, $userAData, $userBData, $canSeeRecalled) {
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            // recall_status / recalled_at 為 v1.6 新增：稽核者匯出時可一眼分辨「收回前內容」與「正常訊息」
            fputcsv($out, ['message_id', 'sender_id', 'sender_nickname', 'receiver_id', 'receiver_nickname', 'content', 'type', 'is_read', 'sent_at', 'read_at', 'recall_status', 'recalled_at']);

            foreach ($messages as $msg) {
                $isSenderA = $msg->sender_id === $minId;
                $senderNick = $isSenderA ? $userAData->nickname : $userBData->nickname;
                $receiverId = $isSenderA ? $maxId : $minId;
                $receiverNick = $isSenderA ? $userBData->nickname : $userAData->nickname;
                $isRecalled = (bool) $msg->is_recalled;
                $contentForCsv = $isRecalled
                    ? ($canSeeRecalled ? $msg->content : '[已收回]')
                    : $msg->content;

                fputcsv($out, [
                    $msg->id,
                    $msg->sender_id,
                    $senderNick,
                    $receiverId,
                    $receiverNick,
                    $contentForCsv,
                    $msg->type,
                    $msg->is_read ? 'Y' : 'N',
                    $msg->sent_at->toISOString(),
                    $msg->read_at?->toISOString() ?? '',
                    $isRecalled ? 'RECALLED' : '',
                    $isRecalled ? ($msg->recalled_at?->toISOString() ?? '') : '',
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

        $canSeeRecalled = $this->canViewRecalledContent($request);
        $recalledCount = $messages->where('is_recalled', true)->count();

        Log::info('[AdminLog] members/chat-logs/export', [
            'admin_id' => $request->user()->id,
            'user_id' => $userId,
            'counterpart_id' => $counterpartId,
            'message_count' => $messages->count(),
            'recalled_in_export' => $recalledCount,
            'role_can_see_recalled' => $canSeeRecalled,
        ]);

        if ($canSeeRecalled && $recalledCount > 0) {
            $this->logRecalledContentView($request, 'chat_logs_member_export_recalled', [
                'resource_type' => 'conversation',
                'resource_id' => $conversation->id,
                'user_id' => $userId,
                'counterpart_id' => $counterpartId,
                'recalled_message_count' => $recalledCount,
            ]);
        }

        $date = now()->format('Ymd');
        $filename = "member_{$userId}_chat_{$counterpartId}_{$date}.csv";

        return response()->streamDownload(function () use ($messages, $minId, $maxId, $userAData, $userBData, $canSeeRecalled) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['message_id', 'sender_id', 'sender_nickname', 'receiver_id', 'receiver_nickname', 'content', 'type', 'is_read', 'sent_at', 'recall_status', 'recalled_at']);

            foreach ($messages as $msg) {
                $isSenderA = $msg->sender_id === $minId;
                $isRecalled = (bool) $msg->is_recalled;
                $contentForCsv = $isRecalled
                    ? ($canSeeRecalled ? $msg->content : '[已收回]')
                    : $msg->content;
                fputcsv($out, [
                    $msg->id,
                    $msg->sender_id,
                    $isSenderA ? $userAData->nickname : $userBData->nickname,
                    $isSenderA ? $maxId : $minId,
                    $isSenderA ? $userBData->nickname : $userAData->nickname,
                    $contentForCsv,
                    $msg->type,
                    $msg->is_read ? 'Y' : 'N',
                    $msg->sent_at->toISOString(),
                    $isRecalled ? 'RECALLED' : '',
                    $isRecalled ? ($msg->recalled_at?->toISOString() ?? '') : '',
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

            $canSeeRecalled = $this->canViewRecalledContent($request);
            $msgData = $messages->map(function (Message $msg) use ($canSeeRecalled) {
                $isRecalled = (bool) $msg->is_recalled;
                $isContentVisible = !$isRecalled || $canSeeRecalled;
                return [
                    'id' => $msg->id,
                    'sender_id' => $msg->sender_id,
                    'content' => $isContentVisible ? $msg->content : null,
                    'type' => $msg->type,
                    'is_recalled' => $isRecalled,
                    'recalled_at' => $isRecalled ? $msg->recalled_at?->toISOString() : null,
                    'is_content_visible' => $isContentVisible,
                    'sent_at' => $msg->sent_at->toISOString(),
                    'is_read' => (bool) $msg->is_read,
                ];
            });

            $recalledCount = $msgData->where('is_recalled', true)->count();
            if ($canSeeRecalled && $recalledCount > 0) {
                $this->logRecalledContentView($request, 'chat_logs_member_view_recalled', [
                    'resource_type' => 'conversation',
                    'resource_id' => $conversation->id,
                    'user_id' => $userId,
                    'counterpart_id' => (int) $counterpartId,
                    'recalled_message_count' => $recalledCount,
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $msgData,
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

        $canSeeRecalled = $this->canViewRecalledContent($request);
        $recalledPreviewIds = [];
        $data = $conversations->map(function (Conversation $conv) use ($userId, $canSeeRecalled, &$recalledPreviewIds) {
            $counterpartId = $conv->user_a_id === $userId ? $conv->user_b_id : $conv->user_a_id;
            $counterpart = User::select('id', 'nickname', 'avatar_url')->find($counterpartId);
            $totalMessages = Message::where('conversation_id', $conv->id)->count();

            $lastMsg = $conv->lastMessage;
            $lastMsgPayload = null;
            if ($lastMsg) {
                $isRecalled = (bool) $lastMsg->is_recalled;
                $isContentVisible = !$isRecalled || $canSeeRecalled;
                if ($isRecalled && $isContentVisible) {
                    // 收集本頁 list 中，super_admin 實際看到 recalled 原文的對話 id
                    $recalledPreviewIds[] = $conv->id;
                }
                $lastMsgPayload = [
                    'content' => $isContentVisible ? $lastMsg->content : '[已收回]',
                    'sent_at' => $lastMsg->sent_at->toISOString(),
                    'is_recalled' => $isRecalled,
                    'is_content_visible' => $isContentVisible,
                ];
            }

            return [
                'conversation_id' => $conv->id,
                'counterpart' => $counterpart ? [
                    'id' => $counterpart->id,
                    'nickname' => $counterpart->nickname,
                    'avatar_url' => $counterpart->avatar_url,
                ] : null,
                'last_message' => $lastMsgPayload,
                'total_messages' => $totalMessages,
            ];
        });

        if (!empty($recalledPreviewIds)) {
            // 即使只是 last_message preview，super_admin 仍然看到了 recalled 原文，
            // 為「實際看到」原則一致性，補上稽核軌跡。
            $this->logRecalledContentView($request, 'chat_logs_member_list_recalled_preview', [
                'resource_type' => 'member',
                'resource_id' => $userId,
                'recalled_preview_conversation_ids' => $recalledPreviewIds,
                'recalled_message_count' => count($recalledPreviewIds),
            ]);
        }

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
