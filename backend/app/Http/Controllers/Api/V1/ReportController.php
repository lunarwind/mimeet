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
        // v3.6: validation list 加上 system_issue (修既有 controller/service drift；
        // 同時是 SMS 驗證問題回報的承載 type，sub-category 在 service 內以 [META] 標記)
        $rules = [
            'type' => 'required|in:harassment,impersonation,scam,inappropriate,other,system_issue',
            'reported_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'description' => 'nullable|string|max:2000',
            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpeg,png,gif,webp|max:5120',
        ];
        // system_issue：description 必填 10-1000 字（SMS 驗證問題規範）
        if ($request->input('type') === 'system_issue') {
            $rules['description'] = 'required|string|min:10|max:1000';
        }
        $request->validate($rules);

        $userId = $request->user()->id;
        if ($request->input('reported_user_id') && $userId === (int) $request->input('reported_user_id')) {
            return response()->json([
                'success' => false,
                'code' => 422,
                'message' => '不能檢舉自己',
            ], 422);
        }

        try {
            $report = $this->reportService->createReport(
                $request->user(),
                $request->only(['reported_user_id', 'type', 'description']),
                $request->file('images', []),
                $request,
            );
        } catch (\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException $e) {
            return response()->json([
                'success' => false,
                'code' => 429,
                'error' => ['code' => 'TOO_MANY_REQUESTS', 'message' => $e->getMessage()],
            ], 429);
        }

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
            'meta' => [
                'page' => $reports->currentPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
                'last_page' => $reports->lastPage(),
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
        \App\Services\CreditScoreService::adjust($request->user(), \App\Services\CreditScoreService::getConfig('credit_add_report_refund', 10), 'report_result_refund', '取消檢舉退還分數');

        return response()->json([
            'success' => true,
            'code' => 200,
            'message' => '檢舉已取消',
            'data' => ['credit_score_refunded' => 10],
        ]);
    }

    public function addFollowup(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $report = Report::where('id', $id)
            ->where('reporter_id', $request->user()->id)
            ->firstOrFail();

        $followup = \App\Models\ReportFollowup::create([
            'report_id'  => $report->id,
            'message'    => $request->input('content'),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '補充說明已送出',
            'data'    => ['followup_id' => $followup->id],
        ], 201);
    }
}
