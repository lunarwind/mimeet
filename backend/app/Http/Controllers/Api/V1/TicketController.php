<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportFollowup;
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
        $request->validate([
            'status' => 'required|in:resolved,dismissed',
            'note' => 'required|string|max:500',
        ]);

        $report = Report::findOrFail($id);
        $adminId = $request->user()->id;

        $report = $this->reportService->resolveReport(
            $report,
            $adminId,
            $request->input('status'),
            $request->input('note'),
        );

        // Calculate affected score changes for response
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
