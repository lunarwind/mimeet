<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {}

    /**
     * POST /api/v1/reports — create a report
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'reported_user_id' => 'required|integer|exists:users,id',
            'type' => 'required|in:fake_photo,harassment,spam,scam,inappropriate,other',
            'description' => 'nullable|string|max:2000',
            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpeg,png,gif,webp|max:5120',
        ]);

        $userId = $request->user()->id;
        if ($userId === (int) $request->input('reported_user_id')) {
            return response()->json([
                'success' => false,
                'code' => 422,
                'message' => '不能檢舉自己',
            ], 422);
        }

        $report = $this->reportService->createReport(
            $request->user(),
            $request->only(['reported_user_id', 'type', 'description']),
            $request->file('images', []),
        );

        return response()->json([
            'success' => true,
            'code' => 201,
            'message' => '檢舉提交成功',
            'data' => [
                'report' => [
                    'id' => $report->id,
                    'uuid' => $report->uuid,
                    'status' => $report->status,
                    'type' => $report->type,
                    'created_at' => $report->created_at->toISOString(),
                ],
            ],
        ], 201);
    }

    /**
     * GET /api/v1/reports — list my reports
     */
    public function index(Request $request): JsonResponse
    {
        $reports = Report::where('reporter_id', $request->user()->id)
            ->with('reportedUser:id,nickname')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '檢舉列表查詢成功',
            'data' => [
                'reports' => $reports->map(fn ($r) => [
                    'id' => $r->id,
                    'uuid' => $r->uuid,
                    'type' => $r->type,
                    'status' => $r->status,
                    'reported_user' => $r->reportedUser ? [
                        'id' => $r->reportedUser->id,
                        'nickname' => $r->reportedUser->nickname,
                    ] : null,
                    'created_at' => $r->created_at->toISOString(),
                ]),
            ],
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/reports/history — alias for index (backward compat)
     */
    public function history(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * DELETE /api/v1/reports/{id} — cancel a pending report
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $report = Report::where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        $this->authorize('delete', $report);

        $report->update(['status' => 'dismissed']);

        // Refund reporter
        \App\Services\CreditScoreService::adjust($request->user(), +10, 'report_cancelled', '取消檢舉退還分數');

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '檢舉已取消',
            'data' => ['credit_score_refunded' => 10],
        ]);
    }
}
