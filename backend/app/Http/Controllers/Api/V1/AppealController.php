<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppealController extends Controller
{
    public function __construct(
        private readonly AppealService $appealService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpeg,png,gif,webp|max:5120',
        ]);

        try {
            $report = $this->appealService->submitAppeal(
                $request->user(),
                $request->input('reason'),
                $request->file('images', []),
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => ['code' => $e->getMessage(), 'message' => match ($e->getMessage()) {
                    'NOT_SUSPENDED'        => '帳號目前非停權狀態',
                    'APPEAL_EXISTS'        => '此停權期間已有進行中的申訴',
                    'APPEAL_LIMIT_REACHED' => '本次停權期間已達申訴次數上限（' . AppealService::APPEAL_LIMIT_PER_SUSPENSION . ' 次）',
                    default                => $e->getMessage(),
                }],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ticket_no' => 'A' . str_pad($report->id, 9, '0', STR_PAD_LEFT),
                'message' => '申訴已送出，我們將在 3 個工作天內回覆',
            ],
        ], 201);
    }

    public function current(Request $request): JsonResponse
    {
        $appeal = $this->appealService->getCurrentAppeal($request->user());

        return response()->json([
            'success' => true,
            'data' => $appeal ? [
                'ticket_no' => 'A' . str_pad($appeal->id, 9, '0', STR_PAD_LEFT),
                'status' => $appeal->status,
                'submitted_at' => $appeal->created_at?->toISOString(),
                'admin_reply' => $appeal->resolution_note,
                'replied_at' => $appeal->resolved_at?->toISOString(),
            ] : null,
        ]);
    }
}
