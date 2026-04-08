<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportFollowup;
use App\Models\User;
use App\Services\CreditScoreService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    /**
     * PATCH /api/v1/admin/tickets/{id}/status — resolve or dismiss a report
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $report = Report::findOrFail($id);
        $adminId = $request->user()->id;

        // Handle appeal-specific actions
        if ($report->type === 'appeal') {
            return $this->handleAppealAction($request, $report, $adminId);
        }

        $request->validate([
            'status' => 'required|in:resolved,dismissed',
            'note' => 'required|string|max:500',
        ]);

        $report = $this->reportService->resolveReport(
            $report,
            $adminId,
            $request->input('status'),
            $request->input('note'),
        );

        $reporterChange = $report->reporter_score_change ?? 0;
        $reportedChange = $request->input('status') === 'resolved' ? -5 : 0;

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '案件已結案',
            'data' => [
                'affected_scores' => [
                    'reporter_change' => $reporterChange,
                    'reported_change' => $reportedChange,
                ],
            ],
        ]);
    }

    private function handleAppealAction(Request $request, Report $report, int $adminId): JsonResponse
    {
        $action = $request->input('action', $request->input('status'));
        $user = User::find($report->reported_user_id);

        if ($action === 'approve_appeal') {
            $request->validate([
                'restore_score' => 'required|integer|min:30|max:100',
                'admin_reply' => 'required|string|max:500',
            ]);

            $restoreScore = (int) $request->input('restore_score');

            if ($user) {
                CreditScoreService::adjust($user, $restoreScore, 'appeal_approved', $request->input('admin_reply'), $adminId);
                // Observer will auto-restore if score >= 30
            }

            $report->update([
                'status' => 'resolved',
                'resolution_note' => $request->input('admin_reply'),
                'resolved_by' => $adminId,
                'resolved_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'code' => 200,
                'message' => '申訴已核准，用戶分數已補回',
                'data' => ['restore_score' => $restoreScore],
            ]);
        }

        if ($action === 'reject_appeal' || $action === 'dismissed') {
            $request->validate([
                'admin_reply' => 'required|string|max:500',
            ]);

            $report->update([
                'status' => 'dismissed',
                'resolution_note' => $request->input('admin_reply'),
                'resolved_by' => $adminId,
                'resolved_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'code' => 200,
                'message' => '申訴已駁回',
            ]);
        }

        return response()->json(['success' => false, 'message' => 'Invalid action'], 422);
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
