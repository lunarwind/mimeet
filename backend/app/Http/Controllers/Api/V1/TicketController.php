<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportFollowup;
use App\Services\TicketNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * D.3 解耦版（2026-05-02）：Ticket 系統 = 純行政管理工具。
 *
 * 解耦原則（用戶決策 Q1/Q2）：
 * - updateStatus 只變更 ticket.status + resolution_note + resolved_by/at
 * - 不變更 user.status（解停須由 admin 手動至會員管理頁操作）
 * - 不變更 credit_score（補分須由 admin 手動至會員管理頁的調整分數操作）
 * - appeal 與其他 ticket type 走相同通用流程，無特例
 *
 * 通知雙軌（用戶決策 Q4-Q8）：
 * - status 變為 resolved/dismissed 時自動觸發通知
 * - active user → 站內訊息（既有 NotificationService）
 * - suspended/auto_suspended user → email（既有 Resend 設施）
 * - 通知管道判斷在 ticket 變更前 snapshot user.status（race-safe）
 *
 * Refs: docs/decisions/2026-05-01-check-suspended-decision.md
 */
class TicketController extends Controller
{
    public function __construct(
        private readonly TicketNotificationService $ticketNotificationService,
    ) {}

    /**
     * PATCH /api/v1/admin/tickets/{id}/status
     *
     * Body:
     *   - status: pending|investigating|resolved|dismissed (required)
     *   - admin_reply: string (required when status=resolved|dismissed, max 2000 chars)
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:pending,investigating,resolved,dismissed',
            'admin_reply' => 'required_if:status,resolved,dismissed|nullable|string|max:2000',
        ]);

        $report = Report::findOrFail($id);
        $oldStatus = $report->status;
        $newStatus = $request->input('status');
        $adminReply = $request->input('admin_reply');
        $adminId = $request->user()->id;

        // ── Race-safe：先 snapshot reporter.status，避免後續 user.status 異動影響通知管道判斷 ──
        // 雖然此版本 ticket 處理不會變更 user.status，但保留此習慣以防未來重新耦合。
        $reporter = $report->reporter;
        $reporterStatusSnapshot = $reporter?->status;

        $isTerminalNew = in_array($newStatus, ['resolved', 'dismissed'], true);
        $isTerminalOld = in_array($oldStatus, ['resolved', 'dismissed'], true);

        $report->update(array_filter([
            'status' => $newStatus,
            'resolution_note' => $adminReply,
            'resolved_by' => $isTerminalNew ? $adminId : null,
            'resolved_at' => $isTerminalNew ? now() : null,
        ], fn ($v) => $v !== null));

        // ── 通知：僅在「首次進入終態」時觸發，重複設定同樣終態不重發 ──
        $shouldNotify = $isTerminalNew && !$isTerminalOld && $reporter !== null;
        if ($shouldNotify) {
            $useEmail = in_array($reporterStatusSnapshot, ['suspended', 'auto_suspended'], true);
            $this->ticketNotificationService->notifyTicketProcessed(
                $report->fresh(),
                $newStatus,
                $adminReply ?? '',
                $useEmail,
            );
        }

        return response()->json([
            'success' => true,
            'code' => 'TICKET_STATUS_UPDATED',
            'message' => '案件狀態已更新',
            'data' => [
                'ticket' => [
                    'id' => $report->id,
                    'status' => $report->fresh()->status,
                    'resolved_at' => $report->fresh()->resolved_at?->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/tickets/{id}/reply — add a followup message
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        Report::findOrFail($id);

        $followup = ReportFollowup::create([
            'report_id' => $id,
            'admin_id' => $request->user()->id,
            'message' => $request->input('message'),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'code' => 201,
            'message' => '回覆已送出',
            'data' => [
                'followup' => [
                    'id' => $followup->id,
                    'message' => $followup->message,
                    'created_at' => $followup->created_at->toISOString(),
                ],
            ],
        ], 201);
    }
}
